"""Provider-free PHP dispatcher regression; no application bootstrap or live call."""
import copy
import json
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


class InterpretationObservationTest(unittest.TestCase):
    def test_completed_interpretation_failure_and_negative_controls(self):
        record = json.loads((ROOT / "docs/evaluation/r28-s04/r06-governed-evidence/R28-R07-BLOCK1-CHECKPOINT-20261112-01/cases/03-v4.case.corrected-b01-04-v1.json").read_text())
        original = record["observation"]
        controls = [("actual persisted empty-trace interpretation failure", original, True)]
        for key, value in [("provider_status", 503), ("category", "transport_error"), ("systemic", True)]:
            observation = copy.deepcopy(original)
            observation["usage"]["planner_failure"][key] = value
            controls.append((key, observation, False))
        observation = copy.deepcopy(original)
        observation["retrieval"]["outcome"] = "insufficient_evidence"
        controls.append(("unrelated no-answer", observation, False))
        for name, observation, expected in controls:
            with self.subTest(name=name):
                result = subprocess.run(["docker", "compose", "run", "--rm", "--no-deps", "-v", f"{ROOT}:/repo", "api", "php", "/repo/scripts/evaluation/r28_r06_production_case.php", "--test-interpretation-observation"],
                    cwd=ROOT, input=json.dumps(observation), text=True, capture_output=True, check=True)
                self.assertIs(json.loads(result.stdout), expected)


if __name__ == "__main__":
    unittest.main()
