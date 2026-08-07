# Session Journal: R16-S05 — Define Retrieval Evaluation and Quality Gates

## Date

2026-08-07

## Session mode

Architecture and documentation only. No application code, migrations,
models, HTTP endpoints, or evaluation code were introduced.

## What happened

Codex correctly paused before beginning Stage 16.6 implementation directly
against Stage 16.5's original stub — a thin, pre-ADR-0017/ADR-0018
"Planned decisions" list that collapsed retrieval and generation metrics
into one undifferentiated catalogue — on the grounds that Stage 16.5 is an
architecture session and the evaluation model itself had not been agreed.
Codex's own review independently recommended a repository-owned evaluation
harness with optional framework adapters ("Option A"): a versioned corpus,
deterministic metrics, framework-neutral evaluator adapters, accepted
baselines, versioned quality-gate policy, and experiment lineage.

Before drafting, ADR-0013, ADR-0017, ADR-0018, ADR-0012 and ADR-0006 were
inspected in full, together with Codex's recommendation and the current
Phase 16 roadmap/guide/tasks state, so ADR-0019 would consume — never
redecide — what each already established. A first full draft followed a
detailed, fully-specified brief covering corpus ownership, stable case
identity, evidence identity, layered expectations, required case families,
deterministic metrics, slices, the hard-failure/comparative distinction,
baseline promotion, experiment lineage, quality-gate policy, the manual
release gate, and the model-assisted-evaluator question.

Two rounds of bounded amendment followed:

