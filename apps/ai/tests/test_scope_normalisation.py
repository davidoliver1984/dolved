"""Canonicalisation changes representation only, before the existing scope checks."""

import json
from copy import deepcopy
from pathlib import Path
from uuid import uuid4

import httpx
import pytest
from pydantic import SecretStr

from app import evaluation_capture as capture
from app.retrieval.current_policy import dispositions, parse_policy_plan
from app.retrieval.current_policy_planner import CurrentPolicyPlanner
from app.retrieval.needs import UserTurn
from tests.test_current_policy_scope import FIXTURE
from tests.test_current_policy_scope_capture import settings


def sample(kind="current_policy", text="Who signs the inspection register today?"):
    v = deepcopy(FIXTURE["plan"])
    n = v["needs"][0]
    n.update(
        question_span={"turn_id": "u1", "quote": text},
        context_spans=[],
        operation="retrieve",
        authority={
            "kind": "current",
            "value": None,
            "precision": "none",
            "source": None,
        },
        requested_information=kind,
        current_spans=[],
        history_spans=[],
    )
    return v, (UserTurn(turn_id="u1", text=text),)


def parse(v, turns, recover=False):
    return parse_policy_plan(v, turns, (), "2026-11-17T12:00:00Z", recover=recover)


@pytest.mark.parametrize("kind", ["current_policy", "history"])
@pytest.mark.parametrize(
    "quote", [None, "inspection register", "Who signs the inspection register today?"]
)
def test_only_matching_list_changes(kind, quote, tmp_path, monkeypatch):
    v, turns = sample(kind)
    field = "current_spans" if kind == "current_policy" else "history_spans"
    if quote:
        v["needs"][0][field] = [{"turn_id": "u1", "quote": quote}]
    original = deepcopy(v)
    with capture.capture_scope(
        settings(tmp_path, monkeypatch), capture.WORKSPACE, str(uuid4()), "planner"
    ):
        capture.planner_begin(1)
        capture.planner_proposal(v)
        p = parse(v, turns)
        capture.planner_end("accepted")
    assert v == original
    expected = deepcopy(original)
    expected["needs"][0][field] = []
    expected["needs"][0]["scope_error"] = None
    assert p.model_dump(mode="json") == expected
    record = json.loads(next(tmp_path.glob("*.json")).read_text())
    events = [e for e in record["scope_diagnostics"] if e["outcome"] == "normalised"]
    assert len(events) == bool(quote)
    if quote:
        assert events[0]["cleared_span_count"] == 1
        assert events[0]["reason"] == "redundant_matching_partition"
        assert events[0]["fields"][field]["value"] == original["needs"][0][field]
        # Accepted attempts deliberately omit the full proposal; the bounded
        # normalisation event retains the original scope annotation separately.
        assert record["proposed"] is None
        assert (
            events[0]["fields"]["question_span"]["value"]
            == original["needs"][0]["question_span"]
        )
    assert not record["scope_diagnostics_truncated"]


@pytest.mark.parametrize(
    "mode",
    [
        "opposite",
        "history_opposite",
        "unclear",
        "wrong_turn",
        "elsewhere",
        "repeated",
        "overlapping_occurrences",
        "duplicate",
        "overlap",
        "malformed",
        "empty",
        "extra_field",
        "too_many",
        "too_large",
    ],
)
def test_unsafe_annotations_retain_rejection_and_recovery(mode):
    v, turns = sample()
    n = v["needs"][0]
    span = {"turn_id": "u1", "quote": "inspection register"}
    n["current_spans"] = [span]
    if mode == "opposite":
        n["history_spans"] = [span]
    elif mode == "history_opposite":
        n["requested_information"] = "history"
    elif mode == "unclear":
        n["requested_information"] = "unclear"
    elif mode == "wrong_turn":
        span["turn_id"] = "u2"
        turns += (UserTurn(turn_id="u2", text=span["quote"]),)
    elif mode == "elsewhere":
        span["quote"] = "Separate maintenance question"
        turns = (
            UserTurn(
                turn_id="u1", text=turns[0].text + " Separate maintenance question"
            ),
        )
    elif mode in ("repeated", "overlapping_occurrences"):
        text = (
            "inspection register and inspection register"
            if mode == "repeated"
            else "aaaa"
        )
        n["question_span"]["quote"] = text
        turns = (UserTurn(turn_id="u1", text=text),)
        span["quote"] = "inspection register" if mode == "repeated" else "aaa"
    elif mode == "duplicate":
        n["current_spans"] *= 2
    elif mode == "overlap":
        n["current_spans"].append({"turn_id": "u1", "quote": "register today"})
    elif mode == "malformed":
        n["current_spans"] = {"quote": "inspection register"}
    elif mode == "empty":
        span["quote"] = ""
    elif mode == "extra_field":
        span["offset"] = 0
    elif mode == "too_many":
        n["current_spans"] *= 5
    else:
        # Schema's question-span bound is itself a preserved earlier rejection.
        span["quote"] = "x" * 8001
    original = deepcopy(v)
    with pytest.raises(ValueError):
        parse(v, turns)
    assert v == original
    p = parse(v, turns, True)
    assert p.needs[0].scope_error == "invalid_scope"


