# Temporal association correction and production-path diagnostic — 2026-11-14

**Completed bounded package; Block 1 HOLD. Hold branch only; no readiness claim.**
Starting commit `02470b24a23ed3001faebe41d9cf94c9c4818180`.

## Reproduction and correction

Before editing, controlled two-need/same-version/shared-paragraph reproductions
returned Python `ValueError: temporal answer-use coverage mismatch` and PHP
`GenerationException: Temporal answer-use coverage is invalid.` The old expected
map overwrites a need at the same part index and forbids repeated indices.
Additionally, whole-paragraph citation ownership prevents independently owned
evidence handles from supporting their respective associations. This confirms a
structural defect, not the contents of item 29's unavailable rejected drafts.

Generation v5 makes each `(part_index, need_id)` explicit, with its own
`evidence_ids`. Every association checks its own grounded quote, target, family,
version, full interval and evidence ownership; the union accounts for all cited
handles. Missing, duplicate, borrowed, unassigned or invalid temporal associations
fail closed. Non-temporal ownership and single-need behaviour remain intact.
The existing entailment call receives all associations; no extra provider stage
or retry was added. PHP and Python versions advance together, including generation
profile/persistence checks. Existing JSON persistence carries v2 association
receipts; rendering displays every association. Historical v1 receipts and the
Python v4 reader remain available without rewriting historical evidence.

## Evaluation-only capture

The authenticated planner/generation routes establish a request-local capture
scope only when explicitly enabled for workspace
`5323cd6b-8b7a-40f9-98b0-9413c75f643e`. The configured destination must be under the
operator-mounted `/evaluation-evidence`; no caller supplies a path. Files use
exclusive creation, bounded allowlisted data and redaction, not raw provider
bodies, headers or arbitrary exception text. Nothing is logged or persisted in
the application database. Other workspaces, disabled settings, outside paths and
symlinks cannot capture. I/O failure does not change the application decision;
the diagnostic preflight and reconciliation must detect missing evidence.

Rejected planner records include original proposed fields and the actual
post-normalisation validator input where available, closed validation details,
and the actual calendar-notation conversion count. Accepted planner records retain
the count, not a redundant model body. Rejected generation records include bounded
parts/temporal uses and the exact allowlisted invariant when available. Missing
parsed fields are explicitly unavailable, never inferred. No feedback or validator
condition changes follow from capture.

## Verification and execution boundary

Focused and full-suite JUnit/logs are retained under `provider-free/` (lossless gzip).
Final AI: **1,450 tests, 1,408 passed, 38 failed, 4 skipped**. Final PHP: **930 tests,
2 failures, 9 skipped, 5,652 assertions**. Exact retained failure identities, zero
new and zero resolved. The historically intermittent inode-substitution test
reproduced here; it is not claimed fixed. Focused AI: **76 passed**; copied harness
stop tests: **4 passed**. Scoped Ruff/format/Mypy and PHP style checks pass.
Initial new-fixture failures (an invalid test handle, a fake Eloquent relation,
PHP fixture bracket, and dependency-override default parameters) were corrected
without changing assertions or application outcomes. Initial Laravel runs lacked
test-process memory headroom, stateful test origins or the retained versioned test
bucket; their logs/JUnit are preserved separately. Final tests use SQLite memory,
the existing isolated `dolved-r28-s03-c1-period-tests-20261114` bucket and 512 MB
test-process memory. No application memory/provider setting changed.
Diagnostic results are recorded below.
The diagnostic selection is exactly **G3, 17, 25**, copied byte-for-byte from
retained question-only inputs. It is a new observation, never a replacement for
earlier checkpoint verdicts. No other question or reviewer material is needed.

Items **19 and 28 remain unresolved Block 1 findings**, with implementation owned
by Block 2. This package does not mark them passed or close Block 1.

## Frozen production diagnostic

- Execution commit: `181e00a9fe7ef1b2785d3ed79f347868504e0381`.
- Runtime aggregate before/after:
  `fa20a7970da378a3c5aa49e995e1c674e1c26e8254543b869d0337d2d467b5b8`.
- Run: `R28-TEMPORAL-ASSOCIATION-DIAGNOSTIC-20261114-01`; exclusive one-use
  authorisation `R28-TEMPORAL-ASSOCIATION-DIAGNOSTIC-AUTH-20261114-01`.
- Three inputs once, **18:59:55–19:01:36 UTC**, 14 November 2026. No redispatch.
- Reservation **USD12** (stage bound USD6.9375066 plus USD5 uncertainty, rounded
  up), stated before consumption; ceiling **USD50**. No production limit reduced.
- Isolated `dolved-r28-s03`, authorised workspace/actor, fresh persisted conversation
  and one user turn per input, authenticated membership, real production route.
  All 50 migrations already Ran; no migration or corpus mutation was needed.
