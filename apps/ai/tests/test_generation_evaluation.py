import hashlib
import json
import os
from pathlib import Path

import pytest
from pydantic import ValidationError

from app.evaluation.generation_evaluation import (
    GenerationEvaluationCase,
    GenerationEvaluationResult,
    RecordedGenerationObservation,
    aggregate_generation_observations,
    build_generation_request,
    deterministic_evaluate,
    evaluate_generation_case,
    load_generation_population,
    reevaluate_recorded_generation,
)
from app.evaluation.generation_reporting import (
    render_markdown,
    write_generation_evaluation_artifacts,
)
from app.evaluation.model_assisted import FakeModelAssistedEvaluator
from app.evaluation.models import (
    ModelAssistedEvaluationRequest,
    ModelAssistedEvaluationResult,
    ModelAssistedMetric,
    ModelAssistedMetricObservation,
    ModelAssistedStatus,
)
from app.generation.models import AnswerPart, GenerationOutcome, GenerationResult

POPULATION = Path(
    os.environ.get(
        "GENERATION_EVALUATION_POPULATION",
        "/generation-evaluation/populations/grounded-generation-v1.json",
    )
)
BASELINE_OBSERVATIONS = Path(
    os.environ.get(
        "GENERATION_EVALUATION_BASELINE_OBSERVATIONS",
        "/evaluation-runs/GEN-EXP-0001-grounded-generation-baseline/application-observations.json",
    )
)


def test_population_is_bounded_diverse_and_deterministic() -> None:
    first = load_generation_population(POPULATION)
    second = load_generation_population(POPULATION)
    assert len(first.cases) == 13
    assert first.digest() == second.digest()
    assert first.generation_fingerprint == (
        "40a18f357fbc864ff54781e607300c3374dd65829563fc2b334a2876de19b2f5"
    )
    outcomes = [case.expected_outcome for case in first.cases]
    assert outcomes.count(GenerationOutcome.ANSWERED) == 6
    assert outcomes.count(GenerationOutcome.QUALIFIED) == 5
    assert outcomes.count(GenerationOutcome.INSUFFICIENT_EVIDENCE) == 2
    surfaces = {surface for case in first.cases for surface in case.surfaces}
    assert {
        "hostile-evidence",
        "multi-document",
        "compare",
        "historical",
        "modality",
        "count",
        "duration",
    }.issubset(surfaces)


def test_population_rejects_incoherent_expected_outcome() -> None:
    population = load_generation_population(POPULATION)
    value = population.cases[0].model_dump(mode="json")
    value["expected_outcome"] = "answered"
    with pytest.raises(ValidationError):
        GenerationEvaluationCase.model_validate(value)


def test_request_assembly_is_deterministic_and_preserves_compare_sides() -> None:
    population = load_generation_population(POPULATION)
    case = next(item for item in population.cases if "compare" in item.surfaces)
    first = build_generation_request(population, case)
    second = build_generation_request(population, case)
    assert first == second
    assert tuple(side.value for side in first.constraints.required_sides) == (
        "primary",
        "comparison",
    )
    assert [item.evidence_id for item in first.evidence] == ["ev-01", "ev-02"]


def test_deterministic_evaluation_detects_citation_overrefusal_and_leakage() -> None:
    population = load_generation_population(POPULATION)
    case = population.cases[0]
    request = build_generation_request(population, case)
    over_refusal = GenerationResult(
        outcome=GenerationOutcome.INSUFFICIENT_EVIDENCE,
        unsupported_aspects=("fixed number of hours",),
        insufficiency_reason="The exact duration is unavailable.",
    )
    refused = deterministic_evaluate(case, request, over_refusal)
    assert refused.over_refusal
    assert not refused.outcome_correct

    leaked = GenerationResult(
        outcome=GenerationOutcome.QUALIFIED,
        answer_parts=(
            AnswerPart(
                text="Evidence 2 says the medicine remains held.",
                evidence_ids=("ev-99",),
            ),
        ),
        unsupported_aspects=("fixed duration",),
    )
    evaluated = deterministic_evaluate(case, request, leaked)
    assert not evaluated.citation_membership_correct
    assert evaluated.internal_identifier_leakage


