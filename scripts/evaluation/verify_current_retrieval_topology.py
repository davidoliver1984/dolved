"""Fail-closed rendered-Compose audit for the current retrieval candidate."""

from __future__ import annotations

import json
import sys
from pathlib import Path
from typing import Any


def main(path: Path) -> None:
    config: dict[str, Any] = json.loads(path.read_text())
    services = config.get("services", {})
    for name in ("api", "ai"):
        service = services.get(name)
        if not isinstance(service, dict):
            raise SystemExit(f"required service missing from rendered topology: {name}")
        environment = service.get("environment", {})
        for secret in (
            "VOYAGE_API_KEY",
            "RETRIEVAL_PLANNER_API_KEY",
            "CONTEXTUALISER_API_KEY",
            "GENERATION_OPENAI_API_KEY",
        ):
            if str(environment.get(secret, "")).strip():
                raise SystemExit(
                    f"provider credential is available to {name}: {secret}"
                )
        for mount in service.get("volumes", []):
            source = str(mount.get("source", "")).lower()
            target = str(mount.get("target", "")).lower()
            joined = f"{source}:{target}"
            if any(term in joined for term in ("calibration", "held-out", "held_out")):
                raise SystemExit(f"protected split mount exposed to {name}: {joined}")
            if target in {"/evaluation", "/workspace", "/tests/evaluation"}:
                raise SystemExit(
                    f"broad repository/evaluation mount exposed to {name}: {joined}"
                )
            if "/cases" in target or "/splits" in target:
                raise SystemExit(
                    f"benchmark authoring/split mount exposed to {name}: {joined}"
                )

    if (
        services["api"]["environment"].get("EVALUATION_CURRENT_IDENTITY")
        != "dolved-evaluation-current"
    ):
        raise SystemExit("Laravel evaluation identity is not exact")
    if services["ai"]["environment"].get("ENVIRONMENT") != "evaluation-current":
        raise SystemExit("Python evaluation environment is not exact")


if __name__ == "__main__":
    main(Path(sys.argv[1]))
