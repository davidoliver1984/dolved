import json

import httpx
import pytest
from pydantic import SecretStr

from app.retrieval.planner import RetrievalPlanningError, StructuredChatRetrievalPlanner


def planner(response_plan: dict[str, object]) -> StructuredChatRetrievalPlanner:
    def handler(request: httpx.Request) -> httpx.Response:
        request_payload = json.loads(request.content)
        assert request_payload["response_format"]["type"] == "json_schema"
        return httpx.Response(
            200,
            json={"choices": [{"message": {"content": json.dumps(response_plan)}}]},
        )

    return StructuredChatRetrievalPlanner(
        api_url="https://planner.invalid/v1/chat/completions",
        api_key=SecretStr("test-key"),
        provider_name="test-provider",
        model="test-model",
        timeout_seconds=1,
        client=httpx.Client(transport=httpx.MockTransport(handler)),
    )


def test_structured_planner_validates_typed_output_and_preserves_query() -> None:
    question = "What was valid on 1 January 2026?"
    plan = planner(
        {
            "retrieval_queries": [question],
            "temporal_mode": "valid_at_date",
            "valid_at": "2026-01-01T00:00:00Z",
        }
    ).plan(question, evaluated_at="2026-08-07T12:00:00Z")

    assert plan.valid_at is not None
    assert plan.retrieval_queries == (question,)


def test_structured_planner_rejects_malformed_or_changed_query_output() -> None:
    with pytest.raises(RetrievalPlanningError, match="no usable plan"):
        planner({"temporal_mode": "valid_at_date"}).plan(
            "Question", evaluated_at="2026-08-07T12:00:00Z"
        )

    with pytest.raises(RetrievalPlanningError, match="altered"):
        planner({"retrieval_queries": ["Changed"], "temporal_mode": "current"}).plan(
            "Original", evaluated_at="2026-08-07T12:00:00Z"
        )
