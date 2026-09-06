# R01P live diagnostic findings — 2026-09-06

R01P remains in progress. No full evaluation, pilot-readiness result or completed eight-question slice is claimed. Code is uncommitted; no push, index rebuild, corpus alteration or AWS call occurred.

## What caused the 503

Targeted attempt 0002 retained `invalid_grounded_needs_plan`, provider HTTP **200**, one attempt. The separate one-request planner probe 0003 isolated the local validation error at `needs[0].authority`: **non-current authority requires a grounded reference**. Its schema/grounding validator groups missing value, source or non-none precision under this rule; the probe did not retain which individual field was missing. Do not infer a provider outage from the AI route's 503, or claim the lost 0001 detail was reconstructed.

The probe used the real planner adapter/transport and retained safe validation messages and usage only, not a raw provider response. Usage: 1,021 input / 2,130 output tokens, no retry, estimated $0.00451525 at configured prices.

## Bounded corrections and results

Prompt v2 explicitly described complete authority-field combinations without changing validation. Targeted attempt 0004 passed planning but incorrectly treated document/topic phrases as locations and selected historical retrieval rather than comparison. Laravel correctly returned `unresolved_location_reference`. No answer was produced.

Prompt v3 added generic organisational-site versus topic distinctions, before/after field-change interpretation, and standalone aspect context. It contained no ROPA-specific alias or expected answer. Identities remain `evidence-needs-plan-v1` and `structured-chat-needs-v1`; that run used `bounded-evidence-needs-v3`, with matching PHP consumer and pinned profile. Existing frozen inputs are unchanged.

Fresh slice 0005:

- **R01P-01 completed in 49.0113 seconds.** The real pipeline selected both comparison versions and persisted a cited answer identifying “Legal basis” → “Lawful basis (UK GDPR Article 6).” Its second aspect lacked useful lawful-basis support and received mostly retention/right-to-work/recruitment evidence. The generated answer explicitly disclosed the missing basis but added tangential material. This is partial progress, not acceptance. The actual second retrieval aspect still contained “there,” losing the shared antecedent despite the instruction; no evidence-backed conclusion about reranker quality alone follows from that poor query.
- **R01P-02 failed in 20.3956 seconds after a valid but overcautious clarification plan.** The model requested an explicit older version despite the wording “which field replaced” and unnecessarily asked whether the stated current policy was current. An application integration defect then put the long clarification into `clarification_reason` (a varchar code column) and would also hand it to a code-only renderer.
- Questions 03–08 did not run. Execution stopped at the first failed case; no automatic continuation or rerun after the following correction.

The clarification integration now separates `evidence_needs_clarification` from the human-readable question. The existing controlled renderer handles that explicit planner reason and preserves the full text; unsupported legacy codes still reject. No migration or truncation workaround was added. A synthetic >255-character clarification test proves the bounded reason, exact message and one planner-only dispatch. Its initial failure was a missing test import, corrected before passing.

## Verification and accounting

- Prompt/identity change: 42 Python tests; 47 Laravel tests / 193 assertions, all passed.
- Clarification integration: 27 passed / 257 assertions, with 3 opt-in bridge tests explicitly skipped because the temporary bridge was not running. These do not replace the earlier executed cross-service proof.
- Scoped Ruff, format checks, Pint and `git diff --check` passed.
- This diagnostic sequence made five planner requests, five Voyage requests (two embeddings plus three physical reranking sides), and one answer-generation request. Recorded successful retrieval/generation retries: zero. No judge or corpus embedding.
- Available 0005 case-01 receipts: embeddings 16 tokens, reranking 19,647 tokens, generation 23,706 input / 871 output. Estimated non-planner cost $0.00865277, using the configured reranker rate where its receipt reports cost unavailable. Including probe 0003 gives $0.01316802 known estimated cost, **excluding four planner requests whose receipts are absent from the case reports**. This is not a total bill; retain the earlier conservative allowances for missing usage.

## Preservation and next step

Original container evidence is unchanged. Host copies are under `/tmp/r01p-preserved-0001.mBGV6E/`: original 0001, targeted 0002, safe probe 0003, targeted 0004 and stopped slice 0005. Each run retains its original profile/manifest and dispatch records. The failed 0002 application run is `76345e60-85de-4b70-b8de-af95c1b446e9`; successful 0004 controlled clarification is `484a1013-8d50-41f8-ae27-8dc356ef749f`.

The next checkpoint below supersedes the standalone-aspect/over-clarification next action. Broader repair remains gated; no new audit handoff is required.

## Prompt v4 and stopped slice 0006

The prompt now distinguishes exact source quotations from rewritten, self-contained retrieval aspects. Relative change wording can ground a previous/current comparison without a calendar label; an explicitly current policy does not require confirmation. No domain-specific alias, expected answer, validator relaxation or new provider stage was added. The PHP consumer and pinned profile require `bounded-evidence-needs-v4`; contract/adapter identities are unchanged.

Provider-free verification: **42 Python tests and 48 Laravel tests / 197 assertions passed**, plus scoped Ruff lint/format and whitespace checks. Code stayed unchanged during the live run.

Fresh run 0006 stopped after **185.4195 seconds**:

| Question | Result | Seconds | Evidence review |
| --- | --- | --- | --- |
| 01 | completed; both aspects supported | 47.3615 | Both ROPA versions plus employment lawful-basis passages support the rename and contract/legal-obligation answer. |
| 02 | completed; both aspects supported | 33.8950 | Same substantive answer, without the earlier unnecessary clarification. |
| 03 | completed; both aspects supported | 46.0970 | Reordered wording retains the same two substantive answers. |
| 04 | completed; both aspects supported | 36.5538 | End-of-life version passages support the reordering; current data-protection passages support the handling requirements. |
| 05 | failed before retrieval/generation | 21.4980 | `invalid_grounded_needs_plan`, provider HTTP 200, one attempt, non-systemic. Exact validation leaf not retained; do not infer it. |
| 06–08 | not executed | — | Missing-history, missing-record and tenant controls remain unproven live. |

Review inspected actual cited canonical chunks (IDs 244, 245, 248, 407, 410, 451, 647, 650, 692), not expected evidence to influence execution. The successful answers' substantive claims are supported; this is a bounded manual citation review, not a full scored evaluation or complete slice acceptance.

Available completed-case receipts record 4 planner, 8 embedding, 12 physical reranker and 4 generation requests, with zero recorded retries. Including failed question 05's one planner attempt gives **29 evidenced provider attempts**. Eight application rerank dispatches expand into twelve physical sides; these counts must not be conflated. Known input tokens: **184,365**; output: **13,514**. The failed planner's usage is unavailable.

