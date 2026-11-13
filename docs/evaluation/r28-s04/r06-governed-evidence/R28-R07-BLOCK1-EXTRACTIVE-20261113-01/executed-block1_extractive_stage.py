"""Checkpoint-only hold-branch verification. Never changes application execution."""
from pathlib import Path

STAGE = "block1_extractive"
REMOTE_REF = "refs/heads/codex/block1-extractive-hold-20261112"
DIRECTORY = "docs/evaluation/r28-s04/block1-extractive-checkpoint-20261113"
SELECTION_SHA256 = "0de5c2ea1998820c1ef950db79ea4386d36a25a7f91bfa85e3693dfb02cc0183"


def verify_commit(governed, authority):
    pinned = authority["execution_commit"]
    if len(pinned) != 40 or any(c not in "0123456789abcdef" for c in pinned):
        raise ValueError("extractive checkpoint requires an exact commit SHA")
    if governed.git("rev-parse", "HEAD") != pinned:
        raise ValueError("extractive checkpoint local SHA mismatch")
    remote = governed.git("ls-remote", "origin", REMOTE_REF).split()
    if remote != [pinned, REMOTE_REF]:
        raise ValueError("extractive checkpoint remote SHA mismatch")
    if governed.runtime_aggregate() != authority["runtime_source_aggregate"]:
        raise ValueError("extractive checkpoint runtime aggregate mismatch")


def inputs(governed):
    directory = governed.ROOT / DIRECTORY
    if governed.sha256(directory / "selection-checksums.sha256") != SELECTION_SHA256:
        raise ValueError("extractive fixed manifest changed")
    for line in (directory / "selection-checksums.sha256").read_text().splitlines():
        digest, name = line.split("  ", 1)
        if name not in {"execution-input.json", "execution-scopes.json", "selection-and-expectations.json"} or governed.sha256(directory / name) != digest:
            raise ValueError("extractive selection checksum mismatch")
    execution = governed.json_file(directory / "execution-input.json")
    scopes = governed.json_file(directory / "execution-scopes.json")
    if len(execution["items"]) != 32 or len(scopes["items"]) != 32:
        raise ValueError("extractive selection count mismatch")
    for item in execution["items"]:
        if set(item) != {"case_id", "variant_id", "utterance"}:
            raise ValueError("non-question material in extractive execution")
    return execution, scopes, {
        "selection_sha256": governed.sha256(directory / "selection-checksums.sha256"),
        "input_sha256": governed.sha256(directory / "execution-input.json"),
        "scopes_sha256": governed.sha256(directory / "execution-scopes.json"),
        "population_sha256": governed.sha256(governed.POPULATION),
        "dispatcher_sha256": governed.sha256(governed.DISPATCHER),
        "runner_sha256": governed.snapshot_digest({"runner": governed.sha256(governed.RUNNER), "stage": governed.sha256(Path(__file__))}),
        "runtime_source_aggregate": governed.runtime_aggregate(),
    }
