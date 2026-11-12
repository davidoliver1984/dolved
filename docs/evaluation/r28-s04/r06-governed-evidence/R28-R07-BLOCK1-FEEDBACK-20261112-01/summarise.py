"""Offline route/usage inventory; never sends requests or scores semantic coverage."""
import json
from pathlib import Path

root = Path(__file__).parent
cases, receipts = [], []
for path in sorted((root / "cases").glob("*.json")):
    case = json.loads(path.read_text())
    observation = case["observation"]
    trace = observation.get("production_trace") or {}
    lineage = (observation.get("retrieval") or {}).get("lineage") or {}
    usage = observation.get("usage") or {}
    attempts = usage.get("planner_validation_attempts", (usage.get("planner_failure") or {}).get("validation_attempts", []))
    cases.append({"file": str(path.relative_to(root)), "case_id": case["case_id"],
        "status": observation["status"], "displayed_text": case["displayed_text"],
        "workspace_id": observation.get("workspace_id"), "needs_opt_in": case["needs_opt_in"],
        "needs_pipeline_exercised": case["needs_pipeline_exercised"],
        "planner_attempts": attempts,
        "plan": lineage.get("evidence_needs_plan", trace.get("needs_plan", {})),
        "final_evidence": trace.get("needs_final_evidence", []),
        "generation_outcome": (observation.get("generation") or {}).get("outcome"),
        "unsupported_aspects": (observation.get("generation") or {}).get("unsupported_aspects", [])})
    for receipt in attempts:
        receipts.append({"case_id": case["case_id"], "path": "usage.planner_validation_attempts",
                         "stage": "planner", "provider": "openai", "model": "gpt-5-mini", **receipt})
    if isinstance(usage.get("generation"), dict):
        # Aggregate already includes entailment: never count nested usage again.
        receipts.append({"case_id": case["case_id"], "path": "usage.generation", **usage["generation"]})
    for stage, value in trace.items():
        if isinstance(value, dict) and isinstance(value.get("usage"), list):
            receipts.extend({"case_id": case["case_id"], "path": stage + ".usage", **r} for r in value["usage"])

priced = sum(r.get("cost_usd") or 0 for r in receipts)
estimates, missing = [], []
for r in receipts:
    if r.get("cost_usd") is not None:
        continue
    if r.get("model") == "gpt-5-mini" and isinstance(r.get("input_tokens"), int) and isinstance(r.get("output_tokens"), int):
        estimates.append({"case_id": r["case_id"], "stage": r["stage"],
            "cost_usd": r["input_tokens"] * .25 / 1_000_000 + r["output_tokens"] * 2 / 1_000_000,
            "basis": "repository gpt-5-mini rate snapshot; no cache discount; not billed receipt"})
    else:
        missing.append({k: r.get(k) for k in ("case_id", "stage", "provider", "model", "execution", "input_tokens", "request_count")})
accounting = {"receipts": receipts, "known_priced_receipt_sum_usd": priced,
    "offline_missing_openai_price_estimates": estimates,
    "known_plus_offline_openai_estimate_usd": priced + sum(r["cost_usd"] for r in estimates),
    "still_unpriced": missing,
    "note": "Not a complete billed total. Voyage rerank/infrastructure pricing unavailable. Nested generation/entailment not double-counted."}
(root / "case-inventory.json").write_text(json.dumps(cases, indent=2) + "\n")
(root / "accounting.json").write_text(json.dumps(accounting, indent=2) + "\n")
print(json.dumps({"attempted": len(cases), "known_plus_offline_openai_estimate_usd": accounting["known_plus_offline_openai_estimate_usd"], "unpriced_receipts": len(missing)}))
