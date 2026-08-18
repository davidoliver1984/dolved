from __future__ import annotations

import json
from uuid import uuid4

import httpx
import pytest
from pydantic import SecretStr, ValidationError

from app.conversation.models import (
    ContextTurn,
    ContextualisationRequest,
    ContextualisationResult,
)
from app.conversation.openai_adapter import OpenAIQueryContextualizer


def _request(*, history: tuple[ContextTurn, ...] = ()) -> ContextualisationRequest:
    return ContextualisationRequest(
        request_id=uuid4(),
        workspace_id=uuid4(),
        current_message="What about that procedure?",
        history=history,
        context_policy_version="bounded-turn-pairs-v1",
    )


def _adapter(transport: httpx.BaseTransport) -> OpenAIQueryContextualizer:
    return OpenAIQueryContextualizer(
        api_url="https://provider.example/v1/chat/completions",
        api_key=SecretStr("test-only-secret"),
        model="gpt-5-mini",
        contextualiser_version="conversation-context-v1",
        timeout_seconds=1,
        max_attempts=2,
        client=httpx.Client(transport=transport),
    )


def test_no_history_is_deterministic_and_does_not_call_provider() -> None:
    calls = 0

    def handler(request: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        return httpx.Response(500)

    result = _adapter(httpx.MockTransport(handler)).contextualize(_request())

    assert result.status == "resolved"
    assert result.resolved_query == "What about that procedure?"
    assert result.used_prior_context is False
    assert result.usage == {"execution": "deterministic", "request_count": 0}
    assert calls == 0


def test_history_is_rendered_to_strict_structured_provider_request() -> None:
    captured: dict[str, object] = {}

    def handler(request: httpx.Request) -> httpx.Response:
        captured.update(json.loads(request.content))
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "message": {
                            "content": json.dumps(
                                {
                                    "status": "resolved",
                                    "resolved_query": "What is the current medication procedure?",
                                    "used_prior_context": True,
                                    "interpretation_metadata": {
                                        "used_turn_ordinals": [1]
                                    },
                                    "clarification_question": None,
                                }
                            )
                        }
                    }
                ],
                "usage": {"prompt_tokens": 21, "completion_tokens": 9},
            },
        )

    result = _adapter(httpx.MockTransport(handler)).contextualize(
        _request(
            history=(
                ContextTurn(
                    user_message="Which medication procedure applies?",
                    assistant_message="Use the current approved procedure.",
                    assistant_kind="grounded_answer",
                    user_ordinal=1,
                    assistant_ordinal=2,
                ),
            )
        )
    )

    assert result.resolved_query == "What is the current medication procedure?"
    assert result.used_prior_context is True
    assert result.usage is not None
    assert result.usage["request_count"] == 1
    response_format = captured["response_format"]
    assert isinstance(response_format, dict)
    assert response_format["json_schema"]["strict"] is True  # type: ignore[index]


@pytest.mark.parametrize(
    "payload",
    [
        {
            "status": "resolved",
            "resolved_query": None,
            "used_prior_context": False,
            "clarification_question": None,
            "contextualiser_version": "conversation-context-v1",
        },
        {
            "status": "clarification_required",
            "resolved_query": "Invented repair",
            "used_prior_context": True,
            "clarification_question": "Which one?",
            "contextualiser_version": "conversation-context-v1",
        },
    ],
)
def test_contextualisation_result_cross_field_contract_fails_closed(
    payload: dict[str, object],
) -> None:
    with pytest.raises(ValidationError):
        ContextualisationResult.model_validate(payload)


def test_history_must_be_unique_and_ordered() -> None:
    duplicate = ContextTurn(
        user_message="First",
        assistant_message="Answer",
        assistant_kind="grounded_answer",
        user_ordinal=1,
        assistant_ordinal=2,
    )
    with pytest.raises(ValidationError):
        _request(history=(duplicate, duplicate))


def test_interpretation_metadata_rejects_unbounded_provider_fields() -> None:
    with pytest.raises(ValidationError):
        ContextualisationResult.model_validate(
            {
                "status": "resolved",
                "resolved_query": "Resolved question",
                "used_prior_context": True,
                "interpretation_metadata": {
                    "used_turn_ordinals": [1],
                    "provider_reasoning": "must never be persisted",
                },
                "clarification_question": None,
                "contextualiser_version": "conversation-context-v1",
            }
        )
