"""Generate the single approved continuation authorization; no provider calls."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[4] / "scripts/evaluation"))

import block1_extractive_stage as stage
import run_r28_r06_governed as g


def main():
    execution, scopes, identities = g.verify_inputs(stage.TAIL_STAGE)
    snapshot = g.runtime_snapshot()
    authority = g.json_file(g.ROOT / stage.DIRECTORY / "authorization.json")
    authority.update(
        authorization_id="R28-R07-AUTH-BLOCK1-EXTRACTIVE-TAIL-20261113-01",
        run_id="R28-R07-BLOCK1-EXTRACTIVE-TAIL-20261113-01",
        stage=stage.TAIL_STAGE,
        manifest_id=execution["subset_id"],
        execution_commit=g.git("rev-parse", "HEAD"),
        runtime_snapshot_sha256=g.snapshot_digest(snapshot),
        approval_basis="David approved on 2026-11-13 the hash-bound continuation of frozen inputs 9–32 only, retaining original 1–8 without redispatch. Exact pushed hold SHA and original runtime aggregate required. Revised outcome-based false-flag criteria in plan commit b8c9cd7 apply. USD 3 cumulative cap less prior known priced/estimated USD 0.11196123; unpriced Voyage reranking and local infrastructure remain explicitly unaccounted, not zero. Per-case source review; no main merge unless every criterion passes.",
        **identities,
    )
    stage.verify_commit(g, authority)
    directory = g.ROOT / "docs/evaluation/r28-s04/block1-extractive-continuation-20261113"
    directory.mkdir(exist_ok=False)
    for name, value in {
        "authorization.json": authority,
        "execution-input.json": execution,
        "execution-scopes.json": scopes,
        "runtime-snapshot.json": snapshot,
        "continuation-link.json": {
            "original_run": stage.ORIGINAL_RUN,
            "original_ledger_sha256": stage.LEDGER_SHA256,
            "original_inventory_sha256": stage.INVENTORY_SHA256,
            "original_selection_sha256": stage.SELECTION_SHA256,
            "global_input_numbers": list(range(9, 33)),
            "local_index_to_global": {str(i): i + 8 for i in range(1, 25)},
            "prior_known_usd": 0.11196123,
            "remaining_known_usd": 2.88803877,
            "unpriced": ["Voyage reranking", "local Qdrant infrastructure"],
        },
    }.items():
        g.write_exclusive(directory / name, value)
    g.write_inventory(directory)
    print(json.dumps({"authorization": str(directory / "authorization.json"), "case_count": len(execution["items"]), "runtime_source_aggregate": identities["runtime_source_aggregate"]}))


if __name__ == "__main__":
    main()
