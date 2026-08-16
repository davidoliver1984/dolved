import hashlib
import json
import os
import subprocess
import sys
from pathlib import Path
from typing import Any

from jsonschema import Draft202012Validator, FormatChecker
from referencing import Registry

REPOSITORY = Path(os.environ.get("V3_ENGINEERING_REPOSITORY", "/workspace"))
EVALUATION = Path(os.environ.get("V3_ENGINEERING_EVALUATION_ROOT", "/evaluation"))
POPULATION = EVALUATION / ("engineering-populations/dolved-care-engineering/v3/v1")
BENCHMARK = EVALUATION / "benchmarks/dolved-care-engineering/v3"
CALIBRATION = EVALUATION / ("calibration-populations/dolved-care-engineering/v3/v1")
CONTRACTS = Path(
    os.environ.get("V3_ENGINEERING_CONTRACT_ROOT", "/contracts/evaluation/v3")
)


def load(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text())


def canonical_bytes(value: Any) -> bytes:
    return json.dumps(
        value, ensure_ascii=False, sort_keys=True, separators=(",", ":")
    ).encode()


def digest(value: Any) -> str:
    return hashlib.sha256(canonical_bytes(value)).hexdigest()


def test_population_is_valid_reviewed_and_source_anchored() -> None:
    corpus = load(POPULATION / "corpus.json")
    schemas = {
        path.name: load(path) for path in sorted(CONTRACTS.glob("*.schema.json"))
    }
    registry = Registry().with_contents(
        (schema["$id"], schema) for schema in schemas.values()
    )
    Draft202012Validator(
        schemas["corpus.schema.json"],
        registry=registry,
        format_checker=FormatChecker(),
    ).validate(corpus)

    assert len(corpus["cases"]) == 10
    assert sum(len(case["variants"]) for case in corpus["cases"]) == 31
    assert all(case["authoring_status"] == "REVIEWED" for case in corpus["cases"])
    assert len({case["case_id"] for case in corpus["cases"]}) == 10

    for case in corpus["cases"]:
        review = load(POPULATION / "reviews" / f"{case['case_id']}.json")
        assert review["case_sha256"] == digest(case)
        assert review["source_catalog_digest"] == (
            "4b6864760d735f5b59ec7027e2e6006d21435fb738d366d5d52e78fd48a3ae6e"
        )
        assert review["human_review"]["approved"] is True
        for source in case["source_lineage"]:
            path = BENCHMARK / source["source_path"]
            assert (
                hashlib.sha256(path.read_bytes()).hexdigest() == source["source_sha256"]
            )
        for evidence in case["retrieval_expectation"]["evidence_units"]:
            text = (BENCHMARK / evidence["source_path"]).read_text()
            assert all(excerpt in text for excerpt in evidence["canonical_excerpts"])


def test_inventory_covers_every_v2_engineering_case_without_cross_split_migration() -> (
    None
):
    inventory = load(POPULATION / "migration-inventory.json")
    records = inventory["cases"]
    assert len(records) == len({record["source_case_id"] for record in records}) == 42
    assert inventory["classification_counts"] == {
        "RETAINABLE": 7,
        "REQUIRES_V3_RECONCILIATION": 1,
        "BLOCKED_BY_CALIBRATION_CLUSTER": 34,
        "RETIRED": 0,
    }
    assert all(record["v2_split_owner"] == "engineering_tuning" for record in records)
    assert all(
        record["proposed_v3_case_id"] is None
        for record in records
        if record["classification"] == "BLOCKED_BY_CALIBRATION_CLUSTER"
    )


def test_engineering_and_calibration_are_independent_and_held_out_is_unavailable() -> (
    None
):
    evidence = load(POPULATION / "independence.json")
    assert evidence["overlap"] == {
        "case_ids": [],
        "semantic_cluster_ids": [],
        "leakage_group_ids": [],
    }
    assert evidence["held_out"] == {
        "assignment_status": "UNASSIGNED_AND_UNAVAILABLE",
        "content_accessed": False,
    }
    compiler = (
        REPOSITORY / "scripts/evaluation/compile_v3_engineering_population.py"
    ).read_text()
    assert "sealed_held_out" not in compiler
    assert 'CALIBRATION_ROOT / "corpus.json"' not in compiler


