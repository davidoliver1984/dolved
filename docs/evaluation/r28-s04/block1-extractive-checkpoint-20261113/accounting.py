"""Retained-receipt accounting; estimates are not a complete provider invoice."""
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
RUN = HERE.parent / "r06-governed-evidence/R28-R07-BLOCK1-EXTRACTIVE-20261113-01"
receipts = []
for path in sorted((RUN / "cases").glob("*.json")):
    record = json.loads(path.read_text())
    usage = record["observation"].get("usage") or {}
    entries = []
    for attempt in usage.get("planner_validation_attempts", []):
        # Accepted attempt counters already include its interpretation check.
        entries.append(("planner_attempt", {"model": "gpt-5-mini", "provider": "openai", **attempt}))
        if attempt.get("status") == "rejected" and attempt.get("interpretation_check"):
            entries.append(("rejected_attempt_checker", attempt["interpretation_check"]))
    if usage.get("generation"):
        entries.append(("generation_aggregate_including_review", usage["generation"]))
    for key, stage in (record["observation"].get("production_trace") or {}).items():
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
        receipts.append({"case_id": record["case_id"], "source": source, "receipt": value, "accounted_usd": amount, "accounted_basis": basis})
unknown = [{"case_id": r["case_id"], "source": r["source"], "provider": r["receipt"].get("provider"), "stage": r["receipt"].get("stage")} for r in receipts if r["accounted_usd"] is None]
output = {"reservation_usd": 3, "minimum_headroom_before_next_dispatch_usd": .5,
    "known_and_retained_rate_estimate_subtotal_usd": sum(r["accounted_usd"] or 0 for r in receipts),
    "complete_billed_total_available": False, "unpriced_stages": unknown, "receipts": receipts}
(HERE / "accounting.json").write_text(json.dumps(output, indent=2) + "\n")
print(json.dumps({k: v for k, v in output.items() if k not in {"receipts", "unpriced_stages"}}))
