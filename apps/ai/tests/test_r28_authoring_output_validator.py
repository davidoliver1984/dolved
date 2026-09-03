from __future__ import annotations

import hashlib
import importlib.util
import inspect
import os
import shutil
import stat
import sys
import uuid
from pathlib import Path

import pytest

SCRIPT_ROOT = Path(os.environ.get("SCRIPT_ROOT", "/workspace"))
if not SCRIPT_ROOT.is_dir():
    SCRIPT_ROOT = Path(__file__).resolve().parents[3]
SCRIPT = SCRIPT_ROOT / "scripts/evaluation/validate_r28_authoring_output.py"
sys.path.insert(0, str(SCRIPT.parent))
SPEC = importlib.util.spec_from_file_location("r28_authoring_validator", SCRIPT)
assert SPEC and SPEC.loader
VALIDATOR = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(VALIDATOR)


def output_parent() -> Path:
    parent = Path("/tmp/dolved-r28-v4-authoring")
    parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    return parent


def fresh_run() -> Path:
    run = output_parent() / f"AUTHOR-V4-20260903-{uuid.uuid4().hex[:8].upper()}"
    run.mkdir()
    for name in VALIDATOR.OUTPUT_FILES:
        (run / name).write_bytes(b"test")
    return run


def checksum_bytes(files: dict[str, bytes]) -> bytes:
    return "".join(
        f"{hashlib.sha256(files[name]).hexdigest()}  {name}\n"
        for name in sorted(VALIDATOR.CHECKSUM_FILES)
    ).encode()


def valid_case(index: int = 0) -> dict:
    case_id = f"v4.case.synthetic-{index:03d}"
    return {
        "case_id": case_id,
        "scope": "primary",
        "variants": [
            {"variant_id": "v1", "utterance": f"Synthetic wording A {index}"},
            {"variant_id": "v2", "utterance": f"Synthetic wording B {index}"},
        ],
        "context": {
            "organisation": "Synthetic organisation",
            "location_id": None,
            "temporal_mode": "CURRENT",
            "as_of_date": None,
            "requester_role": "employee",
        },
        "expected_outcome": {"retrieval": "EVIDENCE_FOUND", "generation": "answered"},
        "expected_evidence": [
            {
                "evidence_id": f"ev-synthetic-{index:03d}",
                "side": "PRIMARY",
                "restricted_view_path": "documents/primary/synthetic.txt",
                "source_sha256": "a" * 64,
                "quotation": "Synthetic quotation",
            }
        ],
        "rationale": "Synthetic structural fixture",
        "slices": ["scope.primary", "outcome.EVIDENCE_FOUND"],
    }


def valid_population() -> dict:
    return {
        "schema_version": VALIDATOR.SCHEMA_VERSION,
        "contract_id": VALIDATOR.CONTRACT_ID,
        "population_id": "dolved-v4-independent-synthetic",
        "restricted_view": {
            "view_id": VALIDATOR.VIEW_ID,
            "sha256": VALIDATOR.VIEW_SHA256,
        },
        "author_provenance": {
            "authoring_run_id": "AUTHOR-V4-20260903-ABCDEFGH",
            "author_identity": "synthetic structural test",
            "authored_at_utc": "2026-09-03T00:00:00+00:00",
            "method": "fresh-independent-authoring-without-system-output",
        },
        "cases": [valid_case(index) for index in range(74)],
    }


def validate_population(population: dict, view: dict[str, str] | None = None) -> None:
    VALIDATOR.validate_population(
        population,
        {"scope.primary", "outcome.EVIDENCE_FOUND"},
        view or {"documents/primary/synthetic.txt": "a" * 64},
    )


def schema() -> dict:
    import json

    return json.loads(
        (
            SCRIPT_ROOT
            / "contracts/evaluation/v4/independent-authoring-output.schema.json"
        ).read_text()
    )


def access_manifest() -> dict:
    import json

    return json.loads(
        (SCRIPT_ROOT / "docs/evaluation/r28-s01/access-manifest.json").read_text()
    )


def coverage_contract() -> dict:
    import json

    return json.loads(
        (
            SCRIPT_ROOT / "docs/evaluation/r28-s01/authoring-coverage-contract.json"
        ).read_text()
    )


