from collections.abc import Iterator
from typing import Annotated, cast

from fastapi import APIRouter, Depends, HTTPException, Request, status
from fastapi.responses import JSONResponse, StreamingResponse

from app.generation.errors import (
    GenerationContextBudgetError,
    GenerationProviderFailure,
)
from app.generation.factory import build_generator, build_needs_generator
from app.generation.models import (
    GenerationProviderError,
    GenerationRequest,
    GenerationResponse,
    GenerationResult,
    GenerationStreamEvent,
)
from app.generation.needs_output import NEEDS_RESPONSE
from app.generation.needs_request import GenerationNeedsRequest
from app.generation.openai_adapter import OpenAIGenerator
from app.generation.protocol import Generator, StreamingGenerator
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
    settings = get_settings()
    if (
        settings.generation_provider == "openai"
        and not settings.generation_openai_api_key.get_secret_value().strip()
    ):
        return UnconfiguredGenerator()
    try:
        return build_generator(settings)
    except ValueError:
        return UnconfiguredGenerator()


def needs_generator_dependency() -> OpenAIGenerator:
    try:
        return build_needs_generator(get_settings())
    except (ValueError, TypeError) as exception:
        raise HTTPException(503, "Needs generation is not configured.") from exception


def needs_response(payload: dict[str, object]) -> JSONResponse:
    return JSONResponse(NEEDS_RESPONSE.validate_python(payload).model_dump(mode="json"))


@router.post("/answer-needs")
async def answer_needs(
    request: Request,
    generator: Annotated[OpenAIGenerator, Depends(needs_generator_dependency)],
) -> JSONResponse:
    body = await authenticate(request, "generation.answer-needs", get_settings())
    try:
        incoming = GenerationNeedsRequest.model_validate_json(body)
    except ValueError as exception:
        raise HTTPException(422, "Invalid needs generation request.") from exception
    if str(incoming.workspace_id) != request.headers.get(
        "X-Retrieval-Caller-Workspace-ID"
    ) or str(incoming.request_id) != request.headers.get(
        "X-Retrieval-Caller-Request-ID"
    ):
        raise HTTPException(401, "Signed identity does not match body.")
    envelope = {"contract_version": 2, "request_id": str(incoming.request_id)}
    try:
        result = generator.generate_needs(incoming).validate_for_needs_request(incoming)
    except GenerationContextBudgetError as exception:
        return needs_response(
            {
                **envelope,
                "status": "context_budget_exceeded",
                "failure": exception.failure.model_dump(mode="json"),
            }
        )
    except GenerationProviderFailure as exception:
        return needs_response(
            {
                **envelope,
                "status": "provider_error",
                "error": exception.error.model_dump(mode="json"),
            }
        )
    except ValueError as exception:
        raise HTTPException(422, "Invalid needs generation result.") from exception
    return needs_response(
        {**envelope, "status": "completed", "result": result.model_dump(mode="json")}
    )


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


@router.post("/stream", response_class=StreamingResponse)
async def stream(
    request: Request,
    generator: Annotated[Generator, Depends(generator_dependency)],
) -> StreamingResponse:
    body = await authenticate(request, "generation.stream", get_settings())
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

    def framed_events() -> Iterator[bytes]:
        sequence = 1
        try:
            streaming = cast(StreamingGenerator, generator)
            for event in streaming.stream(incoming):
                sequence = event.sequence + 1
                yield (event.model_dump_json() + "\n").encode()
        except GenerationContextBudgetError as exception:
            event = GenerationStreamEvent(
                request_id=incoming.request_id,
                sequence=sequence,
                event_type="context_budget_exceeded",
                failure=exception.failure,
            )
            yield (event.model_dump_json() + "\n").encode()
        except GenerationProviderFailure as exception:
            event = GenerationStreamEvent(
                request_id=incoming.request_id,
                sequence=sequence,
                event_type="generation_failed",
                error=exception.error,
            )
            yield (event.model_dump_json() + "\n").encode()
        except AttributeError, TypeError, ValueError:
            # Do not expose provider payloads or exception text over this boundary.
            event = GenerationStreamEvent.model_validate(
                {
                    "request_id": incoming.request_id,
                    "sequence": sequence,
                    "event_type": "generation_failed",
                    "error": {
                        "category": "contract_validation_failure",
                        "provider": None,
                        "model": None,
                        "http_status": None,
                        "attempt_count": 1,
                        "latency_ms": 0,
                    },
                }
            )
            yield (event.model_dump_json() + "\n").encode()

    return StreamingResponse(
        framed_events(),
        media_type="application/x-ndjson",
        headers={"Cache-Control": "no-store", "X-Content-Type-Options": "nosniff"},
    )
