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

    assert len(corpus["cases"]) == 8
    assert sum(len(case["variants"]) for case in corpus["cases"]) == 24
    assert all(case["authoring_status"] == "REVIEWED" for case in corpus["cases"])
    assert len({case["case_id"] for case in corpus["cases"]}) == 8

    for case in corpus["cases"]:
        review = load(POPULATION / "reviews" / f"{case['case_id']}.json")
        assert review["case_sha256"] == digest(case)
        assert review["source_catalog_digest"] == (
            "bc1876ad8d4e15f30c638021c5cf3d3d719c5ca694e52090614db58005a006fc"
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
