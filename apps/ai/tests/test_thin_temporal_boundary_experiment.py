import json
from copy import deepcopy
from pathlib import Path
from typing import Any

import httpx
import pytest
from pydantic import SecretStr

from app.evaluation.thin_intent_location_classifier import (
    LocationClassifierCallResult,
    StructuredThinIntentLocationClassifier,
    TemporalIntent,
    ThinIntentLocationClassification,
)
from app.evaluation.thin_temporal_boundary_experiment import (
    build_temporal_boundary_projection,
    run_temporal_boundary_experiment,
)

BENCHMARK = Path("/evaluation/benchmarks/dolved-care-engineering/v2")


def test_projection_refines_only_temporal_boundary_on_engineering_split() -> None:
    corpus = json.loads((BENCHMARK / "compiled/corpus.json").read_text())
    split = json.loads((BENCHMARK / "splits/v1.json").read_text())

    projection = build_temporal_boundary_projection(corpus, split)

    cases = {item["case_id"] for item in projection["variants"]}
    assert cases == set(split["assignments"]["engineering_tuning"])
    assert not cases & set(split["assignments"]["threshold_calibration"])
    assert not cases & set(split["assignments"]["sealed_held_out"])
    assert len(projection["variants"]) == 126
    assert _mode_count(projection, "CURRENT") == 88
    assert _mode_count(projection, "COMPARE") == 22
    assert _mode_count(projection, "VALID_AT_DATE") == 2
    assert _mode_count(projection, None) == 14
    old_only = _expectation(
        projection, "health-safety.moving-handling.compare", "colloquial"
    )
    assert old_only["expected"]["temporal_intent"] is None
    assert old_only["expected"]["location_references"] == []
    replacement = _expectation(
        projection, "pilot.current.withdrawn-before-authority", "scheduled"
    )
    resurrection = _expectation(
        projection, "pilot.current.withdrawn-no-resurrection", "withdrawn"
    )
    assert replacement["expected"]["temporal_intent"] == "COMPARE"
    assert resurrection["expected"]["temporal_intent"] == "COMPARE"


def test_adapter_uses_the_injected_experiment_prompt() -> None:
    captured: dict[str, Any] = {}

    def handler(request: httpx.Request) -> httpx.Response:
        captured.update(json.loads(request.content))
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "message": {
                            "content": json.dumps(
                                {
                                    "temporal_intent": "CURRENT",
                                    "explicit_date": None,
                                    "temporal_reference": None,
                                    "location_references": [],
                                }
                            )
                        }
                    }
                ]
            },
            request=request,
        )

    classifier = StructuredThinIntentLocationClassifier(
        api_url="https://provider.invalid",
        api_key=SecretStr("secret"),
        provider="openai",
        model="gpt-5-mini",
        system_prompt="frozen PLN-EXP-0003 prompt",
        client=httpx.Client(transport=httpx.MockTransport(handler)),
    )

    assert classifier.classify("Question").classification is not None
    assert captured["messages"][0]["content"] == "frozen PLN-EXP-0003 prompt"


def test_runner_resumes_and_preserves_prior_experiment_comparison(
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

    projection: dict[str, Any] = {
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
    aggregate = {
        "structured_output_reliability": 1.0,
        "temporal_intent_accuracy": 1.0,
        "temporal_intent_by_mode": {},
        "estimated_cost_usd": 0.1,
    }
    first = {"aggregate": aggregate}
    second = {"aggregate": aggregate, "variants": []}
    fake = FakeClassifier()
    arguments: dict[str, Any] = {
        "classifier": fake,
        "projection": projection,
        "output_directory": tmp_path,
        "repository_commit": "a" * 40,
        "input_price_per_million": 0.25,
        "cached_input_price_per_million": 0.025,
        "output_price_per_million": 2.0,
        "pln_exp0001_result": first,
        "pln_exp0002_result": second,
        "contract_schema": {"type": "object"},
    }

    initial = run_temporal_boundary_experiment(**arguments)  # type: ignore[arg-type]
    resumed = run_temporal_boundary_experiment(**arguments)  # type: ignore[arg-type]

    assert fake.calls == 1
    assert initial == resumed
    assert initial["comparison"]["PLN-EXP-0003"]["false_compare_count"] == 0

    revised: dict[str, Any] = deepcopy(projection)
    revised["variants"][0]["expected"]["temporal_intent"] = "COMPARE"
    revised_arguments = {**arguments, "projection": revised}
    with pytest.raises(ValueError, match="lineage does not match"):
        run_temporal_boundary_experiment(**revised_arguments)  # type: ignore[arg-type]
    reconciled = run_temporal_boundary_experiment(  # type: ignore[arg-type]
        **revised_arguments,
        reconcile_existing_expectations=True,
    )
    assert fake.calls == 1
    assert reconciled["variants"][0]["expected"]["temporal_intent"] == "COMPARE"
    assert reconciled["variants"][0]["classification"]["temporal_intent"] == "CURRENT"


def _mode_count(projection: dict[str, Any], mode: str | None) -> int:
    return sum(
        item["expected"]["temporal_intent"] == mode for item in projection["variants"]
    )


def _expectation(
    projection: dict[str, Any], case_id: str, variant_id: str
) -> dict[str, Any]:
    return next(
        item
        for item in projection["variants"]
        if item["case_id"] == case_id and item["variant_id"] == variant_id
    )
