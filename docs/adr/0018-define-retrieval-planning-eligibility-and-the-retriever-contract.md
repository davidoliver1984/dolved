# ADR 0018: Define Retrieval Planning, Eligibility and the Retriever Contract

## Status

Accepted

## Date

2026-08-07

## Relationship to prior ADRs

### Consumes ADR-0017; decides nothing about it

ADR-0017 defines the domain model this ADR builds a consumer against:
`DocumentFamily`, explicit linear version lineage, the derived
`CURRENT`/`VALID_AT_DATE` temporal-authority rule, the minimal
`DRAFT → APPROVED → WITHDRAWN` governance model, and per-version
`OrganisationalLocation` applicability. This ADR treats every one of those
as settled and authoritative. It does not redefine, re-derive, or reopen any
part of ADR-0017's domain model or invariants — it only decides how a
retrieval request consumes them. Where this document needs to name a
concept ADR-0017 owns (attained authority, an authority window, a
governance state), it uses ADR-0017's own vocabulary rather than inventing
a parallel one.

### Extends ADR-0016's dual gate; does not redefine it

ADR-0016's dual retrieval-visibility gate — a published Qdrant point **and**
PostgreSQL `INDEXED` — remains the unconditional structural prerequisite for
a chunk to be retrievable at all, exactly as ADR-0016 and ADR-0017 both
already state. This ADR's eligibility model is a **third, additive gate**,
applied on top of the existing two, never a substitute for either and never
a redefinition of what `INDEXED` means. A chunk can pass ADR-0016's dual
gate and still be correctly excluded by this ADR's eligibility model (not
yet approved, not effective, withdrawn, or out of the authorised or
applicable scope) — that is the expected, common case, not an edge case to
design around.

### A new, independent protocol — not a supersession of ADR-0009, ADR-0015 or ADR-0016

ADR-0009, ADR-0015 and ADR-0016 together define a purpose-scoped HMAC
protocol (`v1`, superseded in part by `v2`) authenticating the Python
ingestion worker's asynchronous, lease-based requests to Laravel. This
document introduces a **separate, independently-versioned protocol** for a
structurally different call: Laravel calling Python, synchronously, inside
a user request's critical path, with no processing lease, no `event_id`, no
attempt to resume, and no asynchronous delivery semantics. "Retrieval
planning, eligibility and the retriever contract" below explains why
reusing the existing protocol's shape was considered and rejected, not
silently avoided. This document does not touch, reopen, or reinterpret
ADR-0009, ADR-0015 or ADR-0016's own protocol in any way; the two protocols
are independent, distinguished by principal, key ring and purpose
namespace, and are expected to evolve independently.

### Citation correction: stale "Phase 15" forward references in ADR-0013 and ADR-0014

ADR-0013 (dated 2026-08-03) and ADR-0014 (dated 2026-08-04) each name
"Phase 15" as the future phase that would decide retrieval, query
decomposition, evaluation and hybrid retrieval/reranking — accurate under
the roadmap sequencing at the time each was written. Phase 15 was
subsequently restructured to "Ingestion Orchestration" and retrieval moved
to Phase 16 (recorded in `IMPLEMENTATION_GUIDE.md`'s Phase 16 restructuring
note, 2026-08-03, and carried through ADR-0017). This is the same citation
drift ADR-0013 itself already corrected for ADR-0006's forward reference to
`R13-S01` — this document performs the equivalent correction for ADR-0013's
and ADR-0014's forward references to "Phase 15," rather than leaving a
reader who follows either citation to land in the wrong place. Neither
ADR-0013 nor ADR-0014 is rewritten; both remain immutable, exactly as
ADR-0013's own precedent for this situation established. Every forward
reference either ADR makes to "Phase 15" retrieval, query decomposition,
evaluation, hybrid retrieval or reranking should be read as "Phase 16,"
concretely: ADR-0013's Stage 15.2 → this document (`R16-S02`); ADR-0013's
Stage 15.4 → `R16-S05`; ADR-0013's Stages 15.6–15.7 → `R16-S07`–`R16-S08`;
ADR-0014's "Phase 15, Stage 15.2" and "Stage 15.6" references, in its own
"Scope boundaries" section, correct the same way.

## Context

Phase 16's core question, agreed before any Phase 16 ADR was drafted:
*"which evidence is eligible to answer this authorised user's question?"*
Retrieval answers that question. It does not generate the answer — grounded
generation, prompt assembly and citation construction remain Phase 17's
concern, consuming this document's output rather than being decided by it.

Phase 16, Stage 16.1 (ADR-0017, accepted `R16-S01`) resolved the versioning
half of that question: what a version is, and what "authoritative at time
T" honestly means. It deliberately did not decide how a user's question
becomes a resolved retrieval request, who is allowed to see what, or how
Python's vector search is actually invoked — it named `RetrievalPlanner`,
`EligibilityResolver` and the Retriever contract as Stage 16.2's job,
consuming its domain model rather than deciding it. This document is that
job.

An independent architectural review of the proposed Phase 16 direction,
conducted before this document was drafted, settled the shape this ADR
formalises: a Laravel-owned authorisation boundary, a Python-owned,
LLM-backed planning boundary that never touches authorisation or storage, a
Laravel-owned deterministic eligibility boundary that combines the two, and
a Python-owned retrieval boundary that searches only within what Laravel
has already resolved. That review also confirmed a genuine, previously
unaddressed gap: every existing internal-authentication ADR (0009, 0015,
0016) was built around Python, as an asynchronous worker holding a
processing lease, calling Laravel. Retrieval is the platform's first
Laravel-to-Python synchronous call, inside a user request's critical path,
and needs its own answer — not a stretched reuse of a protocol whose
principal, direction, lease assumptions and failure behaviour do not fit.

## What this ADR decides and does not decide

This ADR defines: the request flow from an authenticated user's question to
a returned `RetrievalResult`; the three-way metadata classification
(security, eligibility, descriptive) that keeps authorisation, eligibility
and relevance from being conflated; `AuthorisedKnowledgeScope` as
Laravel-owned and access-only; the provider-neutral, LLM-backed
`RetrievalPlanner` boundary and the typed `RetrievalPlan` it produces;
`EligibilityResolver` as Laravel-owned, deterministic, narrowing-only
domain logic that consumes ADR-0017's data; how per-version location
applicability is resolved, kept separate from access; `EligibleRetrievalScope`
as the provider-neutral, storage-neutral evidence boundary Python is
allowed to search; `COMPARE`'s two-sided resolution and result semantics;
the provider-neutral `Retriever` contract and its division of
responsibility from `EligibilityResolver`; `RetrievalResult` and a
controlled retrieval-outcome taxonomy; and a new, independently-versioned
synchronous Laravel-to-Python protocol for this call shape. It does not
decide evaluation metrics or quality-gate policy (`R16-S05`); hybrid
retrieval, sparse retrieval, fusion or reranking (`R16-S07`); calibrated
evidence thresholds or abstention policy; answer generation, prompt
assembly or citation construction (Phase 17); Administration UI for
locations, governance or retrieval configuration (Phase 19); or query
decomposition's activation — the decomposition seam ADR-0013 already
committed to is preserved and integrated, not implemented, exercised only
by V1's identity/no-op planner.

## Decision

### The core retrieval question, and what generation is not

The request flow, end to end:

```text
Authenticated user
  -> Laravel resolves AuthorisedKnowledgeScope
  -> Laravel calls Python RetrievalPlanner (rc1: retrieval.plan)
  -> Python returns a typed RetrievalPlan
  -> Laravel's EligibilityResolver combines:
       - the RetrievalPlan
       - AuthorisedKnowledgeScope
       - authoritative DocumentFamily/version/governance/temporal data (ADR-0017)
  -> Laravel produces an EligibleRetrievalScope
  -> Laravel calls Python's Retriever (rc1: retrieval.search)
  -> Python searches only inside the resolved scope
  -> Python returns candidate identities, scores and retrieval lineage
       (never chunk text or provenance; never queries PostgreSQL)
  -> Laravel batch-hydrates chunk text/provenance and rechecks eligibility
  -> Laravel assembles the final RetrievalResult
```

Every step before the Retriever call is about narrowing *what may be
searched*; the Retriever call itself is about *finding relevant evidence
inside that already-narrowed universe*. Nothing downstream of
`AuthorisedKnowledgeScope` may expand it — every subsequent step only
narrows or leaves unchanged the set of documents a query could possibly
surface. Generation — turning retrieved evidence into an answer with
citations — is Phase 17's concern and is not designed, assumed, or
special-cased here; this document's only obligation to Phase 17 is to
return a `RetrievalResult` precise enough to be trustworthy evidence for
something else to reason about.

### Metadata classification: security, eligibility and descriptive facts

Every fact a document or version carries falls into exactly one of three
categories, and collapsing any two of them together is the single most
likely way this architecture could quietly become unsafe or unreliable:

- **Security metadata** — workspace membership, permissions, access
  grants. Answers *"what may this user see at all?"* This is the hard
  authorisation boundary: Laravel-owned, resolved before anything else runs,
  and never influenced by anything the planner or a prompt says.
- **Eligibility metadata** — approval, temporal authority, publication
  status, technical readiness (`INDEXED`), version lineage, location
  applicability. Answers *"of what this user may see, which of it is
  currently the organisation's authoritative evidence?"* Deterministic,
  Laravel-owned, and — like security metadata — never influenced by prompt
  content.
