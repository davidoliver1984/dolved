"""Dispatch deterministic benchmark compilation by contract version."""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

AI_ROOT = Path(__file__).resolve().parents[2] / "apps/ai"
sys.path.insert(0, str(AI_ROOT))

from app.evaluation.benchmark import (  # noqa: E402
    compile_v2_benchmark,
    compile_v3_benchmark,
)


def parser() -> argparse.ArgumentParser:
    root = argparse.ArgumentParser()
    root.add_argument("--benchmark-root", type=Path, required=True)
    root.add_argument("--contract-root", type=Path, required=True)
    root.add_argument("--contract-version", choices=("v2", "v3"))
    return root


def inferred_contract_version(contract_root: Path) -> str:
    version = contract_root.name
    if version not in {"v2", "v3"}:
        raise ValueError(
            "contract version must be explicit when contract-root is not v2 or v3"
        )
    return version


if __name__ == "__main__":
    arguments = parser().parse_args()
    version = arguments.contract_version or inferred_contract_version(
        arguments.contract_root
    )
    compiler = compile_v2_benchmark if version == "v2" else compile_v3_benchmark
    compiler(arguments.benchmark_root, arguments.contract_root)
