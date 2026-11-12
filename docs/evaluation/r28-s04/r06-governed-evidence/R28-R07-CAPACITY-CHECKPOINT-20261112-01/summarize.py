"""Provider-free summary of retained receipts; never dispatches requests."""
import json
import sys
from pathlib import Path

root = Path(__file__).parent
cases = []
for path in sorted((root / "cases").glob("*.json")):
    raw = json.loads(path.read_text())
    obs = raw["observation"]
    trace = obs.get("production_trace") or {}
    plan = trace.get("needs_plan") or obs.get("retrieval", {}).get("lineage", {}).get("evidence_needs_plan", {})
    usage = obs.get("usage") or {}
    receipts = []
    for attempt in usage.get("planner_validation_attempts", []):
        receipts.append({"source": "planner_validation_attempt", **attempt})
    generation = usage.get("generation")
    if generation:
        # Aggregate already includes the nested entailment receipt. Do not double count it.
        receipts.append({"source": "generation_including_entailment", **{k: v for k, v in generation.items() if k not in ("entailment_review", "validation_attempts")}})
    for key, stage in trace.items():
        if key.endswith((".search", ".reranked")):
            for receipt in stage.get("usage", []):
                receipts.append({"source": key, **receipt})
    cases.append({
        "record": str(path.relative_to(root)), "case_id": raw["case_id"],
        "status": obs["status"], "needs_pipeline_exercised": raw["needs_pipeline_exercised"],
        "plan": plan, "final_evidence": trace.get("needs_final_evidence", []),
        "displayed_text": raw["displayed_text"],
        "persisted_render_matches_display": raw["displayed_text"] == (obs.get("generation") or {}).get("rendered_text") if obs.get("generation") else None,
        "receipts": receipts,
        "known_estimated_cost_usd": sum(r.get("cost_usd") or 0 for r in receipts),
        "input_tokens_reported": sum(r.get("input_tokens") or 0 for r in receipts),
        "output_tokens_reported": sum(r.get("output_tokens") or 0 for r in receipts),
        "unpriced_receipts": [{"source": r["source"], "stage": r.get("stage"), "input_tokens": r.get("input_tokens"), "output_tokens": r.get("output_tokens")} for r in receipts if r.get("cost_usd") is None],
    })
output = json.dumps({"cases": cases, "known_estimated_cost_usd": sum(c["known_estimated_cost_usd"] for c in cases), "complete_provider_billing_total_available": False, "accounting_note": "Planner and reranking receipts do not carry prices. Infrastructure cost is also unavailable. Generation aggregate includes entailment; nested receipts are not added again. These are reported usage/estimated costs, not a provider invoice."}, indent=2)
if "--write" in sys.argv:
    (root / "accounting-and-case-summary.json").write_text(output + "\n")
else:
    print(output)
