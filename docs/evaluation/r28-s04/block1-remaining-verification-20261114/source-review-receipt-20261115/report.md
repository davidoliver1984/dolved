# Bounded source-review receipt correction — 15 November 2026

Starting commit `d3f08d5c8d583172e686e24ae1ff0298a167add1`, hold branch only.
No provider calls, selector redesign, HTTP-timeout change or new retry/call.
Item19 and G3 remain separate open obligations; frozen criteria unchanged.

## Actual path (confirmed before implementation)

`GenerateGroundedAnswer::prepare()` → `GenerationClient::generate()` → signed
`/api/internal/retrieval/generation/answer-needs` → `needs_generator_dependency`
→ `build_needs_generator/build_generator` → `OpenAIGenerator::generate_needs/_generate`.
The adapter calls `responses.parse` to generate the answer, validates it, and—only
for nonempty answer parts—calls `responses.parse` again on **the same client** for
source review. `review_input` supplies full cited passage text, question, grounded
requested spans, per-need allowed handles and applicable temporal context.
The correction also explicitly carries already-supplied cross-turn context spans
into that review payload. It never substitutes the short anchor for full context.

Allowlisted isolated Compose configuration and inspection of the existing stopped
`dolved-r28-s03-ai-1` / `dolved-r28-s03-api-1` containers agree: model gpt-5-mini, low reasoning,
generation output4096, generation timeout120s, max_attempts3, backoff2s/max30s.
The factory passes timeout120 to `OpenAI(..., max_retries=0)`. Both sequential
provider calls inherit that transport timeout. The Laravel generation POST uses
`retrieval.timeout_seconds=480`; it encloses generation **and** review, not one
120-second review route. No standalone review endpoint or independent review retry.
Transport timeouts are not a proven whole-operation deadline.

The Python retry loop surrounds both calls. A retryable review transport error
can repeat generation then review; a completed contract rejection exits to Laravel.
Laravel permits two validation attempts. Thus normal two completed/rejected answers
can use two generation + two review calls; a conservative combined ceiling allows
six of each (three transport attempts in each of two validation attempts).
SDK retries remain zero. Request-path tests exercise this relation provider-free.
Existing retry accounting may lack tokens for failed transport attempts; reservation
must not treat unknown usage as zero. Nothing here changes that retry policy.

## Receipt v2 and completeness boundary

Short exact anchors reuse the family-reviewer's 8–300-character + exact-substring
pattern. A substantive source shorter than eight characters may be quoted whole;
a short fragment of a longer source cannot. **No quote is silently truncated.**
The reviewer still assesses the full passage, including qualifications outside the
anchor. A matching anchor proves text identity, not entailment.

All receipt dimensions are bounded: twelve parts; thirty claim/source/need
associations **in total**, not thirty per part; answer quotes1–600 characters;
source anchors1–300 subject to the short-source rule; identifiers1–32; missing
conditions≤160; part index0–11; closed verdict/judgement enums and boolean capacity.
Thirty accommodates six requested aspects × five physical source associations;
it does not assume questions can never contain more material claims. Longer answer
quotations can be split without dropping text or merging semantic judgements.
If complete review cannot fit, `capacity_exceeded=true, parts=[]` is explicitly
rejected as entailment_uncertain. Impossible part/owner counts reject before the
review call. No partial receipt can imply acceptance.

Counts/characters alone do not control token density. The **entire canonical JSON**,
including keys, IDs, reasons, enums, answer quotations, anchors and the capacity
flag, must also fit12,288 o200k tokens. Dense Unicode can exceed that bound despite
fitting character limits: it is rejected, never shortened or partially accepted.
Maximum-field fixtures and the exact whole-receipt boundary are tested.

Output allowance is17,408 =12,288 receipt +4,096 reasoning +1,024 serialisation
headroom, not an8,192 target. The whole-receipt cap is an acceptance limit; the
model can still exhaust reasoning/output or emit invalid/incomplete JSON. Those
receipts remain rejected. Neither headroom amount is a completion guarantee.
Measurements and exact sizes are in `receipt-sizing.json`.
The maximum-field English fixture measures 6,694 tokens; the 12,288 acceptance
cap leaves 5,594 tokens for token-density variation, with reasoning/format headroom
additional. A same-count, same-character-limit dense Unicode fixture measures 56,314
and is correctly rejected. Character limits alone would not have established a
meaningful output envelope. Neither fixture estimates production output frequency.

## Latency and reservation (not live measurements)

Illustrative serial emission at40–100 output tokens/s would take roughly174–435s
to exhaust17,408 tokens, before input/prefill/network time. Real reasoning/emission
rates are unknown; the maximum allowance is not expected output. Smaller anchors
reduce typical receipt volume but provider-free sizing cannot prove completion
within the unchanged120s timeout or enclosing480s request.

Per review output reservation falls from32,768 to17,408 (15,360 fewer tokens).
Normal two-review maximum:34,816; conservative six-review maximum:104,448.
Including six4096-token generation calls gives129,024 output tokens, compared with
221,184 for the same retry envelope at the prior review ceiling. Use the full
retry envelope, not just two review calls. This is not a complete USD reservation:
input reservations, earlier pipeline stages, selector and unknown/failed attempts
remain additive under existing governed controls. No live authorisation is consumed.
The review input is not separately token-metered in current code; do not falsely
claim the generation400,000-token preflight was applied to that review input.
Existing input/reservation controls must account for repeated full-passage context;
this correction changes only the receipt/output reservation, not those controls.

## Verification/publication

Provider-free checks and full-suite exact failure identities are recorded in the
adjacent raw JUnit and `suite-comparison.json`. Historical evidence is untouched.
Runtime manifest includes the internal receipt version and producer/consumer pins.
Publication is hold-only; Journey local. Block1 remains HOLD.

Final comparison: AI 1,534 tests (1,492 passed, 38 failed, 4 skipped); Laravel 949 tests
(two failures, nine skipped, 5,743 assertions). Exact identities match the retained
baseline, zero new/absent; no resolved failure claimed. The inode-substitution
failure reproduced. Focused request-path/anchor tests initially 68 passed; five
additional bound/split-coverage checks and the cross-turn-context check are included
in the final full suite.
No assertions were relaxed. The first factory test used a field name rather than
the settings key alias and failed before client construction; corrected fixture
injection uses `model_copy` and never a real credential.
Scoped Ruff/format and Pint pass; Python typecheck passes for169 source files.
An initial schema callback returned `dict.pop`'s value instead of `None`; its type
annotation/callback was corrected without changing the schema transformation.
