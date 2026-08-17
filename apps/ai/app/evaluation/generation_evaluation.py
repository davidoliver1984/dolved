"""Bounded Stage 17.4 evaluation of the committed generation boundary."""

from __future__ import annotations

import hashlib
import json
import re
from datetime import UTC, datetime
from enum import StrEnum
from pathlib import Path
from typing import Annotated, Any
from uuid import NAMESPACE_URL, uuid5

from pydantic import Field, model_validator

from app.evaluation.canonical import canonical_json
from app.evaluation.model_assisted import ModelAssistedEvaluator
from app.evaluation.models import (
    Identifier,
    ModelAssistedAnswerPart,
    ModelAssistedEvaluationRequest,
    ModelAssistedEvaluationResult,
    ModelAssistedGenerationResult,
    ModelAssistedMetric,
    ModelAssistedStatus,
    RetrievedCandidate,
    StrictModel,
)
from app.generation.models import (
    GenerationOutcome,
    GenerationRequest,
    GenerationResult,
)
from app.generation.protocol import Generator

GENERATION_EVALUATION_HARNESS_VERSION = "generation-evaluation-v2"
INTERNAL_LEAKAGE = re.compile(
    r"\b(?:ev-\d{2,}|answerpart|answer part id|claim\s+\d+|evidence\s+\d+)\b",
    re.IGNORECASE,
)


class EvaluationCohort(StrEnum):
    REGRESSION = "REGRESSION"
    INDEPENDENT = "INDEPENDENT"


class GenerationEvaluationEvidence(StrictModel):
    evidence_id: Annotated[str, Field(pattern=r"^ev-[0-9]{2,}$")]
    text: Annotated[str, Field(min_length=1)]
    document_chunk_id: Annotated[int, Field(gt=0)]
    document_id: Annotated[int, Field(gt=0)]
    ingestion_event_claim_id: Annotated[int, Field(gt=0)]
    side: str = "primary"
    temporal_authority: dict[str, object] = Field(default_factory=dict)
    applicability_context: dict[str, object] = Field(default_factory=dict)


class GenerationEvaluationCase(StrictModel):
    case_id: Identifier
    cohort: EvaluationCohort
    question: Annotated[str, Field(min_length=1, max_length=8000)]
    expected_outcome: GenerationOutcome
    evidence: tuple[GenerationEvaluationEvidence, ...] = Field(min_length=1)
    required_sides: tuple[str, ...] = ("primary",)
    reference_answer: str | None = None
    expected_unsupported_aspects: tuple[Annotated[str, Field(min_length=1)], ...] = ()
    surfaces: tuple[Identifier, ...] = Field(min_length=1)
    prohibited_output_fragments: tuple[Annotated[str, Field(min_length=1)], ...] = ()

    @model_validator(mode="after")
    def validate_expected_shape(self) -> GenerationEvaluationCase:
        evidence_ids = [item.evidence_id for item in self.evidence]
        if len(evidence_ids) != len(set(evidence_ids)):
            raise ValueError("generation evaluation evidence IDs must be unique")
        evidence_sides = {item.side for item in self.evidence}
        if not set(self.required_sides).issubset(evidence_sides):
            raise ValueError("every required side must have authored evidence")
        if self.expected_outcome is GenerationOutcome.INSUFFICIENT_EVIDENCE:
            if (
                self.reference_answer is not None
                or not self.expected_unsupported_aspects
            ):
                raise ValueError(
                    "insufficiency cases require unsupported aspects and no reference answer"
                )
        elif not self.reference_answer:
            raise ValueError("answering cases require an authored reference answer")
        if (
            self.expected_outcome is GenerationOutcome.QUALIFIED
            and not self.expected_unsupported_aspects
        ):
            raise ValueError("qualified cases require expected unsupported aspects")
        if (
            self.expected_outcome is GenerationOutcome.ANSWERED
            and self.expected_unsupported_aspects
        ):
            raise ValueError("answered cases cannot expect unsupported aspects")
        return self


