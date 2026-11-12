# Block 1 bounded-feedback checkpoint — 2026-11-12

Implemented/pushed `817c307b98b52f30cdbc8f9af01b561165e92b9c`: planner identity v24/v8. Only safe stage/field/code is sent on the existing second eligible attempt. No rejected output, exception text or receipts enter that request. Original turns/catalogue/schema stay intact, all validators remain unchanged, maximum two attempts. The fixed preservation instruction is not a production completeness checker.

## Provider-free proof and limits

[Verification and raw JUnit](../../block1-feedback-20261112/verification.md): AI 1,233→1,241 passing, identical 38 failing names / 4 skips; Laravel unchanged 837 passing / 2 failing names / 9 skips. Zero new/resolved failures. Full suites still exit nonzero. Eight new tests include privacy, bounded feedback, corrected complete output, repeated-invalid exhaustion, transport behavior and first/second-attempt omitted-clause counterexamples. Existing identical-retry expectations were updated to the newly approved bounded difference; preliminary results retained. Sixteen runner tests and the PHP observation test's five controls pass. Changed-source Ruff/Pint pass.

The living plan now assigns general clause omission to Block 1 alongside input-4 drift and makes silently omitted requested parts an explicit Block 4 readiness failure. No production coverage mechanism is designed. The preselected four questions and separate clause expectations were frozen before dispatch at SHA-256 `49047353fca0c8831a199510d584b1ebd233eb65dd6a9c5315eed590026566e7`.

## Once-only live outcomes

| Input | Planner attempts | Requested-part review | Actual user outcome |
| --- | --- | --- | --- |
| b01-04/v1 | First accepted; feedback unexercised | One complete comparison retains before-January frequency AND interval after change; not a lossy merge. Temporal version execution not demonstrated. | Safe no-answer. Topical reviewer `selected` rejected `quote_not_in_candidate`, effective `no_match`; zero final evidence. Separate downstream Block 2 obstruction, not a new feedback fix or a reconstructed historical cause. |
| b01-06/v1 | First rejected `schema / needs.0.fact_reference / fact_date_precision`; second accepted | Approved claim, “last month” and payment timing all retained. **Caveat:** fact_reference types “last month” as duration; not proof of correct relative-calendar representation. | Matching authorised email says next salary run. Answer repeats that and discloses unavailable exact payroll dates/cut-offs. Source text directly checked, including the inert embedded instruction that the answer does not obey. |
| Unfamiliar date/event question | First accepted; feedback unexercised | Both pre-July contact and post-handover successor retained in comparison. | Correct Training Team→IT service desk answer, from the two actual e-learning versions. |
| b01-07/v1 control | First accepted; feedback unexercised | Both starter AND annual replacement changes and April transition retained. | Correct £40→£55 (+£15), £25→£35 (+£10), supported by both versions. |

Full per-case reasoning/source IDs are in [clause-coverage-review.md](clause-coverage-review.md); literal texts/plans/selected evidence in [case-inventory.json](case-inventory.json) and immutable raw `cases/`. Four cases attempted once, five planner requests: **3 first-attempt acceptances; 1/1 second attempts accepted; 0 exhausted-interpretation fallbacks**. Three real answers, one evidence no-answer, zero clarifications/operational failures. All four `needs_pipeline_exercised` flags true. Independent review found zero silent omissions, but the relative-date typing caveat prevents claiming a wholly correct interpretation repair. One observed correction is not a reliable success-rate estimate or causal proof versus sampling variance.

Each case paused for independent review before continuation. b01-06 keeps the frozen security-test scope, authenticated injection workspace/user, injection-only needs opt-in; other cases use authenticated primary workspace/user and primary-only opt-in. No cross-workspace widening. No selective rerun, new retry loop or inline tuning. No cutover, Block 2 repair, full population evaluation or readiness declaration.

## Freeze, accounting and repository

Before/after application aggregate: `4bd179c16a4fefbb49d20f1cf948434cad3ecb0a7a3485e75d46563a35dc7ae0`. Before/after configuration/corpus/actor snapshot: `f271c1ac843e53aaa16f6f0c70f6f3b642499d69dac2467d17e80c42f280d792`. Exact commit stayed `817c307` throughout. Authorization, dispatch ledger, full raw cases, freeze and checksums included. Checksums exclude only their own inventory file.

Cumulative USD 2 authorization; known priced receipts USD **0.00773321**, plus offline OpenAI estimates gives **0.03947546**, not a complete invoice. Six Voyage reranking and twelve Qdrant infrastructure receipts remain unpriced. Recorded provider requests 28; input tokens 123,903 and output tokens 7,844 across receipts. Generation aggregate already includes entailment, not counted twice. [Accounting](accounting.json) is reproducible with `summarise.py`; no missing price fabricated. No further calls made to improve the sample or resolve a price gap.

Remaining Block 1 work includes relative-date/duration distinctions and the general clause-coverage limitation; neither is designed or fixed here. b01-04's original event/calendar choice remains unknown because rejected outputs were not retained; today's first-attempt acceptance does not prove feedback repaired it. b01-10 was not dispatched or changed. Three unrelated tracked edits (ADR-0039 and noninteractive runner/test changes), plus unrelated untracked files, remain untouched. Plan, journal and local gitignored journey updated together. Stop at this report.
