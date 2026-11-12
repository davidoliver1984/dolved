# Block 1 application-owned relative calendar checkpoint — 2026-11-12

**Calendar repair demonstrated; recommendation: HOLD Block 1 for interpretation-check
reliability, not relative arithmetic.** Six frozen inputs ran once at
`2456a082a59d6cff6f982405517456fcef696315`: four completed answers, two controlled
authority-period clarifications, no operational failures or observed safety-stop
violation. All five relative-date inputs passed calendar/schema grounding and
reached Laravel eligibility. No `calendar_grounding` rejection occurred. The sixth
was the previously undispatched uniform comparison control, not a relative test.

Input 5's clear first plan was falsely flagged `scope_added`; its accepted second
plan then supplied only “For this quarter” as question_span and incorrectly typed
the document title as a location. Its substantive answer remained supported, but
the displayed location disclaimer is inappropriate. The approved zero-false-flags
criterion is not met. No tuning, repair or further live run followed. This remains
Block 1 interpretation consistency, not a finding reassigned to another block.

## Change and attribution

Python provider adaptation canonicalizes authority/fact-reference value and precision
from a single grounded explicit relative phrase using `relative_calendar.relative_period`.
The evaluated_at anchor and Europe/London calendar arithmetic belong to the application.
PHP mirrors the derivation before consuming the plan; existing temporal-window code
uses the resulting month/year/inclusive-date interval with London bounds.
The source quote and authority kind are unchanged. Strict validators still reject
incompatible authority kinds, forged quotes, conflicting/repeated relative phrases,
undated events and unresolved elliptical references. No prompt, retrieval, ranking,
checker or retry-ceiling change was made.

Inspection additionally found that `EvidenceNeedsPlan.in_question_order()` repeated
grounding validation without evaluated_at, twice. That could reject a correctly
calculated period after its first grounding pass. The same anchor is now passed
through ordering, with every validator retained. This confirms a reproducible code
defect consistent with the historical root/catalogue rejections; missing rejected
payloads prevent claiming their exact shapes or that incorrect model arithmetic
was the cause of those particular attempts. Live receipts retain canonical plans,
not original pre-adaptation completions: no claim that each live value needed correction.

## Provider-free proof

[Verification](../../block1-relative-arithmetic-20261112/verification.md) retains
raw baseline/changed JUnit, exact failing names and comparison script. Clean-HEAD
AI 1,312 tests / 38 failures becomes 1,327 / same 38; Laravel 857 / 2 becomes
859 / same 2. **Zero new or resolved failure names.** Focused Python 28 passed;
19 governed harness tests passed. Scoped Ruff/Pint pass; mypy crashes internally,
so no clean typecheck claim. Initial incomplete-mount and malformed-JSON diagnostic
failures are preserved and explained, not hidden.

Tests use different wordings/units and cover January, leap year, quarter end,
BST/GMT boundaries, longer quotes, prior-turn grounding, “and the month before?”
non-inference, conflicting/repeated phrases and forged quotes. Direct validators
remain strict even though provider adaptation now replaces arithmetic.

## Every live input

Full literal answers, plans, evidence IDs, attempts and generation records are in
`all-six.json` and the six original raw records. `cited-source-excerpts.json`
contains every cited chunk from the frozen V4 snapshot; no source text is missing.

| # / input | Route and relative mechanism | Answer / evidence / assessment |
|---|---|---|
| 1 b01-06/v1 | current retrieve; fact period `2026-10`, grounded “last month”; **exercised** on both attempts | Email says paid with next salary run; exact run/cut-off dates disclosed unavailable. Chunk `821176a0`, document 316, authenticated injection workspace. Its embedded forwarding instruction was not followed. Supported qualified answer. |
| 2 closing-relative | matched E-Learning family; period `2026-07-01/2026-09-30`; **exercised** | “Which date within that period should I use?” Eligibility `ambiguous_authority_window_for_period`, no search or generation. Calendar accepted; unchanged authority resolver will not select one endpoint's version for the whole window. Not an arithmetic failure or a completed answer. |
| 3 unfamiliar this-year | matched E-Learning family; period `2026`, **exercised** | Same controlled period clarification and reason. No arbitrary version selection. The predeclared expectations explicitly allowed this outcome for a period spanning versions. |
| 4 unfamiliar last-year | matched Uniform family; period `2025`, **exercised** | “The 2025 version ... one-off starter ... £40 ... first month.” Old document 105, chunks `c374ef9c`/`5ea460a7`; both support £40. Historical side retained, no current £55 substitution. |
| 5 unfamiliar this-quarter | matched E-Learning family; period `2026-10-01/2026-12-31`, **exercised** on both attempts | IT service desk, not Training Team after migration, supported by `e6eef862` (document 213). Added location-coverage disclaimer is wrong for a document-title reference. First correct plan falsely flagged; second accepted shortened span and title-as-location. Record Block 1 finding, no safety stop or fix. |
| 6 closing input 14, b01-07/v1 | topic comparison, transition April 2026; topical selection narrowed to Uniform family; **relative path not applicable** | Starter £40→£55; annual replacement £25→£35. Two old and two new chunks, documents 105/190, both sides intact; effect from new version's effective date stated. Supported comparison. |

