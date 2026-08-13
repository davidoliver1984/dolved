"""Provider-free evidence-threshold replay over persisted reranker lineage."""

from __future__ import annotations

import math
from collections import defaultdict
from typing import Literal

from pydantic import Field, model_validator

from app.evaluation.models import Identifier, StrictModel
from app.evaluation.threshold_policy import (
    EvidenceCoverage,
    SliceRecall,
    ThresholdCalibrationPolicy,
    ThresholdCandidateMetrics,
    case_first_expected_evidence_unit_recall,
    select_evidence_threshold,
)


class ReplayExpectedEvidence(StrictModel):
    evidence_unit_id: Identifier
    side: Literal["PRIMARY", "COMPARISON"] = "PRIMARY"
    relevance_grade: int = Field(default=1, ge=1, le=2)


class PreThresholdCandidate(StrictModel):
    candidate_id: Identifier
    side: Literal["PRIMARY", "COMPARISON"] = "PRIMARY"
    reranker_rank: int = Field(ge=1)
    reranker_score: float
    covered_evidence_unit_ids: tuple[Identifier, ...] = ()


class ThresholdReplayVariant(StrictModel):
    case_id: Identifier
    variant_id: Identifier
    slices: tuple[Identifier, ...]
    expected_outcome: Identifier
    required_sides: tuple[Literal["PRIMARY", "COMPARISON"], ...] = ("PRIMARY",)
    expected_evidence: tuple[ReplayExpectedEvidence, ...] = ()
    pre_threshold_candidates: tuple[PreThresholdCandidate, ...] = ()
    pre_retrieval_outcome: Identifier | None = None
    absolute_failures: tuple[Identifier, ...] = ()

    @model_validator(mode="after")
    def valid_candidate_lineage(self) -> ThresholdReplayVariant:
        identities = [item.candidate_id for item in self.pre_threshold_candidates]
        if len(identities) != len(set(identities)):
            raise ValueError("pre-threshold candidate identities must be unique")
        for side in self.required_sides:
            ranks = [
                item.reranker_rank
                for item in self.pre_threshold_candidates
                if item.side == side
            ]
            if len(ranks) != len(set(ranks)):
                raise ValueError("reranker ranks must be unique within each side")
        return self


class ThresholdReplayDataset(StrictModel):
    schema_version: Literal["v1"] = "v1"
    benchmark_id: Literal["dolved-care-engineering"]
    corpus_version: Literal["v2"]
    corpus_digest: str
    split: Literal["threshold_calibration"]
    final_evidence_k: Literal[5]
    variants: tuple[ThresholdReplayVariant, ...]


class ThresholdBoundaryResult(StrictModel):
    metrics: ThresholdCandidateMetrics
    expected_units_accepted: int = Field(ge=0)
    expected_units_rejected: int = Field(ge=0)
    evidence_producing_variants: int = Field(ge=0)
    controlled_rejection_case_count: int = Field(ge=0)
    accepted_candidate_count: int = Field(ge=0)
    rejected_candidate_count: int = Field(ge=0)
    minimum_accepted_margin: float | None
    maximum_rejected_margin: float | None
    score_minimum: float | None
    score_maximum: float | None


class ThresholdReplayResult(StrictModel):
    schema_version: Literal["v1"] = "v1"
    benchmark_id: Literal["dolved-care-engineering"]
    corpus_version: Literal["v2"]
    corpus_digest: str
    split: Literal["threshold_calibration"]
    control_threshold: float
    boundaries: tuple[ThresholdBoundaryResult, ...]
    selected_threshold: float


def threshold_boundaries(
    dataset: ThresholdReplayDataset, control_threshold: float
) -> tuple[float, ...]:
    scores = {
        item.reranker_score
        for variant in dataset.variants
        for item in variant.pre_threshold_candidates
    }
    maximum = max(scores, default=control_threshold)
    above_maximum = math.nextafter(maximum, math.inf)
    return tuple(sorted(scores | {control_threshold, above_maximum}))


