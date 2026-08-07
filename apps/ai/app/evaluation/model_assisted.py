"""Provider-neutral model-assisted evaluation boundary."""

from __future__ import annotations

from typing import Protocol

from app.evaluation.models import (
    EvaluationCorpus,
    ModelAssistedEvaluationRequest,
    ModelAssistedEvaluationResult,
    ModelAssistedStatus,
    VariantObservation,
)


class ModelAssistedEvaluator(Protocol):
    async def evaluate(
        self, request: ModelAssistedEvaluationRequest
    ) -> ModelAssistedEvaluationResult: ...


class FakeModelAssistedEvaluator:
    def __init__(self, score: float = 1.0) -> None:
        self._score = score

    async def evaluate(
        self, request: ModelAssistedEvaluationRequest
    ) -> ModelAssistedEvaluationResult:
        return ModelAssistedEvaluationResult(
            status=ModelAssistedStatus.COMPLETED,
            case_id=request.case_id,
            variant_id=request.variant_id,
            scores={metric: self._score for metric in request.metrics},
            evaluator_identity={
                "implementation": "deterministic-fake",
                "version": "v1",
            },
        )


async def evaluate_recorded_contexts(
    *,
    evaluator: ModelAssistedEvaluator,
    corpus: EvaluationCorpus,
    observations: tuple[VariantObservation, ...],
) -> tuple[ModelAssistedEvaluationResult, ...]:
    """Evaluate each recorded query against its complete retrieved context set."""

    questions = {
        (case.case_id, variant.variant_id): variant.question
        for case in corpus.cases
        for variant in case.variants
    }
    results: list[ModelAssistedEvaluationResult] = []
    for observation in observations:
        key = (observation.case_id, observation.variant_id)
        question = questions.get(key)
        if question is None:
            raise ValueError(f"observation has no corpus question: {key}")
        results.append(
            await evaluator.evaluate(
                ModelAssistedEvaluationRequest(
                    case_id=observation.case_id,
                    variant_id=observation.variant_id,
                    question=question,
                    retrieved_evidence=observation.candidates,
                )
            )
        )
    return tuple(results)


def failed_model_assisted_result(
    request: ModelAssistedEvaluationRequest,
    evaluator_identity: dict[str, str],
    failure_code: str,
) -> ModelAssistedEvaluationResult:
    return ModelAssistedEvaluationResult(
        case_id=request.case_id,
        variant_id=request.variant_id,
        status=ModelAssistedStatus.FAILED,
        scores={},
        evaluator_identity=evaluator_identity,
        failure_code=failure_code,
    )
