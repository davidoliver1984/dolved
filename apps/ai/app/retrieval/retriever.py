import time
from collections.abc import Callable
from dataclasses import dataclass
from uuid import NAMESPACE_URL, uuid5

from opentelemetry import trace
from opentelemetry.trace import SpanKind

from app.embedding.errors import EmbeddingError
from app.embedding.models import EmbeddingInput, EmbeddingPurpose, EmbeddingRequest
from app.embedding.protocol import Embedder
from app.retrieval.failures import (
    RetrievalExecutionError,
    RetrievalFailureCategory,
    RetrievalFailureObservation,
    RetrievalFailureStage,
)
from app.retrieval.fusion import ReciprocalRankFusion
from app.retrieval.models import (
    OperationUsage,
    RetrievalCandidate,
    RetrievalSide,
    SearchLineage,
    SearchRequest,
    SearchResponse,
    SearchStageDiagnostics,
)
from app.sparse.errors import SparseEncodingError
from app.sparse.models import (
    SparseEncodingInput,
    SparseEncodingPurpose,
    SparseEncodingRequest,
)
from app.sparse.protocol import SparseEncoder
from app.telemetry import trace_attributes
from app.vector_store.errors import VectorStoreError
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
        embedding_started = time.perf_counter()
        try:
            embedded = self._embedder.embed(
                EmbeddingRequest(
                    correlation_id=request.request_id,
                    workspace_id=request.workspace_id,
                    profile=request.embedding_profile,
                    purpose=EmbeddingPurpose.QUERY,
                    items=(EmbeddingInput(source_id=source_id, text=request.query),),
                )
            )
        except Exception as exception:
            raise self._failure(
                exception,
                stage=RetrievalFailureStage.DENSE_EMBEDDING,
                execution=(
                    "local"
                    if request.embedding_profile.provider == "deterministic"
                    else "provider_api"
                ),
                provider=request.embedding_profile.provider,
                model=request.embedding_profile.model,
                started=embedding_started,
                request_attempted=not isinstance(exception, ValueError),
            ) from exception
        embedding_latency_ms = (time.perf_counter() - embedding_started) * 1000
        if embedded.profile_fingerprint != request.embedding_profile_fingerprint:
            compatibility_error = ValueError(
                "query embedding profile is incompatible with the corpus"
            )
            raise self._failure(
                compatibility_error,
                stage=RetrievalFailureStage.DENSE_EMBEDDING,
                execution="local",
                provider=request.embedding_profile.provider,
                model=request.embedding_profile.model,
                started=embedding_started,
                request_attempted=True,
            ) from compatibility_error
        query_vector = embedded.embeddings[0].values
        sparse_query = None
        sparse_latency_ms = 0.0
        if request.is_hybrid:
            if self._sparse_encoder is None or request.sparse_embedding_profile is None:
                configuration_error = ValueError(
                    "hybrid retrieval requires a sparse encoder"
                )
                raise self._failure(
                    configuration_error,
                    stage=RetrievalFailureStage.SPARSE_ENCODING,
                    execution="local",
                    provider="application",
                    model="unconfigured",
                    started=time.perf_counter(),
                    request_attempted=False,
                    usage=(
                        self._embedding_usage(request, embedded, embedding_latency_ms),
                    ),
                ) from configuration_error
            sparse_started = time.perf_counter()
            try:
                sparse_result = self._sparse_encoder.encode(
                    SparseEncodingRequest(
                        correlation_id=request.request_id,
                        workspace_id=request.workspace_id,
                        profile=request.sparse_embedding_profile,
                        purpose=SparseEncodingPurpose.QUERY,
                        items=(
                            SparseEncodingInput(
                                source_id=source_id, text=request.query
                            ),
                        ),
                    )
                )
            except Exception as exception:
                raise self._failure(
                    exception,
                    stage=RetrievalFailureStage.SPARSE_ENCODING,
                    execution="local",
                    provider=request.sparse_embedding_profile.provider,
                    model=request.sparse_embedding_profile.model,
                    started=sparse_started,
                    request_attempted=True,
                    usage=(
                        self._embedding_usage(request, embedded, embedding_latency_ms),
                    ),
                ) from exception
            sparse_latency_ms = (time.perf_counter() - sparse_started) * 1000
            if sparse_result.profile_fingerprint != request.sparse_profile_fingerprint:
                sparse_compatibility_error = ValueError(
                    "query sparse profile is incompatible with the corpus"
                )
                raise self._failure(
                    sparse_compatibility_error,
                    stage=RetrievalFailureStage.SPARSE_ENCODING,
                    execution="local",
                    provider=request.sparse_embedding_profile.provider,
                    model=request.sparse_embedding_profile.model,
                    started=sparse_started,
                    request_attempted=True,
                    usage=(
                        self._embedding_usage(request, embedded, embedding_latency_ms),
                    ),
                ) from sparse_compatibility_error
            sparse_query = sparse_result.encodings[0].vector
        candidates: list[RetrievalCandidate] = []
        diagnostics: list[SearchStageDiagnostics] = []
        dense_search_latency_ms = 0.0
        sparse_search_latency_ms = 0.0
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
                search_started = time.perf_counter()
                try:
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
                except Exception as exception:
                    raise self._failure(
                        exception,
                        stage=RetrievalFailureStage.QDRANT_DENSE_SEARCH,
                        execution="infrastructure",
                        provider="qdrant",
                        model=request.vector_space.collection_name,
                        started=search_started,
                        request_attempted=True,
                        usage=(
                            self._embedding_usage(
                                request, embedded, embedding_latency_ms
                            ),
                        ),
                        candidate_lineage_produced=bool(candidates),
                    ) from exception
                dense_search_latency_ms += (time.perf_counter() - search_started) * 1000
                span.set_attributes(
                    trace_attributes({"rag.retrieval.candidate_count": len(hits)})
                )
            dense_candidates = tuple(
                self._candidate(hit, rank, scope.side, "dense")
                for rank, hit in enumerate(hits, start=1)
            )
            if request.hybrid_configuration is None or sparse_query is None:
                candidates.extend(dense_candidates)
                if request.capture_diagnostics:
                    diagnostics.append(
                        SearchStageDiagnostics(
                            side=scope.side,
                            dense_candidates=dense_candidates,
                        )
                    )
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
                sparse_search_started = time.perf_counter()
                try:
                    sparse_hits = self._vector_store.search_sparse(
                        SparseVectorSearchRequest(
                            scope=vector_scope,
                            query_vector=sparse_query,
                            limit=request.hybrid_configuration.sparse_candidate_k,
                        )
                    )
                except Exception as exception:
                    raise self._failure(
                        exception,
                        stage=RetrievalFailureStage.QDRANT_SPARSE_SEARCH,
                        execution="infrastructure",
                        provider="qdrant",
                        model=request.vector_space.collection_name,
                        started=sparse_search_started,
                        request_attempted=True,
                        usage=(
                            self._embedding_usage(
                                request, embedded, embedding_latency_ms
                            ),
                        ),
                        candidate_lineage_produced=bool(candidates or dense_candidates),
                    ) from exception
                sparse_search_latency_ms += (
                    time.perf_counter() - sparse_search_started
                ) * 1000
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
                try:
                    fused = ReciprocalRankFusion(
                        request.hybrid_configuration.rrf_k
                    ).fuse(
                        dense_candidates,
                        sparse_candidates,
                        limit=request.hybrid_configuration.fusion_candidate_k,
                    )
                except Exception as exception:
                    raise self._failure(
                        exception,
                        stage=RetrievalFailureStage.FUSION,
                        execution="local",
                        provider="application",
                        model="rrf-v1",
                        started=sparse_search_started,
                        request_attempted=False,
                        usage=(
                            self._embedding_usage(
                                request, embedded, embedding_latency_ms
                            ),
                        ),
                        candidate_lineage_produced=bool(
                            dense_candidates or sparse_candidates
                        ),
                    ) from exception
                fusion_span.set_attributes(
                    trace_attributes(
                        {"rag.retrieval.fused_candidate_count": len(fused)}
                    )
                )
            candidates.extend(fused)
            if request.capture_diagnostics:
                diagnostics.append(
                    SearchStageDiagnostics(
                        side=scope.side,
                        dense_candidates=dense_candidates,
                        sparse_candidates=sparse_candidates,
                        fused_candidates=fused,
                    )
                )
            self._observe(
                RetrievalStageSnapshot(
                    side=scope.side,
                    dense_candidates=dense_candidates,
                    sparse_candidates=sparse_candidates,
                    fused_candidates=fused,
                )
            )
        usage = [
            OperationUsage(
                stage="dense_embedding",
                provider=request.embedding_profile.provider,
                model=request.embedding_profile.model,
                execution=(
                    "local"
                    if request.embedding_profile.provider == "deterministic"
                    else "provider_api"
                ),
                request_count=embedded.provider_attempt_count,
                retry_count=embedded.provider_retry_count,
                provider_attempt_count=embedded.provider_attempt_count,
                provider_retry_count=embedded.provider_retry_count,
                rate_limit_event_count=embedded.rate_limit_event_count,
                retry_delays=embedded.retry_delays,
                first_provider_attempt_at=embedded.first_provider_attempt_at,
                final_provider_success_at=embedded.final_provider_success_at,
                provider_retry_elapsed_ms=(
                    embedded.provider_retry_elapsed_seconds * 1000
                ),
                input_tokens=embedded.provider_input_tokens,
                latency_ms=embedding_latency_ms,
                cost_basis=(
                    "zero_cost_local"
                    if request.embedding_profile.provider == "deterministic"
                    else "estimated"
                    if embedded.estimated_cost_usd is not None
                    else "unavailable"
                ),
                cost_usd=(
                    0
                    if request.embedding_profile.provider == "deterministic"
                    else embedded.estimated_cost_usd
                ),
                pricing_snapshot=embedded.pricing_snapshot,
            ),
            OperationUsage(
                stage="qdrant_dense_search",
                provider="qdrant",
                model=request.vector_space.collection_name,
                execution="infrastructure",
                request_count=len(request.scopes),
                retry_count=0,
                latency_ms=dense_search_latency_ms,
                cost_basis="unavailable",
            ),
        ]
        if request.is_hybrid and request.sparse_embedding_profile is not None:
            usage.extend(
                [
                    OperationUsage(
                        stage="sparse_encoding",
                        provider=request.sparse_embedding_profile.provider,
                        model=request.sparse_embedding_profile.model,
                        execution="local",
                        request_count=1,
                        retry_count=0,
                        latency_ms=sparse_latency_ms,
                        cost_basis="zero_cost_local",
                        cost_usd=0,
                    ),
                    OperationUsage(
                        stage="qdrant_sparse_search",
                        provider="qdrant",
                        model=request.vector_space.collection_name,
                        execution="infrastructure",
                        request_count=len(request.scopes),
                        retry_count=0,
                        latency_ms=sparse_search_latency_ms,
                        cost_basis="unavailable",
                    ),
                ]
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
            diagnostics=tuple(diagnostics),
            usage=tuple(usage),
        )

    @staticmethod
    def _embedding_usage(request, embedded, latency_ms):  # type: ignore[no-untyped-def]
        return OperationUsage(
            stage="dense_embedding",
            provider=request.embedding_profile.provider,
            model=request.embedding_profile.model,
            execution=(
                "local"
                if request.embedding_profile.provider == "deterministic"
                else "provider_api"
            ),
            request_count=embedded.provider_attempt_count,
            retry_count=embedded.provider_retry_count,
            provider_attempt_count=embedded.provider_attempt_count,
            provider_retry_count=embedded.provider_retry_count,
            rate_limit_event_count=embedded.rate_limit_event_count,
            retry_delays=embedded.retry_delays,
            first_provider_attempt_at=embedded.first_provider_attempt_at,
            final_provider_success_at=embedded.final_provider_success_at,
            provider_retry_elapsed_ms=embedded.provider_retry_elapsed_seconds * 1000,
            input_tokens=embedded.provider_input_tokens,
            latency_ms=latency_ms,
            cost_basis=(
                "zero_cost_local"
                if request.embedding_profile.provider == "deterministic"
                else "estimated"
                if embedded.estimated_cost_usd is not None
                else "unavailable"
            ),
            cost_usd=(
                0
                if request.embedding_profile.provider == "deterministic"
                else embedded.estimated_cost_usd
            ),
            pricing_snapshot=embedded.pricing_snapshot,
        )

    @staticmethod
    def _failure(
        exception: Exception,
        *,
        stage: RetrievalFailureStage,
        execution: str,
        provider: str,
        model: str,
        started: float,
        request_attempted: bool,
        usage: tuple[OperationUsage, ...] = (),
        candidate_lineage_produced: bool = False,
    ) -> RetrievalExecutionError:
        code = getattr(exception, "code", "unknown")
        category = {
            "timeout": RetrievalFailureCategory.TIMEOUT,
            "rate_limited": RetrievalFailureCategory.RATE_LIMITED,
            "malformed_response": RetrievalFailureCategory.INVALID_PROVIDER_RESPONSE,
            "invalid_input": RetrievalFailureCategory.CONTRACT_VALIDATION_ERROR,
            "input_too_large": RetrievalFailureCategory.CONTRACT_VALIDATION_ERROR,
            "unavailable": RetrievalFailureCategory.INFRASTRUCTURE_ERROR,
            "provider_unavailable": RetrievalFailureCategory.CONNECTION_ERROR,
        }.get(code)
        if category is None:
            if isinstance(exception, VectorStoreError):
                category = RetrievalFailureCategory.INFRASTRUCTURE_ERROR
            elif isinstance(exception, SparseEncodingError):
                category = RetrievalFailureCategory.LOCAL_EXECUTION_ERROR
            elif isinstance(exception, EmbeddingError):
                category = RetrievalFailureCategory.PROVIDER_HTTP_ERROR
            else:
                category = RetrievalFailureCategory.LOCAL_EXECUTION_ERROR
        attempts = getattr(exception, "attempts", None)
        return RetrievalExecutionError(
            RetrievalFailureObservation(
                stage=stage,
                execution=execution,
                provider=provider,
                model=model,
                category=category,
                http_status=getattr(exception, "provider_status", None),
                retry_count=max(0, attempts - 1) if isinstance(attempts, int) else None,
                provider_retry_count=(
                    max(0, attempts - 1) if isinstance(attempts, int) else None
                ),
                outer_retry_count=0,
                request_count=attempts
                if isinstance(attempts, int)
                else (1 if request_attempted else 0),
                first_failure_at=getattr(exception, "first_failure_at", None),
                final_failure_at=getattr(exception, "final_failure_at", None),
                retry_delay_ms=(
                    getattr(exception, "total_retry_delay_seconds", 0.0) * 1000
                ),
                provider_retry_after_seconds=getattr(
                    exception, "retry_after_seconds", None
                ),
                provider_timing_source=getattr(
                    exception, "provider_timing_source", None
                ),
                latency_ms=(time.perf_counter() - started) * 1000,
                usage=usage,
                downstream_request_attempted=request_attempted,
                candidate_lineage_produced=candidate_lineage_produced,
            )
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
