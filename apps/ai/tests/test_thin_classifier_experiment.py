import json
from datetime import date
from pathlib import Path

import httpx
from app.evaluation.thin_classifier_experiment import (
    EXPERIMENT_ID,
    build_expectation_projection,
    run_experiment,
)
from app.evaluation.thin_intent_classifier import (
    StructuredThinIntentClassifier,
    TemporalIntent,
    ThinIntentClassification,
)
from pydantic import SecretStr

BENCHMARK = Path("/evaluation/benchmarks/dolved-care-engineering/v2")


def test_projection_uses_only_engineering_split_and_reconciles_application_state() -> (
    None
):
    corpus = json.loads((BENCHMARK / "compiled/corpus.json").read_text())
    split = json.loads((BENCHMARK / "splits/v1.json").read_text())

    projection = build_expectation_projection(corpus, split)

    assert projection["experiment_id"] == EXPERIMENT_ID
    assert projection["benchmark"]["case_count"] == 42
    assert len(projection["variants"]) == 126
    projected_cases = {item["case_id"] for item in projection["variants"]}
    assert projected_cases == set(split["assignments"]["engineering_tuning"])
    assert not projected_cases & set(split["assignments"]["threshold_calibration"])
    assert not projected_cases & set(split["assignments"]["sealed_held_out"])
    historical = _expectation(
        projection, "health-safety.accident.valid-at-date", "historical"
    )
    historical_expected = historical["expected"]
    assert isinstance(historical_expected, dict)
    assert historical_expected["explicit_date"] is None
    harbour = _expectation(projection, "pilot.applicability.bristol-conflict", "direct")
    harbour_expected = harbour["expected"]
    assert isinstance(harbour_expected, dict)
    assert harbour_expected["accepted_applicability_references"] == ["Harbour View"]


def test_adapter_does_not_semantically_retry_invalid_http_200() -> None:
    requests = 0

    def handler(request: httpx.Request) -> httpx.Response:
        nonlocal requests
        requests += 1
        return httpx.Response(
            200,
            json={"choices": [{"message": {"content": "{}"}}]},
            request=request,
        )

    classifier = StructuredThinIntentClassifier(
        api_url="https://provider.invalid",
        api_key=SecretStr("secret"),
        provider="openai",
        model="gpt-5-mini",
        client=httpx.Client(transport=httpx.MockTransport(handler)),
    )

    result = classifier.classify("Question")

    assert requests == 1
    assert result.failure is not None
    assert result.failure.category == "invalid_typed_classification"


def test_adapter_accepts_the_strict_json_date_contract() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
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
                                    "applicability_reference": None,
                                }
                            )
                        }
                    }
                ],
                "usage": {
                    "prompt_tokens": 10,
                    "completion_tokens": 5,
                    "prompt_tokens_details": {"cached_tokens": 2},
                },
            },
            request=request,
        )

    classifier = StructuredThinIntentClassifier(
        api_url="https://provider.invalid",
        api_key=SecretStr("secret"),
        provider="openai",
        model="gpt-5-mini",
        client=httpx.Client(transport=httpx.MockTransport(handler)),
    )

    result = classifier.classify("What applied on 15 January 2024?")

    assert result.classification is not None
    assert result.classification.explicit_date == date(2024, 1, 15)
    assert result.cached_input_tokens == 2


def test_run_is_resumable_without_recalling_finalised_observations(
    tmp_path: Path,
) -> None:
    class FakeClassifier:
        provider = "openai"
        model = "gpt-5-mini"
        calls = 0

        def classify(self, question: str):  # type: ignore[no-untyped-def]
            from app.evaluation.thin_intent_classifier import ClassifierCallResult

            self.calls += 1
            return ClassifierCallResult(
                classification=ThinIntentClassification(
                    temporal_intent=TemporalIntent.CURRENT,
                    explicit_date=date(2026, 1, 1) if "date" in question else None,
                    temporal_reference=None,
                    applicability_reference=None,
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
                    "accepted_applicability_references": [],
                },
                "overall_scoring_status": "SCORED",
                "review_reason": None,
            }
        ],
    }
    fake = FakeClassifier()
    arguments = {
        "classifier": fake,
        "projection": projection,
        "output_directory": tmp_path,
        "repository_commit": "a" * 40,
        "input_price_per_million": 0.25,
        "cached_input_price_per_million": 0.025,
        "output_price_per_million": 2.0,
        "exp0001_result": None,
    }

    first = run_experiment(**arguments)  # type: ignore[arg-type]
    second = run_experiment(**arguments)  # type: ignore[arg-type]

    assert fake.calls == 1
    assert first["aggregate"]["overall_accuracy"] == 1.0
    assert second["aggregate"]["total_variants"] == 1
    assert first == second


def _expectation(
    projection: dict[str, object], case_id: str, variant_id: str
) -> dict[str, object]:
    variants = projection["variants"]
    assert isinstance(variants, list)
    return next(
        item
        for item in variants
        if item["case_id"] == case_id and item["variant_id"] == variant_id
    )
