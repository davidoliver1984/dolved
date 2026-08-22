"""Stable human-readable experiment comparison reports."""

from app.evaluation.historical_result import ComparisonResult
from app.evaluation.models import ExperimentResult


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
