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
    output_model_for_request,
)


def request(
    *,
    outcome: Literal["answered", "qualified", "insufficient_evidence"] = "qualified",
    part_count: int = 1,
) -> ModelAssistedEvaluationRequest:
    parts = tuple(
        ModelAssistedAnswerPart(
            part_index=index,
            text=f"Supported answer part {index}.",
            evidence_ids=("ev-01",),
        )
        for index in range(1, part_count + 1)
    )
    generated = ModelAssistedGenerationResult(
        outcome=outcome,
        answer_parts=parts if outcome != "insufficient_evidence" else (),
        unsupported_aspects=() if outcome == "answered" else ("authorising role",),
        insufficiency_reason="No relevant evidence."
        if outcome == "insufficient_evidence"
        else None,
    )
    if outcome == "insufficient_evidence":
        metrics = (ModelAssistedMetric.INSUFFICIENCY_CORRECTNESS,)
    else:
        metrics = (
            ModelAssistedMetric.ANSWER_PART_GROUNDEDNESS,
            ModelAssistedMetric.ANSWER_FACTUAL_PRECISION,
            ModelAssistedMetric.ANSWER_COMPLETENESS,
        )
        if outcome == "qualified":
            metrics += (ModelAssistedMetric.QUALIFIED_USEFULNESS,)
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
            usage=SimpleNamespace(
                input_tokens=321,
                input_tokens_details=SimpleNamespace(cached_tokens=21),
                output_tokens=45,
            ),
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
    assert result.cached_input_tokens == 21
    assert result.output_tokens == 45
    assert result.cost_usd == 0.00016552
    assert result.retry_count == 0
    assert result.evaluator_identity["prompt_version"] == EVALUATOR_PROMPT_VERSION
    grounded = result.metric_observations[0]
    assert grounded.answer_part_indices == (1,)
    assert grounded.input_tokens == 321
    assert grounded.cached_input_tokens == 21
    assert grounded.output_tokens == 45
    assert grounded.retry_count == 0
    assert grounded.cost_usd == 0.00016552
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
    assert result.failure_code == "part_indices_mismatch"
    assert result.scores == {}
    assert result.input_tokens == 321
    assert result.cached_input_tokens == 21
    assert result.output_tokens == 45
    assert result.cost_usd == 0.00016552
    assert result.details == {"validation_reason": "part_indices_mismatch"}
    assert all(item.failure_code for item in result.metric_observations)


@pytest.mark.parametrize(
    ("actual_indices", "part_count"),
    [
        ([1], 2),
        ([1, 2, 3], 2),
        ([2, 1], 2),
        ([1, 1], 2),
    ],
)
@pytest.mark.asyncio
async def test_part_indices_must_match_exactly_in_order(
    actual_indices: list[int], part_count: int
) -> None:
    responses = FakeResponses(
        AnswerEvaluationOutput(
            part_judgements=[
                PartJudgement(
                    part_index=index, grounded=True, unsupported_categories=[]
                )
                for index in actual_indices
            ],
            factual_precision=1,
            completeness=1,
            qualification_useful=True,
            insufficiency_correct=None,
        )
    )
    result = await OpenAIAnswerEvaluator(
        api_key="test", client=SimpleNamespace(responses=responses)
    ).evaluate(request(part_count=part_count))
    assert result.failure_code == "part_indices_mismatch"


@pytest.mark.parametrize(
    ("outcome", "overrides", "failure_code"),
    [
        (
            "qualified",
            {"qualification_useful": None},
            "qualification_usefulness_missing",
        ),
        (
            "insufficient_evidence",
            {"insufficiency_correct": None},
            "insufficiency_correctness_missing",
        ),
        ("qualified", {"factual_precision": None}, "factual_precision_missing"),
        ("qualified", {"completeness": None}, "completeness_missing"),
    ],
)
@pytest.mark.asyncio
async def test_request_dependent_missing_values_have_closed_failure_codes(
    outcome: Literal["answered", "qualified", "insufficient_evidence"],
    overrides: dict[str, object],
    failure_code: str,
) -> None:
    values: dict[str, object] = {
        "part_judgements": []
        if outcome == "insufficient_evidence"
        else [PartJudgement(part_index=1, grounded=True, unsupported_categories=[])],
        "factual_precision": None if outcome == "insufficient_evidence" else 1,
        "completeness": None if outcome == "insufficient_evidence" else 1,
        "qualification_useful": True if outcome == "qualified" else None,
        "insufficiency_correct": True if outcome == "insufficient_evidence" else None,
    }
    values.update(overrides)
    responses = FakeResponses(AnswerEvaluationOutput.model_validate(values))
    result = await OpenAIAnswerEvaluator(
        api_key="test", client=SimpleNamespace(responses=responses)
    ).evaluate(request(outcome=outcome))
    assert result.failure_code == failure_code
    assert result.input_tokens == 321
    assert result.output_tokens == 45