class GenerationEvaluationPopulation(StrictModel):
    schema_version: str = "v1"
    population_id: Identifier
    population_version: str
    generation_fingerprint: str
    cases: tuple[GenerationEvaluationCase, ...] = Field(min_length=1)

    @model_validator(mode="after")
    def unique_cases(self) -> GenerationEvaluationPopulation:
        case_ids = [case.case_id for case in self.cases]
        if len(case_ids) != len(set(case_ids)):
            raise ValueError("generation evaluation case IDs must be unique")
        return self

    def digest(self) -> str:
        return hashlib.sha256(canonical_json(self.model_dump(mode="json"))).hexdigest()


class DeterministicGenerationEvaluation(StrictModel):
    outcome_correct: bool
    citation_membership_correct: bool
    structural_invariants_correct: bool
    over_refusal: bool
    overclaiming: bool
    internal_identifier_leakage: bool
    prohibited_fragment_leakage: tuple[str, ...] = ()
    qualified_shape_useful: bool | None = None
    insufficiency_shape_correct: bool | None = None


class GenerationCaseObservation(StrictModel):
    case_id: Identifier
    cohort: EvaluationCohort
    question: str
    expected_outcome: GenerationOutcome
    request: GenerationRequest
    result: GenerationResult
    deterministic: DeterministicGenerationEvaluation
    answer_part_evaluations: tuple[ModelAssistedEvaluationResult, ...] = ()
    answer_evaluation: ModelAssistedEvaluationResult | None = None


class RecordedGenerationObservation(StrictModel):
    case_id: Identifier
    cohort: EvaluationCohort
    question: str
    expected_outcome: GenerationOutcome
    request: GenerationRequest
    result: GenerationResult


class SemanticMetricCoverage(StrictModel):
    eligible: Annotated[int, Field(ge=0)]
    scored: Annotated[int, Field(ge=0)]
    failed: Annotated[int, Field(ge=0)]
    unevaluable: Annotated[int, Field(ge=0)]
    coverage: Annotated[float, Field(ge=0, le=1)] | None
    mean: Annotated[float, Field(ge=0, le=1)] | None


class EvaluatorOperationalAggregate(StrictModel):
    request_count: Annotated[int, Field(ge=0)]
    completed_count: Annotated[int, Field(ge=0)]
    failed_count: Annotated[int, Field(ge=0)]
    retry_count: Annotated[int, Field(ge=0)] | None
    latency_mean_ms: Annotated[float, Field(ge=0)] | None
    input_tokens: Annotated[int, Field(ge=0)] | None
    output_tokens: Annotated[int, Field(ge=0)] | None
    cost_usd: Annotated[float, Field(ge=0)] | None


class GenerationEvaluationAggregate(StrictModel):
    total_cases: Annotated[int, Field(ge=1)]
    outcome_accuracy: Annotated[float, Field(ge=0, le=1)]
    per_outcome_accuracy: dict[str, float]
    confusion_matrix: dict[str, dict[str, int]]
    citation_correctness: Annotated[float, Field(ge=0, le=1)]
    over_refusal_count: Annotated[int, Field(ge=0)]
    over_refusal_rate: Annotated[float, Field(ge=0, le=1)]
    overclaiming_count: Annotated[int, Field(ge=0)]
    unsupported_answer_part_count: Annotated[int, Field(ge=0)]
    answer_part_count: Annotated[int, Field(ge=0)]
    total_answer_parts: Annotated[int, Field(ge=0)]
    successfully_scored_answer_parts: Annotated[int, Field(ge=0)]
    failed_evaluator_answer_parts: Annotated[int, Field(ge=0)]
    unevaluable_answer_parts: Annotated[int, Field(ge=0)]
    metric_coverage: dict[str, SemanticMetricCoverage]
    unsupported_claim_rate: float | None
    groundedness_mean: float | None
    factual_precision_mean: float | None
    completeness_mean: float | None
    evaluator_operations: EvaluatorOperationalAggregate
    hostile_case_count: Annotated[int, Field(ge=0)]
    hostile_failures: Annotated[int, Field(ge=0)]


class GenerationEvaluationResult(StrictModel):
    schema_version: str = "v2"
    experiment_id: Identifier
    executed_at: datetime
    repository_commit: str
    harness_version: str = GENERATION_EVALUATION_HARNESS_VERSION
    population_id: Identifier
    population_digest: str
    generation_lineage: dict[str, Any]
    evaluator_lineage: dict[str, Any]
    observations: tuple[GenerationCaseObservation, ...]
    aggregate: GenerationEvaluationAggregate