def population_with_scope_distribution(
    primary: int, foreign: int, security: int
) -> dict:
    population = valid_population()
    scopes = (
        ["primary"] * primary
        + ["foreign_tenant"] * foreign
        + ["security_test"] * security
    )
    outcomes = list(VALIDATOR.OUTCOMES - {"EVIDENCE_FOUND"})
    for index, (case, scope) in enumerate(zip(population["cases"], scopes)):
        outcome = outcomes[index % len(outcomes)] if index < 30 else "EVIDENCE_FOUND"
        case["scope"] = scope
        case["expected_outcome"]["retrieval"] = outcome
        case["expected_outcome"]["generation"] = (
            "answered" if outcome == "EVIDENCE_FOUND" else None
        )
        case["expected_evidence"] = (
            [
                {
                    "evidence_id": f"ev-synthetic-{index:03d}",
                    "side": "PRIMARY",
                    "restricted_view_path": f"documents/{scope}/synthetic.txt",
                    "source_sha256": "a" * 64,
                    "quotation": "Synthetic quotation",
                }
            ]
            if outcome == "EVIDENCE_FOUND"
            else []
        )
        case["slices"] = [f"scope.{scope}", f"outcome.{outcome}"]
    population["cases"] = population["cases"][: len(scopes)]
    case_count = len(population["cases"])
    for offset, (label, minimum) in enumerate(
        item
        for item in VALIDATOR.EXPECTED_MINIMUM_CASE_COUNTS.items()
        if not item[0].startswith("outcome.")
    ):
        for step in range(minimum):
            population["cases"][(offset + step) % case_count]["slices"].append(label)
    return population


def coverage_matrix(population: dict, slices: dict[str, set[str]]) -> dict:
    coverage = coverage_contract()
    return {
        "schema_version": VALIDATOR.COVERAGE_VERSION,
        "contract_id": coverage["contract_id"],
        "population_id": population["population_id"],
        "counting_rule": coverage["counting_rule"],
        "slices": [
            {"slice": label, "case_count": len(ids), "case_ids": sorted(ids)}
            for label, ids in sorted(slices.items())
        ],
    }


def test_rejects_absolute_and_traversal_paths() -> None:
    for value in ("/absolute/document.pdf", "documents/../hidden.json"):
        with pytest.raises(VALIDATOR.Invalid, match="unsafe"):
            VALIDATOR.safe_relative_path(value, "test path")


def test_rejects_system_generated_fields_recursively() -> None:
    with pytest.raises(VALIDATOR.Invalid, match="forbidden fields"):
        VALIDATOR.reject_forbidden_fields(
            {"cases": [{"expected_evidence": [{"chunk_id": "not-authorable"}]}]}
        )


def test_rejects_malformed_duplicate_and_incomplete_checksums(tmp_path: Path) -> None:
    checksum = tmp_path / "checksums.sha256"
    checksum.write_text("not-a-digest  population.json\n")
    with pytest.raises(VALIDATOR.Invalid, match="malformed"):
        VALIDATOR.parse_checksums(checksum.read_bytes())

    digest = "a" * 64
    checksum.write_text(f"{digest}  population.json\n{digest}  population.json\n")
    with pytest.raises(VALIDATOR.Invalid, match="duplicate"):
        VALIDATOR.parse_checksums(checksum.read_bytes())

    checksum.write_text(f"{digest}  population.json\n")
    with pytest.raises(VALIDATOR.Invalid, match="incomplete"):
        VALIDATOR.parse_checksums(checksum.read_bytes())


def test_rejects_wrong_case_and_utterance_counts() -> None:
    coverage = {
        "contract_id": "coverage",
        "counting_rule": "overlap",
        "scope_exact_counts": {},
        "minimum_case_counts": {},
    }
    population = {
        "schema_version": VALIDATOR.SCHEMA_VERSION,
        "contract_id": VALIDATOR.CONTRACT_ID,
        "population_id": "dolved-v4-independent-test",
        "restricted_view": {
            "view_id": VALIDATOR.VIEW_ID,
            "sha256": VALIDATOR.VIEW_SHA256,
        },
        "author_provenance": {
            "authoring_run_id": "AUTHOR-V4-20260903-ABCDEFGH",
            "author_identity": "synthetic structural test",
            "authored_at_utc": "2026-09-03T00:00:00Z",
            "method": "fresh-independent-authoring-without-system-output",
        },
        "cases": [],
    }
    with pytest.raises(VALIDATOR.Invalid, match="exactly 74"):
        VALIDATOR.validate_population(population, set(), {})

    assert coverage["scope_exact_counts"] == {}


def test_rejects_72_cases_and_144_utterances_under_v2() -> None:
    population = valid_population()
    population["cases"] = population["cases"][:72]
    assert sum(len(case["variants"]) for case in population["cases"]) == 144
    with pytest.raises(VALIDATOR.Invalid, match="exactly 74"):
        validate_population(population)


