import json
from pathlib import Path
from typing import Any

import pytest

from app.evaluation.benchmark.common import content_digest, digest_bytes
from app.evaluation.benchmark.v3 import compile_benchmark, source_catalog_digest

CONTRACT_ROOT = Path("/contracts/evaluation/v3")
TAXONOMY_SOURCE = CONTRACT_ROOT / "taxonomy.v1.json"
ZERO_DIGEST = "0" * 64


def write_json(file_path: Path, value: Any) -> None:
    file_path.parent.mkdir(parents=True, exist_ok=True)
    file_path.write_text(json.dumps(value, indent=2, ensure_ascii=False) + "\n")


def taxonomy_reference(taxonomy_path: Path) -> dict[str, str]:
    return {
        "taxonomy_id": "dolved-care-engineering-taxonomy",
        "taxonomy_version": "1",
        "taxonomy_sha256": digest_bytes(taxonomy_path.read_bytes()),
    }


def case(case_id: str, split_suffix: str) -> dict[str, Any]:
    return {
        "case_id": case_id,
        "cluster_id": f"cluster.{split_suffix}",
        "leakage_group_ids": [f"leakage.{split_suffix}"],
        "domain": "medication",
        "variants": [
            {
                "variant_id": variant_id,
                "question": "What is the expected evidence?",
                "evaluation_facets": ["colloquial"]
                if variant_id == "colloquial"
                else [],
            }
            for variant_id in ("direct", "colloquial", "contrast")
        ],
        "slices": ["CURRENT"],
        "evaluation_facets": ["universal"],
        "planner_expectation": {
            "temporal_mode": "CURRENT",
            "valid_at": None,
            "primary_anchor": None,
            "comparison_anchor": None,
            "applicability_reference": {
                "input": None,
                "resolved_location_id": None,
                "requires_clarification": False,
            },
            "expected_outcome": "PLAN_READY",
        },
        "eligibility_expectation": {
            "eligible_versions": [
                {
                    "document_family_id": "family.example",
                    "document_version_id": "document.example.v1",
                    "side": "PRIMARY",
                    "applicability": {
                        "kind": "UNIVERSAL",
                        "governing_location_id": None,
                        "requested_location_id": None,
                    },
                }
            ],
            "excluded_versions": [],
            "expected_outcome": "ELIGIBLE_SCOPE_READY",
        },
        "retrieval_expectation": {
            "evidence_units": [
                {
                    "evidence_id": f"evidence.{split_suffix}",
                    "document_family_id": "family.example",
                    "document_version_id": "document.example.v1",
                    "side": "PRIMARY",
                    "source_path": "documents/example.md",
                    "canonical_excerpts": ["Expected source evidence."],
                    "relevance_grade": 2,
                    "minimum_token_coverage": 1,
                    "locator": None,
                    "notes": None,
                }
            ]
        },
        "outcome_expectation": {"outcome": "EVIDENCE_FOUND"},
        "threshold_observability": {
            "classification": "POSITIVE_EVIDENCE",
            "reranker_evaluable": True,
            "required_sides": ["PRIMARY"],
            "justification": "Synthetic provider-free compiler fixture.",
        },
    }


def prepare_parent(parent_root: Path) -> str:
    parent_corpus = {"cases": [{"case_id": "case.parent"}]}
    parent_catalog = {
        "families": [{"versions": [{"version_id": "document.example.v1"}]}]
    }
    corpus_path = parent_root / "compiled/corpus.json"
    catalog_path = parent_root / "document-catalog.json"
    write_json(corpus_path, parent_corpus)
    write_json(catalog_path, parent_catalog)
    files = {
        "compiled/corpus.json": digest_bytes(corpus_path.read_bytes()),
        "document-catalog.json": digest_bytes(catalog_path.read_bytes()),
    }
    parent_digest = content_digest(files)
    write_json(
        parent_root / "compiled/checksums.json",
        {"benchmark_digest": parent_digest, "files": files},
    )
    return parent_digest


