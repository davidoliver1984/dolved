#!/usr/bin/env python3
"""Strict provider-free validator for independent R28 authoring output."""

from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import io
import json
import os
import re
import stat
import sys
import tarfile
import unicodedata
from pathlib import Path, PurePosixPath
from typing import Any

try:
    from jsonschema import Draft202012Validator, FormatChecker
    from jsonschema.exceptions import SchemaError, ValidationError
except ModuleNotFoundError as error:
    raise SystemExit(
        "FAIL: governed dependency jsonschema[format-nongpl] is unavailable; "
        "run this validator with apps/ai/.venv/bin/python"
    ) from error

from r28_authoring_access import (
    VIEW_MEMBER_PREFIX,
    classify_r28_authoring_input,
)

SCHEMA_VERSION = "r28-independent-authoring-output-v1"
CONTRACT_ID = "dolved-v4-independent-authoring-output-v1"
COVERAGE_VERSION = "r28-authoring-coverage-matrix-v1"
DECLARATION_VERSION = "r28-author-declaration-v1"
VIEW_ID = "dolved-care-v4-question-author-view-v1"
VIEW_SHA256 = "8f73c9c12a843be9641698f39db60243a977e6c1c700a3f89f72dbbb890e44b9"
OUTPUT_FILES = {
    "population.json",
    "coverage-matrix.json",
    "author-declaration.json",
    "authoring-report.md",
    "checksums.sha256",
}
CHECKSUM_FILES = OUTPUT_FILES - {"checksums.sha256"}
CASE_ID = re.compile(r"^v4\.case\.[a-z0-9][a-z0-9-]{2,47}$")
POPULATION_ID = re.compile(r"^dolved-v4-independent-[a-z0-9][a-z0-9-]{2,47}$")
RUN_ID = re.compile(r"^AUTHOR-V4-(?P<date>[0-9]{8})-(?P<suffix>[A-Z0-9]{8})$")
SHA256 = re.compile(r"^[0-9a-f]{64}$")
OUTCOMES = {
    "EVIDENCE_FOUND",
    "INSUFFICIENT_EVIDENCE",
    "CLARIFICATION_REQUIRED",
    "NO_ELIGIBLE_EVIDENCE",
    "NO_RETRIEVAL_CANDIDATES",
    "COMPARISON_SCOPE_INCOMPLETE",
    "TEMPORAL_SCOPE_UNRESOLVED",
}
GENERATION_OUTCOMES = {"answered", "qualified", "insufficient_evidence", None}
TEMPORAL_MODES = {
    "CURRENT",
    "HISTORICAL_REFERENCE",
    "VALID_AT_DATE",
    "COMPARE",
    "CLARIFICATION_REQUIRED",
}
SCOPES = {"primary", "foreign_tenant", "security_test"}
FORBIDDEN_FIELDS = {
    "observed_answer",
    "system_answer",
    "dolved_answer",
    "provider_output",
    "provider_response",
    "rank",
    "score",
    "chunk_id",
    "candidate_id",
    "document_id",
    "reranker_score",
    "retrieval_result",
}
PERMITTED_PARENT = Path("/tmp/dolved-r28-v4-authoring")
REPOSITORY_ROOT = Path(__file__).resolve().parents[2]
REQUIRED_EXTERNAL_INPUTS = {
    "docs/evaluation/r28-s01/access-manifest.json",
    "tests/evaluation/authoring-views/dolved-care-v4/v1/question-author-view.tar.gz",
    "contracts/evaluation/v4/independent-authoring-output.schema.json",
    "docs/evaluation/r28-s01/authoring-coverage-contract.json",
    "docs/evaluation/r28-s01/authoring-output-contract.md",
    "scripts/evaluation/r28_authoring_access.py",
    "scripts/evaluation/r28_access_guard.py",
    "scripts/evaluation/validate_r28_authoring_output.py",
}
OPTIONAL_EXTERNAL_INPUTS = {
    "docs/evaluation/r28-s01/question-authoring-handoff.md",
}
REQUIRED_VIEW_MEMBERS = {f"{VIEW_MEMBER_PREFIX}manifest.json"}


class Invalid(ValueError):
    pass


def require(condition: bool, message: str) -> None:
    if not condition:
        raise Invalid(message)


