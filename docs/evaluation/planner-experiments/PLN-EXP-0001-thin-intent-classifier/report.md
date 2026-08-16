# PLN-EXP-0001-thin-intent-classifier

Isolated engineering experiment: no retrieval, eligibility, protected splits or production planner changes.

## Headline

- Valid structured responses: `126/126`
- Overall accuracy: `0.6423` over `123` scored variants
- Temporal-intent accuracy: `0.9286`
- CURRENT accuracy: `0.9111`
- COMPARE accuracy: `0.9524`
- VALID_AT_DATE accuracy: `1.0000`
- Exact-date accuracy: `1.0000` over `2` exact-date variants
- Applicability exact-reference accuracy: `0.4545` over `11` expected-reference variants
- Latency mean/p50/p95: `6432.32` / `5155.14` / `23031.16` ms
- Tokens input/cached/output: `75960` / `0` / `47137`
- Estimated cost: `$0.113264`

## Temporal confusion matrix

| Expected \ Predicted | CURRENT | COMPARE | VALID_AT_DATE |
|---|---:|---:|---:|
| CURRENT | 82 | 8 | 0 |
| COMPARE | 0 | 20 | 1 |
| VALID_AT_DATE | 0 | 0 | 15 |

## EXP-0001 comparison

```json
{
  "comparability_note": "EXP-0001 latency is an application/retrieval observation, not planner-only; cost and token usage for its planner were not machine-recorded. The responsibility boundaries intentionally differ.",
  "exp0001": {
    "application_observation_latency_ms": {
      "max": 38739.652392,
      "median": 7454.992544,
      "min": 4477.687085
    },
    "by_mode": {
      "COMPARE": {
        "correct": 0,
        "total": 21
      },
      "CURRENT": {
        "correct": 0,
        "total": 90
      },
      "VALID_AT_DATE": {
        "correct": 0,
        "total": 15
      }
    },
    "planner_cost_usd": null,
    "planner_token_usage": null,
    "temporal_intent_correct": 0,
    "total_variants": 126,
    "valid_typed_responses": 87
  },
  "thin_classifier": {
    "by_mode": {
      "COMPARE": {
        "correct": 20,
        "total": 21
      },
      "CURRENT": {
        "correct": 82,
        "total": 90
      },
      "VALID_AT_DATE": {
        "correct": 15,
        "total": 15
      }
    },
    "classifier_latency_ms": {
      "mean": 6432.323478769888,
      "p50": 5155.143626998324,
      "p95": 23031.155468997895
    },
    "estimated_cost_usd": 0.113264,
    "temporal_intent_correct": 117,
    "token_usage": {
      "cached_input_tokens": 0,
      "input_tokens": 75960,
      "output_tokens": 47137
    },
    "total_variants": 126,
    "valid_typed_responses": 126
  }
}
```

## Ground-truth reconciliation

`24` field-level reconciliations are preserved in `expectations.json`.

## Per-variant results

