"""Cumulative retained-receipt accounting, including the immutable first eight."""
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
BASE = HERE.parent
receipts = []
for run in ("R28-R07-BLOCK1-EXTRACTIVE-20261113-01", "R28-R07-BLOCK1-EXTRACTIVE-TAIL-20261113-01", "R28-R07-BLOCK1-EXTRACTIVE-FINAL-20261113-01"):
    for path in sorted((BASE / "r06-governed-evidence" / run / "cases").glob("*.json")):
        r = json.loads(path.read_text())
        usage = r["observation"].get("usage") or {}
        entries = []
        for attempt in usage.get("planner_validation_attempts", []):
            entries.append(("planner_attempt", {"model": "gpt-5-mini", "provider": "openai", **attempt}))
            if attempt.get("status") == "rejected" and attempt.get("interpretation_check"):
                entries.append(("rejected_attempt_checker", attempt["interpretation_check"]))
        if usage.get("generation"):
            entries.append(("generation_aggregate_including_review", usage["generation"]))
        for key, stage in (r["observation"].get("production_trace") or {}).items():
            if isinstance(stage, dict) and stage.get("usage"):
                values = stage["usage"] if isinstance(stage["usage"], list) else [stage["usage"]]
                entries.extend((key, value) for value in values)
        for source, value in entries:
            amount = value.get("cost_usd")
            basis = value.get("cost_basis")
            if amount is None and value.get("model") == "gpt-5-mini" and isinstance(value.get("input_tokens"), int) and isinstance(value.get("output_tokens"), int):
                cached = value.get("cached_input_tokens") or 0
                amount = ((value["input_tokens"] - cached) * .25 + cached * .025 + value["output_tokens"] * 2) / 1_000_000
                basis = "retained_2026-08-19_rate_estimate_uncached_when_unknown"
            receipts.append({"raw_record": str(path.relative_to(BASE)), "case_id": r["case_id"], "source": source, "receipt": value, "accounted_usd": amount, "accounted_basis": basis})
output = {
    "cumulative_cap_usd": 3,
    "minimum_headroom_before_next_dispatch_usd": .5,
    "known_and_retained_rate_estimate_subtotal_usd": sum(r["accounted_usd"] or 0 for r in receipts),
    "complete_billed_total_available": False,
    "unpriced_stages": [{"case_id": r["case_id"], "source": r["source"], "provider": r["receipt"].get("provider"), "stage": r["receipt"].get("stage")} for r in receipts if r["accounted_usd"] is None],
    "receipts": receipts,
}
output["reranking_conservative_allowance_usd"] = sum((r["receipt"].get("input_tokens") or 0) * max(1, r["receipt"].get("provider_attempt_count") or r["receipt"].get("request_count") or 1) * .05 / 1_000_000 for r in receipts if r["receipt"].get("provider") == "voyage" and r["accounted_usd"] is None)
output["retained_rate_basis"] = "voyage-pricing-2026-09-04 configured 0.05 USD/million; multiply by physical attempts conservatively"
output["unpriced_uncertainty_reserve_usd"] = .5
output["conservative_committed_usd"] = output["known_and_retained_rate_estimate_subtotal_usd"] + output["reranking_conservative_allowance_usd"] + .5
output["available_after_reserve_usd"] = 3 - output["conservative_committed_usd"]
output["next_case_headroom_required_usd"] = .5
output["next_dispatch_budget_gate"] = output["available_after_reserve_usd"] >= .5
(HERE / "accounting.json").write_text(json.dumps(output, indent=2) + "\n")
print(json.dumps({k:v for k,v in output.items() if k not in {"receipts", "unpriced_stages"}}))