def test_population_digests_are_reproducible(tmp_path: Path) -> None:
    subprocess.run(
        [
            sys.executable,
            str(REPOSITORY / "scripts/evaluation/compile_v3_engineering_population.py"),
            "--output-root",
            str(tmp_path),
        ],
        check=True,
        cwd=REPOSITORY,
    )
    committed = sorted(
        path.relative_to(POPULATION)
        for path in POPULATION.rglob("*.json")
        if path.name != "provisioning-definition.json"
    )
    generated = sorted(path.relative_to(tmp_path) for path in tmp_path.rglob("*.json"))
    assert generated == committed
    assert all(
        (tmp_path / path).read_bytes() == (POPULATION / path).read_bytes()
        for path in committed
    )


def test_population_manifest_is_truthful() -> None:
    manifest = load(POPULATION / "population-manifest.json")
    core = {
        key: value
        for key, value in manifest.items()
        if key not in {"schema_version", "population_digest"}
    }
    assert manifest["population_digest"] == digest(core)
    assert (
        manifest["corpus_file_sha256"]
        == hashlib.sha256((POPULATION / "corpus.json").read_bytes()).hexdigest()
    )
    assert (
        manifest["expectations_file_sha256"]
        == hashlib.sha256((POPULATION / "expectations.json").read_bytes()).hexdigest()
    )


def test_v2_and_spent_calibration_lineage_remain_unchanged() -> None:
    v2_checksums = load(
        EVALUATION / "benchmarks/dolved-care-engineering/v2/compiled/checksums.json"
    )
    assert v2_checksums["benchmark_digest"] == (
        "aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d"
    )
    calibration = load(CALIBRATION / "population-manifest.json")
    assert calibration["population_digest"] == (
        "bafc720dbf03aba9e3fdee597ba0b9f2bfaa38db5adc8502b88bf68d07c57345"
    )
    assert calibration["case_count"] == 44
    assert calibration["variant_count"] == 132


def test_regression_additions_preserve_engineering_ownership_and_semantics() -> None:
    cases = {
        case["case_id"]: case for case in load(POPULATION / "corpus.json")["cases"]
    }

    location = cases[
        "v3.infection-control.current.midlands-community-specimen-transport"
    ]
    assert location["cluster_id"] == (
        "cluster.v3-engineering.midlands-community-specimen-transport"
    )
    assert location["planner_expectation"]["location_references"] == ["Coventry"]
    assert location["eligibility_expectation"]["eligible_versions"][0][
        "applicability"
    ] == {
        "kind": "ANCESTOR",
        "governing_location_id": "location.region.midlands",
        "requested_location_id": "location.willow-bank",
    }
    assert len(location["retrieval_expectation"]["evidence_units"]) == 2

    historical = cases["v3.medication.historical.controlled-drugs-v1"]
    exact_date = next(
        variant
        for variant in historical["variants"]
        if variant["variant_id"] == "exact-date"
    )
    assert exact_date["planner_expectation_override"] == {
        "temporal_mode": "VALID_AT_DATE",
        "explicit_date": "2024-06-15",
        "temporal_reference": None,
    }

    comparison = cases["v3.medication.compare.controlled-drugs-discrepancy"]
    assert comparison["planner_expectation"]["temporal_mode"] == "COMPARE"
    versioned = next(
        variant
        for variant in comparison["variants"]
        if variant["variant_id"] == "versioned"
    )
    assert versioned["question"] == (
        "What changed between version 1 and the current controlled-drugs "
        "procedure when the stock count is wrong?"
    )
    assert comparison["planner_expectation"]["temporal_reference"] == {
        "kind": "historical_reference",
        "value": "version 1",
    }
    evidence = comparison["retrieval_expectation"]["evidence_units"]
    assert {unit["side"] for unit in evidence} == {"PRIMARY", "COMPARISON"}
    assert comparison["threshold_observability"]["required_sides"] == [
        "PRIMARY",
        "COMPARISON",
    ]
