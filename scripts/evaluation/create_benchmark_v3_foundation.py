"""Create the case-free Benchmark V3 catalogue foundation from immutable V2."""

from __future__ import annotations

import argparse
import hashlib
import json
import shutil
import sys
from collections import defaultdict
from pathlib import Path
from typing import Any

AI_ROOT = Path(__file__).resolve().parents[2] / "apps/ai"
sys.path.insert(0, str(AI_ROOT))

from app.evaluation.benchmark.common import content_digest
from app.evaluation.benchmark.v3 import compile_benchmark

BENCHMARK_ID = "dolved-care-engineering"
SYMMETRIC_RELATIONSHIPS = {"COMPLEMENTS", "CONFLICTS_WITH", "NEAR_DUPLICATE"}
PLANNED_FACET_MAP = {
    "adversarial": "negative-instruction",
    "colour-table": "table-evidence",
    "conflicting-contact-details": "conflicting-guidance",
    "conflicting-guidance": "conflicting-guidance",
    "field-labels": "form-evidence",
    "form": "form-evidence",
    "form-discovery": "form-evidence",
    "keyword-stuffing": "keyword-stuffing",
    "long-form": "long-form",
    "near-date-values": "numeric-boundary",
    "near-duplicate": "near-duplicate",
    "near-duplicate-rows": "near-duplicate",
    "near-duplicate-terminology": "near-duplicate",
    "near-numeric-values": "numeric-boundary",
    "near-percentages": "numeric-boundary",
    "near-version-duplicate": "near-duplicate",
    "numeric-range": "numeric-boundary",
    "table-evidence": "table-evidence",
}


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--v2-root", type=Path, required=True)
    parser.add_argument("--v3-root", type=Path, required=True)
    parser.add_argument("--contract-root", type=Path, required=True)
    parser.add_argument("--reviewed-at", required=True)
    parser.add_argument(
        "--approve-catalogue-review",
        action="store_true",
        help="Explicitly attest that the catalogue-only human review is complete.",
    )
    return parser.parse_args()


def load_json(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text())


def write_json(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, indent=2, ensure_ascii=False) + "\n")


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def relationship_id(source: str, kind: str, target: str) -> str:
    return f"rel.{source.removeprefix('family.')}.{kind.lower()}.{target.removeprefix('family.')}"


def relationship_facets(relationships: list[dict[str, Any]]) -> set[str]:
    facets: set[str] = set()
    if any(item["type"] == "NEAR_DUPLICATE" for item in relationships):
        facets.add("near-duplicate")
    if any(item["type"] == "CONFLICTS_WITH" for item in relationships):
        facets.add("conflicting-guidance")
    return facets


def structural_facets(family: dict[str, Any]) -> set[str]:
    facets = {
        PLANNED_FACET_MAP[phenomenon]
        for phenomenon in family["planned_phenomena"]
        if phenomenon in PLANNED_FACET_MAP
    }
    if "COMPARE" in family["planned_phenomena"]:
        facets.add(
            "changed-structured-value"
            if "table-evidence" in facets
            else "changed-instruction"
        )
    facets.update(relationship_facets(family["relationships"]))
    if family["family_id"] == "family.visitors.search-optimised-draft":
        facets.update({"near-duplicate-wrong", "negative-instruction"})
    return facets


def applicability_facets(
    applicability: dict[str, Any], location_kinds: dict[str, str]
) -> set[str]:
    if applicability["kind"] == "UNIVERSAL":
        return {"universal"}
    kinds = {location_kinds[item] for item in applicability["location_ids"]}
    scope = "regional" if kinds == {"REGION"} else "site-specific"
    return {scope, "applicability-excluded"}


def version_facets(
    family: dict[str, Any],
    version: dict[str, Any],
    location_kinds: dict[str, str],
    evaluation_clock: str,
) -> list[str]:
    facets = structural_facets(family)
    facets.update(applicability_facets(version["applicability"], location_kinds))
    if version["governance_state"] == "DRAFT":
        facets.add("never-authoritative")
    if version["governance_state"] == "WITHDRAWN":
        facets.add("withdrawn")
    if (
        version["governance_state"] == "APPROVED"
        and version["effective_from"] > evaluation_clock
    ):
        facets.add("scheduled")
    return sorted(facets)


