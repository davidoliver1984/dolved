#!/usr/bin/env python3
"""Provider-free verification for the immutable R28 V4 evaluation population."""

from __future__ import annotations

import hashlib
import json
import os
import stat
import subprocess
import sys
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
FREEZE = ROOT / "tests/evaluation/engineering-populations/dolved-care-v4/v1"
ACCESS = ROOT / "docs/evaluation/r28-s01/r28-s04-population-access.json"
SCRIPT_DIR = ROOT / "scripts/evaluation"
sys.path.insert(0, str(SCRIPT_DIR))

import validate_r28_authoring_output as v3

IDENTITY = "dolved-care-v4-evaluation-population-v1"
DIGEST = "6254188d7fc7a698641750a81d436eac97eb425244704b64b1daac0c92803161"
DIGEST_ALGORITHM = "r28-frozen-population-digest-v1"
RUN_ID = "AUTHOR-V4-20260903-H4K9T2MC"
POPULATION_ID = "dolved-v4-independent-corrected-74case-v3-r2"
CONTRACT_AGGREGATE = "58e4d4b3ebbde74118bbbd287240ef861fea9035aa291642e2be2a97c6ae1624"
CONTRACT_COMMIT = "d069337b9fc4b1a0da782ea5df04789eb738021e"
CONTRACT_PATHS = (
    "docs/evaluation/r28-s01/access-manifest.json",
    "contracts/evaluation/v4/independent-authoring-output.schema.json",
    "docs/evaluation/r28-s01/authoring-coverage-contract.json",
    "scripts/evaluation/r28_authoring_access.py",
    "scripts/evaluation/r28_access_guard.py",
    "scripts/evaluation/validate_r28_authoring_output.py",
)
EXPECTED_DIRECTORY_FILES = {
    *v3.OUTPUT_FILES,
    "freeze-manifest.json",
    "README.md",
}
CANONICAL_PATH = "tests/evaluation/engineering-populations/dolved-care-v4/v1"
CORRECTION_RULE = "Any correction requires a new population identity and version."
REPLACEMENT_RULE = "No later run may silently replace this population."
EXPECTED_SCOPES = {"primary": 62, "foreign_tenant": 6, "security_test": 6}
EXPECTED_ROUTING = {
    "all_utterances": 148,
    "retrieval_utterances": 106,
    "maximum_reranking_utterances": 96,
    "generation_utterances": 86,
    "judging_utterances": 86,
    "deterministic_termination_utterances": 62,
    "outcomes": {
        "EVIDENCE_FOUND": 86,
        "INSUFFICIENT_EVIDENCE": 10,
        "NO_RETRIEVAL_CANDIDATES": 10,
        "NO_ELIGIBLE_EVIDENCE": 12,
        "CLARIFICATION_REQUIRED": 10,
        "COMPARISON_SCOPE_INCOMPLETE": 10,
        "TEMPORAL_SCOPE_UNRESOLVED": 10,
    },
}
EXPECTED_CEILINGS = {
    "query_embedding_items": 106,
    "reranker_base_http_requests": 140,
    "reranker_maximum_attempts": 280,
    "total_base_provider_requests": 314,
    "total_maximum_physical_attempts": 628,
    "total_input_tokens": 7_416_320,
    "total_output_tokens": 1_056_768,
    "generation_calls": 86,
    "judge_calls": 86,
    "concurrency": 1,
    "wall_clock_minutes": 180,
    "maximum_retries_per_logical_request": 1,
    "total_usd": 30,
}


def require(condition: bool, message: str) -> None:
    if not condition:
        raise ValueError(message)


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def regular_file_bytes(path: Path) -> bytes:
    before = path.lstat()
    require(
        stat.S_ISREG(before.st_mode)
        and not stat.S_ISLNK(before.st_mode)
        and before.st_nlink == 1,
        f"frozen entry is not one ordinary non-linked file: {path.name}",
    )
    flags = os.O_RDONLY | os.O_NOFOLLOW
    descriptor = os.open(path, flags)
    try:
        after = os.fstat(descriptor)
        require(
            stat.S_ISREG(after.st_mode)
            and after.st_nlink == 1
            and (after.st_dev, after.st_ino) == (before.st_dev, before.st_ino),
            f"frozen entry changed during open: {path.name}",
        )
        chunks: list[bytes] = []
        while chunk := os.read(descriptor, 1024 * 1024):
            chunks.append(chunk)
        return b"".join(chunks)
    finally:
        os.close(descriptor)


