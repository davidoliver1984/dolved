"""Isolated four-intent classifier used only by PLN-EXP-0004."""

from __future__ import annotations

import json
import time
from datetime import date
from enum import StrEnum

import httpx
from pydantic import BaseModel, ConfigDict, Field, SecretStr, ValidationError

from app.evaluation.thin_intent_classifier import (
    ClassifierFailure,
    _provider_failure,
    _retry_delay,
    _strict_response_schema,
)


class HistoricalTemporalIntent(StrEnum):
    CURRENT = "CURRENT"
    COMPARE = "COMPARE"
    VALID_AT_DATE = "VALID_AT_DATE"
    HISTORICAL_REFERENCE = "HISTORICAL_REFERENCE"


class HistoricalReferenceClassification(BaseModel):
    model_config = ConfigDict(extra="forbid", frozen=True, strict=True)

    temporal_intent: HistoricalTemporalIntent
    explicit_date: date | None
    temporal_reference: str | None = Field(default=None, min_length=1, max_length=255)
    location_references: tuple[str, ...] = Field(max_length=8)


class HistoricalClassifierCallResult(BaseModel):
    model_config = ConfigDict(extra="forbid", frozen=True)

    classification: HistoricalReferenceClassification | None = None
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
- CURRENT means the user asks what rule, value, or authority status applies now.
- CURRENT remains correct when the question contrasts two current values or options, describes
  an operational thing changing, compares locations, or asks whether a scheduled/withdrawn
  rule currently applies.
- COMPARE only when the user explicitly asks whether or how policy, document, procedure, or
  application-authority states differ across time.
- HISTORICAL_REFERENCE means the user asks about one non-current policy/document state through
  wording such as old, previous, version 1, 2023 procedure, before withdrawal, or earlier policy,
  without asking for comparison and without specifying a date-qualified calendar period.
- VALID_AT_DATE means the user asks what applied at an exact date or a sufficiently explicit
  calendar period such as January 2024 or in 2024. Set explicit_date only for an exact calendar
  day. Never invent a day for a month/year or year-only period; preserve it in temporal_reference.
- Words such as "or", "different", "changed", "before", and "old" do not alone prove COMPARE.
  Interpret what is being contrasted: policy states over time means COMPARE; one old state means
  HISTORICAL_REFERENCE; current values and current authority status mean CURRENT.

Temporal examples:
"Is the deadline three days or two?" => CURRENT.
"Has the October electronic MAR rule started yet?" => CURRENT.
"Did the medication policy change?" => COMPARE.
"Did the proposed reporting method replace the previous one?" => COMPARE.
"What did version 1 say?" => HISTORICAL_REFERENCE with temporal_reference "version 1".
"Before it was withdrawn, what did outbreak version 2 require?" => HISTORICAL_REFERENCE.
"What applied on 1 June 2024?" => VALID_AT_DATE with explicit_date 2024-06-01.
"What was the rule in January 2024?" => VALID_AT_DATE, explicit_date null.

Location rules (unchanged from PLN-EXP-0002 and PLN-EXP-0003):
- location_references contains only physical care locations, sites, homes, offices, named
  regions, or geographic areas explicitly mentioned in the question.
- Preserve question wording. Do not resolve aliases or emit IDs.
- Return separate entries when multiple locations are mentioned.
- Roles, people, objects, documents, organisations, and actions are not locations.
- Examples that are NOT locations: frontline staff, community workers, managers, a resident,
  meds chart, medicines fridge, data protection policy, ICO, before giving medication.

"What applies at Harbour View?" => locations ["Harbour View"].
"Does the South West procedure cover Meadow Court?" => locations ["South West", "Meadow Court"].
"It is on the meds chart as needed — is that enough?" => locations [].
"""


class StructuredHistoricalReferenceClassifier:
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
            raise ValueError(
                "historical-reference-classifier credentials are unavailable"
            )
        self.api_url = api_url
        self.provider = provider
        self.model = model
        self._api_key = api_key
        self._max_transport_attempts = max_transport_attempts
        self._client = client or httpx.Client(timeout=timeout_seconds)

    def classify(self, question: str) -> HistoricalClassifierCallResult:
        started = time.perf_counter()
        schema = _strict_response_schema(
            HistoricalReferenceClassification.model_json_schema()
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
                            {"role": "system", "content": SYSTEM_PROMPT},
                            {"role": "user", "content": question},
                        ],
                        "response_format": {
                            "type": "json_schema",
                            "json_schema": {
                                "name": "thin_historical_reference_classification",
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
                classification = HistoricalReferenceClassification.model_validate_json(
                    encoded
                )
                usage = payload.get("usage") or {}
                details = usage.get("prompt_tokens_details") or {}
                return HistoricalClassifierCallResult(
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
) -> HistoricalClassifierCallResult:
    return HistoricalClassifierCallResult(
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