@pytest.mark.asyncio
async def test_semantic_wiring_preserves_complete_multi_document_result() -> None:
    population = load_generation_population(POPULATION)
    case = next(item for item in population.cases if "multi-document" in item.surfaces)
    request = build_generation_request(population, case)
    result = GenerationResult(
        outcome=GenerationOutcome.ANSWERED,
        answer_parts=(
            AnswerPart(text="Isolate the area immediately.", evidence_ids=("ev-01",)),
            AnswerPart(
                text="Complete the record before the end of the shift.",
                evidence_ids=("ev-02",),
            ),
        ),
    )

    class RecordingEvaluator:
        def __init__(self) -> None:
            self.requests: list[ModelAssistedEvaluationRequest] = []

        async def evaluate(
            self, evaluation: ModelAssistedEvaluationRequest
        ) -> ModelAssistedEvaluationResult:
            self.requests.append(evaluation)
            return ModelAssistedEvaluationResult(
                case_id=evaluation.case_id,
                variant_id=evaluation.variant_id,
                status=ModelAssistedStatus.COMPLETED,
                scores={metric: 1.0 for metric in evaluation.metrics},
                evaluator_identity={"implementation": "recording-fake"},
            )

    evaluator = RecordingEvaluator()
    observation = await evaluate_generation_case(
        case=case, request=request, result=result, evaluator=evaluator
    )
    assert not observation.answer_part_evaluations
    assert len(evaluator.requests) == 1
    evaluation = evaluator.requests[0]
    assert tuple(item.candidate_id for item in evaluation.retrieved_evidence) == (
        "ev-01",
        "ev-02",
    )
    assert evaluation.metrics == (
        ModelAssistedMetric.ANSWER_PART_GROUNDEDNESS,
        ModelAssistedMetric.ANSWER_FACTUAL_PRECISION,
        ModelAssistedMetric.ANSWER_COMPLETENESS,
    )
    assert evaluation.generated_result is not None
    assert [part.evidence_ids for part in evaluation.generated_result.answer_parts] == [
        ("ev-01",),
        ("ev-02",),
    ]


@pytest.mark.asyncio
async def test_qualified_representation_includes_unsupported_aspects() -> None:
    population = load_generation_population(POPULATION)
    case = population.cases[0]
    request = build_generation_request(population, case)
    result = GenerationResult(
        outcome=GenerationOutcome.QUALIFIED,
        answer_parts=(
            AnswerPart(
                text="Stock remains quarantined until pharmacy advice is recorded.",
                evidence_ids=("ev-01",),
            ),
        ),
        unsupported_aspects=("fixed quarantine duration",),
    )

    class RecordingEvaluator(FakeModelAssistedEvaluator):
        captured: ModelAssistedEvaluationRequest | None = None

        async def evaluate(
            self, evaluation: ModelAssistedEvaluationRequest
        ) -> ModelAssistedEvaluationResult:
            self.captured = evaluation
            return await super().evaluate(evaluation)

    evaluator = RecordingEvaluator()
    await evaluate_generation_case(
        case=case, request=request, result=result, evaluator=evaluator
    )
    assert evaluator.captured is not None
    assert evaluator.captured.generated_result is not None
    assert evaluator.captured.generated_result.outcome == "qualified"
    assert evaluator.captured.generated_result.unsupported_aspects == (
        "fixed quarantine duration",
    )
    assert evaluator.captured.reference_unsupported_aspects == (
        "fixed number of hours",
    )


