"""Compile the reviewed provider-free current-retrieval fixture.

The immutable Benchmark V2 engineering snapshot remains the parent population.
Two questions whose original wording no longer expresses their reviewed ADR-0022
semantics are replaced with already-reviewed Benchmark V3 wording. The current
organisation catalogue is likewise taken from V3 because it is the independently
reviewed source of the authoritative Coventry, Midlands and South West aliases.
No expected EvidenceUnit or document source content is used to derive plans,
organisation state, or searchable text.
"""

from __future__ import annotations

import hashlib
import json
import sys
from copy import deepcopy
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[2]
OUTPUT = ROOT / "tests/evaluation/current-retrieval/v1"
V2_ROOT = ROOT / "tests/evaluation/benchmarks/dolved-care-engineering/v2"
V3_ROOT = ROOT / "tests/evaluation/benchmarks/dolved-care-engineering/v3"
PARENT_SNAPSHOT = (
    ROOT
    / "tests/evaluation/engineering-snapshots/dolved-care-engineering/v2/corpus.json"
)
PARENT_PLANS = (
    ROOT
    / "tests/evaluation/planner-expectations/v2/engineering-expectations.json"
)

RECONCILIATIONS: dict[tuple[str, str], dict[str, Any]] = {
    ("health-safety.moving-handling.compare", "colloquial"): {
        "reviewed_v3_case_id": "v3.health.compare.moving-handling-staffing",
        "reviewed_v3_variant_id": "colloquial",
        "question": "How did the rule about one or two staff for a hoist change?",
        "contract": {
            "contract_version": 2,
            "temporal_mode": "compare",
            "explicit_date": None,
            "temporal_reference": None,
            "location_references": [],
            "clarification_reason": None,
        },
    },
    ("medication.controlled-drugs.valid-at-date", "contrast"): {
        "reviewed_v3_case_id": "v3.medication.historical.controlled-drugs-v1",
        "reviewed_v3_variant_id": "precision",
        "question": (
            "Did controlled-drugs procedure version 1 allow reporting a "
            "discrepancy by the end of the shift?"
        ),
        "contract": {
            "contract_version": 2,
            "temporal_mode": "historical_reference",
            "explicit_date": None,
            "temporal_reference": {
                "kind": "historical_reference",
                "value": "version 1",
            },
            "location_references": [],
            "clarification_reason": None,
        },
    },
}


def canonical_bytes(value: Any) -> bytes:
    return json.dumps(
        value, ensure_ascii=False, sort_keys=True, separators=(",", ":")
    ).encode()


def digest(value: Any) -> str:
    return hashlib.sha256(canonical_bytes(value)).hexdigest()


def file_digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def output_bytes(value: Any) -> bytes:
    return (
        json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + "\n"
    ).encode()


def load(path: Path) -> dict[str, Any]:
    value = json.loads(path.read_bytes())
    if not isinstance(value, dict):
        raise TypeError(f"expected a JSON object: {path}")
    return value


def reviewed_v3_variants() -> dict[tuple[str, str], str]:
    result: dict[tuple[str, str], str] = {}
    for path in sorted((V3_ROOT / "cases").glob("*.json")):
        for case in load(path).get("cases", []):
            for variant in case.get("variants", []):
                result[(case["case_id"], variant["variant_id"])] = variant["question"]
    return result


