import shutil
from pathlib import Path

import pytest

from app.evaluation.generation_verification import verify_generation_evidence

GENERATION = Path("/generation-evaluation")
RUNS = Path("/evaluation-runs")


def test_committed_generation_evidence_is_provider_free_and_reproducible() -> None:
    result = verify_generation_evidence(
        generation_root=GENERATION,
        runs_root=RUNS,
    )

    assert result.run_count == 2
    assert result.case_count == 26
    assert result.artifact_count == 18


def test_generation_verification_fails_on_historical_observation_change(
    tmp_path: Path,
) -> None:
    generation = tmp_path / "generation"
    runs = tmp_path / "runs"
    shutil.copytree(GENERATION, generation)
    for source in sorted(RUNS.glob("GEN-EXP-*")):
        shutil.copytree(source, runs / source.name)
    observation = next(runs.glob("GEN-EXP-*/application-observations.json"))
    observation.write_bytes(observation.read_bytes() + b"\n")

    with pytest.raises(ValueError, match="checksum mismatch"):
        verify_generation_evidence(generation_root=generation, runs_root=runs)


def test_generation_verification_fails_on_missing_declared_checkpoint(
    tmp_path: Path,
) -> None:
    generation = tmp_path / "generation"
    runs = tmp_path / "runs"
    shutil.copytree(GENERATION, generation)
    source = next(iter(sorted(RUNS.glob("GEN-EXP-*"))))
    target = runs / source.name
    shutil.copytree(source, target)
    checkpoint = next(target.glob("checkpoint-*.json"))
    checkpoint.unlink()

    with pytest.raises(ValueError, match="declared generation artifact is missing"):
        verify_generation_evidence(generation_root=generation, runs_root=runs)
