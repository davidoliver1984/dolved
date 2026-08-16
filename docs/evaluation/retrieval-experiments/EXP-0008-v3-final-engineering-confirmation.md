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
