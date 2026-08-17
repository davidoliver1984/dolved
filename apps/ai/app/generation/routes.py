from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Request, status

from app.generation.errors import (
    GenerationContextBudgetError,
    GenerationProviderFailure,
)
from app.generation.models import (
    GenerationProviderError,
    GenerationRequest,
    GenerationResponse,
    GenerationResult,
)
from app.generation.protocol import Generator
from app.retrieval.routes import authenticate
from app.settings import get_settings

router = APIRouter(
    prefix="/api/internal/retrieval/generation", tags=["internal-generation"]
)


class UnconfiguredGenerator:
    def generate(self, request: GenerationRequest) -> GenerationResult:
        raise GenerationProviderFailure(
            GenerationProviderError(
                category="contract_validation_failure",
                provider=None,
                model=None,
                attempt_count=1,
                latency_ms=0,
            )
        )


def generator_dependency() -> Generator:
    return UnconfiguredGenerator()


@router.post("/answer", response_model=GenerationResponse)
async def answer(
    request: Request,
    generator: Annotated[Generator, Depends(generator_dependency)],
) -> GenerationResponse:
    body = await authenticate(request, "generation.answer", get_settings())
    try:
        incoming = GenerationRequest.model_validate_json(body)
    except ValueError as exception:
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_CONTENT, "Invalid generation request."
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
        result = generator.generate(incoming).validate_against(incoming)
    except GenerationContextBudgetError as exception:
        return GenerationResponse(
            request_id=incoming.request_id,
            status="context_budget_exceeded",
            failure=exception.failure,
        )
    except GenerationProviderFailure as exception:
        return GenerationResponse(
            request_id=incoming.request_id,
            status="provider_error",
            error=exception.error,
        )
    except ValueError as exception:
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_CONTENT, "Invalid generation result."
        ) from exception
    return GenerationResponse(
        request_id=incoming.request_id, status="completed", result=result
    )
