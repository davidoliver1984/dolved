"""Deterministic human-readable projections of saved evaluation run artefacts."""

from __future__ import annotations

import html
import json
import math
from collections.abc import Mapping
from dataclasses import dataclass
from pathlib import Path
from statistics import mean
from typing import Any

import plotly.graph_objects as go  # type: ignore[import-untyped]
import plotly.io as pio  # type: ignore[import-untyped]
from plotly.offline.offline import get_plotlyjs  # type: ignore[import-untyped]

from app.evaluation.canonical import content_digest

METRICS = ("recall_at_k", "precision_at_k", "mrr", "ndcg_at_k")
METRIC_LABELS = {
    "recall_at_k": "Recall@K",
    "precision_at_k": "Precision@K",
    "mrr": "MRR",
    "ndcg_at_k": "nDCG@K",
}
RUN_STATUSES = {"EXPERIMENTAL", "PASS", "FAIL", "REJECTED", "ACCEPTED", "PROMOTED"}


@dataclass(frozen=True)
class RunArtifacts:
    run_dir: Path
    config: dict[str, Any]
    raw_result: dict[str, Any]
    result: dict[str, Any]
    comparison: dict[str, Any] | None


def load_json(path: Path) -> Any:
    return json.loads(path.read_text())


def canonical_json(value: Any) -> str:
    return json.dumps(value, indent=2, ensure_ascii=False, sort_keys=True) + "\n"


def load_run_artifacts(run_dir: Path) -> RunArtifacts:
    config_path = run_dir / "config.json"
    result_path = run_dir / "result.json"
    if not config_path.is_file() or not result_path.is_file():
        raise ValueError("a run requires config.json and result.json")
    config = _object(load_json(config_path), "config.json")
    raw_result = _object(load_json(result_path), "result.json")
    _validate_config(config, run_dir.name)
    result = _selected_result(raw_result, config.get("result_selector"))
    _validate_result_lineage(config, raw_result, result)
    comparison_path = run_dir / "comparison.json"
    comparison = (
        _object(load_json(comparison_path), "comparison.json")
        if comparison_path.is_file()
        else None
    )
    return RunArtifacts(run_dir, config, raw_result, result, comparison)


def write_comparison(run_dir: Path, baseline_path: Path) -> dict[str, Any]:
    artifacts = load_run_artifacts(run_dir)
    baseline_raw = _object(load_json(baseline_path), str(baseline_path))
    baseline = _selected_result(
        baseline_raw, artifacts.config.get("baseline_result_selector")
    )
    for field in ("corpus_version", "corpus_digest"):
        if baseline["lineage"][field] != artifacts.result["lineage"][field]:
            raise ValueError(f"baseline and candidate disagree on {field}")
    candidate_metrics = artifacts.result["aggregate"]["metrics"]
    baseline_metrics = baseline["aggregate"]["metrics"]
    comparison = {
        "schema_version": "v1",
        "baseline_experiment_id": baseline["experiment_id"],
        "baseline_repository_commit": baseline["lineage"]["repository_commit"],
        "baseline_result_digest": content_digest(baseline_raw),
        "candidate_result_digest": content_digest(artifacts.raw_result),
        "metrics": {
            metric: {
                "baseline": baseline_metrics[metric],
                "candidate": candidate_metrics[metric],
                "delta": candidate_metrics[metric] - baseline_metrics[metric],
            }
            for metric in METRICS
        },
        "slices": _slice_comparison(artifacts.result, baseline),
        "gate": artifacts.config.get("gate"),
    }
    (run_dir / "comparison.json").write_text(canonical_json(comparison))
    return comparison


def generate_run_report(run_dir: Path) -> RunArtifacts:
    artifacts = load_run_artifacts(run_dir)
    (run_dir / "report.md").write_text(markdown_report(artifacts))
    (run_dir / "report.html").write_text(html_report(artifacts))
    notes_path = run_dir / "notes.md"
    if not notes_path.exists():
        notes_path.write_text(
            "# Hypothesis\n\n# Change From Baseline\n\n# What Happened\n\n"
            "# What I Learned\n\n# Decision\n\n# Next Experiment\n"
        )
    return artifacts


def update_experiment_index(runs_root: Path, index_path: Path) -> None:
    rows: list[str] = []
    for run_dir in sorted(path for path in runs_root.iterdir() if path.is_dir()):
        if (
            not (run_dir / "config.json").is_file()
            or not (run_dir / "result.json").is_file()
        ):
            continue
        artifacts = load_run_artifacts(run_dir)
        metrics = artifacts.result["aggregate"]["metrics"]
        config = artifacts.config
        benchmark = config["benchmark"]
        marker = _baseline_marker(config)
        rows.append(
            "| "
            + " | ".join(
                (
                    f"[{config['run_id']}](runs/{config['run_id']}/report.md)",
                    artifacts.result["executed_at"][:10],
                    _escape_markdown(config["description"]),
                    f"{benchmark['id']} {benchmark['version']}",
                    _metric(metrics["recall_at_k"]),
                    _metric(metrics["precision_at_k"]),
                    _metric(metrics["mrr"]),
                    _metric(metrics["ndcg_at_k"]),
                    config["status"],
                    _escape_markdown(str(config.get("decision") or "—")),
                    marker,
                )
            )
            + " |"
        )
    lines = [
        "# Evaluation experiments",
        "",
        "This index is generated from immutable run directories. Raw JSON remains authoritative.",
        "",
        "| Experiment | Date | Change | Benchmark | Recall | Precision | MRR | nDCG | Gate/status | Decision | Baseline |",
        "|---|---|---|---|---:|---:|---:|---:|---|---|---|",
        *rows,
    ]
    if not rows:
        lines.extend(["", "No persisted experiment runs yet."])
    index_path.parent.mkdir(parents=True, exist_ok=True)
    index_path.write_text("\n".join(lines) + "\n")


