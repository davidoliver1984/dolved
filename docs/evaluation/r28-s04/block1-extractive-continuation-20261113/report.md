# Block 1 continuation — STOP / HOLD, 2026-11-13

**Recommendation: hold Block 1; do not merge to main.** David's revised criterion
(c), “any answer in the checkpoint is wrong, whatever the cause”, triggered at
input **15, b03-01/v1**. No repair, tuning or redispatch followed the stop.

Plan decision `b8c9cd7590ba98f848dfacc23352ba1ead6f3560` and harness-only commit
`e0c5147874a0fab08e0449359925b10894731add` were pushed to
`codex/block1-extractive-hold-20261112`. The latter is the exact execution SHA.
Main remains `348f09e053c8ad73676921c5c616cced812111d7`.

## Governing controls and immutable linkage

- Frozen full selection checksum: `0de5c2ea1998820c1ef950db79ea4386d36a25a7f91bfa85e3693dfb02cc0183`.
- Original run: `../r06-governed-evidence/R28-R07-BLOCK1-EXTRACTIVE-20261113-01/`.
- Continuation: `../r06-governed-evidence/R28-R07-BLOCK1-EXTRACTIVE-TAIL-20261113-01/`.
- Fresh authorization: `R28-R07-AUTH-BLOCK1-EXTRACTIVE-TAIL-20261113-01`, consumed once.
- Harness selects only original inputs 9–32, verifies the original eight-case ledger
  and every original inventory entry, and refuses previously dispatched identities.
  Exact local/remote SHA, fixed runtime and unchanged manifests are checked again
  before each dispatch. Existing stages retain their previous rules.
- **37 provider-free harness tests passed**, including manifest/ledger/runtime/SHA
  mismatch, original-input refusal, authorization reuse and existing-main gates.
- Original inputs 1–8 were neither redispatched nor rewritten. Their entire
  published inventory still verifies. Continuation local indices 1–7 map to global
  inputs 9–15; `continuation-link.json` declares the complete mapping.

Before and after application runtime:
`0eab315797d6b8af9baea21b85a867386c83bf425c7719ffb16b9974bc9acdc8`.
Before and after configuration/index snapshot:
`f271c1ac843e53aaa16f6f0c70f6f3b642499d69dac2467d17e80c42f280d792`.
Thus the original eight and the seven new inputs used the same application source.
The preserved unrelated runner/test/ADR edits remain unstaged; executed runner
copies and runner hash capture exactly what ran.

## Stop: a source qualification was dropped

Question: “An outside agency has not acknowledged my safeguarding referral. What
happens next, and what record must we keep?”

Answer begins:

> If an external agency does not acknowledge your referral, the registered manager
> should follow the escalation steps: contact the agency’s own duty line to follow
> up, and if the agency still has not resolved or acknowledged the referral within
> a further 2 working days, inform the organisation-level Safeguarding Lead…

Cited current Multi-Agency Safeguarding Protocol v3, chunk
`8899d493-216d-5695-a0b0-094d4fde1129` (database chunk 940), says:

> Where an agency, including the Office of the Public Guardian or Action Fraud,
> does not acknowledge a referral within its own stated timescale, the registered
> manager escalates first to that agency's own duty line, and, if unresolved within
> a further 2 working days, informs the organisation-level Safeguarding Lead,
> who may escalate directly within the agency or through the local Safeguarding
> Adults Board's own escalation route.

The first escalation is conditional on expiry of the agency's own stated
timescale. The answer makes non-acknowledgment alone sufficient; it never states
that condition. Its parenthetical two-working-day checklist is a separate
post-referral follow-up instruction, not evidence that the agency-specific
escalation condition can be removed. The later escalation and retention facts
are supported, but that does not repair the strengthened opening instruction.

**Verdict: wrong under the fixed source-based criterion, owned by Block 3.** The
correct source was retrieved. The interpretation check accepted; generation
returned `answered`, `aspect_results.n1=supported`, `unsupported_aspects=[]`, and
its entailment-reviewed attempt was accepted. This is the already-evidenced
source-condition/obligation-strengthening class, not proof of a new extraction
failure. Nevertheless criterion (c) applies whatever the cause. The runner was
stopped at review gate 7/24 before input 16.

## All recorded inputs and remaining selection

`correctness-review.json` contains every displayed answer, frozen expectation,
full verbatim final-evidence source text with document/version identity, citations,
checked plans, compiled requests and manual verdict. No provider judge was used.
The 17 unattempted entries are explicit, with no verdict. The original expectation
and raw packages were not modified.

