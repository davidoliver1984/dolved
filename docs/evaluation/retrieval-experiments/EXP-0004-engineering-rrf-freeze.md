# EXP-0004 engineering RRF freeze

## Decision

`rrf_k=5` is the frozen engineering configuration carried forward into
subsequent evaluation. It remains subject to calibration and sealed held-out
acceptance.

This is not calibration acceptance, held-out acceptance, production-policy
promotion, or a claim that `5` is universally optimal. The experimental
EvidenceThresholdPolicy remains `CALIBRATING`, and the evidence threshold remains
exactly `0.337890625`.

## Evidence trail

The decision follows this repository-owned sequence:

1. EXP-0003 exposed expected candidates lost at the RRF fusion boundary.
2. RRF-EXP-0001 replayed immutable EXP-0003 candidate sets without providers and
   identified a stable engineering region at `rrf_k=1–5`.
3. `rrf_k=5` was selected instead of `1` as the conservative edge of that region,
   retaining more rank damping while testing the predicted recoveries.
4. EXP-0004 ran the full application pipeline with `rrf_k=5`; every other
   retrieval setting remained frozen against the `rrf_k=60` control.

EXP-0004 confirmed that all three predicted strong dense-only candidates entered
fusion top 15 and reached reranking. Two survived the unchanged threshold and
became final evidence. The third scored `0.32421875` and was rejected by the
unchanged `0.337890625` threshold. The weak Dense-rank-33/Sparse-rank-36 control
remained outside fusion.

Within the 97-variant equivalent clean-upstream cohort, EXP-0004 improved:

| Metric | EXP-0003 | EXP-0004 |
|---|---:|---:|
| Recall@5 | 0.9639 | 0.9794 |
| Precision@5 | 0.2072 | 0.2113 |
| MRR | 0.9442 | 0.9545 |
| nDCG@5 | 0.9322 | 0.9457 |

No expected EvidenceUnit regression in that equivalent clean-upstream cohort was
attributable to `rrf_k=5`. Aggregate comparisons still include ordinary provider
nondeterminism, and the complete EXP-0003-to-EXP-0004 comparison also includes the
already-committed location and temporal corrections. Those effects must not be
misattributed to RRF.

Calibration and held-out splits remained physically unavailable throughout the
experiment. RRF tuning on the engineering split stops at this decision; `rrf_k=1`
was not tested end to end.

## Historical artefact note

The reviewed EXP-0004 `application-observations.json`, `result.json`,
`run-manifest.json`, and generated report/config artefacts are retained exactly as
produced. In particular, the historical `config.json` contains the stale generic
description discovered after the run. The corrected compiler now gives future
scratch regeneration a truthful controlled-RRF description, but the reviewed
historical file was not silently rewritten.

The authoritative reviewed result digest is:

`821022de0b62ca349d99f46ed268b6c62fa49f8f0a0b95be88715a05dfe1aae4`
