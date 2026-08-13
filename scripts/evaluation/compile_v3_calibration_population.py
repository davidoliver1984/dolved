"""Compile the frozen Benchmark V3 calibration population provider-free."""

from __future__ import annotations

import hashlib
import json
import sys
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "apps/ai"))

from app.evaluation.calibration_compatibility import (
    AuthoringReviewEvidence,
    BenchmarkTaxonomyEvidence,
    CalibrationCompatibilityRequirements,
    CalibrationPopulationSpecification,
    SplitIdentityEvidence,
    build_independence_evidence,
    build_population_manifest,
    content_digest,
    evaluate_compatibility,
)

POPULATION = (
    ROOT / "tests/evaluation/calibration-populations/dolved-care-engineering/v3/v1"
)
EVALUATION = ROOT / "tests/evaluation"


def load(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text())


def file_digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main() -> None:
    snapshot = load(POPULATION / "corpus.json")
    review = load(POPULATION / "authoring-review.json")
    taxonomy = load(
        EVALUATION / "benchmarks/dolved-care-engineering/v3/taxonomy/v1.json"
    )
    case_ids = tuple(sorted(str(case["case_id"]) for case in snapshot["cases"]))
    cluster_ids = tuple(sorted({str(case["cluster_id"]) for case in snapshot["cases"]}))
    independence = build_independence_evidence(
        SplitIdentityEvidence(case_ids=case_ids, semantic_cluster_ids=cluster_ids),
        SplitIdentityEvidence(case_ids=(), semantic_cluster_ids=()),
        SplitIdentityEvidence(case_ids=(), semantic_cluster_ids=()),
        score_driven_selection=False,
        post_result_reassignment=False,
        population_frozen_before_provider_execution=True,
    )
    slices = tuple(
        sorted(str(item["slice_id"]) for item in taxonomy["intrinsic_slices"])
    )
    facets = tuple(
        sorted(str(item["facet_id"]) for item in taxonomy["evaluation_facets"])
    )
    taxonomy_evidence = BenchmarkTaxonomyEvidence(
        intrinsic_slices_schema_version="v3-taxonomy-1",
        declared_intrinsic_slices=slices,
        intrinsic_slices_digest=content_digest(list(slices)),
        evaluation_facets_schema_version="v3-taxonomy-1",
        declared_evaluation_facets=facets,
        evaluation_facets_digest=content_digest(list(facets)),
    )
    authoring_review = AuthoringReviewEvidence(
        review_artifact_id=str(review["review_artifact_id"]),
        review_artifact_version=str(review["review_artifact_version"]),
        review_artifact_digest=str(review["review_artifact_digest"]),
        reviewed_case_ids_digest=str(review["reviewed_case_ids_digest"]),
        semantic_quality_reviewed=bool(review["semantic_quality_reviewed"]),
        representative_coverage_reviewed=bool(
            review["representative_coverage_reviewed"]
        ),
        author_rationale_reviewed=bool(review["author_rationale_reviewed"]),
        governance_reviewed=bool(review["governance_reviewed"]),
    )
    population = build_population_manifest(
        snapshot,
        independence=independence,
        benchmark_taxonomy=taxonomy_evidence,
        authoring_review=authoring_review,
    )
    requirements_path = (
        EVALUATION / "policies/calibration-compatibility/v1/requirements.json"
    )
    specification_path = (
        EVALUATION
        / "population-specifications/evidence-threshold-calibration/v1/specification.json"
    )
    threshold_policy_path = (
        EVALUATION / "policies/evidence-threshold-calibration/v1/policy.json"
    )
    requirements = CalibrationCompatibilityRequirements.model_validate(
        load(requirements_path)
    )
    specification = CalibrationPopulationSpecification.model_validate(
        load(specification_path)
    )
    compatibility = evaluate_compatibility(
        requirements,
        specification,
        population,
        threshold_policy_sha256=file_digest(threshold_policy_path),
        population_spec_sha256=file_digest(specification_path),
        compatibility_policy_sha256=file_digest(requirements_path),
        expected_compatibility_policy_sha256=file_digest(requirements_path),
    )
    (POPULATION / "population-manifest.json").write_text(
        json.dumps(population.model_dump(mode="json"), indent=2) + "\n"
    )
    (POPULATION / "composition-compatibility.json").write_text(
        json.dumps(compatibility.model_dump(mode="json"), indent=2) + "\n"
    )
    print(
        json.dumps(
            {
                "case_count": population.case_count,
                "variant_count": population.variant_count,
                "compatible": compatibility.compatible,
                "failures": compatibility.failure_reasons,
            },
            indent=2,
        )
    )


if __name__ == "__main__":
    main()