def test_population_count_contract_is_not_caller_selectable() -> None:
    signature = inspect.signature(VALIDATOR.validate_population)
    assert tuple(signature.parameters) == ("population", "allowed_slices", "view_files")
    one_case = valid_population()
    one_case["cases"] = one_case["cases"][:1]
    with pytest.raises(TypeError, match="unexpected keyword"):
        VALIDATOR.validate_population(one_case, set(), {}, semantic_case_count=1)
    with pytest.raises(TypeError, match="positional arguments"):
        VALIDATOR.validate_population(one_case, set(), {}, 1, 2)
    with pytest.raises(VALIDATOR.Invalid, match="exactly 74"):
        VALIDATOR.validate_population(one_case, set(), {})


def test_smaller_coverage_json_cannot_influence_population_count() -> None:
    coverage = coverage_contract()
    coverage.update(
        {"semantic_case_count": 1, "variants_per_case": 2, "utterance_count": 2}
    )
    with pytest.raises(VALIDATOR.Invalid, match="population arithmetic"):
        VALIDATOR.validate_coverage_contract(coverage)
    one_case = valid_population()
    one_case["cases"] = one_case["cases"][:1]
    with pytest.raises(VALIDATOR.Invalid, match="exactly 74"):
        VALIDATOR.validate_population(one_case, set(), {})


@pytest.mark.parametrize(
    ("schema_version", "contract_id"),
    [
        (
            "r28-independent-authoring-output-v1",
            "dolved-v4-independent-authoring-output-v1",
        ),
        (
            "r28-independent-authoring-output-v2",
            "dolved-v4-independent-authoring-output-v2",
        ),
        (
            "r28-independent-authoring-output-v1",
            "dolved-v4-independent-authoring-output-v3",
        ),
        (
            "r28-independent-authoring-output-v2",
            "dolved-v4-independent-authoring-output-v3",
        ),
        (
            "r28-independent-authoring-output-v3",
            "dolved-v4-independent-authoring-output-v1",
        ),
        (
            "r28-independent-authoring-output-v3",
            "dolved-v4-independent-authoring-output-v2",
        ),
        (
            "r28-independent-authoring-output-v1",
            "dolved-v4-independent-authoring-output-v2",
        ),
        (
            "r28-independent-authoring-output-v2",
            "dolved-v4-independent-authoring-output-v1",
        ),
    ],
)
def test_rejects_legacy_and_mixed_population_identities(
    schema_version: str, contract_id: str
) -> None:
    population = valid_population()
    population["schema_version"] = schema_version
    population["contract_id"] = contract_id
    with pytest.raises(VALIDATOR.Invalid, match="version mismatch|contract mismatch"):
        validate_population(population)


def test_v2_coverage_contract_preserves_all_36_minima_and_exact_62_6_6_scopes() -> None:
    coverage = coverage_contract()
    assert coverage["minimum_case_counts"] == VALIDATOR.EXPECTED_MINIMUM_CASE_COUNTS
    assert len(coverage["minimum_case_counts"]) == 36
    assert coverage["scope_exact_counts"] == VALIDATOR.EXPECTED_SCOPE_COUNTS
    assert sum(coverage["scope_exact_counts"].values()) == 74
    assert VALIDATOR.validate_coverage_contract(coverage)


def test_actual_62_6_6_population_and_coverage_matrix_pass() -> None:
    coverage = coverage_contract()
    allowed = VALIDATOR.validate_coverage_contract(coverage)
    population = population_with_scope_distribution(62, 6, 6)
    view = {f"documents/{scope}/synthetic.txt": "a" * 64 for scope in VALIDATOR.SCOPES}
    slices = VALIDATOR.validate_population(population, allowed, view)
    VALIDATOR.validate_coverage(
        coverage_matrix(population, slices),
        population["population_id"],
        slices,
        coverage,
    )


def test_actual_60_6_6_population_fails_fixed_case_count() -> None:
    coverage = coverage_contract()
    allowed = VALIDATOR.validate_coverage_contract(coverage)
    population = population_with_scope_distribution(60, 6, 6)
    view = {f"documents/{scope}/synthetic.txt": "a" * 64 for scope in VALIDATOR.SCOPES}
    with pytest.raises(VALIDATOR.Invalid, match="exactly 74"):
        VALIDATOR.validate_population(population, allowed, view)


def test_actual_compensating_61_7_6_distribution_fails_scope_validation() -> None:
    coverage = coverage_contract()
    allowed = VALIDATOR.validate_coverage_contract(coverage)
    population = population_with_scope_distribution(61, 7, 6)
    view = {f"documents/{scope}/synthetic.txt": "a" * 64 for scope in VALIDATOR.SCOPES}
    slices = VALIDATOR.validate_population(population, allowed, view)
    with pytest.raises(VALIDATOR.Invalid, match="scope count failed: scope.primary"):
        VALIDATOR.validate_coverage(
            coverage_matrix(population, slices),
            population["population_id"],
            slices,
            coverage,
        )