def markdown_report(artifacts: RunArtifacts) -> str:
    config = artifacts.config
    result = artifacts.result
    aggregate = result["aggregate"]
    lines = [
        f"# Evaluation run: {config['run_id']}",
        "",
        f"**Status:** {config['status']}",
        "",
        "## Run summary",
        "",
        "| Field | Value |",
        "|---|---|",
        f"| Description | {_escape_markdown(config['description'])} |",
        f"| Executed at | `{result['executed_at']}` |",
        f"| Repository commit | `{config['repository']['commit']}` |",
        f"| Working tree | `{'dirty' if config['repository']['dirty'] else 'clean'}` |",
        f"| Benchmark | `{config['benchmark']['id']}` / `{config['benchmark']['version']}` |",
        f"| Benchmark digest | `{config['benchmark']['digest']}` |",
        f"| Corpus | `{config['corpus']['version']}` / `{config['corpus']['digest']}` |",
        f"| Split | `{config['split']['version']}` / `{config['split']['digest']}` |",
        f"| Harness | `{config['harness_version']}` |",
        f"| Threshold policy | `{config.get('threshold_policy_identity') or 'not applicable'}` |",
        "",
        "## Exact tested configuration",
        "",
        *_configuration_markdown(config),
        "",
        "## Headline metrics",
        "",
        "| Metric | Value |",
        "|---|---:|",
        *[
            f"| {METRIC_LABELS[name]} | {_metric(aggregate['metrics'][name])} |"
            for name in METRICS
        ],
        f"| Planner accuracy | {_metric(aggregate['planner_accuracy'])} |",
        f"| Eligibility accuracy | {_metric(aggregate['eligibility_accuracy'])} |",
        f"| Outcome accuracy | {_metric(aggregate['outcome_accuracy'])} |",
        "",
        "## Baseline comparison",
        "",
        *_comparison_markdown(artifacts.comparison),
        "",
        "## Slice metrics",
        "",
        "| Slice | Cases | Recall | Precision | MRR | nDCG | Planner | Eligibility | Outcome |",
        "|---|---:|---:|---:|---:|---:|---:|---:|---:|",
        *_slice_rows(result),
        "",
        "## Hard failures",
        "",
        *([f"- `{failure}`" for failure in result["hard_failures"]] or ["None."]),
        "",
        "## Operational metrics",
        "",
        *_operational_markdown(artifacts),
        "",
        "## Strongest improvements and regressions",
        "",
        *_strongest_changes(artifacts.comparison),
        "",
        "## Case-level drill-down",
        "",
        *_case_markdown(result),
        "",
        "## Available and missing stage lineage",
        "",
        *_lineage_availability(artifacts),
        "",
        "## Decision",
        "",
        f"Status: **{config['status']}**",
        "",
        f"Decision: {config.get('decision') or 'No human decision recorded.'}",
        "",
        "Generated from `result.json`, `config.json` and optional `comparison.json`; raw JSON is authoritative.",
    ]
    return "\n".join(lines) + "\n"


def html_report(artifacts: RunArtifacts) -> str:
    config = artifacts.config
    result = artifacts.result
    charts = {
        "headline": _figure_html(_headline_figure(result), "headline-chart"),
        "comparison": _figure_html(
            _comparison_figure(artifacts.comparison), "comparison-chart"
        ),
        "slices": _figure_html(_slice_figure(result), "slice-chart"),
        "funnel": _figure_html(_funnel_figure(artifacts), "funnel-chart"),
    }
    hard_failures = result["hard_failures"]
    hard_failure_html = (
        "<ul>"
        + "".join(
            f"<li><code>{html.escape(item)}</code></li>" for item in hard_failures
        )
        + "</ul>"
        if hard_failures
        else "<p>None.</p>"
    )
    return f"""<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{html.escape(config["run_id"])} evaluation report</title>
<style>{_css()}</style><script>{get_plotlyjs()}</script></head>
<body><main>
<header><div><p class="eyebrow">Evaluation experiment</p><h1>{html.escape(config["run_id"])}</h1><p>{html.escape(config["description"])}</p></div><span class="status {config["status"].lower()}">{html.escape(config["status"])}</span></header>
<section><h2>Run identity</h2>{_identity_html(artifacts)}</section>
<section><h2>Tested configuration</h2>{_configuration_html(config)}</section>
<section><h2>Headline retrieval metrics</h2>{charts["headline"]}</section>
<section><h2>Baseline comparison</h2>{charts["comparison"]}{_comparison_html(artifacts.comparison)}</section>
<section><h2>Slice performance</h2>{charts["slices"]}{_slice_table_html(result)}</section>
<section><h2>Candidate funnel</h2>{charts["funnel"]}{_funnel_note(artifacts)}</section>
<section><h2>Operational metrics</h2>{_operational_html(artifacts)}</section>
<section class="failures"><h2>Hard failures / gate failures</h2>{hard_failure_html}{_gate_html(artifacts.comparison)}</section>
<section><h2>Case-level drill-down</h2><p class="muted">Open a case to follow expected evidence and every observed candidate through Dense → Sparse → RRF → Reranker → Threshold → Final evidence. An em dash means that stage data was not available; it is never inferred.</p>{_case_table_html(result)}</section>
<section><h2>Lineage availability</h2><ul>{"".join(f"<li>{html.escape(item)}</li>" for item in _lineage_availability(artifacts))}</ul></section>
<section><h2>Decision</h2><p><strong>{html.escape(config["status"])}</strong> — {html.escape(str(config.get("decision") or "No human decision recorded."))}</p></section>
</main><script>{_sorting_script()}</script></body></html>"""


