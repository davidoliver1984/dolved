"""Deterministic checksums for immutable evaluation run artefacts."""

from __future__ import annotations

import hashlib
from pathlib import Path

ARTEFACTS = (
    "application-observations.json",
    "comparison.json",
    "config.json",
    "notes.md",
    "provisioning-mapping.json",
    "report.html",
    "report.md",
    "result.json",
    "run-manifest.json",
)


def write_checksums(run_directory: Path) -> Path:
    lines: list[str] = []
    for name in ARTEFACTS:
        path = run_directory / name
        if path.is_file():
            lines.append(f"{hashlib.sha256(path.read_bytes()).hexdigest()}  {name}")
    if not lines:
        raise ValueError(f"No evaluation artefacts found in {run_directory}")
    output = run_directory / "checksums.sha256"
    output.write_text("\n".join(lines) + "\n")
    return output
