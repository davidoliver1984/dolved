from typing import Annotated, Literal
from uuid import UUID, uuid4

from pydantic import (
    Field,
    SerializeAsAny,
    StringConstraints,
    model_validator,
)

from app.extraction.models import (
    ExtractedDocumentMetadata,
    ExtractionWarning,
    ExtractorIdentity,
    ImmutableModel,
    SourceLocation,
    TableRow,
)

NonEmptyString = Annotated[str, StringConstraints(min_length=1)]


class NormaliserIdentity(ImmutableModel):
    name: NonEmptyString
    version: NonEmptyString


class NormalisationChange(ImmutableModel):
    code: NonEmptyString
    message: NonEmptyString
    source_element_ids: tuple[UUID, ...] = Field(min_length=1)


class NormalisedElement(ImmutableModel):
    id: UUID = Field(default_factory=uuid4)
    kind: str
    text: str
    source_element_ids: tuple[UUID, ...] = Field(min_length=1)
    source_locations: tuple[SerializeAsAny[SourceLocation], ...] = Field(min_length=1)
    start_character: int = Field(ge=0)
    end_character: int = Field(ge=0)
    confidence: float | None = Field(default=None, ge=0, le=1)

    @model_validator(mode="after")
    def validate_normalised_element(self) -> NormalisedElement:
        if self.end_character < self.start_character:
            raise ValueError("end_character must not precede start_character")

        if len(set(self.source_element_ids)) != len(self.source_element_ids):
            raise ValueError("source_element_ids must be unique")

        return self


class NormalisedParagraphElement(NormalisedElement):
    kind: Literal["paragraph"] = "paragraph"


class NormalisedHeadingElement(NormalisedElement):
    kind: Literal["heading"] = "heading"
    level: int = Field(ge=1, le=9)


class NormalisedTableElement(NormalisedElement):
    kind: Literal["table"] = "table"
    rows: tuple[TableRow, ...] = Field(min_length=1)

    @model_validator(mode="after")
    def validate_table_shape(self) -> NormalisedTableElement:
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


class NormalisedUnknownElement(NormalisedElement):
    kind: Literal["unknown"] = "unknown"
    original_kind: NonEmptyString
    preserved_payload_json: NonEmptyString


class NormalisedDocument(ImmutableModel):
    workspace_id: UUID
    document_id: UUID
    source_media_type: NonEmptyString
    source_byte_size: int = Field(gt=0)
    source_extractor: ExtractorIdentity
    normaliser: NormaliserIdentity
    text: str
    elements: tuple[SerializeAsAny[NormalisedElement], ...] = ()
    extraction_warnings: tuple[ExtractionWarning, ...] = ()
    changes: tuple[NormalisationChange, ...] = ()
    metadata: ExtractedDocumentMetadata = Field(
        default_factory=ExtractedDocumentMetadata
    )
