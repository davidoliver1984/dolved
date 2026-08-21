import json
from datetime import UTC, datetime
from pathlib import Path
from typing import Any

from app.evaluation.rrf_sensitivity_experiment import run_rrf_sensitivity_experiment

SOURCE = Path(
    "/evaluation-runs/EXP-0003-post-reliability-corrected-engineering-baseline"
)


def test_replay_is_provider_free_deterministic_and_reproduces_control(
    tmp_path: Path,
) -> None:
    def run() -> dict[str, Any]:
        return run_rrf_sensitivity_experiment(
            source_result_path=SOURCE / "result.json",
            source_observations_path=SOURCE / "application-observations.json",
            output_directory=tmp_path,
            repository_commit="a" * 40,
            generated_at=datetime(2026, 8, 13, 12, tzinfo=UTC),
        )

    first = run()
    first_bytes = (tmp_path / "result.json").read_bytes()
    second = run()

    assert first == second
    assert first_bytes == (tmp_path / "result.json").read_bytes()
    assert first["cohort"]["variant_count"] == 109
    assert first["cohort"]["expected_evidence_unit_count"] == 138
    control = next(item for item in first["sensitivity"] if item["rrf_k"] == 60)
    assert control["retained_count"] == 134
    assert control["recovered_count"] == 0
    assert control["regressed_count"] == 0
    assert len(first["known_fusion_losses"]) == 4
    assert first["source"]["result_sha256"] == (
        "7fb672abc2a7c104861d96e74c28d6705321e5eae77d57fc399cfbc9390140af"
    )
    assert first["source"]["observations_sha256"] == (
        "e1a2f72fc86d3baa47ec9efbce632209f09d1b35fa4586a4c1dc5f741c5878bd"
    )
    assert json.loads((tmp_path / "checksums.json").read_text())
