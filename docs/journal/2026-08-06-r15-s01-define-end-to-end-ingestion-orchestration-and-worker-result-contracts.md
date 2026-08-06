# Session Journal: R15-S01 — Define End-to-End Ingestion Orchestration and Worker Result Contracts

## Date

2026-08-06

## Session mode

Architecture and documentation only. No application code, migrations,
models, HTTP endpoints, or worker code were introduced.

## What happened

The session began by explicitly setting aside any earlier, informal
ADR-0015 discussion and treating this as a fresh architectural review of the
repository's current accepted state — ADR 0007 through ADR 0014,
`PROJECT_ROADMAP.md`, `IMPLEMENTATION_GUIDE.md` and `tasks.json` — before
forming any recommendation. The review confirmed the seam this phase exists
to close was deliberately left open by two already-accepted ADRs: ADR 0008
explicitly deferred "the exact authentication mechanism" for any lifecycle
transition beyond the initial claim, and ADR 0009 authenticated only that
one claim, closing with its own instruction that a broader scope "should be
replaced or superseded rather than expanded into an improvised general
identity system." The review answered ten questions (service ownership,
lifecycle state transitions, worker-to-Laravel communication, trust
boundaries, completion semantics, failure semantics, queue semantics,
initial provisioning, observability, future-proofing) and recommended: no
change to service ownership or the Document lifecycle; authenticated HTTP
superseding ADR-0009 in part rather than a return queue or event bus; and a
processing-lease concept to handle a worker crashing after claiming, which
ADR-0009 had not addressed.

A first full draft of ADR-0015 followed, incorporating refinements agreed
before drafting: two separate worker-result contracts (bounded canonical
chunk submission, and a small referential completion contract) rather than
one unbounded payload; a purpose-scoped `v2` HMAC signature protocol
extending ADR-0009's primitives, with a computed and independently
cross-checked normative test vector; a five-outcome processing lease
(proceed, owned by another live worker, already completed, permanently
failed, reclaimable); Laravel independently recomputing chunk count and
manifest digest rather than trusting Python's summary; domain-owned failure
classification with only Laravel able to transition `FAILED`; a DLQ
terminal-reconciliation invariant that never lets a message's exhaustion
directly mutate PostgreSQL; and explicit, differentiated initial-generation
provisioning (platform-level explicit and idempotent; workspace-level lazy
and Laravel-authoritative).

Two further rounds of bounded amendment followed review of that draft:

1. **Queue/lease coordination and `v1`/`v2` migration policy.** The first
   draft treated the processing lease's expiry and SQS visibility as loosely
   related, and left `v1` signature acceptance open-ended. This round
   required them to be one coordinated timing and ownership model (not two
   independently-tuned values) and replaced the open-ended `v1` allowance
   with a bounded policy: `v2` required from first implementation for every
   new operation, `v1` retained only for the existing claim during a
   temporary migration window, with its removal tracked and tested as real
   Stage 15.2 work rather than left indefinite.
2. **Provisional-chunk reclaim semantics, non-atomic renewal, and narrowed
   retry-exhaustion semantics.** A closer read exposed three further edge
   cases: the first draft scoped chunk idempotency to "the current lease,"
   which would have orphaned a predecessor's already-persisted chunks on
   reclaim; it implied lease renewal and SQS visibility extension could be
   treated as one atomic operation, which is not achievable across two
   independently-owned systems; and it allowed any retry-exhausted failure,
   including a failed callback or a lost lease, to become a Document
   processing failure. This round separated chunk **ownership** (durably
   `event_id`-scoped) from submission **authority** (the current lease);
   defined both partial-success outcomes when lease renewal and visibility
   extension diverge, without ever claiming atomicity; and constrained
   `ingestion.fail` to failures Python's own processing domain classifies as
   terminal while the worker still holds a valid lease, explicitly excluding
   control-plane and callback exhaustion from ever being reclassified as a
   processing failure.

The ADR was approved after this second amendment round with no further
changes requested, and accepted.

## Decisions recorded

