from typing import cast
from uuid import uuid4

import pytest
from pydantic import ValidationError

from app.embedding.fake import DeterministicFakeEmbedder
from app.embedding.models import V1_VOYAGE_PROFILE
from app.retrieval.models import (
    HybridRetrievalConfiguration,
    RetrievalPlan,
    RetrievalSide,
    SearchRequest,
    SearchScope,
    TemporalAnchor,
    TemporalAnchorKind,
    TemporalMode,
)
from app.retrieval.retriever import DenseRetriever, RetrievalStageSnapshot
from app.sparse.fake import DeterministicSparseEncoder
from app.sparse.models import SparseEmbeddingProfile
from app.vector_store.models import (
    SparseVectorSpace,
    VectorPublicationStatus,
    VectorSearchHit,
    VectorSearchRequest,
    VectorSpace,
)
from app.vector_store.protocol import VectorStore


class RecordingVectorStore:
    def __init__(self) -> None:
        self.requests: list[VectorSearchRequest] = []

    def search(self, request):
        self.requests.append(request)
        document_id = request.scope.document_ids[0]
        return (
            VectorSearchHit(
                point_id=uuid4(),
                score=0.75,
                workspace_id=request.scope.workspace_id,
                document_id=document_id,
                chunk_id=uuid4(),
                workspace_corpus_generation_id=request.scope.workspace_corpus_generation_id,
                embedding_space_generation_id=request.scope.vector_space.embedding_space_generation_id,
                event_id=uuid4(),
                publication_status=VectorPublicationStatus.PUBLISHED,
            ),
        )

    def search_sparse(self, request):
        document_id = request.scope.document_ids[0]
        return (
            VectorSearchHit(
                point_id=uuid4(),
                score=4.25,
                workspace_id=request.scope.workspace_id,
                document_id=document_id,
                chunk_id=uuid4(),
                workspace_corpus_generation_id=request.scope.workspace_corpus_generation_id,
                embedding_space_generation_id=request.scope.vector_space.embedding_space_generation_id,
                sparse_space_generation_id=request.scope.vector_space.sparse.sparse_space_generation_id,
                event_id=uuid4(),
                publication_status=VectorPublicationStatus.PUBLISHED,
            ),
        )


def test_retrieval_plan_enforces_typed_temporal_shapes() -> None:
    current = RetrievalPlan(
        retrieval_queries=("What is current?",),
        temporal_mode=TemporalMode.CURRENT,
    )
    compare = RetrievalPlan(
        retrieval_queries=("What changed?",),
        temporal_mode=TemporalMode.COMPARE,
        primary_anchor=TemporalAnchor(kind=TemporalAnchorKind.CURRENT),
        comparison_anchor=TemporalAnchor(kind=TemporalAnchorKind.PREVIOUS),
    )
    assert current.temporal_mode is TemporalMode.CURRENT
    assert compare.comparison_anchor is not None

    with pytest.raises(ValidationError, match="valid_at"):
        RetrievalPlan(
            retrieval_queries=("Old policy",),
            temporal_mode=TemporalMode.VALID_AT_DATE,
        )


def test_dense_retriever_uses_query_profile_and_keeps_compare_sides_separate() -> None:
    workspace_id = uuid4()
    generation_id = uuid4()
    embedding_generation_id = uuid4()
    primary_document = uuid4()
    comparison_document = uuid4()
    vector_space = VectorSpace(
        collection_name="test",
        embedding_space_generation_id=embedding_generation_id,
        profile_fingerprint=V1_VOYAGE_PROFILE.fingerprint(),
        dimensions=V1_VOYAGE_PROFILE.dimensions,
    )
    store = RecordingVectorStore()
    embedder = DeterministicFakeEmbedder()
    request = SearchRequest(
        contract_version=1,
        request_id=uuid4(),
        workspace_id=workspace_id,
        query="What changed?",
        embedding_profile=V1_VOYAGE_PROFILE,
        embedding_profile_fingerprint=V1_VOYAGE_PROFILE.fingerprint(),
        vector_space=vector_space,
        workspace_corpus_generation_id=generation_id,
        candidate_k=5,
        scopes=(
            SearchScope(
                side=RetrievalSide.PRIMARY, eligible_document_ids=(primary_document,)
            ),
            SearchScope(
                side=RetrievalSide.COMPARISON,
                eligible_document_ids=(comparison_document,),
            ),
        ),
    )

    result = DenseRetriever(
        embedder=embedder,
        vector_store=cast(VectorStore, store),
    ).search(request)

    assert len(store.requests) == 2
    assert store.requests[0].scope.document_ids == (primary_document,)
    assert store.requests[1].scope.document_ids == (comparison_document,)
    assert [candidate.side for candidate in result.candidates] == [
        RetrievalSide.PRIMARY,
        RetrievalSide.COMPARISON,
    ]
    assert embedder.requests[0].purpose.value == "query"


