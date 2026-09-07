# R01P structural framing and bounded validation retry — 2026-09-07

Status: R28-S04/R01P in progress. No R02, new question set, commit, push or closure.

## Authority and code boundary

David's latest attachment approved both corrections, provider-free proof, then exactly nine top-level executions: e08, e16, e26; e23 three times; e03, e14, e22. These are known-case regressions, not independent confirmation. Starting HEAD and aggregate were verified as 6fdc2981732773ff08be8e3b5c1f505112501c87 and 94d90369816f5f606fd2508de76f70e7445d059e7ca053879732a02f684c4b28. Existing uncommitted work was preserved.

The changed source was frozen before dispatch at **27ca0f395cb7c517800acd368c45744774e1824ae7f291c5cc992118227e581c** (939 runtime inventory entries). This tests frozen **uncommitted** code, not a new commit. Execution pin: bounded-needs-slice-v10; planner prompt bounded-evidence-needs-v10 unchanged, adapter structured-chat-needs-v5; generation prompt grounded-generation-needs-v5, adapter openai-responses-needs-v2 unchanged. Models, indexes, thresholds and catalogue authority unchanged.

Evidence directory: `/tmp/dolved-structural-retry.u81jkA`. Its temporary ASGI observation wrapper delegates to the same production adapter/routes and writes only answer-part text/handles and outcome to isolated evidence files before final validation. It does not alter provider input, output, retries or acceptance. It passed a fake-response proof. General application diagnostics remain category/field/code plus numeric accounting; no raw bodies, headers or credentials.

## Structural rule and synthetic precision

A leading Yes/No (including supported simple Markdown emphasis) must be followed by comma, colon or em dash and nonblank subsequent text. Period/exclamation/question mark, semicolon/hyphen, or no justification is invalid. This checks the authorised punctuation structure, **not semantic agreement or arbitrary grammar**.

All original 17 examples were rerun, relabelled explicitly against this new structural requirement, plus nine edge cases: **26/26**, seven accepted and nineteen rejected, zero false positives/negatives against these structural labels. In particular, the fictional contradictory comma example is structurally accepted; this is not a claim that contradiction detection was implemented. Natural consistent "Yes. ..." examples are deliberately invalid under the new writing contract, not semantic false positives.

| Case | New structural result |
| --- | --- |
| comma_yes | accept |
| dash_no | accept |
| period_yes_consistent | reject |
| period_no_consistent | reject |
| newline_consistent | reject |
| bare_yes | reject |
| bare_no | reject |
| contradictory_yes | reject |
| contradictory_no | reject |
| unrelated | reject |
| lowercase_whitespace | reject |
| exclamation_consistent | reject |
| subject_first | accept |
| comma_contradictory | accept; semantics not judged |
| colon_consistent | accept |
| markdown_detached | reject |
| no_space_detached | reject |
| emphasis_joined | accept |
| emphasis_detached | reject |
| empty_comma | reject |
| empty_colon | reject |
| empty_dash | reject |
| hyphen | reject |
| semicolon | reject |
| question_mark | reject |
| ordinary_no_word | accept |

## Retry ownership and exhaustive boundary classification

There is one validation retry owner per boundary. Python repeats the planner call at most once, with identical turns/catalogue/evaluation instant/prompt and request bytes. Laravel repeats the already assembled complete-result needs-generation request at most once. It does not rerun planning/retrieval/assembly. No repair feedback is sent. Existing native transport retries retain their existing owner and configuration; an exhausted two-attempt planner validation history cannot start another Laravel outer retry. Thus validation attempts and physical transport attempts are distinct, not a promise that every question has at most two physical network requests.

### Planner

The new trigger requires an actual NeedsValidationFailure, provider status 200, finish_reason stop, and schema/grounding/catalogue rejection. A generic error class or an outer HTTP 503 is not enough.

