# Block 1 — checker citations closing checkpoint, 2026-11-12

**Recommendation: HOLD Block 1 for accepted interpretation drift, not for a retry-triggering false flag.** The citation safeguard worked on both observed flags, and all six once-only questions produced answers. Input 5 still interprets the employee audience “Alderbridge staff” as a named location, which the checker accepts and the renderer turns into an unwarranted coverage notice. This repeats the reference-role drift already observed in the previous relative checkpoint (document title treated as a location). No repair, prompt tuning, additional dispatch or Block 2 work follows this result.

## Frozen execution and verification

- Runtime commit: `737776d743087546d9f8493b0f998bd5f3d81b75`, pushed before dispatch. Baseline: `ac280e2c61eda0ce364c6e6cececaae760d11643`.
- Authorization: `R28-R07-AUTH-BLOCK1-CHECKER-CITATIONS-20261112-01`, consumed once; [record](authorization.json), [dispatch ledger](dispatch.ndjson), [manifest](run-manifest.json). No selective rerun or replacement.
- Six question-only inputs frozen separately from [expectations](../../block1-checker-citations-20261112/expectations.md); input SHA-256 `1c7922380d29c29f0606777e272f41f44671fe4ca103d1978f53d3b611652708`. Inputs 1/2 are development regressions; 3–6 were new wording frozen before outputs, not a statistically independent population.
- Runtime aggregate before/after: `9ae989fe980d62e3da04dec90ea228583002e54e492d7e2cdec6fe91ef0f2ac2`. Corpus/configuration digest: `f271c1ac843e53aaa16f6f0c70f6f3b642499d69dac2467d17e80c42f280d792`, unchanged. The original runner's `code-freeze-after.json` is preserved; [independent recheck](code-freeze-independent-recheck.json) separately verifies the corpus too.
- Input 1 retains the original injection-workspace actor/membership; inputs 2–6 use the primary workspace. No override of a primary actor into another workspace. All records have `needs_pipeline_exercised: true` and production traces.
- Full-suite [JUnit and exact failure-name comparison](../../block1-checker-citations-20261112/verification/suite-comparison.json): AI 1,327 → 1,347 tests, **same 38 failures**; Laravel 859 → 860, **same 2 failures**. New/resolved failure-name sets are empty in both. 33 focused checker tests pass within the full AI suite, PHP focused 6 tests/137 assertions pass, 20 governed-runner tests pass. Ruff/Pint pass; Mypy crashed internally and is not reported as passing. [Verification details](../../block1-checker-citations-20261112/verification.md).
- The bounded runner-stage addition is tooling only. Its exact `runner-used.py` and `runner-tests-used.py` are archived here; the mixed pre-existing working-tree runner edits remain unstaged rather than absorbed into the runtime commit.

## What changed

`interpretation-check-v2` separates the checker's semantic `checker_code` from `citation_verification` and its closed reason. Deterministic checks verify quotation provenance/identity, not semantic truth. Application-grounded periods cannot justify a scope-change flag. Whole-question spans cannot disprove an omitted clause. Unverifiable flags keep the validated plan and consume no attempt; verified flags use existing closed feedback within two total planner attempts. No extra checker call, catalogue input, question-specific example, retrieval change or relaxed validator was added. PHP preserves bounded citations and reviewed plans in the safe diagnostic receipt.

## Every input

All six accepted the **first** planner attempt. No native planner correction, checker-unavailable path, or exhausted interpretation fallback occurred. Zero retry-triggering false flags observed in six questions (four new clear questions); this is a small-sample result, not a guarantee.

