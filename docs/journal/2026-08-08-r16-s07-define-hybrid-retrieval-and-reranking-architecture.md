# Session Journal: R16-S07 — Define Hybrid Retrieval and Reranking Architecture

## Date

2026-08-08

## Session mode

Architecture and documentation only. No application code, migrations,
models, HTTP endpoints, or retrieval code were introduced.

## What happened

Before drafting, ADR-0014, ADR-0017, ADR-0018, ADR-0019 and ADR-0020 were
inspected in full, together with Codex's implementation-driven
recommendation (informed by the Stage 16.6 baseline and hands-on
evaluation-harness experience) and the current Phase 16 roadmap/guide/
tasks state. A first full draft evaluated Codex's recommended shape
independently rather than transcribing it: `SparseEncoder`
(FastEmbed/SPLADE++), application-owned `FusionStrategy` (RRF), a
provider-neutral `Reranker` (Voyage `rerank-2.5`), six independently
versioned candidate-pipeline parameters, and a new `EvidenceThresholdPolicy`
exercising the calibrated-acceptance decision ADR-0018 had explicitly
deferred to this session.

Two rounds of bounded amendment followed:

- **Round 1** made the six candidate-pipeline parameters' independence
  explicit by name (`dense_candidate_k`, `sparse_candidate_k`,
  `fusion_candidate_k`, `reranker_candidate_k`, `evidence_threshold`,
  `final_evidence_k`), stating plainly that `fusion_candidate_k` and
  `reranker_candidate_k` sharing an initial experimental value was
  coincidence, not a structural relationship. A minor wording change
  replaced "maximise the probability" with "increase the likelihood" in
  the Philosophy section, for consistency with the platform's existing
  refusal to describe retrieval/fusion/reranker scores as calibrated
  probabilities.
- **Round 2**, following Codex's implementation-readiness review, closed
  thirteen further implementation-significant gaps: independence not
  meaning every candidate-count combination is valid (added explicit
  structural, data-flow bounds distinct from the semantic independence
  above); RRF specified precisely enough to be fully deterministic
  (1-based ranks, versioned `rrf_k`, at-most-one-contribution-per-list, a
  fixed three-step tie-break never depending on provider return order);
  the "dense vectors reused unchanged" claim corrected, since ADR-0014's
  point-identity derivation means a new generation always has newly-derived
  point identities regardless of strategy; an honest acknowledgement that
  sparse-profile/generation lineage requires real PostgreSQL migrations,
  never claimed unnecessary; `SparseEncoder` input-length validation and
  no-silent-truncation, mirroring ADR-0013's existing discipline;
  `EvidenceThresholdPolicy` ownership pinned explicitly to Laravel (Python
  computes scores, never decides "good enough"), with immutable identity
  binding to the exact configuration it was calibrated against; a required
  calibration/held-out split for threshold and configuration selection;
  a lifecycle-correct rollback operation (`SUPERSEDED -> ACTIVE`, atomic,
  audited) replacing an under-specified "repoint" description; a fully
  specified `retrieval.rerank` `rc1` purpose inheriting every existing
  security/privacy requirement; strict reranker response validation on
  both the Python and Laravel sides; and a corrected claim that SPLADE++
  is merely the selected V1 implementation of a stated requirement
  (tenant-/corpus-independent sparse encoding), not the only method that
  could ever satisfy it.
- The ADR was accepted after this round with no further changes requested.

## Decisions recorded

`docs/adr/0021-define-hybrid-retrieval-and-reranking-architecture.md`
records, in its final accepted form, everything summarised in
`IMPLEMENTATION_GUIDE.md` Stage 16.7's Decision section — the full hybrid
pipeline; `SparseEncoder`/`SparseEmbeddingProfile` and the sparse-space
generation extending ADR-0014's model; the BM25-versus-learned-sparse-
encoder reasoning; deterministic RRF fusion; the six independently
versioned, structurally bounded candidate-pipeline parameters; the
`Reranker` contract and its response validation; the extended two-round-
trip Laravel hydration sequence; the `retrieval.rerank` `rc1` purpose;
`EvidenceThresholdPolicy`'s ownership, identity binding, and calibration/
held-out requirement; the new `INSUFFICIENT_EVIDENCE` outcome; and the
lifecycle-correct rollback operation — not duplicated here.

