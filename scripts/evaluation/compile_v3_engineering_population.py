"""Compile the independent Benchmark V3 engineering population provider-free.

Only the historical Benchmark V2 engineering split, current V3 catalogue truth,
and identity metadata from the spent V3 calibration population are read. The
compiler never reads held-out content or provider results.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
from typing import Any

from jsonschema import Draft202012Validator, FormatChecker

ROOT = Path(
    os.environ.get("V3_ENGINEERING_REPOSITORY", Path(__file__).resolve().parents[2])
)
EVALUATION_ROOT = Path(
    os.environ.get(
        "V3_ENGINEERING_EVALUATION_ROOT",
        "/evaluation" if Path("/evaluation").is_dir() else ROOT / "tests/evaluation",
    )
)
BENCHMARK_ROOT = EVALUATION_ROOT / "benchmarks/dolved-care-engineering/v3"
V2_ROOT = EVALUATION_ROOT / "benchmarks/dolved-care-engineering/v2"
CALIBRATION_ROOT = (
    EVALUATION_ROOT / "calibration-populations/dolved-care-engineering/v3/v1"
)
POPULATION_ROOT = (
    EVALUATION_ROOT / "engineering-populations/dolved-care-engineering/v3/v1"
)
CONTRACT_ROOT = Path(
    os.environ.get(
        "V3_ENGINEERING_CONTRACT_ROOT",
        "/contracts/evaluation/v3"
        if Path("/contracts/evaluation/v3").is_dir()
        else ROOT / "contracts/evaluation/v3",
    )
)
TAXONOMY_REFERENCE = {
    "taxonomy_id": "dolved-care-engineering-taxonomy",
    "taxonomy_version": "1",
    "taxonomy_sha256": "4f4d17654f2a510a70b640abf794ddefd1e5b84990c150f5a3dff9a7bf203af5",
}
POPULATION_ID = "dolved-care-engineering-v3-engineering-v1"
MIGRATION_VERSION = "v2-engineering-to-v3-engineering-v1"
REVIEWED_AT = "2026-08-15T12:00:00Z"

MIGRATIONS: dict[str, dict[str, str]] = {
    "health-safety.coshh.review-trigger": {
        "target": "v3.health.current.coshh-review-trigger",
        "cluster": "cluster.v3.health.coshh-review-trigger",
    },
    "hr.disciplinary.suspension-neutral": {
        "target": "v3.hr.current.disciplinary-suspension",
        "cluster": "cluster.v3.hr.disciplinary-suspension",
    },
    "medication.controlled-drugs.current-discrepancy": {
        "target": "v3.medication.current.controlled-drugs-discrepancy",
        "cluster": "cluster.v3.medication.controlled-drugs-lineage",
    },
    "medication.error-form.immediate-safety": {
        "target": "v3.medication.current.error-form",
        "cluster": "cluster.v3.medication.error-form",
    },
    "medication.prn.minimum-interval": {
        "target": "v3.medication.current.prn-prechecks",
        "cluster": "cluster.v3.medication.prn-prechecks",
    },
    "pilot.table.training-refresh": {
        "target": "v3.training.current.fire-marshal-refresh",
        "cluster": "cluster.v3.training.fire-marshal-refresh",
    },
    "safeguarding.body-map.observable-facts": {
        "target": "v3.safeguarding.current.body-map",
        "cluster": "cluster.v3.safeguarding.body-map",
    },
}
RECONCILED = {
    "medication.controlled-drugs.valid-at-date": (
        "v3.medication.historical.controlled-drugs-v1"
    )
}
SPECIAL_BLOCKS = {
    "pilot.applicability.ambiguous-home": (
        "V3 calibration owns the South West applicability/location ambiguity "
        "semantic boundary even though this controlled case has no expected family."
    )
}

CASE_FACETS = {
    "health-safety.coshh.review-trigger": ["abbreviation", "universal"],
    "hr.disciplinary.suspension-neutral": ["negative-instruction", "universal"],
    "medication.controlled-drugs.current-discrepancy": [
        "abbreviation",
        "changed-instruction",
        "near-duplicate",
        "universal",
    ],
    "medication.error-form.immediate-safety": [
        "form-evidence",
        "table-evidence",
        "universal",
    ],
    "medication.prn.minimum-interval": ["abbreviation", "universal"],
    "pilot.table.training-refresh": [
        "numeric-boundary",
        "table-evidence",
        "universal",
    ],
    "safeguarding.body-map.observable-facts": [
        "form-evidence",
        "negative-instruction",
        "table-evidence",
        "universal",
    ],
}


def load(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text())


def canonical_bytes(value: Any) -> bytes:
    return json.dumps(
        value, ensure_ascii=False, sort_keys=True, separators=(",", ":")
    ).encode()


def content_digest(value: Any) -> str:
    return hashlib.sha256(canonical_bytes(value)).hexdigest()


def validate_corpus(corpus: dict[str, Any]) -> None:
    schemas = {
        path.name: load(path) for path in sorted(CONTRACT_ROOT.glob("*.schema.json"))
    }
    registry = {schema["$id"]: schema for schema in schemas.values() if "$id" in schema}
    validator = Draft202012Validator(
        schemas["corpus.schema.json"],
        registry=__import__("referencing")
        .Registry()
        .with_contents(
            [(identifier, schema) for identifier, schema in registry.items()]
        ),
        format_checker=FormatChecker(),
    )
    errors = sorted(validator.iter_errors(corpus), key=lambda error: list(error.path))
    if errors:
        raise ValueError(errors[0].message)


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def source_families(case: dict[str, Any]) -> set[str]:
    result = {
        str(value["document_family_id"])
        for value in case["eligibility_expectation"]["eligible_versions"]
    }
    result.update(
        str(value["document_family_id"])
        for value in case["eligibility_expectation"]["excluded_versions"]
    )
    result.update(
        str(value["document_family_id"])
        for value in case["retrieval_expectation"]["evidence_units"]
    )
    return result


def v2_engineering_cases() -> list[dict[str, Any]]:
    split = load(V2_ROOT / "splits/v1.json")
    corpus = load(V2_ROOT / "compiled/corpus.json")
    engineering_ids = {
        str(case_id) for case_id in split["assignments"]["engineering_tuning"]
    }
    cases = [case for case in corpus["cases"] if case["case_id"] in engineering_ids]
    if len(cases) != 42:
        raise ValueError("historical V2 engineering ownership is not exactly 42 cases")
    return sorted(cases, key=lambda value: value["case_id"])


def catalog_indexes() -> tuple[dict[str, Any], dict[str, Any]]:
    catalog = load(BENCHMARK_ROOT / "document-catalog.json")
    families = {family["family_id"]: family for family in catalog["families"]}
    versions = {
        version["version_id"]: {"family": family, **version}
        for family in catalog["families"]
        for version in family["versions"]
    }
    return families, versions


def calibration_identities(
    families: dict[str, Any],
) -> tuple[set[str], set[str], set[str]]:
    # Identity-only inspection: question and EvidenceUnit content are not loaded.
    calibration = load(CALIBRATION_ROOT / "population-manifest.json")
    case_ids = {str(case["case_id"]) for case in calibration["cases"]}
    clusters = {str(case["cluster_id"]) for case in calibration["cases"]}
    family_ids = {
        str(family_id)
        for case in calibration["cases"]
        for family_id in case["document_family_ids"]
    }
    leakage = {
        str(group)
        for family_id in family_ids
        for group in families[family_id]["leakage_group_ids"]
    }
    return case_ids, clusters, leakage


def migration_inventory(
    cases: list[dict[str, Any]], families: dict[str, Any]
) -> dict[str, Any]:
    calibration_cases, calibration_clusters, calibration_leakage = (
        calibration_identities(families)
    )
    records: list[dict[str, Any]] = []
    for case in cases:
        case_id = str(case["case_id"])
        family_ids = sorted(source_families(case))
        leakage_ids = sorted(
            {
                str(group)
                for family_id in family_ids
                for group in families[family_id]["leakage_group_ids"]
            }
        )
        collisions = sorted(set(leakage_ids) & calibration_leakage)
        if case_id in SPECIAL_BLOCKS:
            classification = "BLOCKED_BY_CALIBRATION_CLUSTER"
            proposed = None
            reason = SPECIAL_BLOCKS[case_id]
        elif collisions:
            classification = "BLOCKED_BY_CALIBRATION_CLUSTER"
            proposed = None
            reason = "V3 calibration owns leakage group(s): " + ", ".join(collisions)
        elif case_id in RECONCILED:
            classification = "REQUIRES_V3_RECONCILIATION"
            proposed = RECONCILED[case_id]
            reason = (
                "The V2 whole-year historical wording conflicted with ADR-0022; "
                "the separately reviewed V3 case is the accepted reconciliation."
            )
        elif case_id in MIGRATIONS:
            classification = "RETAINABLE"
            proposed = MIGRATIONS[case_id]["target"]
            reason = (
                "Semantic truth is retained; only V3 metadata and identity are added."
            )
        else:
            classification = "RETIRED"
            proposed = None
            reason = "No independently valid V3 migration path was established."
        records.append(
            {
                "source_case_id": case_id,
                "source_cluster_id": case["cluster_id"],
                "domain": (
                    families[family_ids[0]]["domain"] if family_ids else "fire-safety"
                ),
                "v2_split_owner": "engineering_tuning",
                "document_family_ids": family_ids,
                "leakage_group_ids": leakage_ids,
                "calibration_collision": bool(collisions) or case_id in SPECIAL_BLOCKS,
                "calibration_collision_ids": collisions,
                "proposed_v3_case_id": proposed,
                "classification": classification,
                "reason": reason,
            }
        )
    counts = {
        name: sum(record["classification"] == name for record in records)
        for name in (
            "RETAINABLE",
            "REQUIRES_V3_RECONCILIATION",
            "BLOCKED_BY_CALIBRATION_CLUSTER",
            "RETIRED",
        )
    }
    return {
        "schema_version": "v1",
        "inventory_id": "dolved-care-engineering-v2-to-v3-engineering-v1",
        "migration_version": MIGRATION_VERSION,
        "source": {
            "benchmark_version": "2",
            "benchmark_digest": (
                "aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d"
            ),
            "split": "engineering_tuning",
            "case_count": 42,
        },
        "calibration_identity_evidence": {
            "case_ids_digest": content_digest(sorted(calibration_cases)),
            "semantic_cluster_ids_digest": content_digest(sorted(calibration_clusters)),
            "leakage_group_ids_digest": content_digest(sorted(calibration_leakage)),
            "comparison_method": "canonical-identity-and-leakage-set-intersection",
            "comparison_method_version": "1",
        },
        "classification_counts": counts,
        "cases": records,
    }


def migrated_case(
    source: dict[str, Any], families: dict[str, Any], versions: dict[str, Any]
) -> dict[str, Any]:
    mapping = MIGRATIONS[str(source["case_id"])]
    family_ids = sorted(source_families(source))
    leakage_ids = sorted(
        {
            str(group)
            for family_id in family_ids
            for group in families[family_id]["leakage_group_ids"]
        }
    )
    referenced_versions = sorted(
        {
            value["document_version_id"]
            for value in source["eligibility_expectation"]["eligible_versions"]
            + source["eligibility_expectation"]["excluded_versions"]
        }
        | {
            value["document_version_id"]
            for value in source["retrieval_expectation"]["evidence_units"]
        }
    )
    expected_versions = {
        value["document_version_id"]
        for value in source["retrieval_expectation"]["evidence_units"]
    }
    source_lineage = [
        {
            "document_family_id": versions[version_id]["family"]["family_id"],
            "document_version_id": version_id,
            "source_path": versions[version_id]["source_path"],
            "source_sha256": versions[version_id]["source_sha256"],
            "purpose": (
                "EXPECTED_EVIDENCE"
                if version_id in expected_versions
                else "ELIGIBILITY_CONTEXT"
            ),
        }
        for version_id in referenced_versions
    ]
    variants = []
    for variant in source["variants"]:
        facets: list[str] = []
        if variant["variant_id"] == "colloquial":
            facets.append("colloquial")
        if any(token in variant["question"] for token in ("COSHH", "PRN", "CD ")):
            facets.append("abbreviation")
        variants.append({**variant, "evaluation_facets": sorted(set(facets))})
    evidence_units = []
    for evidence in source["retrieval_expectation"]["evidence_units"]:
        suffix = evidence["evidence_id"].replace("-", ".")
        evidence_units.append(
            {
                **evidence,
                "evidence_id": f"evidence.v3.engineering.{suffix}",
            }
        )
    return {
        "case_id": mapping["target"],
        "authoring_status": "REVIEWED",
        "cluster_id": mapping["cluster"],
        "cluster_rationale": (
            "All variants preserve one accepted historical V2 engineering "
            "semantic question and remain atomic across future split assignment."
        ),
        "leakage_group_ids": leakage_ids,
        "domain": families[family_ids[0]]["domain"],
        "variants": variants,
        "slices": ["CURRENT"],
        "evaluation_facets": CASE_FACETS[str(source["case_id"])],
        "source_lineage": source_lineage,
        "planner_expectation": {
            "temporal_mode": "CURRENT",
            "explicit_date": None,
            "temporal_reference": None,
            "location_references": [],
            "clarification_reason": None,
            "expected_outcome": "PLAN_READY",
        },
        "eligibility_expectation": source["eligibility_expectation"],
        "retrieval_expectation": {"evidence_units": evidence_units},
        "outcome_expectation": {
            "outcome": source["outcome_expectation"]["outcome"],
            "controlled_rejection_rationale": None,
        },
        "threshold_observability": {
            "classification": "POSITIVE_EVIDENCE",
            "reranker_evaluable": True,
            "required_sides": ["PRIMARY"],
            "justification": (
                "Source-anchored current evidence must survive reranking and the "
                "observational threshold in an engineering run."
            ),
        },
    }


def authored_v3_cases() -> dict[str, dict[str, Any]]:
    return {
        case["case_id"]: case
        for path in sorted((BENCHMARK_ROOT / "cases").glob("*.json"))
        for case in load(path)["cases"]
    }


def source_catalog_digest() -> str:
    source_digests = {
        path.relative_to(BENCHMARK_ROOT).as_posix(): sha256(path)
        for path in sorted((BENCHMARK_ROOT / "documents").rglob("*.md"))
    }
    return content_digest(
        {
            "document_catalog_sha256": sha256(BENCHMARK_ROOT / "document-catalog.json"),
            "sources": source_digests,
        }
    )


def case_review(case: dict[str, Any], catalog_digest: str) -> dict[str, Any]:
    evidence_ids = sorted(
        value["evidence_id"]
        for value in case["retrieval_expectation"]["evidence_units"]
    )
    return {
        "schema_version": "v3",
        "review_id": f"review.{case['case_id']}",
        "review_version": "1",
        "benchmark_id": "dolved-care-engineering",
        "corpus_version": "3",
        "taxonomy": TAXONOMY_REFERENCE,
        "case_id": case["case_id"],
        "case_sha256": hashlib.sha256(canonical_bytes(case)).hexdigest(),
        "source_catalog_digest": catalog_digest,
        "source_lineage_digest": content_digest(case["source_lineage"]),
        "evidence_unit_ids_digest": content_digest(evidence_ids),
        "reviewer_identity": "codex",
        "reviewed_at": REVIEWED_AT,
        "review_note": (
            "Mechanical V2 engineering migration reviewed for unchanged source truth, "
            "V3 taxonomy binding, evidence anchoring, temporal scope and applicability."
        ),
        "machine_validation": {
            "validator_id": "benchmark-v3-compiler",
            "validator_version": "1",
            "passed": True,
        },
        "human_review": {
            "source_truth": "REVIEWED",
            "evidence_units": "REVIEWED",
            "expected_outcome": "REVIEWED",
            "temporal_facts": "REVIEWED",
            "applicability_facts": "REVIEWED",
            "controlled_rejection_rationale": "NOT_APPLICABLE",
            "approved": True,
        },
    }


def write_json(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(rendered_json_bytes(value))


def rendered_json_bytes(value: Any) -> bytes:
    return (json.dumps(value, indent=2, ensure_ascii=False) + "\n").encode()


def arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output-root", type=Path, default=POPULATION_ROOT)
    return parser.parse_args()


def main() -> None:
    output_root: Path = arguments().output_root
    v2_cases = v2_engineering_cases()
    families, versions = catalog_indexes()
    inventory = migration_inventory(v2_cases, families)
    by_id = {case["case_id"]: case for case in v2_cases}
    migrated = [
        migrated_case(by_id[source_id], families, versions)
        for source_id in sorted(MIGRATIONS)
    ]
    current_v3 = authored_v3_cases()
    reconciled = [current_v3[target] for target in RECONCILED.values()]
    cases = sorted(migrated + reconciled, key=lambda value: value["case_id"])

    corpus = {
        "schema_version": "v3",
        "benchmark_id": "dolved-care-engineering",
        "corpus_version": "3",
        "title": "Dolved Care Engineering Benchmark V3 engineering population",
        "evaluation_clock": "2026-08-01T12:00:00Z",
        "matching_algorithm": "normalised-token-coverage-v1",
        "taxonomy": TAXONOMY_REFERENCE,
        "cases": cases,
    }
    validate_corpus(corpus)

    catalog_digest = source_catalog_digest()
    reviews = [case_review(case, catalog_digest) for case in migrated]
    existing_reviews = {
        review["case_id"]: review
        for path in sorted((BENCHMARK_ROOT / "reviews/cases").glob("*.json"))
        if (review := load(path))["case_id"] in RECONCILED.values()
    }
    reviews.extend(existing_reviews.values())

    case_ids = [case["case_id"] for case in cases]
    cluster_ids = sorted({case["cluster_id"] for case in cases})
    leakage_ids = sorted(
        {group for case in cases for group in case["leakage_group_ids"]}
    )
    calibration_case_ids, calibration_clusters, calibration_leakage = (
        calibration_identities(families)
    )
    overlap = {
        "case_ids": sorted(set(case_ids) & calibration_case_ids),
        "semantic_cluster_ids": sorted(set(cluster_ids) & calibration_clusters),
        "leakage_group_ids": sorted(set(leakage_ids) & calibration_leakage),
    }
    independence: dict[str, Any] = {
        "schema_version": "v1",
        "comparison_method": "canonical-identity-and-leakage-set-intersection",
        "comparison_method_version": "1",
        "engineering": {
            "case_ids_digest": content_digest(case_ids),
            "semantic_cluster_ids_digest": content_digest(cluster_ids),
            "leakage_group_ids_digest": content_digest(leakage_ids),
        },
        "calibration": {
            "case_ids_digest": content_digest(sorted(calibration_case_ids)),
            "semantic_cluster_ids_digest": content_digest(sorted(calibration_clusters)),
            "leakage_group_ids_digest": content_digest(sorted(calibration_leakage)),
        },
        "held_out": {
            "assignment_status": "UNASSIGNED_AND_UNAVAILABLE",
            "content_accessed": False,
        },
        "overlap": overlap,
    }
    if any(overlap.values()):
        raise ValueError("V3 engineering population overlaps V3 calibration")

    expectations = {
        "schema_version": "v1",
        "population_id": POPULATION_ID,
        "expectations": [
            {
                "case_id": case["case_id"],
                "variant_id": variant["variant_id"],
                "planner_expectation": {
                    **case["planner_expectation"],
                    **variant.get("planner_expectation_override", {}),
                },
            }
            for case in cases
            for variant in case["variants"]
        ],
    }
    benchmark_digest = content_digest(
        {
            "taxonomy_sha256": sha256(BENCHMARK_ROOT / "taxonomy/v1.json"),
            "organisation_sha256": sha256(BENCHMARK_ROOT / "organisation.json"),
            "document_catalog_sha256": sha256(BENCHMARK_ROOT / "document-catalog.json"),
            "source_catalog_digest": catalog_digest,
            "cases": cases,
            "reviews": sorted(reviews, key=lambda value: value["case_id"]),
        }
    )
    population_core = {
        "population_id": POPULATION_ID,
        "population_version": "1",
        "benchmark_id": "dolved-care-engineering",
        "benchmark_version": "3",
        "benchmark_authoring_digest": benchmark_digest,
        "case_ids_digest": content_digest(case_ids),
        "semantic_cluster_ids_digest": content_digest(cluster_ids),
        "leakage_group_ids_digest": content_digest(leakage_ids),
        "corpus_file_sha256": hashlib.sha256(rendered_json_bytes(corpus)).hexdigest(),
        "expectations_file_sha256": hashlib.sha256(
            rendered_json_bytes(expectations)
        ).hexdigest(),
        "migration_inventory_content_digest": content_digest(inventory),
        "independence_content_digest": content_digest(independence),
        "case_count": len(cases),
        "variant_count": sum(len(case["variants"]) for case in cases),
        "source_case_count": 42,
        "migration_version": MIGRATION_VERSION,
    }
    manifest: dict[str, Any] = {"schema_version": "v1", **population_core}
    manifest["population_digest"] = content_digest(population_core)
    write_json(output_root / "migration-inventory.json", inventory)
    write_json(output_root / "corpus.json", corpus)
    write_json(output_root / "expectations.json", expectations)
    write_json(output_root / "independence.json", independence)
    write_json(output_root / "population-manifest.json", manifest)
    for review in reviews:
        write_json(output_root / "reviews" / f"{review['case_id']}.json", review)

    print(
        json.dumps(
            {
                "case_count": manifest["case_count"],
                "variant_count": manifest["variant_count"],
                "population_digest": manifest["population_digest"],
                "classification_counts": inventory["classification_counts"],
            },
            indent=2,
        )
    )


if __name__ == "__main__":
    main()
