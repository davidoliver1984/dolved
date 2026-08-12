from datetime import UTC, datetime

import pytest
from pydantic import ValidationError

from app.evaluation.canonical import content_digest
from app.evaluation.harness import RetrievalEvaluationHarness
from app.evaluation.matching import MATCHING_ALGORITHM, candidates_cover, normalise_text
from app.evaluation.metrics import evaluate_metrics, evaluate_metrics_by_side
from app.evaluation.models import (
    EvaluationCase,
    EvaluationCorpus,
    EvaluationTextCaptureMode,
    EvidenceUnit,
    ExperimentLineage,
    QuestionVariant,
    RetrievedCandidate,
    VariantObservation,
)


def unit(
    *, evidence_id: str = "evidence.one", excerpts: tuple[str, ...] = ("alpha beta",)
) -> EvidenceUnit:
    return EvidenceUnit(
        evidence_id=evidence_id,
        document_family_id="family.one",
        document_version_id="document.v1",
        source_path="sources/example.md",
        canonical_excerpts=excerpts,
    )


def candidate(candidate_id: str, rank: int, text: str) -> RetrievedCandidate:
    return RetrievedCandidate(
        candidate_id=candidate_id,
        document_family_id="family.one",
        document_version_id="document.v1",
        rank=rank,
        text=text,
    )


def lineage() -> ExperimentLineage:
    return ExperimentLineage(
        repository_commit="abc123",
        corpus_version="1",
        corpus_digest="a" * 64,
        policy_version="1",
        policy_digest="b" * 64,
        harness_version="retrieval-evaluation-v1",
        matching_algorithm=MATCHING_ALGORITHM,
        planner={"provider": "fake", "model": "fixed"},
        embedding_profile_fingerprint="c" * 64,
        chunking_configuration={"strategy": "baseline"},
        retrieval_configuration={"candidate_k": 2},
    )


def test_matching_is_normalised_and_supports_combined_chunk_coverage() -> None:
    evidence = unit(excerpts=("Annual LEAVE is twenty-eight days",))
    chunks = (
        candidate("chunk.1", 1, "Annual leave is"),
        candidate("chunk.2", 2, "twenty eight days."),
    )

    assert normalise_text("  Annual\nLEAVE  ") == "annual leave"
    assert candidates_cover(evidence, chunks)
    metrics, covered = evaluate_metrics((evidence,), chunks, 2)
    assert metrics.mrr == 0.5
    assert metrics.ndcg_at_k == pytest.approx(1 / 1.584962500721156)
    assert covered == ("evidence.one",)


def test_metrics_count_distinct_evidence_and_do_not_reward_duplicates() -> None:
    evidence = unit()
    chunks = (
        candidate("chunk.1", 1, "alpha beta"),
        candidate("chunk.2", 2, "alpha beta repeated"),
    )

    metrics, covered = evaluate_metrics((evidence,), chunks, 2)

    assert metrics.recall_at_k == 1
    assert metrics.precision_at_k == 0.5
    assert metrics.mrr == 1
    assert metrics.ndcg_at_k == 1
    assert covered == ("evidence.one",)


def test_wrong_document_version_never_covers_evidence() -> None:
    wrong = candidate("chunk.1", 1, "alpha beta").model_copy(
        update={"document_version_id": "document.v2"}
    )
    metrics, covered = evaluate_metrics((unit(),), (wrong,), 1)
    assert metrics.recall_at_k == 0
    assert covered == ()


def test_compare_sides_are_evaluated_independently() -> None:
    primary = unit(evidence_id="primary")
    comparison = unit(evidence_id="comparison").model_copy(
        update={"side": "COMPARISON"}
    )
    candidates = (
        candidate("primary.chunk", 1, "alpha beta"),
        candidate("comparison.chunk", 1, "alpha beta").model_copy(
            update={"side": "COMPARISON"}
        ),
    )
    metrics, side_metrics, covered = evaluate_metrics_by_side(
        (primary, comparison), candidates, 1
    )
    assert metrics.recall_at_k == 1
    assert side_metrics["PRIMARY"].mrr == 1
    assert side_metrics["COMPARISON"].mrr == 1
    assert set(covered) == {"primary", "comparison"}