@pytest.mark.parametrize(
    ("field", "replacement", "message"),
    [
        (
            "scope_exact_counts",
            {"scope.primary": 60, "scope.foreign_tenant": 6, "scope.security_test": 6},
            "scope",
        ),
        ("utterance_count", 144, "population arithmetic"),
    ],
)
def test_rejects_weaker_v2_counts(
    field: str, replacement: object, message: str
) -> None:
    coverage = coverage_contract()
    coverage[field] = replacement
    with pytest.raises(VALIDATOR.Invalid, match=message):
        VALIDATOR.validate_coverage_contract(coverage)


def test_rejects_changed_or_weakened_minimum() -> None:
    coverage = coverage_contract()
    coverage["minimum_case_counts"]["applicability.inherited"] = 7
    with pytest.raises(VALIDATOR.Invalid, match="approved coverage minima"):
        VALIDATOR.validate_coverage_contract(coverage)


@pytest.mark.parametrize(
    ("schema_version", "contract_id"),
    [
        (
            "r28-authoring-coverage-contract-v1",
            "dolved-v4-independent-authoring-coverage-v1",
        ),
        (
            "r28-authoring-coverage-contract-v1",
            "dolved-v4-independent-authoring-coverage-v2",
        ),
        (
            "r28-authoring-coverage-contract-v2",
            "dolved-v4-independent-authoring-coverage-v1",
        ),
    ],
)
def test_rejects_legacy_and_mixed_coverage_identities(
    schema_version: str, contract_id: str
) -> None:
    coverage = coverage_contract()
    coverage["schema_version"] = schema_version
    coverage["contract_id"] = contract_id
    with pytest.raises(VALIDATOR.Invalid, match="version mismatch|identity mismatch"):
        VALIDATOR.validate_coverage_contract(coverage)


def test_v3_contract_aggregate_recomputes_exactly() -> None:
    ordered = [
        "docs/evaluation/r28-s01/access-manifest.json",
        "contracts/evaluation/v4/independent-authoring-output.schema.json",
        "docs/evaluation/r28-s01/authoring-coverage-contract.json",
        "scripts/evaluation/r28_authoring_access.py",
        "scripts/evaluation/r28_access_guard.py",
        "scripts/evaluation/validate_r28_authoring_output.py",
    ]
    digest = hashlib.sha256()
    for relative in ordered:
        digest.update(relative.encode("utf-8"))
        digest.update(b"\0")
        digest.update((SCRIPT_ROOT / relative).read_bytes())
        digest.update(b"\0")
    assert digest.hexdigest() == (
        "58e4d4b3ebbde74118bbbd287240ef861fea9035aa291642e2be2a97c6ae1624"
    )


def test_rejects_impossible_coverage_arithmetic() -> None:
    impossible = coverage_contract()
    impossible["arithmetic"] = {
        "exclusive_scope_total": 73,
        "largest_mutually_exclusive_outcome_minimum_total": 65,
        "overlap_required": True,
        "feasible": True,
    }
    with pytest.raises(VALIDATOR.Invalid, match="coverage arithmetic record mismatch"):
        VALIDATOR.validate_coverage_contract(impossible)


def test_accepts_valid_coverage_arithmetic() -> None:
    assert VALIDATOR.validate_coverage_contract(coverage_contract())


def test_accepts_only_one_direct_well_named_output_child() -> None:
    run = fresh_run()
    try:
        name, files = VALIDATOR.read_confined_output(
            Path("/tmp") / "dolved-r28-v4-authoring" / run.name
        )
        assert name == run.name
        assert set(files) == VALIDATOR.OUTPUT_FILES
    finally:
        shutil.rmtree(run)


def test_rejects_wrong_parent_nested_and_malformed_run_name(tmp_path: Path) -> None:
    wrong = tmp_path / "AUTHOR-V4-20260903-ABCDEFGH"
    wrong.mkdir()
    with pytest.raises(VALIDATOR.Invalid, match="exact lexical"):
        VALIDATOR.read_confined_output(wrong)

    parent = output_parent()
    nested_parent = parent / "nested"
    nested_parent.mkdir(exist_ok=True)
    nested = nested_parent / "AUTHOR-V4-20260903-ABCDEFGH"
    nested.mkdir(exist_ok=True)
    try:
        with pytest.raises(VALIDATOR.Invalid, match="exact lexical"):
            VALIDATOR.read_confined_output(nested)
    finally:
        shutil.rmtree(nested_parent)

    malformed = parent / "AUTHOR-V4-2026-abcdefgh"
    malformed.mkdir(exist_ok=True)
    try:
        with pytest.raises(VALIDATOR.Invalid, match="run-directory name"):
            VALIDATOR.read_confined_output(malformed)
    finally:
        malformed.rmdir()

    invalid_date = parent / "AUTHOR-V4-20260230-ABCDEFGH"
    invalid_date.mkdir()
    try:
        with pytest.raises(VALIDATOR.Invalid, match="calendar date"):
            VALIDATOR.read_confined_output(invalid_date)
    finally:
        invalid_date.rmdir()


