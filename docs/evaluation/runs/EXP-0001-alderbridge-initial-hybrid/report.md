# Evaluation run: EXP-0001-alderbridge-initial-hybrid

**Status:** EXPERIMENTAL

## Run summary

| Field | Value |
|---|---|
| Description | First truthful V2 engineering-split dense versus hybrid retrieval baseline |
| Executed at | `2026-08-11T14:19:20Z` |
| Repository commit | `95b04a1177dd3fc1d19eae05b17d4273b59343cb` |
| Working tree | `dirty` |
| Benchmark | `dolved-care-engineering` / `v2` |
| Benchmark digest | `aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d` |
| Corpus | `v2` / `aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d` |
| Split | `1` / `fca770615b5fbf20e81b494454969d54dbab2bfa66abf728455e95832b57465f` |
| Harness | `retrieval-evaluation-v1` |
| Threshold policy | `11386497e6316bf199abb75ad0c6ca8baaafe759c297d5044dfc7ce07630eb21` |

## Exact tested configuration

### Provider/model lineage

| Component | Configuration |
|---|---|
| dense | `{"adapter_version":"1","dimensions":1024,"embedding_profile_fingerprint":"ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c","model":"voyage-4-large","provider":"voyage"}` |
| fusion | `{"rrf_k":60,"strategy":"rrf","version":"1"}` |
| reranking | `{"adapter_version":"1","model":"rerank-2.5","provider":"voyage"}` |
| sparse | `{"adapter_version":"1","model":"prithivida/Splade_PP_en_v1","model_revision":"efcd182bc7eb351e81a9445752d4388c2bab500b","provider":"fastembed","sparse_profile_fingerprint":"e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d"}` |

### Candidate pipeline

| Setting | Value |
|---|---:|
| dense_candidate_k | `40` |
| evidence_threshold | `0.337890625` |
| final_evidence_k | `5` |
| fusion_candidate_k | `15` |
| reranker_candidate_k | `15` |
| rrf_k | `60` |
| sparse_candidate_k | `40` |

## Headline metrics

| Metric | Value |
|---|---:|
| Recall@K | 0.0569 |
| Precision@K | 0.0008 |
| MRR | 0.0041 |
| nDCG@K | 0.0569 |
| Planner accuracy | 0.0000 |
| Eligibility accuracy | 0.0081 |
| Outcome accuracy | 0.0325 |

## Planner reliability

| Measure | Value |
|---|---:|
| Total variants | 126 |
| Successful planner variants | 87 |
| Planner failures | 39 |
| Planner reliability | 0.6905 |
| Retrieval metric population | 87 |

Failure categories: `invalid_typed_plan`: 39

Retrieval metrics are computed only over variants where planning succeeded and retrieval ran. Planner hard failures remain separate and cannot be offset by retrieval averages.

## Baseline comparison

Baseline: `EXP-0001-alderbridge-initial-hybrid-dense`

| Metric | Baseline | Candidate | Delta |
|---|---:|---:|---:|
| Recall@K | 0.0569 | 0.0569 | +0.0000 |
| Precision@K | 0.0008 | 0.0008 | +0.0000 |
| MRR | 0.0041 | 0.0041 | +0.0000 |
| nDCG@K | 0.0569 | 0.0569 | +0.0000 |

## Slice metrics

| Slice | Cases | Recall | Precision | MRR | nDCG | Planner | Eligibility | Outcome |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| COMPARE | 7 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| COSHH-alias | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| CURRENT | 30 | 0.0805 | 0.0011 | 0.0057 | 0.0805 | 0.0000 | 0.0115 | 0.0460 |
| ICO-alias | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| MCA-alias | 3 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| RIDDOR-alias | 2 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| VALID_AT_DATE | 5 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| abbreviation | 4 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| adversarial | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| ambiguous-alias | 1 | 1.0000 | 0.0000 | 0.0000 | 1.0000 | 0.0000 | 0.0000 | 1.0000 |
| applicability | 4 | 0.2500 | 0.0000 | 0.0000 | 0.2500 | 0.0000 | 0.0000 | 0.2500 |
| clarification | 1 | 1.0000 | 0.0000 | 0.0000 | 1.0000 | 0.0000 | 0.0000 | 1.0000 |
| colloquial | 2 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| conflicting-guidance | 3 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| descendant-site | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| forms | 4 | 0.0833 | 0.0083 | 0.0417 | 0.0833 | 0.0000 | 0.0833 | 0.0833 |
| historical | 5 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| keyword-stuffing | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| location-alias | 3 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| long-form | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| multi-document | 3 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| multi-evidence | 16 | 0.0208 | 0.0021 | 0.0104 | 0.0208 | 0.0000 | 0.0208 | 0.0208 |
| near-duplicate | 2 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| near-numeric-values | 5 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| near-time-values | 7 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| near-version-duplicate | 14 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| negative-exclusion | 2 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| negative-instruction | 3 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| never-authoritative | 2 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| numeric-boundary | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| numeric-range | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| predecessor-resurrection | 1 | 1.0000 | 0.0000 | 0.0000 | 1.0000 | 0.0000 | 0.0000 | 0.0000 |
| prose | 2 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| region-specific | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| regional-applicability | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| role-alias | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| scheduled-future | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| site-specific | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| table-evidence | 3 | 0.1111 | 0.0111 | 0.0556 | 0.1111 | 0.0000 | 0.1111 | 0.1111 |
| tables | 3 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| temporal-authority | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| withdrawn | 2 | 0.5000 | 0.0000 | 0.0000 | 0.5000 | 0.0000 | 0.0000 | 0.0000 |
| withdrawn-before-authority | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| zero-evidence | 2 | 1.0000 | 0.0000 | 0.0000 | 1.0000 | 0.0000 | 0.0000 | 0.5000 |

## Hard failures

- `eligibility_mismatch`
- `outcome_mismatch`
- `planner_failure:invalid_typed_plan:complaints.handling.current-deadlines:direct`
- `planner_failure:invalid_typed_plan:gdpr.data-protection.current-reporting:direct`
- `planner_failure:invalid_typed_plan:health-safety.accident.current-riddor-timing:colloquial`
- `planner_failure:invalid_typed_plan:health-safety.accident.current-riddor-timing:direct`
- `planner_failure:invalid_typed_plan:health-safety.accident.current-riddor-timing:expanded`
- `planner_failure:invalid_typed_plan:health-safety.accident.valid-at-date:contrast`
- `planner_failure:invalid_typed_plan:health-safety.accident.valid-at-date:dated`
- `planner_failure:invalid_typed_plan:health-safety.coshh.review-trigger:direct`
- `planner_failure:invalid_typed_plan:health-safety.moving-handling.compare:compare`
- `planner_failure:invalid_typed_plan:hr.annual-leave.compare:direct`
- `planner_failure:invalid_typed_plan:hr.annual-leave.current-notice:direct`
- `planner_failure:invalid_typed_plan:hr.annual-leave.current-notice:table`
- `planner_failure:invalid_typed_plan:hr.annual-leave.valid-at-date:contrast`
- `planner_failure:invalid_typed_plan:hr.annual-leave.valid-at-date:dated`
- `planner_failure:invalid_typed_plan:infection.outbreak.valid-before-withdrawal:contrast`
- `planner_failure:invalid_typed_plan:infection.outbreak.valid-before-withdrawal:dated`
- `planner_failure:invalid_typed_plan:medication.controlled-drugs.current-discrepancy:direct`
- `planner_failure:invalid_typed_plan:medication.controlled-drugs.valid-at-date:contrast`
- `planner_failure:invalid_typed_plan:medication.controlled-drugs.valid-at-date:dated`
- `planner_failure:invalid_typed_plan:medication.prn.minimum-interval:direct`
- `planner_failure:invalid_typed_plan:pilot.compare.medication-administration:direct`
- `planner_failure:invalid_typed_plan:pilot.current.medication-administration:abbreviation`
- `planner_failure:invalid_typed_plan:pilot.current.medication-administration:colloquial`
- `planner_failure:invalid_typed_plan:pilot.current.scheduled-medication-version:colloquial`
- `planner_failure:invalid_typed_plan:pilot.current.scheduled-medication-version:direct`
- `planner_failure:invalid_typed_plan:pilot.current.withdrawn-before-authority:direct`
- `planner_failure:invalid_typed_plan:pilot.current.withdrawn-no-resurrection:direct`
- `planner_failure:invalid_typed_plan:pilot.location-alias.bristol:alias`
- `planner_failure:invalid_typed_plan:pilot.multi-document.medication-storage:direct`
- `planner_failure:invalid_typed_plan:pilot.valid-at-date.medication-administration:dated`
- `planner_failure:invalid_typed_plan:pilot.valid-at-date.medication-administration:historical`
- `planner_failure:invalid_typed_plan:safeguarding.allegations.compare-process:colloquial`
- `planner_failure:invalid_typed_plan:safeguarding.allegations.compare-process:compare`
- `planner_failure:invalid_typed_plan:safeguarding.allegations.current-hr-timing:direct`
- `planner_failure:invalid_typed_plan:safeguarding.body-map.observable-facts:direct`
- `planner_failure:invalid_typed_plan:safeguarding.capacity.unwise-decision:colloquial`
- `planner_failure:invalid_typed_plan:safeguarding.covert-medication.multi-document:colloquial`
- `planner_failure:invalid_typed_plan:training.medication.compare:compare`
- `planner_failure:invalid_typed_plan:training.medication.current-rounds:direct`
- `planner_mismatch`

