"""Projection, scoring and reporting for isolated PLN-EXP-0004."""

from __future__ import annotations

import html
import json
from collections import Counter
from datetime import UTC, datetime
from pathlib import Path
from statistics import mean
from typing import Any

from app.evaluation.canonical import content_digest
from app.evaluation.thin_historical_reference_classifier import (
    HistoricalClassifierCallResult,
    HistoricalReferenceClassification,
    StructuredHistoricalReferenceClassifier,
)
from app.evaluation.thin_location_classifier_experiment import (
    _accuracy,
    _fmt,
    _load,
    _location_examples,
    _observation_name,
    _percentile,
    _ratio,
    _write,
    _write_checksums,
)
from app.evaluation.thin_temporal_boundary_experiment import (
    build_temporal_boundary_projection,
)

EXPERIMENT_ID = "PLN-EXP-0004-thin-intent-historical-reference"
PERIOD_VALID_AT_DATE = {
    ("health-safety.accident.valid-at-date", "dated"),
    ("health-safety.accident.valid-at-date", "contrast"),
    ("hr.annual-leave.valid-at-date", "dated"),
    ("hr.annual-leave.valid-at-date", "contrast"),
    ("infection.outbreak.valid-before-withdrawal", "contrast"),
    ("medication.controlled-drugs.valid-at-date", "dated"),
    ("pilot.valid-at-date.medication-administration", "historical"),
}
HISTORICAL_REFERENCE = {
    ("health-safety.accident.valid-at-date", "historical"),
    ("health-safety.moving-handling.compare", "colloquial"),
    ("hr.annual-leave.valid-at-date", "old"),
    ("infection.outbreak.valid-before-withdrawal", "historical"),
    ("medication.controlled-drugs.valid-at-date", "historical"),
    ("medication.controlled-drugs.valid-at-date", "contrast"),
    ("pilot.valid-at-date.medication-administration", "colloquial"),
}


def build_historical_reference_projection(
    corpus: dict[str, Any], split: dict[str, Any]
) -> dict[str, Any]:
    projection = build_temporal_boundary_projection(corpus, split)
    projection["experiment_id"] = EXPERIMENT_ID
    projection["scoring"]["temporal_intent"] = (
        "exact four-intent enum equality; month/year and year-only periods are "
        "VALID_AT_DATE with no invented explicit day"
    )
    reconciled = 0
    for variant in projection["variants"]:
        key = (variant["case_id"], variant["variant_id"])
        if key in PERIOD_VALID_AT_DATE:
            intent = "VALID_AT_DATE"
            reason = (
                "The question supplies a sufficiently explicit calendar period; no exact "
                "day is invented and explicit_date remains null."
            )
        elif key in HISTORICAL_REFERENCE:
            intent = "HISTORICAL_REFERENCE"
            reason = (
                "The question identifies one historical policy/document state without "
                "requesting comparison or supplying a date-qualified period."
            )
        else:
            continue
        if variant["expected"]["temporal_intent"] is not None:
            raise ValueError(
                "PLN-EXP-0004 historical reconciliation target was already scored"
            )
        variant["expected"]["temporal_intent"] = intent
        variant["review"]["temporal"] = None
        projection["reconciliations"].append(
            {
                "case_id": variant["case_id"],
                "variant_id": variant["variant_id"],
                "field": "temporal_intent",
                "original": None,
                "projected": intent,
                "reason": reason,
            }
        )
        reconciled += 1
    if reconciled != 14 or any(
        item["expected"]["temporal_intent"] is None for item in projection["variants"]
    ):
        raise ValueError(
            "PLN-EXP-0004 must truthfully score all 14 historical-gap variants"
        )
    return projection


