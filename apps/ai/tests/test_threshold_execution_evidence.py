from __future__ import annotations

from app.evaluation.calibration_compatibility import ThresholdExecutionEvidence
from app.evaluation.threshold_replay import (
    PreThresholdCandidate,
    ThresholdReplayDataset,
    ThresholdReplayVariant,
)


def test_v3_replay_dataset_and_execution_evidence_preserve_versioned_lineage() -> None:
    dataset = ThresholdReplayDataset(
        benchmark_id="dolved-care-engineering",
        corpus_version="3",
        corpus_digest="a" * 64,
        split="threshold_calibration",
        final_evidence_k=5,
        variants=(
            ThresholdReplayVariant(
                case_id="case.one",
                variant_id="direct",
                slices=("CURRENT",),
                expected_outcome="EVIDENCE_FOUND",
                pre_threshold_candidates=(
                    PreThresholdCandidate(
                        candidate_id="candidate.one",
                        reranker_rank=1,
                        reranker_score=0.5,
                    ),
                ),
            ),
        ),
    )

    assert dataset.corpus_version == "3"
    evidence = ThresholdExecutionEvidence.model_validate(
        {
            "cases": [
                {
                    "case_id": "case.one",
                    "complete_pre_threshold_lineage": True,
                    "reranker_scores": [0.5],
                }
            ]
        }
    )
    assert evidence.cases[0].reranker_scores == (0.5,)
