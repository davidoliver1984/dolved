"""Offline exact failure-set comparison from retained JUnit."""
import json
from pathlib import Path
import xml.etree.ElementTree as ET

root = Path(__file__).parent


def read(path):
    cases = list(ET.parse(path).iter("testcase"))
    names = sorted({c.attrib.get("classname", "") + "::" + c.attrib["name"]
                    for c in cases if c.find("failure") is not None or c.find("error") is not None})
    return {"tests": len(cases), "failure_names": names,
            "skipped": sum(c.find("skipped") is not None for c in cases)}


out = {"baseline_commit": "6a46ace605995fe4e7c024296a602cdae5fb05b5", "suites": {}}
for service in ("ai", "api"):
    before, after = read(root / f"baseline-{service}.xml"), read(root / f"changed-{service}.xml")
    out["suites"][service] = {"baseline": before, "changed": after,
        "new_failures": sorted(set(after["failure_names"]) - set(before["failure_names"])),
        "resolved_failures": sorted(set(before["failure_names"]) - set(after["failure_names"]))}
out["identical_failure_sets"] = all(not x["new_failures"] and not x["resolved_failures"] for x in out["suites"].values())
(root / "suite-comparison.json").write_text(json.dumps(out, indent=2) + "\n")
print(json.dumps(out, indent=2))
