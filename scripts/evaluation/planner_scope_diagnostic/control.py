"""Bounded 18-slot diagnostic using existing exclusive marker/reservation helpers.

No execution authority is supplied by this module. Production callbacks live in
commands.py; tests substitute dispatch, never plans in the production path.
"""

import json
import sys
import time
from decimal import Decimal
from pathlib import Path
from uuid import uuid4

sys.path.insert(
    0, str(Path(__file__).resolve().parents[1] / "current_policy_confirmation")
)
from runner import digest, durable, reserve


def validate_manifest(questions, invocations):
    assert list(questions) == ["schema_version", "items"]
    assert [x["id"] for x in questions["items"]] == [f"D{i}" for i in range(1, 7)]
    assert all(set(x) == {"id", "utterance"} for x in questions["items"])
    expected = [
        {"id": f"D{i}-R{r}", "question_id": f"D{i}"}
        for r in range(1, 4)
        for i in range(1, 7)
    ]
    assert invocations["items"] == expected
    return {x["id"]: x["utterance"] for x in questions["items"]}


def safe_outcome(result):
    if result.get("status") == "accepted":
        return True
    return (
        result.get("status") == "planner_rejected"
        and result.get("category") == "invalid_grounded_needs_plan"
        and result.get("systemic") is False
        and result.get("provider_status") == 200
        and bool(result.get("validation_attempts"))
    )


def execute(b, verify, dispatch, now=time.time):
    verify()
    questions = json.loads(Path(b["questions"]).read_text())
    invocations = json.loads(Path(b["invocations"]).read_text())
    texts = validate_manifest(questions, invocations)
    authority = Path(b["authorization"])
    auth = json.loads(authority.read_text())
    import os

    if (
        digest(authority) != os.environ.get("CURRENT_POLICY_AUTHORIZATION_SHA256")
        or auth.get("binding_sha256") != b["binding_sha256"]
        or auth.get("approved") is not True
    ):
        raise ValueError("authority mismatch")
    destination = Path(b["destination"])
    if destination.exists() or Path(str(authority) + ".consumed").exists():
        raise ValueError("prior operational state")
    if Decimal(b["per_invocation_usd"]) * 18 > Decimal(b["ceiling_usd"]):
        raise ValueError("total budget refusal")
    if now() + b["maximum_invocation_seconds"] > b["calendar_deadline_epoch"]:
        raise ValueError("calendar deadline refusal")
    Path(str(authority) + ".consumed").mkdir()
    destination.mkdir()
    durable(
        destination / "started.json",
        {"time": now(), "binding_sha256": b["binding_sha256"], "authorization_sha256": digest(authority)},
        True,
    )
    ledger = []
    deadline = None
    for item in invocations["items"]:
        try:
            verify()
            current = now()
            if deadline is None:
                deadline = min(
                    b["calendar_deadline_epoch"], current + b["duration_seconds"]
                )
                durable(
                    destination / "clock.json",
                    {"first_dispatch_epoch": current, "deadline_epoch": deadline},
                    True,
                )
            if current + b["maximum_invocation_seconds"] > deadline:
                raise ValueError("insufficient remaining execution window")
            reserve(
                ledger,
                item["id"],
                Decimal(b["per_invocation_usd"]),
                Decimal(b["ceiling_usd"]),
            )
            durable(destination / "ledger.json", ledger)
            request = {
                "invocation_id": str(uuid4()),
                "utterance": texts[item["question_id"]],
                "expected_profile": b["profile"],
            }
            durable(
                destination / (item["id"] + "-start.json"),
                {"time": current, "input": request},
                True,
            )
            result = dispatch(request, deadline - current, item["id"])
            durable(destination / (item["id"] + ".json"), result, True)
            if not safe_outcome(result):
                raise ValueError("typed outcome requires halt")
            ledger[-1].update(
                state="recorded",
                accounting="full reservation retained; nested receipts not added",
            )
            durable(destination / "ledger.json", ledger)
        except Exception as error:
            if ledger:
                ledger[-1]["state"] = "halt_no_redispatch"
                durable(destination / "ledger.json", ledger)
            durable(
                destination / "halt.json",
                {
                    "item": item["id"],
                    "exception_class": type(error).__name__,
                    "time": now(),
                },
                True,
            )
            raise
    durable(destination / "completed.json", {"items": 18, "time": now()}, True)
