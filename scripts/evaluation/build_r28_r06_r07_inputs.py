"""Build question-only R06/R07 inputs from the immutable V2 population.

This is a provider-free data preparation step. It does not grant execution
authority, inspect expected evidence, or dispatch a conversation.
"""

from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
POPULATION = (
    ROOT / "tests/evaluation/engineering-populations/dolved-care-v4/v2/population.json"
)
FREEZE = POPULATION.with_name("freeze-manifest.json")
SELECTION = ROOT / "docs/evaluation/r28-s04/r06-selection-20261108.json"


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def write_new(path: Path, value: object) -> None:
    if path.exists():
        raise ValueError(f"refusing to overwrite {path}")
    path.write_text(json.dumps(value, indent=2, ensure_ascii=False) + "\n")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--stage",
        choices=("topical_diagnostics", "regression", "confirmation", "continuation", "confirmation_retry", "confirmation_remaining", "confirmation_tail", "case8_retry", "confirmation_after_case8", "confirmation_cases10_16", "case14_16_retry", "case5_retry", "case9_compare_regression", "case9_uniform_control_retry", "compare_family_final_controls", "case6_retry", "generic_controls", "full", "r07_case32_diagnostic", "r07_case40_retry", "r07_tail", "r07_tail_after_44"),
        required=True,
    )
    parser.add_argument("--output-dir", type=Path, required=True)
    args = parser.parse_args()

    freeze = json.loads(FREEZE.read_text())
    selection = json.loads(SELECTION.read_text())
    expected_sha = freeze["candidate_files"]["population.json"]
    if (
        sha256(POPULATION) != expected_sha
        or selection["population_file_sha256"] != expected_sha
    ):
        raise ValueError("frozen population bytes differ from the selected identity")
    digest = freeze["frozen_population"]["digest"]
    if digest != selection["population_digest"]:
        raise ValueError("frozen population digest differs from the selection")

    population = json.loads(POPULATION.read_text())
    cases = {case["case_id"]: case for case in population["cases"]}
    if len(cases) != 74:
        raise ValueError("population case count is not 74")

    if args.stage in ("topical_diagnostics", "full", "r07_case32_diagnostic", "r07_case40_retry", "r07_tail", "r07_tail_after_44"):
        selected = [
            {
                "case_id": case["case_id"],
                "variant_id": variant["variant_id"],
                "scope": case["scope"],
            }
            for case in population["cases"]
            for variant in case["variants"]
        ]
        if args.stage == "topical_diagnostics":
            selected = [selected[index - 1] for index in (17, 18, 124, 128, 67, 89, 105, 132)]
            manifest_id = "R28-R07-TOPICAL-DIAGNOSTICS-8-20261112-V1"
            expected_count = 8
        elif args.stage == "r07_case32_diagnostic":
            selected = selected[31:32]
            manifest_id = "R28-R07-CASE32-DIAGNOSTIC-1-20261111-V1"
            expected_count = 1
        elif args.stage == "r07_case40_retry":
            selected = selected[39:40]
            manifest_id = "R28-R07-CASE40-RETRY-1-20261109-V1"
            expected_count = 1
        elif args.stage == "r07_tail":
            selected = selected[40:]
            manifest_id = "R28-R07-TAIL-108-20261109-V1"
            expected_count = 108
        elif args.stage == "r07_tail_after_44":
            selected = selected[44:]
            manifest_id = "R28-R07-TAIL-AFTER-44-104-20261109-V1"
            expected_count = 104
        else:
            manifest_id = "R28-R07-FULL-148-V2"
            expected_count = 148
    elif args.stage == "continuation":
        selected = selection["regression"]["items"][-2:]
        manifest_id = "R28-R06-CONTINUATION-2-20261108-V1"
        expected_count = 2
    elif args.stage == "confirmation_retry":
        selected = selection["confirmation"]["items"][3:4]
        manifest_id = "R28-R06-CONFIRMATION-RETRY-1-20261108-V1"
        expected_count = 1
    elif args.stage == "confirmation_remaining":
        selected = selection["confirmation"]["items"][4:]
        manifest_id = "R28-R06-CONFIRMATION-REMAINING-12-20261108-V1"
        expected_count = 12
    elif args.stage == "confirmation_tail":
        selected = selection["confirmation"]["items"][6:]
        manifest_id = "R28-R06-CONFIRMATION-TAIL-10-20261108-V1"
        expected_count = 10
    elif args.stage == "case8_retry":
        selected = selection["confirmation"]["items"][7:8]
        manifest_id = "R28-R06-CASE8-RETRY-1-20261108-V1"
        expected_count = 1
    elif args.stage == "confirmation_after_case8":
        selected = selection["confirmation"]["items"][8:]
        manifest_id = "R28-R06-CONFIRMATION-CASES-9-16-20261108-V1"
        expected_count = 8
    elif args.stage == "confirmation_cases10_16":
        selected = selection["confirmation"]["items"][9:]
        manifest_id = "R28-R06-CONFIRMATION-CASES-10-16-20261108-V1"
        expected_count = 7
    elif args.stage == "case14_16_retry":
        selected = [selection["confirmation"]["items"][index] for index in (13, 15)]
        manifest_id = "R28-R06-CASES-14-16-RETRY-2-20261109-V1"
        expected_count = 2
    elif args.stage == "case9_compare_regression":
        selected = [selection["confirmation"]["items"][index] for index in (8, 6, 12, 2)]
        manifest_id = "R28-R06-CASE9-COMPARE-REGRESSION-4-20261109-V1"
        expected_count = 4
    elif args.stage == "case9_uniform_control_retry":
        selected = [selection["confirmation"]["items"][2]]
        manifest_id = "R28-R06-CASE9-UNIFORM-CONTROL-RETRY-1-20261109-V1"
        expected_count = 1
    elif args.stage == "compare_family_final_controls":
        selected = [selection["confirmation"]["items"][index] for index in (2, 6, 12)]
        manifest_id = "R28-R06-COMPARE-FAMILY-FINAL-CONTROLS-3-20261109-V1"
        expected_count = 3
    elif args.stage in ("case6_retry", "case5_retry", "generic_controls"):
        start, stop, manifest_id = {
            "case6_retry": (5, 6, "R28-R06-CASE6-RETRY-1-20261108-V1"),
            "case5_retry": (4, 5, "R28-R06-CASE5-RETRY-1-20261108-V1"),
            "generic_controls": (0, 3, "R28-R06-GENERIC-CONTROLS-3-20261108-V1"),
        }[args.stage]
        selected = selection["confirmation"]["items"][start:stop]
        expected_count = stop - start
    else:
        block = selection[args.stage]
        selected = block["items"]
        manifest_id = block["manifest_id"]
        expected_count = 8 if args.stage == "regression" else 16

    if len(selected) != expected_count:
        raise ValueError("selection count differs from declared run size")
    seen: set[tuple[str, str]] = set()
    items: list[dict[str, str]] = []
    scopes: list[dict[str, str]] = []
    for item in selected:
        case_id, variant_id, scope = item["case_id"], item["variant_id"], item["scope"]
        identity = (case_id, variant_id)
        if identity in seen:
            raise ValueError("duplicate case/variant identity")
        seen.add(identity)
        case = cases[case_id]
        if case["scope"] != scope:
            raise ValueError("case scope does not match the frozen population")
        variants = [
            variant
            for variant in case["variants"]
            if variant["variant_id"] == variant_id
        ]
        if len(variants) != 1:
            raise ValueError("variant identity is absent or duplicated")
        items.append(
            {
                "case_id": case_id,
                "variant_id": variant_id,
                "utterance": variants[0]["utterance"],
            }
        )
        scopes.append({"case_id": case_id, "variant_id": variant_id, "scope": scope})

    output_dir: Path = args.output_dir.resolve()
    if output_dir.exists():
        raise ValueError("output directory must be new")
    output_dir.mkdir(parents=True, mode=0o700)
    write_new(
        output_dir / "execution-input.json",
        {
            "schema_version": "r28-production-path-input-v1",
            "subset_id": manifest_id,
            "population_id": freeze["frozen_population"]["identity"],
            "population_digest": digest,
            "items": items,
        },
    )
    write_new(
        output_dir / "execution-scopes.json",
        {
            "manifest_id": manifest_id,
            "items": scopes,
        },
    )
    write_new(
        output_dir / "build-manifest.json",
        {
            "manifest_id": manifest_id,
            "stage": args.stage,
            "case_count": len(items),
            "population_file_sha256": expected_sha,
            "selection_file_sha256": sha256(SELECTION),
            "execution_authorised": False,
        },
    )
    inventory = "".join(
        f"{sha256(path)}  {path.name}\n" for path in sorted(output_dir.glob("*.json"))
    )
    (output_dir / "checksums.sha256").write_text(inventory)
    print(
        json.dumps(
            {
                "manifest_id": manifest_id,
                "count": len(items),
                "output_dir": str(output_dir),
            }
        )
    )


if __name__ == "__main__":
    main()
