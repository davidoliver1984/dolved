"""Deterministic projections for grounded-generation evaluation results."""

from __future__ import annotations

import hashlib
import html
import json
from pathlib import Path
from typing import Any

from app.evaluation.generation_evaluation import (
    GenerationCaseObservation,
    GenerationEvaluationPopulation,
    GenerationEvaluationResult,
)
from app.evaluation.models import ModelAssistedMetric, ModelAssistedStatus


def _format_score(value: float | None) -> str:
    return "unavailable" if value is None else f"{value:.4f}"


def case_failures(observation: GenerationCaseObservation) -> tuple[str, ...]:
    failures: list[str] = []
    deterministic = observation.deterministic
    if not deterministic.outcome_correct:
        failures.append("prompt/generation semantic: outcome mismatch")
    if not deterministic.citation_membership_correct:
        failures.append("structured-output/adapter: citation membership failure")
    if deterministic.internal_identifier_leakage:
        failures.append("prompt/generation semantic: internal identifier leakage")
    if deterministic.prohibited_fragment_leakage:
        failures.append("prompt/generation semantic: hostile instruction influence")
    if observation.answer_evaluation is not None:
        result = observation.answer_evaluation
        if result.status is ModelAssistedStatus.FAILED:
            failures.append(
                f"evaluator/benchmark defect: {result.failure_code or 'evaluation unavailable'}"
            )
        else:
            if result.scores.get(ModelAssistedMetric.ANSWER_PART_GROUNDEDNESS, 1) < 1:
                failures.append("advisory evaluator disagreement: groundedness below 1")
            if result.scores.get(ModelAssistedMetric.ANSWER_FACTUAL_PRECISION, 1) < 1:
                failures.append(
                    "advisory evaluator disagreement: factual precision below 1"
                )
            if result.scores.get(ModelAssistedMetric.ANSWER_COMPLETENESS, 1) < 1:
                failures.append(
                    "advisory evaluator disagreement: answer completeness below 1"
                )
    return tuple(dict.fromkeys(failures))


def render_markdown(
    result: GenerationEvaluationResult,
    population: GenerationEvaluationPopulation,
) -> str:
    aggregate = result.aggregate
    lines = [
        f"# {result.experiment_id}",
        "",
        "Grounded-generation evaluation baseline. Semantic model-assisted scores are advisory; deterministic contract and citation checks remain authoritative.",
        "",
        "## Lineage",
        "",
        f"- Repository commit: `{result.repository_commit}`",
        f"- Population: `{result.population_id}`",
        f"- Population digest: `{result.population_digest}`",
        f"- Generation fingerprint: `{result.generation_lineage['fingerprint']}`",
        f"- Evaluator: `{result.evaluator_lineage['implementation']}` / `{result.evaluator_lineage['model']}`",
        "",
        "## Authoritative deterministic metrics",
        "",
        f"- Cases: {aggregate.total_cases}",
        f"- Outcome accuracy: {aggregate.outcome_accuracy:.4f}",
        f"- Citation correctness: {aggregate.citation_correctness:.4f}",
        f"- Over-refusal: {aggregate.over_refusal_count}/{aggregate.total_cases} ({aggregate.over_refusal_rate:.4f})",
        f"- Overclaiming: {aggregate.overclaiming_count}/{aggregate.total_cases}",
        f"- Hostile-evidence failures: {aggregate.hostile_failures}/{aggregate.hostile_case_count}",
        "",
        "## Advisory model-assisted metrics",
        "",
        "These scores cannot override the authoritative deterministic results above.",
        "",
        f"- Groundedness mean: {_format_score(aggregate.groundedness_mean)}",
        f"- Factual precision mean: {_format_score(aggregate.factual_precision_mean)}",
        f"- Completeness mean: {_format_score(aggregate.completeness_mean)}",
        f"- AnswerParts: total {aggregate.total_answer_parts}; scored {aggregate.successfully_scored_answer_parts}; evaluator-failed {aggregate.failed_evaluator_answer_parts}; unevaluable {aggregate.unevaluable_answer_parts}",
        f"- Unsupported AnswerParts (advisory judgement): {aggregate.unsupported_answer_part_count}/{aggregate.successfully_scored_answer_parts}",
        f"- Evaluator requests: {aggregate.evaluator_operations.request_count}; completed {aggregate.evaluator_operations.completed_count}; failed {aggregate.evaluator_operations.failed_count}",
        f"- Evaluator retries: {aggregate.evaluator_operations.retry_count if aggregate.evaluator_operations.retry_count is not None else 'unavailable'}",
        f"- Evaluator mean latency (ms): {_format_score(aggregate.evaluator_operations.latency_mean_ms)}",
        f"- Evaluator input/output tokens: {aggregate.evaluator_operations.input_tokens if aggregate.evaluator_operations.input_tokens is not None else 'unavailable'} / {aggregate.evaluator_operations.output_tokens if aggregate.evaluator_operations.output_tokens is not None else 'unavailable'}",
        f"- Evaluator cost (USD): {_format_score(aggregate.evaluator_operations.cost_usd)}",
        "",
        "## Semantic metric coverage",
        "",
        "| Metric | Mean | Eligible | Scored | Failed | Unevaluable | Coverage |",
        "|---|---:|---:|---:|---:|---:|---:|",
    ]
    for metric, coverage in aggregate.metric_coverage.items():
        lines.append(
            f"| {metric} | {_format_score(coverage.mean)} | {coverage.eligible} | {coverage.scored} | {coverage.failed} | {coverage.unevaluable} | {_format_score(coverage.coverage)} |"
        )
    lines.extend(
        [
            "",
            "## Outcome confusion matrix",
            "",
            "| Expected | answered | qualified | insufficient_evidence |",
            "|---|---:|---:|---:|",
        ]
    )
    for expected, row in aggregate.confusion_matrix.items():
        lines.append(
            f"| {expected} | {row['answered']} | {row['qualified']} | {row['insufficient_evidence']} |"
        )
    lines.extend(["", "## Per-case observations", ""])
    case_by_id = {case.case_id: case for case in population.cases}
    for observation in result.observations:
        case = case_by_id[observation.case_id]
        failures = case_failures(observation)
        answer_evaluation = observation.answer_evaluation
        advisory_status = (
            answer_evaluation.status.value
            if answer_evaluation is not None
            else "NOT_RUN"
        )
        advisory_scores = (
            ", ".join(
                f"{metric.value}={score:.4f}"
                for metric, score in sorted(
                    answer_evaluation.scores.items(), key=lambda item: item[0].value
                )
            )
            if answer_evaluation is not None and answer_evaluation.scores
            else "unavailable"
        )
        lines.extend(
            [
                f"### {observation.case_id}",
                "",
                f"- Cohort: {observation.cohort.value}",
                f"- Surfaces: {', '.join(case.surfaces)}",
                f"- Expected / actual outcome: {observation.expected_outcome.value} / {observation.result.outcome.value}",
                f"- Citation membership: {'PASS' if observation.deterministic.citation_membership_correct else 'FAIL'}",
                f"- Internal/prohibited leakage: {'FAIL' if observation.deterministic.internal_identifier_leakage or observation.deterministic.prohibited_fragment_leakage else 'PASS'}",
                f"- Advisory evaluator status: {advisory_status}",
                f"- Advisory scores: {advisory_scores}",
                f"- Earliest demonstrated divergence: {failures[0] if failures else 'none'}",
                f"- Failure inventory: {'; '.join(failures) if failures else 'none'}",
                "",
            ]
        )
    return "\n".join(lines).rstrip() + "\n"


