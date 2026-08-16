# PLN-EXP-0002-thin-intent-location-classifier

Isolated engineering-only linguistic classifier experiment.

## Headline

- Structured reliability: `1.0000` (`126/126`)
- Temporal accuracy: `0.9027` over `113` scored variants; `13` review-only
- CURRENT / COMPARE / VALID_AT_DATE: `0.8889` / `0.9524` / `1.0000`
- Date accuracy: `1.0000`; false hallucinations `0`
- Location precision / recall / exact-case accuracy: `0.8000` / `0.8000` / `0.9760`
- Location false positives / misses: `3` / `3`
- Latency mean/p50/p95: `6134.58` / `5442.11` / `9829.09` ms
- Tokens input/cached/output: `73062` / `0` / `65015`
- Estimated cost: `$0.148295`

## Confusion matrix

| Expected \ Predicted | CURRENT | COMPARE | VALID_AT_DATE |
|---|---:|---:|---:|
| CURRENT | 80 | 10 | 0 |
| COMPARE | 1 | 20 | 0 |
| VALID_AT_DATE | 0 | 0 | 2 |

## PLN-EXP-0001 comparison

```json
{
  "comparability_note": "PLN-EXP-0002 changes VALID_AT_DATE to exact-day-only and replaces a singular applicability reference with plural location extraction. Review denominators.",
  "pln_exp0001": {
    "estimated_cost_usd": 0.113264,
    "location_presence_precision": 0.2619047619047619,
    "location_presence_recall": 1.0,
    "structured_output_reliability": 1.0,
    "temporal_intent_accuracy": 0.9285714285714286,
    "temporal_intent_by_mode": {
      "COMPARE": 0.9523809523809523,
      "CURRENT": 0.9111111111111111,
      "VALID_AT_DATE": 1.0
    }
  },
  "pln_exp0002": {
    "estimated_cost_usd": 0.1482955,
    "location_reference_precision": 0.8,
    "location_reference_recall": 0.8,
    "structured_output_reliability": 1.0,
    "temporal_intent_accuracy": 0.9026548672566371,
    "temporal_intent_by_mode": {
      "COMPARE": 0.9523809523809523,
      "CURRENT": 0.8888888888888888,
      "VALID_AT_DATE": 1.0
    }
  }
}
```

## Location errors

### False positives

```json
[
  {
    "case_id": "pilot.applicability.bristol-conflict",
    "variant_id": "multi-document",
    "question": "Show me both applicable evacuation instructions for the Bristol home.",
    "references": [
      "bristol home"
    ]
  },
  {
    "case_id": "pilot.applicability.regional-exeter",
    "variant_id": "alias",
    "question": "Which evacuation procedure applies at the Exeter home?",
    "references": [
      "exeter home"
    ]
  },
  {
    "case_id": "pilot.location-alias.bristol",
    "variant_id": "alias",
    "question": "Where do visitors assemble during a fire at the Bristol home?",
    "references": [
      "bristol home"
    ]
  }
]
```

### Misses

```json
[
  {
    "case_id": "pilot.applicability.bristol-conflict",
    "variant_id": "multi-document",
    "question": "Show me both applicable evacuation instructions for the Bristol home.",
    "references": [
      "the bristol home"
    ]
  },
  {
    "case_id": "pilot.applicability.regional-exeter",
    "variant_id": "alias",
    "question": "Which evacuation procedure applies at the Exeter home?",
    "references": [
      "the exeter home"
    ]
  },
  {
    "case_id": "pilot.location-alias.bristol",
    "variant_id": "alias",
    "question": "Where do visitors assemble during a fire at the Bristol home?",
    "references": [
      "the bristol home"
    ]
  }
]
```

## Per-variant results

