"""Baseline comparison and manual quality-gate governance."""

from __future__ import annotations

import hashlib
import json
from datetime import UTC, datetime
from pathlib import Path, PurePosixPath

from app.evaluation.canonical import content_digest
from app.evaluation.current_retrieval import EligibilityArtifact
from app.evaluation.historical_result import ComparisonResult
from app.evaluation.models import (
    BaselinePromotion,
    ExperimentResult,
    GateDecision,
    ManualGateRecord,
    QualityGatePolicy,
)

SEMANTIC_COMPARISON_EXCLUDED_FIELDS = frozenset(
    {
        "executed_at",
        "operational",
        "latency_ms",
        "dense_score",
        "sparse_score",
        "fused_score",
        "reranker_score",
    }
)


def _semantic_comparison_value(value: object) -> object:
    if isinstance(value, dict):
        return {
            key: _semantic_comparison_value(item)
            for key, item in value.items()
            if key not in SEMANTIC_COMPARISON_EXCLUDED_FIELDS
        }
    if isinstance(value, list):
        return [_semantic_comparison_value(item) for item in value]
    return value


def semantic_comparison_digest(experiment: ExperimentResult) -> str:
    """Hash stable deterministic semantics using the reviewed comparison rules."""
    semantic = _semantic_comparison_value(experiment.model_dump(mode="json"))
    payload = (
        json.dumps(
            semantic, ensure_ascii=False, allow_nan=False, indent=2, sort_keys=True
        )
        + "\n"
    ).encode()
    return hashlib.sha256(payload).hexdigest()


