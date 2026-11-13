"""Provider-free safety proof for the single approved hold-branch checkpoint."""
import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import block1_extractive_stage as stage
import run_r28_r06_governed as governed


class ExtractiveStageTest(unittest.TestCase):
    def authority(self):
        value = governed.json_file(governed.ROOT / "docs/evaluation/r28-s04/r06-governed-authorization-block1-references-20261112.json")
        value.update(stage=stage.STAGE, execution_commit="a" * 40, runtime_source_aggregate="b" * 64)
        return value

    def fake_git(self, *args):
        if args == ("rev-parse", "HEAD"):
            return "a" * 40
        if args == ("ls-remote", "origin", stage.REMOTE_REF):
            return f"{'a' * 40}\t{stage.REMOTE_REF}"
        if args == ("rev-parse", "origin/main"):
            return "c" * 40
        if args[:2] == ("diff", "--name-only"):
            return ""
        raise AssertionError(args)

    def test_exact_pushed_hold_sha_is_accepted(self):
        with patch.object(governed, "git", side_effect=self.fake_git), patch.object(governed, "runtime_aggregate", return_value="b" * 64):
            stage.verify_commit(governed, self.authority())

    def test_local_remote_and_runtime_mismatches_are_refused(self):
        for target in ("local", "remote", "runtime"):
            def changed(*args):
                if target == "local" and args == ("rev-parse", "HEAD"):
                    return "d" * 40
                if target == "remote" and args[0] == "ls-remote":
                    return f"{'d' * 40}\t{stage.REMOTE_REF}"
                return self.fake_git(*args)
            with self.subTest(target=target), patch.object(governed, "git", side_effect=changed), patch.object(governed, "runtime_aggregate", return_value=("d" if target == "runtime" else "b") * 64):
                with self.assertRaisesRegex(ValueError, "mismatch"):
                    stage.verify_commit(governed, self.authority())

    def test_existing_stage_still_requires_main(self):
        authority = self.authority()
        authority.update(stage="topical_diagnostics")
        with tempfile.TemporaryDirectory() as temp, patch.object(governed, "git", side_effect=self.fake_git):
            path = Path(temp) / "authorization.json"
            path.write_text(json.dumps(authority))
            with self.assertRaisesRegex(ValueError, "remote commit mismatch"):
                governed.verify_authorization(path, "topical_diagnostics", authority["manifest_id"], {})

    def test_second_authorization_use_refused_before_any_dispatch(self):
        authority = self.authority()
        with tempfile.TemporaryDirectory() as temp, patch.object(governed, "OUTPUT_ROOT", Path(temp)), patch.object(governed.subprocess, "run") as run:
            registry = Path(temp) / ".authorizations"
            registry.mkdir()
            (registry / f"{authority['authorization_id']}.json").write_text("{}")
            with self.assertRaisesRegex(ValueError, "already consumed"):
                governed.dispatch(stage.STAGE, authority, {"items": []}, {"items": []}, {}, {})
            run.assert_not_called()

    def test_fixed_inputs_exclude_development_cases_from_first_thirty(self):
        execution, scopes, _ = stage.inputs(governed)
        self.assertEqual(32, len(execution["items"]))
        self.assertEqual(["v4.case.corrected-b01-01", "v4.case.corrected-b03-05"], [i["case_id"] for i in execution["items"][-2:]])
        self.assertTrue(all(set(i) == {"case_id", "variant_id", "utterance"} for i in execution["items"]))
        self.assertEqual(30, len(set(i["case_id"] for i in execution["items"][:30])))
        self.assertEqual(32, len(scopes["items"]))


