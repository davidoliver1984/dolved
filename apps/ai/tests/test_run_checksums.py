from pathlib import Path

import pytest

from app.evaluation.run_checksums import write_checksums


def test_run_checksums_are_deterministic_and_exclude_their_own_output(
    tmp_path: Path,
) -> None:
    (tmp_path / "result.json").write_text('{"result":true}\n')
    (tmp_path / "config.json").write_text('{"config":true}\n')

    output = write_checksums(tmp_path)
    first = output.read_text()
    write_checksums(tmp_path)

    assert output.read_text() == first
    assert "result.json" in first
    assert "config.json" in first
    assert "checksums.sha256" not in first


def test_run_checksums_fail_when_no_supported_artefacts_exist(tmp_path: Path) -> None:
    with pytest.raises(ValueError, match="No evaluation artefacts"):
        write_checksums(tmp_path)
