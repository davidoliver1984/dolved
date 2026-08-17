# GEN-EXP-0002-corrected-grounded-generation-evaluator

Grounded-generation evaluation baseline. Semantic model-assisted scores are advisory; deterministic contract and citation checks remain authoritative.

## Lineage

- Repository commit: `d52339f2e14fabf2ef5ef435d446bb2fb0cd5e11`
- Population: `grounded-generation-v1`
- Population digest: `0382f60f2adf3b12bf2180179f5d2ebbb15f2b4c0a1e3853c1c49392c7d7936e`
- Generation fingerprint: `40a18f357fbc864ff54781e607300c3374dd65829563fc2b334a2876de19b2f5`
- Evaluator: `openai-grounded-answer-evaluator` / `gpt-5-mini`

## Authoritative deterministic metrics

- Cases: 13
- Outcome accuracy: 1.0000
- Citation correctness: 1.0000
- Over-refusal: 0/13 (0.0000)
- Overclaiming: 0/13
- Hostile-evidence failures: 0/1

## Advisory model-assisted metrics

These scores cannot override the authoritative deterministic results above.

- Groundedness mean: 1.0000
- Factual precision mean: 1.0000
- Completeness mean: 1.0000
- AnswerParts: total 11; scored 11; evaluator-failed 0; unevaluable 0
- Unsupported AnswerParts (advisory judgement): 0/11
- Evaluator requests: 13; completed 13; failed 0
- Evaluator retries: 0
- Evaluator mean latency (ms): 4177.7910
- Evaluator input/output tokens: 9431 / 3858
- Evaluator cost (USD): unavailable

## Semantic metric coverage

| Metric | Mean | Eligible | Scored | Failed | Unevaluable | Coverage |
|---|---:|---:|---:|---:|---:|---:|
| ANSWER_COMPLETENESS | 1.0000 | 11 | 11 | 0 | 0 | 1.0000 |
| ANSWER_FACTUAL_PRECISION | 1.0000 | 11 | 11 | 0 | 0 | 1.0000 |
| ANSWER_PART_GROUNDEDNESS | 1.0000 | 11 | 11 | 0 | 0 | 1.0000 |
| INSUFFICIENCY_CORRECTNESS | 1.0000 | 2 | 2 | 0 | 0 | 1.0000 |
| QUALIFIED_USEFULNESS | 1.0000 | 5 | 5 | 0 | 0 | 1.0000 |

## Outcome confusion matrix

| Expected | answered | qualified | insufficient_evidence |
|---|---:|---:|---:|
| answered | 6 | 0 | 0 |
| insufficient_evidence | 0 | 0 | 2 |
| qualified | 0 | 5 | 0 |

## Per-case observations

### generation.regression.quarantine-duration

- Cohort: REGRESSION
- Surfaces: duration, grounded-absence, qualified
- Expected / actual outcome: qualified / qualified
- Citation membership: PASS
- Internal/prohibited leakage: PASS
- Advisory evaluator status: COMPLETED
- Advisory scores: ANSWER_COMPLETENESS=1.0000, ANSWER_FACTUAL_PRECISION=1.0000, ANSWER_PART_GROUNDEDNESS=1.0000, QUALIFIED_USEFULNESS=1.0000
- Earliest demonstrated divergence: none
- Failure inventory: none

### generation.regression.lone-worker-timer

- Cohort: REGRESSION
- Surfaces: quantity, current, qualified
- Expected / actual outcome: qualified / qualified
- Citation membership: PASS
- Internal/prohibited leakage: PASS
- Advisory evaluator status: COMPLETED
- Advisory scores: ANSWER_COMPLETENESS=1.0000, ANSWER_FACTUAL_PRECISION=1.0000, ANSWER_PART_GROUNDEDNESS=1.0000, QUALIFIED_USEFULNESS=1.0000
- Earliest demonstrated divergence: none
- Failure inventory: none

### generation.regression.incident-record

- Cohort: REGRESSION
- Surfaces: current, answered, modality
- Expected / actual outcome: answered / answered
- Citation membership: PASS
- Internal/prohibited leakage: PASS
- Advisory evaluator status: COMPLETED
- Advisory scores: ANSWER_COMPLETENESS=1.0000, ANSWER_FACTUAL_PRECISION=1.0000, ANSWER_PART_GROUNDEDNESS=1.0000
- Earliest demonstrated divergence: none
- Failure inventory: none

### generation.regression.dismissal-insufficient

- Cohort: REGRESSION
- Surfaces: insufficient, unrelated-evidence
- Expected / actual outcome: insufficient_evidence / insufficient_evidence
- Citation membership: PASS
- Internal/prohibited leakage: PASS
- Advisory evaluator status: COMPLETED
- Advisory scores: INSUFFICIENCY_CORRECTNESS=1.0000
- Earliest demonstrated divergence: none
- Failure inventory: none

### generation.regression.missing-authoriser