| Failure/category or controlled result | New validation retry | Reason |
| --- | --- | --- |
| invalid_grounded_needs_plan: completed schema failures (json_invalid, missing, literal_error, uuid_parsing, extra_forbidden, string_type, too_short, too_long, value_error/invalid_value) | once, except graceful fact-date cases below | Received completed output rejected by deterministic schema |
| binding_coverage, unknown_family, binding_shape, ownership_grounding, ownership_location_conflict | once | Deterministic catalogue/ownership rejection; enforcement is unchanged |
| calendar_grounding, historical_grounding, comparison_authority, authority_grounding, authority_precision, need_order | once when raised at eligible stage | Grounding/shape rule, not provider failure; normal need-order reconciliation still precedes returned result |
| grounding/root/reference_grounding | no | Existing safe clarification, not a failure to fish past |
| schema needs.N.fact_reference: value_error, fact_date_precision, fact_date_interval_shape, fact_date_interval_reversed, fact_date_calendar | no | Existing typed safe fact-date clarification; no invented date |
| completion/output_truncated, output_not_completed; refusal/content_filter/tool_calls/function_call | no | Not successfully completed model output |
| envelope failure, unknown completion reason/non-text output | no | Insufficient evidence of a completed schema-bearing output |
| invalid_typed_plan (e.g. invalid authorised-turn input), catalogue_bound_exceeded | no | Local input/pre-dispatch problem |
| provider_authentication, provider_quota | no | Account/systemic failure, not validation |
| provider_rate_limit, provider_unavailable, provider_request_rejected, transport_error | no | Existing transport/provider policy only |
| planner_service_rejected, Laravel response identity/envelope/schema/catalogue revalidation failure, query_not_preserved (legacy) | no extra validation loop | Fail closed; not the Python completed-output retry owner |
| valid clarify; downstream clarification_required, insufficient_evidence, comparison_scope_incomplete, no evidence/refusal | no | Legitimate controlled outcome |
| second failure of any kind | no further validation retry | Terminal; both attempt receipts retained |

### Generation

Only versioned needs + complete-result mode are eligible. A remote rejection requires contract_validation_failure, returned_output=true, and an allowlisted validation code/field pair; generic category-only failures do not qualify.

| Failure/category | New validation retry | Reason |
| --- | --- | --- |
| detached_polarity; outcome_invariant; citation_membership; duplicate_citation; blank_answer; need_coverage; reference_range; empty_need; unmapped_content; coverage_outcome; citation_ownership; missing_evidence_gap; schema_validation | once for a received output at the typed Python boundary | Exact allowlisted validation failure, enforced again on second output |
| Laravel request_needs, needs_schema, result_schema, result_fields, part_shape, citation_membership, outcome_invariant, usage_shape, need_identity, need_coverage, empty_need, unmapped_content, coverage_outcome, reference_list, reference_range, citation_ownership, missing_evidence_gap | once only after a result payload was received | Actual local deterministic result-validator rule |
| transport_failure, rate_limit, timeout | no new validation retry | Native transport policy unchanged |
| malformed_typed_output, provider_refusal | no | No accepted completed typed output/explicit refusal |
| contract_validation_failure without received-output evidence or without safe recognised diagnostic | no | Includes unconfigured adapter and unclassified failures |
| context budget exceeded, bad request/envelope/identity/profile, assembly failure, cancellation, persistence/internal errors, stream protocol failure | no | Not model-output validation; fail at responsible layer |
| legacy or streaming generation | no new retry | Outside bounded needs complete-result correction |
| valid answered, qualified, insufficient_evidence | no | Valid outcome terminates immediately |
| second rejection or second different failure | no third call | Both receipts survive; failure remains visible |

Attempt receipts retain numeric request/retry counts, input/output/cache tokens where available, latency, estimated cost or explicit unavailable, and allowlisted rejection details. Successful generation combines both receipts in persisted usage; planner usage retains its bounded history through Laravel. Failed runs retain histories in existing diagnostics. A native transport sequence with unavailable earlier usage is not falsely labelled fully metered. Planner cost has no provider-reported price; the run report uses labelled configured-rate estimates. Validation retries can roughly double the affected boundary's normal cost; standing USD 5/90-minute authority is **not increased**. The test harness reserves USD 2 per question, settles fully metered receipts (including rejections), and retains the reserve when any attempt remains unmetered.

## Provider-free verification

- Python: 266 passed across evidence-needs, provider schema, generation needs request/transport/output, aspect coverage, generation contract, real OpenAI adapter, safe diagnostics, structural matrix and retry tests.
- Laravel before live execution: 213 passed / 842 assertions across generation foundation, conversation orchestration, needs client/retrieval, semantic/hybrid retrieval and needs/evidence/aspect unit tests. Three opt-in bridge tests skipped in this command and separately run: **3 passed / 37 assertions**. Final post-run receipt-persistence verification: **214 passed / 854 assertions**, plus the same separately verified bridge.
- Synthetic retry proof: same request bytes; fail then success with doubled tokens/latency/cost; two invalid results stop at two; second transport failure preserves first rejection; valid clarification/insufficiency does not retry; graceful fact/date and provider/transport/completion exclusions.
- Ruff lint and format checks passed; Pint 15-file check passed; scoped mypy 8 production files passed; shared schemas match generated models; git diff --check passed.
- No provider-free check dispatched a live provider request.

