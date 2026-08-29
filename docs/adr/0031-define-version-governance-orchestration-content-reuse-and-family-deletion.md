# ADR 0031: Define Version Governance Orchestration, Content Reuse and Family Deletion

## Status

Accepted

## Date

2026-08-28

## Relationship to prior ADRs

### Consumes ADR-0017's domain model without redesigning its authority calculation

ADR-0017 already defines `DocumentFamily`, linear version lineage, the
`authority_start` derivation, the `DRAFT → APPROVED → WITHDRAWN` governance
model, and per-version immutable applicability snapshots. **This ADR does
not reopen any of that.** The domain logic implementing it already
exists — `ApproveDocumentVersion`, `WithdrawDocumentVersion`,
`RescheduleDocumentVersion`, `CreateDocumentVersion`, and
`CorrectDocumentGovernanceTimestamps` all exist today under
`app/Actions/Documents/`. This ADR's first decision is to expose the
existing domain layer, not to redesign it.

### Consumes ADR-0030's metadata classification and checksum state

Every metadata field this ADR's API surface reads or writes is defined by
ADR-0030. **This ADR requires `checksum_verification_status = 'verified'`
as the first element of its clone compatibility proof** — a `pending` or
`unavailable` predecessor is simply ineligible for cloning, exactly as
ADR-0030 already states.

### Consumes ADR-0032's canonical artefact, digests, and upload/projection
### machinery

This ADR's clone mechanism consumes ADR-0032's `DocumentExtractionArtifact`
schema, its canonical serialisation/digest algorithm, and its lease-bound
upload and atomic-projection-publication machinery. **ADR-0031's clone
orchestration cannot be implemented before ADR-0032's artefact/digest
foundation exists** — see "Implementation order" below.

### Extends ADR-0025's principles; the child deletion implementation is
### genuinely extended, not literally unmodified

ADR-0025 already defines owner/admin-only deletion authorization, the
ingestion-quiescence barrier, vector-scope enumeration, hard chunk
deletion with `EvidenceSnapshot` lineage preserved, and a single-open-
per-document idempotency rule. **This ADR reuses those *principles*
unmodified** — the quiescence barrier, the cleanup ordering, the
citation-survival guarantee, and the retry discipline are not redesigned.
**But the child deletion's actual implementation is genuinely extended**,
because ADR-0025 predates every layer introduced since: it must now also
cover source-checksum state, artefact upload-authorisation records, the
extraction artefact, every projection generation, extraction warnings,
corpus assignments, clone manifests, clone-origin claims and their
materialisation lineage, and the `physical_removal_authorised_at` decision.
**Stating the child deletion as "completely unmodified" — as an earlier
working draft of this decomposition did — is corrected here**: the
principles transfer; the concrete cleanup surface does not stay the same
shape it was under ADR-0025 alone.

### Consumes ADR-0014/0016/0018's vector and eligibility model

Verified: ADR-0014's minimal Qdrant payload is `workspace_id`,
`document_id`, `chunk_id`, `workspace_corpus_generation_id`,
`embedding_space_generation_id`, `event_id`, `publication_status`.
Applicability is resolved entirely in Laravel by ADR-0018's
`EligibilityResolver` and is never encoded in this payload.

## Context

Discovery verified that Dolved's version/family/applicability domain model
is strong but has no orchestration layer above it. Two prior correction
passes fixed the clone's Laravel/Python ownership split, the bounded
manifest protocol's shape, and the family-deletion parent/child model.
**This revision applies Codex's second Tier-1 audit**, which found: cloning
was not clearly separated from ordinary ingestion in operational reporting;
the compatibility proof relied on comparing many individual fields rather
than one durable, persisted identity; the clone-mapping manifest itself had
no defined lifecycle or cleanup; family deletion could mutate state before
any confirmation step; "discard `DRAFT`" risked being read as a new
governance state; the child deletion's extension over ADR-0025 was
described as "unmodified" when it is not; and the final transition to
`INDEXED` was described narratively rather than as one atomic transaction.

### Product decision: clone reporting is a distinct category

**`content_clone` is a distinct operational/materialisation category from
ordinary `ingestion`**, closed as settled product direction, applied
throughout this ADR:

- Clone attempts, outcomes, failures, and latency are reported as
  `content_clone` — never merged into ordinary ingestion's own counters.
- Clone attempts never increment ordinary ingestion failure/attempt
  counters, regardless of outcome.
