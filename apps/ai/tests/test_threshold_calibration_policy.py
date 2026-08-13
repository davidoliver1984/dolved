import json
from pathlib import Path

import jsonschema

from app.evaluation.threshold_policy import (
    EvidenceCoverage,
    SliceRecall,
    ThresholdCalibrationPolicy,
    ThresholdCandidateMetrics,
    case_first_expected_evidence_unit_recall,
    select_evidence_threshold,
)

POLICY_PATH = Path("/evaluation/policies/evidence-threshold-calibration/v1/policy.json")
SCHEMA_PATH = Path(
    "/contracts/evaluation/v2/evidence-threshold-calibration-policy.schema.json"
)


def policy() -> ThresholdCalibrationPolicy:
    return ThresholdCalibrationPolicy.model_validate_json(POLICY_PATH.read_text())


def candidate(
    threshold: float,
    *,
    recall: float,
    precision: float = 0.5,
    rejection: float = 1.0,
    failures: tuple[str, ...] = (),
    lost: int = 0,
    mrr: float = 0.5,
    ndcg: float = 0.5,
    slice_recall: float = 0.5,
) -> ThresholdCandidateMetrics:
    return ThresholdCandidateMetrics(
        threshold=threshold,
        absolute_failures=failures,
        controlled_rejection_correctness=rejection,
        benchmark_precision=precision,
        case_first_expected_evidence_unit_recall=recall,
        variants_losing_all_evidence=lost,
        mrr=mrr,
        ndcg=ndcg,
        load_bearing_slice_recall={
            name: SliceRecall(case_count=1, recall=slice_recall)
            for name in policy().load_bearing_slices
        },
        uncredited_unannotated_accepted_candidates=0,
    )


def test_repository_policy_matches_schema_and_predeclared_slices() -> None:
    payload = json.loads(POLICY_PATH.read_text())
    jsonschema.validate(payload, json.loads(SCHEMA_PATH.read_text()))

    assert payload["control_threshold"] == 0.337890625
    assert set(payload["load_bearing_slices"]) == {
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
    }


def test_case_first_recall_weights_sides_variants_and_cases_in_order() -> None:
    coverage = (
        EvidenceCoverage(
            case_id="compare",
            variant_id="one",
            side="PRIMARY",
            expected_evidence_unit_ids=("p1", "p2"),
            covered_evidence_unit_ids=("p1", "p1"),
        ),
        EvidenceCoverage(
            case_id="compare",
            variant_id="one",
            side="COMPARISON",
            expected_evidence_unit_ids=("c1",),
            covered_evidence_unit_ids=("c1",),
        ),
        EvidenceCoverage(
            case_id="compare",
            variant_id="two",
            expected_evidence_unit_ids=("p1",),
            covered_evidence_unit_ids=(),
        ),
        EvidenceCoverage(
            case_id="simple",
            variant_id="one",
            expected_evidence_unit_ids=("s1",),
            covered_evidence_unit_ids=("s1",),
        ),
        EvidenceCoverage(
            case_id="controlled-rejection",
            variant_id="one",
            expected_evidence_unit_ids=(),
            covered_evidence_unit_ids=(),
        ),
    )

    # compare variant one=(1/2 + 1)/2=.75; variant two=0; case=.375;
    # simple case=1; zero-evidence case excluded; corpus=(.375+1)/2=.6875.
    assert case_first_expected_evidence_unit_recall(coverage) == 0.6875


def test_selection_rejects_absolute_or_control_regressions() -> None:
    control = candidate(0.337890625, recall=0.5)
    absolute = candidate(0.4, recall=0.9, failures=("cross_workspace_evidence",))
    precision_regression = candidate(0.5, recall=0.9, precision=0.49)
    rejection_regression = candidate(0.6, recall=0.9, rejection=0.99)
    slice_regression = candidate(0.7, recall=0.9, slice_recall=0.49)

    assert (
        select_evidence_threshold(
            policy(),
            (
                control,
                absolute,
                precision_regression,
                rejection_regression,
                slice_regression,
            ),
        )
        == control
    )


def test_selection_fails_closed_when_control_has_an_absolute_failure() -> None:
    control = candidate(
        0.337890625,
        recall=0.5,
        failures=("temporally_ineligible_evidence",),
    )

    try:
        select_evidence_threshold(policy(), (control,))
    except ValueError as error:
        assert "control has an absolute" in str(error)
    else:
        raise AssertionError("an invalid control cannot be retained")


def test_selection_uses_declared_tie_break_order_and_not_f1() -> None:
    control = candidate(0.337890625, recall=0.5)
    lower = candidate(0.4, recall=0.6, precision=0.6, lost=1)
    selected = candidate(0.5, recall=0.6, precision=0.6, lost=0, mrr=0.6)
    higher_threshold_but_lower_mrr = candidate(
        0.9, recall=0.6, precision=0.6, lost=0, mrr=0.5, ndcg=1.0
    )

    assert (
        select_evidence_threshold(
            policy(), (control, lower, selected, higher_threshold_but_lower_mrr)
        )
        == selected
    )


def test_selection_retains_control_without_strict_recall_improvement() -> None:
    control = candidate(0.337890625, recall=0.5)
    same_recall = candidate(0.8, recall=0.5, precision=1.0)

    assert select_evidence_threshold(policy(), (control, same_recall)) == control


def test_uncredited_candidates_are_diagnostic_not_absolute_failures() -> None:
    observed = candidate(0.4, recall=0.6).model_copy(
        update={"uncredited_unannotated_accepted_candidates": 7}
    )

    assert observed.uncredited_unannotated_accepted_candidates == 7
    assert observed.absolute_failures == ()
