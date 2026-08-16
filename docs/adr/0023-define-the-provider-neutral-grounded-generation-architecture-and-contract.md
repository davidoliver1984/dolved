# ADR 0023: Define the Provider-Neutral Grounded Generation Architecture and Contract

## Status

Accepted

## Date

2026-08-16

## Post-acceptance implementation-readiness clarification

### Clarification date

2026-08-16

Before R17-S02 implementation began, a repository-level readiness review
found that several illustrative names and stage-boundary statements in this
ADR did not map literally onto the persistence identities and implementation
stages that already exist. The repository owner explicitly approved this
same-day, pre-implementation clarification to the Accepted ADR because it
records factual identity/terminology mappings and makes already-accepted
ownership executable; it does not reverse or alter an architectural
decision.

The clarification is deliberately narrow:

- `EvidenceSnapshot` uses the real persisted identities
  `document_chunk_id`, `document_id` and `ingestion_event_claim_id`. These
  replace the illustrative `canonical_chunk_id`, `document_version_id` and
  `extraction_run_id` names in the initially accepted text. A document
  version is already a `Document` row, and the canonical chunk's durable
  production lineage is its ingestion-event claim; no new extraction-run
  persistence entity is introduced.
- Laravel assembles generation from an explicit internal snapshot containing
  the original question, the final authorised `RetrievalResult`, the
  temporal-authority and applicability/location facts already resolved for
  that retrieval, the authorised workspace scope, and correlation/lineage
  context. Generation assembly reflects this settled input and never
  re-resolves temporal authority, location, applicability or eligibility.
- The `rc1` `generation.answer` response envelope has three mutually
  exclusive alternatives: a completed `GenerationResult`; a typed
  `GENERATION_CONTEXT_BUDGET_EXCEEDED` structural failure; or a typed
  `GenerationProviderError`. The budget failure is neither a business
  outcome nor a provider error. It may be raised locally by Laravel before a
  call, or returned by Python after deterministic provider-specific
  rendering/token measurement and before a provider call.
- R17-S02 owns the language-neutral contracts, Laravel context packing and
  request assembly, deterministic validation, persistence/migrations,
  fingerprint contract, provider-neutral Python model/interface foundation,
  and `rc1` contract extension. It contains no provider prompt wording,
  OpenAI adapter or live provider call. R17-S03 owns deterministic
  provider-specific rendering, prompt wording/version, OpenAI/gpt-5-mini,
  generation-specific structured-output verification, provider token
  measurement, bounded retry/failure mapping and real-provider verification.

This explicitly approved clarification is not a precedent for silently
editing Accepted ADRs. A substantive change to an accepted architectural
decision still requires a new or superseding ADR.

## Relationship to prior ADRs

### Consumes ADR-0018 and ADR-0021's final evidence; decides nothing about retrieval

ADR-0018 defines `RetrievalPlanner`, `EligibilityResolver` and the
`Retriever` boundary; ADR-0021 extends that pipeline with dense+sparse
fusion, reranking and `EvidenceThresholdPolicy`, ending in "Final evidence
set -> generation (Phase 17)". This document treats a `RetrievalResult` in
the `EVIDENCE_FOUND` outcome, carrying that already-authorised, already-
threshold-accepted final evidence set, as settled input. It does not
redefine, re-derive or reopen `candidate_k` tuning, fusion, reranking, the
evidence threshold, or any part of the retrieval outcome taxonomy — where it
needs to name a concept those ADRs own, it uses their vocabulary rather than
inventing a parallel one. Phase 16 is closed for the current engineering
phase (V3 engineering confirmation, case-first Recall@K 0.9667; see
"Context" below); nothing in this document revisits it.

### Extends `rc1` with a new purpose, rather than inventing a new protocol

ADR-0018 established `rc1` (Retrieval Call, v1) as the synchronous,
HMAC-signed, `retrieval-caller`-principal protocol for Laravel calling
Python inside a user request's critical path, and named `retrieval.plan`
and `retrieval.search` as its initial purposes. ADR-0021 added
`retrieval.rerank` to the same protocol without changing its signature
format, exactly the extension path ADR-0018 committed to: *"a new purpose
to this same protocol without requiring a new signature format."* Grounded
generation is the same call shape — synchronous, inside the same request's
critical path, Laravel-signed, Python-verified, no processing lease, no
`event_id` — so this document adds a fourth `rc1` purpose,
`generation.answer`, rather than defining a new protocol version. This is a
genuine architectural decision this ADR makes, not one the R17-S01 brief's
notes addressed directly; see the review appendix for why it surfaced only
during repository reconciliation.

### Extends the provider-neutral boundary pattern, not a new pattern

`Generator` is the fifth application of the same Open/Closed shape this
platform has now used four times: `Embedder` (ADR-0013), `VectorStore`
(ADR-0014), `RetrievalPlanner`/`Retriever` (ADR-0018), `SparseEncoder`/
`Reranker` (ADR-0021). Nothing about a provider-neutral generation boundary
is architecturally new; this document applies an already-proven shape to a
fifth external AI capability.

### Extends the snapshot-plus-fingerprint lineage idiom, not a new one

`ChunkingResult`'s configuration (ADR-0011), `EmbeddingProfile` (ADR-0013)
and `SparseEmbeddingProfile` (ADR-0021) each retain a canonical, inspectable
snapshot and a deterministic `sha256`-of-canonical-JSON fingerprint derived
from it. This document applies the identical idiom to generation
configuration, discharging the obligation `PROJECT_ROADMAP.md`'s
"Design constraint — Quality lineage across the pipeline" (recorded
2026-08-03) already assigned here: *"the Phase 17 generation ADR owns the
prompt-template and generation-configuration links."*

### Resolves the citation/re-extraction design constraint

`PROJECT_ROADMAP.md`'s "Design constraint — Citations and re-extraction"
(recorded 2026-07-30, arising from ADR-0010's extraction-run-scoped element
identity) was deliberately deferred *"until retrieval and answer generation
provide enough context to define the actual citation requirements."* This
document is that resolution — see "Durable evidence identity" below.

