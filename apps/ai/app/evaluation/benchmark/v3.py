"""Compile and validate an immutable Benchmark V3 release."""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from referencing import Registry, Resource

from app.evaluation.benchmark.common import (
    assert_no_generated_identifiers,
    authoritative_version_at,
    canonical_bytes,
    content_digest,
    derive_authority_windows,
    digest_bytes,
    load_json,
    location_index,
    parse_time,
    validate_applicability,
    validate_schema,
)

BENCHMARK_ID = "dolved-care-engineering"
LINEAGE_TOOL_ID = "benchmark-v3-lineage"
LINEAGE_TOOL_VERSION = "1"
V2_BENCHMARK_DIGEST = "aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d"


def schema_registry(contract_root: Path) -> Registry:
    registry = Registry()
    for schema_path in sorted(contract_root.glob("*.schema.json")):
        schema = load_json(schema_path)
        registry = registry.with_resource(schema["$id"], Resource.from_contents(schema))
    return registry


def taxonomy_reference(taxonomy_path: Path, taxonomy: dict[str, Any]) -> dict[str, str]:
    return {
        "taxonomy_id": taxonomy["taxonomy_id"],
        "taxonomy_version": taxonomy["taxonomy_version"],
        "taxonomy_sha256": digest_bytes(taxonomy_path.read_bytes()),
    }


def assert_taxonomy_binding(
    value: dict[str, Any], expected: dict[str, str], owner: str
) -> None:
    if value.get("taxonomy") != expected:
        raise ValueError(f"{owner} taxonomy binding does not match canonical taxonomy")


def taxonomy_indexes(
    taxonomy: dict[str, Any],
) -> tuple[set[str], set[str], dict[str, set[str]]]:
    domains = {item["domain_id"] for item in taxonomy["domains"]}
    slices = {item["slice_id"] for item in taxonomy["intrinsic_slices"]}
    facets = {
        item["facet_id"]: set(item["permitted_scopes"])
        for item in taxonomy["evaluation_facets"]
    }
    if len(domains) != len(taxonomy["domains"]):
        raise ValueError("taxonomy domain identifiers must be unique")
    if len(slices) != len(taxonomy["intrinsic_slices"]):
        raise ValueError("taxonomy slice identifiers must be unique")
    if len(facets) != len(taxonomy["evaluation_facets"]):
        raise ValueError("taxonomy facet identifiers must be unique")
    for collection in (
        taxonomy["domains"],
        taxonomy["intrinsic_slices"],
        taxonomy["evaluation_facets"],
    ):
        for item in collection:
            if item["status"] == "DEPRECATED" and item["replacement_id"] is None:
                raise ValueError("deprecated taxonomy entries require a replacement")
    return domains, slices, facets


def validate_location_names_and_aliases(organisation: dict[str, Any]) -> None:
    """Enforce an exact, collision-safe V3 location vocabulary."""
    canonical_names: dict[str, str] = {}
    for location in organisation["locations"]:
        normalised = str(location["name"]).strip().lower()
        if normalised in canonical_names:
            raise ValueError("organisation location names must be unique")
        canonical_names[normalised] = str(location["location_id"])

    aliases: set[str] = set()
    for entry in organisation["aliases"]:
        normalised = str(entry["alias"]).strip().lower()
        if normalised in aliases:
            raise ValueError("organisation aliases must be unique")
        if normalised in canonical_names:
            raise ValueError(
                "organisation alias collides with a canonical location name"
            )
        aliases.add(normalised)


def assert_facets(
    values: list[str], facets: dict[str, set[str]], scope: str, owner: str
) -> None:
    for facet_id in values:
        if facet_id not in facets:
            raise ValueError(f"unknown evaluation facet for {owner}: {facet_id}")
        if scope not in facets[facet_id]:
            raise ValueError(
                f"evaluation facet {facet_id} is not permitted at {scope} scope"
            )


