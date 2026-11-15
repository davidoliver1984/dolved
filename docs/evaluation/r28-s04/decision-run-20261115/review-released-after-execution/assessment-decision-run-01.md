# Reviewer assessment — R28-DECISION-BACKEND-20261115-01 (2026-11-15)

Reviewer: Claude, grading independently against the sealed expectations.json (a397b69e…edb5df) and its frozen grading rules. Evidence: hold commit 270fc74, backend-run/.

## Execution
- The run stopped at H2 on a runner post-dispatch JSONDecodeError (harness-side, cause inconclusive). 14/34 dispatched, all from the fresh cohort; 20 not dispatched (H3, H4, C1–C4, M1–M4, K1–K4, and all six regressions).
- Production H2 completed and persisted; its saved answer is graded below, flagged as runner-unfinalised.

## Grades (backend saved message; delivery flag separate)
| Item | Group | Grade | Cause / note |
|---|---|---|---|
| O1, O3, O4, O5, O6, O8, O9, O10, O11, O12 | ordinary | FAIL (misleading no-evidence) | Retrieval qualified 2–15 candidates per need, but the coverage selector returned invalid_output (HTTP 503). CoverageSelectionClient treats invalid_output as non-fallback, so RetrieveWorkspaceEvidence returned InsufficientEvidence and generation was never called. "Couldn't find enough available evidence" is false per the trace. The selector's rejection reason was not captured. |
| O2 | ordinary | FAIL (misleading no-evidence) | Selector proposed correctly. Both generation attempts were correct ("No — trade union rep or work colleague, not family") and both were rejected with answer_parts.text / entailment_uncertain. |
| O7 | ordinary | FAIL (misleading no-evidence) | The plan split the question into two distinct needs (retention; viewing), and the selector proposed. Both generation attempts linked both parts to both needs and were rejected with answer_parts.evidence_ids / citation_ownership. The rejected drafts contained the correct 31-day retention figure. (Corrected: an earlier draft wrongly said the two needs had identical spans.) |
| H1 | historical | FULLY CORRECT | Verbal order, order book completed within 24h, cited from v1 only. The partner-site alternative matches v1 wording ("both sites' order books"), not v2's electronic system. Temporal basis shown. Delivery flag: the temporal basis appears only in rendered text. |
| H2 | historical | NOT GRADED: dispatched, not runner-finalised | Production completed and persisted, but runner capture/integrity finalisation failed (JSONDecodeError), so there is no executor record or verdict. Observation only: the saved text reads "< 25% annualised" under v1 (effective 2025-04-01 to 2026-09-01), consistent with the expectation. This is not counted as a validated result. |

## Gate (frozen; applied as written)
- Ordinary: 0/12 fully correct (required 12/12). **FAIL.**
- Fresh: 1 validated fully correct (H1), 12 failed, H2 unvalidated, 10 not dispatched. The maximum achievable is 12/24 against the required 22/24. **FAIL, independent of the stop.**
- No wrong factual answers among those dispatched.
- Historical group 2/2 dispatched; comparison, multipart, controls and regressions were not run.

**Decision-gate outcome: FAILED.** Under the agreed terms, the current repair programme stops.

## Evidence basis
Stages are taken from the API trace records (coverage_selection status/reason, generation call count, generation sidecar validation field and code), not from HTTP status. For all 12 ordinary items, the governing source named at authoring was among the retained threshold-qualified or final evidence, so the no-evidence message is misleading under the grading rules.

## Limits of this assessment
- 14 of 34 items. Controls and regressions are unassessed.
- The selector's rejection details are unavailable, so the specific validation failure is not established.
- This assessment does not claim what generation would have produced for the 10 selector-blocked items.
