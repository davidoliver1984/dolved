from typing import Annotated, Literal
from uuid import UUID, uuid4

from pydantic import (
    BaseModel,
    ConfigDict,
    Field,
    SerializeAsAny,
    StringConstraints,
    model_validator,
)

NonEmptyString = Annotated[str, StringConstraints(min_length=1)]


class ImmutableModel(BaseModel):
    model_config = ConfigDict(extra="forbid", frozen=True)


class ExtractionContext(ImmutableModel):
    workspace_id: UUID
    document_id: UUID
    source_media_type: NonEmptyString


class ExtractorIdentity(ImmutableModel):
    name: NonEmptyString
    version: NonEmptyString
    parser_name: NonEmptyString | None = None
    parser_version: NonEmptyString | None = None

    @model_validator(mode="after")
    def validate_parser_identity(self) -> ExtractorIdentity:
        if (self.parser_name is None) != (self.parser_version is None):
            raise ValueError("parser_name and parser_version must be provided together")

        return self


class ExtractedDocumentMetadata(ImmutableModel):
    title: str | None = None
    author: str | None = None
    subject: str | None = None
    keywords: str | None = None
    creator: str | None = None
    producer: str | None = None
    creation_date: str | None = None
    modification_date: str | None = None


class ExtractionWarning(ImmutableModel):
    code: NonEmptyString
    message: NonEmptyString
    element_id: UUID | None = None
    source_location: SerializeAsAny[SourceLocation] | None = None


class SourceLocation(ImmutableModel):
    kind: str


class TextSourceLocation(SourceLocation):
    kind: Literal["text"] = "text"
    start_character: int = Field(ge=0)
    end_character: int = Field(ge=0)
    start_line: int = Field(ge=1)
    end_line: int = Field(ge=1)

    @model_validator(mode="after")
    def validate_ranges(self) -> TextSourceLocation:
        if self.end_character < self.start_character:
            raise ValueError("end_character must not precede start_character")

        if self.end_line < self.start_line:
            raise ValueError("end_line must not precede start_line")

        return self


class PdfSourceLocation(SourceLocation):
    """A PDF location in points from pdfplumber's displayed page top-left."""

    kind: Literal["pdf"] = "pdf"
    page_number: int = Field(ge=1)
    page_width: float = Field(gt=0)
    page_height: float = Field(gt=0)
    x0: float
    top: float
    x1: float
    bottom: float
    rotation_degrees: Literal[0, 90, 180, 270] = 0
    has_distinct_crop_box: bool = False
    start_character: int | None = Field(default=None, ge=0)
    end_character: int | None = Field(default=None, ge=0)

    @model_validator(mode="after")
    def validate_pdf_ranges(self) -> PdfSourceLocation:
        if self.x1 < self.x0:
            raise ValueError("x1 must not precede x0")

        if self.bottom < self.top:
            raise ValueError("bottom must not precede top")

        if (self.start_character is None) != (self.end_character is None):
            raise ValueError(
                "start_character and end_character must be provided together"
            )

        if (
            self.start_character is not None
            and self.end_character is not None
            and self.end_character < self.start_character
        ):
            raise ValueError("end_character must not precede start_character")

        return self


class DocxSourceLocation(SourceLocation):
    """A location in the ordered DOCX document body."""

    kind: Literal["docx"] = "docx"
    body_block_index: int = Field(ge=0)
    table_row_index: int | None = Field(default=None, ge=0)
    table_column_index: int | None = Field(default=None, ge=0)
    start_character: int | None = Field(default=None, ge=0)
    end_character: int | None = Field(default=None, ge=0)

    @model_validator(mode="after")
    def validate_docx_ranges(self) -> DocxSourceLocation:
        if (self.table_row_index is None) != (self.table_column_index is None):
            raise ValueError(
                "table_row_index and table_column_index must be provided together"
            )

        if (self.start_character is None) != (self.end_character is None):
            raise ValueError(
                "start_character and end_character must be provided together"
            )

        if (
            self.start_character is not None
            and self.end_character is not None
            and self.end_character < self.start_character
        ):
            raise ValueError("end_character must not precede start_character")

        return self


class Element(ImmutableModel):
    id: UUID = Field(default_factory=uuid4)
    kind: str
    source_location: SerializeAsAny[SourceLocation]
    confidence: float | None = Field(default=None, ge=0, le=1)


class ParagraphElement(Element):
    kind: Literal["paragraph"] = "paragraph"
    text: NonEmptyString


class HeadingElement(Element):
    kind: Literal["heading"] = "heading"
    text: NonEmptyString
    level: int = Field(ge=1, le=9)


class UnknownElement(Element):
    """Conservative representation for an element a consumer does not recognise."""

    kind: Literal["unknown"] = "unknown"
    original_kind: NonEmptyString
    preserved_text: str | None = None


class TableCell(ImmutableModel):
    row_index: int = Field(ge=0)
    column_index: int = Field(ge=0)
    text: str
    source_location: SerializeAsAny[SourceLocation] | None = None


class TableRow(ImmutableModel):
    index: int = Field(ge=0)
    cells: tuple[TableCell, ...] = Field(min_length=1)


class TableElement(Element):
    kind: Literal["table"] = "table"
    text: NonEmptyString
    rows: tuple[TableRow, ...] = Field(min_length=1)

    @model_validator(mode="after")
    def validate_table_shape(self) -> TableElement:
        column_count = len(self.rows[0].cells)

        for row_index, row in enumerate(self.rows):
            if row.index != row_index:
                raise ValueError("table row indexes must be contiguous")

            if len(row.cells) != column_count:
                raise ValueError("table rows must have equal column counts")

            for column_index, cell in enumerate(row.cells):
                if cell.row_index != row_index or cell.column_index != column_index:
                    raise ValueError("table cell indexes must match their position")

        return self


class ExtractedDocument(ImmutableModel):
    workspace_id: UUID
    document_id: UUID
    source_media_type: NonEmptyString
    source_byte_size: int = Field(gt=0)
    extractor: ExtractorIdentity
    text: NonEmptyString
    elements: tuple[SerializeAsAny[Element], ...] = Field(min_length=1)
    warnings: tuple[ExtractionWarning, ...] = ()
    metadata: ExtractedDocumentMetadata = Field(
        default_factory=ExtractedDocumentMetadata
    )
