#!/usr/bin/env python3
"""Fail-closed path guard for Phase 28 evaluation-data access."""

from __future__ import annotations

import argparse
import fnmatch
import json
from pathlib import Path

from r28_authoring_access import (
    VIEW_ARCHIVE,
    classify_r28_authoring_input,
    signed_view_members,
)


def _matches(path: str, pattern: str) -> bool:
    return fnmatch.fnmatchcase(path, pattern) or fnmatch.fnmatchcase(
        path + "/", pattern
    )


def rejected_paths(manifest: dict, raw_paths: list[str]) -> list[str]:
    groups = {item["classification"]: item for item in manifest["classifications"]}
    allowed = (
        groups["allowed_policy_schema"]["paths"]
        + groups["allowed_runner_source_code"]["paths"]
        + groups["allowed_engineering_population"]["paths"]
        + groups["allowed_r28_authoring_neutral_inputs"]["paths"]
    )
    forbidden = (
        groups["forbidden_calibration"]["paths"]
        + groups["forbidden_held_out"]["paths"]
        + groups["forbidden_previous_outputs"]["paths"]
    )
    rejected: list[str] = []
    for raw_path in raw_paths:
        path = raw_path
        if any(_matches(path, pattern) for pattern in forbidden):
            rejected.append(f"FORBIDDEN {path}")
        elif not any(_matches(path, pattern) for pattern in allowed):
            rejected.append(f"UNCERTAIN {path}")
    return rejected


def rejected_r28_authoring_paths(
    manifest: dict, raw_paths: list[str], view_members: set[str]
) -> list[str]:
    return [
        f"R28_AUTHORING_REJECTED {path}"
        for path in raw_paths
        if classify_r28_authoring_input(path, manifest, view_members) is None
    ]


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("paths", nargs="+")
    parser.add_argument(
        "--manifest",
        default="docs/evaluation/r28-s01/access-manifest.json",
    )
    parser.add_argument(
        "--mode", choices=("general", "r28-authoring"), default="general"
    )
    parser.add_argument(
        "--restricted-view",
        default=(
            "tests/evaluation/authoring-views/dolved-care-v4/v1/"
            "question-author-view.tar.gz"
        ),
    )
    args = parser.parse_args()
    manifest = json.loads(Path(args.manifest).read_text(encoding="utf-8"))
    if args.mode == "r28-authoring":
        if args.restricted_view != VIEW_ARCHIVE:
            print(f"R28_AUTHORING_REJECTED_VIEW {args.restricted_view}")
            return 2
        view_data = Path(args.restricted_view).read_bytes()
        view_members = signed_view_members(view_data)
        rejected = rejected_r28_authoring_paths(manifest, args.paths, view_members)
    else:
        rejected = rejected_paths(manifest, args.paths)

    if rejected:
        print("\n".join(rejected))
        return 2
    print("ALLOWED")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
