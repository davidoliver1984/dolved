# R01P dated-record and draft-retrieval diagnosis

Date: 2026-09-07

Session: R28-S04 / R01P

Repository HEAD during diagnosis: `2849398ac425801c77217f2a678d80c93d69143b`
Runtime-source aggregate: `915361f671c95b4af9a2529021dbd955f96d358ad8c41022957cb74ac5c10056`

This is a bounded diagnosis and regression record, not R02 and not a correctness pass-rate claim.

## Item 1 — self-dated point-in-time record

The two original clarifications had a shared upstream classification defect but stopped at different points:

- `g03` produced a planner `plan` whose only need used `authority.kind = previous` for the value `January medication audit`. Laravel consequently mapped the request to `historical_reference`; eligibility returned `historical_reference_unresolved`.
- `g30` was stopped directly by the planner with a clarification asking which January/year was intended.

The target is not a versioned policy. The isolated workspace contains one indexed, approved document in the family `Medication Audit Outcome Memo — Willow Bank`: public document ID `5d8bbdf2-4543-4aaa-953e-40a3ac1e4f41`, effective date `2026-01-28`, source `medication-audit-memo-willowbank.docx`. Its title and content identify January 2026 as the event/record date.

The raw v11 plans identify the remaining grammatical distinction. In g03, the month is a pre-nominal modifier inside the definite event phrase `the January medication audit at Willow Bank`; the model treated that whole phrase as a named older selector and reused the whole question as applicability. In g30, `at Willow Bank` is a clean location complement and `in January` is a trailing event-time adjunct; the model emitted current authority and the exact `Willow Bank` site span. This is directly evidenced output comparison; it does not establish a deterministic linguistic rule beyond these observed structures.

The final bounded correction updates the needs-planner instruction so a named point-in-time memo, minutes or audit-outcome record uses current authority when a pre-nominal or trailing month/year identifies the record or event. It requires the applicability source to be the smallest exact organisational-site phrase, excluding the record title, date, action and surrounding question. It explicitly excludes policies, procedures, forms and other versioned authority. Prompt identity advances to `bounded-evidence-needs-v12`; the Laravel execution binding advances to v12.

Provider-free proof covers three differently structured self-dated records and both sides of the safety boundary: each point-in-time record remains `current` with only its exact location span, while a request for the policy in force during a historical period remains historical. The pre-existing multi-candidate and undesignated-location cases continue to fail closed through Laravel rather than being widened.

The v12 live regression completed cleanly. g03 emitted `authority.kind = current`, exact applicability `Willow Bank`, retrieved the intended memo as its only evidence item and answered. g30 retained current authority and exact Willow Bank applicability and answered. The two unrelated controls also answered. No Laravel eligibility rule was weakened.

## Item 2 — draft and informal records

The target documents are present and indexed but have `governance_status = draft`:

- `v4.draft.car-parking-proposal`: document `046d7aa3-8c6b-4515-8d4a-48a76e46ea32`, runtime family `79d0c350-b864-4990-8e91-25f3c5cfc392`, title `Staff Car Parking Allocation — Proposal`.
- `v4.draft.anonymous-suggestion-box-note`: document `540ea6dd-4e02-4d2e-812e-d2e75a87eee8`, runtime family `50f44fbc-ebbe-4ad6-b8f7-c91ec8c64af2`, title `Anonymous Suggestion — Break Room Coffee Machine`.

Neither document ID appears in the persisted `g08` or `g10` records. Both questions had topic-level planner bindings and broad eligible-document sets, but Laravel's governance eligibility excluded these two draft documents before dense or sparse search. They therefore had no dense, sparse, fused or reranker rank/score and never entered final evidence selection.

This is not evidence of a short-document chunking, embedding or ranking defect. Including draft/unofficial content in ordinary answers would change the product's authority/governance semantics and could let unapproved material be treated as operational truth. No retrieval boost or other code change was made. The pattern remains an explicit held-open product decision: if users must discover draft/informal records, that needs an intentional non-authoritative discovery mode and clear answer treatment rather than silently widening the current-authority evidence set.

## Provider-free verification

- Focused Python needs-planner suite: 68 passed.
- Full relevant Python suites: 269 passed.
- Full relevant Laravel suites: 212 passed / 875 assertions; three opt-in cross-service cases skipped because their bridge was not running.
- Ruff lint: passed.
- Ruff formatting: passed (two files formatted).
- Scoped mypy: passed.
- Pint: passed for five changed PHP files.
- `git diff --check`: passed.
- No stale active v10 execution binding remains in application/tests.

## Bounded live regression

Evidence root: `/tmp/dolved-memo-regression-v12-run/`

Source/input freeze: `/tmp/dolved-memo-regression-v12-input/code-freeze.json`

Exact input SHA-256: `da9535a15e28eb261efda9990ae592a03ce3937ac3f55672be87f80b5d2ad35b`

Each authorised case ran exactly once. No Item 2 case was run because Item 2 produced no fix.

| Case | Outcome | Detail |
| --- | --- | --- |
| g03 | completed / answered | v12 emitted current authority and exact `Willow Bank`; the single selected memo reported 98% MAR completion, one correctly recorded declined dose rather than an omission, and no formal action |
| g30 | completed / answered | Retained current authority and exact `Willow Bank`; answer reported 98% MAR completion, meeting the policy target, with no formal action |
| g06 control | completed / answered | Used continence products correctly routed as clinical rather than general waste |
| g28 control | completed / answered | Oakfield hoist inspections correctly reported overdue, with the six-monthly inspection context |

Accounting: 16 physical/internal provider requests (four planning, four search, four reranking and four generation), zero retries, 82.809996 seconds, configured-rate estimated cost USD 0.04197304. The run stayed within the standing USD 5 / 90-minute envelope. No re-embedding, index/model/threshold change or AWS access occurred.

## Commit boundary and next action

The seven-file v12 runtime/test correction, this report and the required plan/tracker state form one coherent local checkpoint. They are committed together; no push is authorised. Unrelated untracked files remain preserved.

R28-S04 and R01P remain `in_progress`. Item 2 is closed as deliberate governance behaviour; any explicit draft-discovery mode is a later David-owned product decision. Do not begin R02 or select another question set. Next action is the next independently authored confirmation after this checkpoint.
