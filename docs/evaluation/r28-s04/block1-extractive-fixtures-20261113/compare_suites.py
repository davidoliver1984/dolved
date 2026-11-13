"""Provider-free comparison; preserves the original failed-gate artifacts."""
import hashlib
import json
import re
from pathlib import Path
import xml.etree.ElementTree as ET

OUT = Path(__file__).resolve().parent
OLD = OUT.parent / "block1-extractive-20261112"


def read(path):
    cases = list(ET.parse(path).iter("testcase"))
    failures = {}
    for case in cases:
        failure = case.find("failure")
        if failure is None:
            failure = case.find("error")
        if failure is not None:
            failures[f"{case.get('classname')}::{case.get('name')}"] = failure.text
    return {"tests": len(cases), "failures": failures}


result = {"baseline_commit": "348f09e053c8ad73676921c5c616cced812111d7", "suites": {}}
inputs = {}
for suite in ("ai", "api"):
    paths = [OLD / "verification" / f"{prefix}-{suite}.xml" for prefix in ("baseline", "changed", "migrated-ready")]
    baseline, held, migrated = [read(p) for p in paths]
    result["suites"][suite] = {
        "baseline": baseline, "held_candidate": held, "migrated": migrated,
        "new_failures": sorted(migrated["failures"].keys() - baseline["failures"].keys()),
        "resolved_baseline_failures": sorted(baseline["failures"].keys() - migrated["failures"].keys()),
        "resolved_held_failures": sorted(held["failures"].keys() - migrated["failures"].keys()),
    }
    for path in paths:
        inputs[str(path.relative_to(OUT.parent))] = hashlib.sha256(path.read_bytes()).hexdigest()
result["junit_sha256"] = inputs
(OUT / "suite-comparison.json").write_text(json.dumps(result, indent=2) + "\n")
for suite, values in result["suites"].items():
    print(suite, "failures", len(values["migrated"]["failures"]), "new", values["new_failures"], "resolved baseline", values["resolved_baseline_failures"])

def type_errors(path):
    return sorted(re.sub(r":\d+: error:", ": error:", line) for line in path.read_text().splitlines() if ": error:" in line)

a = type_errors(OUT / "typecheck-ai-baseline.log")
b = type_errors(OUT / "typecheck-ai-local-cache.log")
(OUT / "typecheck-comparison.json").write_text(json.dumps({"baseline": a, "candidate": b, "new": sorted(set(b)-set(a)), "resolved": sorted(set(a)-set(b))}, indent=2) + "\n")
paths = [p for p in OUT.rglob("*") if p.is_file() and p.name != "checksums.sha256"]
paths += list((OLD / "verification").glob("migrated*"))
(OUT / "checksums.sha256").write_text("".join(f"{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.relative_to(OUT.parent)}\n" for p in sorted(paths)))
