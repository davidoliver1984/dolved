from app.evaluation.threshold_policy import ThresholdCalibrationPolicy
from app.evaluation.threshold_replay import (
    PreThresholdCandidate,
    ReplayExpectedEvidence,
    ThresholdReplayDataset,
    ThresholdReplayVariant,
    replay_thresholds,
    threshold_boundaries,
)


def test_compare_may_retain_one_canonical_candidate_on_each_side() -> None:
    variant = ThresholdReplayVariant(
        case_id="case.compare",
        variant_id="direct",
        slices=("COMPARE",),
        expected_outcome="EVIDENCE_FOUND",
        required_sides=("PRIMARY", "COMPARISON"),
        pre_threshold_candidates=(
            PreThresholdCandidate(
                candidate_id="chunk.shared",
                side="PRIMARY",
                reranker_rank=1,
                reranker_score=0.8,
            ),
            PreThresholdCandidate(
                candidate_id="chunk.shared",
                side="COMPARISON",
                reranker_rank=1,
                reranker_score=0.7,
            ),
        ),
    )

    assert len(variant.pre_threshold_candidates) == 2


LOAD_BEARING = (
    "CURRENT",
    "COMPARE",
    "VALID_AT_DATE",
    "historical",
    "temporal-authority",
    "applicability",
    "location-alias",
    "multi-evidence",
    "multi-document",
    "adversarial",
    "zero-evidence",
)


def policy() -> ThresholdCalibrationPolicy:
    return ThresholdCalibrationPolicy(
        policy_id="evidence-threshold-calibration-v1",
        policy_version="1",
        control_threshold=0.337890625,
        absolute_failures=("cross_workspace_evidence",),
        load_bearing_slices=LOAD_BEARING,
        controlled_rejection_outcomes=(
            "NO_ELIGIBLE_EVIDENCE",
            "INSUFFICIENT_EVIDENCE",
            "COMPARISON_SCOPE_INCOMPLETE",
            "CLARIFICATION_REQUIRED",
        ),
        selection_rule="case-first-evidence-recall-constrained-v1",
    )


def dataset() -> ThresholdReplayDataset:
    expected = ReplayExpectedEvidence(evidence_unit_id="unit.primary")
    return ThresholdReplayDataset(
        benchmark_id="dolved-care-engineering",
        corpus_version="v2",
        corpus_digest="a" * 64,
        split="threshold_calibration",
        final_evidence_k=5,
        variants=(
            ThresholdReplayVariant(
                case_id="case.evidence",
                variant_id="direct",
                slices=LOAD_BEARING,
                expected_outcome="EVIDENCE_FOUND",
                expected_evidence=(expected,),
                pre_threshold_candidates=(
                    PreThresholdCandidate(
                        candidate_id="candidate.uncredited",
                        reranker_rank=1,
                        reranker_score=0.4,
                    ),
                    PreThresholdCandidate(
                        candidate_id="candidate.expected",
                        reranker_rank=2,
                        reranker_score=0.6,
                        covered_evidence_unit_ids=("unit.primary",),
                    ),
                ),
            ),
            ThresholdReplayVariant(
                case_id="case.compare",
                variant_id="compare",
                slices=LOAD_BEARING,
                expected_outcome="COMPARISON_SCOPE_INCOMPLETE",
                required_sides=("PRIMARY", "COMPARISON"),
                expected_evidence=(
                    ReplayExpectedEvidence(evidence_unit_id="unit.p"),
                    ReplayExpectedEvidence(
                        evidence_unit_id="unit.c", side="COMPARISON"
                    ),
                ),
                pre_threshold_candidates=(
                    PreThresholdCandidate(
                        candidate_id="candidate.p",
                        reranker_rank=1,
                        reranker_score=0.7,
                        covered_evidence_unit_ids=("unit.p",),
                    ),
                    PreThresholdCandidate(
                        candidate_id="candidate.c",
                        side="COMPARISON",
                        reranker_rank=1,
                        reranker_score=0.2,
                        covered_evidence_unit_ids=("unit.c",),
                    ),
                ),
            ),
            ThresholdReplayVariant(
                case_id="case.no-evidence",
                variant_id="direct",
                slices=LOAD_BEARING,
                expected_outcome="NO_ELIGIBLE_EVIDENCE",
                expected_evidence=(),
                pre_threshold_candidates=(),
                pre_retrieval_outcome="NO_ELIGIBLE_EVIDENCE",
            ),
        ),
    )


def test_grid_contains_scores_exact_control_and_above_maximum() -> None:
    boundaries = threshold_boundaries(dataset(), policy().control_threshold)

    assert {0.2, 0.4, 0.6, 0.7, 0.337890625}.issubset(boundaries)
    assert boundaries[-1] > 0.7


def test_replay_ignores_historical_threshold_flags_and_recomputes_final_k() -> None:
    result = replay_thresholds(dataset(), policy())
    control = next(
        item
        for item in result.boundaries
        if item.metrics.threshold == policy().control_threshold
    )

    assert control.expected_units_accepted == 2
    assert control.expected_units_rejected == 1
    assert control.metrics.uncredited_unannotated_accepted_candidates == 1
    assert control.metrics.controlled_rejection_correctness == 1.0


def test_compare_sides_are_replayed_independently() -> None:
    result = replay_thresholds(dataset(), policy())
    selected = next(item for item in result.boundaries if item.metrics.threshold == 0.4)

    # PRIMARY qualifies and COMPARISON does not, so the declared controlled
    # comparison-incomplete result remains exactly correct.
    assert selected.metrics.controlled_rejection_correctness == 1.0


def test_replay_is_deterministic_and_provider_free() -> None:
    first = replay_thresholds(dataset(), policy()).model_dump_json()
    second = replay_thresholds(dataset(), policy()).model_dump_json()

    assert first == second
