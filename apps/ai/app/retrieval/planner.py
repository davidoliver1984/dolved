import hashlib
import json
import time
from dataclasses import dataclass
from typing import Any

import httpx
from opentelemetry import trace
from opentelemetry.trace import SpanKind, Status, StatusCode
from pydantic import SecretStr, ValidationError

from app.retrieval.models import OperationUsage, PlannerLineage, RetrievalPlan
from app.telemetry import trace_attributes


class RetrievalPlanningError(RuntimeError):
    def __init__(
        self,
        message: str,
        *,
        category: str = "invalid_typed_plan",
        provider_status: int | None = None,
        systemic: bool = False,
    ) -> None:
        super().__init__(message)
        self.category = category
        self.provider_status = provider_status
        self.systemic = systemic


@dataclass(frozen=True)
class PlanningResult:
    plan: RetrievalPlan
    lineage: PlannerLineage
    usage: OperationUsage


def _strict_response_schema(value: Any) -> Any:
    """Adapt Pydantic JSON Schema to the provider's strict-output subset."""
    if isinstance(value, list):
        return [_strict_response_schema(item) for item in value]
    if not isinstance(value, dict):
        return value

    result = {
        key: _strict_response_schema(item)
        for key, item in value.items()
        if key != "default"
    }
    properties = result.get("properties")
    if isinstance(properties, dict):
        result["additionalProperties"] = False
        result["required"] = list(properties)
    return result


class StructuredChatRetrievalPlanner:
    """Isolated OpenAI-compatible structured-output planner adapter."""

    def __init__(
        self,
        *,
        api_url: str,
        api_key: SecretStr,
        provider_name: str,
        model: str,
        timeout_seconds: float,
        client: httpx.Client | None = None,
        contract_schema_version: str = "plan-response-v2",
        prompt_version: str = "adr-0022-v1",
        adapter_version: str = "structured-chat-v2",
    ) -> None:
        if not api_key.get_secret_value().strip():
            raise RetrievalPlanningError(
                "retrieval planner credentials are unavailable"
            )
        self._api_url = api_url
        self._api_key = api_key
        self._provider_name = provider_name
        self._model = model
        self._contract_schema_version = contract_schema_version
        self._prompt_version = prompt_version
        self._adapter_version = adapter_version
        self._client = client or httpx.Client(timeout=timeout_seconds)

    def plan(self, question: str, *, evaluated_at: str) -> RetrievalPlan:
        return self.plan_with_observation(question, evaluated_at=evaluated_at).plan

    def plan_with_observation(
        self, question: str, *, evaluated_at: str
    ) -> PlanningResult:
        started = time.perf_counter()
        schema = _strict_response_schema(RetrievalPlan.model_json_schema())
        with trace.get_tracer("maketime.python.retrieval").start_as_current_span(
            "plan retrieval intent",
            kind=SpanKind.CLIENT,
            attributes=trace_attributes(
                {
                    "gen_ai.operation.name": "retrieval_planning",
                    "gen_ai.provider.name": self._provider_name,
                    "gen_ai.request.model": self._model,
                }
            ),
            record_exception=False,
            set_status_on_exception=False,
        ) as span:
            try:
                response = self._client.post(
                    self._api_url,
                    headers={
                        "Authorization": (f"Bearer {self._api_key.get_secret_value()}")
                    },
                    json={
                        "model": self._model,
                        "messages": [
                            {
                                "role": "system",
                                "content": _planner_prompt(evaluated_at),
                            },
                            {"role": "user", "content": question},
                        ],
                        "response_format": {
                            "type": "json_schema",
                            "json_schema": {
                                "name": "retrieval_plan",
                                "strict": True,
                                "schema": schema,
                            },
                        },
                    },
                )
                response.raise_for_status()
                payload = response.json()
                content = payload["choices"][0]["message"]["content"]
                raw_plan = json.loads(content) if isinstance(content, str) else content
                plan = RetrievalPlan.model_validate(raw_plan)
                span.set_attributes(
                    trace_attributes(
                        {
                            "rag.processing.outcome": "succeeded",
                            "rag.retrieval.temporal_mode": plan.temporal_mode.value,
                        }
                    )
                )
            except httpx.HTTPStatusError as exception:
                category, systemic = _provider_failure(exception.response)
                span.set_attributes(
                    trace_attributes(
                        {
                            "error.type": type(exception).__name__,
                            "rag.processing.outcome": "failed",
                        }
                    )
                )
                span.set_status(Status(StatusCode.ERROR))
                raise RetrievalPlanningError(
                    "retrieval planner provider rejected the request",
                    category=category,
                    provider_status=exception.response.status_code,
                    systemic=systemic,
                ) from exception
            except httpx.HTTPError as exception:
                span.set_attributes(
                    trace_attributes(
                        {
                            "error.type": type(exception).__name__,
                            "rag.processing.outcome": "failed",
                        }
                    )
                )
                span.set_status(Status(StatusCode.ERROR))
                raise RetrievalPlanningError(
                    "retrieval planner transport failed",
                    category="transport_error",
                ) from exception
            except (
                KeyError,
                IndexError,
                TypeError,
                ValueError,
                ValidationError,
            ) as exception:
                span.set_attributes(
                    trace_attributes(
                        {
                            "error.type": type(exception).__name__,
                            "rag.processing.outcome": "failed",
                        }
                    )
                )
                span.set_status(Status(StatusCode.ERROR))
                raise RetrievalPlanningError(
                    "retrieval planner returned no usable plan",
                    category="invalid_typed_plan",
                    provider_status=200,
                ) from exception
        if plan.retrieval_queries != (question,):
            raise RetrievalPlanningError(
                "retrieval planner altered the V1 retrieval query",
                category="query_not_preserved",
                provider_status=200,
            )
        provider_usage = payload.get("usage")
        input_tokens = None
        cached_input_tokens = None
        output_tokens = None
        if isinstance(provider_usage, dict):
            input_tokens = _non_negative_int(provider_usage.get("prompt_tokens"))
            output_tokens = _non_negative_int(provider_usage.get("completion_tokens"))
            details = provider_usage.get("prompt_tokens_details")
            if isinstance(details, dict):
                cached_input_tokens = _non_negative_int(details.get("cached_tokens"))
        return PlanningResult(
            plan=plan,
            lineage=self.lineage(),
            usage=OperationUsage(
                stage="planner",
                provider=self._provider_name,
                model=self._model,
                execution="provider_api",
                request_count=1,
                retry_count=0,
                input_tokens=input_tokens,
                cached_input_tokens=cached_input_tokens,
                output_tokens=output_tokens,
                latency_ms=(time.perf_counter() - started) * 1000,
                cost_basis="unavailable",
                cost_usd=None,
                pricing_snapshot=None,
            ),
        )

    def lineage(self) -> PlannerLineage:
        values = {
            "provider": self._provider_name,
            "model": self._model,
            "contract_schema_version": self._contract_schema_version,
            "prompt_version": self._prompt_version,
            "adapter_version": self._adapter_version,
        }
        encoded = json.dumps(values, sort_keys=True, separators=(",", ":")).encode()
        return PlannerLineage(**values, fingerprint=hashlib.sha256(encoded).hexdigest())