def _object(value: Any, label: str) -> dict[str, Any]:
    if not isinstance(value, dict):
        raise TypeError(f"{label} must contain a JSON object")
    return value


def _validate_config(config: Mapping[str, Any], directory_name: str) -> None:
    required = {
        "schema_version",
        "run_id",
        "description",
        "status",
        "repository",
        "benchmark",
        "corpus",
        "split",
        "harness_version",
        "providers",
        "candidate_pipeline",
    }
    missing = required.difference(config)
    if missing:
        raise ValueError(f"config.json is missing: {sorted(missing)}")
    if config["run_id"] != directory_name:
        raise ValueError("run_id must match its directory name")
    if config["status"] not in RUN_STATUSES:
        raise ValueError("config.json has an unsupported status")
    if not isinstance(config["repository"].get("dirty"), bool):
        raise TypeError("repository.dirty must be a boolean")
    for key in ("repository", "benchmark", "corpus", "split", "providers"):
        if not isinstance(config[key], dict):
            raise TypeError(f"{key} must be an object")
    required_providers = {
        "dense": {
            "provider",
            "model",
            "embedding_profile_fingerprint",
            "dimensions",
            "adapter_version",
        },
        "sparse": {
            "provider",
            "model",
            "sparse_profile_fingerprint",
            "model_revision",
            "adapter_version",
        },
        "fusion": {"strategy", "version", "rrf_k"},
        "reranking": {"provider", "model", "adapter_version"},
    }
    for component, fields in required_providers.items():
        value = config["providers"].get(component)
        if not isinstance(value, dict) or fields.difference(value):
            raise ValueError(f"providers.{component} must record {sorted(fields)}")
    required_pipeline = {
        "dense_candidate_k",
        "sparse_candidate_k",
        "fusion_candidate_k",
        "reranker_candidate_k",
        "evidence_threshold",
        "final_evidence_k",
    }
    if not isinstance(
        config["candidate_pipeline"], dict
    ) or required_pipeline.difference(config["candidate_pipeline"]):
        raise ValueError(f"candidate_pipeline must record {sorted(required_pipeline)}")


def _validate_result_lineage(
    config: Mapping[str, Any], raw_result: Mapping[str, Any], result: Mapping[str, Any]
) -> None:
    lineage = result["lineage"]
    expected = {
        "corpus_version": config["corpus"]["version"],
        "corpus_digest": config["corpus"]["digest"],
        "harness_version": config["harness_version"],
    }
    for field, value in expected.items():
        if lineage[field] != value:
            raise ValueError(f"config and result disagree on {field}")
    recorded_revision = str(lineage["repository_commit"])
    configured_commit = str(config["repository"]["commit"])
    valid_revisions = {configured_commit}
    if config["repository"]["dirty"]:
        valid_revisions.add(f"{configured_commit}-dirty")
    if recorded_revision not in valid_revisions:
        raise ValueError("config and result disagree on repository commit")
    policy = raw_result.get("policy")
    threshold_identity = config.get("threshold_policy_identity")
    if isinstance(policy, dict) and policy.get("fingerprint") != threshold_identity:
        raise ValueError("config and result disagree on threshold policy identity")


def _selected_result(raw: dict[str, Any], selector: Any) -> dict[str, Any]:
    if "aggregate" in raw and "variants" in raw:
        return raw
    selected = selector or "hybrid"
    value = raw.get(selected)
    if not isinstance(value, dict) or "aggregate" not in value:
        raise ValueError(
            "result.json is not an ExperimentResult or selectable result envelope"
        )
    return value


def _slice_comparison(
    candidate: dict[str, Any], baseline: dict[str, Any]
) -> dict[str, Any]:
    common = sorted(
        set(candidate.get("slices", {})).intersection(baseline.get("slices", {}))
    )
    return {
        name: {
            metric: candidate["slices"][name]["metrics"][metric]
            - baseline["slices"][name]["metrics"][metric]
            for metric in METRICS
        }
        for name in common
    }


def _configuration_markdown(config: dict[str, Any]) -> list[str]:
    lines = [
        "### Provider/model lineage",
        "",
        "| Component | Configuration |",
        "|---|---|",
    ]
    for component, value in sorted(config["providers"].items()):
        lines.append(f"| {component} | `{_compact_json(value)}` |")
    lines.extend(
        ["", "### Candidate pipeline", "", "| Setting | Value |", "|---|---:|"]
    )
    for key, value in config["candidate_pipeline"].items():
        lines.append(f"| {key} | `{value}` |")
    return lines


