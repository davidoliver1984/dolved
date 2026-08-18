# ADR 0024: Define the Conversation and Streaming Domain

## Status

Accepted

## Date

2026-08-18

## Post-acceptance implementation-readiness clarification

### Clarification date

2026-08-18

### Why this clarification is required

R18-S02 implementation-readiness review found one genuine architectural
gap in the accepted decision: ADR-0018's retrieval contract has eight
controlled `RetrievalResult` outcomes —

```text
EVIDENCE_FOUND
NO_ELIGIBLE_EVIDENCE
NO_RETRIEVAL_CANDIDATES
INSUFFICIENT_EVIDENCE
TEMPORAL_SCOPE_UNRESOLVED
COMPARISON_SCOPE_INCOMPLETE
CLARIFICATION_REQUIRED
RETRIEVAL_FAILED
```

— and the accepted ADR-0024 completely defines only two paths through
them: `EVIDENCE_FOUND` reaching successful grounded generation, and
contextualiser-originated clarification. It does not define how the
remaining six controlled outcomes become a `GenerationRun` transition, a
visible assistant turn (or the deliberate absence of one), an
authoritative persistent record, safe display text, a retryable or
non-retryable result, or a future conversation-history item. Left
unresolved, R18-S02 would have had to invent this mapping during
implementation — exactly what ADR-0024 exists to prevent — and could
easily have collapsed several legitimately distinct controlled retrieval
outcomes into the single, generic `RETRIEVAL_FAILED` failure code, losing
real audit and evaluation precision along the way. This clarification
defines the complete mapping. It does not reopen or revise any previously
accepted ownership, streaming, persistence, or conversation decision.

### Governing handoff rule

Generation runs only when the final, Laravel-owned `RetrievalResult` is
`EVIDENCE_FOUND` with a non-empty, currently authorised final evidence
set. Every other retrieval outcome short-circuits before
`GenerationRequest` assembly, before any call to `generation.stream` or
`generation.answer`, before any provider generation call, and before any
`GeneratedAnswer`, authoritative `AnswerPart`, or `EvidenceSnapshot` is
created for that turn. Laravel owns this handoff, unchanged from ADR-0024's
existing ownership boundary: Python never reclassifies a `RetrievalResult`
and never decides whether generation may proceed — it simply never
receives the request for any outcome other than `EVIDENCE_FOUND`.

### Complete retrieval-outcome-to-conversation mapping

| Retrieval outcome | Generation | `GenerationRun` terminal state | Assistant `Message` |
|---|---|---|---|
| `EVIDENCE_FOUND` | Runs | `COMPLETED` (business outcome per `GeneratedAnswer`) | `GROUNDED_ANSWER` |
| `NO_ELIGIBLE_EVIDENCE` | Does not run | `RETRIEVAL_NO_ANSWER` | `NO_ANSWER` |
| `NO_RETRIEVAL_CANDIDATES` | Does not run | `RETRIEVAL_NO_ANSWER` | `NO_ANSWER` |
| `INSUFFICIENT_EVIDENCE` (retrieval) | Does not run | `RETRIEVAL_NO_ANSWER` | `NO_ANSWER` |
| `COMPARISON_SCOPE_INCOMPLETE` | Does not run | `RETRIEVAL_NO_ANSWER` | `NO_ANSWER` |
| `CLARIFICATION_REQUIRED` (retrieval) | Does not run | `CLARIFICATION_REQUIRED` | `CLARIFICATION` |
| `TEMPORAL_SCOPE_UNRESOLVED` | Does not run | `FAILED` (`RETRIEVAL_SCOPE_UNRESOLVED`) | none |
| `RETRIEVAL_FAILED` | Does not run | `FAILED` | none |

`EVIDENCE_FOUND` is the only retrieval outcome that may ever enter
generation. `NO_ELIGIBLE_EVIDENCE`, `NO_RETRIEVAL_CANDIDATES`, retrieval's
own `INSUFFICIENT_EVIDENCE`, and `COMPARISON_SCOPE_INCOMPLETE` are four
distinct, internally-tracked outcomes that all resolve to the same
`RETRIEVAL_NO_ANSWER` run state and the same `NO_ANSWER` Message kind —
this is a deliberate security/privacy projection, not a loss of internal
precision (see "Security-safe wording policy" below): a workspace member
must never be able to distinguish, from the assistant's response alone,
"nothing matched," "matching material exists but you cannot see it," or
"temporal/applicability rules excluded it," the same `404`-not-`403`
discipline ADR-0006 already requires everywhere else. The user-facing
wording for all four is drawn from one safe, generic family — conceptually
*"I couldn't find enough available evidence to answer that"* for the first
three, and *"I couldn't find enough available evidence to complete both
sides of that comparison"* for `COMPARISON_SCOPE_INCOMPLETE` specifically,
since a comparison's two-sided structure is itself safe to acknowledge
without revealing which side failed or why. Exact copy and localisation
remain R18-S02 implementation detail.

**Retrieval's `INSUFFICIENT_EVIDENCE` and generation's `INSUFFICIENT_EVIDENCE`
are different facts sharing one name, and must never be collapsed into one
lineage or persistence model:**

```text
RetrievalOutcome::INSUFFICIENT_EVIDENCE
  → post-threshold retrieval supplied no final evidence at all
  → the Generator is never called
  → no GenerationRequest, no GeneratedAnswer, no AnswerParts exist
  → conversation-domain result: RETRIEVAL_NO_ANSWER / NO_ANSWER Message

GenerationOutcome::INSUFFICIENT_EVIDENCE
  → retrieval supplied EVIDENCE_FOUND; evidence existed
  → the Generator was called and reasoned over that evidence
  → a validated GeneratedAnswer exists, with a required insufficiency_reason
  → conversation-domain result: COMPLETED / GROUNDED_ANSWER Message,
    display text sourced from insufficiency_reason exactly as ADR-0024
    already defines for this outcome
```

The first means retrieval found nothing to reason over. The second means
generation reasoned over real evidence and honestly reported that the
evidence didn't establish the specific fact asked for. Confusing the two
would misreport a retrieval-stage fact as a generation-stage one, or vice
versa — precisely the kind of collapse this clarification exists to
prevent.

**`TEMPORAL_SCOPE_UNRESOLVED` is a defensive, expected-to-be-rare
*structural* failure (ADR-0018), not an ordinary user-resolvable
ambiguity** — those cases are already represented by
`CLARIFICATION_REQUIRED` with a typed reason. It therefore maps to
`FAILED` (`RETRIEVAL_SCOPE_UNRESOLVED` or an equivalent explicit internal
code), never to a fabricated assistant `Message` and never to
`RETRIEVAL_NO_ANSWER`'s ordinary no-answer treatment. Preserving the
original `RetrievalOutcome::TEMPORAL_SCOPE_UNRESOLVED` in the persisted
retrieval snapshot, rather than rewriting it as generic `RETRIEVAL_FAILED`,
matters because the two have different likely causes and different
operator response — this mapping to a failed conversation run records that
a controlled defensive retrieval stop could not produce a safe
conversational answer, it does not relabel *why*. Retry eligibility for
this and for genuine `RETRIEVAL_FAILED` follows the internal cause; V1
does not promise that repeating an identical request resolves a
deterministic contract or configuration problem.

### Retrieval-originated clarification: a second, distinct source

Retrieval-originated clarification is not the same thing as
contextualiser-originated clarification, and ADR-0024's original text —
which described every `CLARIFICATION` `Message` as sourced from a
contextualisation-result snapshot — is superseded on this specific point
(see "What this clarification supersedes" below). Two genuinely different
sources now exist, both producing the same visible shape:

```text
CONTEXTUALISER
  source:  ContextualisationResultSnapshot (ADR-0024, unchanged)
  content: a bounded, model-generated clarification_question

RETRIEVAL
  source:  RetrievalOutcomeSnapshot (this clarification)
  content: a typed PlannerClarificationReason or
           EligibilityClarificationReason, rendered through a versioned,
           deterministic, application-owned reason-to-question mapping —
           never arbitrary RetrievalResult.reason prose

Both produce:
  Message.role   = ASSISTANT
  Message.kind   = CLARIFICATION
  GenerationRun.status = CLARIFICATION_REQUIRED
```

Their visible shape is identical; their source and lineage remain
explicit and distinguishable in the persisted record. Laravel must not
call the contextualiser a second time merely to phrase a retrieval
clarification it can already represent with a controlled typed reason —
that would be model-generated prose standing in for what a deterministic
mapping already does more safely. The retrieval result representation
must therefore carry, for a `CLARIFICATION_REQUIRED` outcome: the source
(`PLANNER` or `ELIGIBILITY_RESOLVER`); a typed reason; whatever safe
structural metadata the renderer needs; and the relevant retrieval/planner
lineage — Laravel never infers the source or semantic meaning of a
clarification from an uncontrolled string. Reason families already
established by ADR-0018/ADR-0022 include, illustratively: `PLANNER` —
`UNCLASSIFIABLE_TEMPORAL_INTENT`; `ELIGIBILITY_RESOLVER` —
`AMBIGUOUS_AUTHORITY_WINDOW_FOR_PERIOD`,
`UNRESOLVABLE_TEMPORAL_PERIOD`, `AMBIGUOUS_HISTORICAL_REFERENCE`,
`HISTORICAL_REFERENCE_UNRESOLVED`, `UNRESOLVED_LOCATION_REFERENCE`,
`AMBIGUOUS_LOCATION_REFERENCE`, `MULTIPLE_UNRELATED_LOCATION_REFERENCES`.
Exact user-facing templates are R18-S02 implementation detail, but every
template must be deterministic, bounded, versioned, application-owned,
security-safe, free of citations, and structurally incapable of asserting
a factual answer — the same discipline ADR-0024 already requires of
contextualiser-originated clarification text, applied here to a second
source.

### A third `Message.kind`: `NO_ANSWER`

ADR-0024's `Message.kind` set is extended from two values to three:

```text
GROUNDED_ANSWER
CLARIFICATION
NO_ANSWER
```

Roles remain exactly `USER`/`ASSISTANT`, unchanged — `NO_ANSWER` is a
`Message.kind`, not a provider role and not a generation business outcome.
Its invariants:

```text
ASSISTANT + NO_ANSWER
  → originates from exactly one GenerationRun that reached
    RETRIEVAL_NO_ANSWER
  → has no GeneratedAnswer, no AnswerParts, no EvidenceSnapshots, no
    citations
  → has exactly one ControlledAssistantResponse (see below)
  → display text is deterministically derived from the persisted
    RetrievalOutcomeSnapshot, via the versioned controlled-response
    renderer — never from arbitrary prose
```

ADR-0024's own rule that every assistant `Message` has exactly one durably
persisted record explaining its content is extended, not broken, by this
third case:

```text
GROUNDED_ANSWER  → GeneratedAnswer
CLARIFICATION    → Clarification record, sourced from either
                    ContextualisationResultSnapshot or
                    RetrievalOutcomeSnapshot (see above)
NO_ANSWER        → ControlledAssistantResponse, sourced from
                    RetrievalOutcomeSnapshot
```

### Durable `RetrievalOutcomeSnapshot`

Every `GenerationRun` that reaches retrieval durably persists an
application-owned `RetrievalOutcomeSnapshot` (or an equivalent durable
record; the exact table/class name is R18-S02 implementation detail),
containing, as applicable: the exact `RetrievalOutcome`; a typed reason
and reason source, where the outcome is `CLARIFICATION_REQUIRED`;
retrieval/planner/eligibility lineage; correlation identifiers; an
evaluated timestamp; candidate/evidence counts; comparison-side state
where safe and structurally required; safe structured metadata the
deterministic renderer needs; the version of the controlled-response/
clarification renderer used; and a retryability classification where
applicable. It must never contain raw provider reasoning, unauthorised
evidence, uncontrolled provider prose, user-facing wording masquerading
as a typed reason, or cross-workspace identifiers — the same
allowlist-first, minimum-necessary discipline ADR-0012 and ADR-0024
already apply everywhere else in this domain. For `EVIDENCE_FOUND`,
existing retrieval/evidence lineage already recorded elsewhere may satisfy
some or all of this requirement; this clarification does not require
duplicating an authoritative record that already exists. For every
controlled non-generation outcome, the snapshot *is* the authoritative
explanation of why generation did not run.

### `ControlledAssistantResponse`

A small, application-owned, durable response record for deterministic,
non-generated assistant content — or an equivalent structurally enforced
representation; the exact name is implementation detail:

```text
ControlledAssistantResponse
  message_id
  generation_run_id
  retrieval_outcome_snapshot_id
  response_kind: NO_ANSWER
  renderer_version
  rendered_text
  created_at
```

Required invariants: it is not a `GeneratedAnswer` and is not
model-generated; it has no `AnswerPart`s, `EvidenceSnapshot`s, or
citations; its text is selected through a versioned, deterministic
renderer, never composed freely; it is written atomically with the
assistant `Message` and the run's terminal transition, in one atomic
write, exactly as ADR-0024 already requires for the `GROUNDED_ANSWER`
case; it is immutable once written; and it preserves the underlying
internal retrieval outcome even where several outcomes deliberately share
identical public wording. Retrieval-originated `CLARIFICATION` content may
use a sibling `Clarification` record or a common controlled-response shape
capable of distinguishing `CLARIFICATION` from `NO_ANSWER` — either is
acceptable R18-S02 implementation detail, provided neither is ever forced
into `GeneratedAnswer`'s shape.

### Conversation history, extended

A successfully persisted `NO_ANSWER` `Message` is a real, visible
conversational turn, and — like a `CLARIFICATION` turn — is eligible to
enter the bounded three-turn contextualisation window described in
ADR-0024's "Contextualisation" section. It remains context only, never
evidence, exactly as the governing principle already requires of every
other kind of prior turn:

```text
Eligible assistant history:
  GROUNDED_ANSWER  from a run that reached COMPLETED
  CLARIFICATION    from a run that reached CLARIFICATION_REQUIRED
  NO_ANSWER        from a run that reached RETRIEVAL_NO_ANSWER

Ineligible history (unchanged from ADR-0024):
  failed runs; cancelled runs; provisional AnswerPartAcceptedForDisplay
  projections; progress events; operational error projections
```

A subsequent question such as *"why couldn't you compare them?"* may use a
prior `NO_ANSWER` turn to interpret the user's intent, exactly as any
other conversational context does — but any new factual answer still
requires fresh, currently authorised retrieval, unchanged.

### `GenerationRun` lifecycle, extended

The terminal-state model gains one legitimate, controlled terminal state,
`RETRIEVAL_NO_ANSWER`, alongside the existing `COMPLETED`,
`CLARIFICATION_REQUIRED`, `FAILED`, and `CANCELLED`:

