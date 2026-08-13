#!/usr/bin/env python3
"""Build and validate a provider-free calibration population manifest."""

from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path

from app.evaluation.calibration_compatibility import (
    AuthoringReviewEvidence,
    BenchmarkTaxonomyEvidence,
    CalibrationCompatibilityRequirements,
    CalibrationPopulationSpecification,
    PipelineFailureKind,
    PopulationIndependenceEvidence,
    ThresholdExecutionEvidence,
    build_population_manifest,
    classify_pre_threshold_failure,
    evaluate_compatibility,
)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--snapshot", required=True, type=Path)
    parser.add_argument("--threshold-policy", required=True, type=Path)
    parser.add_argument("--requirements", required=True, type=Path)
    parser.add_argument("--population-specification", required=True, type=Path)
    parser.add_argument("--independence-evidence", required=True, type=Path)
    parser.add_argument("--benchmark-taxonomy", required=True, type=Path)
    parser.add_argument("--authoring-review-evidence", required=True, type=Path)
    parser.add_argument("--expected-compatibility-policy-sha256", required=True)
    parser.add_argument("--threshold-execution-evidence", type=Path)
    parser.add_argument("--population-manifest", required=True, type=Path)
    parser.add_argument("--compatibility-result", required=True, type=Path)
    parser.add_argument("--observations", type=Path)
    arguments = parser.parse_args()

    snapshot = json.loads(arguments.snapshot.read_text())
    requirements = CalibrationCompatibilityRequirements.model_validate_json(
        arguments.requirements.read_text()
    )
    specification = CalibrationPopulationSpecification.model_validate_json(
        arguments.population_specification.read_text()
    )
    independence = PopulationIndependenceEvidence.model_validate_json(
        arguments.independence_evidence.read_text()
    )
    benchmark_taxonomy = BenchmarkTaxonomyEvidence.model_validate_json(
        arguments.benchmark_taxonomy.read_text()
    )
    authoring_review = AuthoringReviewEvidence.model_validate_json(
        arguments.authoring_review_evidence.read_text()
    )
    execution_evidence = (
        ThresholdExecutionEvidence.model_validate_json(
            arguments.threshold_execution_evidence.read_text()
        )
        if arguments.threshold_execution_evidence is not None
        else None
    )
    policy_digest = hashlib.sha256(arguments.threshold_policy.read_bytes()).hexdigest()
    population_spec_digest = hashlib.sha256(
        arguments.population_specification.read_bytes()
    ).hexdigest()
    compatibility_policy_digest = hashlib.sha256(
        arguments.requirements.read_bytes()
    ).hexdigest()
    failure_counts: dict[PipelineFailureKind, int] = {}
    if arguments.observations is not None:
        observations_payload = json.loads(arguments.observations.read_text())
        observations = observations_payload.get("observations")
        if not isinstance(observations, list):
            raise ValueError("calibration observations are unavailable")
        expected = {
            (str(case["case_id"]), str(variant["variant_id"]))
            for case in snapshot["cases"]
            for variant in case["variants"]
        }
        observed = {
            (str(item["case"]["case_id"]), str(item["variant"]["variant_id"]))
            for item in observations
        }
        unexpected = observed - expected
        if unexpected:
            raise ValueError("observations contain unexpected calibration identities")
        lost = len(expected - observed)
        if lost:
            failure_counts["lost_evaluation_case"] = lost
        for item in observations:
            failure = classify_pre_threshold_failure(item)
            if failure is not None:
                failure_counts[failure] = failure_counts.get(failure, 0) + 1
    population = build_population_manifest(
        snapshot,
        pipeline_failure_counts=failure_counts,
        independence=independence,
        benchmark_taxonomy=benchmark_taxonomy,
        authoring_review=authoring_review,
    )
    result = evaluate_compatibility(
        requirements,
        specification,
        population,
        threshold_policy_sha256=policy_digest,
        population_spec_sha256=population_spec_digest,
        compatibility_policy_sha256=compatibility_policy_digest,
        expected_compatibility_policy_sha256=(
            arguments.expected_compatibility_policy_sha256
        ),
        execution_evidence=execution_evidence,
    )
    arguments.population_manifest.parent.mkdir(parents=True, exist_ok=True)
    arguments.population_manifest.write_text(
        population.model_dump_json(indent=2) + "\n"
    )
    arguments.compatibility_result.write_text(result.model_dump_json(indent=2) + "\n")
    if not result.compatible:
        for reason in result.failure_reasons:
            print(reason)
        return 1
    print(
        "Calibration population is compatible: "
        f"{population.case_count} cases / {population.variant_count} variants"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
