#!/usr/bin/env python3
"""Replay evidence thresholds without calling retrieval providers."""

from __future__ import annotations

import argparse
from pathlib import Path

from app.evaluation.threshold_policy import ThresholdCalibrationPolicy
from app.evaluation.threshold_replay import ThresholdReplayDataset, replay_thresholds


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True, type=Path)
    parser.add_argument("--policy", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    parser.add_argument("--report", required=True, type=Path)
    arguments = parser.parse_args()

    dataset = ThresholdReplayDataset.model_validate_json(arguments.input.read_text())
    policy = ThresholdCalibrationPolicy.model_validate_json(
        arguments.policy.read_text()
    )
    result = replay_thresholds(dataset, policy)
    arguments.output.parent.mkdir(parents=True, exist_ok=True)
    arguments.output.write_text(result.model_dump_json(indent=2) + "\n")
    arguments.report.write_text(_report(result.model_dump(mode="json")))
    return 0


def _report(result: dict[str, object]) -> str:
    boundaries = result["boundaries"]
    assert isinstance(boundaries, list)
    lines = [
        "# Evidence threshold calibration replay",
        "",
        f"Selected threshold: `{result['selected_threshold']}`",
        "",
        (
            "Benchmark precision is measured only against annotated EvidenceUnits. "
            "Accepted candidates without that coverage are reported as "
            "**uncredited / unannotated**, not as false positives."
        ),
        "",
        "| Threshold | Case-first recall | Benchmark precision | Controlled rejection | Uncredited / unannotated |",
        "| ---: | ---: | ---: | ---: | ---: |",
    ]
    for boundary in boundaries:
        assert isinstance(boundary, dict)
        metrics = boundary["metrics"]
        assert isinstance(metrics, dict)
        lines.append(
            "| {threshold} | {recall:.6f} | {precision:.6f} | {rejection:.6f} | {uncredited} |".format(
                threshold=metrics["threshold"],
                recall=metrics["case_first_expected_evidence_unit_recall"],
                precision=metrics["benchmark_precision"],
                rejection=metrics["controlled_rejection_correctness"],
                uncredited=metrics["uncredited_unannotated_accepted_candidates"],
            )
        )
    return "\n".join(lines) + "\n"


if __name__ == "__main__":
    raise SystemExit(main())
