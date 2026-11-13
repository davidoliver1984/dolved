"""Assemble human verdicts with complete retained sources and compiler replay.

No model judge, no automatic semantic scoring, no dispatch.
"""
import json
from collections import Counter
from pathlib import Path

from app.retrieval.needs import EvidenceNeed, compiled_request

HERE = Path(__file__).resolve().parent
BASE = HERE.parent
ROOT = HERE.parents[3]
selection = json.loads((BASE / "block1-extractive-checkpoint-20261113/execution-input.json").read_text())["items"]
population = {c["case_id"]: c for c in json.loads((ROOT / "tests/evaluation/engineering-populations/dolved-care-v4/v2/population.json").read_text())["cases"]}
verdicts = json.loads((HERE / "manual-verdicts.json").read_text())
chunks = {}
for path in [BASE / "r07-evidence-units-v4-20261111/ranked-chunk-text-snapshot.json", BASE / "block1-extractive-checkpoint-20261113/additional-chunks.json", BASE / "block1-extractive-continuation-20261113/additional-chunks.json", HERE / "additional-chunks.json"]:
    chunks.update({c["chunk_id"]: c for c in json.loads(path.read_text())["rows"]})
records = {}
for name in ("R28-R07-BLOCK1-EXTRACTIVE-20261113-01", "R28-R07-BLOCK1-EXTRACTIVE-TAIL-20261113-01", "R28-R07-BLOCK1-EXTRACTIVE-FINAL-20261113-01"):
    for path in (BASE / "r06-governed-evidence" / name / "cases").glob("*.json"):
        r = json.loads(path.read_text())
        key = (r["case_id"], r["variant_id"])
        assert key not in records
        records[key] = (path, r)
rows = []
flags = []
for number, item in enumerate(selection, 1):
    row = {"input": number, **item, "role": "development" if number > 30 else "labelled_checkpoint"}
    found = records.get((item["case_id"], item["variant_id"]))
    if not found:
        rows.append({**row, "status": "not_dispatched_after_correctness_stop", "verdict": None})
        continue
    path, r = found
    o = r["observation"]
    trace = o.get("production_trace") or {}
    needs = trace.get("needs_plan", {}).get("needs", [])
    checks = [a["interpretation_check"] for a in o.get("usage", {}).get("planner_validation_attempts", []) if a.get("interpretation_check")]
    requests = []
    for value in needs:
        need = EvidenceNeed.model_validate(value)
        text = compiled_request(need)
        sources = [*need.context_spans, need.question_span, need.authority.source, need.applicability.source]
        if need.fact_reference:
            sources.append(need.fact_reference.source)
        if need.claim_under_test:
            sources.append(need.claim_under_test.source)
        assert all(s.quote in r["utterance"] for s in sources if s is not None)
        checked = next(n for n in checks[-1]["reviewed_plan"]["needs"] if n["need_id"] == need.need_id)
        assert text == checked["aspect"]
        requests.append({"need_id": need.need_id, "operation": need.operation, "authority": value["authority"], "compiled_request": text, "model_aspect_diagnostic_only": need.aspect, "matches_checked_execution_view": True, "all_source_quotes_grounded": True})
    source_ids = list(dict.fromkeys(e["chunk_id"] for e in trace.get("needs_final_evidence", [])))
    assert all(cid in chunks for cid in source_ids)
    frozen = population[item["case_id"]]
    row.update({
        **verdicts[str(number)],
        "raw_record": str(path.relative_to(BASE)),
        "status": o["status"],
        "displayed_text": r["displayed_text"],
        "frozen_expectation": {k:frozen[k] for k in ("expected_outcome", "expected_evidence", "rationale", "slices")},
        "compiled_requests": requests,
        "generation_parts_and_citations": (o.get("generation") or {}).get("parts"),
        "scope_facts": {k:v for k,v in trace.items() if k.endswith(".scope_fact")},
        "final_evidence": trace.get("needs_final_evidence", []),
        "supporting_source_text_quoted_verbatim": [chunks[cid] for cid in source_ids],
        "interpretation_checks_including_plans": checks,
        "generation_attempts": (o.get("usage", {}).get("generation") or {}).get("validation_attempts", []),
    })
    rows.append(row)
    for c in checks:
        if c["checker_code"] != "accepted":
            flags.append({"input": number, "case_id": item["case_id"], "check": c, "manual_review": verdicts[str(number)]["reason"], "false_retry_flag": number == 8, "final_verdict": row["verdict"], "extra_retry_cost": {"request_count": 2, "input_tokens": 19830, "output_tokens": 1235, "latency_ms": 14609.41734, "estimated_usd": .0074275, "basis": "retained_rate_estimate_not_invoice"} if number == 8 else None})
output = {
    "review_method": "Assistant manual source-based review; no model-judge provider call; frozen labels are not treated as ground truth when contradicted by actual cited version text.",
    "complete_32_case_review": False,
    "reviewed": len(records),
    "not_dispatched": 32 - len(records),
    "verdict_counts": dict(Counter(r["verdict"] for r in rows if r["verdict"])),
    "false_retry_flag_questions": [8],
    "stop_input": 16,
    "stop_criterion": "stage-specific: upstream authority error/shared no-answer, not solely Block 3",
    "flags": flags,
    "cases": rows,
}
(HERE / "correctness-review.json").write_text(json.dumps(output, indent=2, ensure_ascii=False) + "\n")
print(json.dumps({k:v for k,v in output.items() if k not in {"cases", "flags"}}))