- A clone attempt records zero provider calls, zero tokens, and zero cost
  when none occur — honestly, using the same `execution: "local"`/
  `cost_basis: "zero_cost_local"` vocabulary the existing worker-usage
  schema already provides.
- A higher-level "document materialisation" roll-up may aggregate
  `ingestion` and `content_clone` for a summary view, but the subcategory
  remains visible underneath it — an aggregate is a presentation choice,
  never a fact-losing merge.
- Deletion enumeration, `EvidenceSnapshot` lineage, and terminal
  publication-completeness checks may remain **origin-agnostic** where both
  origins are structurally valid inputs to the same check.
- Ordinary ingestion callbacks may only mutate `ingestion`-origin claims;
  clone callbacks may only mutate `content_clone`-origin claims — an
  origin mismatch fails closed.

## Decision

### Implementation order — binding

1. ADR-0030's schema, metadata, and checksum-state foundation.
2. ADR-0032's canonical artefact schema, upload-authorisation record, and
   atomic projection-publication machinery.
3. This ADR's governance routes and idempotency model may proceed
   alongside step 2 where genuinely independent.
4. This ADR's clone orchestration begins only after step 2 exists.
5. This ADR's family-deletion extension follows once every layer it must
   clean up has a real persistence path.

### Action and controller ownership

**Controllers own**: request validation; tenant-scoped lookup and
concealment; the initial, coarse policy check; idempotency-key lookup.

**Actions own**: the transaction; the final, authoritative permission
check; every lock; current-state and lineage revalidation; the mutation;
the audit/result record.

**Standardised family-lineage mutation locking**, used by every Action
touching more than one row in a family:

1. Lock the family row.
2. Lock every relevant version row, in deterministic ascending-`id` order.
3. Revalidate current state under the held locks.
4. Mutate.

### `CreateApplicabilityOnlySuccessor`

A named Action: authorises; locks the family and predecessor
deterministically; revalidates applicability/lineage under the held locks;
creates the target version, reusing `CreateDocumentVersion`'s lower-level
row-construction logic only where safe; atomically creates the clone
lineage (below) in the same transaction as the target version row; enters
clone-specific processing rather than ordinary ingestion.
**`CreateDocumentVersion` itself is never exposed directly to the
browser.**

### Governance API surface

| Capability | Backing Action | Notes |
|---|---|---|
| List a family's version history | (new read query) | Tenant-scoped |
| Create an applicability-only successor | `CreateApplicabilityOnlySuccessor` | The only R23 route creating a successor with no fresh upload |
| Approve a version | `ApproveDocumentVersion` | Unchanged eligibility rules |
| Withdraw / cancel a scheduled version | `WithdrawDocumentVersion` | Single action per ADR-0017 |
| Reschedule a not-yet-attained version | `RescheduleDocumentVersion` | Unchanged uniqueness constraint |
| Correct a governance timestamp | `CorrectDocumentGovernanceTimestamps` | Elevated permission |

Tenant-safe concealment and a small, stable, typed public error vocabulary
apply to every route.

### Governance idempotency

```
UNIQUE (workspace_id, purpose, idempotency_key)
```

Stored but excluded from uniqueness: actor identity; target kind/identity;
a canonical request-payload digest; current state; the terminal result
identity (populated only after execution).

- Matching row, matching actor/target/digest → existing result.
- Matching row, differing actor/target/digest → typed
  `idempotency_key_conflict`, fails closed.
- Different `purpose` or different `workspace_id` → entirely independent.
- A completed retry still requires current authentication, tenancy, and
  concealment on every request.
- A genuinely new request always enters the Action's own locking and
  revalidation — idempotency matching never substitutes for it.

### Applicability-only successor and the durable pipeline fingerprint

Because ADR-0017 records applicability as an immutable per-version
snapshot, changing it requires a new governed version. This ADR resolves
whether that version must re-run extraction, chunking, and embedding
against unchanged content.

**It must not, provided compatibility is proved — and the proof is now
expressed as one durable, persisted fingerprint, not a transient header or
an ad hoc list re-compared field by field each time.**

**`materialisation_pipeline_fingerprint`**: a canonical SHA-256 fingerprint,
computed and persisted on every claim (ordinary or clone), over the
versioned identities that determine stored-materialisation compatibility:

