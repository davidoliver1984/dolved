"""Stable human-readable experiment comparison reports."""

from app.evaluation.models import ExperimentResult


def comparison_report(candidate: ExperimentResult, baseline: ExperimentResult) -> str:
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
        before = getattr(baseline.aggregate.metrics, name)
        after = getattr(candidate.aggregate.metrics, name)
        lines.append(f"| {name} | {before:.4f} | {after:.4f} | {after - before:+.4f} |")
    lines.extend(["", "## Absolute failures", ""])
    lines.extend(
        [f"- `{failure}`" for failure in candidate.hard_failures]
        or ["No absolute failures."]
    )
    lines.extend(["", "## Slice metrics", ""])
    for name, result in sorted(candidate.slices.items()):
        lines.append(
            f"- **{name}**: recall={result.metrics.recall_at_k:.4f}, "
            f"mrr={result.metrics.mrr:.4f}, cases={result.case_count}"
        )
    return "\n".join(lines) + "\n"
