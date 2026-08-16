# PLN-EXP-0003-thin-intent-temporal-boundary

Isolated engineering-only temporal-boundary experiment.

## Headline

- Structured reliability: `1.0000`
- Temporal accuracy: `1.0000` over `112` scored variants
- CURRENT / COMPARE / VALID_AT_DATE: `1.0000` / `1.0000` / `1.0000`
- False COMPARE count/rate: `0` / `0.0000`
- Genuine COMPARE recall: `1.0000`
- Location precision / recall: `0.7857` / `0.7333`
- Estimated cost: `$0.153717`

## Three-experiment comparison

```json
{
  "PLN-EXP-0001": {
    "estimated_cost_usd": 0.113264,
    "false_compare_count": 8,
    "false_compare_rate": 0.0761904761904762,
    "genuine_compare_recall": 0.9523809523809523,
    "location_reference_precision": null,
    "location_reference_recall": null,
    "structured_output_reliability": 1.0,
    "temporal_intent_accuracy": 0.9285714285714286,
    "temporal_intent_by_mode": {
      "COMPARE": 0.9523809523809523,
      "CURRENT": 0.9111111111111111,
      "VALID_AT_DATE": 1.0
    }
  },
  "PLN-EXP-0002": {
    "estimated_cost_usd": 0.1482955,
    "false_compare_count": 10,
    "false_compare_rate": 0.10869565217391304,
    "genuine_compare_recall": 0.9523809523809523,
    "location_reference_precision": 0.8,
    "location_reference_recall": 0.8,
    "structured_output_reliability": 1.0,
    "temporal_intent_accuracy": 0.9026548672566371,
    "temporal_intent_by_mode": {
      "COMPARE": 0.9523809523809523,
      "CURRENT": 0.8888888888888888,
      "VALID_AT_DATE": 1.0
    }
  },
  "PLN-EXP-0003": {
    "estimated_cost_usd": 0.1537165,
    "false_compare_count": 0,
    "false_compare_rate": 0.0,
    "genuine_compare_recall": 1.0,
    "location_reference_precision": 0.7857142857142857,
    "location_reference_recall": 0.7333333333333333,
    "structured_output_reliability": 1.0,
    "temporal_intent_accuracy": 1.0,
    "temporal_intent_by_mode": {
      "COMPARE": 1.0,
      "CURRENT": 1.0,
      "VALID_AT_DATE": 1.0
    }
  },
  "comparability_note": "PLN-EXP-0001 used a broader temporal denominator and singular applicability presence. PLN-EXP-0002/3 use exact-day VALID_AT_DATE and plural locations; PLN-EXP-0003 additionally reviews one historical-only variant and, under its refined definition, reconciles two application-authority transition questions from CURRENT to genuine COMPARE."
}
```

## Previous false-COMPARE cases

```json
[
  {
    "case_id": "complaints.handling.current-deadlines",
    "variant_id": "contrast",
    "question": "Is the acknowledgement deadline three days or two?",
    "pln_exp0002": "COMPARE",
    "pln_exp0003": "CURRENT",
    "resolved": true
  },
  {
    "case_id": "health-safety.coshh.review-trigger",
    "variant_id": "product",
    "question": "Do we need a new hazardous-substance assessment when a product formulation changes?",
    "pln_exp0002": "COMPARE",
    "pln_exp0003": "CURRENT",
    "resolved": true
  },
  {
    "case_id": "health-safety.coshh.review-trigger",
    "variant_id": "colloquial",
    "question": "The cleaning chemical changed \u2014 can we wait for the annual COSHH review?",
    "pln_exp0002": "COMPARE",
    "pln_exp0003": "CURRENT",
    "resolved": true
  },
  {
    "case_id": "medication.fridge.boundary-table",
    "variant_id": "colloquial",
    "question": "Is eight okay but just over eight too warm?",
    "pln_exp0002": "COMPARE",
    "pln_exp0003": "CURRENT",
    "resolved": true
  },
  {
    "case_id": "pilot.applicability.bristol-conflict",
    "variant_id": "conflict",
    "question": "Why do the South West and Bristol procedures name different assembly points?",
    "pln_exp0002": "COMPARE",
    "pln_exp0003": "CURRENT",
    "resolved": true
  },
  {
    "case_id": "pilot.current.scheduled-medication-version",
    "variant_id": "scheduled",
    "question": "Has the October electronic MAR rule started yet?",
    "pln_exp0002": "COMPARE",
    "pln_exp0003": "CURRENT",
    "resolved": true
  },
  {
    "case_id": "pilot.current.withdrawn-no-resurrection",
    "variant_id": "colloquial",
    "question": "Which outbreak rules do we use now that the newer one was pulled?",
    "pln_exp0002": "COMPARE",
    "pln_exp0003": "CURRENT",
    "resolved": true
  },
  {
    "case_id": "pilot.table.training-refresh",
    "variant_id": "contrast",
    "question": "Is fire marshal refresher training yearly or every two years?",
    "pln_exp0002": "COMPARE",
    "pln_exp0003": "CURRENT",
    "resolved": true
  }
]
```

