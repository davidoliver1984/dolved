# C1 temporal answer-use envelope

Request/response envelope 4 adds application-owned `temporal_targets` to needs and
result 4 adds `temporal_uses`. Existing v2/v3 envelopes remain readable. Planner v4
is unchanged. Only a production-matched single family with a recognised explicit
day enters C1; topic discovery and scope facts are not expanded.

The inventory recognises ISO YYYY-MM-DD and English D Month YYYY in grounded need
question/context spans. It does not infer event dates, ambiguous numeric dates,
month/year-only periods, or anaphoric dates. Existing relative arithmetic remains
unchanged. A target contains its need/family, exact question reference (for dates),
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

Profile: `generation-result-v4`, `grounded-generation-temporal-v1`,
`openai-responses-temporal-v1`. No new model call or retry. The active general
interpretation check returns v2 receipts; historical v3 readers/fixtures remain.
Deploy producer/consumer together, with the nullable temporal_context migration.
Old answers are not backfilled or reinterpreted.
