from typing import Literal

from pydantic import Field, model_validator

from app.evaluation.models import Identifier, StrictModel


class HybridCalibrationObservation(StrictModel):
    observation_id: Identifier
    case_id: Identifier
    variant_id: Identifier
    candidate_id: Identifier
    split: Literal["calibration", "held_out"]
    reranker_score: float = Field(ge=0, le=1)
    relevant: bool


class HybridCalibrationDataset(StrictModel):
    schema_version: Literal["v1"] = "v1"
    corpus_version: str
    corpus_digest: str
    reranker: dict[str, str]
    hybrid_configuration: dict[str, int | str]
    observations: tuple[HybridCalibrationObservation, ...] = Field(min_length=4)

    @model_validator(mode="after")
    def valid_splits(self) -> HybridCalibrationDataset:
        identities = tuple(item.observation_id for item in self.observations)
        if len(set(identities)) != len(identities):
            raise ValueError("calibration observation IDs must be unique")
        case_splits: dict[str, str] = {}
        for item in self.observations:
            existing = case_splits.setdefault(item.case_id, item.split)
            if existing != item.split:
                raise ValueError("a case cannot cross calibration and held-out splits")
        for split in ("calibration", "held_out"):
            items = [item for item in self.observations if item.split == split]
            if not items or {item.relevant for item in items} != {False, True}:
                raise ValueError(f"{split} must contain relevant and irrelevant cases")
        return self


class ClassificationMetrics(StrictModel):
    precision: float = Field(ge=0, le=1)
    recall: float = Field(ge=0, le=1)
    f1: float = Field(ge=0, le=1)
    accepted: int = Field(ge=0)
    abstained: int = Field(ge=0)


class HybridCalibrationResult(StrictModel):
    evidence_threshold: float = Field(ge=0, le=1)
    calibration: ClassificationMetrics
    held_out: ClassificationMetrics


def calibrate_hybrid_threshold(
    dataset: HybridCalibrationDataset,
) -> HybridCalibrationResult:
    calibration = tuple(
        item for item in dataset.observations if item.split == "calibration"
    )
    held_out = tuple(item for item in dataset.observations if item.split == "held_out")
    candidates = sorted({item.reranker_score for item in calibration}, reverse=True)
    selected = max(
        ((_metrics(calibration, threshold), threshold) for threshold in candidates),
        key=lambda item: (
            item[0].f1,
            item[0].precision,
            item[0].recall,
            item[1],
        ),
    )
    return HybridCalibrationResult(
        evidence_threshold=selected[1],
        calibration=selected[0],
        held_out=_metrics(held_out, selected[1]),
    )


def _metrics(
    observations: tuple[HybridCalibrationObservation, ...], threshold: float
) -> ClassificationMetrics:
    accepted = tuple(item for item in observations if item.reranker_score >= threshold)
    true_positive = sum(item.relevant for item in accepted)
    relevant = sum(item.relevant for item in observations)
    precision = true_positive / len(accepted) if accepted else 0.0
    recall = true_positive / relevant if relevant else 0.0
    f1 = (
        2 * precision * recall / (precision + recall) if precision + recall > 0 else 0.0
    )
    return ClassificationMetrics(
        precision=precision,
        recall=recall,
        f1=f1,
        accepted=len(accepted),
        abstained=len(observations) - len(accepted),
    )
