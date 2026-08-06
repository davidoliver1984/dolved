# Session Journal: R15-S02 — Define Ingestion Publication and Recovery Semantics

## Date

2026-08-06

## Session mode

Architecture and documentation only. No application code, migrations,
models, HTTP endpoints, or worker code were introduced.

## What happened

Before Stage 15.3 implementation began, a post-acceptance review of ADR-0015
— not a re-litigation of its already-approved architecture — found three
genuine correctness gaps. The review confirmed each was real rather than
theoretical:

- **Provisional-vector visibility.** Ordinary incremental ingestion writes
  Qdrant points directly into a workspace's already-`ACTIVE` corpus
  generation, tagged identically to already-published points. Nothing in
  ADR-0015 distinguished a point written moments ago from one confirmed
  complete, leaving a real exposure window between a successful Qdrant write
  and a confirmed `INDEXED` transition.
- **Cross-worker chunk recovery.** ADR-0015 promised a successor worker
  could resume a predecessor's submitted chunks. Tracing the identity chain
  through ADR-0010 (fresh `ExtractedElement` identity on every independent
  extraction run) and ADR-0011 (chunk identity derived in part from
  `NormalisedElement` identity, itself derived from the source
  `ExtractedElement`) confirmed this was not achievable by recomputation —
  two independent extraction runs over byte-identical content produce
  different chunk identities regardless of chunking's own determinism,
  which is scoped to an already-held `NormalisedDocument` value, not to
  "the same document, re-extracted." ADR-0011 had already flagged persisting
  a chunk set for later resumption as a legitimate concern for "later
  orchestration... phases" — this session was that phase.
- **The `v2` purpose list.** ADR-0015's own "Lease, visibility and
  acknowledgement coordination" section discussed lease renewal as an
  authenticated operation throughout, but the purpose list it actually
  defined never included one — an internal gap in the accepted text, not a
  new requirement.

A first full draft of ADR-0016 followed, superseding ADR-0015 in part (for
all three gaps) and ADR-0014 in part (narrowly, for two payload-field
additions this required — `event_id` and a publication-status marker,
justified against ADR-0014's own index-only-when-operations-require-it
discipline, not by habit). It introduced: an explicit provisional-to-
published vector lifecycle; a dual retrieval-visibility gate (published
Qdrant point *and* PostgreSQL `INDEXED`, both required); an open-versus-
sealed chunk-attempt model, with sealing required before embedding begins
and a new, narrowly-scoped `ingestion.attempt.resume` contract for
recovering a sealed attempt; and four new `v2` purposes
(`ingestion.lease.renew`, `ingestion.chunks.seal`, `ingestion.attempt.resume`,
`ingestion.publication.authorise`), bringing the total to eight, each
independently purpose-signed. A new normative test vector for
`ingestion.lease.renew` was computed and independently verified using the
same method as ADR-0015's own vectors before being trusted.

One round of bounded amendment followed review of that draft: the original
saga verified the provisional point set before publication, then went
directly from the publish mutation to reporting completion, with nothing
checking that the publish mutation itself — a distributed Qdrant operation
that can partially succeed or time out — had actually reached every point.
This was closed by adding explicit post-publication completeness
verification (count equality alone insufficient, matching every other
completeness check this pipeline already requires) and binding publication
authorisation to the exact immutable evidence Laravel approved, so neither a
partial publish nor a differing point set could ever be mistaken for, or
substituted into, a completed attempt.

The ADR was approved after this amendment with one further, narrow wording
correction — a "Consequences → Negative" bullet had described post-
publication verification as a separate authenticated round trip to Laravel,
when it is in fact a Qdrant-side verification operation that strengthens
the evidence presented to the existing `ingestion.complete` call, not a new
Laravel call of its own — and was accepted.

## Decisions recorded

`docs/adr/0016-define-ingestion-publication-and-recovery-semantics.md`
records, in its final accepted form, everything summarised in
`IMPLEMENTATION_GUIDE.md` Stage 15.2's Decision section — the twelve-step
publication saga; the dual retrieval-visibility gate; post-publication
verification and evidence-bound authorisation; the open/sealed chunk-attempt
model and the `ingestion.attempt.resume` contract; the complete eight-purpose
`v2` protocol; the unified reclaim/DLQ cleanup policy; the ADR-0014 payload
amendment and its index justification; publication as a business-audited
event; and the cross-reference to ADR-0006/ADR-0007's existing deletion
deferral — not duplicated here.

The Phase 15 stage structure is corrected accordingly: R15-S01 (completed,
ADR-0015) is unchanged; R15-S02 (this session, ADR-0016) is now the
completed architecture record it always should have been before
implementation began; R15-S03 (new) is "Implement End-to-End Ingestion
Orchestration," implementing ADR-0015 and ADR-0016 together.

## Verification performed

* Read ADR 0010, 0011, 0014 and 0015 in full, the completed Phase 14
  implementation record, the prior R15-S02 planning stub, and `tasks.json`,
  before forming any recommendation.
* Traced the extraction/chunking identity chain explicitly (ADR-0010's
  fresh per-run element identity through ADR-0011's derived chunk identity)
  to confirm the cross-worker resumption gap was a genuine architectural
  impossibility, not an under-specification.
* Computed the `ingestion.lease.renew` `v2` test vector directly and
  confirmed it against the same canonicalisation method already verified
  for ADR-0015's vectors.
* Checked the accepted ADR against each Stage 15.2 acceptance criterion in
  `IMPLEMENTATION_GUIDE.md`; all are met.
* Confirmed, after drafting and after the amendment round, that only the
  ADR file itself had changed and that no application code or other
  accepted ADR was modified.
* Did not run `make lint` / `make test` / etc. — no application code changed
  in this session, so those checks do not apply.

## Problems or corrections

One round of bounded amendment materially strengthened the first full draft
— see "What happened" above: adding independent post-publication
verification and evidence-bound authorisation, closing a partial-Qdrant-
mutation gap the first draft had not fully resolved. A second, narrow,
non-architectural wording correction followed acceptance review, fixing an
inaccurate description of post-publication verification as a Laravel round
trip rather than a Qdrant-side check. Neither represented disagreement with
the underlying architecture, approved in principle from the first review.

## Next steps / important takeaways

* Stage 15.3 (Implement End-to-End Ingestion Orchestration) now implements a
  fully settled contract spanning ADR-0015 and ADR-0016 together: the
  purpose-scoped `v2` protocol (eight purposes, two verified normative test
  vectors), the five-outcome processing lease, the open/sealed chunk
  model and resume contract, the publication saga with its dual
  verification points, and the dual retrieval-visibility gate.
* Stage 15.3 inherits explicit, tracked obligations: the shared chunk-
  manifest and point-identity digest canonicalisation must be
  language-neutral, versioned, and conformance-tested in both PHP and
  Python; removing temporary `v1` compatibility must be tested, not merely
  completed; and the reclaim/DLQ cleanup policy must be implemented once,
  shared by both trigger paths, not built twice.
* The per-failure-category terminal/retry classification table remains
  Stage 15.3's implementation of taxonomies ADR-0010, ADR-0013 and ADR-0014
  already each independently established — it does not require a further
  ADR.
* Document deletion during publication remains cross-referenced to, not
  solved by, ADR-0006/ADR-0007's existing deletion-orchestration deferral;
  Stage 15.3 must ensure publication/completion revalidate Document
  eligibility, not design the full deletion saga.
