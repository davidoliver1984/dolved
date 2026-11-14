# C1 live evidence — 14 November 2026

Start with [report.md](report.md). This package contains execution evidence, not correctness verdicts.

- [route-summary.json](route-summary.json): observed per-item operations, targets, lane allocation and hashes.
- `cases/F1.json` … `F5.json`: complete captured records and answers; matching `F*-ledger.ndjson` files are hash-chained observation/call ledgers.
- [persistence-verification.json](persistence-verification.json): independent readback of the five conversations.
- [accounting-reconciled.json](accounting-reconciled.json) and [nested-stage-receipts.json](nested-stage-receipts.json): pricing estimates/allowances and non-additive component receipts.
- [authorization.json](authorization.json), `authorization-consumed/marker.json`, [governed-inputs.sha256](governed-inputs.sha256): frozen authority and harness/input bindings.
- `preflight/`: before/after migration state, frozen runtime verification, capture fixture proof and per-item budget/runtime checks.
- `harness/`: capture-only execution tooling, proof generation, integrity and reporting scripts. No application source was changed.
- [checksums.sha256](checksums.sha256): final package inventory, excluding itself.

The original released input is unchanged at `../released-input/questions-only.json`. Expectations are not included. The prior failed-deployment package is preserved in the parent directory.
