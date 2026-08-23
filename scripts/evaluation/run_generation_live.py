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

from app.evaluation.generation_evaluation import load_generation_population
from app.generation.openai_adapter import OpenAIGenerationProfile
from app.settings import Settings

EXPERIMENT_ID = re.compile(r"^GEN-SEC-LIVE-[A-Z0-9][A-Z0-9-]{7,80}$")
COMMIT = re.compile(r"^[0-9a-f]{40}$")
SHA256 = re.compile(r"^[0-9a-f]{64}$")
POLICY_PATH = Path("tests/evaluation/security/v1/live-generation-policy.json")
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

    @classmethod
    def load(cls, path: Path) -> LiveGenerationPolicy:
        value = json.loads(path.read_text())
        expected = {
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
        if set(value) != expected or value.get("schema_version") != "v1":
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


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--policy", type=Path, required=True)
    parser.add_argument("--repository-root", type=Path, required=True)
    parser.add_argument("--repository-commit", required=True)
    parser.add_argument("--experiment-id", required=True)
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
    expected_policy = (repository / POLICY_PATH).resolve()
    if policy_path.resolve() != expected_policy:
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
    if os.environ.get("RUN_LIVE_GENERATION_EVALUATION") != "1":
        raise SystemExit(
            "Set RUN_LIVE_GENERATION_EVALUATION=1 to permit paid live-provider calls."
        )
    repository = verify_repository_identity(
        repository_root=args.repository_root,
        repository_commit=args.repository_commit,
        policy_path=args.policy,
    )
    policy = LiveGenerationPolicy.load(repository / POLICY_PATH)
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
