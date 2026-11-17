import json
import os
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

from control import digest, execute, validate_manifest


class ControlTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.p = Path(self.tmp.name)
        q = {
            "schema_version": 1,
            "items": [
                {"id": f"D{i}", "utterance": f"Generic policy question {i}?"}
                for i in range(1, 7)
            ],
        }
        inv = {
            "schema_version": 1,
            "items": [
                {"id": f"D{i}-R{r}", "question_id": f"D{i}"}
                for r in range(1, 4)
                for i in range(1, 7)
            ],
        }
        (self.p / "q").write_text(json.dumps(q))
        (self.p / "i").write_text(json.dumps(inv))
        self.b = {
            "questions": str(self.p / "q"),
            "invocations": str(self.p / "i"),
            "authorization": str(self.p / "auth"),
            "binding_sha256": "frozen",
            "destination": str(self.p / "run"),
            "per_invocation_usd": "1.032768",
            "ceiling_usd": "25",
            "maximum_invocation_seconds": 990,
            "calendar_deadline_epoch": 99999,
            "duration_seconds": 3600,
            "profile": {"server": "pin"},
        }
        (self.p / "auth").write_text(
            json.dumps({"approved": True, "binding_sha256": "frozen"})
        )
        self.env = patch.dict(
            os.environ, {"CURRENT_POLICY_AUTHORIZATION_SHA256": digest(self.p / "auth")}
        )
        self.env.start()
        self.addCleanup(self.env.stop)

    def test_exact_order_fresh_identity_and_no_history(self):
        calls = []
        execute(
            self.b,
            lambda: None,
            lambda req, remaining, item: (
                calls.append((req, item)) or {"status": "accepted"}
            ),
            now=lambda: 100,
        )
        self.assertEqual(
            [x[1] for x in calls],
            [f"D{i}-R{r}" for r in range(1, 4) for i in range(1, 7)],
        )
        self.assertEqual(len({x[0]["invocation_id"] for x in calls}), 18)
        self.assertTrue(
            all(
                set(x[0]) == {"invocation_id", "utterance", "expected_profile"}
                for x in calls
            )
        )
        with self.assertRaises(ValueError):
            execute(
                self.b,
                lambda: None,
                lambda *a: self.fail("redispatch"),
                now=lambda: 100,
            )

    def test_prior_output_and_consumed_refused(self):
        for name in ["run", "auth.consumed"]:
            p = self.p / name
            p.mkdir()
            with self.assertRaises(ValueError):
                execute(
                    self.b,
                    lambda: None,
                    lambda *a: self.fail("dispatch"),
                    now=lambda: 100,
                )
            p.rmdir()

    def test_budget_refuses_before_consumption(self):
        self.b["ceiling_usd"] = "1"
        with self.assertRaises(ValueError):
            execute(
                self.b, lambda: None, lambda *a: self.fail("dispatch"), now=lambda: 100
            )
        self.assertFalse((self.p / "auth.consumed").exists())

    def test_drift_and_uncertain_dispatch_preserve_ledger(self):
        calls = []

        def dispatch(req, remaining, item):
            self.assertTrue((self.p / "run/ledger.json").exists())
            calls.append(item)
            raise TimeoutError("controlled uncertain state")

        with self.assertRaises(TimeoutError):
            execute(self.b, lambda: None, dispatch, now=lambda: 100)
        self.assertEqual(calls, ["D1-R1"])
        self.assertEqual(
            json.loads((self.p / "run/ledger.json").read_text())[0]["liability_usd"],
            "1.032768",
        )
        self.assertTrue((self.p / "run/halt.json").exists())

    def test_typed_rejection_continues_systemic_halts(self):
        calls = []

        def dispatch(req, remaining, item):
            calls.append(item)
            return {
                "status": "planner_rejected",
                "category": "invalid_grounded_needs_plan",
                "provider_status": 200,
                "systemic": len(calls) == 2,
                "validation_attempts": [{"status": "rejected"}],
            }

        with self.assertRaises(ValueError):
            execute(self.b, lambda: None, dispatch, now=lambda: 100)
        self.assertEqual(calls, ["D1-R1", "D2-R1"])

    def test_insufficient_window_no_dispatch(self):
        self.b["calendar_deadline_epoch"] = 1089
        with self.assertRaises(ValueError):
            execute(
                self.b, lambda: None, lambda *a: self.fail("dispatch"), now=lambda: 100
            )

    def test_drift_between_slots_halts(self):
        ticks = []
        calls = []

        def verify():
            ticks.append(1)
            if len(ticks) == 3:
                raise ValueError("drift")

        with self.assertRaises(ValueError):
            execute(
                self.b,
                verify,
                lambda req, r, item: calls.append(item) or {"status": "accepted"},
                now=lambda: 100,
            )
        self.assertEqual(calls, ["D1-R1"])

    def test_reordered_selection_and_changed_authority_refused(self):
        inv = json.loads((self.p / "i").read_text())
        inv["items"].reverse()
        (self.p / "i").write_text(json.dumps(inv))
        with self.assertRaises(AssertionError):
            execute(
                self.b, lambda: None, lambda *a: self.fail("dispatch"), now=lambda: 100
            )
        inv["items"].reverse()
        (self.p / "i").write_text(json.dumps(inv))
        (self.p / "auth").write_text("{}")
        with self.assertRaises(ValueError):
            execute(
                self.b, lambda: None, lambda *a: self.fail("dispatch"), now=lambda: 100
            )


if __name__ == "__main__":
    unittest.main()