- **Descriptive metadata** — title, subject, tags, document type, owner,
  organisational labels, reference number. Answers *"what is this evidence
  actually about?"* These are relevance and subject-resolution signals, not
  hard filters, unless a specific descriptive field is independently proven
  to be the authoritative selector for a specific, narrow purpose (for
  example, an exact reference-number lookup a user typed verbatim) — and
  even then, that proof happens in deterministic Laravel code, never by
  the planner inferring a filter from natural language.

A user asking *"show me the HR policy"* must never cause `department_id =
HR` to become a hard filter merely because the planner recognised the word
"HR." The planner's only job is temporal/version intent classification (see
"`RetrievalPlanner`" below); resolving *which* document that phrase
actually refers to is a relevance question the Retriever's semantic search
answers by finding matching content within the already-eligible scope, not
a filter question `RetrievalPlan` or `EligibleRetrievalScope` ever encodes.
Treating a semantic term as a hard filter would silently narrow the search
universe based on an unverified guess about intent — exactly the kind of
prompt-controlled access this document exists to prevent.

**Location is the one deliberate, narrow exception to "descriptive terms
are never hard filters," and only because ADR-0017 independently defines
applicability as a hard eligibility dimension in its own right** — not
because location is somehow a more trustworthy category of word than
"HR." A question naming a specific place (*"the medication procedure at
Blackpool"*) may carry a semantic `applicability_reference` (see
"`RetrievalPlan`" below), but that reference only ever becomes eligibility
narrowing after Laravel deterministically resolves it against authoritative
`OrganisationalLocation` data — the planner never decides this on its own,
and the same "never invented, never guessed" discipline this section
already requires applies to it exactly as it applies to every other field.
This exception must not be read as license to extend the same treatment to
any other descriptive field a future feature might want to treat
specially; it exists here solely because ADR-0017, not this document, made
applicability an authoritative eligibility fact.

### `AuthorisedKnowledgeScope`: the maximum search universe, Laravel-owned

`AuthorisedKnowledgeScope` is derived exclusively from the authenticated
user, their workspace, and their stored permissions — the same
authorisation machinery ADR-0006 already establishes (active membership,
policies, `404`-not-`403` fail-closed concealment). It defines the absolute
ceiling of what a query could ever surface, before any temporal, governance
or applicability narrowing is applied. Access logic never runs inside, or
is influenced by, the planner or any prompt-derived value — the planner
does not receive a user's department, roles, access groups, or raw
permission rules, and nothing a prompt says can grant, restore, or expand
access. This is a direct application of ADR-0006's existing invariant that
*"authentication does not grant workspace access"* and *"client-supplied
[input] is never trusted without membership validation"*, extended here to
say explicitly: neither does a natural-language question. Every later step
in this document — `EligibilityResolver`, `EligibleRetrievalScope`, the
Retriever — may only narrow `AuthorisedKnowledgeScope` further; none may
widen it back out, under any circumstance, including a failure mode. A
component that cannot determine eligibility safely must return a
narrower-or-empty result, never a broader one.

### `RetrievalPlanner`: a provider-neutral, LLM-backed planning boundary

`RetrievalPlanner` is a provider-neutral abstraction, owned and reached
through the same Open/Closed discipline already applied to `Embedder`
(ADR-0013) and `VectorStore` (ADR-0014): no application code outside one
isolated implementation depends on a specific LLM provider's SDK or request
shape. Its input is the user's natural-language question and an
authoritative evaluation instant supplied by Laravel — never something the
planner infers or defaults, since a relative expression like *"last year's
policy"* has no fixed meaning without an externally supplied "now." Its
output is a strict, typed `RetrievalPlan` (below).

**The V1 production planner is LLM-backed. This is a settled design
choice, not a placeholder awaiting a simpler implementation.** The task the
LLM performs is narrow and bounded — natural language in, a typed retrieval
intent out — never authorisation, ID resolution, retrieval, embedding,
reranking, or answering. A keyword or regex-based classifier cannot
reliably distinguish *"what does the policy currently say"* from *"what did
it say before the 2023 update"* from *"how has this changed"* across the
open-ended ways a real user phrases a question; the LLM's role is exactly
that bounded natural-language classification task, nothing more. A
rule-based implementation remains legitimate as a test double, a
deterministic fallback, a baseline the evaluation harness (`R16-S05`)
measures the production planner against, or an evaluation comparator — it
is not, and does not become, the primary V1 production implementation.

Around the LLM call, the planner enforces:

- **strict structured output** — the LLM is constrained to produce a value
  conforming to `RetrievalPlan`'s schema, not free text later parsed
  heuristically;
- **schema validation** — the planner validates the LLM's raw structured
  output against that schema before ever returning it to Laravel, exactly
  the same "never trust a response merely because it returned the expected
  shape" discipline ADR-0013 already requires of `Embedder`'s batch
  responses;
- **deterministic date/selector validation** — any date or selector value
  the LLM produced is validated deterministically (parseable, well-formed,
  not nonsensical) inside the planner's own code, not trusted as
  free-form LLM output;
- **safe fallback behaviour** — a malformed, unparseable, or
  schema-violating LLM response never becomes a best-effort guess forwarded
  to Laravel; it becomes a typed planner failure, distinct from
  `CLARIFICATION_REQUIRED` (see "Retrieval outcome taxonomy" below), since
  the two mean different things: one is the LLM correctly reporting genuine
  ambiguity in the question, the other is the planner itself failing to
  produce a usable answer;
