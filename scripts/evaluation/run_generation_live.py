#!/usr/bin/env python3
"""Bound and invoke one optional live prompt-injection evaluation."""

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from app.evaluation.generation_evaluation import (
    build_generation_request,
    load_generation_population,
)
from app.generation.openai_adapter import (
    OpenAIGenerationProfile,
    OpenAITokenMeter,
    render_openai_request,
)
from app.settings import Settings

EXPERIMENT_ID = re.compile(r"^GEN-SEC-LIVE-[A-Z0-9][A-Z0-9-]{7,80}$")
COMMIT = re.compile(r"^[0-9a-f]{40}$")
SHA256 = re.compile(r"^[0-9a-f]{64}$")
POLICY_PATH = Path("tests/evaluation/security/v1/live-generation-policy.json")
R28_POLICY_PATH = Path(
    "tests/evaluation/security/v1/r28-s02-live-generation-policy.json"
)
POPULATION_PATH = Path(
    "docs/evaluation/generation/populations/prompt-injection-v1.json"
)


@dataclass(frozen=True)
class LiveGenerationPolicy:
    policy_id: str
    population_path: Path
    population_id: str
    population_digest: str
    generation_fingerprint: str
    evaluator_model: str
    maximum_cases: int
    maximum_generation_attempts_per_case: int
    maximum_evaluator_attempts_per_case: int
    maximum_total_provider_attempts: int
    maximum_generation_output_tokens_per_case: int
    maximum_evaluator_output_tokens_per_case: int
    maximum_total_output_tokens: int
    maximum_wall_seconds: int
    output_root: Path
    maximum_total_input_tokens: int | None = None
    maximum_generation_cost_usd: float | None = None
    maximum_evaluator_cost_usd: float | None = None
    maximum_total_cost_usd: float | None = None
    input_cost_per_million_tokens_usd: float | None = None
    cached_input_cost_per_million_tokens_usd: float | None = None
    output_cost_per_million_tokens_usd: float | None = None
    pricing_snapshot: str | None = None

    @classmethod
    def load(cls, path: Path) -> LiveGenerationPolicy:
        value = json.loads(path.read_text())
        expected_v1 = {
            "schema_version",
            "policy_id",
            "population_path",
            "population_id",
            "population_digest",
            "generation_fingerprint",
            "evaluator_model",
            "maximum_cases",
            "maximum_generation_attempts_per_case",
            "maximum_evaluator_attempts_per_case",
            "maximum_total_provider_attempts",
            "maximum_generation_output_tokens_per_case",
            "maximum_evaluator_output_tokens_per_case",
            "maximum_total_output_tokens",
            "maximum_wall_seconds",
            "output_root",
        }
        expected_v2 = expected_v1 | {
            "maximum_total_input_tokens",
            "maximum_generation_cost_usd",
            "maximum_evaluator_cost_usd",
            "maximum_total_cost_usd",
            "input_cost_per_million_tokens_usd",
            "cached_input_cost_per_million_tokens_usd",
            "output_cost_per_million_tokens_usd",
            "pricing_snapshot",
        }
        schema_version = value.get("schema_version")
        if not (
            (schema_version == "v1" and set(value) == expected_v1)
            or (schema_version == "v2" and set(value) == expected_v2)
        ):
            raise ValueError("live generation policy shape is invalid")
        return cls(
            policy_id=_string(value, "policy_id"),
            population_path=Path(_string(value, "population_path")),
            population_id=_string(value, "population_id"),
            population_digest=_digest(value, "population_digest"),
            generation_fingerprint=_digest(value, "generation_fingerprint"),
            evaluator_model=_string(value, "evaluator_model"),
            maximum_cases=_positive_int(value, "maximum_cases"),
            maximum_generation_attempts_per_case=_positive_int(
                value, "maximum_generation_attempts_per_case"
            ),
            maximum_evaluator_attempts_per_case=_positive_int(
                value, "maximum_evaluator_attempts_per_case"
            ),
            maximum_total_provider_attempts=_positive_int(
                value, "maximum_total_provider_attempts"
            ),
            maximum_generation_output_tokens_per_case=_positive_int(
                value, "maximum_generation_output_tokens_per_case"
            ),
            maximum_evaluator_output_tokens_per_case=_positive_int(
                value, "maximum_evaluator_output_tokens_per_case"
            ),
            maximum_total_output_tokens=_positive_int(
                value, "maximum_total_output_tokens"
            ),
            maximum_wall_seconds=_positive_int(value, "maximum_wall_seconds"),
            output_root=Path(_string(value, "output_root")),
            maximum_total_input_tokens=(
                _positive_int(value, "maximum_total_input_tokens")
                if schema_version == "v2"
                else None
            ),
            maximum_generation_cost_usd=(
                _positive_number(value, "maximum_generation_cost_usd")
                if schema_version == "v2"
                else None
            ),
            maximum_evaluator_cost_usd=(
                _positive_number(value, "maximum_evaluator_cost_usd")
                if schema_version == "v2"
                else None
            ),
            maximum_total_cost_usd=(
                _positive_number(value, "maximum_total_cost_usd")
                if schema_version == "v2"
                else None
            ),
            input_cost_per_million_tokens_usd=(
                _positive_number(value, "input_cost_per_million_tokens_usd")
                if schema_version == "v2"
                else None
            ),
            cached_input_cost_per_million_tokens_usd=(
                _positive_number(value, "cached_input_cost_per_million_tokens_usd")
                if schema_version == "v2"
                else None
            ),
            output_cost_per_million_tokens_usd=(
                _positive_number(value, "output_cost_per_million_tokens_usd")
                if schema_version == "v2"
                else None
            ),
            pricing_snapshot=(
                _string(value, "pricing_snapshot") if schema_version == "v2" else None
            ),
        )


