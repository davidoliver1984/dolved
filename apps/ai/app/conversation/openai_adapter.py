from __future__ import annotations

import json
import time
from typing import Any

import httpx
from opentelemetry import trace
from opentelemetry.trace import SpanKind, Status, StatusCode
from pydantic import SecretStr

from app.conversation.models import (
    ContextualisationRequest,
    ContextualisationResult,
    InterpretationMetadata,
)
from app.telemetry import trace_attributes


class ContextualisationError(RuntimeError):
    pass


def _strict_schema() -> dict[str, Any]:
    return {
        "type": "object",
        "additionalProperties": False,
        "required": [
            "status",
            "resolved_query",
            "used_prior_context",
            "interpretation_metadata",
            "clarification_question",
        ],
        "properties": {
            "status": {"enum": ["resolved", "clarification_required"]},
            "resolved_query": {"type": ["string", "null"]},
            "used_prior_context": {"type": "boolean"},
            "interpretation_metadata": {
                "type": ["object", "null"],
                "additionalProperties": False,
                "properties": {
                    "used_turn_ordinals": {
                        "type": "array",
                        "items": {"type": "integer"},
                    }
                },
                "required": ["used_turn_ordinals"],
            },
            "clarification_question": {"type": ["string", "null"]},
        },
    }


class OpenAIQueryContextualizer:
    def __init__(
        self,
        *,
        api_url: str,
        api_key: SecretStr,
        model: str,
        contextualiser_version: str,
        timeout_seconds: float,
        max_attempts: int,
        client: httpx.Client | None = None,
    ) -> None:
        if not api_key.get_secret_value().strip():
            raise ContextualisationError("contextualiser credentials are unavailable")
        self._api_url = api_url
        self._api_key = api_key
        self._model = model
        self._version = contextualiser_version
        self._max_attempts = max_attempts
        self._client = client or httpx.Client(timeout=timeout_seconds)

    def contextualize(
        self, request: ContextualisationRequest
    ) -> ContextualisationResult:
        if not request.history:
            return ContextualisationResult(
                status="resolved",
                resolved_query=request.current_message,
                used_prior_context=False,
                interpretation_metadata=InterpretationMetadata(used_turn_ordinals=()),
                clarification_question=None,
                contextualiser_version=self._version,
                usage={"execution": "deterministic", "request_count": 0},
            )
        started = time.perf_counter()
        tracer = trace.get_tracer("dolved.python.conversation")
        with tracer.start_as_current_span(
            "conversation.contextualize.provider",
            kind=SpanKind.CLIENT,
            attributes=trace_attributes(
                {
                    "gen_ai.operation.name": "contextualize",
                    "gen_ai.provider.name": "openai",
                    "gen_ai.request.model": self._model,
                    "rag.operation.stage": "contextualisation_provider",
                }
            ),
            record_exception=False,
            set_status_on_exception=False,
        ) as span:
            try:
                result = self._contextualize_provider(request, started)
                span.set_attributes(
                    trace_attributes({"rag.operation.outcome": "completed"})
                )
                return result
            except Exception as exception:
                span.set_attributes(
                    trace_attributes(
                        {
                            "rag.operation.outcome": "failed",
                            "error.type": type(exception).__name__,
                        }
                    )
                )
                span.set_status(Status(StatusCode.ERROR))
                raise

    def _contextualize_provider(
        self, request: ContextualisationRequest, started: float
    ) -> ContextualisationResult:
        last_error: Exception | None = None
        for attempt in range(1, self._max_attempts + 1):
            try:
                response = self._client.post(
                    self._api_url,
                    headers={
                        "Authorization": f"Bearer {self._api_key.get_secret_value()}"
                    },
                    json={
                        "model": self._model,
                        "messages": [
                            {"role": "system", "content": _prompt()},
                            {
                                "role": "user",
                                "content": json.dumps(
                                    {
                                        "history": [
                                            item.model_dump(mode="json")
                                            for item in request.history
                                        ],
                                        "current_message": request.current_message,
                                    },
                                    ensure_ascii=False,
                                    separators=(",", ":"),
                                ),
                            },
                        ],
                        "response_format": {
                            "type": "json_schema",
                            "json_schema": {
                                "name": "conversation_contextualisation",
                                "strict": True,
                                "schema": _strict_schema(),
                            },
                        },
                    },
                )
                response.raise_for_status()
                payload = response.json()
                content = payload["choices"][0]["message"]["content"]
                parsed = json.loads(content)
                usage = payload.get("usage")
                result = ContextualisationResult.model_validate(
                    {
                        **parsed,
                        "contextualiser_version": self._version,
                        "usage": {
                            "execution": "provider_api",
                            "request_count": attempt,
                            "input_tokens": usage.get("prompt_tokens")
                            if isinstance(usage, dict)
                            else None,
                            "output_tokens": usage.get("completion_tokens")
                            if isinstance(usage, dict)
                            else None,
                            "latency_ms": (time.perf_counter() - started) * 1000,
                        },
                    }
                )
                used = (
                    result.interpretation_metadata.used_turn_ordinals
                    if result.interpretation_metadata is not None
                    else ()
                )
                available = {item.user_ordinal for item in request.history}
                if any(ordinal not in available for ordinal in used):
                    raise ValueError("contextualisation cited an unavailable turn")
                return result
            except (
                httpx.HTTPError,
                KeyError,
                IndexError,
                TypeError,
                ValueError,
            ) as exception:
                last_error = exception
                retryable = isinstance(exception, httpx.HTTPStatusError) and (
                    exception.response.status_code == 429
                    or exception.response.status_code >= 500
                )
                if not retryable or attempt == self._max_attempts:
                    break
                time.sleep(min(2 ** (attempt - 1), 8))
        raise ContextualisationError(
            "contextualisation provider call failed"
        ) from last_error


def _prompt() -> str:
    return """Resolve the current message into a standalone retrieval question using only the supplied bounded conversation history.
Do not answer the question. Do not treat prior assistant text as evidence. Do not invent facts, dates, locations, authority or applicability.
If the reference cannot be resolved unambiguously, return clarification_required with one concise question.
If resolved, preserve the user's semantic intent exactly and return only the standalone query.
Record only the user-message ordinals actually used in interpretation_metadata.used_turn_ordinals."""
