"""Deterministic provider/local usage aggregation for evaluation runs."""

from __future__ import annotations

from collections import defaultdict
from collections.abc import Iterable
from statistics import fmean
from typing import Any

from app.evaluation.models import CostBasis, StageUsageObservation


def aggregate_usage(
    observations: Iterable[tuple[bool, bool, StageUsageObservation]],
    *,
    attempted_variants: int,
    successfully_planned_variants: int,
    evidence_producing_variants: int,
) -> dict[str, Any]:
    """Aggregate known costs without converting missing pricing into zero."""
    values = tuple(observations)
    by_provider: dict[tuple[str, str], list[StageUsageObservation]] = defaultdict(list)
    for _, _, item in values:
        by_provider[(item.provider or "local", item.model or item.stage)].append(item)

    provider_rows: list[dict[str, Any]] = []
    unavailable: set[str] = set()
    known_cost = 0.0
    for (provider, model), items in sorted(by_provider.items()):
        costs = [item.cost_usd for item in items if item.cost_usd is not None]
        provider_api_items = [
            item for item in items if item.execution == "PROVIDER_API"
        ]
        missing = any(
            item.cost_basis is CostBasis.UNAVAILABLE for item in provider_api_items
        )
        if missing:
            unavailable.add(f"{provider}/{model}")
        known_cost += sum(
            item.cost_usd or 0
            for item in provider_api_items
            if item.cost_usd is not None
        )
        provider_rows.append(
            {
                "provider": provider,
                "model": model,
                "request_count": sum(item.request_count for item in items),
                "retry_count": _optional_sum(item.retry_count for item in items),
                "input_tokens": _optional_sum(item.input_tokens for item in items),
                "cached_input_tokens": _optional_sum(
                    item.cached_input_tokens for item in items
                ),
                "output_tokens": _optional_sum(item.output_tokens for item in items),
                "known_cost_usd": round(sum(costs), 12),
                "cost_complete": not missing,
                "pricing_snapshots": sorted(
                    {item.pricing_snapshot for item in items if item.pricing_snapshot}
                ),
                "latency_ms": _latency(
                    [item.latency_ms for item in items if item.latency_ms is not None]
                ),
            }
        )

    complete = not unavailable
    return {
        "attempted_variants": attempted_variants,
        "successfully_planned_variants": successfully_planned_variants,
        "evidence_producing_variants": evidence_producing_variants,
        "known_provider_api_cost_usd": round(known_cost, 12),
        "total_provider_api_cost_usd": round(known_cost, 12) if complete else None,
        "cost_complete": complete,
        "unavailable_cost_lineage": sorted(unavailable),
        "mean_api_cost_per_attempted_variant_usd": (
            known_cost / attempted_variants if complete and attempted_variants else None
        ),
        "mean_api_cost_per_successfully_planned_variant_usd": (
            known_cost / successfully_planned_variants
            if complete and successfully_planned_variants
            else None
        ),
        "mean_api_cost_per_evidence_producing_variant_usd": (
            known_cost / evidence_producing_variants
            if complete and evidence_producing_variants
            else None
        ),
        "providers": provider_rows,
        "stages": _stages(item for _, _, item in values),
        "generation": {
            "execution": "NOT_EXECUTED",
            "cost_basis": "UNAVAILABLE",
            "cost_usd": None,
        },
    }


def _stages(values: Iterable[StageUsageObservation]) -> list[dict[str, Any]]:
    grouped: dict[str, list[StageUsageObservation]] = defaultdict(list)
    for item in values:
        grouped[item.stage].append(item)
    return [
        {
            "stage": stage,
            "execution_count": len(items),
            "request_count": sum(item.request_count for item in items),
            "retry_count": _optional_sum(item.retry_count for item in items),
            "latency_ms": _latency(
                [item.latency_ms for item in items if item.latency_ms is not None]
            ),
        }
        for stage, items in sorted(grouped.items())
    ]


def _optional_sum(values: Iterable[int | None]) -> int | None:
    present = [value for value in values if value is not None]
    return sum(present) if present else None


def _latency(values: list[float]) -> dict[str, float] | None:
    if not values:
        return None
    ordered = sorted(values)
    return {
        "min": ordered[0],
        "mean": fmean(ordered),
        "p50": _percentile(ordered, 0.50),
        "p95": _percentile(ordered, 0.95),
        "max": ordered[-1],
    }


def _percentile(values: list[float], quantile: float) -> float:
    if len(values) == 1:
        return values[0]
    position = (len(values) - 1) * quantile
    lower = int(position)
    upper = min(lower + 1, len(values) - 1)
    fraction = position - lower
    return values[lower] + ((values[upper] - values[lower]) * fraction)