## Operational metrics

```json
{
  "dense": {
    "latency_ms": {
      "max": 38739.652392,
      "median": 7454.992544,
      "min": 4477.687085
    },
    "provider_cost": 0,
    "request_count": 39,
    "token_usage": 0
  },
  "hybrid": {
    "latency_ms": {
      "max": 38739.652392,
      "median": 7454.992544,
      "min": 4477.687085
    },
    "provider_cost": 0,
    "request_count": 40,
    "token_usage": 7016
  },
  "usage_note": "Reranker input tokens are provider-reported. Planner and query-embedding usage are not exposed by the current rc1 responses."
}
```

## Strongest improvements and regressions

### Regressions

None.

### Improvements

None.

## Case-level drill-down

### `complaints.handling.compare` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did complaint handling get faster?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `complaints.compare-current` | `family.complaints.handling` | `doc.complaints.handling.v2` | documents/complaints/handling-v2.md |
| COMPARISON | `complaints.compare-old` | `family.complaints.handling` | `doc.complaints.handling.v1` | documents/complaints/handling-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `complaints.handling.compare` / `compare`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare version 1 and version 2 complaint deadlines.
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `complaints.compare-current` | `family.complaints.handling` | `doc.complaints.handling.v2` | documents/complaints/handling-v2.md |
| COMPARISON | `complaints.compare-old` | `family.complaints.handling` | `doc.complaints.handling.v1` | documents/complaints/handling-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `complaints.handling.compare` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How did complaint response times change from the old policy?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `complaints.compare-current` | `family.complaints.handling` | `doc.complaints.handling.v2` | documents/complaints/handling-v2.md |
| COMPARISON | `complaints.compare-old` | `family.complaints.handling` | `doc.complaints.handling.v1` | documents/complaints/handling-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `complaints.handling.current-deadlines` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How quickly should we reply to a complaint now?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `complaints.handling.v2-deadlines` | `family.complaints.handling` | `doc.complaints.handling.v2` | documents/complaints/handling-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `complaints.handling.current-deadlines` / `contrast`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is the acknowledgement deadline three days or two?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `complaints.handling.v2-deadlines` | `family.complaints.handling` | `doc.complaints.handling.v2` | documents/complaints/handling-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `complaints.handling.current-deadlines` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What are the current complaint acknowledgement and response targets?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:complaints.handling.current-deadlines:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `complaints.handling.v2-deadlines` | `family.complaints.handling` | `doc.complaints.handling.v2` | documents/complaints/handling-v2.md |

#### PRIMARY

### `gdpr.breach.ico-owner` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do I tell the regulator myself after a privacy incident?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.breach.dpo-decision` | `family.gdpr.breach` | `doc.gdpr.breach.v1` | documents/gdpr/personal-data-breach.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.breach.ico-owner` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Who decides whether a data breach is reported to the ICO?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.breach.dpo-decision` | `family.gdpr.breach` | `doc.gdpr.breach.v1` | documents/gdpr/personal-data-breach.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.breach.ico-owner` / `timing`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Should frontline staff contact the ICO within 72 hours?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.breach.dpo-decision` | `family.gdpr.breach` | `doc.gdpr.breach.v1` | documents/gdpr/personal-data-breach.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.data-protection.compare` / `change`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did data-loss reporting change from 24 hours to four hours?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.policy.compare-current` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v2` | documents/gdpr/data-protection-v2.md |
| COMPARISON | `gdpr.policy.compare-old` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v1` | documents/gdpr/data-protection-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.data-protection.compare` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare the old and current privacy incident reporting deadlines.
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.policy.compare-current` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v2` | documents/gdpr/data-protection-v2.md |
| COMPARISON | `gdpr.policy.compare-old` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v1` | documents/gdpr/data-protection-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.data-protection.compare` / `history`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What changed in the data protection policy?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.policy.compare-current` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v2` | documents/gdpr/data-protection-v2.md |
| COMPARISON | `gdpr.policy.compare-old` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v1` | documents/gdpr/data-protection-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.data-protection.current-reporting` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How fast do I tell privacy about a data mistake?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.policy.v2-four-hours` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v2` | documents/gdpr/data-protection-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.data-protection.current-reporting` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How quickly must suspected personal-data loss be reported now?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:gdpr.data-protection.current-reporting:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.policy.v2-four-hours` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v2` | documents/gdpr/data-protection-v2.md |

#### PRIMARY

### `gdpr.data-protection.current-reporting` / `email`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: I sent information to the wrong person — when do I report it?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.policy.v2-four-hours` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v2` | documents/gdpr/data-protection-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.accident.current-riddor-timing` / `colloquial`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How soon do we tell safety about something that might need RIDDOR reporting?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:health-safety.accident.current-riddor-timing:colloquial`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.riddor.v2-one-day` | `family.health-safety.accident-reporting` | `doc.health-safety.accident-reporting.v2` | documents/health-safety/accident-reporting-v2.md |

#### PRIMARY

### `health-safety.accident.current-riddor-timing` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How quickly must a possible RIDDOR incident reach the health and safety lead now?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:health-safety.accident.current-riddor-timing:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.riddor.v2-one-day` | `family.health-safety.accident-reporting` | `doc.health-safety.accident-reporting.v2` | documents/health-safety/accident-reporting-v2.md |

#### PRIMARY

### `health-safety.accident.current-riddor-timing` / `expanded`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What is the current deadline for escalating a potentially reportable incident?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:health-safety.accident.current-riddor-timing:expanded`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.riddor.v2-one-day` | `family.health-safety.accident-reporting` | `doc.health-safety.accident-reporting.v2` | documents/health-safety/accident-reporting-v2.md |

#### PRIMARY

### `health-safety.accident.valid-at-date` / `contrast`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Was the safety-lead deadline two working days in 2024?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:health-safety.accident.valid-at-date:contrast`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.riddor.v1-two-days` | `family.health-safety.accident-reporting` | `doc.health-safety.accident-reporting.v1` | documents/health-safety/accident-reporting-v1.md |

#### PRIMARY

### `health-safety.accident.valid-at-date` / `dated`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What RIDDOR escalation deadline applied in January 2024?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:health-safety.accident.valid-at-date:dated`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.riddor.v1-two-days` | `family.health-safety.accident-reporting` | `doc.health-safety.accident-reporting.v1` | documents/health-safety/accident-reporting-v1.md |

#### PRIMARY

