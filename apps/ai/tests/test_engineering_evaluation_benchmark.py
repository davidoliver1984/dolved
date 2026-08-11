import hashlib
import json
from datetime import datetime
from pathlib import Path
from typing import Any

import jsonschema

BENCHMARK_ROOT = Path("/evaluation/benchmarks/dolved-care-engineering/v1")
CONTRACT_ROOT = Path("/contracts/evaluation/v2")
FORBIDDEN_GROUND_TRUTH_KEYS = {
    "chunk_id",
    "extracted_element_id",
    "normalised_element_id",
    "source_element_id",
}


def load_json(path: Path) -> Any:
    return json.loads(path.read_text())


def canonical_bytes(value: Any) -> bytes:
    return json.dumps(
        value,
        allow_nan=False,
        ensure_ascii=False,
        separators=(",", ":"),
        sort_keys=True,
    ).encode()


def keys_in(value: Any) -> set[str]:
    if isinstance(value, dict):
        return set(value).union(*(keys_in(item) for item in value.values()))
    if isinstance(value, list):
        return set().union(*(keys_in(item) for item in value))
    return set()


def _cases_grouped_by_cluster(
    corpus: dict[str, Any],
) -> dict[str, list[dict[str, Any]]]:
    clusters: dict[str, list[dict[str, Any]]] = {}
    for case in corpus["cases"]:
        clusters.setdefault(case["cluster_id"], []).append(case)
    return clusters


def test_v2_benchmark_artifacts_validate_against_shared_contracts() -> None:
    pairs = (
        ("manifest.json", "benchmark-manifest.schema.json"),
        ("organisation.json", "organisation.schema.json"),
        ("document-catalog.json", "document-catalog.schema.json"),
        ("splits/v1.json", "split.schema.json"),
        ("compiled/corpus.json", "corpus.schema.json"),
    )
    for value_name, schema_name in pairs:
        value = load_json(BENCHMARK_ROOT / value_name)
        schema = load_json(CONTRACT_ROOT / schema_name)
        jsonschema.Draft202012Validator.check_schema(schema)
        jsonschema.Draft202012Validator(
            schema, format_checker=jsonschema.FormatChecker()
        ).validate(value)


def test_catalogue_and_authored_counts_are_truthful() -> None:
    catalog = load_json(BENCHMARK_ROOT / "document-catalog.json")
    manifest = load_json(BENCHMARK_ROOT / "manifest.json")
    versions = [
        version for family in catalog["families"] for version in family["versions"]
    ]
    pilot_versions = [version for version in versions if version["pilot"]]
    pilot_families = {
        family["family_id"]
        for family in catalog["families"]
        if any(version["pilot"] for version in family["versions"])
    }
    corpus = load_json(BENCHMARK_ROOT / "compiled/corpus.json")

    assert len(catalog["families"]) == manifest["planned_counts"]["document_families"]
    assert len(versions) == manifest["planned_counts"]["document_versions"]
    assert len(pilot_families) == manifest["pilot_counts"]["document_families"]
    assert len(pilot_versions) == manifest["pilot_counts"]["document_versions"]
    authored_versions = [version for version in versions if version["source_path"]]
    authored_families = {
        family["family_id"]
        for family in catalog["families"]
        if any(version["source_path"] for version in family["versions"])
    }
    assert len(authored_families) == manifest["authored_counts"]["document_families"]
    assert len(authored_versions) == manifest["authored_counts"]["document_versions"]
    assert len(corpus["cases"]) == manifest["authored_counts"]["semantic_cases"]


def test_every_evidence_unit_is_source_anchored_without_pipeline_ids() -> None:
    corpus = load_json(BENCHMARK_ROOT / "compiled/corpus.json")
    assert not FORBIDDEN_GROUND_TRUTH_KEYS.intersection(keys_in(corpus))

    for case in corpus["cases"]:
        assert 3 <= len(case["variants"]) <= 5
        for evidence in case["retrieval_expectation"]["evidence_units"]:
            source = (BENCHMARK_ROOT / evidence["source_path"]).read_text()
            for excerpt in evidence["canonical_excerpts"]:
                assert excerpt in source


def test_complete_split_meets_targets_without_splitting_clusters() -> None:
    split = load_json(BENCHMARK_ROOT / "splits/v1.json")
    corpus = load_json(BENCHMARK_ROOT / "compiled/corpus.json")
    case_ids = {case["case_id"] for case in corpus["cases"]}

    assigned_cases = set().union(
        split["assignments"]["engineering_tuning"],
        split["assignments"]["threshold_calibration"],
        split["assignments"]["sealed_held_out"],
    )

    assert split["assignment_status"] == "COMPLETE"
    assert assigned_cases == case_ids
    assert split["assignments"]["unassigned"] == []
    for split_name, expected_count in split["targets"].items():
        assert len(split["assignments"][split_name]) == expected_count

    split_for_case = {
        case_id: split_name
        for split_name in (
            "engineering_tuning",
            "threshold_calibration",
            "sealed_held_out",
        )
        for case_id in split["assignments"][split_name]
    }
    for cases in _cases_grouped_by_cluster(corpus).values():
        assert len({split_for_case[case["case_id"]] for case in cases}) == 1
    assert split["targets"] == {
        "engineering_tuning": 42,
        "threshold_calibration": 28,
        "sealed_held_out": 22,
    }


def test_fixed_clock_authority_windows_cover_adr_0017_edge_cases() -> None:
    authority = load_json(BENCHMARK_ROOT / "compiled/authority-windows.json")
    assert authority["evaluation_clock"] == "2026-08-01T12:00:00Z"
    assert len(authority["windows"]) == 71

    medication = authority["windows"]["family.medication.administration"]
    assert medication == [
        {
            "version_id": "doc.medication.administration.v1",
            "authority_start": "2024-01-01T00:00:00Z",
            "authority_end": "2025-05-01T00:00:00Z",
        },
        {
            "version_id": "doc.medication.administration.v2",
            "authority_start": "2025-05-01T00:00:00Z",
            "authority_end": "2026-10-01T00:00:00Z",
        },
        {
            "version_id": "doc.medication.administration.v3",
            "authority_start": "2026-10-01T00:00:00Z",
            "authority_end": None,
        },
    ]
    assert authority["windows"]["family.safeguarding.adult-reporting"] == [
        {
            "version_id": "doc.safeguarding.adult-reporting.v1",
            "authority_start": "2024-07-01T00:00:00Z",
            "authority_end": None,
        }
    ]
    assert authority["windows"]["family.infection.outbreak-management"][-1] == {
        "version_id": "doc.infection.outbreak-management.v2",
        "authority_start": "2025-10-01T00:00:00Z",
        "authority_end": "2026-06-01T12:00:00Z",
    }
    clock = datetime.fromisoformat(authority["evaluation_clock"])
    last_end = datetime.fromisoformat(
        authority["windows"]["family.infection.outbreak-management"][-1][
            "authority_end"
        ]
    )
    assert last_end < clock


def test_committed_checksums_cover_every_canonical_benchmark_file() -> None:
    checksums = load_json(BENCHMARK_ROOT / "compiled/checksums.json")
    for relative_path, expected_digest in checksums["files"].items():
        actual_digest = hashlib.sha256(
            (BENCHMARK_ROOT / relative_path).read_bytes()
        ).hexdigest()
        assert actual_digest == expected_digest

    expected_benchmark_digest = hashlib.sha256(
        canonical_bytes(checksums["files"])
    ).hexdigest()
    assert checksums["benchmark_digest"] == expected_benchmark_digest