def prepare_release(temporary_root: Path) -> tuple[Path, Path, str]:
    benchmark_family_root = temporary_root / "dolved-care-engineering"
    parent_root = benchmark_family_root / "v2"
    benchmark_root = benchmark_family_root / "v3"
    parent_digest = prepare_parent(parent_root)
    taxonomy_path = benchmark_root / "taxonomy/v1.json"
    taxonomy_path.parent.mkdir(parents=True)
    taxonomy_path.write_bytes(TAXONOMY_SOURCE.read_bytes())
    taxonomy = taxonomy_reference(taxonomy_path)
    source_path = benchmark_root / "documents/example.md"
    source_path.parent.mkdir(parents=True)
    source_path.write_text("# Example\n\nExpected source evidence.\n")

    cases = [
        case("case.engineering", "engineering"),
        case("case.calibration", "calibration"),
        case("case.held-out", "held-out"),
    ]
    write_json(benchmark_root / "cases/medication.json", cases)
    organisation = {
        "schema_version": "v2",
        "benchmark_id": "dolved-care-engineering",
        "organisation": {
            "organisation_id": "organisation.example",
            "name": "Example Care",
            "description": "Synthetic compiler fixture.",
            "fictional": True,
        },
        "evaluation_clock": "2026-08-01T12:00:00Z",
        "locations": [
            {
                "location_id": "location.example",
                "name": "Example",
                "kind": "CARE_HOME",
                "parent_location_id": None,
            }
        ],
        "aliases": [],
        "terminology": [{"term": "Example", "meaning": "Fixture", "aliases": []}],
    }
    write_json(benchmark_root / "organisation.json", organisation)
    catalog = {
        "schema_version": "v3",
        "benchmark_id": "dolved-care-engineering",
        "catalog_version": "1",
        "taxonomy": taxonomy,
        "families": [
            {
                "family_id": "family.example",
                "domain": "medication",
                "title": "Example",
                "document_type": "POLICY",
                "planned_phenomena": ["universal"],
                "relationships": [],
                "leakage_group_ids": ["document-leakage.example"],
                "versions": [
                    {
                        "version_id": "document.example.v1",
                        "version_number": "1",
                        "source_path": "documents/example.md",
                        "source_sha256": digest_bytes(source_path.read_bytes()),
                        "governance_state": "APPROVED",
                        "created_at": "2025-01-01T00:00:00Z",
                        "approved_at": "2025-01-01T00:00:00Z",
                        "effective_from": "2025-01-01T00:00:00Z",
                        "withdrawn_at": None,
                        "supersedes_version_id": None,
                        "applicability": {"kind": "UNIVERSAL", "location_ids": []},
                        "evaluation_facets": ["universal"],
                        "leakage_group_ids": ["document-leakage.example"],
                    }
                ],
            }
        ],
    }
    catalog_path = benchmark_root / "document-catalog.json"
    write_json(catalog_path, catalog)
    catalogue_review = {
        "schema_version": "v3",
        "review_id": "catalogue-review.synthetic",
        "review_version": "1",
        "benchmark_id": "dolved-care-engineering",
        "corpus_version": "3",
        "taxonomy": taxonomy,
        "parent_benchmark_digest": parent_digest,
        "organisation_sha256": digest_bytes(
            (benchmark_root / "organisation.json").read_bytes()
        ),
        "document_catalog_sha256": digest_bytes(catalog_path.read_bytes()),
        "source_digests": {
            "documents/example.md": digest_bytes(source_path.read_bytes())
        },
        "document_family_ids_digest": content_digest(["family.example"]),
        "document_version_ids_digest": content_digest(["document.example.v1"]),
        "reviewed_at": "2026-08-13T00:00:00Z",
        "machine_validation": {
            "validator_id": "benchmark-v3-compiler",
            "validator_version": "1",
            "passed": True,
        },
        "human_review": {
            "document_identities_reviewed": True,
            "source_lineage_reviewed": True,
            "governance_reviewed": True,
            "applicability_reviewed": True,
            "relationships_reviewed": True,
            "approved": True,
        },
    }
    write_json(benchmark_root / "reviews/catalogue-review-v1.json", catalogue_review)
    split_case_ids = sorted(item["case_id"] for item in cases)
    split = {
        "schema_version": "v3",
        "benchmark_id": "dolved-care-engineering",
        "corpus_version": "3",
        "split_version": "1",
        "assignment_status": "SEALED",
        "taxonomy": taxonomy,
        "allocation_method": "synthetic-test",
        "allocation_method_version": "1",
        "case_ids_digest": content_digest(split_case_ids),
        "targets": {
            "engineering_tuning": 1,
            "threshold_calibration": 1,
            "sealed_held_out": 1,
        },
        "assignments": {
            "engineering_tuning": ["case.engineering"],
            "threshold_calibration": ["case.calibration"],
            "sealed_held_out": ["case.held-out"],
            "unassigned": [],
        },
        "rules": ["Synthetic identities remain in one split."],
    }
    write_json(benchmark_root / "splits/v1.json", split)
    review = {
        "schema_version": "v3",
        "review_id": "review.synthetic",
        "review_version": "1",
        "benchmark_id": "dolved-care-engineering",
        "corpus_version": "3",
        "taxonomy": taxonomy,
        "reviewed_case_ids_digest": content_digest(split_case_ids),
        "reviewed_source_catalog_digest": source_catalog_digest(
            benchmark_root, catalog_path
        ),
        "reviewed_at": "2026-08-13T00:00:00Z",
        "machine_validation": {
            "validator_id": "benchmark-v3-compiler",
            "validator_version": "1",
            "passed": True,
        },
        "human_review": {
            "semantic_quality_reviewed": True,
            "representative_coverage_reviewed": True,
            "author_rationale_reviewed": True,
            "governance_reviewed": True,
            "specialised_reviews": {
                "temporal": True,
                "applicability": True,
                "adversarial": True,
                "threshold_sensitive": True,
            },
        },
    }
    write_json(benchmark_root / "reviews/authoring-review-v1.json", review)
    parent_release = {
        "benchmark_id": "dolved-care-engineering",
        "corpus_version": "2",
        "benchmark_digest": parent_digest,
    }
    lineage = {
        "schema_version": "v3",
        "lineage_id": "lineage.synthetic",
        "lineage_version": "1",
        "parent_release": parent_release,
        "target_release": {
            "benchmark_id": "dolved-care-engineering",
            "corpus_version": "3",
        },
        "taxonomy": taxonomy,
        "migration_tool": {"tool_id": "benchmark-v3-lineage", "tool_version": "1"},
        "v2_unchanged": True,
        "case_changes": [
            {
                "source_id": "case.parent",
                "target_id": "case.engineering",
                "classification": "REVISED",
                "reason": "Synthetic retained identity mapping.",
            },
            {
                "source_id": None,
                "target_id": "case.calibration",
                "classification": "NEW",
                "reason": "Synthetic new case.",
            },
            {
                "source_id": None,
                "target_id": "case.held-out",
                "classification": "NEW",
                "reason": "Synthetic new case.",
            },
        ],
        "document_changes": [
            {
                "source_id": "document.example.v1",
                "target_id": "document.example.v1",
                "classification": "RETAINED_UNCHANGED",
                "reason": "Synthetic unchanged document.",
            }
        ],
    }
    write_json(benchmark_root / "lineage/v2-to-v3.json", lineage)
    manifest = {
        "schema_version": "v3",
        "benchmark_id": "dolved-care-engineering",
        "corpus_version": "3",
        "status": "COMPLETE",
        "evaluation_clock": "2026-08-01T12:00:00Z",
        "canonical_source_format": "text/markdown",
        "parent_release": parent_release,
        "taxonomy": taxonomy,
        "paths": {
            "taxonomy": "taxonomy/v1.json",
            "organisation": "organisation.json",
            "document_catalog": "document-catalog.json",
            "catalogue_review": "reviews/catalogue-review-v1.json",
            "case_sources": "cases",
            "split": "splits/v1.json",
            "authoring_review": "reviews/authoring-review-v1.json",
            "release_lineage": "lineage/v2-to-v3.json",
            "source_checksums": "compiled/source-checksums.json",
            "compiled_corpus": "compiled/corpus.json",
            "authority_windows": "compiled/authority-windows.json",
            "split_identities": "compiled/split-identities.json",
            "checksums": "compiled/checksums.json",
        },
        "authored_counts": {
            "document_families": 1,
            "document_versions": 1,
            "semantic_cases": 3,
        },
    }
    write_json(benchmark_root / "manifest.json", manifest)
    return benchmark_root, parent_root, parent_digest


