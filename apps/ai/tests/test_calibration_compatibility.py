import hashlib
import json
import os
import subprocess
import sys
from pathlib import Path
from typing import Any, cast

import jsonschema
import pytest

from app.evaluation.calibration_compatibility import (
    AuthoringReviewEvidence,
    BenchmarkTaxonomyEvidence,
    CalibrationCompatibilityRequirements,
    CalibrationCompatibilityResult,
    CalibrationPopulationSpecification,
    PipelineFailureKind,
    PopulationIndependenceEvidence,
    SplitIdentityEvidence,
    ThresholdEvaluationCaseEvidence,
    ThresholdExecutionEvidence,
    build_independence_evidence,
    build_population_manifest,
    classify_pre_threshold_failure,
    evaluate_compatibility,
)

EVALUATION_ROOT = Path(os.environ.get("CAL_COMPAT_EVALUATION_ROOT", "/evaluation"))
FIXTURE = EVALUATION_ROOT / (
    "fixtures/calibration-compatibility/cal-exp-0001-population.json"
)
REQUIREMENTS = EVALUATION_ROOT / (
    "policies/calibration-compatibility/v1/requirements.json"
)
SPECIFICATION = EVALUATION_ROOT / (
    "population-specifications/evidence-threshold-calibration/v1/specification.json"
)
POLICY = EVALUATION_ROOT / "policies/evidence-threshold-calibration/v1/policy.json"
CONTRACTS = Path(os.environ.get("CAL_COMPAT_CONTRACT_ROOT", "/contracts/evaluation/v2"))
WORKSPACE_ROOT = Path(os.environ.get("CAL_COMPAT_WORKSPACE_ROOT", "/workspace"))


def requirements() -> CalibrationCompatibilityRequirements:
    return CalibrationCompatibilityRequirements.model_validate_json(
        REQUIREMENTS.read_text()
    )


def specification() -> CalibrationPopulationSpecification:
    return CalibrationPopulationSpecification.model_validate_json(
        SPECIFICATION.read_text()
    )


def independence(
    snapshot: dict[str, Any] | None = None, **changes: object
) -> PopulationIndependenceEvidence:
    source = snapshot or compatible_snapshot()
    case_ids = sorted(str(case["case_id"]) for case in source["cases"])
    cluster_ids = sorted({str(case["cluster_id"]) for case in source["cases"]})
    base = build_independence_evidence(
        SplitIdentityEvidence(
            case_ids=tuple(case_ids), semantic_cluster_ids=tuple(cluster_ids)
        ),
        SplitIdentityEvidence(
            case_ids=("engineering.case",),
            semantic_cluster_ids=("engineering.cluster",),
        ),
        SplitIdentityEvidence(
            case_ids=("held-out.case",),
            semantic_cluster_ids=("held-out.cluster",),
        ),
        score_driven_selection=False,
        post_result_reassignment=False,
        population_frozen_before_provider_execution=True,
    )
    payload: dict[str, object] = base.model_dump(mode="json")
    payload.update(changes)
    payload["engineering_overlap_case_count"] = len(
        cast(list[object], payload["engineering_overlap_case_ids"])
    )
    payload["held_out_overlap_case_count"] = len(
        cast(list[object], payload["held_out_overlap_case_ids"])
    )
    payload["split_semantic_cluster_count"] = len(
        cast(list[object], payload["split_semantic_cluster_ids"])
    )
    return PopulationIndependenceEvidence.model_validate(payload)


