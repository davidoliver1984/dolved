import json
from datetime import UTC, datetime
from pathlib import Path
from uuid import UUID

import jsonschema
import pytest

from app.evaluation.canonical import content_digest
from app.evaluation.models import (
    AggregateResult,
    BaselinePromotion,
    CandidateFunnel,
    CandidateStageLineage,
    EvaluationCorpus,
    EvaluationTextCaptureMode,
    ExpectedEvidenceIdentity,
    ExperimentLineage,
    ExperimentResult,
    GateDecision,
    ManualGateRecord,
    MetricValues,
    OperationalObservation,
    PlannerEvaluationObservation,
    QualityGatePolicy,
    VariantResult,
)

CONTRACT_ROOT = Path("/contracts/evaluation/v1")
EVALUATION_ROOT = Path("/evaluation")


@pytest.mark.parametrize("corpus_version", ["v1", "v2"])
def test_corpus_and_policy_validate_against_shared_schemas(
    corpus_version: str,
) -> None:
    pairs = (
        (
            EVALUATION_ROOT / f"corpus/{corpus_version}/corpus.json",
            CONTRACT_ROOT / "corpus.schema.json",
        ),
        (
            EVALUATION_ROOT / "policies/v1/policy.json",
            CONTRACT_ROOT / "policy.schema.json",
        ),
    )
    for value_path, schema_path in pairs:
        value = json.loads(value_path.read_text())
        jsonschema.Draft202012Validator(json.loads(schema_path.read_text())).validate(
            value
        )

    EvaluationCorpus.model_validate_json(pairs[0][0].read_text())
    QualityGatePolicy.model_validate_json(pairs[1][0].read_text())


@pytest.mark.parametrize("corpus_version", ["v1", "v2"])
def test_every_canonical_excerpt_exists_in_its_committed_source(
    corpus_version: str,
) -> None:
    corpus = EvaluationCorpus.model_validate_json(
        (EVALUATION_ROOT / f"corpus/{corpus_version}/corpus.json").read_text()
    )
    for case in corpus.cases:
        for unit in case.evidence_units:
            source = (EVALUATION_ROOT / unit.source_path).read_text()
            for excerpt in unit.canonical_excerpts:
                assert excerpt in source


@pytest.mark.parametrize("corpus_version", ["v1", "v2"])
def test_corpus_covers_every_required_v1_case_family(corpus_version: str) -> None:
    corpus = EvaluationCorpus.model_validate_json(
        (EVALUATION_ROOT / f"corpus/{corpus_version}/corpus.json").read_text()
    )
    slices = {slice_name for case in corpus.cases for slice_name in case.slices}
    assert {
        "CURRENT",
        "VALID_AT_DATE",
        "COMPARE",
        "scheduled-future",
        "late-approval",
        "withdrawn",
        "never-authoritative",
        "authority-gap",
        "predecessor-resurrection",
        "universal",
        "site-specific",
        "region-specific",
        "descendant-site",
        "location-alias",
        "clarification",
        "active-member",
        "non-member",
        "cross-workspace",
        "workspace-concealment",
        "empty-eligible-scope",
        "zero-candidates",
        "adversarial",
        "tables",
        "multi-evidence",
        "paraphrase",
        "synonymous-terminology",
    } <= slices


def test_v2_predecessor_resurrection_case_enforces_adr_0017() -> None:
    corpus = EvaluationCorpus.model_validate_json(
        (EVALUATION_ROOT / "corpus/v2/corpus.json").read_text()
    )
    case = next(
        item
        for item in corpus.cases
        if item.case_id == "temporal.predecessor-resurrection"
    )

    assert case.expected_outcome == "NO_ELIGIBLE_EVIDENCE"
    assert case.evidence_units == ()