def test_canonical_digest_is_order_independent_and_content_sensitive() -> None:
    assert content_digest({"b": 2, "a": 1}) == content_digest({"a": 1, "b": 2})
    assert content_digest({"a": 1}) != content_digest({"a": 2})


def test_harness_aggregates_variants_within_cases_before_corpus() -> None:
    corpus = EvaluationCorpus(
        schema_version="v1",
        corpus_version="1",
        title="test",
        matching_algorithm=MATCHING_ALGORITHM,
        cases=(
            EvaluationCase(
                case_id="case.many",
                variants=(
                    QuestionVariant(variant_id="one", question="one"),
                    QuestionVariant(variant_id="two", question="two"),
                ),
                slices=("CURRENT",),
                evidence_units=(unit(),),
            ),
            EvaluationCase(
                case_id="case.single",
                variants=(QuestionVariant(variant_id="one", question="one"),),
                slices=("CURRENT",),
                evidence_units=(unit(evidence_id="evidence.two"),),
            ),
        ),
    )
    observations = (
        VariantObservation(
            case_id="case.many",
            variant_id="one",
            candidates=(candidate("a", 1, "alpha beta"),),
            planner_correct=True,
            eligibility_correct=True,
            outcome_correct=True,
        ),
        VariantObservation(
            case_id="case.many",
            variant_id="two",
            candidates=(),
            planner_correct=True,
            eligibility_correct=True,
            outcome_correct=True,
        ),
        VariantObservation(
            case_id="case.single",
            variant_id="one",
            candidates=(candidate("b", 1, "alpha beta"),),
            planner_correct=True,
            eligibility_correct=True,
            outcome_correct=True,
        ),
    )

    result = RetrievalEvaluationHarness().evaluate(
        experiment_id="experiment.case-weighting",
        corpus=corpus,
        observations=observations,
        lineage=lineage(),
        candidate_k=1,
        executed_at=datetime(2026, 8, 7, tzinfo=UTC),
    )

    assert result.aggregate.metrics is not None
    assert result.aggregate.metrics.recall_at_k == pytest.approx(0.75)
    assert result.slices["CURRENT"].case_count == 2


def test_missing_variant_is_an_absolute_lost_case_failure() -> None:
    corpus = EvaluationCorpus(
        schema_version="v1",
        corpus_version="1",
        title="test",
        matching_algorithm=MATCHING_ALGORITHM,
        cases=(
            EvaluationCase(
                case_id="case.one",
                variants=(QuestionVariant(variant_id="one", question="one"),),
                slices=("CURRENT",),
            ),
        ),
    )
    result = RetrievalEvaluationHarness().evaluate(
        experiment_id="experiment.missing",
        corpus=corpus,
        observations=(),
        lineage=lineage(),
        candidate_k=1,
    )
    assert "lost_evaluation_case" in result.hard_failures
    assert "lost_case:case.one:one" in result.hard_failures


def test_question_capture_is_privacy_safe_by_default_and_explicit_for_benchmarks() -> (
    None
):
    assert (
        VariantObservation(
            case_id="case.one",
            variant_id="one",
            planner_correct=True,
            eligibility_correct=True,
            outcome_correct=True,
        ).question
        is None
    )
    with pytest.raises(ValidationError, match="text capture is disabled"):
        VariantObservation(
            case_id="case.one",
            variant_id="one",
            planner_correct=True,
            eligibility_correct=True,
            outcome_correct=True,
            question="customer question",
        )
    with pytest.raises(ValidationError, match="cannot retain the raw question"):
        VariantObservation(
            case_id="case.one",
            variant_id="one",
            planner_correct=True,
            eligibility_correct=True,
            outcome_correct=True,
            text_capture_mode=EvaluationTextCaptureMode.REDACTED,
            question="customer question",
        )
    benchmark = VariantObservation(
        case_id="case.one",
        variant_id="one",
        planner_correct=True,
        eligibility_correct=True,
        outcome_correct=True,
        text_capture_mode=EvaluationTextCaptureMode.BENCHMARK_TEXT,
        question="fictional benchmark question",
    )
    assert benchmark.question == "fictional benchmark question"
