#!/usr/bin/env python3
"""Provider-free static preflight for the immutable R28-S03 boundary."""

from __future__ import annotations

import hashlib
import json
import os
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DEFINITION = ROOT / "docs/evaluation/r28-s03/run-definition.json"


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def git(*args: str) -> str:
    return subprocess.check_output(["git", *args], cwd=ROOT, text=True).strip()


def fail(message: str) -> None:
    raise SystemExit(f"R28-S03 preflight failed: {message}")


def main() -> int:
    definition = json.loads(DEFINITION.read_text())
    if git("branch", "--show-current") != "main":
        fail("branch is not main")
    if git("rev-parse", "HEAD") != git("rev-parse", "origin/main"):
        fail("HEAD does not equal origin/main")
    if git("status", "--porcelain", "--untracked-files=no"):
        fail("tracked worktree is not clean")
    source = definition["source"]
    for key in ("archive", "freeze_manifest"):
        path = ROOT / source[f"{key}_path"]
        if not path.is_file() or sha256(path) != source[f"{key}_sha256"]:
            fail(f"{key} identity mismatch")
    profile = definition["retrieval_materialisation_profile"]
    profile_path = ROOT / profile["profile_path"]
    if sha256(profile_path) != profile["profile_sha256"]:
        fail("deterministic retrieval profile identity mismatch")
    env = (ROOT / ".env.r28-s03").read_text()
    required = {
        "COMPOSE_PROJECT_NAME=dolved-r28-s03",
        "POSTGRES_DB=dolved_e2e_r28_s03",
        "E2E_DATABASE_MARKER=dolved_e2e_r28_s03",
    }
    if not required.issubset(set(env.splitlines())):
        fail("isolated runtime identity is incomplete")
    compose = (ROOT / "compose.r28-s03.yaml").read_text()
    for forbidden in ("/evaluation", "/evaluation-runs", "held-out", "calibration"):
        if forbidden in compose:
            fail(f"protected mount/reference present: {forbidden}")
    if 'OPENAI_API_KEY: ""' not in compose or 'VOYAGE_API_KEY: ""' not in compose:
        fail("provider credentials are not explicitly absent")
    corpus_root = os.environ.get("R28_CORPUS_ROOT")
    if not corpus_root or not Path(corpus_root).is_dir():
        fail("the safely extracted frozen corpus root is unavailable")
    effective = json.loads(
        subprocess.check_output(
            [
                "docker",
                "compose",
                "--env-file",
                ".env.r28-s03",
                "-p",
                "dolved-r28-s03",
                "-f",
                "compose.yaml",
                "-f",
                "compose.e2e.yaml",
                "-f",
                "compose.r28-s03.yaml",
                "config",
                "--format",
                "json",
            ],
            cwd=ROOT,
            text=True,
            env={**os.environ, "R28_CORPUS_ROOT": corpus_root},
        )
    )
    for service_name in ("api", "ai", "worker", "publisher"):
        service = effective["services"][service_name]
        destinations = {volume["target"] for volume in service.get("volumes", [])}
        if destinations.intersection({"/evaluation", "/evaluation-runs", "/workspace"}):
            fail(f"{service_name} exposes a broad evaluation/repository mount")
    for service_name in ("ai", "worker"):
        environment = effective["services"][service_name]["environment"]
        for key in (
            "OPENAI_API_KEY",
            "VOYAGE_API_KEY",
            "RETRIEVAL_PLANNER_API_KEY",
            "CONTEXTUALISER_API_KEY",
            "GENERATION_OPENAI_API_KEY",
        ):
            if environment.get(key) not in {"", None}:
                fail(f"{service_name} exposes provider credential {key}")
    print(
        json.dumps(
            {
                "status": "ready",
                "head": git("rev-parse", "HEAD"),
                "run_id": definition["run_id"],
                "provider_calls_permitted": False,
                "aws_access_permitted": False,
                "effective_runtime_isolation": "verified",
            },
            sort_keys=True,
        )
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
