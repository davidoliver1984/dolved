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
            if observation.contributes_retrieval_metrics:
                metrics, side_metrics, covered = evaluate_metrics_by_side(
                    case.evidence_units, observation.candidates, candidate_k
                )
            else:
                metrics, side_metrics, covered = None, {}, ()
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
                    planning_status=observation.planning_status,
                    retrieval_executed=observation.retrieval_executed,
                    contributes_retrieval_metrics=observation.contributes_retrieval_metrics,
                    planner_failure=observation.planner_failure,
                    planner_evaluation=observation.planner_evaluation,
                    retrieval_failure=observation.retrieval_failure,
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
    retrieval_results = [item for item in results if item.metrics is not None]
    planner_success_count = sum(item.planner_failure is None for item in results)
    categories = _failure_categories(results)
    return AggregateResult(
        metrics=_mean_metrics(
            [item.metrics for item in retrieval_results if item.metrics]
        ),
        planner_accuracy=fmean(item.planner_correct for item in results),
        eligibility_accuracy=_optional_mean(
            [
                item.eligibility_correct
                for item in results
                if item.eligibility_correct is not None
            ]
        ),
        outcome_accuracy=_optional_mean(
            [
                item.outcome_correct
                for item in results
                if item.outcome_correct is not None
            ]
        ),
        case_count=1,
        variant_count=len(results),
        retrieval_metric_variant_count=len(retrieval_results),
        planner_success_count=planner_success_count,
        planner_failure_count=len(results) - planner_success_count,
        planner_reliability=planner_success_count / len(results),
        planner_failure_categories=categories,
    )


def _aggregate_aggregates(results: list[AggregateResult]) -> AggregateResult:
    if not results:
        return AggregateResult(
            metrics=None,
            planner_accuracy=0,
            eligibility_accuracy=None,
            outcome_accuracy=None,
            case_count=0,
        )
    variant_count = sum(item.variant_count for item in results)
    retrieval_count = sum(item.retrieval_metric_variant_count for item in results)
    planner_success_count = sum(item.planner_success_count for item in results)
    categories: dict[str, int] = defaultdict(int)
    for item in results:
        for category, count in item.planner_failure_categories.items():
            categories[category] += count
    return AggregateResult(
        metrics=_mean_metrics([item.metrics for item in results if item.metrics]),
        planner_accuracy=fmean(item.planner_accuracy for item in results),
        eligibility_accuracy=_optional_mean(
            [
                item.eligibility_accuracy
                for item in results
                if item.eligibility_accuracy is not None
            ]
        ),
        outcome_accuracy=_optional_mean(
            [
                item.outcome_accuracy
                for item in results
                if item.outcome_accuracy is not None
            ]
        ),
        case_count=len(results),
        variant_count=variant_count,
        retrieval_metric_variant_count=retrieval_count,
        planner_success_count=planner_success_count,
        planner_failure_count=variant_count - planner_success_count,
        planner_reliability=planner_success_count / variant_count,
        planner_failure_categories=dict(sorted(categories.items())),
    )


def _mean_metrics(values: list[MetricValues]) -> MetricValues | None:
    if not values:
        return None
    return MetricValues(
        recall_at_k=fmean(item.recall_at_k for item in values),
        precision_at_k=fmean(item.precision_at_k for item in values),
        mrr=fmean(item.mrr for item in values),
        ndcg_at_k=fmean(item.ndcg_at_k for item in values),
    )


def _optional_mean(values: list[bool | float]) -> float | None:
    return fmean(values) if values else None


def _failure_categories(results: list[VariantResult]) -> dict[str, int]:
    categories: dict[str, int] = defaultdict(int)
    for result in results:
        if result.planner_failure is not None:
            categories[result.planner_failure.category] += 1
    return dict(sorted(categories.items()))