def valid_run_id(value: Any, where: str) -> str:
    text = bounded(value, 1, 40, where)
    match = RUN_ID.fullmatch(text)
    require(match is not None, f"invalid {where}")
    try:
        date_text = match.group("date")
        dt.date.fromisoformat(f"{date_text[:4]}-{date_text[4:6]}-{date_text[6:]}")
    except ValueError as error:
        raise Invalid(f"invalid calendar date in {where}") from error
    return text


def load_object(data: bytes, name: str) -> dict[str, Any]:
    value = json.loads(data.decode("utf-8"))
    require(isinstance(value, dict), f"{name} must contain one JSON object")
    return value


def exact_keys(value: dict[str, Any], expected: set[str], where: str) -> None:
    require(set(value) == expected, f"{where} fields mismatch")


def bounded(value: Any, minimum: int, maximum: int, where: str) -> str:
    require(
        isinstance(value, str) and minimum <= len(value) <= maximum,
        f"{where} is out of bounds",
    )
    return value


def safe_relative_path(value: Any, where: str) -> str:
    text = bounded(value, 1, 300, where)
    path = PurePosixPath(text)
    require(not path.is_absolute() and ".." not in path.parts, f"{where} is unsafe")
    return text


def normalized_utterance(value: str) -> str:
    normalized = unicodedata.normalize("NFC", value).strip()
    return re.sub(r"\s+", " ", normalized, flags=re.UNICODE).casefold()


def validate_population_schema(
    population: dict[str, Any], schema: dict[str, Any]
) -> None:
    try:
        Draft202012Validator.check_schema(schema)
        validator = Draft202012Validator(schema, format_checker=FormatChecker())
        validator.validate(population)
    except (SchemaError, ValidationError) as error:
        location = ".".join(str(item) for item in error.absolute_path) or "root"
        raise Invalid(
            f"population JSON Schema validation failed at {location}: {error.message}"
        ) from error


def reject_forbidden_fields(value: Any, where: str = "root") -> None:
    if isinstance(value, dict):
        forbidden = FORBIDDEN_FIELDS.intersection(value)
        require(
            not forbidden, f"{where} contains forbidden fields: {sorted(forbidden)}"
        )
        for key, item in value.items():
            reject_forbidden_fields(item, f"{where}.{key}")
    elif isinstance(value, list):
        for index, item in enumerate(value):
            reject_forbidden_fields(item, f"{where}[{index}]")


def parse_checksums(data: bytes) -> dict[str, str]:
    records: dict[str, str] = {}
    pattern = re.compile(r"^([0-9a-f]{64})  ([A-Za-z0-9][A-Za-z0-9.-]*)$")
    for line in data.decode("utf-8").splitlines():
        match = pattern.fullmatch(line)
        require(match is not None, "malformed checksum record")
        digest, name = match.groups()
        require(name in CHECKSUM_FILES, f"unexpected checksum path: {name}")
        require(name not in records, f"duplicate checksum path: {name}")
        records[name] = digest
    require(set(records) == CHECKSUM_FILES, "checksum inventory is incomplete")
    return records


def validate_checksums(files: dict[str, bytes]) -> None:
    checksums = parse_checksums(files["checksums.sha256"])
    for name, expected in checksums.items():
        require(
            hashlib.sha256(files[name]).hexdigest() == expected,
            f"checksum mismatch: {name}",
        )


def trusted_input_bytes(provided: Path, repository_relative: str) -> bytes:
    expected = REPOSITORY_ROOT / repository_relative
    try:
        require(
            provided.resolve(strict=True) == expected.resolve(strict=True),
            "neutral input path does not match its exact allowlisted identity",
        )
        before = expected.lstat()
        require(
            stat.S_ISREG(before.st_mode) and not stat.S_ISLNK(before.st_mode),
            "neutral input must be a regular non-symlink file",
        )
        require(
            hasattr(os, "O_NOFOLLOW"),
            "platform cannot provide non-following file opens",
        )
        fd = os.open(expected, os.O_RDONLY | os.O_NOFOLLOW)
        try:
            after = os.fstat(fd)
            require(
                stat.S_ISREG(after.st_mode)
                and (after.st_dev, after.st_ino) == (before.st_dev, before.st_ino),
                "neutral input changed during open",
            )
            chunks: list[bytes] = []
            while chunk := os.read(fd, 1024 * 1024):
                chunks.append(chunk)
            return b"".join(chunks)
        finally:
            os.close(fd)
    except Invalid:
        raise
    except OSError as error:
        raise Invalid("unable to open exact neutral input safely") from error