def test_result_and_governance_records_validate_against_shared_schemas() -> None:
    now = datetime(2026, 8, 7, tzinfo=UTC)
    aggregate = AggregateResult(
        metrics=MetricValues(recall_at_k=1, precision_at_k=1, mrr=1, ndcg_at_k=1),
        planner_accuracy=1,
        eligibility_accuracy=1,
        outcome_accuracy=1,
        case_count=1,
    )
    result = ExperimentResult(
        experiment_id="contract.example",
        executed_at=now,
        candidate_k=3,
        lineage=ExperimentLineage(
            repository_commit="abc123",
            corpus_version="1",
            corpus_digest="a" * 64,
            policy_version="1",
            policy_digest="b" * 64,
            harness_version="retrieval-evaluation-v1",
            matching_algorithm="normalised-token-coverage-v1",
            planner={},
            embedding_profile_fingerprint="c" * 64,
            chunking_configuration={},
            retrieval_configuration={},
        ),
        aggregate=aggregate,
        slices={"CURRENT": aggregate},
        variants=(
            VariantResult(
                case_id="case.current",
                variant_id="direct",
                metrics=aggregate.metrics,
                side_metrics={"PRIMARY": aggregate.metrics}
                if aggregate.metrics
                else {},
                covered_evidence_ids=("evidence.current",),
                planner_correct=True,
                eligibility_correct=True,
                outcome_correct=True,
                hard_failures=(),
                operational=OperationalObservation(),
                text_capture_mode=EvaluationTextCaptureMode.BENCHMARK_TEXT,
                question="What is the current procedure?",
                expected_outcome="EVIDENCE_FOUND",
                expected_evidence=(
                    ExpectedEvidenceIdentity(
                        evidence_unit_id="evidence.current",
                        document_family_id="family.policy",
                        document_version_id="policy.v2",
                        source_path="documents/policy-v2.md",
                    ),
                ),
                candidate_lineage=(
                    CandidateStageLineage(
                        candidate_id="candidate.current",
                        chunk_id=UUID("11111111-1111-4111-8111-111111111111"),
                        document_family_id="family.policy",
                        document_version_id="policy.v2",
                        dense_rank=1,
                        dense_score=0.8,
                        fused_rank=1,
                        fused_score=0.032,
                        reranker_rank=1,
                        reranker_score=0.7,
                        passed_evidence_threshold=True,
                        included_in_final_evidence=True,
                        covered_evidence_unit_ids=("evidence.current",),
                    ),
                ),
                candidate_funnel=(
                    CandidateFunnel(
                        dense_candidate_count=10,
                        sparse_candidate_count=10,
                        unique_post_fusion_count=8,
                        candidates_sent_to_reranker=8,
                        candidates_surviving_threshold=2,
                        final_evidence_count=1,
                    ),
                ),
            ),
        ),
        hard_failures=(),
    )
    records = (
        (result, "../v2/experiment-result.schema.json"),
        (
            BaselinePromotion(
                experiment_id=result.experiment_id,
                corpus_version="1",
                corpus_digest="a" * 64,
                policy_version="1",
                policy_digest="b" * 64,
                promoted_by="David Oliver",
                promoted_at=now,
                reason="Reviewed initial baseline",
            ),
            "baseline-promotion.schema.json",
        ),
        (
            ManualGateRecord(
                experiment_id=result.experiment_id,
                decision=GateDecision.ACCEPTED,
                reviewer="David Oliver",
                decided_at=now,
                reason="Review accepted",
            ),
            "manual-gate.schema.json",
        ),
    )
    for record, schema_name in records:
        value = json.loads(record.model_dump_json())
        schema = json.loads((CONTRACT_ROOT / schema_name).read_text())
        jsonschema.Draft202012Validator(schema).validate(value)


def test_planner_contract_detail_is_forbidden_outside_benchmark_text_mode() -> None:
    with pytest.raises(ValueError, match="benchmark text capture"):
        VariantResult(
            case_id="case.current",
            variant_id="direct",
            metrics=None,
            side_metrics={},
            covered_evidence_ids=(),
            planner_correct=True,
            eligibility_correct=True,
            outcome_correct=True,
            hard_failures=(),
            operational=OperationalObservation(),
            planner_evaluation=PlannerEvaluationObservation(
                expected_contract={"temporal_mode": "CURRENT"},
                actual_plan={"temporal_mode": "CURRENT"},
                correct=True,
            ),
        )


def test_canonical_digest_is_order_independent_and_detects_mutation() -> None:
    assert content_digest({"b": 2, "a": 1}) == content_digest({"a": 1, "b": 2})
    assert content_digest({"a": 1}) != content_digest({"a": 2})