def run_historical_reference_experiment(
    *,
    classifier: StructuredHistoricalReferenceClassifier,
    projection: dict[str, Any],
    output_directory: Path,
    repository_commit: str,
    input_price_per_million: float,
    cached_input_price_per_million: float,
    output_price_per_million: float,
    prior_results: dict[str, dict[str, Any]],
    contract_schema: dict[str, Any],
) -> dict[str, Any]:
    output_directory.mkdir(parents=True, exist_ok=True)
    observations_directory = output_directory / "observations"
    observations_directory.mkdir(exist_ok=True)
    config = {
        "schema_version": "v1",
        "experiment_id": EXPERIMENT_ID,
        "repository_commit": repository_commit,
        "benchmark": projection["benchmark"],
        "expectation_digest": content_digest(projection),
        "provider": classifier.provider,
        "model": classifier.model,
        "adapter": "isolated-thin-historical-reference-structured-chat-v1",
        "contract": (
            "contracts/evaluation/thin-intent-historical-reference/"
            "v1/classification.schema.json"
        ),
        "changed_variable": "add HISTORICAL_REFERENCE temporal intent",
        "text_capture_mode": "BENCHMARK_TEXT",
        "pricing_usd_per_million_tokens": {
            "input": input_price_per_million,
            "cached_input": cached_input_price_per_million,
            "output": output_price_per_million,
        },
    }
    config_path = output_directory / "config.json"
    if config_path.exists() and _load(config_path) != config:
        raise ValueError("existing PLN-EXP-0004 lineage does not match")
    _write(config_path, config)
    _write(output_directory / "expectations.json", projection)
    _write(output_directory / "classification.schema.json", contract_schema)
    for expectation in projection["variants"]:
        path = observations_directory / _observation_name(expectation)
        if path.exists():
            _validate_observation(_load(path), expectation, config)
            continue
        call = classifier.classify(str(expectation["question"]))
        _write(path, _observation(expectation, call, config))
        if call.failure is not None and call.failure.systemic:
            raise RuntimeError(f"systemic classifier failure: {call.failure.category}")
    observations = [
        _load(observations_directory / _observation_name(item))
        for item in projection["variants"]
    ]
    result = _result(config, projection, observations, prior_results)
    _write(output_directory / "result.json", result)
    (output_directory / "report.md").write_text(_markdown(result))
    (output_directory / "report.html").write_text(_html(result))
    notes = output_directory / "notes.md"
    if not notes.exists():
        notes.write_text("# Human notes\n\nNo production adoption is approved.\n")
    _write_checksums(output_directory)
    return result


def _observation(
    expectation: dict[str, Any],
    call: HistoricalClassifierCallResult,
    config: dict[str, Any],
) -> dict[str, Any]:
    actual = call.classification
    return {
        "schema_version": "v1",
        "experiment_id": EXPERIMENT_ID,
        "expectation_digest": config["expectation_digest"],
        "provider": config["provider"],
        "model": config["model"],
        "case_id": expectation["case_id"],
        "variant_id": expectation["variant_id"],
        "question": expectation["question"],
        "expected": expectation["expected"],
        "classification": actual.model_dump(mode="json") if actual else None,
        "failure": call.failure.model_dump(mode="json") if call.failure else None,
        "scores": _score(expectation, actual),
        "latency_ms": call.latency_ms,
        "usage": {
            "input_tokens": call.input_tokens,
            "cached_input_tokens": call.cached_input_tokens,
            "output_tokens": call.output_tokens,
        },
        "observed_at": datetime.now(UTC).isoformat(),
        "review": expectation["review"],
    }


def _score(
    expectation: dict[str, Any], actual: HistoricalReferenceClassification | None
) -> dict[str, Any]:
    expected = expectation["expected"]
    expected_locations = expected["location_references"]
    if actual is None:
        return {
            "temporal_intent_correct": False,
            "explicit_date_correct": False,
            "location_exact_match": False if expected_locations is not None else None,
            "location_true_positives": 0,
            "location_false_positives": 0,
            "location_false_negatives": len(expected_locations or []),
        }
    predicted = {_normalise(value) for value in actual.location_references}
    truth = {_normalise(value) for value in expected_locations or []}
    return {
        "temporal_intent_correct": (
            actual.temporal_intent.value == expected["temporal_intent"]
        ),
        "explicit_date_correct": (
            actual.explicit_date.isoformat() if actual.explicit_date else None
        )
        == expected["explicit_date"],
        "location_exact_match": (
            predicted == truth if expected_locations is not None else None
        ),
        "location_true_positives": len(predicted & truth),
        "location_false_positives": len(predicted - truth),
        "location_false_negatives": len(truth - predicted),
    }


