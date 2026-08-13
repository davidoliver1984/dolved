# RRF sensitivity experiment

Experiment: `RRF-EXP-0001-exp0003-engineering-sensitivity`

This is a provider-free replay over immutable EXP-0003 Dense and Sparse ranks. It does not run reranking or authorise a production change.

## Cohort

- Variants: 109
- Expected EvidenceUnit instances: 138
- Fusion limit: 15

## Sensitivity

| k | Retained | Recall@15 | Precision@15 | MRR | nDCG@15 | Top1 | Top3 | Top5 | Top10 | Recovered | Regressed | Net |
|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| 1 | 137 | 0.9928 | 0.0708 | 0.8822 | 0.8962 | 0.7754 | 0.9348 | 0.9565 | 0.9855 | 3 | 0 | +3 |
| 5 | 137 | 0.9928 | 0.0708 | 0.8757 | 0.8901 | 0.7681 | 0.9130 | 0.9420 | 0.9855 | 3 | 0 | +3 |
| 10 | 136 | 0.9855 | 0.0703 | 0.8745 | 0.8872 | 0.7681 | 0.9058 | 0.9493 | 0.9638 | 2 | 0 | +2 |
| 20 | 134 | 0.9710 | 0.0693 | 0.8735 | 0.8836 | 0.7681 | 0.9058 | 0.9493 | 0.9710 | 0 | 0 | +0 |
| 30 | 134 | 0.9710 | 0.0693 | 0.8735 | 0.8836 | 0.7681 | 0.9058 | 0.9493 | 0.9710 | 0 | 0 | +0 |
| 40 | 134 | 0.9710 | 0.0693 | 0.8722 | 0.8826 | 0.7681 | 0.9058 | 0.9493 | 0.9710 | 0 | 0 | +0 |
| 50 | 134 | 0.9710 | 0.0693 | 0.8722 | 0.8825 | 0.7681 | 0.9058 | 0.9493 | 0.9638 | 0 | 0 | +0 |
| 60 | 134 | 0.9710 | 0.0693 | 0.8716 | 0.8820 | 0.7681 | 0.8986 | 0.9493 | 0.9638 | 0 | 0 | +0 |
| 80 | 134 | 0.9710 | 0.0693 | 0.8716 | 0.8820 | 0.7681 | 0.8986 | 0.9493 | 0.9638 | 0 | 0 | +0 |
| 100 | 134 | 0.9710 | 0.0693 | 0.8716 | 0.8820 | 0.7681 | 0.8986 | 0.9493 | 0.9638 | 0 | 0 | +0 |

## Known fusion losses

- `pilot.current.scheduled-medication-version::direct::PRIMARY::medication.v2.omission-current-rule` — Dense `3`, Sparse `None`; k=1: rank 9, k=5: rank 10, k=10: rank 13, k=20: rank 21, k=30: rank 25, k=40: rank 26, k=50: rank 26, k=60: rank 26, k=80: rank 26, k=100: rank 26
- `pilot.current.scheduled-medication-version::scheduled::PRIMARY::medication.v2.omission-current-rule` — Dense `4`, Sparse `None`; k=1: rank 9, k=5: rank 10, k=10: rank 18, k=20: rank 25, k=30: rank 29, k=40: rank 29, k=50: rank 29, k=60: rank 29, k=80: rank 29, k=100: rank 29
- `pilot.multi-document.medication-storage::numeric::PRIMARY::medication.administration.fridge-gate` — Dense `3`, Sparse `None`; k=1: rank 3, k=5: rank 8, k=10: rank 13, k=20: rank 23, k=30: rank 25, k=40: rank 25, k=50: rank 25, k=60: rank 25, k=80: rank 25, k=100: rank 25
- `safeguarding.covert-medication.multi-document::multi::PRIMARY::covert-medication.capacity-controls` — Dense `33`, Sparse `36`; k=1: rank 32, k=5: rank 31, k=10: rank 30, k=20: rank 30, k=30: rank 30, k=40: rank 30, k=50: rank 30, k=60: rank 30, k=80: rank 30, k=100: rank 30

## Source-rank populations

- `both_moderate`: n=2; retained at k=5 1, at k=60 1
- `both_top_5`: n=125; retained at k=5 125, at k=60 125
- `dense_only`: n=3; retained at k=5 3, at k=60 0
- `one_top_5_other_lower`: n=8; retained at k=5 8, at k=60 8

## Engineering slices

| Slice | Variants | EvidenceUnits | Recall@15 k=5 | Recall@15 k=60 |
|---|---:|---:|---:|---:|
| CURRENT | 76 | 85 | 0.9882 | 0.9529 |
| COMPARE | 21 | 41 | 1.0000 | 1.0000 |
| VALID_AT_DATE | 7 | 7 | 1.0000 | 1.0000 |
| HISTORICAL_REFERENCE | 5 | 5 | 1.0000 | 1.0000 |
| multi-evidence | 44 | 70 | 0.9857 | 0.9714 |
| multi-document | 6 | 12 | 0.9167 | 0.8333 |
| tables | 18 | 18 | 1.0000 | 1.0000 |
| adversarial | 3 | 6 | 1.0000 | 1.0000 |
| temporal-authority | 3 | 3 | 1.0000 | 1.0000 |

## Expected-rank distributions

- k=1: median=1.0, p90=3, outside top 15=1, ranks 16–20=0, 21–30=0, >30=1
- k=5: median=1.0, p90=3, outside top 15=1, ranks 16–20=0, 21–30=0, >30=1
- k=60: median=1.0, p90=4, outside top 15=4, ranks 16–20=0, 21–30=4, >30=0

## Conclusion

- Best retained-count region: `[1, 5]`
- Proposed k for a controlled end-to-end engineering experiment: `5`
- Engineering-only offline evidence; no production change is authorised without a controlled end-to-end experiment.
