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


class ExtractionWarning(ImmutableModel):
    code: NonEmptyString
    message: NonEmptyString
    element_id: UUID | None = None


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


class Element(ImmutableModel):
    id: UUID = Field(default_factory=uuid4)
    kind: str
    source_location: SerializeAsAny[SourceLocation]
    confidence: float | None = Field(default=None, ge=0, le=1)


class ParagraphElement(Element):
    kind: Literal["paragraph"] = "paragraph"
    text: NonEmptyString


class UnknownElement(Element):
    """Conservative representation for an element a consumer does not recognise."""

    kind: Literal["unknown"] = "unknown"
    original_kind: NonEmptyString
    preserved_text: str | None = None


class ExtractedDocument(ImmutableModel):
    workspace_id: UUID
    document_id: UUID
    source_media_type: NonEmptyString
    source_byte_size: int = Field(gt=0)
    extractor: ExtractorIdentity
    text: NonEmptyString
    elements: tuple[SerializeAsAny[Element], ...] = Field(min_length=1)
    warnings: tuple[ExtractionWarning, ...] = ()