def test_search_contract_rejects_profile_lineage_mismatch() -> None:
    profile = V1_VOYAGE_PROFILE
    with pytest.raises(ValidationError, match="fingerprint"):
        SearchRequest(
            contract_version=1,
            request_id=uuid4(),
            workspace_id=uuid4(),
            query="Question",
            embedding_profile=profile,
            embedding_profile_fingerprint="0" * 64,
            vector_space=VectorSpace(
                collection_name="test",
                embedding_space_generation_id=uuid4(),
                profile_fingerprint="0" * 64,
                dimensions=profile.dimensions,
            ),
            workspace_corpus_generation_id=uuid4(),
            candidate_k=5,
            scopes=(SearchScope(eligible_document_ids=(uuid4(),)),),
        )


def test_hybrid_retriever_fuses_compare_sides_independently_and_reports_lineage() -> (
    None
):
    snapshots: list[RetrievalStageSnapshot] = []
    sparse_profile = SparseEmbeddingProfile(
        provider="test",
        model="deterministic-sparse",
        tokenizer="test",
        max_input_tokens=100,
        adapter_version="1",
    )
    sparse_space = SparseVectorSpace(
        sparse_space_generation_id=uuid4(),
        profile_fingerprint=sparse_profile.fingerprint(),
    )
    vector_space = VectorSpace(
        collection_name="test",
        embedding_space_generation_id=uuid4(),
        profile_fingerprint=V1_VOYAGE_PROFILE.fingerprint(),
        dimensions=V1_VOYAGE_PROFILE.dimensions,
        sparse=sparse_space,
    )
    request = SearchRequest(
        contract_version=1,
        request_id=uuid4(),
        workspace_id=uuid4(),
        query="What changed?",
        embedding_profile=V1_VOYAGE_PROFILE,
        embedding_profile_fingerprint=V1_VOYAGE_PROFILE.fingerprint(),
        vector_space=vector_space,
        workspace_corpus_generation_id=uuid4(),
        candidate_k=4,
        sparse_embedding_profile=sparse_profile,
        sparse_profile_fingerprint=sparse_profile.fingerprint(),
        sparse_vector_space=sparse_space,
        hybrid_configuration=HybridRetrievalConfiguration(
            version="test-v1",
            dense_candidate_k=4,
            sparse_candidate_k=4,
            fusion_candidate_k=4,
            reranker_candidate_k=2,
            final_evidence_k=1,
            rrf_k=60,
        ),
        scopes=(
            SearchScope(side=RetrievalSide.PRIMARY, eligible_document_ids=(uuid4(),)),
            SearchScope(
                side=RetrievalSide.COMPARISON,
                eligible_document_ids=(uuid4(),),
            ),
        ),
    )

    result = DenseRetriever(
        embedder=DeterministicFakeEmbedder(),
        sparse_encoder=DeterministicSparseEncoder(),
        vector_store=cast(VectorStore, RecordingVectorStore()),
        stage_observer=snapshots.append,
    ).search(request)

    assert [candidate.side for candidate in result.candidates] == [
        RetrievalSide.PRIMARY,
        RetrievalSide.PRIMARY,
        RetrievalSide.COMPARISON,
        RetrievalSide.COMPARISON,
    ]
    assert all(
        candidate.retrieval_method == "hybrid" for candidate in result.candidates
    )
    assert result.lineage.sparse_profile_fingerprint == sparse_profile.fingerprint()
    assert (
        result.lineage.sparse_space_generation_id
        == sparse_space.sparse_space_generation_id
    )
    assert result.lineage.fusion_strategy == "rrf"
    assert [snapshot.side for snapshot in snapshots] == [
        RetrievalSide.PRIMARY,
        RetrievalSide.COMPARISON,
    ]
    assert all(len(snapshot.dense_candidates) == 1 for snapshot in snapshots)
    assert all(
        snapshot.sparse_candidates is not None and len(snapshot.sparse_candidates) == 1
        for snapshot in snapshots
    )
    assert all(
        snapshot.fused_candidates is not None and len(snapshot.fused_candidates) == 2
        for snapshot in snapshots
    )
