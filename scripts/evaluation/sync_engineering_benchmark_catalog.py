"""Synchronise authored Markdown paths into the engineering benchmark catalogue."""

from __future__ import annotations

import argparse
import json
import re
from pathlib import Path
from typing import Any

FAMILY_PATTERN = re.compile(r"^Document family: `(?P<value>[^`]+)`\s*$", re.MULTILINE)
VERSION_PATTERN = re.compile(r"^Version: (?P<value>\S+)\s*$", re.MULTILINE)


def load_json(path: Path) -> Any:
    return json.loads(path.read_text())


def document_identity(path: Path) -> tuple[str, str]:
    source = path.read_text()
    family = FAMILY_PATTERN.search(source)
    version = VERSION_PATTERN.search(source)
    if family is None or version is None:
        raise ValueError(f"document header has no family/version identity: {path}")
    return family.group("value"), version.group("value")


def synchronise(benchmark_root: Path) -> None:
    catalog_path = benchmark_root / "document-catalog.json"
    manifest_path = benchmark_root / "manifest.json"
    split_path = benchmark_root / "splits/v1.json"
    catalog = load_json(catalog_path)
    manifest = load_json(manifest_path)
    split = load_json(split_path)

    source_by_identity: dict[tuple[str, str], str] = {}
    for path in sorted((benchmark_root / "documents").rglob("*.md")):
        identity = document_identity(path)
        if identity in source_by_identity:
            raise ValueError(f"duplicate document family/version header: {identity}")
        source_by_identity[identity] = path.relative_to(benchmark_root).as_posix()

    catalog_identities: set[tuple[str, str]] = set()
    authored_families: set[str] = set()
    authored_versions = 0
    for family in catalog["families"]:
        family_id = family["family_id"]
        for version in family["versions"]:
            identity = (family_id, version["version_number"])
            if identity in catalog_identities:
                raise ValueError(f"duplicate catalogue family/version: {identity}")
            catalog_identities.add(identity)
            version["source_path"] = source_by_identity.get(identity)
            if version["source_path"] is not None:
                authored_families.add(family_id)
                authored_versions += 1

    unknown_sources = set(source_by_identity).difference(catalog_identities)
    if unknown_sources:
        raise ValueError(f"documents have no catalogue version: {sorted(unknown_sources)}")

    cases = [
        case
        for path in sorted((benchmark_root / "cases").glob("*.json"))
        for case in load_json(path)
    ]
    case_ids = [case["case_id"] for case in cases]
    semantic_cases = len(case_ids)
    manifest["authored_counts"] = {
        "document_families": len(authored_families),
        "document_versions": authored_versions,
        "semantic_cases": semantic_cases,
    }
    if authored_versions > manifest["pilot_counts"]["document_versions"]:
        manifest["status"] = "EXPANDING"

    assigned = {
        case_id
        for split_name, values in split["assignments"].items()
        if split_name != "unassigned"
        for case_id in values
    }
    unknown_assigned = assigned.difference(case_ids)
    if unknown_assigned:
        raise ValueError(f"split references missing cases: {sorted(unknown_assigned)}")
    split_by_case = {
        case_id: split_name
        for split_name, values in split["assignments"].items()
        if split_name != "unassigned"
        for case_id in values
    }
    cluster_splits: dict[str, set[str]] = {}
    for case in cases:
        assigned_split = split_by_case.get(case["case_id"])
        if assigned_split is not None:
            cluster_splits.setdefault(case["cluster_id"], set()).add(assigned_split)
    conflicting_clusters = {
        cluster_id: values
        for cluster_id, values in cluster_splits.items()
        if len(values) > 1
    }
    if conflicting_clusters:
        raise ValueError(f"semantic clusters already cross splits: {conflicting_clusters}")

    for case in cases:
        if case["case_id"] in assigned:
            continue
        inherited = cluster_splits.get(case["cluster_id"])
        if inherited:
            split_name = next(iter(inherited))
            split["assignments"][split_name].append(case["case_id"])
            assigned.add(case["case_id"])
    for split_name in ("engineering_tuning", "threshold_calibration", "sealed_held_out"):
        split["assignments"][split_name] = sorted(split["assignments"][split_name])
    split["assignments"]["unassigned"] = sorted(set(case_ids).difference(assigned))
    if split["assignments"]["unassigned"]:
        split["assignment_status"] = "EXPANDING"
    elif all(
        len(split["assignments"][split_name]) == target
        for split_name, target in split["targets"].items()
    ) and manifest["authored_counts"] == manifest["planned_counts"]:
        manifest["status"] = "COMPLETE"
        split["assignment_status"] = "COMPLETE"

    catalog_path.write_text(json.dumps(catalog, indent=2, ensure_ascii=False) + "\n")
    manifest_path.write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n")
    split_path.write_text(json.dumps(split, indent=2, ensure_ascii=False) + "\n")


def parser() -> argparse.ArgumentParser:
    root = argparse.ArgumentParser()
    root.add_argument("--benchmark-root", type=Path, required=True)
    return root


if __name__ == "__main__":
    arguments = parser().parse_args()
    synchronise(arguments.benchmark_root)
