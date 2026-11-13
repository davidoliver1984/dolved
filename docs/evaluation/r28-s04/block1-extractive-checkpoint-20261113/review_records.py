"""Provider-free replay of retained source spans; no semantic judge calls."""
import json
from pathlib import Path

from app.retrieval.needs import EvidenceNeed, compiled_request

HERE = Path(__file__).resolve().parent
RUN = HERE.parent / "r06-governed-evidence/R28-R07-BLOCK1-EXTRACTIVE-20261113-01"
rows = []
for path in sorted((RUN / "cases").glob("*.json")):
    record = json.loads(path.read_text())
    trace = record["observation"].get("production_trace") or {}
    checks = [a["interpretation_check"] for a in record["observation"].get("usage", {}).get("planner_validation_attempts", []) if a.get("interpretation_check")]
    compiled = []
    for value in trace.get("needs_plan", {}).get("needs", []):
        need = EvidenceNeed.model_validate(value)
        text = compiled_request(need)
        sources = [*need.context_spans, need.question_span, need.authority.source, need.applicability.source]
        if need.fact_reference:
            sources.append(need.fact_reference.source)
        if need.claim_under_test:
            sources.append(need.claim_under_test.source)
        assert all(s.quote in record["utterance"] for s in sources if s is not None)
        accepted = checks[-1]["reviewed_plan"]["needs"]
        checked = next(n for n in accepted if n["need_id"] == need.need_id)
        assert text == checked["aspect"]
        compiled.append({"need_id": need.need_id, "compiled_request": text, "matches_checked_execution_view": True, "model_aspect_diagnostic_only": need.aspect})
    rows.append({"raw_record": path.name, "case_id": record["case_id"], "question": record["utterance"], "displayed_text": record["displayed_text"], "status": record["observation"]["status"], "compiled_needs": compiled, "checks": checks})
flagged = [r for r in rows if any(c["checker_code"] != "accepted" for c in r["checks"])]
case8 = next(r for r in rows if r["case_id"].endswith("b01-10"))
same = case8["checks"][0]["reviewed_plan"]["needs"][0]["aspect"] == case8["checks"][1]["reviewed_plan"]["needs"][0]["aspect"]
assert same
(HERE / "record-review.json").write_text(json.dumps({"case8_first_and_second_compiled_aspect_identical": same, "all_check_receipts_count": sum(len(r["checks"]) for r in rows), "flagged_cases": [r["case_id"] for r in flagged], "cases": rows}, indent=2) + "\n")
print(json.dumps({"replayed_records": len(rows), "case8_identical_compiled_requests": same, "flagged_cases": [r["case_id"] for r in flagged]}))
