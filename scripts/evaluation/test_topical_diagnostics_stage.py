"""Provider-free fixed-selection proof for the diagnostic-only retry."""
import unittest
import run_r28_r06_governed as governed


class TopicalDiagnosticsStageTest(unittest.TestCase):
    def test_exact_frozen_eight(self):
        execution, scopes, _ = governed.verify_inputs("topical_diagnostics")
        expected = [("b01-09", "v1"), ("b01-09", "v2"),
                    ("b06-02", "v2"), ("b06-04", "v2"),
                    ("b03-10", "v1"), ("b04-09", "v1"),
                    ("b05-05", "v1"), ("b06-06", "v2")]
        self.assertEqual([(f"v4.case.corrected-{case}", variant) for case, variant in expected],
                         [(item["case_id"], item["variant_id"]) for item in execution["items"]])
        self.assertEqual({"primary"}, {item["scope"] for item in scopes["items"]})
        self.assertTrue(all(set(item) == {"case_id", "variant_id", "utterance"}
                            for item in execution["items"]))


if __name__ == "__main__":
    unittest.main()