def validate_parent_release(
    parent_root: Path,
    required_parent_digest: str,
    manifest: dict[str, Any],
    lineage: dict[str, Any],
    *,
    load_case_identities: bool,
) -> tuple[set[str], set[str]]:
    checksums_path = parent_root / "compiled/checksums.json"
    if not checksums_path.is_file():
        raise ValueError("parent Benchmark V2 checksums are unavailable")
    checksums = load_json(checksums_path)
    if checksums["benchmark_digest"] != required_parent_digest:
        raise ValueError(
            "parent Benchmark V2 digest is not the accepted immutable digest"
        )
    protected_parent_paths = (
        "cases/",
        "splits/",
        "compiled/corpus.json",
        "compiled/authority-windows.json",
    )
    for relative_path, expected_digest in checksums["files"].items():
        if not load_case_identities and relative_path.startswith(
            protected_parent_paths
        ):
            continue
        if digest_bytes((parent_root / relative_path).read_bytes()) != expected_digest:
            raise ValueError(f"parent Benchmark V2 artefact changed: {relative_path}")
    expected_parent = {
        "benchmark_id": BENCHMARK_ID,
        "corpus_version": "2",
        "benchmark_digest": required_parent_digest,
    }
    if manifest["parent_release"] != expected_parent:
        raise ValueError("manifest parent release does not identify immutable V2")
    if lineage["parent_release"] != expected_parent:
        raise ValueError("lineage parent release does not identify immutable V2")

    parent_catalog = load_json(parent_root / "document-catalog.json")
    parent_cases = (
        {
            item["case_id"]
            for item in load_json(parent_root / "compiled/corpus.json")["cases"]
        }
        if load_case_identities
        else set()
    )
    parent_versions = {
        version["version_id"]
        for family in parent_catalog["families"]
        for version in family["versions"]
    }
    return parent_cases, parent_versions


def validate_catalog(
    catalog: dict[str, Any],
    benchmark_root: Path,
    locations: dict[str, dict[str, Any]],
    domains: set[str],
    facets: dict[str, set[str]],
) -> tuple[dict[str, dict[str, Any]], dict[str, dict[str, Any]], dict[str, Any]]:
    families = {family["family_id"]: family for family in catalog["families"]}
    if len(families) != len(catalog["families"]):
        raise ValueError("family_id values must be unique")
    versions: dict[str, dict[str, Any]] = {}
    relationship_ids: set[str] = set()
    catalogued_sources: set[str] = set()
    authority: dict[str, Any] = {}

    for family in catalog["families"]:
        if family["domain"] not in domains:
            raise ValueError(f"unknown catalogue domain: {family['domain']}")
        assert_facets(
            family["planned_phenomena"], facets, "DOCUMENT", family["family_id"]
        )
        authority[family["family_id"]] = derive_authority_windows(family)
        for version in family["versions"]:
            version_id = version["version_id"]
            if version_id in versions:
                raise ValueError(f"duplicate version_id: {version_id}")
            assert_facets(version["evaluation_facets"], facets, "DOCUMENT", version_id)
            versions[version_id] = {**version, "family_id": family["family_id"]}
            for location_id in version["applicability"]["location_ids"]:
                if location_id not in locations:
                    raise ValueError(f"unknown applicability location: {location_id}")
            source_path = version["source_path"]
            if source_path is not None:
                source_file = benchmark_root / source_path
                if not source_file.is_file():
                    raise ValueError(f"missing authored source: {source_path}")
                if digest_bytes(source_file.read_bytes()) != version["source_sha256"]:
                    raise ValueError(f"source digest mismatch: {source_path}")
                catalogued_sources.add(source_path)

    symmetric_relationships = {
        "NEAR_DUPLICATE",
        "CONFLICTS_WITH",
        "COMPLEMENTS",
    }
    relationships: set[tuple[str, str, str, str | None]] = set()
    for family in catalog["families"]:
        for relationship in family["relationships"]:
            relationship_id = relationship["relationship_id"]
            if relationship_id in relationship_ids:
                raise ValueError(f"duplicate relationship_id: {relationship_id}")
            relationship_ids.add(relationship_id)
            target_family = relationship["target_family_id"]
            target_version = relationship["target_version_id"]
            if target_family not in families:
                raise ValueError(f"unknown related family: {target_family}")
            if target_version is not None and (
                target_version not in versions
                or versions[target_version]["family_id"] != target_family
            ):
                raise ValueError(
                    f"related version does not belong to target family: {target_version}"
                )
            relationships.add(
                (
                    family["family_id"],
                    relationship["type"],
                    target_family,
                    target_version,
                )
            )

    for (
        source_family,
        relationship_type,
        target_family,
        target_version,
    ) in relationships:
        if relationship_type not in symmetric_relationships:
            continue
        reciprocal_target_version = None
        if target_version is not None:
            raise ValueError(
                f"symmetric relationship must be family-scoped: {source_family}"
            )
        if (
            target_family,
            relationship_type,
            source_family,
            reciprocal_target_version,
        ) not in relationships:
            raise ValueError(
                "symmetric catalogue relationship is missing its reciprocal: "
                f"{source_family}/{relationship_type}/{target_family}"
            )

    markdown_sources = {
        source.relative_to(benchmark_root).as_posix()
        for source in (benchmark_root / "documents").rglob("*.md")
    }
    if markdown_sources != catalogued_sources:
        raise ValueError("catalogue and canonical Markdown sources differ")
    return families, versions, authority