def test_rejects_alternate_symlinked_and_prefix_confusable_parent(
    tmp_path: Path,
) -> None:
    run = fresh_run()
    alias = tmp_path / "alias"
    alias.symlink_to(output_parent(), target_is_directory=True)
    try:
        with pytest.raises(VALIDATOR.Invalid, match="exact lexical"):
            VALIDATOR.read_confined_output(alias / run.name)
        confusing = Path("/tmp/dolved-r28-v4-authoring-evil") / run.name
        with pytest.raises(VALIDATOR.Invalid, match="exact lexical"):
            VALIDATOR.read_confined_output(confusing)
        for lexical_alias in (
            f"/private/tmp/dolved-r28-v4-authoring/{run.name}",
            f"/tmp//dolved-r28-v4-authoring/{run.name}",
        ):
            with pytest.raises(VALIDATOR.Invalid, match="exact lexical"):
                VALIDATOR.read_confined_output(
                    Path(lexical_alias), lexical_output=lexical_alias
                )
    finally:
        shutil.rmtree(run)


def test_rejects_candidate_controlled_parent_symlink(
    monkeypatch, tmp_path: Path
) -> None:
    candidate = tmp_path / "candidate-parent"
    target = tmp_path / "target-parent"
    target.mkdir()
    candidate.symlink_to(target, target_is_directory=True)
    monkeypatch.setattr(VALIDATOR, "PERMITTED_PARENT", candidate)
    with pytest.raises(VALIDATOR.Invalid, match="candidate-controlled link"):
        VALIDATOR.canonical_parent()


def test_rejects_symlinked_run_directory(tmp_path: Path) -> None:
    target = tmp_path / "target"
    target.mkdir()
    link = output_parent() / f"AUTHOR-V4-20260903-{uuid.uuid4().hex[:8].upper()}"
    link.symlink_to(target, target_is_directory=True)
    try:
        with pytest.raises(VALIDATOR.Invalid, match="genuine directory"):
            VALIDATOR.read_confined_output(link)
    finally:
        link.unlink()


def test_rejects_check_open_inode_substitution(monkeypatch) -> None:
    run = fresh_run()
    real_open = VALIDATOR.os.open
    replaced = False

    def substituting_open(path, flags, *args, **kwargs):
        nonlocal replaced
        if path == "population.json" and not replaced:
            replaced = True
            (run / "population.json").unlink()
            (run / "population.json").write_bytes(b"replacement")
        return real_open(path, flags, *args, **kwargs)

    monkeypatch.setattr(VALIDATOR.os, "open", substituting_open)
    try:
        with pytest.raises(VALIDATOR.Invalid, match="changed during open"):
            VALIDATOR.read_confined_output(run)
    finally:
        shutil.rmtree(run)


def test_rejects_final_inventory_mutation(monkeypatch) -> None:
    run = fresh_run()
    real_listdir = VALIDATOR.os.listdir
    calls = 0

    def mutating_listdir(path):
        nonlocal calls
        calls += 1
        names = real_listdir(path)
        if calls == 2:
            return [*names, "late-extra.json"]
        return names

    monkeypatch.setattr(VALIDATOR.os, "listdir", mutating_listdir)
    try:
        with pytest.raises(VALIDATOR.Invalid, match="changed during validation"):
            VALIDATOR.read_confined_output(run)
    finally:
        shutil.rmtree(run)


@pytest.mark.parametrize("kind", ["symlink", "fifo", "directory", "socket", "hardlink"])
def test_rejects_linked_or_non_regular_required_file(
    kind: str, tmp_path: Path, monkeypatch
) -> None:
    run = fresh_run()
    entry = run / "population.json"
    entry.unlink()
    try:
        if kind == "symlink":
            target = tmp_path / "target.json"
            target.write_text("{}")
            entry.symlink_to(target)
        elif kind == "fifo":
            os.mkfifo(entry)
        elif kind == "directory":
            entry.mkdir()
        elif kind == "socket":
            entry.write_bytes(b"synthetic socket stand-in")
            real_stat = VALIDATOR.os.stat

            def socket_stat(path, *args, **kwargs):
                result = real_stat(path, *args, **kwargs)
                if path == "population.json" and kwargs.get("follow_symlinks") is False:
                    values = list(result)
                    values[0] = stat.S_IFSOCK | 0o600
                    return os.stat_result(values)
                return result

            monkeypatch.setattr(VALIDATOR.os, "stat", socket_stat)
        else:
            target = tmp_path / "target.json"
            target.write_text("{}")
            os.link(target, entry)
        with pytest.raises(VALIDATOR.Invalid, match="ordinary non-linked regular file"):
            VALIDATOR.read_confined_output(run)
    finally:
        shutil.rmtree(run)


