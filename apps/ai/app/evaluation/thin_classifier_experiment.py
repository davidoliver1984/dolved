"""Projection, scoring and reporting for the isolated thin-classifier experiment."""

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
from app.evaluation.thin_intent_classifier import (
    ClassifierCallResult,
    StructuredThinIntentClassifier,
    ThinIntentClassification,
)

EXPERIMENT_ID = "PLN-EXP-0001-thin-intent-classifier"
EXACT_DATES = {
    ("infection.outbreak.valid-before-withdrawal", "dated"): "2026-01-01",
    ("pilot.valid-at-date.medication-administration", "dated"): "2024-06-01",
}
APPLICABILITY_EXPECTATIONS: dict[tuple[str, str], tuple[str, ...] | None] = {
    ("hr.lone-worker.coventry-overdue", "alias"): ("Coventry", "Coventry community"),
    ("hr.lone-worker.coventry-overdue", "timing"): ("Midlands",),
    ("hr.lone-worker.coventry-overdue", "colloquial"): (),
    ("pilot.applicability.ambiguous-home", "ambiguous"): ("the home",),
    ("pilot.applicability.ambiguous-home", "pronoun"): None,
    ("pilot.applicability.ambiguous-home", "underspecified"): ("our care home",),
    ("pilot.applicability.bristol-conflict", "direct"): ("Harbour View",),
    ("pilot.applicability.bristol-conflict", "conflict"): None,
    ("pilot.applicability.bristol-conflict", "multi-document"): ("Bristol home",),
    ("pilot.applicability.regional-exeter", "alias"): ("Exeter home",),
    ("pilot.applicability.regional-exeter", "canonical"): ("Meadow Court",),
    ("pilot.applicability.regional-exeter", "inheritance"): None,
    ("pilot.location-alias.bristol", "alias"): ("Bristol home",),
    ("pilot.location-alias.bristol", "canonical"): ("Harbour View",),
    ("pilot.location-alias.bristol", "colloquial"): ("Bristol",),
}
REVIEW_REASONS = {
    ("pilot.applicability.ambiguous-home", "pronoun"): (
        "Standalone pronoun has no supplied conversational antecedent."
    ),
    ("pilot.applicability.bristol-conflict", "conflict"): (
        "Question contains two applicability references but the experimental contract is singular."
    ),
    ("pilot.applicability.regional-exeter", "inheritance"): (
        "Question contains both a region and a site but the experimental contract is singular."
    ),
}


def build_expectation_projection(
    corpus: dict[str, Any], split: dict[str, Any]
) -> dict[str, Any]:
    engineering = tuple(split["assignments"]["engineering_tuning"])
    cases = {item["case_id"]: item for item in corpus["cases"]}
    if len(engineering) != 42 or any(case_id not in cases for case_id in engineering):
        raise ValueError("classifier experiment requires exactly 42 engineering cases")
    variants: list[dict[str, Any]] = []
    reconciliations: list[dict[str, Any]] = []
    for case_id in engineering:
        case = cases[case_id]
        expected_intent = str(case["planner_expectation"]["temporal_mode"])
        for variant in case["variants"]:
            key = (case_id, str(variant["variant_id"]))
            accepted = APPLICABILITY_EXPECTATIONS.get(key, ())
            review_reason = REVIEW_REASONS.get(key)
            expected_date = EXACT_DATES.get(key)
            variants.append(
                {
                    "case_id": case_id,
                    "variant_id": variant["variant_id"],
                    "question": variant["question"],
                    "expected": {
                        "temporal_intent": expected_intent,
                        "explicit_date": expected_date,
                        "accepted_applicability_references": (
                            list(accepted) if accepted is not None else None
                        ),
                    },
                    "overall_scoring_status": (
                        "REVIEW_REQUIRED" if review_reason else "SCORED"
                    ),
                    "review_reason": review_reason,
                }
            )
            original_date = case["planner_expectation"].get("valid_at")
            original_reference = (
                case["planner_expectation"]
                .get("applicability_reference", {})
                .get("input")
            )
            if original_date and expected_date is None:
                reconciliations.append(
                    {
                        "case_id": case_id,
                        "variant_id": variant["variant_id"],
                        "field": "explicit_date",
                        "original": original_date,
                        "projected": None,
                        "reason": (
                            "Application-state date is not an exact date inferable from this question."
                        ),
                    }
                )
            projected_refs = list(accepted) if accepted is not None else None
            if original_reference is not None and projected_refs != [
                original_reference
            ]:
                reconciliations.append(
                    {
                        "case_id": case_id,
                        "variant_id": variant["variant_id"],
                        "field": "applicability_reference",
                        "original": original_reference,
                        "projected": projected_refs,
                        "reason": (
                            "Classifier truth retains question wording and does not resolve application aliases."
                        ),
                    }
                )
    if len(variants) != 126:
        raise ValueError("classifier experiment requires exactly 126 variants")
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
            "temporal_intent": "exact enum equality for every valid response",
            "explicit_date": "exact ISO date equality; partial dates must remain null",
            "applicability_reference": (
                "case-insensitive whitespace-normalised equality against accepted question wording"
            ),
            "overall": (
                "all three scored fields correct; REVIEW_REQUIRED variants excluded"
            ),
            "temporal_reference": "retained for inspection but not scored in v1",
        },
        "reconciliations": reconciliations,
        "variants": variants,
    }


