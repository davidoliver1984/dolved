"""R00 diagnostic replay: fake only HTTP transport; never call providers.

Run through stdin in the existing AI container, with PYTHONDONTWRITEBYTECODE=1.
These assertions reproduce defects in the baseline, not desired behaviour.
"""
import json
import socket

import httpx
from pydantic import SecretStr

from app.retrieval.planner import StructuredChatRetrievalPlanner


def forbidden(*args, **kwargs):
    raise AssertionError("Network forbidden in R00 replay")


socket.socket.connect = forbidden
results = []
for label, question, mode, date, locations in [
    ("unsupported_date", "How did the order of DNACPR, symptom management and advance care planning change in June 2026, and did their underlying requirements change?", "compare", "0606-06-06", []),
    ("generic_location", "If we discover a completely new workplace hazard, what is the immediate management response and what related documentation has to be kept?", "current", None, ["workplace"]),
    ("training_policy_override", "What was our training policy on 15 December 2031?", "valid_at_date", "2031-12-15", []),
]:
    payload = {
        "retrieval_queries": [question],
        "intent": {"temporal_mode": mode, "explicit_date": date, "temporal_reference": None, "clarification_reason": None},
        "location_references": locations,
        "version_transition_boundary": None,
        "fact_date": None,
        "requested_evidence_type": "current_versus_historical_comparison" if mode == "compare" else "policy_or_procedural_requirements",
    }
    def handler(request):
        assert json.loads(request.content)["response_format"]["type"] == "json_schema"
        return httpx.Response(200, json={"choices": [{"message": {"content": json.dumps(payload)}}], "usage": {"prompt_tokens": 1, "completion_tokens": 1}})
    planner = StructuredChatRetrievalPlanner(api_url="https://planner.invalid/v1/chat/completions", api_key=SecretStr("fake"), provider_name="fake", model="fake", timeout_seconds=1, client=httpx.Client(transport=httpx.MockTransport(handler)))
    plan = planner.plan(question, evaluated_at="2026-09-05T12:00:00Z")
    actual = plan.model_dump(mode="json")
    if label == "unsupported_date":
        assert actual["explicit_date"] == "0606-06-06"
    elif label == "generic_location":
        assert actual["location_references"] == ["workplace"]
    else:
        assert actual["temporal_mode"] == "current"
        assert actual["requested_evidence_type"] == "personal_record_status"
    results.append({"reproduction": label, "actual": actual})
print(json.dumps({"transport": "httpx.MockTransport", "network_connect": "blocked", "results": results}, indent=2))