def view_inventory(view_data: bytes) -> tuple[dict[str, str], set[str]]:
    require(
        hashlib.sha256(view_data).hexdigest() == VIEW_SHA256,
        "restricted-view SHA-256 mismatch",
    )
    with tarfile.open(fileobj=io.BytesIO(view_data), mode="r:gz") as archive:
        root = VIEW_ID
        manifest = json.load(archive.extractfile(f"{root}/manifest.json"))
        require(manifest["view_id"] == VIEW_ID, "restricted-view identity mismatch")
        inventory = {item["path"]: item["sha256"] for item in manifest["inventory"]}
        members = {member.name for member in archive.getmembers()}
        return inventory, members


def canonical_parent() -> Path:
    try:
        temporary = Path("/tmp")
        temporary_info = temporary.lstat()
        if stat.S_ISLNK(temporary_info.st_mode):
            require(
                sys.platform == "darwin"
                and temporary.resolve(strict=True) == Path("/private/tmp")
                and not Path("/private/tmp").is_symlink(),
                "only the platform-owned macOS /tmp to /private/tmp mapping is permitted",
            )
        else:
            require(
                stat.S_ISDIR(temporary_info.st_mode),
                "platform /tmp must be a genuine directory",
            )
        lexical_parent_info = PERMITTED_PARENT.lstat()
        require(
            stat.S_ISDIR(lexical_parent_info.st_mode)
            and not stat.S_ISLNK(lexical_parent_info.st_mode),
            "permitted output parent must not be a candidate-controlled link",
        )
        parent = PERMITTED_PARENT.resolve(strict=True)
        info = parent.lstat()
    except OSError as error:
        raise Invalid("permitted output parent is unavailable") from error
    require(
        stat.S_ISDIR(info.st_mode) and not stat.S_ISLNK(info.st_mode),
        "permitted output parent must be a genuine directory",
    )
    return parent


def read_confined_output(
    output: Path, lexical_output: str | None = None
) -> tuple[str, dict[str, bytes]]:
    """Open one exact run directory and its files without following links."""
    valid_run_id(output.name, "output run-directory name")
    require(
        (lexical_output if lexical_output is not None else str(output))
        == f"/tmp/dolved-r28-v4-authoring/{output.name}",
        "output must use the exact lexical /tmp authoring path",
    )
    parent = canonical_parent()
    try:
        path_info = output.lstat()
    except OSError as error:
        raise Invalid("output run directory is unavailable") from error
    require(
        stat.S_ISDIR(path_info.st_mode) and not stat.S_ISLNK(path_info.st_mode),
        "output run directory must be a genuine directory",
    )

    require(
        hasattr(os, "O_DIRECTORY") and hasattr(os, "O_NOFOLLOW"),
        "platform cannot provide directory-relative non-following opens",
    )
    directory_flags = os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW
    parent_fd = run_fd = -1
    opened: dict[str, tuple[bytes, tuple[int, int]]] = {}
    try:
        parent_fd = os.open(parent, directory_flags)
        run_fd = os.open(output.name, directory_flags, dir_fd=parent_fd)
        run_info = os.fstat(run_fd)
        require(stat.S_ISDIR(run_info.st_mode), "opened run entry is not a directory")
        require(
            (run_info.st_dev, run_info.st_ino) == (path_info.st_dev, path_info.st_ino),
            "output run directory changed during validation",
        )
        try:
            names = set(os.listdir(run_fd))
        except OSError as error:
            raise Invalid("cannot enumerate output run directory safely") from error
        require(
            names == OUTPUT_FILES,
            "output directory filenames must match the contract exactly",
        )
        for name in sorted(OUTPUT_FILES):
            try:
                before = os.stat(name, dir_fd=run_fd, follow_symlinks=False)
            except OSError as error:
                raise Invalid(f"cannot inspect output entry safely: {name}") from error
            require(
                stat.S_ISREG(before.st_mode) and before.st_nlink == 1,
                f"output entry must be one ordinary non-linked regular file: {name}",
            )
            flags = os.O_RDONLY | os.O_NOFOLLOW
            try:
                fd = os.open(name, flags, dir_fd=run_fd)
            except OSError as error:
                raise Invalid(
                    f"cannot open output entry without following links: {name}"
                ) from error
            try:
                after = os.fstat(fd)
                require(
                    stat.S_ISREG(after.st_mode) and after.st_nlink == 1,
                    f"opened output entry is not one ordinary regular file: {name}",
                )
                identity = (after.st_dev, after.st_ino)
                require(
                    identity == (before.st_dev, before.st_ino),
                    f"output entry changed during open: {name}",
                )
                chunks: list[bytes] = []
                while chunk := os.read(fd, 1024 * 1024):
                    chunks.append(chunk)
                opened[name] = (b"".join(chunks), identity)
            finally:
                os.close(fd)
        require(
            set(os.listdir(run_fd)) == OUTPUT_FILES,
            "output directory changed during validation",
        )
        for name, (_, identity) in opened.items():
            current = os.stat(name, dir_fd=run_fd, follow_symlinks=False)
            require(
                stat.S_ISREG(current.st_mode)
                and current.st_nlink == 1
                and (current.st_dev, current.st_ino) == identity,
                f"output entry changed during validation: {name}",
            )
    except Invalid:
        raise
    except OSError as error:
        raise Invalid("unable to establish fail-closed output confinement") from error
    finally:
        if run_fd >= 0:
            os.close(run_fd)
        if parent_fd >= 0:
            os.close(parent_fd)
    return output.name, {name: item[0] for name, item in opened.items()}