- Deployment initially stopped before consumption because the previous temporary
  Compose overlay was missing. It was recovered privately from the running
  containers' environment. Comparison proves all environment values preserved
  except the two authorised capture settings. No credentials were printed or
  published. API/AI were recreated together; mounted source hashes verified before
  dispatch and between inputs. Capture was disabled again after completion.

| Input | Planner attempts and route | Displayed outcome |
| --- | --- | --- |
| G3 | Both attempts rejected at grounding/root/temporal_relation_grounding; no retrieval or generation | “I couldn't interpret this question reliably, so I haven't used a guessed interpretation to search or answer it. Please try rephrasing your question.” |
| 17 | First schema rejection; second accepted with `before/2026-01/month`; comparison current-side discovery and family review. Original/effective reviewer decision `no_match`, no validation-rejection code; no final evidence or generation | “I couldn't find enough available evidence to answer that question.” |
| 25 | Both attempts rejected at schema/needs.0.authority/temporal_relation_grounding; no retrieval or generation | Same honest interpretation-failure text as G3 |

The raw records, hash-chained ledgers, persisted/reloaded results and all six
sidecars are included. `diagnostic-analysis.json` reconciles every planner receipt
with its sidecar: **five rejections, one acceptance, none missing**. Every sidecar
belongs to one of these three authenticated requests. All six actual notation
conversion counts are **0**; the model supplied canonical notation directly in
the retained rejected proposals. No live conversion is claimed.

### Each rejected attempt, classified against its actual proposed fields

| Input/attempt | Proposed values and exact failure | Classification |
| --- | --- | --- |
| G3/1 | `after`, `2025-07-01`, `day`; source “and it started on 1 July 2025.”; fact reference the same date. Question span “How often did the kitchen area have to be inspected once it started?” passes date grounding but contains no literal `after` | **mixed** |
| G3/2 | Same authority, source “it started on 1 July 2025.”; fact reference now null. Same question span, still no literal `after` | **mixed** |
| 17/1 | `period`, `2026-01`, `month`, source “before January 2026” | **justified** |
| 25/1 | `period`, `2026-01`, `month`, source “before January 2026” | **justified** |
| 25/2 | Same authority and source; changed context spans did not repair the mismatch | **justified** |

**Exact mechanisms:** `NeedAuthority.coherent_reference()` in `needs.py:144–147`
rejects `period` when the source starts with `before` or `after`. January itself
is not the requested before-January authority: these three rejections are both
structurally and semantically justified. The retained proposed and validator-input
fields are identical, ruling out notation conversion as their cause.

G3 reaches `EvidenceNeedsPlan.validate_grounding()` at `needs.py:443–452`. For a
`before`/`after` authority it searches for that literal word in the authority source
plus the need's question span. The word is absent; the validator operates exactly
as written. But “once it started” can express a meaningful activation-time
relation without that word. Conversely, the typed `after`-a-day selector means
the adjacent authority after the named day, not necessarily the activation instant.
Hence **mixed**, not “the validator is broken” or “the entire model plan is correct”.
The code-level cause is confirmed; the best semantic representation is not decided
by this diagnostic. `analyse.py` checks the real authority model/date grounding
and the exact literal predicate using the retained fields; it does not invent a
complete missing plan, add a provider call or modify a validator.

These are **new observations**, not reconstructions of earlier missing outputs.
The earlier checkpoint verdicts and the three historical effective `no_match`
causes are unchanged. Input 17's new genuine `no_match` does not explain those.

### What was and was not exercised

Production planner rejection capture and actual notation-count capture are proven
on the serving route. Generation rejection capture, v5 shared-paragraph acceptance
and historical receipt rendering are **provider-free verified only** in this
package: none of the three diagnostic questions reached generation. No additional
call was made to force that branch. This is an explicit live-verification limit,
not a pass or an inferred generation result.

### Accounting and integrity

`complete-accounting.json` supplements the frozen harness's top-level accounting
with the available failed-planner attempt receipts (without double-counting input
17's aggregate). **10 provider requests; 124,767 recorded input tokens and 7,047
output tokens**, across stages with different tokenisers. Retained-rate estimated
subtotal **USD0.0436364**, plus **USD0.00032935** conservative reranker allowance.
The reranker has token/attempt receipts but no price identity; the total invoice
cost is unavailable. Estimated subtotal plus allowance is **USD0.04396575**, well
inside the stated reservation; it is not asserted as an invoice total.

`persistence.json` verifies three distinct conversations, one user turn and one run
each, and the displayed/persisted outcomes. `governed-inputs-after.txt`, mounted
checks and runtime checks verify no execution-source drift. `capture-disabled.json`
confirms restoration to default-off. Package-wide `checksums.sha256` and its
`aggregate.sha256` cover the final published evidence. The production source was
not changed after the diagnostic freeze. Main remains
`348f09e053c8ad73676921c5c616cced812111d7`; the three pre-existing tracked edits remain
untouched. Journey updated locally only. No follow-on fix or run is authorised
by completion of this package.
