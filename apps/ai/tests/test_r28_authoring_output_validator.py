from __future__ import annotations

import hashlib
import importlib.util
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
        "cases": [valid_case(index) for index in range(72)],
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
    with pytest.raises(VALIDATOR.Invalid, match="exactly 72"):
        VALIDATOR.validate_population(population, set(), {})

    assert coverage["scope_exact_counts"] == {}


def test_rejects_impossible_coverage_arithmetic() -> None:
    impossible = {
        "schema_version": "r28-authoring-coverage-contract-v1",
        "contract_id": "dolved-v4-independent-authoring-coverage-v1",
        "semantic_case_count": 72,
        "variants_per_case": 2,
        "utterance_count": 144,
        "scope_exact_counts": {"scope.primary": 72, "scope.foreign_tenant": 1},
        "minimum_case_counts": {
            "outcome.EVIDENCE_FOUND": 72,
            "outcome.CLARIFICATION_REQUIRED": 5,
            "safety.cross_tenant": 5,
            "safety.prompt_injection": 5,
        },
        "arithmetic": {},
    }
    with pytest.raises(VALIDATOR.Invalid, match="scope counts"):
        VALIDATOR.validate_coverage_contract(impossible)


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


def test_exactly_144_globally_distinct_utterances_pass() -> None:
    population = valid_population()
    slices = VALIDATOR.validate_population(
        population,
        {"scope.primary", "outcome.EVIDENCE_FOUND"},
        {"documents/primary/synthetic.txt": "a" * 64},
    )
    assert len(population["cases"]) == 72
    assert sum(len(case["variants"]) for case in population["cases"]) == 144
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
