"""Mechanical schema snapshots; no provider calls or evaluation inputs."""
import json
from pathlib import Path

from app.generation.current_policy import CurrentRequest, Output

root = Path(__file__).resolve().parents[2] / "contracts/http/current-policy-answer/v1"
for name, model in [("request", CurrentRequest), ("output", Output)]:
    (root / f"{name}.schema.json").write_text(json.dumps(model.model_json_schema(), indent=2) + "\n")
