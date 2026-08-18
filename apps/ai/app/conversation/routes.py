from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Request, status

from app.conversation.factory import build_contextualizer
from app.conversation.models import (
    ContextualisationRequest,
    ContextualisationResponse,
    ContextualisationResult,
)
from app.conversation.openai_adapter import ContextualisationError
from app.conversation.protocol import QueryContextualizer
from app.retrieval.routes import authenticate
from app.settings import get_settings

router = APIRouter(
    prefix="/api/internal/retrieval/conversation", tags=["internal-conversation"]
)


class UnconfiguredContextualizer:
    def contextualize(
        self, request: ContextualisationRequest
    ) -> ContextualisationResult:
        raise ContextualisationError("contextualiser is unavailable")


def contextualizer_dependency() -> QueryContextualizer:
    settings = get_settings()
    try:
        return build_contextualizer(settings)
    except ValueError, ContextualisationError:
        return UnconfiguredContextualizer()


@router.post("/contextualize", response_model=ContextualisationResponse)
async def contextualize(
    request: Request,
    contextualizer: Annotated[QueryContextualizer, Depends(contextualizer_dependency)],
) -> ContextualisationResponse:
    body = await authenticate(request, "conversation.contextualize", get_settings())
    try:
        incoming = ContextualisationRequest.model_validate_json(body)
    except ValueError as exception:
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_CONTENT,
            "Invalid contextualisation request.",
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
        result = contextualizer.contextualize(incoming)
    except ContextualisationError as exception:
        raise HTTPException(
            status.HTTP_503_SERVICE_UNAVAILABLE,
            {"code": "contextualisation_failed"},
        ) from exception
    return ContextualisationResponse(request_id=incoming.request_id, result=result)