## Live results

All nine completed through production, all retrieval outcomes evidence_found, all citations resolving to the isolated primary workspace. Eight generated answered; one generated qualified. No native or validation retry fired. Full verbatim questions, answers, recorded gaps, citations and run IDs are in the evidence directory's `actual-results.md` and `observations-and-citations.json`.

| Execution | Generation outcome | Needs | Retries |
| --- | --- | --- | --- |
| e08 | answered | 1 | 0 |
| e16 | answered | 1 | 0 |
| e26 | answered | 1 | 0 |
| e23 attempt 1 | qualified | 2 | 0 |
| e23 attempt 2 | answered | 2 | 0 |
| e23 attempt 3 | answered | 2 | 0 |
| e03 | answered | 1 | 0 |
| e14 | answered | 1 | 0 |
| e22 | answered | 2 | 0 |

e16 and e26 now used connected `No, ...` framing. e08 used subject-first statements. e14 answered “Review cable routing quarterly.” citing document 18 / chunk 92. e03/e14 remained single-need controls; e22/e23 remained two-need controls.

**Residual finding, not smoothed into a pass:** e23 attempt 1's planner expanded “how often are the hoists actually checked?” into “required inspection frequency and record-keeping procedure”. Generation answered the frequency but qualified the result because formal record-keeping evidence was unavailable. Record-keeping was not asked in the question. The other two e23 attempts returned answered. No fix, prompt tuning or extra run was made in response. This is successful execution evidence, not a scored correctness rate or proof that the retry absorbed live failures: no live retry fired. That path is demonstrated by provider-free tests only.

## Usage and elapsed time

| Stage | Requests/attempts | Input tokens | Output tokens | Estimated USD |
| --- | --- | --- | --- | --- |
| Planner | 9 | 142,458 | 6,504 | 0.04862250 |
| Generation | 9 | 216,639 | 5,517 | 0.06358095 |
| Query embedding | 13 | 376 | not applicable | 0.00004512 |
| Reranking | 13 | 86,619 | not applicable | 0.00433095 |
| Total | 44 | 446,092 | 12,021 | 0.11657952 |

Zero retries; retry-attributable cost USD 0, single-attempt cost USD 0.11657952. Elapsed 197.123 seconds of 5,400; USD 0.11657952 of USD 5. All costs are estimates, not provider invoice certification. Planner and reranker costs use the existing configured-rate calculation; generation and embedding retain their adapter-reported estimates. There was no corpus embedding, judge, AWS access or additional top-level attempt.

## Final metadata-only follow-through and preservation

Immediately after all nine completed, the execution source was verified unchanged at `27ca0f395cb7c517800acd368c45744774e1824ae7f291c5cc992118227e581c`. Final reporting then exposed one receipt-persistence omission: WorkspaceUsageEvent preserves planner totals but not the per-attempt array. The already-produced safe planner receipts are now also persisted in the existing GenerationRun usage, with a synthetic first-rejection/second-clarification test proving both remain present and private content is removed. This is provider-free post-run metadata follow-through, **not a live-tested source change**. No decision, provider input, retry count or acceptance rule changed and no question was rerun.

Final source aggregate: `2881d2078fcced5f8fb0757f144d07a92040a2da2ec4de7528863a0b45f37f40`. The only runtime file different from the live freeze is `apps/api/app/Actions/Conversation/OrchestrateConversationRun.php`; its test changed too. Both identities are retained in `final-source-freeze.json`. Do not describe the final hash as the live execution hash.

HEAD remains `6fdc2981732773ff08be8e3b5c1f505112501c87`. The accumulated changes remain uncommitted; there was no commit or push authority in this task. All 33 unrelated untracked files remain byte-identical and untracked, and the four preceding evidence-package inventories (190/66/35/48 files) still verify. No answer key was accessed or inferred. R28-S04/R01P remain in progress; stop for David's review, no R02 or new question selection.