- **Round 1** corrected the brief's proposed fallback for relevance ground
  truth. The brief suggested anchoring evidence identity to source-element
  identity "where sufficiently stable"; tracing ADR-0010's own accepted
  rule (*"a new extraction run creates new element UUIDs... no
  deterministic cross-extraction identifier scheme is implied"*) through
  ADR-0016's already-documented consequence for chunk identity confirmed
  that no pipeline-generated identifier — not chunk, not normalised
  element, not extracted element — survives even a same-content
  re-extraction, let alone the chunking-strategy changes the harness exists
  to compare. Ground truth was corrected to anchor to
  `Document`/`DocumentFamily`/version identity plus a corpus-authored
  canonical text excerpt, resolved against retrieved chunk text at
  evaluation time via text-containment matching, never via any pipeline
  identifier. The first draft also deferred a provider-neutral
  model-assisted-evaluator abstraction, reasoning that Stage 16.6's actual
  metrics (Recall@K/Precision@K/MRR/nDCG) were entirely deterministic with
  no concrete caller.
- **Round 2** reversed that deferral on explicit direction: Ragas is
  wanted genuinely integrated in V1, not left as named-but-unbuilt future
  extensibility. A provider-neutral `ModelAssistedEvaluator` contract was
  introduced and built in V1, with a concrete `RagasEvaluator` adapter as
  its first implementation, translating between application-owned request/
  result types and Ragas's own shapes entirely inside that one adapter.
  Only Ragas's context-relevance metric — computable from a question and
  retrieved evidence alone, with no generated answer required — was wired
  into Phase 16, as an advisory signal alongside the deterministic metrics;
  faithfulness, answer relevancy and answer correctness remain deferred to
  Stage 17.4, which extends the same adapter rather than building a second
  one. A short philosophy statement was added before acceptance, making
  explicit that retrieval evaluation is a first-class architectural
  capability, not a testing activity.
- The ADR was accepted after this round.

## Decisions recorded

`docs/adr/0019-define-retrieval-evaluation-and-quality-gates.md` records,
in its final accepted form, everything summarised in
`IMPLEMENTATION_GUIDE.md` Stage 16.5's Decision section — repository
ownership of the corpus, schema, stable identities, deterministic metrics,
results, baselines and quality-gate policy; corpus versioning and
immutability once baselined; stable semantic case identity; the
source-anchored relevance-identity strategy; layered planner/eligibility/
retrieval/operational expectations and results; the required V1 case
families; first-class slices; the absolute-invariant/comparative-quality
distinction; deliberate baseline promotion; experiment lineage; the
initial manual release gate; and the `ModelAssistedEvaluator`/
`RagasEvaluator` boundary and its Phase-16-appropriate metric — not
duplicated here.

Stage 16.5's title in `IMPLEMENTATION_GUIDE.md`, `tasks.json` and
`PROJECT_ROADMAP.md` is corrected from "Define Evaluation and
Quality-Gate Architecture" to "Define Retrieval Evaluation and Quality
Gates" to match the accepted ADR.

## Verification performed

* Read ADR-0013, ADR-0017, ADR-0018, ADR-0012 and ADR-0006 in full, and
  Codex's own R16-S05 architecture recommendation, before forming any
  independent view.
* Traced the extraction/chunking identity chain (ADR-0010's fresh per-run
  element identity, through ADR-0016's already-documented consequence for
  chunk identity) to confirm no pipeline-generated identifier is stable
  enough to anchor evaluation ground truth to, before correcting the first
  draft's proposed fallback.
* Checked the accepted ADR against each Stage 16.5 acceptance criterion in
  `IMPLEMENTATION_GUIDE.md`; all are met.
* Confirmed, after each amendment round and again before acceptance, that
  only the ADR file itself had changed and that no other accepted ADR or
  application code was modified.
* Resynchronised `tasks.json`'s `guide_start_line`/`guide_end_line`
  references for Stage 16.5 and every stage/phase from R16-S06 through
  R23-S03 against the completed record's actual length, taking care to
  shift only sessions that actually follow Stage 16.5 (an initial transform
  incorrectly shifted the already-completed R16-S01–R16-S04 as well; caught
  by structural verification and corrected before proceeding). Verified
  structurally: unique phase/session identifiers, every session's recorded
  start line matching its actual heading text in `IMPLEMENTATION_GUIDE.md`,
  and no unaccounted-for line ranges other than the already-established
  deliberate gap for the "Phase 16 restructuring note (second)" heading
  between Stage 16.2 and Stage 16.3. Pre-existing header/line mismatches in
  phases R00–R13, unrelated to this session, were confirmed present before
  this change and left untouched as out of scope.
* Did not run `make lint` / `make test` / etc. — no application code
  changed in this session, so those checks do not apply.

## Problems or corrections

Two rounds of bounded amendment were required before acceptance: a
correction to the relevance-identity anchoring strategy (round 1, closing
a genuine correctness gap the brief itself had not fully resolved), and a
reversal of the model-assisted-evaluator deferral to introduce and build
`ModelAssistedEvaluator`/`RagasEvaluator` in V1 (round 2, a directional
correction, not a correctness fix — the first draft's reasoning for
deferral was internally sound but rested on a premise about V1 scope that
was corrected). A short philosophy-statement addition followed, requested
alongside acceptance rather than as a substantive round. During the
tasks.json resync, an initial line-shift transform incorrectly included
already-completed sessions preceding Stage 16.5; this was caught by the
same structural verification this project applies to every planning-file
transform, before it was written anywhere durable, and corrected in a
second pass.

## Next steps / important takeaways

* Stage 16.6 (Implement Retrieval Evaluation) is next: it builds the
  corpus (schema, required case families, golden-regression mechanism),
  the deterministic metrics, the `ModelAssistedEvaluator` contract and
  `RagasEvaluator` adapter with the Phase-16 context-relevance metric, the
  experiment/result and quality-gate-policy schemas, baseline recording
  and promotion, and the initial manual release-gate reporting — with no
  new architectural decision of its own.
* Stage 16.6 produces the first measured baseline; regression tolerances
  and which comparative metrics become release-blocking are calibrated
  from that evidence, not invented now.
* Stage 17.4 (Add Answer Evaluation) extends the same
  `ModelAssistedEvaluator`/`RagasEvaluator` adapter with faithfulness,
  answer relevancy and answer correctness once generation exists — it does
  not build a second evaluator boundary.
* Hybrid retrieval, reranking, calibrated evidence thresholds, and any
  customer-facing production evaluation dataset remain out of ADR-0019's
  scope, as recorded in its own "Scope boundaries" section.
