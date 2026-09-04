import importlib.util
import json
import os
import shutil
import subprocess
import sys
from dataclasses import replace
from pathlib import Path
from types import ModuleType
from typing import Any

import pytest
from app.evaluation.generation_evaluation import build_generation_request
from app.generation.models import AnswerPart, GenerationOutcome, GenerationResult
from app.settings import Settings
from pydantic import SecretStr

SCRIPT_ROOT = Path(os.environ.get("SCRIPT_ROOT", "/workspace"))
SOURCE_ROOT = (
    SCRIPT_ROOT if SCRIPT_ROOT.is_dir() else Path(__file__).resolve().parents[3]
)
SCRIPT = SCRIPT_ROOT / "scripts/evaluation/run_generation_live.py"
POLICY = (
    Path("/evaluation/security/v1/live-generation-policy.json")
    if Path("/evaluation").exists()
    else SOURCE_ROOT / "tests/evaluation/security/v1/live-generation-policy.json"
)
R28_POLICY = (
    SOURCE_ROOT / "tests/evaluation/security/v1/r28-s02-live-generation-policy.json"
)
POPULATION = (
    Path("/generation-evaluation/populations/prompt-injection-v1.json")
    if Path("/generation-evaluation").exists()
    else SOURCE_ROOT / "docs/evaluation/generation/populations/prompt-injection-v1.json"
)


def load_script() -> ModuleType:
    spec = importlib.util.spec_from_file_location("run_generation_live", SCRIPT)
    assert spec is not None and spec.loader is not None
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def live_settings(**values: object) -> Settings:
    return Settings().model_copy(
        update={
            "generation_openai_api_key": SecretStr("test-only-credential"),
            "generation_adapter_version": "openai-responses-v1",
            **values,
        }
    )


def materialise_repository(
    tmp_path: Path, policy: Any, *, policy_source: Path = POLICY
) -> Path:
    repository = tmp_path / "repository"
    population = repository / policy.population_path
    population.parent.mkdir(parents=True)
    shutil.copyfile(POPULATION, population)
    policy_relative = (
        module_policy_path()
        if policy_source == POLICY
        else policy_source.relative_to(SOURCE_ROOT)
    )
    live_policy = repository / policy_relative
    live_policy.parent.mkdir(parents=True)
    shutil.copyfile(policy_source, live_policy)
    return repository


def module_policy_path() -> Path:
    return Path("tests/evaluation/security/v1/live-generation-policy.json")


def commit_repository(repository: Path) -> str:
    for arguments in (
        ("init", "--quiet"),
        ("config", "user.name", "Security Test"),
        ("config", "user.email", "security@example.test"),
        ("add", "."),
        ("commit", "--quiet", "-m", "fixture"),
    ):
        subprocess.run(["git", "-C", str(repository), *arguments], check=True)
    commit = subprocess.run(
        ["git", "-C", str(repository), "rev-parse", "HEAD"],
        check=True,
        capture_output=True,
        text=True,
    ).stdout.strip()
    subprocess.run(
        [
            "git",
            "-C",
            str(repository),
            "update-ref",
            "refs/remotes/origin/main",
            commit,
        ],
        check=True,
    )
    return commit


def test_r28_policy_binds_input_cost_and_single_attempt_ceilings(
    tmp_path: Path,
) -> None:
    module = load_script()
    policy = replace(module.LiveGenerationPolicy.load(R28_POLICY), output_root=tmp_path)
    repository = materialise_repository(tmp_path, policy, policy_source=R28_POLICY)

    population, output, attempts = module.validate(
        policy=policy,
        repository_root=repository,
        experiment_id="GEN-SEC-LIVE-R28-S02-0001",
        settings=live_settings(),
    )

    assert population.name == "prompt-injection-v1.json"
    assert output == tmp_path / "GEN-SEC-LIVE-R28-S02-0001"
    assert attempts == 6
    assert policy.maximum_total_input_tokens == 100_000
    assert policy.maximum_total_cost_usd == 7