def load_generation_population(path: Path) -> GenerationEvaluationPopulation:
    return GenerationEvaluationPopulation.model_validate_json(path.read_text())


def build_generation_request(
    population: GenerationEvaluationPopulation, case: GenerationEvaluationCase
) -> GenerationRequest:
    identity = (
        f"{population.population_id}:{population.population_version}:{case.case_id}"
    )
    return GenerationRequest.model_validate(
        {
            "request_id": str(uuid5(NAMESPACE_URL, f"request:{identity}")),
            "workspace_id": str(uuid5(NAMESPACE_URL, "dolved:generation-evaluation")),
            "question": case.question,
            "evidence": [
                {
                    **item.model_dump(mode="json"),
                    "source_provenance": [{"kind": "generation-evaluation-fixture"}],
                }
                for item in case.evidence
            ],
            "constraints": {
                "context_policy_version": "whole-evidence-v1",
                "max_context_characters": 60_000,
                "required_sides": list(case.required_sides),
            },
        }
    )


def deterministic_evaluate(
    case: GenerationEvaluationCase,
    request: GenerationRequest,
    result: GenerationResult,
) -> DeterministicGenerationEvaluation:
    citation_correct = True
    try:
        result.validate_against(request)
    except ValueError:
        citation_correct = False
    prose = "\n".join(part.text for part in result.answer_parts)
    prohibited = tuple(
        fragment
        for fragment in case.prohibited_output_fragments
        if fragment.casefold() in prose.casefold()
    )
    expected = case.expected_outcome
    over_refusal = (
        expected in {GenerationOutcome.ANSWERED, GenerationOutcome.QUALIFIED}
        and result.outcome is GenerationOutcome.INSUFFICIENT_EVIDENCE
    )
    overclaiming = (
        expected
        in {
            GenerationOutcome.QUALIFIED,
            GenerationOutcome.INSUFFICIENT_EVIDENCE,
        }
        and result.outcome is GenerationOutcome.ANSWERED
    )
    qualified_useful = None
    if result.outcome is GenerationOutcome.QUALIFIED:
        qualified_useful = bool(result.answer_parts and result.unsupported_aspects)
    insufficiency_correct = None
    if result.outcome is GenerationOutcome.INSUFFICIENT_EVIDENCE:
        insufficiency_correct = bool(
            not result.answer_parts
            and result.unsupported_aspects
            and result.insufficiency_reason
        )
    return DeterministicGenerationEvaluation(
        outcome_correct=result.outcome is expected,
        citation_membership_correct=citation_correct,
        structural_invariants_correct=True,
        over_refusal=over_refusal,
        overclaiming=overclaiming,
        internal_identifier_leakage=bool(INTERNAL_LEAKAGE.search(prose)),
        prohibited_fragment_leakage=prohibited,
        qualified_shape_useful=qualified_useful,
        insufficiency_shape_correct=insufficiency_correct,
    )


def _retrieved_evidence(
    request: GenerationRequest, evidence_ids: set[str] | None = None
) -> tuple[RetrievedCandidate, ...]:
    selected = [
        item
        for item in request.evidence
        if evidence_ids is None or item.evidence_id in evidence_ids
    ]
    return tuple(
        RetrievedCandidate(
            candidate_id=item.evidence_id,
            document_family_id=f"family-{item.document_id}",
            document_version_id=f"document-{item.document_id}",
            rank=index,
            text=item.text,
            side="COMPARISON" if item.side.value == "comparison" else "PRIMARY",
        )
        for index, item in enumerate(selected, start=1)
    )


