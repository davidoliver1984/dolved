#!/usr/bin/env python3
"""Compile live calibration observations into provider-free replay input."""

from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Any, Literal

from app.evaluation.application_benchmark import (
    _candidate_records,
    _document_mapping,
    _side,
)
from app.evaluation.calibration_compatibility import classify_pre_threshold_failure
from app.evaluation.models import EvidenceUnit
from app.evaluation.threshold_replay import (
    PreThresholdCandidate,
    ReplayExpectedEvidence,
    ThresholdReplayDataset,
    ThresholdReplayVariant,
)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--observations", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    arguments = parser.parse_args()
    raw = json.loads(arguments.observations.read_text())
    if raw.get("run_id") != "CAL-EXP-0001-evidence-threshold-calibration":
        raise ValueError("unexpected calibration run identity")
    observations = raw.get("observations")
    if not isinstance(observations, list) or len(observations) != 84:
        raise ValueError("calibration requires exactly 84 durable observations")
    if len({item["case"]["case_id"] for item in observations}) != 28:
        raise ValueError("calibration requires exactly 28 semantic cases")
    documents = _document_mapping(raw)
    variants = tuple(_variant(item, documents) for item in observations)
    dataset = ThresholdReplayDataset(
        benchmark_id="dolved-care-engineering",
        corpus_version="v2",
        corpus_digest=str(raw["benchmark"]["digest"]),
        split="threshold_calibration",
        final_evidence_k=5,
        variants=variants,
    )
    arguments.output.parent.mkdir(parents=True, exist_ok=True)
    arguments.output.write_text(dataset.model_dump_json(indent=2) + "\n")
    return 0


def _variant(
    observation: dict[str, Any], documents: dict[str, tuple[str, str]]
) -> ThresholdReplayVariant:
    case = observation["case"]
    variant = observation["variant"]
    units = tuple(
        _unit(item) for item in case["retrieval_expectation"]["evidence_units"]
    )
    hybrid = observation.get("hybrid")
    failures: tuple[str, ...] = ()
    candidates: tuple[PreThresholdCandidate, ...] = ()
    pre_retrieval_outcome: str | None = None
    if isinstance(hybrid, dict):
        result = hybrid["result"]
        _retrieved, lineage, _funnel = _candidate_records(
            trace=hybrid["trace"],
            result=result,
            units=units,
            documents=documents,
            dense_only=False,
        )
        candidates = tuple(
            PreThresholdCandidate(
                candidate_id=item.candidate_id,
                side=item.side,
                reranker_rank=item.reranker_rank,
                reranker_score=item.reranker_score,
                covered_evidence_unit_ids=item.covered_evidence_unit_ids,
            )
            for item in lineage
            if item.reranker_rank is not None and item.reranker_score is not None
        )
        if not candidates:
            pre_retrieval_outcome = _outcome(str(result["outcome"]))
    else:
        failure = classify_pre_threshold_failure(observation)
        failures = (failure,) if failure is not None else ()

    required_sides: tuple[Literal["PRIMARY", "COMPARISON"], ...] = (
        ("PRIMARY", "COMPARISON")
        if case["planner_expectation"]["temporal_mode"] == "COMPARE"
        else ("PRIMARY",)
    )
    return ThresholdReplayVariant(
        case_id=case["case_id"],
        variant_id=variant["variant_id"],
        slices=tuple(case["slices"]),
        expected_outcome=case["outcome_expectation"]["outcome"],
        required_sides=required_sides,
        expected_evidence=tuple(
            ReplayExpectedEvidence(
                evidence_unit_id=item.evidence_id,
                side=item.side,
                relevance_grade=item.relevance_grade,
            )
            for item in units
        ),
        pre_threshold_candidates=candidates,
        pre_retrieval_outcome=pre_retrieval_outcome,
        absolute_failures=failures,
    )


def _unit(raw: dict[str, Any]) -> EvidenceUnit:
    return EvidenceUnit(
        evidence_id=raw["evidence_id"],
        document_family_id=raw["document_family_id"],
        document_version_id=raw["document_version_id"],
        side=_side(raw["side"]),
        source_path=raw["source_path"],
        canonical_excerpts=tuple(raw["canonical_excerpts"]),
        relevance_grade=raw["relevance_grade"],
        minimum_token_coverage=raw["minimum_token_coverage"],
        notes=raw.get("notes"),
    )


def _outcome(value: str) -> str:
    return {
        "no_eligible_evidence": "NO_ELIGIBLE_EVIDENCE",
        "no_retrieval_candidates": "NO_RETRIEVAL_CANDIDATES",
        "clarification_required": "CLARIFICATION_REQUIRED",
        "comparison_scope_incomplete": "COMPARISON_SCOPE_INCOMPLETE",
        "temporal_scope_unresolved": "TEMPORAL_SCOPE_UNRESOLVED",
    }.get(value, value.upper())


if __name__ == "__main__":
    raise SystemExit(main())