def compatible_snapshot() -> dict[str, Any]:
    spec = specification()
    labels = sorted({label for group in spec.semantic_groups for label in group.labels})
    facets = sorted(
        {
            facet
            for group in spec.semantic_groups
            for facet in group.diversity.required_benchmark_facets
        }
        | {"compare-primary-empty", "compare-comparison-empty"}
    )
    outcomes = [
        outcome.outcome
        for outcome in spec.controlled_rejection.threshold_sensitive_outcomes
        for _ in range(outcome.minimum_case_count)
    ]
    domains = [
        "medication",
        "safeguarding",
        "infection-control",
        "fire-safety",
        "health-safety",
        "gdpr",
        "complaints",
        "hr",
        "payroll",
        "training",
        "visitors",
    ]
    cases: list[dict[str, Any]] = []
    for index in range(spec.preferred_case_count_minimum):
        outcome = outcomes[index] if index < len(outcomes) else "EVIDENCE_FOUND"
        cases.append(
            {
                "case_id": f"case.population.{index:02}",
                "cluster_id": f"cluster.population.{index:02}",
                "domain": domains[index % len(domains)],
                "document_family_ids": [
                    f"family.population.{index:02}.a",
                    f"family.population.{index:02}.b",
                ],
                "evaluation_facets": facets,
                "variants": [{"variant_id": "direct"}],
                "slices": labels,
                "outcome_expectation": {"outcome": outcome},
                "retrieval_expectation": {
                    "evidence_units": [
                        {
                            "evidence_id": f"evidence.population.{index:02}",
                            "source_path": f"documents/population-{index:02}.md",
                        }
                    ]
                },
            }
        )
    case_ids = sorted(item["case_id"] for item in cases)
    return {
        "benchmark": {
            "id": "dolved-care-engineering",
            "version": "v3-test",
            "digest": "a" * 64,
        },
        "split": {
            "name": "threshold_calibration",
            "version": "test-2",
            "case_ids_digest": _digest(case_ids),
        },
        "cases": cases,
    }


def historical_snapshot() -> dict[str, Any]:
    snapshot = json.loads(FIXTURE.read_text())
    for case in snapshot["cases"]:
        case["cluster_id"] = f"legacy.{case['case_id']}"
        case["domain"] = case["case_id"].split(".", maxsplit=1)[0]
        case["document_family_ids"] = [f"legacy-family.{case['case_id']}"]
        case["evaluation_facets"] = []
    return snapshot


def taxonomy(
    snapshot: dict[str, Any] | None = None, *, omit: str | None = None
) -> BenchmarkTaxonomyEvidence:
    slices = sorted(
        {label for group in specification().semantic_groups for label in group.labels}
        | {
            str(label)
            for case in (snapshot or compatible_snapshot())["cases"]
            for label in case["slices"]
        }
    )
    facets = sorted(
        {
            facet
            for group in specification().semantic_groups
            for facet in group.diversity.required_benchmark_facets
            if facet != omit
        }
        | {"compare-primary-empty", "compare-comparison-empty"}
    )
    return BenchmarkTaxonomyEvidence(
        intrinsic_slices_schema_version="v1",
        declared_intrinsic_slices=tuple(slices),
        intrinsic_slices_digest=_digest(slices),
        evaluation_facets_schema_version="v1",
        declared_evaluation_facets=tuple(facets),
        evaluation_facets_digest=_digest(facets),
    )


def authoring_review(snapshot: dict[str, Any]) -> AuthoringReviewEvidence:
    case_ids = sorted(str(case["case_id"]) for case in snapshot["cases"])
    return AuthoringReviewEvidence(
        review_artifact_id="calibration-population-authoring-review-v1",
        review_artifact_version="1",
        review_artifact_digest="f" * 64,
        reviewed_case_ids_digest=_digest(case_ids),
        semantic_quality_reviewed=True,
        representative_coverage_reviewed=True,
        author_rationale_reviewed=True,
        governance_reviewed=True,
    )


def execution(
    snapshot: dict[str, Any], *, complete: bool = True, one_score: bool = False
) -> ThresholdExecutionEvidence:
    return ThresholdExecutionEvidence(
        cases=tuple(
            ThresholdEvaluationCaseEvidence(
                case_id=case["case_id"],
                complete_pre_threshold_lineage=complete,
                reranker_scores=(0.5,) if one_score else (index / 100, 0.9),
            )
            for index, case in enumerate(snapshot["cases"], start=1)
        )
    )


