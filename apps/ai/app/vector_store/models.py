import math
from enum import StrEnum
from typing import Annotated
from uuid import UUID

from pydantic import Field, StringConstraints, field_validator, model_validator

from app.extraction.models import ImmutableModel
from app.sparse.models import SparseVector
from app.vector_store.identity import deterministic_point_id

NonEmptyString = Annotated[str, StringConstraints(strip_whitespace=True, min_length=1)]


class VectorDistance(StrEnum):
    COSINE = "cosine"


class VectorPublicationStatus(StrEnum):
    PROVISIONAL = "provisional"
    PUBLISHED = "published"


class VectorSpace(ImmutableModel):
    collection_name: NonEmptyString
    embedding_space_generation_id: UUID
    profile_fingerprint: NonEmptyString
    vector_name: NonEmptyString = "dense"
    dimensions: int = Field(gt=0)
    distance: VectorDistance = VectorDistance.COSINE
    sparse: SparseVectorSpace | None = None


class SparseVectorSpace(ImmutableModel):
    sparse_space_generation_id: UUID
    profile_fingerprint: NonEmptyString
    vector_name: NonEmptyString = "sparse"


class VectorPointIdentity(ImmutableModel):
    workspace_id: UUID
    document_id: UUID
    chunk_id: UUID
    workspace_corpus_generation_id: UUID
    embedding_space_generation_id: UUID
    sparse_space_generation_id: UUID | None = None
    event_id: UUID
    publication_status: VectorPublicationStatus

    @property
    def point_id(self) -> UUID:
        return deterministic_point_id(
            embedding_space_generation_id=self.embedding_space_generation_id,
            workspace_id=self.workspace_id,
            workspace_corpus_generation_id=self.workspace_corpus_generation_id,
            chunk_id=self.chunk_id,
        )


class VectorPoint(VectorPointIdentity):
    values: tuple[float, ...] = Field(min_length=1)
    sparse_vector: SparseVector | None = None

    @field_validator("values")
    @classmethod
    def reject_non_finite_values(cls, values: tuple[float, ...]) -> tuple[float, ...]:
        if not all(math.isfinite(value) for value in values):
            raise ValueError("vector values must all be finite")
        return values

    def identity(self) -> VectorPointIdentity:
        return VectorPointIdentity(
            **self.model_dump(exclude={"values", "sparse_vector"})
        )


class VectorScope(ImmutableModel):
    vector_space: VectorSpace
    workspace_id: UUID
    workspace_corpus_generation_id: UUID
    document_id: UUID | None = None
    document_ids: tuple[UUID, ...] = Field(default=(), max_length=5000)
    event_id: UUID | None = None
    publication_status: VectorPublicationStatus | None = None

    @model_validator(mode="after")
    def validate_document_scope(self) -> VectorScope:
        if self.document_id is not None and self.document_ids:
            raise ValueError("document_id and document_ids are mutually exclusive")
        if len(set(self.document_ids)) != len(self.document_ids):
            raise ValueError("document_ids must be unique")
        return self


class VectorUpsertRequest(ImmutableModel):
    vector_space: VectorSpace
    points: tuple[VectorPoint, ...] = Field(min_length=1)
    batch_size: int = Field(default=64, ge=1, le=1000)

    @model_validator(mode="after")
    def validate_points(self) -> VectorUpsertRequest:
        point_ids = tuple(point.point_id for point in self.points)
        if len(set(point_ids)) != len(point_ids):
            raise ValueError("vector point identities must be unique within an upsert")
        for point in self.points:
            if (
                point.embedding_space_generation_id
                != self.vector_space.embedding_space_generation_id
            ):
                raise ValueError(
                    "point embedding-space generation does not match vector space"
                )
            if len(point.values) != self.vector_space.dimensions:
                raise ValueError("point dimensions do not match vector space")
            if self.vector_space.sparse is None:
                if (
                    point.sparse_vector is not None
                    or point.sparse_space_generation_id is not None
                ):
                    raise ValueError(
                        "dense-only vector space cannot receive sparse values"
                    )
            elif (
                point.sparse_vector is None
                or point.sparse_space_generation_id
                != self.vector_space.sparse.sparse_space_generation_id
            ):
                raise ValueError("point sparse generation does not match vector space")
        return self


class VectorUpsertResult(ImmutableModel):
    point_ids: tuple[UUID, ...] = Field(min_length=1)
    batch_count: int = Field(gt=0)


