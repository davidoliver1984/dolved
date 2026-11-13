"""Offline checkpoint measurement; never supplies labels to execution."""
import importlib.util
import json
import sys
from pathlib import Path
from statistics import fmean

from app.evaluation.models import EvaluationCorpus

ROOT = Path(__file__).resolve().parents[4]
HERE = Path(__file__).resolve().parent
RUNS = [ROOT / "docs/evaluation/r28-s04/r06-governed-evidence" / name for name in ("R28-R07-BLOCK1-EXTRACTIVE-20261113-01", "R28-R07-BLOCK1-EXTRACTIVE-TAIL-20261113-01")]
paths = [p for run in RUNS for p in sorted((run / "cases").glob("*.json"))]
spec = importlib.util.spec_from_file_location("original_metrics", ROOT / "docs/evaluation/r28-s04/r07-evidence-unit-metrics-v4-20261111/compute_metrics.py")
metrics = importlib.util.module_from_spec(spec)
spec.loader.exec_module(metrics)
corpus_path = ROOT / "docs/evaluation/r28-s04/r07-evidence-units-v4-20261111/evidence-units-corpus.json"
snapshot_path = corpus_path.with_name("ranked-chunk-text-snapshot.json")
cases = {c.case_id: c for c in EvaluationCorpus.model_validate_json(corpus_path.read_text()).cases}
chunks = {r["chunk_id"]: r for r in json.loads(snapshot_path.read_text())["rows"]}
chunks.update({r["chunk_id"]: r for r in json.loads((HERE.parent / "block1-extractive-checkpoint-20261113/additional-chunks.json").read_text())["rows"]})
extra = HERE / "additional-chunks.json"
if extra.exists():
    chunks.update({r["chunk_id"]: r for r in json.loads(extra.read_text())["rows"]})
selection = json.loads((HERE.parent / "block1-extractive-checkpoint-20261113/selection-and-expectations.json").read_text())["cases"]
selected = {c["case_id"]: c for c in selection}
results = []
missing = set()
for path in paths:
    record = json.loads(path.read_text())
    trace = record["observation"].get("production_trace") or {}
    for key, stage in trace.items():
        if key.endswith(".reranked"):
            missing.update(c["chunk_id"] for c in stage["candidates"] if c["chunk_id"] not in chunks)
if missing:
    print(json.dumps({"missing_chunk_ids": sorted(missing)}))
    sys.exit(2)
for path in paths:
    record = json.loads(path.read_text())
    case = cases[record["case_id"]]
    trace = record["observation"].get("production_trace") or {}
    ranked, lineage = metrics.attempted_ranking(trace, chunks)
    row = {"case_id": case.case_id, "variant_id": record["variant_id"], "role": selected[case.case_id]["role"],
        "reached_rerank": any(k.endswith(".reranked") for k in trace), "status": record["observation"]["status"],
        "displayed_text": record["displayed_text"], "attempted_rerank": {}, "ranked_lineage": lineage}
    for side in ("PRIMARY", "COMPARISON"):
        units = tuple(u for u in case.evidence_units if u.side == side)
        if units:
            row["attempted_rerank"][side] = metrics.metric_dict(units, [r for r in ranked if r.side == side])
    results.append(row)
summary = {}
for side in ("PRIMARY", "COMPARISON"):
    observed = [r for r in results if r["role"] == "labelled_checkpoint" and r["reached_rerank"] and side in r["attempted_rerank"]]
    baseline = [s["baseline"] for s in selection[:30] if s["baseline"]["reached_rerank"] and side in s["baseline"]["attempted_rerank"]]
    intersection = [r for r in observed if selected[r["case_id"]]["baseline"]["reached_rerank"]]
    def recall(rows):
        return fmean(r["attempted_rerank"][side]["recall_at_k"] for r in rows) if rows else None
    summary[side] = {"observed_attempted_count": len(observed), "observed_recall_at_5": recall(observed),
        "frozen_baseline_attempted_count": len(baseline), "frozen_baseline_recall_at_5": recall(baseline),
        "intersection_count": len(intersection), "intersection_new_recall_at_5": recall(intersection),
        "intersection_baseline_recall_at_5": recall([selected[r["case_id"]]["baseline"] for r in intersection])}
output = {"K": 5, "matching_algorithm": "normalised-token-coverage-v1", "completed_records": len(results),
    "complete_checkpoint": len(results) == 32, "summary": summary, "cases": results}
destination = HERE / (sys.argv[1] if len(sys.argv) > 1 else "results.json")
destination.write_text(json.dumps(output, indent=2) + "\n")
print(json.dumps({"records": len(results), "summary": summary}))
