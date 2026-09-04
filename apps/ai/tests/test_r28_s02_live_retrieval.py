import importlib.util
import json
import os
import shutil
import subprocess
import sys
from pathlib import Path
from types import ModuleType
from typing import Any

import pytest
from app.settings import Settings

SCRIPT_ROOT = Path(os.environ.get("SCRIPT_ROOT", "/workspace"))
SOURCE_ROOT = (
    SCRIPT_ROOT if SCRIPT_ROOT.is_dir() else Path(__file__).resolve().parents[3]
)
SCRIPT = SOURCE_ROOT / "scripts/evaluation/run_r28_s02_retrieval_live.py"
POLICY = SOURCE_ROOT / "tests/evaluation/policies/v1/r28-s02-live-retrieval-policy.json"


def load_script() -> ModuleType:
    spec = importlib.util.spec_from_file_location("run_r28_s02_retrieval_live", SCRIPT)
    assert spec is not None and spec.loader is not None
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def materialise_repository(tmp_path: Path, policy: Any) -> Path:
    repository = tmp_path / "repository"
    for key in ("corpus_path", "quality_policy_path"):
        relative = Path(policy[key])
        target = repository / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(SOURCE_ROOT / relative, target)
    policy_target = (
        repository / "tests/evaluation/policies/v1/r28-s02-live-retrieval-policy.json"
    )
    policy_target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(POLICY, policy_target)
    return repository


def commit_repository(repository: Path) -> str:
    for arguments in (
        ("init", "--quiet"),
        ("config", "user.name", "R28 Test"),
        ("config", "user.email", "r28@example.test"),
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


def bounded_settings() -> Settings:
    return Settings().model_copy(
        update={"embedding_max_attempts": 1, "reranker_max_attempts": 1}
    )


def test_r28_retrieval_policy_binds_population_lineage_and_ceilings(
    tmp_path: Path,
) -> None:
    module = load_script()
    value = json.loads(POLICY.read_text())
    value["output_root"] = str(tmp_path / "output")
    policy = module.RetrievalPolicy(value)
    repository = materialise_repository(tmp_path, policy)

    result = module.validate(
        policy=policy,
        repository=repository,
        experiment_id="R28-S02-LIVE-RETRIEVAL-BASELINE-0001",
        settings=bounded_settings(),
    )

    assert result["case_count"] == 23
    assert result["variant_count"] == 25
    assert result["provider_input_token_upper_bound"] <= 500_000
    assert result["provider_cost_upper_bound_usd"] <= 8


def test_r28_retrieval_policy_fails_closed_for_identity_and_attempt_drift(
    tmp_path: Path,
) -> None:
    module = load_script()
    value = json.loads(POLICY.read_text())
    value["output_root"] = str(tmp_path / "output")
    policy = module.RetrievalPolicy(value)
    repository = materialise_repository(tmp_path, policy)
    arguments = {
        "policy": policy,
        "repository": repository,
        "experiment_id": "R28-S02-LIVE-RETRIEVAL-BASELINE-0001",
    }

    with pytest.raises(ValueError, match="corpus SHA-256"):
        drifted = module.RetrievalPolicy({**value, "corpus_sha256": "0" * 64})
        module.validate(**{**arguments, "policy": drifted}, settings=bounded_settings())
    with pytest.raises(ValueError, match="embedding attempts"):
        module.validate(
            **arguments,
            settings=bounded_settings().model_copy(
                update={"embedding_max_attempts": 2}
            ),
        )
    with pytest.raises(ValueError, match="reranker attempts"):
        module.validate(
            **arguments,
            settings=bounded_settings().model_copy(update={"reranker_max_attempts": 2}),
        )


def test_r28_retrieval_repository_requires_clean_origin_main(
    tmp_path: Path,
) -> None:
    module = load_script()
    policy = module.RetrievalPolicy.load(POLICY)
    repository = materialise_repository(tmp_path, policy)
    commit = commit_repository(repository)
    policy_path = repository / module.POLICY_PATH

    assert module.verify_repository(repository, commit, policy_path) == repository
    (repository / "drift.txt").write_text("drift\n")
    subprocess.run(["git", "-C", str(repository), "add", "drift.txt"], check=True)
    subprocess.run(
        ["git", "-C", str(repository), "commit", "--quiet", "-m", "drift"],
        check=True,
    )
    drifted_head = subprocess.run(
        ["git", "-C", str(repository), "rev-parse", "HEAD"],
        check=True,
        capture_output=True,
        text=True,
    ).stdout.strip()
    with pytest.raises(ValueError, match="origin/main"):
        module.verify_repository(repository, drifted_head, policy_path)