def _planner_prompt(evaluated_at: str) -> str:
    return (
        """You are a thin linguistic retrieval-intent and location-reference classifier.
Use only language in the question. Never resolve authority, documents, versions, aliases,
locations, applicability, permissions, or eligible scope. Preserve the question exactly as
the sole retrieval query.

CURRENT: what rule/value/status applies now. Contrasts between current values, options,
locations, boundary values, products, or objects are CURRENT, not COMPARE.
COMPARE: explicit change/difference between policy, document, procedure, or authority states
over time. Do not create PRIMARY/COMPARISON objects.
VALID_AT_DATE: what applied on an exact date or calendar period. Use explicit_date only for an
exact calendar day. Otherwise use temporal_reference kind calendar_period and preserve wording.
Never manufacture a day or time.
HISTORICAL_REFERENCE: one non-current state named linguistically (old, previous, version 1,
the 2023 procedure, before withdrawal) without requesting comparison. Use temporal_reference
kind historical_reference and preserve wording.
CLARIFICATION_REQUIRED: only when linguistic temporal intent genuinely cannot be classified;
use reason unclassifiable_temporal_intent.

location_references contains only physical locations, sites, homes, offices, named regions,
or geographic areas explicitly present. Preserve wording, return multiple references
separately, and never resolve aliases. Roles, people, objects, documents, organisations, and
actions are not locations.

The authoritative evaluation instant is """
        + evaluated_at
    )


def _provider_failure(response: httpx.Response) -> tuple[str, bool]:
    code: str | None = None
    try:
        payload = response.json()
        error = payload.get("error") if isinstance(payload, dict) else None
        code = (
            str(error.get("code"))
            if isinstance(error, dict) and error.get("code")
            else None
        )
    except ValueError:
        pass
    if response.status_code in {401, 403}:
        return "provider_authentication", True
    if response.status_code == 429 and code in {
        "credit_balance_exhausted",
        "insufficient_quota",
    }:
        return "provider_quota", True
    if response.status_code == 429:
        return "provider_rate_limit", False
    if response.status_code >= 500:
        return "provider_unavailable", False
    return "provider_request_rejected", False


class FixedRetrievalPlanner:
    """Deterministic test double; never selected as the production planner."""

    def __init__(self, plan: RetrievalPlan) -> None:
        self._plan = plan

    def plan(self, question: str, *, evaluated_at: str) -> RetrievalPlan:
        del evaluated_at
        return self._plan.model_copy(update={"retrieval_queries": (question,)})

    def plan_with_observation(
        self, question: str, *, evaluated_at: str
    ) -> PlanningResult:
        return PlanningResult(
            plan=self.plan(question, evaluated_at=evaluated_at),
            lineage=self.lineage(),
            usage=OperationUsage(
                stage="planner",
                provider="deterministic",
                model="fixed-retrieval-planner",
                execution="local",
                request_count=1,
                retry_count=0,
                latency_ms=0,
                cost_basis="zero_cost_local",
                cost_usd=0,
            ),
        )

    def lineage(self) -> PlannerLineage:
        values = {
            "provider": "deterministic",
            "model": "fixed-retrieval-planner",
            "contract_schema_version": "plan-response-v2",
            "prompt_version": "fixed-v1",
            "adapter_version": "fixed-v1",
        }
        encoded = json.dumps(values, sort_keys=True, separators=(",", ":")).encode()
        return PlannerLineage(**values, fingerprint=hashlib.sha256(encoded).hexdigest())


def _non_negative_int(value: object) -> int | None:
    return (
        value
        if isinstance(value, int) and not isinstance(value, bool) and value >= 0
        else None
    )
