# ADR 0020: Clarify Retrieval Evaluation Evidence and Model-Assisted Evaluation Semantics

## Status

Accepted

## Date

2026-08-07

## Relationship to ADR-0019

**ADR-0019 remains Accepted and authoritative.** This document does not
reopen, redesign, or re-litigate any part of ADR-0019's architecture:
repository ownership of the corpus, schema, results, baselines and
quality-gate policy; corpus versioning and immutability; layered planner/
eligibility/retrieval/operational evaluation; first-class slices; the
absolute-invariant/comparative-quality distinction; deliberate baseline
promotion; the initial human-reviewed release gate; the framework-neutral
`ModelAssistedEvaluator` boundary with a concrete `RagasEvaluator` in V1;
and the evaluation/generation separation all stand exactly as ADR-0019
already decided them.

Before `R16-S06` implementation began, Codex performed a pre-implementation
review of accepted ADR-0019 and found several implementation-significant
issues — some factual corrections to what ADR-0019's text implied, some
underspecified points implementation would otherwise have had to guess at.
Because ADR-0019 was already committed and tagged (`phase-16-s05`) by the
time this review happened, this document follows this repository's own
immutability rule: **an accepted ADR is not rewritten in place; a
correction or clarification is recorded as a new, narrower ADR that says
explicitly what it changes and what it leaves standing.** This document is
that narrow follow-up. It clarifies and, only for the bounded points listed
below, partially supersedes ADR-0019; every other part of ADR-0019 is
unchanged and this document must not be read as reopening it.

**Which points are clarification versus partial supersession**, stated
precisely rather than left ambiguous: the `EvidenceUnit` contract,
deterministic-metric semantics over it, the digest/lineage additions,
case-first aggregation, the deterministic-versus-stochastic distinction,
the offline/live test policy, and the `RagasEvaluator` model-injection and
failure-handling requirements are all **clarifications and additions** —
they make explicit what ADR-0019's stated intent already implied, without
changing what ADR-0019 decided. The adversarial-case ownership correction,
the narrowed V1 `ModelAssistedEvaluationRequest` shape, and the corrected
Ragas context-relevance semantics are **partial supersessions** — each
corrects a specific claim ADR-0019's text made (or a specific field its
text permitted) that does not hold up against ADR-0018's actual contract
or Ragas's actual documented behaviour. Both kinds are recorded in one
document because both were found in the same review and both are bounded,
not because this document treats them as the same kind of change.

## Context

`R16-S06` (Implement Retrieval Evaluation) was about to begin implementing
directly against accepted ADR-0019. Before doing so, Codex performed the
pre-implementation review this repository's workflow already expects of an
architecture stage before its implementation session starts, and found
issues an implementer would otherwise have had to resolve unilaterally, or
would have implemented incorrectly against ADR-0019's literal text. Two
were factual corrections: ADR-0019's adversarial-case framing implied
document content could reach and corrupt `RetrievalPlanner`, which
contradicts ADR-0018's own contract (the planner receives only the
question and an evaluation instant, never documents); and ADR-0019's
`ModelAssistedEvaluationRequest` description simultaneously permitted a
reference answer in the V1 request while elsewhere correctly stating that
Phase 17 would define answer-dependent fields — an internal contradiction.
The remainder were genuine underspecifications: ADR-0019 committed to
source-anchored relevance ground truth without naming the ground-truth
unit itself; committed to Ragas integration without settling model
ownership, test policy, or failure isolation; and left several lineage and
aggregation questions (content digests, phrasing-variant weighting,
stochastic reproducibility) implicit rather than stated.

None of these findings challenge ADR-0019's architecture. All of them are
exactly the kind of bounded precision a pre-implementation review is
supposed to surface before an implementer has to guess.

## What this ADR decides and does not decide