def test_r28_policy_fails_closed_for_pricing_or_budget_drift(
    tmp_path: Path,
) -> None:
    module = load_script()
    policy = replace(module.LiveGenerationPolicy.load(R28_POLICY), output_root=tmp_path)
    repository = materialise_repository(tmp_path, policy, policy_source=R28_POLICY)
    arguments = {
        "policy": policy,
        "repository_root": repository,
        "experiment_id": "GEN-SEC-LIVE-R28-S02-0001",
    }

    with pytest.raises(ValueError, match="input"):
        module.validate(
            **{
                **arguments,
                "policy": replace(policy, maximum_total_input_tokens=1),
            },
            settings=live_settings(),
        )
    with pytest.raises(ValueError, match="pricing_snapshot"):
        module.validate(
            **arguments,
            settings=live_settings(generation_pricing_snapshot="drifted"),
        )


def test_live_policy_binds_population_profile_and_attempt_ceiling(
    tmp_path: Path,
) -> None:
    module = load_script()
    policy = replace(module.LiveGenerationPolicy.load(POLICY), output_root=tmp_path)
    repository = materialise_repository(tmp_path, policy)

    population, output, attempts = module.validate(
        policy=policy,
        repository_root=repository,
        experiment_id="GEN-SEC-LIVE-TEST-0001",
        settings=live_settings(),
    )

    assert population.name == "prompt-injection-v1.json"
    assert output == tmp_path / "GEN-SEC-LIVE-TEST-0001"
    assert attempts == 6


def test_live_policy_fails_closed_for_population_or_budget_drift(
    tmp_path: Path,
) -> None:
    module = load_script()
    policy = replace(module.LiveGenerationPolicy.load(POLICY), output_root=tmp_path)
    repository = materialise_repository(tmp_path, policy)
    arguments = {
        "policy": policy,
        "repository_root": repository,
        "experiment_id": "GEN-SEC-LIVE-TEST-0001",
    }

    with pytest.raises(ValueError, match="population digest"):
        module.validate(
            **{**arguments, "policy": replace(policy, population_digest="0" * 64)},
            settings=live_settings(),
        )
    with pytest.raises(ValueError, match="provider attempts"):
        module.validate(
            **{
                **arguments,
                "policy": replace(policy, maximum_generation_attempts_per_case=2),
            },
            settings=live_settings(),
        )
    with pytest.raises(ValueError, match="total output tokens"):
        module.validate(
            **{
                **arguments,
                "policy": replace(
                    policy,
                    maximum_generation_attempts_per_case=2,
                    maximum_total_provider_attempts=9,
                ),
            },
            settings=live_settings(),
        )
    with pytest.raises(ValueError, match="generation output tokens"):
        module.validate(
            **{
                **arguments,
                "policy": replace(
                    policy,
                    maximum_generation_output_tokens_per_case=4095,
                ),
            },
            settings=live_settings(),
        )
    (tmp_path / "GEN-SEC-LIVE-TEST-0001").mkdir()
    with pytest.raises(ValueError, match="cannot be rerun"):
        module.validate(**arguments, settings=live_settings())


def test_live_wrapper_requires_opt_in_and_honestly_skips_without_credentials(
    monkeypatch: pytest.MonkeyPatch,
    capsys: pytest.CaptureFixture[str],
    tmp_path: Path,
) -> None:
    module = load_script()
    policy = module.LiveGenerationPolicy.load(POLICY)
    repository = materialise_repository(tmp_path, policy)
    repository_commit = commit_repository(repository)
    monkeypatch.setattr(
        sys,
        "argv",
        [
            str(SCRIPT),
            "--policy",
            str(repository / module.POLICY_PATH),
            "--repository-root",
            str(repository),
            "--repository-commit",
            repository_commit,
            "--experiment-id",
            "GEN-SEC-LIVE-TEST-0001",
        ],
    )
    monkeypatch.delenv("RUN_LIVE_GENERATION_EVALUATION", raising=False)
    monkeypatch.setattr(module, "Settings", lambda: live_settings())
    with pytest.raises(SystemExit, match="permit paid live-provider calls"):
        module.main()

    monkeypatch.setenv("RUN_LIVE_GENERATION_EVALUATION", "1")
    settings_without_credentials = Settings().model_copy(
        update={
            "generation_openai_api_key": SecretStr(""),
            "generation_adapter_version": "openai-responses-v1",
        }
    )
    monkeypatch.setattr(
        module,
        "Settings",
        lambda: settings_without_credentials,
    )
    module.main()
    output = json.loads(capsys.readouterr().out)
    assert output == {
        "provider_calls": 0,
        "reason": "generation/evaluator credential is not configured",
        "status": "SKIP",
    }


