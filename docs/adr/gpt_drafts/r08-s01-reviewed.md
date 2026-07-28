Revise ADR 0007 rather than replacing its overall structure.

Keep the core decisions:

- Document is a durable workspace-owned domain record, distinct from source bytes.
- PostgreSQL is authoritative for identity, ownership and lifecycle.
- Object storage holds source content.
- Search/vector representations are derived and rebuildable.
- Use an explicit state machine.
- Every upload is currently an independent Document.
- True versioning is deferred.
- Deletion is asynchronous.

Make these corrections:

1. Do not assume extracted or normalised text is durably stored. Replace claims
   that vectors can be rebuilt “from extracted text” with source-neutral
   wording: derived representations are rebuildable from authoritative source
   content through the processing pipeline.

2. Do not claim retry preserves processing history. It preserves Document
   identity. Whether ingestion attempts require their own durable history is
   deferred to the ingestion architecture.

3. Resolve the retry contradiction. Domain-level FAILED → QUEUED retry is an
   explicit authorised action. Automatic retries belong only to transport and
   queue delivery behaviour.

4. Remove the requirement that explicit domain retries have an unspecified
   lifetime ceiling. Instead prohibit uncontrolled automatic retry loops and
   state that queue delivery/redrive retries must be bounded by Phase 9 policy.

5. Clarify UPLOADING as an incomplete and unconfirmed upload. It must not be
   usable. Abandoned uploads may be expired or cleaned up by a later
   operational policy; do not describe indefinite ambiguity as desirable.

6. Add a deletion concurrency invariant: after deletion is requested, workers
   must not return the Document to an active state, publish new derived
   artefacts, or make it retrievable. DELETING and DELETED are cancellation
   barriers and cannot transition back to active lifecycle states.

7. Soften permanent tombstone retention. DELETED may be retained to support
   reconciliation and auditability, but retention duration and eventual hard
   purge are deferred to a later data-retention decision.

8. Qualify the statement that a Document can exist after source bytes are
   absent. This is valid during deletion/recovery; unexpected absence for an
   active Document is inconsistent state requiring reconciliation.

9. Define INDEXED as successful completion of the selected indexing pipeline,
   with the approved searchable representation fully available for
   workspace-filtered retrieval. Partial vector writes must not qualify.

10. State that UPLOADED → QUEUED occurs only after ingestion-event publication
    succeeds. A failed publication leaves the Document UPLOADED.

11. Remove forced comparisons claiming ADR 0006 already applies the same
    state-machine reasoning unless that claim is explicitly supported by ADR
    0006.

Keep the ADR architecture-only. Do not add migrations, columns, endpoints,
classes, queue settings, retry counts, timeout values, or implementation code.

Return the complete revised ADR ready for review.