def validate_coverage_contract(coverage: dict[str, Any]) -> set[str]:
    require(
        coverage["schema_version"] == "r28-authoring-coverage-contract-v1",
        "coverage contract version mismatch",
    )
    require(
        coverage["contract_id"] == "dolved-v4-independent-authoring-coverage-v1",
        "coverage contract identity mismatch",
    )
    require(
        coverage["semantic_case_count"] == 72
        and coverage["variants_per_case"] == 2
        and coverage["utterance_count"] == 144,
        "coverage population arithmetic mismatch",
    )
    scopes = coverage["scope_exact_counts"]
    minima = coverage["minimum_case_counts"]
    require(sum(scopes.values()) == 72, "exclusive scope counts must total 72")
    require(
        sum(value for key, value in minima.items() if key.startswith("outcome.")) <= 72,
        "mutually exclusive outcome minima exceed 72",
    )
    require(
        all(
            isinstance(value, int) and 0 <= value <= 72
            for value in (*scopes.values(), *minima.values())
        ),
        "coverage count is out of bounds",
    )
    require(
        all(
            minima[key] >= 5
            for key in ("safety.cross_tenant", "safety.prompt_injection")
        ),
        "safety-critical slices require at least five cases",
    )
    require(
        coverage["arithmetic"]
        == {
            "exclusive_scope_total": 72,
            "largest_mutually_exclusive_outcome_minimum_total": 65,
            "overlap_required": True,
            "feasible": True,
        },
        "coverage arithmetic record mismatch",
    )
    return set(scopes) | set(minima)


