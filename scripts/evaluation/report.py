"""Generate deterministic local reports from saved evaluation run artefacts."""

from __future__ import annotations

import argparse
from pathlib import Path

from app.evaluation.run_reporting import (
    generate_run_report,
    update_experiment_index,
    write_comparison,
)


def generate(args: argparse.Namespace) -> None:
    if args.baseline_result is not None:
        write_comparison(args.run_dir, args.baseline_result)
    generate_run_report(args.run_dir)
    update_experiment_index(args.runs_root, args.index)


def index(args: argparse.Namespace) -> None:
    update_experiment_index(args.runs_root, args.index)


def parser() -> argparse.ArgumentParser:
    root = argparse.ArgumentParser()
    commands = root.add_subparsers(required=True)

    generate_parser = commands.add_parser("generate")
    generate_parser.add_argument("--run-dir", type=Path, required=True)
    generate_parser.add_argument("--runs-root", type=Path, required=True)
    generate_parser.add_argument("--index", type=Path, required=True)
    generate_parser.add_argument("--baseline-result", type=Path)
    generate_parser.set_defaults(handler=generate)

    index_parser = commands.add_parser("index")
    index_parser.add_argument("--runs-root", type=Path, required=True)
    index_parser.add_argument("--index", type=Path, required=True)
    index_parser.set_defaults(handler=index)
    return root


if __name__ == "__main__":
    arguments = parser().parse_args()
    arguments.handler(arguments)
