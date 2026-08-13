import hashlib
import json
from copy import deepcopy
from pathlib import Path
from typing import Any

import jsonschema
import pytest
from referencing import Registry, Resource

CONTRACT_ROOT = Path("/contracts/evaluation")
V3_ROOT = CONTRACT_ROOT / "v3"
V3_SCHEMA_NAMES = (
    "benchmark-manifest.schema.json",
    "benchmark-taxonomy.schema.json",
    "benchmark-catalogue-review.schema.json",
    "corpus.schema.json",
    "document-catalog.schema.json",
    "split.schema.json",
    "benchmark-authoring-review.schema.json",
    "benchmark-release-lineage.schema.json",
)
V2_SCHEMA_DIGESTS = {
    "benchmark-manifest.schema.json": "c520d716ab21909ca5292b4b2486cdc83a7e7a234ee75e0dd34b18dd7106f12f",
    "corpus.schema.json": "cfe827230163cdccb432378bb7f5d9a515162ecc81b65a020fe55406b7311c3d",
    "document-catalog.schema.json": "bc0be62cb5c284d6dd1fc502a6032e381dd0f24e25234f489d6c296b0a65feca",
    "split.schema.json": "c8bc012826b379ef375433d85fb361487968842ab93981d7ea4520018c3dee64",
}
V2_BENCHMARK_DIGEST = "aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d"
V2_BENCHMARK_ROOT = Path("/evaluation/benchmarks/dolved-care-engineering/v2")
CALIBRATION_POPULATION_SPEC = Path(
    "/evaluation/population-specifications/evidence-threshold-calibration/v1/specification.json"
)


def load_json(file_path: Path) -> dict[str, Any]:
    return json.loads(file_path.read_text())


def v3_registry() -> Registry:
    registry = Registry()
    for schema_name in V3_SCHEMA_NAMES:
        schema = load_json(V3_ROOT / schema_name)
        registry = registry.with_resource(schema["$id"], Resource.from_contents(schema))
    return registry


def validator_for_schema(schema_name: str) -> jsonschema.Draft202012Validator:
    return jsonschema.Draft202012Validator(
        load_json(V3_ROOT / schema_name),
        registry=v3_registry(),
        format_checker=jsonschema.FormatChecker(),
    )


def taxonomy_reference() -> dict[str, str]:
    return {
        "taxonomy_id": "dolved-care-engineering-taxonomy",
        "taxonomy_version": "1",
        "taxonomy_sha256": hashlib.sha256(
            (V3_ROOT / "taxonomy.v1.json").read_bytes()
        ).hexdigest(),
    }