def validate_population(
    population: dict[str, Any], allowed_slices: set[str], view_files: dict[str, str]
) -> dict[str, set[str]]:
    exact_keys(
        population,
        {
            "schema_version",
            "contract_id",
            "population_id",
            "restricted_view",
            "author_provenance",
            "cases",
        },
        "population",
    )
    require(
        population["schema_version"] == SCHEMA_VERSION,
        "population schema version mismatch",
    )
    require(population["contract_id"] == CONTRACT_ID, "population contract mismatch")
    require(
        isinstance(population["population_id"], str)
        and POPULATION_ID.fullmatch(population["population_id"]),
        "invalid population identity",
    )
    exact_keys(population["restricted_view"], {"view_id", "sha256"}, "restricted_view")
    require(
        population["restricted_view"] == {"view_id": VIEW_ID, "sha256": VIEW_SHA256},
        "population view binding mismatch",
    )
    provenance = population["author_provenance"]
    exact_keys(
        provenance,
        {"authoring_run_id", "author_identity", "authored_at_utc", "method"},
        "author_provenance",
    )
    valid_run_id(provenance["authoring_run_id"], "authoring run identity")
    bounded(provenance["author_identity"], 1, 200, "author_identity")
    require(
        provenance["method"] == "fresh-independent-authoring-without-system-output",
        "invalid authoring method",
    )
    authored_at = dt.datetime.fromisoformat(provenance["authored_at_utc"])
    require(
        authored_at.utcoffset() == dt.timedelta(0)
        and provenance["authored_at_utc"].endswith(("Z", "+00:00")),
        "authored_at_utc must carry an explicit UTC offset",
    )
    cases = population["cases"]
    require(
        isinstance(cases, list) and len(cases) == 72,
        "population must contain exactly 72 semantic cases",
    )
    case_ids: set[str] = set()
    slice_cases = {name: set() for name in allowed_slices}
    variant_ids: set[tuple[str, str]] = set()
    global_texts: set[str] = set()
    utterances = 0
    for case in cases:
        exact_keys(
            case,
            {
                "case_id",
                "scope",
                "variants",
                "context",
                "expected_outcome",
                "expected_evidence",
                "rationale",
                "slices",
            },
            "case",
        )
        case_id = case["case_id"]
        require(
            isinstance(case_id, str) and CASE_ID.fullmatch(case_id), "invalid case ID"
        )
        require(case_id not in case_ids, "duplicate case ID")
        case_ids.add(case_id)
        scope = case["scope"]
        require(scope in SCOPES, "invalid case scope")
        variants = case["variants"]
        require(
            isinstance(variants, list) and len(variants) == 2,
            f"{case_id} must contain exactly two variants",
        )
        require(
            {item.get("variant_id") for item in variants if isinstance(item, dict)}
            == {"v1", "v2"},
            f"{case_id} variant IDs must be v1 and v2",
        )
        for variant in variants:
            exact_keys(variant, {"variant_id", "utterance"}, "variant")
            identity = (case_id, variant["variant_id"])
            require(identity not in variant_ids, "duplicate variant identity")
            variant_ids.add(identity)
            text = bounded(variant["utterance"], 3, 500, "utterance")
            normalized_text = normalized_utterance(text)
            require(
                normalized_text and normalized_text not in global_texts,
                "all 144 utterances must be globally distinct after normative normalization",
            )
            global_texts.add(normalized_text)
            utterances += 1
        context = case["context"]
        exact_keys(
            context,
            {
                "organisation",
                "location_id",
                "temporal_mode",
                "as_of_date",
                "requester_role",
            },
            "context",
        )
        bounded(context["organisation"], 1, 200, "organisation")
        require(
            context["location_id"] is None
            or (
                isinstance(context["location_id"], str)
                and len(context["location_id"]) <= 160
            ),
            "invalid location context",
        )
        require(context["temporal_mode"] in TEMPORAL_MODES, "invalid temporal mode")
        if context["as_of_date"] is not None:
            dt.date.fromisoformat(context["as_of_date"])
        require(
            (context["temporal_mode"] == "VALID_AT_DATE")
            == (context["as_of_date"] is not None),
            "VALID_AT_DATE requires exactly one date",
        )
        bounded(context["requester_role"], 1, 120, "requester role")
        outcome = case["expected_outcome"]
        exact_keys(outcome, {"retrieval", "generation"}, "expected_outcome")
        require(
            outcome["retrieval"] in OUTCOMES
            and outcome["generation"] in GENERATION_OUTCOMES,
            "invalid expected outcome",
        )
        require(
            (outcome["retrieval"] == "EVIDENCE_FOUND")
            == (outcome["generation"] is not None),
            "generation outcome is permitted exactly for EVIDENCE_FOUND",
        )
        evidence = case["expected_evidence"]
        require(
            isinstance(evidence, list) and len(evidence) <= 12,
            "invalid expected evidence collection",
        )
        require(
            outcome["retrieval"] != "EVIDENCE_FOUND" or evidence,
            "EVIDENCE_FOUND requires evidence",
        )
        evidence_ids: set[str] = set()
        sides: set[str] = set()
        for item in evidence:
            exact_keys(
                item,
                {
                    "evidence_id",
                    "side",
                    "restricted_view_path",
                    "source_sha256",
                    "quotation",
                },
                "expected_evidence",
            )
            evidence_id = bounded(item["evidence_id"], 4, 66, "evidence ID")
            require(
                re.fullmatch(r"ev-[a-z0-9][a-z0-9-]{2,63}", evidence_id) is not None
                and evidence_id not in evidence_ids,
                "invalid or duplicate evidence ID",
            )
            evidence_ids.add(evidence_id)
            require(item["side"] in {"PRIMARY", "COMPARISON"}, "invalid evidence side")
            sides.add(item["side"])
            relative = safe_relative_path(item["restricted_view_path"], "evidence path")
            require(
                relative.startswith(f"documents/{scope}/"),
                "evidence path crosses case scope",
            )
            require(
                relative in view_files, "evidence path is absent from restricted view"
            )
            require(
                SHA256.fullmatch(item["source_sha256"]) is not None
                and view_files[relative] == item["source_sha256"],
                "evidence source hash mismatch",
            )
            bounded(item["quotation"], 1, 500, "evidence quotation")
        if (
            context["temporal_mode"] == "COMPARE"
            and outcome["retrieval"] == "EVIDENCE_FOUND"
        ):
            require(
                sides == {"PRIMARY", "COMPARISON"},
                "successful comparison requires evidence on both sides",
            )
        bounded(case["rationale"], 1, 1000, "rationale")
        slices = case["slices"]
        require(
            isinstance(slices, list)
            and 1 <= len(slices) <= 24
            and len(slices) == len(set(slices)),
            "invalid slice list",
        )
        require(set(slices).issubset(allowed_slices), "unknown slice label")
        require(
            f"scope.{scope}" in slices and f"outcome.{outcome['retrieval']}" in slices,
            "scope and outcome slices are required",
        )
        for label in slices:
            slice_cases[label].add(case_id)
    require(
        utterances == 144 and len(variant_ids) == 144 and len(global_texts) == 144,
        "population must contain exactly 144 globally distinct utterances",
    )
    return slice_cases