def prepare_foundation(temporary_root: Path) -> tuple[Path, Path, str]:
    benchmark_root, parent_root, parent_digest = prepare_release(temporary_root)
    manifest_path = benchmark_root / "manifest.json"
    manifest = load_json(manifest_path)
    manifest["status"] = "FOUNDATION"
    manifest["authored_counts"]["semantic_cases"] = 0
    manifest["paths"] = {
        key: manifest["paths"][key]
        for key in (
            "taxonomy",
            "organisation",
            "document_catalog",
            "catalogue_review",
            "release_lineage",
            "source_checksums",
        )
    }
    write_json(manifest_path, manifest)
    lineage_path = benchmark_root / "lineage/v2-to-v3.json"
    lineage = load_json(lineage_path)
    lineage["case_changes"] = []
    write_json(lineage_path, lineage)
    return benchmark_root, parent_root, parent_digest


def compile_fixture(temporary_root: Path) -> Path:
    benchmark_root, parent_root, parent_digest = prepare_release(temporary_root)
    compile_benchmark(
        benchmark_root,
        CONTRACT_ROOT,
        parent_benchmark_root=parent_root,
        required_parent_digest=parent_digest,
    )
    return benchmark_root


def test_v3_compilation_is_deterministic(tmp_path: Path) -> None:
    benchmark_root = compile_fixture(tmp_path)
    first = {
        output.name: output.read_bytes()
        for output in sorted((benchmark_root / "compiled").iterdir())
    }
    _, parent_root, parent_digest = (
        benchmark_root,
        benchmark_root.parent / "v2",
        load_json(benchmark_root / "manifest.json")["parent_release"][
            "benchmark_digest"
        ],
    )
    compile_benchmark(
        benchmark_root,
        CONTRACT_ROOT,
        parent_benchmark_root=parent_root,
        required_parent_digest=parent_digest,
    )
    second = {
        output.name: output.read_bytes()
        for output in sorted((benchmark_root / "compiled").iterdir())
    }
    assert first == second