def replay_thresholds(
    dataset: ThresholdReplayDataset, policy: ThresholdCalibrationPolicy
) -> ThresholdReplayResult:
    if dataset.corpus_digest == "":
        raise ValueError("calibration corpus digest is required")
    results = tuple(
        _replay_boundary(dataset, policy, threshold)
        for threshold in threshold_boundaries(dataset, policy.control_threshold)
    )
    selected = select_evidence_threshold(
        policy, tuple(item.metrics for item in results)
    )
    return ThresholdReplayResult(
        benchmark_id=dataset.benchmark_id,
        corpus_version=dataset.corpus_version,
        corpus_digest=dataset.corpus_digest,
        split=dataset.split,
        control_threshold=policy.control_threshold,
        boundaries=results,
        selected_threshold=selected.threshold,
    )


def _replay_boundary(
    dataset: ThresholdReplayDataset,
    policy: ThresholdCalibrationPolicy,
    threshold: float,
) -> ThresholdBoundaryResult:
    coverage: list[EvidenceCoverage] = []
    variant_metrics: dict[tuple[str, str], tuple[float, float, float]] = {}
    case_outcomes: dict[str, list[bool]] = defaultdict(list)
    slice_cases: dict[str, dict[str, list[EvidenceCoverage]]] = defaultdict(
        lambda: defaultdict(list)
    )
    absolute_failures: set[str] = set()
    accepted_units: set[tuple[str, str, str]] = set()
    expected_units: set[tuple[str, str, str]] = set()
    uncredited = 0
    evidence_producing = 0
    losing_all = 0

    for variant in dataset.variants:
        absolute_failures.update(variant.absolute_failures)
        selected_by_side: dict[str, tuple[PreThresholdCandidate, ...]] = {}
        for side in variant.required_sides:
            selected_by_side[side] = tuple(
                sorted(
                    (
                        item
                        for item in variant.pre_threshold_candidates
                        if item.side == side and item.reranker_score >= threshold
                    ),
                    key=lambda item: item.reranker_rank,
                )[: dataset.final_evidence_k]
            )
        outcome = _outcome(variant, selected_by_side)
        if variant.expected_outcome in policy.controlled_rejection_outcomes:
            case_outcomes[variant.case_id].append(outcome == variant.expected_outcome)
        if any(selected_by_side.values()):
            evidence_producing += 1
        elif variant.expected_evidence:
            losing_all += 1

        side_recalls: list[float] = []
        side_precisions: list[float] = []
        side_mrr: list[float] = []
        side_ndcg: list[float] = []
        for side in variant.required_sides:
            expected = tuple(
                item for item in variant.expected_evidence if item.side == side
            )
            selected = selected_by_side[side]
            if not expected:
                uncredited += len(selected)
                continue
            expected_ids = {item.evidence_unit_id for item in expected}
            credited: set[str] = set()
            first_rank: int | None = None
            gains: list[int] = []
            for rank, candidate in enumerate(selected, 1):
                newly_covered = (
                    expected_ids.intersection(candidate.covered_evidence_unit_ids)
                    - credited
                )
                if newly_covered and first_rank is None:
                    first_rank = rank
                credited.update(newly_covered)
                if not newly_covered:
                    uncredited += 1
                gains.append(
                    max(
                        (
                            item.relevance_grade
                            for item in expected
                            if item.evidence_unit_id in newly_covered
                        ),
                        default=0,
                    )
                )
            expected_keys = {
                (variant.case_id, variant.variant_id, item.evidence_unit_id)
                for item in expected
            }
            expected_units.update(expected_keys)
            accepted_units.update(key for key in expected_keys if key[2] in credited)
            item_coverage = EvidenceCoverage(
                case_id=variant.case_id,
                variant_id=variant.variant_id,
                side=side,
                expected_evidence_unit_ids=tuple(sorted(expected_ids)),
                covered_evidence_unit_ids=tuple(sorted(credited)),
            )
            coverage.append(item_coverage)
            for slice_name in variant.slices:
                slice_cases[slice_name][variant.case_id].append(item_coverage)
            side_recalls.append(len(credited) / len(expected_ids))
            side_precisions.append(len(credited) / dataset.final_evidence_k)
            side_mrr.append(1 / first_rank if first_rank else 0.0)
            side_ndcg.append(_ndcg(gains, expected, dataset.final_evidence_k))
        if side_recalls:
            count = len(side_recalls)
            variant_metrics[(variant.case_id, variant.variant_id)] = (
                sum(side_precisions) / count,
                sum(side_mrr) / count,
                sum(side_ndcg) / count,
            )

    scores = [
        item.reranker_score
        for variant in dataset.variants
        for item in variant.pre_threshold_candidates
    ]
    accepted_scores = [score for score in scores if score >= threshold]
    rejected_scores = [score for score in scores if score < threshold]
    metrics = ThresholdCandidateMetrics(
        threshold=threshold,
        absolute_failures=tuple(sorted(absolute_failures)),
        controlled_rejection_correctness=_case_first_boolean(case_outcomes),
        benchmark_precision=_case_first_metric(variant_metrics, 0),
        case_first_expected_evidence_unit_recall=(
            case_first_expected_evidence_unit_recall(tuple(coverage))
        ),
        variants_losing_all_evidence=losing_all,
        mrr=_case_first_metric(variant_metrics, 1),
        ndcg=_case_first_metric(variant_metrics, 2),
        load_bearing_slice_recall={
            name: SliceRecall(
                case_count=len(slice_cases[name]),
                recall=case_first_expected_evidence_unit_recall(
                    tuple(
                        item for values in slice_cases[name].values() for item in values
                    )
                ),
            )
            for name in policy.load_bearing_slices
            if slice_cases[name]
        },
        uncredited_unannotated_accepted_candidates=uncredited,
    )
    return ThresholdBoundaryResult(
        metrics=metrics,
        expected_units_accepted=len(accepted_units),
        expected_units_rejected=len(expected_units - accepted_units),
        evidence_producing_variants=evidence_producing,
        controlled_rejection_case_count=len(case_outcomes),
        accepted_candidate_count=len(accepted_scores),
        rejected_candidate_count=len(rejected_scores),
        minimum_accepted_margin=(
            min(score - threshold for score in accepted_scores)
            if accepted_scores
            else None
        ),
        maximum_rejected_margin=(
            max(score - threshold for score in rejected_scores)
            if rejected_scores
            else None
        ),
        score_minimum=min(scores) if scores else None,
        score_maximum=max(scores) if scores else None,
    )


