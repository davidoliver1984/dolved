"""Frozen Phase A adapters. Only explicit execute mode can dispatch a question."""

import hashlib
import json
import os
import subprocess
import sys
from pathlib import Path

from control import digest, execute


def inspect(name):
    return json.loads(subprocess.check_output(["docker", "inspect", name]))[0]


def verify(b):
    w = Path(b["worktree"])
    assert (
        subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=w, text=True).strip()
        == b["commit"]
    )
    for p, h in b["files"].items():
        assert digest(w / p) == h, "bound file drift"
    for service, expected in b["containers"].items():
        c = inspect("dolved-r28-s03-" + service + "-1")
        assert (
            c["Id"] == expected["id"]
            and c["Image"] == expected["image"]
            and c["State"]["Running"]
        )
        assert (
            hashlib.sha256(json.dumps(c["Config"], sort_keys=True).encode()).hexdigest()
            == expected["configuration_sha256"]
        )
        if service in ("api", "ai"):
            rows = {}
            for p, h in b["runtime_files"].items():
                prefix = "apps/" + service + "/"
                if p.startswith(prefix):
                    rows["/app/" + p[len(prefix) :]] = h
                elif p.startswith("contracts/"):
                    rows["/" + p] = h
            cmd = (
                [
                    "php",
                    "-r",
                    '$a=json_decode(stream_get_contents(STDIN),true);foreach($a as $p=>$h){if(hash_file("sha256",$p)!==$h)exit(2);}',
                ]
                if service == "api"
                else [
                    "python",
                    "-c",
                    "import sys,json,pathlib,hashlib;assert all(hashlib.sha256(pathlib.Path(p).read_bytes()).hexdigest()==h for p,h in json.load(sys.stdin).items())",
                ]
            )
            subprocess.run(
                ["docker", "exec", "-i", c["Id"], *cmd],
                input=json.dumps(rows),
                text=True,
                check=True,
                capture_output=True,
            )
    names = subprocess.check_output(
        [
            "docker",
            "ps",
            "--filter",
            "label=com.docker.compose.project=dolved-r28-s03",
            "--format",
            "{{.Names}}",
        ],
        text=True,
    ).splitlines()
    for name in names:
        c = inspect(name)
        assert not any(
            x in " ".join(c["Config"].get("Cmd") or [])
            for x in ["queue:work", "schedule:", "consume-ingestion"]
        )
        assert not any(x in name for x in ["worker", "publisher", "bootstrap"])
    script = (
        (w / "scripts/evaluation/current_policy_confirmation/preflight.php")
        .read_text()
        .removeprefix("<?php")
    )
    result = subprocess.run(
        [
            "docker",
            "exec",
            "-i",
            "-e",
            "CURRENT_POLICY_APP_ROOT=/app",
            "dolved-r28-s03-api-1",
            "php",
            "-r",
            script,
        ],
        input=json.dumps(b["context"]),
        text=True,
        capture_output=True,
        check=True,
    )
    assert json.loads(result.stdout) == b["expected_preflight"]
    # This read-only guard is independently frozen; no conversation runner is used.
    code = 'require "/app/vendor/autoload.php";$a=require "/app/bootstrap/app.php";$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();echo json_encode(["queued"=>Illuminate\\Support\\Facades\\DB::table("jobs")->where("queue","conversation-generation")->count(),"timeout"=>config("retrieval.timeout_seconds"),"attempts"=>config("retrieval.max_attempts")]);'
    state = json.loads(
        subprocess.check_output(
            ["docker", "exec", "dolved-r28-s03-api-1", "php", "-r", code]
        )
    )
    assert state == b["transport_bounds"]
    p = Path(b["capture"])
    assert p.is_dir() and not p.is_symlink()
    retained = {}
    for item in json.loads(Path(b["invocations"]).read_text())["items"]:
        result = Path(b["destination"]) / (item["id"] + ".json")
        if result.exists():
            for row in json.loads(result.read_text()).get("captures", []):
                retained[Path(row["path"]).name] = row["sha256"]
    assert {x.name: digest(x) for x in p.iterdir() if x.is_file()} == retained, (
        "capture integrity drift"
    )
    assert all(x.is_file() and not x.is_symlink() for x in p.iterdir())