def _comparison_markdown(comparison: dict[str, Any] | None) -> list[str]:
    if comparison is None:
        return ["No baseline comparison artefact was supplied."]
    lines = [
        f"Baseline: `{comparison['baseline_experiment_id']}`",
        "",
        "| Metric | Baseline | Candidate | Delta |",
        "|---|---:|---:|---:|",
    ]
    for metric in METRICS:
        values = comparison["metrics"][metric]
        lines.append(
            f"| {METRIC_LABELS[metric]} | {_metric(values['baseline'])} | "
            f"{_metric(values['candidate'])} | {values['delta']:+.4f} |"
        )
    return lines


def _slice_rows(result: dict[str, Any]) -> list[str]:
    rows = []
    for name, value in sorted(result.get("slices", {}).items()):
        metrics = value["metrics"]
        rows.append(
            f"| {name} | {value['case_count']} | {_metric(metrics['recall_at_k'])} | "
            f"{_metric(metrics['precision_at_k'])} | {_metric(metrics['mrr'])} | "
            f"{_metric(metrics['ndcg_at_k'])} | {_metric(value['planner_accuracy'])} | "
            f"{_metric(value['eligibility_accuracy'])} | {_metric(value['outcome_accuracy'])} |"
        )
    return rows or ["| — | 0 | — | — | — | — | — | — | — |"]


def _operational_summary(artifacts: RunArtifacts) -> dict[str, Any]:
    envelope = artifacts.raw_result.get("operational")
    if isinstance(envelope, dict):
        return envelope
    observations = [
        item.get("operational", {}) for item in artifacts.result["variants"]
    ]
    latencies = sorted(float(item.get("latency_ms", 0)) for item in observations)
    return {
        "latency_ms": _latency(latencies),
        "token_usage": sum(int(item.get("token_usage", 0)) for item in observations),
        "provider_cost": sum(
            float(item.get("provider_cost", 0)) for item in observations
        ),
        "request_count": sum(
            int(item.get("request_count", 0)) for item in observations
        ),
    }


def _operational_markdown(artifacts: RunArtifacts) -> list[str]:
    return ["```json", canonical_json(_operational_summary(artifacts)).rstrip(), "```"]


def _strongest_changes(comparison: dict[str, Any] | None) -> list[str]:
    if comparison is None:
        return ["No comparison data available."]
    changes = sorted(
        (
            (values[metric], f"{slice_name} / {METRIC_LABELS[metric]}")
            for slice_name, values in comparison.get("slices", {}).items()
            for metric in METRICS
        ),
        key=lambda item: item[0],
    )
    if not changes:
        return ["No common baseline slices available."]
    regressions = [item for item in changes if item[0] < 0][:5]
    improvements = [item for item in reversed(changes) if item[0] > 0][:5]
    return [
        "### Regressions",
        "",
        *([f"- {label}: {delta:+.4f}" for delta, label in regressions] or ["None."]),
        "",
        "### Improvements",
        "",
        *([f"- {label}: {delta:+.4f}" for delta, label in improvements] or ["None."]),
    ]


def _case_markdown(result: dict[str, Any]) -> list[str]:
    lines: list[str] = []
    for item in sorted(
        result["variants"], key=lambda value: (value["case_id"], value["variant_id"])
    ):
        metrics = item["metrics"]
        lines.extend(
            [
                f"### `{item['case_id']}` / `{item['variant_id']}`",
                "",
                f"- Planner correct: `{item['planner_correct']}`",
                f"- Eligibility correct: `{item['eligibility_correct']}`",
                f"- Outcome correct: `{item['outcome_correct']}`",
                f"- Expected outcome: `{item.get('expected_outcome') or 'not recorded'}`",
                f"- Text capture: `{item.get('text_capture_mode', 'DISABLED')}`",
                f"- Question: {_escape_markdown(item.get('question') or 'not retained')}",
                f"- Covered EvidenceUnits: `{', '.join(item['covered_evidence_ids']) or 'none'}`",
                f"- Metrics: recall={_metric(metrics['recall_at_k'])}, precision={_metric(metrics['precision_at_k'])}, MRR={_metric(metrics['mrr'])}, nDCG={_metric(metrics['ndcg_at_k'])}",
                f"- Hard failures: `{', '.join(item['hard_failures']) or 'none'}`",
                "",
            ]
        )
        for side, side_metrics in sorted(item.get("side_metrics", {}).items()):
            lines.append(
                f"  - {side}: recall={_metric(side_metrics['recall_at_k'])}, "
                f"precision={_metric(side_metrics['precision_at_k'])}, "
                f"MRR={_metric(side_metrics['mrr'])}, nDCG={_metric(side_metrics['ndcg_at_k'])}"
            )
        lines.append("")
        expected = item.get("expected_evidence", [])
        if expected:
            lines.extend(
                [
                    "Expected evidence:",
                    "",
                    "| Side | EvidenceUnit | Family | Version | Source |",
                    "|---|---|---|---|---|",
                    *[
                        f"| {value['side']} | `{value['evidence_unit_id']}` | `{value['document_family_id']}` | `{value['document_version_id']}` | {_escape_markdown(value.get('source_path') or 'not retained')} |"
                        for value in expected
                    ],
                    "",
                ]
            )
        for side in _variant_sides(item):
            funnel = next(
                (
                    value
                    for value in item.get("candidate_funnel", [])
                    if value["side"] == side
                ),
                None,
            )
            lines.extend([f"#### {side}", ""])
            if funnel is not None:
                lines.extend(
                    [
                        "Candidate funnel: "
                        + " → ".join(
                            f"{label}={_optional(value)}"
                            for label, value in _funnel_values(funnel)
                        ),
                        "",
                    ]
                )
            candidates = [
                value
                for value in item.get("candidate_lineage", [])
                if value["side"] == side
            ]
            if candidates:
                lines.extend(
                    [
                        "| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |",
                        "|---|---|---:|---:|---:|---:|---|---|---|",
                        *[
                            _candidate_markdown_row(candidate)
                            for candidate in _sort_candidates(candidates)
                        ],
                        "",
                    ]
                )
    return lines


