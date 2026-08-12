"""Version-aware, comparison-only loading for immutable evaluation results."""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from typing import Any, Literal

from pydantic import Field

from app.evaluation.canonical import content_digest
from app.evaluation.models import (
    AggregateResult,
    ExperimentLineage,
    ExperimentResult,
    Identifier,
    ModelAssistedEvaluationResult,
    StrictModel,
    VariantResult,
)


class HistoricalExperimentResultV1(StrictModel):
    """The immutable V1 result shape, validated without rewriting it as V2."""

    schema_version: Literal["v1"] = "v1"
    experiment_id: Identifier
    executed_at: datetime
    candidate_k: int = Field(ge=1)
    lineage: ExperimentLineage
    aggregate: AggregateResult
    slices: dict[str, AggregateResult]
    variants: tuple[VariantResult, ...]
    hard_failures: tuple[Identifier, ...]
    model_assisted: tuple[ModelAssistedEvaluationResult, ...] = ()


@dataclass(frozen=True)
class ComparisonResult:
    schema_version: Literal["v1", "v2"]
    experiment_id: str
    repository_commit: str
    corpus_version: str
    corpus_digest: str
    aggregate: AggregateResult
    slices: dict[str, AggregateResult]
    source_digest: str
    unavailable_fields: dict[str, None] | None = None


def load_comparison_result(raw: dict[str, Any]) -> ComparisonResult:
    """Validate the stored version, then expose only comparison-relevant fields."""

    schema_version = raw.get("schema_version")
    if schema_version == "v1":
        validated_v1 = HistoricalExperimentResultV1.model_validate(raw)
        return _comparison_result(
            validated_v1,
            raw,
            {
                "generation_usage": None,
                "planner_contract_schema_version": None,
                "pricing": None,
                "stage_usage": None,
            },
        )
    if schema_version == "v2":
        return _comparison_result(ExperimentResult.model_validate(raw), raw, None)
    raise ValueError("historical result must declare schema_version v1 or v2")


def _comparison_result(
    validated: HistoricalExperimentResultV1 | ExperimentResult,
    raw: dict[str, Any],
    unavailable_fields: dict[str, None] | None,
) -> ComparisonResult:
    return ComparisonResult(
        schema_version=validated.schema_version,
        experiment_id=validated.experiment_id,
        repository_commit=validated.lineage.repository_commit,
        corpus_version=validated.lineage.corpus_version,
        corpus_digest=validated.lineage.corpus_digest,
        aggregate=validated.aggregate,
        slices=validated.slices,
        source_digest=content_digest(raw),
        unavailable_fields=unavailable_fields,
    )
