"""Provider-free tests for the R06 one-use production-path gate."""

from __future__ import annotations

import copy
import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import run_r28_r06_governed as governed


class R28R06GovernedTest(unittest.TestCase):
    def setUp(self) -> None:
        self.authorization = (
            governed.ROOT
            / "docs/evaluation/r28-s04/r06-governed-authorization-regression.json"
        )

    def test_all_inputs_are_frozen_question_only(self) -> None:
        for stage, count in (
            ("regression", 8),
            ("confirmation", 16),
            ("continuation", 2),
            ("confirmation_retry", 1),
            ("confirmation_remaining", 12),
            ("case6_retry", 1),
            ("case5_retry", 1),
            ("generic_controls", 3),
        ):
            execution, scopes, identities = governed.verify_inputs(stage)
            self.assertEqual(count, len(execution["items"]))
            self.assertEqual(count, len(scopes["items"]))
            if stage == "continuation":
                self.assertEqual(
                    [(item["case_id"], item["variant_id"]) for item in scopes["items"]],
                    [
                        ("v4.case.corrected-b01-06", "v1"),
                        ("v4.case.corrected-b03-08", "v1"),
                    ],
                )
            if stage == "confirmation_retry":
                self.assertEqual([item["case_id"] for item in scopes["items"]], ["v4.case.corrected-b01-11"])
            if stage == "confirmation_remaining":
                self.assertEqual(scopes["items"], governed.json_file(governed.SELECTION)["confirmation"]["items"][4:])
            if stage in {"case6_retry", "case5_retry", "generic_controls"}:
                start, stop = {"case6_retry": (5, 6), "case5_retry": (4, 5), "generic_controls": (0, 3)}[stage]
                self.assertEqual(scopes["items"], governed.json_file(governed.SELECTION)["confirmation"]["items"][start:stop])
            self.assertEqual(
                "f91da9483634aaf4e5d66e32c2a70c2088093f321cfcf1a8244b3210b81771b4",
                identities["runtime_source_aggregate"],
            )

    def test_exact_authorization_passes_and_mutation_fails(self) -> None:
        execution, _, identities = governed.verify_inputs("regression")
        accepted = governed.json_file(self.authorization)
        accepted.update(identities)
        accepted["execution_commit"] = governed.git("rev-parse", "HEAD")
        original_git = governed.git

        def clean_runtime_git(*args, **kwargs):
            if args[:2] == ("diff", "--name-only"):
                return ""
            if args == ("rev-parse", "origin/main"):
                return original_git("rev-parse", "HEAD")
            return original_git(*args, **kwargs)

        with patch.object(governed, "git", side_effect=clean_runtime_git):
            with tempfile.TemporaryDirectory() as temp:
                exact_path = Path(temp) / "exact-authorization.json"
                exact_path.write_text(json.dumps(accepted))
                accepted = governed.verify_authorization(
                    exact_path, "regression", execution["subset_id"], identities
                )
            self.assertTrue(accepted["authorised"])
            for changed in (
                "input_sha256",
                "runtime_source_aggregate",
                "dispatcher_sha256",
            ):
                with self.subTest(changed=changed), tempfile.TemporaryDirectory() as temp:
                    mutated = copy.deepcopy(accepted)
                    mutated[changed] = "0" * 64
                    path = Path(temp) / "authorization.json"
                    path.write_text(json.dumps(mutated))
                    with self.assertRaisesRegex(ValueError, "mismatch"):
                        governed.verify_authorization(
                            path, "regression", execution["subset_id"], identities
                        )

    def test_one_use_marker_rejects_second_claim(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "authorization-consumed.json"
            governed.write_exclusive(path, {"authorization_id": "first"})
            with self.assertRaises(FileExistsError):
                governed.write_exclusive(path, {"authorization_id": "second"})

    def test_controlled_planner_clarification_is_a_valid_record(self) -> None:
        item = {"case_id": "case", "variant_id": "v1", "utterance": "question"}
        scope = {"scope": "security_test"}
        record = {
            **item,
            "frozen_scope": "security_test",
            "needs_opt_in": [governed.INJECTION_WORKSPACE],
            "needs_pipeline_exercised": True,
            "displayed_text": "Which date, month or period do you mean?",
            "observation": {
                "status": "clarification_required",
                "retrieval": {
                    "clarification_source": "planner",
                    "clarification_reason": "invalid_fact_date_reference",
                },
            },
        }
        self.assertTrue(
            governed.valid_record(record, item, scope, governed.INJECTION_WORKSPACE)
        )
        record["needs_pipeline_exercised"] = False
        self.assertFalse(
            governed.valid_record(record, item, scope, governed.INJECTION_WORKSPACE)
        )
        record["needs_pipeline_exercised"] = True
        record["observation"]["status"] = "failed"
        self.assertFalse(
            governed.valid_record(record, item, scope, governed.INJECTION_WORKSPACE)
        )


if __name__ == "__main__":
    unittest.main()