`docs/adr/0015-define-end-to-end-ingestion-orchestration-and-worker-result-contracts.md`
records, in its final accepted form, everything summarised in
`IMPLEMENTATION_GUIDE.md` Stage 15.1's Decision section — service ownership
and the Document lifecycle unchanged; the partial supersession of ADR 0009;
the purpose-scoped `v2` protocol and bounded `v1` migration policy; the
processing lease and its five claim outcomes; the ownership/authority split
for provisional chunks; the two separate worker-result contracts; the trust
boundary between what Laravel independently verifies and what it records as
an authenticated Python assertion; domain-owned, lease-gated failure
classification; non-atomic lease/visibility coordination with both defined
partial-success outcomes; DLQ terminal reconciliation as an
eventually-consistent, idempotent, `event_id`-keyed invariant rather than a
direct state mutation; and differentiated initial-generation provisioning —
not duplicated here.

The roadmap citation clarification `IMPLEMENTATION_GUIDE.md` Stage 15.1
already required is recorded inside ADR-0015 itself: ADR-0013's and
ADR-0014's existing "Phase 15" references now resolve to Phase 16, and their
later-phase references shift by one in the same way, as a citation
correction only. Neither accepted ADR was rewritten.

## Verification performed

* Read ADR 0007, 0008, 0009, 0010, 0011, 0012, 0013 and 0014 in full, plus
  the relevant sections of `PROJECT_ROADMAP.md`, `IMPLEMENTATION_GUIDE.md`
  and `tasks.json`, before forming any recommendation.
* Computed the `v2` signature test vector directly and independently
  reproduced ADR-0009's own published `v1` vector first, using the same
  method, to confirm the canonicalisation approach before extending it to
  the six-field `v2` form — the vector recorded in ADR-0015 is verified, not
  asserted.
* Checked the accepted ADR against each Stage 15.1 acceptance criterion in
  `IMPLEMENTATION_GUIDE.md`; all are met.
* Confirmed, after each amendment round, that only the ADR file itself had
  changed and that no application code or other ADR was modified.
* Did not run `make lint` / `make test` / etc. — no application code changed
  in this session, so those checks do not apply.

## Problems or corrections

Two rounds of requested refinement materially changed the accepted
document from its first full draft, detailed under "What happened" above:
the first closed an under-specified queue/lease timing relationship and an
open-ended authentication migration policy; the second closed three edge
cases the first draft had not fully resolved (chunk ownership surviving a
lease reclaim, the non-atomicity of cross-system renewal, and the scope of
which failures may become an authoritative Document `FAILED` outcome). None
of these represented a disagreement with the underlying architecture, which
was approved in principle from the first review; each was a bounded
tightening exposed by reading the draft closely rather than only the
summary of it.

## Next steps / important takeaways

* Stage 15.2 (Implement End-to-End Ingestion Orchestration) can proceed
  against a fully settled contract: the purpose-scoped `v2` protocol and its
  verified test vector, the five-outcome processing lease, the two
  worker-result contracts, and the coordinated (non-atomic) lease/visibility
  timing model are all decided; only their concrete implementation shape
  remains.
* Stage 15.2 inherits explicit, tracked obligations rather than open
  questions: removing temporary `v1` compatibility must be tested, not
  merely completed; the shared chunk-manifest digest canonicalisation must
  be language-neutral, versioned, and exercised by PHP/Python conformance
  tests; and DLQ terminal reconciliation must be idempotent, `event_id`-
  keyed, workspace/document-validated, and itself observable and alertable.
* The per-failure-category retry/terminal-classification table Stage 15.2
  needs is explicitly left to that stage's implementation of the typed
  failure rules the owning pipeline domains (ADR 0010, ADR 0013, and this
  ADR) already established — it does not require a further ADR.
* Three implementation choices remain deliberately open for Stage 15.2: the
  lease's physical representation (extend ADR-0009's existing durable
  event/claim record, or a dedicated entity); the shared canonicalisation
  specification's exact repository location; and concrete timing values and
  schema names.