def test_empty_foundation_release_validates_without_case_outputs(
    tmp_path: Path,
) -> None:
    benchmark_root, parent_root, parent_digest = prepare_foundation(tmp_path)

    compile_benchmark(
        benchmark_root,
        CONTRACT_ROOT,
        parent_benchmark_root=parent_root,
        required_parent_digest=parent_digest,
    )

    assert (benchmark_root / "compiled/source-checksums.json").is_file()
    assert not (benchmark_root / "compiled/corpus.json").exists()
    assert not (benchmark_root / "compiled/authority-windows.json").exists()
    assert not (benchmark_root / "compiled/split-identities.json").exists()
    assert not (benchmark_root / "compiled/checksums.json").exists()


def test_foundation_parent_validation_does_not_require_case_corpus(
    tmp_path: Path,
) -> None:
    benchmark_root, parent_root, parent_digest = prepare_foundation(tmp_path)
    (parent_root / "compiled/corpus.json").unlink()

    compile_benchmark(
        benchmark_root,
        CONTRACT_ROOT,
        parent_benchmark_root=parent_root,
        required_parent_digest=parent_digest,
    )


def test_authoring_release_allows_incomplete_split_assignment(tmp_path: Path) -> None:
    benchmark_root, parent_root, parent_digest = prepare_release(tmp_path)
    manifest_path = benchmark_root / "manifest.json"
    manifest = load_json(manifest_path)
    manifest["status"] = "AUTHORING"
    for path_name in (
        "authoring_review",
        "compiled_corpus",
        "authority_windows",
        "split_identities",
        "checksums",
    ):
        del manifest["paths"][path_name]
    write_json(manifest_path, manifest)
    split_path = benchmark_root / "splits/v1.json"
    split = load_json(split_path)
    split["assignment_status"] = "DRAFT"
    split["assignments"]["sealed_held_out"] = []
    split["assignments"]["unassigned"] = ["case.held-out"]
    write_json(split_path, split)

    compile_benchmark(
        benchmark_root,
        CONTRACT_ROOT,
        parent_benchmark_root=parent_root,
        required_parent_digest=parent_digest,
    )

    assert (benchmark_root / "compiled/source-checksums.json").is_file()
    assert not (benchmark_root / "compiled/corpus.json").exists()


