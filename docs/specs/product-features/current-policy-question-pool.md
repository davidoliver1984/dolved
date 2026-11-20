# Current-Policy Question-Pool Conversation Profile

## Status

Accepted by David, 16 November 2026, in the conditional Stop 1 acceptance of commit `912dc8dd9661210f106c47e8ee9d771c4c5ae05c`. Stop 1 requires affected-part disclosure and relative-calendar corrections; architecture acceptance does not waive those corrections or authorise live execution. Previously Proposed on 16 November 2026 for Stop 1 review. The original decision gate remains FAILED and its repair programme STOPPED. This is a separate product direction. Adapted from the Phase 0 draft; its six-unit/new-storage recommendations are superseded by the approved clarification.

## Date

2026-11-16

## Context

Development experiments suggest useful question-level pooling but do not establish production reliability. Document authority does not fully express requested information scope. A current document may describe history, and a single need may contain both current and historical requests. Presentation currently has several competing projections of persisted answer parts and notices.

Preserve ADR-0002 service responsibilities, ADR-0005 authentication, ADR-0006 tenancy, ADR-0017 authority, ADR-0021 ranking, ADR-0024 durable conversations and ADR-0039 scope facts. This establishes profile-scoped exceptions to ADR-0018/0022/0038 interpretation and ADR-0023 generation ownership/presentation, not global removal of legacy paths.

## Decision

Server-select and pin `current-policy-pool-v1` for configured workspaces. Request JSON cannot select it. Keep legacy readers and in-flight identities. Default is off; Stop 1 explicitly refuses execution of the incomplete product pipeline rather than falling into legacy retrieval/generation.

Each existing need gains requested_information: current_policy/history/mixed/unclear, independently of source authority. Only mixed needs carry grounded current/history partitions: at most four spans per side, 8,000 total quoted characters, no duplicate or overlap, no silent truncation. Existing three-need and attempt limits remain. No new model call. Scope and semantic completeness remain model judgments; substring checks prove provenance only.

Validate the whole base envelope, references, grounding and catalogue before local recovery. The first invalid scope annotation uses the existing second attempt. On that attempt, independently invalid scope annotations and their dependent needs are quarantined with typed interpretation failure, never converted wholesale to current. Broken base/security/authority/integrity validation remains request-fatal. A clearly expressed mixed need projects only current clauses and retains a fixed history decline; clarification means real ambiguity, not bad decomposition. Preserve independently answerable neighbours.

History includes past-policy states, introductions, change explanations and comparisons, even if described in a current source. Current questions can mention dates/events. Authorised event records are in scope: identify record/date, do not imply ongoing conditions, and call a record latest only with evidence of recency. Scope decline is application-owned and never described as missing evidence.

The complete path will use current eligibility and applicability, unchanged ranking/thresholds, first five qualified passages per admitted need, physical deduplication, at most 15 passages/60,000 source characters. No coverage selector, comparison routing or historical-alternative discovery. One simplified generation request, no semantic support-review/entailment call or per-part need/side ownership gate. Retain tenant, source-integrity, citation-membership checks and untrusted-document instructions. Citation membership does not prove support. Removing semantic review is an explicitly accepted risk subject to independent validation.

Compose final text once after citation mapping and save it transactionally in existing Message.display_text with GeneratedAnswer/AnswerPart/EvidenceSnapshot associations. Use existing JSON for versioned dispositions. No storage migration, generic block-document architecture or six-unit representation. API, completion SSE and reload consume that saved result; web renders it once. Historical records are not rewritten. Readers precede producers. Stops 2 and 3 deliver these downstream pieces separately.

## Alternatives considered

The approved clarification rejects six universal requested units and a new response table as unnecessary. One indivisible scope label per need cannot handle a mixed need; conditional spans address that specific representational gap. Global legacy deletion and a Desktop experiment stub are rejected. Another classifier/reviewer model is not authorised. Reinstating fabricated answer ownership links would undo question-level pooling.

## Consequences

Coordinated Python/PHP schemas, validation, profile pins and tests are required. Date-bearing current questions must not be declined automatically. Partial execution must not weaken applicability or source authority. Malformed scope is distinct from genuine ambiguity, absence of evidence, capacity and execution failure. Existing model judgments can still be wrong; no provider-free test establishes semantic reliability.

No provider calls, rollout or pilot readiness is authorised by implementation. Frozen acceptance remains: 42 fresh answerable questions, at least 40 complete, at most two honestly incomplete and zero materially wrong; all 20 controls and six regressions separately pass, with actual web/reload evidence. These future gates do not reinterpret the original failure.

## Implementation record — Stop 2

The 16 November backend increment supplies the callable current-policy service, signed generation contract and existing-message transaction. It also corrects Stop 1 affected-part disclosure and PHP relative-calendar canonicalisation. See `contracts/http/current-policy-answer/v1/README.md` for saved-text/citation and typed-outcome boundaries. Delivery remains blocked until Stop 3 integrates saved text; no presentation parity or live semantic correctness is claimed. A dated-current request mislabelled historical remains an explicit quality risk, not grounds to relabel authority or weaken validation.
