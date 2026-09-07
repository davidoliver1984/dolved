# Guard precision and e23 variability — 2026-09-07

R28-S04 / R01P remain in progress. No R02, commit, push or new question set. This task characterises two issues; no production-code or validation-rule change was made.

## Starting and frozen identity

HEAD `6fdc2981732773ff08be8e3b5c1f505112501c87`; runtime aggregate `94d90369816f5f606fd2508de76f70e7445d059e7ca053879732a02f684c4b28`. Existing uncommitted work matched the preceding checkpoint; no unexpected runtime drift. All live repetitions use this same code and the existing 982-chunk primary index.

## Task A — actual detector boundary

Seventeen synthetic, fictional badge/access-code examples were executed through the actual `GenerationNeedsResult.model_validate()` implementation. No corpus, held-back answer key or live provider was used. Semantic labels are stipulated for this controlled fixture, not machine-verified entailment or a population-level accuracy estimate.

The exact current regex is `^\s*(?:yes|no)\s*(?:[.!?](?:\s|$)|$)`, case-insensitive, applied at the beginning of every answer part. It inspects neither the question nor evidence nor the meaning of following prose. A period/exclamation/question mark followed by whitespace/end triggers rejection. Commas, dashes, colons, Markdown prefixes and punctuation without following whitespace do not.

| Synthetic answer | Intended semantic acceptance | Actual detector |
| --- | --- | --- |
| Yes, and the cited section also notes that staff may request a replacement badge. | Accept | Accepted |
| No — the policy explicitly forbids sharing access codes. | Accept | Accepted |
| Yes. This is confirmed by the cited passage, which states that staff may request a replacement badge. | Accept | Rejected: detached_polarity |
| No. The policy explicitly forbids sharing access codes. | Accept | Rejected: detached_polarity |
| Yes.<br>Staff may request a replacement badge under section 4. | Accept | Rejected: detached_polarity |
| Yes. | Reject | Rejected: detached_polarity |
| No | Reject | Rejected: detached_polarity |
| Yes. Staff are not permitted to share access codes. | Reject | Rejected: detached_polarity |
| No. Staff are permitted to request a replacement badge. | Reject | Rejected: detached_polarity |
| Yes. The fire drill is held quarterly. | Reject | Rejected: detached_polarity |
|   yes. Staff may request a replacement badge. | Accept | Rejected: detached_polarity |
| Yes! Staff may request a replacement badge. | Accept | Rejected: detached_polarity |
| Staff may request a replacement badge under section 4. | Accept | Accepted |
| Yes, staff are not permitted to share access codes. | Reject | Accepted |
| Yes: staff may request a replacement badge. | Accept | Accepted |
| **Yes.** Staff are not permitted to share access codes. | Reject | Accepted |
| Yes.Staff are not permitted to share access codes. | Reject | Accepted |

Five consistent period/exclamation constructions were rejected. Three deliberately bad comma/Markdown/no-space constructions passed. The detector is therefore a formatting prohibition, **not a precise detector of semantic detachment or contradiction**. This disproves the claim that the existing syntax restriction itself removes the whole contradiction class.

The newer requirement explicitly accepts “Yes. This is confirmed…” while the previously accepted d04 rule prohibited a separate Yes./No. token before the substance. Both consistent and contradictory versions have the same prefix. Narrowing punctuation matching to accept the former also admits the latter; adding semantic word lists would repeat the rejected brittle approach. No safe regex-only correction satisfying both requirements was demonstrated, so the guard was not changed. Its precision is not being reported as correct. A correction is warranted at the framing contract/answer-construction boundary; allowing normal sentence-level directness while rejecting contradictions requires semantic assessment or avoiding a separate polarity headline, not a finer punctuation rule. That decision is left explicit rather than silently weakening the invariant.

## Task B — exact previously recorded predicate

Prior attempt 1: run `467b32e1-dac0-4d6f-bd0b-d410d5392964`, `/tmp/dolved-generation-diagnostic.IgahFO/run/e23.json`. `catalogue / root / binding_coverage`, HTTP 200, finish reason `stop`, one planner attempt, 15,833 input / 664 output tokens, 8.731 seconds. No retrieval or generation occurred.