def test_foundation_rejects_premature_split_artifact(tmp_path: Path) -> None:
    benchmark_root, parent_root, parent_digest = prepare_foundation(tmp_path)
    manifest_path = benchmark_root / "manifest.json"
    manifest = load_json(manifest_path)
    manifest["paths"]["split"] = "splits/v1.json"
    write_json(manifest_path, manifest)

    with pytest.raises(Exception, match="should not be valid"):
        compile_benchmark(
            benchmark_root,
            CONTRACT_ROOT,
            parent_benchmark_root=parent_root,
            required_parent_digest=parent_digest,
        )


def test_complete_release_requires_case_sources(tmp_path: Path) -> None:
    benchmark_root, parent_root, parent_digest = prepare_release(tmp_path)
    manifest_path = benchmark_root / "manifest.json"
    manifest = load_json(manifest_path)
    del manifest["paths"]["case_sources"]
    write_json(manifest_path, manifest)

    with pytest.raises(Exception, match="case_sources"):
        compile_benchmark(
            benchmark_root,
            CONTRACT_ROOT,
            parent_benchmark_root=parent_root,
            required_parent_digest=parent_digest,
        )


def test_complete_release_rejects_zero_cases(tmp_path: Path) -> None:
    benchmark_root, parent_root, parent_digest = prepare_release(tmp_path)
    manifest_path = benchmark_root / "manifest.json"
    manifest = load_json(manifest_path)
    manifest["authored_counts"]["semantic_cases"] = 0
    write_json(manifest_path, manifest)

    with pytest.raises(Exception, match="less than the minimum"):
        compile_benchmark(
            benchmark_root,
            CONTRACT_ROOT,
            parent_benchmark_root=parent_root,
            required_parent_digest=parent_digest,
        )


def test_foundation_requires_catalogue_review(tmp_path: Path) -> None:
    benchmark_root, parent_root, parent_digest = prepare_foundation(tmp_path)
    manifest_path = benchmark_root / "manifest.json"
    manifest = load_json(manifest_path)
    del manifest["paths"]["catalogue_review"]
    write_json(manifest_path, manifest)

    with pytest.raises(Exception, match="catalogue_review"):
        compile_benchmark(
            benchmark_root,
            CONTRACT_ROOT,
            parent_benchmark_root=parent_root,
            required_parent_digest=parent_digest,
        )


def test_foundation_rejects_stale_catalogue_review_evidence(tmp_path: Path) -> None:
    benchmark_root, parent_root, parent_digest = prepare_foundation(tmp_path)
    organisation_path = benchmark_root / "organisation.json"
    organisation = load_json(organisation_path)
    organisation["organisation"]["description"] = "Changed after catalogue review."
    write_json(organisation_path, organisation)

    with pytest.raises(ValueError, match="organisation_sha256"):
        compile_benchmark(
            benchmark_root,
            CONTRACT_ROOT,
            parent_benchmark_root=parent_root,
            required_parent_digest=parent_digest,
        )


def load_json(file_path: Path) -> dict[str, Any]:
    return json.loads(file_path.read_text())


def compile_after_mutation(
    temporary_root: Path, relative_path: str, mutation: Any
) -> None:
    benchmark_root, parent_root, parent_digest = prepare_release(temporary_root)
    file_path = benchmark_root / relative_path
    value = load_json(file_path)
    mutation(value)
    write_json(file_path, value)
    compile_benchmark(
        benchmark_root,
        CONTRACT_ROOT,
        parent_benchmark_root=parent_root,
        required_parent_digest=parent_digest,
    )


def test_v3_rejects_unknown_facets(tmp_path: Path) -> None:
    with pytest.raises(Exception, match="population-private-facet"):
        compile_after_mutation(
            tmp_path,
            "cases/medication.json",
            lambda cases: cases[0].update(
                {"evaluation_facets": ["population-private-facet"]}
            ),
        )


