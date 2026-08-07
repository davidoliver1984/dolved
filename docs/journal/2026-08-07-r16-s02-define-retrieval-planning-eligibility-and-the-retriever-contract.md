# Session Journal: R16-S02 — Define Retrieval Planning, Eligibility and the Retriever Contract

## Date

2026-08-07

## Session mode

Architecture and documentation only. No application code, migrations,
models, HTTP endpoints, or retrieval code were introduced.

## What happened

Before drafting, ADR-0017, ADR-0016, ADR-0014, ADR-0013, ADR-0012, ADR-0009,
ADR-0007 and ADR-0006 were inspected in full, together with the current
Phase 16 roadmap/guide/tasks state, so ADR-0018 would consume — never
redecide — what each already established. A first full draft followed a
detailed, fully-specified brief covering the request flow, the
`RetrievalPlanner`/`RetrievalPlan` contract, `AuthorisedKnowledgeScope`,
`EligibilityResolver`/`EligibleRetrievalScope`, `COMPARE` semantics, the
`Retriever` contract, `RetrievalResult` and a retrieval outcome taxonomy,
and a new Laravel-to-Python synchronous protocol.

Two rounds of bounded amendment followed:

- **Round 1** addressed four issues found on review. First, the draft had
  the Python `Retriever` hydrate chunk text and provenance directly from
  PostgreSQL by analogy with ADR-0014's rebuild workflow; that analogy did
  not hold, and the design was corrected so the Retriever performs scoped
  vector search only, returning candidate identities already present on the
  Qdrant point, while Laravel batch-hydrates and performs a final
  eligibility recheck — closing, as a side effect, an eligibility-staleness
  trade-off the first draft had accepted. Second, the draft had no way to
  carry a semantic location reference (*"the medication procedure at
  Blackpool"*) even though ADR-0017 makes location applicability a hard
  eligibility dimension; a deliberately narrow, optional
  `applicability_reference` field was added to `RetrievalPlan`, resolved
  deterministically by `EligibilityResolver` against authoritative
  `OrganisationalLocation` data, with an unresolved or ambiguous reference
  producing `CLARIFICATION_REQUIRED` rather than being silently dropped.
  Third, the outcome taxonomy's `NO_SEMANTIC_MATCH` value implied a
  calibrated quality judgement this document has no honest basis for
  making before evaluation/hybrid-retrieval work exists; it was replaced
  with the purely count-based `NO_RETRIEVAL_CANDIDATES`. Fourth, the new
  `rc1` protocol's confidentiality and replay posture were strengthened:
  authenticated TLS was made an explicit, mandatory requirement (HMAC
  provides authentication/integrity only), and a signed `request_id` plus a
  bounded, freshness-window-scoped server-side replay-suppression cache
  were added — deliberately lighter than ingestion's durable event ledger,
  since retrieval is a synchronous, read-only call.
- The ADR was accepted after this round with no further changes requested.

Accepting ADR-0018 also surfaced a planning gap, recorded in its own
"Roadmap clarification" section: Phase 16, as sequenced after Stage 16.1
(ADR-0017), moved directly from the versioning/temporal-authority domain
model to the retrieval contract, without a session that actually builds
ADR-0017's relational/domain foundation before retrieval implementation
would need it. Phase 16 is corrected from seven stages to eight
accordingly — see "Decisions recorded" below.

## Decisions recorded

`docs/adr/0018-define-retrieval-planning-eligibility-and-the-retriever-contract.md`
records, in its final accepted form, everything summarised in
`IMPLEMENTATION_GUIDE.md` Stage 16.2's Decision section — the full request
flow; the three-way security/eligibility/descriptive metadata
classification; the `RetrievalPlanner`/`RetrievalPlan` contract, including
`applicability_reference`; `EligibilityResolver`/`EligibleRetrievalScope`;
`COMPARE`'s two-sided `PRIMARY`/`COMPARISON` resolution; the
PostgreSQL-free `Retriever` contract; `RetrievalResult` and the corrected
outcome taxonomy; and the complete `rc1` protocol, including its mandatory
TLS requirement and bounded replay defence — not duplicated here.

The Phase 16 stage structure is corrected accordingly: a new Stage 16.3
("Implement Document Versioning and Temporal Authority Foundation") is
inserted, and every stage after it is renumbered by one, extending Phase 16
from seven stages to eight. Stage 16.1's and this session's own Stage 16.2
completed records, commits and tags are unchanged by the correction.

## Verification performed

* Read ADR-0017, ADR-0016, ADR-0014, ADR-0013, ADR-0012, ADR-0009, ADR-0007
  and ADR-0006 in full, and the current Phase 16 roadmap/guide/tasks state,
  before forming any recommendation.
* Computed and independently cross-verified (Python `hmac`/`hashlib` and
  `openssl`, two separate implementations) two successive normative `rc1`
  test vectors for `retrieval.search` — a six-field vector, superseded by
  the seven-field vector once the signed `request_id` was added in round 1
  — before trusting either.
* Traced each rejected design from round 1 (Python PostgreSQL hydration, a
  raw-score `NO_SEMANTIC_MATCH` threshold, a timestamp-only replay defence)
  against the specific correctness, truthfulness, or confidentiality gap it
  left open, to confirm each correction actually closes the gap it targets.
* Checked the accepted ADR against each Stage 16.2 acceptance criterion in
  `IMPLEMENTATION_GUIDE.md`; all are met.
* Confirmed, after the amendment round and again before acceptance, that
  only the ADR file itself had changed and that no other accepted ADR or
  application code was modified.
* Resynchronised `tasks.json`'s `guide_start_line`/`guide_end_line`
  references for Stage 16.2 onward, the newly inserted Stage 16.3, and
  every stage/phase from R16-S04 through R23-S03, against the restructured
  guide content. Verified structurally: unique phase/session identifiers
  (90 sessions, up from 89), every session's recorded start line matching
  its actual heading text in `IMPLEMENTATION_GUIDE.md`, and no inverted or
  unaccounted-for line ranges other than the deliberate gap occupied by the
  "Phase 16 restructuring note (second)" heading between Stage 16.2 and
  Stage 16.3 — the same, already-established pattern used for the first
  Phase 16 restructuring note before Stage 16.1. Pre-existing header/line
  mismatches in phases R00–R13, unrelated to this session, were confirmed
  present before this change and left untouched as out of scope.
* Did not run `make lint` / `make test` / etc. — no application code
  changed in this session, so those checks do not apply.

## Problems or corrections

One round of bounded amendment materially strengthened the first full
draft, closing four distinct gaps found on review: an unjustified Python
PostgreSQL access grant; a missing, safely-bounded way to express location
applicability from a user's question; an untruthful semantic-match outcome
definition; and an underspecified transport-confidentiality/replay posture
for the new synchronous protocol. None represented disagreement with the
underlying architecture, approved in principle from the first review.

## Next steps / important takeaways

* Stage 16.3 (Implement Document Versioning and Temporal Authority
  Foundation) is next: it implements ADR-0017's relational/domain model —
  the `DocumentFamily` backfill migration, lineage and governance tables,
  the `authority_start`/`authority_end` derivation as a query, the three
  structural constraints (`effective_from` uniqueness, `authority_start`
  uniqueness, lineage-monotonic ordering), and the backdated-correction
  permission/audit path — with no new architectural decision of its own.
* Stage 16.4 (Implement Semantic Retrieval, renumbered from the former
  Stage 16.3) implements ADR-0018 against the completed Stage 16.3
  foundation: `RetrievalPlanner`, `EligibilityResolver`, the `Retriever`,
  and the `rc1` protocol.
* `rc1`'s bounded, in-memory replay-suppression cache is an accepted V1
  limitation (does not survive a Python process restart) — a shared,
  distributed cache for a multi-instance Python deployment is Stage 16.4
  implementation work, not designed here.
* Evaluation metrics, hybrid retrieval, reranking, calibrated evidence
  thresholds, and answer generation remain out of ADR-0018's scope, as
  recorded in its own "Scope boundaries" section — nothing here should be
  read as having decided them.