The rejected predicate in `EvidenceNeedsPlan.validate_catalogue()` is `[b.need_id for b in self.bindings] != [n.need_id for n in self.needs]`. Equality requires the same IDs with the same multiplicity and order. Missing/extra/duplicated bindings and pure order mismatches can all fail it. It is checked before family allowlisting and before `in_question_order()` reconciliation. The persisted record contains neither rejected array, and the trace is empty; it cannot distinguish those subconditions. It would be an invention to call this specifically a missing binding or reorder defect. The original previous-round generation failure is separate historical evidence, not an extra planner observation in the five-attempt experiment.

Four additional attempts were authorised explicitly, not accidental selective reruns. Each uses the exact e23 text in an independent conversation through `OrchestrateConversationRun`; the harness names them attempt2–attempt5 solely to keep immutable records separate. No code, model, index, threshold or prompt changes occur between attempts.

## Five e23 observations on unchanged runtime

Question verbatim: “Quick one — my uniform's falling apart, can I get a new one, and how often are the hoists actually checked?”

| Attempt | Planner | Retrieval | Generation / actual result |
| --- | --- | --- | --- |
| 1 (prior diagnostic) | Rejected: catalogue/root/binding_coverage | Not reached | Not reached; no answer |
| 2 (new) | Accepted; 2 needs | Evidence found | Answered |
| 3 (new) | Accepted; 2 needs | Evidence found | Rejected: answer_parts.text/detached_polarity; no answer |
| 4 (new) | Accepted; 2 needs | Evidence found | Qualified |
| 5 (new) | Accepted; 2 needs | Evidence found | Answered |

**Variable and unstable, not consistently reproducing one defect and not “probably fine.”** The precise binding failure occurred in one of five observed attempts; the four additional attempts did not reproduce it. One new attempt independently reproduced the generation framing rejection. The two earlier failure categories must not be conflated. All issued citation IDs resolve inside the primary workspace; this does not establish entailment or correctness. These are known-case observations, not independent evaluation or a reliability estimate from a statistically adequate sample.

### Full generated responses

#### e23-attempt2

If your uniform is worn or ill-fitting you can get it replaced: uniformed staff must wear the current Alderbridge uniform, keep it clean and in good repair, and raise a fit/condition concern with your line manager so a replacement or exchange is arranged through the uniform supplier; replacement is provided annually or as needed under the Uniform Allowance Guidance. Financial support is provided too: new starters in a uniformed role receive a one-off initial uniform allowance (now £55) in their first month’s pay, and staff in uniformed roles receive a replacement allowance each April (£35) to support ongoing upkeep and replacement.

Hoists are subject to statutory LOLER inspections every six months and those inspections are carried out by an external contractor; additionally, care staff must complete a pre-use check of hoists (and slings) before every use as part of routine safe practice.

Unsupported aspects: []

Citations: document 15 / chunk 73; document 15 / chunk 74; document 22 / chunk 123; document 190 / chunk 594; document 260 / chunk 782.

#### e23-attempt3

No answer produced. Generation was rejected by the detached-polarity guard.

Unsupported aspects: []

Citations: none.

#### e23-attempt4

If your uniform is worn or falling apart, Alderbridge’s policies require uniformed staff to wear a clean, good‑repair Alderbridge uniform and provide financial support for replacement. The current Uniform Allowance Guidance pays every staff member in a uniformed role an annual replacement allowance of £35 each April to support ongoing upkeep and replacement, and a one‑off initial allowance of £55 is paid in a new starter’s first month. The Dress Code and Uniform Policy says uniformed staff wear the supplied uniform, replaced annually or as needed through the Uniform Allowance Guidance, and that the registered manager addresses dress‑code concerns promptly. These documents also state the organisation reviews allowance amounts periodically and that an identity badge must be worn on shift.

Hoists are subject to two layers of checks: a pre‑use check by care staff before every use, and a statutory LOLER inspection carried out by an external contractor every 6 months. The Manual Handling Equipment Guidance requires a pre‑use check before each hoist/sling use (now including a mandatory sling weight‑limit cross‑check against the resident’s recorded weight), use of at least two staff for every hoist transfer, and states Facilities arranges the now 6‑monthly LOLER inspections; the Equipment Inspection Schedule also lists hoists as inspected 6‑monthly (LOLER) by an external contractor.

