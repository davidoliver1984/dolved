import json
from copy import deepcopy
from types import SimpleNamespace
from uuid import uuid4

import pytest

from app import evaluation_capture as capture
from app.retrieval.needs_planner import NeedsValidationFailure
from tests.test_evidence_needs import EXAMPLES
from tests.test_needs_correction_feedback import invalid_plan, run_outputs

pytestmark = pytest.mark.usefixtures("accepted_interpretation")


def setup_capture(tmp_path, monkeypatch, enabled=True):
    monkeypatch.setattr(capture, "CAPTURE_ROOT", tmp_path)
    return SimpleNamespace(
        evaluation_capture_enabled=enabled, evaluation_capture_destination=str(tmp_path)
    )


def dependency(value):
    def get():
        return value

    return get


@pytest.mark.parametrize("kind", ["temporal_relation_grounding", "fact_date_precision"])
@pytest.mark.parametrize("corrected", [False, True])
def test_planner_capture_preserves_rejections_and_retry_without_changing_result(
    tmp_path, monkeypatch, kind, corrected
):
    settings = setup_capture(tmp_path, monkeypatch)
    bad = invalid_plan()
    if kind == "fact_date_precision":
        bad = deepcopy(EXAMPLES[0]["plan"])
        bad["needs"][0]["fact_reference"] = {
            "value": "2025",
            "precision": "day",
            "source": {"turn_id": "u1", "quote": "2025"},
        }
    outcomes, requests = [], []
    monkeypatch.setattr("app.retrieval.needs_planner.time.perf_counter", lambda: 1.0)
    for enabled in (False, True):
        settings.evaluation_capture_enabled = enabled
        planner, calls, turns = run_outputs(
            [bad, EXAMPLES[0]["plan"]] if corrected else [bad]
        )
        with capture.capture_scope(
            settings, capture.WORKSPACE, str(uuid4()), "planner"
        ):
            try:
                result = planner.plan_needs(turns, evaluated_at="2026-11-12T12:00:00Z")
                outcomes.append(result)
            except NeedsValidationFailure as error:
                outcomes.append((error.validation, error.validation_attempts))
        requests.append(calls)
    assert outcomes[0] == outcomes[1]
    assert requests[0] == requests[1]
    records = [json.loads(path.read_text()) for path in tmp_path.glob("*.json")]
    rejected = [r for r in records if r["status"] == "rejected"]
    assert len(records) == 2
    assert len(rejected) == (1 if corrected else 2)
    assert {r["validation"]["code"] for r in rejected} == {kind}
    assert (
        rejected[0]["proposed"]["needs"][0]["question_span"]
        == bad["needs"][0]["question_span"]
    )
    assert "aspect" not in rejected[0]["proposed"]["needs"][0]


@pytest.mark.parametrize(
    "mode", ["disabled", "foreign", "outside", "symlink", "no_scope"]
)
def test_capture_refuses_disabled_or_misscoped_destinations(
    tmp_path, monkeypatch, mode
):
    settings = setup_capture(tmp_path, monkeypatch, mode != "disabled")
    workspace = str(uuid4()) if mode == "foreign" else capture.WORKSPACE
    if mode == "outside":
        settings.evaluation_capture_destination = str(tmp_path.parent)
    if mode == "symlink":
        link = tmp_path / "link"
        link.symlink_to(tmp_path, target_is_directory=True)
        settings.evaluation_capture_destination = str(link)
    if mode != "no_scope":
        with capture.capture_scope(settings, workspace, str(uuid4()), "planner"):
            capture.planner_begin(1)
            capture.planner_proposal({"needs": []})
            capture.planner_end("rejected")
    capture.planner_end("rejected")
    assert list(tmp_path.glob("*.json")) == []


def test_generation_capture_is_allowlisted_bounded_redacted_and_no_logs(
    tmp_path, monkeypatch, caplog
):
    settings = setup_capture(tmp_path, monkeypatch)
    with capture.capture_scope(settings, capture.WORKSPACE, str(uuid4()), "generation"):
        capture.generation_rejected(
            1,
            {
                "answer_parts": [
                    {
                        "text": "Bearer secret password=hidden",
                        "evidence_ids": ["ev-1"],
                        "headers": "secret",
                    }
                ],
                "temporal_uses": [
                    {
                        "part_index": 0,
                        "need_id": "n1",
                        "target_id": "n1:requested",
                        "question_quote": "now",
                    }
                ],
                "credentials": "never",
            },
            {"field": "result", "code": "temporal_target_invalid"},
            ValueError("temporal answer-use coverage mismatch"),
        )
    record = json.loads(next(tmp_path.glob("*.json")).read_text())
    assert record["explanation"] == "temporal answer-use coverage mismatch"
    assert record["proposed"]["answer_parts"][0]["text"] == "[REDACTED] [REDACTED]"
    assert "credentials" not in record["proposed"]
    assert not caplog.records