def compile_fixture() -> dict[str, dict[str, Any]]:
    snapshot = deepcopy(load(PARENT_SNAPSHOT))
    plans = deepcopy(load(PARENT_PLANS))
    organisation = load(V3_ROOT / "organisation.json")
    checksums = deepcopy(load(V2_ROOT / "compiled/checksums.json"))
    reviewed = reviewed_v3_variants()

    if snapshot.get("snapshot_digest") != (
        "8f67bb00ad22fe8f74ecdc834f66f22a00bf97bffe409d6857ce44fc0a0a5de5"
    ):
        raise ValueError("the parent engineering snapshot has changed")
    if plans.get("expectations_digest") != (
        "9a301a9be26e2b0fec7c7d497db8e59b63a5ab55f339336b395c01e9e0a2235f"
    ):
        raise ValueError("the parent authored-plan catalogue has changed")

    snapshot_pairs = {
        (case["case_id"], variant["variant_id"]): variant
        for case in snapshot["cases"]
        for variant in case["variants"]
    }
    plan_pairs = {
        (item["case_id"], item["variant_id"]): item
        for item in plans["expectations"]
    }
    if snapshot_pairs.keys() != plan_pairs.keys() or len(snapshot_pairs) != 126:
        raise ValueError("the parent variant populations do not match")

    lineage_entries = []
    for identity, correction in RECONCILIATIONS.items():
        reviewed_identity = (
            correction["reviewed_v3_case_id"],
            correction["reviewed_v3_variant_id"],
        )
        if reviewed.get(reviewed_identity) != correction["question"]:
            raise ValueError(f"reviewed V3 wording drifted: {reviewed_identity}")
        snapshot_pairs[identity]["question"] = correction["question"]
        plan_pairs[identity]["question"] = correction["question"]
        plan_pairs[identity]["contract"] = correction["contract"]
        lineage_entries.append(
            {
                "case_id": identity[0],
                "variant_id": identity[1],
                "reviewed_v3_case_id": reviewed_identity[0],
                "reviewed_v3_variant_id": reviewed_identity[1],
                "question_digest": hashlib.sha256(
                    correction["question"].encode()
                ).hexdigest(),
            }
        )

    reconciliation = {
        "id": "current-retrieval-v1-review-reconciliation",
        "version": "1",
        "parent_snapshot_digest": snapshot.pop("snapshot_digest"),
        "parent_plan_catalogue_digest": plans.pop("expectations_digest"),
        "organisation_source": "benchmark-v3-reviewed-catalogue",
        "organisation_sha256": file_digest(V3_ROOT / "organisation.json"),
        "entries": lineage_entries,
    }
    reconciliation["digest"] = digest(reconciliation)
    snapshot["current_retrieval_reconciliation"] = reconciliation
    snapshot["snapshot_digest"] = digest(snapshot)
    plans["current_retrieval_reconciliation"] = reconciliation
    plans["expectations_digest"] = digest(plans)

    checksums["corpus_version"] = "2-current-retrieval-v1"
    checksums["files"]["organisation.json"] = hashlib.sha256(
        output_bytes(organisation)
    ).hexdigest()
    checksums["parent_benchmark_digest"] = checksums.pop("benchmark_digest")
    checksums["current_retrieval_reconciliation_digest"] = reconciliation[
        "digest"
    ]
    checksums["fixture_digest"] = digest(
        {
            "files": checksums["files"],
            "reconciliation": reconciliation,
        }
    )

    lineage = {
        "schema_version": "v1",
        "fixture_id": "dolved-current-retrieval",
        "fixture_version": "1",
        "case_count": snapshot["case_count"],
        "variant_count": snapshot["variant_count"],
        "parent_benchmark": snapshot["benchmark"],
        "reconciliation": reconciliation,
        "artefacts": {
            "corpus_digest": snapshot["snapshot_digest"],
            "plans_digest": plans["expectations_digest"],
            "organisation_sha256": hashlib.sha256(
                output_bytes(organisation)
            ).hexdigest(),
            "source_checksums_digest": digest(checksums),
        },
        "protected_splits_accessed": False,
    }
    lineage["lineage_digest"] = digest(lineage)

    return {
        "corpus.json": snapshot,
        "plans.json": plans,
        "organisation.json": organisation,
        "source-checksums.json": checksums,
        "lineage.json": lineage,
    }


def main() -> None:
    generated = compile_fixture()
    if sys.argv[1:] == ["--check"]:
        mismatched = [
            filename
            for filename, payload in generated.items()
            if not (OUTPUT / filename).is_file()
            or load(OUTPUT / filename) != payload
        ]
        if mismatched:
            raise SystemExit(
                "current-retrieval fixture differs from deterministic compilation: "
                + ", ".join(mismatched)
            )
        return
    if sys.argv[1:]:
        raise SystemExit("usage: compile_current_retrieval_fixture.py [--check]")
    OUTPUT.mkdir(parents=True, exist_ok=True)
    for filename, payload in generated.items():
        (OUTPUT / filename).write_text(
            output_bytes(payload).decode()
        )


if __name__ == "__main__":
    main()