def _result(
    config: dict[str, Any],
    projection: dict[str, Any],
    observations: list[dict[str, Any]],
    prior: dict[str, dict[str, Any]],
) -> dict[str, Any]:
    valid = [item for item in observations if item["classification"] is not None]
    modes = ("CURRENT", "COMPARE", "VALID_AT_DATE", "HISTORICAL_REFERENCE")
    confusion = {
        expected: {
            predicted: sum(
                item["expected"]["temporal_intent"] == expected
                and item["classification"] is not None
                and item["classification"]["temporal_intent"] == predicted
                for item in observations
            )
            for predicted in modes
        }
        for expected in modes
    }
    locations = [
        item
        for item in observations
        if item["scores"]["location_exact_match"] is not None
    ]
    tp = sum(item["scores"]["location_true_positives"] for item in locations)
    fp = sum(item["scores"]["location_false_positives"] for item in locations)
    fn = sum(item["scores"]["location_false_negatives"] for item in locations)
    false_compare = sum(
        item["expected"]["temporal_intent"] != "COMPARE"
        and item["classification"] is not None
        and item["classification"]["temporal_intent"] == "COMPARE"
        for item in observations
    )
    false_historical = sum(
        item["expected"]["temporal_intent"] != "HISTORICAL_REFERENCE"
        and item["classification"] is not None
        and item["classification"]["temporal_intent"] == "HISTORICAL_REFERENCE"
        for item in observations
    )
    pln3 = {
        (item["case_id"], item["variant_id"]): item
        for item in prior["PLN-EXP-0003"]["variants"]
    }
    prior_scored = [
        item
        for item in observations
        if pln3[(item["case_id"], item["variant_id"])]["expected"]["temporal_intent"]
        is not None
    ]
    prior_regressions = [
        item for item in prior_scored if not item["scores"]["temporal_intent_correct"]
    ]
    prior_review = [item for item in observations if item not in prior_scored]
    latencies = sorted(float(item["latency_ms"]) for item in observations)
    usage = {
        key: sum(int(item["usage"][key]) for item in observations)
        for key in ("input_tokens", "cached_input_tokens", "output_tokens")
    }
    pricing = config["pricing_usd_per_million_tokens"]
    cost = (
        (usage["input_tokens"] - usage["cached_input_tokens"]) * pricing["input"]
        + usage["cached_input_tokens"] * pricing["cached_input"]
        + usage["output_tokens"] * pricing["output"]
    ) / 1_000_000
    aggregate = {
        "total_variants": len(observations),
        "valid_structured_responses": len(valid),
        "invalid_structured_responses": len(observations) - len(valid),
        "structured_output_reliability": _ratio(len(valid), len(observations)),
        "temporal_scored_variants": len(observations),
        "temporal_review_variants": 0,
        "temporal_intent_accuracy": _accuracy(observations, "temporal_intent_correct"),
        "temporal_intent_by_mode": {
            mode: _accuracy(
                [
                    item
                    for item in observations
                    if item["expected"]["temporal_intent"] == mode
                ],
                "temporal_intent_correct",
            )
            for mode in modes
        },
        "confusion_matrix": confusion,
        "false_compare_count": false_compare,
        "genuine_compare_recall": _ratio(confusion["COMPARE"]["COMPARE"], 22),
        "false_historical_reference_count": false_historical,
        "previously_correct_pln_exp0003_regression_count": len(prior_regressions),
        "previously_correct_pln_exp0003_regressions": [
            {"case_id": item["case_id"], "variant_id": item["variant_id"]}
            for item in prior_regressions
        ],
        "prior_review_variants_now_scorable": len(prior_review),
        "prior_review_variants_correct": sum(
            item["scores"]["temporal_intent_correct"] for item in prior_review
        ),
        "explicit_date_accuracy": _accuracy(observations, "explicit_date_correct"),
        "false_date_hallucination_count": sum(
            item["expected"]["explicit_date"] is None
            and item["classification"] is not None
            and item["classification"]["explicit_date"] is not None
            for item in observations
        ),
        "location_reference_precision": _ratio(tp, tp + fp),
        "location_reference_recall": _ratio(tp, tp + fn),
        "location_exact_match_accuracy": _accuracy(locations, "location_exact_match"),
        "location_false_positive_examples": _location_examples(
            locations, "false_positive"
        ),
        "location_missed_examples": _location_examples(locations, "missed"),
        "failure_categories": dict(
            sorted(
                Counter(
                    item["failure"]["category"]
                    for item in observations
                    if item["failure"] is not None
                ).items()
            )
        ),
        "latency_ms": {
            "mean": mean(latencies),
            "p50": _percentile(latencies, 0.5),
            "p95": _percentile(latencies, 0.95),
        },
        "usage": usage,
        "estimated_cost_usd": cost,
    }
    return {
        "schema_version": "v1",
        "experiment_id": EXPERIMENT_ID,
        "executed_at": max(str(item["observed_at"]) for item in observations),
        "lineage": config,
        "aggregate": aggregate,
        "comparison": _comparison(prior, aggregate),
        "reconciliation_count": len(projection["reconciliations"]),
        "variants": observations,
    }