def result(
    snapshot: dict[str, Any],
    *,
    failures: dict[PipelineFailureKind, int] | None = None,
    independence_evidence: PopulationIndependenceEvidence | None = None,
    taxonomy_evidence: BenchmarkTaxonomyEvidence | None = None,
    execution_evidence: ThresholdExecutionEvidence | None = None,
) -> CalibrationCompatibilityResult:
    population = build_population_manifest(
        snapshot,
        pipeline_failure_counts=failures,
        independence=independence_evidence or independence(snapshot),
        benchmark_taxonomy=taxonomy_evidence or taxonomy(snapshot),
        authoring_review=authoring_review(snapshot),
    )
    return evaluate_compatibility(
        requirements(),
        specification(),
        population,
        threshold_policy_sha256=_file_digest(POLICY),
        population_spec_sha256=_file_digest(SPECIFICATION),
        compatibility_policy_sha256=_file_digest(REQUIREMENTS),
        expected_compatibility_policy_sha256=_file_digest(REQUIREMENTS),
        execution_evidence=execution_evidence,
    )


def test_contract_models_match_repository_json_schemas() -> None:
    schema_files = {
        REQUIREMENTS: "calibration-compatibility-requirements.schema.json",
        SPECIFICATION: "calibration-population-specification.schema.json",
    }
    for payload_file, schema_file in schema_files.items():
        jsonschema.validate(
            json.loads(payload_file.read_text()),
            json.loads((CONTRACTS / schema_file).read_text()),
        )
    population = build_population_manifest(
        compatible_snapshot(),
        independence=independence(compatible_snapshot()),
        benchmark_taxonomy=taxonomy(),
        authoring_review=authoring_review(compatible_snapshot()),
    )
    jsonschema.validate(
        population.model_dump(mode="json"),
        json.loads(
            (CONTRACTS / "calibration-population-manifest.schema.json").read_text()
        ),
    )
    observed = result(compatible_snapshot())
    jsonschema.validate(
        observed.model_dump(mode="json"),
        json.loads(
            (CONTRACTS / "calibration-compatibility-result.schema.json").read_text()
        ),
    )
    jsonschema.validate(
        execution(compatible_snapshot()).model_dump(mode="json"),
        json.loads(
            (
                CONTRACTS / "calibration-threshold-execution-evidence.schema.json"
            ).read_text()
        ),
    )
    jsonschema.validate(
        independence(compatible_snapshot()).model_dump(mode="json"),
        json.loads(
            (CONTRACTS / "calibration-independence-evidence.schema.json").read_text()
        ),
    )


def test_cal_exp_0001_population_remains_incompatible() -> None:
    observed = result(
        historical_snapshot(), failures={"planner_failure_before_threshold": 1}
    )

    assert observed.compatible is False
    by_id = {item.requirement_id: item for item in observed.slice_results}
    assert by_id["adversarial-v1"].available_case_count == 0
    assert by_id["zero-evidence-v1"].available_case_count == 0
    assert observed.controlled_rejection.available_threshold_sensitive_case_count == 0
    assert observed.controlled_rejection.metric_available is False
    assert observed.pipeline_failures.observed_counts == {
        "planner_failure_before_threshold": 1
    }


def test_compatible_population_passes_deterministically() -> None:
    first = result(compatible_snapshot())
    second = result(compatible_snapshot())

    assert first.compatible is True
    assert first.failure_reasons == ()
    assert first.controlled_rejection.metric_available is True
    assert first.threshold_execution.evaluated is False
    assert first.model_dump_json() == second.model_dump_json()


def test_missing_required_group_fails() -> None:
    snapshot = compatible_snapshot()
    for case in snapshot["cases"]:
        case["slices"] = [label for label in case["slices"] if label != "adversarial"]

    observed = result(snapshot)

    assert observed.compatible is False
    assert any(
        value.startswith("slice_requirement:adversarial-v1")
        for value in observed.failure_reasons
    )


