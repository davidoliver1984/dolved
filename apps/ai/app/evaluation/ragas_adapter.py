"""The only module permitted to expose Ragas-specific behaviour."""

from __future__ import annotations

from typing import Any

from app.evaluation.model_assisted import failed_model_assisted_result
from app.evaluation.models import (
    ModelAssistedEvaluationRequest,
    ModelAssistedEvaluationResult,
    ModelAssistedMetric,
    ModelAssistedStatus,
)


class RagasEvaluator:
    def __init__(
        self,
        evaluator_model: Any,
        *,
        provider: str,
        model: str,
        ragas_version: str,
        scorer: Any | None = None,
    ) -> None:
        if scorer is None:
            from ragas.metrics.collections import ContextRelevance

            scorer = ContextRelevance(llm=evaluator_model)
        self._scorer = scorer
        self._identity = {
            "implementation": "ragas",
            "ragas_version": ragas_version,
            "provider": provider,
            "model": model,
        }

    async def evaluate(
        self, request: ModelAssistedEvaluationRequest
    ) -> ModelAssistedEvaluationResult:
        if request.metrics != (ModelAssistedMetric.CONTEXT_RELEVANCE,):
            return failed_model_assisted_result(
                request, self._identity, "unsupported_metric"
            )
        try:
            result = await self._scorer.ascore(
                user_input=request.question,
                retrieved_contexts=[item.text for item in request.retrieved_evidence],
            )
            score = float(result.value)
        except Exception:  # noqa: BLE001 -- translate every provider/framework failure
            return failed_model_assisted_result(
                request, self._identity, "evaluator_failed"
            )
        return ModelAssistedEvaluationResult(
            case_id=request.case_id,
            variant_id=request.variant_id,
            status=ModelAssistedStatus.COMPLETED,
            scores={ModelAssistedMetric.CONTEXT_RELEVANCE: score},
            evaluator_identity=self._identity,
        )
