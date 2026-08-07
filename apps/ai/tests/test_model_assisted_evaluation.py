from types import SimpleNamespace

import pytest

from app.evaluation.model_assisted import (
    FakeModelAssistedEvaluator,
    evaluate_recorded_contexts,
)
from app.evaluation.models import (
    EvaluationCase,
    EvaluationCorpus,
    ModelAssistedEvaluationRequest,
    ModelAssistedMetric,
    ModelAssistedStatus,
    QuestionVariant,
    RetrievedCandidate,
    VariantObservation,
)
from app.evaluation.ragas_adapter import RagasEvaluator


def request() -> ModelAssistedEvaluationRequest:
    return ModelAssistedEvaluationRequest(
        case_id="case.one",
        variant_id="exact",
        question="What is the policy?",
        retrieved_evidence=(
            RetrievedCandidate(
                candidate_id="chunk.one",
                document_family_id="family.one",
                document_version_id="document.v1",
                rank=1,
                text="The policy allows remote work.",
            ),
        ),
    )


@pytest.mark.asyncio
async def test_fake_evaluator_is_offline_and_application_owned() -> None:
    result = await FakeModelAssistedEvaluator(0.75).evaluate(request())
    assert result.status is ModelAssistedStatus.COMPLETED
    assert (result.case_id, result.variant_id) == ("case.one", "exact")
    assert result.scores[ModelAssistedMetric.CONTEXT_RELEVANCE] == 0.75


@pytest.mark.asyncio
async def test_ragas_adapter_maps_aggregate_context_relevance() -> None:
    class Scorer:
        async def ascore(self, **kwargs: object) -> SimpleNamespace:
            assert kwargs == {
                "user_input": "What is the policy?",
                "retrieved_contexts": ["The policy allows remote work."],
            }
            return SimpleNamespace(value=0.8)

    evaluator = RagasEvaluator(
        object(), provider="test", model="judge", ragas_version="0.4.3", scorer=Scorer()
    )
    result = await evaluator.evaluate(request())
    assert result.status is ModelAssistedStatus.COMPLETED
    assert result.scores[ModelAssistedMetric.CONTEXT_RELEVANCE] == 0.8


@pytest.mark.asyncio
async def test_ragas_failure_is_controlled_and_does_not_raise() -> None:
    class BrokenScorer:
        async def ascore(self, **kwargs: object) -> None:
            raise TimeoutError

    evaluator = RagasEvaluator(
        object(),
        provider="test",
        model="judge",
        ragas_version="0.4.3",
        scorer=BrokenScorer(),
    )
    result = await evaluator.evaluate(request())
    assert result.status is ModelAssistedStatus.FAILED
    assert result.failure_code == "evaluator_failed"


@pytest.mark.asyncio
async def test_recorded_context_wiring_preserves_case_and_variant_identity() -> None:
    corpus = EvaluationCorpus(
        schema_version="v1",
        corpus_version="1",
        title="Test corpus",
        matching_algorithm="normalised-token-coverage-v1",
        cases=(
            EvaluationCase(
                case_id="case.one",
                variants=(
                    QuestionVariant(variant_id="exact", question="What is the policy?"),
                ),
                slices=("CURRENT",),
            ),
        ),
    )
    observation = VariantObservation(
        case_id="case.one",
        variant_id="exact",
        candidates=request().retrieved_evidence,
        planner_correct=True,
        eligibility_correct=True,
        outcome_correct=True,
    )
    results = await evaluate_recorded_contexts(
        evaluator=FakeModelAssistedEvaluator(0.6),
        corpus=corpus,
        observations=(observation,),
    )
    assert len(results) == 1
    assert results[0].case_id == "case.one"
    assert results[0].scores[ModelAssistedMetric.CONTEXT_RELEVANCE] == 0.6


@pytest.mark.integration
@pytest.mark.asyncio
async def test_live_ragas_context_relevance_is_opt_in() -> None:
    import os

    if os.getenv("RUN_LIVE_RAGAS_TESTS") != "1":
        pytest.skip("set RUN_LIVE_RAGAS_TESTS=1 with evaluator credentials")
    from openai import AsyncOpenAI
    from ragas.llms.base import llm_factory

    model = os.environ["RAGAS_EVALUATOR_MODEL"]
    llm = llm_factory(
        model,
        provider="openai",
        client=AsyncOpenAI(api_key=os.environ["OPENAI_API_KEY"]),
    )
    result = await RagasEvaluator(
        llm, provider="openai", model=model, ragas_version="0.4.3"
    ).evaluate(request())
    assert result.status is ModelAssistedStatus.COMPLETED