### `health-safety.accident.valid-at-date` / `historical`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How long did managers have under the old accident procedure?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.riddor.v1-two-days` | `family.health-safety.accident-reporting` | `doc.health-safety.accident-reporting.v1` | documents/health-safety/accident-reporting-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.coshh.review-trigger` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: The cleaning chemical changed — can we wait for the annual COSHH review?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.coshh.review` | `family.health-safety.coshh` | `doc.health-safety.coshh.v1` | documents/health-safety/coshh-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.coshh.review-trigger` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When must a COSHH assessment be reviewed?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:health-safety.coshh.review-trigger:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.coshh.review` | `family.health-safety.coshh` | `doc.health-safety.coshh.v1` | documents/health-safety/coshh-procedure.md |

#### PRIMARY

### `health-safety.coshh.review-trigger` / `product`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do we need a new hazardous-substance assessment when a product formulation changes?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.coshh.review` | `family.health-safety.coshh` | `doc.health-safety.coshh.v1` | documents/health-safety/coshh-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.moving-handling.compare` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did the old policy say two staff for every hoist?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.hoist.v2-assessed` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v2` | documents/health-safety/moving-handling-v2.md |
| COMPARISON | `health-safety.hoist.v1-universal-two` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v1` | documents/health-safety/moving-handling-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.moving-handling.compare` / `compare`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare old and current requirements for two-person hoist transfers.
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:health-safety.moving-handling.compare:compare`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.hoist.v2-assessed` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v2` | documents/health-safety/moving-handling-v2.md |
| COMPARISON | `health-safety.hoist.v1-universal-two` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v1` | documents/health-safety/moving-handling-v1.md |

#### PRIMARY

#### COMPARISON

### `health-safety.moving-handling.compare` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How did the hoist staffing rule change from the previous policy?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.hoist.v2-assessed` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v2` | documents/health-safety/moving-handling-v2.md |
| COMPARISON | `health-safety.hoist.v1-universal-two` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v1` | documents/health-safety/moving-handling-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.moving-handling.current-staffing` / `assessment`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What decides whether one or two trained staff perform a hoist transfer?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.hoist.assessment-controls-staffing` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v2` | documents/health-safety/moving-handling-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.moving-handling.current-staffing` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is two carers always the rule for using a hoist?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.hoist.assessment-controls-staffing` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v2` | documents/health-safety/moving-handling-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.moving-handling.current-staffing` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do all hoist transfers require two staff under the current policy?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.hoist.assessment-controls-staffing` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v2` | documents/health-safety/moving-handling-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.annual-leave.compare` / `allowance`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How did the leave allowance and booking notice change?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.compare-current` | `family.hr.annual-leave` | `doc.hr.annual-leave.v2` | documents/hr/annual-leave-v2.md |
| COMPARISON | `hr.leave.compare-old` | `family.hr.annual-leave` | `doc.hr.annual-leave.v1` | documents/hr/annual-leave-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.annual-leave.compare` / `change`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What changed for booking a week off?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.compare-current` | `family.hr.annual-leave` | `doc.hr.annual-leave.v2` | documents/hr/annual-leave-v2.md |
| COMPARISON | `hr.leave.compare-old` | `family.hr.annual-leave` | `doc.hr.annual-leave.v1` | documents/hr/annual-leave-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.annual-leave.compare` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare the current annual leave notice rule with the previous policy.
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:hr.annual-leave.compare:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.compare-current` | `family.hr.annual-leave` | `doc.hr.annual-leave.v2` | documents/hr/annual-leave-v2.md |
| COMPARISON | `hr.leave.compare-old` | `family.hr.annual-leave` | `doc.hr.annual-leave.v1` | documents/hr/annual-leave-v1.md |

#### PRIMARY

#### COMPARISON

### `hr.annual-leave.current-notice` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How early should I book five days off?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.v2-six-weeks` | `family.hr.annual-leave` | `doc.hr.annual-leave.v2` | documents/hr/annual-leave-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.annual-leave.current-notice` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How much notice do I need now for a week of annual leave?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:hr.annual-leave.current-notice:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.v2-six-weeks` | `family.hr.annual-leave` | `doc.hr.annual-leave.v2` | documents/hr/annual-leave-v2.md |

#### PRIMARY

### `hr.annual-leave.current-notice` / `table`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What is the current notice period for five working days' holiday?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:hr.annual-leave.current-notice:table`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.v2-six-weeks` | `family.hr.annual-leave` | `doc.hr.annual-leave.v2` | documents/hr/annual-leave-v2.md |

#### PRIMARY

### `hr.annual-leave.valid-at-date` / `contrast`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Was the allowance 28 days in 2024?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:hr.annual-leave.valid-at-date:contrast`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.v1-allowance` | `family.hr.annual-leave` | `doc.hr.annual-leave.v1` | documents/hr/annual-leave-v1.md |

#### PRIMARY

### `hr.annual-leave.valid-at-date` / `dated`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How much leave did full-time staff receive in June 2024?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:hr.annual-leave.valid-at-date:dated`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.v1-allowance` | `family.hr.annual-leave` | `doc.hr.annual-leave.v1` | documents/hr/annual-leave-v1.md |

#### PRIMARY

### `hr.annual-leave.valid-at-date` / `old`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What was the annual leave allowance under version 1?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.v1-allowance` | `family.hr.annual-leave` | `doc.hr.annual-leave.v1` | documents/hr/annual-leave-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.disciplinary.suspension-neutral` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Does being suspended mean the allegation is proven?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.disciplinary.suspension` | `family.hr.disciplinary` | `doc.hr.disciplinary.v1` | documents/hr/disciplinary-policy.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.disciplinary.suspension-neutral` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is suspension a disciplinary punishment?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.disciplinary.suspension` | `family.hr.disciplinary` | `doc.hr.disciplinary.v1` | documents/hr/disciplinary-policy.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.disciplinary.suspension-neutral` / `review`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How often must a precautionary suspension be reviewed?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.disciplinary.suspension` | `family.hr.disciplinary` | `doc.hr.disciplinary.v1` | documents/hr/disciplinary-policy.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.lone-worker.coventry-overdue` / `alias`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What happens when a Coventry community worker misses their check-out?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.lone-worker.overdue-sequence` | `family.hr.lone-worker-welfare` | `doc.hr.lone-worker-welfare.v1` | documents/hr/midlands-lone-worker-welfare.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.lone-worker.coventry-overdue` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Our lone worker is 15 minutes late checking in — what next?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.lone-worker.overdue-sequence` | `family.hr.lone-worker-welfare` | `doc.hr.lone-worker-welfare.v1` | documents/hr/midlands-lone-worker-welfare.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.lone-worker.coventry-overdue` / `timing`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When does the Midlands coordinator escalate an overdue welfare check?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.lone-worker.overdue-sequence` | `family.hr.lone-worker-welfare` | `doc.hr.lone-worker-welfare.v1` | documents/hr/midlands-lone-worker-welfare.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `infection.outbreak.valid-before-withdrawal` / `contrast`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Was twice-daily symptom monitoring authoritative in January 2026?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:infection.outbreak.valid-before-withdrawal:contrast`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `infection.outbreak.v2.twice-daily` | `family.infection.outbreak-management` | `doc.infection.outbreak-management.v2` | documents/infection-control/outbreak-management-v2-withdrawn.md |

#### PRIMARY

### `infection.outbreak.valid-before-withdrawal` / `dated`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What outbreak monitoring applied on 1 January 2026?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:infection.outbreak.valid-before-withdrawal:dated`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `infection.outbreak.v2.twice-daily` | `family.infection.outbreak-management` | `doc.infection.outbreak-management.v2` | documents/infection-control/outbreak-management-v2-withdrawn.md |

#### PRIMARY

### `infection.outbreak.valid-before-withdrawal` / `historical`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Before it was withdrawn, what did outbreak version 2 require?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `infection.outbreak.v2.twice-daily` | `family.infection.outbreak-management` | `doc.infection.outbreak-management.v2` | documents/infection-control/outbreak-management-v2-withdrawn.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.controlled-drugs.current-discrepancy` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: The CD count is wrong — what do we do straight away?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.cd.immediate-escalation` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v2` | documents/medication/controlled-drugs-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.controlled-drugs.current-discrepancy` / `contrast`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Can a controlled drugs stock mismatch wait until shift end?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.cd.immediate-escalation` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v2` | documents/medication/controlled-drugs-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.controlled-drugs.current-discrepancy` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When must a controlled-drug discrepancy be escalated now?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:medication.controlled-drugs.current-discrepancy:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.cd.immediate-escalation` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v2` | documents/medication/controlled-drugs-v2.md |