Configured-rate estimates: planner $0.02320675, embedding $0.00001752, reranker $0.00409335, generation $0.02940925; total known estimate **$0.05672687**, excluding failed-planner usage. Retaining its conservative $0.013192 allowance gives $0.06991887 for this run's estimate plus reservation, not an invoice or actual total. Earlier missing receipts retain their existing allowances. No judge, corpus embedding, reindex or AWS call occurred.

Original API directory `/tmp/r01p-live-20260906-0006` remains unchanged; a new host copy is preserved beneath `/tmp/r01p-preserved-0001.mBGV6E/`. Manifest SHA-256 `8afb8bd86cebe27173ff0ec821e6ebd11a009b28be8f4b267c34efcbaa8766e6`; stopped record `699ad388f6c561685233e23b59a8a5ebf4e365f119ecff58d760b80a7edefb37`. Failed application run: `b5ab2a8a-183a-4b67-aa7d-ae0eaf94b860`. All previous run evidence is preserved.

Next: isolate question 05's transition-plan validation failure, not a presumed outage, without blindly rerunning completed paid work. R01P stays in progress; no commit/push or expansion acceptance.

## June correction — probes 0007–0009

0007 made one fresh planner-only call (not reconstruction of lost 0006 output). It returned HTTP 200 and correctly selected two `transition_period` comparisons, but both values were `June 2026` at month precision instead of canonical `2026-06`. Both failed `date value does not match declared precision`. This is exact evidence for the new probe, not proof of the unretained 0006 validation leaf.

Prompt v5 specified canonical calendar notation versus verbatim source quotes. One fresh production question in 0008 then validated structurally but used `previous` with the named value `change in June 2026`, losing the intended temporal selection. Its two comparisons exceeded R01P's three-lane bound, so it returned controlled clarification after 25.4227 seconds, without search or generation. No malformed plan was repaired or validator relaxed.

Prompt **`bounded-evidence-needs-v6`** makes explicit calendar transition selection take precedence over unqualified previous-version comparison. Final planner-only probe 0009 returned two independently grounded aspects, both `transition_period`, `value=2026-06`, `precision=month`, source quote `June 2026`; schema and grounding passed. This is successful interpretation evidence, **not a completed production answer**. Contract `evidence-needs-plan-v1` and adapter `structured-chat-needs-v1` remain unchanged; PHP consumer/profile and tests carry v6.

Provider-free checks: **44 Python passed; 24 Laravel passed / 226 assertions, 3 opt-in bridge tests skipped**. Scoped Ruff lint/format, two-module mypy and `git diff --check` passed. New tests cover both June 2026 and February 2025 canonical month conversion, rejection of raw month names in value, and rejection of an invented first day. Prompt assertions verify transmitted instructions, not model-quality generalisation.

Three planner calls, no native retries, no Voyage/generation/judge/index/AWS work this checkpoint. Probe 0007: 1,644 input / 2,335 output tokens, configured-rate estimate $0.005081. Probe 0009: 1,819 input / 2,216 output, $0.00488675. 0008 usage is not retained in the bounded case report; reserve $0.014 rather than claiming zero. Known estimate $0.00996775 plus that reservation. All new evidence is copied beneath `/tmp/r01p-preserved-0001.mBGV6E/`; previous evidence untouched.

**Remaining scope decision:** two requested comparisons require four lanes under the existing per-need execution model. The accepted R01P limit is two needs/three lanes, even though the broader ADR permits more. Do not drop/merge an aspect, relabel a comparison as current retrieval, or silently raise the bound to pass this case. David's approval is required for a narrow four-lane slice amendment and its revised request/cost envelope before completing the compound June answer. R01P remains in progress; no commit/push.

## Approved four-lane extension and pair-selection diagnosis

David approved the proposed narrow extension on 2026-09-06. Retrieval, evidence selection, PHP generation assembly and Python request validation now allow two needs/four lanes, retaining K=5 and all pair/ownership checks. New tests prove both pairs survive, including repeated source chunks with distinct need-owned handles, and three needs remain rejected. The eight-question nominal maximum becomes 64 requests / 360 conservative attempts; estimated envelope $4.50204928, still below $5. No changes to questions, models, thresholds, indexes or prior evidence.

Run 0010 completed in 41.5326 seconds with both June transition aspects. It returned a cited answer, but manual review rejects full completeness: it said the order changed without stating the actual before/after ordering. Search and reranking found the relevant passages: current chunk 647 was rank 1 at both stages for both needs; previous chunk 407 was search rank 3/2 and reranker rank 2. Final selection nevertheless chose chunks 648/409 for both needs plus 650. The selector compared pair minimum rank and then chunk-ID order, allowing a weaker companion to tie a stronger pair. This is a local allocation defect, not evidence that search missed the passage or that provider reranking alone caused the omission.

The bounded correction compares both minimum and maximum member ranks before the existing identity tie-break. No score weight or threshold is tuned. A synthetic test with alphabetically earlier but weaker chunks proves both top-ranked sides survive for two comparisons within K=5. Selection identity is `bounded-needs-k5-v2`; execution pin v3 distinguishes this from run 0010's v2.

Verification: 57 Python tests passed; initial Laravel selection/retrieval/generation suites 64 passed / 186 assertions (3 bridge skips), conversation pin regression 15 passed / 154 assertions. After tie-break correction, 12 selection/retrieval tests passed / 77 assertions (3 bridge skips). Scoped Pint, Ruff, mypy and whitespace checks passed. Bridge skips are not live cross-service proof.

Run 0010 usage: 1 planner, 2 query embeddings, 4 physical rerank requests, 1 generation; no retries. Recorded input 38,652 tokens / output 2,487. Configured-rate estimated total $0.00879256 (planner $0.00403, embeddings $0.00000816, reranking $0.0014589, generation $0.0032955). This is estimated, not an invoice. Immutable host directory `r01p-june-20260906-0010` is under the existing preservation root. Case SHA-256 `39c7bcf1ff0bfd91420e7da264fa63abb08ef6d7f08ec67bed0b0bfab6f7af20`. No commit/push or AWS call.

### June confirmation 0011

The full June question completed in **41.9296 seconds**, application run `fd47bff3-f953-4331-a6ae-055004732ef6`. Both needs retained `transition_period`/`2026-06`; both comparison pairs survived. Selected evidence now includes the rank-1 order-bearing chunk 647 for both needs. The answer specifically states DNACPR and symptom management moved ahead of advance care planning, and attributes unchanged underlying requirements to the policy's own statements, retaining its “except where noted” qualification. Manual review against actual chunks 647/409/650 supports these claims. This passes this question's bounded diagnostic, not the complete eight-question slice or general comparison accuracy.

Usage: 8 physical provider requests (planner 1, embeddings 2, reranking 4, generation 1), zero recorded retries; **38,722 input / 3,052 output tokens**. Configured-rate estimate **$0.00991342** (planner $0.005092; embeddings $0.00000912; reranker $0.0014653; generation $0.003347). Two live questions this checkpoint cost an estimated $0.01870598 combined. No judge, corpus embedding, AWS or index change. Original run and new host copy `r01p-june-20260906-0011` remain preserved. No commit/push.

