from collections.abc import Callable
from dataclasses import dataclass
from uuid import NAMESPACE_URL, uuid5

from opentelemetry import trace
from opentelemetry.trace import SpanKind

from app.embedding.models import EmbeddingInput, EmbeddingPurpose, EmbeddingRequest
from app.embedding.protocol import Embedder
from app.retrieval.fusion import ReciprocalRankFusion
from app.retrieval.models import (
    RetrievalCandidate,
    RetrievalSide,
    SearchLineage,
    SearchRequest,
    SearchResponse,
)
from app.sparse.models import (
    SparseEncodingInput,
    SparseEncodingPurpose,
    SparseEncodingRequest,
)
from app.sparse.protocol import SparseEncoder
from app.telemetry import trace_attributes
from app.vector_store.models import (
    SparseVectorSearchRequest,
    VectorScope,
    VectorSearchRequest,
)
from app.vector_store.protocol import VectorStore


@dataclass(frozen=True)
class RetrievalStageSnapshot:
    """Optional in-process diagnostics; never changes the retrieval response."""

    side: RetrievalSide
    dense_candidates: tuple[RetrievalCandidate, ...]
    sparse_candidates: tuple[RetrievalCandidate, ...] | None
    fused_candidates: tuple[RetrievalCandidate, ...] | None


