"""Finalize the stopped run without any provider invocation or case mutation."""
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

RUN = g.OUTPUT_ROOT / "R28-R07-BLOCK1-EXTRACTIVE-20261113-01"
authority = g.json_file(RUN / "authorization.json")
g.block1_extractive_stage.verify_commit(g, authority)
snapshot = g.runtime_snapshot()
if g.snapshot_digest(snapshot) != authority["runtime_snapshot_sha256"]:
    raise RuntimeError("after-run index/configuration drift")
g.write_exclusive(RUN / "code-freeze-after.json", {
    "execution_commit": g.git("rev-parse", "HEAD"),
    "runtime_source_aggregate": g.runtime_aggregate(),
    "runtime_snapshot_sha256": g.snapshot_digest(snapshot),
    "runtime_snapshot": snapshot, "stopped_after": 8,
    "reason": "retry_triggering_false_clause_omitted_flag_on_b01_10_v1",
    "at_utc": datetime.now(timezone.utc).isoformat(),
})
for path in [g.RUNNER, g.DISPATCHER, Path(g.block1_extractive_stage.__file__)]:
    shutil.copyfile(path, RUN / ("executed-" + path.name))
existing = {r["chunk_id"] for r in g.json_file(ROOT / "docs/evaluation/r28-s04/r07-evidence-units-v4-20261111/ranked-chunk-text-snapshot.json")["rows"]}
missing = set()
for path in (RUN / "cases").glob("*.json"):
    trace = g.json_file(path)["observation"].get("production_trace") or {}
    for key, stage in trace.items():
        if key.endswith(".reranked"):
            missing.update(c["chunk_id"] for c in stage["candidates"] if c["chunk_id"] not in existing)
container = subprocess.check_output([*g.COMPOSE, "ps", "-q", "api"], cwd=ROOT, text=True).strip()
target = "/tmp/r28-extractive-readonly-chunks.php"
subprocess.run(["docker", "cp", str(HERE / "hydrate_missing.php"), f"{container}:{target}"], check=True)
result = subprocess.run([*g.COMPOSE, "exec", "-T", "api", "php", target], cwd=ROOT, input=json.dumps(sorted(missing)), text=True, capture_output=True, check=True)
pulled = json.loads(result.stdout)
g.write_exclusive(HERE / "additional-chunks.json", pulled)
g.write_exclusive(HERE / "additional-chunks-freeze.json", {"sha256": g.sha256(HERE / "additional-chunks.json"), "requested_chunk_ids": sorted(missing), "read_only_transaction": True, "source": "observed reranked chunk IDs only, authorised primary/injection workspaces", "at_utc": datetime.now(timezone.utc).isoformat()})
g.write_inventory(RUN)
print(json.dumps({"stopped_records": len(list((RUN / "cases").glob("*.json"))), "source_and_index_unchanged": True, "additional_chunks": len(pulled["rows"])}))