def _lineage_availability(artifacts: RunArtifacts) -> list[str]:
    available = [
        "Available: case_id, variant_id, correctness flags, final per-case metrics, side metrics, covered EvidenceUnit IDs and final operational observations.",
    ]
    candidate_lineage = any(
        item.get("candidate_lineage") for item in artifacts.result["variants"]
    )
    if candidate_lineage:
        available.append(
            "Available: question/expectation context, exact candidate-stage lineage and per-side candidate funnels from result.json."
        )
    else:
        available.append(
            "Candidate-stage lineage is absent from this saved result (for example, a historical artefact or a controlled outcome that short-circuited retrieval)."
        )
    return available


def _headline_figure(result: dict[str, Any]) -> go.Figure:
    metrics = result["aggregate"]["metrics"]
    figure = go.Figure(
        go.Bar(
            x=[METRIC_LABELS[name] for name in METRICS],
            y=[metrics[name] for name in METRICS],
            marker_color="#2563eb",
        )
    )
    figure.update_layout(
        yaxis_range=[0, 1], margin={"l": 40, "r": 20, "t": 20, "b": 40}, height=340
    )
    return figure


def _comparison_figure(comparison: dict[str, Any] | None) -> go.Figure:
    if comparison is None:
        return _empty_figure("No comparison.json supplied")
    figure = go.Figure()
    labels = [METRIC_LABELS[name] for name in METRICS]
    figure.add_bar(
        name="Baseline",
        x=labels,
        y=[comparison["metrics"][name]["baseline"] for name in METRICS],
    )
    figure.add_bar(
        name="Candidate",
        x=labels,
        y=[comparison["metrics"][name]["candidate"] for name in METRICS],
    )
    figure.update_layout(
        barmode="group",
        yaxis_range=[0, 1],
        margin={"l": 40, "r": 20, "t": 20, "b": 40},
        height=360,
    )
    return figure


def _slice_figure(result: dict[str, Any]) -> go.Figure:
    slices = sorted(result.get("slices", {}))
    if not slices:
        return _empty_figure("No slice metrics available")
    figure = go.Figure(
        data=go.Heatmap(
            x=[METRIC_LABELS[name] for name in METRICS],
            y=slices,
            z=[
                [result["slices"][name]["metrics"][metric] for metric in METRICS]
                for name in slices
            ],
            zmin=0,
            zmax=1,
            colorscale="Blues",
            hovertemplate="%{y}<br>%{x}: %{z:.4f}<extra></extra>",
        )
    )
    figure.update_layout(
        height=max(420, len(slices) * 25), margin={"l": 170, "r": 20, "t": 20, "b": 40}
    )
    return figure


def _funnel_figure(artifacts: RunArtifacts) -> go.Figure:
    funnels = [
        funnel
        for variant in artifacts.result["variants"]
        for funnel in variant.get("candidate_funnel", [])
    ]
    if not funnels:
        return _empty_figure(
            "Candidate-stage counts are not present in the saved artefacts"
        )
    stages = (
        ("dense_candidate_count", "Dense"),
        ("sparse_candidate_count", "Sparse"),
        ("unique_post_fusion_count", "Unique after RRF"),
        ("candidates_sent_to_reranker", "Sent to reranker"),
        ("candidates_surviving_threshold", "Passed threshold"),
        ("final_evidence_count", "Final evidence"),
    )
    present = [
        (label, sum(value[field] for value in funnels if value.get(field) is not None))
        for field, label in stages
        if any(value.get(field) is not None for value in funnels)
    ]
    return go.Figure(
        go.Funnel(
            y=[name for name, _ in present],
            x=[value for _, value in present],
        )
    )


def _empty_figure(message: str) -> go.Figure:
    figure = go.Figure()
    figure.add_annotation(text=message, x=0.5, y=0.5, showarrow=False)
    figure.update_xaxes(visible=False)
    figure.update_yaxes(visible=False)
    figure.update_layout(height=180, margin={"l": 20, "r": 20, "t": 20, "b": 20})
    return figure


def _figure_html(figure: go.Figure, div_id: str) -> str:
    return pio.to_html(
        figure,
        full_html=False,
        include_plotlyjs=False,
        div_id=div_id,
        config={"displaylogo": False, "responsive": True},
    )


