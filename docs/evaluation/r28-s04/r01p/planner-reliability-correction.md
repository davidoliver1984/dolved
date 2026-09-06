# R01P planner reliability correction — 2026-09-06

## Boundary and diagnosis

Starting HEAD was `f1c7e69f3798558ddeef3e1f43aa6df2d0dacc9a`. Both planning files were already modified. The prior 935-file runtime-source aggregate matched `4ac0bc20a2526d22e179e0bf85276714df417b99789141fdcad1ff7650a4fa07`. No unexpected source drift was found or reverted.

Persisted source evidence: `/tmp/dolved-confirmation20.mGG6uG/part2/c06.json`, `c07.json`, `c10.json`, manifest/dispatch records and combined observations. All 119 original checksums still match after this task.

- **c06:** HTTP 200, one attempt, finish stop, 1,099 completion tokens / 384 reasoning. The exact failing validator was grounding/root/need_order. The consumer rejected a decrease in (user-turn ordinal, first exact quote position). Rejected provider text was not retained: we cannot claim its exact wording or that every later check would have passed.
- **c07:** HTTP 200, one attempt, finish stop, 433 completion tokens / 128 reasoning. Grounding/root/reference_grounding identifies a quote absent from its claimed authorised turn (or a missing turn ID). The retained diagnostic does not identify which source field. Previously this propagated to retrieval_failed.
- **c10:** HTTP 200, one attempt, finish length, all 4,096 completion tokens were reasoning, leaving zero visible tokens. This was not a network error.

## Exactly three corrections

