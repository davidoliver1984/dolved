#!/usr/bin/env python3
"""Run the provider-free EXP-0003 RRF sensitivity replay."""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
AI_APP = ROOT / "apps/ai"
sys.path.insert(0, str(AI_APP))

from app.evaluation.rrf_sensitivity_experiment import (  # noqa: E402
    run_rrf_sensitivity_experiment,
)

DEFAULT_SOURCE = ROOT / "docs/evaluation/runs/EXP-0003-post-reliability-corrected-engineering-baseline"
DEFAULT_OUTPUT = ROOT / "docs/evaluation/retrieval-experiments/RRF-EXP-0001-exp0003-engineering-sensitivity"


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", type=Path, default=DEFAULT_SOURCE)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument("--repository-commit", required=True)
    args = parser.parse_args()
    result = run_rrf_sensitivity_experiment(
        source_result_path=args.source / "result.json",
        source_observations_path=args.source / "application-observations.json",
        output_directory=args.output,
        repository_commit=args.repository_commit,
    )
    print(args.output / "report.html")
    print(result["conclusion"])


if __name__ == "__main__":
    main()
