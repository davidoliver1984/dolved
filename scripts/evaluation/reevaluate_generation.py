#!/usr/bin/env python3
"""Re-evaluate immutable generation observations without generation calls."""

from __future__ import annotations

import argparse
import asyncio
import hashlib
import json
import tempfile
from pathlib import Path
from typing import Any

from app.evaluation.generation_evaluation import (
    GENERATION_EVALUATION_HARNESS_VERSION,
    RecordedGenerationObservation,
    load_generation_population,
    reevaluate_recorded_generation,
)
from app.evaluation.generation_reporting import write_generation_evaluation_artifacts
from app.evaluation.openai_answer_evaluator import OpenAIAnswerEvaluator
from app.settings import Settings

SOURCE_OBSERVATION_SHA256 = (
    "bee2cdf0ddfda896cf23f8c3a27ec7b250db9d202180ed52b4d3ab6cf0f6c38e"
)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--source-run",
        type=Path,
        default=Path("docs/evaluation/runs/GEN-EXP-0001-grounded-generation-baseline"),
    )
    parser.add_argument(
        "--population",
        type=Path,
        default=Path(
            "docs/evaluation/generation/populations/grounded-generation-v1.json"
        ),
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=Path(
            "docs/evaluation/runs/GEN-EXP-0002-corrected-grounded-generation-evaluator"
        ),
    )
    parser.add_argument(
        "--experiment-id",
        default="GEN-EXP-0002-corrected-grounded-generation-evaluator",
    )
    parser.add_argument("--evaluator-model", default="gpt-5-mini")
    parser.add_argument("--repository-commit", required=True)
    return parser.parse_args()


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def harness_digest(root: Path) -> str:
    paths = (
        root / "apps/ai/app/evaluation/generation_evaluation.py",
        root / "apps/ai/app/evaluation/generation_reporting.py",
        root / "apps/ai/app/evaluation/models.py",
        root / "apps/ai/app/evaluation/model_assisted.py",
        root / "apps/ai/app/evaluation/openai_answer_evaluator.py",
        root / "scripts/evaluation/reevaluate_generation.py",
    )
    digest = hashlib.sha256()
    for path in paths:
        digest.update(str(path.relative_to(root)).encode())
        digest.update(b"\0")
        digest.update(path.read_bytes())
        digest.update(b"\0")
    return digest.hexdigest()


async def main() -> None:
    args = parse_args()
    root = Path(__file__).resolve().parents[2]
    source_run = (root / args.source_run).resolve()
    population_path = (root / args.population).resolve()
    output = (root / args.output).resolve()
    if output.exists() and any(output.iterdir()):
        raise SystemExit(f"refusing to overwrite existing evaluation pass: {output}")

    source_path = source_run / "application-observations.json"
    source_digest = sha256(source_path)
    if source_digest != SOURCE_OBSERVATION_SHA256:
        raise SystemExit("source generation observation digest does not match binding")
    source_manifest = json.loads((source_run / "run-manifest.json").read_text())
    population = load_generation_population(population_path)
    if source_manifest["population_digest"] != population.digest():
        raise SystemExit("source generation population digest does not match")
    recorded = tuple(
        RecordedGenerationObservation.model_validate(value)
        for value in json.loads(source_path.read_text())
    )

    settings = Settings()
    api_key = settings.generation_openai_api_key.get_secret_value()
    if not api_key:
        raise SystemExit("evaluator credential is not configured")
    evaluator = OpenAIAnswerEvaluator(api_key=api_key, model=args.evaluator_model)
    evaluator_lineage: dict[str, Any] = {
        **evaluator.identity,
        "metrics": [
            "ANSWER_PART_GROUNDEDNESS",
            "ANSWER_FACTUAL_PRECISION",
            "ANSWER_COMPLETENESS",
            "QUALIFIED_USEFULNESS",
            "INSUFFICIENCY_CORRECTNESS",
        ],
        "same_model_as_generator": (
            args.evaluator_model == source_manifest["generation_lineage"]["model"]
        ),
        "cost_basis": "unavailable",
    }
    output.mkdir(parents=True, exist_ok=False)
    result = await reevaluate_recorded_generation(
        experiment_id=args.experiment_id,
        repository_commit=args.repository_commit,
        population=population,
        recorded=recorded,
        evaluator=evaluator,
        generation_lineage=source_manifest["generation_lineage"],
        evaluator_lineage=evaluator_lineage,
        checkpoint_path=output / "checkpoint-evaluation-observations.json",
    )
    manifest = {
        "schema_version": "v2",
        "experiment_id": args.experiment_id,
        "repository_commit": args.repository_commit,
        "evaluation_harness_version": GENERATION_EVALUATION_HARNESS_VERSION,
        "evaluation_harness_digest": harness_digest(root),
        "evaluation_harness_uncommitted": True,
        "source_generation_run_id": source_manifest["experiment_id"],
        "source_generation_observations_sha256": source_digest,
        "source_generation_observations_bytes": source_path.stat().st_size,
        "generation_provider_calls": 0,
        "population_id": population.population_id,
        "population_digest": population.digest(),
        "generation_lineage": source_manifest["generation_lineage"],
        "evaluator_lineage": evaluator_lineage,
        "evaluator_request_count": result.aggregate.evaluator_operations.request_count,
        "trial_count": 1,
        "selective_rerun_permitted": False,
        "retrieval_executed": False,
        "sealed_held_out_accessed": False,
    }
    (output / "run-manifest.json").write_text(
        json.dumps(manifest, indent=2, sort_keys=True) + "\n"
    )
    config = {
        **manifest,
        "source_run_path": str(args.source_run),
        "population_path": str(args.population),
        "case_count": len(population.cases),
    }
    hashes = write_generation_evaluation_artifacts(
        output_dir=output,
        result=result,
        population=population,
        config=config,
    )
    if sha256(output / "application-observations.json") != source_digest:
        raise SystemExit("re-evaluation altered immutable generation observations")
    with tempfile.TemporaryDirectory() as temporary:
        regenerated = write_generation_evaluation_artifacts(
            output_dir=Path(temporary),
            result=result,
            population=population,
            config=config,
        )
    if {
        key: value
        for key, value in hashes.items()
        if key not in {"run-manifest.json", "checkpoint-evaluation-observations.json"}
    } != regenerated:
        raise SystemExit("provider-free report regeneration was not deterministic")
    print(
        json.dumps(
            {
                "experiment_id": result.experiment_id,
                "source_generation_observations_sha256": source_digest,
                "generation_provider_calls": 0,
                "aggregate": result.aggregate.model_dump(mode="json"),
                "artifact_hashes": hashes,
            },
            indent=2,
            sort_keys=True,
        )
    )


if __name__ == "__main__":
    asyncio.run(main())
