"""Checkpoint-only hold-branch verification. Never changes application execution."""
from pathlib import Path
import json

STAGE = "block1_extractive"
TAIL_STAGE = "block1_extractive_tail"
STAGES = (STAGE, TAIL_STAGE)
RUNTIME_SHA256 = "0eab315797d6b8af9baea21b85a867386c83bf425c7719ffb16b9974bc9acdc8"
ORIGINAL_RUN = "docs/evaluation/r28-s04/r06-governed-evidence/R28-R07-BLOCK1-EXTRACTIVE-20261113-01"
LEDGER_SHA256 = "62fb573c22c25d0b2552be33954681a422c24baf722dbfe8d95f116ee58229af"
INVENTORY_SHA256 = "3fb347b602efa806facfe6bd8beab4579b411e63443ac16e4f019a89358eb2f8"
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
    if authority.get("stage") == TAIL_STAGE:
        if authority["runtime_source_aggregate"] != RUNTIME_SHA256:
            raise ValueError("continuation runtime aggregate differs from original eight")
        inputs(governed, TAIL_STAGE)


def inputs(governed, stage=STAGE):
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
    identities = {
        "selection_sha256": governed.sha256(directory / "selection-checksums.sha256"),
        "input_sha256": governed.sha256(directory / "execution-input.json"),
        "scopes_sha256": governed.sha256(directory / "execution-scopes.json"),
        "population_sha256": governed.sha256(governed.POPULATION),
        "dispatcher_sha256": governed.sha256(governed.DISPATCHER),
        "runner_sha256": governed.snapshot_digest({"runner": governed.sha256(governed.RUNNER), "stage": governed.sha256(Path(__file__))}),
        "runtime_source_aggregate": governed.runtime_aggregate(),
    }
    if stage == TAIL_STAGE:
        verify_original(governed, execution["items"])
        execution = {**execution, "subset_id": execution["subset_id"] + "-TAIL-9-32", "items": execution["items"][8:]}
        scopes = {**scopes, "manifest_id": execution["subset_id"], "items": scopes["items"][8:]}
        identities["input_sha256"] = governed.snapshot_digest(execution)
        identities["scopes_sha256"] = governed.snapshot_digest(scopes)
        if identities["runtime_source_aggregate"] != RUNTIME_SHA256:
            raise ValueError("continuation runtime aggregate mismatch")
    return execution, scopes, identities


def verify_original(governed, full_items):
    """Read-only identity and integrity check; the old evidence is never opened for writing."""
    root = governed.ROOT / ORIGINAL_RUN
    if governed.sha256(root / "dispatch.ndjson") != LEDGER_SHA256:
        raise ValueError("original ledger changed")
    if governed.sha256(root / "checksums.sha256") != INVENTORY_SHA256:
        raise ValueError("original inventory changed")
    for line in (root / "checksums.sha256").read_text().splitlines():
        digest, name = line.split("  ", 1)
        path = root / name
        if not path.resolve().is_relative_to(root.resolve()) or governed.sha256(path) != digest:
            raise ValueError("original evidence checksum mismatch")
    ledger = [json.loads(line) for line in (root / "dispatch.ndjson").read_text().splitlines()]
    recorded = [(r["case_id"], r["variant_id"]) for r in ledger if r["event"] == "recorded"]
    if recorded != [(i["case_id"], i["variant_id"]) for i in full_items[:8]]:
        raise ValueError("original eight recorded inputs differ")
    validate_tail(full_items[8:], full_items, ledger)


def validate_tail(items, full_items, ledger):
    seen = {(r["case_id"], r["variant_id"]) for r in ledger}
    if any((i["case_id"], i["variant_id"]) in seen for i in items):
        raise ValueError("continuation input already present in original ledger")
    if items != full_items[8:] or len(items) != 24:
        raise ValueError("continuation must contain only frozen inputs 9–32")