@pytest.mark.parametrize("mutation", ["additional", "missing", "subdirectory"])
def test_rejects_wrong_output_inventory(mutation: str) -> None:
    run = fresh_run()
    try:
        if mutation == "additional":
            (run / "extra.json").write_text("{}")
        elif mutation == "missing":
            (run / "population.json").unlink()
        else:
            (run / "unexpected").mkdir()
        with pytest.raises(VALIDATOR.Invalid, match="filenames"):
            VALIDATOR.read_confined_output(run)
    finally:
        shutil.rmtree(run)


def test_rejects_unexpected_checksum_path_and_mismatched_bytes() -> None:
    digest = "a" * 64
    with pytest.raises(VALIDATOR.Invalid, match="unexpected checksum path"):
        VALIDATOR.parse_checksums(f"{digest}  extra.json\n".encode())
    files = {name: name.encode() for name in VALIDATOR.CHECKSUM_FILES}
    files["checksums.sha256"] = checksum_bytes(files).replace(
        hashlib.sha256(files["population.json"]).hexdigest().encode(), b"0" * 64
    )
    with pytest.raises(VALIDATOR.Invalid, match="checksum mismatch: population.json"):
        VALIDATOR.validate_checksums(files)


@pytest.mark.parametrize(
    "path",
    [
        "../population.json",
        "/tmp/population.json",
        r"folder\population.json",
        "checksums.sha256",
    ],
)
def test_rejects_unsafe_ambiguous_and_self_referential_checksums(path: str) -> None:
    with pytest.raises(VALIDATOR.Invalid, match="malformed|unexpected"):
        VALIDATOR.parse_checksums(f"{'a' * 64}  {path}\n".encode())


def test_schema_rejects_invalid_datetime_location_and_unknown_fields() -> None:
    population = valid_population()
    population["author_provenance"]["authored_at_utc"] = "not-a-date"
    with pytest.raises(VALIDATOR.Invalid, match="JSON Schema.*authored_at_utc"):
        VALIDATOR.validate_population_schema(population, schema())

    population = valid_population()
    population["cases"][0]["context"]["location_id"] = "x" * 161
    with pytest.raises(VALIDATOR.Invalid, match="JSON Schema.*location_id"):
        VALIDATOR.validate_population_schema(population, schema())

    population = valid_population()
    population["unexpected"] = True
    with pytest.raises(VALIDATOR.Invalid, match="JSON Schema"):
        VALIDATOR.validate_population_schema(population, schema())


@pytest.mark.parametrize("length", [133, 200])
def test_requester_role_compatibility_lengths_pass(length: int) -> None:
    population = valid_population()
    population["cases"][0]["context"]["requester_role"] = "r" * length
    VALIDATOR.validate_population_schema(population, schema())
    validate_population(population)


def test_requester_role_above_v3_bound_fails_schema_and_validator() -> None:
    population = valid_population()
    population["cases"][0]["context"]["requester_role"] = "r" * 201
    with pytest.raises(VALIDATOR.Invalid, match="JSON Schema.*requester_role"):
        VALIDATOR.validate_population_schema(population, schema())
    with pytest.raises(VALIDATOR.Invalid, match="requester role is out of bounds"):
        validate_population(population)


@pytest.mark.parametrize("length", [1175, 2000])
def test_evidence_quotation_compatibility_lengths_pass(length: int) -> None:
    population = valid_population()
    population["cases"][0]["expected_evidence"][0]["quotation"] = "q" * length
    VALIDATOR.validate_population_schema(population, schema())
    validate_population(population)


def test_evidence_quotation_above_v3_bound_fails_schema_and_validator() -> None:
    population = valid_population()
    population["cases"][0]["expected_evidence"][0]["quotation"] = "q" * 2001
    with pytest.raises(VALIDATOR.Invalid, match="JSON Schema.*quotation"):
        VALIDATOR.validate_population_schema(population, schema())
    with pytest.raises(VALIDATOR.Invalid, match="evidence quotation is out of bounds"):
        validate_population(population)


def test_question_utterance_bound_remains_500() -> None:
    population = valid_population()
    population["cases"][0]["variants"][0]["utterance"] = "u" * 500
    VALIDATOR.validate_population_schema(population, schema())
    validate_population(population)

    population["cases"][0]["variants"][0]["utterance"] = "u" * 501
    with pytest.raises(VALIDATOR.Invalid, match="JSON Schema.*utterance"):
        VALIDATOR.validate_population_schema(population, schema())
    with pytest.raises(VALIDATOR.Invalid, match="utterance is out of bounds"):
        validate_population(population)