def _comparison(
    prior: dict[str, dict[str, Any]], current: dict[str, Any]
) -> dict[str, Any]:
    output: dict[str, Any] = {
        "comparability_note": (
            "PLN-EXP-0004 adds HISTORICAL_REFERENCE and scores all 14 prior review-only "
            "variants; its 126-variant temporal denominator is therefore broader than PLN-EXP-0003."
        )
    }
    for name, result in prior.items():
        aggregate = result["aggregate"]
        false_compare = aggregate.get("false_compare_count")
        if false_compare is None:
            false_compare = sum(
                item["expected"].get("temporal_intent") not in {None, "COMPARE"}
                and item.get("classification") is not None
                and item["classification"]["temporal_intent"] == "COMPARE"
                for item in result.get("variants", [])
            )
        output[name] = {
            "structured_output_reliability": aggregate["structured_output_reliability"],
            "temporal_intent_accuracy": aggregate["temporal_intent_accuracy"],
            "temporal_intent_by_mode": aggregate["temporal_intent_by_mode"],
            "false_compare_count": false_compare,
            "temporal_review_variants": aggregate.get("temporal_review_variants", 0),
            "estimated_cost_usd": aggregate["estimated_cost_usd"],
        }
    output["PLN-EXP-0004"] = {
        "structured_output_reliability": current["structured_output_reliability"],
        "temporal_intent_accuracy": current["temporal_intent_accuracy"],
        "temporal_intent_by_mode": current["temporal_intent_by_mode"],
        "false_compare_count": current["false_compare_count"],
        "temporal_review_variants": current["temporal_review_variants"],
        "estimated_cost_usd": current["estimated_cost_usd"],
    }
    return output


def _markdown(result: dict[str, Any]) -> str:
    aggregate = result["aggregate"]
    lines = [
        f"# {EXPERIMENT_ID}",
        "",
        "Isolated engineering-only four-intent classifier experiment.",
        "",
        "## Headline",
        "",
        f"- Structured reliability: `{_fmt(aggregate['structured_output_reliability'])}`",
        f"- Temporal accuracy: `{_fmt(aggregate['temporal_intent_accuracy'])}` over 126 variants",
        f"- CURRENT / COMPARE / VALID_AT_DATE / HISTORICAL_REFERENCE: `{_fmt(aggregate['temporal_intent_by_mode']['CURRENT'])}` / `{_fmt(aggregate['temporal_intent_by_mode']['COMPARE'])}` / `{_fmt(aggregate['temporal_intent_by_mode']['VALID_AT_DATE'])}` / `{_fmt(aggregate['temporal_intent_by_mode']['HISTORICAL_REFERENCE'])}`",
        f"- False COMPARE / false HISTORICAL_REFERENCE: `{aggregate['false_compare_count']}` / `{aggregate['false_historical_reference_count']}`",
        f"- Prior review-only variants correct: `{aggregate['prior_review_variants_correct']}/{aggregate['prior_review_variants_now_scorable']}`",
        f"- PLN-EXP-0003 regression count: `{aggregate['previously_correct_pln_exp0003_regression_count']}`",
        f"- Location precision / recall: `{_fmt(aggregate['location_reference_precision'])}` / `{_fmt(aggregate['location_reference_recall'])}`",
        f"- Estimated cost: `${aggregate['estimated_cost_usd']:.6f}`",
        "",
        "## Four-experiment comparison",
        "",
        "```json",
        json.dumps(result["comparison"], indent=2, sort_keys=True),
        "```",
        "",
        "## Confusion matrix",
        "",
        "```json",
        json.dumps(aggregate["confusion_matrix"], indent=2),
        "```",
        "",
        "## Per-variant results",
        "",
    ]
    for item in result["variants"]:
        actual = item["classification"] or {}
        lines.append(
            f"- `{item['case_id']} / {item['variant_id']}`: "
            f"expected `{item['expected']['temporal_intent']}`, "
            f"actual `{actual.get('temporal_intent', 'INVALID')}`, "
            f"correct `{item['scores']['temporal_intent_correct']}`"
        )
    return "\n".join(lines) + "\n"


