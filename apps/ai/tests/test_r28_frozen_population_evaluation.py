from __future__ import annotations

import copy
import importlib.util
import json
import shutil
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[3]
SCRIPT = ROOT / "scripts/evaluation/verify_r28_v4_population.py"
SPEC = importlib.util.spec_from_file_location("verify_r28_v4_population", SCRIPT)
assert SPEC and SPEC.loader
VERIFY = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(VERIFY)
SOURCE = ROOT / "tests/evaluation/engineering-populations/dolved-care-v4/v2"


def frozen_copy(tmp_path: Path) -> tuple[Path, dict]:
    destination = tmp_path / "v2"
    shutil.copytree(SOURCE, destination)
    manifest = json.loads((destination / "freeze-manifest.json").read_text())
    return destination, manifest


def test_canonical_frozen_candidate_accepts() -> None:
    manifest = json.loads((SOURCE / "freeze-manifest.json").read_text())
    files = VERIFY.load_and_verify_candidate(SOURCE, manifest)
    VERIFY.validate_v3(files, manifest)


def test_r28_s04_access_binding_accepts_only_exact_population() -> None:
    access = json.loads(VERIFY.ACCESS.read_text())
    VERIFY.validate_access_binding(access)
    changed = copy.deepcopy(access)
    changed["sole_authorised_population"]["identity"] = "r28-s02-substitution"
    with pytest.raises(ValueError, match="access binding drift"):
        VERIFY.validate_access_binding(changed)


def test_r28_s04_access_binding_rejects_v1() -> None:
    access = json.loads(VERIFY.ACCESS.read_text())
    access["sole_authorised_population"] = {
        "path": "tests/evaluation/engineering-populations/dolved-care-v4/v1",
        "identity": VERIFY.HISTORICAL_V1_IDENTITY,
        "digest": VERIFY.HISTORICAL_V1_DIGEST,
        "immutable": True,
    }
    with pytest.raises(ValueError, match="access binding drift"):
        VERIFY.validate_access_binding(access)


def test_r28_s04_access_binding_rejects_missing_v2_lineage() -> None:
    access = json.loads(VERIFY.ACCESS.read_text())
    del access["compatibility_correction"]
    with pytest.raises(KeyError, match="compatibility_correction"):
        VERIFY.validate_access_binding(access)


@pytest.mark.parametrize(
    ("path", "replacement", "message"),
    [
        (("freeze", "immutable"), False, "freeze metadata drift"),
        (("freeze", "correction_rule"), "changed", "freeze metadata drift"),
        (("freeze", "replacement_rule"), "changed", "freeze metadata drift"),
        (
            ("frozen_population", "repository_path"),
            "elsewhere",
            "repository path drift",
        ),
        (("provider_execution_authorised",), True, "authorised provider execution"),
        (("counts", "semantic_cases"), 73, "semantic case count drift"),
        (("counts", "utterances"), 147, "utterance count drift"),
        (("counts", "scopes", "primary"), 61, "scope counts drift"),
        (("counts", "scopes", "foreign_tenant"), 5, "scope counts drift"),
        (("counts", "scopes", "security_test"), 5, "scope counts drift"),
        (
            ("compatibility_correction", "candidate_identity"),
            "dolved-care-v4-evaluation-population-v1",
            "compatibility lineage drift",
        ),
        (
            ("compatibility_correction", "parent_population_digest"),
            "mixed",
            "compatibility lineage drift",
        ),
    ],
)
def test_frozen_manifest_metadata_fails_closed(
    path: tuple[str, ...], replacement: object, message: str
) -> None:
    manifest = json.loads((SOURCE / "freeze-manifest.json").read_text())
    target = manifest
    for key in path[:-1]:
        target = target[key]
    target[path[-1]] = replacement
    with pytest.raises(ValueError, match=message):
        VERIFY.validate_manifest_metadata(manifest)


@pytest.mark.parametrize(
    ("path", "replacement", "message"),
    [
        (("sole_authorised_population", "path"), "elsewhere", "access binding drift"),
        (("sole_authorised_population", "identity"), "changed", "access binding drift"),
        (("sole_authorised_population", "digest"), "changed", "access binding drift"),
        (("sole_authorised_population", "immutable"), False, "access binding drift"),
        (("provider_execution_authorised",), True, "authorised provider execution"),
        (("correction_rule",), "changed", "access correction rule drift"),
    ],
)
def test_access_metadata_fails_closed(
    path: tuple[str, ...], replacement: object, message: str
) -> None:
    access = json.loads(VERIFY.ACCESS.read_text())
    target = access
    for key in path[:-1]:
        target = target[key]
    target[path[-1]] = replacement
    with pytest.raises(ValueError, match=message):
        VERIFY.validate_access_binding(access)


def test_routing_and_ceiling_arithmetic_is_closed_and_exact() -> None:
    access = json.loads(VERIFY.ACCESS.read_text())
    VERIFY.validate_execution_protocol(access["execution_protocol"])


@pytest.mark.parametrize(
    ("section", "field"),
    [
        ("routing", "retrieval_utterances"),
        ("routing", "maximum_reranking_utterances"),
        ("routing", "deterministic_termination_utterances"),
        ("ceilings", "query_embedding_items"),
        ("ceilings", "reranker_base_http_requests"),
        ("ceilings", "total_base_provider_requests"),
        ("ceilings", "total_input_tokens"),
        ("ceilings", "total_usd"),
    ],
)
def test_execution_protocol_mutations_fail_closed(section: str, field: str) -> None:
    access = json.loads(VERIFY.ACCESS.read_text())
    changed = copy.deepcopy(access["execution_protocol"])
    changed[section][field] += 1
    with pytest.raises(
        ValueError, match="execution routing drift|execution ceilings drift"
    ):
        VERIFY.validate_execution_protocol(changed)


