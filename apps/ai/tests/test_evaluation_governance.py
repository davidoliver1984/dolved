import hashlib
import json
from datetime import UTC, datetime, timedelta
from pathlib import Path

import pytest
from pydantic import ValidationError

from app.evaluation.canonical import content_digest
from app.evaluation.current_retrieval import (
    EligibilityArtifact,
    load_verified_eligibility_artifact,
)
from app.evaluation.governance import (
    assess_deterministic_gate,
    assess_gate,
    deterministic_profile_manifest,
    promote_baseline,
    record_gate_decision,
    semantic_comparison_digest,
    verify_baseline_identity,
    verify_checksum_manifest,
    verify_deterministic_profile_digest,
    verify_promoted_deterministic_baseline,
)
from app.evaluation.models import (
    AggregateResult,
    ExperimentLineage,
    ExperimentResult,
    GateDecision,
    MetricValues,
    QualityGatePolicy,
)
from app.evaluation.reporting import comparison_report, deterministic_candidate_report


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


def deterministic_experiment(experiment_id: str) -> ExperimentResult:
    result = experiment(experiment_id)
    lineage = result.lineage.model_copy(
        update={
            "sparse_profile_fingerprint": "d" * 64,
            "reranker_profile_fingerprint": "e" * 64,
            "plan_catalogue_checksum": "f" * 64,
            "eligibility_artifact_contract": "deterministic-eligibility-v1",
            "eligibility_artifact_digest": "1" * 64,
            "eligibility_comparability_digest": "6" * 64,
            "eligibility_catalogue_version": "dolved-care-engineering-v2",
            "eligibility_catalogue_digest": "2" * 64,
            "eligibility_resolver_source_digest": "3" * 64,
            "eligibility_configuration_digest": "4" * 64,
            "eligibility_evaluated_at": "2026-08-01T12:00:00Z",
            "eligibility_document_mapping_digest": "5" * 64,
            "planner": {
                "provider": "deterministic",
                "model": "authored-engineering-plans-v2",
            },
            "retrieval_configuration": {"rrf_k": 5},
        }
    )
    result = result.model_copy(update={"lineage": lineage})
    return result.model_copy(
        update={
            "lineage": lineage.model_copy(
                update={
                    "deterministic_profile_digest": content_digest(
                        deterministic_profile_manifest(result)
                    ),
                }
            ),
        }
    )


def eligibility_artifact_data() -> dict[str, object]:
    body: dict[str, object] = {
        "schema_version": "v1",
        "contract_id": "deterministic-eligibility-v1",
        "run_id": "deterministic-test-run",
        "repository_commit": "a" * 40,
        "evaluated_at": "2026-08-01T12:00:00Z",
        "workspace_public_id": "00000000-0000-0000-0000-000000000001",
        "plan_catalogue": {"version": "v2", "digest": "1" * 64},
        "eligibility_catalogue": {
            "version": "dolved-care-engineering-v2",
            "digest": "2" * 64,
            "document_catalog_digest": "3" * 64,
            "organisation_digest": "4" * 64,
        },
        "resolver": {
            "implementation": "App\\Services\\Retrieval\\EligibilityResolver",
            "boundary": "evaluation:resolve-current-eligibility",
            "source_digest": "5" * 64,
            "configuration_digest": "6" * 64,
        },
        "documents": [
            {
                "evaluation_document_version_id": "document.v1",
                "public_document_id": "00000000-0000-0000-0000-000000000002",
                "qdrant_document_id": "00000000-0000-0000-0000-000000000003",
            }
        ],
        "entries": [
            {
                "case_id": "case.current",
                "variant_id": "direct",
                "question_digest": "7" * 64,
                "outcome": "evidence_found",
                "reason": None,
                "clarification_source": None,
                "resolved_location_public_id": None,
                "document_public_ids_by_side": {
                    "primary": ["00000000-0000-0000-0000-000000000002"]
                },
            }
        ],
        "probes": {
            "no_active_corpus_generation": {
                "resolver_executed": True,
                "outcome": "no_eligible_evidence",
                "eligible_document_count": 0,
            }
        },
        "isolation": {
            "foreign_workspace_probe_executed": True,
            "cross_workspace_document_count_in_scopes": 0,
        },
    }
    comparable = {
        key: body[key]
        for key in (
            "schema_version",
            "contract_id",
            "evaluated_at",
            "plan_catalogue",
            "eligibility_catalogue",
            "resolver",
            "documents",
            "entries",
            "probes",
            "isolation",
        )
    }
    body["comparability_digest"] = content_digest(comparable)
    body["artifact_digest"] = content_digest(body)
    return body


