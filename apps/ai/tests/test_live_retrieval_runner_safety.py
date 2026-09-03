from __future__ import annotations

import importlib.util
import os
from pathlib import Path

import pytest

SCRIPT_ROOT = Path(os.environ.get("SCRIPT_ROOT", "/workspace"))
if not SCRIPT_ROOT.is_dir():
    SCRIPT_ROOT = Path(__file__).resolve().parents[3]
SCRIPT = SCRIPT_ROOT / "scripts/evaluation/run.py"
SPEC = importlib.util.spec_from_file_location("evaluation_run", SCRIPT)
assert SPEC and SPEC.loader
RUNNER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(RUNNER)


class Completed:
    def __init__(self, stdout: str) -> None:
        self.stdout = stdout


def test_live_identity_accepts_matching_clean_exact_commit(
    monkeypatch, tmp_path
) -> None:
    commit = "a" * 40
    outputs = iter((Completed(commit + "\n"), Completed("")))
    monkeypatch.setattr(RUNNER.subprocess, "run", lambda *args, **kwargs: next(outputs))

    RUNNER.verify_live_repository_identity(tmp_path, commit)


@pytest.mark.parametrize("commit", ["a" * 39, "a" * 40 + "-dirty", "HEAD"])
def test_live_identity_rejects_non_exact_commit(commit, tmp_path) -> None:
    with pytest.raises(ValueError, match="exact 40-character"):
        RUNNER.verify_live_repository_identity(tmp_path, commit)


def test_live_identity_rejects_dirty_tracked_worktree(monkeypatch, tmp_path) -> None:
    commit = "b" * 40
    outputs = iter((Completed(commit + "\n"), Completed(" M tracked.py\n")))
    monkeypatch.setattr(RUNNER.subprocess, "run", lambda *args, **kwargs: next(outputs))

    with pytest.raises(ValueError, match="clean tracked worktree"):
        RUNNER.verify_live_repository_identity(tmp_path, commit)


def test_live_identity_rejects_exact_commit_different_from_head(
    monkeypatch, tmp_path
) -> None:
    supplied = "c" * 40
    outputs = iter((Completed("d" * 40 + "\n"), Completed("")))
    monkeypatch.setattr(RUNNER.subprocess, "run", lambda *args, **kwargs: next(outputs))

    with pytest.raises(ValueError, match="does not match repository HEAD"):
        RUNNER.verify_live_repository_identity(tmp_path, supplied)