### Reuses ADR-0019/0020's evaluator boundary; does not build a new one

ADR-0019 built the provider-neutral `ModelAssistedEvaluator` boundary with a
concrete `RagasEvaluator` adapter in V1, and states directly: *"Stage 17.4
reuses the same `RagasEvaluator` adapter."* ADR-0020 confirms the same
extension point. This document's evaluation section (see "Evaluation
implications" below) does not define a new evaluation subsystem — it
describes what Stage 17.4 adds to an extension point that already exists.

### Reuses the Search/RAG audit layer ADR-0006 already anticipated

ADR-0006 names a Search/RAG audit layer recording *"who searched, in which
workspace, the query, retrieved documents/chunks, citations, the model
used, latency, token usage, cost, and correlation identifiers."* This
document's lineage and persistence decisions populate fields ADR-0006
already named as forward direction; it does not invent a new audit
mechanism.

### Consumes, does not reopen, ADR-0017's authority/applicability model

Temporal authority and location applicability are resolved once, upstream,
by `EligibilityResolver` against ADR-0017's domain model, before evidence
ever reaches this document's boundary. Generation reflects that resolution;
it never re-derives or second-guesses it (see "Grounding rules" below).

## Context

Phase 16 retrieval engineering is closed for the current engineering phase.
The latest V3 engineering confirmation (EXP-0008, 2026-08-16):

- case-first Recall@K: 0.9667
- clean-upstream Recall@K: 1.0000
- MRR: 0.9333
- nDCG@K: 0.9157
- planner correctness: 29/31
- eligibility correctness: 30/31
- outcome correctness: 30/31

For correctly scoped evidence, all 36 expected `EvidenceUnit`s survived
every pipeline stage: Dense → Sparse → Union → Fusion → Reranker →
Threshold → Final evidence. This is an engineering-only confirmation
against the project's own repository-owned exam, not a held-out or
production-promoted result (see the evaluation conventions ADR-0019/0020
already establish), but it is the settled basis Phase 17 now builds on.

An independent architecture review of the proposed Phase 17 direction (the
`R17-S01` review) was conducted and accepted before this document was
drafted. It settled the shape this ADR formalises: a single generation
operation that owns sufficiency judgement (no separate LLM judge), three
first-class outcomes preferring useful qualified answers over bare refusal,
a structured-but-natural-prose answer representation with deterministically
verifiable citations, and a provider-neutral boundary with OpenAI/gpt-5-mini
as the initial V1 adapter. This document does not re-derive that review; it
records the resulting decisions and settles the specific open questions the
review left for ADR drafting.

Phase 16 answered: *"did we retrieve trustworthy evidence?"* Phase 17
answers a different question: *"given already-authorised trustworthy
evidence, what is the model allowed to say, how is that answer grounded,
and how is its evidence lineage preserved?"*

## What this ADR decides and does not decide

This ADR defines: the end-to-end generation flow from `RetrievalResult` to
a persisted, validated answer; the provider-neutral `Generator` boundary and
OpenAI/gpt-5-mini as the initial V1 adapter; why generation, not a separate
sufficiency judge, owns the sufficiency decision; the `ANSWERED`/
`QUALIFIED`/`INSUFFICIENT_EVIDENCE` outcome taxonomy and its structural
invariants; `answer_parts[]` as the sole authoritative generated
representation, and the natural-prose requirement that governs it; the
multi-evidence-synthesis boundary; durable `EvidenceSnapshot` identity and
its relationship to extraction-run immutability; citation presentation as a
rendering-layer concern; what Laravel's deterministic validation can and
cannot prove; the evidence-budget/context-packing policy; data minimisation
at the provider boundary; grounding rules, including quantity and
absence-claim handling; the prompt-injection boundary; the retry/failure
taxonomy; the generation lineage/fingerprint design; the Laravel/Python
ownership split; deterministic prompt rendering; the `rc1` protocol
extension; and what Stage 17.4 evaluation adds to the existing evaluator
boundary.

It does not decide: exact `GenerationRequest`/`GenerationResult`
class/schema definitions, database migrations, or serialisation format
(R17-S02); exact prompt wording or the deterministic renderer's
implementation (R17-S03); exact UI citation styling or rendering (a later,
presentation-layer concern this document deliberately does not own); exact
retry counts or backoff constants (implementation, following the pattern
already established); Phase 18 streaming design (recorded only as an
inherited constraint below); or anything about retrieval, planning,
eligibility, fusion, reranking or the evidence threshold (ADR-0018,
ADR-0021 — closed).

## Decision

### Generation assembly input

`RetrievalResult` is the settled evidence outcome, but it is not by itself
the complete Laravel assembly input. Laravel assembles the canonical
`GenerationRequest` from one internal, immutable snapshot containing:

```text
GenerationAssemblyInput
  original_question
  retrieval_result                 # EVIDENCE_FOUND; final authorised evidence
  resolved_temporal_authority      # already resolved upstream
  resolved_applicability_location  # already resolved upstream
  authorised_workspace_scope      # application-only; not provider metadata
  correlation_and_lineage_context
```

This snapshot transports existing application truth into generation. It does
not authorise a second temporal, historical, location, applicability or
eligibility resolution pass. Provider-facing data minimisation still applies:
only the minimum interpretation context defined below crosses `rc1`.

### The end-to-end flow

```text
RetrievalResult (EVIDENCE_FOUND, final evidence set)
  -> Laravel: deterministic context packing (see below)
  -> Laravel: assembles canonical GenerationRequest
  -> Laravel calls Python's Generator (rc1: generation.answer)
  -> Python: deterministically renders provider-specific input
       (or returns the typed GENERATION_CONTEXT_BUDGET_EXCEEDED structural
        failure envelope if the proposed package does not fit the provider's
        actual limits)
  -> Python: provider call (OpenAI gpt-5-mini)
  -> Python: typed parse -> GenerationResult (or typed GenerationProviderError)
  -> Laravel: deterministic validation
       (schema/outcome invariants, citation membership, evidence identity,
        document/version lineage, persistence preconditions)
  -> on pass: Laravel persists GeneratedAnswer, AnswerParts, EvidenceSnapshots,
       and the generation fingerprint
  -> return the validated, user-facing answer
  -> on fail: a typed failure is surfaced; no unvalidated content is ever
       returned as though it were an authoritative answer
```

