import json
from datetime import UTC, datetime
from pathlib import Path

import jsonschema

from app.evaluation.canonical import content_digest
from app.evaluation.models import (
    AggregateResult,
    BaselinePromotion,
    EvaluationCorpus,
    ExperimentLineage,
    ExperimentResult,
    GateDecision,
    ManualGateRecord,
    MetricValues,
    QualityGatePolicy,
)

CONTRACT_ROOT = Path("/contracts/evaluation/v1")
EVALUATION_ROOT = Path("/evaluation")


def test_corpus_and_policy_validate_against_shared_schemas() -> None:
    pairs = (
        (
            EVALUATION_ROOT / "corpus/v1/corpus.json",
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


def test_every_canonical_excerpt_exists_in_its_committed_source() -> None:
    corpus = EvaluationCorpus.model_validate_json(
        (EVALUATION_ROOT / "corpus/v1/corpus.json").read_text()
    )
    for case in corpus.cases:
        for unit in case.evidence_units:
            source = (EVALUATION_ROOT / unit.source_path).read_text()
            for excerpt in unit.canonical_excerpts:
                assert excerpt in source


def test_corpus_covers_every_required_v1_case_family() -> None:
    corpus = EvaluationCorpus.model_validate_json(
        (EVALUATION_ROOT / "corpus/v1/corpus.json").read_text()
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
        variants=(),
        hard_failures=(),
    )
    records = (
        (result, "experiment-result.schema.json"),
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


def test_canonical_digest_is_order_independent_and_detects_mutation() -> None:
    assert content_digest({"b": 2, "a": 1}) == content_digest({"a": 1, "b": 2})
    assert content_digest({"a": 1}) != content_digest({"a": 2})
