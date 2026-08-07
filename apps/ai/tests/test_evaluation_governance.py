from datetime import UTC, datetime, timedelta

import pytest
from pydantic import ValidationError

from app.evaluation.governance import (
    assess_gate,
    promote_baseline,
    record_gate_decision,
    verify_baseline_identity,
)
from app.evaluation.models import (
    AggregateResult,
    ExperimentLineage,
    ExperimentResult,
    GateDecision,
    MetricValues,
    QualityGatePolicy,
)
from app.evaluation.reporting import comparison_report


def experiment(experiment_id: str, recall: float = 1.0) -> ExperimentResult:
    aggregate = AggregateResult(
        metrics=MetricValues(recall_at_k=recall, precision_at_k=1, mrr=1, ndcg_at_k=1),
        planner_accuracy=1,
        eligibility_accuracy=1,
        outcome_accuracy=1,
        case_count=1,
    )
    return ExperimentResult(
        experiment_id=experiment_id,
        executed_at=datetime(2026, 8, 7, tzinfo=UTC),
        candidate_k=5,
        lineage=ExperimentLineage(
            repository_commit="abc",
            corpus_version="1",
            corpus_digest="a" * 64,
            policy_version="1",
            policy_digest="b" * 64,
            harness_version="v1",
            matching_algorithm="normalised-token-coverage-v1",
            planner={},
            embedding_profile_fingerprint="c" * 64,
            chunking_configuration={},
            retrieval_configuration={},
        ),
        aggregate=aggregate,
        slices={"CURRENT": aggregate},
        variants=(),
        hard_failures=(),
    )


def policy() -> QualityGatePolicy:
    return QualityGatePolicy(
        schema_version="v1",
        policy_version="1",
        absolute_failures=("cross_workspace_evidence",),
        load_bearing_slices=("CURRENT",),
        allowed_regressions={"recall_at_k": 0},
        advisory_metrics=("CONTEXT_RELEVANCE",),
    )


def test_promotion_is_distinct_and_digest_bound() -> None:
    baseline = experiment("baseline.one")
    promotion = promote_baseline(
        baseline, promoted_by="David", reason="Initial reviewed baseline"
    )
    verify_baseline_identity(experiment("candidate.one"), promotion)
    changed = experiment("candidate.changed").model_copy(
        update={
            "lineage": baseline.lineage.model_copy(update={"corpus_digest": "d" * 64})
        }
    )
    with pytest.raises(ValueError, match="does not match"):
        verify_baseline_identity(changed, promotion)


def test_gate_reports_regressions_and_never_promotes_automatically() -> None:
    passed, failures = assess_gate(
        experiment("candidate", recall=0.5), experiment("baseline"), policy()
    )
    assert not passed
    assert failures == ("regression:recall_at_k",)


def test_waiver_requires_an_expiry() -> None:
    now = datetime(2026, 8, 7, tzinfo=UTC)
    with pytest.raises(ValidationError):
        record_gate_decision(
            experiment_id="candidate",
            decision=GateDecision.WAIVED,
            reviewer="David",
            reason="Temporary",
            decided_at=now,
        )
    record = record_gate_decision(
        experiment_id="candidate",
        decision=GateDecision.WAIVED,
        reviewer="David",
        reason="Temporary",
        decided_at=now,
        waiver_expires_at=now + timedelta(days=7),
    )
    assert record.waiver_expires_at is not None


def test_human_report_contains_lineage_deltas_slices_and_failures() -> None:
    report = comparison_report(experiment("candidate", 0.9), experiment("baseline"))
    assert "| recall_at_k | 1.0000 | 0.9000 | -0.1000 |" in report
    assert "**CURRENT**" in report
    assert "No absolute failures." in report