- Worker protocol purpose/family and contract version.
- `DocumentExtractionArtifact` schema version (ADR-0032).
- Source extractor identity.
- Normaliser identity (ADR-0032).
- Projection schema and digest-algorithm version (ADR-0032).
- Chunking `strategy_name` + `strategy_version` + `configuration_fingerprint`.
- Dense embedding profile fingerprint.
- Sparse profile fingerprint, where present.
- Qdrant collection identity, dense and sparse named-vector identities,
  vector dimensions, and distance metric.
- The relevant `workspace_corpus_generation_id`,
  `embedding_space_generation_id`, and `sparse_space_generation_id`.

**Persisted alongside the fingerprint, not discarded once computed**: every
component identity/version field listed above, retained for audit and
diagnostics — a mismatch investigation needs to see *which* component
differs, not only that the fingerprints disagree. Also persisted:
`attempt_origin` and the claim/event identity this fingerprint belongs to.

**The compatibility proof, restated as this one durable comparison, plus
the facts a fingerprint alone cannot express:**

- **The predecessor's completed claim and the target clone claim must bind
  to the same `materialisation_pipeline_fingerprint`.** This replaces
  comparing each component individually at proof time with one comparison
  against a value already computed and stored at claim-creation time on
  both sides — **never a transient request header used as the sole
  contract identity**, which was the gap this correction closes.
- `source_checksum_sha256`, in `verified` state (ADR-0030) — a content
  identity, not a pipeline identity, and therefore checked separately from
  the fingerprint.
- The predecessor's claim is fully `Completed` with recorded publication
  evidence (not merely `INDEXED` at the `Document` level — the claim
  itself must show genuine, verified completion).
- The predecessor's `workspace_corpus_generation_id` is currently the
  workspace's **active** corpus-generation assignment — a fingerprint
  match against a superseded generation is not eligible.
- Same workspace, same family.

**Reranker identity is explicitly excluded** — reranking is a query-time
operation with no bearing on stored-content compatibility.

**If the fingerprint does not match, the checksum is not `verified`, the
predecessor's claim is not genuinely completed, or the generation is not
active, ordinary ingestion runs instead** — fail-closed, never a degraded
clone.

**Applicability itself remains exclusively a Laravel-side eligibility
fact**, never encoded into a Qdrant payload.

### R23 successor-creation boundary

Unchanged: `CreateApplicabilityOnlySuccessor` is the only R23 browser-
facing route creating a new version; no existing route accepts a target-
family identity for an upload; ordinary content successors wait for
ADR-0034/R25.

### Independent clone ownership — six layers, none shared

| # | Layer | Who copies it, and how | Integrity proof required before `INDEXED` |
|---|---|---|---|
| 1 | Source object | **Laravel**, via `DocumentObjectStorage`: copy to a new, target-owned key | Checksum matches the predecessor's `source_checksum_sha256` |
| 2 | `DocumentExtractionArtifact` | **Laravel**, via `DocumentObjectStorage`: copy of the already-verified predecessor artefact to a new, target-owned key | Checksum, schema version, and extraction/normaliser lineage match |
| 3 | Relational extraction-element projection | **Laravel**: a fresh projection generation for the target, through ADR-0032's own atomic-publication mechanism | Artefact-checksum binding, row count, and projection-manifest digest, verified by ADR-0032's publish-time check |
| 4 | Extraction warnings | **Laravel**: published alongside the projection | Matching count and warning-manifest digest |
| 5 | Chunk rows, corpus assignment, and materialisation lineage | **Laravel**: new chunk rows with new, deterministic target `public_id`s; one target `workspace_corpus_generation_chunks` assignment per chunk for the (unchanged) active generation; linked to a genuine target-owned `content_clone`-origin claim | Matching row count, a recomputed `chunk_manifest_digest`, matching corpus-assignment count, and verified corpus-publication evidence for the target claim |
| 6 | Qdrant vector points | **Python**, and Python alone, through the bounded clone-manifest protocol below | A typed completeness report from Python's own post-write verification pass, validated by Laravel against its expected manifest |

**Layers 1 through 5 require no Python involvement. Python is required
only for layer 6.**

### `attempt_origin`: bounded vocabulary and complete consumer classification

**Vocabulary: `ingestion`, `content_clone` — exactly two values, no
implicit third.** `IngestionEventClaim` is extended with this
discriminator rather than paired with a second, parallel claim table
(rejected alternative, below), because nearly every existing field on that
model is genuinely meaningful for a clone attempt, and the existing
worker-usage vocabulary already expresses "no provider call was made"
honestly.

