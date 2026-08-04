# Session Journal: R14-S01 — Define Vector Database Architecture

## Date

2026-08-04

## Session mode

Architecture and documentation only. No application code, migrations, models,
Qdrant client code, or `VectorStore` implementation were introduced.

## What happened

The session began with an explicit request for an independent architectural
review of Phase 14 (Qdrant vector storage) rather than a direct implementation
of the requester's stated preference. ADR 0006, 0007, 0010, 0011, 0012 and
0013, `PROJECT_ROADMAP.md`, `IMPLEMENTATION_GUIDE.md` and `tasks.json` were
read and reasoned from before any recommendation was formed. The review
confirmed that the requester's core instinct — PostgreSQL authoritative,
Qdrant a rebuildable search projection — was not a new decision but a
continuation of a commitment ADR 0007 had already made, and identified seven
questions (generation semantics, workspace isolation topology, ownership
split, provider abstraction, rebuild philosophy, payload strategy,
future-proofing) as the substantive open ground.

Three rounds of refinement followed before drafting:

1. **Embedding-profile scope for V1.** The requester asked whether ADR 0006
   actually required independent per-workspace embedding-profile selection or
   only workspace-scoped effective configuration. Re-reading ADR 0006 against
   its own stated purpose (a tenancy-classification exercise for a
   still-unimplemented ADR, not a V1 feature mandate) resolved this as the
   latter. This corrected an overreach in the initial review, which had
   treated live per-workspace divergence as already committed.
2. **Collection boundary versus generation boundary.** The requester
   challenged an initial "one collection per generation" recommendation,
   proposing that a generation might be a lifecycle concept rather than a
   storage-partition concept. Reasoning through this surfaced that "generation"
   was doing two different jobs — a profile-driven compatibility change and a
   corpus-only rebuild — and that conflating them would recreate the
   collection-growth problem already rejected for collection-per-tenant. This
   produced the two-concept model: embedding-space generation (profile-scoped,
   tied to a collection) and workspace corpus generation (PostgreSQL-owned,
   per-workspace, not collection-scoped).
3. **ADR drafting**, using the two-concept model as its architectural basis,
   with the two generation concepts named explicitly throughout to avoid an
   unqualified "generation" anywhere.

A bounded documentation-only amendment followed review of the drafted ADR,
addressing six points:

* making V1 behaviour for ordinary incremental document ingestion explicit
  (it extends the active workspace corpus generation; it does not create a
  new one), and recording the migration-concurrency invariant that a
  candidate generation must incorporate every authoritative change up to its
  cutover boundary before activation;
* correcting language that had overstated Qdrant's physical incapability of
  hosting multiple embedding spaces in one collection — the collection-per-
  embedding-space topology was reframed as a deliberate architectural choice,
  confirmed against current Qdrant documentation;
* correcting the named-dense-vector rationale for the same reason — it is not
  required to avoid a future collection migration, since Qdrant supports
  adding named vectors to an existing collection;
* adding explicit generation lifecycle state semantics for both concepts, and
  the invariants that hold regardless of exact state-machine implementation;
* requiring Qdrant payload indexes on `workspace_id`, `workspace_corpus_generation_id`
  and `document_id`, with justification for each and an explicit decision not
  to index every field by default;
* strengthening the completeness-verification definition so count equality
  alone is explicitly insufficient.

The ADR was approved after this amendment with no further changes requested,
and accepted.

## Decisions recorded

`docs/adr/0014-define-the-vector-storage-architecture-and-qdrant-topology.md`
records, in its final accepted form:

* PostgreSQL remains authoritative for documents, canonical chunk text,
  chunk provenance, `EmbeddingProfile` lineage, and both generation
  lifecycles; Qdrant remains a disposable, rebuildable search projection —
  extending ADR 0007, not reopening it.
* Two distinct generation concepts, named explicitly to avoid ambiguity: an
  **embedding-space generation** (platform-scoped, one `EmbeddingProfile`
  fingerprint, one Qdrant collection, created only by a consequential profile
  change) and a **workspace corpus generation** (PostgreSQL-owned, per
  workspace, extended incrementally by ordinary ingestion, replaced wholesale
  only by a coordinated rebuild).
* V1 uses a single platform-selected embedding profile for all workspaces;
  a `workspace override ?? platform default` resolution seam is preserved
  architecturally but unpopulated.
* Per-workspace corpus-generation activation, resolved explicitly in
  PostgreSQL and passed explicitly to the AI service, supports staged
  rollout and cheap pointer-flip rollback of any future rebuild.
* The migration-concurrency invariant: a candidate workspace corpus
  generation is never activated unless it has incorporated every
  authoritative PostgreSQL change up to an explicit cutover boundary.