#### PRIMARY

### `medication.controlled-drugs.valid-at-date` / `contrast`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did the 2023 procedure allow reporting by the end of the shift?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:medication.controlled-drugs.valid-at-date:contrast`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.cd.v1.shift-end` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v1` | documents/medication/controlled-drugs-v1.md |

#### PRIMARY

### `medication.controlled-drugs.valid-at-date` / `dated`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: In January 2024, when did a controlled-drug discrepancy have to be reported?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:medication.controlled-drugs.valid-at-date:dated`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.cd.v1.shift-end` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v1` | documents/medication/controlled-drugs-v1.md |

#### PRIMARY

### `medication.controlled-drugs.valid-at-date` / `historical`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What was the old CD stock discrepancy deadline?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.cd.v1.shift-end` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v1` | documents/medication/controlled-drugs-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.covert.capacity-requirements` / `abbreviation`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Does covert medication need an MCA and best-interests decision?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.covert.required-decisions` | `family.medication.covert` | `doc.medication.covert.v1` | documents/medication/covert-administration-policy.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.covert.capacity-requirements` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What approvals are required before medicine can be given covertly?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.covert.required-decisions` | `family.medication.covert` | `doc.medication.covert.v1` | documents/medication/covert-administration-policy.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.covert.capacity-requirements` / `refusal`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Can we hide medicine in food because a resident refuses it?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.covert.required-decisions` | `family.medication.covert` | `doc.medication.covert.v1` | documents/medication/covert-administration-policy.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.error-form.immediate-safety` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What details do I write down after a meds mistake?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.error-form.fields` | `family.medication.errors` | `doc.medication.errors.v1` | documents/medication/medication-error-form.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.error-form.immediate-safety` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What goes on the medication error form?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.error-form.fields` | `family.medication.errors` | `doc.medication.errors.v1` | documents/medication/medication-error-form.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.error-form.immediate-safety` / `priority`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Should I finish the medicines incident form before calling for clinical advice?
- Covered EvidenceUnits: `medication.error-form.fields`
- Metrics: recall=1.0000, precision=0.1000, MRR=0.5000, nDCG=1.0000
- Hard failures: `planner_mismatch`

  - COMPARISON: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.error-form.fields` | `family.medication.errors` | `doc.medication.errors.v1` | documents/medication/medication-error-form.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=8 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | #1 / 0.525439 | #1 / 14.730900 | #1 / 0.032787 | #1 / 0.824219 | pass | yes | medication.error-form.fields |
| `b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef`<br>`b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.473696 | #2 / 12.347264 | #2 / 0.032258 | #2 / 0.640625 | pass | yes | none |
| `fc1749ce-678f-5b79-9a27-41ca33d2043c`<br>`fc1749ce-678f-5b79-9a27-41ca33d2043c` | `family.medication.prn`<br>`doc.medication.prn.v1` | #4 / 0.417054 | #12 / 7.108753 | #7 / 0.029514 | #3 / 0.482422 | pass | yes | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.383904 | #3 / 10.729283 | #3 / 0.031025 | #4 / 0.460938 | pass | yes | none |
| `799b04a0-74e1-5134-a911-0c2ccbda4c15`<br>`799b04a0-74e1-5134-a911-0c2ccbda4c15` | `family.medication.administration`<br>`doc.medication.administration.v2` | #5 / 0.404992 | #4 / 9.707485 | #4 / 0.031010 | #5 / 0.445312 | pass | yes | none |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.444898 | #8 / 8.276126 | #5 / 0.030579 | #6 / 0.435547 | pass | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #8 / 0.370204 | #5 / 9.192083 | #6 / 0.030090 | #7 / 0.371094 | pass | no | none |
| `8d8de832-6d4c-5368-b209-2ece5159b021`<br>`8d8de832-6d4c-5368-b209-2ece5159b021` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #7 / 0.378414 | #11 / 7.160558 | #8 / 0.029010 | #8 / 0.351562 | pass | no | none |
| `3cc16b3c-7d04-53a9-a273-eddea88a3ccb`<br>`3cc16b3c-7d04-53a9-a273-eddea88a3ccb` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #29 / 0.260764 | #7 / 8.750044 | #14 / 0.026161 | #9 / 0.328125 | fail | no | none |
| `249cc883-6c9a-5099-bdbb-974f04227e23`<br>`249cc883-6c9a-5099-bdbb-974f04227e23` | `family.complaints.form`<br>`doc.complaints.form.v1` | #11 / 0.337760 | #13 / 6.943173 | #12 / 0.027783 | #10 / 0.324219 | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #9 / 0.353747 | #14 / 6.183732 | #10 / 0.028006 | #11 / 0.324219 | fail | no | none |
| `47a813db-42a0-5b2b-9631-4c30ef6d0306`<br>`47a813db-42a0-5b2b-9631-4c30ef6d0306` | `family.medication.storage`<br>`doc.medication.storage.v1` | #14 / 0.304390 | #10 / 7.345437 | #11 / 0.027799 | #12 / 0.324219 | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #12 / 0.328261 | #15 / 6.140487 | #13 / 0.027222 | #13 / 0.292969 | fail | no | none |
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | #17 / 0.301042 | #6 / 9.172563 | #9 / 0.028139 | #14 / 0.287109 | fail | no | none |
| `1f7baac6-5792-5b2a-9399-26ad4c21d6e4`<br>`1f7baac6-5792-5b2a-9399-26ad4c21d6e4` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #16 / 0.301973 | #17 / 5.965632 | #15 / 0.026145 | #15 / 0.275391 | fail | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #10 / 0.343136 | #27 / 4.927704 | — | — | fail | no | none |
| `56745918-8c2b-5490-a300-4c18bf32a5c6`<br>`56745918-8c2b-5490-a300-4c18bf32a5c6` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #13 / 0.320774 | #32 / 3.841107 | — | — | fail | no | none |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #15 / 0.302728 | #40 / 3.450078 | — | — | fail | no | none |
| `1a330d42-d249-5bf6-ba4b-066222bc5f5b`<br>`1a330d42-d249-5bf6-ba4b-066222bc5f5b` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #18 / 0.299523 | #26 / 4.982963 | — | — | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #19 / 0.290163 | — | — | — | fail | no | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #20 / 0.283594 | #23 / 5.154037 | — | — | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #21 / 0.280891 | #16 / 6.133651 | — | — | fail | no | none |
| `801b4c5b-787b-5e04-99ca-83dd8844448d`<br>`801b4c5b-787b-5e04-99ca-83dd8844448d` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #22 / 0.272433 | #25 / 5.031039 | — | — | fail | no | none |
| `256e756b-7110-5070-9432-97bb1923a202`<br>`256e756b-7110-5070-9432-97bb1923a202` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #23 / 0.271659 | #38 / 3.655278 | — | — | fail | no | none |
| `ee3b92cf-7201-50f5-9315-841d5bceb277`<br>`ee3b92cf-7201-50f5-9315-841d5bceb277` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #24 / 0.271644 | #33 / 3.832925 | — | — | fail | no | none |
| `ee3bb1bd-f03f-5314-b408-a1895aaadc2e`<br>`ee3bb1bd-f03f-5314-b408-a1895aaadc2e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #25 / 0.271534 | #18 / 5.785956 | — | — | fail | no | none |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #26 / 0.267529 | — | — | — | fail | no | none |
| `f85e71bc-4d62-57d9-b403-b13b1a9ff199`<br>`f85e71bc-4d62-57d9-b403-b13b1a9ff199` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #27 / 0.265415 | #36 / 3.754916 | — | — | fail | no | none |
| `14ab94b0-4ade-5c5c-b5bd-77eae8daf94d`<br>`14ab94b0-4ade-5c5c-b5bd-77eae8daf94d` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #28 / 0.264270 | #30 / 4.580947 | — | — | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #30 / 0.255053 | — | — | — | fail | no | none |
| `f193cb26-bd92-5fb8-a0b1-ba2c829f658b`<br>`f193cb26-bd92-5fb8-a0b1-ba2c829f658b` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #31 / 0.244966 | — | — | — | fail | no | none |
| `f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e`<br>`f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #32 / 0.243881 | — | — | — | fail | no | none |
| `1839469e-5726-503f-a711-a010a97420fd`<br>`1839469e-5726-503f-a711-a010a97420fd` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #33 / 0.243034 | #22 / 5.419125 | — | — | fail | no | none |
| `f4b9f291-51c7-5e35-9335-b7e3dd2b37ef`<br>`f4b9f291-51c7-5e35-9335-b7e3dd2b37ef` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #34 / 0.239229 | #31 / 4.203591 | — | — | fail | no | none |
| `3ffac08e-eebd-5bf7-963c-116ad06e0312`<br>`3ffac08e-eebd-5bf7-963c-116ad06e0312` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #35 / 0.237846 | #39 / 3.651207 | — | — | fail | no | none |
| `635ff5e9-ecb1-559b-8683-4b7a96ea7bd9`<br>`635ff5e9-ecb1-559b-8683-4b7a96ea7bd9` | `family.fire.drills`<br>`doc.fire.drills.v2` | #36 / 0.232817 | — | — | — | fail | no | none |
| `5a87d328-f076-5953-aa2e-8d7963341f74`<br>`5a87d328-f076-5953-aa2e-8d7963341f74` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #37 / 0.231562 | — | — | — | fail | no | none |
| `ac335280-6bca-5150-bd9b-db2d198ca588`<br>`ac335280-6bca-5150-bd9b-db2d198ca588` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #38 / 0.231458 | #21 / 5.538192 | — | — | fail | no | none |
| `42e10f18-8de2-53bd-8487-f46c454bf735`<br>`42e10f18-8de2-53bd-8487-f46c454bf735` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #39 / 0.230137 | #19 / 5.752685 | — | — | fail | no | none |
| `ccc94945-e377-526e-93c2-5fd324619661`<br>`ccc94945-e377-526e-93c2-5fd324619661` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | #40 / 0.230117 | #29 / 4.622115 | — | — | fail | no | none |
| `4d1f0d61-d751-52f0-87dd-0327ea89db4e`<br>`4d1f0d61-d751-52f0-87dd-0327ea89db4e` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #9 / 7.892474 | — | — | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | — | #20 / 5.635427 | — | — | fail | no | none |
| `0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6`<br>`0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | — | #24 / 5.128980 | — | — | fail | no | none |
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #28 / 4.807023 | — | — | fail | no | none |
| `f1b2325d-4bb3-581b-8d14-7b8cdd43f216`<br>`f1b2325d-4bb3-581b-8d14-7b8cdd43f216` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | — | #34 / 3.820559 | — | — | fail | no | none |
| `be5c3624-95a2-5d5d-9f05-a9fb635d68a6`<br>`be5c3624-95a2-5d5d-9f05-a9fb635d68a6` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | — | #35 / 3.781827 | — | — | fail | no | none |
| `aeb0ea01-92b2-5418-ad27-c95cacb3b030`<br>`aeb0ea01-92b2-5418-ad27-c95cacb3b030` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | — | #37 / 3.677134 | — | — | fail | no | none |

#### COMPARISON

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=8 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | #1 / 0.525439 | #1 / 14.730900 | #1 / 0.032787 | #1 / 0.824219 | pass | yes | none |
| `b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef`<br>`b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.473696 | #2 / 12.347264 | #2 / 0.032258 | #2 / 0.640625 | pass | yes | none |
| `fc1749ce-678f-5b79-9a27-41ca33d2043c`<br>`fc1749ce-678f-5b79-9a27-41ca33d2043c` | `family.medication.prn`<br>`doc.medication.prn.v1` | #4 / 0.417054 | #12 / 7.108753 | #7 / 0.029514 | #3 / 0.482422 | pass | yes | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.383904 | #3 / 10.729283 | #3 / 0.031025 | #4 / 0.460938 | pass | yes | none |
| `799b04a0-74e1-5134-a911-0c2ccbda4c15`<br>`799b04a0-74e1-5134-a911-0c2ccbda4c15` | `family.medication.administration`<br>`doc.medication.administration.v2` | #5 / 0.404992 | #4 / 9.707485 | #4 / 0.031010 | #5 / 0.445312 | pass | yes | none |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.444898 | #8 / 8.276126 | #5 / 0.030579 | #6 / 0.435547 | pass | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #8 / 0.370204 | #5 / 9.192083 | #6 / 0.030090 | #7 / 0.371094 | pass | no | none |
| `8d8de832-6d4c-5368-b209-2ece5159b021`<br>`8d8de832-6d4c-5368-b209-2ece5159b021` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #7 / 0.378414 | #11 / 7.160558 | #8 / 0.029010 | #8 / 0.351562 | pass | no | none |
| `3cc16b3c-7d04-53a9-a273-eddea88a3ccb`<br>`3cc16b3c-7d04-53a9-a273-eddea88a3ccb` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #29 / 0.260764 | #7 / 8.750044 | #14 / 0.026161 | #9 / 0.328125 | fail | no | none |
| `249cc883-6c9a-5099-bdbb-974f04227e23`<br>`249cc883-6c9a-5099-bdbb-974f04227e23` | `family.complaints.form`<br>`doc.complaints.form.v1` | #11 / 0.337760 | #13 / 6.943173 | #12 / 0.027783 | #10 / 0.324219 | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #9 / 0.353747 | #14 / 6.183732 | #10 / 0.028006 | #11 / 0.324219 | fail | no | none |
| `47a813db-42a0-5b2b-9631-4c30ef6d0306`<br>`47a813db-42a0-5b2b-9631-4c30ef6d0306` | `family.medication.storage`<br>`doc.medication.storage.v1` | #14 / 0.304390 | #10 / 7.345437 | #11 / 0.027799 | #12 / 0.324219 | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #12 / 0.328261 | #15 / 6.140487 | #13 / 0.027222 | #13 / 0.292969 | fail | no | none |
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | #17 / 0.301042 | #6 / 9.172563 | #9 / 0.028139 | #14 / 0.287109 | fail | no | none |
| `1f7baac6-5792-5b2a-9399-26ad4c21d6e4`<br>`1f7baac6-5792-5b2a-9399-26ad4c21d6e4` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #16 / 0.301973 | #17 / 5.965632 | #15 / 0.026145 | #15 / 0.275391 | fail | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #10 / 0.343136 | #27 / 4.927704 | — | — | fail | no | none |
| `56745918-8c2b-5490-a300-4c18bf32a5c6`<br>`56745918-8c2b-5490-a300-4c18bf32a5c6` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #13 / 0.320774 | #32 / 3.841107 | — | — | fail | no | none |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #15 / 0.302728 | #40 / 3.450078 | — | — | fail | no | none |
| `1a330d42-d249-5bf6-ba4b-066222bc5f5b`<br>`1a330d42-d249-5bf6-ba4b-066222bc5f5b` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #18 / 0.299523 | #26 / 4.982963 | — | — | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #19 / 0.290163 | — | — | — | fail | no | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #20 / 0.283594 | #23 / 5.154037 | — | — | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #21 / 0.280891 | #16 / 6.133651 | — | — | fail | no | none |
| `801b4c5b-787b-5e04-99ca-83dd8844448d`<br>`801b4c5b-787b-5e04-99ca-83dd8844448d` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #22 / 0.272433 | #25 / 5.031039 | — | — | fail | no | none |
| `256e756b-7110-5070-9432-97bb1923a202`<br>`256e756b-7110-5070-9432-97bb1923a202` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #23 / 0.271659 | #38 / 3.655278 | — | — | fail | no | none |
| `ee3b92cf-7201-50f5-9315-841d5bceb277`<br>`ee3b92cf-7201-50f5-9315-841d5bceb277` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #24 / 0.271644 | #33 / 3.832925 | — | — | fail | no | none |
| `ee3bb1bd-f03f-5314-b408-a1895aaadc2e`<br>`ee3bb1bd-f03f-5314-b408-a1895aaadc2e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #25 / 0.271534 | #18 / 5.785956 | — | — | fail | no | none |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #26 / 0.267529 | — | — | — | fail | no | none |
| `f85e71bc-4d62-57d9-b403-b13b1a9ff199`<br>`f85e71bc-4d62-57d9-b403-b13b1a9ff199` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #27 / 0.265415 | #36 / 3.754916 | — | — | fail | no | none |
| `14ab94b0-4ade-5c5c-b5bd-77eae8daf94d`<br>`14ab94b0-4ade-5c5c-b5bd-77eae8daf94d` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #28 / 0.264270 | #30 / 4.580947 | — | — | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #30 / 0.255053 | — | — | — | fail | no | none |
| `f193cb26-bd92-5fb8-a0b1-ba2c829f658b`<br>`f193cb26-bd92-5fb8-a0b1-ba2c829f658b` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #31 / 0.244966 | — | — | — | fail | no | none |
| `f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e`<br>`f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #32 / 0.243881 | — | — | — | fail | no | none |
| `1839469e-5726-503f-a711-a010a97420fd`<br>`1839469e-5726-503f-a711-a010a97420fd` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #33 / 0.243034 | #22 / 5.419125 | — | — | fail | no | none |
| `f4b9f291-51c7-5e35-9335-b7e3dd2b37ef`<br>`f4b9f291-51c7-5e35-9335-b7e3dd2b37ef` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #34 / 0.239229 | #31 / 4.203591 | — | — | fail | no | none |
| `3ffac08e-eebd-5bf7-963c-116ad06e0312`<br>`3ffac08e-eebd-5bf7-963c-116ad06e0312` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #35 / 0.237846 | #39 / 3.651207 | — | — | fail | no | none |
| `635ff5e9-ecb1-559b-8683-4b7a96ea7bd9`<br>`635ff5e9-ecb1-559b-8683-4b7a96ea7bd9` | `family.fire.drills`<br>`doc.fire.drills.v2` | #36 / 0.232817 | — | — | — | fail | no | none |
| `5a87d328-f076-5953-aa2e-8d7963341f74`<br>`5a87d328-f076-5953-aa2e-8d7963341f74` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #37 / 0.231562 | — | — | — | fail | no | none |
| `ac335280-6bca-5150-bd9b-db2d198ca588`<br>`ac335280-6bca-5150-bd9b-db2d198ca588` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #38 / 0.231458 | #21 / 5.538192 | — | — | fail | no | none |
| `42e10f18-8de2-53bd-8487-f46c454bf735`<br>`42e10f18-8de2-53bd-8487-f46c454bf735` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #39 / 0.230137 | #19 / 5.752685 | — | — | fail | no | none |
| `ccc94945-e377-526e-93c2-5fd324619661`<br>`ccc94945-e377-526e-93c2-5fd324619661` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | #40 / 0.230117 | #29 / 4.622115 | — | — | fail | no | none |
| `4d1f0d61-d751-52f0-87dd-0327ea89db4e`<br>`4d1f0d61-d751-52f0-87dd-0327ea89db4e` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #9 / 7.892474 | — | — | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | — | #20 / 5.635427 | — | — | fail | no | none |
| `0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6`<br>`0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | — | #24 / 5.128980 | — | — | fail | no | none |
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #28 / 4.807023 | — | — | fail | no | none |
| `f1b2325d-4bb3-581b-8d14-7b8cdd43f216`<br>`f1b2325d-4bb3-581b-8d14-7b8cdd43f216` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | — | #34 / 3.820559 | — | — | fail | no | none |
| `be5c3624-95a2-5d5d-9f05-a9fb635d68a6`<br>`be5c3624-95a2-5d5d-9f05-a9fb635d68a6` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | — | #35 / 3.781827 | — | — | fail | no | none |
| `aeb0ea01-92b2-5418-ad27-c95cacb3b030`<br>`aeb0ea01-92b2-5418-ad27-c95cacb3b030` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | — | #37 / 3.677134 | — | — | fail | no | none |

### `medication.fridge.boundary-table` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is eight okay but just over eight too warm?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.fridge.boundaries` | `family.medication.fridge-reference` | `doc.medication.fridge-reference.v1` | documents/medication/fridge-monitoring-reference.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.fridge.boundary-table` / `decimal`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What action is required at 8.1 degrees?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.fridge.boundaries` | `family.medication.fridge-reference` | `doc.medication.fridge-reference.v1` | documents/medication/fridge-monitoring-reference.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.fridge.boundary-table` / `upper`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is a medicines fridge reading of exactly 8°C in range?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.fridge.boundaries` | `family.medication.fridge-reference` | `doc.medication.fridge-reference.v1` | documents/medication/fridge-monitoring-reference.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.prn.minimum-interval` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: It is on the meds chart as needed — is that enough to give it?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.prn.prechecks` | `family.medication.prn` | `doc.medication.prn.v1` | documents/medication/prn-protocol.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.prn.minimum-interval` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What must I check before giving a PRN medicine?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:medication.prn.minimum-interval:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.prn.prechecks` | `family.medication.prn` | `doc.medication.prn.v1` | documents/medication/prn-protocol.md |

