import logging
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Request, status

from app.embedding.factory import create_deferred_embedder
from app.retrieval.authentication import (
    ReplayCache,
    RetrievalAuthenticationError,
    RetrievalCallerAuthenticator,
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
        plan = planner.plan(
            incoming.question,
            evaluated_at=incoming.evaluated_at.isoformat(),
        )
    except RetrievalPlanningError as exception:
        raise HTTPException(
            status.HTTP_503_SERVICE_UNAVAILABLE, "Retrieval planning failed."
        ) from exception
    return PlanResponse(request_id=incoming.request_id, plan=plan)


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
