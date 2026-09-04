# Evaluation experiments

This index is generated from immutable run directories. Raw JSON remains authoritative.

| Experiment | Date | Change | Benchmark | Recall | Precision | MRR | nDCG | Gate/status | Decision | Baseline |
|---|---|---|---|---:|---:|---:|---:|---|---|---|
| [EXP-0001-alderbridge-initial-hybrid](runs/EXP-0001-alderbridge-initial-hybrid/report.md) | 2026-08-11 | First truthful V2 engineering-split dense versus hybrid retrieval baseline | dolved-care-engineering v2 | 0.0569 | 0.0008 | 0.0041 | 0.0569 | EXPERIMENTAL | — | — |
| [EXP-0002-adr0022-corrected-planning-baseline](runs/EXP-0002-adr0022-corrected-planning-baseline/report.md) | 2026-08-12 | First exact-commit ADR-0022 full-pipeline engineering baseline | dolved-care-engineering v2 | 0.1389 | 0.0183 | 0.0913 | 0.1398 | EXPERIMENTAL | — | — |
| [EXP-0003-post-reliability-corrected-engineering-baseline](runs/EXP-0003-post-reliability-corrected-engineering-baseline/report.md) | 2026-08-12 | Post-reliability corrected full-pipeline engineering baseline | dolved-care-engineering v2 | 0.8849 | 0.1786 | 0.8181 | 0.8576 | EXPERIMENTAL | — | — |
| [EXP-0004-rrf-k-5-controlled-engineering-experiment](runs/EXP-0004-rrf-k-5-controlled-engineering-experiment/report.md) | 2026-08-13 | Controlled engineering RRF experiment: rrf_k=60 control versus rrf_k=5 treatment with all other retrieval variables frozen | dolved-care-engineering v2 | 0.8889 | 0.1802 | 0.8181 | 0.8600 | EXPERIMENTAL | — | — |
| [CAL-EXP-0003-v3-post-planner-hardening-calibration](runs/CAL-EXP-0003-v3-post-planner-hardening-calibration/closure.md) | 2026-08-14 | Exact-lineage evidence-threshold calibration after planner hardening | dolved-care-engineering v3 calibration | 0.7982 | 0.1982 | 0.7917 | 0.7743 | CALIBRATED_NOT_HELD_OUT_ACCEPTED | Retain `0.337890625` | — |
| [EXP-0008-v3-final-engineering-confirmation](runs/EXP-0008-v3-final-engineering-confirmation/report.md) | 2026-08-16 | Final consolidated V3 engineering-only planner/retrieval confirmation | dolved-care-engineering v3 engineering | 0.9667 | 0.2100 | 0.9333 | 0.9157 | EXPERIMENTAL (engineering-only) | Accepted final engineering confirmation; close current retrieval block | — |
| [R28-S02-LIVE-RETRIEVAL-BASELINE-0001](runs/R28-S02-LIVE-RETRIEVAL-BASELINE-0001/closure.md) | 2026-09-04 | Approved current-code live retrieval execution/lineage baseline | bound legacy V2 population (23 cases / 25 variants; recorded plans) | 1.0000 | 0.1130 | 0.5014 | 0.9516 | EXECUTION BASELINE ONLY | Complete recall on this narrow population; no pilot-readiness decision | — |

## Grounded-generation experiments

| Experiment | Date | Population | Generation calls | Deterministic outcome correctness | Advisory semantic result | Decision |
|---|---|---|---:|---:|---|---|
| [GEN-EXP-0001-grounded-generation-baseline](runs/GEN-EXP-0001-grounded-generation-baseline/report.md) | 2026-08-17 | grounded-generation-v1 (13 cases) | 13 | 13/13 | Historical Ragas answer-metric baseline | Preserved immutable original provider-backed baseline |
| [GEN-EXP-0002-corrected-grounded-generation-evaluator](runs/GEN-EXP-0002-corrected-grounded-generation-evaluator/report.md) | 2026-08-17 | grounded-generation-v1 (same 13 cases) | 0 | 13/13 | 1.0000 across all eligible metrics with complete coverage | Accepted evaluator-only corrected replay; advisory engineering evidence |
| [GEN-SEC-LIVE-R28-S02-BASELINE-0001](runs/GEN-SEC-LIVE-R28-S02-BASELINE-0001/report.md) | 2026-09-04 | prompt-injection-v1 (3 hostile-evidence smoke cases) | 3 | 3/3 | 1.0000 groundedness, factual precision, completeness and citation correctness | Approved narrow security-smoke execution baseline; no pilot-readiness decision |