- **`CLARIFICATION_REQUIRED` over guessing** — an unresolved historical or
  comparison intent (*"the old version"* with no version named; *"compare
  the versions"* with no anchors given) becomes `CLARIFICATION_REQUIRED`,
  never a guessed date or an assumed anchor;
- **a deterministic/fake implementation for tests** — mirroring `Embedder`'s
  deterministic fake (ADR-0013), producing a fixed, predictable
  `RetrievalPlan` for a given input, so the ordinary test suite requires no
  live LLM call, no credentials, and no non-determinism;
- **evaluation compatibility** — the planner's output is measurable against
  the evaluation harness `R16-S05` will build, the same way ADR-0013
  committed embedding-provider changes to the repository-owned evaluation
  harness rather than subjective judgement.

The planner never authorises, resolves a symbolic anchor to an actual
`Document`/version identifier, queries PostgreSQL, queries Qdrant,
retrieves, embeds, reranks, or answers. Resolving *what* `CURRENT` or
`PREVIOUS` actually refers to is `EligibilityResolver`'s job, using
authoritative data the planner is never given.

### `RetrievalPlan`: temporal intent, applicability reference, and how they compose with ADR-0013's decomposition seam

`RetrievalPlan` carries three independent axes, deliberately kept distinct
because they answer different questions, are resolved by different
mechanisms, and evolve on different schedules:

- **`retrieval_queries`** — one or more bounded retrieval queries, the
  query-decomposition seam ADR-0013 already committed to and
  `PROJECT_ROADMAP.md`'s "Design constraint — Query decomposition and the
  retrieval pipeline shape" already describes. V1 exercises only an
  identity/no-op planner for this axis: `retrieval_queries` always contains
  exactly the original question, unchanged, as its single element. This
  document does not enable model-assisted decomposition — it preserves the
  boundary ADR-0013 already requires, satisfying the same commitment, not
  reopening or redesigning it.
- **`temporal_mode`** — the axis this document newly introduces, made
  possible only now that ADR-0017 gives it a settled meaning: `CURRENT`,
  `VALID_AT_DATE` (an explicit instant or a bounded temporal selector),
  `COMPARE` (two symbolic anchors), or `CLARIFICATION_REQUIRED` (a
  controlled reason).
- **`applicability_reference`** — an optional, single, semantic-only
  reference to an organisational location the question appears to name
  (for example, `"Blackpool"`), deliberately kept as narrow as this
  document could make it while still carrying the fact ADR-0017's
  applicability model needs. See below for its full contract.

These three axes compose without conflict: V1 always resolves exactly one
retrieval query, paired with exactly one temporal mode and at most one
applicability reference. `COMPARE` does not
require a second entry in `retrieval_queries` — the *same* query text is
searched against two independently-resolved eligibility scopes (see
"`COMPARE`" below); the fan-out belongs to `temporal_mode` and
`EligibleRetrievalScope`, not to query decomposition. A future model-
assisted planner that decomposes *"what does the policy say about X, and
how does that compare to Y"* into genuinely separate retrieval queries
remains additive to this shape — each decomposed query would carry its own
`temporal_mode` — without requiring `RetrievalPlan`'s structure to change.

For `COMPARE`, the V1 symbolic anchors are `CURRENT`, `AT_DATE(date)`, and
`PREVIOUS`. The plan carries these anchors symbolically only — never a
resolved `Document` or version identifier; `EligibilityResolver` resolves
them, using authoritative data the planner never sees.

Confidence is expressed as a controlled classification the plan is either
resolved or requires clarification for, never as an arbitrary numeric
score. This document deliberately avoids treating an LLM's internal
confidence as a calibrated probability — a raw 0–1 value from a language
model does not mean what a calibrated probability means, and presenting it
as one would misrepresent the actual uncertainty involved. `CLARIFICATION_REQUIRED`
carries a controlled reason (for example, `AMBIGUOUS_TEMPORAL_REFERENCE`,
`MISSING_COMPARISON_ANCHOR`) rather than a bare score a caller would have to
interpret.

**`applicability_reference`'s contract**, deliberately as narrow as this
document could make it:

- **semantic reference only** — a bare string the planner extracted because
  the question appears to name a specific organisational location, nothing
  more structured than that;
- **never a database identifier** — the planner has no access to
  `OrganisationalLocation` records, IDs, or aliases (the same total
  isolation from PostgreSQL already required of every other planner
  output), so it is structurally incapable of inventing one; it can only
  ever emit the text it read in the question;
- **never carries permission or access meaning** — the planner does not
  know, and this field does not encode, whether the named location is one
  the user can actually see; that is `AuthorisedKnowledgeScope`'s question
  entirely, answered later, by Laravel, never by this field;
- **never pushed to Qdrant as a free-text filter** — `EligibleRetrievalScope`
  never carries raw location text; only a location `EligibilityResolver`
  has already deterministically resolved and validated ever becomes part
  of the eligible scope (see "Location and applicability" below);
- **singular for V1** — at most one reference per plan, not a list. A
  question naming several locations at once is a genuine future capability
  with no demonstrated V1 requirement; keeping this field to exactly zero
  or one reference is the smaller, more conservative contract, and widening
  it to a bounded list later is additive, not a redesign (see "Alternatives
  considered" below);
- **absent by default** — most questions name no location at all, and the
  field is simply omitted; its absence carries no meaning beyond "the
  question did not appear to name one."

### `EligibilityResolver`: Laravel-owned, deterministic, narrowing-only

`EligibilityResolver` is explicitly Laravel-owned, deterministic
application/domain logic — not an AI component, not probabilistic, and not
reachable through the `rc1` protocol, because it never crosses the
Laravel-to-Python boundary at all. Its input is the `RetrievalPlan`,
`AuthorisedKnowledgeScope`, and ADR-0017's authoritative
`DocumentFamily`/version/governance/temporal data, read directly from
PostgreSQL. Its output is an `EligibleRetrievalScope`.

It resolves, deterministically:

- `CURRENT` and `VALID_AT_DATE`, using ADR-0017's derivation rule exactly
  as ADR-0017 defines it — this document does not restate or approximate
  that derivation, it calls it;
- `COMPARE`'s two symbolic anchors, independently, into two resolved sides
  (see "`COMPARE`" below);
- approval/governance eligibility and temporal authority (ADR-0017);
- version lineage, where `PREVIOUS` requires walking one step back through
  ADR-0017's attained-authority ordering from whatever `PRIMARY` resolved
  to;
- ADR-0016's dual gate prerequisites (published Qdrant point and
  PostgreSQL `INDEXED`) — `EligibilityResolver` never treats a
  not-yet-`INDEXED` version as eligible evidence, regardless of its
  governance or temporal state;
- an optional `applicability_reference`, resolved deterministically against
  authoritative `OrganisationalLocation` names and aliases, validated
  against `AuthorisedKnowledgeScope`, and — once resolved and validated —
  applied through ADR-0017's existing per-version applicability rules
  exactly as any other applicability fact already is; see "Location and
  applicability" below for the full resolution flow, including what
  happens when it cannot be resolved;
- any other deterministic evidence-eligibility rule this platform
  establishes.

It may only ever narrow `AuthorisedKnowledgeScope` — never expand it, under
any input, including a planner failure or an unusual combination of
anchors. A plan carrying `CLARIFICATION_REQUIRED` short-circuits before
`EligibilityResolver` runs at all. `EligibilityResolver` itself can also
produce `CLARIFICATION_REQUIRED` mid-resolution — specifically, when an
`applicability_reference` fails to resolve safely (see below) — in which
case it has already run partially but stops before computing an
`EligibleRetrievalScope`; in both cases, the Retriever is never invoked,
and no broad or "just search everything current" fallback occurs. An
unresolved question produces a controlled outcome asking the user to
clarify, never a wider, unintended search.

### Location and applicability: consuming ADR-0017's model, kept separate from access

`EligibilityResolver` consumes ADR-0017's `OrganisationalLocation`
hierarchy and per-version applicability snapshots exactly as ADR-0017
defines them, deciding nothing new about their shape. Access and
applicability remain two separate questions, resolved by two separate
mechanisms, and must never be conflated: authorisation answers *"what may
this user see?"* (`AuthorisedKnowledgeScope`, Security metadata);
applicability answers *"where does this version apply?"* (Eligibility
metadata, ADR-0017). A location reference appearing in a user's question —
*"the medication procedure at Blackpool"* — cannot grant access on its own,
and only ever becomes an eligibility-narrowing fact after passing through
the deterministic resolution below; it is never treated as raw text for
Qdrant to fuzzily match against.

**Resolution flow for `applicability_reference`**, run by `EligibilityResolver`
after `AuthorisedKnowledgeScope` is already established and before
`EligibleRetrievalScope` is produced:

1. If the plan carries no `applicability_reference`, this step is skipped
   entirely — nothing about applicability narrowing changes from a plan
   that never mentioned a location.
2. Otherwise, Laravel looks up the reference text deterministically against
   authoritative `OrganisationalLocation` names and aliases — an exact or
   alias match, never a fuzzy or embedding-based semantic match, so the
   outcome is always auditable and reproducible.
3. **Exactly one match**: the resolved `OrganisationalLocation` is
   validated against `AuthorisedKnowledgeScope`. For V1's flat,
   workspace-level authorisation model (ADR-0006's granular permission
   engine remains deferred), this validation is ordinarily a no-op — every
   location within an authorised workspace is trivially "within" scope —
   but the step exists structurally now so that a future granular,
   location-scoped permission engine has a defined place to plug into
   without this contract being redesigned when it lands. The validated
   location is then applied through ADR-0017's existing per-version
   applicability rules exactly as any other applicability fact: `UNIVERSAL`
   applicability matches every location; a `Region`-level applicability
   extends to its descendant `Site`s; a version whose recorded applicability
   does not cover the resolved location is excluded, precisely as ADR-0017
   already specifies.
4. **Zero matches, or more than one ambiguous match** (for example, two
   distinct `OrganisationalLocation`s that happen to share a name in
   different regions): the reference does not resolve safely. Consistent
   with this document's existing "no silent substitute" discipline for
   `COMPARE` (below) and for the planner's own unresolved-intent handling,
   this produces `CLARIFICATION_REQUIRED` (reason
   `UNRESOLVED_APPLICABILITY_REFERENCE` or `AMBIGUOUS_APPLICABILITY_REFERENCE`
   as appropriate) rather than either silently ignoring the location the
   user explicitly asked about or silently narrowing to a guessed match.
   Silently proceeding without the requested narrowing would risk
   returning evidence from the wrong location entirely — a materially
   worse failure mode for a question like the medication-procedure example
   above than asking the user to clarify.

No free-text location filter is ever accepted or constructed from a prompt,
at any point in this flow — every eligibility consequence of a location
reference passes through steps 2–3 above, deterministically, before it can
narrow anything.

### `EligibleRetrievalScope`: the provider-neutral evidence boundary

`EligibleRetrievalScope` is a provider-neutral, storage-neutral domain
object expressing exactly the evidence universe Python's Retriever is
permitted to search — and nothing more. It does not expose Laravel policy
internals, raw access-control rules, arbitrary SQL, or Qdrant's filter DSL;
those remain entirely inside their respective owning layers, the same
encapsulation discipline ADR-0014 already applies to `VectorStore`
(*"no application code outside one isolated adapter... depends on
Qdrant-specific concepts"*). What it carries is enough to constrain
retrieval safely and efficiently:

- `workspace_id` — the mandatory tenant boundary, matching ADR-0006's
  explicit-propagation invariant;
- the authoritative `embedding_space_generation_id` and
  `workspace_corpus_generation_id` for the workspace, resolved explicitly
  by Laravel from PostgreSQL and passed explicitly — never inferred by
  Python, exactly as ADR-0014's "Explicit propagation, never inference"
  invariant already requires for every other call into `VectorStore`;
- the resolved set of eligible `Document`/version identifiers
  `EligibilityResolver` computed — the mechanism that actually constrains
  which evidence Python may return. Python's `VectorStore` adapter
  translates this identifier set into a Qdrant-side filter internally
  (alongside the mandatory `workspace_id`/`workspace_corpus_generation_id`
  filters ADR-0014 already requires); Laravel never constructs or sees
  Qdrant filter syntax itself. The resolved eligible set is expected, in
  the ordinary case, to be small relative to a workspace's total corpus —
  location and access narrowing already bound it before temporal/governance
  narrowing is even applied. If a future workspace's eligible set becomes
  large enough that an explicit identifier list is impractical, that is
  measured evidence for a follow-up decision to introduce a more compact
  scope representation — consistent with this platform's repeated
  preference (ADR-0010, ADR-0013, ADR-0014) not to solve an unmeasured
  problem speculatively — not something V1 needs to design against now;
- a bounded candidate limit (`candidate_k`; see "Retriever" below).

For ordinary modes (`CURRENT`, `VALID_AT_DATE`), `EligibleRetrievalScope`
represents exactly one resolved evidence scope. For `COMPARE`, it preserves
two independently-resolved sides, never merged (see below).

### `COMPARE`: two independently-resolved sides, never merged

`COMPARE` is a genuine V1 requirement, not speculative future-proofing —
*"what changed in the latest policy,"* *"compare the current version with
the 2022 version,"* and *"how has this policy changed"* are all real
questions this platform commits to answering correctly from V1. The planner
expresses only symbolic anchors (`CURRENT`, `AT_DATE(date)`, `PREVIOUS`);
`EligibilityResolver` resolves each side independently, using ADR-0017's
derivation for each anchor on its own terms.

The two sides are labelled `PRIMARY` and `COMPARISON` — deliberately not
`CURRENT`/`HISTORICAL`, to avoid colliding with `temporal_mode`'s own
`CURRENT` value, which could otherwise ambiguously mean either "the overall
mode is CURRENT" or "this side's anchor is CURRENT." `PRIMARY`'s anchor is
resolved first; `COMPARISON`'s `PREVIOUS` anchor, where used, is resolved
relative to whatever `PRIMARY` actually resolved to — one step back through
ADR-0017's attained-authority lineage ordering, never an independent
"second-latest" computation that could disagree with what `PRIMARY` turned
out to be.

`EligibleRetrievalScope` preserves both sides as two separate, independently
scoped evidence universes; the Retriever searches each side independently,
never merging them into one candidate list, and `RetrievalResult` preserves
which side every returned item belongs to throughout. Losing that
attribution would make a comparison answer indistinguishable from a single
confused one.

If either side cannot be resolved safely — `PRIMARY` finds no
authoritative version at all (a real ADR-0017 outcome, not an error);
`AT_DATE` names a date with no authoritative version; `PREVIOUS` has
nothing to be previous *to*, because `PRIMARY` itself resolved to "no
current version" — the whole request produces `COMPARISON_SCOPE_INCOMPLETE`
(see "Retrieval outcome taxonomy" below). There is no silent substitute
version, and no partial comparison silently returned with one side quietly
empty and unexplained.

### `Retriever`: a provider-neutral retrieval boundary, Python-owned, with no PostgreSQL access

`Retriever` is a provider-neutral abstraction, following the same
Open/Closed discipline as `Embedder` and `VectorStore`. Its input
conceptually includes: the original query text (from `retrieval_queries`);
the resolved `EligibleRetrievalScope` (one scope, or two for `COMPARE`);
the authoritative embedding-space and workspace-corpus generation
identities; and a bounded `candidate_k` — the retrieval-time candidate
count, deliberately named to distinguish it from any later, smaller
evidence count reranking (`R16-S07`) might eventually produce; this
document does not conflate the two.

**The Retriever performs scoped vector search only; it never reads
PostgreSQL, directly or indirectly.** The service-boundary direction this
platform has held throughout — Laravel owns PostgreSQL and authoritative
relational/domain data; Python owns AI/ML mechanics and vector-store
interaction (ADR-0002) — applies to retrieval exactly as it applies
everywhere else. This is a firmer position than an earlier draft of this
document took, which would have had the Retriever hydrate chunk text and
provenance directly from PostgreSQL by analogy with ADR-0014's rebuild
workflow; that analogy does not actually hold; see "Alternatives
considered" below for why it was rejected on review. Concretely, the
Retriever:

- embeds the query using the workspace's compatible **query** embedding
  profile (ADR-0013's `input_type=query` mapping), through the existing
  `Embedder` contract — it never re-embeds document/chunk text, which is
  already stored;
- validates embedding-profile compatibility against the generation
  identities it was given, the same defensive check ADR-0013 and ADR-0014
  already require rather than trusting dimension-matching alone;
- calls `VectorStore.search`, scoped by the mandatory `workspace_id` and
  `workspace_corpus_generation_id` filters ADR-0014 already requires, plus
  the eligible-identifier filter `EligibleRetrievalScope` supplies;
- applies only the explicit resolved scope — it never widens a search
  beyond what it was given, regardless of how few results come back;
- performs one narrow, mechanical defensive check on every candidate
  before returning it: that its Qdrant payload identifiers
  (`workspace_id`, `workspace_corpus_generation_id`,
  `embedding_space_generation_id`, `document_id`) actually match the scope
  it was given — the same "defensive, explicitly-checked field" discipline
  ADR-0014 already applies to `embedding_space_generation_id` generally,
  applied here to guard against a stale or incorrectly constructed filter,
  never a re-derivation of eligibility semantics;
- returns **candidate identities, scores and retrieval lineage only** —
  `chunk_id`, `document_id`, `workspace_corpus_generation_id`,
  `embedding_space_generation_id`, raw similarity score, rank, and
  retrieval method — every one of these fields is already present on the
  Qdrant point itself (ADR-0014's minimal payload), so this requires no new
  read access of any kind, PostgreSQL or otherwise. It returns no chunk
  text, no provenance, and no `DocumentFamily` or version-lineage
  information Python was never given and has no authoritative source for.

The Retriever must **not**: authorise; determine `CURRENT`; resolve dates;
interpret governance state; infer document lineage; answer; rerank; or read
PostgreSQL for any reason, including to hydrate chunk text, provenance, or
any other authoritative fact. Any of those would mean Python either
silently re-deriving a decision `EligibilityResolver` already made, or
acquiring a class of relational access this platform has deliberately never
granted it outside ADR-0014's own narrow, previously-decided rebuild
exception (see below).

**Batch hydration, the final eligibility recheck, and `RetrievalResult`
assembly are Laravel's job, not Python's.** After the Retriever returns
candidate identities, Laravel:

- batch-hydrates authoritative chunk text and provenance from PostgreSQL by
  the returned `chunk_id`/`document_id` values — an ordinary, already-
  authoritative read against data Laravel already owns;
- performs the final defensive identity/eligibility check: re-validating
  each returned candidate's document/version identity against current
  authoritative eligibility, using the same `EligibilityResolver` logic
  already used to build the original `EligibleRetrievalScope`. This closes
  the narrow staleness window between scope resolution and search
  completion by construction — Laravel already owns eligibility logic, so
  re-checking it a second time during hydration is Laravel checking its own
  prior decision again, not eligibility logic moving anywhere new;
- resolves `DocumentFamily` and version-lineage identity for each
  candidate, which Laravel already holds authoritatively and Python was
  never given;
- assembles the final, application-level `RetrievalResult` (below).

A candidate whose eligibility no longer holds at hydration time — because
something changed in the interval between resolution and search — is
dropped from the assembled result rather than silently included; this
document does not specify a distinct outcome code for that narrow case,
since it changes only the final candidate count Laravel already computes,
not the shape of the outcome taxonomy below.

### `RetrievalResult` and the retrieval outcome taxonomy

`RetrievalResult` is the single, uniform, **Laravel-assembled** response
for a completed retrieval request — Laravel constructs it directly for
every short-circuited outcome (`CLARIFICATION_REQUIRED`, `NO_ELIGIBLE_EVIDENCE`,
`TEMPORAL_SCOPE_UNRESOLVED`, `COMPARISON_SCOPE_INCOMPLETE`), and assembles
it, after batch hydration and the final eligibility recheck described in
"`Retriever`" above, for outcomes that actually reached a search
(`EVIDENCE_FOUND`, `NO_RETRIEVAL_CANDIDATES`, `RETRIEVAL_FAILED`). Python's
Retriever response is never returned to a caller as-is; it is always
Laravel's hydrated, re-checked assembly that constitutes `RetrievalResult`.
It is a typed semantic result, never a bare list of chunks or an empty
array with no explanation of why. At minimum it carries: a controlled
outcome classification; ranked candidates, each with its raw retrieval
score, rank, retrieval method, chunk identity, `Document` identity,
`DocumentFamily` identity, resolved version identity and lineage position,
hydrated provenance and chunk text, and which `COMPARE` side it belongs to
where applicable. Raw similarity scores are retained as raw scores; they
are never described, labelled, or presented as probabilities — a cosine
similarity is not a calibrated confidence, and this document does not
pretend otherwise, the same honesty required of `RetrievalPlanner`'s own
confidence handling above.

**The controlled outcome taxonomy**, at minimum:

- **`EVIDENCE_FOUND`** — the ordinary successful path: a non-empty eligible
  scope was searched and the Retriever returned one or more candidates.
  **V1 does not, and must not, reject a non-empty candidate set on raw
  score quality.** Plain dense retrieval with `candidate_k > 0` will
  ordinarily return some nearest neighbours even when they are weak, and
  without a calibrated acceptance policy — explicitly out of this
  document's scope, deferred to the hybrid retrieval/reranking and
  evaluation architecture (`R16-S05`, `R16-S07`) — this document has no
  truthful basis for classifying a low-scoring result as a semantic
  non-match. Every non-empty candidate set is `EVIDENCE_FOUND`, however
  weak any individual score is; deciding whether the evidence is *good
  enough* is deliberately left to later, explicitly evidence-quality-aware
  work, not invented here as an implicit threshold.
- **`NO_ELIGIBLE_EVIDENCE`** — `EligibilityResolver` resolved successfully,
  but the eligible scope is empty; the Retriever is never invoked, since
  there is nothing to search.
- **`NO_RETRIEVAL_CANDIDATES`** — the eligible scope was non-empty and was
  searched, but the Retriever returned zero candidates. This is a purely
  factual, count-based outcome — the scoped vector search produced no
  points at all (for example, an otherwise-eligible corpus segment that
  happens to have no indexed content yet) — never a judgement about
  whether any returned candidate was semantically close enough. It is
  distinct from `NO_ELIGIBLE_EVIDENCE`'s Laravel-side outcome because the
  two mean different things: nothing was allowed to be searched, versus
  something was searched and literally nothing came back. This document
  deliberately does not define, and does not permit any Stage 16.4
  implementation to define, a raw-score threshold below which a candidate
  is treated as though it were absent — that would be exactly the
  uncalibrated, invented quality judgement this taxonomy is designed to
  avoid making prematurely.
- **`TEMPORAL_SCOPE_UNRESOLVED`** — a defensive, expected-to-be-rare
  outcome: the resolved `RetrievalPlan`'s temporal selector could not be
  deterministically resolved by `EligibilityResolver` even after passing
  the planner's own structured-output validation (for example, a
  plan/resolver contract-version mismatch, or a selector shape the
  resolver does not recognise). This is not the normal outcome for "no
  version existed at that date" — that case simply produces an eligible
  scope excluding that family and is not, by itself, a whole-query failure.
- **`COMPARISON_SCOPE_INCOMPLETE`** — `COMPARE` specific: either side could
  not be resolved safely.
- **`CLARIFICATION_REQUIRED`** — genuine ambiguity, reported either by the
  planner (unresolved temporal/comparison intent) or by `EligibilityResolver`
  itself (an `applicability_reference` that failed to resolve to exactly
  one authoritative location — see "Location and applicability" above).
  Always short-circuits before the Retriever runs; where the planner is
  the source, `EligibilityResolver` is never invoked either.
- **`RETRIEVAL_FAILED`** — a genuine operational failure (timeout, Qdrant
  unavailable, an embedding failure, a protocol/authentication failure)
  distinct from any legitimate "no evidence" semantic outcome above, so a
  caller can always tell "I looked and found nothing" apart from "something
  broke and I could not look."

These are internal semantic classifications. User-facing wording is a
separate, later concern and must never leak sensitive existence or access
information through its phrasing — a caller must not be able to
distinguish, from the user-facing message alone, "this document does not
exist" from "this document exists but you cannot see it," the same `404`-
not-`403` concealment discipline ADR-0006 already requires. Every
security-sensitive failure fails closed; nothing about a failure — an
authentication error, a planner failure, a Retriever failure — ever
responds by widening the search.

### The Laravel → Python synchronous retrieval-call protocol (`rc1`)

**Why the existing worker protocol does not fit.** ADR-0009's `v1` and
ADR-0015/0016's `v2` protocols share a specific shape: Python is the
signer, Laravel is the verifier; the caller is a single fixed principal
(`ingestion-worker`) that holds a processing lease against a durable
`event_id`; requests are part of an asynchronous, at-least-once-delivered,
resumable workflow; and idempotency is enforced through a durable event
ledger recording claims and completions. None of that fits retrieval.
Retrieval reverses the direction (Laravel calls Python); has no lease,
attempt, or `event_id` — a retrieval request is not a multi-step attempt
that can be abandoned and resumed, it is one bounded synchronous call; and
is a read operation with no state-mutation to make idempotent through a
durable ledger, only ordinary bounded-retry safety to provide. Reusing the
`v2` purpose-scoping mechanism under the existing `ingestion-worker`
principal was considered and rejected specifically because purpose-scoping
exists to bind a signature to *one specific operation for one specific
principal* — folding a structurally unrelated caller and call shape into
that same principal and key ring would blur exactly the boundary
purpose-scoping exists to keep sharp, forcing a synthetic `event_id` and
lease-shaped fields onto a request that has neither.

**What is reused, deliberately.** The parts of the existing protocol that
generalise regardless of direction are kept: HMAC-SHA256 signing; a Key ID
plus Base64-encoded, ≥32-byte secret key ring supporting overlapping keys
for rotation; a timestamp-bound freshness window; a canonical
string-to-sign binding method, path and an exact body digest so a captured
signature cannot be replayed against a different request; purpose-bound
signing, so a signature valid for one operation is never accepted for
another; and a normative, independently-verified test vector. These
properties are direction-agnostic and have already proven themselves
through two prior ADRs; there is no reason to invent a weaker replacement
for a call that is, if anything, more latency-sensitive and more directly
in a user's critical path than ingestion ever is.

**The new protocol, named `rc1`** (Retrieval Call, version 1) — its own
independent namespace, never confused with, and never sharing a Key ring
or purpose value with, ingestion's `v1`/`v2` protocol:

- **Transport: authenticated TLS is mandatory, not optional hardening.**
  HMAC-SHA256 provides authentication and integrity — it proves a request
  genuinely came from the `retrieval-caller` principal and was not altered
  in transit — it provides **no confidentiality whatsoever**. `rc1`
  requests carry the raw user question; `rc1` responses carry resolved
  evidence identifiers and, after Laravel's hydration pass, would expose
  protected content if intercepted. Without TLS, both would be plainly
  readable to anything positioned on the network path, HMAC signature or
  not. `rc1` MUST run exclusively over authenticated TLS, restated here
  explicitly rather than left to be assumed from ADR-0009's existing
  "HTTPS over private networking" pattern, because `rc1`'s payload
  sensitivity is categorically higher than the lifecycle-only metadata
  ADR-0009 originally protected. This is a transport requirement in
  addition to, never a substitute for, HMAC authentication — the two
  protect against different threats (an unauthenticated caller, versus an
  eavesdropping or tampering network position) and neither is sufficient
  alone.
- **Principal**: a new, dedicated machine identity, `retrieval-caller`,
  representing Laravel-as-caller. Laravel signs; Python verifies — the
  reverse of ingestion's direction. Python holds its own key ring for this
  principal, entirely separate from any key Laravel holds for verifying
  ingestion-worker requests.
- **Purposes**: `retrieval.plan` (the `RetrievalPlanner` call) and
  `retrieval.search` (the `Retriever` call), each independently signed; a
  signature valid for one is never accepted for the other. Future
  extensions (for example, a reranking call once `R16-S07` is designed) add
  a new purpose to this same protocol without requiring a new signature
  format, exactly as ingestion's `v2` protocol already demonstrated four
  new purposes fit its existing six-field shape without modification.
- **Canonical string-to-sign** — seven UTF-8 fields joined by a single
  `\n`, with no `event_id`/lease field, since none exists for this call
  shape, but with a signed `request_id` added for replay protection (see
  below):

  ```text
  <timestamp>\n<method>\n<request-path>\n<body-sha256>\n<workspace_id>\n<purpose>\n<request_id>
  ```

  `workspace_id` replaces `event_id` as the field that binds a signature to
  *what this request is about* — the meaningful scoping identity for a
  stateless, synchronous call is the workspace the request concerns, not an
  ingestion attempt that has no equivalent here. `request_id` is a new,
  seventh field this protocol adds beyond what ingestion's six-field shape
  established — necessary specifically because retrieval has no durable
  event ledger to fall back on for replay safety (see below); it must be
  part of the signed content, not a separate unsigned header, or an
  attacker capturing a valid request could strip and swap it to defeat
  replay detection entirely.
- **Freshness**: the same bounded clock-skew window ADR-0009 already
  establishes (five minutes initially), pending Stage 16.3 tuning if a
  measured reason justifies a tighter window for a synchronous,
  user-facing call — not assumed here without evidence.
- **Replay protection — bounded, lighter-weight than ingestion's durable
  ledger, but not absent.** A timestamp freshness window alone means a
  captured, still-valid request could be resent during that window and
  re-authenticate successfully; because a replayed retrieval request can
  return protected evidence, this document does not leave that gap open
  merely because retrieval is read-only. The defence is deliberately
  minimal:
  - Laravel generates a fresh, unique `request_id` (a UUID) for every
    `rc1` call and includes it in both the signed fields and the request
    body.
  - Python maintains a **bounded, server-side replay-suppression cache**
    of `request_id` values it has already accepted, scoped per principal,
    retained only for the duration of the freshness window — anything
    older than the window is already rejected on timestamp grounds
    regardless, so the cache never needs to retain an entry longer than
    that. This is explicitly **not** a durable ledger, **not** a
    processing-attempt record, and **not** a queue — it is a small,
    time-bounded, in-memory (or equivalently bounded) structure whose only
    job is refusing to accept the same signed `request_id` twice inside one
    freshness window. Losing this cache's contents on a Python process
    restart is an accepted limitation for V1, not silently ignored: it
    narrowly reopens the replay window for whatever requests were
    in-flight at that exact moment, bounded in scope and duration by the
    freshness window itself; a shared, distributed cache (for multiple
    Python instances or restart resilience) is a legitimate Stage 16.4
    implementation choice if warranted, not fixed here.
  - A request presenting a `request_id` already present in the cache
    within the window is rejected outright as a replay — a distinct
    failure classification from an expired timestamp or a bad signature,
    never silently served as though it were the original request's result,
    since retrieval has no durable "already-completed" record to serve
    from the way ingestion's claim protocol does.
- **Idempotency and retry posture — a deliberate, named difference from
  ingestion.** Retrieval is a read-only operation with no state mutation to
  make idempotent through a durable ledger. `rc1` requires no `event_id`,
  no claim record, and no "already-completed" idempotent replay-return
  behaviour; ordinary bounded HTTP retry with backoff is sufficient safety
  for a request that does not change anything on either side, **provided
  every genuine retry mints a fresh `request_id`** rather than resending
  the exact same signed request — see "Safe retries versus replay" below
  for the distinction this depends on. This is stated explicitly so it is
  never mistaken for an oversight relative to the ingestion protocols' much
  heavier idempotency machinery — the two call shapes have genuinely
  different requirements, not merely different implementations of the same
  one.
- **Timeout behaviour**: since the call sits inside a user request's
  critical path, Laravel applies a bounded request timeout to every `rc1`
  call; a timeout produces `RETRIEVAL_FAILED` (see "Retrieval outcome
  taxonomy" above), never an indefinite wait and never a silent, unbounded
  retry loop — the same "no unbounded automatic loop" discipline ADR-0007
  already requires of document processing retries, applied here to a
  synchronous call instead of an asynchronous one.
- **Bounded request size**: `EligibleRetrievalScope`'s eligible-identifier
  list and `candidate_k` are both bounded, for the same reason ADR-0015's
  chunk submission batches are bounded — an unbounded request body is never
  an acceptable shape for an internal contract, synchronous or not.
- **Workspace context**: `workspace_id` is mandatory in both the signed
  fields and the request body; Python treats it as untrusted input until
  the signature verifies, exactly as ADR-0006 already requires of any
  tenant identity crossing a service boundary — Python does not
  independently re-authorise the workspace, since authorisation already
  happened in Laravel before this call was made; the signature establishes
  that the request genuinely came from Laravel, not that the workspace
  access itself is valid.
- **Request correlation / trace context**: the existing OpenTelemetry trace
  context (ADR-0012) propagates across this call exactly as it already
  propagates across every other hop of one logical request; no new,
  parallel correlation mechanism is introduced. `request_id` serves replay
  protection specifically and is distinct from the trace/correlation
  identifier, though both may be attached to the same span for debugging.
- **No queue or worker lease is expected or used** for `rc1` calls, at any
  point — this is a genuinely synchronous, request/response protocol, not
  an asynchronous one dressed up to look synchronous.

**Safe retries versus replay — the distinction the mechanism above depends
on:**

- **Duplicate identical retry caused by network uncertainty** (Laravel's
  HTTP client times out waiting for a response that actually succeeded
  server-side, and application-level retry logic resends the call):
  Laravel always mints a **new** `request_id` for a deliberate retry — it
  never resends the exact same signed request bytes. Because retrieval is
  read-only, a second, freshly-signed attempt is safe and produces the same
  kind of answer as the first would have; there is no state to duplicate.
  This is the recommended, and required, retry behaviour.
- **A replayed request presenting the same `request_id`** within the
  freshness window: rejected outright by the replay-suppression cache,
  regardless of whether the signature, timestamp, workspace and purpose are
  all otherwise valid — a captured-and-resent request is never treated as
  though it were a fresh, legitimate call.
- **A retry with a fresh `request_id`**: always treated as an entirely new,
  independently authenticated request; this is the normal, expected shape
  of any legitimate client-side retry, per above.
- **An expired request** (timestamp outside the freshness window): rejected
  on timestamp grounds before the replay cache is even consulted — the
  cheaper check runs first, consistent with ADR-0009's existing freshness
  behaviour.
- **Wrong workspace, wrong purpose, or an invalid signature**: rejected by
  signature verification exactly as today; none of the replay-protection
  additions above change this existing, unmodified behaviour.

**Normative test vector**, computed and independently cross-verified (via
two separate HMAC-SHA256 implementations) for `retrieval.search`, using the
same non-secret development secret already established as this project's
canonical illustrative key:

```text
secret (Base64):
MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=

timestamp:
1785326400

method:
POST

request path:
/api/internal/retrieval/search

exact body:
{"contract_version":1,"workspace_id":"5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41","embedding_space_generation_id":"7c1a2b3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d"}

body SHA-256:
9baff7b79505e18f84ab1a202ea9c42109876e09e23003c67f407d94c9aab215

workspace ID:
5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41

purpose:
retrieval.search

request ID:
b6e4a1d2-9f3c-4b7a-8e2d-5c1f0a9b8d7e

expected signature:
rc1=f2329579bdd74d6871d52c52b06095a7ce2fdb42d57ffdb9fe541990040195e8
```

### Observability and privacy

`RetrievalPlanner`, `EligibilityResolver` and the Retriever all inherit
ADR-0012's foundation directly; none invents ad hoc logging or a parallel
telemetry mechanism. Safe, allowlisted telemetry may include: `temporal_mode`;
outcome classification; candidate count; eligible-scope size; call
durations; embedding-profile classification; the embedding-space and
workspace-corpus generation identities involved; retrieval method; and
failure classification — all identifiers or coarse counts, never free text.
Never captured by default: the raw user question, document or chunk text,
vectors, credentials, HMAC secrets or signatures, or provider payloads —
exactly ADR-0012's existing allowlist-first posture, applied to this
pipeline stage the same way ADR-0013 already applied it to embedding.
Where ADR-0006's Search/RAG audit layer becomes relevant (*"who searched,
in which workspace, the query, retrieved documents/chunks, citations, the
model used, latency, token usage, cost, and correlation identifiers"*) is a
Stage 16.3 implementation decision about which of those already-anticipated
fields this stage populates; this document does not redesign that audit
layer, only confirms retrieval's telemetry obligations sit alongside it,
governed by the same privacy default.

### Forward compatibility: hybrid retrieval, reranking and evaluation

Nothing in this document blocks, and this document commits that each of
the following remains achievable without redesigning the model above:
sparse/keyword retrieval and fusion, added as an additional retrieval
method within the same `Retriever` boundary (`R16-S07`); reranking,
consuming `RetrievalResult`'s candidates and producing a smaller, reordered
evidence set without requiring `EligibleRetrievalScope` or the outcome
taxonomy to change (`R16-S07`); the evaluation harness (`R16-S05`) measuring
`RetrievalPlanner` and `Retriever` output directly, since both already
produce typed, inspectable results rather than opaque ones; and a future
model-assisted query-decomposition planner populating `retrieval_queries`
with more than one entry, additive to the shape already defined above. None
of these is implemented by this document or by Stage 16.2 — naming them
here is what lets `R16-S05` through `R16-S08` inherit a settled contract
instead of re-deriving one, the same way ADR-0013's and ADR-0014's own
forward-looking sections did for the stages that followed them.

## Roadmap clarification: Phase 16 stage structure

Recorded here as the planning correction this ADR's review surfaced,
pending separate application to `PROJECT_ROADMAP.md`, `IMPLEMENTATION_GUIDE.md`
and `tasks.json` after this document is reviewed — this ADR does not itself
modify any of them. `R16-S01` (ADR-0017, accepted) currently has no
explicit implementation session: Phase 16's stage sequence, as it stands,
moves directly from defining the versioning/temporal-authority domain model
to defining this document, without a session that actually builds
ADR-0017's relational/domain foundation (the `DocumentFamily` backfill
migration, the lineage and governance tables, the structural constraints
"Unambiguous temporal succession" fixes) before retrieval implementation
would need it. The corrected sequence:

```text
R16-S01 — Define Document Versioning and Temporal Authority
  completed; ADR-0017

R16-S02 — Define Retrieval Planning, Eligibility and the Retriever Contract
  architecture-only; ADR-0018 (this document)

R16-S03 — Implement Document Versioning and Temporal Authority Foundation
  implementation of ADR-0017's relational/domain model

R16-S04 — Implement Semantic Retrieval
  implementation of ADR-0018 (this document) against the completed
  Stage 16.3 foundation

R16-S05 — Define Evaluation and Quality-Gate Architecture

R16-S06 — Implement Retrieval Evaluation

R16-S07 — Define Hybrid Retrieval and Reranking Architecture

R16-S08 — Implement Hybrid Retrieval and Reranking
```

This inserts one implementation session (`R16-S03`) and renumbers every
session after it by one, extending Phase 16 from seven stages to eight.
`R16-S01`'s completed record, commit and tag are unchanged and are not
touched by this correction. This document's own acceptance record belongs
at `R16-S02` under either the old or corrected numbering — the correction
affects only sessions after it.

## Alternatives considered

### A rule-based or regex-based planner as the primary V1 production implementation

Explicitly rejected, not merely deprioritised. A keyword or pattern-based
classifier cannot reliably distinguish the open-ended range of ways a real
user phrases a temporal or comparison question — *"as it stood before the
update," "what changed since last year," "the version before this
one"* — without the classifier itself becoming an ad hoc, ever-growing
pattern library that is really trying to approximate natural-language
understanding badly. A rule-based implementation remains legitimate as a
test double, deterministic fallback, evaluation baseline, or comparator —
never as the thing V1 actually runs in production.

### Reusing ADR-0009/0015/0016's worker HMAC protocol unmodified for retrieval calls

Considered and rejected — see "The Laravel → Python synchronous
retrieval-call protocol" above for the full reasoning. The existing
protocol's principal, direction, lease/`event_id` assumptions and
idempotency model are all built around a structurally different call
shape; forcing retrieval into that shape would mean inventing synthetic
lease/attempt fields for a call that has no lease or attempt, and blurring
one principal's key ring across two unrelated trust relationships.

### mTLS or a static bearer token for the synchronous contract

Both considered and rejected for the same reasons ADR-0009 already
rejected them for the asynchronous worker boundary, and neither
consideration is weaker here. mTLS remains deferred: certificate issuance,
rotation and proxy/load-balancer integration are disproportionate
operational surface for one internal call boundary at this stage. A static
bearer token is rejected outright: it does not bind method, path or body,
and a captured token remains reusable until rotated — weaker than what
this platform has already established it will not accept for a materially
less latency-sensitive call.

### Leaving `rc1`'s transport confidentiality implicit, relying on ADR-0009's existing "HTTPS over private networking" pattern

Considered, since the pattern already exists and nothing about it is
wrong. Rejected as insufficiently explicit for this specific protocol: HMAC
proves authenticity, not confidentiality, and `rc1`'s payload — a raw user
question, and evidence identifiers after hydration — is materially more
sensitive than the lifecycle-only bodies ADR-0009 originally protected.
Leaving confidentiality to be inferred from a general pattern risks a
future implementation treating HMAC alone as sufficient protection for a
call whose content genuinely needs it stated as a hard requirement, not
an assumption.

### A timestamp freshness window alone, with no replay-suppression mechanism

This was the first full draft's position. Rejected on review: a freshness
window bounds *how long* a captured request remains authenticatable, but
does not prevent it being *resent* within that window — and because a
replayed retrieval request can return protected knowledge (unlike, for
example, a replayed lease-renewal request, which the durable event ledger
already renders harmless), that gap matters here in a way it does not for
ingestion. A bounded, time-limited replay-suppression cache closes it at a
cost proportionate to the risk — no queue, no lease, no durable ledger, just
a small structure scoped to the freshness window itself.

### A durable, ingestion-style event ledger for retrieval replay protection

Considered and rejected as disproportionate. Ingestion's durable ledger
exists to make an asynchronous, multi-step, resumable claim idempotent
across worker restarts and redeliveries — none of which applies to a
synchronous, single-round-trip, read-only call. A bounded, freshness-
window-scoped cache provides everything retrieval actually needs (refusing
an exact resend) without the durability, recovery and reconciliation
machinery a real processing-attempt ledger would require for a call shape
that has no attempt to track.

### Merging `COMPARE`'s two sides into one ranked candidate list

Rejected. A single merged list of "current" and "historical" chunks,
undifferentiated, is not a comparison — it is two answers pretending to be
one, and would force generation (Phase 17) to somehow reconstruct which
evidence belonged to which side after the fact, information this layer
already has and must not discard.

### Treating LLM confidence as a calibrated 0–1 probability

Rejected. A language model's internal confidence signal is not a
calibrated probability in any rigorous sense, and presenting it as one
would misrepresent genuine uncertainty as a precise, comparable number it
is not. Controlled classification (`CLARIFICATION_REQUIRED` with a
specific reason) states what is actually known — the plan is resolved, or
it is not, and if not, why — without manufacturing false precision.

### Automatically turning a recognised semantic term into a hard metadata filter

Rejected, and treated as a security-adjacent decision, not merely a
relevance one — see "Metadata classification" above. Converting *"the HR
policy"* into `department_id = HR` on the planner's own inference would let
prompt phrasing silently narrow (or, worse, in some other case, appear to
justify widening) the search universe based on an unverified guess,
without any deterministic Laravel logic ever having decided that mapping
was correct. Relevance is the Retriever's semantic search's job; hard
filters are `EligibilityResolver`'s, and only from authoritative data.

### The planner resolving `applicability_reference` to an `OrganisationalLocation` identifier itself

Considered and rejected. The planner has, and must continue to have, zero
access to PostgreSQL — resolving a name to an authoritative ID is exactly
the kind of lookup that access would require. Keeping the planner's output
a bare semantic string and moving resolution entirely into
`EligibilityResolver` preserves the planner's total isolation from
authoritative data intact, and keeps the one deterministic lookup this
document allows fully auditable inside Laravel.

### Pushing `applicability_reference` to Qdrant as a free-text payload filter

Considered and rejected. This would reintroduce exactly the "prompt term
becomes a search-time filter" pattern "Metadata classification" and the
`department_id = HR` alternative above already reject, only relocated to
Qdrant instead of PostgreSQL — and it would bypass ADR-0017's applicability
rules entirely (`UNIVERSAL` matching, `Region`-to-`Site` extension), since
Qdrant has no notion of either. The reference must be resolved and
validated before it can narrow anything, which requires PostgreSQL access
Python does not have (see "`Retriever`" above) — resolution belongs in
`EligibilityResolver`, not in a filter clause.

### A bounded list of multiple `applicability_reference` values for V1

Considered, since a question could in principle name more than one
location. Rejected for V1 in favour of a single, optional reference: no
stated V1 requirement names multiple simultaneous locations in one
question, and a list reopens real unresolved design questions (do multiple
locations combine as AND or OR narrowing? how does an ambiguous match on
one of several interact with the others?) with no concrete case to design
against yet. A single reference is the smaller, more conservative contract;
widening it to a bounded list later, if a genuine need appears, is additive
to this shape, not a redesign — the same "don't solve an unmeasured
problem speculatively" reasoning this platform applies elsewhere (ADR-0010,
ADR-0013, ADR-0014).

### Silently ignoring an unresolved or ambiguous `applicability_reference`

Considered, as the simpler alternative to producing `CLARIFICATION_REQUIRED`.
Rejected: for a question like *"the medication procedure at Blackpool,"*
silently searching without that narrowing risks returning evidence that
applies to a *different* location entirely, presented as though it
answered the question asked — a materially worse outcome than pausing to
ask the user to clarify. This mirrors `COMPARE`'s own "no silent
substitute version" rule exactly, applied to the same underlying concern:
an unresolved eligibility-relevant fact must never be quietly dropped in a
way that lets a wrong answer look like a right one.

### Collapsing `EligibilityResolver` into Python, alongside the Retriever

Rejected. This would move a genuinely deterministic authorisation-adjacent
decision — which evidence is eligible — into the AI service, contradicting
ADR-0002's and ADR-0006's foundational position that Laravel is the
security boundary and Python is never independently authoritative for
tenant or eligibility decisions. It would also mean granting Python direct
access to governance and temporal-authority state, widening Python's
PostgreSQL access footprint — currently exactly zero for retrieval, see
below — for no benefit over keeping the decision where the platform's
existing trust boundary already sits.

### The Retriever hydrating chunk text and provenance directly from PostgreSQL

This was the first full draft's design, justified there by analogy with
ADR-0014's rebuild workflow (*"read the authoritative, persisted chunks and
their lineage from PostgreSQL"*). Rejected on review: the analogy does not
actually hold. ADR-0014's rebuild read is a narrow, batch, operator-
triggered maintenance path with no per-user-request latency pressure and no
live eligibility question attached to it; retrieval is a synchronous,
per-query, user-facing path where granting Python its own PostgreSQL read
would establish a second, independent route into authoritative relational
data outside Laravel's ownership, for a call shape that does not actually
need it — every field the Retriever needs to identify a candidate
(`chunk_id`, `document_id`, the generation identities) is already present
on the Qdrant point itself, per ADR-0014's existing minimal payload.
Reversing this was not a cosmetic change: it also removed the need for the
narrow eligibility-staleness trade-off the first draft had accepted (see
below), since Laravel re-checks eligibility itself during the same
hydration pass it already needs to perform.

### Accepting a narrow eligibility-staleness window instead of a mandatory Laravel-side recheck

This was the first full draft's position: a query's `EligibleRetrievalScope`
could theoretically go stale in the interval between `EligibilityResolver`
resolving it and the Retriever's search completing, and the first draft
accepted that narrow window for V1 rather than re-checking eligibility a
second time. Rejected on review, once Python no longer hydrates candidates
directly (see above): Laravel already has to touch PostgreSQL once more per
request regardless, to batch-hydrate chunk text and provenance for
whatever candidates the Retriever returned. Re-running `EligibilityResolver`'s
check against those same candidates during that same pass closes the
staleness window at effectively no additional round trip, so accepting the
staleness as a trade-off no longer buys anything — the cost that trade-off
was avoiding is now paid anyway, so there is no reason not to also get the
correctness benefit.

### Naming `COMPARE`'s two sides `CURRENT`/`HISTORICAL`

Considered and rejected in favour of `PRIMARY`/`COMPARISON`. `temporal_mode`
already uses `CURRENT` as one of its own values; reusing the same word as a
side label would make "the mode is CURRENT" and "this side's anchor is
CURRENT" ambiguous in exactly the kind of subtle way this platform has
repeatedly preferred to name precisely rather than gloss over (mirroring
ADR-0017's own insistence on precise terminology for "attained authority").

### `NO_SEMANTIC_MATCH`, defined as a raw-score quality judgement

This was the first full draft's outcome name and definition — "the eligible
scope was searched, but no chunk semantically matched the query." Rejected
on review as untruthful for V1: plain dense retrieval with `candidate_k > 0`
ordinarily returns some nearest neighbours even when none of them are
actually a good match, and this document has, deliberately, no calibrated
acceptance threshold to decide when a returned candidate counts as "no
match" versus "a weak match" — that policy is explicitly deferred to the
evaluation and hybrid retrieval/reranking architecture (`R16-S05`,
`R16-S07`). Naming and defining an outcome this document has no honest
basis for computing would have quietly smuggled an invented threshold into
a document that elsewhere insists raw scores are never treated as
calibrated signals. Replaced with `NO_RETRIEVAL_CANDIDATES`, a purely
count-based outcome ("the Retriever returned zero candidates") that this
document *can* compute truthfully without inventing a quality policy —
see "`RetrievalResult` and the retrieval outcome taxonomy" above.

## Consequences

### Positive

- Retrieval inherits one settled definition of "eligible evidence,"
  combining ADR-0017's temporal-authority model with access and
  applicability, rather than each future retrieval-touching change having
  to re-derive what "eligible" means.
- The LLM-backed planner is bounded to exactly the task it is good at
  (classifying intent) and structurally prevented from the tasks it should
  never perform (authorising, resolving IDs, retrieving) — a prompt cannot
  expand what a user can see, regardless of how it is phrased.
- `COMPARE` is a first-class, correctly-scoped V1 capability from the
  start, rather than a single-scope design retrofitted later to support
  two.
- A new, purpose-built synchronous protocol, built from already-proven
  components (HMAC-SHA256, Key ID rotation, purpose-binding,
  freshness windows), gives Laravel-to-Python calls the same rigor as
  Python-to-Laravel calls already have, without forcing an asynchronous
  protocol's assumptions onto a synchronous call.
- The outcome taxonomy lets every layer above retrieval (eventually,
  generation) distinguish "nothing is eligible," "nothing matched," "the
  question needs clarification," and "something broke" — four genuinely
  different situations a single boolean or an empty list would otherwise
  collapse together.
- `RetrievalResult`'s typed lineage fields directly satisfy
  `PROJECT_ROADMAP.md`'s "Design constraint — Quality lineage across the
  pipeline" for the retrieval link of that chain.
- Forward compatibility for hybrid retrieval, reranking and evaluation is
  confirmed architecturally before any of the three is built, the same way
  ADR-0013's and ADR-0014's own forward-looking sections already did for
  the stages that followed them.
- Python's PostgreSQL access footprint for retrieval is exactly zero — the
  Retriever returns only identities, scores and lineage already present on
  the Qdrant point, never touching PostgreSQL directly — keeping the
  service-boundary direction (ADR-0002) unambiguous for this pipeline
  stage rather than accumulating narrow, hard-to-audit exceptions.
- The eligibility-staleness window an earlier draft accepted as a trade-off
  is closed by construction, not by extra work: Laravel already has to
  touch PostgreSQL once to hydrate returned candidates, and re-checking
  eligibility during that same pass costs no additional round trip.
- A question naming a specific organisational location can be answered
  correctly from V1 onward, without either silently ignoring the location
  or letting a prompt term become an unverified hard filter — the one
  narrow exception this document makes to "descriptive terms are never
  filters" is bounded, deterministic, and justified entirely by ADR-0017's
  own applicability model, not by a general relaxation of that rule.
- `rc1`'s confidentiality requirement and replay defence are stated
  explicitly rather than left to be inferred from an existing pattern or
  assumed safe because the call is read-only — closing two real gaps
  (content exposure in transit; a captured request being resent to
  extract protected knowledge) before any implementation exists to get
  them wrong, the same "decide it before Stage 16.3 discovers it the hard
  way" discipline this platform has applied at every other protocol
  boundary.

### Negative

- Two new provider-neutral abstractions (`RetrievalPlanner`, `Retriever`)
  and a new domain service (`EligibilityResolver`) are real implementation
  surface for Stage 16.3 and 16.4, not a free consequence of naming them
  here.
- A production LLM call now sits on the critical path of every retrieval
  request, introducing latency, cost and an external dependency (mirroring
  the same trade-off ADR-0013 already accepted for embedding) that a
  purely rule-based planner would not have had — accepted because retrieval
  quality for open-ended temporal phrasing is judged to need it.
- A new, independently-versioned internal protocol (`rc1`) is additional
  protocol surface the platform must implement, test and maintain
  correctly in both languages, alongside the existing `v1`/`v2` ingestion
  protocol — two internal authentication schemes instead of one, accepted
  because the two call shapes genuinely differ rather than superficially
  differing.
- Laravel now performs a second, distinct pass over the candidates the
  Retriever returns — batch hydration plus a full eligibility recheck —
  rather than trusting the originally resolved scope through to the
  response. This is real, additional per-request work on the synchronous
  critical path, accepted because it closes a genuine correctness gap at a
  cost that was already going to be paid for hydration regardless.
- `EligibleRetrievalScope`'s explicit eligible-identifier-list approach may
  need revisiting if a workspace's eligible document count ever grows large
  enough to make an explicit list impractical — deferred, per this
  document's own reasoning, until measured evidence justifies redesigning
  it.
- The three-way metadata classification (security/eligibility/descriptive)
  and the discipline of never inferring a hard filter from prompt content
  place a real, ongoing implementation burden on every future feature that
  touches retrieval filtering: it is easier, and this document explicitly
  forecloses, simply mapping a recognised term to a database column.
- `applicability_reference`'s deterministic name/alias resolution is real
  implementation surface (a lookup Stage 16.3 must build and keep correct
  as locations are renamed or given aliases), and an unresolved or
  ambiguous reference means a question that could, in principle, have been
  answered by ignoring the location instead produces a clarification
  request — a real, if deliberate, cost in requests-that-need-a-follow-up
  in exchange for never silently answering from the wrong location.
- `rc1`'s replay-suppression cache is additional server-side state Python
  must maintain and keep correctly bounded, and its contents do not survive
  a process restart — a narrow, accepted limitation for V1 (see "The
  Laravel → Python synchronous retrieval-call protocol" above), not a
  fully durable guarantee. A future multi-instance Python deployment will
  need a shared cache for this protection to hold across instances, left
  to Stage 16.4 rather than designed here.

## Architectural invariants

- Retrieval answers "which evidence is eligible," never generates an
  answer; generation remains Phase 17's concern.
- Security metadata, eligibility metadata and descriptive metadata are
  never conflated; a descriptive term is never automatically promoted to a
  hard filter without independent, deterministic proof it is the intended
  selector.
- `AuthorisedKnowledgeScope` is Laravel-owned, derived only from
  authenticated user/workspace/permissions, and is never expanded by
  anything downstream, including a prompt, a plan, or a failure mode.
- `RetrievalPlanner` never authorises, resolves IDs, queries PostgreSQL or
  Qdrant, retrieves, embeds, reranks, or answers; it performs bounded
  natural-language-to-typed-intent classification only.
- The V1 production `RetrievalPlanner` is LLM-backed; a rule-based
  implementation exists only as a test double, fallback, or evaluation
  baseline, never as the primary production path.
- `RetrievalPlan`'s `retrieval_queries` and `temporal_mode` axes are
  independent; V1 always resolves exactly one retrieval query per request,
  paired with one temporal mode.
- Confidence is never expressed as an uncalibrated numeric probability;
  genuine ambiguity produces `CLARIFICATION_REQUIRED` with a controlled
  reason.
- `EligibilityResolver` is Laravel-owned, deterministic, and may only
  narrow `AuthorisedKnowledgeScope`, never expand it.
- `CLARIFICATION_REQUIRED` always short-circuits before the Retriever runs,
  whether produced by the planner (before `EligibilityResolver` runs at
  all) or by `EligibilityResolver` itself (an unresolved
  `applicability_reference`, mid-resolution); no broad or fallback search
  ever occurs in its place.
- ADR-0016's dual gate (published Qdrant point plus PostgreSQL `INDEXED`)
  remains the unconditional prerequisite for retrievability; this
  document's eligibility model is additive, never a redefinition of
  `INDEXED`.
- Access and applicability are resolved independently; a location
  reference in a prompt never grants access on its own.
- `applicability_reference` is a bare semantic string only, never a
  database identifier, never carries permission meaning, and is limited to
  at most one per plan for V1; it is resolved deterministically (exact or
  alias match) by `EligibilityResolver` against authoritative
  `OrganisationalLocation` data, never fuzzily matched and never pushed to
  Qdrant as a free-text filter. An unresolved or ambiguous reference
  produces `CLARIFICATION_REQUIRED`; it is never silently dropped and never
  silently guessed.
- `EligibleRetrievalScope` never exposes raw SQL, Qdrant filter DSL, or
  Laravel policy internals to Python.
- `COMPARE`'s two sides (`PRIMARY`, `COMPARISON`) are always resolved and
  searched independently and are never merged into one candidate list;
  either side failing to resolve safely produces
  `COMPARISON_SCOPE_INCOMPLETE`, never a silent substitute.
- The Retriever never authorises, determines `CURRENT`, resolves dates,
  interprets governance state, infers lineage, answers, reranks, or reads
  PostgreSQL for any reason; its only defensive check against
  `EligibleRetrievalScope` is a mechanical payload-identity match, never a
  re-derivation of eligibility.
- Batch hydration of chunk text/provenance and the final eligibility
  recheck against returned candidates are Laravel's responsibility,
  performed after the Retriever returns candidate identities, never
  Python's.
- Raw retrieval similarity scores are never described or presented as
  probabilities, and are never used to define, in this document or by any
  Stage 16.4 implementation of it, a threshold below which a returned
  candidate is treated as absent.
- Every security-sensitive failure fails closed; no failure of any kind —
  planner, eligibility, or retrieval — ever results in a widened search.
- User-facing outcome wording never reveals, through its phrasing, whether
  a document does not exist versus exists but is inaccessible.
- The `rc1` protocol is independently versioned from, and shares no
  principal, key ring, or purpose namespace with, ingestion's `v1`/`v2`
  protocol.
- `rc1` requests are synchronous, use no processing lease and no
  `event_id`, and rely on ordinary bounded retry — using a fresh
  `request_id` per retry — rather than a durable idempotency ledger.
- `rc1` MUST run exclusively over authenticated TLS; HMAC-SHA256 signing
  provides authentication and integrity only and is never treated as
  providing confidentiality on its own.
- Every `rc1` request carries a signed, unique `request_id`; Python
  rejects, as a replay, any request presenting a `request_id` it has
  already accepted within the current freshness window, via a bounded,
  freshness-window-scoped server-side cache — never a durable ledger, never
  a queue, never a processing-attempt record.
- Telemetry for planning, eligibility and retrieval follows ADR-0012's
  existing allowlist-first posture; raw question text, chunk text, vectors,
  and protocol secrets/signatures are never captured by default.

## Scope boundaries

This document does not define:

- evaluation metrics, corpus design, or quality-gate policy — `R16-S05`;
- sparse/keyword retrieval, hybrid candidate fusion, or the `Reranker`
  contract and provider — `R16-S07`, per ADR-0013's existing commitment
  that reranking is bundled with hybrid retrieval in one ADR;
- calibrated evidence thresholds or abstention policy — deferred to the
  hybrid retrieval/reranking ADR, consistent with ADR-0013's forward
  direction;
- answer generation, prompt assembly, or citation construction — Phase 17;
- Administration UI for locations, governance actions, or retrieval
  configuration — Phase 19;
- activation of model-assisted query decomposition — the seam is preserved
  and integrated with `temporal_mode` above; V1 exercises only the
  identity/no-op planner ADR-0013 already committed to;
- exact `RetrievalPlan`, `EligibleRetrievalScope`, `RetrievalResult` class
  definitions, schemas, or serialisation formats — Stage 16.3/16.4
  implementation concerns, constrained by the invariants fixed here;
- the exact mechanism for translating an eligible-identifier set into a
  Qdrant filter, or the exact representation if that set later needs to
  become more compact than an explicit list — Stage 16.4 implementation
  work;
- broad agentic retrieval, multi-turn planning state, or any retrieval
  behaviour beyond one bounded request/response cycle per query.

These remain open for the stages named above to decide with the context
this document establishes.