#### PRIMARY

### `medication.prn.minimum-interval` / `expanded`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What information is needed before giving when-required medication?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.prn.prechecks` | `family.medication.prn` | `doc.medication.prn.v1` | documents/medication/prn-protocol.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.adversarial.visitor-negative` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Can a visitor use the lift in a fire and skip the reception book?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `visitors.fire-instructions` | `family.visitors.general` | `doc.visitors.general.v1` | documents/visitors/visitors-contractors.md |
| PRIMARY | `visitors.sign-in` | `family.visitors.general` | `doc.visitors.general.v1` | documents/visitors/visitors-contractors.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.adversarial.visitor-negative` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What must a visitor do when the fire alarm sounds?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `visitors.fire-instructions` | `family.visitors.general` | `doc.visitors.general.v1` | documents/visitors/visitors-contractors.md |
| PRIMARY | `visitors.sign-in` | `family.visitors.general` | `doc.visitors.general.v1` | documents/visitors/visitors-contractors.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.adversarial.visitor-negative` / `sign-in`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do visitors have to sign in and what happens during an evacuation?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `visitors.fire-instructions` | `family.visitors.general` | `doc.visitors.general.v1` | documents/visitors/visitors-contractors.md |
| PRIMARY | `visitors.sign-in` | `family.visitors.general` | `doc.visitors.general.v1` | documents/visitors/visitors-contractors.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.applicability.ambiguous-home` / `ambiguous`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `True`
- Expected outcome: `CLARIFICATION_REQUIRED`
- Text capture: `BENCHMARK_TEXT`
- Question: Where is the fire assembly point at the home?
- Covered EvidenceUnits: `none`
- Metrics: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
- Hard failures: `planner_mismatch, eligibility_mismatch`


### `pilot.applicability.ambiguous-home` / `pronoun`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `True`
- Expected outcome: `CLARIFICATION_REQUIRED`
- Text capture: `BENCHMARK_TEXT`
- Question: Which evacuation plan applies there?
- Covered EvidenceUnits: `none`
- Metrics: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
- Hard failures: `planner_mismatch, eligibility_mismatch`


### `pilot.applicability.ambiguous-home` / `underspecified`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `True`
- Expected outcome: `CLARIFICATION_REQUIRED`
- Text capture: `BENCHMARK_TEXT`
- Question: What should visitors do when the alarm sounds at our care home?
- Covered EvidenceUnits: `none`
- Metrics: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
- Hard failures: `planner_mismatch, eligibility_mismatch`


### `pilot.applicability.bristol-conflict` / `conflict`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Why do the South West and Bristol procedures name different assembly points?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.south-west.fallback-condition` | `family.fire.south-west-evacuation` | `doc.fire.south-west-evacuation.v1` | documents/fire-safety/south-west-evacuation.md |
| PRIMARY | `fire.harbour-view.local-override` | `family.fire.harbour-view-evacuation` | `doc.fire.harbour-view-evacuation.v1` | documents/fire-safety/harbour-view-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.applicability.bristol-conflict` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What regional and local fire instructions apply at Harbour View?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.south-west.fallback-condition` | `family.fire.south-west-evacuation` | `doc.fire.south-west-evacuation.v1` | documents/fire-safety/south-west-evacuation.md |
| PRIMARY | `fire.harbour-view.local-override` | `family.fire.harbour-view-evacuation` | `doc.fire.harbour-view-evacuation.v1` | documents/fire-safety/harbour-view-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.applicability.bristol-conflict` / `multi-document`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Show me both applicable evacuation instructions for the Bristol home.
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.south-west.fallback-condition` | `family.fire.south-west-evacuation` | `doc.fire.south-west-evacuation.v1` | documents/fire-safety/south-west-evacuation.md |
| PRIMARY | `fire.harbour-view.local-override` | `family.fire.harbour-view-evacuation` | `doc.fire.harbour-view-evacuation.v1` | documents/fire-safety/harbour-view-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.applicability.regional-exeter` / `alias`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Which evacuation procedure applies at the Exeter home?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.south-west.regional-fallback` | `family.fire.south-west-evacuation` | `doc.fire.south-west-evacuation.v1` | documents/fire-safety/south-west-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.applicability.regional-exeter` / `canonical`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Where does Meadow Court assemble under the regional procedure?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.south-west.regional-fallback` | `family.fire.south-west-evacuation` | `doc.fire.south-west-evacuation.v1` | documents/fire-safety/south-west-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.applicability.regional-exeter` / `inheritance`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Does the South West fire procedure cover Meadow Court?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.south-west.regional-fallback` | `family.fire.south-west-evacuation` | `doc.fire.south-west-evacuation.v1` | documents/fire-safety/south-west-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.compare.medication-administration` / `change`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What changed in the checks before giving medication?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v2.seven-checks` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |
| COMPARISON | `medication.v1.six-checks` | `family.medication.administration` | `doc.medication.administration.v1` | documents/medication/safe-administration-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.compare.medication-administration` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What extra check was added to the newer meds policy?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v2.seven-checks` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |
| COMPARISON | `medication.v1.six-checks` | `family.medication.administration` | `doc.medication.administration.v1` | documents/medication/safe-administration-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.compare.medication-administration` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare the current medicine checks with the previous policy.
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:pilot.compare.medication-administration:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v2.seven-checks` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |
| COMPARISON | `medication.v1.six-checks` | `family.medication.administration` | `doc.medication.administration.v1` | documents/medication/safe-administration-v1.md |