@pytest.mark.asyncio
async def test_insufficiency_representation_has_no_answer_parts() -> None:
    population = load_generation_population(POPULATION)
    case = population.cases[3]
    request = build_generation_request(population, case)
    result = GenerationResult(
        outcome=GenerationOutcome.INSUFFICIENT_EVIDENCE,
        unsupported_aspects=("dismissal process",),
        insufficiency_reason="The evidence does not address dismissal.",
    )

    class RecordingEvaluator(FakeModelAssistedEvaluator):
        captured: ModelAssistedEvaluationRequest | None = None

        async def evaluate(
            self, evaluation: ModelAssistedEvaluationRequest
        ) -> ModelAssistedEvaluationResult:
            self.captured = evaluation
            return await super().evaluate(evaluation)

    evaluator = RecordingEvaluator()
    await evaluate_generation_case(
        case=case, request=request, result=result, evaluator=evaluator
    )
    assert evaluator.captured is not None
    assert evaluator.captured.metrics == (
        ModelAssistedMetric.INSUFFICIENCY_CORRECTNESS,
    )
    assert evaluator.captured.generated_result is not None
    assert evaluator.captured.generated_result.answer_parts == ()


@pytest.mark.asyncio
async def test_aggregate_reports_confusion_and_advisory_semantic_scores() -> None:
    population = load_generation_population(POPULATION)
    case = population.cases[2]
    request = build_generation_request(population, case)
    result = GenerationResult(
        outcome=GenerationOutcome.ANSWERED,
        answer_parts=(
            AnswerPart(text=case.reference_answer or "", evidence_ids=("ev-01",)),
        ),
    )
    observation = await evaluate_generation_case(
        case=case,
        request=request,
        result=result,
        evaluator=FakeModelAssistedEvaluator(1.0),
    )
    aggregate = aggregate_generation_observations((observation,), (case,))
    assert aggregate.outcome_accuracy == 1.0
    assert aggregate.confusion_matrix["answered"]["answered"] == 1
    assert aggregate.groundedness_mean == 1.0
    assert aggregate.completeness_mean == 1.0
    assert aggregate.unsupported_claim_rate == 0.0
    assert aggregate.total_answer_parts == 1
    assert aggregate.successfully_scored_answer_parts == 1
    assert aggregate.failed_evaluator_answer_parts == 0
    assert aggregate.metric_coverage["ANSWER_PART_GROUNDEDNESS"].coverage == 1.0


@pytest.mark.asyncio
async def test_failed_evaluator_does_not_shrink_answer_part_denominator() -> None:
    population = load_generation_population(POPULATION)
    case = next(item for item in population.cases if "multi-document" in item.surfaces)
    request = build_generation_request(population, case)
    generated = GenerationResult(
        outcome=GenerationOutcome.ANSWERED,
        answer_parts=(
            AnswerPart(text="Isolate the area.", evidence_ids=("ev-01",)),
            AnswerPart(text="Complete the record.", evidence_ids=("ev-02",)),
        ),
    )

    class FailedEvaluator:
        async def evaluate(
            self, evaluation: ModelAssistedEvaluationRequest
        ) -> ModelAssistedEvaluationResult:
            return ModelAssistedEvaluationResult(
                case_id=evaluation.case_id,
                variant_id=evaluation.variant_id,
                status=ModelAssistedStatus.FAILED,
                evaluator_identity={"implementation": "failed-fake"},
                failure_code="provider_failure",
                metric_observations=tuple(
                    ModelAssistedMetricObservation(
                        metric=metric,
                        status=ModelAssistedStatus.FAILED,
                        failure_code="provider_failure",
                    )
                    for metric in evaluation.metrics
                ),
            )

    observation = await evaluate_generation_case(
        case=case,
        request=request,
        result=generated,
        evaluator=FailedEvaluator(),
    )
    aggregate = aggregate_generation_observations((observation,), (case,))
    assert aggregate.total_answer_parts == 2
    assert aggregate.successfully_scored_answer_parts == 0
    assert aggregate.failed_evaluator_answer_parts == 2
    assert aggregate.unevaluable_answer_parts == 0
    coverage = aggregate.metric_coverage["ANSWER_PART_GROUNDEDNESS"]
    assert (coverage.eligible, coverage.scored, coverage.failed) == (2, 0, 2)
    assert observation.deterministic.outcome_correct