## Per-variant results

| Case | Variant | Expected | Actual | Correct | Expected locations | Actual locations |
|---|---|---|---|---|---|---|
| complaints.handling.compare | direct | COMPARE | COMPARE | True | [] | [] |
| complaints.handling.compare | compare | COMPARE | COMPARE | True | [] | [] |
| complaints.handling.compare | colloquial | COMPARE | COMPARE | True | [] | [] |
| complaints.handling.current-deadlines | direct | CURRENT | CURRENT | True | [] | [] |
| complaints.handling.current-deadlines | colloquial | CURRENT | CURRENT | True | [] | [] |
| complaints.handling.current-deadlines | contrast | CURRENT | CURRENT | True | [] | [] |
| gdpr.breach.ico-owner | direct | CURRENT | CURRENT | True | [] | [] |
| gdpr.breach.ico-owner | timing | CURRENT | CURRENT | True | [] | [] |
| gdpr.breach.ico-owner | colloquial | CURRENT | CURRENT | True | [] | [] |
| gdpr.data-protection.compare | direct | COMPARE | COMPARE | True | [] | [] |
| gdpr.data-protection.compare | change | COMPARE | COMPARE | True | [] | [] |
| gdpr.data-protection.compare | history | COMPARE | COMPARE | True | [] | [] |
| gdpr.data-protection.current-reporting | direct | CURRENT | CURRENT | True | [] | [] |
| gdpr.data-protection.current-reporting | email | CURRENT | CURRENT | True | [] | [] |
| gdpr.data-protection.current-reporting | colloquial | CURRENT | CURRENT | True | [] | [] |
| health-safety.accident.current-riddor-timing | direct | CURRENT | CURRENT | True | [] | [] |
| health-safety.accident.current-riddor-timing | expanded | CURRENT | CURRENT | True | [] | [] |
| health-safety.accident.current-riddor-timing | colloquial | CURRENT | CURRENT | True | [] | [] |
| health-safety.accident.valid-at-date | dated | None | VALID_AT_DATE | None | [] | [] |
| health-safety.accident.valid-at-date | historical | None | COMPARE | None | [] | [] |
| health-safety.accident.valid-at-date | contrast | None | COMPARE | None | [] | [] |
| health-safety.coshh.review-trigger | direct | CURRENT | CURRENT | True | [] | [] |
| health-safety.coshh.review-trigger | product | CURRENT | CURRENT | True | [] | [] |
| health-safety.coshh.review-trigger | colloquial | CURRENT | CURRENT | True | [] | [] |
| health-safety.moving-handling.compare | direct | COMPARE | COMPARE | True | [] | [] |
| health-safety.moving-handling.compare | compare | COMPARE | COMPARE | True | [] | [] |
| health-safety.moving-handling.compare | colloquial | None | COMPARE | None | [] | [] |
| health-safety.moving-handling.current-staffing | direct | CURRENT | CURRENT | True | [] | [] |
| health-safety.moving-handling.current-staffing | assessment | CURRENT | CURRENT | True | [] | [] |
| health-safety.moving-handling.current-staffing | colloquial | CURRENT | CURRENT | True | [] | [] |
| hr.annual-leave.compare | direct | COMPARE | COMPARE | True | [] | [] |
| hr.annual-leave.compare | change | COMPARE | COMPARE | True | [] | [] |
| hr.annual-leave.compare | allowance | COMPARE | COMPARE | True | [] | [] |
| hr.annual-leave.current-notice | direct | CURRENT | CURRENT | True | [] | [] |
| hr.annual-leave.current-notice | colloquial | CURRENT | CURRENT | True | [] | [] |
| hr.annual-leave.current-notice | table | CURRENT | CURRENT | True | [] | [] |
| hr.annual-leave.valid-at-date | dated | None | VALID_AT_DATE | None | [] | [] |
| hr.annual-leave.valid-at-date | old | None | COMPARE | None | [] | [] |
| hr.annual-leave.valid-at-date | contrast | None | VALID_AT_DATE | None | [] | [] |
| hr.disciplinary.suspension-neutral | direct | CURRENT | CURRENT | True | [] | [] |
| hr.disciplinary.suspension-neutral | review | CURRENT | CURRENT | True | [] | [] |
| hr.disciplinary.suspension-neutral | colloquial | CURRENT | CURRENT | True | [] | [] |
| hr.lone-worker.coventry-overdue | alias | CURRENT | CURRENT | True | ["Coventry"] | ["Coventry"] |
| hr.lone-worker.coventry-overdue | timing | CURRENT | CURRENT | True | ["Midlands"] | [] |
| hr.lone-worker.coventry-overdue | colloquial | CURRENT | CURRENT | True | [] | [] |
| infection.outbreak.valid-before-withdrawal | dated | VALID_AT_DATE | VALID_AT_DATE | True | [] | [] |
| infection.outbreak.valid-before-withdrawal | historical | None | COMPARE | None | [] | [] |
| infection.outbreak.valid-before-withdrawal | contrast | None | COMPARE | None | [] | [] |
| medication.controlled-drugs.current-discrepancy | direct | CURRENT | CURRENT | True | [] | [] |
| medication.controlled-drugs.current-discrepancy | contrast | CURRENT | CURRENT | True | [] | [] |
| medication.controlled-drugs.current-discrepancy | colloquial | CURRENT | CURRENT | True | [] | [] |
| medication.controlled-drugs.valid-at-date | dated | None | COMPARE | None | [] | [] |
| medication.controlled-drugs.valid-at-date | historical | None | COMPARE | None | [] | [] |
| medication.controlled-drugs.valid-at-date | contrast | None | COMPARE | None | [] | [] |
| medication.covert.capacity-requirements | direct | CURRENT | CURRENT | True | [] | [] |
| medication.covert.capacity-requirements | refusal | CURRENT | CURRENT | True | [] | [] |
| medication.covert.capacity-requirements | abbreviation | CURRENT | CURRENT | True | [] | [] |
| medication.error-form.immediate-safety | direct | CURRENT | CURRENT | True | [] | [] |
| medication.error-form.immediate-safety | priority | CURRENT | CURRENT | True | [] | [] |
| medication.error-form.immediate-safety | colloquial | CURRENT | CURRENT | True | [] | [] |
| medication.fridge.boundary-table | upper | CURRENT | CURRENT | True | [] | [] |
| medication.fridge.boundary-table | decimal | CURRENT | CURRENT | True | [] | [] |
| medication.fridge.boundary-table | colloquial | CURRENT | CURRENT | True | [] | [] |
| medication.prn.minimum-interval | direct | CURRENT | CURRENT | True | [] | [] |
| medication.prn.minimum-interval | expanded | CURRENT | CURRENT | True | [] | [] |
| medication.prn.minimum-interval | colloquial | CURRENT | CURRENT | True | [] | [] |
| pilot.adversarial.visitor-negative | direct | CURRENT | CURRENT | True | [] | [] |
| pilot.adversarial.visitor-negative | sign-in | CURRENT | CURRENT | True | [] | [] |
| pilot.adversarial.visitor-negative | colloquial | CURRENT | CURRENT | True | [] | [] |
| pilot.applicability.ambiguous-home | ambiguous | CURRENT | CURRENT | True | ["the home"] | ["the home"] |
| pilot.applicability.ambiguous-home | pronoun | CURRENT | CURRENT | True | null | [] |
| pilot.applicability.ambiguous-home | underspecified | CURRENT | CURRENT | True | ["our care home"] | ["our care home"] |
| pilot.applicability.bristol-conflict | direct | CURRENT | CURRENT | True | ["Harbour View"] | ["Harbour View"] |
| pilot.applicability.bristol-conflict | conflict | CURRENT | CURRENT | True | ["South West", "Bristol"] | ["South West", "Bristol"] |
| pilot.applicability.bristol-conflict | multi-document | CURRENT | CURRENT | True | ["the Bristol home"] | ["Bristol home"] |
| pilot.applicability.regional-exeter | alias | CURRENT | CURRENT | True | ["the Exeter home"] | ["Exeter home"] |
| pilot.applicability.regional-exeter | canonical | CURRENT | CURRENT | True | ["Meadow Court"] | ["Meadow Court"] |
| pilot.applicability.regional-exeter | inheritance | CURRENT | CURRENT | True | ["South West", "Meadow Court"] | ["South West", "Meadow Court"] |
| pilot.compare.medication-administration | direct | COMPARE | COMPARE | True | [] | [] |
| pilot.compare.medication-administration | change | COMPARE | COMPARE | True | [] | [] |
| pilot.compare.medication-administration | colloquial | COMPARE | COMPARE | True | [] | [] |
| pilot.current.medication-administration | direct | CURRENT | CURRENT | True | [] | [] |
| pilot.current.medication-administration | colloquial | CURRENT | CURRENT | True | [] | [] |
| pilot.current.medication-administration | abbreviation | CURRENT | CURRENT | True | [] | [] |
| pilot.current.scheduled-medication-version | direct | CURRENT | CURRENT | True | [] | [] |
| pilot.current.scheduled-medication-version | scheduled | CURRENT | CURRENT | True | [] | [] |
| pilot.current.scheduled-medication-version | colloquial | CURRENT | CURRENT | True | [] | [] |
| pilot.current.withdrawn-before-authority | direct | CURRENT | CURRENT | True | [] | [] |
| pilot.current.withdrawn-before-authority | scheduled | COMPARE | COMPARE | True | [] | [] |
| pilot.current.withdrawn-before-authority | colloquial | CURRENT | CURRENT | True | [] | [] |
| pilot.current.withdrawn-no-resurrection | direct | CURRENT | CURRENT | True | [] | [] |
| pilot.current.withdrawn-no-resurrection | withdrawn | COMPARE | COMPARE | True | [] | [] |
| pilot.current.withdrawn-no-resurrection | colloquial | CURRENT | CURRENT | True | [] | [] |
| pilot.location-alias.bristol | alias | CURRENT | CURRENT | True | ["the Bristol home"] | ["Bristol home"] |
| pilot.location-alias.bristol | canonical | CURRENT | CURRENT | True | ["Harbour View"] | ["Harbour View"] |
| pilot.location-alias.bristol | colloquial | CURRENT | CURRENT | True | ["Bristol"] | ["Bristol"] |
| pilot.multi-document.medication-storage | direct | CURRENT | CURRENT | True | [] | [] |
| pilot.multi-document.medication-storage | colloquial | CURRENT | CURRENT | True | [] | [] |
| pilot.multi-document.medication-storage | numeric | CURRENT | CURRENT | True | [] | [] |
| pilot.table.training-refresh | direct | CURRENT | CURRENT | True | [] | [] |
| pilot.table.training-refresh | colloquial | CURRENT | CURRENT | True | [] | [] |
| pilot.table.training-refresh | contrast | CURRENT | CURRENT | True | [] | [] |
| pilot.valid-at-date.medication-administration | dated | VALID_AT_DATE | VALID_AT_DATE | True | [] | [] |
| pilot.valid-at-date.medication-administration | historical | None | VALID_AT_DATE | None | [] | [] |
| pilot.valid-at-date.medication-administration | colloquial | None | COMPARE | None | [] | [] |
| safeguarding.allegations.compare-process | direct | COMPARE | COMPARE | True | [] | [] |
| safeguarding.allegations.compare-process | compare | COMPARE | COMPARE | True | [] | [] |
| safeguarding.allegations.compare-process | colloquial | COMPARE | COMPARE | True | [] | [] |
| safeguarding.allegations.current-hr-timing | direct | CURRENT | CURRENT | True | [] | [] |
| safeguarding.allegations.current-hr-timing | contrast | CURRENT | CURRENT | True | [] | [] |
| safeguarding.allegations.current-hr-timing | colloquial | CURRENT | CURRENT | True | [] | [] |
| safeguarding.body-map.observable-facts | direct | CURRENT | CURRENT | True | [] | [] |
| safeguarding.body-map.observable-facts | cause | CURRENT | CURRENT | True | [] | [] |
| safeguarding.body-map.observable-facts | colloquial | CURRENT | CURRENT | True | [] | [] |
| safeguarding.capacity.unwise-decision | direct | CURRENT | CURRENT | True | [] | [] |
| safeguarding.capacity.unwise-decision | MCA | CURRENT | CURRENT | True | [] | [] |
| safeguarding.capacity.unwise-decision | colloquial | CURRENT | CURRENT | True | [] | [] |
| safeguarding.covert-medication.multi-document | direct | CURRENT | CURRENT | True | [] | [] |
| safeguarding.covert-medication.multi-document | multi | CURRENT | CURRENT | True | [] | [] |
| safeguarding.covert-medication.multi-document | colloquial | CURRENT | CURRENT | True | [] | [] |
| training.medication.compare | direct | COMPARE | COMPARE | True | [] | [] |
| training.medication.compare | compare | COMPARE | COMPARE | True | [] | [] |
| training.medication.compare | colloquial | COMPARE | COMPARE | True | [] | [] |
| training.medication.current-rounds | direct | CURRENT | CURRENT | True | [] | [] |
| training.medication.current-rounds | controlled | CURRENT | CURRENT | True | [] | [] |
| training.medication.current-rounds | colloquial | CURRENT | CURRENT | True | [] | [] |
