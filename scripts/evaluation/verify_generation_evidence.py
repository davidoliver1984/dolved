#!/usr/bin/env python3
"""Verify immutable generation evidence without provider calls."""

from __future__ import annotations

import argparse
import json
from pathlib import Path

from app.evaluation.generation_verification import verify_generation_evidence


def parser() -> argparse.ArgumentParser:
    command = argparse.ArgumentParser()
    command.add_argument("--generation-root", type=Path, required=True)
    command.add_argument("--runs-root", type=Path, required=True)
    return command


def main() -> None:
    arguments = parser().parse_args()
    result = verify_generation_evidence(
        generation_root=arguments.generation_root,
        runs_root=arguments.runs_root,
    )
    print(
        json.dumps(
            {
                "status": "PASS",
                "provider_calls": 0,
                "run_count": result.run_count,
                "case_count": result.case_count,
                "artifact_count": result.artifact_count,
            },
            sort_keys=True,
        )
    )


if __name__ == "__main__":
    main()
