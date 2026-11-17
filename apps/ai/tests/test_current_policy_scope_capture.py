import json
from copy import deepcopy
from types import SimpleNamespace
from uuid import uuid4

import httpx
import pytest
from pydantic import SecretStr

from app import evaluation_capture as capture
from app.retrieval.current_policy import UserTurn, dispositions, parse_policy_plan
from app.retrieval.current_policy_planner import CurrentPolicyPlanner
from tests.test_current_policy_scope import FIXTURE


def settings(tmp_path, monkeypatch, enabled=True):
    monkeypatch.setattr(capture, "CAPTURE_ROOT", tmp_path)
    return SimpleNamespace(
        evaluation_capture_enabled=enabled, evaluation_capture_destination=str(tmp_path)
    )


def mutate(name):
    value = deepcopy(FIXTURE["plan"])
    n = value["needs"][0]
    if name == "missing":
        del n["requested_information"]
    elif name == "null":
        n["requested_information"] = None
    elif name == "malformed":
        n["current_spans"] = {"quote": "not a list"}
    elif name == "nonmixed":
        n["requested_information"] = "history"
    elif name == "partition_missing":
        n["current_spans"] = []
    elif name == "overlap":
        n["history_spans"] = deepcopy(n["current_spans"])
    elif name == "duplicate":
        n["current_spans"] *= 2
    elif name == "ungrounded":
        n["current_spans"][0]["quote"] = "invented clause"
    elif name == "authority":
        n.update(
            requested_information="current_policy", current_spans=[], history_spans=[]
        )
    elif name == "base":
        del n["authority"]
    elif name == "grounding":
        n["authority"]["source"]["quote"] = "invented date"
    return value


def run(value, recover):
    return parse_policy_plan(
        value,
        tuple(UserTurn(**t) for t in FIXTURE["turns"]),
        (),
        "2026-11-16T12:00:00Z",
        recover=recover,
    )


@pytest.mark.parametrize(
    "name,reason,state",
    [
        ("missing", "scope_field_schema", "absent"),
        ("null", "scope_annotation_missing", "null"),
        ("malformed", "scope_field_schema", "malformed"),
        ("nonmixed", "non_mixed_partition", "validly_parsed"),
        ("partition_missing", "mixed_partition_missing", "validly_parsed"),
        ("overlap", "partition_overlap", "validly_parsed"),
        ("duplicate", "partition_overlap", "validly_parsed"),
        ("ungrounded", "partition_not_exact_clause", "validly_parsed"),
        ("authority", "current_authority_incompatible", "validly_parsed"),
    ],
)
def test_scope_reasons_preserve_retry_and_recovery(
    tmp_path, monkeypatch, name, reason, state
):
    s = settings(tmp_path, monkeypatch)
    for attempt, recover in [(1, False), (2, True)]:
        with capture.capture_scope(s, capture.WORKSPACE, str(uuid4()), "planner"):
            capture.planner_begin(attempt)
            if recover:
                result = run(mutate(name), True)
                assert dispositions(result)[0]["reason"] == "invalid_scope"
                capture.planner_end("accepted")
            else:
                with pytest.raises(ValueError, match="invalid scope annotation"):
                    run(mutate(name), False)
                capture.planner_end("rejected")
    records = sorted(
        (json.loads(p.read_text()) for p in tmp_path.glob("*.json")),
        key=lambda r: r["attempt"],
    )
    for r in records:
        event = next(x for x in r["scope_diagnostics"] if x["reason"] == reason)
        assert event["need_id"] == "n1"
        key = "current_spans" if name == "malformed" else "requested_information"
        assert event["fields"][key]["state"] == state
        assert event["outcome"] == (
            "rejected" if r["attempt"] == 1 else "quarantined_direct"
        )


@pytest.mark.parametrize("corrected", [False, True])
def test_real_transport_capture_on_off_identical_latest_attempt(
    tmp_path, monkeypatch, corrected
):
    monkeypatch.setattr("app.retrieval.needs_planner.time.perf_counter", lambda: 1.0)
    monkeypatch.setattr(
        "app.retrieval.needs_planner.review_interpretation",
        lambda **k: {"code": "accepted", "input_tokens": 1, "output_tokens": 1},
    )
    results, requests = [], []
    for enabled in (False, True):
        calls = []
        outputs = [
            mutate("nonmixed"),
            deepcopy(FIXTURE["plan"]) if corrected else mutate("partition_missing"),
        ]

        def transport(req, calls=calls, outputs=outputs):
            calls.append(json.loads(req.content))
            return httpx.Response(
                200,
                json={
                    "choices": [
                        {
                            "finish_reason": "stop",
                            "message": {"content": json.dumps(outputs[len(calls) - 1])},
                        }
                    ],
                    "usage": {"prompt_tokens": 5, "completion_tokens": 5},
                },
            )

        planner = CurrentPolicyPlanner(
            api_url="https://invalid.test",
            api_key=SecretStr("fake"),
            provider_name="openai",
            model="test",
            timeout_seconds=1,
            client=httpx.Client(transport=httpx.MockTransport(transport)),
        )
        with capture.capture_scope(
            settings(tmp_path, monkeypatch, enabled),
            capture.WORKSPACE,
            str(uuid4()),
            "planner",
        ):
            results.append(
                planner.plan_needs(
                    tuple(UserTurn(**t) for t in FIXTURE["turns"]),
                    evaluated_at="2026-11-16T12:00:00Z",
                )
            )
        requests.append(calls)
    assert results[0] == results[1]
    assert requests[0] == requests[1] and len(requests[0]) == 2
    records = sorted(
        (json.loads(p.read_text()) for p in tmp_path.glob("*.json")),
        key=lambda r: r["attempt"],
    )
    assert records[0]["validation"]["code"] == "scope_annotation_invalid"
    assert any(
        x["reason"] == "non_mixed_partition" for x in records[0]["scope_diagnostics"]
    )
    assert any(
        x["reason"] == "mixed_partition_missing"
        for x in records[1]["scope_diagnostics"]
    ) == (not corrected)
    assert dispositions(results[1].plan)[0]["status"] == (
        "partial_scope" if corrected else "interpretation_failure"
    )


