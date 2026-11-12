"""Provider-free tests for the R06 one-use production-path gate."""

from __future__ import annotations

import copy
import json
import tempfile
import unittest
from collections import Counter
from pathlib import Path
from unittest.mock import patch

import run_r28_r06_governed as governed


class R28R06GovernedTest(unittest.TestCase):
    def test_relative_checkpoint_preserves_known_questions_and_membership(self) -> None:
        execution, scopes, _ = governed.verify_inputs("block1_relative")
        original, _, _ = governed.verify_inputs("block1_closing")
        self.assertEqual(6, len(execution["items"]))
        self.assertEqual(original["items"][5], execution["items"][0])
        self.assertEqual(original["items"][7], execution["items"][1])
        self.assertEqual(original["items"][13], execution["items"][5])
        self.assertEqual(["security_test"] + ["primary"] * 5, [s["scope"] for s in scopes["items"]])
        self.assertTrue(all(set(i) == {"case_id", "variant_id", "utterance"} for i in execution["items"]))

    def test_closing_tail_is_only_frozen_inputs_two_through_fourteen(self) -> None:
        full, full_scopes, _ = governed.verify_inputs("block1_closing")
        tail, scopes, identities = governed.verify_inputs("block1_closing_tail")
        self.assertEqual(full["items"][1:], tail["items"])
        self.assertEqual(full_scopes["items"][1:], scopes["items"])
        self.assertEqual(13, len(tail["items"]))
        self.assertEqual(identities["selection_sha256"], governed.snapshot_digest(tail))
        self.assertEqual("security_test", scopes["items"][4]["scope"])

    def test_closing_checkpoint_is_fixed_and_preserves_security_scope(self) -> None:
        execution, scopes, identities = governed.verify_inputs("block1_closing")
        self.assertEqual(14, len(execution["items"]))
        self.assertEqual("507fff88a4e029c11d890efd426a17da8aa5530f5813fa13c942f6096324cbe4", identities["input_sha256"])
        self.assertEqual(["primary"] * 5 + ["security_test"] + ["primary"] * 8, [x["scope"] for x in scopes["items"]])
        self.assertTrue(all(set(x) == {"case_id", "variant_id", "utterance"} for x in execution["items"]))
        probes = governed.json_file(governed.ROOT / "docs/evaluation/r28-s04/block1-closing-20261112/omission-probes.json")
        self.assertEqual(4, len(probes["items"]))
        self.assertTrue(all(set(x) == {"probe_id", "user_turns", "plan"} for x in probes["items"]))

    def test_feedback_checkpoint_freezes_four_inputs_and_original_security_scope(self) -> None:
        execution, scopes, identities = governed.verify_inputs("block1_feedback")
        self.assertEqual(4, len(execution["items"]))
        self.assertEqual("49047353fca0c8831a199510d584b1ebd233eb65dd6a9c5315eed590026566e7", identities["input_sha256"])
        self.assertEqual(["primary", "security_test", "primary", "primary"], [i["scope"] for i in scopes["items"]])
        population = governed.json_file(governed.POPULATION)
        case = next(c for c in population["cases"] if c["case_id"] == "v4.case.corrected-b01-06")
        self.assertEqual(case["scope"], scopes["items"][1]["scope"])
        self.assertEqual(case["variants"][0]["utterance"], execution["items"][1]["utterance"])
        self.assertTrue(all(set(i) == {"case_id", "variant_id", "utterance"} for i in execution["items"]))

    def test_block1_continuation_contains_only_the_four_never_dispatched_inputs(self) -> None:
        full, _, _ = governed.verify_inputs("block1_checkpoint")
        tail, _, _ = governed.verify_inputs("block1_remaining")
        self.assertEqual(full["items"][3:7], tail["items"])
        self.assertEqual(4, len(tail["items"]))

    def test_block1_checkpoint_is_fixed_and_question_only(self) -> None:
        execution, scopes, identities = governed.verify_inputs("block1_checkpoint")
        self.assertEqual(7, len(execution["items"]))
        self.assertEqual("e8841dba95a86828fe0f825a5e9b5b58c7cc3d5f925e0829bca6584ee41988c8", identities["input_sha256"])
        self.assertTrue(all(set(i) == {"case_id", "variant_id", "utterance"} for i in execution["items"]))
        self.assertEqual(["primary"] * 7, [i["scope"] for i in scopes["items"]])

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
            ("confirmation_tail", 10),
            ("case8_retry", 1),
            ("confirmation_after_case8", 8),
            ("confirmation_cases10_16", 7),
            ("case14_16_retry", 2),
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
            if stage == "confirmation_tail":
                self.assertEqual(scopes["items"], governed.json_file(governed.SELECTION)["confirmation"]["items"][6:])
            if stage == "case8_retry":
                self.assertEqual(scopes["items"], governed.json_file(governed.SELECTION)["confirmation"]["items"][7:8])
            if stage == "confirmation_after_case8":
                self.assertEqual(scopes["items"], governed.json_file(governed.SELECTION)["confirmation"]["items"][8:])
            if stage == "confirmation_cases10_16":
                self.assertEqual(scopes["items"], governed.json_file(governed.SELECTION)["confirmation"]["items"][9:])
            if stage == "case14_16_retry":
                selected = governed.json_file(governed.SELECTION)["confirmation"]["items"]
                self.assertEqual(scopes["items"], [selected[13], selected[15]])
            if stage in {"case6_retry", "case5_retry", "generic_controls"}:
                start, stop = {"case6_retry": (5, 6), "case5_retry": (4, 5), "generic_controls": (0, 3)}[stage]
                self.assertEqual(scopes["items"], governed.json_file(governed.SELECTION)["confirmation"]["items"][start:stop])
            self.assertEqual(
                governed.runtime_aggregate(),
                identities["runtime_source_aggregate"],
            )

    def test_capacity_checkpoint_is_fixed_question_only_and_primary_scoped(self) -> None:
        execution, scopes, identities = governed.verify_inputs("capacity_checkpoint")
        self.assertEqual(5, len(execution["items"]))
        self.assertEqual({"primary"}, {item["scope"] for item in scopes["items"]})
        self.assertEqual(governed.runtime_aggregate(), identities["runtime_source_aggregate"])
        for item in execution["items"]:
            self.assertEqual({"case_id", "variant_id", "utterance"}, set(item))
        with patch.object(governed, "sha256", return_value="0" * 64):
            with self.assertRaisesRegex(ValueError, "freeze mismatch"):
                governed.verify_inputs("capacity_checkpoint")

    def test_current_runtime_fingerprint_is_derived_not_a_historical_constant(self) -> None:
        with patch.object(governed, "runtime_aggregate", return_value="a" * 64):
            _, _, identities = governed.verify_inputs("regression")
        self.assertEqual("a" * 64, identities["runtime_source_aggregate"])

    def test_capacity_overflow_selects_only_frozen_fifth_input(self) -> None:
        full, _, full_identity = governed.verify_inputs("capacity_checkpoint")
        execution, scopes, identity = governed.verify_inputs("capacity_overflow")
        self.assertEqual(full["items"][4:5], execution["items"])
        self.assertEqual("capacity.overflow", execution["items"][0]["case_id"])
        self.assertEqual(["primary"], [item["scope"] for item in scopes["items"]])
        self.assertEqual(full_identity["input_sha256"], identity["input_sha256"])

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

    def test_full_review_gate_can_acknowledge_valid_record_without_stdin(self) -> None:
        with patch("builtins.input", side_effect=AssertionError("stdin must not be used")):
            self.assertEqual("continue 8", governed.review_gate_decision(8, 148, True))
        item = {"case_id": "case", "variant_id": "v1", "utterance": "question"}
        scope = {"scope": "primary"}
        record = {**item, "frozen_scope": "primary",
                  "needs_opt_in": [governed.PRIMARY_WORKSPACE],
                  "needs_pipeline_exercised": True,
                  "displayed_text": "A supported answer.",
                  "observation": {"status": "completed"}}
        self.assertTrue(governed.valid_record(record, item, scope, governed.PRIMARY_WORKSPACE))
        record["observation"]["status"] = "failed"
        self.assertFalse(governed.valid_record(record, item, scope, governed.PRIMARY_WORKSPACE))

    def test_interactive_review_gate_still_stops_on_eof(self) -> None:
        with patch("builtins.input", side_effect=EOFError):
            self.assertEqual("stop", governed.review_gate_decision(8, 148, False))

    def test_full_r07_selection_is_exact_frozen_population(self) -> None:
        execution, scopes, identities = governed.verify_inputs("full")
        self.assertEqual("R28-R07-FULL-148-V2", execution["subset_id"])
        self.assertEqual(148, len(execution["items"]))
        self.assertEqual(148, len(scopes["items"]))
        self.assertEqual(
            {"primary": 124, "foreign_tenant": 12, "security_test": 12},
            dict(Counter(item["scope"] for item in scopes["items"])),
        )
        self.assertEqual(64, len(identities["runtime_source_aggregate"]))

    def test_r07_retry_and_tail_partition_only_unfinished_work(self) -> None:
        full, full_scopes, _ = governed.verify_inputs("full")
        retry, retry_scopes, _ = governed.verify_inputs("r07_case40_retry")
        tail, tail_scopes, _ = governed.verify_inputs("r07_tail")
        self.assertEqual(full["items"][39:40], retry["items"])
        self.assertEqual(full_scopes["items"][39:40], retry_scopes["items"])
        self.assertEqual(full["items"][40:], tail["items"])
        self.assertEqual(full_scopes["items"][40:], tail_scopes["items"])
        self.assertEqual(1, len(retry["items"]))
        self.assertEqual(108, len(tail["items"]))

    def test_case32_diagnostic_selects_only_the_frozen_v2_utterance(self) -> None:
        full, full_scopes, _ = governed.verify_inputs("full")
        diagnostic, diagnostic_scopes, _ = governed.verify_inputs("r07_case32_diagnostic")
        self.assertEqual(full["items"][31:32], diagnostic["items"])
        self.assertEqual(full_scopes["items"][31:32], diagnostic_scopes["items"])
        self.assertEqual("v2", diagnostic["items"][0]["variant_id"])

    def test_r07_second_tail_excludes_all_44_attempted_utterances(self) -> None:
        full, full_scopes, _ = governed.verify_inputs("full")
        remaining, remaining_scopes, _ = governed.verify_inputs("r07_tail_after_44")
        self.assertEqual(full["items"][44:], remaining["items"])
        self.assertEqual(full_scopes["items"][44:], remaining_scopes["items"])
        self.assertEqual(104, len(remaining["items"]))

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