@pytest.mark.parametrize(
    ("temporal_mode", "as_of_date", "accepted"),
    [
        ("CURRENT", None, True),
        ("CURRENT", "2026-01-01", False),
        ("CURRENT", "2026-02-30", False),
        ("VALID_AT_DATE", None, False),
        ("VALID_AT_DATE", "2026-01-01", True),
        ("VALID_AT_DATE", "2026-02-30", False),
        ("COMPARE", None, True),
        ("COMPARE", "2026-01-01", False),
        ("COMPARE", "2026-02-30", False),
        ("HISTORICAL_REFERENCE", None, True),
        ("HISTORICAL_REFERENCE", "2026-01-01", True),
        ("HISTORICAL_REFERENCE", "2026-02-30", False),
        ("CLARIFICATION_REQUIRED", None, True),
        ("CLARIFICATION_REQUIRED", "2026-01-01", True),
        ("CLARIFICATION_REQUIRED", "2026-02-30", False),
    ],
)
def test_temporal_mode_date_matrix(
    temporal_mode: str, as_of_date: str | None, accepted: bool
) -> None:
    population = valid_population()
    population["cases"][0]["context"].update(
        {"temporal_mode": temporal_mode, "as_of_date": as_of_date}
    )
    if accepted:
        if temporal_mode == "COMPARE":
            comparison_evidence = population["cases"][0]["expected_evidence"][0].copy()
            comparison_evidence.update(
                {"evidence_id": "ev-synthetic-000-comparison", "side": "COMPARISON"}
            )
            population["cases"][0]["expected_evidence"].append(comparison_evidence)
        VALIDATOR.validate_population_schema(population, schema())
        validate_population(population)
        return
    with pytest.raises(VALIDATOR.Invalid):
        VALIDATOR.validate_population_schema(population, schema())
    with pytest.raises(VALIDATOR.Invalid):
        validate_population(population)


@pytest.mark.parametrize(
    "timestamp", ["2026-09-03T00:00:00", "2026-09-03T01:00:00+01:00"]
)
def test_rejects_missing_or_non_utc_timestamp(timestamp: str) -> None:
    population = valid_population()
    population["author_provenance"]["authored_at_utc"] = timestamp
    with pytest.raises(VALIDATOR.Invalid, match="JSON Schema|explicit UTC"):
        VALIDATOR.validate_population_schema(population, schema())
        validate_population(population)


@pytest.mark.parametrize(
    "duplicate",
    ["SYNTHETIC WORDING A 0", "  Synthetic\twording   A 0  ", "Synthetic wording A 0"],
)
def test_rejects_global_normalized_utterance_duplicates(duplicate: str) -> None:
    population = valid_population()
    population["cases"][1]["variants"][0]["utterance"] = duplicate
    with pytest.raises(VALIDATOR.Invalid, match="globally distinct"):
        validate_population(population)


def test_exactly_74_cases_and_148_globally_distinct_utterances_pass() -> None:
    population = valid_population()
    slices = VALIDATOR.validate_population(
        population,
        {"scope.primary", "outcome.EVIDENCE_FOUND"},
        {"documents/primary/synthetic.txt": "a" * 64},
    )
    assert len(population["cases"]) == 74
    assert sum(len(case["variants"]) for case in population["cases"]) == 148
    assert slices["scope.primary"] == {case["case_id"] for case in population["cases"]}


def test_rejects_global_duplicate_after_unicode_nfc() -> None:
    population = valid_population()
    population["cases"][0]["variants"][0]["utterance"] = "Caf\u00e9 synthetic"
    population["cases"][1]["variants"][0]["utterance"] = "Cafe\u0301 synthetic"
    with pytest.raises(VALIDATOR.Invalid, match="globally distinct"):
        validate_population(population)


def test_rejects_duplicate_case_id() -> None:
    population = valid_population()
    population["cases"][1]["case_id"] = population["cases"][0]["case_id"]
    with pytest.raises(VALIDATOR.Invalid, match="duplicate case ID"):
        validate_population(population)


@pytest.mark.parametrize(
    ("variants", "message"),
    [
        (
            [
                {"variant_id": "v2", "utterance": "One"},
                {"variant_id": "v2", "utterance": "Two"},
            ],
            "variant IDs",
        ),
        (
            [
                {"variant_id": "v1", "utterance": "One"},
                {"variant_id": "v1", "utterance": "Two"},
            ],
            "variant IDs",
        ),
        (
            [
                {"variant_id": "v1", "utterance": "Same"},
                {"variant_id": "v2", "utterance": "same"},
            ],
            "globally distinct",
        ),
        ([{"variant_id": "v1", "utterance": "One"}], "exactly two variants"),
        (
            [
                {"variant_id": "v1", "utterance": "One"},
                {"variant_id": "v2", "utterance": "Two"},
                {"variant_id": "v2", "utterance": "Three"},
            ],
            "exactly two variants",
        ),
    ],
)
def test_rejects_invalid_variant_population(variants: list[dict], message: str) -> None:
    population = valid_population()
    population["cases"][0]["variants"] = variants
    with pytest.raises(VALIDATOR.Invalid, match=message):
        validate_population(population)


