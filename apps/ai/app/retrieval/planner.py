import hashlib
import json
import time
from copy import deepcopy
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


def _provider_plan_schema() -> dict[str, Any]:
    """Build a strict provider envelope whose intent branches are always valid plans."""
    plan_schema = _strict_response_schema(RetrievalPlan.model_json_schema())
    properties = plan_schema["properties"]
    definitions = plan_schema.get("$defs", {})

    explicit_date = next(
        branch
        for branch in properties["explicit_date"]["anyOf"]
        if branch.get("type") == "string"
    )
    temporal_reference = next(
        branch
        for branch in properties["temporal_reference"]["anyOf"]
        if "$ref" in branch
    )

    def intent(
        mode: str,
        *,
        date: dict[str, Any] | None = None,
        reference: dict[str, Any] | None = None,
        clarification: bool = False,
    ) -> dict[str, Any]:
        return {
            "type": "object",
            "properties": {
                "temporal_mode": {"type": "string", "enum": [mode]},
                "explicit_date": deepcopy(date) if date else {"type": "null"},
                "temporal_reference": (
                    deepcopy(reference) if reference else {"type": "null"}
                ),
                "clarification_reason": (
                    {
                        "type": "string",
                        "enum": ["unclassifiable_temporal_intent"],
                    }
                    if clarification
                    else {"type": "null"}
                ),
            },
            "required": [
                "temporal_mode",
                "explicit_date",
                "temporal_reference",
                "clarification_reason",
            ],
            "additionalProperties": False,
        }

    calendar_reference = {
        "type": "object",
        "properties": {
            "kind": {"type": "string", "enum": ["calendar_period"]},
            "value": deepcopy(definitions["TemporalReference"]["properties"]["value"]),
        },
        "required": ["kind", "value"],
        "additionalProperties": False,
    }
    historical_reference = {
        "type": "object",
        "properties": {
            "kind": {"type": "string", "enum": ["historical_reference"]},
            "value": deepcopy(definitions["TemporalReference"]["properties"]["value"]),
        },
        "required": ["kind", "value"],
        "additionalProperties": False,
    }

    return {
        "type": "object",
        "properties": {
            "retrieval_queries": deepcopy(properties["retrieval_queries"]),
            "intent": {
                "anyOf": [
                    intent("current"),
                    intent("compare"),
                    intent("compare", date=explicit_date),
                    intent("compare", reference=temporal_reference),
                    intent("valid_at_date", date=explicit_date),
                    intent("valid_at_date", reference=calendar_reference),
                    intent("historical_reference", reference=historical_reference),
                    intent("clarification_required", clarification=True),
                ]
            },
            "location_references": deepcopy(properties["location_references"]),
        },
        "required": ["retrieval_queries", "intent", "location_references"],
        "additionalProperties": False,
        "$defs": definitions,
    }


def _plan_from_provider_envelope(value: object) -> dict[str, object]:
    if not isinstance(value, dict) or set(value) != {
        "retrieval_queries",
        "intent",
        "location_references",
    }:
        raise RetrievalPlanningError(
            "retrieval planner returned an invalid schema envelope",
            category="schema_validation_failure",
            provider_status=200,
        )
    intent = value["intent"]
    if not isinstance(intent, dict) or set(intent) != {
        "temporal_mode",
        "explicit_date",
        "temporal_reference",
        "clarification_reason",
    }:
        raise RetrievalPlanningError(
            "retrieval planner returned an invalid intent schema",
            category="schema_validation_failure",
            provider_status=200,
        )
    return {
        "retrieval_queries": value["retrieval_queries"],
        "temporal_mode": intent["temporal_mode"],
        "explicit_date": intent["explicit_date"],
        "temporal_reference": intent["temporal_reference"],
        "location_references": value["location_references"],
        "clarification_reason": intent["clarification_reason"],
    }


