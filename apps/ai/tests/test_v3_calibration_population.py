import hashlib
import json
import os
from pathlib import Path
from typing import Any

from app.evaluation.calibration_compatibility import (
    CalibrationCompatibilityResult,
    CalibrationPopulationManifest,
    content_digest,
)

EVALUATION_ROOT = Path(os.environ.get("V3_CALIBRATION_EVALUATION_ROOT", "/evaluation"))
POPULATION_ROOT = EVALUATION_ROOT / (
    "calibration-populations/dolved-care-engineering/v3/v1"
)


def load(name: str) -> dict[str, Any]:
    return json.loads((POPULATION_ROOT / name).read_text())


def test_v3_calibration_population_is_frozen_and_composition_compatible() -> None:
    snapshot = load("corpus.json")
    population = CalibrationPopulationManifest.model_validate(
        load("population-manifest.json")
    )
    compatibility = CalibrationCompatibilityResult.model_validate(
        load("composition-compatibility.json")
    )

    assert population.case_count == 44
    assert population.variant_count == 132
    assert compatibility.compatible is True
    assert compatibility.failure_reasons == ()
    assert population.split_case_ids_digest == population.case_ids_digest
    assert population.independence.engineering_overlap_case_count == 0
    assert population.independence.held_out_overlap_case_count == 0
    assert population.independence.split_semantic_cluster_count == 0
    assert population.independence.population_frozen_before_provider_execution is True
    assert population.benchmark_digest == snapshot["benchmark"]["digest"]


def test_v3_threshold_sensitive_cases_are_reranker_evaluable() -> None:
    cases = load("corpus.json")["cases"]
    threshold_sensitive = [
        case
        for case in cases
        if case["threshold_observability"]["classification"] == "THRESHOLD_SENSITIVE"
    ]

    assert len(threshold_sensitive) == 9
    assert all(
        case["threshold_observability"]["reranker_evaluable"] is True
        for case in threshold_sensitive
    )
    assert (
        sum(
            case["outcome_expectation"]["outcome"] == "INSUFFICIENT_EVIDENCE"
            for case in cases
        )
        == 5
    )
    assert (
        sum(
            case["outcome_expectation"]["outcome"] == "COMPARISON_SCOPE_INCOMPLETE"
            for case in cases
        )
        == 4
    )
    assert all(
        case["retrieval_expectation"]["evidence_units"]
        for case in cases
        if case["outcome_expectation"]["outcome"] == "COMPARISON_SCOPE_INCOMPLETE"
    )


def test_v3_population_identity_digests_match_snapshot() -> None:
    snapshot = load("corpus.json")
    population = CalibrationPopulationManifest.model_validate(
        load("population-manifest.json")
    )
    case_ids = sorted(str(case["case_id"]) for case in snapshot["cases"])
    cluster_ids = sorted({str(case["cluster_id"]) for case in snapshot["cases"]})

    assert population.case_ids_digest == content_digest(case_ids)
    assert (
        population.independence.calibration_semantic_cluster_ids_digest
        == content_digest(cluster_ids)
    )
    assert population.independence.engineering_case_ids_digest == content_digest([])
    assert population.independence.held_out_case_ids_digest == content_digest([])
    assert hashlib.sha256((POPULATION_ROOT / "corpus.json").read_bytes()).hexdigest()
