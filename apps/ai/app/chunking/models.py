import hashlib
import json
from typing import Annotated, Literal
from uuid import UUID

from pydantic import Field, SerializeAsAny, StringConstraints, model_validator

from app.extraction.models import ImmutableModel, SourceLocation

NonEmptyString = Annotated[str, StringConstraints(min_length=1)]


class TokenizerIdentity(ImmutableModel):
    library: NonEmptyString
    library_version: NonEmptyString
    encoding: NonEmptyString
    vocabulary_fingerprint: NonEmptyString


class BaselineChunkingConfiguration(ImmutableModel):
    tokenizer: TokenizerIdentity
    target_tokens: int = Field(default=400, gt=0)
    max_tokens: int = Field(default=512, gt=0)
    overlap_tokens: int = Field(default=64, ge=0)
    preferred_min_tokens: int = Field(default=100, ge=0)

    @model_validator(mode="after")
    def validate_token_limits(self) -> BaselineChunkingConfiguration:
        if self.target_tokens > self.max_tokens:
            raise ValueError("target_tokens must not exceed max_tokens")
        if self.overlap_tokens >= self.target_tokens:
            raise ValueError("overlap_tokens must be less than target_tokens")
        if self.preferred_min_tokens > self.target_tokens:
            raise ValueError("preferred_min_tokens must not exceed target_tokens")
        return self

    def fingerprint(self) -> str:
        payload = json.dumps(
            self.model_dump(mode="json"),
            ensure_ascii=False,
            separators=(",", ":"),
            sort_keys=True,
        )
        return hashlib.sha256(payload.encode()).hexdigest()


class ChunkContribution(ImmutableModel):
    normalised_element_id: UUID
    source_element_ids: tuple[UUID, ...] = Field(min_length=1)
    source_locations: tuple[SerializeAsAny[SourceLocation], ...] = Field(min_length=1)
    element_start_character: int = Field(ge=0)
    element_end_character: int = Field(ge=0)
    chunk_start_character: int = Field(ge=0)
    chunk_end_character: int = Field(ge=0)
    role: Literal["primary", "overlap"]

    @model_validator(mode="after")
    def validate_ranges(self) -> ChunkContribution:
        if self.element_end_character <= self.element_start_character:
            raise ValueError("element contribution must contain text")
        if self.chunk_end_character <= self.chunk_start_character:
            raise ValueError("chunk contribution must contain text")
        return self


class Chunk(ImmutableModel):
    id: UUID
    ordinal: int = Field(ge=0)
    text: NonEmptyString
    token_count: int = Field(gt=0)
    contributions: tuple[ChunkContribution, ...] = Field(min_length=1)


class ChunkingWarning(ImmutableModel):
    code: NonEmptyString
    message: NonEmptyString
    normalised_element_id: UUID | None = None
    chunk_ordinal: int | None = Field(default=None, ge=0)


class ChunkingResult(ImmutableModel):
    workspace_id: UUID
    document_id: UUID
    strategy_name: NonEmptyString
    strategy_version: NonEmptyString
    configuration: BaselineChunkingConfiguration
    configuration_fingerprint: NonEmptyString
    chunks: tuple[Chunk, ...]
    warnings: tuple[ChunkingWarning, ...] = ()

    @model_validator(mode="after")
    def validate_result(self) -> ChunkingResult:
        if self.configuration_fingerprint != self.configuration.fingerprint():
            raise ValueError("configuration_fingerprint does not match configuration")
        if any(chunk.ordinal != index for index, chunk in enumerate(self.chunks)):
            raise ValueError("chunk ordinals must be contiguous")
        return self