def run_experiment(
    *,
    classifier: StructuredThinIntentClassifier,
    projection: dict[str, Any],
    output_directory: Path,
    repository_commit: str,
    input_price_per_million: float,
    cached_input_price_per_million: float,
    output_price_per_million: float,
    exp0001_result: dict[str, Any] | None = None,
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
        "adapter": "isolated-thin-intent-structured-chat-v1",
        "contract": "contracts/evaluation/thin-intent-classifier/v1/classification.schema.json",
        "text_capture_mode": "BENCHMARK_TEXT",
        "pricing_usd_per_million_tokens": {
            "input": input_price_per_million,
            "cached_input": cached_input_price_per_million,
            "output": output_price_per_million,
        },
    }
    config_path = output_directory / "config.json"
    if config_path.exists() and _load(config_path) != config:
        raise ValueError("existing classifier run lineage does not match requested run")
    _write_json(config_path, config)
    _write_json(output_directory / "expectations.json", projection)

    for expectation in projection["variants"]:
        observation_path = observations_directory / _observation_name(expectation)
        if observation_path.exists():
            _validate_observation(_load(observation_path), expectation, config)
            continue
        call = classifier.classify(str(expectation["question"]))
        observation = _observation(expectation, call, config)
        _write_json(observation_path, observation)
        if call.failure is not None and call.failure.systemic:
            raise RuntimeError(f"systemic classifier failure: {call.failure.category}")

    observations = [
        _load(observations_directory / _observation_name(item))
        for item in projection["variants"]
    ]
    result = _result(config, projection, observations)
    if exp0001_result is not None:
        result["comparison_with_exp0001"] = _exp0001_comparison(
            exp0001_result, projection, result
        )
    _write_json(output_directory / "result.json", result)
    (output_directory / "report.md").write_text(_markdown_report(result, projection))
    (output_directory / "report.html").write_text(_html_report(result, projection))
    notes = output_directory / "notes.md"
    if not notes.exists():
        notes.write_text(
            "# Human notes\n\nNo architectural adoption or follow-on model experiment has been approved.\n"
        )
    return result


def _observation(
    expectation: dict[str, Any], call: ClassifierCallResult, config: dict[str, Any]
) -> dict[str, Any]:
    classification = (
        call.classification.model_dump(mode="json")
        if call.classification is not None
        else None
    )
    scores = _score(expectation, call.classification)
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
        "classification": classification,
        "failure": call.failure.model_dump(mode="json") if call.failure else None,
        "scores": scores,
        "latency_ms": call.latency_ms,
        "usage": {
            "input_tokens": call.input_tokens,
            "cached_input_tokens": call.cached_input_tokens,
            "output_tokens": call.output_tokens,
        },
        "observed_at": datetime.now(UTC).isoformat(),
        "overall_scoring_status": expectation["overall_scoring_status"],
        "review_reason": expectation["review_reason"],
    }


def _score(
    expectation: dict[str, Any], actual: ThinIntentClassification | None
) -> dict[str, bool | None]:
    if actual is None:
        return {
            "temporal_intent_correct": False,
            "explicit_date_correct": False,
            "applicability_reference_correct": False,
            "overall_correct": False,
        }
    expected = expectation["expected"]
    refs = expected["accepted_applicability_references"]
    temporal = actual.temporal_intent.value == expected["temporal_intent"]
    explicit_date = (
        actual.explicit_date.isoformat() if actual.explicit_date else None
    ) == expected["explicit_date"]
    applicability = (
        None
        if refs is None
        else (
            actual.applicability_reference is None
            if refs == []
            else _normalise_reference(actual.applicability_reference)
            in {_normalise_reference(value) for value in refs}
        )
    )
    overall = (
        None
        if expectation["overall_scoring_status"] == "REVIEW_REQUIRED"
        else temporal and explicit_date and applicability is True
    )
    return {
        "temporal_intent_correct": temporal,
        "explicit_date_correct": explicit_date,
        "applicability_reference_correct": applicability,
        "overall_correct": overall,
    }


