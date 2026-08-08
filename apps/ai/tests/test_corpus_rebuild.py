from typing import cast
from uuid import uuid4

from app.embedding.fake import DeterministicFakeEmbedder
from app.embedding.models import EmbeddingProfile
from app.retrieval.corpus_rebuild import (
    CorpusPointIdentity,
    CorpusRebuildBatchRequest,
    CorpusRebuildChunk,
    CorpusRebuilder,
    CorpusVerificationRequest,
)
from app.sparse.fake import DeterministicSparseEncoder
from app.sparse.models import SparseEmbeddingProfile
from app.vector_store.models import (
    SparseVectorSpace,
    VectorCompletenessReport,
    VectorCompletenessRequest,
    VectorSpace,
    VectorUpsertRequest,
    VectorUpsertResult,
)
from app.vector_store.protocol import VectorStore


class RecordingVectorStore:
    def __init__(self) -> None:
        self.spaces: list[VectorSpace] = []
        self.upserts: list[VectorUpsertRequest] = []
        self.verifications: list[VectorCompletenessRequest] = []

    def ensure_vector_space(self, vector_space: VectorSpace) -> None:
        self.spaces.append(vector_space)

    def upsert(self, request: VectorUpsertRequest) -> VectorUpsertResult:
        self.upserts.append(request)
        return VectorUpsertResult(
            point_ids=tuple(point.point_id for point in request.points),
            batch_count=1,
        )

    def verify_completeness(
        self, request: VectorCompletenessRequest
    ) -> VectorCompletenessReport:
        self.verifications.append(request)
        return VectorCompletenessReport(
            expected_count=len(request.expected_points),
            actual_count=len(request.expected_points),
            vector_schema_compatible=True,
        )


def profiles() -> tuple[EmbeddingProfile, SparseEmbeddingProfile]:
    return (
        EmbeddingProfile(
            provider="test",
            model="dense",
            dimensions=3,
            output_dtype="float",
            document_input_type="document",
            query_input_type="query",
            normalisation="unit_length",
            truncation=False,
            adapter_version="1",
        ),
        SparseEmbeddingProfile(
            provider="test",
            model="sparse",
            tokenizer="test",
            max_input_tokens=100,
            adapter_version="1",
        ),
    )


def test_corpus_rebuild_computes_both_axes_under_new_generation_identities() -> None:
    dense_profile, sparse_profile = profiles()
    sparse_generation_id = uuid4()
    space = VectorSpace(
        collection_name="test",
        embedding_space_generation_id=uuid4(),
        profile_fingerprint=dense_profile.fingerprint(),
        dimensions=3,
        sparse=SparseVectorSpace(
            sparse_space_generation_id=sparse_generation_id,
            profile_fingerprint=sparse_profile.fingerprint(),
        ),
    )
    store = RecordingVectorStore()
    rebuilder = CorpusRebuilder(
        embedder=DeterministicFakeEmbedder(),
        sparse_encoder=DeterministicSparseEncoder(),
        vector_store=cast(VectorStore, store),
        batch_size=10,
    )
    workspace_id = uuid4()
    generation_id = uuid4()
    event_id = uuid4()
    chunks = tuple(
        CorpusRebuildChunk(chunk_id=uuid4(), document_id=uuid4(), text=text)
        for text in ("alpha policy", "beta procedure")
    )

    first = rebuilder.rebuild_batch(
        CorpusRebuildBatchRequest(
            request_id=uuid4(),
            workspace_id=workspace_id,
            rebuild_event_id=event_id,
            embedding_profile=dense_profile,
            sparse_embedding_profile=sparse_profile,
            vector_space=space,
            workspace_corpus_generation_id=generation_id,
            chunks=chunks,
        )
    )
    second = rebuilder.rebuild_batch(
        CorpusRebuildBatchRequest(
            request_id=uuid4(),
            workspace_id=workspace_id,
            rebuild_event_id=event_id,
            embedding_profile=dense_profile,
            sparse_embedding_profile=sparse_profile,
            vector_space=space,
            workspace_corpus_generation_id=generation_id,
            chunks=chunks,
        )
    )

    assert first.point_ids == second.point_ids
    assert len(store.upserts) == 2
    assert all(point.sparse_vector is not None for point in store.upserts[0].points)
    assert all(
        point.sparse_space_generation_id == sparse_generation_id
        for point in store.upserts[0].points
    )

    verification = rebuilder.verify(
        CorpusVerificationRequest(
            request_id=uuid4(),
            workspace_id=workspace_id,
            vector_space=space,
            workspace_corpus_generation_id=generation_id,
            points=tuple(
                CorpusPointIdentity(
                    chunk_id=chunk.chunk_id,
                    document_id=chunk.document_id,
                    event_id=event_id,
                )
                for chunk in chunks
            ),
        )
    )

    assert verification.complete
    assert verification.point_ids == first.point_ids
    assert store.verifications[0].scope.workspace_id == workspace_id
    assert store.verifications[0].scope.workspace_corpus_generation_id == generation_id