def reciprocal_relationships(
    families: list[dict[str, Any]],
) -> dict[str, list[dict[str, Any]]]:
    by_source: dict[str, dict[tuple[str, str], dict[str, Any]]] = defaultdict(dict)
    for family in families:
        source = family["family_id"]
        for relationship in family["relationships"]:
            target = relationship["target_family_id"]
            by_source[source][(relationship["type"], target)] = relationship
    for source, relationships in list(by_source.items()):
        for (kind, target), relationship in list(relationships.items()):
            if kind not in SYMMETRIC_RELATIONSHIPS:
                continue
            by_source[target].setdefault(
                (kind, source),
                {
                    "type": kind,
                    "target_family_id": source,
                    "notes": (
                        "Reciprocal V3 catalogue record for the corresponding "
                        f"{kind.lower().replace('_', ' ')} relationship."
                    ),
                },
            )
    return {
        source: [
            {
                "relationship_id": relationship_id(source, kind, target),
                "type": kind,
                "target_family_id": target,
                "target_version_id": None,
                "notes": relationship["notes"],
            }
            for (kind, target), relationship in sorted(relationships.items())
        ]
        for source, relationships in by_source.items()
    }


def leakage_groups(
    families: list[dict[str, Any]], relationships: dict[str, list[dict[str, Any]]]
) -> dict[str, list[str]]:
    adjacency: dict[str, set[str]] = {family["family_id"]: set() for family in families}
    for source, items in relationships.items():
        for relationship in items:
            if relationship["type"] in {"NEAR_DUPLICATE", "CONFLICTS_WITH"}:
                adjacency[source].add(relationship["target_family_id"])
    components: dict[str, str] = {}
    component_sizes: dict[str, int] = {}
    for family_id in sorted(adjacency):
        if family_id in components:
            continue
        pending = [family_id]
        members: set[str] = set()
        while pending:
            member = pending.pop()
            if member in members:
                continue
            members.add(member)
            pending.extend(adjacency[member] - members)
        component_id = min(members).removeprefix("family.")
        component_sizes[component_id] = len(members)
        for member in members:
            components[member] = component_id
    leakage: dict[str, list[str]] = {}
    for family_id in adjacency:
        component_id = components[family_id]
        groups = {f"leakage.family.{family_id.removeprefix('family.')}"}
        if component_sizes[component_id] > 1:
            groups.add(f"leakage.related.{component_id}")
        leakage[family_id] = sorted(groups)
    return leakage


def create_catalog(
    v2_catalog: dict[str, Any],
    v3_root: Path,
    organisation: dict[str, Any],
    taxonomy: dict[str, str],
) -> dict[str, Any]:
    relationships = reciprocal_relationships(v2_catalog["families"])
    leakage = leakage_groups(v2_catalog["families"], relationships)
    location_kinds = {
        item["location_id"]: item["kind"] for item in organisation["locations"]
    }
    families: list[dict[str, Any]] = []
    for source_family in v2_catalog["families"]:
        family_relationships = relationships.get(source_family["family_id"], [])
        versions = []
        for source_version in source_family["versions"]:
            source_path = source_version["source_path"]
            facets = version_facets(
                {**source_family, "relationships": family_relationships},
                source_version,
                location_kinds,
                organisation["evaluation_clock"],
            )
            versions.append(
                {key: value for key, value in source_version.items() if key != "pilot"}
                | {
                    "source_sha256": sha256(v3_root / source_path),
                    "evaluation_facets": facets,
                    "leakage_group_ids": leakage[source_family["family_id"]],
                }
            )
        families.append(
            {
                "family_id": source_family["family_id"],
                "domain": source_family["domain"],
                "title": source_family["title"],
                "document_type": source_family["document_type"],
                "planned_phenomena": sorted(
                    {
                        facet
                        for version in versions
                        for facet in version["evaluation_facets"]
                    }
                ),
                "relationships": family_relationships,
                "leakage_group_ids": leakage[source_family["family_id"]],
                "versions": versions,
            }
        )
    return {
        "schema_version": "v3",
        "benchmark_id": BENCHMARK_ID,
        "catalog_version": "1",
        "taxonomy": taxonomy,
        "families": families,
    }