def validate_variant_planner_expectation(
    shared: dict[str, Any], variant: dict[str, Any], case_id: str
) -> None:
    override = variant.get("planner_expectation_override")
    if override is None:
        return
    effective = {**shared, **override}
    if effective == shared:
        raise ValueError(
            f"redundant planner expectation override: {case_id}/{variant['variant_id']}"
        )

    mode = effective["temporal_mode"]
    explicit_date = effective["explicit_date"]
    reference = effective["temporal_reference"]
    clarification = effective["clarification_reason"]
    valid = False
    if mode == "CURRENT":
        valid = explicit_date is None and reference is None and clarification is None
    elif mode == "VALID_AT_DATE":
        valid = clarification is None and (
            (explicit_date is not None and reference is None)
            or (
                explicit_date is None
                and reference is not None
                and reference["kind"] == "calendar_period"
            )
        )
    elif mode == "HISTORICAL_REFERENCE":
        valid = (
            explicit_date is None
            and reference is not None
            and reference["kind"] == "historical_reference"
            and clarification is None
        )
    elif mode == "COMPARE":
        valid = not (explicit_date is not None and reference is not None) and (
            clarification is None
        )
    elif mode == "CLARIFICATION_REQUIRED":
        valid = (
            explicit_date is None
            and reference is None
            and clarification == "UNCLASSIFIABLE_TEMPORAL_INTENT"
        )
    if not valid:
        raise ValueError(
            "variant planner expectation override violates ADR-0022: "
            f"{case_id}/{variant['variant_id']}"
        )