#### PRIMARY

#### COMPARISON

### `pilot.current.medication-administration` / `abbreviation`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What does the current policy say about signing a MAR?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:pilot.current.medication-administration:abbreviation`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.mar.sign-after-observation` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |

#### PRIMARY

### `pilot.current.medication-administration` / `colloquial`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do I tick the meds chart before or after the resident takes it?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:pilot.current.medication-administration:colloquial`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.mar.sign-after-observation` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |

#### PRIMARY

### `pilot.current.medication-administration` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When should I sign the MAR after giving a medicine?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.mar.sign-after-observation` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.current.scheduled-medication-version` / `colloquial`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do we have to add the incident number to the meds chart now?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:pilot.current.scheduled-medication-version:colloquial`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v2.omission-current-rule` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |

#### PRIMARY

### `pilot.current.scheduled-medication-version` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do omitted doses currently need an electronic incident reference on the MAR?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:pilot.current.scheduled-medication-version:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v2.omission-current-rule` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |

#### PRIMARY

### `pilot.current.scheduled-medication-version` / `scheduled`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Has the October electronic MAR rule started yet?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v2.omission-current-rule` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.current.withdrawn-before-authority` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do I email triage first or tell the home manager straight away?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.immediate-manager-report` | `family.safeguarding.adult-reporting` | `doc.safeguarding.adult-reporting.v1` | documents/safeguarding/adult-reporting-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.current.withdrawn-before-authority` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Who must staff tell immediately about a safeguarding concern?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:pilot.current.withdrawn-before-authority:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.immediate-manager-report` | `family.safeguarding.adult-reporting` | `doc.safeguarding.adult-reporting.v1` | documents/safeguarding/adult-reporting-v1.md |