def _html(result: dict[str, Any]) -> str:
    aggregate = result["aggregate"]
    rows = "".join(
        "<tr>"
        f"<td>{html.escape(item['case_id'])}</td>"
        f"<td>{html.escape(item['variant_id'])}</td>"
        f"<td>{html.escape(item['question'])}</td>"
        f"<td><pre>{html.escape(json.dumps(item['expected'], indent=2))}</pre></td>"
        f"<td><pre>{html.escape(json.dumps(item['classification'], indent=2))}</pre></td>"
        f"<td><pre>{html.escape(json.dumps(item['scores'], indent=2))}</pre></td></tr>"
        for item in result["variants"]
    )
    summary = html.escape(
        json.dumps(
            {
                "lineage": result["lineage"],
                "aggregate": aggregate,
                "comparison": result["comparison"],
            },
            indent=2,
        )
    )
    return f"""<!doctype html><html lang=en><head><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title>{EXPERIMENT_ID}</title><style>body{{font-family:system-ui;max-width:1500px;margin:auto;padding:2rem;background:#f6f7f9;color:#18202a}}section,table{{background:white}}section{{padding:1rem;margin:1rem 0}}table{{border-collapse:collapse;width:100%;font-size:.82rem}}th,td{{border:1px solid #d7dce2;padding:.5rem;vertical-align:top;text-align:left}}pre{{white-space:pre-wrap;margin:0}}.metrics{{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem}}.metric{{padding:1rem;background:#eef3f8}}</style></head><body><h1>{EXPERIMENT_ID}</h1><section class=metrics><div class=metric><strong>Reliability</strong><br>{_fmt(aggregate["structured_output_reliability"])}</div><div class=metric><strong>Temporal</strong><br>{_fmt(aggregate["temporal_intent_accuracy"])}</div><div class=metric><strong>Historical</strong><br>{_fmt(aggregate["temporal_intent_by_mode"]["HISTORICAL_REFERENCE"])}</div><div class=metric><strong>Prior regressions</strong><br>{aggregate["previously_correct_pln_exp0003_regression_count"]}</div></section><section><h2>Lineage, aggregate and comparison</h2><pre>{summary}</pre></section><h2>Per-variant results</h2><table><thead><tr><th>Case</th><th>Variant</th><th>Question</th><th>Expected</th><th>Actual</th><th>Scores</th></tr></thead><tbody>{rows}</tbody></table></body></html>"""


def _normalise(value: str) -> str:
    return " ".join(value.casefold().split())


def _validate_observation(
    observation: dict[str, Any], expectation: dict[str, Any], config: dict[str, Any]
) -> None:
    required = {
        "experiment_id": EXPERIMENT_ID,
        "expectation_digest": config["expectation_digest"],
        "provider": config["provider"],
        "model": config["model"],
        "case_id": expectation["case_id"],
        "variant_id": expectation["variant_id"],
        "question": expectation["question"],
        "expected": expectation["expected"],
    }
    if any(observation.get(key) != value for key, value in required.items()):
        raise ValueError("persisted PLN-EXP-0004 observation lineage mismatch")