Next: missing-history, missing-personal-record and tenant controls (06–08) remain unexecuted. Earlier successful questions 01–04 used the prior selector; confirm regression behaviour before eventual full slice acceptance. Do not equate derived supported statuses with independently scored quality. No expansion into R02+ yet.

## Remaining controls — run 0012

Executed fixed questions 06–08 once on unchanged execution v3 / planner v6 / selection v2. All completed their application route without exceptions; this does **not** mean all controls passed. The prior run records remain immutable. No runtime code changed during or after this run.

| Control | Application state / elapsed | Reviewed result |
| --- | --- | --- |
| 06: unavailable data-protection history plus current ROPA | completed / 44.4056 s | **Fail.** Planner retained the correct data-protection subject, but selected comparison evidence and generation substituted Data Backup and Recovery Policy changes. The ROPA field answer was correct. Both aspects were mechanically labelled supported despite the wrong-family answer. |
| 07: unavailable individual employment-record status plus ROPA change | completed / 36.2785 s | Bounded missing-record check passes: explicitly says supplied documents cannot establish the user's record update, retains an unsupported-aspect entry and gives the supported ROPA rename. General employment lawful-basis guidance is distinguished from evidence of an actual personal update. |
| 08: other tenant's private record plus ROPA change | clarification_required / 28.8731 s | No private record or answer disclosed; **intended mixed response fails**. Planner misclassifies “another tenant” as relational organisational location. Laravel correctly refuses to resolve it (`unresolved_location_reference`), but stops the whole request rather than preserving the safe ROPA answer and explaining the unavailable private record. No generation occurred. This is not proof of a successful foreign-record retrieval test. |

For 06/07 all persisted citation IDs resolved and belonged to the authorised primary workspace. Question 08 has no generated citations; an empty set must not be presented as evidence that a foreign retrieval attempt succeeded safely. Its first, ordinary ROPA need performed scoped retrieval before the second need was stopped. No foreign content was inspected during this review.

The earliest evidenced 06 failure is after a correctly phrased planner aspect: a comparison from a different document family can survive retrieval/selection when requested history is unavailable, and generation then answers that different question. Subject match and answerability are not established by same-family pairing or a relevance threshold alone. The immediate priority is a bounded subject/family-and-answerability correction, not adding title aliases or tuning against this case. The second issue is tenant wording being misinterpreted as location. Preserve both failures; no automatic paid rerun or wider R02+ work began.

Verification on this code: **116 Python tests passed; 142 Laravel tests passed / 456 assertions, 3 opt-in bridge tests skipped**. These are provider-free regressions, not evidence that the failed live controls pass. `git diff --check` passed.

Run totals: **109.5622 seconds; 18 provider requests/attempts** (3 planner, 5 embeddings, 8 physical rerank requests, 2 generation), zero recorded retries. **104,317 input / 8,397 output tokens; configured-rate estimate $0.03276787**, not an invoice. No judge, corpus embedding, index change, AWS, commit or push. Host preservation: `/tmp/r01p-preserved-0001.mBGV6E/r01p-controls-20260906-0012`; manifest SHA-256 `adacd65c33e51fd05800d4377854d0904f88dca21e2d3e2137b1696c2e7f48ef`, complete record `b21973bf283d6c83b3c2d1f3f2920a98c16782001acac6e04784dffb557f8308`.

All eight fixed questions now have development observations across successive code boundaries, not one clean same-version certification run. R01P remains in progress; the expansion gate stays held because the controls expose genuine unresolved correctness issues.

## Bounded subject-guard attempt — run 0013 (unsuccessful)

Implemented canonical source filename/family-name metadata in Laravel's generation evidence (bounded to 500 characters, reloaded from authorised models), needs-generation prompt v2 distinguishing pair validity from subject responsiveness, and planner prompt v7 distinguishing tenant/account ownership from location. No aliases, threshold changes, extra provider stage or deterministic semantic classifier. Existing generation dispatch/profile bindings were updated; one stale dispatch guard was caught by tests and fixed before execution. These are **experimental, uncommitted changes, not a successful repair**.

Provider-free verification: 94 Python passed; 77 Laravel passed / 343 assertions, 3 bridge skips; scoped Ruff/Pint, two-module mypy and whitespace checks passed. The synthetic qualified-output test proves transport/validation of a gap plus independent answer, not semantic detection. Laravel tests prove canonical titles are assembled rather than trusting candidate labels.

Run 0013 repeated only controls 06–08 with no mid-run changes:

- 06 completed in 43.5810 seconds but **failed**: despite the actual planner retaining “data-protection policy” and generation reporting prompt v2, it answered Printer and Scanner Usage Guidance changes. This does not support a claim that the generation instructions solve wrong-family substitution.
- 07 completed in 57.5872 seconds and retained an explicit unsupported personal-record-update aspect with the supported ROPA rename.
- 08 returned clarification in 26.1521 seconds: the actual planner still encoded “another tenant” as relational applicability. No generation or private disclosure occurred; intended mixed-response behaviour still fails.

All persisted citation IDs resolved to primary-workspace chunks. No foreign content was read in this review. Runtime totals: 127.3291 seconds; 18 requests/attempts (3 planner, 5 embeddings, 8 physical reranks, 2 generation), zero retries; 103,895 input / 8,687 output tokens; configured-rate estimate **$0.03338786**, not invoice cost. No reindex, judge, AWS, commit or push. All prior evidence is unchanged. Host copy under `r01p-controls-20260906-0013`; manifest SHA-256 `ab80140e74a2daaccf9a19702c463f38a38a7654e18e94c0b8da226284102f3b`, complete record `c9e927f2c1d6d0a2e2c655df73297a84273fabad1730a4dc8d8a738f43ecdd5b`.

**Stop condition:** do not stack more prompt wording and call it a repair. Recommended bounded design decision: resolve requested subject against an authorised family catalogue before selecting its comparison versions, retaining explicit unresolved/ambiguous/missing-history states rather than substituting a related family. This must use production authority and dynamic catalogue identities, not fixed title aliases. Tenant ownership also needs an explicit distinction from location. The exact contract/ownership change requires review against ADR-0038 before implementation; no new resolver or wider R02 work is authorised by this finding alone. The slice remains unaccepted.

## 2026-09-06 — approved catalogue/ownership correction, live dispatch blocked

David subsequently approved trying the bounded correction. The existing needs planner now receives a bounded, workspace-only family catalogue and returns complete subject/ownership bindings. Laravel validates supplied IDs, then restricts matched families before timeline selection and during final revalidation. Unavailable or external-tenant needs cannot search; an independent supported need remains executable. Genuine subject ambiguity has a typed, persisted clarification. The model still interprets language, so these structural guarantees are not a live semantic pass.