async def evaluate_generation_case(
    *,
    case: GenerationEvaluationCase,
    request: GenerationRequest,
    result: GenerationResult,
    evaluator: ModelAssistedEvaluator,
) -> GenerationCaseObservation:
    generated_result = ModelAssistedGenerationResult(
        outcome=result.outcome.value,
        answer_parts=tuple(
            ModelAssistedAnswerPart(
                part_index=index,
                text=part.text,
                evidence_ids=part.evidence_ids,
            )
            for index, part in enumerate(result.answer_parts, start=1)
        ),
        unsupported_aspects=result.unsupported_aspects,
        insufficiency_reason=result.insufficiency_reason,
    )
    metrics: tuple[ModelAssistedMetric, ...]
    if result.outcome is GenerationOutcome.INSUFFICIENT_EVIDENCE:
        metrics = (ModelAssistedMetric.INSUFFICIENCY_CORRECTNESS,)
    else:
        metrics = (
            ModelAssistedMetric.ANSWER_PART_GROUNDEDNESS,
            ModelAssistedMetric.ANSWER_FACTUAL_PRECISION,
            ModelAssistedMetric.ANSWER_COMPLETENESS,
        )
        if result.outcome is GenerationOutcome.QUALIFIED:
            metrics += (ModelAssistedMetric.QUALIFIED_USEFULNESS,)
    answer_evaluation = await evaluator.evaluate(
        ModelAssistedEvaluationRequest(
            case_id=case.case_id,
            variant_id="complete-result",
            question=case.question,
            retrieved_evidence=_retrieved_evidence(request),
            metrics=metrics,
            generated_result=generated_result,
            reference_answer=case.reference_answer,
            reference_unsupported_aspects=case.expected_unsupported_aspects,
        )
    )
    return GenerationCaseObservation(
        case_id=case.case_id,
        cohort=case.cohort,
        question=case.question,
        expected_outcome=case.expected_outcome,
        request=request,
        result=result,
        deterministic=deterministic_evaluate(case, request, result),
        answer_part_evaluations=(),
        answer_evaluation=answer_evaluation,
    )


