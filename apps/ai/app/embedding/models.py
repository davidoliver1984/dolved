import hashlib
import json
import math
from enum import StrEnum
from typing import Annotated
from uuid import UUID

from pydantic import Field, StringConstraints, field_validator, model_validator

from app.extraction.models import ImmutableModel

NonEmptyString = Annotated[str, StringConstraints(min_length=1)]


class EmbeddingPurpose(StrEnum):
    DOCUMENT = "document"
    QUERY = "query"


class EmbeddingProfile(ImmutableModel):
    provider: NonEmptyString
    model: NonEmptyString
    dimensions: int = Field(gt=0)
    output_dtype: NonEmptyString
    document_input_type: NonEmptyString
    query_input_type: NonEmptyString
    normalisation: NonEmptyString
    truncation: bool
    model_revision: str | None = None
    adapter_version: NonEmptyString

    def fingerprint(self) -> str:
        payload = json.dumps(
            self.model_dump(mode="json"),
            ensure_ascii=False,
            separators=(",", ":"),
            sort_keys=True,
        )
        return hashlib.sha256(payload.encode()).hexdigest()

    def input_type_for(self, purpose: EmbeddingPurpose) -> str:
        if purpose is EmbeddingPurpose.DOCUMENT:
            return self.document_input_type
        return self.query_input_type


class EmbeddingInput(ImmutableModel):
    source_id: UUID
    text: NonEmptyString

    @field_validator("text")
    @classmethod
    def reject_blank_text(cls, value: str) -> str:
        if not value.strip():
            raise ValueError("embedding input text must not be blank")
        return value


class EmbeddingRequest(ImmutableModel):
    correlation_id: UUID
    workspace_id: UUID
    document_id: UUID | None = None
    profile: EmbeddingProfile
    purpose: EmbeddingPurpose
    items: tuple[EmbeddingInput, ...] = Field(min_length=1, max_length=1000)

    @model_validator(mode="after")
    def validate_source_ids(self) -> EmbeddingRequest:
        source_ids = tuple(item.source_id for item in self.items)
        if len(set(source_ids)) != len(source_ids):
            raise ValueError("embedding source IDs must be unique within a batch")
        return self


class EmbeddedVector(ImmutableModel):
    source_id: UUID
    values: tuple[float, ...] = Field(min_length=1)
    dimensions: int = Field(gt=0)

    @model_validator(mode="after")
    def validate_values(self) -> EmbeddedVector:
        if len(self.values) != self.dimensions:
            raise ValueError("embedding dimensions do not match vector length")
        if not all(math.isfinite(value) for value in self.values):
            raise ValueError("embedding values must all be finite")
        return self


class EmbeddingResult(ImmutableModel):
    profile: EmbeddingProfile
    profile_fingerprint: NonEmptyString
    purpose: EmbeddingPurpose
    embeddings: tuple[EmbeddedVector, ...] = Field(min_length=1)
    provider_input_tokens: int | None = Field(default=None, ge=0)

    @model_validator(mode="after")
    def validate_result(self) -> EmbeddingResult:
        if self.profile_fingerprint != self.profile.fingerprint():
            raise ValueError("profile_fingerprint does not match profile")
        if any(
            embedding.dimensions != self.profile.dimensions
            for embedding in self.embeddings
        ):
            raise ValueError("embedding dimensions do not match profile")
        source_ids = tuple(embedding.source_id for embedding in self.embeddings)
        if len(set(source_ids)) != len(source_ids):
            raise ValueError("embedding source IDs must be unique")
        return self


V1_VOYAGE_PROFILE = EmbeddingProfile(
    provider="voyage",
    model="voyage-4-large",
    dimensions=1024,
    output_dtype="float",
    document_input_type="document",
    query_input_type="query",
    normalisation="unit_length",
    truncation=False,
    model_revision=None,
    adapter_version="1",
)
