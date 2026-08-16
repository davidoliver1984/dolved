"""Compile persisted Laravel benchmark observations into evaluation artefacts."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path

from app.evaluation.application_benchmark import compile_application_benchmark_run


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--observations", type=Path, required=True)
    parser.add_argument("--output-directory", type=Path, required=True)
    parser.add_argument("--historical-baseline", type=Path)
    parser.add_argument("--planner-expectations", type=Path)
    parser.add_argument(
        "--planner-prompt-version",
        default="adr-0022-v2",
        help="Immutable planner prompt lineage recorded by the provider run.",
    )
    args = parser.parse_args()
    provider = os.environ.get("RETRIEVAL_PLANNER_PROVIDER", "").strip()
    model = os.environ.get("RETRIEVAL_PLANNER_MODEL", "").strip()
    api_key = os.environ.get("RETRIEVAL_PLANNER_API_KEY", "").strip()
    if not provider or not model or not api_key:
        raise SystemExit(
            "Live RetrievalPlanner provider/model/credential configuration is required."
        )
    planner = {
        "provider": provider,
        "model": model,
        "contract_schema_version": "plan-response-v2",
        "prompt_version": args.planner_prompt_version,
        "adapter_version": "structured-chat-v3",
    }
    planner["fingerprint"] = hashlib.sha256(
        json.dumps(planner, sort_keys=True, separators=(",", ":")).encode()
    ).hexdigest()
    compile_application_benchmark_run(
        raw_path=args.observations,
        output_directory=args.output_directory,
        planner=planner,
        historical_baseline_path=args.historical_baseline,
        planner_expectations_path=args.planner_expectations,
    )


if __name__ == "__main__":
    main()