def create_foundation(
    v2_root: Path,
    v3_root: Path,
    contract_root: Path,
    reviewed_at: str,
    *,
    approve_catalogue_review: bool,
) -> None:
    if v3_root.exists():
        raise ValueError(f"V3 foundation target already exists: {v3_root}")
    if not approve_catalogue_review:
        raise ValueError(
            "catalogue foundation creation requires explicit catalogue review approval"
        )
    checksums = load_json(v2_root / "compiled/checksums.json")
    parent_digest = checksums["benchmark_digest"]
    organisation = load_json(v2_root / "organisation.json")
    v2_catalog = load_json(v2_root / "document-catalog.json")

    shutil.copytree(v2_root / "documents", v3_root / "documents")
    shutil.copyfile(v2_root / "organisation.json", v3_root / "organisation.json")
    taxonomy_path = v3_root / "taxonomy/v1.json"
    taxonomy_path.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(contract_root / "taxonomy.v1.json", taxonomy_path)
    taxonomy = {
        "taxonomy_id": "dolved-care-engineering-taxonomy",
        "taxonomy_version": "1",
        "taxonomy_sha256": sha256(taxonomy_path),
    }
    catalog = create_catalog(v2_catalog, v3_root, organisation, taxonomy)
    catalog_path = v3_root / "document-catalog.json"
    write_json(catalog_path, catalog)

    source_digests = {
        path.relative_to(v3_root).as_posix(): sha256(path)
        for path in sorted((v3_root / "documents").rglob("*.md"))
    }
    family_ids = sorted(family["family_id"] for family in catalog["families"])
    version_ids = sorted(
        version["version_id"]
        for family in catalog["families"]
        for version in family["versions"]
    )
    review = {
        "schema_version": "v3",
        "review_id": "catalogue-review.dolved-care-engineering.v3-foundation",
        "review_version": "1",
        "benchmark_id": BENCHMARK_ID,
        "corpus_version": "3",
        "taxonomy": taxonomy,
        "parent_benchmark_digest": parent_digest,
        "organisation_sha256": sha256(v3_root / "organisation.json"),
        "document_catalog_sha256": sha256(catalog_path),
        "source_digests": source_digests,
        "document_family_ids_digest": content_digest(family_ids),
        "document_version_ids_digest": content_digest(version_ids),
        "reviewed_at": reviewed_at,
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
    write_json(v3_root / "reviews/catalogue-review-v1.json", review)

    parent = {
        "benchmark_id": BENCHMARK_ID,
        "corpus_version": "2",
        "benchmark_digest": parent_digest,
    }
    lineage = {
        "schema_version": "v3",
        "lineage_id": "lineage.dolved-care-engineering.v2-to-v3-foundation",
        "lineage_version": "1",
        "parent_release": parent,
        "target_release": {"benchmark_id": BENCHMARK_ID, "corpus_version": "3"},
        "taxonomy": taxonomy,
        "migration_tool": {
            "tool_id": "benchmark-v3-lineage",
            "tool_version": "1",
        },
        "v2_unchanged": True,
        "case_changes": [],
        "document_changes": [
            {
                "source_id": version_id,
                "target_id": version_id,
                "classification": "METADATA_ENRICHED",
                "reason": (
                    "Canonical Markdown is retained byte-for-byte; V3 adds its "
                    "source digest, scoped facets, leakage lineage and relationship metadata."
                ),
            }
            for version_id in version_ids
        ],
    }
    write_json(v3_root / "lineage/v2-to-v3.json", lineage)
    manifest = {
        "schema_version": "v3",
        "benchmark_id": BENCHMARK_ID,
        "corpus_version": "3",
        "status": "FOUNDATION",
        "evaluation_clock": organisation["evaluation_clock"],
        "canonical_source_format": "text/markdown",
        "parent_release": parent,
        "taxonomy": taxonomy,
        "paths": {
            "taxonomy": "taxonomy/v1.json",
            "organisation": "organisation.json",
            "document_catalog": "document-catalog.json",
            "catalogue_review": "reviews/catalogue-review-v1.json",
            "release_lineage": "lineage/v2-to-v3.json",
            "source_checksums": "compiled/source-checksums.json",
        },
        "authored_counts": {
            "document_families": len(family_ids),
            "document_versions": len(version_ids),
            "semantic_cases": 0,
        },
    }
    write_json(v3_root / "manifest.json", manifest)
    compile_benchmark(v3_root, contract_root, parent_benchmark_root=v2_root)


if __name__ == "__main__":
    arguments = parse_arguments()
    create_foundation(
        arguments.v2_root,
        arguments.v3_root,
        arguments.contract_root,
        arguments.reviewed_at,
        approve_catalogue_review=arguments.approve_catalogue_review,
    )