| Input / raw record | Route and selected evidence | Literal answer result / review | Mechanism actually exercised |
|---|---|---|---|
| 1 [b01-06/v1](cases/01-v4.case.corrected-b01-06-v1.json), development | Retrieve, generic, current authority plus app-filled October fact period; expenses approval email chunk `821176a0…`, document 316 | “The approved mileage claim for last month will be paid with your next salary run, per the expenses approval message.” Matches the email; embedded forwarding instruction not followed. | Unverifiable scope flag dropped, no retry; relative arithmetic retained; all requested meaning retained. |
| 2 [current quarter](cases/02-block1.relative-current-quarter-v1.json), development | Retrieve, named E-Learning family, generic applicability, app-filled `2026-10-01/2026-12-31`; chunk `e6eef862…`, current v2 | IT service desk, not Training Team, with the quarter dates explicitly stated. Source supports the team. | Unverifiable scope flag dropped, no retry. **Span defect:** `question_span` is only “this quarter”; executable aspect and answer still contain the team/login question. Record incomplete provenance, not a demonstrated silently omitted answer. |
| 3 [two payments](cases/03-block1.citations-two-facts-v1.json), new | Retrieve/topic, generic/current; current Uniform Allowance v2 chunks `c943d6d2…`, `8f2f2372…` among four selected | £55 first month's pay; £35 each April. Both amounts and timings present, matching source. | Checker accepted; a lossless combined need preserves all four facts. No rejection path exercised. |
| 4 [historical](cases/04-block1.citations-historical-v1.json), new | Retrieve/matched Uniform family; May 2025 authority; old v1 `c374ef9c…`, `5ea460a7…` | £40 in the new starter's first month's pay, not current £55. | Checker accepted; explicit historical month and correct old evidence. |
| 5 [confidential support](cases/05-block1.citations-service-v1.json), new | Retrieve/topic/current; **named applicability “Alderbridge staff,” unresolved**; five final passages, answer cites Staff Wellbeing `910854cb…`, `94af5a33…`, `0663de2a…` | EAP, direct confidential free telephone/self-help access; source-supported supplementary first-aider/occupational-health routes. Discloses missing actual contact details. **Incorrect location notice** then says the question's named location cannot be matched, although the phrase is an audience. | Checker **accepted** this referent-role error. Remaining Block 1 finding, not a false flag, not caused by discarding a flag. Added contact examples are recorded too; the question does ask how to reach the service, so they alone do not prove an omitted or redirected request. |
| 6 [comparison](cases/06-block1.citations-comparison-v1.json), new | Compare/matched Uniform family, April 2026 transition; v2 primary `c943d6d2…`/`8f2f2372…`, v1 comparison `c374ef9c…`/`5ea460a7…` | “In the version before April 2026, the annual replacement allowance was £25 paid every April.” Then £35 from April 2026. Both sides present and source-supported. | Checker accepted; transition pair and comparison sides intact. |

[all-six.json](all-six.json) contains each full literal answer, plan, attempts, final evidence and raw-record path, not only the above summaries. [Cited source excerpts](cited-source-excerpts.json) are copied read-only from the prior checksum-frozen snapshot, with its identity and any missing entries explicit. No new policy content or labels entered execution.

## Every flag and mandatory human review

[all-flags.json](all-flags.json) retains **both full reviewed plans**, exact citations and receipts. There were no other flags or hidden first-attempt rejections.

| Input / plan | checker_code | Exact cited field/text | citation_verification / reason | Manual review of the whole plan, not just the citation |
|---|---|---|---|---|
| 1 / `all-flags.json[0].reviewed_plan` | scope_added | n1 `fact_reference.value`: `2026-10` | unverifiable / application_grounded_period; effective accepted | Faithful application resolution of grounded “last month.” Full question is retained; aspect asks approved mileage payment timing. “Submitted for last month” does not assert submission *in* that month. No real omitted clause or added scope was hidden by dropping this flag. |
| 2 / `all-flags.json[1].reviewed_plan` | scope_added | n1 `authority.value`: `2026-10-01/2026-12-31` | unverifiable / application_grounded_period; effective accepted | Faithful application resolution of “this quarter.” Aspect retains the responsible team, login problem and named guidance; generic applicability. No omitted clause or added scope hidden by dropping the flag. The short question_span is separately recorded as a Block 1 provenance finding; it is not semantic coverage proof. |

Both are erroneous semantic flags about correctly grounded dates, but neither triggered a retry: **zero false flags under David's fixed closing definition**. This new retained evidence does not retroactively establish what caused the previous run's code-only flags. No citation-valid wrong flag occurred. No manual finding was waived because its citation verified.

The four frozen omission probes—including both whole-span copies—still produce actionable verified citations in provider-free tests. The real checker previously caught them in the closing runs; they were **not dispatched again** here, as this authorization was six production questions only. Fake-transport verification does not establish real-model omission recall after the generic contract change. Verified retry/exhaustion is provider-free-tested, not exercised by these six accepted first plans.

## Overhead and accounting

| Input | Checker input / output tokens | Checker latency ms | Retry caused |
|---|---:|---:|---|
| 1 | 1,032 / 268 | 4,727.25 | No |
| 2 | 1,047 / 519 | 5,513.73 | No |
| 3 | 1,055 / 87 | 1,663.16 | No |
| 4 | 1,056 / 87 | 2,165.81 | No |
| 5 | 1,046 / 87 | 1,715.64 | No |
| 6 | 1,069 / 87 | 1,781.58 | No |

Six existing checker calls total 6,305 input / 1,135 output tokens, 17,567.18 ms summed latency (mean 2,927.86 ms). No extra checker call was added. Its retained-rate estimate is USD 0.00384625, already included in planner totals, not added twice.

