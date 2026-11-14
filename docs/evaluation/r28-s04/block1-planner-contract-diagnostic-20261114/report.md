# Planner-contract diagnostic — pre-dispatch budget blocker

2026-11-14. **Not executed. Block 1 HOLD.** No provider calls, spend,
authorisation creation/consumption, application changes or fixes. No capture
implementation or controlled capture tests were performed after the budget check
showed that the required conservative reservation does not fit the authorised cap.

## Boundary verification

Starting HEAD: `dac50f7a0a03e061e4041112be476f64de02a31e`, as corrected by David.
Branch: `codex/block1-extractive-hold-20261112`.
The additional commit after `7455686` contains only the reviewer publication.
Only its commit metadata and filenames were inspected to establish that fact;
none of the review/expectation contents were read.

Frozen application inventory verified unchanged:
`7f20f123cb9e4af9567e0ea0240ebf715d54755ae2611dc66ecd6719756773af`.
Released question-only file verified:
`7603fc82fc2f95f9852f584f28cc6a3d4949a2a30e17b8ab3b56a52ce4d2899f`.
Main remains `348f09e053c8ad73676921c5c616cced812111d7`.
The three existing unrelated tracked ADR/runner edits are preserved.
No stack operation was required or performed; no new live workspace/connection
preflight is claimed. The original C2 checkpoint is untouched.

## Existing capture boundary

- `apps/ai/app/retrieval/needs_planner.py:682–748`: provider content is parsed,
  normalised and validated inside `_plan_needs_once`. On failure, the adapter
  creates `NeedsValidationFailure` with a closed stage/field/code and completion
  metadata, then raises it without exporting the rejected content or parsed plan.
- `needs_planner.py:493–539`: bounded retry receipts retain validation, usage,
  status and timing—not the failed plan. Feedback is stage/field/code only.
- `apps/ai/app/retrieval/routes.py:248–272`: the HTTP failure exports that bounded
  metadata, attempt count and receipts; no rejected plan field exists there.
- `apps/ai/app/retrieval/interpretation.py:200–216`: an interpretation-check receipt
  can contain a reviewed plan, but only after schema/grounding/catalogue validation
  succeeded. It cannot reveal the earlier fact-date/temporal-grounding rejections.
- The existing `client: httpx.Client | None` constructor seam in
  `needs_planner.py:419–439` / `planner.py:318–344` makes provider responses
  accessible to a separately instantiated evaluation harness before validation.
  There is no discovered runtime flag enabling that capture on the deployed HTTP
  exporter. A harness-only capture could use this seam; evaluation scoping,
  allowlisting, redaction, retry equivalence and both rejection fixtures still
  require proof. They are **not claimed implemented or verified** in this task.

No unrestricted exception text, provider response, credential or authentication
header was captured. No inaccessible rejected plan was inferred from its code.

## Reservation cannot fit USD 0.25 with unchanged limits

Frozen code permits two planner attempts per question (`needs_planner.py:449`),
each with **25,000 combined reasoning/output tokens** (`:417`, `:612`). A plan
passing structural validation invokes the existing interpretation check (`:461`),
whose output ceiling is **4,096 tokens** (`interpretation.py:225`). A rejected
check can lead to the second planner attempt and another check. The diagnostic
must allow failures not to recur; assuming both questions fail before checking
would not reserve their full permitted production path. This is an intrinsic
planner check, not a newly added judging call.

Using the retained accounting basis already used by the C2 checkpoint—USD 0.25
per million uncached input tokens and USD 2 per million output tokens:

| Component | Reservation/subtotal USD |
|---|---:|
| Four permitted planner attempts × 25,000 output tokens | 0.2000000 |
| Four possible intrinsic checks × 4,096 output tokens | 0.0327680 |
| Prior G2/G3 planner inputs: 18,618 + 18,705 + 18,632 + 18,719 = 74,674 tokens | 0.0186685 |
| Subtotal **before checker input tokens or uncertainty** | **0.2514365** |
| Authorised diagnostic cap | **0.2500000** |

The prior input counts are measured historical observations, not a guaranteed
ceiling on the new requests. Therefore 0.2514365 is **not** a complete conservative
reservation, an actual bill, or a claim that the likely run would cost that much.
It already exceeds the cap before adding omitted costs. The planner's hard
100,000-byte request bound (`needs_planner.py:668`) also supplies no tighter
reservation that would make this safe. Cached-input discounts cannot be assumed.
The retained pricing basis is not asserted to be a new provider invoice quote.

No output ceiling, interpretation check, retry or sample size was reduced to
manufacture a fit. No paid diagnostic attempt has been made.

## Findings and disposition

There are **no new proposed values or attempt verdicts to report**. The original
G2/G3 rejections remain unexplained at payload level. Structural validity and
semantic interpretation cannot be classified as justified/unjustified from the
retained codes alone. No new observations are merged into the C2 quality results.

The existing plan records this budget blocker; Journey is updated locally only.
This is an evidence/documentation publication, not a repair or diagnostic success.
Stop for David's decision on the reservation. No replacement code, rule loosening,
main merge, Block 1 closure or additional live work is proposed or performed.