def _identity_html(artifacts: RunArtifacts) -> str:
    config = artifacts.config
    values = (
        ("Executed", artifacts.result["executed_at"]),
        ("Commit", config["repository"]["commit"]),
        ("Tree", "dirty" if config["repository"]["dirty"] else "clean"),
        (
            "Benchmark",
            f"{config['benchmark']['id']} / {config['benchmark']['version']}",
        ),
        ("Benchmark digest", config["benchmark"]["digest"]),
        ("Corpus digest", config["corpus"]["digest"]),
        ("Split", f"{config['split']['version']} / {config['split']['digest']}"),
        ("Harness", config["harness_version"]),
        (
            "Threshold policy",
            config.get("threshold_policy_identity") or "not applicable",
        ),
    )
    return (
        "<dl class=identity>"
        + "".join(
            f"<div><dt>{html.escape(label)}</dt><dd>{html.escape(str(value))}</dd></div>"
            for label, value in values
        )
        + "</dl>"
    )


def _configuration_html(config: dict[str, Any]) -> str:
    provider_rows = "".join(
        f"<tr><td>{html.escape(component)}</td><td><code>{html.escape(_compact_json(value))}</code></td></tr>"
        for component, value in sorted(config["providers"].items())
    )
    pipeline_rows = "".join(
        f"<tr><td>{html.escape(key)}</td><td><code>{html.escape(str(value))}</code></td></tr>"
        for key, value in config["candidate_pipeline"].items()
    )
    return f"<h3>Provider/model lineage</h3><table><thead><tr><th>Component</th><th>Configuration</th></tr></thead><tbody>{provider_rows}</tbody></table><h3>Candidate pipeline</h3><table><thead><tr><th>Setting</th><th>Value</th></tr></thead><tbody>{pipeline_rows}</tbody></table>"


def _comparison_html(comparison: dict[str, Any] | None) -> str:
    if comparison is None:
        return "<p class=muted>No baseline comparison artefact was supplied.</p>"
    rows = "".join(
        f"<tr><td>{METRIC_LABELS[name]}</td><td>{_metric(value['baseline'])}</td><td>{_metric(value['candidate'])}</td><td class={'positive' if value['delta'] > 0 else 'negative' if value['delta'] < 0 else ''}>{value['delta']:+.4f}</td></tr>"
        for name, value in comparison["metrics"].items()
    )
    return f"<table><thead><tr><th>Metric</th><th>Baseline</th><th>Candidate</th><th>Delta</th></tr></thead><tbody>{rows}</tbody></table>"


def _slice_table_html(result: dict[str, Any]) -> str:
    rows = "".join(
        f"<tr><td>{html.escape(name)}</td><td>{value['case_count']}</td>"
        + "".join(f"<td>{_metric(value['metrics'][metric])}</td>" for metric in METRICS)
        + "</tr>"
        for name, value in sorted(result.get("slices", {}).items())
    )
    return f"<table class=sortable><thead><tr><th>Slice</th><th>Cases</th>{''.join(f'<th>{METRIC_LABELS[name]}</th>' for name in METRICS)}</tr></thead><tbody>{rows}</tbody></table>"


def _operational_html(artifacts: RunArtifacts) -> str:
    return f"<pre>{html.escape(canonical_json(_operational_summary(artifacts)))}</pre>"


def _gate_html(comparison: dict[str, Any] | None) -> str:
    gate = comparison.get("gate") if comparison else None
    if not gate:
        return "<p>No gate assessment recorded.</p>"
    return f"<pre>{html.escape(canonical_json(gate))}</pre>"


def _case_table_html(result: dict[str, Any]) -> str:
    rows = []
    details = []
    for item in sorted(
        result["variants"], key=lambda value: (value["case_id"], value["variant_id"])
    ):
        metrics = item["metrics"]
        rows.append(
            f"<tr><td>{html.escape(item['case_id'])}</td><td>{html.escape(item['variant_id'])}</td>"
            f"<td>{str(item['planner_correct']).lower()}</td><td>{str(item['eligibility_correct']).lower()}</td><td>{str(item['outcome_correct']).lower()}</td>"
            f"<td>{html.escape(', '.join(item['covered_evidence_ids']) or 'none')}</td>"
            + "".join(f"<td>{_metric(metrics[name])}</td>" for name in METRICS)
            + f"<td>{html.escape(', '.join(item['hard_failures']) or 'none')}</td></tr>"
        )
        expected_rows = "".join(
            "<tr>"
            f"<td>{html.escape(value['side'])}</td>"
            f"<td><code>{html.escape(value['evidence_unit_id'])}</code></td>"
            f"<td><code>{html.escape(value['document_family_id'])}</code></td>"
            f"<td><code>{html.escape(value['document_version_id'])}</code></td>"
            f"<td>{html.escape(value.get('source_path') or 'not retained')}</td>"
            "</tr>"
            for value in item.get("expected_evidence", [])
        )
        expected_html = (
            "<table><thead><tr><th>Side</th><th>EvidenceUnit</th><th>Family</th><th>Version</th><th>Source</th></tr></thead>"
            f"<tbody>{expected_rows}</tbody></table>"
            if expected_rows
            else "<p class=muted>No expected EvidenceUnits (controlled zero-evidence or clarification case).</p>"
        )
        side_sections = "".join(
            _side_drilldown_html(item, side) for side in _variant_sides(item)
        )
        details.append(
            "<details class=case-detail>"
            f"<summary><strong>{html.escape(item['case_id'])}</strong> / {html.escape(item['variant_id'])} — {html.escape(item.get('expected_outcome') or 'outcome not recorded')}</summary>"
            f"<p><strong>Question:</strong> {html.escape(item.get('question') or 'not retained')} <span class=muted>({html.escape(item.get('text_capture_mode', 'DISABLED'))})</span></p>"
            f"<h4>Expected evidence</h4>{expected_html}{side_sections}</details>"
        )
    headings = "".join(
        f"<th>{label}</th>"
        for label in (
            "Case",
            "Variant",
            "Planner",
            "Eligibility",
            "Outcome",
            "Covered EvidenceUnits",
            *[METRIC_LABELS[name] for name in METRICS],
            "Failures",
        )
    )
    return (
        f"<table class=sortable><thead><tr>{headings}</tr></thead><tbody>{''.join(rows)}</tbody></table>"
        + "<h3>Evidence-path inspection</h3>"
        + "".join(details)
    )


