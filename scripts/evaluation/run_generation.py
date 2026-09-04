#!/usr/bin/env python3
"""Run one immutable grounded-generation evaluation population."""

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
    load_generation_population,
    run_generation_evaluation,
)
from app.evaluation.generation_reporting import write_generation_evaluation_artifacts
from app.evaluation.openai_answer_evaluator import OpenAIAnswerEvaluator
from app.generation.factory import build_generator
from app.generation.openai_adapter import OpenAIGenerationProfile
from app.settings import Settings


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
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
        default=Path("docs/evaluation/runs/GEN-EXP-0001-grounded-generation-baseline"),
    )
    parser.add_argument(
        "--experiment-id",
        default="GEN-EXP-0001-grounded-generation-baseline",
    )
    parser.add_argument("--generation-max-attempts", type=int)
    parser.add_argument("--evaluator-model", default="gpt-5-mini")
    parser.add_argument("--evaluator-max-attempts", type=int, default=2)
    parser.add_argument("--evaluator-max-output-tokens", type=int, default=2048)
    parser.add_argument("--evaluator-input-token-ceiling", type=int)
    parser.add_argument("--evaluator-cost-ceiling-usd", type=float)
    parser.add_argument("--evaluator-input-cost-per-million", type=float, default=0.25)
    parser.add_argument(
        "--evaluator-cached-input-cost-per-million", type=float, default=0.025
    )
    parser.add_argument("--evaluator-output-cost-per-million", type=float, default=2.0)
    parser.add_argument(
        "--evaluator-pricing-snapshot",
        default="openai-gpt-5-mini-pricing-2026-08-19",
    )
    parser.add_argument("--repository-commit", required=True)
    parser.add_argument("--evaluation-harness-committed", action="store_true")
    return parser.parse_args()


def harness_digest(root: Path) -> str:
    paths = (
        root / "apps/ai/app/evaluation/generation_evaluation.py",
        root / "apps/ai/app/evaluation/generation_reporting.py",
        root / "apps/ai/app/evaluation/models.py",
        root / "apps/ai/app/evaluation/model_assisted.py",
        root / "apps/ai/app/evaluation/openai_answer_evaluator.py",
        root / "scripts/evaluation/run_generation.py",
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
    population_path = (root / args.population).resolve()
    output = (root / args.output).resolve()
    if output.exists() and any(output.iterdir()):
        raise SystemExit(f"refusing to overwrite existing run: {output}")
    population = load_generation_population(population_path)
    settings = Settings()
    if args.generation_max_attempts is not None:
        if args.generation_max_attempts < 1:
            raise SystemExit("generation attempts must be positive")
        settings = settings.model_copy(
            update={"generation_max_attempts": args.generation_max_attempts}
        )
    profile = OpenAIGenerationProfile(
        provider=settings.generation_provider,
        model=settings.generation_model,
        contract_version=settings.generation_contract_version,
        prompt_version=settings.generation_prompt_version,
        adapter_version=settings.generation_adapter_version,
        reasoning_effort=settings.generation_reasoning_effort,
        max_output_tokens=settings.generation_max_output_tokens,
        context_window_tokens=settings.generation_context_window_tokens,
    )
    if profile.fingerprint() != population.generation_fingerprint:
        raise SystemExit("generation fingerprint does not match population binding")
    api_key = settings.generation_openai_api_key.get_secret_value()
    if not api_key:
        raise SystemExit("generation/evaluator credential is not configured")

    evaluator = OpenAIAnswerEvaluator(
        api_key=api_key,
        model=args.evaluator_model,
        max_attempts=args.evaluator_max_attempts,
        max_output_tokens=args.evaluator_max_output_tokens,
        maximum_total_input_tokens=args.evaluator_input_token_ceiling,
        maximum_total_cost_usd=args.evaluator_cost_ceiling_usd,
        input_cost_per_million_tokens_usd=args.evaluator_input_cost_per_million,
        cached_input_cost_per_million_tokens_usd=(
            args.evaluator_cached_input_cost_per_million
        ),
        output_cost_per_million_tokens_usd=args.evaluator_output_cost_per_million,
        pricing_snapshot=args.evaluator_pricing_snapshot,
    )
    generation_lineage: dict[str, Any] = {
        **profile.fingerprint_input(),
        "fingerprint": profile.fingerprint(),
    }
    evaluator_lineage: dict[str, Any] = {
        **evaluator.identity,
        "metrics": [
            "ANSWER_PART_GROUNDEDNESS",
            "ANSWER_FACTUAL_PRECISION",
            "ANSWER_COMPLETENESS",
            "QUALIFIED_USEFULNESS",
            "INSUFFICIENCY_CORRECTNESS",
        ],
        "same_model_as_generator": args.evaluator_model == profile.model,
        "cost_basis": "estimated_from_provider_usage",
    }
    output.mkdir(parents=True, exist_ok=False)
    manifest = {
        "schema_version": "v1",
        "experiment_id": args.experiment_id,
        "repository_commit": args.repository_commit,
        "evaluation_harness_version": GENERATION_EVALUATION_HARNESS_VERSION,
        "evaluation_harness_digest": harness_digest(root),
        "evaluation_harness_uncommitted": not args.evaluation_harness_committed,
        "population_id": population.population_id,
        "population_digest": population.digest(),
        "generation_lineage": generation_lineage,
        "evaluator_lineage": evaluator_lineage,
        "trial_count": 1,
        "selective_rerun_permitted": False,
        "retrieval_executed": False,
        "sealed_held_out_accessed": False,
    }
    (output / "run-manifest.json").write_text(
        json.dumps(manifest, indent=2, sort_keys=True) + "\n"
    )
    result = await run_generation_evaluation(
        experiment_id=args.experiment_id,
        repository_commit=manifest["repository_commit"],
        population=population,
        generator=build_generator(settings),
        evaluator=evaluator,
        generation_lineage=generation_lineage,
        evaluator_lineage=evaluator_lineage,
        checkpoint_path=output / "checkpoint-observations.json",
    )
    config = {
        **manifest,
        "population_path": str(args.population),
        "case_count": len(population.cases),
        "outcome_distribution": {
            outcome: sum(
                case.expected_outcome.value == outcome for case in population.cases
            )
            for outcome in ("answered", "qualified", "insufficient_evidence")
        },
    }
    hashes = write_generation_evaluation_artifacts(
        output_dir=output,
        result=result,
        population=population,
        config=config,
    )
    with tempfile.TemporaryDirectory() as temporary:
        temporary_path = Path(temporary)
        for name in ("run-manifest.json", "checkpoint-observations.json"):
            (temporary_path / name).write_bytes((output / name).read_bytes())
        regenerated = write_generation_evaluation_artifacts(
            output_dir=temporary_path,
            result=result,
            population=population,
            config=config,
        )
    if hashes != regenerated:
        raise SystemExit("provider-free report regeneration was not deterministic")
    print(
        json.dumps(
            {
                "experiment_id": result.experiment_id,
                "population_digest": population.digest(),
                "aggregate": result.aggregate.model_dump(mode="json"),
                "artifact_hashes": hashes,
            },
            indent=2,
            sort_keys=True,
        )
    )


if __name__ == "__main__":
    asyncio.run(main())