## Verification performed

* Read ADR-0014, ADR-0017, ADR-0018, ADR-0019 and ADR-0020 in full, and
  Codex's implementation-driven recommendation and implementation-readiness
  review, before forming any recommendation.
* Traced ADR-0014's deterministic point-identity derivation explicitly to
  confirm a hybrid-enabled generation's points are always newly identified,
  before correcting the first draft's "reused unchanged" wording.
* Independently reasoned through why BM25's corpus-statistical weights are
  structurally incompatible with this platform's shared-collection tenancy
  and cheap-incremental-ingestion invariants, rather than accepting
  SPLADE++'s selection on recommendation alone.
* Verified, before beginning the second amendment round, that the first
  round's amendments were genuinely present on disk (byte-level check
  against the file's actual content) after a report suggested they might
  not be — confirmed present; the file had never been committed and no
  write had been lost.
* Checked the accepted ADR against each Stage 16.7 acceptance criterion in
  `IMPLEMENTATION_GUIDE.md`; all are met.
* Confirmed, after each amendment round and again before acceptance, that
  only the ADR file itself had changed and that no other accepted ADR or
  application code was modified.
* Resynchronised `tasks.json`'s `guide_start_line`/`guide_end_line`
  references for Stage 16.7 and every stage/phase from R16-S08 through
  R23-S03. An initial delta-based resync attempt produced 39 new
  mismatches in phases R17–R23, traced to a pre-existing drift between
  `tasks.json`'s stored line numbers and the guide's actual content from
  before this session; corrected by rebuilding every affected range
  directly from the guide file's actual current header positions rather
  than compounding delta arithmetic on a value that was already wrong.
  Final state verified structurally: 90 unique sessions, every session's
  recorded start line matching its actual heading text, the last session's
  end line matching the guide file's actual total line count, and no
  unaccounted-for ranges beyond the already-established deliberate gaps
  around the Phase 16 restructuring notes. Pre-existing header/line
  mismatches in phases R00–R13, unrelated to this session, were confirmed
  present before this change and left untouched as out of scope.
* Did not run `make lint` / `make test` / etc. — no application code
  changed in this session, so those checks do not apply.

## Problems or corrections

Two rounds of bounded amendment were required before acceptance. The
first addressed a wording-precision request (parameter independence,
probability language). The second, following Codex's implementation-
readiness review, closed thirteen genuine implementation-significant gaps
— none reopened the accepted-in-principle architecture (SparseEncoder,
FastEmbed/SPLADE++, application-owned FusionStrategy, RRF, provider-neutral
Reranker, Voyage rerank-2.5, Laravel hydration, EvidenceThresholdPolicy,
verified-generation rollout); each closed a specific determinism, honesty,
ownership, security, or lifecycle-correctness gap the first draft had left
open. A tasks.json line-resync error was also caught and corrected during
this session, by the same structural verification this project applies to
every planning-file transform, before it was committed anywhere.

## Next steps / important takeaways

* Stage 16.8 (Implement Hybrid Retrieval and Reranking) is next, with an
  explicitly broad implementation boundary confirmed in the ADR itself:
  `apps/ai`, `apps/api`, `contracts`, PostgreSQL migrations/models/
  relationships for sparse-profile and sparse-space-generation lineage,
  ingestion/generation-completeness changes, Qdrant collection/vector
  configuration, cross-service tests, evaluation/calibration artefacts,
  configuration/dependency files, and factual guide/tracker/journal
  updates — the prior stub's narrower "apps/ai plus tests" framing is
  explicitly obsolete.
* Stage 16.8 selects `evidence_threshold` and any claimed-improvement
  configuration using a calibration/tuning split of ADR-0019's corpus, and
  assesses the selection against a separate held-out acceptance split that
  never influenced selection — an explicit ADR-0021 requirement, not
  optional evaluation hygiene.
* Wherever planning-document text still carries pre-ADR-0017 "freshness"/
  "archival" terminology, it should be corrected to ADR-0017/ADR-0018's
  actual temporal-authority and eligibility vocabulary as it is
  encountered in future sessions.
* Workspace billing, commercial pricing, answer generation, prompt
  construction, citation generation, and agent workflows remain out of
  ADR-0021's scope, as recorded in its own "Scope boundaries" section.