Identities: `evidence-needs-plan-v2`, `bounded-evidence-needs-v8`, `bounded-needs-slice-v4`. No new model call, provider, threshold, K or index. Historical v1 schemas and prior run evidence remain unchanged. Local checks: 100 Python tests passed; 118 Laravel tests / 403 assertions passed, three opt-in cross-service bridge tests skipped; Ruff, Pint, scoped two-module mypy and `git diff --check` passed. New cases cover unmatched IDs, missing/duplicate bindings, ownership/location separation, grounded ownership, missing requested history despite another complete family pair, independent current support, and cross-workspace rejection.

Fresh controls 06/08 were prepared as `/tmp/r01p-controls-0014.php`. The execution safety gate rejected the command **before execution**, requiring explicit approval for exporting the questions/catalogue/evidence to existing OpenAI/Voyage providers. No live provider request, retest result, commit or push occurred in this correction task. Do not treat 0014 as executed. Obtain narrow approval before attempting execution; no workaround or alternate dispatch was used. The updated conservative full-eight envelope is $4.82204928 under the existing $5 practical bound; no additional stage was added. R01P remains in progress pending controls and same-version eight-question evidence.

Core source SHA-256 values at this checkpoint:

- Python needs model: `2b9ed0eb523db44bcec061336a87448b33fe77e2ce7ccf8afb4e33d1412aa240`
- Python planner: `ddc8d62f2fcb8c01e807a1092de1619490b9013278084902e1e2532e29262f6b`
- Laravel client: `7a16e11f1f2eca3d02772f9221fe72e5262544ea0abad947196fee8875a15894`
- Laravel plan: `a00e58611394b4bee960432a7208890ada7a5ac50b36a1d89eef9225af5e3082`
- Laravel eligibility: `918ebe4184217d1074f59144d16aff3b9aa3834c0eea4c933c188b8b215ff2e7`
- Execution profile factory: `a172d7d5a5d4d574639e9dca0280f1867626847e9e5549e01099d0c64eeaf63e`

## 2026-09-06 — controls 0014, planner validation failure

David explicitly approved sending the two questions, authorised catalogue and retrieved evidence to existing OpenAI/Voyage providers under $5, then all eight only if both passed. The six core hashes above still matched and `git diff --check` passed before execution. No application code changed in this retest task.

`R28-R01P-CONTROLS-20260906-0014` started at 13:43:14 UTC with the recorded v4/v2/v8 identities. Control 06 failed after **23.9368 seconds**, run ID `2fa74999-7c0e-4871-9540-9bbfdf0f0b1d`: Python's needs-planner output validation returned `invalid_grounded_needs_plan`, provider HTTP **200**, one attempt, systemic=false. Local API returned 503. This is not evidence of provider unavailability and not a retrieved-answer quality result. The stop rule prevented control 08 and the full eight from running.

One OpenAI planning attempt, no retry, no Voyage/search/reranking/generation/judge call. The current adapter collapses envelope/schema, grounding and catalogue-validation exceptions into the same safe category and discards the detailed error. Local logs contain no more specific reason or retained response; the exact failing field cannot be determined honestly from this record. Strict-schema construction was inspected and does require all binding fields; no provider-free evidence proves a particular malformed field. Persisted provider usage rows are absent, so actual tokens/cost are **unavailable**, not zero. The one-attempt conservative configured-rate bound is $0.033192 (100,000 input / 4,096 output allowance), not an invoice measurement.

Original evidence remains in the isolated API container at `/tmp/r01p-controls-20260906-0014`; a separate host copy is under `/tmp/r01p-preserved-0001.mBGV6E/r01p-controls-20260906-0014`. SHA-256: manifest `3780891cd4f60658c8b6472edd7757dc5ff15e8df8ff7f7143ca51b8f0f64b93`; control 06 `72b3ace11f791a712a2a9bdd05c69f83163a99454901976cf4fb6ddd92d07d6f`; dispatch `76bc310a6c250867b554f0d653d588a19b66fc38866e14f8074b68506b0d229e`; stopped `6ee092f23fe18ddde85fefc71c0afaf358ec002c2720dba6c9ebb89faecdace7`. No previous evidence, index, threshold or provider identity changed. No AWS, commit or push.

Next bounded correction should preserve safe validation stage/field/error-category diagnostics (without raw output, credentials or user text), then use the smallest diagnostic attempt needed to identify the rejected invariant. Do not claim the catalogue correction passed live or repeat the full eight blindly. R01P.5–.7 remain incomplete and wider expansion remains held.

## 2026-09-06 — safe diagnostic implementation; 0015 not executed

Following David's “Ok please proceed”, implemented failure-only diagnostics: needs output validation records `envelope`, `schema`, `grounding` or `catalogue`, a bounded allowlisted field path, and a static error category. Python excludes response text, exception input/context/URLs and arbitrary messages; Laravel independently filters keys/values before persisting the diagnostic with the existing planner-failure record. Existing `invalid_grounded_needs_plan` category and no-retry behaviour remain intact. No prompt, schema, model, scoring, retrieval or authority change.

Provider-free checks: **51 Python tests passed; 22 Laravel tests / 173 assertions passed**; Ruff/Pint, scoped mypy and `git diff --check` passed. Tests exercise safe mapped invariants, malicious/raw input exclusion, unknown field rejection, client transport and persisted conversation failure. One initial Laravel test incorrectly reused its first fake HTTP response across loop iterations; splitting it into isolated data-provider cases fixed the test setup without changing application filtering.

Prepared `/tmp/r01p-controls-0015.php` for only question 06. The safety reviewer rejected its execution command before it started, requesting explicit authorization for this additional post-failure provider diagnostic and payload export. **0015 has not executed; no new provider call, cost or result is claimed.** Do not bypass the rejection or run the full slice. Prior evidence remains untouched; no commit or push. Next action is explicit approval for this single-question live diagnostic under the existing $5 bound, then inspect the safe validation result before deciding any correction or further run.

Diagnostic source hashes: Python planner `a3ceb4860688986306845270251fa7447ae444ce1c3a589b6731783649610232`; Python routes `b2186e62f3ff3f3606e3995514adb3b70eb960842e066f79d70273a4ea13004c`; PHP exception `3b5aded4d0cb701f64ac1f2f2a10c68f2b64fc38a16f35c7243d6a8cc353b0ec`; PHP client `15c61ee7d67c4599784386ae72152cf5b05791b6e480e8c475a397fd763a9814`; PHP orchestration `6c7ee3bde9575607ccd94da7888498ea6b132e1168f77d87bf147711d57b7494`.

## 2026-09-06 — question 06 only, successful control 0015

