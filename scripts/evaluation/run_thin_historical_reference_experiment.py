"""Run PLN-EXP-0004 against the engineering split only."""

from __future__ import annotations

import argparse
import json
import os
from pathlib import Path

from app.evaluation.thin_historical_reference_classifier import (
    StructuredHistoricalReferenceClassifier,
)
from app.evaluation.thin_historical_reference_experiment import (
    build_historical_reference_projection,
    run_historical_reference_experiment,
)
from pydantic import SecretStr


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--corpus", type=Path, required=True)
    parser.add_argument("--split", type=Path, required=True)
    parser.add_argument("--output-directory", type=Path, required=True)
    parser.add_argument("--pln-exp0001-result", type=Path, required=True)
    parser.add_argument("--pln-exp0002-result", type=Path, required=True)
    parser.add_argument("--pln-exp0003-result", type=Path, required=True)
    parser.add_argument("--repository-commit", required=True)
    args = parser.parse_args()
    provider = os.environ.get("RETRIEVAL_PLANNER_PROVIDER", "openai").strip()
    model = os.environ.get("RETRIEVAL_PLANNER_MODEL", "gpt-5-mini").strip()
    if provider != "openai" or model != "gpt-5-mini":
        raise SystemExit("PLN-EXP-0004 is bound to OpenAI gpt-5-mini")
    projection = build_historical_reference_projection(
        json.loads(args.corpus.read_text()), json.loads(args.split.read_text())
    )
    run_historical_reference_experiment(
        classifier=StructuredHistoricalReferenceClassifier(
            api_url=os.environ.get(
                "RETRIEVAL_PLANNER_API_URL",
                "https://api.openai.com/v1/chat/completions",
            ),
            api_key=SecretStr(os.environ.get("RETRIEVAL_PLANNER_API_KEY", "")),
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
        output_price_per_million=2.0,
        prior_results={
            "PLN-EXP-0001": json.loads(args.pln_exp0001_result.read_text()),
            "PLN-EXP-0002": json.loads(args.pln_exp0002_result.read_text()),
            "PLN-EXP-0003": json.loads(args.pln_exp0003_result.read_text()),
        },
        contract_schema=json.loads(
            Path(
                "/contracts/evaluation/thin-intent-historical-reference/v1/classification.schema.json"
            ).read_text()
        ),
    )


if __name__ == "__main__":
    main()
