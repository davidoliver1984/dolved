"""Compile reviewed engineering-only ADR-0022 planner truth deterministically."""

from __future__ import annotations

import hashlib
import json
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / "docs/evaluation/planner-experiments/PLN-EXP-0004-thin-intent-historical-reference/expectations.json"
OUTPUT = ROOT / "tests/evaluation/planner-expectations/v2/engineering-expectations.json"

REFERENCES: dict[tuple[str, str], tuple[str, str]] = {
    ("health-safety.accident.valid-at-date", "dated"): ("calendar_period", "January 2024"),
    ("health-safety.accident.valid-at-date", "historical"): ("historical_reference", "old accident procedure"),
    ("health-safety.accident.valid-at-date", "contrast"): ("calendar_period", "2024"),
    ("health-safety.moving-handling.compare", "colloquial"): ("historical_reference", "old policy"),
    ("hr.annual-leave.valid-at-date", "dated"): ("calendar_period", "June 2024"),
    ("hr.annual-leave.valid-at-date", "old"): ("historical_reference", "version 1"),
    ("hr.annual-leave.valid-at-date", "contrast"): ("calendar_period", "2024"),
    ("infection.outbreak.valid-before-withdrawal", "historical"): ("historical_reference", "before it was withdrawn, outbreak version 2"),
    ("infection.outbreak.valid-before-withdrawal", "contrast"): ("calendar_period", "January 2026"),
    ("medication.controlled-drugs.valid-at-date", "dated"): ("calendar_period", "January 2024"),
    ("medication.controlled-drugs.valid-at-date", "historical"): ("historical_reference", "old CD stock discrepancy deadline"),
    ("medication.controlled-drugs.valid-at-date", "contrast"): ("historical_reference", "2023 procedure"),
    ("pilot.valid-at-date.medication-administration", "historical"): ("calendar_period", "June 2024"),
    ("pilot.valid-at-date.medication-administration", "colloquial"): ("historical_reference", "old meds policy"),
}


def compile_expectations() -> dict[str, Any]:
    source_bytes = SOURCE.read_bytes()
    source = json.loads(source_bytes)
    expectations = []
    used_references: set[tuple[str, str]] = set()
    for variant in source["variants"]:
        key = (variant["case_id"], variant["variant_id"])
        expected = variant["expected"]
        mode = str(expected["temporal_intent"]).lower()
        explicit_date = expected.get("explicit_date")
        temporal_reference = None
        if key in REFERENCES:
            kind, value = REFERENCES[key]
            temporal_reference = {"kind": kind, "value": value}
            explicit_date = None
            used_references.add(key)
        if mode in {"valid_at_date", "historical_reference"} and (
            (explicit_date is None) == (temporal_reference is None)
        ):
            raise ValueError(f"missing reviewed temporal selector for {key}")
        expectations.append({
            "case_id": key[0],
            "variant_id": key[1],
            "question": variant["question"],
            "contract": {
                "contract_version": 2,
                "temporal_mode": mode,
                "explicit_date": explicit_date,
                "temporal_reference": temporal_reference,
                "location_references": expected["location_references"],
                "clarification_reason": None,
            },
        })
    if used_references != set(REFERENCES):
        raise ValueError("reviewed ADR-0022 reconciliation keys do not match source")
    payload = {
        "schema_version": "v2",
        "scope": "engineering_tuning",
        "protected_splits_accessed": False,
        "source_experiment": source["experiment_id"],
        "source_expectations_sha256": hashlib.sha256(source_bytes).hexdigest(),
        "reconciled_temporal_reference_count": len(REFERENCES),
        "expectations": sorted(
            expectations, key=lambda item: (item["case_id"], item["variant_id"])
        ),
    }
    encoded = json.dumps(payload, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()
    payload["expectations_digest"] = hashlib.sha256(encoded).hexdigest()
    return payload


def main() -> None:
    payload = compile_expectations()
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.write_text(json.dumps(payload, ensure_ascii=False, indent=2, sort_keys=True) + "\n")


if __name__ == "__main__":
    main()
