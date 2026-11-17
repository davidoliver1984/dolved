import copy
import unittest
from unittest.mock import Mock

from mount_guard import API_BIND_EQUIVALENCES, verify_api_mounts


class MountGuardTests(unittest.TestCase):
    def fixture(self, prefixed=False):
        rules = {**API_BIND_EQUIVALENCES, "/r28-corpus": {"source": "/private/tmp/frozen-corpus", "read_only": True}}
        volumes = [{"type": "bind", "target": k, **v} for k, v in rules.items()]
        api = {"Mounts": [{"Type": "bind", "Destination": m["target"], "Source": ("/host_mnt" if prefixed else "") + m["source"], "RW": not m["read_only"]} for m in volumes]}
        return {"services": {"api": {"volumes": volumes}}}, api, {"api_bind_equivalences": copy.deepcopy(API_BIND_EQUIVALENCES), "corpus_bind_equivalence": rules["/r28-corpus"]}

    def test_exact_and_explicit_equivalences_require_content_verification(self):
        for prefixed in (False, True):
            config, api, bound = self.fixture(prefixed)
            check = Mock()
            verify_api_mounts(config, api, bound, check)
            check.assert_called_once_with()

    def test_wrong_worktree_destination_mode_type_and_unlisted_equivalence_fail(self):
        for mode in ("worktree", "destination", "access", "configured_access", "type", "double_prefix", "unlisted", "bound_rule", "extra_mount"):
            with self.subTest(mode=mode):
                config, api, bound = self.fixture(True)
                if mode == "worktree":
                    api["Mounts"][0]["Source"] = "/host_mnt/Users/davidoliver/Desktop/RAG/apps/api"
                elif mode == "destination":
                    api["Mounts"][0]["Destination"] = "/other"
                elif mode == "access":
                    api["Mounts"][1]["RW"] = True
                elif mode == "configured_access":
                    config["services"]["api"]["volumes"][0]["read_only"] = True
                    api["Mounts"][0]["RW"] = False
                elif mode == "type":
                    api["Mounts"][0]["Type"] = "volume"
                elif mode == "double_prefix":
                    api["Mounts"][0]["Source"] = "/host_mnt" + api["Mounts"][0]["Source"]
                elif mode == "bound_rule":
                    bound["api_bind_equivalences"]["/app"]["source"] = "/other"
                elif mode == "extra_mount":
                    api["Mounts"].append({"Destination": "/extra"})
                else:
                    config["services"]["api"]["volumes"].append({"type": "bind", "target": "/unlisted", "source": "/somewhere", "read_only": True})
                    api["Mounts"].append({"Type": "bind", "Destination": "/unlisted", "Source": "/host_mnt/somewhere", "RW": False})
                with self.assertRaises((AssertionError, KeyError)):
                    verify_api_mounts(config, api, bound, Mock())

    def test_content_mismatch_fails_despite_allowed_spelling(self):
        config, api, bound = self.fixture(True)
        check = Mock(side_effect=ValueError("mounted runtime manifest mismatch"))
        with self.assertRaisesRegex(ValueError, "manifest mismatch"):
            verify_api_mounts(config, api, bound, check)
        check.assert_called_once_with()


if __name__ == "__main__":
    unittest.main()