def validate_coverage(
    matrix: dict[str, Any],
    population_id: str,
    slice_cases: dict[str, set[str]],
    coverage: dict[str, Any],
) -> None:
    exact_keys(
        matrix,
        {"schema_version", "contract_id", "population_id", "counting_rule", "slices"},
        "coverage matrix",
    )
    require(
        matrix["schema_version"] == COVERAGE_VERSION
        and matrix["contract_id"] == coverage["contract_id"],
        "coverage identity mismatch",
    )
    require(
        matrix["population_id"] == population_id
        and matrix["counting_rule"] == coverage["counting_rule"],
        "coverage binding mismatch",
    )
    rows = matrix["slices"]
    require(
        isinstance(rows, list) and len(rows) == len(slice_cases),
        "coverage matrix must contain every closed slice exactly once",
    )
    observed: set[str] = set()
    for row in rows:
        exact_keys(row, {"slice", "case_count", "case_ids"}, "coverage row")
        label = row["slice"]
        require(
            label in slice_cases and label not in observed,
            "unknown or duplicate coverage slice",
        )
        observed.add(label)
        ids = row["case_ids"]
        require(
            isinstance(ids, list)
            and len(ids) == len(set(ids))
            and set(ids) == slice_cases[label],
            f"coverage case binding mismatch: {label}",
        )
        require(row["case_count"] == len(ids), f"coverage count mismatch: {label}")
    require(observed == set(slice_cases), "coverage matrix is incomplete")
    for label, exact in coverage["scope_exact_counts"].items():
        require(len(slice_cases[label]) == exact, f"scope count failed: {label}")
    for label, minimum in coverage["minimum_case_counts"].items():
        require(len(slice_cases[label]) >= minimum, f"coverage minimum failed: {label}")