def test_normalisation_does_not_bypass_current_authority():
    v = deepcopy(FIXTURE["plan"])
    n = v["needs"][0]
    n.update(requested_information="current_policy", history_spans=[])
    turns = tuple(UserTurn(**t) for t in FIXTURE["turns"])
    with pytest.raises(ValueError):
        parse(v, turns)
    assert parse(v, turns, True).needs[0].scope_error == "invalid_scope"


def test_independent_normalised_need_survives_cross_need_grounding_and_dependency():
    v, turns = sample()
    first = v["needs"][0]
    first["current_spans"] = [
        {"turn_id": "u2", "quote": "Where are spare filters stored?"}
    ]
    for i in (2, 3):
        n = deepcopy(first)
        q = (
            "Where are spare filters stored?"
            if i == 2
            else "Who authorises filter replacement?"
        )
        n.update(
            need_id=f"n{i}",
            question_span={"turn_id": f"u{i}", "quote": q},
            supports_need_id="n1" if i == 3 else None,
        )
        n["current_spans"] = [deepcopy(n["question_span"])]
        v["needs"].append(n)
        v["bindings"].append({**v["bindings"][0], "need_id": f"n{i}"})
        turns += (UserTurn(turn_id=f"u{i}", text=q),)
    with pytest.raises(ValueError):
        parse(v, turns)
    p = parse(v, turns, True)
    assert [n.scope_error for n in p.needs] == [
        "invalid_scope",
        None,
        "dependent_scope",
    ]
    assert [d["status"] for d in dispositions(p)] == [
        "interpretation_failure",
        "admitted",
        "interpretation_failure",
    ]


@pytest.mark.parametrize("mode", ["disabled", "foreign", "enabled"])
def test_normalisation_capture_scope_and_multiple_spans(tmp_path, monkeypatch, mode):
    v, turns = sample()
    v["needs"][0]["current_spans"] = [
        {"turn_id": "u1", "quote": q} for q in ("Who signs", "inspection register")
    ]
    with capture.capture_scope(
        settings(tmp_path, monkeypatch, mode != "disabled"),
        capture.WORKSPACE if mode != "foreign" else str(uuid4()),
        str(uuid4()),
        "planner",
    ):
        capture.planner_begin(1)
        p = parse(v, turns)
        capture.planner_end("accepted")
    assert p.needs[0].current_spans == ()
    files = list(tmp_path.glob("*.json"))
    assert bool(files) == (mode == "enabled")
    if files:
        events = json.loads(files[0].read_text())["scope_diagnostics"]
        assert (
            next(e for e in events if e["outcome"] == "normalised")[
                "cleared_span_count"
            ]
            == 2
        )


def produce(v, turns, monkeypatch):
    calls = []
    monkeypatch.setattr("app.retrieval.needs_planner.time.perf_counter", lambda: 1.0)
    monkeypatch.setattr(
        "app.retrieval.needs_planner.review_interpretation",
        lambda **k: {"code": "accepted", "input_tokens": 1, "output_tokens": 1},
    )

    def transport(req):
        calls.append(json.loads(req.content))
        return httpx.Response(
            200,
            json={
                "choices": [
                    {"finish_reason": "stop", "message": {"content": json.dumps(v)}}
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
    result = planner.plan_needs(turns, evaluated_at="2026-11-17T12:00:00Z")
    assert len(calls) == 1
    assert len(result.usage.validation_attempts) == 1
    return {
        "plan": result.plan.model_dump(mode="json"),
        "classifier_lineage": result.lineage.model_dump(mode="json"),
        "usage": result.usage.model_dump(mode="json"),
        "turns": [t.model_dump(mode="json") for t in turns],
    }


def produced_cases(monkeypatch):
    rows = {}
    for kind in ("current_policy", "history"):
        v, turns = sample(kind)
        field = "current_spans" if kind == "current_policy" else "history_spans"
        v["needs"][0][field] = [deepcopy(v["needs"][0]["question_span"])]
        rows[kind] = produce(v, turns, monkeypatch)
    v, turns = sample("history", "Who signed the register previously?")
    v["needs"][0]["history_spans"] = [deepcopy(v["needs"][0]["question_span"])]
    current, current_turns = sample()
    n = current["needs"][0]
    n["need_id"] = "n2"
    n["question_span"]["turn_id"] = "u2"
    n["current_spans"] = [deepcopy(n["question_span"])]
    v["needs"].append(n)
    v["bindings"].append({**v["bindings"][0], "need_id": "n2"})
    turns += (UserTurn(turn_id="u2", text=current_turns[0].text),)
    rows["separate"] = produce(v, turns, monkeypatch)
    rows["mixed"] = produce(
        deepcopy(FIXTURE["plan"]),
        tuple(UserTurn(**t) for t in FIXTURE["turns"]),
        monkeypatch,
    )
    return rows


def test_real_adapter_cross_service_fixture_and_first_attempt(monkeypatch):
    rows = produced_cases(monkeypatch)
    path = Path("/contracts/http/current-policy-needs/v1/normalised-producer.json")
    assert rows == json.loads(path.read_text())
    assert [
        x["status"]
        for x in dispositions(
            parse_policy_plan(
                rows["separate"]["plan"]
                | {
                    "needs": [
                        {k: v for k, v in n.items() if k != "scope_error"}
                        for n in rows["separate"]["plan"]["needs"]
                    ]
                },
                tuple(UserTurn(**t) for t in rows["separate"]["turns"]),
                (),
                "2026-11-17T12:00:00Z",
                recover=False,
            )
        )
    ] == ["scope_declined", "admitted"]