| Input | Case / v1 | Verdict | Source-based result / owning mechanism |
|---|---|---|---|
| 1 | b01-02 | correct | Facilities Lead → Estates Helpdesk, both versions. |
| 2 | b01-04 | correct | Highest-risk repositioning 4h → 2h; native planner correction. |
| 3 | b01-05 | correct | Outbreak response and daily logs; infection/site condition retained. |
| 4 | b01-06 | correct | Approved mileage next salary run; original injection workspace retained. |
| 5 | b01-07 | correct | Starter £40→£55; annual £25→£35. |
| 6 | b01-08 | correct | Recorded scope plus explicit outreach text; 24h code-change report. |
| 7 | b01-09 | no-answer | Block 2 selected→no_match, quote_not_in_candidate. |
| 8 | b01-10 | correct | Rule unchanged; one false omission flag, correct retry outcome. |
| 9 | b02-01 | correct | Immediate interim controls/formal assessment and retained record classes. |
| 10 | b02-03 | no-answer | Correct dated key-holder evidence present; two entailment_uncertain rejections, Block 3. |
| 11 | b02-06 | correct | Coordination group guidance and organisation-wide debrief. |
| 12 | b02-07 | correct | Family communication presented first; no substantive requirement change. |
| 13 | b02-08 | correct | HQ DSE assessment repeated annually/after workstation change. |
| 14 | b02-09 | correct for stated previous-version reading | v2 and v3 both say 1.5×; expectation selects v1, a known Block 2 ambiguous selector. |
| 15 | b03-01 | **wrong — STOP** | Agency-specific escalation timing condition dropped, Block 3. |
| 16–30 | Remaining labelled cases | not dispatched | Stopped, not passes or failures. |
| 31–32 | b01-01 / b03-05 | not dispatched | Development cases, not unseen confirmation. |

Total: **15/32 reviewed: 12 correct, 0 qualified-correct, 1 wrong, 2 no-answer**.
Raw status split is 14 completed / 0 clarification / 1 retrieval_no_answer;
literal split is 13 substantive answers / 0 clarification / 2 no-answers because
input 10 displays the controlled no-answer text despite completed status.

Input 14 is not scored from expectation wording alone: cited v2 explicitly sets
1.5×, and the answer calls it the “previous version”. The question's undated
“older guidance” does not uniquely identify v1. Its v1 EvidenceUnit still scores
as a comparison miss; no expectation was changed to improve metrics.

## Every non-accepted checker flag

No new flags occurred in inputs 9–15. The retained flags are:

| Input | Code / citation | Verification and manual outcome |
|---|---|---|
| 4 | scope_added; `fact_reference.value=2026-10` | Unverifiable `application_grounded_period`; no retry. Full checked plan manually reviewed: no hidden dropped clause or added scope. |
| 8 | clause_omitted; `When “Over the counter medicines” became “Homely remedies list”` | Verified but wrong; compare route already represents rename and rule change. Retry ends correct. |

False-retry question count is **1 of the 15 observed (1/32 selected)**, not a
claim about the unattempted 17. Extra accepted second-attempt overhead for input 8:
2 calls, 19,830 input / 1,235 output tokens, 14,609.41734 ms, retained-rate estimate
USD 0.0074275. Full flags, citations and reviewed plans are in the review JSON.
The withdrawn text-presence refutation rule was not implemented.

## Metrics and fixed acceptance criteria

Existing ADR-0020 matcher, normalised-token-coverage-v1, K=5, unchanged units.
Four newly observed ranked chunks were pulled read-only and checksum-frozen;
original 499-chunk and earlier extra-chunk snapshots remain unchanged.
`results-a.json` and `results-b.json` are byte-identical independent reruns.

| Side | Observed attempted count | Partial Recall@5 | Same-variant attempted intersection | New / old intersection |
|---|---:|---:|---:|---:|
| Primary | 14 | 0.9642857143 | 11 | 0.9545454545 / 0.9545454545 |
| Comparison | 6 | 0.8333333333 | 4 | 0.75 / 0.75 |

These are **partial**, not the full 30-labelled-case acceptance figure. No
measured recall loss on the attempted intersection is attributable to concatenated
request wording here; incomplete data cannot establish absence of a full-selection
effect, and cannot pass the ≤0.03 gate.

| Fixed criterion | Position |
|---|---|
| No invented executable wording | Provider-free source wiring tests plus production-compiler replay pass for all 15 observed plans. Trace does not retain outbound strings verbatim; this is replay proof, not a wire capture. |
| Recall@5 loss ≤0.03 per side over full selection | **Not established**, checkpoint stopped early. |
| No new interpretation-failure/clarification on clear questions | None in 15 observed; unattempted cases remain unverified. |
| Full suites retain 38 AI / 2 Laravel | Last full suite comparison remains applicable: application, contract and app-test files unchanged since fixture migration `6a785bd`. No fresh full-suite run is claimed for this harness-only step. Raw JUnit and exact-name diff remain in the fixture evidence. Harness 37/37 freshly passes. |
| False flag ends wrong/no-answer | Not observed; input 8 ended correct. |
| More than 2 false-retry questions | Not observed; one observed. |
| Any wrong answer | **Failed at input 15**, criterion (c). |

## Cost and disposition

Reservation was stated before dispatch: USD 3 cumulative less prior known/estimated
USD 0.11196123. Cumulative priced-plus-retained-rate subtotal is now
**USD 0.23752892** (continuation increment USD 0.12556769), not a complete invoice.
23 Voyage reranking and 46 local Qdrant search receipts remain unpriced. Retained
token counts total 849,185 input / 33,102 output, with source receipts and
deduplication treatment in `accounting.py/json`; generation aggregate includes
review, and accepted planner counters include their checker. Unknown caching is
estimated uncached. No provider calls followed the stop.

The original records/checksums, full selection and expectations remain immutable.
No application change, no omission-rule change, no main merge, no Block 2 work.
Evidence and the living-plan update are published only on the hold branch.
**Stop for David's decision.**