@pytest.mark.asyncio
async def test_report_regeneration_is_deterministic(tmp_path: Path) -> None:
    population = load_generation_population(POPULATION)
    case = population.cases[2]
    request = build_generation_request(population, case)
    generated = GenerationResult(
        outcome=GenerationOutcome.ANSWERED,
        answer_parts=(
            AnswerPart(text=case.reference_answer or "", evidence_ids=("ev-01",)),
        ),
    )
    observation = await evaluate_generation_case(
        case=case,
        request=request,
        result=generated,
        evaluator=FakeModelAssistedEvaluator(1.0),
    )
    result = GenerationEvaluationResult.model_validate(
        {
            "experiment_id": "GEN-EXP-TEST",
            "executed_at": "2026-08-17T12:00:00Z",
            "repository_commit": "d52339f",
            "population_id": population.population_id,
            "population_digest": population.digest(),
            "generation_lineage": {"fingerprint": population.generation_fingerprint},
            "evaluator_lineage": {
                "implementation": "deterministic-fake",
                "model": "none",
            },
            "observations": [observation.model_dump(mode="json")],
            "aggregate": aggregate_generation_observations((observation,), (case,)),
        }
    )
    first = write_generation_evaluation_artifacts(
        output_dir=tmp_path / "first",
        result=result,
        population=population,
        config={"run_id": "GEN-EXP-TEST"},
    )
    second = write_generation_evaluation_artifacts(
        output_dir=tmp_path / "second",
        result=result,
        population=population,
        config={"run_id": "GEN-EXP-TEST"},
    )
    assert first == second
    assert (
        render_markdown(result, population)
        == (tmp_path / "first/report.md").read_text()
    )


@pytest.mark.asyncio
async def test_frozen_generation_observations_are_reevaluated_without_generation() -> (
    None
):
    population = load_generation_population(POPULATION)
    raw = BASELINE_OBSERVATIONS.read_bytes()
    assert hashlib.sha256(raw).hexdigest() == (
        "bee2cdf0ddfda896cf23f8c3a27ec7b250db9d202180ed52b4d3ab6cf0f6c38e"
    )
    recorded = tuple(
        RecordedGenerationObservation.model_validate(value) for value in json.loads(raw)
    )

    class RecordingEvaluator(FakeModelAssistedEvaluator):
        def __init__(self) -> None:
            super().__init__(1.0)
            self.requests: list[ModelAssistedEvaluationRequest] = []

        async def evaluate(
            self, evaluation: ModelAssistedEvaluationRequest
        ) -> ModelAssistedEvaluationResult:
            self.requests.append(evaluation)
            return await super().evaluate(evaluation)

    evaluator = RecordingEvaluator()
    result = await reevaluate_recorded_generation(
        experiment_id="GEN-EXP-TEST-REEVALUATION",
        repository_commit="d52339f",
        population=population,
        recorded=recorded,
        evaluator=evaluator,
        generation_lineage={"fingerprint": population.generation_fingerprint},
        evaluator_lineage={"implementation": "recording-fake"},
    )
    assert len(result.observations) == len(evaluator.requests) == 13
    assert all(item.deterministic.outcome_correct for item in result.observations)
    assert all(
        item.deterministic.citation_membership_correct for item in result.observations
    )

    by_case = {item.case_id: item for item in evaluator.requests}
    multi = by_case["generation.independent.multi-document-spill"]
    assert multi.generated_result is not None
    assert multi.generated_result.answer_parts[0].evidence_ids == ("ev-01", "ev-02")
    compare = by_case["generation.independent.compare-recording"]
    assert {item.side for item in compare.retrieved_evidence} == {
        "PRIMARY",
        "COMPARISON",
    }
    modality = by_case["generation.independent.modality"]
    assert modality.generated_result is not None
    assert "must" in modality.generated_result.answer_parts[0].text
    qualified = by_case["generation.independent.badge-grace-period"]
    assert qualified.generated_result is not None
    assert qualified.generated_result.unsupported_aspects
    insufficient = by_case["generation.independent.payroll-insufficient"]
    assert insufficient.metrics == (ModelAssistedMetric.INSUFFICIENCY_CORRECTNESS,)