def valid_corpus() -> dict[str, Any]:
    eligible = {
        "document_family_id": "family.example",
        "document_version_id": "document.example.v1",
        "side": "PRIMARY",
        "applicability": {
            "kind": "UNIVERSAL",
            "governing_location_id": None,
            "requested_location_id": None,
        },
    }
    return {
        "schema_version": "v3",
        "benchmark_id": "dolved-care-engineering",
        "corpus_version": "3",
        "title": "Contract fixture",
        "evaluation_clock": "2026-08-01T12:00:00Z",
        "matching_algorithm": "normalised-token-coverage-v1",
        "taxonomy": taxonomy_reference(),
        "cases": [
            {
                "case_id": "case.example",
                "cluster_id": "cluster.example",
                "leakage_group_ids": ["leakage.example"],
                "domain": "medication",
                "variants": [
                    {
                        "variant_id": variant_id,
                        "question": "What is the rule?",
                        "evaluation_facets": [],
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
                    "eligible_versions": [eligible],
                    "excluded_versions": [],
                    "expected_outcome": "ELIGIBLE_SCOPE_READY",
                },
                "retrieval_expectation": {
                    "evidence_units": [
                        {
                            "evidence_id": "evidence.example",
                            "document_family_id": "family.example",
                            "document_version_id": "document.example.v1",
                            "side": "PRIMARY",
                            "source_path": "documents/example.md",
                            "canonical_excerpts": ["Expected source evidence."],
                            "relevance_grade": 2,
                            "minimum_token_coverage": 1,
                        }
                    ]
                },
                "outcome_expectation": {"outcome": "EVIDENCE_FOUND"},
                "threshold_observability": {
                    "classification": "POSITIVE_EVIDENCE",
                    "reranker_evaluable": True,
                    "required_sides": ["PRIMARY"],
                    "justification": "Positive contract fixture.",
                },
            }
        ],
    }


def test_all_v3_schemas_are_valid_draft_2020_12_contracts() -> None:
    for schema_name in V3_SCHEMA_NAMES:
        jsonschema.Draft202012Validator.check_schema(load_json(V3_ROOT / schema_name))


def test_v3_taxonomy_is_valid_complete_and_exactly_bound_to_schema_enums() -> None:
    schema = load_json(V3_ROOT / "benchmark-taxonomy.schema.json")
    taxonomy = load_json(V3_ROOT / "taxonomy.v1.json")
    jsonschema.Draft202012Validator(schema).validate(taxonomy)

    declarations = (
        ("domains", "domain_id", "domain_id"),
        ("intrinsic_slices", "slice_id", "intrinsic_slice_id"),
        ("evaluation_facets", "facet_id", "evaluation_facet_id"),
    )
    for collection_name, identity_name, definition_name in declarations:
        identities = [item[identity_name] for item in taxonomy[collection_name]]
        assert len(identities) == len(set(identities))
        assert set(identities) == set(schema["$defs"][definition_name]["enum"])

    for facet in taxonomy["evaluation_facets"]:
        assert facet["permitted_scopes"]
        assert len(facet["permitted_scopes"]) == len(set(facet["permitted_scopes"]))
    for collection_name in ("domains", "intrinsic_slices", "evaluation_facets"):
        assert all(
            item["replacement_id"] is None
            for item in taxonomy[collection_name]
            if item["status"] == "ACTIVE"
        )
    assert taxonomy["deprecation_policy"]["matching"] == "EXACT_IDENTIFIER_ONLY"


def test_unknown_evaluation_facet_is_rejected() -> None:
    corpus = valid_corpus()
    validator = validator_for_schema("corpus.schema.json")
    validator.validate(corpus)
    corpus["cases"][0]["evaluation_facets"] = ["population-private-facet"]
    with pytest.raises(jsonschema.ValidationError):
        validator.validate(corpus)


def test_unknown_intrinsic_slice_is_rejected() -> None:
    corpus = deepcopy(valid_corpus())
    validator = validator_for_schema("corpus.schema.json")
    validator.validate(corpus)
    corpus["cases"][0]["slices"] = ["CURRENT-ish"]
    with pytest.raises(jsonschema.ValidationError):
        validator.validate(corpus)


def test_v3_taxonomy_owns_every_calibration_population_label_and_facet() -> None:
    taxonomy = load_json(V3_ROOT / "taxonomy.v1.json")
    specification = load_json(CALIBRATION_POPULATION_SPEC)
    declared_slices = {item["slice_id"] for item in taxonomy["intrinsic_slices"]}
    declared_facets = {item["facet_id"] for item in taxonomy["evaluation_facets"]}
    required_slices = {
        label for group in specification["semantic_groups"] for label in group["labels"]
    }
    required_facets = {
        facet
        for group in specification["semantic_groups"]
        for facet in group["diversity"]["required_benchmark_facets"]
    }

    assert required_slices <= declared_slices
    assert required_facets <= declared_facets


def test_v2_contracts_and_benchmark_digest_remain_unchanged() -> None:
    for schema_name, expected_digest in V2_SCHEMA_DIGESTS.items():
        assert (
            hashlib.sha256(
                (CONTRACT_ROOT / "v2" / schema_name).read_bytes()
            ).hexdigest()
            == expected_digest
        )

    checksums = load_json(V2_BENCHMARK_ROOT / "compiled/checksums.json")
    assert checksums["benchmark_digest"] == V2_BENCHMARK_DIGEST
    for relative_path, expected_digest in checksums["files"].items():
        assert (
            hashlib.sha256((V2_BENCHMARK_ROOT / relative_path).read_bytes()).hexdigest()
            == expected_digest
        )


def test_v2_and_v3_contract_identities_remain_separate() -> None:
    for schema_name in (
        "benchmark-manifest.schema.json",
        "corpus.schema.json",
        "document-catalog.schema.json",
        "split.schema.json",
    ):
        v2_schema = load_json(CONTRACT_ROOT / "v2" / schema_name)
        v3_schema = load_json(CONTRACT_ROOT / "v3" / schema_name)
        assert v2_schema["$id"] != v3_schema["$id"]
        assert v2_schema["properties"]["schema_version"]["const"] == "v2"
        assert v3_schema["properties"]["schema_version"]["const"] == "v3"
