# Bounded planner-contract DIAGNOSTIC — 14 November 2026

**Complete; no fix. Block 1 HOLD.** This is
`R28-PLANNER-CONTRACT-DIAGNOSTIC-20261114-01`, not a continuation, correction or
replacement of the C2 checkpoint. The earlier USD 0.25 pre-dispatch blocker remains
preserved in the parent directory as historical evidence; David raised this
diagnostic's ceiling to USD 10. These observations must not enter C2 quality scores.

## Boundary and verification

- Execution HEAD `13b7e09bb9cad2b5c6cb0aff600efeb8b217cdf4`, hold branch only.
- Runtime before/between/after items:
  `7f20f123cb9e4af9567e0ea0240ebf715d54755ae2611dc66ecd6719756773af`.
- Main unchanged at `348f09e053c8ad73676921c5c616cced812111d7`.
- Released question-only SHA-256:
  `7603fc82fc2f95f9852f584f28cc6a3d4949a2a30e17b8ab3b56a52ce4d2899f`.
- Final pre-dispatch governed manifest SHA-256:
  `8b8e8dab2c61e6416c36a11fe072ca26905bf7004d97a38a5ee6b914c265ab71`.
- Only isolated `dolved-r28-s03`; connection `postgres/dolved_e2e_r28_s03`.
  Existing primary actor membership was verified for workspace
  `5323cd6b-8b7a-40f9-98b0-9413c75f643e`. Active corpus: 986 assigned chunks, zero foreign.
  No stack restart, migration, role change, corpus mutation or application change.
- No reviewer/expectation folder contents were read. Original C2 evidence untouched.

The existing injectable `httpx.Client` seam is sufficient. The harness constructs
the production `StructuredChatNeedsPlanner` with the same configured provider,
model, URL, secret, timeout, prompt/schema and attempt ceilings. It attaches request
and response observers only; response bytes are consumed into httpx's normal cache,
not rewritten. Production parsing, canonicalisation, validation, catalogue binding,
question ordering and the intrinsic interpretation check remain unchanged.

The PHP harness verifies existing workspace membership and reads the exact
workspace-scoped, non-tombstoned catalogue query and bounds used by
`RetrievalClient::planNeeds`. Its validated request is passed directly to the
production Python planner in a separate process in the isolated AI container.
This is **not** a full conversation/HTTP-orchestration run: no downstream eligibility,
search, reranking, generation or judging was invoked; no database writes occurred.

Capture retains an explicit projection of parsed needs, diagnostic aspects,
question/context spans, operation, authority, fact_reference, applicability and
bindings. Unknown fields, HTTP headers, credentials, unrestricted exception text
and raw response strings are not retained. Strings/lists are bounded; credential-like
strings are redacted. Projection nulls mean unavailable/absent or null values—not
invented model claims (for example, fact_reference has no `kind` field). Files are
restricted to this diagnostic package, including its private container staging
copy. Process-local logging is disabled; no capture enters application logs or DB.

Provider-free proof: **3 tests plus 2 subtests passed**, with raw JUnit and controlled
captures under `proof/`. `fact_date_precision` and `temporal_relation_grounding`
fixtures have identical request bodies, outcomes, closed feedback and two-attempt
behaviour with capture off/on. First-attempt acceptance and corrected-retry
acceptance are also identical. Foreign workspace capture is refused; unknown keys
and credential-like content are excluded/redacted. No provider calls in this proof.

A staging permission preflight initially stopped before authorisation consumption:
Docker preserved the host owner on the mode-700 package. Only that temporary
package's owner was changed to the isolated AI process user; no application files,
database ownership or controls changed. The first log is retained. After correction,
the exclusive authorisation marker was consumed once and G2 then G3 ran once each.

## Every observed attempt

All four attempts proposed one `retrieve` need, `subject_status=topic`, empty
`family_ids`, ownership `authorised_workspace`. Full spans/bindings are in
`cases/G2.json` and `cases/G3.json`. All four provider completions were HTTP 200 /
finish_reason `stop`; none reached grounding, catalogue validation or interpretation
checking because schema validation failed first.

