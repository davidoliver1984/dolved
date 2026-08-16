"""Projection, scoring and reporting for isolated PLN-EXP-0003."""

from __future__ import annotations

import html
import json
from collections import Counter
from datetime import UTC, datetime
from pathlib import Path
from statistics import mean
from typing import Any

from app.evaluation.canonical import content_digest
from app.evaluation.thin_intent_location_classifier import (
    LocationClassifierCallResult,
    StructuredThinIntentLocationClassifier,
    ThinIntentLocationClassification,
)
from app.evaluation.thin_location_classifier_experiment import (
    _accuracy,
    _fmt,
    _load,
    _location_examples,
    _observation_name,
    _percentile,
    _ratio,
    _score,
    _write,
    _write_checksums,
    build_location_expectation_projection,
)

EXPERIMENT_ID = "PLN-EXP-0003-thin-intent-temporal-boundary"
HISTORICAL_ONLY_RECONCILIATION = (
    "health-safety.moving-handling.compare",
    "colloquial",
)
AUTHORITY_TRANSITION_RECONCILIATIONS = {
    ("pilot.current.withdrawn-before-authority", "scheduled"),
    ("pilot.current.withdrawn-no-resurrection", "withdrawn"),
}


def build_temporal_boundary_projection(
    corpus: dict[str, Any], split: dict[str, Any]
) -> dict[str, Any]:
    """Reuse PLN-EXP-0002 location truth and refine only temporal truth."""
    projection = build_location_expectation_projection(corpus, split)
    projection["experiment_id"] = EXPERIMENT_ID
    projection["scoring"]["temporal_intent"] = (
        "exact enum equality; historical-only questions without an exact day or explicit "
        "cross-time comparison are review-only"
    )
    item = next(
        variant
        for variant in projection["variants"]
        if (variant["case_id"], variant["variant_id"]) == HISTORICAL_ONLY_RECONCILIATION
    )
    item["expected"]["temporal_intent"] = None
    item["review"]["temporal"] = (
        "Asks about one old policy state without an exact day or explicit comparison."
    )
    projection["reconciliations"].append(
        {
            "case_id": item["case_id"],
            "variant_id": item["variant_id"],
            "field": "temporal_intent",
            "original": "COMPARE",
            "projected": None,
            "reason": (
                "The question asks only what an old policy said; it does not explicitly "
                "compare policy/document/application-authority states across time."
            ),
        }
    )
    for variant in projection["variants"]:
        key = (variant["case_id"], variant["variant_id"])
        if key not in AUTHORITY_TRANSITION_RECONCILIATIONS:
            continue
        variant["expected"]["temporal_intent"] = "COMPARE"
        projection["reconciliations"].append(
            {
                "case_id": variant["case_id"],
                "variant_id": variant["variant_id"],
                "field": "temporal_intent",
                "original": "CURRENT",
                "projected": "COMPARE",
                "reason": (
                    "The question explicitly asks whether application-authority states "
                    "changed over time, rather than merely which state applies now."
                ),
            }
        )
    return projection


