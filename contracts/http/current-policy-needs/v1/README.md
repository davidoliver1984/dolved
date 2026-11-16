# Current-policy needs v1

Signed retrieval.plan-needs protocol, request envelope version 3, server-owned product_profile=current-policy-pool-v1. Legacy envelope 2/plan v4 is unchanged. Python producer and Laravel consumer share the plan/response schemas and examples in this directory.

Planner identity: current-policy-needs-v1 / current-policy-scope-v1 / structured-current-policy-v1. Two total planner attempts, same interpretation check, no additional model call. Provider schema excludes application-owned scope_error; response schema includes its closed quarantine reasons. Base validation always precedes local recovery.

Scope partitions are conditional, four exact spans per side, at most 8,000 characters combined. Duplicate, overlapping, ungrounded and ambiguous-occurrence spans are rejected. Repeated occurrences of an identical clause require a uniquely identifying quotation; no guessed offsets. Quarantined and dependent needs never execute. Current-only needs require current authority. Mixed projection removes historical authority only for the explicitly admitted current clauses and preserves applicability/fact/claim restrictions.

History, ambiguity and interpretation failure are separate typed dispositions. Scope decline wording is owned by Laravel. Event-record information is in scope; current document status does not establish latest-event status.

The Step 1 profile is default-off and deliberately refuses conversation execution until downstream pooling/generation/persistence is implemented. This is not a live product gate pass.