class DenseRetriever:
    def __init__(
        self,
        *,
        embedder: Embedder,
        vector_store: VectorStore,
        sparse_encoder: SparseEncoder | None = None,
        stage_observer: Callable[[RetrievalStageSnapshot], None] | None = None,
    ) -> None:
        self._embedder = embedder
        self._vector_store = vector_store
        self._sparse_encoder = sparse_encoder
        self._stage_observer = stage_observer

    def search(self, request: SearchRequest) -> SearchResponse:
        source_id = uuid5(NAMESPACE_URL, f"retrieval-query:{request.request_id}")
        embedded = self._embedder.embed(
            EmbeddingRequest(
                correlation_id=request.request_id,
                workspace_id=request.workspace_id,
                profile=request.embedding_profile,
                purpose=EmbeddingPurpose.QUERY,
                items=(EmbeddingInput(source_id=source_id, text=request.query),),
            )
        )
        if embedded.profile_fingerprint != request.embedding_profile_fingerprint:
            raise ValueError("query embedding profile is incompatible with the corpus")
        query_vector = embedded.embeddings[0].values
        sparse_query = None
        if request.is_hybrid:
            if self._sparse_encoder is None or request.sparse_embedding_profile is None:
                raise ValueError("hybrid retrieval requires a sparse encoder")
            sparse_result = self._sparse_encoder.encode(
                SparseEncodingRequest(
                    correlation_id=request.request_id,
                    workspace_id=request.workspace_id,
                    profile=request.sparse_embedding_profile,
                    purpose=SparseEncodingPurpose.QUERY,
                    items=(
                        SparseEncodingInput(source_id=source_id, text=request.query),
                    ),
                )
            )
            if sparse_result.profile_fingerprint != request.sparse_profile_fingerprint:
                raise ValueError("query sparse profile is incompatible with the corpus")
            sparse_query = sparse_result.encodings[0].vector
        candidates: list[RetrievalCandidate] = []
        for scope in request.scopes:
            vector_scope = VectorScope(
                vector_space=request.vector_space,
                workspace_id=request.workspace_id,
                workspace_corpus_generation_id=(request.workspace_corpus_generation_id),
                document_ids=scope.eligible_document_ids,
            )
            with trace.get_tracer("maketime.python.retrieval").start_as_current_span(
                "search eligible vectors",
                kind=SpanKind.INTERNAL,
                attributes=trace_attributes(
                    {
                        "rag.workspace.id": str(request.workspace_id),
                        "rag.retrieval.method": "dense",
                        "rag.retrieval.side": scope.side.value,
                        "rag.retrieval.eligible_scope_size": len(
                            scope.eligible_document_ids
                        ),
                    }
                ),
            ) as span:
                hits = self._vector_store.search(
                    VectorSearchRequest(
                        scope=vector_scope,
                        query_vector=query_vector,
                        limit=(
                            request.hybrid_configuration.dense_candidate_k
                            if request.hybrid_configuration is not None
                            else request.candidate_k
                        ),
                    )
                )
                span.set_attributes(
                    trace_attributes({"rag.retrieval.candidate_count": len(hits)})
                )
            dense_candidates = tuple(
                self._candidate(hit, rank, scope.side, "dense")
                for rank, hit in enumerate(hits, start=1)
            )
            if request.hybrid_configuration is None or sparse_query is None:
                candidates.extend(dense_candidates)
                self._observe(
                    RetrievalStageSnapshot(
                        side=scope.side,
                        dense_candidates=dense_candidates,
                        sparse_candidates=None,
                        fused_candidates=None,
                    )
                )
                continue
            with trace.get_tracer("maketime.python.retrieval").start_as_current_span(
                "search eligible sparse vectors",
                kind=SpanKind.INTERNAL,
                attributes=trace_attributes(
                    {
                        "rag.workspace.id": str(request.workspace_id),
                        "rag.retrieval.method": "sparse",
                        "rag.retrieval.side": scope.side.value,
                        "rag.retrieval.eligible_scope_size": len(
                            scope.eligible_document_ids
                        ),
                    }
                ),
            ) as sparse_span:
                sparse_hits = self._vector_store.search_sparse(
                    SparseVectorSearchRequest(
                        scope=vector_scope,
                        query_vector=sparse_query,
                        limit=request.hybrid_configuration.sparse_candidate_k,
                    )
                )
                sparse_span.set_attributes(
                    trace_attributes(
                        {"rag.retrieval.sparse_candidate_count": len(sparse_hits)}
                    )
                )
            sparse_candidates = tuple(
                self._candidate(hit, rank, scope.side, "sparse")
                for rank, hit in enumerate(sparse_hits, start=1)
            )
            with trace.get_tracer("maketime.python.retrieval").start_as_current_span(
                "fuse retrieval candidates",
                kind=SpanKind.INTERNAL,
                attributes=trace_attributes(
                    {
                        "rag.retrieval.fusion.strategy": "rrf",
                        "rag.retrieval.fusion.version": "1",
                        "rag.retrieval.fusion.rrf_k": (
                            request.hybrid_configuration.rrf_k
                        ),
                    }
                ),
            ) as fusion_span:
                fused = ReciprocalRankFusion(request.hybrid_configuration.rrf_k).fuse(
                    dense_candidates,
                    sparse_candidates,
                    limit=request.hybrid_configuration.fusion_candidate_k,
                )
                fusion_span.set_attributes(
                    trace_attributes(
                        {"rag.retrieval.fused_candidate_count": len(fused)}
                    )
                )
            candidates.extend(fused)
            self._observe(
                RetrievalStageSnapshot(
                    side=scope.side,
                    dense_candidates=dense_candidates,
                    sparse_candidates=sparse_candidates,
                    fused_candidates=fused,
                )
            )
        return SearchResponse(
            request_id=request.request_id,
            candidates=tuple(candidates),
            lineage=SearchLineage(
                embedding_profile_fingerprint=request.embedding_profile_fingerprint,
                sparse_profile_fingerprint=request.sparse_profile_fingerprint,
                sparse_space_generation_id=(
                    request.sparse_vector_space.sparse_space_generation_id
                    if request.sparse_vector_space is not None
                    else None
                ),
                fusion_strategy=(
                    request.hybrid_configuration.fusion_strategy
                    if request.hybrid_configuration is not None
                    else None
                ),
                fusion_version=(
                    request.hybrid_configuration.fusion_version
                    if request.hybrid_configuration is not None
                    else None
                ),
                rrf_k=(
                    request.hybrid_configuration.rrf_k
                    if request.hybrid_configuration is not None
                    else None
                ),
                configuration_version=(
                    request.hybrid_configuration.version
                    if request.hybrid_configuration is not None
                    else None
                ),
            ),
        )

    def _observe(self, snapshot: RetrievalStageSnapshot) -> None:
        if self._stage_observer is not None:
            self._stage_observer(snapshot)

    @staticmethod
    def _candidate(hit, rank, side, method):  # type: ignore[no-untyped-def]
        return RetrievalCandidate(
            chunk_id=hit.chunk_id,
            document_id=hit.document_id,
            workspace_corpus_generation_id=hit.workspace_corpus_generation_id,
            embedding_space_generation_id=hit.embedding_space_generation_id,
            sparse_space_generation_id=hit.sparse_space_generation_id,
            score=hit.score,
            rank=rank,
            retrieval_method=method,
            side=side,
        )