def test_split_digest_mismatch_fails() -> None:
    snapshot = compatible_snapshot()
    snapshot["split"]["case_ids_digest"] = "0" * 64

    with pytest.raises(ValueError, match="declared split digest"):
        result(snapshot)


def test_declared_but_unused_required_facet_fails() -> None:
    snapshot = compatible_snapshot()
    for case in snapshot["cases"]:
        case["evaluation_facets"] = [
            facet for facet in case["evaluation_facets"] if facet != "keyword-stuffing"
        ]

    observed = result(snapshot)

    assert observed.compatible is False
    assert any(
        value.startswith("benchmark_facet_coverage:adversarial-v1")
        for value in observed.failure_reasons
    )


def test_insufficient_controlled_rejection_fails() -> None:
    snapshot = compatible_snapshot()
    for case in snapshot["cases"]:
        case["outcome_expectation"] = {"outcome": "EVIDENCE_FOUND"}

    observed = result(snapshot)

    assert observed.compatible is False
    assert observed.controlled_rejection.available_threshold_sensitive_case_count == 0
    assert observed.controlled_rejection.metric_available is False


def test_acceptance_only_outcomes_do_not_satisfy_threshold_calibration() -> None:
    snapshot = compatible_snapshot()
    acceptance_outcomes = specification().controlled_rejection.acceptance_only_outcomes
    for index, case in enumerate(snapshot["cases"]):
        case["outcome_expectation"] = {
            "outcome": acceptance_outcomes[index % len(acceptance_outcomes)]
        }

    observed = result(snapshot)

    assert observed.compatible is False
    assert observed.controlled_rejection.available_threshold_sensitive_case_count == 0
    assert observed.controlled_rejection.metric_available is False


def test_complete_threshold_execution_evidence_passes() -> None:
    snapshot = compatible_snapshot()
    observed = result(snapshot, execution_evidence=execution(snapshot))

    assert observed.compatible is True
    assert observed.threshold_execution.evaluated is True
    assert observed.threshold_execution.compatible is True


def test_missing_reranker_lineage_fails_execution_compatibility() -> None:
    snapshot = compatible_snapshot()
    observed = result(snapshot, execution_evidence=execution(snapshot, complete=False))

    assert observed.compatible is False
    assert observed.threshold_execution.compatible is False
    assert any(
        "incomplete_pre_threshold_lineage" in reason
        for reason in observed.threshold_execution.failure_reasons
    )


def test_acceptance_only_cases_do_not_require_reranker_lineage() -> None:
    snapshot = compatible_snapshot()
    snapshot["cases"][-1]["outcome_expectation"] = {"outcome": "NO_ELIGIBLE_EVIDENCE"}
    evidence = execution(snapshot)
    evidence = ThresholdExecutionEvidence(
        cases=tuple(
            item for item in evidence.cases if item.case_id != "case.population.43"
        )
    )

    observed = result(snapshot, execution_evidence=evidence)

    assert observed.compatible is True
    assert observed.threshold_execution.compatible is True


def test_unusable_score_distribution_fails_execution_compatibility() -> None:
    snapshot = compatible_snapshot()
    observed = result(snapshot, execution_evidence=execution(snapshot, one_score=True))

    assert observed.compatible is False
    assert "threshold_execution:distinct_reranker_scores:required=2:available=1" in (
        observed.threshold_execution.failure_reasons
    )


def test_population_taxonomy_drift_fails() -> None:
    snapshot = compatible_snapshot()
    for case in snapshot["cases"]:
        case["evaluation_facets"] = [
            facet for facet in case["evaluation_facets"] if facet != "keyword-stuffing"
        ]
    observed = result(snapshot, taxonomy_evidence=taxonomy(omit="keyword-stuffing"))

    assert observed.compatible is False
    assert any(
        reason.startswith("benchmark_taxonomy_drift:adversarial-v1")
        for reason in observed.failure_reasons
    )


