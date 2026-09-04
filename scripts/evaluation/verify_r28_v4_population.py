#!/usr/bin/env python3
"""Provider-free verification for the immutable R28 V4 evaluation population."""

from __future__ import annotations

import hashlib
import io
import json
import os
import stat
import subprocess
import sys
import tarfile
from copy import deepcopy
from datetime import date
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
FREEZE = ROOT / "tests/evaluation/engineering-populations/dolved-care-v4/v2"
HISTORICAL_V1 = ROOT / "tests/evaluation/engineering-populations/dolved-care-v4/v1"
ACCESS = ROOT / "docs/evaluation/r28-s01/r28-s04-population-access.json"
SCRIPT_DIR = ROOT / "scripts/evaluation"
sys.path.insert(0, str(SCRIPT_DIR))

import validate_r28_authoring_output as v3

IDENTITY = "dolved-care-v4-evaluation-population-v2"
DIGEST = "adc9aa22646fc0f131ab7aa747dce91874655b95479cebc318653c3173e40f4c"
DIGEST_ALGORITHM = "r28-frozen-population-digest-v1"
RUN_ID = "AUTHOR-V4-20260904-C9W2P6LX"
POPULATION_ID = "dolved-v4-independent-comparison-compat-v2"
CONTRACT_AGGREGATE = "58e4d4b3ebbde74118bbbd287240ef861fea9035aa291642e2be2a97c6ae1624"
CONTRACT_COMMIT = "d069337b9fc4b1a0da782ea5df04789eb738021e"
INTEGRATION_START_COMMIT = "b9911fc54997360187f380d652c41e8058dd8494"
HISTORICAL_V1_IDENTITY = "dolved-care-v4-evaluation-population-v1"
HISTORICAL_V1_DIGEST = (
    "6254188d7fc7a698641750a81d436eac97eb425244704b64b1daac0c92803161"
)
COMPATIBILITY_CHECKPOINT = "COMPAT-V2-20260904-R7K3M8QX"
COMPATIBILITY_CHECKPOINT_SHA256 = (
    "40b16d20fab1734ac9cd04e65b66cb63f8423cf864bc8f17be4537c79771d4e1"
)
CORRECTED_CASES_SHA256 = (
    "442a92d6003738805b38d6a959a4ce44675344130d64ba307e5ddeeb265c9d42"
)
ACCEPTED_VERDICT = "R28_V4_COMPARISON_COMPATIBILITY_V2_CANDIDATE_ACCEPTED"
AUTHORITY_REFERENCE_DATE = date(2026, 9, 4)
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
CANONICAL_PATH = "tests/evaluation/engineering-populations/dolved-care-v4/v2"
CORRECTION_RULE = "Any correction requires a new population identity and version."
REPLACEMENT_RULE = "No later run may silently replace this population."
EXPECTED_SCOPES = {"primary": 62, "foreign_tenant": 6, "security_test": 6}
EXPECTED_ROUTING = {
    "all_utterances": 148,
    "corpus_embedding_requests": 8,
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
    "corpus_embedding_maximum_attempts": 16,
    "corpus_embedding_maximum_items_per_request": 128,
    "corpus_embedding_input_tokens_per_attempt": 93_750,
    "corpus_embedding_request_bytes_per_attempt": 524_288,
    "reranker_base_http_requests": 140,
    "reranker_maximum_attempts": 280,
    "total_base_provider_requests": 321,
    "total_maximum_physical_attempts": 642,
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
    require(
        manifest["schema_version"] == "r28-frozen-evaluation-population-manifest-v2",
        "freeze manifest schema drift",
    )
    population = manifest["frozen_population"]
    require(population["repository_path"] == CANONICAL_PATH, "repository path drift")
    require(
        manifest["freeze"]
        == {
            "date": "2026-09-04",
            "contract_repository_commit": CONTRACT_COMMIT,
            "integration_start_commit": INTEGRATION_START_COMMIT,
            "candidate_copy_rule": "byte-identical",
            "immutable": True,
            "correction_rule": CORRECTION_RULE,
            "replacement_rule": REPLACEMENT_RULE,
        },
        "freeze metadata drift",
    )
    require(
        manifest["compatibility_correction"]
        == {
            "parent_population_identity": HISTORICAL_V1_IDENTITY,
            "parent_population_digest": HISTORICAL_V1_DIGEST,
            "checkpoint_identity": COMPATIBILITY_CHECKPOINT,
            "checkpoint_sha256": COMPATIBILITY_CHECKPOINT_SHA256,
            "corrected_canonical_case_sha256": CORRECTED_CASES_SHA256,
            "candidate_identity": POPULATION_ID,
            "candidate_aggregate_sha256": DIGEST,
            "accepted_verdict": ACCEPTED_VERDICT,
            "accepted_on": "2026-09-04",
            "semantic_delta": {
                "comparison_side_corrections": 63,
                "fully_replaced_cases": ["v4.case.corrected-b02-09"],
                "unchanged_cases": 52,
            },
            "authority_reference_date": AUTHORITY_REFERENCE_DATE.isoformat(),
        },
        "V2 compatibility lineage drift",
    )
    require(
        manifest["final_audit"]
        == {"verdict": ACCEPTED_VERDICT, "independently_authored_and_audited": True},
        "V2 acceptance verdict drift",
    )
    counts = manifest["counts"]
    require(counts["semantic_cases"] == 74, "semantic case count drift")
    require(counts["utterances"] == 148, "utterance count drift")
    require(counts["scopes"] == EXPECTED_SCOPES, "scope counts drift")
    require(counts["answerable_comparison_cases"] == 22, "comparison count drift")
    require(
        counts["scheduled_future_comparison_sides"] == 0,
        "scheduled comparison count drift",
    )
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
    metadata = view_document_metadata(view_path)
    validate_comparison_authority(population, metadata)
    validate_v1_delta(population)
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
    for identity in (
        "r28-independent-authoring-output-v3",
        "dolved-v4-independent-authoring-output-v3",
        "r28-authoring-coverage-contract-v2",
        COMPATIBILITY_CHECKPOINT_SHA256,
        CORRECTED_CASES_SHA256,
        HISTORICAL_V1_DIGEST,
    ):
        require(identity in report, f"authoring report lineage drift: {identity}")
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


def view_document_metadata(view_path: Path) -> dict[str, dict[str, Any]]:
    archive = regular_file_bytes(view_path)
    member_name = "dolved-care-v4-question-author-view-v1/metadata/documents.json"
    with tarfile.open(fileobj=io.BytesIO(archive), mode="r:gz") as bundle:
        member = bundle.getmember(member_name)
        require(member.isfile() and not member.issym(), "view metadata is not regular")
        stream = bundle.extractfile(member)
        if stream is None:
            raise ValueError("view metadata is unreadable")
        payload = json.loads(stream.read())
    documents = payload["documents"]
    by_path = {document["view_path"]: document for document in documents}
    require(len(by_path) == len(documents), "duplicate view metadata path")
    return by_path


def authority_state(document: dict[str, Any]) -> str:
    effective = date.fromisoformat(document["effective_date"])
    superseded_raw = document["superseded_date"]
    superseded = date.fromisoformat(superseded_raw) if superseded_raw else None
    governance = document["governance_status"]
    if effective > AUTHORITY_REFERENCE_DATE:
        return "scheduled_future"
    if governance not in {"approved", "withdrawn"}:
        return "never_authoritative"
    if superseded is not None and superseded <= AUTHORITY_REFERENCE_DATE:
        return "historical"
    return "current"


def validate_comparison_authority(
    population: dict[str, Any], metadata: dict[str, dict[str, Any]]
) -> None:
    comparison_cases = 0
    scheduled_sides = 0
    for case in population["cases"]:
        if not (
            case["context"]["temporal_mode"] == "COMPARE"
            and case["expected_outcome"]["retrieval"] == "EVIDENCE_FOUND"
        ):
            continue
        comparison_cases += 1
        for evidence in case["expected_evidence"]:
            path = evidence["restricted_view_path"]
            require(path in metadata, f"missing authority metadata: {path}")
            state = authority_state(metadata[path])
            if state == "scheduled_future":
                scheduled_sides += 1
            expected_state = {
                "PRIMARY": "current",
                "COMPARISON": "historical",
            }[evidence["side"]]
            require(
                state == expected_state,
                f"comparison authority mismatch: {case['case_id']} {evidence['side']} "
                f"is {state}",
            )
        sides = {evidence["side"] for evidence in case["expected_evidence"]}
        require(
            sides == {"PRIMARY", "COMPARISON"},
            f"comparison side coverage mismatch: {case['case_id']}",
        )
    require(comparison_cases == 22, "answerable comparison case count drift")
    require(scheduled_sides == 0, "scheduled-future comparison evidence present")


def validate_v1_delta(population: dict[str, Any]) -> None:
    historical = json.loads(regular_file_bytes(HISTORICAL_V1 / "population.json"))
    old_cases = {case["case_id"]: case for case in historical["cases"]}
    new_cases = {case["case_id"]: case for case in population["cases"]}
    require(set(old_cases) == set(new_cases), "V1/V2 case identity drift")
    changed: list[str] = []
    side_corrections = 0
    replacement = "v4.case.corrected-b02-09"
    for case_id, old_case in old_cases.items():
        new_case = new_cases[case_id]
        if old_case == new_case:
            continue
        changed.append(case_id)
        if case_id == replacement:
            continue
        normalised_old = deepcopy(old_case)
        normalised_new = deepcopy(new_case)
        require(
            len(normalised_old["expected_evidence"])
            == len(normalised_new["expected_evidence"]),
            f"unexpected V2 evidence delta: {case_id}",
        )
        for old_evidence, new_evidence in zip(
            normalised_old["expected_evidence"],
            normalised_new["expected_evidence"],
            strict=True,
        ):
            if old_evidence["side"] != new_evidence["side"]:
                side_corrections += 1
            old_evidence["side"] = new_evidence["side"]
        require(
            normalised_old == normalised_new,
            f"unexpected non-side V2 delta: {case_id}",
        )
    require(len(changed) == 22, "V1/V2 changed case count drift")
    require(replacement in changed, "accepted replacement case missing")
    require(side_corrections == 63, "V1/V2 side correction count drift")
    require(74 - len(changed) == 52, "V1/V2 unchanged case count drift")
    corrected_bytes = (
        json.dumps(
            population["cases"],
            ensure_ascii=False,
            sort_keys=True,
            separators=(",", ":"),
        ).encode("utf-8")
        + b"\n"
    )
    require(
        sha256(corrected_bytes) == CORRECTED_CASES_SHA256,
        "corrected canonical case hash drift",
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
        access["supersedes_for_execution"]
        == {
            "path": "tests/evaluation/engineering-populations/dolved-care-v4/v1",
            "identity": HISTORICAL_V1_IDENTITY,
            "digest": HISTORICAL_V1_DIGEST,
            "disposition": "retained_historical_evidence",
        },
        "R28-S04 V1 historical lineage drift",
    )
    require(
        access["compatibility_correction"]
        == {
            "checkpoint_identity": COMPATIBILITY_CHECKPOINT,
            "checkpoint_sha256": COMPATIBILITY_CHECKPOINT_SHA256,
            "accepted_verdict": ACCEPTED_VERDICT,
            "accepted_on": "2026-09-04",
        },
        "R28-S04 V2 compatibility lineage drift",
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
        routing["corpus_embedding_requests"]
        + 1
        + ceilings["reranker_base_http_requests"]
        + ceilings["generation_calls"]
        + ceilings["judge_calls"]
        == ceilings["total_base_provider_requests"],
        "base provider request arithmetic mismatch",
    )
    require(
        ceilings["corpus_embedding_maximum_attempts"]
        + 2
        + ceilings["reranker_maximum_attempts"]
        + ceilings["generation_calls"] * 2
        + ceilings["judge_calls"] * 2
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
        "five byte identities, 39 coverage slices, 22 comparison cases, V1/V2 "
        "delta, restricted view and contract aggregate complete"
    )
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (KeyError, OSError, TypeError, ValueError, json.JSONDecodeError) as error:
        raise SystemExit(f"FAIL: {error}") from error