**Origin-specific — an event ID with the wrong origin fails closed, and a
callback cannot cross-mutate:**
- Ordinary ingestion's request/lease-renewal/completion/failure/
  cancellation/retry Actions require `attempt_origin = 'ingestion'`.
- The clone request/lease-renewal/completion/failure/cancellation Actions
  (below) require `attempt_origin = 'content_clone'`.

**Separately reported, per the product decision above — never merged:**
workspace attempt/failure counts; operational stuck counts; latency
metrics; the audit/event vocabulary; usage/activity projections; telemetry
operation labels.

**Origin-agnostic, where both origins are structurally valid inputs to the
same check:** deletion quiescence; vector-generation/scope enumeration;
`EvidenceSnapshot` materialisation lineage; terminal corpus-publication
completeness; source/chunk/vector cleanup.

**Required implementation sweep — every named consumer, from Codex's
audit, must be checked and given an explicit origin condition (where
origin-specific) or an intentional roll-up (where aggregating), never left
assuming every claim is ordinary ingestion:**

| Consumer | Required treatment |
|---|---|
| `GetWorkspaceUsage` | Reports `ingestion` and `content_clone` attempt/cost/token counts separately; may additionally expose the aggregated "document materialisation" roll-up |
| `TelemetryServiceProvider` | Operation labels distinguish origin explicitly |
| `RecordOperationalSnapshot` | Stuck-operation and failure counts reported per origin, never combined silently |
| Ordinary ingestion callback/lease/retry Actions | Reject any claim whose `attempt_origin` is not `ingestion` |
| Clone request/lease/completion/failure/cancellation Actions | Reject any claim whose `attempt_origin` is not `content_clone` |
| Deletion enumeration/quiescence | Origin-agnostic — enumerates by `document_id` regardless of origin |
| Evidence/publication consumers (`EvidenceSnapshot` lineage, corpus-publication completeness) | Origin-agnostic — a genuinely completed claim of either origin satisfies them |

**No query may retain an unexamined assumption that every claim is
ordinary ingestion** — every one of the above is a required implementation
checkpoint, not an incidental note.

### Clone operation, claim linkage, and pipeline fingerprint binding