def test_required_slice_taxonomy_drift_fails() -> None:
    snapshot = compatible_snapshot()
    for case in snapshot["cases"]:
        case["slices"] = [item for item in case["slices"] if item != "adversarial"]
    taxonomy_evidence = taxonomy().model_copy(
        update={
            "declared_intrinsic_slices": tuple(
                item
                for item in taxonomy().declared_intrinsic_slices
                if item != "adversarial"
            ),
            "intrinsic_slices_digest": _digest(
                [
                    item
                    for item in taxonomy().declared_intrinsic_slices
                    if item != "adversarial"
                ]
            ),
        }
    )

    observed = result(snapshot, taxonomy_evidence=taxonomy_evidence)

    assert observed.compatible is False
    assert any(
        value.startswith("benchmark_taxonomy_missing_required_labels:adversarial")
        for value in observed.failure_reasons
    )


def test_independence_evidence_digest_mismatch_fails() -> None:
    snapshot = compatible_snapshot()
    invalid = independence(snapshot).model_copy(
        update={"calibration_case_ids_digest": "0" * 64}
    )

    with pytest.raises(ValueError, match="independence calibration case digest"):
        result(snapshot, independence_evidence=invalid)


def test_independence_comparison_detects_case_and_cluster_overlap() -> None:
    evidence = build_independence_evidence(
        SplitIdentityEvidence(
            case_ids=("calibration.one", "calibration.two"),
            semantic_cluster_ids=("cluster.one", "cluster.two"),
        ),
        SplitIdentityEvidence(
            case_ids=("calibration.one",), semantic_cluster_ids=("cluster.two",)
        ),
        SplitIdentityEvidence(
            case_ids=("calibration.two",), semantic_cluster_ids=("cluster.three",)
        ),
        score_driven_selection=False,
        post_result_reassignment=False,
        population_frozen_before_provider_execution=True,
    )

    assert evidence.engineering_overlap_case_ids == ("calibration.one",)
    assert evidence.held_out_overlap_case_ids == ("calibration.two",)
    assert evidence.split_semantic_cluster_ids == ("cluster.two",)
    assert evidence.engineering_overlap_case_count == 1
    assert evidence.held_out_overlap_case_count == 1
    assert evidence.split_semantic_cluster_count == 1


def test_compatibility_policy_digest_mismatch_fails() -> None:
    snapshot = compatible_snapshot()
    population = build_population_manifest(
        snapshot,
        independence=independence(snapshot),
        benchmark_taxonomy=taxonomy(),
        authoring_review=authoring_review(snapshot),
    )

    observed = evaluate_compatibility(
        requirements(),
        specification(),
        population,
        threshold_policy_sha256=_file_digest(POLICY),
        population_spec_sha256=_file_digest(SPECIFICATION),
        compatibility_policy_sha256=_file_digest(REQUIREMENTS),
        expected_compatibility_policy_sha256="0" * 64,
    )

    assert observed.compatible is False
    assert "compatibility_policy_digest_mismatch" in observed.failure_reasons


def test_engineering_overlap_fails() -> None:
    observed = result(
        compatible_snapshot(),
        independence_evidence=independence(
            engineering_overlap_case_ids=["case.population.00"]
        ),
    )

    assert observed.compatible is False
    assert "independence:engineering_overlap" in observed.failure_reasons


def test_held_out_overlap_fails() -> None:
    observed = result(
        compatible_snapshot(),
        independence_evidence=independence(
            held_out_overlap_case_ids=["case.population.00"]
        ),
    )

    assert observed.compatible is False
    assert "independence:held_out_overlap" in observed.failure_reasons


