"""Projection, scoring and reporting for PLN-EXP-0002."""

from __future__ import annotations

import hashlib
import html
import json
import math
from collections import Counter
from datetime import UTC, datetime
from pathlib import Path
from statistics import mean
from typing import Any

from app.evaluation.canonical import canonical_json, content_digest
from app.evaluation.thin_intent_location_classifier import (
    LocationClassifierCallResult,
    StructuredThinIntentLocationClassifier,
    ThinIntentLocationClassification,
)

EXPERIMENT_ID = "PLN-EXP-0002-thin-intent-location-classifier"
EXACT_DATES = {
    ("infection.outbreak.valid-before-withdrawal", "dated"): "2026-01-01",
    ("pilot.valid-at-date.medication-administration", "dated"): "2024-06-01",
}
LOCATION_EXPECTATIONS: dict[tuple[str, str], tuple[str, ...] | None] = {
    ("hr.lone-worker.coventry-overdue", "alias"): ("Coventry",),
    ("hr.lone-worker.coventry-overdue", "timing"): ("Midlands",),
    ("hr.lone-worker.coventry-overdue", "colloquial"): (),
    ("pilot.applicability.ambiguous-home", "ambiguous"): ("the home",),
    ("pilot.applicability.ambiguous-home", "pronoun"): None,
    ("pilot.applicability.ambiguous-home", "underspecified"): ("our care home",),
    ("pilot.applicability.bristol-conflict", "direct"): ("Harbour View",),
    ("pilot.applicability.bristol-conflict", "conflict"): ("South West", "Bristol"),
    ("pilot.applicability.bristol-conflict", "multi-document"): ("the Bristol home",),
    ("pilot.applicability.regional-exeter", "alias"): ("the Exeter home",),
    ("pilot.applicability.regional-exeter", "canonical"): ("Meadow Court",),
    ("pilot.applicability.regional-exeter", "inheritance"): (
        "South West",
        "Meadow Court",
    ),
    ("pilot.location-alias.bristol", "alias"): ("the Bristol home",),
    ("pilot.location-alias.bristol", "canonical"): ("Harbour View",),
    ("pilot.location-alias.bristol", "colloquial"): ("Bristol",),
}
LOCATION_REVIEW = {
    ("pilot.applicability.ambiguous-home", "pronoun"): (
        "Standalone 'there' has no supplied conversational antecedent."
    )
}


def build_location_expectation_projection(
    corpus: dict[str, Any], split: dict[str, Any]
) -> dict[str, Any]:
    engineering = tuple(split["assignments"]["engineering_tuning"])
    cases = {item["case_id"]: item for item in corpus["cases"]}
    if len(engineering) != 42 or any(case_id not in cases for case_id in engineering):
        raise ValueError("PLN-EXP-0002 requires exactly 42 engineering cases")
    variants: list[dict[str, Any]] = []
    reconciliations: list[dict[str, Any]] = []
    for case_id in engineering:
        case = cases[case_id]
        original_intent = str(case["planner_expectation"]["temporal_mode"])
        for variant in case["variants"]:
            variant_id = str(variant["variant_id"])
            key = (case_id, variant_id)
            expected_date = EXACT_DATES.get(key)
            temporal_review = (
                original_intent == "VALID_AT_DATE" and expected_date is None
            )
            locations = LOCATION_EXPECTATIONS.get(key, ())
            location_review = LOCATION_REVIEW.get(key)
            variants.append(
                {
                    "case_id": case_id,
                    "variant_id": variant_id,
                    "question": variant["question"],
                    "expected": {
                        "temporal_intent": (
                            None if temporal_review else original_intent
                        ),
                        "explicit_date": expected_date,
                        "location_references": (
                            list(locations) if locations is not None else None
                        ),
                    },
                    "review": {
                        "temporal": (
                            "No allowed temporal class represents non-date historical intent."
                            if temporal_review
                            else None
                        ),
                        "locations": location_review,
                    },
                }
            )
            original_date = case["planner_expectation"].get("valid_at")
            if original_date and expected_date is None:
                reconciliations.append(
                    {
                        "case_id": case_id,
                        "variant_id": variant_id,
                        "field": "explicit_date",
                        "original": original_date,
                        "projected": None,
                        "reason": "Hidden/partial application-state date is not an exact day in the question.",
                    }
                )
            if temporal_review:
                reconciliations.append(
                    {
                        "case_id": case_id,
                        "variant_id": variant_id,
                        "field": "temporal_intent",
                        "original": original_intent,
                        "projected": None,
                        "reason": "The three-value contract has no historical-without-exact-date class.",
                    }
                )
            original_location = (
                case["planner_expectation"]
                .get("applicability_reference", {})
                .get("input")
            )
            projected_locations = list(locations) if locations is not None else None
            if original_location is not None and projected_locations != [
                original_location
            ]:
                reconciliations.append(
                    {
                        "case_id": case_id,
                        "variant_id": variant_id,
                        "field": "location_references",
                        "original": original_location,
                        "projected": projected_locations,
                        "reason": "Projection preserves all and only location wording in this question.",
                    }
                )
    if len(variants) != 126:
        raise ValueError("PLN-EXP-0002 requires exactly 126 variants")
    return {
        "schema_version": "v1",
        "experiment_id": EXPERIMENT_ID,
        "benchmark": {
            "id": corpus["benchmark_id"],
            "version": corpus["corpus_version"],
            "digest": content_digest(corpus),
            "split_version": split["split_version"],
            "split": "engineering_tuning",
            "case_count": 42,
            "variant_count": 126,
        },
        "scoring": {
            "temporal_intent": "exact enum equality; unrepresentable historical variants are review-only",
            "explicit_date": "exact ISO date equality; partial/hidden dates must remain null",
            "locations": "case-insensitive whitespace-normalised set equality and micro precision/recall",
            "temporal_reference": "retained for inspection but not scored",
        },
        "reconciliations": reconciliations,
        "variants": variants,
    }


