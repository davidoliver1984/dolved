"""Isolated plural-location classifier used only by PLN-EXP-0002."""

from __future__ import annotations

import json
import time
from datetime import date

import httpx
from pydantic import BaseModel, ConfigDict, Field, SecretStr, ValidationError

from app.evaluation.thin_intent_classifier import (
    ClassifierFailure,
    TemporalIntent,
    _provider_failure,
    _retry_delay,
    _strict_response_schema,
)


class ThinIntentLocationClassification(BaseModel):
    model_config = ConfigDict(extra="forbid", frozen=True, strict=True)

    temporal_intent: TemporalIntent
    explicit_date: date | None
    temporal_reference: str | None = Field(default=None, min_length=1, max_length=255)
    location_references: tuple[str, ...] = Field(max_length=8)


class LocationClassifierCallResult(BaseModel):
    model_config = ConfigDict(extra="forbid", frozen=True)

    classification: ThinIntentLocationClassification | None = None
    failure: ClassifierFailure | None = None
    latency_ms: float = Field(ge=0)
    input_tokens: int = Field(ge=0)
    cached_input_tokens: int = Field(ge=0)
    output_tokens: int = Field(ge=0)


SYSTEM_PROMPT = """You are a deliberately thin linguistic retrieval-intent and location-reference classifier.

Use only words in the user's question. You have no documents, authority data, versions,
location aliases, hierarchies, applicability, permissions, eligibility, or databases.

Return exactly the strict structured contract supplied by the caller.

Temporal rules:
- CURRENT is the default unless the user explicitly asks about change/comparison or gives an
  exact calendar date.
- COMPARE only when the user asks about change, difference, replacement, or comparison.
- VALID_AT_DATE only when the question contains one exact calendar day.
- A month, year, "old", version number, withdrawal, or application-state fact is not an exact
  date. Never manufacture a date. Preserve historical/comparative wording in
  temporal_reference for deterministic application logic.
- explicit_date is ISO YYYY-MM-DD only for an exact day stated in the question; otherwise null.

Location rules:
- location_references contains only physical care locations, sites, homes, offices, named
  regions, or geographic areas explicitly mentioned in the question.
- Preserve question wording. Do not resolve aliases or emit IDs.
- Return separate entries when multiple locations are mentioned.
- Roles, people, objects, documents, organisations, and actions are not locations.
- Examples that are NOT locations: frontline staff, community workers, managers, a resident,
  meds chart, medicines fridge, data protection policy, ICO, before giving medication.

Examples:
"Is suspension a disciplinary punishment?" => CURRENT, locations [].
"Did complaint handling get faster?" => COMPARE, locations [].
"What applied on 15 January 2024?" => VALID_AT_DATE, date 2024-01-15.
"How long did managers have under the old procedure?" => no exact date; preserve the old wording.
"What applies at Harbour View?" => locations ["Harbour View"].
"Does the South West procedure cover Meadow Court?" => locations ["South West", "Meadow Court"].
"It is on the meds chart as needed — is that enough?" => locations [].
"""


class StructuredThinIntentLocationClassifier:
    def __init__(
        self,
        *,
        api_url: str,
        api_key: SecretStr,
        provider: str,
        model: str,
        timeout_seconds: float = 60,
        max_transport_attempts: int = 3,
        system_prompt: str = SYSTEM_PROMPT,
        client: httpx.Client | None = None,
    ) -> None:
        if not api_key.get_secret_value().strip():
            raise ValueError("thin-location-classifier credentials are unavailable")
        self.api_url = api_url
        self.provider = provider
        self.model = model
        self._system_prompt = system_prompt
        self._api_key = api_key
        self._max_transport_attempts = max_transport_attempts
        self._client = client or httpx.Client(timeout=timeout_seconds)

    def classify(self, question: str) -> LocationClassifierCallResult:
        started = time.perf_counter()
        schema = _strict_response_schema(
            ThinIntentLocationClassification.model_json_schema()
        )
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
                            {"role": "system", "content": self._system_prompt},
                            {"role": "user", "content": question},
                        ],
                        "response_format": {
                            "type": "json_schema",
                            "json_schema": {
                                "name": "thin_intent_location_classification",
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
                classification = ThinIntentLocationClassification.model_validate_json(
                    encoded
                )
                usage = payload.get("usage") or {}
                details = usage.get("prompt_tokens_details") or {}
                return LocationClassifierCallResult(
                    classification=classification,
                    latency_ms=(time.perf_counter() - started) * 1000,
                    input_tokens=int(usage.get("prompt_tokens") or 0),
                    cached_input_tokens=int(details.get("cached_tokens") or 0),
                    output_tokens=int(usage.get("completion_tokens") or 0),
                )
            except KeyError, IndexError, TypeError, ValueError, ValidationError:
                return _failed(started, "invalid_typed_classification", 200, attempt)
        raise AssertionError("transport attempt loop must return")


def _failed(
    started: float,
    category: str,
    status: int | None,
    attempt: int,
    *,
    systemic: bool = False,
) -> LocationClassifierCallResult:
    return LocationClassifierCallResult(
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