def _result(
    config: dict[str, Any],
    projection: dict[str, Any],
    observations: list[dict[str, Any]],
) -> dict[str, Any]:
    valid = [item for item in observations if item["classification"] is not None]
    scored = [
        item for item in observations if item["scores"]["overall_correct"] is not None
    ]
    expected_modes = ("CURRENT", "COMPARE", "VALID_AT_DATE")
    confusion = {
        expected: {
            predicted: sum(
                item["expected"]["temporal_intent"] == expected
                and item["classification"] is not None
                and item["classification"]["temporal_intent"] == predicted
                for item in observations
            )
            for predicted in expected_modes
        }
        for expected in expected_modes
    }
    exact_date_items = [
        item for item in observations if item["expected"]["explicit_date"] is not None
    ]
    applicable_items = [
        item
        for item in observations
        if item["expected"]["accepted_applicability_references"] not in (None, [])
    ]
    absent_applicability_items = [
        item
        for item in observations
        if item["expected"]["accepted_applicability_references"] == []
    ]
    predicted_applicability = [
        item
        for item in observations
        if item["expected"]["accepted_applicability_references"] is not None
        and item["classification"] is not None
        and item["classification"]["applicability_reference"] is not None
    ]
    applicability_true_positives = sum(
        bool(item["expected"]["accepted_applicability_references"])
        for item in predicted_applicability
    )
    latencies = sorted(float(item["latency_ms"]) for item in observations)
    usage = {
        key: sum(int(item["usage"][key]) for item in observations)
        for key in ("input_tokens", "cached_input_tokens", "output_tokens")
    }
    price = config["pricing_usd_per_million_tokens"]
    uncached = usage["input_tokens"] - usage["cached_input_tokens"]
    estimated_cost = (
        uncached * price["input"]
        + usage["cached_input_tokens"] * price["cached_input"]
        + usage["output_tokens"] * price["output"]
    ) / 1_000_000
    return {
        "schema_version": "v1",
        "experiment_id": EXPERIMENT_ID,
        "executed_at": max(str(item["observed_at"]) for item in observations),
        "lineage": config,
        "aggregate": {
            "total_variants": len(observations),
            "valid_structured_responses": len(valid),
            "invalid_structured_responses": len(observations) - len(valid),
            "structured_output_reliability": _ratio(len(valid), len(observations)),
            "overall_scored_variants": len(scored),
            "overall_accuracy": _accuracy(scored, "overall_correct"),
            "temporal_intent_accuracy": _accuracy(
                observations, "temporal_intent_correct"
            ),
            "temporal_intent_by_mode": {
                mode: _accuracy(
                    [
                        item
                        for item in observations
                        if item["expected"]["temporal_intent"] == mode
                    ],
                    "temporal_intent_correct",
                )
                for mode in expected_modes
            },
            "explicit_date_accuracy": _accuracy(
                exact_date_items, "explicit_date_correct"
            ),
            "exact_date_variant_count": len(exact_date_items),
            "no_exact_date_non_hallucination_accuracy": _accuracy(
                [
                    item
                    for item in observations
                    if item["expected"]["explicit_date"] is None
                ],
                "explicit_date_correct",
            ),
            "applicability_reference_accuracy": _accuracy(
                applicable_items, "applicability_reference_correct"
            ),
            "applicability_variant_count": len(applicable_items),
            "applicability_presence_precision": _ratio(
                applicability_true_positives, len(predicted_applicability)
            ),
            "applicability_presence_recall": _ratio(
                applicability_true_positives, len(applicable_items)
            ),
            "applicability_absence_accuracy": _ratio(
                sum(
                    item["classification"] is not None
                    and item["classification"]["applicability_reference"] is None
                    for item in absent_applicability_items
                ),
                len(absent_applicability_items),
            ),
            "applicability_absent_variant_count": len(absent_applicability_items),
            "review_required_variants": len(observations) - len(scored),
            "failure_categories": dict(
                sorted(
                    Counter(
                        item["failure"]["category"]
                        for item in observations
                        if item["failure"] is not None
                    ).items()
                )
            ),
            "confusion_matrix": confusion,
            "latency_ms": {
                "mean": mean(latencies),
                "p50": _percentile(latencies, 0.50),
                "p95": _percentile(latencies, 0.95),
            },
            "usage": usage,
            "estimated_cost_usd": estimated_cost,
        },
        "reconciliation_count": len(projection["reconciliations"]),
        "variants": observations,
    }