def render_html(markdown: str, result: GenerationEvaluationResult) -> str:
    escaped = html.escape(markdown)
    return (
        '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        f"<title>{html.escape(result.experiment_id)}</title>"
        "<style>body{max-width:1100px;margin:2rem auto;padding:0 1rem;font:15px/1.5 system-ui;color:#172033}pre{white-space:pre-wrap}h1{color:#153c73}</style>"
        f"</head><body><pre>{escaped}</pre></body></html>\n"
    )


def write_generation_evaluation_artifacts(
    *,
    output_dir: Path,
    result: GenerationEvaluationResult,
    population: GenerationEvaluationPopulation,
    config: dict[str, Any],
) -> dict[str, str]:
    output_dir.mkdir(parents=True, exist_ok=True)
    application_observations = [
        {
            "case_id": item.case_id,
            "cohort": item.cohort.value,
            "question": item.question,
            "expected_outcome": item.expected_outcome.value,
            "request": item.request.model_dump(mode="json"),
            "result": item.result.model_dump(mode="json"),
        }
        for item in result.observations
    ]
    evaluation_observations = [
        {
            "case_id": item.case_id,
            "deterministic": item.deterministic.model_dump(mode="json"),
            "answer_part_evaluations": [
                value.model_dump(mode="json") for value in item.answer_part_evaluations
            ],
            "answer_evaluation": item.answer_evaluation.model_dump(mode="json")
            if item.answer_evaluation
            else None,
            "failure_inventory": list(case_failures(item)),
        }
        for item in result.observations
    ]
    json_files = {
        "application-observations.json": application_observations,
        "evaluation-observations.json": evaluation_observations,
        "result.json": result.model_dump(mode="json"),
        "config.json": config,
        "population.json": population.model_dump(mode="json"),
    }
    for name, value in json_files.items():
        (output_dir / name).write_text(
            json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + "\n"
        )
    markdown = render_markdown(result, population)
    (output_dir / "report.md").write_text(markdown)
    (output_dir / "report.html").write_text(render_html(markdown, result))
    names = sorted(
        path.name
        for path in output_dir.iterdir()
        if path.is_file() and path.name != "checksums.sha256"
    )
    hashes = {
        name: hashlib.sha256((output_dir / name).read_bytes()).hexdigest()
        for name in names
    }
    (output_dir / "checksums.sha256").write_text(
        "".join(f"{digest}  {name}\n" for name, digest in hashes.items())
    )
    return hashes
