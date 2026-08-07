# Session Journal: R16-S04 — Implement Semantic Retrieval

## Date

2026-08-07

## What was implemented

The platform can now execute ADR-0018's semantic-retrieval path while preserving
ADR-0017's temporal-authority model. Laravel establishes what the authenticated
user may search, Python plans the temporal intent, Laravel resolves the eligible
document versions deterministically, and Python performs only the resulting
scoped vector search. Laravel then hydrates authoritative content from PostgreSQL
and checks eligibility again before exposing evidence.

The implementation supports CURRENT, VALID_AT_DATE and independently labelled
COMPARE retrieval, together with a deliberate CLARIFICATION_REQUIRED
short-circuit. Exact and aliased organisational locations, ancestor
applicability, version lineage and authority-at-date are resolved from Laravel's
authoritative domain model rather than guessed by the planner or filtered from
Qdrant metadata.

Laravel and Python communicate through the new purpose-scoped `rc1` protocol.
Its HMAC canonicalisation, signed request identity, freshness and replay defence
are shared and independently tested across both languages. Python remains unable
to access PostgreSQL; Qdrant remains behind `VectorStore`; and every search is
explicitly bounded by workspace, active corpus generation and eligible document
identity.

## Verification evidence

* Repository formatting, lint and type checks passed.
* Laravel passed 177 tests with 736 assertions.
* Python passed 209 tests; one credential-dependent live test was skipped as
  designed.
* Next.js passed 26 tests across seven files.
* Focused cross-language tests covered canonicalisation, replay and purpose
  isolation, planning, temporal/applicability eligibility, comparison retrieval,
  profile compatibility, vector filtering, hydration, final eligibility recheck,
  empty results, bounded retries and tenant isolation.
* Docker Compose validation, service health, route registration, contract JSON
  parsing and `git diff --check` passed.

## Problems and corrections

Focused tests exposed a collection callback-signature mismatch and an initially
incorrect stale-candidate outcome. The callback was made explicit, and candidates
that become ineligible between search and hydration now produce the truthful
final `NO_RETRIEVAL_CANDIDATES` outcome when none remain. Empty-search and
operational-failure tests were also separated after discovering that replacing an
HTTP fake within one test did not replace its first callback.

## Architectural boundary held

ADR-0017 and ADR-0018 were implemented without amendment. The planner cannot
authorise or resolve document identities, Python cannot access PostgreSQL,
Laravel remains authoritative for canonical evidence and eligibility, and raw
questions or content are excluded from telemetry. Evaluation, quality gates,
hybrid retrieval, reranking, answer generation and citations remain later-stage
work.

## Commit boundary

Approved commit: `Implement semantic document retrieval`

Approved annotated tag: `phase-16-s04`