* Illustrative generation lifecycle states — `BUILDING → VERIFYING →
  AVAILABLE → RETIRING → RETIRED` for an embedding-space generation;
  `BUILDING → VERIFYING → ACTIVE → SUPERSEDED → RETIRED` for a workspace
  corpus generation — with invariants including at most one `ACTIVE`
  workspace corpus generation per workspace, and an embedding-space
  generation never described as globally active.
* Rebuildability is defined precisely: read persisted chunk text and lineage
  from PostgreSQL, re-embed, recreate/ensure the compatible collection,
  upsert deterministically, verify completeness, activate only after
  verification. Canonical accepted chunk text is durably persisted in
  PostgreSQL to make this possible; raw vector arrays are never duplicated
  into PostgreSQL.
* Collection topology is one Qdrant collection per embedding-space
  generation, adopted as a deliberate architectural choice — Qdrant is not
  claimed to be incapable of hosting multiple embedding spaces in one
  collection — for lifecycle-isolation, completeness-verification and
  retirement reasons.
* A minimal Qdrant payload (`workspace_id`, `document_id`, `chunk_id`,
  `workspace_corpus_generation_id`, `embedding_space_generation_id`), with
  payload indexes required on `workspace_id`, `workspace_corpus_generation_id`
  and `document_id` specifically, not on every field by default.
* Deterministic point identity, derived from embedding-space generation,
  workspace, workspace corpus generation and chunk identity.
* Completeness verification defined precisely: expected/actual identity
  comparison, payload-identity checks and vector-schema checks — count
  equality alone is explicitly insufficient.
* A provider-neutral `VectorStore` boundary, isolating the rest of the
  platform from Qdrant-specific types, exceptions, transport and
  request/response models, with activation deliberately excluded from its
  contract as a PostgreSQL/domain concern.
* V1 physical configuration: 1024 float dimensions, cosine distance, one
  named dense vector (`dense`) — justified on its own merits, not as a
  workaround for a migration constraint that does not exist.

Alternatives rejected and recorded in the ADR include collection-per-workspace,
collection-per-workspace-corpus-generation, one universal collection,
treating every document upload as a new workspace corpus generation,
requiring independent per-workspace profile selection in V1, an
undifferentiated single "generation" concept, persisting raw vectors in
PostgreSQL, a fatter Qdrant payload, random point identifiers, folding
activation into `VectorStore`, and deferring the named-vector decision.

## Verification performed

* Read ADR 0006, 0007, 0010, 0011, 0012 and 0013 in full, plus the relevant
  sections of `PROJECT_ROADMAP.md`, `IMPLEMENTATION_GUIDE.md` and
  `tasks.json`, before forming any recommendation.
* Checked current Qdrant documentation (named vectors, sparse vectors,
  adding/removing named vectors on an existing collection) before finalising
  claims about collection and vector-schema capabilities, and corrected an
  earlier draft's overstated framing of physical incapability as a result.
* Checked the accepted ADR against each Stage 14.1 acceptance criterion in
  `IMPLEMENTATION_GUIDE.md`; all are met.
* Confirmed `docs/rag-platform-tasks.json` does not exist in this repository
  state, so no secondary tracker file requires synchronisation.
* Did not run `make lint` / `make test` / etc. — no application code changed
  in this session, so those checks do not apply.

## Problems or corrections

The initial architectural review contained one overreach, corrected during
the second round of discussion: it read ADR 0006 as requiring live,
independent per-workspace embedding-profile selection in V1, when ADR 0006 in
fact only established workspace-scoped effective configuration as a
tenancy-classification category. This was corrected before the ADR was
drafted, not after, so it does not appear in the accepted document. The
first drafted version of the ADR also overstated Qdrant's physical
incapability of hosting multiple embedding spaces in one collection and of
requiring a named vector to avoid a future migration; both were corrected in
the bounded amendment round after verification against current Qdrant
documentation.

## Next steps / important takeaways

* Stage 14.2 (Add Qdrant Development Service) can proceed against a settled
  architecture: one collection per embedding-space generation, a named dense
  vector, and a `VectorStore` abstraction whose contract is now fully
  specified in ADR 0014.
* Stage 14.3 (Persist Chunk Vectors) inherits concrete, decided obligations
  rather than open questions: durable chunk-text persistence in PostgreSQL,
  the exact minimal payload set and its required indexes, deterministic
  point-identity inputs (algorithm itself still open), and the precise
  semantic meaning of completeness verification (efficient implementation
  still open).
* Stage 14.3/14.4 must design the concrete migration-concurrency mechanics
  (dual-write, catch-up passes, scheduling, or event replay) that satisfy the
  invariant this ADR decided but deliberately left unimplemented.
* The generation lifecycle state names recorded in ADR 0014 are illustrative;
  Stage 14.3 owns the actual enum/state-machine implementation.
* `docs/adr/README.md` was updated to index ADR 0014 as part of this
  session's completion evidence, consistent with the precedent set at
  R07-S01.
