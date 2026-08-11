"""Layered, sliced retrieval experiment evaluation."""

from __future__ import annotations

from collections import defaultdict
from datetime import UTC, datetime
from statistics import fmean

from app.evaluation.metrics import evaluate_metrics_by_side
from app.evaluation.models import (
    AggregateResult,
    EvaluationCorpus,
    ExperimentLineage,
    ExperimentResult,
    MetricValues,
    VariantObservation,
    VariantResult,
)


class RetrievalEvaluationHarness:
    VERSION = "retrieval-evaluation-v1"

    def evaluate(
        self,
        *,
        experiment_id: str,
        corpus: EvaluationCorpus,
        observations: tuple[VariantObservation, ...],
        lineage: ExperimentLineage,
        candidate_k: int,
        executed_at: datetime | None = None,
    ) -> ExperimentResult:
        expected = {
            (case.case_id, variant.variant_id): case
            for case in corpus.cases
            for variant in case.variants
        }
        supplied = {(item.case_id, item.variant_id): item for item in observations}
        missing = set(expected) - set(supplied)
        unexpected = set(supplied) - set(expected)
        if unexpected:
            raise ValueError(f"unexpected observations: {sorted(unexpected)}")

        results: list[VariantResult] = []
        hard_failures: set[str] = set()
        for key, case in expected.items():
            observation = supplied.get(key)
            if observation is None:
                hard_failures.add(f"lost_case:{key[0]}:{key[1]}")
                continue
            metrics, side_metrics, covered = evaluate_metrics_by_side(
                case.evidence_units, observation.candidates, candidate_k
            )
            hard_failures.update(observation.hard_failures)
            results.append(
                VariantResult(
                    case_id=observation.case_id,
                    variant_id=observation.variant_id,
                    metrics=metrics,
                    side_metrics=side_metrics,
                    covered_evidence_ids=covered,
                    planner_correct=observation.planner_correct,
                    eligibility_correct=observation.eligibility_correct,
                    outcome_correct=observation.outcome_correct,
                    hard_failures=observation.hard_failures,
                    operational=observation.operational,
                    text_capture_mode=observation.text_capture_mode,
                    question=observation.question,
                    expected_evidence=observation.expected_evidence,
                    expected_outcome=observation.expected_outcome,
                    candidate_lineage=observation.candidate_lineage,
                    candidate_funnel=observation.candidate_funnel,
                )
            )

        by_case: dict[str, list[VariantResult]] = defaultdict(list)
        for result in results:
            by_case[result.case_id].append(result)
        case_aggregates = {
            case_id: _aggregate(variant_results)
            for case_id, variant_results in by_case.items()
        }

        slices: dict[str, AggregateResult] = {}
        for slice_name in sorted(
            {value for case in corpus.cases for value in case.slices}
        ):
            case_ids = [
                case.case_id for case in corpus.cases if slice_name in case.slices
            ]
            available = [
                case_aggregates[case_id]
                for case_id in case_ids
                if case_id in case_aggregates
            ]
            slices[slice_name] = _aggregate_aggregates(available)

        aggregate = _aggregate_aggregates(list(case_aggregates.values()))
        if missing:
            hard_failures.add("lost_evaluation_case")
        return ExperimentResult(
            experiment_id=experiment_id,
            executed_at=executed_at or datetime.now(UTC),
            candidate_k=candidate_k,
            lineage=lineage,
            aggregate=aggregate,
            slices=slices,
            variants=tuple(results),
            hard_failures=tuple(sorted(hard_failures)),
        )


def _aggregate(results: list[VariantResult]) -> AggregateResult:
    return AggregateResult(
        metrics=_mean_metrics([item.metrics for item in results]),
        planner_accuracy=fmean(item.planner_correct for item in results),
        eligibility_accuracy=fmean(item.eligibility_correct for item in results),
        outcome_accuracy=fmean(item.outcome_correct for item in results),
        case_count=1,
    )


def _aggregate_aggregates(results: list[AggregateResult]) -> AggregateResult:
    if not results:
        return AggregateResult(
            metrics=MetricValues(recall_at_k=0, precision_at_k=0, mrr=0, ndcg_at_k=0),
            planner_accuracy=0,
            eligibility_accuracy=0,
            outcome_accuracy=0,
            case_count=0,
        )
    return AggregateResult(
        metrics=_mean_metrics([item.metrics for item in results]),
        planner_accuracy=fmean(item.planner_accuracy for item in results),
        eligibility_accuracy=fmean(item.eligibility_accuracy for item in results),
        outcome_accuracy=fmean(item.outcome_accuracy for item in results),
        case_count=len(results),
    )


def _mean_metrics(values: list[MetricValues]) -> MetricValues:
    return MetricValues(
        recall_at_k=fmean(item.recall_at_k for item in values),
        precision_at_k=fmean(item.precision_at_k for item in values),
        mrr=fmean(item.mrr for item in values),
        ndcg_at_k=fmean(item.ndcg_at_k for item in values),
    )