No generated content becomes authoritative, user-visible answer content
before the generation contract and citation membership have passed
application validation — the one invariant every later decision in this
document, and the constraint Phase 18 inherits, is built to protect.

### The provider-neutral `Generator` boundary

```text
Generator.generate(request: GenerationRequest) -> GenerationResult
```

`Generator` never raises for a valid business outcome — `ANSWERED`,
`QUALIFIED` and `INSUFFICIENT_EVIDENCE` are ordinary `GenerationResult`
values, never exceptions. It raises a typed `GenerationProviderError` only
for the operational-failure categories in "Retry/failure taxonomy" below.
Provider-specific render/token measurement may instead raise the distinct
typed `GenerationContextBudgetExceeded` structural failure before a provider
call. At the `rc1` boundary these map to three mutually exclusive envelopes:

```text
completed                 -> GenerationResult
context_budget_exceeded   -> GenerationContextBudgetExceeded
provider_error            -> GenerationProviderError
```

The exact JSON schema and serialisation of this tagged response are R17-S02
implementation work; the three-way semantic distinction is fixed here.
No application code outside one isolated adapter implementation depends on
a specific provider's SDK, request shape, or response structure —
`GenerationRequest` and `GenerationResult` never expose provider-specific
fields, including where a provider's structured-output feature is used to
enforce the contract at the API level: the shape of *how* structured output
is requested (for example, a JSON-schema parameter) is adapter-internal
plumbing, never a field on the canonical request.

### Initial V1 provider: OpenAI, gpt-5-mini

Recorded as the initial implementation profile, not an architectural
lock-in, following the same posture ADR-0013 already established for
Voyage: the platform remains provider-neutral through the `Generator`
contract regardless of which adapter is active, and a future provider
change is an additive adapter plus (where the generation contract version
changes) a controlled lineage transition, not a pipeline redesign.
gpt-5-mini is an economical initial choice, and not a new provider
relationship: ADR-0018 already selected OpenAI/gpt-5-mini as the production
`RetrievalPlanner` model, so this stage reuses an already-operational
provider integration rather than introducing a second one to evaluate.
That reuse does not make gpt-5-mini a permanent generation-quality
decision — it is suitable for proving the provider-neutral generation
contract now, and generation *quality* is a separate question this
document deliberately defers to Stage 17.4's evaluation (see below), not
one this ADR claims to have settled by picking a model. Before R17-S03
begins implementation, the selected model must still be confirmed to
support the required strict structured-output behaviour reliably for
*generation* specifically — a model's suitability as ADR-0018's narrow
structured classifier does not, by itself, confirm its suitability for
open-ended grounded generation, so this verification step is not skipped
merely because the model is already in production use elsewhere.

### Generation owns sufficiency; no separate LLM sufficiency judge

Retrieval's `EVIDENCE_FOUND` (ADR-0018) means relevant, authorised evidence
was retrieved. It does not guarantee every requested fact can be answered.
Retrieval-threshold calibration already exposed cases — no fixed
medicine-quarantine duration, no universal police-call timer, no mandatory
receipt-chasing count, no visitor-badge grace period — where highly
relevant evidence survives retrieval without establishing the exact
requested fact, and no retrieval-threshold change can distinguish those
cases without destroying useful recall, because the evidence genuinely is
the most relevant evidence available; it simply does not say what was
asked. Generation already has to understand what the supplied evidence
supports in order to write a grounded answer at all. A second, separate
semantic sufficiency-judge stage would duplicate that same judgement in a
second LLM call, at additional latency and cost, for no benefit this
document can identify — the single generation operation determines what the
evidence supports, what useful answer can be given, and what remains
unsupported, in one pass.

### Outcome taxonomy: `ANSWERED` / `QUALIFIED` / `INSUFFICIENT_EVIDENCE`

Three first-class business outcomes, all ordinary successful
`GenerationResult`s, never provider errors:

- **`ANSWERED`** — the material requested answer is supported by supplied
  evidence.
- **`QUALIFIED`** — supplied evidence materially helps answer the question,
  but one or more material requested aspects are unsupported. This is the
  outcome the medicine-quarantine example above is built to reach: *"The
  policy does not specify a fixed quarantine duration. Instead, the
  medicine must remain quarantined until medicine-specific pharmacy advice
  is recorded"* — not an invented duration, and not a bare refusal.
- **`INSUFFICIENT_EVIDENCE`** — supplied evidence cannot materially answer
  the question at all.