def run_location_experiment(
    *,
    classifier: StructuredThinIntentLocationClassifier,
    projection: dict[str, Any],
    output_directory: Path,
    repository_commit: str,
    input_price_per_million: float,
    cached_input_price_per_million: float,
    output_price_per_million: float,
    pln_exp0001_result: dict[str, Any],
    contract_schema: dict[str, Any] | None = None,
) -> dict[str, Any]:
    output_directory.mkdir(parents=True, exist_ok=True)
    observation_directory = output_directory / "observations"
    observation_directory.mkdir(exist_ok=True)
    config = {
        "schema_version": "v1",
        "experiment_id": EXPERIMENT_ID,
        "repository_commit": repository_commit,
        "benchmark": projection["benchmark"],
        "expectation_digest": content_digest(projection),
        "provider": classifier.provider,
        "model": classifier.model,
        "adapter": "isolated-thin-intent-location-structured-chat-v1",
        "contract": "contracts/evaluation/thin-intent-location-classifier/v1/classification.schema.json",
        "text_capture_mode": "BENCHMARK_TEXT",
        "pricing_usd_per_million_tokens": {
            "input": input_price_per_million,
            "cached_input": cached_input_price_per_million,
            "output": output_price_per_million,
        },
    }
    config_path = output_directory / "config.json"
    if config_path.exists() and _load(config_path) != config:
        raise ValueError("existing PLN-EXP-0002 lineage does not match")
    _write(config_path, config)
    _write(output_directory / "expectations.json", projection)
    if contract_schema is not None:
        _write(output_directory / "classification.schema.json", contract_schema)
    for expectation in projection["variants"]:
        path = observation_directory / _observation_name(expectation)
        if path.exists():
            _validate_observation(_load(path), expectation, config)
            continue
        call = classifier.classify(str(expectation["question"]))
        _write(path, _observation(expectation, call, config))
        if call.failure is not None and call.failure.systemic:
            raise RuntimeError(f"systemic classifier failure: {call.failure.category}")
    observations = [
        _load(observation_directory / _observation_name(item))
        for item in projection["variants"]
    ]
    result = _result(config, projection, observations, pln_exp0001_result)
    _write(output_directory / "result.json", result)
    (output_directory / "report.md").write_text(_markdown(result))
    (output_directory / "report.html").write_text(_html(result))
    notes = output_directory / "notes.md"
    if not notes.exists():
        notes.write_text(
            "# Human notes\n\nNo production adoption or further prompt/model experiment is approved.\n"
        )
    _write_checksums(output_directory)
    return result