A new, durable, Laravel-owned record — **`DocumentContentCloneOperation`**
— is created in the same transaction as the target `Document` row,
alongside a genuinely target-owned `IngestionEventClaim`
(`attempt_origin = 'content_clone'`, `document_id` = the target's own id).

**`DocumentContentCloneOperation` is linked one-to-one to this target
claim.** The operation record carries clone-specific detail (the captured
compatibility proof, including the bound `materialisation_pipeline_
fingerprint` and the checksum, source/target version identities); the
target claim is the one true "which attempt produced this content"
identity every existing consumer already knows how to resolve, since it is
found by the same `document_id`-based lookup an ordinarily-ingested
document's claim already is.

**The target claim's identity — never the predecessor's — appears
everywhere lineage is recorded**: chunk lineage (resolved by `document_id`,
never a copied reference to the predecessor's claim); Qdrant payload
(cloned points carry the **target claim's own `event_id`**, never the
predecessor's — `event_id` is one of ADR-0014's five original payload
fields); deletion scope enumeration (finds the target's own claim with no
special case, since enumeration is already `document_id`-scoped); future
`EvidenceSnapshot` lineage (captures the target claim's `event_id` as
`source_ingestion_event_id`); and audit (names both the source and target
claim identities, so "was this content re-processed or cloned" remains
directly answerable).

**Corpus publication for cloned points is genuine**: the target claim
proceeds through the same `PublicationAuthorised → Completed` states
ordinary ingestion uses, and cloned Qdrant points are explicitly
transitioned `PROVISIONAL → PUBLISHED` via the existing `publish(scope)`
mechanism, scoped to the target claim's own `event_id` — satisfying
ADR-0016's dual gate for cloned content exactly as it already applies to
ordinarily-ingested content.

### Clone-manifest lifecycle

**A durable, Laravel-owned `DocumentContentCloneManifest` record**,
distinct from the clone operation itself, tracks the immutable point-
mapping object's own lifecycle:

- Exact object key; schema version; entry count; SHA-256 checksum.
- The clone operation/claim identity and lease generation it belongs to.
- Created / verified / consumed timestamps (or an equivalent state field).
- A hard expiry.
- Cleanup status and retry metadata — the same shape ADR-0032's artefact
  upload-authorisation record already establishes, reused here rather than
  invented a second time.

**Lifecycle:**

1. Laravel creates and verifies the immutable manifest (checksum/count/
   schema, as already specified below).
2. Python consumes only the manifest belonging to the **current** claim and
   lease generation — never a stale or superseded one.
3. On a successful clone, count/digest evidence is persisted durably on the
   target claim; **the manifest object itself becomes cleanup-eligible
   once terminal verification succeeds** — it is scaffolding used to
   produce the result, not part of the result.
4. A failed, cancelled, expired, or stale attempt's manifest becomes
   cleanup-eligible immediately.
5. The **same bounded, database-led sweep pattern** ADR-0032 defines for
   artefact uploads applies here — selecting eligible rows in bounded
   batches, claiming idempotently, deleting only the exact recorded key,
   never a prefix or bucket scan.
6. **A retried clone operation either reuses the same, still-valid
   verified manifest** (if the mapping is unchanged) **or creates a new,
   attempt-bound manifest under a new exact key** — it never overwrites an
   existing manifest object.
7. Cleanup failure is visible and retryable, exactly as ADR-0032's own
   sweep already specifies.
8. **Family or single-version deletion can identify any remaining manifest
   object tied to a version or claim** — the child deletion's cleanup
   proof (below) explicitly includes checking for, and removing, an
   orphaned clone manifest, not only the Qdrant points it once described.
9. **Export does not need the transient clone-mapping manifest** once
   durable clone lineage, count, and digest evidence exist on the target
   claim — the manifest describes *how* cloning happened, not the *result*
   ADR-0037 needs to export.

### Bounded clone contract with Python

1. Laravel constructs the complete canonical source-to-target point
   mapping — source point identity, target point identity, and target
   payload bindings (`document_id`, `chunk_id`, and the target claim's
   `event_id`).
2. Laravel writes this mapping to one exact, authorised, immutable object
   key, tracked by the `DocumentContentCloneManifest` above.
3. The manifest carries a versioned schema, an entry count, and a SHA-256
   checksum.
4. The small, HMAC-signed clone request to Python contains only: operation/
   event/lease identity; the exact source and target Qdrant scopes; the
   manifest's key/reference, checksum, count, and schema version; the
   vector-profile/generation identity both scopes share; a correlation
   identifier. **Never the mapping itself.**
5. Python fetches only the exact authorised manifest key belonging to its
   current claim and lease generation.
6. Python validates the fetched manifest's checksum, count, and schema
   version, then performs a **bounded-page** Qdrant `scroll`/`upsert`.
7. Python runs a **separate, independent post-write completeness pass** —
   its own fresh `scroll`/count against the target scope.
8. Python reports a typed count, a produced-point manifest digest, and the
   completeness-pass result back to Laravel.
9. **Laravel validates this report against its own expected manifest.
   Laravel never independently reruns `verify_completeness` itself and
   never constructs or owns Qdrant query syntax** — Python alone owns
   Qdrant syntax and the actual post-write completeness pass; Laravel
   validates Python's typed report against a manifest it already knows to
   be correct.
10. Runtime limits (R23 measurement, not a product decision): a maximum
    manifest byte size; a maximum mapping-entry count per manifest (above
    which the clone is not attempted, and ordinary ingestion runs instead
    — never split across multiple manifests in V1); a maximum Qdrant batch
    size per page.
11. Rejected outright: a stale lease generation acknowledging; a
    conflicting acknowledgement; any mismatch between the reported count/
    digest and Laravel's own expected manifest.

### Clone state machine and fallback

```
AUTHORISED → COPYING → VERIFYING → INDEXED
```

Failure path:

```
COPYING / VERIFYING → CLEANUP_REQUIRED → FALLBACK_READY → ordinary ingestion
```

- **`AUTHORISED`**: compatibility proof passed; target version row exists;
  no layer copied.
- **`COPYING`**: Laravel-only layers 1–5 established first; layer 6 last,
  through the bounded manifest protocol.
- **`VERIFYING`**: every layer's integrity proof is checked; any single
  failure returns to `CLEANUP_REQUIRED`.
- **`INDEXED`**: reached only through the atomic final transaction below.
- **`CLEANUP_REQUIRED`**: only the verified source-object copy (layer 1)
  may survive into fallback. **Every other layer — artefact, projection
  generations, warnings, chunks, corpus assignments, Qdrant points, and any
  clone-manifest object — must be removed and independently verified
  absent**, not merely "a delete was issued."
- **`FALLBACK_READY`**: reached only once every non-source layer's absence
  is verified. A cleanup failure that cannot achieve this blocks the
  transition outright and surfaces a typed, visibly stuck condition.
- Ordinary ingestion begins only from `FALLBACK_READY`, reusing layer 1's
  surviving source object, and produces a wholly fresh artefact/
  projection/chunks/vectors set — cloned and freshly generated derived
  content never mix.

**Locking and contention**: clone, ingestion, deletion, and governance
operations against the same family all contend using the same family-first
deterministic locking order. **Family deletion cannot begin while any
version in the family has an open `DocumentContentCloneOperation`** — the
family-deletion preview step (below) checks this explicitly.

### Atomic `INDEXED` publication

**One final Laravel transaction — never a sequence of separately-
committing steps a reader could observe mid-way through:**

1. Locks the target `Document` and its claim/`DocumentContentCloneOperation`
   rows.
2. Revalidates current `attempt_origin`, lease, and operation state.
3. Confirms all six layers' completeness.
4. Confirms corpus assignments and publication evidence.
5. Confirms expected manifests and digests match.
6. Persists terminal clone/materialisation evidence on the claim.
7. Transitions the claim to `Completed`.
8. Transitions the `Document` to `INDEXED`.
9. Emits the audit record and any activity/telemetry outbox entries this
   transition requires.

**No reader may observe `INDEXED` before every one of these commits
together** — this applies identically to ordinary ingestion's own final
transition, restated here explicitly for clone completion because the
prior draft described this narratively rather than as one atomic unit.

### Family deletion: preview, then confirm — no mutation before confirmation

**A destructive, family-wide operation requires an explicit two-step flow**
— a materially higher blast radius than ADR-0025's own single-version
deletion, which this ADR does not retroactively change.

**Preview (read-only, no mutation of any kind):**

1. Authorise.
2. Lock and read the family and every version, in deterministic order.
3. Classify every version: current, scheduled, draft, or already-
   superseded/withdrawn.
4. Report exact counts of source/content this operation would remove, and
   any active-operation blocker — **explicitly including an open
   `DocumentContentCloneOperation` on any version**, which blocks the
   operation from proceeding at all until it resolves.
5. Explain plainly: restoration is unavailable after completion; existing
   citation snapshots survive but source viewing disappears; any
   immediate knowledge gap this deletion would create.
6. Produce a **short-lived confirmation digest**, bound to the family,
   the acting user, the classified version set, and the relevant current
   state (governance states, active-operation status) observed during the
   preview.
7. **Makes no mutation of any kind.**

**Confirm (the only step that mutates):**

1. Requires the confirmation digest and an idempotency key.
2. Locks the family, then every version, in the same deterministic order.
3. **Recomputes the preview's state and digest under the fresh locks; if
   it has changed since the preview was issued, the confirmation is
   rejected outright and a fresh preview is required** — never proceeding
   against a state the user did not actually see confirmed.
4. Only once the digest matches does the operation proceed: cancel
   scheduled versions, discard drafts, withdraw current authority, and
   create the child deletion operations.

**No state transition or deletion operation occurs before confirmation
succeeds.**

### `DRAFT` disposition, stated exactly

**"Discard `DRAFT`" introduces no new governance state.**

- The version's governance status remains **truthfully `DRAFT`** — it was
  never approved, so there is nothing for a withdrawal to close, and it
  never transitions to `WITHDRAWN`.
- The `Document` enters the **existing, unmodified technical**
  `DELETING → DELETED` lifecycle (ADR-0007) — this is a technical, not a
  governance, transition.
- Source, artefact, projection generations, warnings, corpus assignments,
  chunks, and vectors are removed exactly as for any other version's
  content deletion.
- **The `DRAFT` version remains as a minimal technical tombstone**, its
  governance status still honestly `DRAFT`, under the family-deletion
  audit record.
- Existing audit history for the version is retained, not rewritten.

### Family deletion: sequencing and status

**A dedicated parent status enum, never reusing a child deletion
operation's own status vocabulary:**

```
pending → processing → completed
                     ↘ partially_failed
```

**Sequencing, performed within the confirm step's locks, snapshotting
child targets in the same transaction:**

1. Refuse with a typed conflict if any version has an open clone
   operation (checked again here, not only at preview time, since state
   may have changed).
2. Cancel every scheduled/not-yet-attained `APPROVED` version.
3. Discard every never-approved `DRAFT` version, per "`DRAFT` disposition"
   above.
4. **Withdraw only the current authoritative version, if one exists** —
   every already-superseded, already-`WITHDRAWN` historical version's
   governance facts are left entirely untouched.
5. Snapshot the resolved version list as the frozen set of children.

**One existing-shape `DocumentDeletionOperation` child per version.**

**Each child's cleanup proof covers all seven persisted shapes a version
can own**: extraction artefact; every projection generation; extraction
warnings; corpus assignments; chunk rows; dense and sparse Qdrant points;
the source object — **plus any orphaned clone-manifest object still tied
to that version's claim**, per "Clone-manifest lifecycle" above.

**Parent status is derived from children**, per the enum above. **Idempotent
retry**: one open `DocumentFamilyDeletionOperation` per family. **No silent
promotion, rescheduling, or predecessor resurrection.** **The family row
survives as a tombstone. No restoration in V1.**

### Export-hold interaction — `documents.id` as the definite coordination mutex

**`documents.id` is the exact per-source coordination mutex.**

- Export-hold creation locks the `Document` row.
- Physical-removal authorisation locks the same `Document` row.
- While holding that lock, the deletion path writes a durable, immutable
  decision (`physical_removal_authorised_at` or equivalent).
- Hold creation refuses outright if that decision already exists.
- Deletion cannot write that decision while a live, unexpired export hold
  exists — it defers, using the same bounded stuck-operation reclaim
  mechanism ADR-0025 already provides.
- Once the decision is committed, physical object/vector/chunk deletion
  may proceed outside the transaction.
- Family deletion applies this protocol independently to every child
  `Document` — no family-level shortcut.
- A hold can never block deletion indefinitely, per ADR-0037's fixed
  expiry.

ADR-0037 defines the hold's own schema and expiry value; this ADR fixes the
mutex identity and the lock-then-decide protocol.

### EvidenceSnapshots

Unchanged: ADR-0025's citation-survival design is unconditionally preserved
by every deletion path in this ADR, extended by this ADR's claim-linkage
design so a citation against cloned content captures the target claim's
own `event_id`, never the predecessor's.

## Alternatives considered

### Sharing chunk/vector rows across versions via reference counting

Rejected — `document_chunks.document_id` is a hard, single-owner foreign
key, and ADR-0025's chunk-immutability guard is unconditional.

### Full re-ingestion for every applicability-only change

Retained as the mandatory fallback whenever compatibility cannot be
proved.

### A separate, parallel clone-specific claim table

Rejected — nearly every field `IngestionEventClaim` already carries is
genuinely meaningful for a clone attempt, and the existing worker-usage
vocabulary already expresses "no provider call was made" honestly. A
parallel table would duplicate all of this and force every existing
consumer to learn a second claim type for no benefit.

### Comparing individual compatibility-proof fields at proof time, with no
### persisted pipeline identity

This was the prior draft's shape. Rejected on the second audit: a list of
individually-compared fields, re-compared fresh on every attempt, is
exactly the "transient identity" this correction closes — a persisted,
computed-once-per-claim fingerprint is what actually lets two claims be
compared as a single, durable fact, with the individual fields retained
only for diagnostics when a mismatch needs explaining.

### Sending the complete point mapping inline in the HMAC-signed clone
### request

Withdrawn in the prior correction pass — an unbounded payload risk.

### Treating the clone-mapping manifest as needing no lifecycle beyond
### "Python reads it once"

Considered, and rejected: without an explicit lifecycle (verified/
consumed states, expiry, cleanup eligibility, retry-reuse-versus-new-key
rules), the manifest object would be exactly the kind of untracked,
unbounded-lifetime artefact this decomposition has otherwise been careful
to avoid everywhere else.

### Allowing family deletion to mutate state incrementally as the request
### is processed, with no separate confirmation step

This was the prior draft's shape, and is corrected: a family-wide
destructive operation's blast radius is large enough, and the gap between
"what the user saw" and "what is actually true at execution time" long
enough, to justify a genuine preview-then-confirm flow with a state-bound
digest — a stronger guarantee than ADR-0025's own single-version deletion
needed, and not retroactively applied to it.

### Introducing a `discarded` governance status for family-deleted drafts

Considered, and rejected: `DRAFT` already truthfully describes "never
approved," and the version's actual removal is a technical, not a
governance, fact. A new governance status would duplicate what
`DRAFT` plus the technical `DELETED` state already say together, honestly.

### Describing ADR-0025's reuse as "completely unmodified"

This was the prior draft's wording and is withdrawn: the underlying
*principles* are reused, but the actual cleanup surface a child deletion
must now cover is genuinely larger than ADR-0025 alone ever specified.

### Laravel independently reconstructing and running `verify_completeness`
### against Qdrant itself

Rejected — this would put Qdrant query construction inside Laravel,
contradicting the accepted ownership boundary.

### One `DocumentDeletionOperation` handling an entire family directly

Rejected — the parent/child model reuses ADR-0025's already-correct
single-document scoping unmodified instead of re-deriving it.

## Consequences

### Positive

- Version governance finally has a real, authorised, tenant-safe API.
- Applicability changes to unchanged content cost no provider calls, and
  are now honestly, separately reported as `content_clone` rather than
  distorting ordinary ingestion metrics.
- A single, persisted, durable pipeline fingerprint replaces a long list
  of individually-compared fields, closing the "transient identity" gap
  the second audit found.
- Extending `IngestionEventClaim` rather than inventing a parallel table
  means every existing lineage/deletion/audit consumer keeps working, with
  genuinely truthful attempt identity for cloned content.
- The clone-manifest lifecycle and the artefact orphan sweep it shares a
  pattern with close a real, previously-undefined cleanup gap.
- Family deletion's preview/confirm flow closes the "state changed between
  what the user saw and what actually happened" risk a purely single-shot
  destructive operation would have carried.
- The atomic `INDEXED` transition and the explicit clone state machine
  each give Codex an unambiguous implementation target.

### Negative

- The six-layer clone model duplicates storage bytes, chunk rows, and
  vector points.
- Extending `IngestionEventClaim` with `attempt_origin` requires auditing
  every existing consumer of that model — real, necessary work, not free.
- The clone-manifest lifecycle and its sweep are new, stateful mechanisms
  distinct from, though patterned on, ADR-0032's artefact sweep.
- Family deletion's preview/confirm flow is a genuinely new two-step
  destructive-operation pattern in this codebase, with its own digest-
  binding and staleness-rejection logic to implement and test.
- The pipeline fingerprint must be recomputed and kept in sync whenever any
  of its component identities changes — an ongoing discipline, not a
  one-time cost.

## Scope boundaries

This ADR does not define:

- Any change to ADR-0017's temporal-authority derivation.
- Restoration of a deleted family or version.
- Restricted, permission-scoped, or confidential document-access groups.
- Import staging, matching, or promotion — ADR-0034.
- The generic bulk-operation domain — ADR-0035.
- The concrete `ExportSourceHold` mechanism — ADR-0037.
- Any UI implementation.
- The exact numeric values of clone-manifest runtime limits, or the exact
  bounded window before a stuck cleanup is surfaced — R23 implementation
  measurement.

## Implementation and session allocation (R23)

Binding in sequence, per "Implementation order" above.

- **R23-S02a — Governance routes, resources, policies.** May proceed
  alongside ADR-0032. Version list/history,
  `CreateApplicabilityOnlySuccessor`'s authorisation/validation surface,
  approve, withdraw/cancel, reschedule, timestamp-correction endpoints;
  the corrected governance idempotency model; the controller/Action
  ownership split.
- **R23-S02b — Clone contract and orchestration.** Begins only after
  ADR-0032's foundation exists. `attempt_origin` on `IngestionEventClaim`
  and the full consumer sweep; `materialisation_pipeline_fingerprint`;
  `DocumentContentCloneOperation` and `DocumentContentCloneManifest`; the
  six-layer copy/verify sequence; the bounded clone-manifest protocol; the
  explicit clone state machine; the atomic `INDEXED` transition.
- **R23-S02c — Family deletion and tombstones.** The preview/confirm flow
  and its state-bound digest; the parent status enum; `DRAFT` disposition;
  the seven-shape-plus-manifest child cleanup proof; the reserved
  export-hold check point on `documents.id`.
- **R23-S02d — Tests.** Cross-workspace concealment; idempotency conflict/
  independence cases; concurrent-mutation/lineage-revalidation regression;
  clone-proof fail-closed cases including fingerprint mismatch and
  non-active-generation rejection; the `attempt_origin` consumer sweep,
  with an explicit test per named consumer in the classification table;
  the full clone state machine including cleanup-verified-absent-before-
  fallback and clone-manifest cleanup; the atomic `INDEXED` transition
  under simulated mid-sequence failure; family-deletion preview/confirm
  staleness rejection; `DRAFT`-remains-`DRAFT` regression; `EvidenceSnapshot`
  survival and correct target-claim lineage through both single-version
  and family deletion of cloned content.
