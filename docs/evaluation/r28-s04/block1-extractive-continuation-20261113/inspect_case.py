"""Read retained cases and frozen source text; never dispatch or score automatically."""
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[4]
BASE = ROOT / "docs/evaluation/r28-s04"
run = BASE / "r06-governed-evidence/R28-R07-BLOCK1-EXTRACTIVE-TAIL-20261113-01"
index = int(sys.argv[1])
if "--original" in sys.argv:
    run = BASE / "r06-governed-evidence/R28-R07-BLOCK1-EXTRACTIVE-20261113-01"
path = next((run / "cases").glob(f"{index:02d}-*.json"))
r = json.loads(path.read_text())
chunks = {}
for name in ["r07-evidence-units-v4-20261111/ranked-chunk-text-snapshot.json", "block1-extractive-checkpoint-20261113/additional-chunks.json", "block1-extractive-continuation-20261113/additional-chunks.json"]:
    p = BASE / name
    if p.exists():
        chunks.update({v["chunk_id"]: v for v in json.loads(p.read_text())["rows"]})
population = json.loads((ROOT / "tests/evaluation/engineering-populations/dolved-care-v4/v2/population.json").read_text())
expected = next(v for v in population["cases"] if v["case_id"] == r["case_id"])
o = r["observation"]
t = o.get("production_trace") or {}
checks = [a.get("interpretation_check") for a in o.get("usage", {}).get("planner_validation_attempts", []) if a.get("interpretation_check")]
print(json.dumps({"global_index": index + 8, "question": r["utterance"], "answer": r["displayed_text"], "status": o["status"], "expectation": expected, "parts": (o.get("generation") or {}).get("parts"), "unsupported": (o.get("generation") or {}).get("unsupported_aspects"), "plan": t.get("needs_plan"), "flags": [{k:v for k,v in c.items() if k != "reviewed_plan"} for c in checks], "eligibility": {k:{f:v.get(f) for f in ("outcome", "reason", "resolved_location_public_id")} for k,v in t.items() if k.endswith(".eligibility")}, "final": t.get("needs_final_evidence")}, indent=2))
for item in t.get("needs_final_evidence", []):
    cid = item["chunk_id"]
    print("SOURCE", json.dumps(chunks.get(cid, {"MISSING": cid}), ensure_ascii=False))