def deterministic_profile_manifest(experiment: ExperimentResult) -> dict[str, object]:
    lineage = experiment.lineage
    required = {
        "embedding_profile_fingerprint": lineage.embedding_profile_fingerprint,
        "sparse_profile_fingerprint": lineage.sparse_profile_fingerprint,
        "reranker_profile_fingerprint": lineage.reranker_profile_fingerprint,
        "plan_catalogue_checksum": lineage.plan_catalogue_checksum,
        "eligibility_artifact_contract": lineage.eligibility_artifact_contract,
        "eligibility_artifact_digest": lineage.eligibility_artifact_digest,
        "eligibility_comparability_digest": lineage.eligibility_comparability_digest,
        "eligibility_catalogue_version": lineage.eligibility_catalogue_version,
        "eligibility_catalogue_digest": lineage.eligibility_catalogue_digest,
        "eligibility_resolver_source_digest": lineage.eligibility_resolver_source_digest,
        "eligibility_configuration_digest": lineage.eligibility_configuration_digest,
        "eligibility_evaluated_at": lineage.eligibility_evaluated_at,
        "eligibility_document_mapping_digest": lineage.eligibility_document_mapping_digest,
    }
    if any(value is None for value in required.values()):
        raise ValueError("deterministic execution profile lineage is incomplete")
    return {
        **{
            key: value
            for key, value in required.items()
            if key != "eligibility_artifact_digest"
        },
        "planner": lineage.planner,
        "chunking_configuration": lineage.chunking_configuration,
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
    if (
        promotion.repository_commit is not None
        and baseline.lineage.repository_commit != promotion.repository_commit
    ):
        raise ValueError("promoted deterministic baseline repository identity mismatch")
    if (
        promotion.semantic_comparison_digest is not None
        and semantic_comparison_digest(baseline) != promotion.semantic_comparison_digest
    ):
        raise ValueError("promoted deterministic baseline semantic digest mismatch")


def verify_cross_workspace_isolation(
    experiment: ExperimentResult, artifact: EligibilityArtifact
) -> None:
    """Bind the Laravel isolation probe to the deterministic result lineage."""
    lineage = experiment.lineage
    mapping_digest = content_digest(
        [item.model_dump(mode="json") for item in artifact.documents]
    )
    expected = (
        artifact.contract_id,
        artifact.artifact_digest,
        artifact.comparability_digest,
        artifact.eligibility_catalogue.version,
        artifact.eligibility_catalogue.digest,
        artifact.resolver.source_digest,
        artifact.resolver.configuration_digest,
        artifact.evaluated_at,
        mapping_digest,
        artifact.repository_commit,
    )
    actual = (
        lineage.eligibility_artifact_contract,
        lineage.eligibility_artifact_digest,
        lineage.eligibility_comparability_digest,
        lineage.eligibility_catalogue_version,
        lineage.eligibility_catalogue_digest,
        lineage.eligibility_resolver_source_digest,
        lineage.eligibility_configuration_digest,
        lineage.eligibility_evaluated_at,
        lineage.eligibility_document_mapping_digest,
        lineage.repository_commit,
    )
    if actual != expected:
        raise ValueError("cross-workspace probe does not match deterministic profile")


def verify_checksum_manifest(
    path: Path, *, required: frozenset[str] = frozenset()
) -> None:
    """Verify a bounded baseline inventory without permitting path escape."""
    directory = path.parent.resolve()
    recorded: set[str] = set()
    for line_number, line in enumerate(path.read_text().splitlines(), start=1):
        if not line.strip():
            continue
        parts = line.split(maxsplit=1)
        if len(parts) != 2:
            raise ValueError(f"invalid checksum entry on line {line_number}")
        expected, relative = parts
        relative = relative.removeprefix("*")
        relative_path = PurePosixPath(relative)
        if (
            len(expected) != 64
            or any(character not in "0123456789abcdef" for character in expected)
            or relative_path.is_absolute()
            or ".." in relative_path.parts
            or relative in recorded
        ):
            raise ValueError(f"invalid checksum entry: {relative}")
        target = (directory / Path(*relative_path.parts)).resolve()
        if target.parent != directory or not target.is_file():
            raise ValueError(f"invalid baseline checksum target: {relative}")
        actual = hashlib.sha256(target.read_bytes()).hexdigest()
        if actual != expected:
            raise ValueError(f"baseline checksum mismatch: {relative}")
        recorded.add(relative)
    missing = required - recorded
    if missing:
        raise ValueError(
            "baseline checksum manifest is incomplete: " + ", ".join(sorted(missing))
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
        deterministic_profile_digest=experiment.lineage.deterministic_profile_digest,
        repository_commit=experiment.lineage.repository_commit,
        semantic_comparison_digest=(
            semantic_comparison_digest(experiment)
            if experiment.lineage.deterministic_profile_digest is not None
            else None
        ),
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
            continue
        if slice_name not in baseline.slices:
            failures.add(f"missing_baseline_slice:{slice_name}")
            continue
        candidate_metrics = candidate.slices[slice_name].metrics
        baseline_metrics = baseline.slices[slice_name].metrics
        if candidate_metrics is None or baseline_metrics is None:
            if candidate_metrics is not baseline_metrics:
                failures.add(f"slice_metrics_mismatch:{slice_name}")
            continue
        for metric, tolerance in policy.allowed_regressions.items():
            before = getattr(baseline_metrics, metric)
            after = getattr(candidate_metrics, metric)
            if after < before - tolerance:
                failures.add(f"slice_regression:{slice_name}:{metric}")
    return not failures, tuple(sorted(failures))


def assess_deterministic_gate(
    candidate: ExperimentResult,
    baseline: ExperimentResult,
    policy: QualityGatePolicy,
    artifact: EligibilityArtifact,
) -> tuple[bool, tuple[str, ...]]:
    """Assess deterministic metrics with the approved bound isolation proof."""
    verify_cross_workspace_isolation(candidate, artifact)
    metric_policy = policy.model_copy(
        update={
            "load_bearing_slices": tuple(
                name for name in policy.load_bearing_slices if name != "cross-workspace"
            )
        }
    )
    return assess_gate(candidate, baseline, metric_policy)


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