```text
EVIDENCE_FOUND
  → generation runs → COMPLETED → GROUNDED_ANSWER Message

contextualiser CLARIFICATION_REQUIRED, or retrieval CLARIFICATION_REQUIRED
  → CLARIFICATION_REQUIRED → CLARIFICATION Message

NO_ELIGIBLE_EVIDENCE, NO_RETRIEVAL_CANDIDATES,
retrieval INSUFFICIENT_EVIDENCE, COMPARISON_SCOPE_INCOMPLETE
  → RETRIEVAL_NO_ANSWER → NO_ANSWER Message

TEMPORAL_SCOPE_UNRESOLVED, RETRIEVAL_FAILED
  → FAILED → no assistant Message
```

`RETRIEVAL_NO_ANSWER` is a legitimate, controlled terminal state, not a
failure — it joins `COMPLETED` and `CLARIFICATION_REQUIRED` as an outcome
the system reached correctly, not one it failed to reach. Retry
eligibility, extended: `FAILED` and `CANCELLED` retain ADR-0024's existing
retry rules, unchanged. `RETRIEVAL_NO_ANSWER` is **not** automatically
failure-retryable — retrying an identical request against the same
authorised evidence set would deterministically reach the same outcome
again; the user asking a revised question is a new turn, not a retry.
`CLARIFICATION_REQUIRED` (from either source) is answered through a new
user turn, never through Retry, unchanged. `COMPLETED` remains not
failure-retryable, unchanged.

### Browser events, extended

The Laravel-to-browser `ChatStreamEvent` family gains one explicit
controlled-completion event, alongside `AnswerCompleted`,
`ClarificationRequired`, `RunFailed`, and `RunCancelled`:

```text
AnswerCompleted
ClarificationRequired
NoAnswerCompleted
RunFailed
RunCancelled
```

`NoAnswerCompleted` signals a successfully completed controlled turn, not
an error — the browser renders the persisted `NO_ANSWER` `Message`
normally once the atomic write has committed, exactly as it would render
a `GROUNDED_ANSWER` turn. It must never expose the internal retrieval
distinction that produced it (which of the four collapsed outcomes
actually occurred), consistent with "Security-safe wording policy" below.

### Security-safe wording policy

Exact retrieval outcomes are preserved internally without exception, for
audit, evaluation, debugging, metrics, and regression analysis — this
clarification loses no internal precision. Public wording may
deliberately collapse `NO_ELIGIBLE_EVIDENCE`, `NO_RETRIEVAL_CANDIDATES`,
and retrieval's `INSUFFICIENT_EVIDENCE` into one safe wording family, and
`COMPARISON_SCOPE_INCOMPLETE` into its own closely related one — this is a
security/privacy projection, the same `404`-not-`403` discipline ADR-0006
already requires, never a loss of the internal semantic distinction those
codes preserve. Ordinary telemetry never logs the raw question, retrieved
evidence, or rendered controlled-response text. Safe metrics may include:
the retrieval outcome; the clarification source/reason enum; whether
generation was invoked; the terminal run state; the controlled-renderer
version; and a retryability classification.

### What this clarification supersedes

Because this is a post-acceptance clarification, the original accepted
prose above is left standing rather than silently rewritten to pretend
this gap never existed. Where the original text below is narrower than
what this clarification now establishes, **this clarification takes
precedence**:

- "every `CLARIFICATION` `Message` comes from a contextualisation
  snapshot" is superseded — a `CLARIFICATION` `Message` may also
  originate from a `RetrievalOutcomeSnapshot`, per "Retrieval-originated
  clarification" above;
- "only a run reaching `COMPLETED` or `CLARIFICATION_REQUIRED` produces an
  assistant `Message`" is superseded — a run reaching
  `RETRIEVAL_NO_ANSWER` also produces one, of kind `NO_ANSWER`;
- any reading of the original `Failure taxonomy` section as treating every
  non-`EVIDENCE_FOUND` retrieval result as either `RETRIEVAL_FAILED` or
  contextualiser clarification is superseded by the complete eight-outcome
  mapping above. `RETRIEVAL_FAILED` remains exclusively a genuine operational
  retrieval failure. `TEMPORAL_SCOPE_UNRESOLVED` remains its own preserved
  retrieval outcome but independently maps the conversation run to `FAILED`;
  neither represents a controlled no-answer or comparison-incomplete result;
- the queue-infrastructure factual claim corrected in "Connection-
  independent execution" above (framework configuration exists;
  application-owned usage does not yet) is corrected in place, not merely
  superseded here, since it was a factual error rather than a narrower
  true statement.

**Unchanged and not reopened by this clarification:** the provider-neutral
`Generator`/`QueryContextualizer` boundaries; the `generation.stream`/
`generation.answer` transport architecture; `AnswerPartCandidate`/
`AnswerPartAcceptedForDisplay` streaming and validation; delivery-versus-
authoritative persistence; SSE as the browser transport; queued,
connection-independent execution; single-active-run linear conversations;
and every tenancy, deletion, and observability rule ADR-0024 already
established. Laravel retains every outcome-mapping and deterministic-
rendering decision introduced here; Python receives a generation request
only for `EVIDENCE_FOUND` and never phrases, classifies, or renders any
retrieval-layer controlled outcome; Redis remains outside the
candidate-streaming path, unchanged.

## Relationship to prior ADRs

### Consumes ADR-0023's generation contract and invariants; reopens nothing about generation semantics

ADR-0023 defines the provider-neutral `Generator` boundary, the
`ANSWERED`/`QUALIFIED`/`INSUFFICIENT_EVIDENCE` outcome model, `answer_parts[]`
as the sole authoritative generated representation, durable
`EvidenceSnapshot`s, deterministic validation, and the central invariant this
document inherits verbatim: *"no generated content becomes authoritative,
user-visible answer content before the generation contract and citation
validation have passed"* (ADR-0023, "The end-to-end flow"). ADR-0023's own
"Streaming deferral" section explicitly records that it does not design
Phase 18 streaming, names `answer_parts[]` as "a useful future
validated-streaming unit," and commits only to keeping that structure
first-class for later use. This document is that later use. It does not
redefine sufficiency, the outcome taxonomy, grounding rules, context
packing, or generation lineage — where it needs those concepts it uses
ADR-0023's own vocabulary.

### Extends `rc1` with a new purpose for streamed responses — correcting this document's own first-draft misreading of what the HMAC actually constrains