def _side_drilldown_html(item: dict[str, Any], side: str) -> str:
    funnel = next(
        (value for value in item.get("candidate_funnel", []) if value["side"] == side),
        None,
    )
    funnel_html = (
        "<ol class=funnel-path>"
        + "".join(
            f"<li><span>{html.escape(label)}</span><strong>{html.escape(_optional(value))}</strong></li>"
            for label, value in _funnel_values(funnel)
        )
        + "</ol>"
        if funnel is not None
        else "<p class=muted>No retrieval stages ran for this side.</p>"
    )
    candidates = _sort_candidates(
        [value for value in item.get("candidate_lineage", []) if value["side"] == side]
    )
    candidate_rows = "".join(_candidate_html_row(value) for value in candidates)
    candidates_html = (
        "<div class=table-scroll><table class=sortable><thead><tr>"
        "<th>Candidate / chunk</th><th>Family / version</th><th>Dense</th><th>Sparse</th><th>RRF</th><th>Reranker</th><th>Threshold</th><th>Final</th><th>EvidenceUnits</th>"
        f"</tr></thead><tbody>{candidate_rows}</tbody></table></div>"
        if candidate_rows
        else "<p class=muted>No candidates were observed.</p>"
    )
    return f"<h4>{html.escape(side)}</h4>{funnel_html}{candidates_html}"


def _candidate_html_row(candidate: dict[str, Any]) -> str:
    return (
        "<tr>"
        f"<td><code>{html.escape(candidate['candidate_id'])}</code><br><span class=muted>{html.escape(candidate['chunk_id'])}</span></td>"
        f"<td><code>{html.escape(candidate['document_family_id'])}</code><br><code>{html.escape(candidate['document_version_id'])}</code></td>"
        f"<td>{html.escape(_rank_score(candidate.get('dense_rank'), candidate.get('dense_score')))}</td>"
        f"<td>{html.escape(_rank_score(candidate.get('sparse_rank'), candidate.get('sparse_score')))}</td>"
        f"<td>{html.escape(_rank_score(candidate.get('fused_rank'), candidate.get('fused_score')))}</td>"
        f"<td>{html.escape(_rank_score(candidate.get('reranker_rank'), candidate.get('reranker_score')))}</td>"
        f"<td>{html.escape(_optional_bool(candidate.get('passed_evidence_threshold')))}</td>"
        f"<td>{'yes' if candidate['included_in_final_evidence'] else 'no'}</td>"
        f"<td>{html.escape(', '.join(candidate['covered_evidence_unit_ids']) or 'none')}</td>"
        "</tr>"
    )


def _candidate_markdown_row(candidate: dict[str, Any]) -> str:
    return (
        "| "
        + " | ".join(
            (
                f"`{candidate['candidate_id']}`<br>`{candidate['chunk_id']}`",
                f"`{candidate['document_family_id']}`<br>`{candidate['document_version_id']}`",
                _rank_score(candidate.get("dense_rank"), candidate.get("dense_score")),
                _rank_score(
                    candidate.get("sparse_rank"), candidate.get("sparse_score")
                ),
                _rank_score(candidate.get("fused_rank"), candidate.get("fused_score")),
                _rank_score(
                    candidate.get("reranker_rank"), candidate.get("reranker_score")
                ),
                _optional_bool(candidate.get("passed_evidence_threshold")),
                "yes" if candidate["included_in_final_evidence"] else "no",
                ", ".join(candidate["covered_evidence_unit_ids"]) or "none",
            )
        )
        + " |"
    )


def _variant_sides(item: dict[str, Any]) -> list[str]:
    sides = {
        value["side"]
        for field in ("expected_evidence", "candidate_funnel", "candidate_lineage")
        for value in item.get(field, [])
    }
    return sorted(sides, key=lambda value: (value != "PRIMARY", value))