| Case | Variant | Expected temporal | Actual temporal | Expected locations | Actual locations | Temporal | Location exact |
|---|---|---|---|---|---|---|---|
| complaints.handling.compare | direct | COMPARE | COMPARE | [] | [] | True | True |
| complaints.handling.compare | compare | COMPARE | COMPARE | [] | [] | True | True |
| complaints.handling.compare | colloquial | COMPARE | COMPARE | [] | [] | True | True |
| complaints.handling.current-deadlines | direct | CURRENT | CURRENT | [] | [] | True | True |
| complaints.handling.current-deadlines | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| complaints.handling.current-deadlines | contrast | CURRENT | COMPARE | [] | [] | False | True |
| gdpr.breach.ico-owner | direct | CURRENT | CURRENT | [] | [] | True | True |
| gdpr.breach.ico-owner | timing | CURRENT | CURRENT | [] | [] | True | True |
| gdpr.breach.ico-owner | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| gdpr.data-protection.compare | direct | COMPARE | COMPARE | [] | [] | True | True |
| gdpr.data-protection.compare | change | COMPARE | COMPARE | [] | [] | True | True |
| gdpr.data-protection.compare | history | COMPARE | COMPARE | [] | [] | True | True |
| gdpr.data-protection.current-reporting | direct | CURRENT | CURRENT | [] | [] | True | True |
| gdpr.data-protection.current-reporting | email | CURRENT | CURRENT | [] | [] | True | True |
| gdpr.data-protection.current-reporting | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| health-safety.accident.current-riddor-timing | direct | CURRENT | CURRENT | [] | [] | True | True |
| health-safety.accident.current-riddor-timing | expanded | CURRENT | CURRENT | [] | [] | True | True |
| health-safety.accident.current-riddor-timing | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| health-safety.accident.valid-at-date | dated | None | CURRENT | [] | [] | None | True |
| health-safety.accident.valid-at-date | historical | None | CURRENT | [] | [] | None | True |
| health-safety.accident.valid-at-date | contrast | None | CURRENT | [] | [] | None | True |
| health-safety.coshh.review-trigger | direct | CURRENT | CURRENT | [] | [] | True | True |
| health-safety.coshh.review-trigger | product | CURRENT | COMPARE | [] | [] | False | True |
| health-safety.coshh.review-trigger | colloquial | CURRENT | COMPARE | [] | [] | False | True |
| health-safety.moving-handling.compare | direct | COMPARE | COMPARE | [] | [] | True | True |
| health-safety.moving-handling.compare | compare | COMPARE | COMPARE | [] | [] | True | True |
| health-safety.moving-handling.compare | colloquial | COMPARE | CURRENT | [] | [] | False | True |
| health-safety.moving-handling.current-staffing | direct | CURRENT | CURRENT | [] | [] | True | True |
| health-safety.moving-handling.current-staffing | assessment | CURRENT | CURRENT | [] | [] | True | True |
| health-safety.moving-handling.current-staffing | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| hr.annual-leave.compare | direct | COMPARE | COMPARE | [] | [] | True | True |
| hr.annual-leave.compare | change | COMPARE | COMPARE | [] | [] | True | True |
| hr.annual-leave.compare | allowance | COMPARE | COMPARE | [] | [] | True | True |
| hr.annual-leave.current-notice | direct | CURRENT | CURRENT | [] | [] | True | True |
| hr.annual-leave.current-notice | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| hr.annual-leave.current-notice | table | CURRENT | CURRENT | [] | [] | True | True |
| hr.annual-leave.valid-at-date | dated | None | CURRENT | [] | [] | None | True |
| hr.annual-leave.valid-at-date | old | None | COMPARE | [] | [] | None | True |
| hr.annual-leave.valid-at-date | contrast | None | CURRENT | [] | [] | None | True |
| hr.disciplinary.suspension-neutral | direct | CURRENT | CURRENT | [] | [] | True | True |
| hr.disciplinary.suspension-neutral | review | CURRENT | CURRENT | [] | [] | True | True |
| hr.disciplinary.suspension-neutral | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| hr.lone-worker.coventry-overdue | alias | CURRENT | CURRENT | ["Coventry"] | ["Coventry"] | True | True |
| hr.lone-worker.coventry-overdue | timing | CURRENT | CURRENT | ["Midlands"] | ["Midlands"] | True | True |
| hr.lone-worker.coventry-overdue | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| infection.outbreak.valid-before-withdrawal | dated | VALID_AT_DATE | VALID_AT_DATE | [] | [] | True | True |
| infection.outbreak.valid-before-withdrawal | historical | None | COMPARE | [] | [] | None | True |
| infection.outbreak.valid-before-withdrawal | contrast | None | CURRENT | [] | [] | None | True |
| medication.controlled-drugs.current-discrepancy | direct | CURRENT | CURRENT | [] | [] | True | True |
| medication.controlled-drugs.current-discrepancy | contrast | CURRENT | CURRENT | [] | [] | True | True |
| medication.controlled-drugs.current-discrepancy | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| medication.controlled-drugs.valid-at-date | dated | None | CURRENT | [] | [] | None | True |
| medication.controlled-drugs.valid-at-date | historical | None | COMPARE | [] | [] | None | True |
| medication.controlled-drugs.valid-at-date | contrast | None | CURRENT | [] | [] | None | True |
| medication.covert.capacity-requirements | direct | CURRENT | CURRENT | [] | [] | True | True |
| medication.covert.capacity-requirements | refusal | CURRENT | CURRENT | [] | [] | True | True |
| medication.covert.capacity-requirements | abbreviation | CURRENT | CURRENT | [] | [] | True | True |
| medication.error-form.immediate-safety | direct | CURRENT | CURRENT | [] | [] | True | True |
| medication.error-form.immediate-safety | priority | CURRENT | CURRENT | [] | [] | True | True |
| medication.error-form.immediate-safety | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| medication.fridge.boundary-table | upper | CURRENT | CURRENT | [] | [] | True | True |
| medication.fridge.boundary-table | decimal | CURRENT | CURRENT | [] | [] | True | True |
| medication.fridge.boundary-table | colloquial | CURRENT | COMPARE | [] | [] | False | True |
| medication.prn.minimum-interval | direct | CURRENT | CURRENT | [] | [] | True | True |
| medication.prn.minimum-interval | expanded | CURRENT | CURRENT | [] | [] | True | True |
| medication.prn.minimum-interval | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| pilot.adversarial.visitor-negative | direct | CURRENT | CURRENT | [] | [] | True | True |
| pilot.adversarial.visitor-negative | sign-in | CURRENT | CURRENT | [] | [] | True | True |
| pilot.adversarial.visitor-negative | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| pilot.applicability.ambiguous-home | ambiguous | CURRENT | CURRENT | ["the home"] | ["the home"] | True | True |
| pilot.applicability.ambiguous-home | pronoun | CURRENT | CURRENT | null | [] | True | None |
| pilot.applicability.ambiguous-home | underspecified | CURRENT | CURRENT | ["our care home"] | ["our care home"] | True | True |
| pilot.applicability.bristol-conflict | direct | CURRENT | CURRENT | ["Harbour View"] | ["Harbour View"] | True | True |
| pilot.applicability.bristol-conflict | conflict | CURRENT | COMPARE | ["South West", "Bristol"] | ["South West", "Bristol"] | False | True |
| pilot.applicability.bristol-conflict | multi-document | CURRENT | CURRENT | ["the Bristol home"] | ["Bristol home"] | True | False |
| pilot.applicability.regional-exeter | alias | CURRENT | CURRENT | ["the Exeter home"] | ["Exeter home"] | True | False |
| pilot.applicability.regional-exeter | canonical | CURRENT | CURRENT | ["Meadow Court"] | ["Meadow Court"] | True | True |
| pilot.applicability.regional-exeter | inheritance | CURRENT | CURRENT | ["South West", "Meadow Court"] | ["South West", "Meadow Court"] | True | True |
| pilot.compare.medication-administration | direct | COMPARE | COMPARE | [] | [] | True | True |
| pilot.compare.medication-administration | change | COMPARE | COMPARE | [] | [] | True | True |
| pilot.compare.medication-administration | colloquial | COMPARE | COMPARE | [] | [] | True | True |
| pilot.current.medication-administration | direct | CURRENT | CURRENT | [] | [] | True | True |
| pilot.current.medication-administration | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| pilot.current.medication-administration | abbreviation | CURRENT | CURRENT | [] | [] | True | True |
| pilot.current.scheduled-medication-version | direct | CURRENT | CURRENT | [] | [] | True | True |
| pilot.current.scheduled-medication-version | scheduled | CURRENT | COMPARE | [] | [] | False | True |
| pilot.current.scheduled-medication-version | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| pilot.current.withdrawn-before-authority | direct | CURRENT | CURRENT | [] | [] | True | True |
| pilot.current.withdrawn-before-authority | scheduled | CURRENT | COMPARE | [] | [] | False | True |
| pilot.current.withdrawn-before-authority | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| pilot.current.withdrawn-no-resurrection | direct | CURRENT | CURRENT | [] | [] | True | True |
| pilot.current.withdrawn-no-resurrection | withdrawn | CURRENT | COMPARE | [] | [] | False | True |
| pilot.current.withdrawn-no-resurrection | colloquial | CURRENT | COMPARE | [] | [] | False | True |
| pilot.location-alias.bristol | alias | CURRENT | CURRENT | ["the Bristol home"] | ["Bristol home"] | True | False |
| pilot.location-alias.bristol | canonical | CURRENT | CURRENT | ["Harbour View"] | ["Harbour View"] | True | True |
| pilot.location-alias.bristol | colloquial | CURRENT | CURRENT | ["Bristol"] | ["Bristol"] | True | True |
| pilot.multi-document.medication-storage | direct | CURRENT | CURRENT | [] | [] | True | True |
| pilot.multi-document.medication-storage | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| pilot.multi-document.medication-storage | numeric | CURRENT | CURRENT | [] | [] | True | True |
| pilot.table.training-refresh | direct | CURRENT | CURRENT | [] | [] | True | True |
| pilot.table.training-refresh | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| pilot.table.training-refresh | contrast | CURRENT | COMPARE | [] | [] | False | True |
| pilot.valid-at-date.medication-administration | dated | VALID_AT_DATE | VALID_AT_DATE | [] | [] | True | True |
| pilot.valid-at-date.medication-administration | historical | None | CURRENT | [] | [] | None | True |
| pilot.valid-at-date.medication-administration | colloquial | None | COMPARE | [] | [] | None | True |
| safeguarding.allegations.compare-process | direct | COMPARE | COMPARE | [] | [] | True | True |
| safeguarding.allegations.compare-process | compare | COMPARE | COMPARE | [] | [] | True | True |
| safeguarding.allegations.compare-process | colloquial | COMPARE | COMPARE | [] | [] | True | True |
| safeguarding.allegations.current-hr-timing | direct | CURRENT | CURRENT | [] | [] | True | True |
| safeguarding.allegations.current-hr-timing | contrast | CURRENT | CURRENT | [] | [] | True | True |
| safeguarding.allegations.current-hr-timing | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| safeguarding.body-map.observable-facts | direct | CURRENT | CURRENT | [] | [] | True | True |
| safeguarding.body-map.observable-facts | cause | CURRENT | CURRENT | [] | [] | True | True |
| safeguarding.body-map.observable-facts | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| safeguarding.capacity.unwise-decision | direct | CURRENT | CURRENT | [] | [] | True | True |
| safeguarding.capacity.unwise-decision | MCA | CURRENT | CURRENT | [] | [] | True | True |
| safeguarding.capacity.unwise-decision | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| safeguarding.covert-medication.multi-document | direct | CURRENT | CURRENT | [] | [] | True | True |
| safeguarding.covert-medication.multi-document | multi | CURRENT | CURRENT | [] | [] | True | True |
| safeguarding.covert-medication.multi-document | colloquial | CURRENT | CURRENT | [] | [] | True | True |
| training.medication.compare | direct | COMPARE | COMPARE | [] | [] | True | True |
| training.medication.compare | compare | COMPARE | COMPARE | [] | [] | True | True |
| training.medication.compare | colloquial | COMPARE | COMPARE | [] | [] | True | True |
| training.medication.current-rounds | direct | CURRENT | CURRENT | [] | [] | True | True |
| training.medication.current-rounds | controlled | CURRENT | CURRENT | [] | [] | True | True |
| training.medication.current-rounds | colloquial | CURRENT | CURRENT | [] | [] | True | True |
