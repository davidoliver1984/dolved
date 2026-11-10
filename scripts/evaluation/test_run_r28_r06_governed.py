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
            ("location_shadow_trial_tail12", 12),
            ("location_shadow_grounding_retrial", 5),
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
            if stage == "location_shadow_trial_tail12":
                self.assertEqual(governed.SHADOW_TAIL12_INDICES, (125, 129, 130, 69, 89, 141, 142, 101, 102, 29, 23, 147))
                self.assertEqual(
                    [item["utterance"] for item in execution["items"]],
                    [governed.shadow_baseline(index, item)["utterance"]
                     for index, item in zip(governed.SHADOW_TAIL12_INDICES, execution["items"], strict=True)],
                )
            if stage == "location_shadow_grounding_retrial":
                self.assertEqual(governed.SHADOW_GROUNDING_RETRIAL_INDICES, (141, 142, 45, 125, 129))
                self.assertEqual(
                    [item["utterance"] for item in execution["items"]],
                    [governed.shadow_baseline(index, item)["utterance"]
                     for index, item in zip(governed.SHADOW_GROUNDING_RETRIAL_INDICES, execution["items"], strict=True)],
                )
            self.assertEqual(
                "578e324c7b2b5d8694fa9af76b37773582a5f6676da5af9153ac34d8f7a429d3",
                identities["runtime_source_aggregate"],
            )

    def test_shadow_controlled_calendar_clarification_with_empty_trace_is_valid(self) -> None:
        item = {
            "case_id": "v4.case.corrected-b06-01",
            "variant_id": "v2",
            "utterance": "Compared with the December 2025 schedule, which staff group is listed first from June 2026 and are the supervision and appraisal frequencies the same?",
        }
        scope = {"scope": "primary"}
        rejection = {
            "request_count": 1,
            "status": "rejected",
            "validation": {"code": "calendar_grounding", "field": "root", "stage": "grounding"},
        }
        record = {
            **item,
            "frozen_scope": "primary",
            "needs_opt_in": [governed.PRIMARY_WORKSPACE],
            "needs_pipeline_exercised": True,
            "displayed_text": "Which date, month or period do you mean for this question?",
            "shadow_reviews": [],
            "observation": {
                "status": "clarification_required",
                "production_trace": [],
                "usage": {"planner_failure": {"attempt_count": 2, "validation_attempts": [rejection, rejection]}},
            },
        }
        self.assertTrue(governed.valid_shadow_record(record, item, scope, governed.PRIMARY_WORKSPACE))
        record["observation"]["production_trace"] = {"needs_plan": {"decision": "plan"}}
        self.assertTrue(governed.valid_shadow_record(record, item, scope, governed.PRIMARY_WORKSPACE))
        record["observation"]["production_trace"] = None
        self.assertFalse(governed.valid_shadow_record(record, item, scope, governed.PRIMARY_WORKSPACE))

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