def test_dependency_and_independent_need_attribution(tmp_path, monkeypatch):
    v = mutate("ungrounded")
    turns = tuple(UserTurn(**t) for t in FIXTURE["turns"])
    for i in (2, 3):
        q = f"Where is equipment register {i}?"
        n = deepcopy(v["needs"][0])
        n.update(
            need_id=f"n{i}",
            question_span={"turn_id": f"u{i}", "quote": q},
            operation="retrieve",
            authority={
                "kind": "current",
                "value": None,
                "precision": "none",
                "source": None,
            },
            supports_need_id="n1" if i == 2 else None,
            requested_information="current_policy",
            current_spans=[],
            history_spans=[],
        )
        v["needs"].append(n)
        v["bindings"].append({**v["bindings"][0], "need_id": f"n{i}"})
        turns += (UserTurn(turn_id=f"u{i}", text=q),)
    with capture.capture_scope(
        settings(tmp_path, monkeypatch), capture.WORKSPACE, str(uuid4()), "planner"
    ):
        capture.planner_begin(2)
        p = parse_policy_plan(v, turns, (), "2026-11-16T12:00:00Z", recover=True)
        capture.planner_end("accepted")
    assert [x["status"] for x in dispositions(p)] == [
        "interpretation_failure",
        "interpretation_failure",
        "admitted",
    ]
    events = json.loads(next(tmp_path.glob("*.json")).read_text())["scope_diagnostics"]
    e = next(x for x in events if x["outcome"] == "quarantined_dependency")
    assert (e["need_id"], e["originating_need_id"], e["root_need_ids"]) == (
        "n2",
        "n1",
        ["n1"],
    )


@pytest.mark.parametrize(
    "name,stage", [("base", "base_schema"), ("grounding", "base_grounding")]
)
def test_whole_request_failure_not_recovery(tmp_path, monkeypatch, name, stage):
    with capture.capture_scope(
        settings(tmp_path, monkeypatch), capture.WORKSPACE, str(uuid4()), "planner"
    ):
        capture.planner_begin(2)
        with pytest.raises(ValueError):
            run(mutate(name), True)
        capture.planner_end("rejected")
    events = json.loads(next(tmp_path.glob("*.json")).read_text())["scope_diagnostics"]
    assert events[-1]["stage"] == stage and events[-1]["outcome"] == "failed"
    assert not any(x["outcome"].startswith("quarantined") for x in events)


@pytest.mark.parametrize("mode", ["disabled", "foreign", "io_failure", "bounded"])
def test_capture_boundaries_and_diagnostic_failure(tmp_path, monkeypatch, mode):
    s = settings(tmp_path, monkeypatch, mode != "disabled")
    workspace = str(uuid4()) if mode == "foreign" else capture.WORKSPACE
    with capture.capture_scope(s, workspace, str(uuid4()), "planner"):
        capture.planner_begin(1)
        if mode == "io_failure":
            monkeypatch.setattr(
                capture.os,
                "open",
                lambda *a, **k: (_ for _ in ()).throw(OSError("unavailable")),
            )
        if mode == "bounded":
            for _ in range(40):
                capture.scope_diagnostic(
                    "scope_fields",
                    "rejected",
                    {"need_id": "n1", "current_spans": [{"quote": "x" * 20000}] * 100},
                )
        result = run(deepcopy(FIXTURE["plan"]), False)
        capture.planner_end("accepted")
    assert dispositions(result)[0]["status"] == "partial_scope"
    files = list(tmp_path.glob("*.json"))
    if mode == "bounded":
        r = json.loads(files[0].read_text())
        assert r["scope_diagnostics_truncated"]
        assert files[0].stat().st_size <= 131073
    else:
        assert not files


def test_valid_separate_current_and_history_annotations(tmp_path, monkeypatch):
    v = deepcopy(FIXTURE["plan"])
    history = v["needs"][0]
    history.update(requested_information="history", current_spans=[], history_spans=[])
    current = deepcopy(history)
    current.update(
        need_id="n2",
        question_span={"turn_id": "u2", "quote": "Who issues protective clothing now?"},
        requested_information="current_policy",
        operation="retrieve",
        authority={
            "kind": "current",
            "value": None,
            "precision": "none",
            "source": None,
        },
    )
    v["needs"].append(current)
    v["bindings"].append({**v["bindings"][0], "need_id": "n2"})
    turns = tuple(UserTurn(**t) for t in FIXTURE["turns"]) + (
        UserTurn(turn_id="u2", text=current["question_span"]["quote"]),
    )
    with capture.capture_scope(
        settings(tmp_path, monkeypatch), capture.WORKSPACE, str(uuid4()), "planner"
    ):
        capture.planner_begin(1)
        p = parse_policy_plan(v, turns, (), "2026-11-16T12:00:00Z", recover=False)
        capture.planner_end("accepted")
    assert [x["status"] for x in dispositions(p)] == ["scope_declined", "admitted"]
    events = json.loads(next(tmp_path.glob("*.json")).read_text())["scope_diagnostics"]
    assert [
        e["need_id"]
        for e in events
        if e["stage"] == "scope_annotation" and e["outcome"] == "accepted"
    ] == ["n1", "n2"]
