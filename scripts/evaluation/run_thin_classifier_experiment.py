"""Run the isolated gpt-5-mini linguistic-intent engineering experiment."""

from __future__ import annotations

import argparse
import json
import os
from pathlib import Path

from app.evaluation.thin_classifier_experiment import (
    build_expectation_projection,
    run_experiment,
)
from app.evaluation.thin_intent_classifier import StructuredThinIntentClassifier
from pydantic import SecretStr


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--corpus", type=Path, required=True)
    parser.add_argument("--split", type=Path, required=True)
    parser.add_argument("--output-directory", type=Path, required=True)
    parser.add_argument("--exp0001-result", type=Path, required=True)
    parser.add_argument("--repository-commit", required=True)
    args = parser.parse_args()
    provider = os.environ.get("RETRIEVAL_PLANNER_PROVIDER", "openai").strip()
    model = os.environ.get("RETRIEVAL_PLANNER_MODEL", "gpt-5-mini").strip()
    api_url = os.environ.get(
        "RETRIEVAL_PLANNER_API_URL",
        "https://api.openai.com/v1/chat/completions",
    ).strip()
    api_key = SecretStr(os.environ.get("RETRIEVAL_PLANNER_API_KEY", ""))
    if provider != "openai" or model != "gpt-5-mini":
        raise SystemExit("PLN-EXP-0001 is bound to OpenAI gpt-5-mini")
    projection = build_expectation_projection(
        json.loads(args.corpus.read_text()), json.loads(args.split.read_text())
    )
    run_experiment(
        classifier=StructuredThinIntentClassifier(
            api_url=api_url,
            api_key=api_key,
            provider=provider,
            model=model,
            timeout_seconds=float(
                os.environ.get("RETRIEVAL_PLANNER_TIMEOUT_SECONDS", "60")
            ),
        ),
        projection=projection,
        output_directory=args.output_directory,
        repository_commit=args.repository_commit,
        input_price_per_million=0.25,
        cached_input_price_per_million=0.025,
        output_price_per_million=2.00,
        exp0001_result=json.loads(args.exp0001_result.read_text()),
    )


if __name__ == "__main__":
    main()