This ADR decides, and only this: the corrected adversarial-case ownership
split (planner-robustness versus retrieval-robustness versus generation-
robustness); the repository-owned `EvidenceUnit` contract as the stable
ground-truth anchor ADR-0019's source-anchoring direction requires;
distinct-evidence-unit semantics for Recall@K/Precision@K/MRR/nDCG, with
deterministic, versioned, recorded duplicate-credit handling (exact formula
left to `R16-S06`); reaffirmation that `ModelAssistedEvaluator` →
`RagasEvaluator` remains the V1 architecture; `RagasEvaluator` receiving an
injected, configuration-owned evaluator-model/client, never an implicit
global instantiation; the offline/live Ragas test policy and model-assisted
failure isolation; the Phase 16 `ModelAssistedEvaluationRequest`'s
retrieval-specific scope, with no speculative answer/reference fields;
Ragas context-relevance as an aggregate, advisory, context-set judgement,
never candidate-level attribution; V1 access/isolation cases scoped to the
permission model ADR-0006 actually implements; canonical corpus/policy
content digests as part of experiment/baseline lineage; case-first
aggregation of phrasing variants; the distinction between deterministic
metric reproducibility and stochastic live-model reproducibility; and
confirmation that `R16-S06` remains the correct implementation owner for
all of the above. It does not decide anything ADR-0019 already settled and
does not mention above — that architecture is untouched, referenced here
by cross-reference, never restated as if newly decided.

## Decision

### Adversarial-case ownership, corrected

ADR-0019's adversarial-content discussion is corrected — this is a partial
supersession of ADR-0019's "Required V1 case families" section, not an
extension of it. ADR-0018 is explicit that `RetrievalPlanner` receives only
the user's question text and an authoritative evaluation instant; it never
receives retrieved documents, and never touches PostgreSQL or Qdrant. A
document's content therefore cannot corrupt `RetrievalPlanner` — there is
no path by which it could. The corrected adversarial-case split, by which
component actually receives each kind of adversarial input:

