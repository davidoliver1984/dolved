import json
from pathlib import Path

from app.evaluation.hybrid_calibration import (
    HybridCalibrationDataset,
    calibrate_hybrid_threshold,
)
from app.evaluation.live_hybrid_calibration import LiveCalibrationInput


def fixture() -> HybridCalibrationDataset:
    path = Path("/evaluation/hybrid/v1/offline-calibration.json")
    return HybridCalibrationDataset.model_validate_json(path.read_text())


def test_offline_hybrid_calibration_selects_only_from_calibration_split() -> None:
    dataset = fixture()

    result = calibrate_hybrid_threshold(dataset)
    altered = dataset.model_copy(
        update={
            "observations": tuple(
                item.model_copy(update={"reranker_score": 0.0})
                if item.split == "held_out"
                else item
                for item in dataset.observations
            )
        }
    )

    assert result.evidence_threshold == 0.81
    assert calibrate_hybrid_threshold(altered).evidence_threshold == 0.81
    assert result.calibration.f1 == 1.0
    assert result.held_out.f1 == 1.0


def test_hybrid_calibration_lineage_is_explicitly_offline_not_live_voyage() -> None:
    dataset = fixture()

    assert dataset.reranker == {
        "provider": "deterministic-fake",
        "model": "deterministic-reranker",
        "adapter_version": "1",
        "purpose": "harness-verification-not-production-calibration",
    }
    assert dataset.corpus_digest == (
        "0e78f8e57a3d9c358ae08bdf7e97ded151cc4111cf934f48342427a2a187c1af"
    )
    assert json.loads(Path("/evaluation/hybrid/v1/split.json").read_text())[
        "held_out_case_ids"
    ]


def test_live_calibration_input_is_case_isolated_and_source_anchored() -> None:
    source = LiveCalibrationInput.model_validate_json(
        Path("/evaluation/hybrid/v1/live-calibration-input.json").read_text()
    )
    split = json.loads(Path("/evaluation/hybrid/v1/split.json").read_text())

    assert {
        case.case_id for case in source.cases if case.split == "calibration"
    } == set(split["calibration_case_ids"])
    assert {case.case_id for case in source.cases if case.split == "held_out"} == set(
        split["held_out_case_ids"]
    )
    for case in source.cases:
        for candidate in case.candidates:
            source_text = Path(f"/evaluation/{candidate.source_path}").read_text()
            assert candidate.text in source_text


def test_calibration_rejects_one_case_crossing_both_splits() -> None:
    dataset = fixture()
    duplicated = dataset.observations[0].model_copy(
        update={
            "observation_id": "held-out.duplicate-case",
            "split": "held_out",
        }
    )

    try:
        HybridCalibrationDataset.model_validate(
            dataset.model_dump() | {"observations": (*dataset.observations, duplicated)}
        )
    except ValueError as error:
        assert "case cannot cross" in str(error)
    else:
        raise AssertionError("cross-split case should be rejected")
