#!/usr/bin/env python3
"""Compile privacy-safe post-provider threshold execution evidence."""

from __future__ import annotations

import argparse
from collections import defaultdict
from pathlib import Path

from app.evaluation.calibration_compatibility import (
    ThresholdEvaluationCaseEvidence,
    ThresholdExecutionEvidence,
)
from app.evaluation.threshold_replay import (
    ThresholdReplayDataset,
    ThresholdReplayVariant,
)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    arguments = parser.parse_args()

    dataset = ThresholdReplayDataset.model_validate_json(arguments.input.read_text())
    variants_by_case: dict[str, list[ThresholdReplayVariant]] = defaultdict(list)
    for variant in dataset.variants:
        variants_by_case[variant.case_id].append(variant)
    evidence = ThresholdExecutionEvidence(
        cases=tuple(
            ThresholdEvaluationCaseEvidence(
                case_id=case_id,
                complete_pre_threshold_lineage=all(
                    not variant.absolute_failures for variant in variants
                ),
                reranker_scores=tuple(
                    candidate.reranker_score
                    for variant in variants
                    for candidate in variant.pre_threshold_candidates
                ),
            )
            for case_id, variants in sorted(variants_by_case.items())
        )
    )
    arguments.output.parent.mkdir(parents=True, exist_ok=True)
    arguments.output.write_text(evidence.model_dump_json(indent=2) + "\n")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