def digest_input(records: dict[str, str]) -> bytes:
    return "".join(f"{records[name]}  {name}\n" for name in sorted(records)).encode(
        "ascii"
    )


def validate_manifest_metadata(manifest: dict[str, Any]) -> None:
    population = manifest["frozen_population"]
    require(population["repository_path"] == CANONICAL_PATH, "repository path drift")
    require(
        manifest["freeze"]
        == {
            "date": "2026-09-04",
            "contract_repository_commit": CONTRACT_COMMIT,
            "candidate_copy_rule": "byte-identical",
            "immutable": True,
            "correction_rule": CORRECTION_RULE,
            "replacement_rule": REPLACEMENT_RULE,
        },
        "freeze metadata drift",
    )
    counts = manifest["counts"]
    require(counts["semantic_cases"] == 74, "semantic case count drift")
    require(counts["utterances"] == 148, "utterance count drift")
    require(counts["scopes"] == EXPECTED_SCOPES, "scope counts drift")
    require(
        manifest["provider_execution_authorised"] is False,
        "freeze manifest authorised provider execution",
    )


def load_and_verify_candidate(
    freeze: Path, manifest: dict[str, Any]
) -> dict[str, bytes]:
    require(
        set(os.listdir(freeze)) == EXPECTED_DIRECTORY_FILES,
        "frozen directory inventory mismatch",
    )
    population = manifest["frozen_population"]
    require(population["identity"] == IDENTITY, "frozen population identity drift")
    require(
        population["digest_algorithm"] == DIGEST_ALGORITHM,
        "frozen digest algorithm drift",
    )
    require(population["digest"] == DIGEST, "frozen population digest drift")
    require(
        manifest["source"]
        == {"authoring_run_id": RUN_ID, "population_id": POPULATION_ID},
        "source identity drift",
    )
    validate_manifest_metadata(manifest)
    records = manifest["candidate_files"]
    require(set(records) == v3.OUTPUT_FILES, "candidate manifest inventory mismatch")
    files = {name: regular_file_bytes(freeze / name) for name in sorted(records)}
    for name, expected in records.items():
        require(sha256(files[name]) == expected, f"candidate hash mismatch: {name}")
    require(
        sha256(digest_input(records)) == DIGEST, "frozen digest reconstruction mismatch"
    )
    v3.validate_checksums(files)
    return files


def contract_aggregate() -> str:
    digest = hashlib.sha256()
    for relative in CONTRACT_PATHS:
        content = subprocess.run(
            ["git", "-C", str(ROOT), "show", f"{CONTRACT_COMMIT}:{relative}"],
            check=True,
            capture_output=True,
            timeout=10,
        ).stdout
        digest.update(relative.encode("utf-8"))
        digest.update(b"\0")
        digest.update(content)
        digest.update(b"\0")
    return digest.hexdigest()


def validate_v3(files: dict[str, bytes], manifest: dict[str, Any]) -> None:
    coverage_path = ROOT / "docs/evaluation/r28-s01/authoring-coverage-contract.json"
    coverage = v3.load_object(
        v3.trusted_input_bytes(coverage_path, str(coverage_path.relative_to(ROOT))),
        coverage_path.name,
    )
    allowed_slices = v3.validate_coverage_contract(coverage)
    population = v3.load_object(files["population.json"], "population.json")
    schema_path = (
        ROOT / "contracts/evaluation/v4/independent-authoring-output.schema.json"
    )
    schema = v3.load_object(
        v3.trusted_input_bytes(schema_path, str(schema_path.relative_to(ROOT))),
        schema_path.name,
    )
    v3.validate_population_schema(population, schema)
    v3.reject_forbidden_fields(population)
    view_path = (
        ROOT
        / "tests/evaluation/authoring-views/dolved-care-v4/v1/question-author-view.tar.gz"
    )
    inventory, view_members = v3.view_inventory(
        v3.trusted_input_bytes(view_path, str(view_path.relative_to(ROOT)))
    )
    slices = v3.validate_population(population, allowed_slices, inventory)
    v3.validate_coverage(
        v3.load_object(files["coverage-matrix.json"], "coverage-matrix.json"),
        POPULATION_ID,
        slices,
        coverage,
    )
    access_path = ROOT / "docs/evaluation/r28-s01/access-manifest.json"
    access = v3.load_object(
        v3.trusted_input_bytes(access_path, str(access_path.relative_to(ROOT))),
        access_path.name,
    )
    v3.validate_declaration(
        v3.load_object(files["author-declaration.json"], "author-declaration.json"),
        population,
        RUN_ID,
        view_members,
        access,
    )
    report = files["authoring-report.md"].decode("utf-8")
    require(CONTRACT_AGGREGATE in report, "authoring report contract aggregate drift")
    require(
        manifest["contracts"]["aggregate_sha256"] == CONTRACT_AGGREGATE,
        "manifest contract aggregate drift",
    )
    require(
        manifest["freeze"]["contract_repository_commit"] == CONTRACT_COMMIT,
        "contract repository commit drift",
    )
    require(
        contract_aggregate() == CONTRACT_AGGREGATE,
        "repository contract aggregate mismatch",
    )