def _string(value: dict[str, Any], key: str) -> str:
    item = value.get(key)
    if not isinstance(item, str) or not item:
        raise ValueError(f"{key} must be a non-empty string")
    return item


def _digest(value: dict[str, Any], key: str) -> str:
    item = _string(value, key)
    if not SHA256.fullmatch(item):
        raise ValueError(f"{key} must be a SHA-256 digest")
    return item


def _positive_int(value: dict[str, Any], key: str) -> int:
    item = value.get(key)
    if not isinstance(item, int) or isinstance(item, bool) or item < 1:
        raise ValueError(f"{key} must be a positive integer")
    return item


def _positive_number(value: dict[str, Any], key: str) -> float:
    item = value.get(key)
    if not isinstance(item, (int, float)) or isinstance(item, bool) or item <= 0:
        raise ValueError(f"{key} must be a positive number")
    return float(item)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--policy", type=Path, required=True)
    parser.add_argument("--repository-root", type=Path, required=True)
    parser.add_argument("--repository-commit", required=True)
    parser.add_argument("--experiment-id", required=True)
    parser.add_argument("--preflight-only", action="store_true")
    return parser.parse_args()


def _git(repository_root: Path, *arguments: str) -> str:
    try:
        completed = subprocess.run(
            ["git", "-C", str(repository_root), *arguments],
            check=True,
            capture_output=True,
            text=True,
            timeout=10,
        )
    except (
        FileNotFoundError,
        subprocess.CalledProcessError,
        subprocess.TimeoutExpired,
    ) as exception:
        raise ValueError("repository Git identity could not be verified") from exception
    return completed.stdout.strip()


def verify_repository_identity(
    *, repository_root: Path, repository_commit: str, policy_path: Path
) -> Path:
    if not COMMIT.fullmatch(repository_commit):
        raise ValueError("repository commit must be one exact Git SHA")
    repository = repository_root.resolve()
    permitted_policies = {
        (repository / POLICY_PATH).resolve(),
        (repository / R28_POLICY_PATH).resolve(),
    }
    expected_policy = policy_path.resolve()
    if expected_policy not in permitted_policies:
        raise ValueError(
            "live policy must be read from the repository security boundary"
        )
    if not expected_policy.is_file():
        raise ValueError("repository live policy is unavailable")
    if _git(repository, "rev-parse", "--is-inside-work-tree") != "true":
        raise ValueError("repository root is not a Git worktree")
    if Path(_git(repository, "rev-parse", "--show-toplevel")).resolve() != repository:
        raise ValueError("repository root must be the exact Git worktree root")
    if _git(repository, "rev-parse", "HEAD") != repository_commit:
        raise ValueError("repository HEAD does not match the supplied commit")
    if _git(repository, "rev-parse", "origin/main") != repository_commit:
        raise ValueError("repository HEAD does not match origin/main")
    if _git(repository, "status", "--porcelain=v1", "--untracked-files=no"):
        raise ValueError("tracked repository changes are not permitted")
    return repository


