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
SOURCE = ROOT / "tests/evaluation/engineering-populations/dolved-care-v4/v1"


def frozen_copy(tmp_path: Path) -> tuple[Path, dict]:
    destination = tmp_path / "v1"
    shutil.copytree(SOURCE, destination)
    manifest = json.loads((destination / "freeze-manifest.json").read_text())
    return destination, manifest


def test_canonical_frozen_candidate_accepts() -> None:
    manifest = json.loads((SOURCE / "freeze-manifest.json").read_text())
    VERIFY.load_and_verify_candidate(SOURCE, manifest)


def test_r28_s04_access_binding_accepts_only_exact_population() -> None:
    access = json.loads(VERIFY.ACCESS.read_text())
    VERIFY.validate_access_binding(access)
    changed = copy.deepcopy(access)
    changed["sole_authorised_population"]["identity"] = "r28-s02-substitution"
    with pytest.raises(ValueError, match="access binding drift"):
        VERIFY.validate_access_binding(changed)


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