def run_temporal_boundary_experiment(
    *,
    classifier: StructuredThinIntentLocationClassifier,
    projection: dict[str, Any],
    output_directory: Path,
    repository_commit: str,
    input_price_per_million: float,
    cached_input_price_per_million: float,
    output_price_per_million: float,
    pln_exp0001_result: dict[str, Any],
    pln_exp0002_result: dict[str, Any],
    contract_schema: dict[str, Any],
    reconcile_existing_expectations: bool = False,
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
        "adapter": "isolated-thin-intent-temporal-boundary-structured-chat-v1",
        "contract": (
            "contracts/evaluation/thin-intent-location-classifier/"
            "v1/classification.schema.json"
        ),
        "changed_variable": "temporal COMPARE instruction boundary only",
        "text_capture_mode": "BENCHMARK_TEXT",
        "pricing_usd_per_million_tokens": {
            "input": input_price_per_million,
            "cached_input": cached_input_price_per_million,
            "output": output_price_per_million,
        },
    }
    config_path = output_directory / "config.json"
    if config_path.exists() and _load(config_path) != config:
        if not reconcile_existing_expectations:
            raise ValueError("existing PLN-EXP-0003 lineage does not match")
        _reconcile_observations(
            observation_directory,
            projection,
            old_config=_load(config_path),
            new_config=config,
        )
    _write(config_path, config)
    _write(output_directory / "expectations.json", projection)
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
    result = _result(
        config,
        projection,
        observations,
        pln_exp0001_result,
        pln_exp0002_result,
    )
    _write(output_directory / "result.json", result)
    (output_directory / "report.md").write_text(_markdown(result))
    (output_directory / "report.html").write_text(_html(result))
    notes = output_directory / "notes.md"
    if not notes.exists():
        notes.write_text(
            "# Human notes\n\nNo production adoption or further experiment is approved.\n"
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


def _result(
    config: dict[str, Any],
    projection: dict[str, Any],
    observations: list[dict[str, Any]],
    first: dict[str, Any],
    second: dict[str, Any],
) -> dict[str, Any]:
    valid = [item for item in observations if item["classification"] is not None]
    temporal = [
        item
        for item in observations
        if item["scores"]["temporal_intent_correct"] is not None
    ]
    locations = [
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
                for item in temporal
            )
            for predicted in modes
        }
        for expected in modes
    }
    tp = sum(item["scores"]["location_true_positives"] for item in locations)
    fp = sum(item["scores"]["location_false_positives"] for item in locations)
    fn = sum(item["scores"]["location_false_negatives"] for item in locations)
    non_compare = [
        item for item in temporal if item["expected"]["temporal_intent"] != "COMPARE"
    ]
    false_compare = [
        item
        for item in non_compare
        if item["classification"] is not None
        and item["classification"]["temporal_intent"] == "COMPARE"
    ]
    compare = [
        item for item in temporal if item["expected"]["temporal_intent"] == "COMPARE"
    ]
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
        "temporal_scored_variants": len(temporal),
        "temporal_review_variants": len(observations) - len(temporal),
        "temporal_intent_accuracy": _accuracy(temporal, "temporal_intent_correct"),
        "temporal_intent_by_mode": {
            mode: _accuracy(
                [
                    item
                    for item in temporal
                    if item["expected"]["temporal_intent"] == mode
                ],
                "temporal_intent_correct",
            )
            for mode in modes
        },
        "confusion_matrix": confusion,
        "false_compare_count": len(false_compare),
        "false_compare_rate": _ratio(len(false_compare), len(non_compare)),
        "genuine_compare_count": len(compare),
        "genuine_compare_recall": _accuracy(compare, "temporal_intent_correct"),
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
        "location_scored_variants": len(locations),
        "location_true_positives": tp,
        "location_false_positives": fp,
        "location_false_negatives": fn,
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
        "estimated_cost_usd": estimated_cost,
    }
    return {
        "schema_version": "v1",
        "experiment_id": EXPERIMENT_ID,
        "executed_at": max(str(item["observed_at"]) for item in observations),
        "lineage": config,
        "aggregate": aggregate,
        "comparison": _comparison(first, second, aggregate),
        "previous_false_compare_analysis": _previous_false_compare_analysis(
            second, observations
        ),
        "reconciliation_count": len(projection["reconciliations"]),
        "variants": observations,
    }


def _comparison(
    first: dict[str, Any], second: dict[str, Any], current: dict[str, Any]
) -> dict[str, Any]:
    def row(result: dict[str, Any]) -> dict[str, Any]:
        aggregate = result["aggregate"]
        temporal = _stored_temporal_metrics(result.get("variants", []))
        return {
            "structured_output_reliability": aggregate["structured_output_reliability"],
            "temporal_intent_accuracy": aggregate["temporal_intent_accuracy"],
            "temporal_intent_by_mode": aggregate["temporal_intent_by_mode"],
            "false_compare_count": aggregate.get(
                "false_compare_count", temporal["false_compare_count"]
            ),
            "false_compare_rate": aggregate.get(
                "false_compare_rate", temporal["false_compare_rate"]
            ),
            "genuine_compare_recall": aggregate.get(
                "genuine_compare_recall", temporal["genuine_compare_recall"]
            ),
            "location_reference_precision": aggregate.get(
                "location_reference_precision"
            ),
            "location_reference_recall": aggregate.get("location_reference_recall"),
            "estimated_cost_usd": aggregate["estimated_cost_usd"],
        }

    return {
        "comparability_note": (
            "PLN-EXP-0001 used a broader temporal denominator and singular applicability "
            "presence. PLN-EXP-0002/3 use exact-day VALID_AT_DATE and plural locations; "
            "PLN-EXP-0003 additionally reviews one historical-only variant and, under its "
            "refined definition, reconciles two application-authority transition questions "
            "from CURRENT to genuine COMPARE."
        ),
        "PLN-EXP-0001": row(first),
        "PLN-EXP-0002": row(second),
        "PLN-EXP-0003": row({"aggregate": current, "variants": []}),
    }