def test_repository_identity_requires_matching_clean_head(tmp_path: Path) -> None:
    module = load_script()
    policy = module.LiveGenerationPolicy.load(POLICY)
    repository = materialise_repository(tmp_path, policy)
    commit = commit_repository(repository)
    policy_path = repository / module.POLICY_PATH

    assert (
        module.verify_repository_identity(
            repository_root=repository,
            repository_commit=commit,
            policy_path=policy_path,
        )
        == repository.resolve()
    )
    with pytest.raises(ValueError, match="HEAD does not match"):
        module.verify_repository_identity(
            repository_root=repository,
            repository_commit="0" * 40,
            policy_path=policy_path,
        )


@pytest.mark.parametrize("staged", [False, True], ids=["unstaged", "staged"])
def test_repository_identity_rejects_tracked_changes(
    tmp_path: Path, staged: bool
) -> None:
    module = load_script()
    policy = module.LiveGenerationPolicy.load(POLICY)
    repository = materialise_repository(tmp_path, policy)
    commit = commit_repository(repository)
    policy_path = repository / module.POLICY_PATH
    policy_path.write_text(policy_path.read_text() + "\n")
    if staged:
        subprocess.run(
            ["git", "-C", str(repository), "add", str(policy_path)], check=True
        )

    with pytest.raises(ValueError, match="tracked repository changes"):
        module.verify_repository_identity(
            repository_root=repository,
            repository_commit=commit,
            policy_path=policy_path,
        )


def test_invalid_repository_fails_before_credentials_or_provider_subprocess(
    monkeypatch: pytest.MonkeyPatch, tmp_path: Path
) -> None:
    module = load_script()
    policy = module.LiveGenerationPolicy.load(POLICY)
    repository = materialise_repository(tmp_path, policy)
    monkeypatch.setenv("RUN_LIVE_GENERATION_EVALUATION", "1")
    monkeypatch.setattr(
        sys,
        "argv",
        [
            str(SCRIPT),
            "--policy",
            str(repository / module.POLICY_PATH),
            "--repository-root",
            str(repository),
            "--repository-commit",
            "a" * 40,
            "--experiment-id",
            "GEN-SEC-LIVE-TEST-0001",
        ],
    )
    monkeypatch.setattr(
        module,
        "Settings",
        lambda: pytest.fail("credentials were read before Git validation"),
    )

    with pytest.raises(ValueError, match="Git identity"):
        module.main()


def test_prompt_injection_population_is_bounded_and_preserves_authoritative_fields() -> (
    None
):
    module = load_script()
    policy = module.LiveGenerationPolicy.load(POLICY)
    population = module.load_generation_population(POPULATION)

    assert len(population.cases) == 3
    assert population.digest() == policy.population_digest
    surfaces = {surface for case in population.cases for surface in case.surfaces}
    assert {
        "system-disclosure",
        "cross-tenant-exfiltration",
        "control-field-mutation",
    }.issubset(surfaces)
    assert all(case.prohibited_output_fragments for case in population.cases)
    requests = [build_generation_request(population, case) for case in population.cases]
    assert len({request.workspace_id for request in requests}) == 1
    assert all(
        [evidence.evidence_id for evidence in request.evidence] == ["ev-01"]
        for request in requests
    )
    assert all(
        [side.value for side in request.constraints.required_sides] == ["primary"]
        for request in requests
    )
    hostile_result = GenerationResult(
        outcome=GenerationOutcome.ANSWERED,
        answer_parts=(AnswerPart(text="Unsupported", evidence_ids=("ev-99",)),),
    )
    with pytest.raises(ValueError, match="outside the request"):
        hostile_result.validate_against(requests[-1])
    with pytest.raises(ValueError):
        GenerationResult.model_validate({"outcome": "system_override"})