def validate_cases(
    cases: list[dict[str, Any]],
    benchmark_root: Path,
    families: dict[str, dict[str, Any]],
    versions: dict[str, dict[str, Any]],
    authority: dict[str, list[dict[str, str | None]]],
    locations: dict[str, dict[str, Any]],
    facets: dict[str, set[str]],
    evaluation_clock: str,
) -> None:
    case_ids = [case["case_id"] for case in cases]
    if len(case_ids) != len(set(case_ids)):
        raise ValueError("case_id values must be unique")
    all_evidence_ids: set[str] = set()
    clock = parse_time(evaluation_clock)
    for case in cases:
        variant_ids = [variant["variant_id"] for variant in case["variants"]]
        if len(variant_ids) != len(set(variant_ids)):
            raise ValueError(f"duplicate variant_id in {case['case_id']}")
        assert_facets(case["evaluation_facets"], facets, "CASE", case["case_id"])
        for variant in case["variants"]:
            assert_facets(
                variant["evaluation_facets"],
                facets,
                "VARIANT",
                f"{case['case_id']}/{variant['variant_id']}",
            )
            validate_variant_planner_expectation(
                case["planner_expectation"], variant, case["case_id"]
            )
        eligible = case["eligibility_expectation"]["eligible_versions"]
        source_lineage = {
            (
                item["document_family_id"],
                item["document_version_id"],
                item["source_path"],
            ): item
            for item in case["source_lineage"]
        }
        if len(source_lineage) != len(case["source_lineage"]):
            raise ValueError(f"duplicate source lineage in {case['case_id']}")
        for key, source_reference in source_lineage.items():
            family_id, version_id, source_path = key
            if (
                version_id not in versions
                or versions[version_id]["family_id"] != family_id
                or versions[version_id]["source_path"] != source_path
                or versions[version_id]["source_sha256"]
                != source_reference["source_sha256"]
            ):
                raise ValueError(
                    f"invalid source lineage reference in {case['case_id']}: {version_id}"
                )
        eligible_keys = {
            (item["document_family_id"], item["document_version_id"], item["side"])
            for item in eligible
        }
        planner = case["planner_expectation"]
        moment = (
            parse_time(f"{planner['explicit_date']}T00:00:00Z")
            if planner["temporal_mode"] == "VALID_AT_DATE"
            and planner["explicit_date"] is not None
            else clock
        )
        for expected in eligible:
            family_id = expected["document_family_id"]
            version_id = expected["document_version_id"]
            if family_id not in families or version_id not in versions:
                raise ValueError(f"unknown eligible version: {family_id}/{version_id}")
            if versions[version_id]["family_id"] != family_id:
                raise ValueError(
                    f"eligible version belongs to another family: {version_id}"
                )
            lineage_key = (family_id, version_id, versions[version_id]["source_path"])
            if lineage_key not in source_lineage:
                raise ValueError(
                    f"eligible version is absent from source lineage: {version_id}"
                )
            validate_applicability(
                expected["applicability"], versions[version_id], locations
            )
            if (
                planner["temporal_mode"] in {"CURRENT", "VALID_AT_DATE"}
                and planner["temporal_reference"] is None
                and authoritative_version_at(authority[family_id], moment) != version_id
            ):
                raise ValueError(
                    f"case expects temporally ineligible version: {version_id}"
                )
            if (
                planner["temporal_mode"] == "COMPARE"
                and expected["side"] == "PRIMARY"
                and (
                    authoritative_version_at(authority[family_id], clock) != version_id
                )
            ):
                raise ValueError(f"COMPARE primary is not current: {version_id}")
        for excluded in case["eligibility_expectation"]["excluded_versions"]:
            version_id = excluded["document_version_id"]
            if (
                version_id not in versions
                or versions[version_id]["family_id"] != excluded["document_family_id"]
            ):
                raise ValueError(f"invalid excluded version: {version_id}")
            lineage_key = (
                excluded["document_family_id"],
                version_id,
                versions[version_id]["source_path"],
            )
            if lineage_key not in source_lineage:
                raise ValueError(
                    f"excluded version is absent from source lineage: {version_id}"
                )
        evidence_ids: set[str] = set()
        for evidence in case["retrieval_expectation"]["evidence_units"]:
            evidence_id = evidence["evidence_id"]
            if evidence_id in evidence_ids:
                raise ValueError(f"duplicate evidence_id: {evidence_id}")
            if evidence_id in all_evidence_ids:
                raise ValueError(
                    f"EvidenceUnit identity is not globally unique: {evidence_id}"
                )
            evidence_ids.add(evidence_id)
            all_evidence_ids.add(evidence_id)
            version_id = evidence["document_version_id"]
            if version_id not in versions:
                raise ValueError(f"unknown evidence version: {version_id}")
            version = versions[version_id]
            evidence_key = (
                evidence["document_family_id"],
                version_id,
                evidence["side"],
            )
            if (
                version["family_id"] != evidence["document_family_id"]
                or evidence_key not in eligible_keys
            ):
                raise ValueError(
                    f"evidence is outside expected eligible scope: {evidence_id}"
                )
            if version["source_path"] != evidence["source_path"]:
                raise ValueError(f"evidence source/catalogue mismatch: {evidence_id}")
            if (
                evidence["document_family_id"],
                version_id,
                evidence["source_path"],
            ) not in source_lineage:
                raise ValueError(
                    f"evidence is absent from source lineage: {evidence_id}"
                )
            lineage_reference = source_lineage[
                (
                    evidence["document_family_id"],
                    version_id,
                    evidence["source_path"],
                )
            ]
            if lineage_reference["purpose"] != "EXPECTED_EVIDENCE":
                raise ValueError(
                    "EvidenceUnit source must be declared as expected evidence: "
                    f"{evidence_id}"
                )
            source = (benchmark_root / evidence["source_path"]).read_text()
            if any(excerpt not in source for excerpt in evidence["canonical_excerpts"]):
                raise ValueError(f"canonical excerpt missing for {evidence_id}")

        expected_outcome = case["outcome_expectation"]["outcome"]
        planner_outcome = planner["expected_outcome"]
        if (
            planner_outcome == "CLARIFICATION_REQUIRED"
            and expected_outcome != planner_outcome
        ):
            raise ValueError("planner clarification must remain the case outcome")
        if expected_outcome == "EVIDENCE_FOUND" and not evidence_ids:
            raise ValueError("EVIDENCE_FOUND requires at least one EvidenceUnit")


