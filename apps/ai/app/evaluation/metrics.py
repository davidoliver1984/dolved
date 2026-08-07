"""Deterministic metrics over distinct EvidenceUnit coverage."""

from __future__ import annotations

import math

from app.evaluation.matching import candidates_cover
from app.evaluation.models import EvidenceUnit, MetricValues, RetrievedCandidate


def evaluate_metrics(
    units: tuple[EvidenceUnit, ...],
    candidates: tuple[RetrievedCandidate, ...],
    k: int,
) -> tuple[MetricValues, tuple[str, ...]]:
    ranked = tuple(sorted(candidates, key=lambda candidate: candidate.rank)[:k])
    covered = tuple(unit for unit in units if candidates_cover(unit, ranked))

    recall = len(covered) / len(units) if units else 1.0
    credited: set[str] = set()
    gains: list[int] = []
    first_relevant_rank: int | None = None

    for rank, candidate in enumerate(ranked, start=1):
        newly_matched = [
            unit
            for unit in units
            if unit.evidence_id not in credited
            and candidates_cover(unit, ranked[:rank])
        ]
        if newly_matched and first_relevant_rank is None:
            first_relevant_rank = rank
        credited.update(unit.evidence_id for unit in newly_matched)
        gains.append(max((unit.relevance_grade for unit in newly_matched), default=0))

    precision = len(credited) / k
    mrr = 1 / first_relevant_rank if first_relevant_rank is not None else 0.0
    dcg = _dcg(gains)
    ideal_gains = sorted((unit.relevance_grade for unit in units), reverse=True)[:k]
    idcg = _dcg(ideal_gains)
    ndcg = dcg / idcg if idcg else 1.0

    return (
        MetricValues(
            recall_at_k=recall,
            precision_at_k=precision,
            mrr=mrr,
            ndcg_at_k=ndcg,
        ),
        tuple(unit.evidence_id for unit in covered),
    )


def evaluate_metrics_by_side(
    units: tuple[EvidenceUnit, ...],
    candidates: tuple[RetrievedCandidate, ...],
    k: int,
) -> tuple[MetricValues, dict[str, MetricValues], tuple[str, ...]]:
    """Evaluate COMPARE sides independently before producing one case value."""

    sides = sorted({unit.side for unit in units} | {item.side for item in candidates})
    if not sides:
        metrics, covered = evaluate_metrics(units, candidates, k)
        return metrics, {}, covered

    side_metrics: dict[str, MetricValues] = {}
    covered_ids: list[str] = []
    for side in sides:
        metrics, covered = evaluate_metrics(
            tuple(unit for unit in units if unit.side == side),
            tuple(item for item in candidates if item.side == side),
            k,
        )
        side_metrics[side] = metrics
        covered_ids.extend(covered)

    count = len(side_metrics)
    aggregate = MetricValues(
        recall_at_k=sum(item.recall_at_k for item in side_metrics.values()) / count,
        precision_at_k=(
            sum(item.precision_at_k for item in side_metrics.values()) / count
        ),
        mrr=sum(item.mrr for item in side_metrics.values()) / count,
        ndcg_at_k=sum(item.ndcg_at_k for item in side_metrics.values()) / count,
    )
    return aggregate, side_metrics, tuple(covered_ids)


def _dcg(gains: list[int]) -> float:
    return sum(
        (2**gain - 1) / math.log2(rank + 1) for rank, gain in enumerate(gains, 1)
    )
