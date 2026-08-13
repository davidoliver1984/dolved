#!/usr/bin/env python3
"""Compile privacy-safe calibration split independence evidence."""

from __future__ import annotations

import argparse
from pathlib import Path

from app.evaluation.calibration_compatibility import (
    SplitIdentityEvidence,
    build_independence_evidence,
)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--calibration-identities", required=True, type=Path)
    parser.add_argument("--engineering-identities", required=True, type=Path)
    parser.add_argument("--held-out-identities", required=True, type=Path)
    parser.add_argument(
        "--population-frozen-before-provider-execution", action="store_true"
    )
    parser.add_argument("--score-driven-selection", action="store_true")
    parser.add_argument("--post-result-reassignment", action="store_true")
    parser.add_argument("--output", required=True, type=Path)
    arguments = parser.parse_args()

    evidence = build_independence_evidence(
        SplitIdentityEvidence.model_validate_json(
            arguments.calibration_identities.read_text()
        ),
        SplitIdentityEvidence.model_validate_json(
            arguments.engineering_identities.read_text()
        ),
        SplitIdentityEvidence.model_validate_json(
            arguments.held_out_identities.read_text()
        ),
        score_driven_selection=arguments.score_driven_selection,
        post_result_reassignment=arguments.post_result_reassignment,
        population_frozen_before_provider_execution=(
            arguments.population_frozen_before_provider_execution
        ),
    )
    arguments.output.parent.mkdir(parents=True, exist_ok=True)
    arguments.output.write_text(evidence.model_dump_json(indent=2) + "\n")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