| Case | Variant | Expected | Actual | Date | Applicability | Overall |
|---|---|---|---|---|---|---|
| complaints.handling.compare | direct | COMPARE | COMPARE | True | True | True |
| complaints.handling.compare | compare | COMPARE | COMPARE | True | True | True |
| complaints.handling.compare | colloquial | COMPARE | COMPARE | True | True | True |
| complaints.handling.current-deadlines | direct | CURRENT | CURRENT | True | True | True |
| complaints.handling.current-deadlines | colloquial | CURRENT | CURRENT | True | True | True |
| complaints.handling.current-deadlines | contrast | CURRENT | COMPARE | True | True | False |
| gdpr.breach.ico-owner | direct | CURRENT | CURRENT | True | False | False |
| gdpr.breach.ico-owner | timing | CURRENT | CURRENT | True | False | False |
| gdpr.breach.ico-owner | colloquial | CURRENT | CURRENT | True | True | True |
| gdpr.data-protection.compare | direct | COMPARE | COMPARE | True | True | True |
| gdpr.data-protection.compare | change | COMPARE | COMPARE | True | True | True |
| gdpr.data-protection.compare | history | COMPARE | COMPARE | True | False | False |
| gdpr.data-protection.current-reporting | direct | CURRENT | CURRENT | True | True | True |
| gdpr.data-protection.current-reporting | email | CURRENT | CURRENT | True | True | True |
| gdpr.data-protection.current-reporting | colloquial | CURRENT | CURRENT | True | False | False |
| health-safety.accident.current-riddor-timing | direct | CURRENT | CURRENT | True | True | True |
| health-safety.accident.current-riddor-timing | expanded | CURRENT | CURRENT | True | True | True |
| health-safety.accident.current-riddor-timing | colloquial | CURRENT | CURRENT | True | False | False |
| health-safety.accident.valid-at-date | dated | VALID_AT_DATE | VALID_AT_DATE | True | False | False |
| health-safety.accident.valid-at-date | historical | VALID_AT_DATE | VALID_AT_DATE | True | True | True |
| health-safety.accident.valid-at-date | contrast | VALID_AT_DATE | VALID_AT_DATE | True | True | True |
| health-safety.coshh.review-trigger | direct | CURRENT | CURRENT | True | True | True |
| health-safety.coshh.review-trigger | product | CURRENT | CURRENT | True | True | True |
| health-safety.coshh.review-trigger | colloquial | CURRENT | COMPARE | True | True | False |
| health-safety.moving-handling.compare | direct | COMPARE | COMPARE | True | True | True |
| health-safety.moving-handling.compare | compare | COMPARE | COMPARE | True | True | True |
| health-safety.moving-handling.compare | colloquial | COMPARE | VALID_AT_DATE | True | True | False |
| health-safety.moving-handling.current-staffing | direct | CURRENT | CURRENT | True | True | True |
| health-safety.moving-handling.current-staffing | assessment | CURRENT | CURRENT | True | True | True |
| health-safety.moving-handling.current-staffing | colloquial | CURRENT | CURRENT | True | True | True |
| hr.annual-leave.compare | direct | COMPARE | COMPARE | True | True | True |
| hr.annual-leave.compare | change | COMPARE | COMPARE | True | False | False |
| hr.annual-leave.compare | allowance | COMPARE | COMPARE | True | True | True |
| hr.annual-leave.current-notice | direct | CURRENT | CURRENT | True | True | True |
| hr.annual-leave.current-notice | colloquial | CURRENT | CURRENT | True | True | True |
| hr.annual-leave.current-notice | table | CURRENT | CURRENT | True | False | False |
| hr.annual-leave.valid-at-date | dated | VALID_AT_DATE | VALID_AT_DATE | True | False | False |
| hr.annual-leave.valid-at-date | old | VALID_AT_DATE | VALID_AT_DATE | True | True | True |
| hr.annual-leave.valid-at-date | contrast | VALID_AT_DATE | VALID_AT_DATE | True | True | True |
| hr.disciplinary.suspension-neutral | direct | CURRENT | CURRENT | True | True | True |
| hr.disciplinary.suspension-neutral | review | CURRENT | CURRENT | True | True | True |
| hr.disciplinary.suspension-neutral | colloquial | CURRENT | CURRENT | True | True | True |
| hr.lone-worker.coventry-overdue | alias | CURRENT | CURRENT | True | False | False |
| hr.lone-worker.coventry-overdue | timing | CURRENT | CURRENT | True | False | False |
| hr.lone-worker.coventry-overdue | colloquial | CURRENT | CURRENT | True | False | False |
| infection.outbreak.valid-before-withdrawal | dated | VALID_AT_DATE | VALID_AT_DATE | True | True | True |
| infection.outbreak.valid-before-withdrawal | historical | VALID_AT_DATE | VALID_AT_DATE | True | False | False |
| infection.outbreak.valid-before-withdrawal | contrast | VALID_AT_DATE | VALID_AT_DATE | True | True | True |
| medication.controlled-drugs.current-discrepancy | direct | CURRENT | CURRENT | True | True | True |
| medication.controlled-drugs.current-discrepancy | contrast | CURRENT | CURRENT | True | True | True |
| medication.controlled-drugs.current-discrepancy | colloquial | CURRENT | CURRENT | True | True | True |
| medication.controlled-drugs.valid-at-date | dated | VALID_AT_DATE | VALID_AT_DATE | True | True | True |
| medication.controlled-drugs.valid-at-date | historical | VALID_AT_DATE | VALID_AT_DATE | True | True | True |
| medication.controlled-drugs.valid-at-date | contrast | VALID_AT_DATE | VALID_AT_DATE | True | True | True |
| medication.covert.capacity-requirements | direct | CURRENT | CURRENT | True | True | True |
| medication.covert.capacity-requirements | refusal | CURRENT | CURRENT | True | False | False |
| medication.covert.capacity-requirements | abbreviation | CURRENT | CURRENT | True | True | True |
| medication.error-form.immediate-safety | direct | CURRENT | CURRENT | True | True | True |
| medication.error-form.immediate-safety | priority | CURRENT | CURRENT | True | True | True |
| medication.error-form.immediate-safety | colloquial | CURRENT | CURRENT | True | True | True |
| medication.fridge.boundary-table | upper | CURRENT | CURRENT | True | False | False |
| medication.fridge.boundary-table | decimal | CURRENT | CURRENT | True | False | False |
| medication.fridge.boundary-table | colloquial | CURRENT | CURRENT | True | True | True |
| medication.prn.minimum-interval | direct | CURRENT | CURRENT | True | True | True |
| medication.prn.minimum-interval | expanded | CURRENT | CURRENT | True | True | True |
| medication.prn.minimum-interval | colloquial | CURRENT | CURRENT | True | False | False |
| pilot.adversarial.visitor-negative | direct | CURRENT | CURRENT | True | False | False |
| pilot.adversarial.visitor-negative | sign-in | CURRENT | CURRENT | True | True | True |
| pilot.adversarial.visitor-negative | colloquial | CURRENT | CURRENT | True | False | False |
| pilot.applicability.ambiguous-home | ambiguous | CURRENT | CURRENT | True | True | True |
| pilot.applicability.ambiguous-home | pronoun | CURRENT | CURRENT | True | None | None |
| pilot.applicability.ambiguous-home | underspecified | CURRENT | CURRENT | True | True | True |
| pilot.applicability.bristol-conflict | direct | CURRENT | CURRENT | True | True | True |
| pilot.applicability.bristol-conflict | conflict | CURRENT | COMPARE | True | None | None |
| pilot.applicability.bristol-conflict | multi-document | CURRENT | CURRENT | True | False | False |
| pilot.applicability.regional-exeter | alias | CURRENT | CURRENT | True | False | False |
| pilot.applicability.regional-exeter | canonical | CURRENT | CURRENT | True | False | False |
| pilot.applicability.regional-exeter | inheritance | CURRENT | CURRENT | True | None | None |
| pilot.compare.medication-administration | direct | COMPARE | COMPARE | True | True | True |
| pilot.compare.medication-administration | change | COMPARE | COMPARE | True | False | False |
| pilot.compare.medication-administration | colloquial | COMPARE | COMPARE | True | True | True |
| pilot.current.medication-administration | direct | CURRENT | CURRENT | True | False | False |
| pilot.current.medication-administration | colloquial | CURRENT | CURRENT | True | True | True |
| pilot.current.medication-administration | abbreviation | CURRENT | CURRENT | True | True | True |
| pilot.current.scheduled-medication-version | direct | CURRENT | CURRENT | True | False | False |
| pilot.current.scheduled-medication-version | scheduled | CURRENT | COMPARE | True | True | False |
| pilot.current.scheduled-medication-version | colloquial | CURRENT | CURRENT | True | False | False |
| pilot.current.withdrawn-before-authority | direct | CURRENT | CURRENT | True | True | True |
| pilot.current.withdrawn-before-authority | scheduled | CURRENT | COMPARE | True | True | False |
| pilot.current.withdrawn-before-authority | colloquial | CURRENT | CURRENT | True | False | False |
| pilot.current.withdrawn-no-resurrection | direct | CURRENT | CURRENT | True | True | True |
| pilot.current.withdrawn-no-resurrection | withdrawn | CURRENT | COMPARE | True | False | False |
| pilot.current.withdrawn-no-resurrection | colloquial | CURRENT | COMPARE | True | True | False |
| pilot.location-alias.bristol | alias | CURRENT | CURRENT | True | False | False |
| pilot.location-alias.bristol | canonical | CURRENT | CURRENT | True | True | True |
| pilot.location-alias.bristol | colloquial | CURRENT | CURRENT | True | True | True |
| pilot.multi-document.medication-storage | direct | CURRENT | CURRENT | True | False | False |
| pilot.multi-document.medication-storage | colloquial | CURRENT | CURRENT | True | False | False |
| pilot.multi-document.medication-storage | numeric | CURRENT | CURRENT | True | False | False |
| pilot.table.training-refresh | direct | CURRENT | CURRENT | True | True | True |
| pilot.table.training-refresh | colloquial | CURRENT | CURRENT | True | True | True |
| pilot.table.training-refresh | contrast | CURRENT | COMPARE | True | True | False |
| pilot.valid-at-date.medication-administration | dated | VALID_AT_DATE | VALID_AT_DATE | True | True | True |
| pilot.valid-at-date.medication-administration | historical | VALID_AT_DATE | VALID_AT_DATE | True | True | True |
| pilot.valid-at-date.medication-administration | colloquial | VALID_AT_DATE | VALID_AT_DATE | True | True | True |
| safeguarding.allegations.compare-process | direct | COMPARE | COMPARE | True | True | True |
| safeguarding.allegations.compare-process | compare | COMPARE | COMPARE | True | False | False |
| safeguarding.allegations.compare-process | colloquial | COMPARE | COMPARE | True | False | False |
| safeguarding.allegations.current-hr-timing | direct | CURRENT | CURRENT | True | True | True |
| safeguarding.allegations.current-hr-timing | contrast | CURRENT | CURRENT | True | True | True |
| safeguarding.allegations.current-hr-timing | colloquial | CURRENT | CURRENT | True | True | True |
| safeguarding.body-map.observable-facts | direct | CURRENT | CURRENT | True | True | True |
| safeguarding.body-map.observable-facts | cause | CURRENT | CURRENT | True | False | False |
| safeguarding.body-map.observable-facts | colloquial | CURRENT | CURRENT | True | True | True |
| safeguarding.capacity.unwise-decision | direct | CURRENT | CURRENT | True | True | True |
| safeguarding.capacity.unwise-decision | MCA | CURRENT | CURRENT | True | False | False |
| safeguarding.capacity.unwise-decision | colloquial | CURRENT | CURRENT | True | False | False |
| safeguarding.covert-medication.multi-document | direct | CURRENT | CURRENT | True | True | True |
| safeguarding.covert-medication.multi-document | multi | CURRENT | CURRENT | True | True | True |
| safeguarding.covert-medication.multi-document | colloquial | CURRENT | CURRENT | True | True | True |
| training.medication.compare | direct | COMPARE | COMPARE | True | True | True |
| training.medication.compare | compare | COMPARE | COMPARE | True | True | True |
| training.medication.compare | colloquial | COMPARE | COMPARE | True | True | True |
| training.medication.current-rounds | direct | CURRENT | CURRENT | True | True | True |
| training.medication.current-rounds | controlled | CURRENT | CURRENT | True | True | True |
| training.medication.current-rounds | colloquial | CURRENT | CURRENT | True | True | True |