`generation.answer` (ADR-0023, ADR-0018's `rc1`) is a synchronous,
HMAC-signed, whole-body **request**: the canonical string-to-sign binds
`body-sha256` of the complete request body
(`apps/api/app/Services/Retrieval/RetrievalCallSigner.php:38-46`), and
Python verifies against the complete received body before processing
(`apps/ai/app/retrieval/authentication.py:112-119`,
`apps/ai/app/generation/routes.py:53`). **The first draft of this document
over-read that constraint**, concluding it made Laravel-to-Python response
streaming structurally impossible and recommending an independent Redis
side-channel to work around it. That conclusion was wrong, and the
side-channel is withdrawn (see "Alternatives considered"). Request
authentication and response delivery framing are different concerns:
`rc1`'s HMAC authenticates and replay-checks the complete *request* Laravel
sends; nothing about that scheme constrains how Python's *response* is
framed once the request has been received, verified, and accepted for
processing. A request can be received completely, authenticated
completely, and processed only after successful authentication, while
still being answered with a streamed HTTP response — a framed sequence of
events — rather than one buffered JSON object. This requires an evolved
*response* contract; it does not require a new signature format,
chunk-by-chunk request signing, or any side-channel that bypasses `rc1`
authentication.

This document therefore adds one new `rc1` purpose, `generation.stream`,
under the existing `retrieval-caller` principal, key ring and signing
mechanism — no new principal, no change to the canonical string-to-sign
shape, exactly the extension path ADR-0018 committed to and ADR-0021/0023
already exercised. `generation.stream`'s *request* is authenticated
identically to `generation.answer`'s. Its *response* is a streamed
sequence of framed, provider-neutral events terminating in one complete
result (see "Streaming transport between Laravel and Python" below).
`generation.answer` itself is untouched, and remains the synchronous,
whole-envelope contract — retained permanently as the fallback path for
any provider or model that cannot support safe incremental part detection
(see "Synchronous fallback for unsupported providers" below), not merely
as a migration artefact. Purpose-binding discipline is unchanged: a
signature valid for `generation.stream` is never accepted for
`generation.answer`, `retrieval.plan`, `retrieval.search`, or
`retrieval.rerank`, and vice versa. This is a genuine, load-bearing
correction this document makes to its own first draft, surfaced explicitly
rather than quietly revised away.

### Consumes, does not reopen, ADR-0018's retrieval boundary

Retrieval planning, eligibility resolution and the `Retriever` contract
(ADR-0018, extended by ADR-0021/0022) are unchanged. This document corrects
one piece of stale planning language this brief itself flagged (see
"Reconciling the preliminary Phase 18 wording" below) but does not touch
`RetrievalPlanner`, `EligibilityResolver`, `EligibleRetrievalScope`, or the
retrieval outcome taxonomy.

### Consumes, does not reopen, ADR-0017's temporal-authority model

Contextualisation and generation reflect whatever `EligibilityResolver`
already resolved (ADR-0017's `authority_start`/`authority_end` derivation);
neither the query contextualiser nor conversation history re-derives
temporal authority, applicability, or eligibility.

### Extends ADR-0006's tenancy boundary and audit layers, not a new posture

Every conversation-domain access point inherits ADR-0006's defence-in-depth
enforcement (workspace-scoped routes → authenticated user → active
membership → policies → explicit tenant-scoped queries → RLS → database
constraints) and its `404`-not-`403` concealment rule. This document
extends ADR-0006's named Search/RAG audit layer — *"who searched, in which
workspace, the query, retrieved documents/chunks, citations, the model
used, latency, token usage, cost, and correlation identifiers"* — to cover
conversation and streaming events, rather than inventing a fourth audit
layer.

### Extends ADR-0012's observability foundation; defines its own attribute names as ADR-0012 already expects

ADR-0012 deliberately does not name `rag.*` attributes for generation or
retrieval, leaving "each stage defines its own instrumentation against
these principles when it is actually built" (ADR-0012, "Scope boundaries").
This document does that for conversation/streaming, inheriting the
allowlist-first privacy posture and the invariant that *"a telemetry or
instrumentation failure never causes a user-facing request to fail"*
verbatim.

### Reconciles ADR-0007's document deletion pattern as the established convention for lifecycle design, not a document-specific rule

ADR-0007 defines document deletion as an explicit `DELETING → DELETED`
state-machine pair, reachable from any non-terminal state, asynchronous,
with the row retained post-deletion for audit/reconciliation and hard-purge
policy explicitly deferred. Verified against the actual schema: no
`SoftDeletes` trait, `deleted_at`, or `archived_at` column exists anywhere
in `apps/api` today (grepped across every model and migration) — the
platform's one real, implemented lifecycle convention is an explicit enum
`status` column with DB-level check constraints (for example
`documents.status`, `apps/api/database/migrations/2026_07_28_000003_create_documents_table.php:26-29`).
This document adopts the same shape for `Conversation` lifecycle rather
than introducing Eloquent soft-deletes as a new, inconsistent convention.

### Reconciling the preliminary Phase 18 wording

`IMPLEMENTATION_GUIDE.md` Stage 18.2's planned flow currently states step
4 as *"AI retrieves tenant-filtered context."* Read literally, against
ADR-0023 and ADR-0018, this is wrong: Python never retrieves, never
resolves tenancy, and never chooses evidence — Laravel does, before
Python is ever called. This document does not silently design around that
wording; it flags it as a documentation correction owed to
`IMPLEMENTATION_GUIDE.md` (out of this document's own scope to edit — see
"Scope boundaries") and states the corrected boundary explicitly below.

## Context

Phase 16 (Retrieval) and Phase 17 (Grounded Generation) are both complete.
Phase 17 built the full grounded-generation pipeline — provider-neutral
`Generator`, the `ANSWERED`/`QUALIFIED`/`INSUFFICIENT_EVIDENCE` outcome
model, `answer_parts[]`, durable `EvidenceSnapshot`s, deterministic
validation, `GENERATION_CONTEXT_BUDGET_EXCEEDED` — and it works end to end,
verified by its own evaluation runs (GEN-EXP-0001/0002). Two facts about
what actually exists matter directly to this document, verified against the
implementation rather than assumed:

- **`GenerateGroundedAnswer` is not wired to any HTTP route.** Its only
  caller today is a feature test
  (`apps/api/tests/Feature/GroundedGenerationFoundationTest.php`). Phase 18
  is the first time grounded generation is exposed to a real user, which
  means this document is not preserving backward compatibility with an
  existing public API — it is defining the first one.
- **`generated_answers` is a flat, unthreaded table** — `public_id`,
  `workspace_id`, `created_by_user_id`, `correlation_id`, `question`,
  outcome/fingerprint/usage fields
  (`apps/api/database/migrations/2026_08_16_000014_add_grounded_generation_foundation.php:12-36`)
  — with no `conversation_id` or parent/thread relationship of any kind.
  Introducing conversation threading is additive schema work, not a
  redesign of what Phase 17 built.

Phase 18's objective is to expose this pipeline as a persistent, streaming
conversational experience. The current stage is R18-S01 — Define
Conversation Domain, which must settle the durable conversation,
contextualisation, execution, streaming-authority and persistence
boundaries the later implementation stages (R18-S02 Chat Orchestration API,
R18-S03 Streaming Responses, R18-S04 Chat Interface) build against.

The central tension this document exists to resolve, stated plainly: a
conversation's *history* can legitimately help interpret what a user is
asking now, but a previously generated answer must never be trusted as
*evidence* for a new one — every new factual claim must be freshly
retrieved and authorised against the current workspace, user, temporal
context and applicability constraints, regardless of what was said earlier
in the same conversation, regardless of how confidently it was said, and
regardless of whether it carried citations.

## What this ADR decides and does not decide

This ADR defines: the conversation/grounding/execution domain model and its
aggregate boundaries (`Conversation`, `Message` with its `kind`
discriminator, `GenerationRun`, `GeneratedAnswer`); persisted message roles;
the bounded conversational-context policy, including how clarification
exchanges participate in it; the provider-neutral `QueryContextualizer`
boundary and its own `rc1` purpose; conversation, message, run and answer
persistence shape, including deterministic per-conversation message
ordering; the single authenticated `generation.stream` transport between
Laravel and Python and why it replaces this document's own withdrawn
first-draft side-channel design; part-local versus whole-answer validation
and the precise, narrow sense in which ADR-0023's "authoritative" invariant
is clarified (not amended) for provisional display; the distinction
between bounded, non-authoritative delivery persistence and atomic
authoritative persistence; citation delivery; progress events; transport
(SSE) and why; connection-independent execution, queue durability
guarantees, and reconnect; linear-conversation concurrency and
single-active-run enforcement; timeout/retry/idempotency/cancellation
semantics; the business-outcome-versus-execution-failure taxonomy, with
`CANCELLED` and delivery-interruption kept structurally distinct from
failure codes; precise deletion/retention semantics; tenancy/security
constraints; observability; lineage/fingerprint extension; and the future
seam for branching without building it now.

It does not decide: exact SSE framing/headers, exact Laravel controller or
job class names, exact frontend component structure, exact database column
types beyond what's structurally required, calibrated timeout durations or
numerical SLOs without operational evidence, Phase 19 administration
features, or any implementation belonging to R18-S02/S03/S04. It does not
redecide anything ADR-0023, ADR-0018, ADR-0017, ADR-0012 or ADR-0006 already
settled.

## Decision

### The governing principle

*Conversation context helps determine what the user means. Currently
authorised retrieval evidence determines what the system may claim.*

A previously generated answer is never trusted factual evidence for a new
one merely because it occurred earlier in the conversation, was previously
validated, carries citations, references an `EvidenceSnapshot`, or is
referred back to by the current user. Prior citations and
`EvidenceSnapshot`s may inform the *subject* of a follow-up (safe,
structured hints for contextualisation) but never bypass fresh retrieval
and authorisation. Every new factual answer is grounded against evidence
retrieved and authorised now, for the current workspace, user, temporal
context and applicability constraints — never against what a previous
answer said.

### Reconciled Laravel/Python ownership boundary

ADR-0023's split is preserved and restated precisely because Stage 18.2's
preliminary wording drifted from it:

**Laravel owns:** authentication and authorisation; workspace tenancy;
conversation-context policy and selection of the bounded history window;
assembly of the provider-neutral `ContextualisationRequest`; acceptance of
the typed `ContextualisationResult` and the final interpretation supplied
to `RetrievalPlanner`; authorised retrieval; temporal authority;
applicability/location truth; evidence selection and ordering;
deterministic context-packing; `GenerationRequest` assembly;
request-scoped evidence-handle mapping and ownership; deterministic,
application-level validation (both part-local and whole-answer); all
persistence; run lifecycle; retry and cancellation commands; every
browser-facing event; and the final decision that content is accepted as
an authoritative answer.

**Python owns:** the provider-neutral `QueryContextualizer` and
`Generator` interfaces; provider-specific request rendering; provider
calls; provider-native streaming interpretation; incremental parsing of
provider-native structured output; recognition of a complete
provider-neutral `AnswerPartCandidate`; typed, provider-neutral candidate
events and terminal results; provider/model-specific token measurement;
bounded transient provider retries; provider failure mapping.

**Laravel must not:** call OpenAI or another model provider directly;
parse provider-native token events; incrementally parse provider-native
JSON; depend on OpenAI (or any provider's) response-event types; infer
where an `AnswerPart` ends from raw provider output; own prompt/provider
rendering; perform model-semantic query rewriting itself; or become a
second provider adapter. Laravel may parse the deliberately framed,
provider-neutral `GenerationStreamEvent` protocol and validate its schemas
— that is application-boundary parsing of an already-neutral contract, not
provider-native parsing, and the distinction is load-bearing throughout
this document.

**Python must not:** retrieve; authorise; select or rerank evidence;
resolve tenancy; decide temporal authority or applicability; alter
Laravel-owned evidence; decide that a candidate `AnswerPart` is
application-authoritative; persist any conversation-domain record;
communicate directly with the browser; emit application/browser events; or
bypass Laravel through Redis or any other application side-channel — there
is no side-channel in this architecture for Python to bypass Laravel
through in the first place (see "Alternatives considered").

`IMPLEMENTATION_GUIDE.md` Stage 18.2's step 4, *"AI retrieves
tenant-filtered context,"* is corrected here: **Laravel retrieves, using
already-authorised, already-tenant-scoped evidence; Python never
retrieves.** Nothing about conversation or streaming transfers retrieval
authority to Python. This boundary is stated in these same terms wherever
this document discusses streaming or contextualisation, not restated
loosely elsewhere.

### Domain model: Conversation, Message, GeneratedAnswer, GenerationRun

The brief's proposed shape is correct in its separation of concerns and is
adopted, with cardinalities made explicit:

```text
Workspace
  └── Conversation (1) ──────────────────────────────┐
        └── Message (many, ordinal-ordered)           │ workspace_id
              │                                        │ denormalised onto
              ├── role: USER                           │ every child row
              │     └── GenerationRun (0..N)            │ (see "Tenancy")
              │           └── retry_of_run_id (0..1, self-referential)
              │
              └── role: ASSISTANT (0..1 per run reaching a
                        message-producing terminal state — see below)
                    ├── kind: GROUNDED_ANSWER
                    │     └── GeneratedAnswer (exactly 1)
                    │           ├── AnswerPart (0..N; 0 only when
                    │           │     outcome = INSUFFICIENT_EVIDENCE)
                    │           │     └── cites EvidenceSnapshot (1..N)
                    │           └── EvidenceSnapshot (0..N, deduplicated
                    │                 per answer)
                    └── kind: CLARIFICATION
                          (no GeneratedAnswer; display text sourced from
                           the originating run's ContextualisationResult)
```

**Three distinct concerns, three distinct lifecycles, deliberately not
collapsed:**

- **Conversation domain** (`Conversation`, `Message`) — what visible
  interaction occurred. `Conversation` is the aggregate root for visible
  history. A `Message` is a visible turn; it is created once and, once
  created, its role and authorship never change.
- **Grounding domain** (`GeneratedAnswer`, `AnswerPart`, `EvidenceSnapshot`)
  — why an accepted answer is trustworthy. Unchanged from ADR-0023: still
  the sole authoritative generated representation, still evidence-bound,
  still validated before persistence.
- **Execution domain** (`GenerationRun`) — what happened while attempting
  to produce an answer: queued, contextualising, retrieving, generating,
  validating, completed, failed, cancelled, retried. A run's lifecycle is
  independent of whether it ever produces a `Message`.

**Cardinalities, precisely — corrected from the first draft, which
contradicted itself about whether every ASSISTANT `Message` has a
`GeneratedAnswer`:**

- A `Conversation` has many `Message`s, ordered by an explicit `ordinal`
  (see "Linear-conversation concurrency and message ordering" below), not
  by creation timestamp alone.
- A **USER** `Message` has zero or more `GenerationRun`s (zero only in the
  instant between submission and durable run creation — see "Connection-
  independent execution"; in steady state, at least one). Multiple runs
  exist only through retry.
- A **GenerationRun** belongs to exactly one USER `Message`, and optionally
  references an earlier run via `retry_of_run_id` (self-referential,
  nullable).
- A **GenerationRun** reaching `COMPLETED` produces exactly one **ASSISTANT**
  `Message` of `kind = GROUNDED_ANSWER`, with exactly one `GeneratedAnswer`.
- A **GenerationRun** reaching `CLARIFICATION_REQUIRED` produces exactly
  one **ASSISTANT** `Message` of `kind = CLARIFICATION`, with **no**
  `GeneratedAnswer` — its display text is sourced from the run's
  `ContextualisationResult.clarification_question`, not from any grounded
  content.
- A **GenerationRun** reaching `FAILED` or `CANCELLED` produces **no**
  `Message` and **no** `GeneratedAnswer` at all.
- **`kind` is an application-domain discriminator on the ASSISTANT role,
  not a third persisted role** (see "Persisted message roles" below) —
  it resolves the first draft's contradiction directly: *every* ASSISTANT
  `Message` still has *exactly one* thing that explains its content, but
  that thing is a `GeneratedAnswer` only when `kind = GROUNDED_ANSWER`,
  and a `ContextualisationResult` reference when `kind = CLARIFICATION`.
  The two kinds are never conflated and never share a row shape.
- Within `kind = GROUNDED_ANSWER`, a `GeneratedAnswer` has one or more
  `AnswerPart`s for `ANSWERED`/`QUALIFIED`, and zero for
  `INSUFFICIENT_EVIDENCE` — exactly ADR-0023's own invariant table, applied
  here without exception (see "INSUFFICIENT_EVIDENCE rendering" below for
  how that Message's display text is sourced when there are zero parts).
- `EvidenceSnapshot` cardinality and reuse is unchanged from ADR-0023:
  per-answer scope, one snapshot reusable by every `AnswerPart` in that
  answer that cites the same evidence.

Failed and cancelled runs never require a fabricated assistant `Message`
or a fabricated `GeneratedAnswer` — a run can reach a terminal failure
state while the conversation, from the user's visible perspective, simply
shows the USER message and a recoverable failure state (see "Timeout,
retry and idempotency"). A `CLARIFICATION_REQUIRED` run is not a failure —
it produces a real, visible ASSISTANT turn, just not a grounded one.

### Persisted message roles

**V1 persists exactly two conversation-domain roles: `USER` and
`ASSISTANT`.** No genuine third role is required. `SYSTEM`, `DEVELOPER` and
`TOOL` are provider chat abstractions, not conversation-domain concepts,
and are never persisted as messages — they belong to `GenerationRequest`
construction (Laravel-owned) and provider-specific rendering (Python-owned,
ADR-0023), not to visible conversation history. Operational notices
(searching, checking evidence, provider timeout, cancellation, validation
failure) are never fabricated assistant speech — they are `run.progress`
and terminal-status events (see "Application-owned progress events" and
"Failure taxonomy"), structurally incapable of being confused with
something the assistant said.

A `CLARIFICATION_REQUIRED` outcome from the contextualiser is **not** a
third role. It is represented by an application-domain discriminator on
the ASSISTANT role, `Message.kind`, with exactly two values:

```text
GROUNDED_ANSWER
  → originates from exactly one GenerationRun that reached COMPLETED
  → has exactly one GeneratedAnswer
  → display text is deterministically derived from the authoritative
    GeneratedAnswer (AnswerParts, or insufficiency_reason when outcome is
    INSUFFICIENT_EVIDENCE — see "INSUFFICIENT_EVIDENCE rendering" below)

CLARIFICATION
  → originates from exactly one GenerationRun that reached
    CLARIFICATION_REQUIRED
  → has no GeneratedAnswer
  → display text is deterministically copied from that run's durably
    persisted contextualisation-result snapshot (see "Durable
    contextualisation-result persistence" below — not from an ephemeral,
    in-memory ContextualisationResult, which does not outlive the request
    that produced it)
  → contains a question requesting the missing interpretation, never a
    factual answer
  → is eligible to form a visible conversational turn, including
    participating in a future contextualisation window (see
    "Contextualisation" below)
```

`kind` is not a provider chat abstraction and is not a third persisted
role — it is this document's own, narrow, application-domain concept.
**Every ASSISTANT `Message`, of either `kind`, has exactly one durably
persisted record explaining its content** — a `GeneratedAnswer` for
`GROUNDED_ANSWER`, a contextualisation-result snapshot for
`CLARIFICATION` — never a record that exists only transiently. This
exists solely because a clarification question is genuinely a visible
assistant turn a user must be able to reply to, and forcing it through the
same shape as a completed grounded answer would either fabricate a
`GeneratedAnswer` that doesn't exist or silently drop the question
entirely — both worse than one discriminator column plus one durable
snapshot table.

**Deterministic (structural) validation for a `CLARIFICATION` Message**,
enforced before persistence: the text must be sourced only from the
durable snapshot's `clarification_question` field (never assembled from
any other field); it is bounded in length (the same order of magnitude as
a single `AnswerPart`, not a multi-paragraph response); it carries no
citations and no `evidence_ids` (structurally absent from the row shape,
not merely empty); no `GeneratedAnswer` exists for it; and its presence is
gated on the run having actually reached `CLARIFICATION_REQUIRED` —
Laravel never constructs one from a `COMPLETED` run's content or vice
versa.

**What this validation does not, and cannot, prove — stated honestly
rather than implied away:** Laravel cannot deterministically prove that
arbitrary natural-language prose contains no implicit factual assertion.
Structural bounds (source field, length, absence of citations) are
enforced; genuine semantic compliance — that the contextualiser's question
never smuggles in a fact — is not something deterministic code can verify
from prose alone. This residual risk is managed the same way ADR-0023
already manages the equivalent risk for generation: the contextualiser
contract and prompt explicitly prohibit factual answering, the output is
bounded to a single clarification-question field with no room for an
extended answer, Laravel enforces every structural invariant actually
available to it, and semantic compliance is evaluated and monitored (Stage
17.4-style model-assisted evaluation, extended to this operation) rather
than assumed — any discovered violation becomes a permanent regression
fixture. This is recorded as a bounded, accepted residual model risk, not
a capability this document falsely claims Laravel's deterministic layer
possesses.

### Contextualisation

**Bounded history, not a full transcript, and not a generated summary in
V1.** The default policy: the current user message plus up to three
preceding *completed* conversational turns, where one turn means a USER
`Message` plus its successfully completed, validated ASSISTANT `Message`.
The turn limit is configurable. A separate, deterministic token ceiling
protects the contextualisation request itself; when three turns exceed it,
trimming removes the oldest complete turn first — never an arbitrary
mid-message split.

**Never enters context, under any circumstance:** failed generation output;
cancelled output; provider-native partial tokens; unaccepted `AnswerPart`
candidates; incomplete streamed responses; timeout messages; progress/
status text; operational error details. Only a `Message` produced by a run
that reached a message-producing terminal state — `COMPLETED`
(`kind = GROUNDED_ANSWER`) **or** `CLARIFICATION_REQUIRED`
(`kind = CLARIFICATION`) — may ever enter a future contextualisation
window.

**Clarification exchanges are context, deliberately, even though they are
never evidence.** A completed clarification turn — `USER` (ambiguous) →
`ASSISTANT`/`CLARIFICATION` (the question) → `USER` (the clarifying reply)
— must not have its middle turn silently discarded by the "only completed
turns" rule, or the clarifying reply becomes uninterpretable: for
contextualisation-window purposes, a "completed turn" is any USER `Message`
paired with a terminal ASSISTANT `Message` of *either* `kind`, not only
`GROUNDED_ANSWER`. A `CLARIFICATION` turn is bounded history the
contextualiser may read, exactly like a `GROUNDED_ANSWER` turn; it is
simply never evidence, because it never asserted a fact in the first
place — the governing principle already covers it without needing a
special case at the evidence layer, only this one explicit clarification
at the history-window layer.

**The contextualisation boundary:**

```text
Current user message
      +
bounded conversation history (completed turns only)
      +
safe structured references to prior turn lineage
      ↓
Query Contextualiser
      ↓
RESOLVED (standalone query) | CLARIFICATION_REQUIRED
      ↓
Laravel-authorised retrieval (ADR-0018, unchanged)
      ↓
current authorised evidence
      ↓
GenerationRequest (ADR-0023, unchanged)
```

Ownership is deliberately separated: the contextualiser answers *"what is
the user asking now?"*; `RetrievalPlanner` (ADR-0018) answers *"how should
the resolved query be retrieved?"*; `Generator` (ADR-0023) answers *"what
grounded answer follows from the currently supplied evidence?"* The
contextualiser never answers the user and never manufactures a factual
premise — its only output is a resolved query or a request for
clarification. Previous assistant answers supplied to it are explicitly
untrusted generated context, never evidence, exactly as the governing
principle states.

**`ContextualisationResult`**, provider-neutral, sufficient to represent:

```text
ContextualisationResult
  status: RESOLVED | CLARIFICATION_REQUIRED
  resolved_query: string | null        (present iff RESOLVED)
  used_prior_context: boolean
  interpretation_metadata: object      (safe, structured — e.g. which
                                         prior turn/citation informed
                                         resolution; never raw provider
                                         reasoning text)
  clarification_question: string | null (present iff CLARIFICATION_REQUIRED,
                                         user-facing)
  contextualiser_version: string        (lineage/versioning — mirrors
                                         ADR-0023's prompt_version discipline)
  # bounded failure is a typed GenerationRun failure category
  # (CONTEXTUALISATION_FAILED — see "Failure taxonomy"), not a field on
  # this result
```

**Contextualisation requires a real, provider-neutral boundary — the
first draft's decision to fold it into Laravel-owned orchestration without
one is corrected here.** The reasoning is structural, not about how many
providers exist today: if contextualisation uses an LLM at all, then by
this document's own reaffirmed ownership boundary (see "Reconciled
Laravel/Python ownership boundary" above), Laravel must not call a model
provider directly, must not own provider-specific prompt rendering, and
must not become a second provider adapter. Once that constraint holds, the
operation necessarily crosses the Laravel/Python service boundary, and a
typed, cross-service, provider-neutral operation is what a service
boundary *is* in this codebase — the same reasoning that gives `Generator`
its shape (ADR-0023), `RetrievalPlanner` its shape (ADR-0018), and
`Embedder`/`SparseEncoder`/`Reranker` theirs. The first draft's "only one
provider implementation exists today" reasoning was the wrong test — every
one of those boundaries was introduced with exactly one V1 implementation
behind it; the boundary exists because of *where the capability lives*,
not how many providers currently sit behind it.

**`QueryContextualizer`**, provider-neutral, minimal, the same
Open/Closed shape as `Generator`:

```text
QueryContextualizer.contextualize(
    request: ContextualisationRequest
) -> ContextualisationResult
```

This is deliberately the smallest real boundary the behaviour justifies,
not a second grounded-generation-sized subsystem: one request type, one
result type, contract versioning, prompt/model/configuration lineage
(`contextualiser_version`, mirroring ADR-0023's `prompt_version`
discipline exactly), Python-owned provider rendering and invocation,
bounded transient retry and typed provider failure mapping (the same
categories ADR-0023 already established for generation, reused rather
than re-invented), and Laravel-owned orchestration and acceptance of the
typed result. It carries its own `rc1` purpose,
`conversation.contextualize`, under the same principal, key ring and
signature format as every other `rc1` purpose — following the same
extension path this document's `generation.stream` purpose already uses,
not a new protocol shape.

**The boundary, stated precisely:** Laravel owns *which context is
supplied* to the contextualiser and *what the result may influence*
(retrieval, nothing else); Python owns *how* the selected provider
produces the typed result. The contextualiser produces only: a resolved
standalone query; or a clarification requirement and bounded clarification
question; plus safe structured interpretation metadata and lineage. It
must not retrieve, answer the factual question, manufacture an
authoritative premise, treat prior assistant content as evidence, change
temporal authority or applicability, or gain any autonomous tool
capability — every one of these is the same discipline ADR-0023 already
requires of `Generator`, applied here to a different, narrower operation.

### Durable contextualisation-result persistence

`ContextualisationResult` as defined above is a request/response contract
shape — it does not, by itself, say anything about what outlives the
request that produced it. **This document requires an explicit durable
record, corrected here from language that described `CLARIFICATION`
`Message`s as explained by a result without stating where that result
actually persists.** Every `GenerationRun` that receives a
`ContextualisationResult` durably records an application-owned
contextualisation-result snapshot — never the model's raw response, never
raw provider reasoning:

```text
GenerationRun.contextualisation_result   (durable snapshot, not the
                                            ephemeral request/response object)
  status: RESOLVED | CLARIFICATION_REQUIRED
  clarification_question: string | null   (validated; present iff
                                            CLARIFICATION_REQUIRED — this
                                            exact field is what a
                                            CLARIFICATION Message's display
                                            text is copied from)
  used_prior_context: boolean
  interpretation_metadata: object | null  (safe, structured; retained only
                                            where useful for audit/lineage,
                                            never raw provider reasoning)
  contextualiser_version: string
  # prompt/model/configuration lineage, folded into the run's overall
  # generation_fingerprint extension — see "Generation lineage and usage"
  usage: object | null                    (where the provider reports it)
  correlation identifiers
```

**Cardinality:** a `GenerationRun` that successfully completes
contextualisation (`RESOLVED` or `CLARIFICATION_REQUIRED`) has exactly one
terminal contextualisation-result snapshot. A `CLARIFICATION` `Message`
has exactly one originating `GenerationRun`; that run's `status` is
`CLARIFICATION_REQUIRED`; its persisted snapshot supplies the `Message`'s
display text, atomically, in the same write that records the run reaching
`CLARIFICATION_REQUIRED` — not a separate, later write that could
observably lag or diverge from it.

For `RESOLVED` contextualisation, only the fields required for
orchestration, audit/lineage, and reproducibility within this document's
existing privacy posture are retained — raw provider reasoning is never
required and never stored, the same allowlist-first discipline ADR-0012
already establishes for every other pipeline stage.

No generated long-term conversation summary is introduced in V1. Bounded
raw history is the accepted starting point; a future summary seam is
preserved by keeping `Message` history queryable and ordered, not by
building anything now.

### Conversation persistence

**`Conversation`:** identifier (UUID public identity, matching the
platform's existing `public_id` convention); `workspace_id`; creator
(`created_by_user_id`); `title` (a deterministic, bounded projection of the
first USER `Message`, user-editable thereafter — no V1 model-generated
title; see "Title generation and Phase 18 fit" below); lifecycle `status`
enum (`ACTIVE`, `ARCHIVED`, `DELETING`, `DELETED` — see "Deletion and
retention"); `created_at`/`updated_at`.

**`Message`:** identifier; `conversation_id`; `workspace_id` (denormalised
— see "Tenancy and security"); `ordinal` (monotonically assigned per
conversation, `UNIQUE(conversation_id, ordinal)` — see "Linear-conversation
concurrency and message ordering" below); `role` (`USER`|`ASSISTANT`);
`kind` (nullable for `USER`; `GROUNDED_ANSWER`|`CLARIFICATION` for
`ASSISTANT` — see "Persisted message roles"); display text;
`created_by_user_id` (nullable for `ASSISTANT`); `in_reply_to_message_id`
(nullable, self-referential — the reply-relationship seam named in
"Branching" below); client-supplied idempotency identity (see "Timeout,
retry and idempotency"); `created_at`; immutable once created — a
`Message`'s role, kind, author and conversation are fixed for its
lifetime, and its display text is fixed once the underlying
`GeneratedAnswer` or `ContextualisationResult` exists, never edited in
place (ADR-0007's own "prefer append, never silently rewrite" discipline
applied here).

**`GenerationRun`:** identifier; `workspace_id`; `conversation_id`;
`user_message_id`; `assistant_message_id` (nullable, set only on success);
`retry_of_run_id` (nullable, self-referential); `status` (see "Failure
taxonomy" for the full lifecycle enum); contextualisation lineage
(`contextualiser_version`, resolved-query digest — never the raw resolved
query in telemetry, per "Observability"); retrieval lineage (existing
`RetrievalResult`/plan identifiers, unchanged shape from ADR-0018);
`generation_fingerprint` (ADR-0023, extended — see "Generation lineage");
correlation identifiers; usage metadata (recordable even on failure —
see "Generation lineage and usage"); timings (queued/started/first-
progress/first-part-accepted-for-display/completed, per stage); `failure_code`
(nullable, typed); cancellation state (see "Cancellation"); stream/event
sequence cursor (see "Candidate and browser event separation").

**`GeneratedAnswer`:** unchanged in shape from ADR-0023's existing
`generated_answers` table, extended with `assistant_message_id` and
`generation_run_id` foreign keys (the additive schema work Phase 17's flat
table anticipates, per "Context" above) — `outcome`, `answer_parts` (via
`AnswerPart`), `unsupported_aspects`, `insufficiency_reason`, validation
timestamp, and the existing fingerprint/lineage fields, all unchanged.

`EvidenceSnapshot` and citation persistence preserve ADR-0023's accepted
semantics exactly — no change is made or needed here.

**No second independently generated answer representation is created.**
`answer_parts[]` remains the sole authoritative generated content for
`ANSWERED`/`QUALIFIED`. **The first draft's claim that a
`GROUNDED_ANSWER` Message's display text is "always" concatenated from
`AnswerPart`s was incomplete — corrected here.** ADR-0023's own invariant
table gives `INSUFFICIENT_EVIDENCE` zero `AnswerPart`s and a required,
non-null `insufficiency_reason`; a `GROUNDED_ANSWER` Message for that
outcome has nothing to concatenate. The deterministic rendering rule,
covering all three outcomes without exception: for `ANSWERED`/`QUALIFIED`,
display text is `AnswerPart`s concatenated in part order, exactly as
ADR-0023's `GenerationResult::renderedText()` already does today
(`apps/api/app/Support/Generation/GenerationResult.php:20-23`); for
`INSUFFICIENT_EVIDENCE`, display text is the `GeneratedAnswer`'s
`insufficiency_reason` verbatim. This is not a second generated
representation and not a free-prose escape hatch — `insufficiency_reason`
is already part of the one typed `GenerationResult` ADR-0023 defines, it
is permitted *only* when `outcome = INSUFFICIENT_EVIDENCE`, and ADR-0023's
own grounding rules already forbid it from carrying a cited factual
assertion (it describes an absence, never asserts a fact). Rendering it
verbatim as the Message's display text adds no new capability and no new
risk beyond what ADR-0023 already constrains. In neither case is display
text an independently stored or independently editable string. **A
denormalised rendered-text projection on `Message` is useful** (it avoids re-joining
`AnswerPart`s on every conversation read) **provided equivalence is
structurally guaranteed, not merely conventionally maintained**: it is
written exactly once, atomically with the `AnswerPart`s that produced it,
inside the same transaction that promotes a run to `COMPLETED` (see "Per-
part and whole-answer validation" below), and it is never independently
updatable thereafter — the same discipline ADR-0017 already applies to
derived facts (*"a derived fact, computed at query time... never a stored,
flipped boolean"*), adapted here to *write-once-derived* rather than
*computed-at-read* only because read-time joins across `AnswerPart`s on
every conversation load are a real, avoidable cost this platform has no
reason to pay.

### Validated AnswerPart streaming: the trust model, and the single streamed transport that makes it honest

**Raw provider token streaming to the browser is rejected, unchanged from
ADR-0023's own "Alternatives considered."** So is buffering the complete
result and animating its reveal — that is not streaming, it is a client-
side special effect, and calling it streaming would misrepresent what the
system actually did.

**What is genuinely true about the Phase 17 implementation, retained from
the first draft's investigation:** the current OpenAI adapter makes exactly
one blocking call — `self._client.responses.parse(...)`
(`apps/ai/app/generation/openai_adapter.py:282-291`) — using OpenAI's
non-streaming structured-output helper, and returns exactly one fully
validated `GenerationResult` only once the entire response has arrived
(`apps/ai/app/generation/openai_adapter.py:300-314`,
`apps/ai/app/generation/models.py:89-111`); the `Generator` protocol today
is a flat `generate(request) -> GenerationResult`
(`apps/ai/app/generation/protocol.py:6-7`), with no event-emitting form.
Making genuine incremental delivery real is real, well-scoped R18-S03 work,
not a documentation gap — but, corrected from the first draft, it does
**not** require working around `rc1`'s authentication scheme, and it does
**not** require an independent side-channel.

**The revised architecture uses a single authenticated streaming
Python-to-Laravel response, `rc1`'s new `generation.stream` purpose,
carrying every event for one run — no parallel channel exists:**

```text
Laravel queued GenerationRun worker
        │
        │ complete authenticated generation.stream request
        ▼
Python verifies the rc1 request before processing (unchanged rc1 auth)
        │
        ▼
Python provider adapter calls the provider in streaming mode
        │
        ▼
Python incrementally parses provider-native structured output
        │
        ├── framed AnswerPartCandidate
        ├── framed AnswerPartCandidate
        └── framed terminal GenerationCompleted | GenerationFailed
        │
        ▼
one streamed HTTP response, carrying every framed event in order
        │
        ▼
Laravel consumes the framed, provider-neutral event stream
        │
        ├── part-local validation → AnswerPartAcceptedForDisplay
        ├── durably records bounded delivery-event projections
        ├── projects browser-facing SSE events
        └── on the terminal event: whole-answer validation and, only if
              it passes, the one atomic authoritative persistence write
```

**The terminal event is not a signal that something else already
happened — it *is* the complete candidate result, still subject to
Laravel's own validation before anything is authoritative.**
`GenerationCompleted` carries everything needed to whole-answer-validate a
`GenerationResult` in full: `outcome`, the complete ordered `AnswerPart`s,
`unsupported_aspects`, `insufficiency_reason`, `usage`, and the generation
lineage ADR-0023 requires — but it is Python's report of what generation
produced, not Laravel's acceptance of it (see "Candidate and browser event
separation" below for why no `GenerationStreamEvent`, including this one,
is ever described as application-authoritative). Laravel does **not**
reconstruct the authoritative result by trusting that it received every
`AnswerPartCandidate` along the way — the terminal event is independently
complete and is what whole-answer validation actually runs against; the
candidates the client saw earlier are provisional signal only, never
inputs to the authoritative
write.

**Why a new `rc1` purpose (`generation.stream`) rather than content
negotiation under `generation.answer`:** synchronous whole-result and
streamed-event responses have materially different semantics, not just
different framing — `rc1` already treats purpose binding as a security
property (a signature valid for one purpose is never accepted for
another), and reusing `generation.answer` for two structurally different
response shapes would blur exactly the boundary purpose-scoping exists to
keep sharp, the same reasoning ADR-0018 already gave for not folding
retrieval's synchronous calls into ingestion's asynchronous protocol.
Keeping `generation.answer` completely untouched also means providers or
models that cannot support safe streaming continue using a contract that
was never touched by this change (see "Synchronous fallback" below), and
the new purpose reuses the existing signing format exactly, adding no new
authentication surface.

### Streaming capability: ownership and endpoint selection

Not every provider or model can be trusted to support safe structured
part-level streaming, and **the endpoint decision cannot depend on
information only available after the endpoint is already called —
corrected here from a circularity in an earlier version of this document,
which had Laravel choosing between `generation.stream` and
`generation.answer` while also saying the capability was "decided by
Python's adapter selection," without saying how Laravel would know that
before calling either one.**

The resolution: `delivery_mode` is a **declared, versioned property of the
`GenerationProfile`** (ADR-0023's existing provider/model/adapter-version
identity) — decided in advance, at configuration time, not discovered at
call time:

```text
GenerationProfile
  provider
  model
  adapter_version
  ...
  delivery_mode:
    STREAMING_PARTS         # this profile supports safe incremental
                             # AnswerPartCandidate detection
    COMPLETE_RESULT_ONLY    # this profile does not; use the synchronous
                             # path
```

**Laravel owns selecting the configured, versioned `GenerationProfile` as
part of ordinary orchestration** — the same profile-selection authority
ADR-0023 already gives Laravel — and, once a profile is selected, Laravel
inspects only its declared, provider-neutral `delivery_mode` to choose the
endpoint, never provider name, model name, OpenAI-specific behaviour,
response timing, or any provider-native event type:

```text
STREAMING_PARTS
  → call rc1 generation.stream; complete AnswerPartCandidates are
    delivered incrementally, exactly as described above.

COMPLETE_RESULT_ONLY
  → call rc1 generation.answer (unchanged, synchronous, ADR-0023); the
    browser receives progress events during the wait, then one
    AnswerCompleted event carrying the whole validated answer — never a
    faked incremental reveal of a result that arrived all at once.
```

This does not move provider-specific knowledge into Laravel — Laravel
sees only a provider-neutral declared capability attached to a profile it
already owns selecting. **Python's adapter validates that the requested
profile and `delivery_mode` are actually supported before proceeding**; a
configuration mismatch (for example, a profile marked `STREAMING_PARTS`
against an adapter that cannot actually honour it) produces a typed
contract/configuration failure, never a silent transport change
discovered mid-call — consistent with the fail-closed rule in "Per-part
and whole-answer validation" above, which already forbids exactly that
kind of silent degradation. `delivery_mode` and the endpoint actually used
are recorded in
`GenerationRun` lineage, alongside the rest of the generation fingerprint.

Falling back to the synchronous path never weakens grounding validation —
whole-answer validation is identical either way; the only difference is
whether provisional part-local signal exists before the terminal event
arrives.

### Per-part and whole-answer validation, and the narrow sense in which "authoritative" is clarified

Two genuinely different validation scopes exist, and conflating them is
exactly the mistake this document must not make. Trust-state language is
chosen deliberately so it cannot be confused with final authority — a
Python-detected candidate is never called "validated," because that word
belongs to the boundary that actually confers trust:

```text
Provider-native partial output
        ↓ Python: incremental parsing
AnswerPartCandidate                    (provider-neutral, not yet trusted)
        ↓ Laravel: part-local validation
AnswerPartAcceptedForDisplay           (part-local checks passed; still
        ↓                                belongs to an in-progress answer)
browser delivery, visibly in progress
        ↓ terminal event arrives
Whole-answer validation
        ↓
AnswerCompleted                        (authoritative; persisted)
```

The product-facing description may continue to call the overall feature
"validated AnswerPart streaming" — that framing is fine for users and
documentation — but the contract and this ADR use the precise terms above
internally, specifically so no future reader mistakes "passed part-local
validation" for "final."

**Part-local validation** (knowable the instant a candidate is
structurally complete, performed by Laravel on each `AnswerPartCandidate`
as it arrives in the `generation.stream` response): schema/event
validity; a complete part boundary; non-empty natural prose;
request/run correlation; evidence-handle membership against the
authorised `GenerationRequest`; absence of invented handles; permitted
citation-set structure; sequence validity.

**A candidate that fails part-local validation is a fail-closed event for
the whole run, not a silently-skipped one — corrected here from language
earlier in this document's own revision history that described dropping
one candidate while the run continued.** A malformed event, an invalid
sequence, a run/request mismatch, an invented evidence handle, or any
other structurally invalid `AnswerPartCandidate` makes the streamed
candidate contract for this run untrustworthy as a whole, not just that
one part: Laravel immediately stops forwarding further provisional
`AnswerPartAcceptedForDisplay` projections for the run; requests upstream
cancellation where the provider/runtime supports it (see "Cancellation"
above); terminates the run through the appropriate typed failure (a
malformed-candidate failure is a stream-protocol failure — see "Failure
taxonomy" below — never `STREAM_DELIVERY_INTERRUPTED`, which is unrelated
browser-connection telemetry); retracts any
`AnswerPartAcceptedForDisplay` projections already shown; and never
accepts a `GenerationCompleted` terminal event arriving later on that same
invalid stream as a basis for persistence. **This document does not permit
silent mid-call degradation to complete-result-only delivery after a
provisional-event failure** — a provider or model that cannot reliably
support candidate streaming uses `generation.answer` from the start,
selected through the declared capability model (see "Streaming capability:
ownership and endpoint selection" below), never discovered by failing
mid-stream.

**Whole-answer validation** (knowable only once the terminal
`GenerationCompleted` event has arrived, carrying the complete,
independently-reconstructable `GenerationResult`) is **whole-result
deterministic validation, not semantic proof — restated precisely here
because the first draft's own wording overstated what it covers.**
Laravel can deterministically validate: complete result schema and
contract version; that `outcome` is one of the allowed values; that the
`outcome`/`answer_parts`/`unsupported_aspects`/`insufficiency_reason`
combination matches the required structural shape for that outcome (the
invariant table — a structural check, not a judgement that the outcome
itself was the semantically *correct* one to choose); `AnswerPart`
ordering and bounds; evidence-handle membership and the absence of
invented handles across every part; required `PRIMARY`/`COMPARISON`
structural representation, where that structure is itself encoded in the
result rather than requiring semantic interpretation; citation membership;
run/request correlation; fingerprint and lineage field presence; and
persistence-level constraints. **Laravel cannot deterministically prove**
that the model selected the semantically correct outcome for the
question asked, that any part's prose is genuinely entailed by its cited
evidence, that two natural-language `AnswerPart`s do not semantically
contradict each other, or that every material aspect of the user's
question was truly covered — "cross-part contradictions," named as a
deterministic check in an earlier version of this document, is corrected
here to what it actually is: a semantic property, governed by constrained
prompts, typed contracts, model/provider behaviour, and Stage 17.4-style
model-assisted evaluation (ADR-0023's own residual-risk posture, not
re-litigated here), never a capability this document claims Laravel's
deterministic layer possesses. Part-local validation never proves the
whole answer is complete or final, and an `AnswerPartAcceptedForDisplay`
projection is never treated as authoritative merely because it passed
part-local checks.

**Delivery persistence and authoritative persistence are two distinct
categories, not one durability question — the first draft's contradiction
(provisional parts "are not durably persisted" alongside a requirement for
a "durable, replayable delivery event log") is resolved by naming both
explicitly:**

**Delivery persistence** — bounded, scoped to one `GenerationRun`,
sequenced, replayable after browser reconnect, may contain an
`AnswerPartAcceptedForDisplay` projection, explicitly non-authoritative,
never queried as conversation history, subject to bounded retention and
expiry. Illustrative shape, not a required table name:

```text
ChatDeliveryEvent
  generation_run_id
  sequence
  event_type
  provisional          # true for AnswerPartAcceptedForDisplay projections
  safe_payload
  created_at
  expires_at
```

**Authoritative conversation/grounding persistence** — the ASSISTANT
`Message`, `GeneratedAnswer`, `AnswerPart`s, `EvidenceSnapshot`s and
citations — written only after whole-answer validation passes, in one
atomic transaction:

```text
terminal GenerationCompleted event
        ↓
Laravel whole-answer validation
        ↓
one atomic transaction:
  assistant Message (kind = GROUNDED_ANSWER)
  GeneratedAnswer
  authoritative AnswerParts
  EvidenceSnapshots
  citations
  GenerationRun → COMPLETED
  final AnswerCompleted delivery event
```

A provisional `AnswerPartAcceptedForDisplay` projection may therefore be
stored as a `ChatDeliveryEvent` without ever becoming an authoritative
`AnswerPart` row — the two never share a table, a write path, or a
promotion step. There is no "promote provisional parts in place"
mechanism; the authoritative write is a fresh, complete write derived from
the terminal event's `GenerationResult`, never a mutation of anything
provisional.

**If whole-answer validation fails after parts were displayed
provisionally, nothing is promoted.** The run terminates as `FAILED` (or
`GenerationFailed`'s specific category — see "Failure taxonomy"); a
terminal failure/retraction `ChatDeliveryEvent` is durably deliverable;
provisional delivery events remain explicitly non-authoritative; the UI
retracts or replaces the provisional content — it must not remain on
screen looking like a finished answer; provisional text never enters a
future contextualisation window; and delivery-event retention eventually
removes the provisional payload entirely, on the same bounded schedule as
any other delivery event. What the user *saw* cannot be undone, but what
the system *claims happened* is never allowed to drift from what actually
happened. This bounded delivery log is not general-purpose event sourcing:
its only job is reliably communicating run state and in-progress
projections to clients within a bounded, expiring window — the domain
state (`GenerationRun` plus the authoritative tables) remains the single
source of truth for what happened.

`EvidenceSnapshot`s are created atomically with the accepted `AnswerPart`s
at whole-answer-completion time, from the same durable evidence records
already resolved for the authoritative `GenerationRequest` — never lazily
on first citation click and never ahead of validation, both of which would
risk persisting evidence tied to an answer that never actually completed.

**Does this require a clarification to ADR-0023's "authoritative"
wording?** A narrow one, stated here rather than by editing ADR-0023
itself (this repository's own accepted-ADR immutability convention, and
the lesson already learned once during Phase 17's own reconciliation):
ADR-0023's invariant governs when content is presented **as the finished
answer**. It does not, and was never written to, address a state that did
not exist at the time: content shown **explicitly and visibly marked as
still in progress**, belonging to a run that has not yet reached
whole-answer completion. This document's position is that
`AnswerPartAcceptedForDisplay` projections — clearly distinguished in the
UI from a completed answer, persisted only as non-authoritative,
bounded-retention delivery events, retractable in full if whole-answer
validation fails — do not violate ADR-0023's invariant, because they are
never presented as authoritative in the first place. This is recorded as
this document's own decision, binding for Phase 18, not as an edit to
ADR-0023's text.

**The security/trust guarantee, stated plainly:** raw provider tokens are
never exposed to the browser; only complete parts that passed part-local
application validation are provisionally displayed; provisional content
is never represented as a completed answer; failed provisional content is
retracted; only the whole-answer-validated representation is ever
persisted as authoritative history.

### Citation behaviour while streaming

Unchanged in spirit from ADR-0023: the model never generates user-facing
citation numbering, and citation display never exposes `AnswerPart` IDs,
evidence handles, internal source IDs, model-generated "Claim 1"
structures, or provider-specific identifiers.

**Provisional citations cannot be persistent `EvidenceSnapshot`/citation
identities — corrected here, since claiming otherwise would contradict
this document's own persistence model.** `EvidenceSnapshot`s are created
only inside the final atomic authoritative transaction (see "Per-part and
whole-answer validation" above); a provisional `AnswerPartAcceptedForDisplay`
event can exist long before that transaction ever runs, and the run may
still ultimately fail and create no snapshots at all. A persistent
identity therefore cannot be required in a provisional event without
either persisting snapshots early — rejected, since it would let a
snapshot exist for an answer that never actually completes — or silently
contradicting the persistence model. The corrected flow:

```text
Python AnswerPartCandidate
  text
  request-scoped evidence_ids

        ↓ Laravel part-local validation

AnswerPartAcceptedForDisplay
  text
  run-scoped application citation references   (opaque, not persistent)
  safe provisional source projections

        ↓ terminal event, whole-answer validation passes

authoritative transaction
  EvidenceSnapshots
  AnswerParts
  persistent citations

        ↓

AnswerCompleted
  final persistent citation identities / reconciliation mapping
```

Laravel resolves a request-scoped `evidence_id` against the authorised
evidence mapping it already owns (established at `GenerationRequest`
assembly) and emits a **run-scoped, opaque citation reference** for
provisional display — application-owned, scoped to the `GenerationRun`,
explicitly not a provider evidence handle, not a persistent
`EvidenceSnapshot` ID, not independently usable to retrieve cross-workspace
data, and suitable only for provisional UI rendering and
`ChatDeliveryEvent` replay. On successful completion, Laravel creates the
persistent `EvidenceSnapshot`s and citations atomically, exactly as
already described, and the `AnswerCompleted` event carries the final,
persistent citation identities together with a reconciliation mapping the
frontend uses to resolve its provisional run-scoped references to the
permanent ones. On failure, provisional citation references simply expire
with their `ChatDeliveryEvent`s on the same bounded retention schedule as
everything else provisional — they never become persistent citations, and
`EvidenceSnapshot`s are never persisted early merely to make provisional
citation display simpler.

### Application-owned progress events

Before the first `AnswerPartAcceptedForDisplay` event arrives, the browser
may display
staggered progress text sourced from a small, stable set of durable run
stages (see "Failure taxonomy" for the full lifecycle enum), projected as
a stage key plus a display key the frontend owns copy/localisation for —
never raw provider text, never a fabricated assistant `Message`. Progress
events must reflect genuine orchestration state transitions, never a timer
loop invented to look busy; the UI may *delay* showing a stage to avoid
flicker on fast requests, but must never claim work is happening that
isn't. Progress projections never reveal raw contextualised query text,
evidence content, unauthorised document titles, provider internals, or
other sensitive tenant information — they carry a stage enum and nothing
else content-bearing.

### Transport: Server-Sent Events

**SSE is the correct choice, and WebSockets are rejected, not by default
but on the actual shape of this interaction.** The traffic is
fundamentally one-directional (browser → one command; Laravel → an ordered
event stream for that run), which is exactly SSE's shape and not
WebSockets'. Compared against the credible alternatives:

- **Streamed response to the original POST** — rejected: ties the event
  stream's lifetime to one HTTP request/response cycle and one connection,
  which directly conflicts with "connection-independent execution" below
  (reconnect requires a resource — the run — addressable independently of
  any one request).
- **WebSockets** — rejected for V1: bidirectional capability this
  interaction does not need (cancellation is a separate authenticated
  command, not a stream message); heavier infrastructure and proxy/load-
  balancer handling than plain HTTP; no capability gap versus SSE for this
  shape of traffic. Not rejected because "chat implies WebSockets" — that
  reasoning is explicitly not the basis here.
- **Long polling** — rejected: strictly worse latency and complexity than
  SSE for delivering an ordered event sequence, with none of SSE's native
  reconnect/last-event-ID support.

**Authentication implication worth deciding here, not deferred:** native
`EventSource` cannot set custom headers, so it cannot carry a bearer token
or CSRF header directly — the two credible options are (a) cookie-based
session auth (this platform's existing Sanctum/Fortify session model,
ADR-0005, already fits, since the SSE connection is same-origin) or (b) a
fetch-based SSE client that can set headers, trading `EventSource`'s
native reconnect for manual reconnect logic. Given this platform already
uses Sanctum's stateful session cookies for first-party auth, **cookie-
based `EventSource` is the natural fit and is recommended**, deferring the
exact mechanics (event-stream endpoint auth middleware, CSRF exemption
scope) to R18-S03 as implementation detail that does not change this
architectural choice.

The likely API shape (illustrative, not a specification): `POST` a user
message → Laravel durably creates the `Message` and `GenerationRun`,
enforces the single-active-run invariant, and returns their identifiers →
the queued worker calls Python over `generation.stream` (or
`generation.answer` for the synchronous-fallback case) → the client opens
an authenticated SSE connection scoped to that run → progress,
`AnswerPartAcceptedForDisplay` projections, citations, and a terminal
(completion/clarification/failure/cancellation) event flow in order,
sourced from the single Python response the worker is consuming.
Cancellation is a separate authenticated command, not a message sent over
the SSE connection.

### Connection-independent execution and reconnect

**A `GenerationRun` is never owned by the browser connection.** Its
lifecycle: submit → durably create the USER `Message` and `GenerationRun`
→ execute independently of any browser connection → durably record
authoritative state and a resumable event sequence → project events to
zero or more authorised browser connections for that run.

This requires durable, connection-independent execution — and, confirmed
by direct inspection: **Laravel's framework queue configuration exists
(`apps/api/config/queue.php`), but the application has no
application-owned queued `GenerationRun` jobs or operated
conversation-generation worker path yet** — corrected here from an
earlier over-broad claim that no queue infrastructure existed at all.
`apps/api/app/Jobs/` does not exist, and neither `ShouldQueue` nor
`Bus::dispatch`/`Queue::` is used anywhere in `apps/api/app` today; the
framework capability is configured but unused, not absent. The only
existing precedent for "work that survives the
initiating process" is the transactional-outbox-plus-SQS-plus-external-
worker-lease pattern built for ingestion (ADR-0008, ADR-0015, ADR-0016) —
`IngestionEventClaim`'s claim/lease/renew/complete/fail lifecycle, designed
for a long-running, externally-polled, potentially minutes-long worker
attempt.

**This document recommends introducing Laravel's queue system
(`ShouldQueue`) for the first time, as a deliberate, justified new
pattern, rather than reusing the ingestion lease/claim machinery.** The
two problems are shaped differently: ingestion's worker independently
polls SQS and needs a lease it renews over a long, resumable attempt with
no initiating HTTP request to return to; a `GenerationRun` is
latency-sensitive, initiated synchronously by a Laravel request that
already knows exactly what work to do (one `generation.stream` or
`generation.answer` call plus persistence), and needs only to keep running
after the *browser* disconnects — not after *Laravel's own process* is
asked to do the work. A queued job dispatched at message-submission time,
independent of the SSE connection that happens to be watching it, is the
proportionate mechanism; adopting the full outbox/lease/claim shape for
this would be exactly the kind of heavier-than-necessary machinery this
platform's own engineering philosophy argues against elsewhere.

**`ShouldQueue` alone is not a durability guarantee, and this document does
not claim otherwise.** The concrete, architectural requirements a
production deployment must satisfy: a durable queue backend is used (not
Laravel's synchronous `sync` driver, which offers no independence from the
initiating request at all); the USER `Message` and the initial
`GenerationRun` row are committed to the database *before* the job becomes
executable, and dispatch happens after that commit (or through an
equivalent transactional-outbox-style guarantee) — never dispatched inside
the same transaction that might still roll back; the queued job owns the
Python call independently of any browser/SSE connection, and browser
disconnect never cancels it; the `GenerationRun` row remains the
authoritative record of run state throughout; queue-level retries (the
queue driver's own retry-on-failure behaviour, distinct from user-initiated
"Retry") must never be allowed to begin a second, duplicate provider call
for the *same* attempt — the worker rechecks the run's current status
before calling Python, and a run already in a terminal or in-flight state
is not re-executed; the single-active-run invariant (see "Linear-
conversation concurrency and message ordering" below) is rechecked by the
worker itself, not only enforced at submission time; provider-call
timeout, overall run/orchestration timeout, and user-initiated cancellation
are coordinated so that exactly one of them determines a given run's
outcome, never a race between two mechanisms independently deciding a run
failed; usage already consumed by the provider is retained wherever it was
reported, even for a run that ultimately fails; and terminal persistence
(the atomic authoritative write in "Per-part and whole-answer validation"
above) is idempotent — a worker retry that reaches the terminal write twice
for the same run must not create two `GeneratedAnswer`s.

**The V1 guarantee is stated explicitly, including what it does not
claim:**

```text
The run survives browser disconnection.

A Laravel worker crash during an active provider stream may terminate
that attempt. Recovery produces a controlled failed/retryable state;
V1 does not claim transparent mid-stream resumption of the same
provider call.
```

If the selected queue driver's own semantics cannot automatically detect
and mark a hard worker crash (process killed mid-job, not a clean
exception) as `FAILED` within a bounded time, a bounded, periodic
stale-run reconciliation check — a run stuck `GENERATING` past a
configured deadline is swept to `FAILED` and made retry-eligible — closes
that gap without importing the ingestion lease/claim machinery wholesale;
this is R18-S02/S03's implementation call, not designed in further detail
here. If a future requirement demonstrates `GenerationRun` execution needs
transparent mid-stream resumption after a process restart (not just
surviving a browser disconnect), extending toward the lease pattern is the
additive next step — not designed here, because no evidence yet requires
it.

**The minimum durable state for reconnect**, deliberately not a
general-purpose event-sourcing platform: the `GenerationRun` row itself is
the authoritative state (`status`, timings, terminal outcome/failure), plus
a durable, ordered, replayable **delivery event log** scoped to that run
(sequence number, event type, payload, written durably before being
published to any live SSE connection) retained for a bounded window
sufficient for reconnect (the exact retention window is R18-S03's call;
architecturally it must be bounded, not indefinite). On reconnect, a
client presents its last-received sequence number (SSE's native
`Last-Event-ID` mechanism fits this directly); Laravel replays durable
events after that sequence, then continues live. **Generation lifecycle**
(did the work finish) and **delivery lifecycle** (did this browser receive
every event live) are explicitly distinct: a run can complete perfectly
while a specific browser connection missed every event live and only
recovers on reconnect-replay; stream delivery failure never implies
generation failure, and vice versa.

### Linear-conversation concurrency and message ordering

The first draft stated V1 conversations are linear but never defined what
happens when submission or retry could create concurrent runs — resolved
explicitly here, since "linear" is a claim about data shape, not about
runtime behaviour, and the two can drift apart without an explicit
concurrency invariant.

**At most one non-terminal `GenerationRun` exists per `Conversation` at any
time.** A new ordinary USER `Message` is not accepted while the preceding
run for that conversation is non-terminal — the composer is disabled or
otherwise prevented from submitting client-side, and Laravel rejects a
submission attempt server-side regardless of client state, because the
invariant is enforced at the point of truth, not by UI convention alone.
Where the repository's transaction/database capabilities support it, this
is enforced with a database constraint or transaction-scoped lock (for
example, a partial unique index on `(conversation_id)` for non-terminal
`GenerationRun` rows, or an equivalent serialising check inside the
message-submission transaction) — not left to application-level
convention alone, for the same reason ADR-0017's structural integrity
requirements are enforced at the database layer rather than trusted to
application code.

Retry is allowed only for a `GenerationRun` in an eligible terminal state
(`FAILED` or `CANCELLED`) — never for a `COMPLETED` or
`CLARIFICATION_REQUIRED` run. Two retry requests carrying different
idempotency keys must not be allowed to create two concurrent active runs
for the same USER `Message`; the single-active-run invariant above already
prevents this, rechecked at both submission and worker-execution time (see
"Connection-independent execution" above). Once a run for a USER `Message`
has successfully produced its grounded answer, the failure/timeout Retry
operation is no longer eligible for that message — **regenerating an
already-successful answer is a distinct, future/product-level operation,
not silently conflated with failure retry**, and is out of this document's
scope to design. Cancellation must reach a terminal or safely resolved
state before a new run for the same conversation may begin. V1 must never
allow two runs for one USER `Message` to both produce competing successful
assistant replies — the single-active-run invariant, combined with retry
being terminal-state-only, makes this structurally impossible rather than
merely unlikely.

**Deterministic message ordering.** `Message.ordinal` is a monotonically
assigned, per-conversation sequence, with `UNIQUE(conversation_id, ordinal)`
enforced at the database layer — not timestamp ordering alone, which is
vulnerable to clock skew and same-millisecond collisions under concurrent
writers. `ordinal` supports: deterministic history-window selection (the
bounded three-turn window in "Contextualisation" above is defined in terms
of `ordinal`, not `created_at`); reliable, unambiguous display order;
correct behaviour across retry and reconnect (a retried run's eventual
assistant `Message` still receives the next `ordinal` in sequence, never
reordering what came before it); and the future branching migration named
in "Conversation branching" below, which can introduce non-linear
structure without needing to renumber or reinterpret any existing `ordinal`
value. No branching is implemented now — `ordinal` is simply the ordering
primitive linear V1 needs regardless, chosen so it does not have to be
replaced later.

### Timeout, retry and idempotency

A timeout produces a recoverable failure state with a retry action — never
an assistant answer, never `INSUFFICIENT_EVIDENCE` (a genuine successful
semantic outcome, not an infrastructure failure — see "Failure taxonomy"),
and never a completed `GeneratedAnswer`.

Retry semantics: the same persisted USER `Message` is reused; retry creates
a new, immutable `GenerationRun` with `retry_of_run_id` set; the failed run
is never overwritten or erased; retry performs fresh contextualisation and
fresh authorised retrieval (never reuses the failed run's evidence, per the
governing principle); retry records its own full lineage; retry uses
whatever configuration is current at retry time, unless a future,
explicitly justified reproducibility mode is introduced; only a run
reaching `COMPLETED` ever produces a `GeneratedAnswer` (with its
`GROUNDED_ANSWER` `Message`) — a run reaching `CLARIFICATION_REQUIRED`
produces a `CLARIFICATION` `Message` with no `GeneratedAnswer`, and
`FAILED`/`CANCELLED` runs produce neither.

**Idempotency needs two separate scopes**, reusing the platform's existing
primitive — `IngestionEventClaim`'s "globally-unique UUID column enforced
by a DB unique constraint" pattern
(`apps/api/database/migrations/2026_07_29_000005_create_ingestion_event_claims_table.php:16`)
— without its heavier lease machinery:

- **Message-submission idempotency**: a client-supplied idempotency key
  (UUID), unique per conversation, protecting against duplicate `Message`
  creation from a retried POST (network retry, double-click).
- **Retry idempotency**: a separate client-supplied key scoped to
  "retry of run X," protecting against duplicate `GenerationRun` creation
  from a repeated retry click — distinct from message-submission
  idempotency because retrying an existing message must never accidentally
  be interpreted as submitting a new one, and vice versa.

If `AnswerPartAcceptedForDisplay` projections were shown before a timeout
or failure, they are retracted exactly as described in "Per-part and
whole-answer validation" above — never silently left presented as
complete, never entering future conversation history.

### Cancellation

A user-initiated Stop is a separate authenticated command (not an SSE
message). Laravel records cancellation intent immediately; upstream work
is cancelled where the provider/runtime genuinely supports it — this
document does not promise provider-call cancellation guarantees the
provider cannot actually give; tokens already consumed remain part of
usage/accounting regardless of outcome; the run reaches an explicit
terminal (or well-defined transitional "cancelling") state; partial
content never becomes a completed `GeneratedAnswer` and never enters
future conversation context; retry remains available afterward. Distinct,
explicitly named states: cancellation requested; upstream cancellation
acknowledged; generation completed before cancellation took effect (a real
race, not an error — the answer is simply accepted as normal); cancellation
unavailable (the provider call already passed the point where the runtime
can stop it); browser stream disconnected without any cancellation
intent (the run keeps running — see "Connection-independent execution").

### Failure taxonomy

Business answer outcomes and execution failures are never collapsed.
`ANSWERED`/`QUALIFIED`/`INSUFFICIENT_EVIDENCE` remain, unchanged from
ADR-0023, successful semantic outcomes — `INSUFFICIENT_EVIDENCE` is not an
infrastructure failure and `CLARIFICATION_REQUIRED` is not insufficiency
and not necessarily an error.

**`GenerationRun` lifecycle (durable, internal):** `QUEUED` →
`CONTEXTUALISING` → `RETRIEVING` → `PREPARING_EVIDENCE` → `GENERATING` →
`VALIDATING` → `COMPLETED`; or → `CLARIFICATION_REQUIRED` (terminal for
this run — the user's reply starts a new run); or → `FAILED` (with a typed
`failure_code`); or → `CANCELLATION_REQUESTED` → `CANCELLED`.

**Failure taxonomy (typed, durable) — `FAILED`-state `failure_code`
values only; `CANCELLED` is not one of them, corrected from the first
draft, which listed it in both places.** `CANCELLED` is exclusively a
lifecycle terminal state (via `CANCELLATION_REQUESTED → CANCELLED`,
above) — duplicating it as a failure code would blur "the user stopped
this" with "something went wrong," which are different facts the UI and
telemetry both need to keep separate:

`CONTEXTUALISATION_FAILED`; `RETRIEVAL_FAILED` (ADR-0018's own outcome,
surfaced here); `GENERATION_CONTEXT_BUDGET_EXCEEDED` (ADR-0023, a
structural context-packing failure, explicitly distinct from both
`INSUFFICIENT_EVIDENCE` and from any provider/infrastructure failure
below — it means the evidence may be entirely sufficient but the
application could not represent it within the configured context
envelope); `PROVIDER_UNAVAILABLE`; `PROVIDER_RESPONSE_TIMEOUT` (the
provider call itself exceeded its own bounded timeout); `RUN_TIMEOUT`
(the overall `GenerationRun` — contextualising through validating —
exceeded its orchestration-level wall-clock budget, distinct from a
single provider call timing out, since a run can time out from
accumulated latency across stages even if no individual provider call
did); `PROVIDER_CONTRACT_FAILURE`; `MALFORMED_STREAM_FRAMING` (the
`generation.stream` response itself could not be parsed as a sequence of
framed events — a transport/protocol-level failure, distinct from any
individual event being invalid); `INVALID_CANDIDATE_EVENT` (a framed
event parsed, but a specific `AnswerPartCandidate` failed part-local
validation — schema, sequence, or correlation — triggering the fail-closed
behaviour in "Per-part and whole-answer validation" above);
`INVALID_CANDIDATE_EVIDENCE` (a candidate's `evidence_ids` failed
membership validation specifically — kept distinct from
`INVALID_CANDIDATE_EVENT` because an invented or unauthorised evidence
handle is a materially more serious class of violation than a malformed
event shape, and operators/evaluation need to tell them apart);
`MISSING_TERMINAL_EVENT` (the `generation.stream` response ended — the
HTTP response closed — without a `GenerationCompleted` or
`GenerationFailed` terminal event ever arriving; a protocol violation on
Python's side, not a browser-delivery concern); `GENERATION_CONTRACT_INVALID`
(the terminal `GenerationCompleted` event arrived, but its
`GenerationResult` failed whole-answer deterministic validation — see the
structural/semantic boundary above); `DETERMINISTIC_VALIDATION_FAILED`;
`PERSISTENCE_FAILED`; `INTERNAL_FAILURE`.

**Three failure classes are kept structurally distinct throughout this
taxonomy, restated explicitly here because conflating them is the
specific mistake this section exists to prevent:**

```text
Python/provider stream failed
  → PROVIDER_UNAVAILABLE, PROVIDER_RESPONSE_TIMEOUT,
    PROVIDER_CONTRACT_FAILURE, MALFORMED_STREAM_FRAMING,
    MISSING_TERMINAL_EVENT — something went wrong producing or
    transporting the generation itself.

Laravel rejected the generation contract
  → INVALID_CANDIDATE_EVENT, INVALID_CANDIDATE_EVIDENCE,
    GENERATION_CONTRACT_INVALID, DETERMINISTIC_VALIDATION_FAILED —
    Python/the provider produced *something*, but Laravel's own
    validation correctly refused to trust it.

Browser delivery interrupted while generation continued
  → STREAM_DELIVERY_INTERRUPTED — not a GenerationRun failure_code at
    all; delivery-lifecycle telemetry only.
```

**`STREAM_DELIVERY_INTERRUPTED` is not a `GenerationRun` failure code and
is never written to `GenerationRun.failure_code`, and never causes a
successfully continuing run to be marked `FAILED`.** A browser missing
live events because its connection dropped is exactly the "delivery
lifecycle is distinct from generation lifecycle" invariant already
established in "Connection-independent execution" above; the run keeps
running, and reconnect-replay (not a failure state) is how the browser
recovers. A missing or invalid *Python* terminal event
(`MISSING_TERMINAL_EVENT`, `MALFORMED_STREAM_FRAMING`) is the opposite
case — a generation/protocol failure that legitimately ends the run —
never confused with a browser merely losing its live connection.

**Retry-eligible terminal states, explicit:** `FAILED` and `CANCELLED`
are retry-eligible (see "Linear-conversation concurrency" above).
`CLARIFICATION_REQUIRED` is not retried — the user's reply starts a new
run instead. `COMPLETED` is not retried — a successful answer has no
failure to retry, and regenerating one is the distinct, out-of-scope
future operation already named above.

Not every durable code is safe to project to the browser verbatim —
`GENERATION_CONTRACT_INVALID` or `PERSISTENCE_FAILED`, for instance, are
internal classifications useful for operators and evaluation, not user-
facing detail. The browser-facing projection carries only what the UI
needs to decide: whether an answer completed; whether clarification is
needed; whether a failure is retryable; whether the run was cancelled;
whether provisional content must be discarded. Mapping every durable code
to a safe projection is R18-S02/S03 implementation detail; the
distinction between durable-internal and safe-projected is the
architectural commitment made here.

### Deletion and retention

Conversations follow the platform's one real, implemented lifecycle
convention — an explicit enum `status` state machine, mirroring ADR-0007's
`DELETING → DELETED` shape — rather than introducing Eloquent
`SoftDeletes`/`deleted_at` as a new, inconsistent pattern nothing else in
this codebase uses. `Conversation.status`: `ACTIVE` → `ARCHIVED` (ordinary,
reversible, product-level hide) and `ACTIVE`/`ARCHIVED` → `DELETING` →
`DELETED`. That much is retained from the first draft. **What `DELETED`
actually means was left vague — corrected here by distinguishing five
things the first draft conflated into one word:**

- **Product visibility**: a `DELETED` (or `DELETING`) conversation is
  removed from ordinary listing/access immediately on entering `DELETING`
  — the same visibility change `ARCHIVED` already makes, but one-way.
- **Generation/cancellation barrier**: entering `DELETING` prevents new
  `Message`s, new `GenerationRun`s, and new stream connections for this
  conversation; any `GenerationRun` already in flight receives
  cancellation intent (see "Cancellation" above) and — the specific rule
  this document adds — **may not commit an authoritative answer after the
  deletion barrier has been entered**, even if the underlying provider
  call was already in progress and would otherwise have completed
  successfully. This is a stricter cancellation-barrier reading than
  ADR-0007's document deletion needed, because a conversation deletion
  barrier must prevent new content-bearing writes, not merely new
  processing.
- **Removal of content-bearing data**: deletion processing removes, or
  irreversibly sanitises, every content-bearing projection — USER and
  ASSISTANT `Message` display text, `AnswerPart` text, `insufficiency_reason`
  text, clarification question text, and any provisional
  `ChatDeliveryEvent` payloads still within their retention window.
  `GeneratedAnswer`s, `AnswerPart`s, citations and `EvidenceSnapshot`s are
  removed according to referentially safe application-level deletion
  (children before parents, inside the same asynchronous orchestration
  ADR-0007 already established the shape of). **`DELETED` does not mean
  "all content remains indefinitely but normal queries hide it"** — that
  would just be `ARCHIVED` under a different name, and this document does
  not conflate the two.
- **Minimal tombstone/audit data**: a minimal `Conversation` tombstone
  (its identifier, `workspace_id`, timestamps, and the deletion audit
  event itself) may remain after content removal, for the same
  reconciliation and business-audit reasons ADR-0006 and ADR-0007 already
  retain a row post-deletion rather than hard-removing it immediately.
  **The tombstone must not contain conversation content** — no message
  text, no citations, no evidence — only enough identity and timing to
  prove deletion happened and satisfy ADR-0006's business-audit
  obligation (*"document administration"* is already a named audited
  action; conversation deletion is the same category of event).
- **Hard-purge policy remains explicitly deferred**, consistent with
  ADR-0006 and ADR-0007 both already deferring theirs for their own
  resources — this document does not invent a retention period neither
  of those established, and does not claim one exists. No conflict with
  either ADR was found: both already say "deferred," and this document
  says the same, for the same reason.

**Stream/replay behaviour once `DELETING` or `DELETED`:** an SSE
connection attempt, reconnect, or delivery-event replay request against a
conversation in `DELETING` or `DELETED` is rejected exactly as ADR-0006's
`404`-not-`403` concealment already requires for any resource a requester
should not be able to distinguish "doesn't exist" from "you can't see
it" for — the same tenant-scoped, identity-is-not-authority discipline
"Tenancy and security" below applies everywhere else. An in-flight stream
that was already connected when `DELETING` was entered receives the
cancellation/terminal-failure event, never a silent connection drop.

Cross-workspace access to archived or deleted conversation artefacts
remains prohibited exactly as for any other tenant-owned resource.

### Tenancy and security

Every access point — conversation lists, individual conversations,
messages, runs, retry, cancellation, stream connection and reconnection,
event replay, generated answers, citations, `EvidenceSnapshot`s, title
generation, contextualisation history — inherits ADR-0006's full
defence-in-depth stack unchanged, including `404`-not-`403` concealment.
Identifiers are never authority. `workspace_id` is denormalised onto
`Message`, `GenerationRun` and every other child row rather than resolved
only via a join to `Conversation`, so tenant-scoped queries and RLS
policies can be written directly against each table — the same
denormalisation reasoning ADR-0006 already applies elsewhere for
enforceable tenant constraints, not a new pattern invented here.

Provider-boundary data minimisation is unchanged from ADR-0023: only the
bounded context genuinely required for contextualisation and generation
crosses the provider boundary — never full conversation history beyond the
configured window, never other workspaces' anything. Retrieved evidence
remains untrusted data. Conversation context grants the model no
autonomous retrieval, browsing, state-changing tools, or provider-native
tool capability — unchanged from ADR-0023's own prompt-injection boundary,
extended here to cover conversational context the same way.

### Observability and privacy

Inherits ADR-0012 unchanged: the Collector pattern, allowlist-first
privacy, and *"a telemetry or instrumentation failure never causes a
user-facing request to fail."* Safe, allowlisted signals for this domain:
conversation/message/run identifiers; safe workspace correlation;
distributed correlation ID; contextualisation outcome (`RESOLVED`/
`CLARIFICATION_REQUIRED`, never the resolved query text); history-turn
count supplied; contextualisation token count; retrieval plan/version;
evidence count; generation fingerprint; per-stage timings (queued, first
progress, first-part-accepted-for-display, completed); `AnswerPart` count; citation
count; provider usage; retry lineage; cancellation requested/acknowledged;
reconnect count; terminal status; safe failure code. Never recorded by
default: raw questions, raw resolved queries, conversation titles, message
text, evidence content, or raw provider request/response bodies — capturing
any of those, even for streaming debugging, requires an explicit, separate,
secure mechanism, never "ordinary telemetry because streaming is hard to
debug."

### Generation lineage and usage

ADR-0023's `generation_fingerprint` (provider, model, contract version,
prompt version, adapter version, quality-affecting configuration) is
preserved unchanged. Phase 18 extends lineage only where it introduces
genuinely new quality-affecting components: `contextualiser_version`
(contract/prompt/model/configuration for contextualisation, mirroring
ADR-0023's own prompt-versioning discipline); a conversation-context
policy version (turn count, token ceiling — whatever the configured
policy actually is at run time); a streaming/orchestration contract
version where the candidate-event mechanism itself is versioned. Usage
metadata is recordable per `GenerationRun`, including failed and cancelled
attempts wherever the provider reports usage for them — never attached
only to successful answers, because a timeout or cancellation can still
have consumed real provider resources.

### Candidate and browser event separation

Two event families, never conflated, mirroring the trust transition
already established in "Per-part and whole-answer validation" above. Both
travel over the single authenticated path this document defines — the
first over `rc1`'s `generation.stream` response, the second over the
Laravel-to-browser SSE connection — never over an independent channel:

```text
GenerationStreamEvent (Python-to-Laravel, over rc1 generation.stream —
                        entirely non-authoritative, including its
                        terminal member; never reaches the browser
                        directly)
  = AnswerPartCandidate
  | GenerationCompleted           # the terminal event; a complete, typed,
                                   # provider-neutral report of what
                                   # generation produced — carries the full
                                   # candidate GenerationResult, but is a
                                   # report, not an application decision
  | GenerationFailed              # a typed execution result, not an
                                   # application persistence decision

ChatStreamEvent (Laravel-to-browser, over SSE, application-owned)
  = RunProgress
  | AnswerPartAcceptedForDisplay   # provisional — see "authoritative" clarification above
  | AnswerCompleted                # whole-answer validation passed AND persisted; final
  | ClarificationRequired
  | RunFailed
  | RunCancelled
  | Heartbeat                     # only if idle-connection timeouts in the actual
                                   # deployment environment justify one
```

**No `GenerationStreamEvent` is ever application-authoritative, including
`GenerationCompleted` — corrected here from language earlier in this
document's own revision history that described it as authoritative merely
because it is terminal.** Python never decides that generated content is
authoritative; that decision belongs to Laravel alone, exactly as
"Reconciled Laravel/Python ownership boundary" already states. Provider-
native events stop at the Python adapter; `GenerationStreamEvent`s stop at
Laravel — `AnswerPartCandidate`s become `AnswerPartAcceptedForDisplay`
projections only after part-local validation, and `GenerationCompleted` is
subjected to whole-answer deterministic validation exactly as any other
untrusted input would be, never accepted merely because it arrived as the
terminal event of an otherwise well-formed stream. Only once that
validation passes, and the atomic authoritative write has actually
committed, does Laravel emit `AnswerCompleted` — the one genuinely
authoritative event in this entire diagram. Only
application-owned, safe `ChatStreamEvent`s ever reach the browser. Within
one run, `ChatStreamEvent`s carry a monotonic per-run sequence number; the
terminal event (`AnswerCompleted`, `ClarificationRequired`, `RunFailed`, or
`RunCancelled`) is exactly one per run and closes the stream — a client
that has received a terminal event for a run knows the stream is genuinely
finished, not merely idle. Duplicate delivery (a reconnect replaying an
already-seen sequence number) is handled by the client de-duplicating on
sequence number, the same discipline this platform already applies to
ingestion's at-least-once delivery. Persisted domain state (the
`GenerationRun` row plus the atomic authoritative write) is authoritative
for *what happened*; the durable `ChatDeliveryEvent` log is authoritative
for *what has been told to clients so far* — the two are deliberately
separate concerns, and this document does not build a general-purpose
event-sourcing platform to reconcile them, only the bounded reconnect
mechanism "Connection-independent execution" already describes.

### Conversation branching, editing and regeneration

V1 conversations are strictly linear. Out of scope for V1: editing an
earlier `Message` in place; visible branches; branch-head selection;
arbitrary deletion of individual historical turns; branch navigation UI.
Editing an earlier message is, semantically, branch creation — it must
never rewrite history beneath already-grounded answers, so it is not
attempted at all in V1 rather than attempted unsafely. Retry/regenerate is
explicitly **not** a branch — it is another immutable `GenerationRun` for
the same USER `Message`, exactly as "Timeout, retry and idempotency"
describes.

**The future branching seam, verified to require no rewrite of existing
history:** `GenerationRun.user_message_id` and `GenerationRun.retry_of_run_id`
already make "which attempts belong to which message, and which attempt
retried which" fully explicit and immutable; `Message.in_reply_to_message_id`
already makes reply structure explicit. A future branch feature can be
built additively — for example, by allowing more than one non-retry
`GenerationRun`-originated reply chain per message, or by introducing a
`ConversationBranch` aggregate that groups existing `Message` chains — without
altering any existing row's meaning, because nothing in this model assumes
linearity as a stored invariant; linearity is simply the only shape V1's
application logic constructs. No `ConversationBranch` table is introduced
now, because nothing yet demonstrates the need.

### Title generation and Phase 18 fit

**V1 does not require an LLM-generated title, and this document recommends
against building one now.** The smallest safe direction: `Conversation.title`
begins as a deterministic, bounded projection of the first USER `Message`
(for example, its first N characters, truncated on a word boundary — the
exact bound is R18-S02 implementation detail, not fixed here), and is
user-editable thereafter. No provider call is implied merely by the
existence of a title field, and title generation is explicitly **not**
modelled as a `GenerationRun` — it participates in none of the outcome
taxonomy, lineage, or streaming machinery this document defines, because
it makes no factual claim to ground. If a genuine, accepted requirement
for model-generated titles emerges later, that generation would need the
same data-minimisation and failure-taxonomy discipline as everything else
in this document — noted here only so a future implementer does not treat
it as a free, unaudited side channel — but designing that mechanism is
explicitly deferred, not merely left as an unstated assumption.

## Architectural invariants

- A previously generated answer is never trusted as evidence for a new
  answer; every factual claim is freshly retrieved and authorised, every
  time, regardless of conversation history.
- Laravel retrieves; Python never retrieves, never resolves tenancy, never
  authorises, and never chooses evidence — unchanged and reaffirmed
  against Stage 18.2's stale preliminary wording. Laravel never calls a
  model provider directly and never becomes a second provider adapter.
- No independent side-channel exists between Python and Laravel. Exactly
  one authenticated response per run — `rc1`'s `generation.stream` (framed,
  incremental) or `generation.answer` (synchronous, unchanged) — is ever
  the source of provider-neutral generation events; Redis and any other
  broker are not part of this path.
- `generation.answer`'s contract is untouched by this document.
  `generation.stream` reuses `rc1`'s existing principal, key ring and
  signature format unchanged; only the response framing differs.
- No generated content is presented as the finished, authoritative answer
  before whole-answer validation passes. `AnswerPartAcceptedForDisplay`
  projections may be shown only while visibly marked as in-progress, are
  persisted only as bounded, non-authoritative delivery events — never as
  conversation history — and are fully retractable if whole-answer
  validation fails.
- `AnswerPart`s (for `ANSWERED`/`QUALIFIED`) and `insufficiency_reason`
  (for `INSUFFICIENT_EVIDENCE`) remain the only sources of a
  `GROUNDED_ANSWER` Message's display text; a `CLARIFICATION` Message's
  display text comes only from its run's `ContextualisationResult`. All
  are written once, atomically, never independently generated or
  independently editable.
- Delivery persistence (bounded, replayable, non-authoritative) and
  authoritative persistence (the atomic write on whole-answer validation)
  are structurally distinct tables and write paths, never one mechanism
  wearing two names.
- A `GenerationRun`'s execution is never owned by the browser connection;
  generation lifecycle and delivery lifecycle are tracked and reasoned
  about separately. `ShouldQueue` alone is not claimed as a durability
  guarantee — the specific V1 guarantee (survives browser disconnect;
  does not claim transparent mid-stream worker-crash resumption) is
  stated explicitly, not implied.
- Only a `GenerationRun` reaching `COMPLETED` produces a `GROUNDED_ANSWER`
  Message and `GeneratedAnswer`; only a run reaching
  `CLARIFICATION_REQUIRED` produces a `CLARIFICATION` Message; failed or
  cancelled runs produce neither.
- Only `Message`s produced by a run reaching `COMPLETED` or
  `CLARIFICATION_REQUIRED` ever enter a future contextualisation window;
  failed, cancelled, partial, or provisional content never does — and a
  `CLARIFICATION` Message is context but never evidence.
- At most one non-terminal `GenerationRun` exists per `Conversation`;
  retry is eligible only from `FAILED` or `CANCELLED`; two runs for one
  USER `Message` never produce competing successful replies.
- `CANCELLED` is a lifecycle terminal state, never also listed as a
  `failure_code`; `STREAM_DELIVERY_INTERRUPTED` is delivery telemetry and
  never marks a continuing `GenerationRun` as failed.
- Message-submission idempotency and retry idempotency are separate,
  non-conflatable scopes.
- Conversation deletion follows the platform's one established lifecycle
  convention (an explicit `DELETING → DELETED` state machine), not
  Eloquent soft-deletes; `DELETED` means content-bearing data is removed
  or sanitised, not merely hidden from ordinary queries.
- Every conversation-domain access point inherits ADR-0006's full
  defence-in-depth tenancy enforcement, including `404`-not-`403`
  concealment, unchanged.
- No raw provider request/response body, raw question, raw resolved
  query, conversation title, or evidence content is captured by default
  telemetry.

## Alternatives considered

### Message directly containing the complete grounded-answer representation

Rejected. Collapses the conversation domain and the grounding domain into
one lifecycle, forcing a `Message` to exist before an answer is validated
(or forcing validation before the `Message` can exist at all, which
reintroduces exactly the "fabricated shell" problem this document
explicitly avoids) and losing the ability to represent a retried or failed
attempt without a `Message` at all.

### GeneratedAnswer itself acting as the assistant Message

Rejected for the same reason: it conflates "why is this trustworthy" with
"what visible turn occurred," and cannot represent a `CLARIFICATION_REQUIRED`
turn (which has no `GeneratedAnswer` at all) without an awkward special
case.

### Sending the complete raw transcript to retrieval/generation, with no bounded window or contextualiser

Rejected. Grows the provider payload unboundedly with conversation length,
directly violates ADR-0023's data-minimisation principle, and conflates
"what does history suggest the user means" with "what may the system
claim," exactly the distinction the governing principle exists to prevent.

### A generated rolling conversation summary from V1

Rejected for V1. No demonstrated requirement yet justifies the added
complexity and failure surface (a summary is itself unvalidated generated
content that could silently drift from what was actually said); bounded
raw history is simpler and sufficient until evidence shows otherwise. The
seam is preserved, not foreclosed.

### Raw provider tokens directly to the browser

Rejected, unchanged from ADR-0023's own alternatives analysis, restated
here because streaming makes the temptation concrete: unvalidated,
unauthorised content reaching a user before validation is exactly the
failure mode ADR-0023's central invariant exists to prevent.

### Buffer the complete result, then reveal it with staggered/simulated timing

Rejected. This is not streaming — it is animation of already-complete data
— and would misrepresent to the user (and to this document's own review)
what the system is actually doing. If genuine incremental delivery proves
infeasible for a given provider/model combination, the honest fallback is
to say so and deliver the complete, validated answer as one event, not to
fake incrementalism.

### Provisional raw-token display followed by reconciliation

Rejected. Displaying raw, unvalidated tokens and later "reconciling" them
against a validated result is a worse version of the raw-token rejection
above — it exposes unauthorised content first and corrects it after the
fact, rather than never exposing it unauthorised in the first place.

### WebSockets

Rejected for V1, not because the product resembles chat, but because the
actual traffic shape (one command in, one ordered event stream out,
cancellation as a separate command) has no bidirectional requirement SSE
cannot already satisfy, and WebSockets carry real additional
infrastructure and proxy-handling cost for no corresponding capability
gain here.

### A streamed response to the original POST

Rejected. Couples the event stream's entire lifetime to one HTTP
request/response, directly incompatible with connection-independent
execution and reconnect.

### Long polling

Rejected. Strictly worse latency and complexity than SSE for this shape of
traffic, with none of SSE's native `Last-Event-ID` reconnect support.

### Generation owned by the browser connection

Rejected. A closed browser tab, a dropped network connection, or a slow
client must never be able to corrupt or abort work the user asked for and
that may have already consumed real provider cost.

### Reusing the ingestion outbox/lease/claim machinery for GenerationRun execution

Rejected for V1, in favour of introducing Laravel's queue system for the
first time. The ingestion pattern solves a genuinely different problem
(externally-polled, long-running, resumable-across-worker-restart attempts
with no initiating request to return to); adopting its full weight for a
latency-sensitive, Laravel-initiated `GenerationRun` would be
disproportionate machinery for the actual requirement, which is only
"survive the *browser* disconnecting," not "survive the *server process*
restarting mid-run."

### A full `ConversationBranch` aggregate in V1

Rejected. No demonstrated V1 requirement; the immutable
`retry_of_run_id`/`in_reply_to_message_id` relationships already preserve
the seam to add branching later without rewriting existing history.

### No provider-neutral contextualisation boundary (Laravel-owned orchestration only)

Rejected — reversed from the first draft's own decision, on review.
Folding contextualisation into Laravel-owned orchestration without a typed
cross-service contract would either move provider-specific work into
Laravel (violating the reaffirmed ownership boundary: Laravel must not
call a model provider directly) or leave the actual cross-service
operation undefined in this document, forcing R18-S02 to invent it. The
boundary's justification was never "how many providers exist today" — it
is that the capability crosses the Laravel/Python service boundary at
all, the same reasoning every other provider-neutral boundary in this
platform already rests on.

### An independent Redis pub/sub side-channel for AnswerPart candidates

Rejected — this is the first draft's original recommendation, withdrawn.
Redis is already present in this stack, but availability is not an
architectural reason to introduce it into the candidate-delivery path.
The design created avoidable problems: synchronising an HTTP generation
call with an independent subscription; candidates arriving before Laravel
had subscribed; best-effort pub/sub message loss with no delivery
guarantee; a second, separate correlation/ordering concern alongside the
`rc1` call itself; orphaned candidate streams if the subscribing side
crashed or never subscribed; duplicated transport-level failure handling
(one path for the HTTP call, another for the broker); additional
authentication and authorisation questions for a channel `rc1`'s existing
discipline never covered; genuine ambiguity over whether the HTTP call or
the side-channel was the actual execution record; extra reconnect
complexity; and, most importantly, a real risk of Python beginning to
communicate around Laravel's application boundary rather than through it
— exactly the kind of drift the reaffirmed ownership boundary exists to
prevent. The single streamed `generation.stream` response achieves the
same incremental-delivery goal with none of these problems, because there
is only ever one authenticated channel per run, never two.

### Non-durable, in-memory-only provisional delivery events

Rejected. Reliable reconnect requires a client to recover what it missed
after a dropped connection; an in-memory-only projection with no durable
backing cannot survive a Laravel process boundary and would make
reconnect-replay (see "Connection-independent execution") unreliable by
construction. The bounded, replayable `ChatDeliveryEvent` log is the
smallest durable mechanism that actually satisfies reconnect without
becoming general-purpose event sourcing.

### Allowing concurrent GenerationRuns within one linear conversation

Rejected. Nothing about V1's linear conversation shape requires or
benefits from two runs racing to produce competing successful replies to
the same message, and allowing it would require conflict-resolution
machinery (which reply "wins"?) this document has no justified need to
design. The single-active-run invariant is simpler and sufficient; nothing
about it forecloses a future, deliberately-designed concurrent or
multi-agent capability if one is ever justified.

## Consequences

### Positive

- The conversation, grounding, and execution concerns each get a
  lifecycle that actually matches their real invariants, rather than being
  forced into one shape for schema convenience.
- Every previous-answer-as-evidence failure mode the governing principle
  exists to prevent is closed structurally (fresh retrieval every time),
  not by convention.
- A single authenticated `generation.stream` response resolves genuine
  incremental delivery without any side-channel, keeping exactly one
  authenticated path per run and no ambiguity about which one is the
  execution record.
- SSE plus a queued, connection-independent `GenerationRun` gives genuine
  reconnect and cancellation semantics without adopting WebSockets or the
  full ingestion lease machinery, either of which would have been
  disproportionate.
- The `DELETING`/`DELETED` and idempotency-key patterns reuse conventions
  already proven elsewhere in this codebase rather than inventing parallel
  ones.
- The reaffirmed, provider-neutral contextualisation boundary keeps
  Laravel out of provider-specific work even as a second LLM-backed
  capability (beyond generation itself) enters the system — the ownership
  boundary holds under a second real test, not just the first one.
- The single-active-run invariant makes "two competing successful replies
  to one message" structurally impossible rather than merely unlikely,
  closing a real correctness gap the first draft left open.

### Negative

- Introducing Laravel's queue system for the first time is real, new
  infrastructure surface (queue driver selection, worker process
  supervision) this platform has not needed to operate before, and the
  stated V1 guarantee explicitly excludes transparent mid-stream
  worker-crash resumption — a real, named gap, not a silent one.
- `generation.stream` is a genuinely new `rc1` purpose and response
  contract (not reused from Phase 17) that R18-S03 must design and build
  carefully — incremental JSON parsing on the Python side, framed-event
  consumption on the Laravel side, and the bounded `ChatDeliveryEvent` log
  — this document scopes it but does not eliminate that work.
- The reaffirmed contextualisation boundary is a second, genuinely new
  provider-neutral capability (beyond `Generator`) that R18-S02 must build
  — smaller than `Generator`, but real new surface, not free.
- Provisional, retractable display is a real UX complexity R18-S04 must
  handle honestly (visibly marking in-progress content, cleanly retracting
  it on failure) — a simpler "wait for the whole answer" UX would have
  been easier to build, and this document deliberately chooses the harder,
  more honest path instead.
- Single-active-run semantics simplify V1 correctness but mean a user
  cannot compose a new message while a run is in flight — a deliberate,
  accepted UX constraint, not an oversight.
- Deferred hard-purge policy for deleted conversations means indefinite
  tombstone retention is the current reality, not a chosen retention
  period — accepted deliberately, consistent with ADR-0006 and ADR-0007
  both already deferring theirs, but a real gap that will need closing
  before this is a complete data-retention story.

## Scope boundaries

This document does not define: exact SSE framing, headers, or endpoint
routes; exact `generation.stream` response framing (chunked NDJSON or
another framed-event encoding — an R18-S02/S03 implementation choice, not
fixed here, since it does not change the architecture: exactly one
authenticated streamed response, whatever its wire encoding); exact
Laravel job/controller/class names; exact frontend component structure or
visual design; exact database column types beyond what's structurally
required; exact `ChatDeliveryEvent` retention window; calibrated timeout
durations or numerical SLOs without operational evidence; the detailed
title-generation truncation bound; Phase 19 administration features; the
future regenerate-a-completed-answer operation; or any of
R18-S02/S03/S04's implementation. It does not redecide anything ADR-0023,
ADR-0018, ADR-0017, ADR-0012 or ADR-0006 already settled. The
`IMPLEMENTATION_GUIDE.md` Stage 18.2 wording correction identified above
is noted, not applied — this document does not modify
`IMPLEMENTATION_GUIDE.md`, `PROJECT_ROADMAP.md`, or `tasks.json`, per this
session's explicit instruction.
