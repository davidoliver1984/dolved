# Extractive Block 1 candidate — acceptance HOLD, 2026-11-12

**Stopped before live dispatch.** The fixed full-suite criterion fails: eight new
AI failures and seven new Laravel failures beyond the approved 38/2 baseline.
No live authorization consumed, no provider calls, USD 0 spent. No Recall@5 figures
or closing-question outcomes exist for this candidate. No tuning or second full
suite after this failed acceptance comparison.

Baseline `348f09e053c8ad73676921c5c616cced812111d7` was origin/main at start.
The candidate is prepared locally on `codex/block1-extractive-hold-20261112`,
**not committed, pushed or promoted to main**. The execution approval system
rejected the combined commit/push request after the failed gate, interpreting
David's stop condition as withholding that remote side effect. No workaround or
alternative push was attempted. Scoped changes are staged; the corrected report
and plan status remain additional local edits pending David's decision.
The implementation is not accepted or ready for live execution. Unrelated ADR and
mixed governed-runner/test edits are preserved outside the scoped commit.

## What is implemented in the held candidate

- Plan v4 / prompt v26 / adapter v10: exact context spans for every need, no
  target-question exceptions. Grounding validates each source quotation/turn.
- Application compilation: ordered context quotes, requested clause, then unique
  grounded typed reference quotes, spaces only. Cross-turn pronouns retain their
  wording and receive quoted preceding context; no generated noun replacement.
- Model aspect retained in diagnostic plan only. The checker sees the compiled
  execution view; PHP independently compiles before retrieval and coverage.
  Search/rerank/topical selection consume the compiled RetrievalPlan query.
- Generation envelope v3, extractive-request-v1: original span primacy and
  existing ownership remain; Python rejects disagreement between the compiled
  request and supplied aspect. Generation prompt lineage v12.
- Historical plan v3 and generation v2 remain readable. Live producer/client
  reject legacy plan execution; no persisted record or old evidence is rewritten.
- No new model call, ranking/threshold/lane/retry change, authority/citation
  relaxation, alias work or one-off Finance/short-span repair.

This is the approved structural design, not a guarantee of retrieval relevance:
grounded context selection remains fallible. Before a live checkpoint the entire
producer/consumer and retry-test compatibility must be sound, which is not yet
proved by this candidate.

## Provider-free verification and circuit breaker

Raw XML and logs are under `verification/`; exact sets and failure messages are in
`suite-comparison.json`, reproducible with `compare_suites.py`.

| Suite | Baseline | Candidate | New | Resolved |
|---|---:|---:|---:|---:|
| AI | 38 failures / 1,347 tests | 46 failures / 1,354 tests | **8** | 0 |
| Laravel | 2 failures / 863 tests | 9 failures/errors / 864 tests | **7** | 0 |

The seven new extractive Python cases pass in the full suite: arbitrary aspect
mutation, user-supplied examples, cross-turn pronoun/context grounding, independent
clauses with comparison/relative-date/claim-site fields, generation recomputation
and ownership rejection, and legacy read compatibility. The PHP extractive test
passes; the production retrieval/disclosure test carries an arbitrary invented
aspect and checks it cannot enter search or coverage. These successes do not waive
the full-suite gate or establish live retrieval quality.

New AI failures:

- `tests.test_generation_needs_transport::test_shared_response_schema_and_tagged_failures`
- `tests.test_interpretation_check::test_closed_feedback_two_total_attempts_and_complete_usage[False-meaning_changed]`
- `tests.test_interpretation_check::test_closed_feedback_two_total_attempts_and_complete_usage[False-scope_added]`
- `tests.test_interpretation_check::test_closed_feedback_two_total_attempts_and_complete_usage[True-meaning_changed]`
- `tests.test_interpretation_check::test_closed_feedback_two_total_attempts_and_complete_usage[True-scope_added]`
- `tests.test_interpretation_check::test_unverifiable_flag_keeps_valid_plan_without_an_additional_attempt[True-clause_omitted]`
- `tests.test_interpretation_check::test_unverifiable_flag_keeps_valid_plan_without_an_additional_attempt[True-meaning_changed]`
- `tests.test_interpretation_check::test_unverifiable_flag_keeps_valid_plan_without_an_additional_attempt[True-scope_added]`

Observed assertions: the response-schema test still compares the old contract;
checker tests expect rejection/retry counts but receive an accepted/no-retry path
after the execution-view change. These cannot be silently waived as baseline or
fixed by weakening their safety assertions. No follow-up correction was made.

New Laravel failures:

- `Tests.Feature.ConversationOrchestrationTest::test_evidence_found_runs_the_complete_generation_and_persists_one_authoritative_answer with data set #1`
- same test, data sets `#2`, `#3`, `#4`
- `Tests.Feature.ConversationOrchestrationTest::test_needs_clarification_keeps_text_separate_from_the_bounded_reason_code with data set #0`
- same test, data set `#1`
- `Tests.Feature.EvidenceNeedsRetrievalTest::test_scope_and_operational_answer_are_isolated_through_generation_and_persistence`

Full names/details are in the JSON. Orchestration fixtures and mixed scope/operational
generation remain incompatible with the bumped boundaries. This is incomplete
integration proof, not permission to call the live cases or claim harmlessness.

During initial focused development, before the full-suite acceptance gate, migrated
producer fixtures were updated to the new version; a new test used interval rather
than the existing year precision for “this year,” and a fake generation response
needed to echo the request envelope. Initial logs are retained. Scoped Ruff/Pint
ran; this report does not claim a fresh full typecheck or entirely green suite.

## Fixed acceptance criteria

| Criterion | Position |
|---|---|
| No ungrounded executed query/coverage wording | Structural new tests pass; broader integration has failures, no live proof. |
| Same-case attempted-search Recall@5 loss <=0.03 on either side | **Not measured:** live precondition failed. |
| No new interpretation-failure/clarification on clear questions | **Not established:** orchestration/retry verification fails; no live run. |
| No new AI/Laravel failures beyond the approved baseline | **Failed:** 8 AI +7 Laravel new, exact lists retained. |

The approximately 30 labelled-case selection and USD 3 live reservation were not
finalized or dispatched after the gate failure. Neither excluded development
question was rerun. Their wording is already known from diagnosis; any future
outcome check must not be presented as independent unseen confirmation.

**Recommendation: HOLD Block 1 and keep this candidate off origin/main.** David
decides the disposition of the failed contract/integration tests before any further
repair or run. Original records, metric expectations and unrelated edits remain
unchanged. No Block 2 work started.