This is kept a first-class enum rather than derived from
`ANSWERED + unsupported_aspects[]`, because the retry taxonomy below
already needs `QUALIFIED`/`INSUFFICIENT_EVIDENCE` to be distinguishable
business outcomes, not states inferred from array emptiness scattered
across consumers. Structural invariants, enforced by Laravel's deterministic
validation (never by the model's own self-report):

| Outcome | `answer_parts` | `unsupported_aspects` | `insufficiency_reason` |
|---|---|---|---|
| `ANSWERED` | non-empty | empty | null |
| `QUALIFIED` | non-empty | non-empty | null |
| `INSUFFICIENT_EVIDENCE` | empty | non-empty | non-null |

`INSUFFICIENT_EVIDENCE` deliberately still carries a non-null
`insufficiency_reason`, but that field is bounded, not a second free-text
answer channel: `insufficiency_reason` is a bounded explanation of *why*
generation could not materially answer the requested aspect from the
supplied evidence. It may describe the relationship between the supplied
evidence and the unanswered request, and nothing more. It must not
introduce procedural facts, quantities, dates, authority claims,
applicability claims, or any other substantive, uncited advice — anything
that would need evidence-citation discipline to state safely belongs in
`answer_parts` under `QUALIFIED`, not here:

```json
{
  "outcome": "INSUFFICIENT_EVIDENCE",
  "answer_parts": [],
  "unsupported_aspects": ["dismissal process"],
  "insufficiency_reason":
    "The supplied evidence does not materially address this aspect of the question."
}
```

This keeps the field genuinely useful to a user — *why* nothing could be
answered, not a bare internal error code — without letting it become a
route around `answer_parts`' citation discipline. Laravel's deterministic
validation treats `insufficiency_reason` as free text describing a gap,
never as a claim requiring `evidence_ids`; it is the one field in this
contract intentionally exempt from citation discipline, and it is exempt
*because* it is constrained to describe absence, never to assert anything
substantive that would need grounding.

### `answer_parts[]`: the sole authoritative generated representation

A free-text `answer` field and an independently generated `claims[]` array
were considered and rejected (see "Alternatives considered"). Two
independently generated representations of the same factual content create
correlated state — `answer`'s content must somehow equal `claims`'
content — that deterministic application code cannot enforce, because
semantic equality between two pieces of generated text is not something
Laravel's validation layer can check. Instead, one representation is
authoritative:

```json
{
  "outcome": "QUALIFIED",
  "answer_parts": [
    {
      "text": "The policy does not specify a fixed quarantine duration.",
      "evidence_ids": ["ev-01"]
    },
    {
      "text": "Instead, the medicine must remain quarantined until medicine-specific pharmacy advice is recorded.",
      "evidence_ids": ["ev-01"]
    }
  ],
  "unsupported_aspects": ["fixed quarantine duration"],
  "insufficiency_reason": null
}
```

The rendered user-facing answer is derived from `answer_parts` — there is
no separate free-text field capable of carrying an uncited factual
assertion outside this grounding structure.

**The provider-facing typed result carries no persistent application
identity.** `evidence_ids` are the request-scoped handles Laravel already
supplied in `GenerationRequest` (see "Durable evidence identity" below);
the model does not invent, and is not asked to invent, a persistent
`AnswerPart` identifier — array position is sufficient to distinguish parts
within one generation response, and no demonstrated need exists for the
model to assign one itself. Laravel assigns the durable `AnswerPart` UUID
during persistence, after validation passes, the same way it assigns
`EvidenceSnapshot` identity: persistent application identity is Laravel's
responsibility throughout this contract, never something a provider result
is trusted to originate.

**Natural prose is a load-bearing product requirement, not a detail.**
Structured internally does not mean mechanical to the user. An `AnswerPart`
is a coherent unit of natural user-facing prose whose material factual
content is supported by the same cited evidence set — it may hold one
sentence, several closely related sentences, or a coherent short paragraph,
and should be as large as reasonably possible while preserving clear
evidence attribution. Splitting happens primarily when the supporting
evidence set materially changes, a distinct qualification needs to be
expressed, or paragraph structure naturally requires it — never
mechanically at every sentence or atomic claim. Two adjacent `AnswerPart`s
citing the example above render, concatenated, as ordinary connected prose:
*"The policy does not specify a fixed quarantine duration. Instead, the
medicine must remain quarantined until medicine-specific pharmacy advice
is recorded."* Part IDs, evidence IDs, and any "Claim 1 / Claim 2"-style
formatting must never appear in rendered prose; the model must never
generate `[1]`, footnote markers, or HTML — citation markers are derived
presentation, produced later, not by the model (see "Citation presentation"
below).

### Multi-evidence synthesis

An `AnswerPart` may cite more than one `evidence_id`:

```json
{
  "text": "After an excursion above 8°C, quarantine the medicine and do not return it to use until pharmacy advice has been recorded.",
  "evidence_ids": ["ev-01", "ev-02"]
}
```

Grounded composition, paraphrase and synthesis of directly supported facts
is allowed — not every generated sentence needs to exist verbatim in one
source. What is not allowed is the introduction of any new material
premise, estimate, requirement, authority rule or conclusion the supplied
evidence, alone or combined, does not actually state. What Laravel can and
cannot deterministically prove about this boundary is described in full in
"Deterministic validation" immediately below, not restated here.

### Durable evidence identity: `EvidenceSnapshot`

`evidence_id` values inside `GenerationRequest`/`GenerationResult`
(`ev-01`, `ev-02`, …) are request-scoped handles with no meaning outside one
generation call. Laravel alone owns their mapping to canonical identity;
Python never resolves, invents, or needs to know it.

This resolves the deferred "Design constraint — Citations and
re-extraction": ADR-0010 scopes extracted-element UUIDs to a single
immutable extraction run, so a citation resolved only against a live
`chunk_id` risks silently pointing at different content after a future
re-extraction. On a successful `ANSWERED`/`QUALIFIED` result, after
validation passes, Laravel persists one `EvidenceSnapshot` per evidence
item actually cited by the answer — not per `AnswerPart` — recording:

```text
EvidenceSnapshot
  document_chunk_id
  document_id
  ingestion_event_claim_id
  source_provenance
  cited_text_verbatim
  content_digest
```

Storing the cited text verbatim, alongside its lineage identifiers, is the
specific decision this document commits to: a persisted answer's citations
resolve from data the answer itself carries, never from a live dependency
on the source extraction run's rows still existing. A future re-extraction
can proceed freely without any retention or garbage-collection policy for
old extraction runs being load-bearing for historical-answer correctness —
old `EvidenceSnapshot`s already hold their own copy of what was cited. This
mirrors the "disposable snapshot" treatment ADR-0010 already applies to
extraction itself, applied here to citation.

Snapshot scope is per generated answer, not a global, deduplicated,
content-addressable evidence store: a single `EvidenceSnapshot` is reusable
by every `AnswerPart` within the same answer that cites the same evidence
item, so cited text is never duplicated per part, but no cross-answer
deduplication is built in V1 — there is no demonstrated requirement for one,
and per-answer scope is the smaller, more conservative contract (see
"Alternatives considered").

```text
GeneratedAnswer
  |
  +-- EvidenceSnapshot (ev-01)
  +-- EvidenceSnapshot (ev-02)
  |
  +-- AnswerPart 1 -- cites ev-01
  +-- AnswerPart 2 -- cites ev-01
  +-- AnswerPart 3 -- cites ev-01, ev-02
```

### Citation presentation is a rendering concern

Internal citation identity is `answer_part.evidence_ids[]`, produced by the
model and validated by Laravel. User-facing presentation — `[1]`/`[2]`
markers, footnote styling, or any other citation UX — is derived later, by
Laravel/API/UI, from `answer_parts` and their `evidence_ids`. Citation
numbering and order are never generated by the model. This keeps citation
UX free to change without touching the generation contract, and keeps the
model from having to get UI-shaped numbering right as a semantic task.

### Deterministic validation: what Laravel can and cannot prove

Laravel's deterministic validation must, at minimum, confirm: `GenerationResult`
schema validity; the outcome/`answer_parts`/`unsupported_aspects`/
`insufficiency_reason` invariants above; every `AnswerPart` carries at
least one `evidence_id`; every cited `evidence_id` was present in the
corresponding `GenerationRequest`; no evidence identity was invented;
cited evidence belongs to the authorised request/workspace; the associated
document/version/source lineage is valid; and required persistence
preconditions are met before an answer is treated as authoritative.

Laravel cannot deterministically prove: whether supplied evidence
semantically entails a given part's text; whether every material supported
fact was actually included; whether a paraphrase subtly changed meaning; or
whether a structurally valid, citation-valid answer was nonetheless
semantically influenced by hostile evidence content. These are generation-
quality questions, and this document does not pretend structural citation
validation is a proxy for semantic groundedness — they belong to Stage
17.4 evaluation (see below), not to Laravel's deterministic layer.

### Context packing: deterministic ownership, explicit budget failure

Laravel decides which already-authorised final evidence is proposed for
`GenerationRequest`, deterministically, before evidence ever reaches
Python. This is context *packaging*, not a seventh candidate-selection
stage alongside ADR-0021's six — no reranking or re-scoring happens here.

**Ownership split.** Laravel owns: the authorised evidence set;
`PRIMARY`/`COMPARISON` structural requirements where `RetrievalResult`
carries them (ADR-0018's `COMPARE` semantics); evidence order/priority; the
configured context policy; which evidence, if any, that policy permits
omitting; and fail-closed behaviour when the policy cannot be satisfied.
Python's adapter owns: deterministic provider-specific rendering, and
provider/model-specific token measurement where exact accounting depends
on a tokenizer or serialisation detail Laravel has no reason to know.
Python must not: rerank evidence; semantically choose which evidence
matters; silently discard evidence; alter authority or applicability; or
collapse a required `COMPARISON` side. This split exists so that
provider-specific tokenisation detail never leaks into Laravel's policy
layer, while Python never gains authority to make an evidence-selection
decision that belongs to Laravel.

A naive "take ranked evidence until the budget is full" packing policy is
explicitly rejected (see "Alternatives considered"), because it can empty
one `COMPARE` side entirely while the other remains intact. Partial chunk
truncation is avoided in V1 — evidence is included whole or not included,
unless a later stage designs an explicit, deterministic,
provenance-preserving partial-inclusion scheme.

**Failure is explicit, and never an open-ended negotiation.** Laravel
proposes an evidence package under its own policy, sized in platform-defined
units. If Python's adapter, while deterministically rendering that package,
finds the provider's actual tokenisation means it does not fit — even
though Laravel's policy was already respected — it returns a typed budget
result to Laravel within that same `generation.answer` call, rather than
silently truncating or dropping evidence itself: one proposal, one
deterministic accept-or-reject, never an iterative back-and-forth within a
single call. Laravel then either fails explicitly, or, only if its
configured policy defines a permitted smaller package, makes at most one
further bounded re-proposal under that policy — never an open-ended
repacking loop, and never Python's decision to make. If evidence that is
structurally required — for example, a `COMPARE` side with no content able
to fit — cannot be packed within the accepted budget even after any such
policy-defined fallback, the request fails as a typed
`GENERATION_CONTEXT_BUDGET_EXCEEDED` failure, never silently proceeding
with incomplete, unexplained evidence.

**`GENERATION_CONTEXT_BUDGET_EXCEEDED` is not `INSUFFICIENT_EVIDENCE`, and
must never be treated as though it were.** The two mean structurally
different things. `INSUFFICIENT_EVIDENCE` is a `GenerationResult` business
outcome: generation actually reasoned over the supplied evidence and found
it cannot materially answer the question. `GENERATION_CONTEXT_BUDGET_EXCEEDED`
is an application-level structural packing failure. Laravel may raise it
before an `rc1` call when its deterministic policy cannot assemble a valid
package; Python may return the same typed failure envelope after
provider-specific rendering/token measurement rejects a proposed package
and before any provider call. The required, authorised evidence may be
entirely sufficient to answer the question; the application simply cannot
represent it within the configured generation context envelope. Conflating
the two would misreport a packaging/configuration limitation as though it
were a fact about the evidence itself. Stage 17.4 must never evaluate a
`GENERATION_CONTEXT_BUDGET_EXCEEDED` failure as a semantic-insufficiency
case — it belongs alongside the operational-failure categories in
"Retry/failure taxonomy" below, not among the outcomes the evaluator scores
for groundedness or usefulness.

### Data minimisation at the provider boundary

The provider receives only what it needs to generate the answer:
question text, evidence text and the minimum context needed to interpret it
(source provenance, temporal authority, applicability, `PRIMARY`/
`COMPARISON` side), and generation constraints. It does not receive: dense,
sparse, fusion or reranker scores; other workspaces' anything; embedding
vectors; storage or provider credentials; unrelated workspace metadata; or
raw planner internals. This mirrors ADR-0013's "minimum-necessary-input
principle" for embedding calls, applied here: every additional thing a
third-party provider receives is something this platform would have to
explain, audit and justify if that provider were ever compromised.

### Grounding rules

- **Quantities, dates and durations** must be directly supported by
  supplied evidence. Exact-unit conversion is allowed where semantically
  exact (*"forty-eight hours"* → `48 hours`; *"15 June 2024"* →
  `2024-06-15`); estimation, rounding, extrapolation, and invented
  numeric thresholds or durations not explicitly supported are not.
- **Absence claims must be scoped to supplied evidence.** *"The supplied
  evidence does not specify a fixed duration"* is always acceptable — it is
  the mechanism `QUALIFIED` exists to express. *"No policy anywhere
  specifies a fixed duration"* is never acceptable — the generator only
  ever knows the evidence package it was given, never the full corpus, and
  must not claim otherwise.
- **Authority and applicability are application-owned, reflected not
  re-derived.** The generator states which version/scope is authoritative
  only as `EligibilityResolver` already resolved it (ADR-0017); it never
  independently chooses another authoritative version or applicability
  scope.
- **Material modality is preserved.** *"must"*, *"should"* and *"may"* in
  the evidence are not silently converted into one another.
- **Grounded multi-source synthesis is allowed**, as described above, under
  the same no-new-premise boundary.

These rules govern `answer_parts` content specifically. `insufficiency_reason`
is governed by its own, narrower constraint — see "Outcome taxonomy" above
— because it describes an absence of evidence rather than asserting
anything substantive that would need grounding.

### Prompt injection

Retrieved evidence is untrusted data. Provider input structurally
distinguishes system instructions, the user question, and evidence data —
using the provider's role-separated message structure as the primary
defence, not text delimiters alone — and evidence text carries no
instructional authority regardless of its content. V1 generation receives
no autonomous retrieval, no browsing, no application tools, and no
function/tool capability able to change state or broaden evidence; this
alone closes off the most consequential injection outcomes. A small hostile-
document regression set is added to Stage 17.4's deterministic checks
(citation/contract validity under adversarial evidence is checkable without
a semantic judge). It is recorded as an accepted residual risk, not
eliminated by structural validation: a structurally valid, citation-valid
answer can still be semantically influenced by hostile evidence text — that
risk is Stage 17.4's semantic evaluation to measure, not Laravel's
deterministic layer to prevent.

### Retry/failure taxonomy

Distinguished categories: transport failure; rate limit; timeout; malformed
typed provider output; provider refusal; deterministic contract validation
failure; unsupported citation; `GENERATION_CONTEXT_BUDGET_EXCEEDED` (see
"Context packing" above — an application-level packing failure, not a
provider failure and not a business outcome); and the three business
outcomes (`ANSWERED`, `QUALIFIED`, `INSUFFICIENT_EVIDENCE`), which are
never provider failures. Only genuinely transient categories — transport
failure, rate limit, timeout — are retried, with bounded, capped
exponential backoff and jitter, following the same pattern ADR-0007,
ADR-0008, ADR-0010 and ADR-0013 already establish for every other provider
or infra dependency in this platform, rather than a new retry philosophy
invented here. Malformed typed provider output, deterministic contract
validation failures, unsupported citations, and `GENERATION_CONTEXT_BUDGET_EXCEEDED`
are all structural failures, not retried automatically. For the two model-
output categories, retrying in the hope a model self-corrects is exactly
the semantic retry-to-success pattern this document rejects (see
"Alternatives considered"); for `GENERATION_CONTEXT_BUDGET_EXCEEDED`, an
identical retry would simply fail identically, since nothing about the
proposed package changed. Exact retry counts and backoff constants are an
implementation detail for R17-S03, consistent with how ADR-0013 also left
Voyage's exact retry constants to its implementation stage.

### Generation lineage and fingerprint

Every generated answer persists a `generation_fingerprint`, following the
same canonical-snapshot-plus-deterministic-fingerprint idiom ADR-0011 and
ADR-0013 already established:

```text
generation_fingerprint = sha256(canonical_json({
  provider,
  model,
  contract_version,
  prompt_version,
  adapter_version,
  quality_affecting_configuration,   # sampling params, max_tokens, and any
                                      # other field a GenerationRequest
                                      # constraint that affects output
}))
fingerprint_scheme_version: 1
```

`quality_affecting_configuration` is defined explicitly and versioned, not
left as an undefined catch-all — the same discipline `EmbeddingProfile`
already applies. `fingerprint_scheme_version` is retained separately from
the hash itself, so a future change to what the fingerprint is computed
over does not make historical fingerprints ambiguous. This discharges
`PROJECT_ROADMAP.md`'s quality-lineage design constraint for the
prompt-template/generation-configuration link, and populates the *"model
used"* field ADR-0006's Search/RAG audit layer already anticipated.

### Laravel/Python ownership

**Laravel owns:** authentication/authorisation; tenancy; retrieval
orchestration (unchanged, ADR-0018/0021); the final authorised evidence
package; authority/applicability truth (ADR-0017, unchanged); deterministic
context packing; canonical `GenerationRequest` assembly; request-scoped
evidence-identity mapping; deterministic `GenerationResult` validation;
durable `EvidenceSnapshot` and `GeneratedAnswer` persistence; API contract
enforcement; and Search/RAG audit persistence (ADR-0006).

**Python owns:** the provider-neutral `Generator` interface; deterministic
provider-specific input rendering; the provider adapter and provider call;
bounded provider retry, following the pattern already established in
ingestion (ADR-0015) rather than having Laravel orchestrate low-level
generation retries; typed response parsing; and provider usage/latency
metadata and failure mapping.

Provider-specific structures never leak across this boundary in either
direction.

### Deterministic prompt rendering

For a given `GenerationRequest` and prompt version, Python's provider-input
rendering is a pure, deterministic function — the same input always
produces the same provider input. This is what makes `prompt_version` a
meaningful fingerprint component, and what allows hostile-document fixtures
and adapter regression tests to be reproduced offline, without a live
provider call, mirroring the deterministic-fake testing discipline
ADR-0013 and ADR-0018 already require of `Embedder` and `RetrievalPlanner`.

### The `rc1` protocol extension

A new `rc1` purpose, `generation.answer`, is added under the existing
`retrieval-caller` principal, key ring and signing mechanism ADR-0018
established and ADR-0021 already extended once — no new protocol version,
no new principal, no change to the canonical string-to-sign shape. The
existing purpose-binding discipline applies unchanged: a signature valid
for `generation.answer` is never accepted for `retrieval.plan`,
`retrieval.search` or `retrieval.rerank`, and vice versa. This call sits in
the same user-request critical path as the existing three purposes, so the
same mandatory-TLS, replay-suppression, and bounded-timeout requirements
ADR-0018 already established apply to it without restatement.

### Streaming deferral

Phase 17 V1 is: generate → typed parse → deterministic validation →
persistence → return. No raw, unvalidated model output is streamed to a
user. This document does not design Phase 18 streaming — it records only
the constraint Phase 18 inherits: no generated content becomes
authoritative, user-visible answer content before the generation contract
and citation validation have passed. `answer_parts[]` may prove a useful
future validated-streaming unit (a per-part event, once validated, could be
delivered progressively rather than waiting for the whole answer), but this
is recorded as a possibility, not designed here, and this document does not
let a future streaming requirement shape Phase 17 beyond keeping
`answer_parts` a first-class, independently meaningful structure — which
this document already does for other reasons (see "Alternatives
considered").

### Evaluation implications (Stage 17.4)

Stage 17.4 extends the existing `ModelAssistedEvaluator`/`RagasEvaluator`
boundary ADR-0019 built and ADR-0020 confirmed — it does not define a new
evaluation subsystem. Deterministic checks, runnable without a model judge:
`GenerationResult` structural validity; the outcome invariants above;
citation membership (the same check Laravel's validation layer already
performs, run as a regression suite); evidence-identity validity;
persistence/lineage correctness; and known, unambiguous insufficiency
fixtures. Semantic/model-assisted checks, through the existing evaluator:
groundedness (does a part's text follow from its cited evidence);
answer completeness; unsupported-claim rate; over-refusal;
`QUALIFIED`-answer usefulness; and hostile-document semantic influence. The
sufficiency cases already diagnosed during retrieval-threshold calibration
(medicine quarantine, police-call timer, receipt-chasing count, visitor-
badge grace period) become permanent generation-evaluation fixtures.
Evaluation records reuse the existing repository-owned experiment
convention (immutable, versioned run directories; see ADR-0019); this
document does not invent a parallel record format. Retrieval threshold
calibration is not reopened by this evaluation work.

## Architectural invariants

- No generated content becomes authoritative, user-visible answer content
  before the generation contract and citation membership have passed
  Laravel's deterministic validation.
- `answer_parts[]` is the sole authoritative generated representation;
  there is no independently generated free-text answer field capable of
  carrying an uncited factual assertion.
- Every `AnswerPart` carries at least one `evidence_id`, and every cited
  `evidence_id` was present in the corresponding `GenerationRequest` —
  checked deterministically, never assumed.
- `evidence_id` is request-scoped; canonical evidence identity is
  Laravel-owned and resolved into a durable, per-answer `EvidenceSnapshot`
  carrying the cited text verbatim, independent of future re-extraction.
- The outcome/`answer_parts`/`unsupported_aspects`/`insufficiency_reason`
  invariants are enforced structurally, never trusted from the model's own
  self-report; `insufficiency_reason` is bounded to describing an absence
  of evidence and is never a route around `answer_parts`' citation
  discipline.
- `AnswerPart` and `EvidenceSnapshot` persistent identity is assigned by
  Laravel at persistence time; the provider-facing typed result carries no
  application identity beyond the request-scoped `evidence_ids` Laravel
  already supplied.
- `Generator` is provider-neutral; no `GenerationRequest`/`GenerationResult`
  field is provider-specific, including where a provider's own
  structured-output mechanism is used to enforce the contract.
- Context-packing ownership is split deterministically: Laravel owns the
  evidence set, structural `COMPARE` requirements, order and policy; Python
  owns only provider-specific rendering and token measurement, and never
  reranks, discards evidence, or collapses a required side. Packing never
  silently drops a structurally required `COMPARE` side and never allows
  provider-side truncation; a package that cannot fit fails as a typed
  `GENERATION_CONTEXT_BUDGET_EXCEEDED` failure, which is never treated as,
  or evaluated as, `INSUFFICIENT_EVIDENCE`.
- Only genuinely transient provider-failure categories are retried, with
  bounded, jittered backoff; structural and business-outcome failures —
  including `GENERATION_CONTEXT_BUDGET_EXCEEDED` — are never retried as
  though transient.
- Every generated answer persists a versioned `generation_fingerprint`
  derived from an explicitly defined configuration set.
- Deterministic prompt rendering is a pure function of `GenerationRequest`
  and prompt version.
- `rc1`'s existing principal, key ring and signature format are extended
  with one new purpose, `generation.answer`; no new protocol version is
  introduced.

## Alternatives considered

### A separate LLM sufficiency-judge stage

Rejected. Retrieval-threshold calibration already showed cases where a
higher threshold cannot distinguish "relevant but non-answering" evidence
from genuinely insufficient evidence without destroying useful recall.
Generation already has to make this judgement to write a grounded answer;
a second semantic call would duplicate it at additional latency and cost
for no identified benefit.

### A free `answer` field plus an independently generated `claims[]` array

Rejected. Two independently generated representations of the same factual
content create correlated state deterministic application code cannot
enforce — Laravel cannot verify `answer`'s prose matches `claims`' content
without a semantic check it does not have. `answer_parts[]` collapses this
to one authoritative representation instead.

### A bare `ANSWER`/`REFUSE` outcome model

Rejected. Retrieval-threshold calibration's own diagnosed cases show this
forces a choice between fabricating unsupported detail (an invented
duration) and unnecessarily useless refusal, when a grounded, partial
answer is both possible and genuinely useful. The three-outcome model with
enforced structural invariants exists specifically to avoid this false
choice.

### A provider-specific application contract

Rejected, for the same reason ADR-0013 rejected depending on the Voyage
SDK directly: it would couple Laravel's orchestration and every future
consumer of a generated answer to one vendor's response shape, foreclosing
provider replacement without a pipeline redesign.

### Raw, unvalidated Phase 17 streaming

Rejected. Streaming raw model tokens before the generation contract and
citation membership are validated would let unauthorised or malformed
content reach a user before this document's central invariant — validate
before authoritative — could apply. Streaming is deferred to Phase 18,
where a validated delivery mechanism can be designed properly.

### Fuzzy or semantic citation validation inside deterministic Laravel logic

Rejected. Laravel's validation layer is deliberately restricted to what it
can actually prove — schema, invariants, evidence-identity membership,
lineage — never a semantic judgement about whether cited text is truly
entailed by its evidence. Blending a "probably fine" semantic check into
the deterministic layer would misrepresent what that layer actually
guarantees, exactly the honesty this platform's evaluation architecture
(ADR-0019/0020) already insists on for retrieval metrics.

### A new internal protocol for Laravel-to-Python generation calls

Rejected. ADR-0018 already committed to `rc1` accepting new purposes
"without requiring a new signature format," and ADR-0021 already exercised
that commitment once (`retrieval.rerank`). Generation is the same call
shape as the existing three purposes; a new protocol would duplicate
`rc1`'s already-proven signing, replay-suppression and TLS requirements for
no benefit.

### Duplicating cited evidence text once per `AnswerPart`

Rejected. Multiple parts within one answer commonly cite the same evidence
item; storing the full text again for each citing part wastes storage for
no benefit `EvidenceSnapshot`'s per-answer, reusable scope does not already
provide.

### A global, content-addressable, cross-answer evidence store

Rejected for V1. No demonstrated requirement exists for deduplicating
evidence snapshots across different answers; per-answer scope is the
smaller, more conservative contract, consistent with this platform's
repeated preference (ADR-0010, ADR-0013, ADR-0014, ADR-0018) not to solve
an unmeasured problem speculatively.

### A provider-generated `AnswerPart` identifier

Rejected. Nothing downstream needs a model-invented identifier: array
position already distinguishes parts within one generation response, and
Laravel already owns durable identity assignment for `EvidenceSnapshot`.
Letting the provider originate persistent application identity would be
inconsistent with that, and would trust a value this platform has no way
to guarantee is unique or stable.

### Representing a context-budget failure as `INSUFFICIENT_EVIDENCE`

Rejected. The two describe different facts: whether the evidence itself
can answer the question, versus whether the application can represent
already-sufficient evidence within its configured context envelope.
Collapsing them would make a packaging limitation look like a fact about
evidence quality, and would let Stage 17.4 mis-score a configuration
failure as a semantic-insufficiency case.

### Filling the evidence-token budget by rank order alone

Rejected. A naive "take ranked evidence until the budget is full" packing
policy can empty an entire `COMPARE` side while leaving the other intact,
producing a structurally broken comparison with no observable signal that
anything was wrong. Preserving required structural sides before filling
remaining budget by existing order, and failing explicitly when required
evidence cannot fit, avoids this silently-degraded outcome.

## Consequences

### Positive

- Generation provider, model and configuration remain fully replaceable
  through one abstraction, matching the pattern already proven four times
  over (`Embedder`, `VectorStore`, `RetrievalPlanner`/`Retriever`,
  `SparseEncoder`/`Reranker`).
- The citation/re-extraction design constraint deferred since 2026-07-30 is
  now resolved: historical answers resolve their own evidence
  independently of future re-extraction.
- The three-outcome model gives the platform a principled way to prefer
  useful, honestly qualified answers over both fabrication and unnecessary
  refusal, backed by real diagnosed cases rather than a hypothetical.
- `answer_parts[]` gives deterministic validation something real to check
  (citation membership, structural invariants) while keeping the
  user-facing experience ordinary prose, not a mechanical claim list.
- Reusing `rc1` and the existing evaluator boundary means Phase 17 inherits
  already-proven infrastructure instead of building parallel versions of
  both.
- Quality lineage and Search/RAG audit obligations recorded elsewhere in
  the roadmap are discharged by this document rather than left open again.

### Negative

- Structural citation validation does not, and cannot, prove semantic
  groundedness — the platform is explicit that a contract-valid answer can
  still be semantically wrong or hostile-evidence-influenced, and accepts
  that residual risk pending Stage 17.4's semantic evaluation.
- `EvidenceSnapshot`'s verbatim-text persistence is real, permanent storage
  growth proportional to answer volume — accepted deliberately in exchange
  for not depending on old extraction runs surviving indefinitely.
- Deterministic context packing that fails explicitly when required
  evidence cannot fit means some questions will surface a packing failure
  rather than a degraded-but-present answer — accepted because a silently
  incomplete `COMPARE` side is a worse failure mode than an explicit one.
- The generation-lineage fingerprint's `quality_affecting_configuration`
  must be defined and maintained carefully as generation configuration
  evolves — real, ongoing implementation discipline, not a one-time cost.

## Scope boundaries

This document does not define: exact `GenerationRequest`, `GenerationResult`,
`EvidenceSnapshot`, `AnswerPart` or `GeneratedAnswer` class definitions,
schemas, or serialisation format (R17-S02); the deterministic prompt
renderer's implementation or exact prompt wording (R17-S03); the OpenAI
adapter's exact structured-output configuration (R17-S03, contingent on
confirming gpt-5-mini's structured-output reliability); exact retry counts
or backoff constants (R17-S03, following the established pattern); exact
evidence-token budget numbers (R17-S02); citation UI/rendering styling (a
later presentation-layer concern); or Phase 18 streaming design (recorded
only as an inherited constraint above). These remain open for the stages
named above to decide with the context appropriate to each.
