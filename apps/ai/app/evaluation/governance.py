"""Baseline comparison and manual quality-gate governance."""

from __future__ import annotations

from datetime import UTC, datetime

from app.evaluation.models import (
    BaselinePromotion,
    ExperimentResult,
    GateDecision,
    ManualGateRecord,
    QualityGatePolicy,
)


def promote_baseline(
    experiment: ExperimentResult,
    *,
    promoted_by: str,
    reason: str,
    promoted_at: datetime | None = None,
) -> BaselinePromotion:
    return BaselinePromotion(
        experiment_id=experiment.experiment_id,
        corpus_version=experiment.lineage.corpus_version,
        corpus_digest=experiment.lineage.corpus_digest,
        policy_version=experiment.lineage.policy_version,
        policy_digest=experiment.lineage.policy_digest,
        promoted_by=promoted_by,
        promoted_at=promoted_at or datetime.now(UTC),
        reason=reason,
    )


def verify_baseline_identity(
    candidate: ExperimentResult, promotion: BaselinePromotion
) -> None:
    expected = (
        promotion.corpus_version,
        promotion.corpus_digest,
        promotion.policy_version,
        promotion.policy_digest,
    )
    actual = (
        candidate.lineage.corpus_version,
        candidate.lineage.corpus_digest,
        candidate.lineage.policy_version,
        candidate.lineage.policy_digest,
    )
    if actual != expected:
        raise ValueError(
            "candidate corpus/policy identity does not match the accepted baseline"
        )


def assess_gate(
    candidate: ExperimentResult,
    baseline: ExperimentResult,
    policy: QualityGatePolicy,
) -> tuple[bool, tuple[str, ...]]:
    failures = set(candidate.hard_failures)
    failures.update(
        failure
        for failure in candidate.hard_failures
        if failure in policy.absolute_failures
    )
    for metric, tolerance in policy.allowed_regressions.items():
        before = getattr(baseline.aggregate.metrics, metric)
        after = getattr(candidate.aggregate.metrics, metric)
        if after < before - tolerance:
            failures.add(f"regression:{metric}")
    for slice_name in policy.load_bearing_slices:
        if slice_name not in candidate.slices:
            failures.add(f"missing_slice:{slice_name}")
    return not failures, tuple(sorted(failures))


def record_gate_decision(
    *,
    experiment_id: str,
    decision: GateDecision,
    reviewer: str,
    reason: str,
    decided_at: datetime,
    waiver_expires_at: datetime | None = None,
) -> ManualGateRecord:
    return ManualGateRecord(
        experiment_id=experiment_id,
        decision=decision,
        reviewer=reviewer,
        decided_at=decided_at,
        reason=reason,
        waiver_expires_at=waiver_expires_at,
    )
