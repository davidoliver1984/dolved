"""Real SDK transport → adapter receipts shared with the PHP completion tests."""

import json
from pathlib import Path
from uuid import UUID

import httpx
import openai
import pytest

from app import evaluation_capture as capture
from app.generation.current_policy import generate
from tests.test_current_policy_generation import output, request, settings


def transport_client(raw, status="completed", usage=True):
    calls = []

    def respond(incoming):
        calls.append(json.loads(incoming.content))
        return httpx.Response(
            200,
            json={
                "id": "resp_controlled",
                "object": "response",
                "created_at": 1,
                "model": "gpt-5-mini",
                "status": status,
                "error": None,
                "incomplete_details": {"reason": "max_output_tokens"}
                if status == "incomplete"
                else None,
                "output": [
                    {
                        "id": "msg_controlled",
                        "type": "message",
                        "role": "assistant",
                        "status": "completed",
                        "content": [
                            {"type": "output_text", "text": raw, "annotations": []}
                        ],
                    }
                ],
                "usage": {
                    "input_tokens": 100,
                    "output_tokens": 30,
                    "total_tokens": 130,
                    "input_tokens_details": {"cached_tokens": 10},
                    "output_tokens_details": {"reasoning_tokens": 5},
                }
                if usage
                else None,
            },
        )

    return openai.OpenAI(
        api_key="test-only",
        max_retries=0,
        http_client=httpx.Client(transport=httpx.MockTransport(respond)),
    ), calls


def produced_receipts():
    rows = {}
    for mode in ["supported", "limitations", "invalid_citation", "empty", "no_support"]:
        value = output()
        value["answer_parts"][0]["text"] = "Wear gloves."
        if mode == "limitations":
            value["unsupported_aspects"] = ["The checker role is not established."]
        elif mode == "invalid_citation":
            value["answer_parts"][0]["evidence_ids"] = ["ev-99"]
        elif mode == "empty":
            value["answer_parts"] = []
        elif mode == "no_support":
            value["answer_parts"] = []
            value["unsupported_aspects"] = ["The checker role is not established."]
        client, calls = transport_client(json.dumps(value))
        with client:
            row = generate(request(), settings(), client=client)
        assert len(calls) == 1
        # Correlation/time vary per request, not the produced usage/result contract.
        row.pop("request_id")
        row["usage"].pop("latency_ms")
        rows[mode] = row
    return rows


def test_real_adapter_receipts_match_cross_service_fixture():
    fixture = Path(
        "/contracts/http/current-policy-answer/v1/controlled-adapter-receipts.json"
    )
    assert produced_receipts() == json.loads(fixture.read_text())


@pytest.mark.parametrize(
    "mode,code",
    [
        ("malformed", "malformed_output"),
        ("citation", "invalid_citation_membership"),
        ("empty", "empty_content"),
        ("gap", "invalid_disclosure"),
        ("structure", "structural_validation"),
        ("incomplete", "incomplete_output"),
    ],
)
@pytest.mark.parametrize("scope", ["enabled", "disabled", "foreign"])
def test_rejection_capture_is_scoped_and_does_not_change_decisions(
    tmp_path, monkeypatch, mode, code, scope
):
    monkeypatch.setattr(capture, "CAPTURE_ROOT", tmp_path)
    monkeypatch.setattr("app.generation.current_policy.time.monotonic", lambda: 1.0)
    value = output()
    if mode == "citation":
        value["answer_parts"][0]["evidence_ids"] = ["ev-99"]
    if mode == "empty":
        value["answer_parts"][0]["text"] = " "
    if mode == "gap":
        value["unsupported_aspects"] = [" "]
    if mode == "structure":
        value["outcome"] = "unknown"
    raw = '{"secret":"sk-fakeSecret", bad' if mode == "malformed" else json.dumps(value)
    r = request()
    if scope != "foreign":
        r = r.model_copy(update={"workspace_id": UUID(capture.WORKSPACE)})
    outcomes = []
    for enabled in [False, scope != "disabled"]:
        conf = settings().model_copy(
            update={
                "evaluation_capture_enabled": enabled,
                "evaluation_capture_destination": str(tmp_path),
            }
        )
        client, calls = transport_client(
            raw, "incomplete" if mode == "incomplete" else "completed"
        )
        with client:
            outcomes.append(generate(r, conf, client=client))
        assert len(calls) == 1
    assert outcomes[0] == outcomes[1]
    assert outcomes[0]["status"] == "failed"
    assert "raw_model_output" not in json.dumps(outcomes)
    rows = [json.loads(f.read_text()) for f in tmp_path.glob("*.json")]
    assert len(rows) == (1 if scope == "enabled" else 0)
    if rows:
        assert rows[0]["validation"]["code"] == code
        assert rows[0]["attempt"] == 1
        assert rows[0]["completion"]["status"] in ["completed", "incomplete"]
        assert "sk-fakeSecret" not in json.dumps(rows)
        assert rows[0]["raw_model_output"]


def test_label_is_diagnostic_disclosures_remain_and_usage_unavailable():
    value = output()
    value["unsupported_aspects"] = ["Frequency is unconfirmed."]
    client, _ = transport_client(json.dumps(value), usage=False)
    with client:
        row = generate(request(), settings(), client=client)
    assert row["status"] == "completed"
    assert row["result"] == value
    assert row["usage"]["cost_basis"] == "unavailable"
    assert row["usage"]["pricing_snapshot"] is None
    assert row["usage"]["cost_usd"] is None


@pytest.mark.parametrize("character", ["x", "🧭"])
def test_raw_output_is_bounded_and_capture_io_failure_is_nonfatal(
    tmp_path, monkeypatch, character
):
    monkeypatch.setattr(capture, "CAPTURE_ROOT", tmp_path)
    conf = settings().model_copy(
        update={
            "evaluation_capture_enabled": True,
            "evaluation_capture_destination": str(tmp_path),
        }
    )
    r = request().model_copy(update={"workspace_id": UUID(capture.WORKSPACE)})
    raw = character * 20000
    client, _ = transport_client(raw)
    with client:
        assert generate(r, conf, client=client)["status"] == "failed"
    row = json.loads(next(tmp_path.glob("*.json")).read_text())
    assert len(row["raw_model_output"].encode("utf-8")) == 16384
    assert len(next(tmp_path.glob("*.json")).read_bytes()) <= 131072
    assert row["raw_output_truncated"] is True
    monkeypatch.setattr(
        capture.os, "open", lambda *a, **k: (_ for _ in ()).throw(OSError())
    )
    client, _ = transport_client(raw)
    with client:
        assert generate(r, conf, client=client)["status"] == "failed"
