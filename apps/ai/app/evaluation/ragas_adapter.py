"""The only module permitted to expose Ragas-specific behaviour."""

from __future__ import annotations

import time
from typing import Any

from app.evaluation.models import (
    ModelAssistedEvaluationRequest,
    ModelAssistedEvaluationResult,
    ModelAssistedMetric,
    ModelAssistedMetricObservation,
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
        scorers: dict[ModelAssistedMetric, Any] | None = None,
    ) -> None:
        if scorers is None and scorer is not None:
            scorers = {ModelAssistedMetric.CONTEXT_RELEVANCE: scorer}
        elif scorers is None:
            from ragas.metrics.collections import (
                ContextRelevance,
                FactualCorrectness,
                Faithfulness,
            )

            scorers = {
                ModelAssistedMetric.CONTEXT_RELEVANCE: ContextRelevance(
                    llm=evaluator_model
                ),
                ModelAssistedMetric.ANSWER_PART_GROUNDEDNESS: Faithfulness(
                    llm=evaluator_model
                ),
                ModelAssistedMetric.ANSWER_FACTUAL_PRECISION: FactualCorrectness(
                    llm=evaluator_model, mode="precision"
                ),
                ModelAssistedMetric.ANSWER_COMPLETENESS: FactualCorrectness(
                    llm=evaluator_model, mode="recall"
                ),
            }
        elif scorer is not None:
            raise ValueError("pass scorer or scorers, not both")
        self._scorers = scorers
        self._identity = {
            "implementation": "ragas",
            "ragas_version": ragas_version,
            "provider": provider,
            "model": model,
        }

    async def evaluate(
        self, request: ModelAssistedEvaluationRequest
    ) -> ModelAssistedEvaluationResult:
        scores: dict[ModelAssistedMetric, float] = {}
        observations: list[ModelAssistedMetricObservation] = []
        for metric in request.metrics:
            scorer = self._scorers.get(metric)
            if scorer is None:
                observations.append(
                    ModelAssistedMetricObservation(
                        metric=metric,
                        status=ModelAssistedStatus.FAILED,
                        failure_code="unsupported_metric",
                    )
                )
                continue
            started = time.monotonic()
            try:
                kwargs: dict[str, Any]
                if metric is ModelAssistedMetric.CONTEXT_RELEVANCE:
                    kwargs = {
                        "user_input": request.question,
                        "retrieved_contexts": [
                            item.text for item in request.retrieved_evidence
                        ],
                    }
                elif metric is ModelAssistedMetric.ANSWER_PART_GROUNDEDNESS:
                    kwargs = {
                        "user_input": request.question,
                        "response": request.generated_answer,
                        "retrieved_contexts": [
                            item.text for item in request.retrieved_evidence
                        ],
                    }
                else:
                    kwargs = {
                        "response": request.generated_answer,
                        "reference": request.reference_answer,
                    }
                result = await scorer.ascore(**kwargs)
                scores[metric] = float(result.value)
                observations.append(
                    ModelAssistedMetricObservation(
                        metric=metric,
                        status=ModelAssistedStatus.COMPLETED,
                        latency_ms=(time.monotonic() - started) * 1000,
                    )
                )
            except Exception:  # noqa: BLE001 -- typed privacy-safe translation
                observations.append(
                    ModelAssistedMetricObservation(
                        metric=metric,
                        status=ModelAssistedStatus.FAILED,
                        latency_ms=(time.monotonic() - started) * 1000,
                        failure_code="framework_or_provider_failure",
                    )
                )
        failed = [
            item for item in observations if item.status is ModelAssistedStatus.FAILED
        ]
        return ModelAssistedEvaluationResult(
            case_id=request.case_id,
            variant_id=request.variant_id,
            status=ModelAssistedStatus.FAILED
            if failed
            else ModelAssistedStatus.COMPLETED,
            scores=scores,
            evaluator_identity=self._identity,
            failure_code="metric_evaluation_failed" if failed else None,
            metric_observations=tuple(observations),
        )