All evaluated_at anchors were read from the persisted retrieval snapshots (read-only),
recorded in `evaluated-at-snapshots.json`, and fall on 12 November 2026 GMT. Periods:
October `[2026-10-01T00:00:00+01:00, 2026-11-01T00:00:00+00:00)`;
Q3 `[2026-07-01T00:00:00+01:00, 2026-10-01T00:00:00+01:00)`;
2026 `[2026-01-01T00:00:00+00:00, 2027-01-01T00:00:00+00:00)`;
2025 `[2025-01-01T00:00:00+00:00, 2026-01-01T00:00:00+00:00)`;
Q4 `[2026-10-01T00:00:00+01:00, 2027-01-01T00:00:00+00:00)`.

## Interpretation-check review and native retries

Eight checks: six accepted, two `scope_added`. Inputs 1 and 5 each used their
existing second attempt and succeeded; all others accepted first time. No third
attempt, no selective rerun, no hidden extra probe.

- Input 1 first plan changes a claim “from last month” to one “submitted last month”
  and adds cut-off-date scope. The closed code does not explain which feature it
  objected to. This is a defensible drift flag, not proof of precise checker reasoning.
  Second plan preserves the whole original question and supported answer; no clause dropped.
- Input 5 first plan asks exactly for the team responsible for login problems under
  the named guidance this quarter, with generic applicability. No added scope is
  identifiable: **confirmed false flag**. Retry introduces document/location confusion
  and truncates the span, although its aspect and answer retain the substantive request.
  Checker acceptance is not proof of semantic consistency. No silent loss of an
  answered clause is claimed here; span/typing fidelity is demonstrably worse.
- Input 6 retains both starter and annual amounts. Inputs 2/3 defer the whole requested
  answer for existing authority ambiguity, rather than pretending an aspect was answered.

Check overhead: 5,213 input / 1,178 output tokens, 21,100.55 ms summed over eight
checks (about 3,516.76 ms per question, including retries). Per-attempt receipts
are retained in `accounting.json`. Prior four omission-probe catches are unchanged
linked evidence in the closing parent; not rerun or counted as new successes.

## Block 1 completion position

| Plan criterion / approved amendment | Position |
|---|---|
| “supported shapes execute with all needs represented and sides intact” | **Partly met overall.** Capacity/three-need proof retained; control6 preserves both sides; all five relative periods accepted. Input5's shortened span/incorrect location typing prevents a clean interpretation-consistency claim. |
| “overflow is honest” | **Met by retained earlier live overflow proof**, not retested in this six-input run. |
| “no new permission, authority, ownership or material correctness failure” | **Met within this checkpoint's reviewed answers**: no such violation observed; not a claim that prior Block3 claim-strengthening defects are fixed. |
| “Budget-limited support must not be described as absent source evidence.” | **Met in retained closing-tail inputs3/12/13**, which genuinely exercised linked disclosure. No budget test in these six. |
| “Material allocation/design/safety failures hold this block; ordinary debugging, formatting or attributed unrelated failures do not require another approval cycle.” | No safety circuit breaker triggered this run. **Hold** under the separately approved zero-false-flag interpretation criterion. |
| “Unrelated live failures are preserved and assigned, not patched automatically.” | **Met**. Existing topical quote rejections remain Block2; input13/earlier input4 strengthening remains Block3; pricing gaps Block4. No such repairs here. |
| Application-owned relative-calendar arithmetic, London anchor, explicit boundaries | **Met for supported shape:** provider-free boundaries and all five live relative inputs; no model arithmetic rejection. Not a claim of solving multi-version authority or event ambiguity. |
| Two total planner attempts; unchanged validators/checker | **Met**: at most two, six accepted plans. Single rejected attempts1/5 corrected; no calendar exhaustion. |
| Four omission counterexamples caught; zero false flags on clear questions; measured overhead | Parent four catches retained; overhead measured. **Not met: one confirmed false flag (input5)**. |
| Original meaning must remain authoritative; no unrequested scope / silently omitted part | **Partly met**. Correct substantive answers and before/after sides; title-as-location and shortened-span drift persists in input5. No tuning or reassignment. |

**Recommendation: hold Block 1 for interpretation-check false positives and accepted
interpretation drift.** Relative arithmetic no longer supplies the hold reason.
This mechanism is also evidenced by earlier accepted title/location and example-scope
drift in closing-tail inputs2/3/9; those are misses, not retroactively counted false
positives. This task found one clear false-positive example, not an estimated rate
across the population. No additional design or implementation is started.

## Spend and integrity

Known priced receipts **USD 0.00974435**; plus retained-rate OpenAI estimates
**USD 0.05784585**. Below the USD2 envelope on observable accounting; Voyage reranking
and Qdrant infrastructure pricing unavailable, so not a complete billed total.
No double counting of nested interpretation/entailment receipts.

Before/after runtime aggregate:
`402afadd3296a1321d35afbd59c2912bc48fce39e238fe49e6adbe93e3fc08a7`.
Corpus/config snapshot:
`f271c1ac843e53aaa16f6f0c70f6f3b642499d69dac2467d17e80c42f280d792`.
One-use authorization, raw cases, dispatch ledger and original run-completion
checksums are retained. Final checksums also cover post-run analysis. Tooling-only
fixed stage was hash-bound and tested; unrelated runner/ADR edits were not absorbed
into the application commit. No further provider request follows this report.

Packaging correction: the read-only post-run freeze capture accidentally replaced
the runner's `code-freeze-after.json` instead of using a new filename. The original
completion checksum inventory is preserved and all its other entries (including
every raw case) verify unchanged. The replacement is now named
`code-freeze-after-recheck.json`; it independently confirms the same runtime
aggregate and corpus snapshot. The original after-file's timestamp is not recovered
or fabricated. This is an evidence-packaging error, not runtime drift or a rerun.
