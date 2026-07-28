# Session Journal: R08-S01 — Define Document Lifecycle

## Date

2026-07-28

## Session mode

Architecture and documentation only. No application code, migrations, models,
middleware, policies, routes or frontend changes were made.

## What happened

The document lifecycle and storage-separation model was drafted as
`docs/adr/0007-define-the-document-lifecycle-and-storage-model.md` from a
brief (`docs/adr/gpt_drafts/r08-s01.md`) that named the required topics —
what a Document is, why it is distinct from the uploaded file, ownership,
the relational/storage/vector separation, the lifecycle and state machine,
failure and retry behaviour, deletion, versioning, and future source
extensibility — plus a preferred state-machine shape and a stated preference
for deferring true versioning.

The draft then went through architecture review with three rounds of
amendment before acceptance:

* An initial corrections pass (`docs/adr/gpt_drafts/r08-s01-reviewed.md`)
  fixed several claims that overstated what the ADR was entitled to assume:
  it removed the assumption that extracted/normalised text is durably stored
  (derived representations are now described as rebuildable from
  authoritative source content through the processing pipeline, not "from
  extracted text"); corrected retry to preserve Document identity only, not
  processing history; resolved a contradiction so domain-level `FAILED →
  QUEUED` retry is unambiguously explicit and authorised, never automatic,
  with unbounded-loop prevention stated without inventing retry-count or
  backoff numbers; clarified `UPLOADING` as unusable rather than an
  indefinite ambiguity; added an explicit deletion concurrency invariant
  (`DELETING`/`DELETED` as cancellation barriers with no path back to an
  active state); softened permanent tombstone language to "may be retained";
  qualified that a Document existing without source bytes is only valid
  during deletion/recovery, not as a steady state; tightened `INDEXED` to
  require full pipeline completion, explicitly excluding partial vector
  writes; required `UPLOADED → QUEUED` to depend on successful ingestion-event
  publication; and removed an ADR 0006 cross-reference (state-machine vs.
  boolean-flag reasoning) that ADR 0006 does not actually make, while
  verifying — rather than assuming — that the other ADR 0006 references
  (workspace-owned entity classification, deletion-as-lifecycle, and the
  audit section naming "document administration") were genuinely supported
  by ADR 0006's text before keeping them.
* A final, single-line terminology amendment reworded the `UPLOADED` state
  description so it referred to "the authoritative source content" rather
  than "the file," keeping it consistent with the ADR's own
  Document-is-distinct-from-the-file principle and its source-agnostic
  extensibility section (a future Drive/SharePoint/URL connector should not
  require a new state).

The ADR was approved after these rounds with no further changes requested,
and its status was updated from `Proposed` to `Accepted`.

## Decisions recorded

`docs/adr/0007-define-the-document-lifecycle-and-storage-model.md` records,
in its final accepted form:

* A Document is a durable, workspace-owned domain record, distinct from the
  uploaded file/source bytes it describes; it belongs to exactly one
  workspace, consistent with ADR 0006's workspace-owned entity
  classification, never to an individual user.
* Three non-interchangeable layers: PostgreSQL is authoritative for identity,
  ownership and lifecycle state; S3-compatible object storage holds
  authoritative source content and is never trusted as a lifecycle-state
  source of truth; chunks/embeddings are a derived, disposable, rebuildable
  projection, not authoritative.
* An explicit state machine — `UPLOADING → UPLOADED → QUEUED → PROCESSING →
  INDEXED`, with `PROCESSING → FAILED` and `<any non-DELETED state> →
  DELETING → DELETED` — in preference to boolean flags.
* `UPLOADING` is unusable and non-listable; `UPLOADED` means authoritative
  source content is confirmed present regardless of origin (source-agnostic
  wording, for future connectors); `UPLOADED → QUEUED` requires successful
  event publication; `INDEXED` requires full pipeline completion with no
  partial writes qualifying.
* Domain-level retry (`FAILED → QUEUED`) is always an explicit, authorised
  action that preserves Document identity, not a history of attempts;
  automatic unbounded retry loops are rejected at every layer, with
  transport/queue redrive bounding left to Phase 9.
* Deletion is asynchronous and reachable from any non-deleted state;
  `DELETING`/`DELETED` are cancellation barriers with no valid path back to
  an active state; `DELETED` may retain the row for reconciliation and
  auditability, with retention/purge deferred.
* Every upload is currently an independent Document; true versioning is
  intentionally deferred pending a real product requirement.
* The lifecycle and storage separation are source-agnostic, so a future
  non-upload source can reuse the same pipeline without new states.

## Verification performed

* Read every existing ADR (0001–0006) and `docs/adr/README.md` before
  drafting, to preserve numbering, structure and terminology conventions.
* Checked each ADR 0006 cross-reference in the draft against ADR 0006's
  actual text rather than assuming the comparison held; removed one
  unsupported claim (state-machine-vs-boolean-flags reasoning attributed to
  ADR 0006) and kept three that were genuinely supported.
* Checked the ADR's final form against each Stage 8.1 acceptance criterion in
  `IMPLEMENTATION_GUIDE.md`; all are met at the architectural level
  appropriate to this stage.
* Re-synced `guide_start_line`/`guide_end_line` references in `tasks.json`
  for Phase 8 and Phase 9 after `IMPLEMENTATION_GUIDE.md` grew from this
  session's edit, verifying the new values against the actual file. This
  also corrected pre-existing stale references for `R08-S02`, `R08-S03`,
  `R09` and its sessions that had drifted out of sync with reality during
  the Phase 7.2/7.3 implementation work done earlier (those stages were not
  touched by this session, but their line references were already wrong
  before this session started; they are now accurate).
* Did not run `make lint` / `make test` / etc. — no application code changed
  in this session, so those checks do not apply.

## Problems or corrections

The `docs/rag-platform-tasks.json` duplicate tracker referenced in the
R07-S01 journal no longer exists — it was retired in a prior session
("Retire duplicate task tracker"). `tasks.json` is now the sole tracker; this
session updated only that file.

Beyond the ADR's own three amendment rounds (documented above as refinements,
not errors), the only other issue found was the pre-existing stale
line-number drift for not-yet-reached Phase 8/9 stages, noted above.

## Next steps / important takeaways

* Stage 8.2 (Implement Document Persistence) can now proceed against a
  settled model: a `documents` table (naming to be finalised in that
  session) with a mandatory workspace foreign key, the accepted state
  machine, and negative cross-tenant tests, following
  `CONTRIBUTING.md`'s Actions/Queries convention.
* Stage 8.2/8.3 and Phase 9 will need to decide the concrete retry ceiling,
  backoff and redrive policy that ADR 0007 deliberately left unspecified at
  the domain and transport levels respectively.
* Deletion orchestration across PostgreSQL, object storage and (later)
  Qdrant, and the data-retention/purge policy for `DELETED` rows, remain
  open implementation questions for a later stage, per ADR 0007.
* The `docs/adr/gpt_drafts/` directory now also has `r08-s01.md` and
  `r08-s01-reviewed.md`, consumed by this session; left in place, matching
  the R07-S01 precedent of not deleting consumed drafts.