David explicitly confirmed approval for **question 06 only**, not all eight. The diagnostic source hashes above still matched; whitespace check passed. Executed one fresh conversation, `799ae3b0-f79a-4a68-83d9-6237b92af285`, with unchanged v4/v2/v8 interpretation and generation identities. **Completed in 32.7113 seconds.** No additional question or provider probe was run.

Observed target behaviour passed on this attempt: the planner bound n1 to Data Protection Policy family `fc3d2f69-069d-4303-8d40-d118472ba7d1` and n2 to ROPA family `c2808f93-40a3-431d-a7dd-28477ae34a46`. Laravel returned `comparison_scope_incomplete` for n1 without searching another policy; n2 selected only current ROPA document `c637668a-a5b6-4e7f-ad62-0e13b54c65bd`. Generation persisted `qualified`, n1 unsupported and n2 supported, explicitly recording that the previous Data Protection Policy was not supplied and answering that the current ROPA field is “Lawful basis (UK GDPR Article 6)”. The sole citation (chunk 692/document 229) resolves in the authorised primary workspace and was read: it explicitly contains that field label and the former “Legal basis” rename. No wrong-family substitution occurred.

Usage: 4 provider requests/attempts (one each planner, embedding, reranking, generation), zero retries; 11,876 input / 2,901 output tokens. Configured-rate estimate **$0.00873754**, not invoice cost: planner $0.006816 and reranking $0.00000765 calculated from recorded tokens where durable cost fields were null, plus recorded estimated embedding $0.00000264 and generation $0.00191125. No judge, corpus embedding, index change, AWS, commit or push.

The 0014 validation failure **did not recur**. Its exact original rejected invariant remains unknown; diagnostic-only changes do not prove it fixed. This is one successful development control, not reliability or full-slice acceptance. Question 08 remains untested on v4 and the same-version eight-question check is still outstanding. Stop here under this single-question approval.

Immutable evidence: API `/tmp/r01p-controls-20260906-0015`, host copy `/tmp/r01p-preserved-0001.mBGV6E/r01p-controls-20260906-0015`. SHA-256: result `81cbbe1735c393125d963017cc56c95dab0e77cc277212b805950d939347c9e2`; manifest `054284c7f9e161085648b04065cf1f055e6bc5b892bf6e44d9a076b6b13cb9bd`; complete `8bf0a7a892fcaa1a1932415973c51a8f5385f4570dd24f6cc1df4c4901cbb295`. All preceding attempts remain historical evidence.

## 2026-09-06 — bounded provider-free validation investigation

David requested investigation of the intermittent validation concern. Inspected only the current needs model/adapter and relevant recorded evidence. Ran four local synthetic probes against the **actual strict provider JSON schema**, followed by Pydantic, grounding and catalogue validation. No provider/network call or application change:

| Synthetic scenario | Provider schema | Application result |
| --- | --- | --- |
| Unmodified valid shared example | valid | accepted |
| Previous-version authority with source=null | valid | rejected: schema / needs.0.authority / authority_grounding |
| Question quote absent from supplied turns | valid | rejected: grounding / root / reference_grounding |
| Valid UUID absent from supplied catalogue | valid | rejected: catalogue / root / unknown_family |

Concrete finding: Pydantic after-validators impose conditions not represented in its generated JSON schema. `_strict_response_schema` makes fields required but does not encode cross-field invariants or request-specific membership. A provider-schema-conformant result can therefore still fail the application. These are deliberate synthetic reproductions, **not recovered Run 0014 output**. That exact rejected invariant remains unknowable from the retained generic record. The successful 0015 run does not resolve its origin or establish an error rate.

Smallest recommended next production correction: constrain representable structural alternatives in the provider schema (e.g. current versus grounded non-current authority), and constrain catalogue selection to supplied identities in the per-request schema; preserve independent Laravel membership/authority checks and exact-grounding validation. Grounding and semantic mistakes will still require honest failure handling. Do not silently repair IDs/quotes, weaken validation, infer that all errors are schema-related, or add automatic paid retries in this diagnosis task. A later repeated small check with safe diagnostics is needed for reliability evidence. No full-eight or question 08 execution, no commit/push. R01P remains in progress.

## 2026-09-06 — approved provider-schema restriction implemented

David instructed proceeding with the bounded schema correction. Added `app/retrieval/needs_schema.py` to build the existing wire representation's provider schema from real models, with explicit nested authority alternatives and a request-specific catalogue-ID enum. Current authority cannot carry a fabricated temporal source/value; non-current authority must carry a non-null source, compatible precision and notation. Compare excludes current-only authority; retrieval excludes transition-only authority. Binding alternatives require populated matched/ambiguous lists, empty topic/unavailable lists and grounded external-ownership source shape. Unknown UUIDs are not permitted by the per-request enum. Laravel still independently validates catalogue membership and all existing temporal/grounding rules. No normalization, repair, fallback, added retry or semantic word-list rule.