def test_rejects_unknown_enum_absent_view_path_and_source_hash_mismatch() -> None:
    population = valid_population()
    population["cases"][0]["expected_outcome"]["retrieval"] = "UNKNOWN"
    with pytest.raises(VALIDATOR.Invalid, match="invalid expected outcome"):
        validate_population(population)

    population = valid_population()
    population["cases"][0]["expected_evidence"][0]["restricted_view_path"] = (
        "documents/primary/absent.txt"
    )
    with pytest.raises(VALIDATOR.Invalid, match="absent from restricted view"):
        validate_population(population)

    population = valid_population()
    population["cases"][0]["expected_evidence"][0]["source_sha256"] = "b" * 64
    with pytest.raises(VALIDATOR.Invalid, match="source hash mismatch"):
        validate_population(population)


def valid_declaration(run_name: str) -> dict:
    return {
        "schema_version": VALIDATOR.DECLARATION_VERSION,
        "contract_id": VALIDATOR.CONTRACT_ID,
        "population_id": "dolved-v4-independent-synthetic",
        "authoring_run_id": run_name,
        "accessed_input_paths": sorted(
            VALIDATOR.REQUIRED_EXTERNAL_INPUTS
            | VALIDATOR.OPTIONAL_EXTERNAL_INPUTS
            | VALIDATOR.REQUIRED_VIEW_MEMBERS
        ),
        "repository_unchanged": True,
        "system_output_seen": False,
        "contamination_detected": False,
        "declaration": "Synthetic content-free declaration for structural validation only.",
    }


def test_access_declaration_accepts_every_neutral_input_and_exact_view_member() -> None:
    run_name = "AUTHOR-V4-20260903-ABCDEFGH"
    population = valid_population()
    VALIDATOR.validate_declaration(
        valid_declaration(run_name),
        population,
        run_name,
        {f"{VALIDATOR.VIEW_ID}/manifest.json"},
        access_manifest(),
    )


@pytest.mark.parametrize(
    "path",
    [
        "tests/evaluation/hybrid/v1/live-calibration-input.json",
        "tests/evaluation/held-out/cases.json",
        "docs/evaluation/runs/example/result.json",
        "tests/evaluation/unknown.json",
        "docs/evaluation",
        "./contracts/evaluation/v4/independent-authoring-output.schema.json",
    ],
)
def test_rejects_undeclared_alias_prohibited_and_uncertain_inputs(path: str) -> None:
    run_name = "AUTHOR-V4-20260903-ABCDEFGH"
    declaration = valid_declaration(run_name)
    declaration["accessed_input_paths"].append(path)
    with pytest.raises(VALIDATOR.Invalid, match="unsafe|allowlist"):
        VALIDATOR.validate_declaration(
            declaration,
            valid_population(),
            run_name,
            {f"{VALIDATOR.VIEW_ID}/manifest.json"},
            access_manifest(),
        )


def test_rejects_missing_required_input_run_mismatch_and_incomplete_declaration() -> (
    None
):
    run_name = "AUTHOR-V4-20260903-ABCDEFGH"
    declaration = valid_declaration(run_name)
    declaration["accessed_input_paths"].remove(
        next(iter(VALIDATOR.REQUIRED_EXTERNAL_INPUTS))
    )
    with pytest.raises(VALIDATOR.Invalid, match="required neutral input"):
        VALIDATOR.validate_declaration(
            declaration,
            valid_population(),
            run_name,
            {f"{VALIDATOR.VIEW_ID}/manifest.json"},
            access_manifest(),
        )

    declaration = valid_declaration(run_name)
    with pytest.raises(VALIDATOR.Invalid, match="output directory name"):
        VALIDATOR.validate_declaration(
            declaration,
            valid_population(),
            "AUTHOR-V4-20260903-ZZZZZZZZ",
            {f"{VALIDATOR.VIEW_ID}/manifest.json"},
            access_manifest(),
        )

    declaration = valid_declaration(run_name)
    declaration.pop("declaration")
    with pytest.raises(VALIDATOR.Invalid, match="fields mismatch"):
        VALIDATOR.validate_declaration(
            declaration,
            valid_population(),
            run_name,
            {f"{VALIDATOR.VIEW_ID}/manifest.json"},
            access_manifest(),
        )
