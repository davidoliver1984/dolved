import json
import subprocess
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import commands


class CaptureAdapterTests(unittest.TestCase):
    def test_capture_attribution_and_missing_native_attempts(self):
        for mode in ["valid", "missing", "foreign", "truncated"]:
            with self.subTest(mode=mode), tempfile.TemporaryDirectory() as temp:
                p = Path(temp)
                cap = p / "capture"
                cap.mkdir()
                out = p / "out"
                out.mkdir()
                src = p / "scripts/evaluation/planner_scope_diagnostic"
                src.mkdir(parents=True)
                (src / "invoke.php").write_text("<?php // controlled")
                b = {
                    "destination": str(out),
                    "capture": str(cap),
                    "worktree": str(p),
                    "maximum_invocation_seconds": 990,
                    "context": {"workspace": "authorised"},
                }
                req = {"utterance": "Synthetic question?"}
                result = {
                    "status": "accepted",
                    "requests": [
                        {
                            "request_id": "id",
                            "signed": True,
                            "purpose": "retrieval.plan-needs",
                            "user_turns": [
                                {"turn_id": "user-1", "text": req["utterance"]}
                            ],
                        }
                    ],
                    "usage": {"validation_attempts": [{}, {}]},
                }

                def fake(*a, **k):
                    for n in [1] if mode == "missing" else [1, 2]:
                        (cap / f"{n}.json").write_text(
                            json.dumps(
                                {
                                    "request_id": "id",
                                    "workspace_id": "foreign"
                                    if mode == "foreign"
                                    else "authorised",
                                    "stage": "planner",
                                    "scope_diagnostics": [
                                        {
                                            "stage": "scope_normalisation",
                                            "outcome": "normalised",
                                            "reason": "redundant_matching_partition",
                                            "need_id": "n1",
                                            "field": "current_spans",
                                            "cleared_span_count": 2,
                                        }
                                    ],
                                    "scope_diagnostics_truncated": mode == "truncated",
                                    "attempt": n,
                                    "status": "accepted" if n == 2 else "rejected",
                                }
                            )
                        )
                    return subprocess.CompletedProcess([], 0, json.dumps(result), "")

                with patch.object(commands.subprocess, "run", fake):
                    if mode == "valid":
                        exported = commands.dispatch(b, req, 3600, "test")
                        self.assertEqual(
                            len(exported["captures"]), 2
                        )
                        for record in exported["captures"]:
                            stored = Path(record["path"])
                            self.assertEqual(commands.digest(stored), record["sha256"])
                            event = json.loads(stored.read_text())["scope_diagnostics"][0]
                            self.assertEqual(event["cleared_span_count"], 2)
                            self.assertEqual(event["reason"], "redundant_matching_partition")
                    else:
                        with self.assertRaises(AssertionError):
                            commands.dispatch(b, req, 3600, "test")
                self.assertTrue((out / "test-transport.json").exists())


if __name__ == "__main__":
    unittest.main()