def _validation_failure_category(exception: ValidationError) -> str:
    return (
        "cross_field_validation_failure"
        if any(not error["loc"] for error in exception.errors(include_input=False))
        else "field_validation_failure"
    )


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
        prompt_version: str = "adr-0022-v5",
        adapter_version: str = "structured-chat-v3",
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
        schema = _provider_plan_schema()
        with trace.get_tracer("dolved.python.retrieval").start_as_current_span(
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
                try:
                    payload = response.json()
                    content = payload["choices"][0]["message"]["content"]
                    if not isinstance(content, str):
                        raise TypeError("planner content is not a JSON string")
                except KeyError, IndexError, TypeError, ValueError:
                    raise RetrievalPlanningError(
                        "retrieval planner returned an invalid response shape",
                        category="response_shape_failure",
                        provider_status=200,
                    ) from None
                try:
                    raw_envelope = json.loads(content)
                except json.JSONDecodeError:
                    raise RetrievalPlanningError(
                        "retrieval planner returned invalid JSON",
                        category="json_decode_failure",
                        provider_status=200,
                    ) from None
                raw_plan = _plan_from_provider_envelope(raw_envelope)
                try:
                    plan = RetrievalPlan.model_validate(raw_plan)
                except ValidationError as exception:
                    raise RetrievalPlanningError(
                        "retrieval planner returned an invalid typed plan",
                        category=_validation_failure_category(exception),
                        provider_status=200,
                    ) from None
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
            except RetrievalPlanningError as exception:
                span.set_attributes(
                    trace_attributes(
                        {
                            "error.type": exception.category,
                            "rag.processing.outcome": "failed",
                        }
                    )
                )
                span.set_status(Status(StatusCode.ERROR))
                raise
            except (KeyError, IndexError, TypeError, ValueError) as exception:
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
                    category="schema_validation_failure",
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

CURRENT: what rule/value/status applies now. Timing, duration, sequence, deadlines, incident
chronology, grammatical tense, or numeric contrasts inside the rule are policy content, not
document-authority time, and remain CURRENT unless the question asks which authoritative
document state applied. Contrasts between current values, options, locations, boundary values,
products, or objects are CURRENT, not COMPARE.
COMPARE: explicit change/difference between policy, document, procedure, or authority states
over time. When the question explicitly names the historical comparison selector, preserve it
in explicit_date or temporal_reference; do not discard it merely because the application can
default to the immediately previous attained version. A null selector remains valid for a
genuinely relative current-versus-previous comparison that supplies no explicit selector.
Do not create PRIMARY/COMPARISON objects.
VALID_AT_DATE: what applied on an exact date or calendar period. Use explicit_date only for an
exact calendar day. When the question explicitly supplies a calendar day, month, and year,
preserve that calendar date exactly in explicit_date: 1 January 2026 becomes 2026-01-01,
15 June 2024 becomes 2024-06-15, and 2025-10-03 remains 2025-10-03. Never substitute a
different day, month, or year. Otherwise use temporal_reference kind calendar_period and
preserve wording. Never manufacture a day or time.
HISTORICAL_REFERENCE: one non-current state named linguistically (old, previous, version 1,
the 2023 procedure, before withdrawal) without requesting comparison. Use temporal_reference
kind historical_reference and preserve wording. A question asking whether an earlier version
became current again after a later version was withdrawn asks about current authority and is
CURRENT, unless it explicitly asks to compare the content of the versions.
CLARIFICATION_REQUIRED: only when linguistic temporal intent genuinely cannot be classified;
use reason unclassifiable_temporal_intent.

location_references contains only geographic or organisational places/scopes that answer
"Where does this policy apply?": physical sites, homes, offices, named regions, geographic
areas, or community-service areas explicitly present. Preserve the smallest independently
meaningful applicability referent, excluding surrounding document or entity wording: in
"Midlands regional procedure", the referent is "Midlands", not the whole document phrase.
When multiple independently meaningful scopes are explicit, return each separately. Preserve
both an explicit parent and descendant, such as "Coventry" and "Midlands"; never collapse
relational wording into one combined reference, and never resolve or reconcile them.
Named entities are not locations merely because they appear in the question. Actors, recipients,
departments or functions, regulators, organisations, roles or people, equipment, storage areas
or containers, objects, documents, and actions are not location references unless the same
wording independently identifies an applicability place or scope in the question.

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