def _stored_temporal_metrics(variants: list[dict[str, Any]]) -> dict[str, Any]:
    scored = [
        item
        for item in variants
        if item["expected"].get("temporal_intent") is not None
        and item.get("classification") is not None
    ]
    non_compare = [
        item for item in scored if item["expected"]["temporal_intent"] != "COMPARE"
    ]
    false_compare = sum(
        item["classification"]["temporal_intent"] == "COMPARE" for item in non_compare
    )
    compare = [
        item for item in scored if item["expected"]["temporal_intent"] == "COMPARE"
    ]
    true_compare = sum(
        item["classification"]["temporal_intent"] == "COMPARE" for item in compare
    )
    return {
        "false_compare_count": false_compare,
        "false_compare_rate": _ratio(false_compare, len(non_compare)),
        "genuine_compare_recall": _ratio(true_compare, len(compare)),
    }


def _previous_false_compare_analysis(
    previous: dict[str, Any], observations: list[dict[str, Any]]
) -> list[dict[str, Any]]:
    current = {(item["case_id"], item["variant_id"]): item for item in observations}
    rows = []
    for item in previous["variants"]:
        classification = item["classification"]
        now = current[(item["case_id"], item["variant_id"])]
        if (
            now["expected"]["temporal_intent"] != "CURRENT"
            or classification is None
            or classification["temporal_intent"] != "COMPARE"
        ):
            continue
        actual = now["classification"]
        rows.append(
            {
                "case_id": item["case_id"],
                "variant_id": item["variant_id"],
                "question": item["question"],
                "pln_exp0002": "COMPARE",
                "pln_exp0003": (
                    actual["temporal_intent"] if actual is not None else "INVALID"
                ),
                "resolved": (
                    actual is not None and actual["temporal_intent"] == "CURRENT"
                ),
            }
        )
    return rows


