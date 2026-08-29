import boto3  # type: ignore[import-untyped]
from fastapi import APIRouter, HTTPException, Request, status

from app.content_clone.models import (
    ContentCloneCleanupRequest,
    ContentCloneCleanupResult,
    ContentCloneVectorRequest,
    ContentCloneVectorResult,
)
from app.content_clone.orchestrator import (
    ContentCloneContractError,
    ContentCloneOrchestrator,
)
from app.retrieval.routes import authenticate
from app.settings import get_settings
from app.vector_store.factory import create_vector_store

router = APIRouter(prefix="/api/internal/content-clone", tags=["content-clone"])


def orchestrator_dependency() -> ContentCloneOrchestrator:
    settings = get_settings()
    return ContentCloneOrchestrator(
        object_store=boto3.client(
            "s3",
            region_name=settings.aws_default_region,
            endpoint_url=settings.aws_endpoint_url,
        ),
        vector_store=create_vector_store(settings),
        batch_size=settings.embedding_batch_size,
    )


@router.post("/vector-copy", response_model=ContentCloneVectorResult)
async def vector_copy(request: Request) -> ContentCloneVectorResult:
    settings = get_settings()
    body = await authenticate(request, "content.clone", settings)
    try:
        incoming = ContentCloneVectorRequest.model_validate_json(body)
        if str(incoming.workspace_id) != request.headers.get(
            "X-Retrieval-Caller-Workspace-ID"
        ) or str(incoming.request_id) != request.headers.get(
            "X-Retrieval-Caller-Request-ID"
        ):
            raise ContentCloneContractError("signed identity does not match body")
        complete, count, digest = orchestrator_dependency().clone(incoming)
    except (ValueError, ContentCloneContractError) as exception:
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_CONTENT,
            "Invalid content-clone request.",
        ) from exception
    return ContentCloneVectorResult(
        request_id=incoming.request_id,
        complete=complete,
        point_count=count,
        point_manifest_digest=digest,
    )


@router.post("/vector-cleanup", response_model=ContentCloneCleanupResult)
async def vector_cleanup(request: Request) -> ContentCloneCleanupResult:
    settings = get_settings()
    body = await authenticate(request, "content.clone.cleanup", settings)
    try:
        incoming = ContentCloneCleanupRequest.model_validate_json(body)
        if str(incoming.workspace_id) != request.headers.get(
            "X-Retrieval-Caller-Workspace-ID"
        ) or str(incoming.request_id) != request.headers.get(
            "X-Retrieval-Caller-Request-ID"
        ):
            raise ContentCloneContractError("signed identity does not match body")
        absent = orchestrator_dependency().cleanup(incoming)
    except (ValueError, ContentCloneContractError) as exception:
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_CONTENT,
            "Invalid content-clone cleanup request.",
        ) from exception
    return ContentCloneCleanupResult(request_id=incoming.request_id, absent=absent)
