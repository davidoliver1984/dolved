import copy
import json
from pathlib import Path
from uuid import UUID

import pytest

from app import evaluation_capture as capture
from app.generation.current_policy import CurrentRequest, Output, generate
from tests.test_current_policy_generation import output, request, settings
from tests.test_current_policy_receipts import transport_client


def invoke(value, req=None, conf=None):
    client, calls = transport_client(json.dumps(value))
    with client:
        result = generate(req or request(), conf or settings(), client=client)
    assert len(calls) == 1
    assert result["usage"]["request_count"] == 1
    assert result["usage"]["retry_count"] == 0
    return result


@pytest.mark.parametrize(
    "ids", [["ev-01"], ["ev-01", "ev-01"], ["ev-02", "ev-01", "ev-02", "ev-01"]]
)
def test_exact_ids_first_occurrence_per_part_only(ids):
    req = request()
    data = req.model_dump(mode="json")
    second = copy.deepcopy(data["sources"][0])
    second.update(evidence_id="ev-02", chunk_id="11111111-1111-4111-8111-111111111111")
    data["sources"].append(second)
    value = output()
    value["answer_parts"][0]["evidence_ids"] = ids
    value["answer_parts"].append(
        {"text": "A separate part.", "evidence_ids": ["ev-01"]}
    )
    original = copy.deepcopy(value)
    result = invoke(value, CurrentRequest.model_validate(data))
    expected = copy.deepcopy(value)
    expected["answer_parts"][0]["evidence_ids"] = list(dict.fromkeys(ids))
    assert result["result"] == expected and value == original


@pytest.mark.parametrize(
    "ids",
    [
        ["ev-01", "ev-01", "ev-99"],
        ["ev-99", "ev-99"],
        [],
        [None],
        ["ev-01", 1],
        "ev-01",
        ["EV-01"],
        [" ev-01"],
        ["ev-01"] * 16,
    ],
)
def test_invalid_ids_and_original_size_limit_are_not_repaired(ids):
    value = output()
    value["answer_parts"][0]["evidence_ids"] = ids
    assert invoke(value)["status"] == "failed"


@pytest.mark.parametrize("bad", ["empty", "disclosure", "structure"])
def test_duplicates_do_not_rescue_unrelated_invalid_output(bad):
    value = output()
    value["answer_parts"][0]["evidence_ids"] = ["ev-01", "ev-01"]
    if bad == "empty":
        value["answer_parts"][0]["text"] = " "
    elif bad == "disclosure":
        value["unsupported_aspects"] = [" "]
    else:
        value["answer_parts"][0]["extra"] = "invalid"
    assert invoke(value)["status"] == "failed"


@pytest.mark.parametrize("scope", ["enabled", "disabled", "foreign", "io_failure"])
def test_count_raw_and_canonical_survive_sidecar_without_affecting_outcome(
    tmp_path, monkeypatch, scope
):
    monkeypatch.setattr(capture, "CAPTURE_ROOT", tmp_path)
    monkeypatch.setattr("app.generation.current_policy.time.monotonic", lambda: 1.0)
    req = request()
    if scope != "foreign":
        req = req.model_copy(update={"workspace_id": UUID(capture.WORKSPACE)})
    conf = settings().model_copy(
        update={
            "evaluation_capture_enabled": scope != "disabled",
            "evaluation_capture_destination": str(tmp_path),
        }
    )
    if scope == "io_failure":
        monkeypatch.setattr(
            capture.os, "open", lambda *a, **k: (_ for _ in ()).throw(OSError())
        )
    value = output()
    value["answer_parts"][0]["evidence_ids"] = ["ev-01", "ev-01"]
    result = invoke(value, req, conf)
    assert result == invoke(value, req, settings())
    rows = [json.loads(p.read_text()) for p in tmp_path.glob("*.json")]
    assert len(rows) == (1 if scope == "enabled" else 0)
    if rows:
        row = rows[0]
        assert row["reason"] == "exact_duplicate_evidence_ids"
        assert row["citation_normalisation"] == [{"part_index": 0, "removed_count": 1}]
        assert json.loads(row["raw_model_output"]) == value
        assert json.loads(row["canonical_output"]) == result["result"]
        assert not row["raw_output_truncated"] and not row["canonical_output_truncated"]
    assert "citation_normalisation" not in result


def s6_receipt():
    fixture = json.loads(
        (Path(__file__).parent / "fixtures/current-policy-smoke03-s6.json").read_text()
    )
    value = json.loads(fixture["raw"])
    req = CurrentRequest.model_validate(fixture["request"])
    with pytest.raises(ValueError, match="invalid citation membership"):
        Output.model_validate(value).validate_membership(req)
    usage = fixture["retained_usage"]
    client, calls = transport_client(
        fixture["raw"],
        fixture["completion_status"],
        token_counts={
            "input_tokens": usage["input_tokens"],
            "output_tokens": usage["output_tokens"],
            "total_tokens": usage["input_tokens"] + usage["output_tokens"],
            "input_tokens_details": {"cached_tokens": 0},
            "output_tokens_details": {"reasoning_tokens": 0},
        },
    )
    with client:
        result = generate(req, settings(), client=client)
    assert len(calls) == 1 and result["status"] == "completed"
    expected = copy.deepcopy(value)
    assert expected["answer_parts"][1]["evidence_ids"] == ["ev-01", "ev-02", "ev-02"]
    expected["answer_parts"][1]["evidence_ids"] = ["ev-01", "ev-02"]
    assert result["result"] == expected
    assert result["usage"]["cost_usd"] == usage["cost_usd"]
    # Provider response token-details are synthetic; replay keeps its measured
    # adapter receipt, not a claim that the retained SDK envelope was complete.
    result.pop("request_id")
    result["usage"].pop("latency_ms")
    return result


def test_s6_real_adapter_replay_matches_php_receipt():
    result = s6_receipt()
    expected = json.loads(
        Path(
            "/contracts/http/current-policy-answer/v1/smoke03-s6-canonical-receipt.json"
        ).read_text()
    )
    assert result == expected
