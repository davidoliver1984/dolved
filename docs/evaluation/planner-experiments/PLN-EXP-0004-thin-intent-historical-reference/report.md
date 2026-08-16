# PLN-EXP-0004-thin-intent-historical-reference

Isolated engineering-only four-intent classifier experiment.

## Headline

- Structured reliability: `1.0000`
- Temporal accuracy: `1.0000` over 126 variants
- CURRENT / COMPARE / VALID_AT_DATE / HISTORICAL_REFERENCE: `1.0000` / `1.0000` / `1.0000` / `1.0000`
- False COMPARE / false HISTORICAL_REFERENCE: `0` / `0`
- Prior review-only variants correct: `14/14`
- PLN-EXP-0003 regression count: `0`
- Location precision / recall: `0.8000` / `0.8000`
- Estimated cost: `$0.129360`

## Four-experiment comparison

```json
{
  "PLN-EXP-0001": {
    "estimated_cost_usd": 0.113264,
    "false_compare_count": 8,
    "structured_output_reliability": 1.0,
    "temporal_intent_accuracy": 0.9285714285714286,
    "temporal_intent_by_mode": {
      "COMPARE": 0.9523809523809523,
      "CURRENT": 0.9111111111111111,
      "VALID_AT_DATE": 1.0
    },
    "temporal_review_variants": 0
  },
  "PLN-EXP-0002": {
    "estimated_cost_usd": 0.1482955,
    "false_compare_count": 10,
    "structured_output_reliability": 1.0,
    "temporal_intent_accuracy": 0.9026548672566371,
    "temporal_intent_by_mode": {
      "COMPARE": 0.9523809523809523,
      "CURRENT": 0.8888888888888888,
      "VALID_AT_DATE": 1.0
    },
    "temporal_review_variants": 13
  },
  "PLN-EXP-0003": {
    "estimated_cost_usd": 0.1537165,
    "false_compare_count": 0,
    "structured_output_reliability": 1.0,
    "temporal_intent_accuracy": 1.0,
    "temporal_intent_by_mode": {
      "COMPARE": 1.0,
      "CURRENT": 1.0,
      "VALID_AT_DATE": 1.0
    },
    "temporal_review_variants": 14
  },
  "PLN-EXP-0004": {
    "estimated_cost_usd": 0.1293605,
    "false_compare_count": 0,
    "structured_output_reliability": 1.0,
    "temporal_intent_accuracy": 1.0,
    "temporal_intent_by_mode": {
      "COMPARE": 1.0,
      "CURRENT": 1.0,
      "HISTORICAL_REFERENCE": 1.0,
      "VALID_AT_DATE": 1.0
    },
    "temporal_review_variants": 0
  },
  "comparability_note": "PLN-EXP-0004 adds HISTORICAL_REFERENCE and scores all 14 prior review-only variants; its 126-variant temporal denominator is therefore broader than PLN-EXP-0003."
}
```

## Confusion matrix

```json
{
  "CURRENT": {
    "CURRENT": 88,
    "COMPARE": 0,
    "VALID_AT_DATE": 0,
    "HISTORICAL_REFERENCE": 0
  },
  "COMPARE": {
    "CURRENT": 0,
    "COMPARE": 22,
    "VALID_AT_DATE": 0,
    "HISTORICAL_REFERENCE": 0
  },
  "VALID_AT_DATE": {
    "CURRENT": 0,
    "COMPARE": 0,
    "VALID_AT_DATE": 9,
    "HISTORICAL_REFERENCE": 0
  },
  "HISTORICAL_REFERENCE": {
    "CURRENT": 0,
    "COMPARE": 0,
    "VALID_AT_DATE": 0,
    "HISTORICAL_REFERENCE": 7
  }
}
```

## Per-variant results

