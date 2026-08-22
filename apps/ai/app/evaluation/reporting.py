"""Stable human-readable experiment comparison reports."""

from app.evaluation.historical_result import ComparisonResult
from app.evaluation.models import ExperimentResult


def deterministic_candidate_report(
    candidate: ExperimentResult,
    *,
    operational: dict[str, object],
) -> str:
    """Render a first-baseline candidate without implying promotion or approval."""
    metrics = candidate.aggregate.metrics
    if metrics is None:
        raise ValueError("candidate report requires retrieval metrics")
    lines = [
        f"# Deterministic retrieval candidate: {candidate.experiment_id}",
        "",
        "Status: **CANDIDATE — NOT PROMOTED**",
        "",
        (
            "This provider-free run exercises the current retrieval implementation "
            "from the authored-plan boundary. It is deterministic regression "
            "evidence, not live-provider quality evidence."
        ),
        "",
        "## Lineage",
        "",
        f"- Repository commit: `{candidate.lineage.repository_commit}`",
        (
            f"- Corpus: `{candidate.lineage.corpus_version}` "
            f"(`{candidate.lineage.corpus_digest}`)"
        ),
        (
            f"- Policy: `{candidate.lineage.policy_version}` "
            f"(`{candidate.lineage.policy_digest}`)"
        ),
        (
            "- Deterministic profile: "
            f"`{candidate.lineage.deterministic_profile_digest}`"
        ),
        f"- Plan catalogue: `{candidate.lineage.plan_catalogue_checksum}`",
        "",
        "## Overall metrics",
        "",
        "| Metric | Value |",
        "|---|---:|",
        f"| Recall@K | {metrics.recall_at_k:.6f} |",
        f"| Benchmark precision@K | {metrics.precision_at_k:.6f} |",
        f"| MRR | {metrics.mrr:.6f} |",
        f"| nDCG@K | {metrics.ndcg_at_k:.6f} |",
        f"| Planner accuracy | {candidate.aggregate.planner_accuracy:.6f} |",
        (
            "| Eligibility accuracy | "
            f"{_optional_metric(candidate.aggregate.eligibility_accuracy)} |"
        ),
        (
            "| Outcome accuracy | "
            f"{_optional_metric(candidate.aggregate.outcome_accuracy)} |"
        ),
        f"| Cases | {candidate.aggregate.case_count} |",
        f"| Variants | {candidate.aggregate.variant_count} |",
        "",
        "## Absolute failures",
        "",
    ]
    lines.extend(
        [f"- `{failure}`" for failure in candidate.hard_failures]
        or ["No absolute failures."]
    )
    lines.extend(["", "## Slice metrics", ""])
    for name, result in sorted(candidate.slices.items()):
        if result.metrics is None:
            lines.append(
                f"- **{name}**: no retrieval metrics, cases={result.case_count}"
            )
        else:
            lines.append(
                f"- **{name}**: recall={result.metrics.recall_at_k:.6f}, "
                f"precision={result.metrics.precision_at_k:.6f}, "
                f"mrr={result.metrics.mrr:.6f}, "
                f"nDCG={result.metrics.ndcg_at_k:.6f}, cases={result.case_count}"
            )
    lines.extend(
        [
            "",
            "## Operational profile",
            "",
            f"- Documents: {operational.get('document_count')}",
            f"- Chunks: {operational.get('chunk_count')}",
            f"- Queries: {operational.get('query_count')}",
            "- External provider calls: 0",
            "",
            (
                "Human review and an explicit promotion record are required before "
                "this candidate can become the deterministic baseline."
            ),
        ]
    )
    return "\n".join(lines) + "\n"


def _optional_metric(value: float | None) -> str:
    return "unavailable" if value is None else f"{value:.6f}"


def comparison_report(
    candidate: ExperimentResult, baseline: ExperimentResult | ComparisonResult
) -> str:
    candidate_metrics = candidate.aggregate.metrics
    baseline_metrics = baseline.aggregate.metrics
    if candidate_metrics is None or baseline_metrics is None:
        raise ValueError("comparison requires retrieval metrics in both results")
    lines = [
        f"# Evaluation comparison: {candidate.experiment_id}",
        "",
        f"Baseline: `{baseline.experiment_id}`",
        f"Corpus: `{candidate.lineage.corpus_version}` (`{candidate.lineage.corpus_digest}`)",
        f"Policy: `{candidate.lineage.policy_version}` (`{candidate.lineage.policy_digest}`)",
        "",
        "## Overall metrics",
        "",
        "| Metric | Baseline | Candidate | Delta |",
        "|---|---:|---:|---:|",
    ]
    for name in ("recall_at_k", "precision_at_k", "mrr", "ndcg_at_k"):
        before = getattr(baseline_metrics, name)
        after = getattr(candidate_metrics, name)
        lines.append(f"| {name} | {before:.4f} | {after:.4f} | {after - before:+.4f} |")
    lines.extend(["", "## Absolute failures", ""])
    lines.extend(
        [f"- `{failure}`" for failure in candidate.hard_failures]
        or ["No absolute failures."]
    )
    lines.extend(["", "## Slice metrics", ""])
    for name, result in sorted(candidate.slices.items()):
        if result.metrics is None:
            lines.append(
                f"- **{name}**: no retrieval metrics, cases={result.case_count}"
            )
            continue
        lines.append(
            f"- **{name}**: recall={result.metrics.recall_at_k:.4f}, "
            f"mrr={result.metrics.mrr:.4f}, cases={result.case_count}"
        )
    return "\n".join(lines) + "\n"