def _markdown_report(result: dict[str, Any], projection: dict[str, Any]) -> str:
    aggregate = result["aggregate"]
    lines = [
        f"# {EXPERIMENT_ID}",
        "",
        "Isolated engineering experiment: no retrieval, eligibility, protected splits or production planner changes.",
        "",
        "## Headline",
        "",
        f"- Valid structured responses: `{aggregate['valid_structured_responses']}/{aggregate['total_variants']}`",
        f"- Overall accuracy: `{_format_ratio(aggregate['overall_accuracy'])}` over `{aggregate['overall_scored_variants']}` scored variants",
        f"- Temporal-intent accuracy: `{_format_ratio(aggregate['temporal_intent_accuracy'])}`",
        f"- CURRENT accuracy: `{_format_ratio(aggregate['temporal_intent_by_mode']['CURRENT'])}`",
        f"- COMPARE accuracy: `{_format_ratio(aggregate['temporal_intent_by_mode']['COMPARE'])}`",
        f"- VALID_AT_DATE accuracy: `{_format_ratio(aggregate['temporal_intent_by_mode']['VALID_AT_DATE'])}`",
        f"- Exact-date accuracy: `{_format_ratio(aggregate['explicit_date_accuracy'])}` over `{aggregate['exact_date_variant_count']}` exact-date variants",
        f"- Applicability exact-reference accuracy: `{_format_ratio(aggregate['applicability_reference_accuracy'])}` over `{aggregate['applicability_variant_count']}` expected-reference variants",
        f"- Latency mean/p50/p95: `{aggregate['latency_ms']['mean']:.2f}` / `{aggregate['latency_ms']['p50']:.2f}` / `{aggregate['latency_ms']['p95']:.2f}` ms",
        f"- Tokens input/cached/output: `{aggregate['usage']['input_tokens']}` / `{aggregate['usage']['cached_input_tokens']}` / `{aggregate['usage']['output_tokens']}`",
        f"- Estimated cost: `${aggregate['estimated_cost_usd']:.6f}`",
        "",
        "## Temporal confusion matrix",
        "",
        "| Expected \\ Predicted | CURRENT | COMPARE | VALID_AT_DATE |",
        "|---|---:|---:|---:|",
    ]
    for expected, values in aggregate["confusion_matrix"].items():
        lines.append(
            f"| {expected} | {values['CURRENT']} | {values['COMPARE']} | {values['VALID_AT_DATE']} |"
        )
    comparison = result.get("comparison_with_exp0001")
    lines.extend(
        [
            "",
            "## EXP-0001 comparison",
            "",
            "```json",
            json.dumps(comparison, indent=2, sort_keys=True),
            "```",
            "",
            "## Ground-truth reconciliation",
            "",
            f"`{len(projection['reconciliations'])}` field-level reconciliations are preserved in `expectations.json`.",
            "",
            "## Per-variant results",
            "",
            "| Case | Variant | Expected | Actual | Date | Applicability | Overall |",
            "|---|---|---|---|---|---|---|",
        ]
    )
    for item in result["variants"]:
        actual = item["classification"] or {}
        scores = item["scores"]
        lines.append(
            "| "
            + " | ".join(
                (
                    item["case_id"],
                    item["variant_id"],
                    item["expected"]["temporal_intent"],
                    actual.get("temporal_intent", "INVALID"),
                    str(scores["explicit_date_correct"]),
                    str(scores["applicability_reference_correct"]),
                    str(scores["overall_correct"]),
                )
            )
            + " |"
        )
    return "\n".join(lines) + "\n"


