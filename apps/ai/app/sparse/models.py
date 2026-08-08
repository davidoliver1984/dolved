import hashlib
import json
import math
from enum import StrEnum
from typing import Annotated
from uuid import UUID

from pydantic import Field, StringConstraints, field_validator, model_validator

from app.extraction.models import ImmutableModel

NonEmptyString = Annotated[str, StringConstraints(strip_whitespace=True, min_length=1)]


class SparseEncodingPurpose(StrEnum):
    DOCUMENT = "document"
    QUERY = "query"


class SparseEmbeddingProfile(ImmutableModel):
    provider: NonEmptyString
    model: NonEmptyString
    tokenizer: NonEmptyString
    tokenizer_revision: str | None = None
    output_representation: NonEmptyString = "sparse-index-weight"
    max_input_tokens: int = Field(gt=0)
    document_input_type: NonEmptyString = "document"
    query_input_type: NonEmptyString = "query"
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


class SparseEncodingInput(ImmutableModel):
    source_id: UUID
    text: NonEmptyString

    @field_validator("text")
    @classmethod
    def reject_blank_text(cls, value: str) -> str:
        if not value.strip():
            raise ValueError("sparse encoding input text must not be blank")
        return value


class SparseEncodingRequest(ImmutableModel):
    correlation_id: UUID
    workspace_id: UUID
    document_id: UUID | None = None
    profile: SparseEmbeddingProfile
    purpose: SparseEncodingPurpose
    items: tuple[SparseEncodingInput, ...] = Field(min_length=1, max_length=1000)

    @model_validator(mode="after")
    def validate_source_ids(self) -> SparseEncodingRequest:
        source_ids = tuple(item.source_id for item in self.items)
        if len(set(source_ids)) != len(source_ids):
            raise ValueError("sparse encoding source IDs must be unique")
        return self


class SparseVector(ImmutableModel):
    indices: tuple[int, ...] = Field(min_length=1)
    values: tuple[float, ...] = Field(min_length=1)

    @model_validator(mode="after")
    def validate_values(self) -> SparseVector:
        if len(self.indices) != len(self.values):
            raise ValueError("sparse indices and values must have equal length")
        if len(set(self.indices)) != len(self.indices) or any(
            index < 0 for index in self.indices
        ):
            raise ValueError("sparse indices must be unique non-negative integers")
        if not all(math.isfinite(value) and value != 0 for value in self.values):
            raise ValueError("sparse values must be finite and non-zero")
        return self


class SparseEncodedVector(ImmutableModel):
    source_id: UUID
    vector: SparseVector


class SparseEncodingResult(ImmutableModel):
    profile: SparseEmbeddingProfile
    profile_fingerprint: NonEmptyString
    purpose: SparseEncodingPurpose
    encodings: tuple[SparseEncodedVector, ...] = Field(min_length=1)

    @model_validator(mode="after")
    def validate_result(self) -> SparseEncodingResult:
        if self.profile_fingerprint != self.profile.fingerprint():
            raise ValueError("sparse profile fingerprint does not match profile")
        source_ids = tuple(item.source_id for item in self.encodings)
        if len(set(source_ids)) != len(source_ids):
            raise ValueError("sparse encoding source IDs must be unique")
        return self


V1_SPLADE_PROFILE = SparseEmbeddingProfile(
    provider="fastembed",
    model="prithivida/Splade_PP_en_v1",
    tokenizer="bert-base-uncased",
    tokenizer_revision=None,
    output_representation="sparse-index-weight",
    max_input_tokens=512,
    document_input_type="document",
    query_input_type="query",
    model_revision=None,
    adapter_version="1",
)
