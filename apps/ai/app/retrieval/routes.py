import logging
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Request, status
from opentelemetry import trace
from opentelemetry.trace import SpanKind

from app.embedding.factory import create_deferred_embedder
from app.reranking.errors import RerankingError
from app.reranking.factory import create_deferred_reranker
from app.reranking.models import RerankRequest, RerankResult
from app.reranking.protocol import Reranker
from app.retrieval.authentication import (
    ReplayCache,
    RetrievalAuthenticationError,
    RetrievalCallerAuthenticator,
)
from app.retrieval.corpus_rebuild import (
    CorpusRebuildBatchRequest,
    CorpusRebuildBatchResult,
    CorpusRebuilder,
    CorpusVerificationRequest,
    CorpusVerificationResult,
)
from app.retrieval.models import (
    PlanRequest,
    PlanResponse,
    SearchRequest,
    SearchResponse,
)
from app.retrieval.planner import RetrievalPlanningError, StructuredChatRetrievalPlanner
from app.retrieval.protocols import RetrievalPlanner, Retriever
from app.retrieval.retriever import DenseRetriever
from app.settings import Settings, get_settings
from app.sparse.factory import create_deferred_sparse_encoder
from app.telemetry import trace_attributes
from app.vector_store.factory import create_vector_store

router = APIRouter(prefix="/api/internal/retrieval", tags=["internal-retrieval"])
replay_cache = ReplayCache()
logger = logging.getLogger("retrieval.http")


def planner_dependency() -> RetrievalPlanner:
    settings = get_settings()
    return StructuredChatRetrievalPlanner(
        api_url=settings.retrieval_planner_api_url,
        api_key=settings.retrieval_planner_api_key,
        provider_name=settings.retrieval_planner_provider,
        model=settings.retrieval_planner_model,
        timeout_seconds=settings.retrieval_planner_timeout_seconds,
    )


def retriever_dependency() -> Retriever:
    settings = get_settings()
    return DenseRetriever(
        embedder=create_deferred_embedder(settings),
        vector_store=create_vector_store(settings),
        sparse_encoder=create_deferred_sparse_encoder(settings),
    )


def reranker_dependency() -> Reranker:
    return create_deferred_reranker(get_settings())


def corpus_rebuilder_dependency() -> CorpusRebuilder:
    settings = get_settings()
    return CorpusRebuilder(
        embedder=create_deferred_embedder(settings),
        sparse_encoder=create_deferred_sparse_encoder(settings),
        vector_store=create_vector_store(settings),
        batch_size=settings.embedding_batch_size,
    )


async def authenticate(request: Request, purpose: str, settings: Settings) -> bytes:
    if request.url.query:
        raise HTTPException(
            status.HTTP_401_UNAUTHORIZED,
            "The retrieval caller request is not authenticated.",
        )
    body = await request.body()
    if len(body) > settings.retrieval_max_body_bytes:
        raise HTTPException(
            status.HTTP_413_REQUEST_ENTITY_TOO_LARGE, "Request is too large."
        )
    authenticator = RetrievalCallerAuthenticator(
        keys=settings.retrieval_caller_hmac_keys,
        max_clock_skew_seconds=settings.retrieval_caller_max_clock_skew_seconds,
        replay_cache=replay_cache,
    )
    try:
        authenticator.verify(
            headers=request.headers,
            method=request.method,
            request_path=request.url.path,
            body=body,
            expected_purpose=purpose,
        )
    except RetrievalAuthenticationError as exception:
        logger.warning(
            "Retrieval caller authentication rejected.",
            extra={"verification_outcome": exception.reason},
        )
        raise HTTPException(
            status.HTTP_401_UNAUTHORIZED,
            "The retrieval caller request is not authenticated.",
        ) from exception
    return body


@router.post("/plan", response_model=PlanResponse)
async def plan_retrieval(
    request: Request,
    planner: Annotated[RetrievalPlanner, Depends(planner_dependency)],
) -> PlanResponse:
    settings = get_settings()
    body = await authenticate(request, "retrieval.plan", settings)
    try:
        incoming = PlanRequest.model_validate_json(body)
    except ValueError as exception:
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_CONTENT, "Invalid retrieval request."
        ) from exception
    if str(incoming.workspace_id) != request.headers.get(
        "X-Retrieval-Caller-Workspace-ID"
    ) or str(incoming.request_id) != request.headers.get(
        "X-Retrieval-Caller-Request-ID"
    ):
        raise HTTPException(
            status.HTTP_401_UNAUTHORIZED, "Signed identity does not match body."
        )
    try:
        result = planner.plan_with_observation(
            incoming.question,
            evaluated_at=incoming.evaluated_at.isoformat(),
        )
    except RetrievalPlanningError as exception:
        logger.warning(
            "Retrieval planning failed.",
            extra={
                "failure_category": exception.category,
                "provider_status": exception.provider_status,
                "systemic": exception.systemic,
            },
        )
        raise HTTPException(
            status.HTTP_503_SERVICE_UNAVAILABLE,
            {
                "code": "retrieval_planning_failed",
                "category": exception.category,
                "provider_status": exception.provider_status,
                "attempt_count": 1,
                "systemic": exception.systemic,
            },
        ) from exception
    return PlanResponse(
        request_id=incoming.request_id,
        plan=result.plan,
        classifier_lineage=result.lineage,
        usage=result.usage,
    )