#### PRIMARY

### `pilot.current.withdrawn-before-authority` / `scheduled`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did the proposed central safeguarding mailbox replace reporting to the manager?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.immediate-manager-report` | `family.safeguarding.adult-reporting` | `doc.safeguarding.adult-reporting.v1` | documents/safeguarding/adult-reporting-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.current.withdrawn-no-resurrection` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `NO_ELIGIBLE_EVIDENCE`
- Text capture: `BENCHMARK_TEXT`
- Question: Which outbreak rules do we use now that the newer one was pulled?
- Covered EvidenceUnits: `none`
- Metrics: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`


### `pilot.current.withdrawn-no-resurrection` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `NO_ELIGIBLE_EVIDENCE`
- Text capture: `BENCHMARK_TEXT`
- Question: What is the current respiratory outbreak procedure?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:pilot.current.withdrawn-no-resurrection:direct`


### `pilot.current.withdrawn-no-resurrection` / `withdrawn`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `NO_ELIGIBLE_EVIDENCE`
- Text capture: `BENCHMARK_TEXT`
- Question: After version 2 was withdrawn, did the old outbreak procedure become current again?
- Covered EvidenceUnits: `none`
- Metrics: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`


### `pilot.location-alias.bristol` / `alias`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Where do visitors assemble during a fire at the Bristol home?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:pilot.location-alias.bristol:alias`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.harbour-view.assembly-point` | `family.fire.harbour-view-evacuation` | `doc.fire.harbour-view-evacuation.v1` | documents/fire-safety/harbour-view-evacuation.md |

#### PRIMARY

### `pilot.location-alias.bristol` / `canonical`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What is the Harbour View fire assembly point?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.harbour-view.assembly-point` | `family.fire.harbour-view-evacuation` | `doc.fire.harbour-view-evacuation.v1` | documents/fire-safety/harbour-view-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.location-alias.bristol` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: If the alarm goes at Bristol, where should visitors wait outside?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.harbour-view.assembly-point` | `family.fire.harbour-view-evacuation` | `doc.fire.harbour-view-evacuation.v1` | documents/fire-safety/harbour-view-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.multi-document.medication-storage` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: The meds fridge is too warm — can I still give the medicine and who do I call?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.administration.fridge-gate` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |
| PRIMARY | `medication.storage.out-of-range-response` | `family.medication.storage` | `doc.medication.storage.v1` | documents/medication/storage-temperature-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.multi-document.medication-storage` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What should I do if the medicines fridge reads 9°C before the drug round?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:pilot.multi-document.medication-storage:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.administration.fridge-gate` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |
| PRIMARY | `medication.storage.out-of-range-response` | `family.medication.storage` | `doc.medication.storage.v1` | documents/medication/storage-temperature-procedure.md |

#### PRIMARY

