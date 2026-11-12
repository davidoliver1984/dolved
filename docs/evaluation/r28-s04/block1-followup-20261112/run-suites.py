"""Provider-free full-suite attribution using existing Compose dependency runtimes."""
import subprocess
import sys
from pathlib import Path

repo = Path(__file__).resolve().parents[4]
root = Path(__file__).parent
label = sys.argv[1]
if label not in {"baseline", "changed"}:
    raise SystemExit("baseline or changed required")
for service, command in [
    ("ai", ["/app/.venv/bin/python", "-m", "pytest", "tests", "-q", "--junitxml=/repo/" + str(root.relative_to(repo)) + f"/{label}-ai.xml"]),
    ("api", ["php", "-d", "memory_limit=512M", "vendor/bin/phpunit", "--log-junit", "/repo/" + str(root.relative_to(repo)) + f"/{label}-api.xml"]),
]:
    cmd = ["docker", "compose", "run", "--rm", "--no-deps",
           "-e", "GIT_CONFIG_COUNT=1", "-e", "GIT_CONFIG_KEY_0=safe.directory", "-e", "GIT_CONFIG_VALUE_0=/repo",
           "-v", f"{repo}:/repo", "-v", f"{repo}:/workspace", "-w", "/repo/apps/ai" if service == "ai" else "/app", service, *command]
    with (root / f"{label}-{service}.log").open("w") as log:
        result = subprocess.run(cmd, cwd=repo, stdout=log, stderr=subprocess.STDOUT)
    print(f"{label} {service}: exit {result.returncode}", flush=True)