1. Planner combined completion allowance 4,096 → 25,000; low reasoning unchanged. The planner actually uses Chat Completions, not Responses. Its supported `max_completion_tokens` includes reasoning and visible output; no separate guaranteed visible-output reserve is documented. [Chat API reference](https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create). [Official reasoning guidance](https://developers.openai.com/api/docs/guides/reasoning) recommends at least 25,000 combined tokens when establishing the allowance. This increases headroom, **not a guarantee against future exhaustion**; truncation still fails honestly without hidden retries.
2. Validate all individual grounding and catalogue invariants, then stably sort needs by existing question-span provenance. Remap sequential IDs, support edges and corresponding bindings together; revalidate the resulting plan. No aspect, source, authority, applicability or family selection is invented, widened or dropped. Strict grounding remains enabled.
3. Only the exact non-systemic grounding/root/reference_grounding failure in the needs path becomes existing controlled clarification with enum reason `ungrounded_needs_reference`. No search/generation is dispatched for this rejected plan; safe failure metadata and usage remain persisted. Other validation categories still fail. This is safe failure handling, not a claim the original inference has been repaired.

Planner adapter is `structured-chat-needs-v4`, execution pin `bounded-needs-slice-v7`; matching producer/consumer/test identities updated. Model, prompt, schema shape, thresholds, needs/lane limits and indexes unchanged. Generation's separate 4,096 allowance is unchanged.

## Provider-free verification

Final full relevant run: **169 Python passed** (evidence needs, provider schema, retrieval routes, generation needs output/request/transport, aspect coverage). **152 Laravel passed / 485 assertions; 3 optional bridge tests skipped** (conversation orchestration, needs client/retrieval, generation foundation, production diagnostic, aspect coverage, needs plan/selection).

Scoped Ruff lint and format passed; scoped Pint passed for eight files; mypy passed on two production modules; git diff --check passed. Initial lint/type complaints in the new helper were corrected before the final full Python run.

Synthetic proofs cover:
- Valid but reversed needs: reordered, with exact family binding and support-edge preservation, idempotently; invalid provenance still rejected.
- reference_grounding: typed clarification persisted through real Laravel orchestration, one fake planner dispatch, no generated answer; other failure and ordinary valid-plan tests remain passing.
- Fake response using 5,000 reasoning + 1,000 visible tokens now fits the 25,000 transmitted allowance and validates; explicit length responses remain failures. This proves configuration/transport/validation only, not model reliability.

## Known-case live regression (not independent confirmation)

Only the original c06/c07/c10 text, verbatim, ran once, through the production conversation action in the existing isolated primary workspace. No other 17 questions, no new question set, no judge/reindex/AWS and no selective rerun.

| Case | Observed result | Citation check |
| --- | --- | --- |
| c06 | completed / answered: report clicked phishing link to IT immediately; reporting and escalation guidance | Cited chunks 285/301/722/727 support reporting, containment and escalation details |
| c07 | completed / answered: automatic pension re-enrolment every three years, with option to opt out again | Cited chunks 319/320 support the answer |
| c10 | completed / answered: hotline permits anonymity with stated disclosure exceptions | Cited chunks 196–200 support the answer |

All cited IDs resolve in the primary workspace. No controlled clarification was needed on this particular regression: its exact fallback branch is proven by the provider-free test. Live completion alone does not prove that ordering reconciliation fired or that grounding failures cannot recur.

**14 physical attempts; 0 retries; 100,623 input tokens; 3,907 output tokens; configured-rate estimate USD 0.02892375; elapsed 72.8759 seconds.** Limits: USD 5 / 5,400 seconds, concurrency one, existing native retries. No exhausted limit. Cost is estimated, not an invoice.

Immutable raw evidence and actual cited canonical passages: `/tmp/dolved-reliability3.w6yZgp/run/` and `observations-and-citations.json`. Original container directory: `/tmp/dolved-reliability3-20260906-0001`.

## Frozen state and commit boundary

Runtime-source aggregate before and after live regression: `adeafbe3610f920f8da363763fe885d54345c314b9a67ba0af4f8754810ee398` (935 files). No application change occurred during or after the regression.

R28-S04 remains in_progress. R01P remains open: correction/regression complete, next independent confirmation and expansion decision pending. S05/S06/S07 and R28-GATE remain not_started. R02 was not begun and no next questions were authored or selected.

**No commit or push performed.** The starting implementation already contained extensive uncommitted R01P changes, including files this correction depends on. Committing those complete files would incorporate prior work beyond this correction; staging only today's delta against HEAD would not create a self-contained working implementation. That broader commit boundary was not silently taken. This limitation must be resolved before representing the next confirmation as committed-code evidence.

## Exact changed-file hashes

These are this task's seven production files, five test files and two planning files. The present report is the only additional repository file. Earlier unrelated modifications/untracked files are preserved.

| File | SHA-256 |
| --- | --- |
| `apps/ai/app/retrieval/needs.py` | `c4e94de1b3187cbf001645e5c3a62788023e5a9698800ebb7672ed4c86d03d73` |
| `apps/ai/app/retrieval/needs_planner.py` | `8ff8cd50de8e484172f8dcb96e84c43ee1a92b815db202858a2116c9fc056a4e` |
| `apps/api/app/Actions/Conversation/OrchestrateConversationRun.php` | `aba5b02df8ae26622c9fcdeffa55bca07ce2c610cc62e5a403bdbce94b458977` |
| `apps/api/app/Enums/PlannerClarificationReason.php` | `2f4ffc403a90358ac24f7e877272681ac603ac47c79a6350a10fc21031984662` |
| `apps/api/app/Services/Conversation/ControlledResponseRenderer.php` | `b8c2f546a58795e6fa3ab5fd7dbc6ca3156064ba378c9d8b18e3fe3c29d8b29c` |
| `apps/api/app/Services/Generation/GenerationProfileFactory.php` | `ee74ba276d51a4984b79374017fac26e16b383c371d87ca75a9775f64abfae94` |
| `apps/api/app/Support/Retrieval/EvidenceNeedsPlan.php` | `07638237ab7a75c7f7cedda6bd6b60884c38c4576e6151b8d20b5f856a065b85` |
| `apps/ai/tests/test_evidence_needs.py` | `1b9caa6895697cb28e70590a772d61fca300d90849f01c44f82c24baf70e7b1c` |
| `apps/ai/tests/test_needs_provider_schema.py` | `0295bedd9393e15d07c3dd78f5ed0d448318c2d3458faa71a450fd371225f4c9` |
| `apps/api/tests/Feature/ConversationOrchestrationTest.php` | `c7ab35461b2031d8b00b1000fb3d3bcd544a6b41bad5be90acad2f90042f36d8` |
| `apps/api/tests/Feature/EvidenceNeedsClientTest.php` | `176165ddbd01b9e67664e3a4a0ffad04cc9ece921cc47ec7c5d9c098f482816a` |
| `apps/api/tests/Feature/EvidenceNeedsRetrievalTest.php` | `7aaf6bab7ba28a7c0f6ccc2f636ba0cf009fa3a08c2ce047a983038bee4e3fb2` |
| `docs/evaluation/r28-s04/production-repair-plan.md` | `f847bc5281acfb3c4db248228c224c15a514729c174c8541146b4ca9c8987e74` |
| `tasks.json` | `eebb4b7f9d5366c639ff55a6d72abd99d732a0102407f74e55bb2a6b88a396e7` |

