"""Freeze the deliberately stopped continuation; never changes original evidence."""
import json
import shutil
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[4]
HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT / "scripts/evaluation"))
import run_r28_r06_governed as g
import block1_extractive_stage as stage

RUN = g.OUTPUT_ROOT / "R28-R07-BLOCK1-EXTRACTIVE-FINAL-20261113-01"
authority = g.json_file(RUN / "authorization.json")
stage.verify_commit(g, authority)
snapshot = g.runtime_snapshot()
assert g.snapshot_digest(snapshot) == authority["runtime_snapshot_sha256"]
g.write_exclusive(RUN / "code-freeze-after.json", {
    "execution_commit": g.git("rev-parse", "HEAD"),
    "runtime_source_aggregate": g.runtime_aggregate(),
    "runtime_snapshot_sha256": g.snapshot_digest(snapshot),
    "runtime_snapshot": snapshot,
    "stopped_after_local_input": 1,
    "stopped_after_global_input": 16,
    "reason": "stage_specific_hold_current_authority_for_dated_cleaning_supplier",
    "original_evidence_checksums_unchanged": True,
    "at_utc": datetime.now(timezone.utc).isoformat(),
})
for path in [g.RUNNER, g.DISPATCHER, Path(stage.__file__)]:
    shutil.copyfile(path, RUN / ("executed-" + path.name))
existing = set()
for p in [HERE.parent / "r07-evidence-units-v4-20261111/ranked-chunk-text-snapshot.json", HERE.parent / "block1-extractive-checkpoint-20261113/additional-chunks.json", HERE.parent / "block1-extractive-continuation-20261113/additional-chunks.json"]:
    existing.update(r["chunk_id"] for r in g.json_file(p)["rows"])
missing = set()
for path in (RUN / "cases").glob("*.json"):
    trace = g.json_file(path)["observation"].get("production_trace") or {}
    for key, value in trace.items():
        if key.endswith(".reranked"):
            missing.update(c["chunk_id"] for c in value["candidates"] if c["chunk_id"] not in existing)
container = subprocess.check_output([*g.COMPOSE, "ps", "-q", "api"], cwd=ROOT, text=True).strip()
target = "/tmp/r28-extractive-tail-readonly-chunks.php"
source = HERE.parent / "block1-extractive-checkpoint-20261113/hydrate_missing.php"
subprocess.run(["docker", "cp", str(source), f"{container}:{target}"], check=True)
result = subprocess.run([*g.COMPOSE, "exec", "-T", "api", "php", target], cwd=ROOT, input=json.dumps(sorted(missing)), text=True, capture_output=True, check=True)
pulled = json.loads(result.stdout)
g.write_exclusive(HERE / "additional-chunks.json", pulled)
g.write_exclusive(HERE / "additional-chunks-freeze.json", {"sha256": g.sha256(HERE / "additional-chunks.json"), "requested_chunk_ids": sorted(missing), "read_only_transaction": True, "source": "observed reranked IDs only; authorised workspace read", "at_utc": datetime.now(timezone.utc).isoformat()})
g.write_inventory(RUN)
print(json.dumps({"final_records": 1, "cumulative_records": 16, "runtime_unchanged": True, "original_evidence_unchanged": True, "additional_chunks": len(pulled["rows"])}))
