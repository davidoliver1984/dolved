# EXP-0008 final V3 engineering confirmation

`EXP-0008-v3-final-engineering-confirmation` is the immutable final
engineering-only confirmation boundary for the current ten-case, 31-variant
Benchmark V3 regression population and ADR-0022-v5 planner lineage.

EXP-0008 reuses the verified 94-document, 100-chunk, 100-point V3 materialised
corpus because the reconciliation changed only engineering question and review
lineage. Organisation, catalogue, source-document identities, canonical chunks,
vector profiles and the active point manifest remain unchanged. The run
definition binds both the current evaluation population and the inherited
materialisation record explicitly.

The frozen retrieval configuration remains dense K 40, sparse K 40,
equal-weight RRF k 5, fusion/reranker K 15, observational threshold
0.337890625 and final evidence K 5.

The dedicated runtime exposes only current V3 engineering inputs and a fresh
EXP-0008 checkpoint/output root. EXP-0007, calibration, held-out, V2 engineering
and broad evaluation paths are not mounted. This is development/regression
evidence, not calibration, held-out acceptance or unseen generalisation evidence.

## Closure decision

EXP-0008 completed all 31 variants from exact commit
`a21431bc0f9137978f3c4d082619954f8814bd9d`. The accepted engineering result is
Recall@K `0.9667`, benchmark precision `0.2100`, MRR `0.9333` and nDCG@K
`0.9157`. Clean-upstream Recall@K is `1.0000`, and all 36 correctly scoped
expected EvidenceUnits remained present through Dense, Sparse, union, fusion,
reranking, threshold and final evidence.

Planner correctness is accepted at `29/31`. The two remaining content/event-
time versus authority-time classifications are recorded in the immutable
[closure record](../runs/EXP-0008-v3-final-engineering-confirmation/closure.md)
rather than tuned away. No downstream retrieval-stage defect was demonstrated.

The retrieval core is mature for the current phase; the planner is accepted
with known residual risk. This remains engineering regression evidence only,
with no calibration, sealed-held-out or unseen-generalisation claim.