def dispatch(b, request, remaining, item):
    root = Path(b["destination"])
    cap = Path(b["capture"])
    prior = {p.name: digest(p) for p in cap.glob("*.json")}
    script = (
        (Path(b["worktree"]) / "scripts/evaluation/planner_scope_diagnostic/invoke.php")
        .read_text()
        .removeprefix("<?php")
    )
    result = subprocess.run(
        ["docker", "exec", "-i", "dolved-r28-s03-api-1", "php", "-r", script],
        input=json.dumps(request),
        text=True,
        capture_output=True,
        timeout=min(remaining, b["maximum_invocation_seconds"]),
    )
    # Preserve even an invalid/failed response. The PHP adapter never emits exception prose.
    from runner import durable

    durable(
        root / (item + "-transport.json"),
        {
            "returncode": result.returncode,
            "stdout": result.stdout,
            "stderr_available": bool(result.stderr),
        },
        True,
    )
    if result.returncode:
        raise ValueError("planner transport/entrypoint failure")
    value = json.loads(result.stdout)
    ids = [x["request_id"] for x in value["requests"]]
    if not ids or len(ids) != len(set(ids)):
        raise ValueError("request attribution unavailable")
    for row in value["requests"]:
        assert row["signed"] and row["purpose"] == "retrieval.plan-needs"
        assert row["user_turns"] == [
            {"turn_id": "user-1", "text": request["utterance"]}
        ]
    captures = []
    for p in cap.glob("*.json"):
        if p.name in prior:
            assert digest(p) == prior[p.name]
            continue
        data = json.loads(p.read_text())
        assert (
            data["request_id"] in ids
            and data["workspace_id"] == b["context"]["workspace"]
        )
        assert data["stage"] == "planner" and "scope_diagnostics" in data
        assert not data.get("scope_diagnostics_truncated")
        if any("bound" in x for x in data.get("unavailable", [])):
            raise ValueError("capture truncated")
        captures.append(
            {
                "path": str(p),
                "sha256": digest(p),
                "request_id": data["request_id"],
                "attempt": data["attempt"],
                "status": data["status"],
            }
        )
    for request_id in ids:
        attempts = sorted(
            x["attempt"] for x in captures if x["request_id"] == request_id
        )
        assert attempts in ([1], [1, 2]), "missing/duplicate native attempt capture"
    final_records = [x for x in captures if x["request_id"] == ids[-1]]
    receipt_attempts = (
        (value.get("usage") or {}).get("validation_attempts")
        if value["status"] == "accepted"
        else value.get("validation_attempts")
    )
    assert receipt_attempts and len(final_records) == len(receipt_attempts), (
        "attempt receipt/capture mismatch"
    )
    assert max(final_records, key=lambda x: x["attempt"])["status"] == (
        "accepted" if value["status"] == "accepted" else "rejected"
    )
    value["captures"] = captures
    return value


if __name__ == "__main__":
    b = json.loads(Path(sys.argv[2]).read_text())
    b["binding_sha256"] = digest(sys.argv[2])
    if sys.argv[1] == "verify":
        verify(b)
        assert not list(Path(b["capture"]).iterdir())
        assert (
            not Path(b["authorization"]).exists()
            and not Path(b["destination"]).exists()
        )
        print("Preparation verification passed; no dispatch.")
    elif sys.argv[1] == "execute":
        assert not list(Path(b["capture"]).iterdir()), "prior capture state"
        execute(
            b,
            lambda: verify(b),
            lambda request, remaining, item: dispatch(b, request, remaining, item),
        )
    else:
        raise ValueError("unknown command")