def test_calendar_count_is_actual_not_inferred(tmp_path, monkeypatch):
    from tests.test_evidence_needs import adapter
    from tests.test_needs_calendar_notation import sample

    settings = setup_capture(tmp_path, monkeypatch)
    for value in ("November 2023", "2023-11"):
        plan, turns = sample(value, "month", "November 2023")
        with capture.capture_scope(
            settings, capture.WORKSPACE, str(uuid4()), "planner"
        ):
            adapter(plan, [], finished=True).plan_needs(
                turns, evaluated_at="2026-11-14T12:00:00Z"
            )
    assert sorted(
        json.loads(p.read_text())["calendar_notation_normalization_count"]
        for p in tmp_path.glob("*.json")
    ) == [0, 1]


@pytest.mark.parametrize("foreign", [False, True])
def test_authenticated_serving_routes_capture_real_adapter_rejections_only_for_workspace(
    tmp_path, monkeypatch, foreign
):
    import base64

    from fastapi.testclient import TestClient

    from app.generation.routes import needs_generator_dependency
    from app.main import app
    from app.retrieval.routes import needs_planner_dependency
    from app.settings import get_settings
    from tests.test_generation_contract import signed_request
    from tests.test_generation_needs_transport import AutoReviewResponses, prepared
    from tests.test_openai_generation_adapter import response
    from tests.test_temporal_associations import shared_output, shared_request

    setup_capture(tmp_path, monkeypatch)
    monkeypatch.setattr("app.retrieval.needs_planner.time.perf_counter", lambda: 1.0)
    secret = b"n" * 32
    monkeypatch.setenv(
        "RETRIEVAL_CALLER_HMAC_KEYS",
        json.dumps({"test": base64.b64encode(secret).decode()}),
    )
    monkeypatch.setenv("EVALUATION_CAPTURE_DESTINATION", str(tmp_path))
    workspace = str(uuid4()) if foreign else capture.WORKSPACE
    all_results = []
    try:
        for enabled in (False, True):
            monkeypatch.setenv("EVALUATION_CAPTURE_ENABLED", str(enabled).lower())
            get_settings.cache_clear()
            planner, calls, turns = run_outputs([invalid_plan()])
            app.dependency_overrides[needs_planner_dependency] = dependency(planner)
            path = "/api/internal/retrieval/plan-needs"
            incoming = {
                "contract_version": 2,
                "request_id": str(uuid4()),
                "workspace_id": workspace,
                "evaluated_at": "2026-11-12T12:00:00Z",
                "user_turns": [t.model_dump() for t in turns],
            }
            body, headers = signed_request(
                incoming, secret, path, "retrieval.plan-needs"
            )
            planned = TestClient(app).post(path, content=body, headers=headers)
            assert len(calls) == 2
            _, adapter, _ = prepared()
            adapter._monotonic = lambda: 1.0
            output = shared_output()
            output["temporal_uses"].pop()
            responses = AutoReviewResponses([response(output)])
            adapter._client = SimpleNamespace(responses=responses)
            app.dependency_overrides[needs_generator_dependency] = dependency(adapter)
            raw = shared_request()
            raw.update(workspace_id=workspace, request_id=str(uuid4()))
            path = "/api/internal/retrieval/generation/answer-needs"
            body, headers = signed_request(raw, secret, path, "generation.answer-needs")
            generated = TestClient(app).post(path, content=body, headers=headers)
            assert len(responses.calls) == 1  # No entailment call or diagnostic retry.
            result = generated.json()
            result.pop("request_id", None)
            all_results.append(
                (planned.status_code, planned.json(), generated.status_code, result)
            )
        assert all_results[0] == all_results[1]
        records = [json.loads(p.read_text()) for p in tmp_path.glob("*.json")]
        assert len(records) == (0 if foreign else 3)
        if not foreign:
            assert {r["stage"] for r in records} == {"planner", "generation"}
            generation = next(r for r in records if r["stage"] == "generation")
            assert generation["proposed"]["temporal_uses"][0]["need_id"] == "n1"
            assert generation["explanation"] == "temporal answer-use coverage mismatch"
    finally:
        app.dependency_overrides.clear()
        get_settings.cache_clear()