- `complaints.handling.compare / direct`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `complaints.handling.compare / compare`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `complaints.handling.compare / colloquial`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `complaints.handling.current-deadlines / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `complaints.handling.current-deadlines / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `complaints.handling.current-deadlines / contrast`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `gdpr.breach.ico-owner / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `gdpr.breach.ico-owner / timing`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `gdpr.breach.ico-owner / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `gdpr.data-protection.compare / direct`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `gdpr.data-protection.compare / change`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `gdpr.data-protection.compare / history`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `gdpr.data-protection.current-reporting / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `gdpr.data-protection.current-reporting / email`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `gdpr.data-protection.current-reporting / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `health-safety.accident.current-riddor-timing / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `health-safety.accident.current-riddor-timing / expanded`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `health-safety.accident.current-riddor-timing / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `health-safety.accident.valid-at-date / dated`: expected `VALID_AT_DATE`, actual `VALID_AT_DATE`, correct `True`
- `health-safety.accident.valid-at-date / historical`: expected `HISTORICAL_REFERENCE`, actual `HISTORICAL_REFERENCE`, correct `True`
- `health-safety.accident.valid-at-date / contrast`: expected `VALID_AT_DATE`, actual `VALID_AT_DATE`, correct `True`
- `health-safety.coshh.review-trigger / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `health-safety.coshh.review-trigger / product`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `health-safety.coshh.review-trigger / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `health-safety.moving-handling.compare / direct`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `health-safety.moving-handling.compare / compare`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `health-safety.moving-handling.compare / colloquial`: expected `HISTORICAL_REFERENCE`, actual `HISTORICAL_REFERENCE`, correct `True`
- `health-safety.moving-handling.current-staffing / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `health-safety.moving-handling.current-staffing / assessment`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `health-safety.moving-handling.current-staffing / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `hr.annual-leave.compare / direct`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `hr.annual-leave.compare / change`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `hr.annual-leave.compare / allowance`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `hr.annual-leave.current-notice / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `hr.annual-leave.current-notice / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `hr.annual-leave.current-notice / table`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `hr.annual-leave.valid-at-date / dated`: expected `VALID_AT_DATE`, actual `VALID_AT_DATE`, correct `True`
- `hr.annual-leave.valid-at-date / old`: expected `HISTORICAL_REFERENCE`, actual `HISTORICAL_REFERENCE`, correct `True`
- `hr.annual-leave.valid-at-date / contrast`: expected `VALID_AT_DATE`, actual `VALID_AT_DATE`, correct `True`
- `hr.disciplinary.suspension-neutral / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `hr.disciplinary.suspension-neutral / review`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `hr.disciplinary.suspension-neutral / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `hr.lone-worker.coventry-overdue / alias`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `hr.lone-worker.coventry-overdue / timing`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `hr.lone-worker.coventry-overdue / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `infection.outbreak.valid-before-withdrawal / dated`: expected `VALID_AT_DATE`, actual `VALID_AT_DATE`, correct `True`
- `infection.outbreak.valid-before-withdrawal / historical`: expected `HISTORICAL_REFERENCE`, actual `HISTORICAL_REFERENCE`, correct `True`
- `infection.outbreak.valid-before-withdrawal / contrast`: expected `VALID_AT_DATE`, actual `VALID_AT_DATE`, correct `True`
- `medication.controlled-drugs.current-discrepancy / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.controlled-drugs.current-discrepancy / contrast`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.controlled-drugs.current-discrepancy / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.controlled-drugs.valid-at-date / dated`: expected `VALID_AT_DATE`, actual `VALID_AT_DATE`, correct `True`
- `medication.controlled-drugs.valid-at-date / historical`: expected `HISTORICAL_REFERENCE`, actual `HISTORICAL_REFERENCE`, correct `True`
- `medication.controlled-drugs.valid-at-date / contrast`: expected `HISTORICAL_REFERENCE`, actual `HISTORICAL_REFERENCE`, correct `True`
- `medication.covert.capacity-requirements / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.covert.capacity-requirements / refusal`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.covert.capacity-requirements / abbreviation`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.error-form.immediate-safety / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.error-form.immediate-safety / priority`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.error-form.immediate-safety / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.fridge.boundary-table / upper`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.fridge.boundary-table / decimal`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.fridge.boundary-table / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.prn.minimum-interval / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.prn.minimum-interval / expanded`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `medication.prn.minimum-interval / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.adversarial.visitor-negative / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.adversarial.visitor-negative / sign-in`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.adversarial.visitor-negative / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.applicability.ambiguous-home / ambiguous`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.applicability.ambiguous-home / pronoun`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.applicability.ambiguous-home / underspecified`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.applicability.bristol-conflict / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.applicability.bristol-conflict / conflict`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.applicability.bristol-conflict / multi-document`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.applicability.regional-exeter / alias`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.applicability.regional-exeter / canonical`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.applicability.regional-exeter / inheritance`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.compare.medication-administration / direct`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `pilot.compare.medication-administration / change`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `pilot.compare.medication-administration / colloquial`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `pilot.current.medication-administration / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.current.medication-administration / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.current.medication-administration / abbreviation`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.current.scheduled-medication-version / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.current.scheduled-medication-version / scheduled`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.current.scheduled-medication-version / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.current.withdrawn-before-authority / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.current.withdrawn-before-authority / scheduled`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `pilot.current.withdrawn-before-authority / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.current.withdrawn-no-resurrection / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.current.withdrawn-no-resurrection / withdrawn`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `pilot.current.withdrawn-no-resurrection / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.location-alias.bristol / alias`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.location-alias.bristol / canonical`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.location-alias.bristol / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.multi-document.medication-storage / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.multi-document.medication-storage / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.multi-document.medication-storage / numeric`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.table.training-refresh / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.table.training-refresh / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.table.training-refresh / contrast`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `pilot.valid-at-date.medication-administration / dated`: expected `VALID_AT_DATE`, actual `VALID_AT_DATE`, correct `True`
- `pilot.valid-at-date.medication-administration / historical`: expected `VALID_AT_DATE`, actual `VALID_AT_DATE`, correct `True`
- `pilot.valid-at-date.medication-administration / colloquial`: expected `HISTORICAL_REFERENCE`, actual `HISTORICAL_REFERENCE`, correct `True`
- `safeguarding.allegations.compare-process / direct`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `safeguarding.allegations.compare-process / compare`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `safeguarding.allegations.compare-process / colloquial`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `safeguarding.allegations.current-hr-timing / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `safeguarding.allegations.current-hr-timing / contrast`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `safeguarding.allegations.current-hr-timing / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `safeguarding.body-map.observable-facts / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `safeguarding.body-map.observable-facts / cause`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `safeguarding.body-map.observable-facts / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `safeguarding.capacity.unwise-decision / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `safeguarding.capacity.unwise-decision / MCA`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `safeguarding.capacity.unwise-decision / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `safeguarding.covert-medication.multi-document / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `safeguarding.covert-medication.multi-document / multi`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `safeguarding.covert-medication.multi-document / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `training.medication.compare / direct`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `training.medication.compare / compare`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `training.medication.compare / colloquial`: expected `COMPARE`, actual `COMPARE`, correct `True`
- `training.medication.current-rounds / direct`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `training.medication.current-rounds / controlled`: expected `CURRENT`, actual `CURRENT`, correct `True`
- `training.medication.current-rounds / colloquial`: expected `CURRENT`, actual `CURRENT`, correct `True`