class ContinuationTest(ExtractiveStageTest):
    def test_exact_tail(self):
        full, _, _ = stage.inputs(governed)
        with patch.object(governed, "runtime_aggregate", return_value=stage.RUNTIME_SHA256):
            tail, scopes, identities = stage.inputs(governed, stage.TAIL_STAGE)
        self.assertEqual(full["items"][8:], tail["items"])
        self.assertEqual(24, len(scopes["items"]))
        self.assertEqual(governed.snapshot_digest(tail), identities["input_sha256"])

    def test_original_eight_and_dispatched_tail_refused(self):
        full, _, _ = stage.inputs(governed)
        ledger = [{**i, "event": "recorded"} for i in full["items"][:8]]
        for item in full["items"][:8]:
            with self.assertRaisesRegex(ValueError, "already present"):
                stage.validate_tail([item, *full["items"][9:]], full["items"], ledger)
        with self.assertRaisesRegex(ValueError, "already present"):
            stage.validate_tail(full["items"][8:], full["items"], [*ledger, full["items"][8]])

    def test_manifest_ledger_runtime_mismatch(self):
        real_sha = governed.sha256
        for name in ("selection-checksums.sha256", "execution-input.json", "dispatch.ndjson", "checksums.sha256"):
            with self.subTest(name=name), patch.object(governed, "sha256", side_effect=lambda p: "0" * 64 if p.name == name else real_sha(p)):
                with self.assertRaises(ValueError):
                    stage.inputs(governed, stage.TAIL_STAGE)
        with patch.object(governed, "runtime_aggregate", return_value="0" * 64):
            with self.assertRaisesRegex(ValueError, "runtime aggregate mismatch"):
                stage.inputs(governed, stage.TAIL_STAGE)

    def test_original_runtime_and_sha_pinned(self):
        authority = self.authority()
        authority["stage"] = stage.TAIL_STAGE
        with patch.object(governed, "git", side_effect=self.fake_git), patch.object(governed, "runtime_aggregate", return_value="b" * 64):
            with self.assertRaisesRegex(ValueError, "differs from original"):
                stage.verify_commit(governed, authority)
        authority["execution_commit"] = "d" * 40
        with patch.object(governed, "git", side_effect=self.fake_git):
            with self.assertRaisesRegex(ValueError, "local SHA mismatch"):
                stage.verify_commit(governed, authority)

    def test_tail_authorization_one_use(self):
        authority = self.authority()
        authority["stage"] = stage.TAIL_STAGE
        with tempfile.TemporaryDirectory() as temp, patch.object(governed, "OUTPUT_ROOT", Path(temp)), patch.object(governed.subprocess, "run") as run:
            registry = Path(temp) / ".authorizations"
            registry.mkdir()
            (registry / f"{authority['authorization_id']}.json").write_text("{}")
            with self.assertRaisesRegex(ValueError, "already consumed"):
                governed.dispatch(stage.TAIL_STAGE, authority, {"items": []}, {"items": []}, {}, {})
            run.assert_not_called()


class FinalContinuationTest(ExtractiveStageTest):
    def test_fixed_seventeen_and_unchanged_prior_stage(self):
        full, _, _ = stage.inputs(governed)
        final, scopes, ids = stage.inputs(governed, stage.FINAL_STAGE)
        self.assertEqual(full["items"][15:], final["items"])
        self.assertEqual(17, len(scopes["items"]))
        self.assertEqual(governed.snapshot_digest(final), ids["input_sha256"])
        self.assertEqual(24, len(stage.inputs(governed, stage.TAIL_STAGE)[0]["items"]))

    def test_any_prior_input_or_changed_selection_refused(self):
        full, _, _ = stage.inputs(governed)
        ledger = full["items"][:15]
        for item in ledger:
            with self.assertRaisesRegex(ValueError, "already dispatched"):
                stage.validate_final([item, *full["items"][16:]], full["items"], ledger)
        with self.assertRaisesRegex(ValueError, "requires frozen"):
            stage.validate_final(full["items"][16:], full["items"], ledger)

    def test_both_ledgers_and_manifest_refused_if_changed(self):
        real = governed.sha256
        for name in ("dispatch.ndjson", "checksums.sha256", "selection-checksums.sha256", "execution-input.json"):
            for parent in (stage.ORIGINAL_RUN, stage.TAIL_RUN, stage.DIRECTORY):
                with self.subTest(name=name, parent=parent), patch.object(governed, "sha256", side_effect=lambda p: "0" * 64 if p.name == name and str(p.parent).endswith(parent) else real(p)):
                    applicable = (parent == stage.DIRECTORY and name in {"selection-checksums.sha256", "execution-input.json"}) or (parent != stage.DIRECTORY and name in {"dispatch.ndjson", "checksums.sha256"})
                    if applicable:
                        with self.assertRaises(ValueError):
                            stage.inputs(governed, stage.FINAL_STAGE)

    def test_final_pin_and_authorization_reuse_refused(self):
        authority = self.authority()
        authority["stage"] = stage.FINAL_STAGE
        with patch.object(governed, "git", side_effect=self.fake_git), patch.object(governed, "runtime_aggregate", return_value="b" * 64):
            with self.assertRaisesRegex(ValueError, "differs from original"):
                stage.verify_commit(governed, authority)
        with tempfile.TemporaryDirectory() as temp, patch.object(governed, "OUTPUT_ROOT", Path(temp)), patch.object(governed.subprocess, "run") as run:
            registry = Path(temp) / ".authorizations"
            registry.mkdir()
            (registry / f"{authority['authorization_id']}.json").write_text("{}")
            with self.assertRaisesRegex(ValueError, "already consumed"):
                governed.dispatch(stage.FINAL_STAGE, authority, {"items": []}, {"items": []}, {}, {})
            run.assert_not_called()


if __name__ == "__main__":
    unittest.main()