class VectorSearchRequest(ImmutableModel):
    scope: VectorScope
    query_vector: tuple[float, ...] = Field(min_length=1)
    limit: int = Field(ge=1, le=1000)

    @field_validator("query_vector")
    @classmethod
    def reject_non_finite_query(
        cls, query_vector: tuple[float, ...]
    ) -> tuple[float, ...]:
        if not all(math.isfinite(value) for value in query_vector):
            raise ValueError("query vector values must all be finite")
        return query_vector

    @model_validator(mode="after")
    def validate_dimensions(self) -> VectorSearchRequest:
        if len(self.query_vector) != self.scope.vector_space.dimensions:
            raise ValueError("query dimensions do not match vector space")
        return self


class SparseVectorSearchRequest(ImmutableModel):
    scope: VectorScope
    query_vector: SparseVector
    limit: int = Field(ge=1, le=1000)

    @model_validator(mode="after")
    def require_sparse_space(self) -> SparseVectorSearchRequest:
        if self.scope.vector_space.sparse is None:
            raise ValueError("sparse search requires an explicit sparse vector space")
        return self


class VectorSearchHit(ImmutableModel):
    point_id: UUID
    score: float
    workspace_id: UUID
    document_id: UUID
    chunk_id: UUID
    workspace_corpus_generation_id: UUID
    embedding_space_generation_id: UUID
    sparse_space_generation_id: UUID | None = None
    event_id: UUID
    publication_status: VectorPublicationStatus

    @field_validator("score")
    @classmethod
    def reject_non_finite_score(cls, score: float) -> float:
        if not math.isfinite(score):
            raise ValueError("vector search score must be finite")
        return score


class VectorCompletenessRequest(ImmutableModel):
    scope: VectorScope
    expected_points: tuple[VectorPointIdentity, ...]

    @model_validator(mode="after")
    def validate_expected_scope(self) -> VectorCompletenessRequest:
        point_ids = tuple(point.point_id for point in self.expected_points)
        if len(set(point_ids)) != len(point_ids):
            raise ValueError("expected vector point identities must be unique")
        for point in self.expected_points:
            if point.workspace_id != self.scope.workspace_id:
                raise ValueError("expected point workspace does not match scope")
            if (
                point.workspace_corpus_generation_id
                != self.scope.workspace_corpus_generation_id
            ):
                raise ValueError(
                    "expected point workspace corpus generation does not match scope"
                )
            if (
                point.embedding_space_generation_id
                != self.scope.vector_space.embedding_space_generation_id
            ):
                raise ValueError(
                    "expected point embedding-space generation does not match scope"
                )
            if (
                self.scope.document_id is not None
                and point.document_id != self.scope.document_id
            ):
                raise ValueError("expected point document does not match scope")
            if (
                self.scope.event_id is not None
                and point.event_id != self.scope.event_id
            ):
                raise ValueError("expected point event does not match scope")
            if (
                self.scope.publication_status is not None
                and point.publication_status != self.scope.publication_status
            ):
                raise ValueError(
                    "expected point publication status does not match scope"
                )
        return self


class VectorCompletenessReport(ImmutableModel):
    expected_count: int = Field(ge=0)
    actual_count: int = Field(ge=0)
    missing_point_ids: tuple[UUID, ...] = ()
    unexpected_point_ids: tuple[UUID, ...] = ()
    payload_mismatch_point_ids: tuple[UUID, ...] = ()
    vector_schema_compatible: bool

    @property
    def is_complete(self) -> bool:
        return (
            self.expected_count == self.actual_count
            and not self.missing_point_ids
            and not self.unexpected_point_ids
            and not self.payload_mismatch_point_ids
            and self.vector_schema_compatible
        )


class VectorCloneMapping(ImmutableModel):
    source_point_id: UUID
    target_point: VectorPointIdentity


class VectorCloneRequest(ImmutableModel):
    vector_space: VectorSpace
    source_scope: VectorScope
    target_scope: VectorScope
    mappings: tuple[VectorCloneMapping, ...]
    batch_size: int = Field(default=64, ge=1, le=1000)

    @model_validator(mode="after")
    def validate_mapping_identity(self) -> VectorCloneRequest:
        source_ids = tuple(mapping.source_point_id for mapping in self.mappings)
        target_ids = tuple(mapping.target_point.point_id for mapping in self.mappings)
        if len(set(source_ids)) != len(source_ids) or len(set(target_ids)) != len(
            target_ids
        ):
            raise ValueError("clone point identities must be unique")
        for mapping in self.mappings:
            point = mapping.target_point
            if (
                point.workspace_id != self.target_scope.workspace_id
                or point.document_id != self.target_scope.document_id
                or point.event_id != self.target_scope.event_id
                or point.workspace_corpus_generation_id
                != self.target_scope.workspace_corpus_generation_id
            ):
                raise ValueError("target point does not match target scope")
        return self


class VectorCloneReport(ImmutableModel):
    complete: bool
    point_ids: tuple[UUID, ...]