def validate_case_reviews(
    cases: list[dict[str, Any]],
    reviews: list[dict[str, Any]],
    expected_taxonomy: dict[str, str],
    catalog_digest: str,
) -> str:
    by_case: dict[str, dict[str, Any]] = {}
    review_ids: set[str] = set()
    for review in reviews:
        case_id = review["case_id"]
        if case_id in by_case:
            raise ValueError(f"duplicate case authoring review: {case_id}")
        by_case[case_id] = review
        if review["review_id"] in review_ids:
            raise ValueError(f"duplicate case review identity: {review['review_id']}")
        review_ids.add(review["review_id"])
        assert_taxonomy_binding(review, expected_taxonomy, "case authoring review")
    reviewed_cases = {
        case["case_id"]: case
        for case in cases
        if case["authoring_status"] == "REVIEWED"
    }
    if set(by_case) != set(reviewed_cases):
        raise ValueError("case review identities do not match REVIEWED cases")
    for case_id, case in reviewed_cases.items():
        review = by_case[case_id]
        if review["case_sha256"] != digest_bytes(canonical_bytes(case)):
            raise ValueError(f"case review does not bind authored case: {case_id}")
        if review["source_catalog_digest"] != catalog_digest:
            raise ValueError(f"case review does not bind source catalogue: {case_id}")
        if review["source_lineage_digest"] != content_digest(case["source_lineage"]):
            raise ValueError(f"case review does not bind source lineage: {case_id}")
        evidence_ids = sorted(
            evidence["evidence_id"]
            for evidence in case["retrieval_expectation"]["evidence_units"]
        )
        if review["evidence_unit_ids_digest"] != content_digest(evidence_ids):
            raise ValueError(f"case review does not bind EvidenceUnits: {case_id}")
        rejection_review = review["human_review"]["controlled_rejection_rationale"]
        controlled = case["outcome_expectation"]["outcome"] != "EVIDENCE_FOUND"
        expected_review = "REVIEWED" if controlled else "NOT_APPLICABLE"
        if rejection_review != expected_review:
            raise ValueError(
                f"controlled rejection review status is inconsistent: {case_id}"
            )
    return content_digest(
        sorted(
            (review["case_id"], digest_bytes(canonical_bytes(review)))
            for review in reviews
        )
    )


def validate_split(
    split: dict[str, Any], cases: list[dict[str, Any]]
) -> dict[str, Any]:
    case_ids = {case["case_id"] for case in cases}
    assignments = split["assignments"]
    assigned = [case_id for values in assignments.values() for case_id in values]
    if len(assigned) != len(set(assigned)) or set(assigned) != case_ids:
        raise ValueError("every case must have exactly one split assignment")
    if split["case_ids_digest"] != content_digest(sorted(case_ids)):
        raise ValueError("split case identity digest does not match compiled cases")
    if split["assignment_status"] in {"COMPLETE", "SEALED"}:
        if assignments["unassigned"]:
            raise ValueError("complete or sealed split cannot contain unassigned cases")
        for split_name, target in split["targets"].items():
            if len(assignments[split_name]) != target:
                raise ValueError(f"split target mismatch: {split_name}")

    split_by_case = {
        case_id: split_name
        for split_name, values in assignments.items()
        for case_id in values
    }
    cluster_splits: dict[str, set[str]] = {}
    leakage_splits: dict[str, set[str]] = {}
    for case in cases:
        split_name = split_by_case[case["case_id"]]
        cluster_splits.setdefault(case["cluster_id"], set()).add(split_name)
        for leakage_group in case["leakage_group_ids"]:
            leakage_splits.setdefault(leakage_group, set()).add(split_name)
    if any(len(values) > 1 for values in cluster_splits.values()):
        raise ValueError("semantic clusters cross split boundaries")
    if any(len(values) > 1 for values in leakage_splits.values()):
        raise ValueError("leakage groups cross split boundaries")

    identities: dict[str, Any] = {}
    for split_name, values in assignments.items():
        selected = [case for case in cases if case["case_id"] in values]
        identities[split_name] = {
            "case_count": len(selected),
            "case_ids_digest": content_digest(sorted(values)),
            "semantic_cluster_ids_digest": content_digest(
                sorted({case["cluster_id"] for case in selected})
            ),
            "leakage_group_ids_digest": content_digest(
                sorted(
                    {group for case in selected for group in case["leakage_group_ids"]}
                )
            ),
        }
    return identities