def test_frozen_candidate_rejects_tampering(tmp_path: Path) -> None:
    destination, manifest = frozen_copy(tmp_path)
    with (destination / "population.json").open("ab") as stream:
        stream.write(b"\n")
    with pytest.raises(ValueError, match="candidate hash mismatch"):
        VERIFY.load_and_verify_candidate(destination, manifest)


def test_frozen_candidate_rejects_substitution(tmp_path: Path) -> None:
    destination, manifest = frozen_copy(tmp_path)
    (destination / "population.json").write_bytes(
        (destination / "coverage-matrix.json").read_bytes()
    )
    with pytest.raises(ValueError, match="candidate hash mismatch"):
        VERIFY.load_and_verify_candidate(destination, manifest)


def test_frozen_candidate_rejects_missing_file(tmp_path: Path) -> None:
    destination, manifest = frozen_copy(tmp_path)
    (destination / "authoring-report.md").unlink()
    with pytest.raises(ValueError, match="inventory mismatch"):
        VERIFY.load_and_verify_candidate(destination, manifest)


@pytest.mark.parametrize("field", ["identity", "digest", "digest_algorithm"])
def test_frozen_candidate_rejects_identity_drift(tmp_path: Path, field: str) -> None:
    destination, manifest = frozen_copy(tmp_path)
    changed = copy.deepcopy(manifest)
    changed["frozen_population"][field] = "drifted"
    with pytest.raises(ValueError, match="identity drift|digest drift|algorithm drift"):
        VERIFY.load_and_verify_candidate(destination, changed)


def comparison_fixture() -> tuple[dict, dict[str, dict]]:
    population = json.loads((SOURCE / "population.json").read_text())
    metadata = VERIFY.view_document_metadata(
        ROOT
        / "tests/evaluation/authoring-views/dolved-care-v4/v1/question-author-view.tar.gz"
    )
    return population, metadata


def test_comparison_authority_accepts_current_primary_and_historical_side() -> None:
    population, metadata = comparison_fixture()
    VERIFY.validate_comparison_authority(population, metadata)


def test_comparison_authority_does_not_depend_on_evidence_array_position() -> None:
    population, metadata = comparison_fixture()
    for case in population["cases"]:
        if case["context"]["temporal_mode"] == "COMPARE":
            case["expected_evidence"].reverse()
    VERIFY.validate_comparison_authority(population, metadata)


def test_comparison_authority_rejects_historical_primary() -> None:
    population, metadata = comparison_fixture()
    case = next(
        case
        for case in population["cases"]
        if case["case_id"] == "v4.case.corrected-b01-02"
    )
    historical = next(
        evidence
        for evidence in case["expected_evidence"]
        if evidence["side"] == "COMPARISON"
    )
    historical["side"] = "PRIMARY"
    with pytest.raises(ValueError, match="PRIMARY is historical"):
        VERIFY.validate_comparison_authority(population, metadata)


def test_comparison_authority_rejects_current_comparison() -> None:
    population, metadata = comparison_fixture()
    case = next(
        case
        for case in population["cases"]
        if case["case_id"] == "v4.case.corrected-b01-02"
    )
    current = next(
        evidence
        for evidence in case["expected_evidence"]
        if evidence["side"] == "PRIMARY"
    )
    current["side"] = "COMPARISON"
    with pytest.raises(ValueError, match="COMPARISON is current"):
        VERIFY.validate_comparison_authority(population, metadata)


def test_comparison_authority_rejects_scheduled_future_evidence() -> None:
    population, metadata = comparison_fixture()
    changed = copy.deepcopy(metadata)
    case = next(
        case
        for case in population["cases"]
        if case["case_id"] == "v4.case.corrected-b01-02"
    )
    primary = next(
        evidence
        for evidence in case["expected_evidence"]
        if evidence["side"] == "PRIMARY"
    )
    changed[primary["restricted_view_path"]]["effective_date"] = "2027-01-01"
    with pytest.raises(ValueError, match="PRIMARY is scheduled_future"):
        VERIFY.validate_comparison_authority(population, changed)


def test_v1_v2_delta_is_exact_and_rejects_unreviewed_change() -> None:
    population = json.loads((SOURCE / "population.json").read_text())
    VERIFY.validate_v1_delta(population)
    population["cases"][0]["rationale"] += " changed"
    with pytest.raises(ValueError, match="unexpected non-side V2 delta"):
        VERIFY.validate_v1_delta(population)


def test_v2_manifest_rejects_missing_compatibility_lineage() -> None:
    manifest = json.loads((SOURCE / "freeze-manifest.json").read_text())
    del manifest["compatibility_correction"]
    with pytest.raises(KeyError, match="compatibility_correction"):
        VERIFY.validate_manifest_metadata(manifest)


def test_v2_manifest_rejects_mixed_v1_candidate_checksum() -> None:
    destination_manifest = json.loads((SOURCE / "freeze-manifest.json").read_text())
    destination_manifest["candidate_files"]["population.json"] = (
        "f8236c1f8cac3cc8661751f305339f72c1a63bf4d893fb006149debf3188a620"
    )
    with pytest.raises(ValueError, match="candidate hash mismatch"):
        VERIFY.load_and_verify_candidate(SOURCE, destination_manifest)