def bound_deterministic_experiment(
    experiment_id: str, artifact: EligibilityArtifact
) -> ExperimentResult:
    result = deterministic_experiment(experiment_id)
    mapping_digest = content_digest(
        [item.model_dump(mode="json") for item in artifact.documents]
    )
    lineage = result.lineage.model_copy(
        update={
            "repository_commit": artifact.repository_commit,
            "plan_catalogue_checksum": artifact.plan_catalogue.digest,
            "eligibility_artifact_contract": artifact.contract_id,
            "eligibility_artifact_digest": artifact.artifact_digest,
            "eligibility_comparability_digest": artifact.comparability_digest,
            "eligibility_catalogue_version": artifact.eligibility_catalogue.version,
            "eligibility_catalogue_digest": artifact.eligibility_catalogue.digest,
            "eligibility_resolver_source_digest": artifact.resolver.source_digest,
            "eligibility_configuration_digest": artifact.resolver.configuration_digest,
            "eligibility_evaluated_at": artifact.evaluated_at,
            "eligibility_document_mapping_digest": mapping_digest,
        }
    )
    unbound = result.model_copy(update={"lineage": lineage})
    return unbound.model_copy(
        update={
            "lineage": lineage.model_copy(
                update={
                    "deterministic_profile_digest": content_digest(
                        deterministic_profile_manifest(unbound)
                    )
                }
            )
        }
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
    assert failures == (
        "regression:recall_at_k",
        "slice_regression:CURRENT:recall_at_k",
    )


def test_gate_enforces_metric_bearing_slice_presence() -> None:
    baseline = experiment("baseline")
    missing = experiment("missing").model_copy(update={"slices": {}})
    passed, failures = assess_gate(missing, baseline, policy())
    assert not passed
    assert failures == ("missing_slice:CURRENT",)


@pytest.mark.parametrize(
    "slice_name", ("CURRENT", "COMPARE", "applicability", "adversarial")
)
def test_gate_enforces_metric_bearing_slice_regressions(slice_name: str) -> None:
    baseline = experiment("baseline")
    regressed_slice = baseline.aggregate.model_copy(
        update={
            "metrics": baseline.aggregate.metrics.model_copy(
                update={"recall_at_k": 0.5}
            )
            if baseline.aggregate.metrics is not None
            else None
        }
    )
    candidate = experiment("candidate").model_copy(
        update={"slices": {slice_name: regressed_slice}}
    )
    baseline = baseline.model_copy(update={"slices": {slice_name: baseline.aggregate}})
    slice_policy = policy().model_copy(update={"load_bearing_slices": (slice_name,)})
    passed, failures = assess_gate(candidate, baseline, slice_policy)
    assert not passed
    assert failures == (f"slice_regression:{slice_name}:recall_at_k",)


def test_bound_cross_workspace_probe_satisfies_only_that_requirement() -> None:
    artifact = EligibilityArtifact.model_validate(eligibility_artifact_data())
    baseline = bound_deterministic_experiment("baseline", artifact)
    candidate = bound_deterministic_experiment("candidate", artifact)
    cross_policy = policy().model_copy(
        update={"load_bearing_slices": ("CURRENT", "cross-workspace")}
    )

    assert assess_deterministic_gate(candidate, baseline, cross_policy, artifact) == (
        True,
        (),
    )

    another_slice = cross_policy.model_copy(
        update={"load_bearing_slices": ("CURRENT", "COMPARE", "cross-workspace")}
    )
    passed, failures = assess_deterministic_gate(
        candidate, baseline, another_slice, artifact
    )
    assert not passed
    assert failures == ("missing_slice:COMPARE",)

    for absolute_failure in ("cross_workspace_evidence", "unauthorised_evidence"):
        failed_candidate = candidate.model_copy(
            update={"hard_failures": (absolute_failure,)}
        )
        passed, failures = assess_deterministic_gate(
            failed_candidate, baseline, cross_policy, artifact
        )
        assert not passed
        assert failures == (absolute_failure,)


def test_cross_workspace_probe_fails_closed_for_profile_or_artifact_tampering(
    tmp_path: Path,
) -> None:
    artifact_data = eligibility_artifact_data()
    path = tmp_path / "eligibility-artifact.json"
    path.write_text(json.dumps(artifact_data))
    artifact = load_verified_eligibility_artifact(path)
    candidate = bound_deterministic_experiment("candidate", artifact)

    mismatched = artifact.model_copy(update={"comparability_digest": "8" * 64})
    with pytest.raises(ValueError, match="does not match deterministic profile"):
        assess_deterministic_gate(candidate, candidate, policy(), mismatched)

    artifact_data["run_id"] = "tampered-run"
    path.write_text(json.dumps(artifact_data))
    with pytest.raises(ValueError, match="eligibility artifact digest mismatch"):
        load_verified_eligibility_artifact(path)


@pytest.mark.parametrize(
    ("isolation", "message"),
    (
        ({"cross_workspace_document_count_in_scopes": 0}, "Field required"),
        (
            {
                "foreign_workspace_probe_executed": False,
                "cross_workspace_document_count_in_scopes": 0,
            },
            "Input should be True",
        ),
        (
            {
                "foreign_workspace_probe_executed": True,
                "cross_workspace_document_count_in_scopes": 1,
            },
            "Input should be 0",
        ),
    ),
)
def test_cross_workspace_probe_shape_is_fail_closed(
    isolation: dict[str, object], message: str
) -> None:
    artifact_data = eligibility_artifact_data()
    artifact_data["isolation"] = isolation
    with pytest.raises(ValidationError, match=message):
        EligibilityArtifact.model_validate(artifact_data)


def test_deterministic_promotion_binds_and_recomputes_execution_profile() -> None:
    baseline = deterministic_experiment("deterministic.baseline")
    promotion = promote_baseline(
        baseline, promoted_by="David", reason="Reviewed deterministic baseline"
    )

    verify_promoted_deterministic_baseline(baseline, promotion)
    verify_deterministic_profile_digest(baseline)
    assert promotion.repository_commit == baseline.lineage.repository_commit
    assert promotion.semantic_comparison_digest == semantic_comparison_digest(baseline)

    drifted = baseline.model_copy(
        update={
            "lineage": baseline.lineage.model_copy(
                update={
                    "retrieval_configuration": {"rrf_k": 60},
                }
            ),
        }
    )
    with pytest.raises(ValueError, match="does not match lineage"):
        verify_deterministic_profile_digest(drifted)

    eligibility_drift = baseline.model_copy(
        update={
            "lineage": baseline.lineage.model_copy(
                update={"eligibility_catalogue_digest": "9" * 64}
            )
        }
    )
    with pytest.raises(ValueError, match="does not match lineage"):
        verify_deterministic_profile_digest(eligibility_drift)

    exact_run_artifact_drift = baseline.model_copy(
        update={
            "lineage": baseline.lineage.model_copy(
                update={"eligibility_artifact_digest": "8" * 64}
            )
        }
    )
    verify_deterministic_profile_digest(exact_run_artifact_drift)

    comparability_drift = baseline.model_copy(
        update={
            "lineage": baseline.lineage.model_copy(
                update={"eligibility_comparability_digest": "7" * 64}
            )
        }
    )
    with pytest.raises(ValueError, match="does not match lineage"):
        verify_deterministic_profile_digest(comparability_drift)

    repository_drift = baseline.model_copy(
        update={
            "lineage": baseline.lineage.model_copy(
                update={"repository_commit": "different"}
            )
        }
    )
    with pytest.raises(ValueError, match="repository identity mismatch"):
        verify_promoted_deterministic_baseline(repository_drift, promotion)

    semantic_drift = baseline.model_copy(
        update={"aggregate": baseline.aggregate.model_copy(update={"case_count": 2})}
    )
    with pytest.raises(ValueError, match="semantic digest mismatch"):
        verify_promoted_deterministic_baseline(semantic_drift, promotion)

    promotion_drift = promotion.model_copy(
        update={"semantic_comparison_digest": "0" * 64}
    )
    with pytest.raises(ValueError, match="semantic digest mismatch"):
        verify_promoted_deterministic_baseline(baseline, promotion_drift)


def test_native_historical_promotion_remains_compatible_without_profile_digest() -> (
    None
):
    baseline = experiment("historical.baseline")
    promotion = promote_baseline(
        baseline, promoted_by="David", reason="Historical baseline"
    )

    assert promotion.deterministic_profile_digest is None
    verify_baseline_identity(experiment("historical.candidate"), promotion)


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


def test_deterministic_candidate_report_is_explicitly_unpromoted() -> None:
    report = deterministic_candidate_report(
        deterministic_experiment("deterministic.candidate"),
        operational={"document_count": 2, "chunk_count": 3, "query_count": 1},
    )

    assert "CANDIDATE — NOT PROMOTED" in report
    assert "External provider calls: 0" in report
    assert "Human review and an explicit promotion record are required" in report


def test_checksum_manifest_requires_promotion_and_result(tmp_path: Path) -> None:
    result = tmp_path / "experiment-result.json"
    result.write_text("result")
    manifest = tmp_path / "checksums.sha256"
    manifest.write_text(
        f"{hashlib.sha256(result.read_bytes()).hexdigest()}  {result.name}\n"
    )

    with pytest.raises(ValueError, match="manifest is incomplete"):
        verify_checksum_manifest(
            manifest,
            required=frozenset({"experiment-result.json", "baseline-promotion.json"}),
        )


def test_checksum_manifest_rejects_path_escape_and_duplicates(tmp_path: Path) -> None:
    result = tmp_path / "experiment-result.json"
    result.write_text("result")
    digest = hashlib.sha256(result.read_bytes()).hexdigest()
    manifest = tmp_path / "checksums.sha256"
    manifest.write_text(f"{digest}  ../experiment-result.json\n")
    with pytest.raises(ValueError, match="invalid checksum entry"):
        verify_checksum_manifest(manifest)

    manifest.write_text(f"{digest}  {result.name}\n{digest}  {result.name}\n")
    with pytest.raises(ValueError, match="invalid checksum entry"):
        verify_checksum_manifest(manifest)


def test_checksum_manifest_rejects_tampered_baseline(tmp_path: Path) -> None:
    result = tmp_path / "experiment-result.json"
    result.write_text("reviewed result")
    manifest = tmp_path / "checksums.sha256"
    manifest.write_text(
        f"{hashlib.sha256(result.read_bytes()).hexdigest()}  {result.name}\n"
    )
    result.write_text("tampered result")

    with pytest.raises(ValueError, match="baseline checksum mismatch"):
        verify_checksum_manifest(manifest)