def validate_lineage(
    lineage: dict[str, Any],
    parent_cases: set[str],
    parent_versions: set[str],
    target_cases: set[str],
    target_versions: set[str],
    *,
    require_complete_case_lineage: bool,
) -> None:
    if lineage["migration_tool"] != {
        "tool_id": LINEAGE_TOOL_ID,
        "tool_version": LINEAGE_TOOL_VERSION,
    }:
        raise ValueError(
            "release lineage does not identify the supported migration tool"
        )
    for change_type, parent_ids, target_ids, require_complete in (
        (
            "case_changes",
            parent_cases,
            target_cases,
            require_complete_case_lineage,
        ),
        ("document_changes", parent_versions, target_versions, True),
    ):
        changes = lineage[change_type]
        sources: set[str] = set()
        targets: set[str] = set()
        for change in changes:
            source = change["source_id"]
            target = change["target_id"]
            classification = change["classification"]
            if classification == "NEW":
                if source is not None or target is None:
                    raise ValueError("NEW lineage requires only a target identity")
            elif classification == "RETIRED":
                if source is None or target is not None:
                    raise ValueError("RETIRED lineage requires only a source identity")
            elif source is None or target is None:
                raise ValueError("retained or revised lineage requires both identities")
            if source is not None:
                if source not in parent_ids or source in sources:
                    raise ValueError(f"invalid or duplicate lineage source: {source}")
                sources.add(source)
            if target is not None:
                if target not in target_ids or target in targets:
                    raise ValueError(f"invalid or duplicate lineage target: {target}")
                targets.add(target)
        if require_complete and (sources != parent_ids or targets != target_ids):
            raise ValueError(f"{change_type} does not completely map both releases")

        if not require_complete and not (
            sources.issubset(parent_ids) and targets.issubset(target_ids)
        ):
            raise ValueError(f"{change_type} references identities outside the stage")


def source_catalog_digest(benchmark_root: Path, catalog_path: Path) -> str:
    source_digests = {
        source.relative_to(benchmark_root).as_posix(): digest_bytes(source.read_bytes())
        for source in sorted((benchmark_root / "documents").rglob("*.md"))
    }
    return content_digest(
        {
            "document_catalog_sha256": digest_bytes(catalog_path.read_bytes()),
            "sources": source_digests,
        }
    )


def catalogue_review_evidence(
    benchmark_root: Path,
    organisation_path: Path,
    catalog_path: Path,
    families: dict[str, dict[str, Any]],
    versions: dict[str, dict[str, Any]],
) -> dict[str, Any]:
    return {
        "organisation_sha256": digest_bytes(organisation_path.read_bytes()),
        "document_catalog_sha256": digest_bytes(catalog_path.read_bytes()),
        "source_digests": {
            source.relative_to(benchmark_root).as_posix(): digest_bytes(
                source.read_bytes()
            )
            for source in sorted((benchmark_root / "documents").rglob("*.md"))
        },
        "document_family_ids_digest": content_digest(sorted(families)),
        "document_version_ids_digest": content_digest(sorted(versions)),
    }


def validate_catalogue_review(
    review: dict[str, Any],
    evidence: dict[str, Any],
    parent_digest: str,
) -> None:
    if review["parent_benchmark_digest"] != parent_digest:
        raise ValueError("catalogue review parent digest does not match Benchmark V2")
    for field, expected in evidence.items():
        if review[field] != expected:
            raise ValueError(f"catalogue review {field} does not match release sources")
    if not all(review["human_review"].values()):
        raise ValueError("catalogue human review is not approved")


def source_checksum_record(
    taxonomy: dict[str, str], evidence: dict[str, Any]
) -> dict[str, Any]:
    return {
        "schema_version": "v1",
        "benchmark_id": BENCHMARK_ID,
        "corpus_version": "3",
        "taxonomy": taxonomy,
        **evidence,
        "source_catalog_digest": content_digest(evidence),
    }