Used the [official OpenAI Structured Outputs documentation](https://developers.openai.com/api/docs/guides/structured-outputs) to select supported nested `anyOf`/enum constraints rather than unsupported `if/then` or `allOf`. Root remains an object. Shared enum groups of at most 250 IDs support the existing 500-family bound within per-enum limits. Shared authority definitions avoid unnecessary schema growth. The complete HTTP request is checked against 100,000 bytes before dispatch, preserving the recorded conservative planner allowance. No runtime-wide cache mixes catalogues; per-request generation is deterministic. An initial maximum-catalogue size test identified duplicated definitions; using shared references corrected that before completion.

Identity: planner adapter **`structured-chat-needs-v2`**, execution **`bounded-needs-slice-v5`**. Wire **`evidence-needs-plan-v2`**, prompt **`bounded-evidence-needs-v8`**, generation profile, models, thresholds, K, request counts, spending/time limits and indexes unchanged. PHP consumer and pinned-profile expectations now agree; old experimental pins fail closed rather than silently adopting new behaviour. No frozen population or prior run changed.

Verification: **130 Python tests passed; 120 Laravel tests / 405 assertions passed**, three optional cross-service bridge tests skipped. Includes all valid shared examples; every authority kind/precision; previously schema-valid invalid combinations; request-specific ID isolation; empty/250/251/500-family catalogues; enum/strict-object/subset checks; oversized input rejected before transport; real mock HTTP adapter; independent application validation and generation regressions. Ruff, Pint, scoped two-module mypy and whitespace checks passed. These are provider-free results, not live API acceptance or measured reliability. No inference/provider call, AWS, commit or push in this correction task; official documentation was fetched.

Source SHA-256: schema builder `b3d4540ac9e40b10f1b52b6053807368e0efa40972da9724bcccd59823b98d36`; planner `b430ff1df7f72a2975e170142787bc49351335b3cfb0b75e210b6e8f069c359f`; new schema tests `e64a361e5e482a16cf128dd7ea2df5fa28e043ce85493729721db44f25108102`; PHP plan consumer `72d0e0f60ee86689fb17788cae565a0b6737f8b7bc7969d22b0993e576849f94`; profile factory `7dae0cd613ce43c69888da7c2b4b25b83edba5515060e46a94116ad265911e31`.

Next: explicitly bounded live controls 06/08 on v5, followed by same-version eight-question evidence only if both pass. The original 0014 invariant remains unknown. Quote grounding, correct semantic family choice, duplicate IDs and cross-need consistency still need application validation; do not describe this as elimination of all intermittent/model errors. R01P remains in progress, expansion held.

## 2026-09-06 — final same-code eight-question confirmation 0020

David explicitly approved all eight questions and authorised catalogue/evidence transfer to OpenAI/Voyage under $5. Core hashes matched 0019; no code/configuration changes during execution. All eight completed once, no retries. This completes the two-live-batch correction cycle (0019 targeted, 0020 full eight); no additional run or correction is started.

Observed targeted outcomes: 01/02/03 answered both ROPA rename and employment-policy lawful bases; 04 answered end-of-life reordering and current data-handling guidance; 05 correctly described June ordering before/after and unchanged core requirements; 06 qualified its answer with the absent policy-history evidence, while answering the current ROPA field; 07/08 retained explicit individual/foreign-record gaps without inventing record contents. No recurrence of the three 0018 defects on this run. **Eight execution completions and eight targeted behavioural checks passed**, assessed manually against supplied synthetic documents, not a provider judge or newly calculated frozen metric.

All cited chunks resolve in primary workspace and their text was read. Citation sets: 01=245/248/451/692; 02/03=244/245/248/451/692; 04=245/407/409/647/650; 05=407/409/647/648; 06=692; 07/08=451/692. Source content supports the reviewed answers, including old/new section order and policy employment-basis wording. These are answers about supplied fictional material, not independent legal/clinical certification.

**50 physical provider attempts, zero retries; 230,686 input / 17,059 output tokens; 267.8010 seconds; estimated $0.07963724.** Eight planner, thirteen embedding, twenty-one rerank-side and eight generation attempts. Null planner/rerank costs calculated using recorded tokens and configured rates, not treated as zero; costs are estimates, not invoices. No judge, reindex, AWS, commit or push. Existing evidence and unrelated changes preserved.

Remaining limitations: one successful full-eight development run does not prove reliability or new-question generalisation. Gap entries persist correctly, but generated supported prose does not consistently lead with the limitation; do not claim that presentation requirement fully realised. The supplemental unfamiliar questions were provider-free transport probes, not live semantic tests. R01P handoff/expansion decision remains with David; Phase 28 is not closed and no full148 run authorised here.

Immutable API root `/tmp/r01p-controls-20260906-0020`, host copy `/tmp/r01p-preserved-0001.mBGV6E/r01p-controls-20260906-0020`. SHA-256 manifest `8f1acac73b13c758aa0b6f82f4464c41f9bb4827b69151fec34d2e8ac22032f1`; complete `7b0c430b0f6a797552473ccc47f16b0c7be803d8c5ef54de665d57c3579dcd58`. Result hashes 01–08:

```text
895258b415c0772eadb1d79012f1c7ad80a95a9d08338962c72de124c8bb6dfd
6d30209146945c7238ed1b772ce7b160afb90c71392ad08cc755a9b5af14e86a
218a7b5ecbcb63030a1a081a7baf1bf8c390e17e46e8244be11ea00da319d6c3
688228198366295460d962ad63454b210ca62b40a7711d72426d64fa00de62bb
d81de3a20bd4cf1e3baba1300f48fc6818743c37489dcaa569389f3361eef36c
e4905db656559470ba6afe687844dab736b8df4a04d56f502aa1deb7b6f4e81a
5dd3c52f4e45cb8816b8e667825d9e22ee7774a9b652a9541e96c657f38a447b
9114272f3ce648704bfb44ab17027885a657ec36b68668f1cab9639e417b664e
```

## 2026-09-06 — targeted v6 confirmation 0019

David explicitly approved questions 01/06/08, authorised catalogue and evidence transfer under $5. Core source hashes matched the correction checkpoint. Executed each once, unchanged code; all completed. 01: 48.8677 seconds, answered the ROPA rename and employment contract/legal obligation from policy. 06: 29.7040 seconds, qualified with explicit unavailable Data Protection Policy comparison and supported ROPA field. 08: 29.3372 seconds, qualified ROPA comparison and explicit unsupported other-tenant private record. The three targeted behaviours passed this attempt; no truncation or extra retry occurred.

Citations were read and verified: 01 cites policy chunks 245/248 and ROPA 451/692; 06 cites ROPA 692; 08 cites ROPA 451/692. All resolve in the authorised primary workspace and support the supplied-document answers. Gap entries are preserved in `unsupported_aspects` and per-need status; do not claim that generated answer text itself always begins with the limitation. This is targeted development evidence, not proof of generalisation or full-slice readiness.

Usage: **16 physical provider attempts, zero retries; 76,551 input / 6,995 output tokens; 107.9205 seconds; estimated $0.03149743** using configured price snapshots and recorded tokens (null planner/rerank costs calculated, not treated as zero). Three planner, four embedding, six rerank-side and three generation requests. No judge, reindex, AWS, code change, commit or push. No full-eight run in this task. One same-code full-eight batch remains in the agreed cycle; if the same failures persist there, reassess the approach rather than start another serial repair loop.

Immutable API root `/tmp/r01p-controls-20260906-0019`, host copy `/tmp/r01p-preserved-0001.mBGV6E/r01p-controls-20260906-0019`. SHA-256: 01 `d7c5813ad019fb25416decfb9e2ed0a108a69e6f025a712175c2739d1ad17e3b`; 06 `ccef58649a3e05b5a446c6ed5b3427d7d34ade55f2b17e53c4021459f4e00cea`; 08 `88d0b1c1d019b7c45b52b70f33916f32b5a05756e3599c8ec43e566b64cd69af`; manifest `6a2885d2aaff111ce5f9b3e984fb775bc8475bd2540bd81788cb9a3827287e86`; complete `90ce9885f814ff1587bb5ed6b2d04af7dccb4acd5bf408619c43111880431cba`. Prior runs unchanged; R01P remains in progress.

## 2026-09-06 — approved single correction cycle, provider-free checkpoint

David approved one correction cycle, then targeted 01/06/08 and full-eight only on success, with reassessment if the same failures persist. Implemented three bounded changes, no paid execution in this task:

1. Planner prompt distinguishes a form as the destination of a value from the governing guidance that supplies that value. Such guidance may use the existing topic route; explicitly named policy history remains family-bound. Uses a generic expense/mileage example, not case IDs, corpus answers or a lexical routing rule. This is a semantic instruction, not a deterministic guarantee that the model will choose correctly.
2. Python `GenerationNeedsResult.validate_for_needs_request` and Laravel `ValidateGenerationResult::handleNeeds` now enforce per-need citation ownership. A part cannot count for a need while citing another need's handles. Empty evidence cannot be marked answered; a gap is required. Generation instructions state the same rule. This prevents the structural 06 loophole; it does not prove textual entailment. The application rejects violations rather than rewriting generated answers or adding retries.
3. Explicit planner `reasoning_effort=low`, preserving model and 4,096 completion-token cap, $5 envelope, concurrency and retry policy. Consulted [official Chat Completions reference](https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create) and [model guidance](https://developers.openai.com/api/docs/guides/latest-model?model=gpt-5.2) before setting reasoning. This avoids relying on the provider default but cannot guarantee no truncation. Safe completion diagnostics remain.

Identities: execution `bounded-needs-slice-v6`, planner prompt `bounded-evidence-needs-v9`, adapter `structured-chat-needs-v3`; generation prompt `grounded-generation-needs-v3`. Existing wire contracts/models/limits unchanged. Profile consumers and tests updated; older pins fail closed. No population, index, historical run or expectation modified.

Verification: **139 Python tests; 86 Laravel tests / 362 assertions passed**, three optional bridge tests skipped. Ruff/Pint, two-module mypy and whitespace checks passed. Includes missing-evidence and nonempty cross-need citation rejection, valid qualification, real generation adapter with fake response, planner request effort/cap assertions, signed transport, and production orchestration. Old synthetic fixtures shared citations across needs; replaced those positive fixtures with independently owned parts and kept explicit negative tests. Test-only tuple/required-side/indentation errors were corrected. Three supplemental fake questions about mileage, supplier retention and inspection classification prove topical contract transport, not live model comprehension. No claim of provider-free semantic generalisation.

Core SHA-256: planner `63277919e64d272149f631c4f59b25b4b06a9a3c576cba453744f249a42bc53e`; Python needs output `c503ac21665c6ba407d7d14183c5ca8cd393fee2921bb6709ddb294acee097ec`; generation adapter `67c85212eae2b904839c32ffa2fe511d36e1dff704ff3ba4562a87caa7553bf1`; Laravel validator `4a33667d346c3f76a1f1d3dcbf6efba6bc2e7021543231dd3ac2af8c89440236`; profile factory `82cefdfeac5ff5cd3ce81e261db66874dc0eab1af4c30bb414932374b3a39b3e`.

Stop before live execution in this implementation turn. Next targeted 01/06/08 requires explicit payload approval at execution safeguard; full-eight only if those pass. No extra audit or open-ended loop; remaining failures at this checkpoint warrant reassessment. No commit/push, provider or AWS call; only official documentation browsing. Existing unrelated changes preserved. R01P remains in progress.

## 2026-09-06 — same-code eight-question confirmation 0018

David explicitly approved all eight questions and authorised catalogue/evidence transfer to OpenAI/Voyage under $5 after the initial generic approval was rejected before execution. Ran once on unchanged correction code; no automatic rerun. Seven completed; question 08 failed and the runner stopped. Total 309.1583 seconds.

| Question | Observed result |
| --- | --- |
| 01 | Partial, not accepted: correct ROPA rename, but employment-basis need was bound only to ROPA, excluding available Data Protection Policy evidence. Earliest evidenced gap: planner subject binding/scope. Generation honestly disclosed missing basis. |
| 02 | Targeted answer supported: rename plus employment contract/legal obligation, with policy and ROPA citations. |
| 03 | Targeted answer supported: policy lawful bases plus ROPA rename. |
| 04 | Both requested topics answered with supporting policy citations: end-of-life reordering and current data-handling requirements. |
| 05 | June 2026 transition answered: DNACPR/symptom management moved before advance care planning; underlying content unchanged except where noted. Cited effective-date and change statements support this. |
| 06 | Not accepted: planner bound the correct Data Protection Policy family and eligibility returned comparison_scope_incomplete, but generation answered the ROPA rename for the policy-comparison aspect and returned no unsupported gap. Earliest observed divergence is generation/aspect coverage, not renewed retrieval family substitution. |
| 07 | Targeted qualified answer supported: ROPA rename, no assertion that the user's actual employment record was updated. |
| 08 | Execution failure before retrieval: completion/root/output_truncated, HTTP 200, finish_reason=length. 15,468 input tokens, 4,096 output tokens, all 4,096 reported as reasoning tokens. This run establishes output-budget exhaustion, not a malformed completed plan. |

All cited IDs in completed answers resolved inside the primary workspace and cited source text was inspected. Citation membership alone did not protect 06 against answering the wrong aspect. The broader answers are assessed against supplied synthetic documents, not certified real-world legal/clinical advice. No provider judge used. The new diagnostics worked and retained failed usage; they did not prevent truncation. Do not retrospectively assign this precise cause to earlier runs lacking metadata.

Usage: **45 physical provider attempts, zero retries; 180,796 input / 26,228 output tokens; configured-rate estimated $0.09185649**, including question 08's failed planner. Planner and rerank amounts calculated from recorded tokens where stored cost is null; not invoice charges. Infrastructure route counts: planner 8, search 12, rerank 12 (comparison sides counted separately in physical provider total), generation 7. No judge, corpus embedding, reindex, AWS, code change, commit or push. Full-eight quality acceptance remains unmet; R01P stays in progress and expansion held.

Immutable root `/tmp/r01p-controls-20260906-0018` in API, separate host copy `/tmp/r01p-preserved-0001.mBGV6E/r01p-controls-20260906-0018`. SHA-256 manifest `ab021365f69777f7b0f07f7e6fb4b8ae5088f79d29552090f49543896ffc0bc2`; stopped `884252c1b36a3153b3b4cce0557f7835cefd151519ffb60e6601438818983298`. Results 01–08 respectively:

```text
a3b2b08d29e620c2f42e8d2784aefdcc6be4467c175a4c3b0636c32cb6289500
4fdcec0b92df41ddda7cb09eace747f1702e84df2fdf69813bb3b49aa6c9c725
cdbd1cc6bdfe160ebaafe2ad16aaa241156945dad184816c3c266b8d626320d4
60e3fa9053a470674d1c24e1da6d1a4d83aec848be587e097b01eb83c0c17f13
47cba3f647ba11507d24ff437d05cf720165d336fe825c45750872cbc28397b8
47c44a05f250bc8677f9fd15ea37e19c0d0aa0e89c519539f5543c273eb96790
45bb57d1291564d7c9ee7508d5e326a14a23d013170a241f4510058ef40cbf45
03553fd82578e4e1c3f9e9517634f67af4143936239b62686b1c7aeb7a1a198c
```

## 2026-09-06 — question 08 only, successful control 0017

David explicitly approved question 08 and its authorised provider payloads under $5. Executed once with corrected completion diagnostics, unchanged v5/prompt/model/index limits. Run `64fc24a0-bc65-40e1-af2c-d550d7a286a9` completed in **56.3097 seconds**. No application changes during execution.

Production selected both ROPA versions for n1 and returned no eligible evidence for n2, with no location clarification. Persisted generation is `qualified`: ROPA change supported, foreign-tenant private-record fact unsupported. It answered the rename from “Legal basis” to “Lawful basis (UK GDPR Article 6)” and explicitly stated that the private record was not in supplied evidence. Cited chunks 692/document 229 and 451/document 144 both resolve in the authorised primary workspace; both source texts were read and substantiate the comparison. No other-tenant record or inferred lawful basis was returned. This control passed its targeted behaviour, not a full reliability or phase acceptance gate.

Five physical provider requests/attempts: planner 1, embedding 1, reranking 2 (comparison sides), generation 1; zero retries. Recorded input **18,269**, output **4,421** tokens. Configured-rate estimate **$0.01335512**: planner $0.011069, embedding $0.00000252, reranking $0.00001285, generation $0.00227075. Planner/reranker amounts calculated from recorded tokens where durable cost fields were null; not invoice charges. No judge, reindex, AWS, additional question or rerun.

The parser/completion failure did not recur; this does not establish 0016's actual finish reason or prove the intermittent issue eliminated. Full same-code eight-question confirmation remains outstanding. No automatic full-slice run, commit or push; R01P remains in progress.

Immutable evidence: API `/tmp/r01p-controls-20260906-0017`, separate host copy `/tmp/r01p-preserved-0001.mBGV6E/r01p-controls-20260906-0017`. SHA-256: result `717e22aa921a2cc6d0efed3f2ae25df2536f0faa421db1198c8d4d05c642b412`; manifest `6425aefe39cf714d3fd05b1cbc26de18ebd6e9474d4a05ed4de0460aec745b0f`; complete `c81fb81c9e46617b0b291ace665d5afdb0845c64fd194546fd3e62b2ac1758d4`. Prior evidence unchanged.

## 2026-09-06 — completion diagnostics correction

After a bounded read-only investigation, David requested the fix. Two synthetic MockTransport responses showed `finish_reason=length` and `stop` previously collapsed into the same `json_invalid` diagnostic, losing usage. The correction checks explicit incomplete/refused completion before JSON parsing. Length maps to `completion / root / output_truncated`; filter/tool/refusal to `output_not_completed`. Malformed JSON from a completed response remains `schema / root / json_invalid`. Existing generic failure category and no-retry behaviour remain unchanged. Missing/unrecognised completion reason is recorded as `unknown`, not invented; existing independent plan validation remains required.

Allowlisted completion reason and non-negative input/output/reasoning token counts are captured before validation and independently filtered by Laravel, then persisted in planner-failure usage. No raw content, refusal text, arbitrary metadata or credentials are retained. Unknown usage remains null; no cost is fabricated. Output limit stays 4,096; no prompt/model/schema, index, scoring or successful-plan semantic change. Historical runs remain unchanged and 0016 is not retrospectively claimed truncated.

Provider-free verification: 84 Python tests passed; Laravel transport 8 tests / 22 assertions passed; focused orchestration persistence test verifies safe metadata retention and stopping before retrieval. Ruff, Pint, scoped mypy and whitespace checks passed. No provider calls, paid retest, commit or push. R01P remains in progress; the next explicitly authorised small live attempt can now distinguish completion-limit failure from completed malformed output.

## 2026-09-06 — live controls 0016: 06 passed, 08 parser failure

David explicitly approved only questions 06 and 08, including questions, authorised catalogue and retrieved evidence sent to OpenAI/Voyage under $5. The current source hashes matched the preceding correction. Executed once on v5 / `structured-chat-needs-v2`; no application change, retry or additional question in this execution task.

- **06 completed**, run `daaade0c-5e0f-4efa-ad02-44f4bf53b3b4`, 36.3915 seconds. Production eligibility returned `comparison_scope_incomplete` for Data Protection Policy history, while current ROPA remained eligible. Persisted generation was `qualified`, n1 unsupported / n2 supported. It explicitly recorded unavailable policy-comparison evidence and answered the ROPA rename to “Lawful basis (UK GDPR Article 6)”. The sole citation, chunk 692/document 229, resolves in the primary workspace; its source text was read and supports the answer. No wrong-policy substitution.
- **08 failed**, run `43f0f58f-d4dd-4acf-a505-77dd98e0a8d1`, 38.0571 seconds. Planner provider HTTP 200, one attempt, `invalid_grounded_needs_plan`; safe diagnostic **schema / root / json_invalid**. No retrieval, evidence selection or generation occurred for 08. This is an execution failure, not a passed tenancy answer or measured retrieval failure. The batch stopped as designed.

Read-only adapter inspection locates this diagnostic at `EvidenceNeedsPlan.model_validate_json(content)`, after successful decoding of the provider response envelope and extraction of string content. Thus the content could not be parsed as the expected JSON; this is more specific than 0014's generic failure. Retained evidence does not include completion reason or raw response, so truncation versus another malformed-content cause is not established. The adapter reads usage only after validation, leaving the failed planner's token/cost fields unavailable. No attempt was made to recover raw sensitive output or repeat the request. Cheapest next check: bounded provider-free inspection of completion-state handling and safe metadata retention, before proposing any additional paid attempt. Do not claim this proves the original 0014 cause or that schema constraints eliminate all output failures.

Total: **5 provider attempts, zero retries**, two OpenAI planner attempts, one Voyage embedding, one Voyage rerank, one OpenAI generation; **74.4509 seconds**. Question 06 recorded 17,839 input / 2,615 output tokens and configured-rate estimate **$0.00965629**: planner $0.007920, embedding $0.00000264, rerank $0.00000765, generation $0.001726. Planner/rerank cost calculated from recorded tokens where stored cost is null; these are estimates, not invoices. Question 08 tokens/cost unavailable, not zero; its existing one-attempt conservative allowance is $0.033192, giving combined estimated/allowance envelope **$0.04284829**, below $5. No judge, corpus embedding, index change or AWS.

Original evidence: API `/tmp/r01p-controls-20260906-0016`; separately preserved host copy `/tmp/r01p-preserved-0001.mBGV6E/r01p-controls-20260906-0016`. SHA-256: 06 `530ffa6c9afce2bf8c65454cc471a895840da9a2564ff500615f169cf55b44e8`; 08 `083ad5f799de7d671870579303befee48aee6574f504cf20c9db404104c4e46e`; manifest `fd435cf22676f82ca77f235f877b2621efdc95115b6bbb532cda36f754e36309`; stopped `8307d547ad604648711facb48e94246ab049b69c37e1f86f06612860b236ffca`. Prior evidence untouched. No commit/push. R01P remains in progress; full-eight confirmation and broader expansion held.