def _sort_candidates(candidates: list[dict[str, Any]]) -> list[dict[str, Any]]:
    def sort_key(value: dict[str, Any]) -> tuple[int, int, int, str]:
        for stage, field in enumerate(
            ("reranker_rank", "fused_rank", "dense_rank", "sparse_rank")
        ):
            if value.get(field) is not None:
                return stage, int(value[field]), 0, value["chunk_id"]
        return 4, 0, 0, value["chunk_id"]

    return sorted(candidates, key=sort_key)


def _funnel_values(funnel: dict[str, Any]) -> tuple[tuple[str, Any], ...]:
    return (
        ("Dense", funnel.get("dense_candidate_count")),
        ("Sparse", funnel.get("sparse_candidate_count")),
        ("Unique after RRF", funnel.get("unique_post_fusion_count")),
        ("Reranker", funnel.get("candidates_sent_to_reranker")),
        ("Threshold", funnel.get("candidates_surviving_threshold")),
        ("Final evidence", funnel.get("final_evidence_count")),
    )


def _rank_score(rank: Any, score: Any) -> str:
    if rank is None and score is None:
        return "—"
    rank_value = f"#{rank}" if rank is not None else "rank —"
    score_value = f"{float(score):.6f}" if score is not None else "score —"
    return f"{rank_value} / {score_value}"


def _optional(value: Any) -> str:
    return "—" if value is None else str(value)


def _optional_bool(value: Any) -> str:
    if value is None:
        return "—"
    return "pass" if value else "fail"


def _funnel_note(artifacts: RunArtifacts) -> str:
    if any(item.get("candidate_funnel") for item in artifacts.result["variants"]):
        return "<p class=muted>Totals aggregate observed per-side funnels across case variants. Missing stages are omitted rather than inferred.</p>"
    return "<p class=muted>The current saved result does not preserve candidate-stage counts.</p>"


def _latency(values: list[float]) -> dict[str, float]:
    if not values:
        return {"mean": 0, "p50": 0, "p95": 0, "max": 0}
    return {
        "mean": mean(values),
        "p50": _percentile(values, 0.50),
        "p95": _percentile(values, 0.95),
        "max": values[-1],
    }


def _percentile(values: list[float], percentile: float) -> float:
    index = max(0, math.ceil(len(values) * percentile) - 1)
    return values[index]


def _metric(value: float) -> str:
    return f"{value:.4f}"


def _compact_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"))


def _escape_markdown(value: str) -> str:
    return value.replace("|", "\\|").replace("\n", " ")


def _baseline_marker(config: dict[str, Any]) -> str:
    if config["status"] == "PROMOTED":
        return "promoted"
    if config.get("baseline") is True:
        return "baseline"
    return "—"


def _css() -> str:
    return """body{margin:0;background:#f4f7fb;color:#172033;font-family:Inter,system-ui,sans-serif}main{max-width:1400px;margin:auto;padding:32px}header{display:flex;justify-content:space-between;gap:24px;align-items:start;background:#101827;color:white;padding:28px;border-radius:14px}h1{margin:.1em 0}.eyebrow{text-transform:uppercase;letter-spacing:.12em;color:#93c5fd;font-size:.75rem}.status{padding:8px 12px;border-radius:999px;background:#334155;font-weight:700}.status.pass,.status.accepted,.status.promoted{background:#047857}.status.fail,.status.rejected{background:#b91c1c}section{background:white;margin-top:20px;padding:24px;border-radius:12px;box-shadow:0 1px 3px #0001;overflow:auto}table{border-collapse:collapse;width:100%;font-size:.9rem}th,td{border-bottom:1px solid #dbe2ea;padding:9px;text-align:left;vertical-align:top}th{background:#eef3f8;cursor:pointer}code,pre{font-family:ui-monospace,monospace}pre{white-space:pre-wrap;background:#f7f9fc;padding:14px;border-radius:8px}.identity{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px}.identity div{padding:12px;background:#f7f9fc;border-radius:8px}.identity dt{font-size:.75rem;color:#64748b}.identity dd{margin:4px 0 0;overflow-wrap:anywhere}.failures{border-left:6px solid #dc2626}.positive{color:#047857;font-weight:700}.negative{color:#b91c1c;font-weight:700}.muted{color:#64748b}.case-detail{border:1px solid #dbe2ea;border-radius:10px;margin:12px 0;padding:14px}.case-detail summary{cursor:pointer}.table-scroll{overflow-x:auto}.funnel-path{display:grid;grid-template-columns:repeat(6,minmax(105px,1fr));gap:8px;list-style:none;padding:0}.funnel-path li{background:#eef3f8;border-radius:8px;padding:10px;display:flex;flex-direction:column}.funnel-path span{color:#64748b;font-size:.75rem}@media(max-width:900px){.funnel-path{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){main{padding:12px}header{flex-direction:column}}"""


def _sorting_script() -> str:
    return """document.querySelectorAll('table.sortable th').forEach((th,index)=>th.addEventListener('click',()=>{const table=th.closest('table'),body=table.tBodies[0],rows=[...body.rows],asc=th.dataset.order!=='asc';rows.sort((a,b)=>{const x=a.cells[index].innerText,y=b.cells[index].innerText,nx=Number(x),ny=Number(y);return (!Number.isNaN(nx)&&!Number.isNaN(ny)?nx-ny:x.localeCompare(y))*(asc?1:-1)});rows.forEach(row=>body.appendChild(row));th.dataset.order=asc?'asc':'desc'}));"""