- **Planner robustness** (tests `RetrievalPlanner`'s only actual input):
  adversarial or manipulative user *questions*; questions attempting to
  manipulate `temporal_mode` or `applicability_reference` classification
  through misleading phrasing; ambiguous or deliberately confusing
  natural-language requests that should produce `CLARIFICATION_REQUIRED`
  rather than a guessed interpretation.
- **Retrieval robustness** (tests what the `Retriever` and its ranking
  actually process): adversarial *document content*; prompt-like
  instructions embedded in documents; misleading passages; conflicting
  evidence across documents; content shaped to distort embedding or
  ranking behaviour. Meaningfully testable today, against the deterministic
  `EvidenceUnit` metrics below, independent of whether any LLM ever reads
  the content as an instruction — the planner never does, but ranking
  behaviour can still be distorted by content engineered to game embedding
  similarity.
- **Generation robustness (named, still deferred)** — document content
  actually influencing a *generated answer* (instruction-following effects,
  prompt injection reaching the generation model) requires a generation
  step this pipeline does not yet have. Remains Stage 17.4's concern,
  exactly as ADR-0019 already deferred it; only the *ownership split above*
  is corrected here, not the deferral itself.

### The repository-owned `EvidenceUnit` contract

ADR-0019 commits to anchoring relevance ground truth to source content
rather than any pipeline-generated identifier, but does not name the
ground-truth unit itself. This document supplies it, as a clarification of
ADR-0019's "Stable evidence identity" section, not a change to its
direction: `ExtractedElement`, `NormalisedElement`, and `Chunk` identity
remain rejected as ground-truth anchors, exactly as ADR-0019 already
established, because ADR-0010/ADR-0011 do not guarantee those identities
stable across independent reprocessing or chunking-configuration changes.

An `EvidenceUnit` is the stable, repository-owned ground-truth concept — a
retrieved chunk is merely one representation that may or may not cover it.
It carries, at minimum:

- `evidence_id` — a stable identifier for this specific piece of ground
  truth, independent of any pipeline-generated identifier;
- the authoritative `Document`/`DocumentFamily`/version identity it belongs
  to;
- one or more **canonical source excerpts** — short, human-verified
  quotations from the source document that a corpus author confirms
  actually appear in, and answer, the case;
- a relevance grade, where the case's metrics need one;
- a deterministic coverage/matching requirement — the rule deciding
  whether a given retrieved chunk counts as covering this unit; and
- optional semantic notes, useful to a corpus author or report reader,
  never required by metric execution.

Evidence spanning multiple chunks may satisfy one `EvidenceUnit` through
deterministic **combined coverage** across those chunks; a single
`EvidenceUnit` must never require one individual chunk to contain an
entire canonical excerpt on its own, since real source content routinely
spans chunk boundaries a corpus author has no reason to predict. The exact
normalisation, fuzzy-matching threshold, and multi-chunk coverage-
combination algorithm remain `R16-S06` implementation work — this document
fixes only that the algorithm must be deterministic, versioned, and
recorded as evaluation configuration, never an unversioned detail that
could silently change what "covered" means between runs. The architectural
invariant this section fixes: **ground truth measures semantic source
evidence, never accidental chunk boundaries.**

### Deterministic metrics, defined over distinct evidence units

A clarification of ADR-0019's "Deterministic retrieval metrics" section,
restating each metric precisely against `EvidenceUnit` coverage:

- **Recall@K** — denominator is the count of *distinct* required
  `EvidenceUnit`s for the case (or case side, for `COMPARE`); numerator is
  the count of distinct required units covered by at least one retrieved
  chunk within the top K. Multiple chunks covering the same `evidence_id`
  never increase recall more than once.
- **Precision@K** — each ranked chunk receives deterministic relevance
  credit through the `EvidenceUnit`(s) it covers; duplicate chunks covering
  the same `evidence_id` must not gain artificial repeated credit. This
  document fixes that requirement — deterministic, versioned, and recorded
  duplicate-credit handling — without mandating the exact formula, which
  remains `R16-S06` implementation work.
- **MRR** — the rank of the first retrieved candidate that satisfies a
  required `EvidenceUnit`, per the case's declared relevance expectations.
- **nDCG** — relevance is assigned through matched `EvidenceUnit`s and
  their repository-owned relevance grades, with duplicate coverage treated
  deterministically, per Precision@K above, never double-counted.

Multi-evidence-case completeness is measured by coverage of the declared,
distinct `EvidenceUnit` set — never by chunk count. Nothing here changes
ADR-0019's own position that no numerical release threshold or regression
tolerance is fixed in advance of `R16-S06`'s first measured baseline.

### `ModelAssistedEvaluator` → `RagasEvaluator`: reaffirmed, not reopened

ADR-0019's V1 architecture stands exactly as accepted: a provider-neutral
`ModelAssistedEvaluator` contract, built in V1 (not deferred), with a
concrete `RagasEvaluator` adapter as its first implementation; Ragas
integrated as one replaceable implementation, never the owner of the
corpus, schema, deterministic metrics, results, baselines, or the release
decision; a model-assisted metric advisory until demonstrated stable, and
never — regardless of demonstrated maturity — the sole authority for a
deterministically-testable property (workspace isolation, authorisation,
temporal eligibility, applicability). This document changes none of that;
the sections below add the operational precision Codex's review found
missing around it.

### `RagasEvaluator`: injected, configuration-owned model ownership

`RagasEvaluator` receives its evaluator-model/client as an **injected,
configuration-owned dependency** — it never instantiates a provider or
model implicitly inside the adapter. This is the same explicit-
configuration discipline this platform already applies to `Embedder`'s
provider selection and to `RetrievalPlanner`'s own model configuration,
applied here to close a gap ADR-0019's adapter description left open. No
Ragas-specific type — dataset object, metric class, exception type,
configuration structure — appears anywhere outside the `RagasEvaluator`
adapter, exactly as ADR-0019 already required; injected model ownership is
what makes that isolation testable rather than merely asserted (see
"Offline and live test policy" below).

**Model-assisted experiment lineage** additionally records, beyond what
ADR-0019's general experiment-lineage requirements already cover: the
evaluator implementation identity; the Ragas library version; the judge
provider and judge model identity; relevant evaluator prompt/settings/
configuration; temperature and seed where the provider supports them;
token usage; request/call count; latency; and estimated cost.

### Offline and live test policy

Ordinary repository tests never require credentials or a paid network
call. This means: a deterministic fake `ModelAssistedEvaluator` for
application/harness-level tests; a fake evaluator-model/client injected
into `RagasEvaluator` for adapter translation and mapping tests, exercising
the adapter's own logic without a live judge call; and deterministic tests
for application-owned result mapping and failure handling. A genuine
Ragas-plus-provider integration test is legitimate only as an explicit,
credential-dependent, opt-in test, excluded from ordinary repository/CI
quality gates unless deliberately enabled — the same isolated, opt-in
posture ADR-0013 already requires of real Voyage integration tests.

A model-assisted evaluation failure (the judge call errors, times out, or
the provider is unavailable) produces a controlled, advisory evaluation
failure, recorded in experiment/report lineage. It must never erase
deterministic metric results, never turn a deterministic hard-gate success
or failure into "unknown," and never cause an otherwise-reproducible
deterministic experiment result to disappear. Deterministic and
model-assisted results are independent artefacts within one experiment; a
failure in one must not corrupt or hide the other.

Codex's dependency review found that a non-mutating resolution of Ragas
0.4.3 succeeds under the repository's Python 3.14 environment but
introduces a substantial transitive dependency set. This document does not
encode those specific package-resolution details as architectural
invariants — dependency versions are not architecture — but records the
consequence: adding the concrete Ragas dependency requires a full
repository regression and dependency-compatibility verification during
`R16-S06`, not an assumption that it drops in cleanly.

### The Phase 16 `ModelAssistedEvaluationRequest` is retrieval-specific

A partial supersession of ADR-0019's request description, which
simultaneously permitted a reference answer in the V1 request while
elsewhere correctly stating Phase 17 would define answer-dependent fields
— an internal contradiction resolved here in favour of the narrower
contract. The Phase 16 `ModelAssistedEvaluationRequest` carries only what
retrieval-time context evaluation genuinely needs: the case's question
text (and relevant `retrieval_queries` variant); the retrieved evidence
under judgement (chunk text and its resolved `Document`/`DocumentFamily`/
version identity); which named, application-defined metric(s) to compute;
and evaluator identity/configuration for lineage. It does **not** carry a
`generated_answer` or `reference_answer` field, nullable or otherwise,
merely to future-proof against metrics Phase 16 does not use. When Stage
17.4 introduces answer-dependent metrics, it extends or versions this
application-owned request type with the fields those metrics genuinely
require — an additive change to a type this platform already owns, not a
redesign, and not something this document or ADR-0019 needs to anticipate
the exact shape of now.

### Ragas context-relevance: aggregate judgement, never candidate-level attribution

A partial supersession correcting an overclaim risk in how ADR-0019
described the Phase 16 Ragas signal. Ragas's context-relevance metric
evaluates the **supplied retrieved-context set as an aggregate**, against
the question — it is not, and must not be described as, a mechanism that
deterministically identifies *which exact retrieved candidate* is
responsible for a low score. Its result is an advisory, model-assisted
judgement over the context set as a whole: it may disagree with the
deterministic `EvidenceUnit` metrics and flag a case for human
investigation, but it must never be presented as candidate-level
attribution unless a future, specifically-selected metric actually
provides that property. Deterministic `EvidenceUnit` matching remains the
sole authoritative mechanism for candidate-level relevance judgement,
exactly as ADR-0019 already establishes for deterministic metrics
generally; the Ragas signal is a second, coarser, aggregate-level opinion
alongside it, not a replacement or a refinement of it.

### V1 access/isolation cases match the permission model actually implemented

A clarification of ADR-0019's "Required V1 case families" security/
isolation coverage. ADR-0006 establishes workspace membership as V1's
access boundary; there is no document-level or access-group-level granular
permission model implemented, and `R16-S06` must not invent one merely to
populate a more elaborate-looking corpus. The mandatory V1 security/
isolation case family covers: active workspace membership; non-member
denial; cross-workspace isolation; workspace concealment/fail-closed
behaviour (ADR-0006's `404`-not-`403` posture); and membership-based
authorisation generally. The case taxonomy and corpus schema remain
extensible for granular per-document or access-group permission cases, but
those become a mandatory case family only once such a permission model
actually exists to test.

### Canonical corpus/policy content digests

A clarification and addition to ADR-0019's corpus-immutability and
experiment-lineage requirements. A version label alone cannot prove an
allegedly immutable corpus or quality-gate policy was not edited in place.
Every corpus version and every quality-gate-policy version therefore also
carries a **canonical content digest**, computed deterministically over its
canonicalised content — the same `sha256`-of-canonical-content pattern this
codebase already uses for `ChunkingConfiguration.fingerprint()` and the
`EmbeddingProfile` fingerprint. Baseline promotion treats a corpus version
or policy version whose digest no longer matches what an already-accepted
baseline recorded against that same version number as a distinct artefact,
to be rejected or explicitly re-labelled — never silently accepted as
though nothing had changed. Experiment lineage records both the version
and its digest, for the corpus and for the policy, alongside the harness
implementation version sufficient to identify the executable metric
implementation that ran. The same named immutable version must never
silently mean different content.

### Case-first aggregation of phrasing variants

A clarification of ADR-0019's stable semantic case identity. Every
phrasing variant is evaluated independently, but variant results are
aggregated *within* their semantic case before that case contributes to
corpus- or slice-level metrics; every semantic case then carries equal
weight at the corpus/slice aggregation level, unless an explicitly
versioned quality-gate policy deliberately says otherwise. Adding five
paraphrases to one case must not make that one underlying information need
count six times as heavily as a case with a single phrasing. Per-variant
results remain fully available in the machine-readable report for
diagnosis — case-first aggregation changes what counts toward an aggregate
number, never what is recorded or inspectable.

### Deterministic metric reproducibility versus live model-output determinism

A clarification, not a weakening, of ADR-0019's reproducibility
requirement. **Deterministic metric implementations must reproduce exactly
for the same recorded inputs** — given the same retrieved-candidate list
and the same `EvidenceUnit` set, Recall@K/Precision@K/MRR/nDCG compute the
same number every time, because they are ordinary arithmetic over
already-recorded data. This does **not** imply, and this document
explicitly rejects the implication, that a live `RetrievalPlanner` call or
a live Ragas judge call itself must return byte-identical output on every
invocation — an LLM-backed component is not required to be deterministic
at the model layer.

For stochastic or model-assisted results, experiment lineage records,
where applicable: trial count (when a case is deliberately run more than
once to characterise variance); provider/model identity; evaluator or
planner configuration; temperature; seed, where the provider supports one;
per-trial results; aggregate statistics; and variance/dispersion across
trials. This document does not mandate how many trials are required or
what variance is acceptable — those remain numerical quality-gate-policy
decisions, calibrated from evidence, exactly as ADR-0019 already defers
metric tolerances generally.

### `R16-S06` implementation ownership, confirmed and corrected

`R16-S06` remains the correct implementation owner for everything ADR-0019
already assigned it, now including every clarification above: the
repository-owned corpus/schema; `EvidenceUnit`s and deterministic matching;
Recall@K/Precision@K/MRR/nDCG over distinct evidence units; slicing;
experiment/result schema; corpus/policy content digesting; accepted
baselines and promotion (including digest verification); comparison/report
generation; manual gate records; `ModelAssistedEvaluator`; `RagasEvaluator`
with injected model configuration; offline fakes and their tests; the
opt-in live integration test; and the dependency/regression verification
the Ragas addition requires. Its eventual commit boundary may legitimately
include, as applicable: `apps/ai`; evaluation contracts/schema/data; tests;
scripts; reports; configuration/dependency files; and documentation/
tracker changes. Nothing in this document authorises beginning any of this
implementation before this ADR itself is reviewed and accepted.

## Alternatives considered

### Rewriting ADR-0019 in place

Rejected, and the reason this document exists at all. ADR-0019 was already
committed and tagged (`phase-16-s05`) before this review happened; this
repository's immutability rule for accepted ADRs applies regardless of how
bounded or clearly-correct a change is. A narrow, explicit follow-up ADR
preserves the historical record of what was actually decided when, exactly
as this repository has done for every prior correction of this kind.

### Treating this as a full re-review of ADR-0019 requiring a new ADR-0019-equivalent draft

Rejected. Every finding was bounded and implementation-significant, not a
disagreement with ADR-0019's architecture — repository ownership, corpus
versioning, layering, slicing, baselines, and the gate model were all
explicitly confirmed unchanged. Drafting a full replacement would
misrepresent the scope of what was actually found and would obscure, not
clarify, which parts of the accepted record still stand.

### Describing adversarial document content as a `RetrievalPlanner` robustness concern

This was ADR-0019's original framing, and is corrected here — see
"Adversarial-case ownership" above. It implied document content could
corrupt planner classification, directly contradicting ADR-0018's actual
contract.

### A nullable `generated_answer`/`reference_answer` field on the V1 request, for convenience against an anticipated Stage 17.4 need

Considered and rejected — see "The Phase 16 `ModelAssistedEvaluationRequest`"
above. A field that exists only to be `null` throughout Phase 16 is a
speculative superset designed against a future evaluation mode whose exact
shape is not yet known; Stage 17.4 extending an application-owned type it
already controls is a smaller, more honest change.

### Describing Ragas's context-relevance metric as candidate-level attribution

Considered, since it would make the model-assisted signal more immediately
actionable, and rejected as overclaiming what the metric's documented
behaviour actually provides — see "Ragas context-relevance" above.

### Inventing a granular document-level permission model to populate isolation cases

Considered, since a richer permission model would make for a more
elaborate-looking security corpus, and rejected: testing access rules the
platform does not enforce would test a system that does not exist.
Granular cases become mandatory exactly when a granular permission model
is actually implemented.

### A version label alone, with no content digest, as proof of corpus/policy immutability

Considered and rejected — a label is an assertion; a canonical content
digest, computed the same way this codebase already computes
`ChunkingConfiguration`'s and `EmbeddingProfile`'s fingerprints, turns "this
version was not silently changed" into a checkable fact.

### Variant-first (per-phrasing) weighting for semantic-case aggregation

Considered, as the simpler default, and rejected: it would let a case with
many authored paraphrases dominate an aggregate score over a case with
only one phrasing, rewarding corpus-authoring volume rather than measuring
retrieval quality across distinct information needs.

### Requiring live `RetrievalPlanner` or Ragas judge calls to be byte-identical across runs

Considered, by naive extension of the deterministic-metrics requirement,
and rejected as a category error: an LLM-backed call is not the same kind
of thing as this platform's own metric arithmetic. What is actually
required — the platform's own arithmetic reproducing exactly over recorded
inputs — is preserved in full; live model determinism was never the
property that mattered.

## Consequences

### Positive

- `R16-S06` can begin implementation against a corrected, precise record,
  without an implementer having to silently resolve the contradictions and
  gaps Codex's review found.
- The `EvidenceUnit` abstraction means a chunking-strategy or extraction
  change cannot silently inflate or deflate Recall/Precision/MRR/nDCG by
  changing how many chunks happen to carry the same underlying evidence.
- Corpus and policy content digests turn "this accepted baseline's inputs
  were not silently edited" into a checkable fact.
- The corrected adversarial-case ownership keeps this platform's threat
  model consistent with ADR-0018's actual contract, rather than asserting
  a planner attack surface that does not exist.
- Injected, configuration-owned model ownership for `RagasEvaluator`, plus
  the offline/live test policy, makes the adapter genuinely testable
  without credentials, and keeps a model-assisted failure from ever
  corrupting a deterministic result.
- This repository's own immutability discipline for accepted ADRs is
  demonstrated in practice, not just stated: a genuine post-acceptance
  finding produced a new, narrow record rather than a silent rewrite.

### Negative

- Two ADRs (0019 and 0020) must now be read together to understand the
  full Phase 16 evaluation architecture, rather than one self-contained
  document — an accepted cost of this repository's immutability rule, not
  a design preference.
- Building `EvidenceUnit`, its deterministic matching, and the digest/
  lineage additions is real implementation surface for `R16-S06` beyond
  what ADR-0019 alone would have specified.
- Adding the concrete Ragas dependency introduces a substantial transitive
  dependency set, per Codex's own compatibility review, requiring a full
  repository regression and dependency-compatibility verification during
  `R16-S06`.
- The V1 `ModelAssistedEvaluationRequest`'s narrowed shape means Stage 17.4
  must extend or version an application-owned type rather than finding the
  fields it needs already present.

## Architectural invariants

- ADR-0019 remains Accepted and authoritative for everything this document
  does not explicitly name; this document does not reopen it.
- Adversarial planner-robustness cases test only what `RetrievalPlanner`
  actually receives (the question and evaluation instant); adversarial
  document content is a `Retriever`/ranking-layer concern, never described
  as capable of corrupting planner classification.
- Relevance ground truth is anchored to a repository-owned `EvidenceUnit`
  (authoritative `Document`/`DocumentFamily`/version identity plus one or
  more corpus-authored canonical text excerpts); it is never anchored to
  `ExtractedElement`, `NormalisedElement`, or `Chunk` identity.
- Recall@K, Precision@K, MRR and nDCG are computed over distinct
  `EvidenceUnit` coverage; multiple chunks covering the same `evidence_id`
  never earn duplicated credit; the exact duplicate-credit formula is
  deterministic, versioned, and recorded as evaluation configuration.
- `ModelAssistedEvaluator` → `RagasEvaluator` remains the V1 architecture;
  `RagasEvaluator` receives its evaluator-model/client as an injected,
  configuration-owned dependency, never instantiated implicitly.
- No Ragas-specific type appears anywhere outside the `RagasEvaluator`
  adapter.
- Ordinary repository tests require no credentials or paid network calls;
  a genuine Ragas-plus-provider integration test is explicit, credential-
  dependent, and opt-in only.
- A model-assisted evaluation failure produces a controlled advisory
  failure, recorded in lineage; it never erases, hides, or turns unknown
  any deterministic metric or hard-gate result.
- The Phase 16 `ModelAssistedEvaluationRequest` carries only question,
  retrieved evidence, metric identity, and evaluator configuration; it
  carries no `generated_answer` or `reference_answer` field.
- Ragas's context-relevance metric evaluates the retrieved-context set as
  an aggregate; it is never described as deterministic candidate-level
  attribution. Deterministic `EvidenceUnit` matching remains the sole
  authoritative mechanism for candidate-level relevance judgement.
- The mandatory V1 security/isolation case family tests the permission
  model the platform actually implements (workspace membership,
  cross-workspace isolation, fail-closed concealment); granular per-
  document or access-group permission cases become mandatory only once
  such a model is actually implemented.
- Every corpus version and quality-gate-policy version carries a canonical
  content digest; baseline promotion rejects or explicitly treats as a
  distinct artefact any already-baselined version number that now resolves
  to a different digest.
- Every semantic case's phrasing variants are evaluated independently but
  aggregated within their case before contributing to corpus- or slice-
  level metrics; every semantic case carries equal weight at that
  aggregation level regardless of variant count.
- Deterministic metric implementations must reproduce exactly for
  identical recorded inputs; this never implies or requires byte-identical
  output from a live `RetrievalPlanner` or model-assisted judge call.
- `R16-S06` remains the correct implementation owner for ADR-0019's
  architecture and every clarification this document adds.

## Scope boundaries

This document does not define, and leaves exactly where ADR-0019 (or later
stages) already leaves them:

- anything ADR-0019 already decided and does not appear as a clarified or
  corrected point above;
- hybrid retrieval architecture, sparse/dense fusion, or a reranker
  contract — `R16-S07`;
- calibrated evidence thresholds;
- generation evaluation's exact answer-dependent metrics or corpus
  extensions — Stage 17.4, extending the `ModelAssistedEvaluator`/
  `RagasEvaluator` adapter ADR-0019 builds;
- granular per-document or access-group permission cases — mandatory only
  once such a permission model is actually implemented;
- the exact JSON-Schema document, corpus file layout,
  `ModelAssistedEvaluator` class/module structure, `EvidenceUnit`
  duplicate-credit formula, text-matching/fuzzy-coverage algorithm,
  content-digest algorithm, or metric implementation code — `R16-S06`
  implementation work;
- how many trials, or what variance, a stochastic/model-assisted metric
  requires before a result is trusted — a quality-gate-policy decision;
- the exact package/dependency-version resolution for Ragas — `R16-S06`
  implementation and verification work, informed but not fixed by Codex's
  compatibility review.

These remain open for the stages named above, and for ADR-0019 itself, to
decide with the context this document establishes.
