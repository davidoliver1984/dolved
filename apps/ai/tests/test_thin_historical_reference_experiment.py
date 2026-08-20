import json
from pathlib import Path
from typing import Any

import httpx
from pydantic import SecretStr

from app.evaluation.thin_historical_reference_classifier import (
    HistoricalClassifierCallResult,
    HistoricalReferenceClassification,
    HistoricalTemporalIntent,
    StructuredHistoricalReferenceClassifier,
)
from app.evaluation.thin_historical_reference_experiment import (
    build_historical_reference_projection,
    run_historical_reference_experiment,
)

BENCHMARK = Path("/evaluation/benchmarks/dolved-care-engineering/v2")


def test_projection_scores_all_prior_historical_gap_variants() -> None:
    corpus = json.loads((BENCHMARK / "compiled/corpus.json").read_text())
    split = json.loads((BENCHMARK / "splits/v1.json").read_text())

    projection = build_historical_reference_projection(corpus, split)

    cases = {item["case_id"] for item in projection["variants"]}
    assert cases == set(split["assignments"]["engineering_tuning"])
    assert not cases & set(split["assignments"]["threshold_calibration"])
    assert not cases & set(split["assignments"]["sealed_held_out"])
    assert len(projection["variants"]) == 126
    assert _count(projection, "CURRENT") == 88
    assert _count(projection, "COMPARE") == 22
    assert _count(projection, "VALID_AT_DATE") == 9
    assert _count(projection, "HISTORICAL_REFERENCE") == 7
    assert _count(projection, None) == 0
    month = _expectation(projection, "hr.annual-leave.valid-at-date", "dated")
    assert month["expected"]["temporal_intent"] == "VALID_AT_DATE"
    assert month["expected"]["explicit_date"] is None
    old = _expectation(projection, "hr.annual-leave.valid-at-date", "old")
    assert old["expected"]["temporal_intent"] == "HISTORICAL_REFERENCE"


def test_adapter_accepts_historical_reference_and_does_not_semantically_retry() -> None:
    calls = 0

    def handler(request: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "message": {
                            "content": json.dumps(
                                {
                                    "temporal_intent": "HISTORICAL_REFERENCE",
                                    "explicit_date": None,
                                    "temporal_reference": "version 1",
                                    "location_references": [],
                                }
                            )
                        }
                    }
                ]
            },
            request=request,
        )

    classifier = StructuredHistoricalReferenceClassifier(
        api_url="https://provider.invalid",
        api_key=SecretStr("secret"),
        provider="openai",
        model="gpt-5-mini",
        client=httpx.Client(transport=httpx.MockTransport(handler)),
    )
    result = classifier.classify("What did version 1 say?")
    assert result.classification is not None
    assert (
        result.classification.temporal_intent
        is HistoricalTemporalIntent.HISTORICAL_REFERENCE
    )
    assert calls == 1


def test_runner_resumes_without_provider_calls(tmp_path: Path) -> None:
    class FakeClassifier:
        provider = "openai"
        model = "gpt-5-mini"
        calls = 0

        def classify(self, question: str) -> HistoricalClassifierCallResult:
            self.calls += 1
            return HistoricalClassifierCallResult(
                classification=HistoricalReferenceClassification(
                    temporal_intent=HistoricalTemporalIntent.HISTORICAL_REFERENCE,
                    explicit_date=None,
                    temporal_reference="old policy",
                    location_references=(),
                ),
                latency_ms=1,
                input_tokens=10,
                cached_input_tokens=0,
                output_tokens=5,
            )

    projection: dict[str, Any] = {
        "benchmark": {"id": "test", "version": "v1"},
        "reconciliations": [],
        "variants": [
            {
                "case_id": "case.one",
                "variant_id": "old",
                "question": "What did the old policy say?",
                "expected": {
                    "temporal_intent": "HISTORICAL_REFERENCE",
                    "explicit_date": None,
                    "location_references": [],
                },
                "review": {"temporal": None, "locations": None},
            }
        ],
    }
    prior_aggregate = {
        "structured_output_reliability": 1.0,
        "temporal_intent_accuracy": 1.0,
        "temporal_intent_by_mode": {},
        "estimated_cost_usd": 0.1,
    }
    prior = {
        "PLN-EXP-0001": {"aggregate": prior_aggregate},
        "PLN-EXP-0002": {"aggregate": prior_aggregate},
        "PLN-EXP-0003": {
            "aggregate": prior_aggregate,
            "variants": [
                {
                    "case_id": "case.one",
                    "variant_id": "old",
                    "expected": {"temporal_intent": None},
                }
            ],
        },
    }
    fake = FakeClassifier()
    arguments: dict[str, Any] = {
        "classifier": fake,
        "projection": projection,
        "output_directory": tmp_path,
        "repository_commit": "a" * 40,
        "input_price_per_million": 0.25,
        "cached_input_price_per_million": 0.025,
        "output_price_per_million": 2.0,
        "prior_results": prior,
        "contract_schema": {"type": "object"},
    }

    first = run_historical_reference_experiment(**arguments)  # type: ignore[arg-type]
    second = run_historical_reference_experiment(**arguments)  # type: ignore[arg-type]

    assert fake.calls == 1
    assert first == second
    assert first["aggregate"]["prior_review_variants_correct"] == 1


def _count(projection: dict[str, Any], intent: str | None) -> int:
    return sum(
        item["expected"]["temporal_intent"] == intent for item in projection["variants"]
    )


def _expectation(
    projection: dict[str, Any], case_id: str, variant_id: str
) -> dict[str, Any]:
    return next(
        item
        for item in projection["variants"]
        if item["case_id"] == case_id and item["variant_id"] == variant_id
    )