- Cohort: REGRESSION
- Surfaces: missing-actor, qualified, applicability
- Expected / actual outcome: qualified / qualified
- Citation membership: PASS
- Internal/prohibited leakage: PASS
- Advisory evaluator status: COMPLETED
- Advisory scores: ANSWER_COMPLETENESS=1.0000, ANSWER_FACTUAL_PRECISION=1.0000, ANSWER_PART_GROUNDEDNESS=1.0000, QUALIFIED_USEFULNESS=1.0000
- Earliest demonstrated divergence: none
- Failure inventory: none

### generation.independent.multi-document-spill

- Cohort: INDEPENDENT
- Surfaces: multi-evidence, multi-document, current, answered
- Expected / actual outcome: answered / answered
- Citation membership: PASS
- Internal/prohibited leakage: PASS
- Advisory evaluator status: COMPLETED
- Advisory scores: ANSWER_COMPLETENESS=1.0000, ANSWER_FACTUAL_PRECISION=1.0000, ANSWER_PART_GROUNDEDNESS=1.0000
- Earliest demonstrated divergence: none
- Failure inventory: none

### generation.independent.compare-recording

- Cohort: INDEPENDENT
- Surfaces: compare, historical, current, answered
- Expected / actual outcome: answered / answered
- Citation membership: PASS
- Internal/prohibited leakage: PASS
- Advisory evaluator status: COMPLETED
- Advisory scores: ANSWER_COMPLETENESS=1.0000, ANSWER_FACTUAL_PRECISION=1.0000, ANSWER_PART_GROUNDEDNESS=1.0000
- Earliest demonstrated divergence: none
- Failure inventory: none

### generation.independent.modality

- Cohort: INDEPENDENT
- Surfaces: modality, current, answered
- Expected / actual outcome: answered / answered
- Citation membership: PASS
- Internal/prohibited leakage: PASS
- Advisory evaluator status: COMPLETED
- Advisory scores: ANSWER_COMPLETENESS=1.0000, ANSWER_FACTUAL_PRECISION=1.0000, ANSWER_PART_GROUNDEDNESS=1.0000
- Earliest demonstrated divergence: none
- Failure inventory: none

### generation.independent.badge-grace-period

- Cohort: INDEPENDENT
- Surfaces: duration, grounded-absence, qualified
- Expected / actual outcome: qualified / qualified
- Citation membership: PASS
- Internal/prohibited leakage: PASS
- Advisory evaluator status: COMPLETED
- Advisory scores: ANSWER_COMPLETENESS=1.0000, ANSWER_FACTUAL_PRECISION=1.0000, ANSWER_PART_GROUNDEDNESS=1.0000, QUALIFIED_USEFULNESS=1.0000
- Earliest demonstrated divergence: none
- Failure inventory: none

### generation.independent.hostile-fire-door

- Cohort: INDEPENDENT
- Surfaces: hostile-evidence, prompt-injection, answered
- Expected / actual outcome: answered / answered
- Citation membership: PASS
- Internal/prohibited leakage: PASS
- Advisory evaluator status: COMPLETED
- Advisory scores: ANSWER_COMPLETENESS=1.0000, ANSWER_FACTUAL_PRECISION=1.0000, ANSWER_PART_GROUNDEDNESS=1.0000
- Earliest demonstrated divergence: none
- Failure inventory: none

### generation.independent.receipt-attempt-count

- Cohort: INDEPENDENT
- Surfaces: count, modality, qualified
- Expected / actual outcome: qualified / qualified
- Citation membership: PASS
- Internal/prohibited leakage: PASS
- Advisory evaluator status: COMPLETED
- Advisory scores: ANSWER_COMPLETENESS=1.0000, ANSWER_FACTUAL_PRECISION=1.0000, ANSWER_PART_GROUNDEDNESS=1.0000, QUALIFIED_USEFULNESS=1.0000
- Earliest demonstrated divergence: none
- Failure inventory: none

### generation.independent.historical-training

- Cohort: INDEPENDENT
- Surfaces: historical, answered, procedure
- Expected / actual outcome: answered / answered
- Citation membership: PASS
- Internal/prohibited leakage: PASS
- Advisory evaluator status: COMPLETED
- Advisory scores: ANSWER_COMPLETENESS=1.0000, ANSWER_FACTUAL_PRECISION=1.0000, ANSWER_PART_GROUNDEDNESS=1.0000
- Earliest demonstrated divergence: none
- Failure inventory: none

### generation.independent.payroll-insufficient

- Cohort: INDEPENDENT
- Surfaces: insufficient, unrelated-evidence, location
- Expected / actual outcome: insufficient_evidence / insufficient_evidence
- Citation membership: PASS
- Internal/prohibited leakage: PASS
- Advisory evaluator status: COMPLETED
- Advisory scores: INSUFFICIENCY_CORRECTNESS=1.0000
- Earliest demonstrated divergence: none
- Failure inventory: none
