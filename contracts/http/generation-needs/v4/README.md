# C1 temporal answer-use envelope

Request/response envelope 4 adds application-owned `temporal_targets` to needs and
result 4 adds `temporal_uses`. Existing v2/v3 envelopes remain readable. Planner v4
is unchanged. Only a production-matched single family with a recognised explicit
calendar reference enters C1; topic discovery and scope facts are not expanded.

The approved post-55f4ed6 extension recognises ISO YYYY-MM-DD, English D Month YYYY,
English Month YYYY and standalone four-digit years in grounded question/context
spans. Full month names are case-insensitive. Identifier/numeric punctuation and
adjacent duration units are excluded; larger dates consume their nested month/year.
New whole-year syntax is 1000–9998; month syntax uses four-digit years without a
leading zero and requires a representable exclusive end (December 9999 excluded).
This is syntactic inventory, not semantic date-role interpretation. It does not
infer event dates, abbreviated months, ambiguous numeric dates, intervals or
anaphoric dates. Existing relative arithmetic remains unchanged.

Reference metadata adds precision, value, period_start and period_end (optional for
historical day-only readers). Month/year references have at=null and whole-period
half-open bounds, never an invented exact date. The existing period/authority
resolver must establish one eligible version for the whole interval; transition,
partial and missing coverage remain unavailable with the resolver's reason.
Python and PHP validate whole-period version coverage before accepting answer use.
A target contains its need/family, exact question reference (for dates),
evaluated point, version IDs, inclusive start/exclusive end, requested/precautionary
purpose and searched/empty/ineligible/unsearched/capacity-limited state. Dates are
inventoried independently of planner authority/fact labels. At most one distinct
alternative search per need, within the existing four lanes and five final chunks.
Existing topical discovery accounting is unchanged.

Generation selects a target per answer part of affected needs, with an exact
question quote. Application validation requires need ownership, target identity,
cited version and interval coverage. A current target cannot cite a historical
alternative. The chosen dated reference must occur in its answer-use quote.
Meaning remains model-dependent: a valid quote is not proof that history was asked.
Version/time labels and planner disagreement survive persisted reload; unavailable
targets receive explicit incomplete-evidence disclosure, not claims of absence.

Profile: `generation-result-v4`, `grounded-generation-temporal-v2`,
`openai-responses-temporal-v2`. The v4 schema adds optional precision/bounds and
nullable reference.at; deploy both services together. Existing temporal_context
JSON holds the metadata: no additional migration. No new model call or retry. The active general
interpretation check returns v2 receipts; historical v3 readers/fixtures remain.
Deploy producer/consumer together, with the nullable temporal_context migration.
Old answers are not backfilled or reinterpreted.