def test_v3_rejects_invalid_taxonomy_binding(tmp_path: Path) -> None:
    with pytest.raises(ValueError, match="taxonomy binding"):
        compile_after_mutation(
            tmp_path,
            "manifest.json",
            lambda manifest: manifest["taxonomy"].update(
                {"taxonomy_sha256": ZERO_DIGEST}
            ),
        )


def test_v3_rejects_invalid_source_digest(tmp_path: Path) -> None:
    with pytest.raises(ValueError, match="source digest mismatch"):
        compile_after_mutation(
            tmp_path,
            "document-catalog.json",
            lambda catalog: catalog["families"][0]["versions"][0].update(
                {"source_sha256": ZERO_DIGEST}
            ),
        )


def test_v3_rejects_incomplete_lineage(tmp_path: Path) -> None:
    with pytest.raises(ValueError, match="does not completely map"):
        compile_after_mutation(
            tmp_path,
            "lineage/v2-to-v3.json",
            lambda lineage: lineage["case_changes"].pop(),
        )


def test_v3_rejects_unknown_lineage_tool_version(tmp_path: Path) -> None:
    with pytest.raises(ValueError, match="supported migration tool"):
        compile_after_mutation(
            tmp_path,
            "lineage/v2-to-v3.json",
            lambda lineage: lineage["migration_tool"].update(
                {"tool_version": "unknown"}
            ),
        )


def test_v3_rejects_leakage_groups_crossing_splits(tmp_path: Path) -> None:
    def share_leakage_group(cases: list[dict[str, Any]]) -> None:
        cases[1]["leakage_group_ids"] = cases[0]["leakage_group_ids"]

    with pytest.raises(ValueError, match="leakage groups cross split boundaries"):
        compile_after_mutation(tmp_path, "cases/medication.json", share_leakage_group)


def test_v3_rejects_split_identity_mismatch(tmp_path: Path) -> None:
    with pytest.raises(ValueError, match="split case identity digest"):
        compile_after_mutation(
            tmp_path,
            "splits/v1.json",
            lambda split: split.update({"case_ids_digest": ZERO_DIGEST}),
        )


def test_v3_rejects_non_reciprocal_symmetric_relationship(tmp_path: Path) -> None:
    def add_non_reciprocal_relationship(catalog: dict[str, Any]) -> None:
        source = catalog["families"][0]
        target = {
            **source,
            "family_id": "family.related",
            "title": "Related family",
            "versions": [
                {
                    **source["versions"][0],
                    "version_id": "document.related.v1",
                    "source_path": "documents/related.md",
                    "source_sha256": digest_bytes(b"# Related\n"),
                }
            ],
        }
        source["relationships"] = [
            {
                "relationship_id": "relationship.non-reciprocal",
                "type": "NEAR_DUPLICATE",
                "target_family_id": "family.related",
                "target_version_id": None,
                "notes": "Synthetic missing reciprocal.",
            }
        ]
        catalog["families"].append(target)

    benchmark_root, parent_root, parent_digest = prepare_release(tmp_path)
    related_source = benchmark_root / "documents/related.md"
    related_source.write_text("# Related\n")
    catalog_path = benchmark_root / "document-catalog.json"
    catalog = load_json(catalog_path)
    add_non_reciprocal_relationship(catalog)
    write_json(catalog_path, catalog)
    manifest_path = benchmark_root / "manifest.json"
    manifest = load_json(manifest_path)
    manifest["authored_counts"]["document_families"] = 2
    manifest["authored_counts"]["document_versions"] = 2
    write_json(manifest_path, manifest)
    review_path = benchmark_root / "reviews/catalogue-review-v1.json"
    review = load_json(review_path)
    review["document_catalog_sha256"] = digest_bytes(catalog_path.read_bytes())
    review["source_digests"]["documents/related.md"] = digest_bytes(
        related_source.read_bytes()
    )
    review["document_family_ids_digest"] = content_digest(
        ["family.example", "family.related"]
    )
    review["document_version_ids_digest"] = content_digest(
        ["document.example.v1", "document.related.v1"]
    )
    write_json(review_path, review)

    with pytest.raises(ValueError, match="missing its reciprocal"):
        compile_benchmark(
            benchmark_root,
            CONTRACT_ROOT,
            parent_benchmark_root=parent_root,
            required_parent_digest=parent_digest,
        )