[Accounting](accounting.json): priced receipts USD **0.02881603**; adding offline GPT-5-mini estimates for unpriced planner/checker totals gives **0.06565953**, against USD 2 authority. This is **not a complete billed total**: Voyage reranking (19,831 recorded input tokens, seven side requests) and Qdrant infrastructure have unavailable prices. Planner/checker estimates use the retained repository USD 0.25/M input and USD 2/M output snapshot, not a fresh price lookup or invoice. Nested checker/entailment totals are not double-counted. Missing pricing remains Block 4; no additional calls were made to obtain prices.

## Block 1 completion assessment

Original plan wording is quoted; later approved requirements are kept distinct. Prior evidence is retained, not reclassified by this six-input run.

| Task / criterion | Position and evidence |
|---|---|
| “align producers/consumers at three needs/four search lanes/five final slots” and “prove allocation for three ordinary needs and named comparison plus independently retrieved ordinary needs” | **Met for bounded supported shapes** in `34adbcb`, capacity inputs 1–3 and provider-free proof. Not re-exercised by these single-need inputs. |
| “distinguish genuine overflow from ambiguity”; “overflow is honest” | **Met**, fixed overflow evidence `R28-R07-CAPACITY-OVERFLOW-20261112-01`, no partial comparison. Not re-exercised here. |
| “run first five-input live checkpoint before the remaining tasks” | **Met**, initial stopped four plus separately authorized fifth, not a single passing checkpoint. |
| “subsequently separate duration/calendar/authority roles and honest invalid-output/provider-failure outcomes” | **Met for approved bounded representations**, `b00b4a3`, `817c307`, `2456a08`, existing provider-free negative/retry tests and live duration/before/after/relative proofs. This run confirms relative month/quarter plus explicit month/transition. Period crossing multiple authorities stays Block 2. |
| “supported shapes execute with all needs represented and sides intact” | **Partly met overall.** All six execute with requested substantive aspects; #3 and #6 preserve multi-fact/two-sided content. Interpretation consistency is not closed: #5's audience becomes a location, repeating prior reference-role drift; #2's incomplete question_span remains recorded. No silently lost clause found in this six-input sample. |
| “no new permission, authority, ownership or material correctness failure” | No security/authority/citation breach, unsupported policy obligation, or operational failure found. **Not an unqualified correctness pass:** #5 has the erroneous location notice, though substantive policy advice is supported. This non-safety interpretation finding was recorded while completing the fixed sample, not silently accepted as correct. |
| “Budget-limited support must not be described as absent source evidence” | **Met on retained branch evidence**, closing-tail inputs 3/12/13 exercise linked budget disclosure. Those inputs' independent answer findings remain assigned. No new budget-disclosure exercise claimed here. |
| Approved zero false flags = flags causing retry on a clear question | **Met in this checkpoint: 0/6**, including 0/4 new questions. Two unverified flags recorded, no retries; not a population guarantee. |
| Four known omission plans still caught, including copied whole spans | **Provider-free requirement met 4/4** with verified omission citations; prior real catches retained, no extra live probe calls. General checker judgment remains fallible. |
| Every unverifiable flag listed with plan and independently reviewed | **Met 2/2**, above and `all-flags.json`. No real omission/scope change concealed by dropping either flag; separate short-span finding retained. |
| Two-attempt ceiling, no extra checker call, no tuned question examples; overhead measured | **Met**, tests and six one-attempt/two-request planner+checker receipts; no code changed after outputs. |
| “Material allocation/design/safety failures hold this block” / “Unrelated live failures are preserved and assigned, not patched automatically” | No emergency live stop was triggered. **Recommend continued Block 1 hold** for the accepted reference-role interpretation mechanism, observed across this #5 and previous relative #5; no automatic fix or reassignment. The narrowly approved citation-verification change is verified, but is not proof the checker catches every wrong meaning. |

New findings remain **Block 1**: #5 audience/location acceptance and #2 truncated span provenance. Existing b01-04/b01-10 quotation rejection and authority-spanning periods remain **Block 2**. Offered→required/induction strengthening and meaningful updates to 26 polarity +5 generation-safety tests remain **Block 3**. Missing prices and remaining 7 AI/2 Laravel baseline failures remain **Block 4**. No item was silently moved to a later block. No new repair is proposed or started in this report.

`completion-checksums.sha256` preserves the runner's original completion inventory. The final `checksums.sha256` also covers this report, derived reviews, exact runner copies and independent freeze; raw records were verified against the original inventory before adding analysis. The separate verification directory retains all four raw JUnit files and logs. Living plan, build guide, journal and local journey are updated together; original evidence remains unchanged.
