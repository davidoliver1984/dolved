# Evaluation experiments

This index is generated from immutable run directories. Raw JSON remains authoritative.

| Experiment | Date | Change | Benchmark | Recall | Precision | MRR | nDCG | Gate/status | Decision | Baseline |
|---|---|---|---|---:|---:|---:|---:|---|---|---|
| [EXP-0001-alderbridge-initial-hybrid](runs/EXP-0001-alderbridge-initial-hybrid/report.md) | 2026-08-11 | First truthful V2 engineering-split dense versus hybrid retrieval baseline | dolved-care-engineering v2 | 0.0569 | 0.0008 | 0.0041 | 0.0569 | EXPERIMENTAL | — | — |
| [EXP-0002-adr0022-corrected-planning-baseline](runs/EXP-0002-adr0022-corrected-planning-baseline/report.md) | 2026-08-12 | First exact-commit ADR-0022 full-pipeline engineering baseline | dolved-care-engineering v2 | 0.1389 | 0.0183 | 0.0913 | 0.1398 | EXPERIMENTAL | — | — |
| [EXP-0003-post-reliability-corrected-engineering-baseline](runs/EXP-0003-post-reliability-corrected-engineering-baseline/report.md) | 2026-08-12 | Post-reliability corrected full-pipeline engineering baseline | dolved-care-engineering v2 | 0.8849 | 0.1786 | 0.8181 | 0.8576 | EXPERIMENTAL | — | — |
| [EXP-0004-rrf-k-5-controlled-engineering-experiment](runs/EXP-0004-rrf-k-5-controlled-engineering-experiment/report.md) | 2026-08-13 | Controlled engineering RRF experiment: rrf_k=60 control versus rrf_k=5 treatment with all other retrieval variables frozen | dolved-care-engineering v2 | 0.8889 | 0.1802 | 0.8181 | 0.8600 | EXPERIMENTAL | — | — |
| [CAL-EXP-0003-v3-post-planner-hardening-calibration](runs/CAL-EXP-0003-v3-post-planner-hardening-calibration/closure.md) | 2026-08-14 | Exact-lineage evidence-threshold calibration after planner hardening | dolved-care-engineering v3 calibration | 0.7982 | 0.1982 | 0.7917 | 0.7743 | CALIBRATED_NOT_HELD_OUT_ACCEPTED | Retain `0.337890625` | — |