def _markdown(result: dict[str, Any]) -> str:
    aggregate = result["aggregate"]
    lines = [
        f"# {EXPERIMENT_ID}",
        "",
        "Isolated engineering-only temporal-boundary experiment.",
        "",
        "## Headline",
        "",
        f"- Structured reliability: `{_fmt(aggregate['structured_output_reliability'])}`",
        f"- Temporal accuracy: `{_fmt(aggregate['temporal_intent_accuracy'])}` over `{aggregate['temporal_scored_variants']}` scored variants",
        f"- CURRENT / COMPARE / VALID_AT_DATE: `{_fmt(aggregate['temporal_intent_by_mode']['CURRENT'])}` / `{_fmt(aggregate['temporal_intent_by_mode']['COMPARE'])}` / `{_fmt(aggregate['temporal_intent_by_mode']['VALID_AT_DATE'])}`",
        f"- False COMPARE count/rate: `{aggregate['false_compare_count']}` / `{_fmt(aggregate['false_compare_rate'])}`",
        f"- Genuine COMPARE recall: `{_fmt(aggregate['genuine_compare_recall'])}`",
        f"- Location precision / recall: `{_fmt(aggregate['location_reference_precision'])}` / `{_fmt(aggregate['location_reference_recall'])}`",
        f"- Estimated cost: `${aggregate['estimated_cost_usd']:.6f}`",
        "",
        "## Three-experiment comparison",
        "",
        "```json",
        json.dumps(result["comparison"], indent=2, sort_keys=True),
        "```",
        "",
        "## Previous false-COMPARE cases",
        "",
        "```json",
        json.dumps(result["previous_false_compare_analysis"], indent=2),
        "```",
        "",
        "## Per-variant results",
        "",
        "| Case | Variant | Expected | Actual | Correct | Expected locations | Actual locations |",
        "|---|---|---|---|---|---|---|",
    ]
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
                    str(item["scores"]["temporal_intent_correct"]),
                    json.dumps(item["expected"]["location_references"]),
                    json.dumps(actual.get("location_references")),
                )
            )
            + " |"
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
        f"<td><pre>{html.escape(json.dumps(item['scores'], indent=2))}</pre></td>"
        "</tr>"
        for item in result["variants"]
    )
    summary = html.escape(
        json.dumps(
            {
                "lineage": result["lineage"],
                "aggregate": aggregate,
                "comparison": result["comparison"],
                "previous_false_compare_analysis": result[
                    "previous_false_compare_analysis"
                ],
            },
            indent=2,
        )
    )
    return f"""<!doctype html><html lang=en><head><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><title>{EXPERIMENT_ID}</title><style>body{{font-family:system-ui;max-width:1500px;margin:auto;padding:2rem;background:#f6f7f9;color:#18202a}}section,table{{background:white}}section{{padding:1rem;margin:1rem 0}}table{{border-collapse:collapse;width:100%;font-size:.82rem}}th,td{{border:1px solid #d7dce2;padding:.5rem;vertical-align:top;text-align:left}}pre{{white-space:pre-wrap;margin:0}}.metrics{{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem}}.metric{{padding:1rem;background:#eef3f8}}</style></head><body><h1>{EXPERIMENT_ID}</h1><p>Isolated BENCHMARK_TEXT classifier experiment.</p><section class=metrics><div class=metric><strong>Reliability</strong><br>{_fmt(aggregate["structured_output_reliability"])}</div><div class=metric><strong>Temporal</strong><br>{_fmt(aggregate["temporal_intent_accuracy"])}</div><div class=metric><strong>False COMPARE</strong><br>{aggregate["false_compare_count"]}</div><div class=metric><strong>COMPARE recall</strong><br>{_fmt(aggregate["genuine_compare_recall"])}</div><div class=metric><strong>Location precision</strong><br>{_fmt(aggregate["location_reference_precision"])}</div></section><section><h2>Lineage, aggregate and comparison</h2><pre>{summary}</pre></section><h2>Per-variant results</h2><table><thead><tr><th>Case</th><th>Variant</th><th>Question</th><th>Expected</th><th>Actual</th><th>Scores</th></tr></thead><tbody>{rows}</tbody></table></body></html>"""


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
        raise ValueError("persisted PLN-EXP-0003 observation lineage mismatch")


def _reconcile_observations(
    observation_directory: Path,
    projection: dict[str, Any],
    *,
    old_config: dict[str, Any],
    new_config: dict[str, Any],
) -> None:
    old_stable = {k: v for k, v in old_config.items() if k != "expectation_digest"}
    new_stable = {k: v for k, v in new_config.items() if k != "expectation_digest"}
    if old_stable != new_stable:
        raise ValueError("expectation reconciliation cannot change experiment lineage")
    for expectation in projection["variants"]:
        path = observation_directory / _observation_name(expectation)
        observation = _load(path)
        stable = {
            "experiment_id": EXPERIMENT_ID,
            "provider": new_config["provider"],
            "model": new_config["model"],
            "case_id": expectation["case_id"],
            "variant_id": expectation["variant_id"],
            "question": expectation["question"],
        }
        if any(observation.get(key) != value for key, value in stable.items()):
            raise ValueError("stored observation cannot be safely reconciled")
        classification = observation.get("classification")
        actual = (
            ThinIntentLocationClassification.model_validate_json(
                json.dumps(classification)
            )
            if classification is not None
            else None
        )
        observation["expectation_digest"] = new_config["expectation_digest"]
        observation["expected"] = expectation["expected"]
        observation["review"] = expectation["review"]
        observation["scores"] = _score(expectation, actual)
        _write(path, observation)
