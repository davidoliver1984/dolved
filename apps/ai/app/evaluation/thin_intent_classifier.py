"""Isolated linguistic-intent classifier used only by planner experiments."""

from __future__ import annotations

import json
import time
from datetime import date
from enum import StrEnum
from typing import Any

import httpx
from pydantic import BaseModel, ConfigDict, Field, SecretStr, ValidationError


class TemporalIntent(StrEnum):
    CURRENT = "CURRENT"
    COMPARE = "COMPARE"
    VALID_AT_DATE = "VALID_AT_DATE"


class ThinIntentClassification(BaseModel):
    """Provider-neutral facts inferable from the question alone."""

    model_config = ConfigDict(extra="forbid", frozen=True, strict=True)

    temporal_intent: TemporalIntent
    explicit_date: date | None
    temporal_reference: str | None = Field(max_length=255)
    applicability_reference: str | None = Field(max_length=255)


class ClassifierFailure(BaseModel):
    model_config = ConfigDict(extra="forbid", frozen=True)

    category: str
    provider_status: int | None = None
    attempt_count: int = Field(ge=1)
    systemic: bool = False


class ClassifierCallResult(BaseModel):
    model_config = ConfigDict(extra="forbid", frozen=True)

    classification: ThinIntentClassification | None = None
    failure: ClassifierFailure | None = None
    latency_ms: float = Field(ge=0)
    input_tokens: int = Field(ge=0)
    cached_input_tokens: int = Field(ge=0)
    output_tokens: int = Field(ge=0)


SYSTEM_PROMPT = """You are a deliberately thin linguistic retrieval-intent classifier.

Use only information expressed in the user's question. You have no access to documents,
versions, authority windows, locations, aliases, applicability, permissions, or databases.

Return exactly the strict structured contract supplied by the caller.

Rules:
- temporal_intent is CURRENT when the language expresses no historical or comparative intent.
- temporal_intent is COMPARE when the language asks what changed, whether something became
  faster/different, or otherwise compares historical and current guidance.
- temporal_intent is VALID_AT_DATE for historical intent about what applied at a date, in a
  named period, under an old/versioned procedure, or before/after a historical transition.
- explicit_date is an ISO YYYY-MM-DD date only when the question itself identifies one exact
  calendar day. A month, year, "old", version number, or application-state fact is not an
  exact date and must produce null. Never manufacture a date.
- temporal_reference is the relevant historical/comparative wording copied faithfully from
  the question, or null when none is present. It is not an application identifier.
- applicability_reference is location/applicability wording copied faithfully from the
  question, or null. Never translate an alias, infer a canonical location, or emit an ID.
- Do not decide whether a location reference resolves uniquely. Preserve phrases such as
  "the home" or "there" for deterministic application logic to resolve later.
- Do not resolve current/previous document versions or construct retrieval scopes.

Examples:
"Is suspension a disciplinary punishment?" => CURRENT, no date.
"Did complaint handling get faster?" => COMPARE, no date.
"What applied on 15 January 2024?" => VALID_AT_DATE, 2024-01-15.
"How long did managers have under the old accident procedure?" => VALID_AT_DATE, no date.
"What applies at Harbour View?" => applicability_reference "Harbour View".
"Where is the assembly point at the home?" => applicability_reference "the home"; do not
decide whether it is ambiguous in application state.
"""


class StructuredThinIntentClassifier:
    """OpenAI-compatible adapter isolated from the production RetrievalPlanner."""

    def __init__(
        self,
        *,
        api_url: str,
        api_key: SecretStr,
        provider: str,
        model: str,
        timeout_seconds: float = 60,
        max_transport_attempts: int = 3,
        client: httpx.Client | None = None,
    ) -> None:
        if not api_key.get_secret_value().strip():
            raise ValueError("thin-classifier credentials are unavailable")
        self.api_url = api_url
        self.provider = provider
        self.model = model
        self._api_key = api_key
        self._max_transport_attempts = max_transport_attempts
        self._client = client or httpx.Client(timeout=timeout_seconds)

    def classify(self, question: str) -> ClassifierCallResult:
        started = time.perf_counter()
        schema = _strict_response_schema(ThinIntentClassification.model_json_schema())
        for attempt in range(1, self._max_transport_attempts + 1):
            try:
                response = self._client.post(
                    self.api_url,
                    headers={
                        "Authorization": f"Bearer {self._api_key.get_secret_value()}"
                    },
                    json={
                        "model": self.model,
                        "messages": [
                            {"role": "system", "content": SYSTEM_PROMPT},
                            {"role": "user", "content": question},
                        ],
                        "response_format": {
                            "type": "json_schema",
                            "json_schema": {
                                "name": "thin_intent_classification",
                                "strict": True,
                                "schema": schema,
                            },
                        },
                    },
                )
            except httpx.HTTPError:
                if attempt < self._max_transport_attempts:
                    time.sleep(2 ** (attempt - 1))
                    continue
                return _failed(started, "transport_error", None, attempt)

            if response.status_code >= 400:
                category, systemic = _provider_failure(response)
                if (
                    not systemic
                    and response.status_code in {429, 500, 502, 503, 504}
                    and attempt < self._max_transport_attempts
                ):
                    time.sleep(_retry_delay(response, attempt))
                    continue
                return _failed(
                    started,
                    category,
                    response.status_code,
                    attempt,
                    systemic=systemic,
                )

            try:
                payload = response.json()
                content = payload["choices"][0]["message"]["content"]
                encoded = content if isinstance(content, str) else json.dumps(content)
                classification = ThinIntentClassification.model_validate_json(encoded)
                usage = payload.get("usage") or {}
                details = usage.get("prompt_tokens_details") or {}
                return ClassifierCallResult(
                    classification=classification,
                    latency_ms=(time.perf_counter() - started) * 1000,
                    input_tokens=int(usage.get("prompt_tokens") or 0),
                    cached_input_tokens=int(details.get("cached_tokens") or 0),
                    output_tokens=int(usage.get("completion_tokens") or 0),
                )
            except KeyError, IndexError, TypeError, ValueError, ValidationError:
                # A semantic/typed failure is one observation. Never retry to success.
                return _failed(started, "invalid_typed_classification", 200, attempt)

        raise AssertionError("transport attempt loop must return")


def _failed(
    started: float,
    category: str,
    status: int | None,
    attempt: int,
    *,
    systemic: bool = False,
) -> ClassifierCallResult:
    return ClassifierCallResult(
        failure=ClassifierFailure(
            category=category,
            provider_status=status,
            attempt_count=attempt,
            systemic=systemic,
        ),
        latency_ms=(time.perf_counter() - started) * 1000,
        input_tokens=0,
        cached_input_tokens=0,
        output_tokens=0,
    )


def _retry_delay(response: httpx.Response, attempt: int) -> float:
    value = response.headers.get("Retry-After")
    if value:
        try:
            return max(float(value), 0)
        except ValueError:
            pass
    return float(2 ** (attempt - 1))


def _provider_failure(response: httpx.Response) -> tuple[str, bool]:
    code: str | None = None
    try:
        payload = response.json()
        error = payload.get("error") if isinstance(payload, dict) else None
        if isinstance(error, dict) and error.get("code"):
            code = str(error["code"])
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


def _strict_response_schema(value: Any) -> Any:
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
