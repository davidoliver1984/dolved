import json
from datetime import date
from pathlib import Path
from typing import Any

import httpx
from pydantic import SecretStr

from app.evaluation.thin_intent_location_classifier import (
    LocationClassifierCallResult,
    StructuredThinIntentLocationClassifier,
    TemporalIntent,
    ThinIntentLocationClassification,
)
from app.evaluation.thin_location_classifier_experiment import (
    build_location_expectation_projection,
    run_location_experiment,
)

BENCHMARK = Path("/evaluation/benchmarks/dolved-care-engineering/v2")


def test_projection_is_engineering_only_and_exposes_contract_gap() -> None:
    corpus = json.loads((BENCHMARK / "compiled/corpus.json").read_text())
    split = json.loads((BENCHMARK / "splits/v1.json").read_text())

    projection = build_location_expectation_projection(corpus, split)

    cases = {item["case_id"] for item in projection["variants"]}
    assert cases == set(split["assignments"]["engineering_tuning"])
    assert not cases & set(split["assignments"]["threshold_calibration"])
    assert not cases & set(split["assignments"]["sealed_held_out"])
    assert len(projection["variants"]) == 126
    temporal_review = [
        item
        for item in projection["variants"]
        if item["expected"]["temporal_intent"] is None
    ]
    assert len(temporal_review) == 13
    inheritance = _expectation(
        projection, "pilot.applicability.regional-exeter", "inheritance"
    )
    assert inheritance["expected"]["location_references"] == [
        "South West",
        "Meadow Court",
    ]


def test_adapter_accepts_plural_locations_and_does_not_retry_invalid_200() -> None:
    requests = 0

    def valid_handler(request: httpx.Request) -> httpx.Response:
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "message": {
                            "content": json.dumps(
                                {
                                    "temporal_intent": "VALID_AT_DATE",
                                    "explicit_date": "2024-01-15",
                                    "temporal_reference": "15 January 2024",
                                    "location_references": [
                                        "South West",
                                        "Meadow Court",
                                    ],
                                }
                            )
                        }
                    }
                ],
                "usage": {"prompt_tokens": 10, "completion_tokens": 5},
            },
            request=request,
        )

    classifier = StructuredThinIntentLocationClassifier(
        api_url="https://provider.invalid",
        api_key=SecretStr("secret"),
        provider="openai",
        model="gpt-5-mini",
        client=httpx.Client(transport=httpx.MockTransport(valid_handler)),
    )
    result = classifier.classify("What applied on 15 January 2024 at Meadow Court?")
    assert result.classification is not None
    assert result.classification.explicit_date == date(2024, 1, 15)
    assert result.classification.location_references == ("South West", "Meadow Court")

    def invalid_handler(request: httpx.Request) -> httpx.Response:
        nonlocal requests
        requests += 1
        return httpx.Response(
            200,
            json={"choices": [{"message": {"content": "{}"}}]},
            request=request,
        )

    classifier = StructuredThinIntentLocationClassifier(
        api_url="https://provider.invalid",
        api_key=SecretStr("secret"),
        provider="openai",
        model="gpt-5-mini",
        client=httpx.Client(transport=httpx.MockTransport(invalid_handler)),
    )
    assert classifier.classify("Question").failure is not None
    assert requests == 1


def test_runner_resumes_without_provider_calls_and_reports_deterministically(
    tmp_path: Path,
) -> None:
    class FakeClassifier:
        provider = "openai"
        model = "gpt-5-mini"
        calls = 0

        def classify(self, question: str) -> LocationClassifierCallResult:
            self.calls += 1
            return LocationClassifierCallResult(
                classification=ThinIntentLocationClassification(
                    temporal_intent=TemporalIntent.CURRENT,
                    explicit_date=None,
                    temporal_reference=None,
                    location_references=(),
                ),
                latency_ms=1,
                input_tokens=10,
                cached_input_tokens=0,
                output_tokens=5,
            )

    projection = {
        "benchmark": {"id": "test", "version": "v1"},
        "reconciliations": [],
        "variants": [
            {
                "case_id": "case.one",
                "variant_id": "direct",
                "question": "Current question",
                "expected": {
                    "temporal_intent": "CURRENT",
                    "explicit_date": None,
                    "location_references": [],
                },
                "review": {"temporal": None, "locations": None},
            }
        ],
    }
    prior = {
        "aggregate": {
            "structured_output_reliability": 1.0,
            "temporal_intent_accuracy": 1.0,
            "temporal_intent_by_mode": {},
            "applicability_presence_precision": 1.0,
            "applicability_presence_recall": 1.0,
            "estimated_cost_usd": 0.1,
        }
    }
    fake = FakeClassifier()
    args = {
        "classifier": fake,
        "projection": projection,
        "output_directory": tmp_path,
        "repository_commit": "a" * 40,
        "input_price_per_million": 0.25,
        "cached_input_price_per_million": 0.025,
        "output_price_per_million": 2.0,
        "pln_exp0001_result": prior,
    }
    first = run_location_experiment(**args)  # type: ignore[arg-type]
    second = run_location_experiment(**args)  # type: ignore[arg-type]
    assert fake.calls == 1
    assert first == second


def _expectation(
    projection: dict[str, Any], case_id: str, variant_id: str
) -> dict[str, Any]:
    variants = projection["variants"]
    assert isinstance(variants, list)
    return next(
        item
        for item in variants
        if item["case_id"] == case_id and item["variant_id"] == variant_id
    )
