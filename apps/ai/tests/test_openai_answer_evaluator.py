from types import SimpleNamespace
from typing import Literal

import pytest

from app.evaluation.models import (
    ModelAssistedAnswerPart,
    ModelAssistedEvaluationRequest,
    ModelAssistedGenerationResult,
    ModelAssistedMetric,
    ModelAssistedStatus,
    RetrievedCandidate,
)
from app.evaluation.openai_answer_evaluator import (
    EVALUATOR_PROMPT_VERSION,
    EVALUATOR_REPRESENTATION_VERSION,
    AnswerEvaluationOutput,
    OpenAIAnswerEvaluator,
    PartJudgement,
)


def request(
    *, outcome: Literal["answered", "qualified", "insufficient_evidence"] = "qualified"
) -> ModelAssistedEvaluationRequest:
    parts = (
        ModelAssistedAnswerPart(
            part_index=1,
            text="The room stays closed until the review is complete.",
            evidence_ids=("ev-01",),
        ),
    )
    generated = ModelAssistedGenerationResult(
        outcome=outcome,
        answer_parts=parts if outcome != "insufficient_evidence" else (),
        unsupported_aspects=("authorising role",),
        insufficiency_reason="No relevant evidence."
        if outcome == "insufficient_evidence"
        else None,
    )
    metrics = (
        (ModelAssistedMetric.INSUFFICIENCY_CORRECTNESS,)
        if outcome == "insufficient_evidence"
        else (
            ModelAssistedMetric.ANSWER_PART_GROUNDEDNESS,
            ModelAssistedMetric.ANSWER_FACTUAL_PRECISION,
            ModelAssistedMetric.ANSWER_COMPLETENESS,
            ModelAssistedMetric.QUALIFIED_USEFULNESS,
        )
    )
    return ModelAssistedEvaluationRequest(
        case_id="case.one",
        variant_id="complete-result",
        question="Who reopens the room and what happens first?",
        retrieved_evidence=(
            RetrievedCandidate(
                candidate_id="ev-01",
                document_family_id="family.one",
                document_version_id="document.v1",
                rank=1,
                text="The room must remain closed until review is complete.",
            ),
        ),
        metrics=metrics,
        generated_result=generated,
        reference_answer="The room remains closed until review is complete.",
        reference_unsupported_aspects=("authorising role",),
    )


class FakeResponses:
    def __init__(self, output: AnswerEvaluationOutput) -> None:
        self.output = output
        self.calls: list[dict[str, object]] = []

    async def parse(self, **kwargs: object) -> SimpleNamespace:
        self.calls.append(kwargs)
        return SimpleNamespace(
            output_parsed=self.output,
            usage=SimpleNamespace(input_tokens=321, output_tokens=45),
        )


@pytest.mark.asyncio
async def test_complete_qualified_representation_is_structured_and_versioned() -> None:
    responses = FakeResponses(
        AnswerEvaluationOutput(
            part_judgements=[
                PartJudgement(part_index=1, grounded=True, unsupported_categories=[])
            ],
            factual_precision=1,
            completeness=1,
            qualification_useful=True,
            insufficiency_correct=None,
        )
    )
    evaluator = OpenAIAnswerEvaluator(
        api_key="test", client=SimpleNamespace(responses=responses)
    )
    result = await evaluator.evaluate(request())
    assert result.status is ModelAssistedStatus.COMPLETED
    assert result.scores == {
        ModelAssistedMetric.ANSWER_PART_GROUNDEDNESS: 1,
        ModelAssistedMetric.ANSWER_FACTUAL_PRECISION: 1,
        ModelAssistedMetric.ANSWER_COMPLETENESS: 1,
        ModelAssistedMetric.QUALIFIED_USEFULNESS: 1,
    }
    rendered = responses.calls[0]["input"]
    assert isinstance(rendered, str)
    assert responses.calls[0]["max_output_tokens"] == 2048
    assert EVALUATOR_REPRESENTATION_VERSION in rendered
    assert '"unsupported_aspects":["authorising role"]' in rendered
    assert '"evidence_ids":["ev-01"]' in rendered
    assert result.input_tokens == 321
    assert result.output_tokens == 45
    assert result.cost_usd is None
    assert result.retry_count == 0
    assert result.evaluator_identity["prompt_version"] == EVALUATOR_PROMPT_VERSION
    grounded = result.metric_observations[0]
    assert grounded.answer_part_indices == (1,)
    assert grounded.input_tokens == 321
    assert grounded.output_tokens == 45
    assert grounded.retry_count == 0
    assert grounded.cost_usd is None
    assert grounded.provider_status is None


@pytest.mark.asyncio
async def test_invalid_part_identity_fails_closed_without_semantic_repair() -> None:
    responses = FakeResponses(
        AnswerEvaluationOutput(
            part_judgements=[
                PartJudgement(part_index=2, grounded=True, unsupported_categories=[])
            ],
            factual_precision=1,
            completeness=1,
            qualification_useful=True,
            insufficiency_correct=None,
        )
    )
    evaluator = OpenAIAnswerEvaluator(
        api_key="test", client=SimpleNamespace(responses=responses)
    )
    result = await evaluator.evaluate(request())
    assert result.status is ModelAssistedStatus.FAILED
    assert result.failure_code == "contract_validation_failure"
    assert result.scores == {}
    assert all(item.failure_code for item in result.metric_observations)


@pytest.mark.asyncio
async def test_insufficiency_representation_is_evaluable_without_answer_parts() -> None:
    responses = FakeResponses(
        AnswerEvaluationOutput(
            part_judgements=[],
            factual_precision=None,
            completeness=None,
            qualification_useful=None,
            insufficiency_correct=True,
        )
    )
    evaluator = OpenAIAnswerEvaluator(
        api_key="test", client=SimpleNamespace(responses=responses)
    )
    result = await evaluator.evaluate(request(outcome="insufficient_evidence"))
    assert result.scores == {ModelAssistedMetric.INSUFFICIENCY_CORRECTNESS: 1}
    assert result.details["insufficiency_correct"] is True
