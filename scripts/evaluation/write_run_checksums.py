"""Write deterministic checksums for immutable evaluation run artefacts."""

from __future__ import annotations

import argparse
from pathlib import Path

from app.evaluation.run_checksums import write_checksums


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--run-directory", type=Path, required=True)
    arguments = parser.parse_args()
    write_checksums(arguments.run_directory)


if __name__ == "__main__":
    main()