Unsupported aspects: ["The supplied evidence does not state the specific operational steps you must follow to request a replacement uniform (for example, who to contact or whether a formal request form or approval is required)."]

Citations: document 15 / chunk 73; document 22 / chunk 123; document 190 / chunk 594; document 190 / chunk 595; document 260 / chunk 782.

#### e23-attempt5

Alderbridge’s Dress Code and the Uniform Allowance Guidance require that uniformed staff wear the supplied uniform, keep it clean and in good repair, and may receive financial help to replace it. The Dress Code says uniforms are replaced annually or as needed through the Uniform Allowance Guidance; the Uniform Allowance Guidance specifies an initial one‑off allowance paid in the new starter’s first month and a replacement allowance paid every April (current amounts: £55 initial, £35 replacement). If a uniform doesn’t fit or is worn, raise it with your line manager to arrange a replacement or exchange through the uniform supplier; the registered/line manager deals with dress‑code concerns and the payroll setup step confirms someone’s uniformed role so the allowance is applied.

Hoists are subject to statutory LOLER inspections every 6 months and those inspections are arranged by Facilities or carried out by an external contractor. The Equipment Inspection Schedule lists hoists as inspected 6‑monthly (LOLER) with an external contractor responsible, and the Manual Handling Equipment Guidance also states the LOLER inspection is conducted 6‑monthly (changed from annual) with Facilities arranging the inspections.

Unsupported aspects: []

Citations: document 15 / chunk 73; document 15 / chunk 74; document 22 / chunk 123; document 190 / chunk 594; document 260 / chunk 782.

## Smallest justified next steps, not implemented

- Framing: agree how ordinary period-separated direct answers should coexist with the prohibition on contradictory polarity headlines. The measured false positives and false negatives cannot be resolved by punctuation-only narrowing. Do not describe the present guard as semantic correctness enforcement.
- Planner: retain fail-closed catalogue binding. The smallest discriminating improvement is bounded structural diagnostics recording need-ID and binding-ID order/count (registered n1/n2/n3 only, no raw model text) at the failing predicate. Missing/duplicate/extra IDs must remain invalid. An order-only reconciliation should be considered only if evidence shows complete unique matching ID sets; this task did not establish that subtype and did not implement reconciliation.
- No additional paid run or broader repair is triggered by these findings. The case remains open pending David's decision.

## Verification, cost and preservation

Task A: 17 provider-free cases through the real detector; zero provider calls. No application code, validator, test suite, schema, model, prompt or threshold changed, so no new implementation test/lint/type claim is made; the previous 228-Python/211-Laravel/3-bridge results remain historical evidence. Current source-freeze verification, JSON parsing, documentation diff/whitespace and evidence checksum checks passed.

Task B's four new attempts used **24 provider attempts, zero recorded retries**, 196,710 known input tokens / 5,714 known output tokens, **USD 0.04656962 partial configured-rate estimate** (one failed generation receipt unavailable), **USD 1.04031274 conservative budget charge**, and **127.137 seconds**. All remain within USD 5 / 90 minutes. Prior attempt 1 is historical, not re-billed as current work: one planner request, 15,833 input / 664 output tokens, USD 0.00528625 configured-rate estimate, 8.731 seconds. Combined five-observation known totals are 25 attempts, 212,543 input / 6,378 output tokens, USD 0.05185587 partial estimate and 135.868 seconds of execution, not including the gap between runs. Native retries were unchanged; no re-indexing, judge, AWS, held-back key or extra questions.

Evidence: `/tmp/dolved-precision-variance.qcUPyc/` contains the complete synthetic fixture/results, four original execution records, dispatches, accounting, source freeze, full observations/citations and checksum inventory. Four-repetition input SHA-256 `c6999ebef687b0a7e6841ccd550ab510a2aac51bd6021487dad1458fc4c63768`; all four texts equal the verified source e23 question exactly. Runtime aggregate remains `94d90369816f5f606fd2508de76f70e7445d059e7ca053879732a02f684c4b28` before/after. This task changes only this report, the living plan and tracker; previous uncommitted implementation and 33 unrelated files are preserved. No commit or push; R01P remains open and R02 unstarted.