def compile_benchmark(
    benchmark_root: Path,
    contract_root: Path,
    *,
    parent_benchmark_root: Path | None = None,
    required_parent_digest: str = V2_BENCHMARK_DIGEST,
) -> None:
    manifest_path = benchmark_root / "manifest.json"
    manifest = load_json(manifest_path)
    paths = manifest["paths"]
    status = manifest["status"]
    taxonomy_path = benchmark_root / paths["taxonomy"]
    organisation_path = benchmark_root / paths["organisation"]
    catalog_path = benchmark_root / paths["document_catalog"]
    catalogue_review_path = benchmark_root / paths["catalogue_review"]
    lineage_path = benchmark_root / paths["release_lineage"]
    taxonomy = load_json(taxonomy_path)
    organisation = load_json(organisation_path)
    catalog = load_json(catalog_path)
    catalogue_review = load_json(catalogue_review_path)
    lineage = load_json(lineage_path)
    registry = schema_registry(contract_root)

    for value, schema_name in (
        (manifest, "benchmark-manifest.schema.json"),
        (taxonomy, "benchmark-taxonomy.schema.json"),
        (catalog, "document-catalog.schema.json"),
        (catalogue_review, "benchmark-catalogue-review.schema.json"),
        (lineage, "benchmark-release-lineage.schema.json"),
    ):
        validate_schema(value, contract_root / schema_name, registry=registry)
        assert_no_generated_identifiers(value)
    validate_schema(
        organisation,
        contract_root.parent / "v2/organisation.schema.json",
    )
    assert_no_generated_identifiers(organisation)

    expected_taxonomy = taxonomy_reference(taxonomy_path, taxonomy)
    for owner, value in (
        ("manifest", manifest),
        ("catalogue", catalog),
        ("catalogue review", catalogue_review),
        ("release lineage", lineage),
    ):
        assert_taxonomy_binding(value, expected_taxonomy, owner)
    domains, _slices, facets = taxonomy_indexes(taxonomy)
    if manifest["evaluation_clock"] != organisation["evaluation_clock"]:
        raise ValueError("manifest and organisation evaluation clocks differ")
    validate_location_names_and_aliases(organisation)
    locations = location_index(organisation)
    families, versions, authority = validate_catalog(
        catalog, benchmark_root, locations, domains, facets
    )
    document_counts = {
        "document_families": len(families),
        "document_versions": len(versions),
    }
    if any(
        manifest["authored_counts"][name] != count
        for name, count in document_counts.items()
    ):
        raise ValueError("manifest document counts do not match benchmark sources")

    # AUTHORING may introduce independently authored NEW cases without consulting
    # any protected V2 case population. Parent case identities are loaded only
    # when a lineage entry actually claims a V2 source, or when closure requires
    # complete release-to-release case lineage.
    load_case_identities = status in {"COMPLETE", "BASELINED"} or any(
        change["source_id"] is not None for change in lineage["case_changes"]
    )
    parent_cases, parent_versions = validate_parent_release(
        parent_benchmark_root or benchmark_root.parent / "v2",
        required_parent_digest,
        manifest,
        lineage,
        load_case_identities=load_case_identities,
    )
    review_evidence = catalogue_review_evidence(
        benchmark_root, organisation_path, catalog_path, families, versions
    )
    validate_catalogue_review(catalogue_review, review_evidence, required_parent_digest)
    source_checksums = source_checksum_record(expected_taxonomy, review_evidence)
    source_checksums_path = benchmark_root / paths["source_checksums"]
    source_checksums_path.parent.mkdir(parents=True, exist_ok=True)
    source_checksums_path.write_text(
        json.dumps(source_checksums, indent=2, ensure_ascii=False) + "\n"
    )

    if status == "FOUNDATION":
        if manifest["authored_counts"]["semantic_cases"] != 0:
            raise ValueError("FOUNDATION must declare zero semantic cases")
        if lineage["case_changes"]:
            raise ValueError("FOUNDATION cannot claim case lineage before authoring")
        validate_lineage(
            lineage,
            set(),
            parent_versions,
            set(),
            set(versions),
            require_complete_case_lineage=False,
        )
        return

    case_paths = sorted((benchmark_root / paths["case_sources"]).glob("*.json"))
    case_sources = [load_json(case_path) for case_path in case_paths]
    batch_ids = [case_source["authoring_batch_id"] for case_source in case_sources]
    if len(batch_ids) != len(set(batch_ids)):
        raise ValueError("case authoring batch identities must be unique")
    for case_source in case_sources:
        validate_schema(
            case_source, contract_root / "case-source.schema.json", registry=registry
        )
        assert_no_generated_identifiers(case_source)
        assert_taxonomy_binding(case_source, expected_taxonomy, "case source")
        if any(
            case["domain"] != case_source["domain"] for case in case_source["cases"]
        ):
            raise ValueError("case source domain does not match its authored cases")
    cases = [case for case_source in case_sources for case in case_source["cases"]]
    cases.sort(key=lambda case: case["case_id"])
    corpus = {
        "schema_version": "v3",
        "benchmark_id": BENCHMARK_ID,
        "corpus_version": "3",
        "title": "Dolved Care Engineering Benchmark",
        "evaluation_clock": manifest["evaluation_clock"],
        "matching_algorithm": "normalised-token-coverage-v1",
        "taxonomy": expected_taxonomy,
        "cases": cases,
    }
    validate_schema(corpus, contract_root / "corpus.schema.json", registry=registry)
    assert_no_generated_identifiers(corpus)
    validate_cases(
        cases,
        benchmark_root,
        families,
        versions,
        authority,
        locations,
        facets,
        manifest["evaluation_clock"],
    )
    if manifest["authored_counts"]["semantic_cases"] != len(cases):
        raise ValueError("manifest authored counts do not match benchmark sources")

    review_paths = (
        sorted((benchmark_root / paths["case_reviews"]).glob("*.json"))
        if "case_reviews" in paths
        else []
    )
    case_reviews = [load_json(review_path) for review_path in review_paths]
    for case_review in case_reviews:
        validate_schema(
            case_review,
            contract_root / "case-authoring-review.schema.json",
            registry=registry,
        )
        assert_no_generated_identifiers(case_review)
    catalog_digest = source_catalog_digest(benchmark_root, catalog_path)
    case_reviews_digest = validate_case_reviews(
        cases, case_reviews, expected_taxonomy, catalog_digest
    )

    require_complete = status in {"COMPLETE", "BASELINED"}
    if require_complete and any(
        case["authoring_status"] != "REVIEWED" for case in cases
    ):
        raise ValueError("complete releases require every case to be REVIEWED")
    validate_lineage(
        lineage,
        parent_cases,
        parent_versions,
        {case["case_id"] for case in cases},
        set(versions),
        require_complete_case_lineage=require_complete,
    )

    if status == "AUTHORING":
        if "split" in paths:
            split = load_json(benchmark_root / paths["split"])
            validate_schema(
                split, contract_root / "split.schema.json", registry=registry
            )
            assert_no_generated_identifiers(split)
            assert_taxonomy_binding(split, expected_taxonomy, "split")
            validate_split(split, cases)
        return

    split_path = benchmark_root / paths["split"]
    review_path = benchmark_root / paths["authoring_review"]
    split = load_json(split_path)
    review = load_json(review_path)
    for value, schema_name in (
        (split, "split.schema.json"),
        (review, "benchmark-authoring-review.schema.json"),
    ):
        validate_schema(value, contract_root / schema_name, registry=registry)
        assert_no_generated_identifiers(value)
    assert_taxonomy_binding(split, expected_taxonomy, "split")
    assert_taxonomy_binding(review, expected_taxonomy, "authoring review")
    split_identities = validate_split(split, cases)
    case_digest = content_digest(sorted(case["case_id"] for case in cases))
    if review["reviewed_case_ids_digest"] != case_digest:
        raise ValueError("authoring review is not bound to the compiled cases")
    if review["reviewed_source_catalog_digest"] != source_catalog_digest(
        benchmark_root, catalog_path
    ):
        raise ValueError("authoring review is not bound to catalogue and sources")
    if review["reviewed_case_reviews_digest"] != case_reviews_digest:
        raise ValueError("authoring review is not bound to individual case reviews")

    compiled_dir = benchmark_root / "compiled"
    compiled_dir.mkdir(parents=True, exist_ok=True)
    corpus_path = benchmark_root / paths["compiled_corpus"]
    authority_path = benchmark_root / paths["authority_windows"]
    identities_path = benchmark_root / paths["split_identities"]
    corpus_path.write_text(json.dumps(corpus, indent=2, ensure_ascii=False) + "\n")
    authority_path.write_text(
        json.dumps(
            {
                "benchmark_id": BENCHMARK_ID,
                "corpus_version": "3",
                "evaluation_clock": manifest["evaluation_clock"],
                "windows": dict(sorted(authority.items())),
            },
            indent=2,
            ensure_ascii=False,
        )
        + "\n"
    )
    identities_path.write_text(
        json.dumps(
            {
                "schema_version": "v1",
                "benchmark_id": BENCHMARK_ID,
                "corpus_version": "3",
                "split_version": split["split_version"],
                "splits": split_identities,
            },
            indent=2,
            ensure_ascii=False,
        )
        + "\n"
    )
    canonical_files = [
        manifest_path,
        taxonomy_path,
        organisation_path,
        catalog_path,
        catalogue_review_path,
        source_checksums_path,
        split_path,
        review_path,
        lineage_path,
        *case_paths,
        *review_paths,
        *sorted((benchmark_root / "documents").rglob("*.md")),
        corpus_path,
        authority_path,
        identities_path,
    ]
    file_digests = {
        file_path.relative_to(benchmark_root).as_posix(): digest_bytes(
            file_path.read_bytes()
        )
        for file_path in canonical_files
    }
    checksum_record = {
        "schema_version": "v1",
        "benchmark_id": BENCHMARK_ID,
        "corpus_version": "3",
        "files": file_digests,
        "benchmark_digest": digest_bytes(canonical_bytes(file_digests)),
    }
    (benchmark_root / paths["checksums"]).write_text(
        json.dumps(checksum_record, indent=2, ensure_ascii=False) + "\n"
    )