### `pilot.multi-document.medication-storage` / `numeric`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What do the policy and storage procedure require for a 9 degree fridge reading?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.administration.fridge-gate` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |
| PRIMARY | `medication.storage.out-of-range-response` | `family.medication.storage` | `doc.medication.storage.v1` | documents/medication/storage-temperature-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.table.training-refresh` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When does the fire warden course need renewing?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.fire-marshal.interval` | `family.training.matrix` | `doc.training.matrix.v1` | documents/training/mandatory-training-matrix.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.table.training-refresh` / `contrast`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is fire marshal refresher training yearly or every two years?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.fire-marshal.interval` | `family.training.matrix` | `doc.training.matrix.v1` | documents/training/mandatory-training-matrix.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.table.training-refresh` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How often must a fire marshal repeat practical training?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.fire-marshal.interval` | `family.training.matrix` | `doc.training.matrix.v1` | documents/training/mandatory-training-matrix.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.valid-at-date.medication-administration` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What did the old meds policy say to put on the chart when someone refused?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v1.refused-code` | `family.medication.administration` | `doc.medication.administration.v1` | documents/medication/safe-administration-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.valid-at-date.medication-administration` / `dated`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What MAR code applied to a refused dose on 1 June 2024?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:pilot.valid-at-date.medication-administration:dated`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v1.refused-code` | `family.medication.administration` | `doc.medication.administration.v1` | documents/medication/safe-administration-v1.md |

#### PRIMARY

### `pilot.valid-at-date.medication-administration` / `historical`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: In June 2024, how did staff record medicine refusal?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:pilot.valid-at-date.medication-administration:historical`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v1.refused-code` | `family.medication.administration` | `doc.medication.administration.v1` | documents/medication/safe-administration-v1.md |

#### PRIMARY

### `safeguarding.allegations.compare-process` / `colloquial`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did HR used to be told later than they are now?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:safeguarding.allegations.compare-process:colloquial`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.allegations.v2-hr` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v2` | documents/safeguarding/allegations-staff-v2.md |
| COMPARISON | `safeguarding.allegations.v1-hr` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v1` | documents/safeguarding/allegations-staff-v1.md |

#### PRIMARY

#### COMPARISON

### `safeguarding.allegations.compare-process` / `compare`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare the old and current deadlines for telling HR about a staff allegation.
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:safeguarding.allegations.compare-process:compare`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.allegations.v2-hr` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v2` | documents/safeguarding/allegations-staff-v2.md |
| COMPARISON | `safeguarding.allegations.v1-hr` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v1` | documents/safeguarding/allegations-staff-v1.md |

#### PRIMARY

#### COMPARISON

### `safeguarding.allegations.compare-process` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How did the HR notification rule change between allegations procedures?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.allegations.v2-hr` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v2` | documents/safeguarding/allegations-staff-v2.md |
| COMPARISON | `safeguarding.allegations.v1-hr` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v1` | documents/safeguarding/allegations-staff-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.allegations.current-hr-timing` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How quickly do we tell HR when a staff safeguarding allegation comes in?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.allegations.hr-immediate` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v2` | documents/safeguarding/allegations-staff-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.allegations.current-hr-timing` / `contrast`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Can the manager wait one working day before telling HR?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.allegations.hr-immediate` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v2` | documents/safeguarding/allegations-staff-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.allegations.current-hr-timing` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When must HR be informed about an allegation against staff now?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:safeguarding.allegations.current-hr-timing:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.allegations.hr-immediate` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v2` | documents/safeguarding/allegations-staff-v2.md |

#### PRIMARY

### `safeguarding.body-map.observable-facts` / `cause`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Should staff write what they think caused a bruise on the body map?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.body-map.facts-only` | `family.safeguarding.body-map` | `doc.safeguarding.body-map.v1` | documents/safeguarding/body-map-form.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.body-map.observable-facts` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do I guess how the mark happened or just describe what I can see?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.body-map.facts-only` | `family.safeguarding.body-map` | `doc.safeguarding.body-map.v1` | documents/safeguarding/body-map-form.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.body-map.observable-facts` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What should be recorded on an injury body map?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:safeguarding.body-map.observable-facts:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.body-map.facts-only` | `family.safeguarding.body-map` | `doc.safeguarding.body-map.v1` | documents/safeguarding/body-map-form.md |

#### PRIMARY

### `safeguarding.capacity.unwise-decision` / `MCA`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Under the MCA, is capacity assessed once for every decision?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.capacity.decision-specific` | `family.safeguarding.mental-capacity` | `doc.safeguarding.mental-capacity.v1` | documents/safeguarding/mental-capacity-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.capacity.unwise-decision` / `colloquial`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Can we say a resident has no capacity just because we disagree with their choice?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:safeguarding.capacity.unwise-decision:colloquial`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.capacity.decision-specific` | `family.safeguarding.mental-capacity` | `doc.safeguarding.mental-capacity.v1` | documents/safeguarding/mental-capacity-procedure.md |

#### PRIMARY

### `safeguarding.capacity.unwise-decision` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Does making an unwise decision mean someone lacks capacity?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.capacity.decision-specific` | `family.safeguarding.mental-capacity` | `doc.safeguarding.mental-capacity.v1` | documents/safeguarding/mental-capacity-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.covert-medication.multi-document` / `colloquial`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: If someone cannot decide about tablets, what do both the MCA and meds rules require?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:safeguarding.covert-medication.multi-document:colloquial`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `covert-medication.medicines-controls` | `family.medication.covert` | `doc.medication.covert.v1` | documents/medication/covert-administration-policy.md |
| PRIMARY | `covert-medication.capacity-controls` | `family.safeguarding.mental-capacity` | `doc.safeguarding.mental-capacity.v1` | documents/safeguarding/mental-capacity-procedure.md |

#### PRIMARY

### `safeguarding.covert-medication.multi-document` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What capacity and medicines evidence is needed for covert administration?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `covert-medication.medicines-controls` | `family.medication.covert` | `doc.medication.covert.v1` | documents/medication/covert-administration-policy.md |
| PRIMARY | `covert-medication.capacity-controls` | `family.safeguarding.mental-capacity` | `doc.safeguarding.mental-capacity.v1` | documents/safeguarding/mental-capacity-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.covert-medication.multi-document` / `multi`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Which policies together govern hiding medicine in food?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `covert-medication.medicines-controls` | `family.medication.covert` | `doc.medication.covert.v1` | documents/medication/covert-administration-policy.md |
| PRIMARY | `covert-medication.capacity-controls` | `family.safeguarding.mental-capacity` | `doc.safeguarding.mental-capacity.v1` | documents/safeguarding/mental-capacity-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `training.medication.compare` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did med sign-off change from three rounds to four?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.medication.compare-current` | `family.training.medication-competency` | `doc.training.medication-competency.v2` | documents/training/medication-competency-v2.md |
| COMPARISON | `training.medication.compare-old` | `family.training.medication-competency` | `doc.training.medication-competency.v1` | documents/training/medication-competency-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `training.medication.compare` / `compare`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare old and current observed-round requirements.
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:training.medication.compare:compare`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.medication.compare-current` | `family.training.medication-competency` | `doc.training.medication-competency.v2` | documents/training/medication-competency-v2.md |
| COMPARISON | `training.medication.compare-old` | `family.training.medication-competency` | `doc.training.medication-competency.v1` | documents/training/medication-competency-v1.md |

#### PRIMARY

#### COMPARISON

### `training.medication.compare` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How did the medication competency assessment change?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.medication.compare-current` | `family.training.medication-competency` | `doc.training.medication-competency.v2` | documents/training/medication-competency-v2.md |
| COMPARISON | `training.medication.compare-old` | `family.training.medication-competency` | `doc.training.medication-competency.v1` | documents/training/medication-competency-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `training.medication.current-rounds` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How many med rounds do I need signed off?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.medication.v2-rounds` | `family.training.medication-competency` | `doc.training.medication-competency.v2` | documents/training/medication-competency-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `training.medication.current-rounds` / `controlled`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Must the medication competency include a controlled-drug round?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.medication.v2-rounds` | `family.training.medication-competency` | `doc.training.medication-competency.v2` | documents/training/medication-competency-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `training.medication.current-rounds` / `direct`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How many observed medication rounds are required now?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:training.medication.current-rounds:direct`


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.medication.v2-rounds` | `family.training.medication-competency` | `doc.training.medication-competency.v2` | documents/training/medication-competency-v2.md |

#### PRIMARY


## Available and missing stage lineage

Available: case_id, variant_id, correctness flags, final per-case metrics, side metrics, covered EvidenceUnit IDs and final operational observations.
Available: question/expectation context, exact candidate-stage lineage and per-side candidate funnels from result.json.

## Decision

Status: **EXPERIMENTAL**

Decision: No human decision recorded.

Generated from `result.json`, `config.json` and optional `comparison.json`; raw JSON is authoritative.