@router.post("/search", response_model=SearchResponse)
async def search_retrieval(
    request: Request,
    retriever: Annotated[Retriever, Depends(retriever_dependency)],
) -> SearchResponse:
    settings = get_settings()
    body = await authenticate(request, "retrieval.search", settings)
    try:
        incoming = SearchRequest.model_validate_json(body)
    except ValueError as exception:
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_CONTENT, "Invalid retrieval request."
        ) from exception
    if str(incoming.workspace_id) != request.headers.get(
        "X-Retrieval-Caller-Workspace-ID"
    ) or str(incoming.request_id) != request.headers.get(
        "X-Retrieval-Caller-Request-ID"
    ):
        raise HTTPException(
            status.HTTP_401_UNAUTHORIZED, "Signed identity does not match body."
        )
    if incoming.candidate_k > settings.retrieval_candidate_k_max or any(
        len(scope.eligible_document_ids) > settings.retrieval_max_eligible_documents
        for scope in incoming.scopes
    ):
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_CONTENT, "Retrieval bounds exceeded."
        )
    try:
        return retriever.search(incoming)
    except Exception as exception:
        logger.warning(
            "Scoped retrieval failed.",
            extra={"error_type": type(exception).__name__},
        )
        raise HTTPException(
            status.HTTP_503_SERVICE_UNAVAILABLE, "Retrieval failed."
        ) from exception


@router.post("/rerank", response_model=RerankResult)
async def rerank_retrieval(
    request: Request,
    reranker: Annotated[Reranker, Depends(reranker_dependency)],
) -> RerankResult:
    settings = get_settings()
    body = await authenticate(request, "retrieval.rerank", settings)
    try:
        incoming = RerankRequest.model_validate_json(body)
    except ValueError as exception:
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_CONTENT, "Invalid reranking request."
        ) from exception
    if str(incoming.workspace_id) != request.headers.get(
        "X-Retrieval-Caller-Workspace-ID"
    ) or str(incoming.request_id) != request.headers.get(
        "X-Retrieval-Caller-Request-ID"
    ):
        raise HTTPException(
            status.HTTP_401_UNAUTHORIZED, "Signed identity does not match body."
        )
    if incoming.top_k > settings.retrieval_candidate_k_max:
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_CONTENT, "Reranking bounds exceeded."
        )
    try:
        with trace.get_tracer("maketime.python.retrieval").start_as_current_span(
            "rerank eligible candidates",
            kind=SpanKind.INTERNAL,
            attributes=trace_attributes(
                {
                    "rag.workspace.id": str(incoming.workspace_id),
                    "rag.retrieval.reranker.provider": incoming.profile.provider,
                    "rag.retrieval.reranker.model": incoming.profile.model,
                    "rag.retrieval.reranker.input_count": len(incoming.candidates),
                    "rag.retrieval.reranker.output_bound": incoming.top_k,
                }
            ),
        ) as span:
            result = reranker.rerank(incoming)
            span.set_attributes(
                trace_attributes(
                    {"rag.retrieval.reranker.output_count": len(result.candidates)}
                )
            )
            return result
    except RerankingError as exception:
        logger.warning(
            "Reranking failed.",
            extra={"error_type": type(exception).__name__},
        )
        raise HTTPException(
            status.HTTP_503_SERVICE_UNAVAILABLE, "Reranking failed."
        ) from exception


@router.post("/corpus/rebuild-batch", response_model=CorpusRebuildBatchResult)
async def rebuild_corpus_batch(
    request: Request,
    rebuilder: Annotated[CorpusRebuilder, Depends(corpus_rebuilder_dependency)],
) -> CorpusRebuildBatchResult:
    settings = get_settings()
    body = await authenticate(request, "retrieval.corpus.rebuild", settings)
    try:
        incoming = CorpusRebuildBatchRequest.model_validate_json(body)
    except ValueError as exception:
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_CONTENT, "Invalid corpus rebuild request."
        ) from exception
    if str(incoming.workspace_id) != request.headers.get(
        "X-Retrieval-Caller-Workspace-ID"
    ) or str(incoming.request_id) != request.headers.get(
        "X-Retrieval-Caller-Request-ID"
    ):
        raise HTTPException(
            status.HTTP_401_UNAUTHORIZED, "Signed identity does not match body."
        )
    try:
        return rebuilder.rebuild_batch(incoming)
    except Exception as exception:
        logger.warning(
            "Corpus rebuild batch failed: %s.",
            type(exception).__name__,
            extra={"error_type": type(exception).__name__},
        )
        raise HTTPException(
            status.HTTP_503_SERVICE_UNAVAILABLE, "Corpus rebuild failed."
        ) from exception


@router.post("/corpus/verify", response_model=CorpusVerificationResult)
async def verify_corpus(
    request: Request,
    rebuilder: Annotated[CorpusRebuilder, Depends(corpus_rebuilder_dependency)],
) -> CorpusVerificationResult:
    settings = get_settings()
    body = await authenticate(request, "retrieval.corpus.verify", settings)
    try:
        incoming = CorpusVerificationRequest.model_validate_json(body)
    except ValueError as exception:
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_CONTENT,
            "Invalid corpus verification request.",
        ) from exception
    if str(incoming.workspace_id) != request.headers.get(
        "X-Retrieval-Caller-Workspace-ID"
    ) or str(incoming.request_id) != request.headers.get(
        "X-Retrieval-Caller-Request-ID"
    ):
        raise HTTPException(
            status.HTTP_401_UNAUTHORIZED, "Signed identity does not match body."
        )
    try:
        return rebuilder.verify(incoming)
    except Exception as exception:
        logger.warning(
            "Corpus verification failed.",
            extra={"error_type": type(exception).__name__},
        )
        raise HTTPException(
            status.HTTP_503_SERVICE_UNAVAILABLE, "Corpus verification failed."
        ) from exception