def test_domain_imbalance_fails() -> None:
    snapshot = compatible_snapshot()
    for case in snapshot["cases"]:
        case["domain"] = "medication"

    observed = result(snapshot)

    assert observed.compatible is False
    assert any(
        value.startswith("domain_imbalance:") for value in observed.failure_reasons
    )


def test_lost_or_observed_pre_threshold_failure_fails_distinctly() -> None:
    snapshot = compatible_snapshot()
    lost = result(snapshot, failures={"lost_evaluation_case": 1})
    planner = result(snapshot, failures={"planner_failure_before_threshold": 1})

    assert lost.pipeline_failures.observed_counts == {"lost_evaluation_case": 1}
    assert planner.pipeline_failures.observed_counts == {
        "planner_failure_before_threshold": 1
    }
    assert not lost.compatible and not planner.compatible


def test_failure_classification_is_privacy_safe_and_distinct() -> None:
    assert (
        classify_pre_threshold_failure(
            {"planning": {"status": "failed", "failure_category": "invalid_typed_plan"}}
        )
        == "planner_failure_before_threshold"
    )
    assert (
        classify_pre_threshold_failure(
            {
                "planning": {
                    "status": "failed",
                    "failure_category": "provider_rate_limit",
                }
            }
        )
        == "provider_failure_before_threshold"
    )


def test_manifest_contains_governance_metadata_not_questions_or_evidence() -> None:
    payload = build_population_manifest(
        compatible_snapshot(),
        independence=independence(compatible_snapshot()),
        benchmark_taxonomy=taxonomy(),
        authoring_review=authoring_review(compatible_snapshot()),
    ).model_dump_json()

    assert "question" not in payload
    assert "canonical_excerpts" not in payload
    assert "document_version_id" not in payload
    assert "cluster_id" in payload and "domain_case_counts" in payload
    assert "evaluation_facets" in payload and "diversity_tags" not in payload


def test_provider_free_command_respects_population_contract(tmp_path: Path) -> None:
    snapshot = tmp_path / "compatible-snapshot.json"
    snapshot.write_text(json.dumps(compatible_snapshot()))

    passed = _run_command(snapshot, tmp_path / "pass", independence())
    failed = _run_command(
        snapshot,
        tmp_path / "fail",
        independence(held_out_overlap_case_ids=["case.population.00"]),
    )

    assert passed.returncode == 0
    assert failed.returncode == 1


def _run_command(
    snapshot: Path,
    output: Path,
    independence_evidence: PopulationIndependenceEvidence,
) -> subprocess.CompletedProcess[str]:
    output.mkdir(parents=True)
    evidence = output / "independence.json"
    evidence.write_text(independence_evidence.model_dump_json())
    taxonomy_file = output / "taxonomy.json"
    taxonomy_file.write_text(taxonomy().model_dump_json())
    review_file = output / "authoring-review.json"
    review_file.write_text(authoring_review(compatible_snapshot()).model_dump_json())
    return subprocess.run(
        [
            sys.executable,
            str(
                WORKSPACE_ROOT
                / "scripts/evaluation/validate_calibration_compatibility.py"
            ),
            "--snapshot",
            str(snapshot),
            "--threshold-policy",
            str(POLICY),
            "--requirements",
            str(REQUIREMENTS),
            "--population-specification",
            str(SPECIFICATION),
            "--independence-evidence",
            str(evidence),
            "--benchmark-taxonomy",
            str(taxonomy_file),
            "--authoring-review-evidence",
            str(review_file),
            "--expected-compatibility-policy-sha256",
            _file_digest(REQUIREMENTS),
            "--population-manifest",
            str(output / "population.json"),
            "--compatibility-result",
            str(output / "compatibility.json"),
        ],
        check=False,
        capture_output=True,
        text=True,
        env={**os.environ, "PYTHONPATH": "/app"},
    )


def _digest(value: object) -> str:
    encoded = json.dumps(value, sort_keys=True, separators=(",", ":")).encode()
    return hashlib.sha256(encoded).hexdigest()


def _file_digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()