| Item / attempt | Proposed authority | Proposed fact_reference | Result / classification |
|---|---|---|---|
| G2 / 1 | current; precision none; no value/source | month, value `March 2025`; source `My mother moved into the home in March 2025.` | Rejected `schema / needs.0.fact_reference / fact_date_precision`; **justified**, structural-format rule |
| G2 / 2 | current; precision none; no value/source | Same month/value/source | Same rejection; **justified**, structural-format rule |
| G3 / 1 | date, day, value `2025-07-01`; source `it started on 1 July 2025` | day, value `1 July 2025`; source `it started on 1 July 2025` | Same rejection; **justified**, structural-format rule |
| G3 / 2 | after, day, value `2025-07-01`; source `and it started on 1 July 2025.` | day, value `1 July 2025`; source `it started on 1 July 2025.` | Same rejection; **justified**, structural-format rule |

### Exact responsible check

`apps/ai/app/retrieval/needs_planner.py:731` calls
`EvidenceNeedsPlan.model_validate(...)`. `FactReference.coherent_date`
(`needs.py:174–182`) calls `validate_date_value`. Its full-match rules
(`needs.py:81–87`) require:

- month: `[0-9]{4}-[0-9]{2}`;
- day: `[0-9]{4}-[0-9]{2}-[0-9]{2}`.

Thus the natural-language strings `March 2025` and `1 July 2025` do not satisfy the
declared wire formats, even though they quote meaningful dates. The error is wrapped
as `fact reference date value does not match declared precision`, then mapped by
`NeedsValidationFailure` (`needs_planner.py:330`) to `fact_date_precision`.
The relevant format representations would be `2025-03` and `2025-07-01`; these are
explanations of the existing rule, **not edited plans or a proposed correction**.

Field-only provider-free replay of each captured value through the unchanged
`FactReference` reproduces that exact validation message. `verification.json`
retains the four replay outcomes. No repaired full plan was constructed or executed.

### Feedback, later checks and semantic limits

Both second requests actually received exactly
`{"stage":"schema","field":"needs.0.fact_reference","code":"fact_date_precision"}`,
through the existing bounded feedback path (`needs_planner.py:524–530`). Neither
received its rejected output or an explanatory free-text hint. Both repeated the
invalid natural-language fact value. G3 additionally changed authority from `date`
to `after`; it did not correct the fact-reference representation.

The **observed rejection** is justified in every attempt. That is not a finding
that every other part of each plan is correct, nor that the user question is
ambiguous. G2 distinguishes a current requirement from the mother's move date in
its proposed fields; G3 targets the stated start date rather than the agreement
date. Their full semantic adequacy was not established by schema validation.

G3's original `temporal_relation_grounding` failure **was not reached in this new
run**. In the second captured plan, `after` does not occur in either the authority
source or question span. The frozen later guard at `needs.py:443–452` tests for that
literal relation; the wording `once it started` is different. This is a visible
potential later-check conflict, not a newly observed live grounding rejection.
The original missing rejected payload remains unknown. No inference is made that
its authority/source values matched this new attempt, or that grounding now passes.

No accepted plan occurred. There is no evidence of a fix, no population failure-rate
estimate, and no judgement of any final answer (none was generated).

## Timing, usage and guard

| Item | UTC start → end | Planner attempts | Retained-rate estimate USD |
|---|---|---:|---:|
| G2 | 12:15:07.926695 → 12:15:27.370926 | 2 | 0.01267275 |
| G3 | 12:15:31.956640 → 12:15:59.231530 | 2 | 0.01351975 |
| Total | 46.719 seconds inside planner invocations | 4 | **0.02619250** |

74,674 reported input tokens; 3,762 output tokens. Four observed direct provider
requests; zero interpretation checks actually reached. There are no missing token
receipts in these four calls. Pricing remains the retained uncached USD 0.25/M
input and USD 2/M output estimate, **not billed actuals or a new pricing quote**.

The pre-dispatch USD **3.25 reservation** includes four 25,000-token planner outputs,
four possible 4,096-token intrinsic-check outputs, eight 1 MB input guards with
1,024 input-token framing allowance each, and USD 1 uncertainty, rounded up.
The USD 10 ceiling is a runaway guard. Harness guards permit no more than two
planner and two check requests per item, and no other route/model/limit; they add
no retry. Actual recorded estimate is below the reservation and ceiling. No
infrastructure, authorisation, integrity or budget failure interrupted the live run.

## Disposition

`verification.json` confirms exact inputs, once-only order, scoped requests, four
captured completions and valid ledger chains. Runtime remained frozen through
both items and final verification. `checksums.sha256` covers this separate package.
The original checkpoint and earlier blocked-diagnostic evidence remain unchanged.

Publish evidence and the factual plan note to the hold branch; Journey local only.
Preserve unrelated edits. **No application change, correction, proposed replacement
code, main merge, Block 1 closure or further provider work. Stop for review.**