def validate(
    *,
    policy: LiveGenerationPolicy,
    repository_root: Path,
    experiment_id: str,
    settings: Settings,
) -> tuple[Path, Path, int]:
    population_path, output, case_count = verify_bound_artifacts(
        policy=policy,
        repository_root=repository_root,
        experiment_id=experiment_id,
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
    if (
        profile.provider != "openai"
        or profile.fingerprint() != policy.generation_fingerprint
    ):
        raise ValueError("configured generation profile does not match live policy")
    if (
        settings.generation_max_output_tokens
        > policy.maximum_generation_output_tokens_per_case
    ):
        raise ValueError("generation output tokens exceed the live policy ceiling")
    maximum_attempts = case_count * (
        policy.maximum_generation_attempts_per_case
        + policy.maximum_evaluator_attempts_per_case
    )
    if maximum_attempts > policy.maximum_total_provider_attempts:
        raise ValueError("total provider attempts exceed the live policy ceiling")
    maximum_output_tokens = case_count * (
        policy.maximum_generation_attempts_per_case
        * settings.generation_max_output_tokens
        + policy.maximum_evaluator_attempts_per_case
        * policy.maximum_evaluator_output_tokens_per_case
    )
    if maximum_output_tokens > policy.maximum_total_output_tokens:
        raise ValueError("total output tokens exceed the live policy ceiling")
    if policy.maximum_total_input_tokens is not None:
        if policy.evaluator_model != profile.model:
            raise ValueError("evaluator model differs from the frozen live policy")
        expected_pricing = {
            "generation_input_cost_per_million_tokens_usd": (
                policy.input_cost_per_million_tokens_usd
            ),
            "generation_cached_input_cost_per_million_tokens_usd": (
                policy.cached_input_cost_per_million_tokens_usd
            ),
            "generation_output_cost_per_million_tokens_usd": (
                policy.output_cost_per_million_tokens_usd
            ),
            "generation_pricing_snapshot": policy.pricing_snapshot,
        }
        for name, expected in expected_pricing.items():
            if getattr(settings, name) != expected:
                raise ValueError(f"configured {name} differs from the live policy")
        population = load_generation_population(population_path)
        meter = OpenAITokenMeter(profile.model)
        measured_generation_input = sum(
            meter.measure(
                render_openai_request(
                    build_generation_request(population, case), profile
                )
            )
            for case in population.cases
        )
        if measured_generation_input >= policy.maximum_total_input_tokens:
            raise ValueError("generation input leaves no evaluator token budget")
        assert policy.input_cost_per_million_tokens_usd is not None
        assert policy.output_cost_per_million_tokens_usd is not None
        assert policy.maximum_generation_cost_usd is not None
        maximum_generation_cost = (
            measured_generation_input * policy.input_cost_per_million_tokens_usd
            + case_count
            * policy.maximum_generation_attempts_per_case
            * settings.generation_max_output_tokens
            * policy.output_cost_per_million_tokens_usd
        ) / 1_000_000
        if maximum_generation_cost > policy.maximum_generation_cost_usd:
            raise ValueError(
                "generation cost upper bound exceeds the live policy ceiling"
            )
        assert policy.maximum_evaluator_cost_usd is not None
        assert policy.maximum_total_cost_usd is not None
        evaluator_input_budget = (
            policy.maximum_total_input_tokens - measured_generation_input
        )
        maximum_evaluator_cost = (
            evaluator_input_budget * policy.input_cost_per_million_tokens_usd
            + case_count
            * policy.maximum_evaluator_attempts_per_case
            * policy.maximum_evaluator_output_tokens_per_case
            * policy.output_cost_per_million_tokens_usd
        ) / 1_000_000
        if maximum_evaluator_cost > policy.maximum_evaluator_cost_usd:
            raise ValueError(
                "evaluator cost upper bound exceeds the live policy ceiling"
            )
        if (
            policy.maximum_generation_cost_usd + policy.maximum_evaluator_cost_usd
            > policy.maximum_total_cost_usd
        ):
            raise ValueError("stage cost ceilings exceed total live policy ceiling")
    return population_path, output, maximum_attempts


def verify_bound_artifacts(
    *,
    policy: LiveGenerationPolicy,
    repository_root: Path,
    experiment_id: str,
) -> tuple[Path, Path, int]:
    if not EXPERIMENT_ID.fullmatch(experiment_id):
        raise ValueError("experiment identity is invalid")
    if policy.population_path != POPULATION_PATH:
        raise ValueError(
            "population must be read from the repository security boundary"
        )
    population_path = (repository_root / policy.population_path).resolve()
    if not population_path.is_relative_to(repository_root.resolve()):
        raise ValueError("population must remain inside the repository")
    population = load_generation_population(population_path)
    if population.population_id != policy.population_id:
        raise ValueError("population identity does not match live policy")
    if population.digest() != policy.population_digest:
        raise ValueError("population digest does not match live policy")
    if len(population.cases) > policy.maximum_cases:
        raise ValueError("population exceeds the live case ceiling")
    if population.generation_fingerprint != policy.generation_fingerprint:
        raise ValueError("population generation fingerprint does not match policy")
    if not policy.output_root.is_absolute():
        raise ValueError("live output root must be absolute")
    output = policy.output_root / experiment_id
    if output.exists():
        raise ValueError("live experiment identity already exists and cannot be rerun")
    return population_path, output, len(population.cases)


def main() -> None:
    args = parse_args()
    repository = verify_repository_identity(
        repository_root=args.repository_root,
        repository_commit=args.repository_commit,
        policy_path=args.policy,
    )
    policy = LiveGenerationPolicy.load(args.policy)
    verify_bound_artifacts(
        policy=policy,
        repository_root=repository,
        experiment_id=args.experiment_id,
    )
    settings = Settings()
    population, output, maximum_attempts = validate(
        policy=policy,
        repository_root=repository,
        experiment_id=args.experiment_id,
        settings=settings,
    )
    if args.preflight_only:
        print(
            json.dumps(
                {
                    "status": "READY",
                    "provider_calls": 0,
                    "policy_id": policy.policy_id,
                    "population_digest": policy.population_digest,
                    "case_count": len(load_generation_population(population).cases),
                    "maximum_provider_attempts": maximum_attempts,
                    "maximum_input_tokens": policy.maximum_total_input_tokens,
                    "maximum_output_tokens": policy.maximum_total_output_tokens,
                    "maximum_cost_usd": policy.maximum_total_cost_usd,
                    "maximum_wall_seconds": policy.maximum_wall_seconds,
                    "output": str(output),
                },
                sort_keys=True,
            )
        )
        return
    if os.environ.get("RUN_LIVE_GENERATION_EVALUATION") != "1":
        raise SystemExit(
            "Set RUN_LIVE_GENERATION_EVALUATION=1 to permit paid live-provider calls."
        )
    if not settings.generation_openai_api_key.get_secret_value().strip():
        print(
            json.dumps(
                {
                    "status": "SKIP",
                    "reason": "generation/evaluator credential is not configured",
                    "provider_calls": 0,
                },
                sort_keys=True,
            )
        )
        return
    command = [
        sys.executable,
        str(repository / "scripts/evaluation/run_generation.py"),
        "--population",
        str(population),
        "--output",
        str(output),
        "--experiment-id",
        args.experiment_id,
        "--generation-max-attempts",
        str(policy.maximum_generation_attempts_per_case),
        "--evaluator-model",
        policy.evaluator_model,
        "--evaluator-max-attempts",
        str(policy.maximum_evaluator_attempts_per_case),
        "--evaluator-max-output-tokens",
        str(policy.maximum_evaluator_output_tokens_per_case),
        "--repository-commit",
        args.repository_commit,
        "--evaluation-harness-committed",
    ]
    if policy.maximum_total_input_tokens is not None:
        population_value = load_generation_population(population)
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
        meter = OpenAITokenMeter(profile.model)
        generation_input = sum(
            meter.measure(
                render_openai_request(
                    build_generation_request(population_value, case), profile
                )
            )
            for case in population_value.cases
        )
        evaluator_input_ceiling = policy.maximum_total_input_tokens - generation_input
        assert policy.maximum_evaluator_cost_usd is not None
        assert policy.input_cost_per_million_tokens_usd is not None
        assert policy.cached_input_cost_per_million_tokens_usd is not None
        assert policy.output_cost_per_million_tokens_usd is not None
        assert policy.pricing_snapshot is not None
        command.extend(
            [
                "--evaluator-input-token-ceiling",
                str(evaluator_input_ceiling),
                "--evaluator-cost-ceiling-usd",
                str(policy.maximum_evaluator_cost_usd),
                "--evaluator-input-cost-per-million",
                str(policy.input_cost_per_million_tokens_usd),
                "--evaluator-cached-input-cost-per-million",
                str(policy.cached_input_cost_per_million_tokens_usd),
                "--evaluator-output-cost-per-million",
                str(policy.output_cost_per_million_tokens_usd),
                "--evaluator-pricing-snapshot",
                policy.pricing_snapshot,
            ]
        )
    try:
        subprocess.run(
            command,
            cwd=repository,
            check=True,
            timeout=policy.maximum_wall_seconds,
        )
    except subprocess.TimeoutExpired as exception:
        raise SystemExit(
            "live generation evaluation exceeded its wall-clock ceiling"
        ) from exception
    manifest = json.loads((output / "run-manifest.json").read_text())
    result = json.loads((output / "result.json").read_text())
    if (
        manifest.get("repository_commit") != args.repository_commit
        or manifest.get("population_digest") != policy.population_digest
        or manifest.get("evaluation_harness_uncommitted") is not False
        or result.get("population_digest") != policy.population_digest
    ):
        raise SystemExit("live generation artifacts do not match the approved identity")
    if policy.maximum_total_input_tokens is not None:
        observations = result.get("observations", [])
        generation_usages = [
            item.get("result", {}).get("usage") for item in observations
        ]
        if any(not isinstance(usage, dict) for usage in generation_usages):
            raise SystemExit("live generation usage lineage is incomplete")
        generation_input = sum(
            int(usage.get("input_tokens") or 0) for usage in generation_usages
        )
        generation_output = sum(
            int(usage.get("output_tokens") or 0) for usage in generation_usages
        )
        generation_attempts = sum(
            int(usage.get("request_count") or 0) for usage in generation_usages
        )
        generation_retries = sum(
            int(usage.get("retry_count") or 0) for usage in generation_usages
        )
        generation_costs = [usage.get("cost_usd") for usage in generation_usages]
        evaluator = result.get("aggregate", {}).get("evaluator_operations", {})
        evaluator_input = evaluator.get("input_tokens")
        evaluator_cost = evaluator.get("cost_usd")
        evaluator_output = evaluator.get("output_tokens")
        evaluator_attempts = evaluator.get("request_count")
        evaluator_retries = evaluator.get("retry_count")
        if (
            any(cost is None for cost in generation_costs)
            or evaluator_input is None
            or evaluator_output is None
            or evaluator_cost is None
            or evaluator_attempts is None
            or evaluator_retries is None
        ):
            raise SystemExit("live generation cost/token lineage is incomplete")
        generation_cost = sum(float(cost) for cost in generation_costs)
        total_input = generation_input + int(evaluator_input)
        total_output = generation_output + int(evaluator_output)
        total_attempts = generation_attempts + int(evaluator_attempts)
        total_cost = generation_cost + float(evaluator_cost)
        assert policy.maximum_generation_cost_usd is not None
        assert policy.maximum_evaluator_cost_usd is not None
        assert policy.maximum_total_cost_usd is not None
        if total_input > policy.maximum_total_input_tokens:
            raise SystemExit("live generation input-token ceiling was exceeded")
        if total_output > policy.maximum_total_output_tokens:
            raise SystemExit("live generation output-token ceiling was exceeded")
        if total_attempts > policy.maximum_total_provider_attempts:
            raise SystemExit("live generation provider-attempt ceiling was exceeded")
        if generation_retries or int(evaluator_retries):
            raise SystemExit("live generation single-attempt policy was violated")
        if generation_cost > policy.maximum_generation_cost_usd:
            raise SystemExit("live generation stage USD ceiling was exceeded")
        if float(evaluator_cost) > policy.maximum_evaluator_cost_usd:
            raise SystemExit("live evaluator stage USD ceiling was exceeded")
        if total_cost > policy.maximum_total_cost_usd:
            raise SystemExit("live generation total USD ceiling was exceeded")
    print(
        json.dumps(
            {
                "status": "PASS",
                "evidence_kind": "optional_live_provider_measurement",
                "experiment_id": args.experiment_id,
                "policy_id": policy.policy_id,
                "population_digest": policy.population_digest,
                "maximum_provider_attempts": maximum_attempts,
                "maximum_output_tokens": policy.maximum_total_output_tokens,
                "maximum_wall_seconds": policy.maximum_wall_seconds,
            },
            sort_keys=True,
        )
    )


if __name__ == "__main__":
    main()