def _exp0001_comparison(
    exp0001: dict[str, Any], projection: dict[str, Any], result: dict[str, Any]
) -> dict[str, Any]:
    hybrid = exp0001["hybrid"]
    expectation_modes = {
        (item["case_id"], item["variant_id"]): item["expected"]["temporal_intent"]
        for item in projection["variants"]
    }
    old_by_mode = {
        mode: {
            "correct": sum(
                item["planner_correct"]
                and expectation_modes[(item["case_id"], item["variant_id"])] == mode
                for item in hybrid["variants"]
            ),
            "total": sum(value == mode for value in expectation_modes.values()),
        }
        for mode in ("CURRENT", "COMPARE", "VALID_AT_DATE")
    }
    latency = exp0001.get("operational", {}).get("hybrid", {}).get("latency_ms", {})
    return {
        "comparability_note": (
            "EXP-0001 latency is an application/retrieval observation, not planner-only; "
            "cost and token usage for its planner were not machine-recorded. The responsibility "
            "boundaries intentionally differ."
        ),
        "exp0001": {
            "valid_typed_responses": hybrid["aggregate"]["planner_success_count"],
            "total_variants": hybrid["aggregate"]["variant_count"],
            "temporal_intent_correct": sum(
                item["planner_correct"] for item in hybrid["variants"]
            ),
            "by_mode": old_by_mode,
            "planner_cost_usd": None,
            "planner_token_usage": None,
            "application_observation_latency_ms": latency,
        },
        "thin_classifier": {
            "valid_typed_responses": result["aggregate"]["valid_structured_responses"],
            "total_variants": result["aggregate"]["total_variants"],
            "temporal_intent_correct": sum(
                item["scores"]["temporal_intent_correct"] for item in result["variants"]
            ),
            "by_mode": {
                mode: {
                    "correct": sum(
                        item["expected"]["temporal_intent"] == mode
                        and item["scores"]["temporal_intent_correct"]
                        for item in result["variants"]
                    ),
                    "total": sum(
                        item["expected"]["temporal_intent"] == mode
                        for item in result["variants"]
                    ),
                }
                for mode in ("CURRENT", "COMPARE", "VALID_AT_DATE")
            },
            "estimated_cost_usd": result["aggregate"]["estimated_cost_usd"],
            "token_usage": result["aggregate"]["usage"],
            "classifier_latency_ms": result["aggregate"]["latency_ms"],
        },
    }


def _html_report(result: dict[str, Any], projection: dict[str, Any]) -> str:
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
    return f"""<!doctype html><html lang=en><head><meta charset=utf-8>
<meta name=viewport content="width=device-width,initial-scale=1"><title>{EXPERIMENT_ID}</title>
<style>body{{font-family:system-ui;max-width:1500px;margin:auto;padding:2rem;background:#f6f7f9;color:#18202a}}section,table{{background:white;border-radius:8px}}section{{padding:1rem;margin:1rem 0}}table{{border-collapse:collapse;width:100%;font-size:.82rem}}th,td{{border:1px solid #d7dce2;padding:.5rem;vertical-align:top;text-align:left}}pre{{white-space:pre-wrap;margin:0}}.metrics{{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem}}.metric{{padding:1rem;background:#eef3f8;border-radius:6px}}</style></head><body>
<h1>{EXPERIMENT_ID}</h1><p>Isolated BENCHMARK_TEXT linguistic-classifier experiment.</p>
<section class=metrics><div class=metric><strong>Structured reliability</strong><br>{_format_ratio(aggregate["structured_output_reliability"])}</div><div class=metric><strong>Overall accuracy</strong><br>{_format_ratio(aggregate["overall_accuracy"])}</div><div class=metric><strong>Temporal accuracy</strong><br>{_format_ratio(aggregate["temporal_intent_accuracy"])}</div><div class=metric><strong>Estimated cost</strong><br>${aggregate["estimated_cost_usd"]:.6f}</div></section>
<section><h2>Lineage and aggregate</h2><pre>{html.escape(json.dumps({"lineage": result["lineage"], "aggregate": aggregate, "reconciliations": len(projection["reconciliations"])}, indent=2))}</pre></section>
<h2>Per-variant results</h2><table><thead><tr><th>Case</th><th>Variant</th><th>Question</th><th>Expected</th><th>Actual</th><th>Scores</th></tr></thead><tbody>{rows}</tbody></table></body></html>"""


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
        raise ValueError("persisted classifier observation lineage mismatch")


def _observation_name(expectation: dict[str, Any]) -> str:
    identity = f"{expectation['case_id']}:{expectation['variant_id']}".encode()
    return hashlib.sha256(identity).hexdigest() + ".json"


def _normalise_reference(value: str | None) -> str | None:
    return " ".join(value.casefold().split()) if value is not None else None


def _accuracy(items: list[dict[str, Any]], field: str) -> float | None:
    values = [
        item["scores"][field] for item in items if item["scores"][field] is not None
    ]
    return _ratio(sum(value is True for value in values), len(values))


def _ratio(numerator: int, denominator: int) -> float | None:
    return numerator / denominator if denominator else None


def _percentile(values: list[float], quantile: float) -> float:
    return values[min(math.ceil(len(values) * quantile) - 1, len(values) - 1)]


def _format_ratio(value: float | None) -> str:
    return "n/a" if value is None else f"{value:.4f}"


def _load(path: Path) -> Any:
    return json.loads(path.read_text())


def _write_json(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_bytes(canonical_json(value) + b"\n")
    temporary.replace(path)
