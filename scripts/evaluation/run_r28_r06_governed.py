"""One-use, identity-bound R06 dispatch through the production conversation action.

No provider request occurs during --preflight. Live execution is permitted only
with an exact, separate authorization file and an unused authorization ID.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import subprocess
from datetime import UTC, datetime
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
SELECTION = ROOT / "docs/evaluation/r28-s04/r06-selection-20261108.json"
PREPARED = ROOT / "docs/evaluation/r28-s04/r06-prepared-20261108"
POPULATION = (
    ROOT / "tests/evaluation/engineering-populations/dolved-care-v4/v2/population.json"
)
FREEZE = POPULATION.with_name("freeze-manifest.json")
DISPATCHER = Path(__file__).with_name("r28_r06_production_case.php")
RUNNER = Path(__file__).resolve()
OUTPUT_ROOT = ROOT / "docs/evaluation/r28-s04/r06-governed-evidence"
PRIMARY_WORKSPACE = "5323cd6b-8b7a-40f9-98b0-9413c75f643e"
PRIMARY_USER = "ae454ce6-4d91-4c7e-9a40-b4750b696a6b"
INJECTION_WORKSPACE = "4db6ffe2-6974-415d-8fd3-002b8fb05d05"
INJECTION_USER = "6972994f-c594-42ef-a747-39d01ae24fc3"
SCHEMA = "r28-r06-production-authorization-v1"
STAGES = {"regression": 8, "confirmation": 16, "continuation": 2,
          "confirmation_retry": 1, "confirmation_remaining": 12,
          "case6_retry": 1, "case5_retry": 1, "generic_controls": 3}
COMPOSE = [
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
]
RUNTIME_PATHS = (
    "apps/api/app",
    "apps/api/config",
    "apps/api/routes",
    "apps/ai/app",
    "contracts",
)


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def json_file(path: Path) -> dict[str, Any]:
    value = json.loads(path.read_text())
    if not isinstance(value, dict):
        raise TypeError(f"JSON object required: {path}")
    return value


def git(*args: str, binary: bool = False) -> bytes | str:
    result = subprocess.run(
        ["git", *args], cwd=ROOT, check=True, capture_output=True, timeout=15
    ).stdout
    return result if binary else result.decode().strip()


def runtime_aggregate() -> str:
    paths = git("ls-files", "-z", "--", *RUNTIME_PATHS, binary=True).split(b"\0")
    lines = bytearray()
    for name in sorted(p for p in paths if p):
        digest = hashlib.sha256((ROOT / os.fsdecode(name)).read_bytes()).hexdigest()
        lines.extend(digest.encode() + b"  " + name + b"\n")
    return hashlib.sha256(lines).hexdigest()


def verify_inputs(stage: str) -> tuple[dict[str, Any], dict[str, Any], dict[str, str]]:
    selection = json_file(SELECTION)
    freeze = json_file(FREEZE)
    if freeze["provider_execution_authorised"] is not False:
        raise ValueError("frozen population policy changed")
    if sha256(POPULATION) != freeze["candidate_files"]["population.json"]:
        raise ValueError("frozen population checksum mismatch")
    if selection["population_digest"] != freeze["frozen_population"]["digest"]:
        raise ValueError("population identity mismatch")

    directory = PREPARED / stage
    input_path = directory / "execution-input.json"
    scopes_path = directory / "execution-scopes.json"
    build_path = directory / "build-manifest.json"
    inventory = (directory / "checksums.sha256").read_text().splitlines()
    expected_inventory = [
        f"{sha256(path)}  {path.name}"
        for path in sorted((input_path, scopes_path, build_path))
    ]
    if inventory != expected_inventory:
        raise ValueError("prepared input checksum inventory mismatch")
    execution = json_file(input_path)
    scopes = json_file(scopes_path)
    build = json_file(build_path)
    if stage == "continuation":
        manifest = {
            "manifest_id": "R28-R06-CONTINUATION-2-20261108-V1",
            "items": selection["regression"]["items"][-2:],
        }
    elif stage == "confirmation_retry":
        manifest = {
            "manifest_id": "R28-R06-CONFIRMATION-RETRY-1-20261108-V1",
            "items": selection["confirmation"]["items"][3:4],
        }
    elif stage == "confirmation_remaining":
        manifest = {
            "manifest_id": "R28-R06-CONFIRMATION-REMAINING-12-20261108-V1",
            "items": selection["confirmation"]["items"][4:],
        }
    elif stage in ("case6_retry", "case5_retry", "generic_controls"):
        start, stop, manifest_id = {
            "case6_retry": (5, 6, "R28-R06-CASE6-RETRY-1-20261108-V1"),
            "case5_retry": (4, 5, "R28-R06-CASE5-RETRY-1-20261108-V1"),
            "generic_controls": (0, 3, "R28-R06-GENERIC-CONTROLS-3-20261108-V1"),
        }[stage]
        manifest = {"manifest_id": manifest_id,
                    "items": selection["confirmation"]["items"][start:stop]}
    else:
        manifest = selection[stage]
    if (
        execution["subset_id"] != manifest["manifest_id"]
        or scopes["manifest_id"] != manifest["manifest_id"]
        or build["manifest_id"] != manifest["manifest_id"]
        or build["selection_file_sha256"] != sha256(SELECTION)
        or build["population_file_sha256"] != sha256(POPULATION)
        or build["execution_authorised"] is not False
        or len(execution["items"]) != STAGES[stage]
        or len(scopes["items"]) != STAGES[stage]
        or scopes["items"] != manifest["items"]
    ):
        raise ValueError("prepared input differs from frozen selection")
    population = json_file(POPULATION)
    cases = {case["case_id"]: case for case in population["cases"]}
    for item, scope in zip(execution["items"], scopes["items"], strict=True):
        if set(item) != {"case_id", "variant_id", "utterance"}:
            raise ValueError("execution input contains non-question material")
        if (item["case_id"], item["variant_id"]) != (
            scope["case_id"],
            scope["variant_id"],
        ):
            raise ValueError("execution/scope ordering mismatch")
        case = cases[item["case_id"]]
        matching = [
            variant
            for variant in case["variants"]
            if variant["variant_id"] == item["variant_id"]
        ]
        if len(matching) != 1 or matching[0]["utterance"] != item["utterance"]:
            raise ValueError("question differs from frozen population")
        if case["scope"] != scope["scope"]:
            raise ValueError("scope differs from frozen population")
    return (
        execution,
        scopes,
        {
            "selection_sha256": sha256(SELECTION),
            "input_sha256": sha256(input_path),
            "scopes_sha256": sha256(scopes_path),
            "population_sha256": sha256(POPULATION),
            "dispatcher_sha256": sha256(DISPATCHER),
            "runner_sha256": sha256(RUNNER),
            "runtime_source_aggregate": runtime_aggregate(),
        },
    )


def verify_authorization(
    path: Path, stage: str, manifest_id: str, identities: dict[str, str]
) -> dict[str, Any]:
    authority = json_file(path)
    required = {
        "schema_version",
        "authorization_id",
        "run_id",
        "stage",
        "manifest_id",
        "authorised",
        "approved_by",
        "approved_on",
        "approval_basis",
        "execution_commit",
        "selection_sha256",
        "input_sha256",
        "scopes_sha256",
        "population_sha256",
        "dispatcher_sha256",
        "runner_sha256",
        "runtime_source_aggregate",
        "runtime_snapshot_sha256",
        "selective_rerun_authorised",
    }
    if set(authority) != required:
        raise ValueError("authorization fields differ from the governed schema")
    if (
        authority["schema_version"] != SCHEMA
        or authority["authorised"] is not True
        or authority["selective_rerun_authorised"] is not (stage in {"continuation", "confirmation_retry", "case6_retry", "case5_retry", "generic_controls"})
        or authority["approved_by"] != "David"
        or authority["stage"] != stage
        or authority["manifest_id"] != manifest_id
        or not re.fullmatch(
            r"R28-R06-AUTH-[A-Z0-9-]{8,80}", authority["authorization_id"]
        )
        or not re.fullmatch(r"R28-R06-[A-Z0-9-]{8,80}", authority["run_id"])
        or not re.fullmatch(r"\d{4}-\d{2}-\d{2}", authority["approved_on"])
        or not isinstance(authority["approval_basis"], str)
        or not authority["approval_basis"].strip()
    ):
        raise ValueError("run authorization is false or malformed")
    if authority["execution_commit"] != git("rev-parse", "HEAD"):
        raise ValueError("authorization commit mismatch")
    if authority["execution_commit"] != git("rev-parse", "origin/main"):
        raise ValueError("authorization remote commit mismatch")
    for field, expected in identities.items():
        if authority[field] != expected:
            raise ValueError(f"authorization {field} mismatch")
    changed_runtime = git("diff", "--name-only", "--", *RUNTIME_PATHS)
    if changed_runtime:
        raise ValueError("runtime source has uncommitted changes")
    return authority


def runtime_snapshot() -> dict[str, Any]:
    container = subprocess.run(
        [*COMPOSE, "ps", "-q", "api"],
        cwd=ROOT,
        capture_output=True,
        text=True,
        check=True,
        timeout=20,
    ).stdout.strip()
    if not container:
        raise RuntimeError("dedicated R28 API container is not running")
    script_path = "/tmp/r28-r06-governed-preflight.php"
    subprocess.run(
        ["docker", "cp", str(DISPATCHER), f"{container}:{script_path}"],
        check=True,
        capture_output=True,
        timeout=20,
    )
    response = subprocess.run(
        [*COMPOSE, "exec", "-T", "api", "php", script_path, "--preflight"],
        cwd=ROOT,
        capture_output=True,
        text=True,
        check=True,
        timeout=30,
    )
    snapshot = json.loads(response.stdout)
    expected = [
        (PRIMARY_WORKSPACE, PRIMARY_USER, 986),
        ("be22830b-ddf1-4de5-b852-f7f4c111cf8e", None, 12),
        (INJECTION_WORKSPACE, INJECTION_USER, 6),
    ]
    if snapshot["environment"] != "local" or len(snapshot["workspaces"]) != 3:
        raise RuntimeError("R28 runtime environment/workspace roster differs")
    for actual, (workspace_id, user_id, count) in zip(
        snapshot["workspaces"], expected, strict=True
    ):
        if (
            actual["workspace_id"] != workspace_id
            or actual["user_public_id"] != user_id
            or actual["generation_status"] != "active"
            or actual["assigned_point_count"] != count
            or actual["foreign_chunk_assignments"] != 0
            or actual["dense_provider"] != "voyage"
            or actual["dense_model"] != "voyage-4-large"
            or actual["dense_dimensions"] != 1024
            or actual["sparse_provider"] != "fastembed"
            or actual["sparse_model"] != "prithivida/Splade_PP_en_v1"
        ):
            raise RuntimeError("R28 workspace generation or tenant boundary differs")
    if (
        snapshot["planner"]["model"] != "gpt-5-mini"
        or snapshot["generation_model"] != "gpt-5-mini"
    ):
        raise RuntimeError("R28 provider model configuration differs")
    return snapshot


def snapshot_digest(snapshot: dict[str, Any]) -> str:
    return hashlib.sha256(
        json.dumps(snapshot, sort_keys=True, separators=(",", ":")).encode()
    ).hexdigest()


def valid_record(
    record: dict[str, Any], item: dict[str, str], scope: dict[str, str], workspace: str
) -> bool:
    return (
        record.get("case_id") == item["case_id"]
        and record.get("variant_id") == item["variant_id"]
        and record.get("utterance") == item["utterance"]
        and record.get("frozen_scope") == scope["scope"]
        and record.get("needs_opt_in") == [workspace]
        and record.get("needs_pipeline_exercised") is True
        and isinstance(record.get("observation"), dict)
        and record["observation"].get("status") in {
            "completed", "retrieval_no_answer", "clarification_required"
        }
        and isinstance(record.get("displayed_text"), str)
        and bool(record["displayed_text"].strip())
    )


def write_exclusive(path: Path, value: object) -> None:
    descriptor = os.open(path, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
    with os.fdopen(descriptor, "w") as handle:
        json.dump(value, handle, ensure_ascii=False, sort_keys=True)
        handle.write("\n")
        handle.flush()
        os.fsync(handle.fileno())


def dispatch(
    stage: str,
    authority: dict[str, Any],
    execution: dict[str, Any],
    scopes: dict[str, Any],
    identities: dict[str, str],
    initial_snapshot: dict[str, Any],
) -> None:
    OUTPUT_ROOT.mkdir(parents=True, exist_ok=True)
    registry = OUTPUT_ROOT / ".authorizations"
    registry.mkdir(exist_ok=True, mode=0o700)
    run_dir = OUTPUT_ROOT / authority["run_id"]
    if run_dir.exists():
        raise ValueError("run directory already exists; no complete rerun")
    marker = registry / f"{authority['authorization_id']}.json"
    if marker.exists():
        raise ValueError("one-use authorization already consumed")
    run_dir.mkdir(mode=0o700)
    cases_dir = run_dir / "cases"
    cases_dir.mkdir(mode=0o700)
    write_exclusive(
        marker,
        {
            "authorization_id": authority["authorization_id"],
            "run_id": authority["run_id"],
        },
    )
    write_exclusive(run_dir / "authorization.json", authority)
    write_exclusive(
        run_dir / "code-freeze.json",
        {
            "execution_commit": authority["execution_commit"],
            **identities,
            "runtime_snapshot": initial_snapshot,
            "runtime_snapshot_sha256": snapshot_digest(initial_snapshot),
            "started_at_utc": datetime.now(UTC).isoformat(),
        },
    )
    write_exclusive(
        run_dir / "run-manifest.json",
        {
            "run_id": authority["run_id"],
            "stage": stage,
            "manifest_id": execution["subset_id"],
            "population_identity": "dolved-care-v4-evaluation-population-v2",
            "case_count": len(execution["items"]),
            "execution_boundary": "RunR28ProductionPathDiagnostic.handleCase -> OrchestrateConversationRun",
            "concurrency": 1,
            "selective_rerun_authorised": authority["selective_rerun_authorised"],
            "answer_key_entered_execution": False,
            "index_note": "Primary currently contains 986 assigned chunks: frozen 982 plus four separately approved injection-bait documents; foreign 12; injection 6.",
            "runtime_snapshot_sha256": snapshot_digest(initial_snapshot),
            "execution_inputs": execution["items"],
            "execution_scopes": scopes["items"],
        },
    )
    container_id = subprocess.run(
        [*COMPOSE, "ps", "-q", "api"],
        cwd=ROOT,
        capture_output=True,
        text=True,
        check=True,
        timeout=20,
    ).stdout.strip()
    if not container_id:
        raise RuntimeError("API container is not running")
    script_path = f"/tmp/{authority['run_id']}-production-case.php"
    subprocess.run(
        ["docker", "cp", str(DISPATCHER), f"{container_id}:{script_path}"],
        check=True,
        timeout=20,
    )

    ledger = run_dir / "dispatch.ndjson"
    for index, (item, scope) in enumerate(
        zip(execution["items"], scopes["items"], strict=True)
    ):
        if snapshot_digest(runtime_snapshot()) != snapshot_digest(initial_snapshot):
            raise RuntimeError(
                "R28 runtime configuration/index changed before next case"
            )
        if runtime_aggregate() != identities["runtime_source_aggregate"]:
            raise RuntimeError("runtime source changed before the next case")
        workspace, user = (
            (INJECTION_WORKSPACE, INJECTION_USER)
            if scope["scope"] == "security_test"
            else (PRIMARY_WORKSPACE, PRIMARY_USER)
        )
        case_path = (
            cases_dir / f"{index + 1:02d}-{item['case_id']}-{item['variant_id']}.json"
        )
        if case_path.exists():
            raise RuntimeError("case record already exists; no selective rerun")
        with ledger.open("a") as handle:
            handle.write(
                json.dumps(
                    {
                        "index": index,
                        "case_id": item["case_id"],
                        "variant_id": item["variant_id"],
                        "event": "dispatch_started",
                        "at": datetime.now(UTC).isoformat(),
                    }
                )
                + "\n"
            )
            handle.flush()
            os.fsync(handle.fileno())
        payload = {
            **item,
            "scope": scope["scope"],
            "workspace_id": workspace,
            "user_public_id": user,
        }
        result = subprocess.run(
            [
                *COMPOSE,
                "exec",
                "-T",
                "-e",
                f"CONVERSATION_NEEDS_WORKSPACE_IDS={workspace}",
                "api",
                "php",
                script_path,
            ],
            cwd=ROOT,
            input=json.dumps(payload),
            text=True,
            capture_output=True,
            timeout=960,
            check=False,
        )
        if result.returncode != 0:
            write_exclusive(
                case_path,
                {
                    "case_id": item["case_id"],
                    "variant_id": item["variant_id"],
                    "execution_failure": {
                        "exit_code": result.returncode,
                        "category": "production_dispatch_failed",
                    },
                },
            )
            raise RuntimeError(
                f"production dispatch failed at case {index + 1}; no rerun: {result.stderr[-500:]}"
            )
        record = json.loads(result.stdout)
        write_exclusive(case_path, record)
        if not valid_record(record, item, scope, workspace):
            raise RuntimeError(
                f"production record validation failed at case {index + 1}; no rerun"
            )
        with ledger.open("a") as handle:
            handle.write(
                json.dumps(
                    {
                        "index": index,
                        "case_id": item["case_id"],
                        "variant_id": item["variant_id"],
                        "event": "recorded",
                        "sha256": sha256(case_path),
                        "at": datetime.now(UTC).isoformat(),
                    }
                )
                + "\n"
            )
            handle.flush()
            os.fsync(handle.fileno())
        print(
            json.dumps(
                {
                    "index": index + 1,
                    "case_id": item["case_id"],
                    "status": record["observation"]["status"],
                }
            ),
            flush=True,
        )

    if runtime_aggregate() != identities["runtime_source_aggregate"]:
        raise RuntimeError("runtime source changed after dispatch")
    inventory = "".join(
        f"{sha256(path)}  {path.relative_to(run_dir)}\n"
        for path in sorted(run_dir.rglob("*.json"))
    )
    (run_dir / "checksums.sha256").write_text(inventory)
    print(
        json.dumps(
            {
                "status": "COMPLETED_UNSCORED",
                "run_dir": str(run_dir),
                "case_count": len(execution["items"]),
            }
        )
    )


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--stage", choices=STAGES, required=True)
    parser.add_argument("--authorization", type=Path, required=True)
    parser.add_argument("--preflight", action="store_true")
    args = parser.parse_args()
    execution, scopes, identities = verify_inputs(args.stage)
    authority = verify_authorization(
        args.authorization, args.stage, execution["subset_id"], identities
    )
    snapshot = runtime_snapshot()
    if authority["runtime_snapshot_sha256"] != snapshot_digest(snapshot):
        raise ValueError("authorization runtime snapshot mismatch")
    if args.preflight:
        print(
            json.dumps(
                {
                    "status": "READY_NO_PROVIDER_CALL",
                    "stage": args.stage,
                    "run_id": authority["run_id"],
                    "case_count": len(execution["items"]),
                    "runtime_source_aggregate": identities["runtime_source_aggregate"],
                    "runtime_snapshot_sha256": snapshot_digest(snapshot),
                }
            )
        )
        return
    dispatch(args.stage, authority, execution, scopes, identities, snapshot)


if __name__ == "__main__":
    main()