def _observation(
    expectation: dict[str, Any],
    call: LocationClassifierCallResult,
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
    expectation: dict[str, Any], actual: ThinIntentLocationClassification | None
) -> dict[str, Any]:
    expected = expectation["expected"]
    expected_locations = expected["location_references"]
    if actual is None:
        return {
            "temporal_intent_correct": False if expected["temporal_intent"] else None,
            "explicit_date_correct": False,
            "location_exact_match": False if expected_locations is not None else None,
            "location_true_positives": 0,
            "location_false_positives": 0,
            "location_false_negatives": len(expected_locations or []),
        }
    predicted = {_normalise(item) for item in actual.location_references}
    truth = {_normalise(item) for item in expected_locations or []}
    return {
        "temporal_intent_correct": (
            actual.temporal_intent.value == expected["temporal_intent"]
            if expected["temporal_intent"] is not None
            else None
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
    prior: dict[str, Any],
) -> dict[str, Any]:
    valid = [item for item in observations if item["classification"] is not None]
    temporal_scored = [
        item
        for item in observations
        if item["scores"]["temporal_intent_correct"] is not None
    ]
    location_scored = [
        item
        for item in observations
        if item["scores"]["location_exact_match"] is not None
    ]
    modes = ("CURRENT", "COMPARE", "VALID_AT_DATE")
    confusion = {
        expected: {
            predicted: sum(
                item["expected"]["temporal_intent"] == expected
                and item["classification"] is not None
                and item["classification"]["temporal_intent"] == predicted
                for item in temporal_scored
            )
            for predicted in modes
        }
        for expected in modes
    }
    tp = sum(item["scores"]["location_true_positives"] for item in location_scored)
    fp = sum(item["scores"]["location_false_positives"] for item in location_scored)
    fn = sum(item["scores"]["location_false_negatives"] for item in location_scored)
    latencies = sorted(float(item["latency_ms"]) for item in observations)
    usage = {
        key: sum(int(item["usage"][key]) for item in observations)
        for key in ("input_tokens", "cached_input_tokens", "output_tokens")
    }
    pricing = config["pricing_usd_per_million_tokens"]
    estimated_cost = (
        (usage["input_tokens"] - usage["cached_input_tokens"]) * pricing["input"]
        + usage["cached_input_tokens"] * pricing["cached_input"]
        + usage["output_tokens"] * pricing["output"]
    ) / 1_000_000
    aggregate = {
        "total_variants": len(observations),
        "valid_structured_responses": len(valid),
        "invalid_structured_responses": len(observations) - len(valid),
        "structured_output_reliability": _ratio(len(valid), len(observations)),
        "temporal_scored_variants": len(temporal_scored),
        "temporal_review_variants": len(observations) - len(temporal_scored),
        "temporal_intent_accuracy": _accuracy(
            temporal_scored, "temporal_intent_correct"
        ),
        "temporal_intent_by_mode": {
            mode: _accuracy(
                [
                    item
                    for item in temporal_scored
                    if item["expected"]["temporal_intent"] == mode
                ],
                "temporal_intent_correct",
            )
            for mode in modes
        },
        "confusion_matrix": confusion,
        "explicit_date_accuracy": _accuracy(observations, "explicit_date_correct"),
        "false_date_hallucination_count": sum(
            item["expected"]["explicit_date"] is None
            and item["classification"] is not None
            and item["classification"]["explicit_date"] is not None
            for item in observations
        ),
        "location_reference_precision": _ratio(tp, tp + fp),
        "location_reference_recall": _ratio(tp, tp + fn),
        "location_exact_match_accuracy": _accuracy(
            location_scored, "location_exact_match"
        ),
        "location_scored_variants": len(location_scored),
        "location_true_positives": tp,
        "location_false_positives": fp,
        "location_false_negatives": fn,
        "location_false_positive_examples": _location_examples(
            location_scored, "false_positive"
        ),
        "location_missed_examples": _location_examples(location_scored, "missed"),
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
        "estimated_cost_usd": estimated_cost,
    }
    return {
        "schema_version": "v1",
        "experiment_id": EXPERIMENT_ID,
        "executed_at": max(str(item["observed_at"]) for item in observations),
        "lineage": config,
        "aggregate": aggregate,
        "comparison_with_pln_exp0001": _comparison(prior, aggregate),
        "reconciliation_count": len(projection["reconciliations"]),
        "variants": observations,
    }


def _comparison(prior: dict[str, Any], current: dict[str, Any]) -> dict[str, Any]:
    previous = prior["aggregate"]
    return {
        "comparability_note": (
            "PLN-EXP-0002 changes VALID_AT_DATE to exact-day-only and replaces a singular "
            "applicability reference with plural location extraction. Review denominators."
        ),
        "pln_exp0001": {
            "structured_output_reliability": previous["structured_output_reliability"],
            "temporal_intent_accuracy": previous["temporal_intent_accuracy"],
            "temporal_intent_by_mode": previous["temporal_intent_by_mode"],
            "location_presence_precision": previous["applicability_presence_precision"],
            "location_presence_recall": previous["applicability_presence_recall"],
            "estimated_cost_usd": previous["estimated_cost_usd"],
        },
        "pln_exp0002": {
            "structured_output_reliability": current["structured_output_reliability"],
            "temporal_intent_accuracy": current["temporal_intent_accuracy"],
            "temporal_intent_by_mode": current["temporal_intent_by_mode"],
            "location_reference_precision": current["location_reference_precision"],
            "location_reference_recall": current["location_reference_recall"],
            "estimated_cost_usd": current["estimated_cost_usd"],
        },
    }


def _markdown(result: dict[str, Any]) -> str:
    a = result["aggregate"]
    lines = [
        f"# {EXPERIMENT_ID}",
        "",
        "Isolated engineering-only linguistic classifier experiment.",
        "",
        "## Headline",
        "",
        f"- Structured reliability: `{_fmt(a['structured_output_reliability'])}` (`{a['valid_structured_responses']}/{a['total_variants']}`)",
        f"- Temporal accuracy: `{_fmt(a['temporal_intent_accuracy'])}` over `{a['temporal_scored_variants']}` scored variants; `{a['temporal_review_variants']}` review-only",
        f"- CURRENT / COMPARE / VALID_AT_DATE: `{_fmt(a['temporal_intent_by_mode']['CURRENT'])}` / `{_fmt(a['temporal_intent_by_mode']['COMPARE'])}` / `{_fmt(a['temporal_intent_by_mode']['VALID_AT_DATE'])}`",
        f"- Date accuracy: `{_fmt(a['explicit_date_accuracy'])}`; false hallucinations `{a['false_date_hallucination_count']}`",
        f"- Location precision / recall / exact-case accuracy: `{_fmt(a['location_reference_precision'])}` / `{_fmt(a['location_reference_recall'])}` / `{_fmt(a['location_exact_match_accuracy'])}`",
        f"- Location false positives / misses: `{a['location_false_positives']}` / `{a['location_false_negatives']}`",
        f"- Latency mean/p50/p95: `{a['latency_ms']['mean']:.2f}` / `{a['latency_ms']['p50']:.2f}` / `{a['latency_ms']['p95']:.2f}` ms",
        f"- Tokens input/cached/output: `{a['usage']['input_tokens']}` / `{a['usage']['cached_input_tokens']}` / `{a['usage']['output_tokens']}`",
        f"- Estimated cost: `${a['estimated_cost_usd']:.6f}`",
        "",
        "## Confusion matrix",
        "",
        "| Expected \\ Predicted | CURRENT | COMPARE | VALID_AT_DATE |",
        "|---|---:|---:|---:|",
    ]
    for expected, row in a["confusion_matrix"].items():
        lines.append(
            f"| {expected} | {row['CURRENT']} | {row['COMPARE']} | {row['VALID_AT_DATE']} |"
        )
    lines.extend(
        [
            "",
            "## PLN-EXP-0001 comparison",
            "",
            "```json",
            json.dumps(result["comparison_with_pln_exp0001"], indent=2, sort_keys=True),
            "```",
            "",
            "## Location errors",
            "",
            "### False positives",
            "",
            "```json",
            json.dumps(a["location_false_positive_examples"], indent=2),
            "```",
            "",
            "### Misses",
            "",
            "```json",
            json.dumps(a["location_missed_examples"], indent=2),
            "```",
            "",
            "## Per-variant results",
            "",
            "| Case | Variant | Expected temporal | Actual temporal | Expected locations | Actual locations | Temporal | Location exact |",
            "|---|---|---|---|---|---|---|---|",
        ]
    )
    for item in result["variants"]:
        actual = item["classification"] or {}
        lines.append(
            "| "
            + " | ".join(
                (
                    item["case_id"],
                    item["variant_id"],
                    str(item["expected"]["temporal_intent"]),
                    str(actual.get("temporal_intent", "INVALID")),
                    json.dumps(item["expected"]["location_references"]),
                    json.dumps(actual.get("location_references")),
                    str(item["scores"]["temporal_intent_correct"]),
                    str(item["scores"]["location_exact_match"]),
                )
            )
            + " |"
        )
    return "\n".join(lines) + "\n"


def _html(result: dict[str, Any]) -> str:
    a = result["aggregate"]
    rows = "".join(
        "<tr>"
        f"<td>{html.escape(item['case_id'])}</td><td>{html.escape(item['variant_id'])}</td>"
        f"<td>{html.escape(item['question'])}</td>"
        f"<td><pre>{html.escape(json.dumps(item['expected'], indent=2))}</pre></td>"
        f"<td><pre>{html.escape(json.dumps(item['classification'], indent=2))}</pre></td>"
        f"<td><pre>{html.escape(json.dumps(item['scores'], indent=2))}</pre></td></tr>"
        for item in result["variants"]
    )
    return f"""<!doctype html><html lang=en><head><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title>{EXPERIMENT_ID}</title><style>body{{font-family:system-ui;max-width:1500px;margin:auto;padding:2rem;background:#f6f7f9;color:#18202a}}section,table{{background:white}}section{{padding:1rem;margin:1rem 0}}table{{border-collapse:collapse;width:100%;font-size:.82rem}}th,td{{border:1px solid #d7dce2;padding:.5rem;vertical-align:top;text-align:left}}pre{{white-space:pre-wrap;margin:0}}.metrics{{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem}}.metric{{padding:1rem;background:#eef3f8}}</style></head><body><h1>{EXPERIMENT_ID}</h1><p>Isolated BENCHMARK_TEXT classifier experiment.</p><section class=metrics><div class=metric><strong>Reliability</strong><br>{_fmt(a["structured_output_reliability"])}</div><div class=metric><strong>Temporal</strong><br>{_fmt(a["temporal_intent_accuracy"])}</div><div class=metric><strong>Location precision</strong><br>{_fmt(a["location_reference_precision"])}</div><div class=metric><strong>Location recall</strong><br>{_fmt(a["location_reference_recall"])}</div><div class=metric><strong>Cost</strong><br>${a["estimated_cost_usd"]:.6f}</div></section><section><h2>Lineage and aggregate</h2><pre>{html.escape(json.dumps({"lineage": result["lineage"], "aggregate": a, "comparison": result["comparison_with_pln_exp0001"]}, indent=2))}</pre></section><h2>Per-variant results</h2><table><thead><tr><th>Case</th><th>Variant</th><th>Question</th><th>Expected</th><th>Actual</th><th>Scores</th></tr></thead><tbody>{rows}</tbody></table></body></html>"""


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
        raise ValueError("persisted PLN-EXP-0002 observation lineage mismatch")


def _normalise(value: str) -> str:
    return " ".join(value.casefold().split())


def _location_examples(
    observations: list[dict[str, Any]], kind: str
) -> list[dict[str, Any]]:
    examples: list[dict[str, Any]] = []
    for item in observations:
        actual = item["classification"]
        if actual is None:
            predicted: set[str] = set()
        else:
            predicted = {_normalise(value) for value in actual["location_references"]}
        truth = {
            _normalise(value)
            for value in (item["expected"]["location_references"] or [])
        }
        values = sorted(
            predicted - truth if kind == "false_positive" else truth - predicted
        )
        if values:
            examples.append(
                {
                    "case_id": item["case_id"],
                    "variant_id": item["variant_id"],
                    "question": item["question"],
                    "references": values,
                }
            )
    return examples


def _accuracy(items: list[dict[str, Any]], field: str) -> float | None:
    values = [
        item["scores"][field] for item in items if item["scores"][field] is not None
    ]
    return _ratio(sum(value is True for value in values), len(values))


def _ratio(numerator: int, denominator: int) -> float | None:
    return numerator / denominator if denominator else None


def _percentile(values: list[float], quantile: float) -> float:
    return values[min(math.ceil(len(values) * quantile) - 1, len(values) - 1)]


def _fmt(value: float | None) -> str:
    return "n/a" if value is None else f"{value:.4f}"


def _observation_name(expectation: dict[str, Any]) -> str:
    raw = f"{expectation['case_id']}:{expectation['variant_id']}".encode()
    return hashlib.sha256(raw).hexdigest() + ".json"


def _load(path: Path) -> Any:
    return json.loads(path.read_text())


def _write(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_bytes(canonical_json(value) + b"\n")
    temporary.replace(path)


def _write_checksums(output_directory: Path) -> None:
    names = (
        "classification.schema.json",
        "config.json",
        "expectations.json",
        "result.json",
        "report.md",
        "report.html",
        "notes.md",
    )
    checksums = {
        name: hashlib.sha256((output_directory / name).read_bytes()).hexdigest()
        for name in names
        if (output_directory / name).exists()
    }
    _write(output_directory / "checksums.json", checksums)