def aggregate_generation_observations(
    observations: tuple[GenerationCaseObservation, ...],
    cases: tuple[GenerationEvaluationCase, ...],
) -> GenerationEvaluationAggregate:
    total = len(observations)
    outcomes = tuple(GenerationOutcome)
    confusion = {
        expected.value: {actual.value: 0 for actual in outcomes}
        for expected in outcomes
    }
    for item in observations:
        confusion[item.expected_outcome.value][item.result.outcome.value] += 1
    per_outcome: dict[str, float] = {}
    for expected in outcomes:
        row = confusion[expected.value]
        count = sum(row.values())
        per_outcome[expected.value] = row[expected.value] / count if count else 0.0
    total_answer_parts = sum(len(item.result.answer_parts) for item in observations)
    grounded_flags: list[bool] = []
    failed_answer_parts = 0
    for item in observations:
        evaluation = item.answer_evaluation
        part_count = len(item.result.answer_parts)
        if not part_count:
            continue
        if evaluation is None:
            continue
        if evaluation.status is ModelAssistedStatus.FAILED:
            failed_answer_parts += part_count
            continue
        grounded_flags.extend(
            bool(value["grounded"])
            for value in evaluation.details.get("part_judgements", [])
        )
    scored_answer_parts = len(grounded_flags)
    unevaluable_answer_parts = (
        total_answer_parts - scored_answer_parts - failed_answer_parts
    )
    unsupported_parts = sum(not value for value in grounded_flags)

    def metric_coverage(
        metric: ModelAssistedMetric, eligible: int
    ) -> SemanticMetricCoverage:
        relevant = []
        for item in observations:
            evaluation = item.answer_evaluation
            if evaluation is not None and any(
                observation.metric is metric
                for observation in evaluation.metric_observations
            ):
                relevant.append(evaluation)
        scores = [item.scores[metric] for item in relevant if metric in item.scores]
        failed = sum(
            any(
                observation.metric is metric
                and observation.status is ModelAssistedStatus.FAILED
                for observation in item.metric_observations
            )
            for item in relevant
        )
        unevaluable = eligible - len(scores) - failed
        return SemanticMetricCoverage(
            eligible=eligible,
            scored=len(scores),
            failed=failed,
            unevaluable=unevaluable,
            coverage=len(scores) / eligible if eligible else None,
            mean=sum(scores) / len(scores) if scores else None,
        )

    answer_cases = sum(bool(item.result.answer_parts) for item in observations)
    qualified_cases = sum(
        item.result.outcome is GenerationOutcome.QUALIFIED for item in observations
    )
    insufficient_cases = sum(
        item.result.outcome is GenerationOutcome.INSUFFICIENT_EVIDENCE
        for item in observations
    )
    coverage = {
        ModelAssistedMetric.ANSWER_PART_GROUNDEDNESS.value: SemanticMetricCoverage(
            eligible=total_answer_parts,
            scored=scored_answer_parts,
            failed=failed_answer_parts,
            unevaluable=unevaluable_answer_parts,
            coverage=scored_answer_parts / total_answer_parts
            if total_answer_parts
            else None,
            mean=sum(grounded_flags) / scored_answer_parts
            if scored_answer_parts
            else None,
        ),
        ModelAssistedMetric.ANSWER_FACTUAL_PRECISION.value: metric_coverage(
            ModelAssistedMetric.ANSWER_FACTUAL_PRECISION, answer_cases
        ),
        ModelAssistedMetric.ANSWER_COMPLETENESS.value: metric_coverage(
            ModelAssistedMetric.ANSWER_COMPLETENESS, answer_cases
        ),
        ModelAssistedMetric.QUALIFIED_USEFULNESS.value: metric_coverage(
            ModelAssistedMetric.QUALIFIED_USEFULNESS, qualified_cases
        ),
        ModelAssistedMetric.INSUFFICIENCY_CORRECTNESS.value: metric_coverage(
            ModelAssistedMetric.INSUFFICIENCY_CORRECTNESS, insufficient_cases
        ),
    }
    case_by_id = {case.case_id: case for case in cases}
    hostile = [
        item
        for item in observations
        if "hostile-evidence" in case_by_id[item.case_id].surfaces
    ]
    hostile_failures = sum(
        bool(
            item.deterministic.prohibited_fragment_leakage
            or item.deterministic.internal_identifier_leakage
            or not item.deterministic.citation_membership_correct
        )
        for item in hostile
    )
    over_refusals = sum(item.deterministic.over_refusal for item in observations)
    overclaims = sum(item.deterministic.overclaiming for item in observations)
    evaluator_results = [
        item.answer_evaluation
        for item in observations
        if item.answer_evaluation is not None
    ]
    latencies = [
        item.latency_ms for item in evaluator_results if item.latency_ms is not None
    ]

    def total_int_if_complete(attribute: str) -> int | None:
        values = [getattr(item, attribute) for item in evaluator_results]
        if not values or any(value is None for value in values):
            return None
        return sum(int(value) for value in values)

    def total_float_if_complete(attribute: str) -> float | None:
        values = [getattr(item, attribute) for item in evaluator_results]
        if not values or any(value is None for value in values):
            return None
        return sum(float(value) for value in values)

    return GenerationEvaluationAggregate(
        total_cases=total,
        outcome_accuracy=sum(
            item.deterministic.outcome_correct for item in observations
        )
        / total,
        per_outcome_accuracy=per_outcome,
        confusion_matrix=confusion,
        citation_correctness=sum(
            item.deterministic.citation_membership_correct for item in observations
        )
        / total,
        over_refusal_count=over_refusals,
        over_refusal_rate=over_refusals / total,
        overclaiming_count=overclaims,
        unsupported_answer_part_count=unsupported_parts,
        answer_part_count=total_answer_parts,
        total_answer_parts=total_answer_parts,
        successfully_scored_answer_parts=scored_answer_parts,
        failed_evaluator_answer_parts=failed_answer_parts,
        unevaluable_answer_parts=unevaluable_answer_parts,
        metric_coverage=coverage,
        unsupported_claim_rate=(
            unsupported_parts / scored_answer_parts if scored_answer_parts else None
        ),
        groundedness_mean=coverage[
            ModelAssistedMetric.ANSWER_PART_GROUNDEDNESS.value
        ].mean,
        factual_precision_mean=coverage[
            ModelAssistedMetric.ANSWER_FACTUAL_PRECISION.value
        ].mean,
        completeness_mean=coverage[ModelAssistedMetric.ANSWER_COMPLETENESS.value].mean,
        evaluator_operations=EvaluatorOperationalAggregate(
            request_count=len(evaluator_results),
            completed_count=sum(
                item.status is ModelAssistedStatus.COMPLETED
                for item in evaluator_results
            ),
            failed_count=sum(
                item.status is ModelAssistedStatus.FAILED for item in evaluator_results
            ),
            retry_count=total_int_if_complete("retry_count"),
            latency_mean_ms=sum(latencies) / len(latencies) if latencies else None,
            input_tokens=total_int_if_complete("input_tokens"),
            output_tokens=total_int_if_complete("output_tokens"),
            cost_usd=total_float_if_complete("cost_usd"),
        ),
        hostile_case_count=len(hostile),
        hostile_failures=hostile_failures,
    )


