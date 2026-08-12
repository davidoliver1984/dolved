import pytest
from pydantic import ValidationError

from app.evaluation.models import CostBasis, StageUsageObservation
from app.evaluation.usage_reporting import aggregate_usage


def test_usage_aggregation_is_deterministic_and_preserves_pricing_lineage() -> None:
    embedding = StageUsageObservation(
        stage="dense_embedding",
        provider="voyage",
        model="voyage-4-large",
        execution="PROVIDER_API",
        request_count=1,
        retry_count=1,
        input_tokens=200,
        latency_ms=25,
        cost_basis=CostBasis.ESTIMATED,
        cost_usd=0.000024,
        pricing_snapshot="voyage-pricing-2026-08-12",
    )
    sparse = StageUsageObservation(
        stage="sparse_encoding",
        provider="fastembed",
        model="prithivida/Splade_PP_en_v1",
        execution="LOCAL",
        request_count=1,
        retry_count=0,
        latency_ms=5,
        cost_basis=CostBasis.ZERO_COST_LOCAL,
        cost_usd=0,
    )

    first = aggregate_usage(
        ((True, True, sparse), (True, True, embedding)),
        attempted_variants=1,
        successfully_planned_variants=1,
        evidence_producing_variants=1,
    )
    second = aggregate_usage(
        ((True, True, embedding), (True, True, sparse)),
        attempted_variants=1,
        successfully_planned_variants=1,
        evidence_producing_variants=1,
    )

    assert first == second
    assert first["total_provider_api_cost_usd"] == 0.000024
    assert first["providers"][1]["pricing_snapshots"] == ["voyage-pricing-2026-08-12"]
    assert first["generation"]["execution"] == "NOT_EXECUTED"
    assert first["stages"][0]["latency_ms"]["p50"] == 25
    assert first["stages"][0]["latency_ms"]["p95"] == 25


def test_stage_usage_accepts_repository_qualified_model_identity() -> None:
    usage = StageUsageObservation(
        stage="sparse_encoding",
        provider="fastembed",
        model="prithivida/Splade_PP_en_v1",
        execution="LOCAL",
        request_count=1,
        cost_basis=CostBasis.ZERO_COST_LOCAL,
        cost_usd=0,
    )

    assert usage.model == "prithivida/Splade_PP_en_v1"


def test_stage_usage_rejects_unsafe_model_identity_characters() -> None:
    with pytest.raises(ValidationError, match="String should match pattern"):
        StageUsageObservation(
            stage="sparse_encoding",
            provider="fastembed",
            model="prithivida/Splade PP en v1",
            execution="LOCAL",
            request_count=1,
            cost_basis=CostBasis.ZERO_COST_LOCAL,
            cost_usd=0,
        )


def test_unavailable_pricing_is_never_reported_as_zero() -> None:
    planner = StageUsageObservation(
        stage="planner",
        provider="openai",
        model="gpt-5-mini",
        execution="PROVIDER_API",
        request_count=1,
        input_tokens=100,
        output_tokens=20,
        latency_ms=10,
        cost_basis=CostBasis.UNAVAILABLE,
    )
    result = aggregate_usage(
        ((True, False, planner),),
        attempted_variants=1,
        successfully_planned_variants=1,
        evidence_producing_variants=0,
    )

    assert result["known_provider_api_cost_usd"] == 0
    assert result["total_provider_api_cost_usd"] is None
    assert result["mean_api_cost_per_attempted_variant_usd"] is None
    assert result["unavailable_cost_lineage"] == ["openai/gpt-5-mini"]


def test_cost_semantics_reject_estimates_without_a_pricing_snapshot() -> None:
    with pytest.raises(ValidationError, match="pricing snapshot"):
        StageUsageObservation(
            stage="dense_embedding",
            provider="voyage",
            model="voyage-4-large",
            execution="PROVIDER_API",
            request_count=1,
            cost_basis=CostBasis.ESTIMATED,
            cost_usd=0.1,
        )
