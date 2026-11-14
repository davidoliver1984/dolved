"""Reproduce failure identity comparison and source freeze; no providers."""
import ast
import gzip
import hashlib
import json
import subprocess
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

repo = Path(sys.argv[1])
root = Path(sys.argv[2]) if len(sys.argv) > 2 else Path(__file__).resolve().parent
baseline = repo / "docs/evaluation/r28-s04/block1-extractive-20261112/verification"
result = {}
for suite in ("ai", "api"):
    rows = []
    for path in (baseline / f"migrated-ready-{suite}.xml", root / f"{suite}-full-final.xml"):
        if not path.exists():
            path = path.with_suffix(path.suffix + ".gz")
        raw = gzip.decompress(path.read_bytes()) if path.suffix == ".gz" else path.read_bytes()
        cases = list(ET.fromstring(raw).iter("testcase"))
        failed = sorted(f"{c.get('classname')}::{c.get('name')}" for c in cases
                        if c.find("failure") is not None or c.find("error") is not None)
        rows.append({"path": str(path.relative_to(repo)), "tests": len(cases),
                     "sha256": hashlib.sha256(path.read_bytes()).hexdigest(),
                     "failures": failed})
    result[suite] = {"baseline": rows[0], "current": rows[1],
        "new": sorted(set(rows[1]["failures"]) - set(rows[0]["failures"])),
        "not_reproduced": sorted(set(rows[0]["failures"]) - set(rows[1]["failures"]))}
(root / "suite-comparison.json").write_text(json.dumps(result, indent=2) + "\n")

def git(*args):
    return subprocess.check_output(["git", *args], cwd=repo)

paths = git("ls-files", "-z", "--", "apps/api/app", "apps/api/config", "apps/api/routes", "apps/ai/app", "contracts").split(b"\0")
records = b"".join(hashlib.sha256((repo / name.decode()).read_bytes()).hexdigest().encode()
    + b"  " + name + b"\n" for name in sorted(p for p in paths if p))
(root / "runtime-source.sha256").write_bytes(records)
path = "apps/ai/app/retrieval/needs_planner.py"
old = git("show", "HEAD:" + path).decode()
new = (repo / path).read_text()
def prompt(text):
    return next(ast.literal_eval(n.value) for n in ast.parse(text).body
        if isinstance(n, ast.Assign) and any(isinstance(t, ast.Name) and t.id == "PROMPT" for t in n.targets))
freeze = {"base_head": git("rev-parse", "HEAD").decode().strip(),
    "branch": git("branch", "--show-current").decode().strip(),
    "main": git("rev-parse", "main").decode().strip(),
    "runtime_aggregate": hashlib.sha256(records).hexdigest(),
    "prompt_unchanged": prompt(old) == prompt(new),
    "prompt_version": "bounded-evidence-needs-v26",
    "adapter_version": "structured-chat-needs-v11",
    "provider_calls": 0,
    "changed_code_hashes": {p: hashlib.sha256((repo / p).read_bytes()).hexdigest()
        for p in ("apps/ai/app/retrieval/needs_planner.py",
                  "apps/ai/tests/test_needs_calendar_notation.py",
                  "apps/ai/tests/test_evidence_needs.py",
                  "apps/ai/tests/test_needs_provider_schema.py",
                  "apps/api/app/Support/Retrieval/EvidenceNeedsPlan.php",
                  "apps/api/tests/Unit/EvidenceNeedsPlanTest.php",
                  "apps/api/tests/Feature/EvidenceNeedsClientTest.php",
                  "apps/api/tests/Feature/EvidenceNeedsRetrievalTest.php",
                  "apps/api/tests/Feature/ConversationOrchestrationTest.php",
                  "contracts/http/retrieval-needs/v4/README.md",
                  "contracts/http/retrieval-needs/v4/python-produced-plan.json")},
    "unchanged_validators_and_schema": {
        p: git("show", "HEAD:" + p) == (repo / p).read_bytes()
        for p in ("apps/ai/app/retrieval/needs.py", "apps/ai/app/retrieval/needs_schema.py")}}
(root / "code-freeze.json").write_text(json.dumps(freeze, indent=2) + "\n")
print(json.dumps({"freeze": freeze, "suites": {k: {"tests": v["current"]["tests"],
    "failures": len(v["current"]["failures"]), "new": v["new"],
    "not_reproduced": v["not_reproduced"]} for k, v in result.items()}}, indent=2))
