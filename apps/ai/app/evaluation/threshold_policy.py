"""Predeclared evidence-threshold calibration decision policy."""

from __future__ import annotations

from collections import defaultdict
from typing import Literal

from pydantic import Field, model_validator

from app.evaluation.models import Identifier, StrictModel


class EvidenceCoverage(StrictModel):
    case_id: Identifier
    variant_id: Identifier
    side: Literal["PRIMARY", "COMPARISON"] = "PRIMARY"
    expected_evidence_unit_ids: tuple[Identifier, ...]
    covered_evidence_unit_ids: tuple[Identifier, ...] = ()


class SliceRecall(StrictModel):
    case_count: int = Field(ge=1)
    recall: float = Field(ge=0, le=1)


class ThresholdCandidateMetrics(StrictModel):
    threshold: float
    absolute_failures: tuple[Identifier, ...] = ()
    controlled_rejection_correctness: float = Field(ge=0, le=1)
    benchmark_precision: float = Field(ge=0, le=1)
    case_first_expected_evidence_unit_recall: float = Field(ge=0, le=1)
    variants_losing_all_evidence: int = Field(ge=0)
    mrr: float = Field(ge=0, le=1)
    ndcg: float = Field(ge=0, le=1)
    load_bearing_slice_recall: dict[Identifier, SliceRecall]
    uncredited_unannotated_accepted_candidates: int = Field(ge=0)


class ThresholdCalibrationPolicy(StrictModel):
    schema_version: Literal["v1"] = "v1"
    policy_id: Identifier
    policy_version: str
    control_threshold: float
    absolute_failures: tuple[Identifier, ...]
    load_bearing_slices: tuple[Identifier, ...]
    controlled_rejection_outcomes: tuple[Identifier, ...]
    selection_rule: Literal["case-first-evidence-recall-constrained-v1"]

    @model_validator(mode="after")
    def unique_policy_lists(self) -> ThresholdCalibrationPolicy:
        for name in (
            "absolute_failures",
            "load_bearing_slices",
            "controlled_rejection_outcomes",
        ):
            values = getattr(self, name)
            if len(values) != len(set(values)):
                raise ValueError(f"{name} must be unique")
        return self


def case_first_expected_evidence_unit_recall(
    coverage: tuple[EvidenceCoverage, ...],
) -> float:
    """Average sides within variants, variants within cases, then cases equally."""

    variant_sides: dict[tuple[str, str], list[float]] = defaultdict(list)
    for item in coverage:
        expected = set(item.expected_evidence_unit_ids)
        if not expected:
            continue
        covered = expected.intersection(item.covered_evidence_unit_ids)
        variant_sides[(item.case_id, item.variant_id)].append(
            len(covered) / len(expected)
        )

    case_variants: dict[str, list[float]] = defaultdict(list)
    for (case_id, _variant_id), side_recalls in variant_sides.items():
        case_variants[case_id].append(sum(side_recalls) / len(side_recalls))
    if not case_variants:
        return 0.0

    case_recalls = (
        sum(variant_recalls) / len(variant_recalls)
        for variant_recalls in case_variants.values()
    )
    return sum(case_recalls) / len(case_variants)


def select_evidence_threshold(
    policy: ThresholdCalibrationPolicy,
    candidates: tuple[ThresholdCandidateMetrics, ...],
) -> ThresholdCandidateMetrics:
    """Apply the predeclared constrained rule; maximum F1 is intentionally absent."""

    controls = [
        item for item in candidates if item.threshold == policy.control_threshold
    ]
    if len(controls) != 1:
        raise ValueError("exactly one current-control threshold result is required")
    if len({item.threshold for item in candidates}) != len(candidates):
        raise ValueError("threshold candidates must be unique")
    control = controls[0]
    _assert_slice_coverage(policy, control)
    if control.absolute_failures:
        raise ValueError("the factual control has an absolute invariant failure")

    eligible: list[ThresholdCandidateMetrics] = []
    for candidate in candidates:
        _assert_slice_coverage(policy, candidate)
        if candidate.absolute_failures:
            continue
        if (
            candidate.controlled_rejection_correctness
            < control.controlled_rejection_correctness
            or candidate.benchmark_precision < control.benchmark_precision
            or any(
                candidate.load_bearing_slice_recall[name].recall
                < control.load_bearing_slice_recall[name].recall
                for name in policy.load_bearing_slices
            )
        ):
            continue
        if (
            candidate.case_first_expected_evidence_unit_recall
            > control.case_first_expected_evidence_unit_recall
        ):
            eligible.append(candidate)

    if not eligible:
        return control
    return max(
        eligible,
        key=lambda item: (
            item.case_first_expected_evidence_unit_recall,
            item.benchmark_precision,
            -item.variants_losing_all_evidence,
            item.mrr,
            item.ndcg,
            item.threshold,
        ),
    )


def _assert_slice_coverage(
    policy: ThresholdCalibrationPolicy, candidate: ThresholdCandidateMetrics
) -> None:
    missing = set(policy.load_bearing_slices) - set(candidate.load_bearing_slice_recall)
    if missing:
        raise ValueError(
            "threshold result lacks predeclared load-bearing slices: "
            + ", ".join(sorted(missing))
        )
