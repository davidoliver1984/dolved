from typing import Literal
from uuid import UUID

from pydantic import Field, model_validator

from app.embedding.models import (
    EmbeddingInput,
    EmbeddingProfile,
    EmbeddingPurpose,
    EmbeddingRequest,
)
from app.embedding.protocol import Embedder
from app.extraction.models import ImmutableModel
from app.sparse.models import (
    SparseEmbeddingProfile,
    SparseEncodingInput,
    SparseEncodingPurpose,
    SparseEncodingRequest,
)
from app.sparse.protocol import SparseEncoder
from app.vector_store.models import (
    VectorCompletenessRequest,
    VectorPoint,
    VectorPointIdentity,
    VectorPublicationStatus,
    VectorScope,
    VectorSpace,
    VectorUpsertRequest,
)
from app.vector_store.protocol import VectorStore


class CorpusRebuildChunk(ImmutableModel):
    chunk_id: UUID
    document_id: UUID
    text: str = Field(min_length=1)


class CorpusRebuildBatchRequest(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    workspace_id: UUID
    rebuild_event_id: UUID
    embedding_profile: EmbeddingProfile
    sparse_embedding_profile: SparseEmbeddingProfile
    vector_space: VectorSpace
    workspace_corpus_generation_id: UUID
    chunks: tuple[CorpusRebuildChunk, ...] = Field(min_length=1, max_length=100)

    @model_validator(mode="after")
    def validate_lineage(self) -> CorpusRebuildBatchRequest:
        if (
            self.embedding_profile.fingerprint()
            != self.vector_space.profile_fingerprint
            or self.vector_space.sparse is None
            or self.sparse_embedding_profile.fingerprint()
            != self.vector_space.sparse.profile_fingerprint
        ):
            raise ValueError("corpus rebuild profile lineage does not match")
        identities = tuple(chunk.chunk_id for chunk in self.chunks)
        if len(set(identities)) != len(identities):
            raise ValueError("corpus rebuild chunk IDs must be unique")
        return self


class CorpusRebuildBatchResult(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    point_ids: tuple[UUID, ...] = Field(min_length=1)


class CorpusPointIdentity(ImmutableModel):
    chunk_id: UUID
    document_id: UUID
    event_id: UUID


class CorpusVerificationRequest(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    workspace_id: UUID
    vector_space: VectorSpace
    workspace_corpus_generation_id: UUID
    points: tuple[CorpusPointIdentity, ...] = Field(min_length=1)

    @model_validator(mode="after")
    def unique_points(self) -> CorpusVerificationRequest:
        identities = tuple(point.chunk_id for point in self.points)
        if len(set(identities)) != len(identities):
            raise ValueError("corpus verification chunk IDs must be unique")
        return self


class CorpusVerificationResult(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    complete: bool
    point_ids: tuple[UUID, ...]


class CorpusRebuilder:
    def __init__(
        self,
        *,
        embedder: Embedder,
        sparse_encoder: SparseEncoder,
        vector_store: VectorStore,
        batch_size: int,
    ) -> None:
        self._embedder = embedder
        self._sparse_encoder = sparse_encoder
        self._vector_store = vector_store
        self._batch_size = batch_size

    def rebuild_batch(
        self, request: CorpusRebuildBatchRequest
    ) -> CorpusRebuildBatchResult:
        dense = self._embedder.embed(
            EmbeddingRequest(
                correlation_id=request.request_id,
                workspace_id=request.workspace_id,
                profile=request.embedding_profile,
                purpose=EmbeddingPurpose.DOCUMENT,
                items=tuple(
                    EmbeddingInput(source_id=chunk.chunk_id, text=chunk.text)
                    for chunk in request.chunks
                ),
            )
        )
        sparse = self._sparse_encoder.encode(
            SparseEncodingRequest(
                correlation_id=request.request_id,
                workspace_id=request.workspace_id,
                profile=request.sparse_embedding_profile,
                purpose=SparseEncodingPurpose.DOCUMENT,
                items=tuple(
                    SparseEncodingInput(source_id=chunk.chunk_id, text=chunk.text)
                    for chunk in request.chunks
                ),
            )
        )
        if (
            dense.profile_fingerprint != request.vector_space.profile_fingerprint
            or request.vector_space.sparse is None
            or sparse.profile_fingerprint
            != request.vector_space.sparse.profile_fingerprint
        ):
            raise ValueError("corpus rebuild encoder lineage does not match")
        dense_by_id = {item.source_id: item for item in dense.embeddings}
        sparse_by_id = {item.source_id: item for item in sparse.encodings}
        document_by_chunk = {
            chunk.chunk_id: chunk.document_id for chunk in request.chunks
        }
        points = tuple(
            VectorPoint(
                workspace_id=request.workspace_id,
                document_id=document_by_chunk[chunk_id],
                chunk_id=chunk_id,
                workspace_corpus_generation_id=request.workspace_corpus_generation_id,
                embedding_space_generation_id=(
                    request.vector_space.embedding_space_generation_id
                ),
                sparse_space_generation_id=(
                    request.vector_space.sparse.sparse_space_generation_id
                ),
                event_id=request.rebuild_event_id,
                publication_status=VectorPublicationStatus.PUBLISHED,
                values=embedding.values,
                sparse_vector=sparse_by_id[chunk_id].vector,
            )
            for chunk_id, embedding in dense_by_id.items()
        )
        if len(points) != len(request.chunks) or len(sparse_by_id) != len(points):
            raise ValueError("corpus rebuild encoders returned incomplete identities")
        self._vector_store.ensure_vector_space(request.vector_space)
        result = self._vector_store.upsert(
            VectorUpsertRequest(
                vector_space=request.vector_space,
                points=points,
                batch_size=self._batch_size,
            )
        )
        return CorpusRebuildBatchResult(
            request_id=request.request_id,
            point_ids=result.point_ids,
        )

    def verify(self, request: CorpusVerificationRequest) -> CorpusVerificationResult:
        sparse_generation_id = (
            request.vector_space.sparse.sparse_space_generation_id
            if request.vector_space.sparse is not None
            else None
        )
        expected = tuple(
            VectorPointIdentity(
                workspace_id=request.workspace_id,
                document_id=point.document_id,
                chunk_id=point.chunk_id,
                workspace_corpus_generation_id=request.workspace_corpus_generation_id,
                embedding_space_generation_id=(
                    request.vector_space.embedding_space_generation_id
                ),
                sparse_space_generation_id=sparse_generation_id,
                event_id=point.event_id,
                publication_status=VectorPublicationStatus.PUBLISHED,
            )
            for point in request.points
        )
        report = self._vector_store.verify_completeness(
            VectorCompletenessRequest(
                scope=VectorScope(
                    vector_space=request.vector_space,
                    workspace_id=request.workspace_id,
                    workspace_corpus_generation_id=(
                        request.workspace_corpus_generation_id
                    ),
                    publication_status=VectorPublicationStatus.PUBLISHED,
                ),
                expected_points=expected,
            )
        )
        return CorpusVerificationResult(
            request_id=request.request_id,
            complete=report.is_complete,
            point_ids=tuple(point.point_id for point in expected),
        )
