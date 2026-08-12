"""Compile an engineering-only case snapshot from EXP-0001 observations.

The source file already contains only the reviewed 42-case/126-variant engineering
split. This compiler deliberately has no canonical benchmark-corpus or split path,
so it cannot read calibration or held-out case content.
"""

from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
from typing import Any

EXPECTED_BENCHMARK_ID = "dolved-care-engineering"
EXPECTED_BENCHMARK_VERSION = "v2"
EXPECTED_BENCHMARK_DIGEST = (
    "aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d"
)
EXPECTED_SOURCE_RUN = "EXP-0001-alderbridge-initial-hybrid"


def canonical_bytes(value: Any) -> bytes:
    return json.dumps(
        value, ensure_ascii=False, sort_keys=True, separators=(",", ":")
    ).encode()


def digest(value: Any) -> str:
    return hashlib.sha256(canonical_bytes(value)).hexdigest()


def compile_snapshot(source_path: Path) -> dict[str, Any]:
    source_bytes = source_path.read_bytes()
    source = json.loads(source_bytes)
    benchmark = source.get("benchmark")
    observations = source.get("observations")
    if (
        source.get("run_id") != EXPECTED_SOURCE_RUN
        or not isinstance(benchmark, dict)
        or benchmark.get("id") != EXPECTED_BENCHMARK_ID
        or benchmark.get("version") != EXPECTED_BENCHMARK_VERSION
        or benchmark.get("digest") != EXPECTED_BENCHMARK_DIGEST
        or not isinstance(observations, list)
        or len(observations) != 126
    ):
        raise ValueError("the source is not the immutable EXP-0001 engineering run")

    cases: dict[str, dict[str, Any]] = {}
    observed_variants: set[tuple[str, str]] = set()
    for observation in observations:
        if not isinstance(observation, dict):
            raise TypeError("an engineering observation is not an object")
        case = observation.get("case")
        variant = observation.get("variant")
        if not isinstance(case, dict) or not isinstance(variant, dict):
            raise TypeError("an engineering observation lacks case/variant truth")
        case_id = str(case.get("case_id", ""))
        variant_id = str(variant.get("variant_id", ""))
        if not case_id or not variant_id:
            raise ValueError("an engineering case/variant identity is empty")
        existing = cases.setdefault(case_id, case)
        if canonical_bytes(existing) != canonical_bytes(case):
            raise ValueError(f"case truth differs between observations: {case_id}")
        key = (case_id, variant_id)
        if key in observed_variants:
            raise ValueError(f"duplicate engineering variant: {key}")
        observed_variants.add(key)

    case_ids = sorted(cases)
    declared_ids = benchmark.get("engineering_case_ids")
    if (
        len(cases) != 42
        or not isinstance(declared_ids, list)
        or case_ids != sorted(str(value) for value in declared_ids)
    ):
        raise ValueError("engineering case identities do not match EXP-0001 lineage")
    declared_variants = {
        (case_id, str(variant["variant_id"]))
        for case_id, case in cases.items()
        for variant in case.get("variants", [])
        if isinstance(variant, dict) and "variant_id" in variant
    }
    if declared_variants != observed_variants or len(declared_variants) != 126:
        raise ValueError("engineering variant truth is incomplete or inconsistent")

    payload: dict[str, Any] = {
        "schema_version": "v1",
        "benchmark": {
            "id": benchmark["id"],
            "version": benchmark["version"],
            "digest": benchmark["digest"],
            "evaluation_clock": benchmark["evaluation_clock"],
        },
        "split": {
            "name": "engineering_tuning",
            "version": str(benchmark["split_version"]),
            "case_ids": case_ids,
            "case_ids_digest": digest(case_ids),
        },
        "source": {
            "experiment_id": source["run_id"],
            "application_observations_sha256": hashlib.sha256(source_bytes).hexdigest(),
        },
        "case_count": len(cases),
        "variant_count": len(observed_variants),
        "cases": [cases[case_id] for case_id in case_ids],
    }
    payload["snapshot_digest"] = digest(payload)
    return payload


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source-observations", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    snapshot = compile_snapshot(args.source_observations)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(
        json.dumps(snapshot, ensure_ascii=False, indent=2, sort_keys=True) + "\n"
    )


if __name__ == "__main__":
    main()
