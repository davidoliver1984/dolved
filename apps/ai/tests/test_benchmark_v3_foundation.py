import json
import shutil
from pathlib import Path
from typing import Any

from app.evaluation.benchmark.common import content_digest, digest_bytes
from app.evaluation.benchmark.v3 import compile_benchmark

BENCHMARKS_ROOT = Path("/evaluation/benchmarks/dolved-care-engineering")
V2_ROOT = BENCHMARKS_ROOT / "v2"
V3_ROOT = BENCHMARKS_ROOT / "v3"
CONTRACT_ROOT = Path("/contracts/evaluation/v3")
V2_DIGEST = "aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d"


def load_json(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text())


def test_repository_v3_first_domain_authoring_batch_compiles(tmp_path: Path) -> None:
    manifest = load_json(V3_ROOT / "manifest.json")
    assert manifest["status"] == "AUTHORING"
    assert manifest["authored_counts"] == {
        "document_families": 71,
        "document_versions": 93,
        "semantic_cases": 45,
    }
    case_sources = [
        load_json(path) for path in sorted((V3_ROOT / "cases").glob("*.json"))
    ]
    cases = [case for source in case_sources for case in source["cases"]]
    reviews = list((V3_ROOT / "reviews/cases").glob("*.json"))
    assert {source["domain"] for source in case_sources} == {
        "complaints",
        "fire-safety",
        "gdpr",
        "health-safety",
        "hr",
        "infection-control",
        "medication",
        "payroll",
        "safeguarding",
        "training",
        "visitors",
    }
    assert len(cases) == 45
    assert len(reviews) == 45
    assert all(case["authoring_status"] == "REVIEWED" for case in cases)
    assert not (V3_ROOT / "splits").exists()
    assert not (V3_ROOT / "compiled/corpus.json").exists()
    assert not (V3_ROOT / "compiled/authority-windows.json").exists()
    assert not (V3_ROOT / "compiled/split-identities.json").exists()
    assert not (V3_ROOT / "compiled/checksums.json").exists()

    temporary_authoring = tmp_path / "v3"
    shutil.copytree(V3_ROOT, temporary_authoring)
    before = (temporary_authoring / "compiled/source-checksums.json").read_bytes()
    compile_benchmark(
        temporary_authoring,
        CONTRACT_ROOT,
        parent_benchmark_root=V2_ROOT,
    )
    after = (temporary_authoring / "compiled/source-checksums.json").read_bytes()
    assert after == before
    assert not (temporary_authoring / "compiled/corpus.json").exists()


def test_v3_retains_v2_organisation_and_sources_byte_for_byte() -> None:
    assert (V3_ROOT / "organisation.json").read_bytes() == (
        V2_ROOT / "organisation.json"
    ).read_bytes()
    v2_sources = {
        path.relative_to(V2_ROOT / "documents"): path
        for path in (V2_ROOT / "documents").rglob("*.md")
    }
    v3_sources = {
        path.relative_to(V3_ROOT / "documents"): path
        for path in (V3_ROOT / "documents").rglob("*.md")
    }
    assert v2_sources.keys() == v3_sources.keys()
    assert len(v3_sources) == 93
    for relative_path, v2_source in v2_sources.items():
        assert v2_source.read_bytes() == v3_sources[relative_path].read_bytes()


def test_v3_catalogue_review_and_lineage_bind_every_source() -> None:
    catalog = load_json(V3_ROOT / "document-catalog.json")
    review = load_json(V3_ROOT / "reviews/catalogue-review-v1.json")
    lineage = load_json(V3_ROOT / "lineage/v2-to-v3.json")
    source_checksums = load_json(V3_ROOT / "compiled/source-checksums.json")
    taxonomy_digest = digest_bytes((V3_ROOT / "taxonomy/v1.json").read_bytes())
    taxonomy = {
        "taxonomy_id": "dolved-care-engineering-taxonomy",
        "taxonomy_version": "1",
        "taxonomy_sha256": taxonomy_digest,
    }
    families = {family["family_id"] for family in catalog["families"]}
    versions = {
        version["version_id"]
        for family in catalog["families"]
        for version in family["versions"]
    }
    expected_sources = {
        path.relative_to(V3_ROOT).as_posix(): digest_bytes(path.read_bytes())
        for path in sorted((V3_ROOT / "documents").rglob("*.md"))
    }

    assert catalog["taxonomy"] == taxonomy
    assert review["taxonomy"] == taxonomy
    assert lineage["taxonomy"] == taxonomy
    assert review["parent_benchmark_digest"] == V2_DIGEST
    assert review["source_digests"] == expected_sources
    assert source_checksums["source_digests"] == expected_sources
    assert review["document_family_ids_digest"] == content_digest(sorted(families))
    assert review["document_version_ids_digest"] == content_digest(sorted(versions))
    assert len(lineage["case_changes"]) == 45
    assert {change["classification"] for change in lineage["case_changes"]} == {"NEW"}
    assert len(lineage["document_changes"]) == 93
    assert {change["classification"] for change in lineage["document_changes"]} == {
        "METADATA_ENRICHED"
    }


def test_v3_symmetric_catalogue_relationships_are_reciprocal() -> None:
    catalog = load_json(V3_ROOT / "document-catalog.json")
    relationships = {
        (family["family_id"], relationship["type"], relationship["target_family_id"])
        for family in catalog["families"]
        for relationship in family["relationships"]
    }
    for source, relationship_type, target in relationships:
        if relationship_type in {"COMPLEMENTS", "CONFLICTS_WITH", "NEAR_DUPLICATE"}:
            assert (target, relationship_type, source) in relationships
