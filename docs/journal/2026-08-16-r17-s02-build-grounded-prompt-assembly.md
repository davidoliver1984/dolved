# Session Journal: R17-S02 Build Grounded Prompt Assembly

## Date

2026-08-16

## Completion date

2026-08-17

## Status

Completed, reviewed and accepted for the R17-S02 commit boundary. The tracker
has advanced to R17-S03; no R17-S03 implementation or provider execution is
included in this stage.

## What was implemented

R17-S02 establishes ADR-0023's provider-neutral grounded-generation boundary.
The shared `rc1` contract now carries a canonical `GenerationRequest` and one
of three mutually exclusive response shapes: a completed `GenerationResult`,
`GENERATION_CONTEXT_BUDGET_EXCEEDED`, or a typed provider error.

Laravel assembles requests only from final authorised retrieval evidence and
the temporal/applicability facts already resolved upstream. It assigns
request-scoped `ev-NN` handles, preserves final-evidence order and whole chunk
text, and applies a deterministic character-unit packaging policy. The policy
reserves at least one evidence item for every required side before considering
optional evidence. If that structural minimum cannot fit, it fails explicitly;
it never drops a COMPARE side or reports semantic insufficiency.

`GenerationResult` has no parallel free-text answer. Its authoritative content
is `answer_parts[]`, each with one or more request-scoped evidence handles.
Laravel validates the outcome invariants and citation membership before a
transaction persists the answer.

Persistence now records one application-owned `GeneratedAnswer`, ordered
application-owned `AnswerPart`s, and one per-answer `EvidenceSnapshot` for each
cited evidence handle. A snapshot stores `cited_text_verbatim`, its SHA-256,
source provenance, and the real `document_chunk_id`, `document_id`, and
`ingestion_event_claim_id`. Multiple parts reuse the same snapshot.

The generation fingerprint mechanism uses canonical JSON and a separately
stored scheme version. Concrete OpenAI, model, prompt, adapter and sampling
values remain R17-S03 inputs; R17-S02 only supplies the versioned mechanism and
persistence fields.

Python now has immutable provider-neutral request/result models, outcome and
envelope invariants, citation validation, a `Generator` protocol, typed boundary
failures, and a deterministic test double. The default runtime dependency fails
closed with a typed provider-error envelope because no concrete provider adapter
exists yet.

The final pre-commit review aligned the persisted field name exactly with
ADR-0023 (`cited_text_verbatim`), added explicit transactional rollback proof,
completed the three-way response-union rejection matrix, and aligned Laravel's
8,000-character `insufficiency_reason` bound with the shared/Python contract.

## Security and ownership

Laravel rehydrates every candidate within the authorised workspace and verifies
the canonical chunk/document/ingestion identity and exact text before creating
a request. It repeats those checks transactionally before snapshot persistence.
Provider-created durable IDs and citations outside the request are rejected.
No retrieval, authority, applicability, threshold, calibration or benchmark
behaviour changed.

## Deliberately deferred

R17-S03 still owns provider-specific rendering, prompt wording/version, OpenAI
and gpt-5-mini integration, provider token measurement, retry/failure mapping,
and live verification. No generation provider was called in this session.

## Verification

Provider-free verification completed:

- focused Python generation/rc1/authentication contracts: 12 passed;
- focused Laravel contract, migration, persistence and tenant checks: 13 passed
  (47 assertions);
- application-wide Python Ruff and Ruff formatting: passed (121 files);
- application-wide Python Mypy plus the new generation tests: passed (121
  source files);
- repository-wide Pint: passed (301 files);
- JSON parsing: all 51 contract JSON files passed;
- `git diff --check`: passed.

The complete historical test commands were also attempted from isolated
one-off containers. They do not form a green baseline in this checkout: the
Python suite requires broad `/evaluation` fixtures (including protected split
material deliberately not mounted for this task) and repository-wide Mypy
contains ten pre-existing errors across two test files; the Laravel suite
reached 227 passes but retained 43
environment/lineage failures including absent S3 test mapping and a pre-existing
V3 provisioning-definition/constant mismatch. None of those failures executes
the new generation boundary, and the focused generation plus application-wide
static checks above are clean. No protected split was mounted to force a green
result.