def test_request_bound_provider_schema_requires_request_specific_values() -> None:
    qualified_model = output_model_for_request(request(part_count=2))
    schema = qualified_model.model_json_schema()
    judgements = schema["properties"]["part_judgements"]
    assert judgements["minItems"] == 2
    assert judgements["maxItems"] == 2
    assert "factual_precision" in schema["required"]
    assert "completeness" in schema["required"]
    assert "qualification_useful" in schema["required"]
    with pytest.raises(ValueError):
        qualified_model.model_validate(
            {
                "part_judgements": [
                    {"part_index": 1, "grounded": True, "unsupported_categories": []},
                    {"part_index": 2, "grounded": True, "unsupported_categories": []},
                ],
                "factual_precision": None,
                "completeness": 1,
                "qualification_useful": True,
                "insufficiency_correct": None,
            }
        )
    with pytest.raises(ValueError):
        qualified_model.model_validate(
            {
                "part_judgements": [
                    {
                        "part_index": 1,
                        "grounded": True,
                        "unsupported_categories": [],
                    },
                    {
                        "part_index": 2,
                        "grounded": True,
                        "unsupported_categories": [],
                    },
                ],
                "completeness": 1,
                "qualification_useful": True,
                "insufficiency_correct": None,
            }
        )
    with pytest.raises(ValueError):
        qualified_model.model_validate(
            {
                "part_judgements": [
                    {
                        "part_index": 1,
                        "grounded": True,
                        "unsupported_categories": ["INVALID"],
                    },
                    {"part_index": 2, "grounded": True, "unsupported_categories": []},
                ],
                "factual_precision": 1.1,
                "completeness": 1,
                "qualification_useful": True,
                "insufficiency_correct": None,
            }
        )


@pytest.mark.asyncio
async def test_input_and_cost_ceilings_fail_before_provider_call() -> None:
    output = AnswerEvaluationOutput(
        part_judgements=[
            PartJudgement(part_index=1, grounded=True, unsupported_categories=[])
        ],
        factual_precision=1,
        completeness=1,
        qualification_useful=True,
        insufficiency_correct=None,
    )
    input_limited = FakeResponses(output)
    evaluator = OpenAIAnswerEvaluator(
        api_key="test",
        maximum_total_input_tokens=1,
        client=SimpleNamespace(responses=input_limited),
    )
    result = await evaluator.evaluate(request())
    assert result.failure_code == "input_token_ceiling_exceeded"
    assert input_limited.calls == []

    cost_limited = FakeResponses(output)
    evaluator = OpenAIAnswerEvaluator(
        api_key="test",
        maximum_total_cost_usd=0.000001,
        client=SimpleNamespace(responses=cost_limited),
    )
    result = await evaluator.evaluate(request())
    assert result.failure_code == "cost_ceiling_exceeded"
    assert cost_limited.calls == []


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


@pytest.mark.asyncio
async def test_answered_representation_is_valid_without_conditional_booleans() -> None:
    responses = FakeResponses(
        AnswerEvaluationOutput(
            part_judgements=[
                PartJudgement(part_index=1, grounded=False, unsupported_categories=[])
            ],
            factual_precision=0,
            completeness=0,
            qualification_useful=None,
            insufficiency_correct=None,
        )
    )
    evaluator = OpenAIAnswerEvaluator(
        api_key="test", client=SimpleNamespace(responses=responses)
    )
    result = await evaluator.evaluate(request(outcome="answered"))
    assert result.status is ModelAssistedStatus.COMPLETED
    assert result.scores[ModelAssistedMetric.ANSWER_FACTUAL_PRECISION] == 0
    assert result.scores[ModelAssistedMetric.ANSWER_COMPLETENESS] == 0
