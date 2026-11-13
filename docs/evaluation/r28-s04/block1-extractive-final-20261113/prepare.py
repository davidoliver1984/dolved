"""One-use final-tail authorization; read-only preflight, no provider calls."""
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[3]
sys.path.insert(0, str(ROOT / "scripts/evaluation"))
import run_r28_r06_governed as g
import block1_extractive_stage as s

e, scopes, identities = g.verify_inputs(s.FINAL_STAGE)
snapshot = g.runtime_snapshot()
budget = g.json_file(HERE / "accounting.json")
assert budget["next_dispatch_budget_gate"]
authority = g.json_file(ROOT / s.DIRECTORY / "authorization.json")
authority.update(
    authorization_id="R28-R07-AUTH-BLOCK1-EXTRACTIVE-FINAL-20261113-01",
    run_id="R28-R07-BLOCK1-EXTRACTIVE-FINAL-20261113-01",
    stage=s.FINAL_STAGE, manifest_id=e["subset_id"],
    execution_commit=g.git("rev-parse", "HEAD"),
    runtime_snapshot_sha256=g.snapshot_digest(snapshot),
    approval_basis="David approved the fixed 16–32 continuation on 2026-11-13, after stage-specific plan addendum 51a2123. Both prior ledgers/inventories immutable; no repeat of inputs 1–15. Exact pushed hold SHA, unchanged runtime, one-use authorization, original selection and expectations. USD 3 cumulative cap with retained-rate conservative reranking allowance and USD 0.50 unpriced uncertainty reserve, plus USD 0.50 next-case headroom. Source-based stage-specific review at every case; criteria (a)/(b), security/authority/citation stops unchanged. No application changes or selective reruns.",
    **identities,
)
s.verify_commit(g, authority)
for name, value in {"authorization.json": authority, "execution-input.json": e, "execution-scopes.json": scopes, "runtime-snapshot.json": snapshot, "budget-reservation.json": budget, "continuation-link.json": {"original_run": s.ORIGINAL_RUN, "second_run": s.TAIL_RUN, "original_ledger_sha256": s.LEDGER_SHA256, "second_ledger_sha256": s.TAIL_LEDGER_SHA256, "original_inventory_sha256": s.INVENTORY_SHA256, "second_inventory_sha256": s.TAIL_INVENTORY_SHA256, "selection_sha256": s.SELECTION_SHA256, "global_inputs": list(range(16,33)), "local_to_global": {str(i):i+15 for i in range(1,18)}}}.items():
    g.write_exclusive(HERE / name, value)
g.write_inventory(HERE)
print(authority["execution_commit"], len(e["items"]), identities["runtime_source_aggregate"])