def validate_access_binding(access: dict[str, Any]) -> None:
    require(
        access["schema_version"] == "r28-s04-population-access-v1",
        "access schema drift",
    )
    require(
        access["session"] == "R28-S04" and access["policy"] == "fail_closed",
        "access policy drift",
    )
    require(
        access["sole_authorised_population"]
        == {
            "path": CANONICAL_PATH,
            "identity": IDENTITY,
            "digest": DIGEST,
            "immutable": True,
        },
        "R28-S04 population access binding drift",
    )
    require(
        access["substitution"]
        == {
            "r28_s02_population_permitted": False,
            "calibration_population_permitted": False,
            "held_out_population_permitted": False,
            "historical_population_permitted": False,
            "missing_or_mismatched_identity_result": "FAIL",
        },
        "R28-S04 substitution policy drift",
    )
    require(
        access["provider_execution_authorised"] is False,
        "access file authorised provider execution",
    )
    require(
        access["correction_rule"] == CORRECTION_RULE,
        "access correction rule drift",
    )
    validate_execution_protocol(access["execution_protocol"])


def validate_execution_protocol(protocol: dict[str, Any]) -> None:
    routing = protocol["routing"]
    ceilings = protocol["ceilings"]
    require(routing == EXPECTED_ROUTING, "execution routing drift")
    require(ceilings == EXPECTED_CEILINGS, "execution ceilings drift")

    outcomes = routing["outcomes"]
    require(
        sum(outcomes.values()) == routing["all_utterances"], "routing total mismatch"
    )
    require(
        outcomes["EVIDENCE_FOUND"]
        + outcomes["INSUFFICIENT_EVIDENCE"]
        + outcomes["NO_RETRIEVAL_CANDIDATES"]
        == routing["retrieval_utterances"],
        "retrieval routing mismatch",
    )
    require(
        outcomes["EVIDENCE_FOUND"] + outcomes["INSUFFICIENT_EVIDENCE"]
        == routing["maximum_reranking_utterances"],
        "reranking routing mismatch",
    )
    require(
        routing["all_utterances"] - outcomes["EVIDENCE_FOUND"]
        == routing["deterministic_termination_utterances"],
        "deterministic termination mismatch",
    )
    require(
        1
        + 1
        + ceilings["reranker_base_http_requests"]
        + ceilings["generation_calls"]
        + ceilings["judge_calls"]
        == ceilings["total_base_provider_requests"],
        "base provider request arithmetic mismatch",
    )
    require(
        ceilings["total_base_provider_requests"] * 2
        == ceilings["total_maximum_physical_attempts"],
        "maximum attempt arithmetic mismatch",
    )


def main() -> int:
    manifest = json.loads(regular_file_bytes(FREEZE / "freeze-manifest.json"))
    files = load_and_verify_candidate(FREEZE, manifest)
    validate_v3(files, manifest)
    validate_access_binding(json.loads(regular_file_bytes(ACCESS)))
    print(
        "PASS frozen R28 V4 population: 74 semantic cases, 148 utterances, "
        "five byte identities, 39 coverage slices, restricted view and contract aggregate complete"
    )
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (KeyError, OSError, TypeError, ValueError, json.JSONDecodeError) as error:
        raise SystemExit(f"FAIL: {error}") from error
