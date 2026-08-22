"""Baseline comparison and manual quality-gate governance."""

from __future__ import annotations

from datetime import UTC, datetime

from app.evaluation.canonical import content_digest
from app.evaluation.historical_result import ComparisonResult
from app.evaluation.models import (
    BaselinePromotion,
    ExperimentResult,
    GateDecision,
    ManualGateRecord,
    QualityGatePolicy,
)


def deterministic_profile_manifest(experiment: ExperimentResult) -> dict[str, object]:
    lineage = experiment.lineage
    required = {
        "embedding_profile_fingerprint": lineage.embedding_profile_fingerprint,
        "sparse_profile_fingerprint": lineage.sparse_profile_fingerprint,
        "reranker_profile_fingerprint": lineage.reranker_profile_fingerprint,
        "plan_catalogue_checksum": lineage.plan_catalogue_checksum,
    }
    if any(value is None for value in required.values()):
        raise ValueError("deterministic execution profile lineage is incomplete")
    return {
        **required,
        "retrieval_configuration": lineage.retrieval_configuration,
        "harness_version": lineage.harness_version,
    }


def verify_deterministic_profile_digest(experiment: ExperimentResult) -> None:
    recorded = experiment.lineage.deterministic_profile_digest
    if recorded is None:
        raise ValueError("deterministic execution profile digest is unavailable")
    if content_digest(deterministic_profile_manifest(experiment)) != recorded:
        raise ValueError(
            "deterministic execution profile digest does not match lineage"
        )


def verify_promoted_deterministic_baseline(
    baseline: ExperimentResult, promotion: BaselinePromotion
) -> None:
    if baseline.experiment_id != promotion.experiment_id:
        raise ValueError("promoted deterministic baseline experiment identity mismatch")
    verify_deterministic_profile_digest(baseline)
    verify_baseline_identity(baseline, promotion)


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
        deterministic_profile_digest=experiment.lineage.deterministic_profile_digest,
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
    if (
        promotion.deterministic_profile_digest is not None
        and candidate.lineage.deterministic_profile_digest
        != promotion.deterministic_profile_digest
    ):
        raise ValueError(
            "candidate deterministic execution profile does not match the accepted baseline"
        )


def assess_gate(
    candidate: ExperimentResult,
    baseline: ExperimentResult | ComparisonResult,
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