def _outcome(
    variant: ThresholdReplayVariant,
    selected_by_side: dict[str, tuple[PreThresholdCandidate, ...]],
) -> str:
    if variant.pre_retrieval_outcome is not None:
        return variant.pre_retrieval_outcome
    if len(variant.required_sides) > 1 and any(
        not selected_by_side[side] for side in variant.required_sides
    ):
        return "COMPARISON_SCOPE_INCOMPLETE"
    if not any(selected_by_side.values()):
        return "INSUFFICIENT_EVIDENCE"
    return "EVIDENCE_FOUND"


def _case_first_boolean(values: dict[str, list[bool]]) -> float:
    if not values:
        return 1.0
    return sum(sum(items) / len(items) for items in values.values()) / len(values)


def _case_first_metric(
    values: dict[tuple[str, str], tuple[float, float, float]], index: int
) -> float:
    cases: dict[str, list[float]] = defaultdict(list)
    for (case_id, _variant_id), metrics in values.items():
        cases[case_id].append(metrics[index])
    if not cases:
        return 0.0
    return sum(sum(items) / len(items) for items in cases.values()) / len(cases)


def _ndcg(
    gains: list[int], expected: tuple[ReplayExpectedEvidence, ...], k: int
) -> float:
    dcg = sum((2**gain - 1) / math.log2(rank + 1) for rank, gain in enumerate(gains, 1))
    ideal = sorted((item.relevance_grade for item in expected), reverse=True)[:k]
    idcg = sum(
        (2**gain - 1) / math.log2(rank + 1) for rank, gain in enumerate(ideal, 1)
    )
    return dcg / idcg if idcg else 1.0