def validate_declaration(
    declaration: dict[str, Any],
    population: dict[str, Any],
    run_directory_name: str,
    view_members: set[str],
    access_manifest: dict[str, Any],
) -> None:
    exact_keys(
        declaration,
        {
            "schema_version",
            "contract_id",
            "population_id",
            "authoring_run_id",
            "accessed_input_paths",
            "repository_unchanged",
            "system_output_seen",
            "contamination_detected",
            "declaration",
        },
        "author declaration",
    )
    require(
        declaration["schema_version"] == DECLARATION_VERSION
        and declaration["contract_id"] == CONTRACT_ID,
        "declaration identity mismatch",
    )
    require(
        declaration["population_id"] == population["population_id"],
        "declaration population mismatch",
    )
    require(
        declaration["authoring_run_id"]
        == population["author_provenance"]["authoring_run_id"],
        "declaration run mismatch",
    )
    require(
        declaration["authoring_run_id"] == run_directory_name,
        "authoring run identity must equal the output directory name",
    )
    paths = declaration["accessed_input_paths"]
    require(
        isinstance(paths, list)
        and len(REQUIRED_EXTERNAL_INPUTS) <= len(paths) <= 400
        and len(paths) == len(set(paths)),
        "invalid accessed input paths",
    )
    observed_external: set[str] = set()
    observed_members: set[str] = set()
    for path in paths:
        text = bounded(path, 1, 500, "accessed input path")
        classification = classify_r28_authoring_input(
            text, access_manifest, view_members
        )
        require(
            classification is not None,
            "accessed input is outside the exact R28-authoring allowlist",
        )
        if classification == "VIEW_MEMBER":
            observed_members.add(text)
        else:
            observed_external.add(text)
    require(
        REQUIRED_EXTERNAL_INPUTS.issubset(observed_external),
        "required neutral input is undeclared",
    )
    require(
        REQUIRED_VIEW_MEMBERS.issubset(observed_members),
        "required restricted-view manifest access is undeclared",
    )
    require(
        declaration["repository_unchanged"] is True,
        "author must leave repository unchanged",
    )
    require(
        declaration["system_output_seen"] is False
        and declaration["contamination_detected"] is False,
        "authoring contamination declared",
    )
    bounded(declaration["declaration"], 40, 2000, "declaration text")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--restricted-view", type=Path, required=True)
    parser.add_argument(
        "--coverage-contract",
        type=Path,
        default=Path("docs/evaluation/r28-s01/authoring-coverage-contract.json"),
    )
    args = parser.parse_args()
    run_directory_name, files = read_confined_output(
        Path(args.output_dir), lexical_output=args.output_dir
    )
    validate_checksums(files)
    coverage_data = trusted_input_bytes(
        args.coverage_contract,
        "docs/evaluation/r28-s01/authoring-coverage-contract.json",
    )
    coverage = load_object(coverage_data, args.coverage_contract.name)
    allowed_slices = validate_coverage_contract(coverage)
    population = load_object(files["population.json"], "population.json")
    schema_data = trusted_input_bytes(
        REPOSITORY_ROOT
        / "contracts/evaluation/v4/independent-authoring-output.schema.json",
        "contracts/evaluation/v4/independent-authoring-output.schema.json",
    )
    schema = load_object(schema_data, "independent-authoring-output.schema.json")
    validate_population_schema(population, schema)
    reject_forbidden_fields(population)
    view_data = trusted_input_bytes(
        args.restricted_view,
        "tests/evaluation/authoring-views/dolved-care-v4/v1/question-author-view.tar.gz",
    )
    inventory, view_members = view_inventory(view_data)
    slices = validate_population(population, allowed_slices, inventory)
    validate_coverage(
        load_object(files["coverage-matrix.json"], "coverage-matrix.json"),
        population["population_id"],
        slices,
        coverage,
    )
    validate_declaration(
        load_object(files["author-declaration.json"], "author-declaration.json"),
        population,
        run_directory_name,
        view_members,
        load_object(
            trusted_input_bytes(
                REPOSITORY_ROOT / "docs/evaluation/r28-s01/access-manifest.json",
                "docs/evaluation/r28-s01/access-manifest.json",
            ),
            "access-manifest.json",
        ),
    )
    report = files["authoring-report.md"].decode("utf-8")
    bounded(report, 40, 100_000, "authoring report")
    print("PASS 72 semantic cases, 144 utterances, coverage and provenance complete")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (
        Invalid,
        KeyError,
        TypeError,
        ValueError,
        json.JSONDecodeError,
        tarfile.TarError,
    ) as error:
        raise SystemExit(f"FAIL: {error}") from error