async def run_generation_evaluation(
    *,
    experiment_id: str,
    repository_commit: str,
    population: GenerationEvaluationPopulation,
    generator: Generator,
    evaluator: ModelAssistedEvaluator,
    generation_lineage: dict[str, Any],
    evaluator_lineage: dict[str, Any],
    checkpoint_path: Path | None = None,
) -> GenerationEvaluationResult:
    observations: list[GenerationCaseObservation] = []
    for case in population.cases:
        request = build_generation_request(population, case)
        result = generator.generate(request)
        observation = await evaluate_generation_case(
            case=case,
            request=request,
            result=result,
            evaluator=evaluator,
        )
        observations.append(observation)
        if checkpoint_path is not None:
            checkpoint_path.parent.mkdir(parents=True, exist_ok=True)
            checkpoint_path.write_text(
                json.dumps(
                    [item.model_dump(mode="json") for item in observations],
                    ensure_ascii=False,
                    indent=2,
                    sort_keys=True,
                )
                + "\n"
            )
    observed = tuple(observations)
    return GenerationEvaluationResult(
        experiment_id=experiment_id,
        executed_at=datetime.now(UTC),
        repository_commit=repository_commit,
        population_id=population.population_id,
        population_digest=population.digest(),
        generation_lineage=generation_lineage,
        evaluator_lineage=evaluator_lineage,
        observations=observed,
        aggregate=aggregate_generation_observations(observed, population.cases),
    )


async def reevaluate_recorded_generation(
    *,
    experiment_id: str,
    repository_commit: str,
    population: GenerationEvaluationPopulation,
    recorded: tuple[RecordedGenerationObservation, ...],
    evaluator: ModelAssistedEvaluator,
    generation_lineage: dict[str, Any],
    evaluator_lineage: dict[str, Any],
    checkpoint_path: Path | None = None,
) -> GenerationEvaluationResult:
    """Evaluate immutable generation observations without invoking a generator."""
    cases = {case.case_id: case for case in population.cases}
    recorded_ids = [item.case_id for item in recorded]
    if len(recorded_ids) != len(set(recorded_ids)):
        raise ValueError("recorded generation case identities must be unique")
    if set(recorded_ids) != set(cases):
        raise ValueError(
            "recorded generation population does not match bound population"
        )
    observations: list[GenerationCaseObservation] = []
    for item in recorded:
        case = cases[item.case_id]
        if (
            item.cohort is not case.cohort
            or item.question != case.question
            or item.expected_outcome is not case.expected_outcome
        ):
            raise ValueError(
                "recorded generation case metadata does not match population"
            )
        if item.request != build_generation_request(population, case):
            raise ValueError(
                "recorded GenerationRequest does not match bound population"
            )
        observations.append(
            await evaluate_generation_case(
                case=case,
                request=item.request,
                result=item.result,
                evaluator=evaluator,
            )
        )
        if checkpoint_path is not None:
            checkpoint_path.parent.mkdir(parents=True, exist_ok=True)
            checkpoint_path.write_text(
                json.dumps(
                    [value.model_dump(mode="json") for value in observations],
                    ensure_ascii=False,
                    indent=2,
                    sort_keys=True,
                )
                + "\n"
            )
    observed = tuple(observations)
    return GenerationEvaluationResult(
        experiment_id=experiment_id,
        executed_at=datetime.now(UTC),
        repository_commit=repository_commit,
        population_id=population.population_id,
        population_digest=population.digest(),
        generation_lineage=generation_lineage,
        evaluator_lineage=evaluator_lineage,
        observations=observed,
        aggregate=aggregate_generation_observations(observed, population.cases),
    )
