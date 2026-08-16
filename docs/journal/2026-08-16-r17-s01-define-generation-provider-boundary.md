# Session Journal: R17-S01 — Define Generation Provider Boundary

## Date

2026-08-16

## Session mode

Architecture and documentation only. No application code, migrations,
contracts, prompt templates, provider adapters, or evaluation cases were
introduced; no provider was called.

## What happened

With Phase 16 retrieval engineering closed for the current engineering
phase (V3 engineering confirmation: case-first Recall@K 0.9667,
clean-upstream Recall@K 1.0000, planner/eligibility/outcome correctness
29/31, 30/31, 30/31, all 36 expected `EvidenceUnit`s surviving every
pipeline stage — EXP-0008), an independent architectural review of the
proposed Phase 17 direction was carried out before any ADR drafting began,
covering the provider-neutral generation boundary, sufficiency ownership,
the outcome/citation model, durable evidence identity, context packing,
grounding rules, prompt-injection treatment, retry semantics, generation
lineage and the Laravel/Python ownership split.

A first full draft of ADR 0023 followed, resolving several open questions
the review had deliberately left for ADR drafting: the `answer_parts[]`
representation (rejecting a separate free-text `answer` plus independent
`claims[]`), the `ANSWERED`/`QUALIFIED`/`INSUFFICIENT_EVIDENCE` outcome
taxonomy with enforced structural invariants, durable per-answer
`EvidenceSnapshot`s resolving the citation/re-extraction design constraint
deferred since 2026-07-30, and — a genuine gap the review's own notes had
not addressed — extending the existing `rc1` protocol with a new
`generation.answer` purpose rather than inventing a new Laravel-to-Python
protocol, consistent with `rc1`'s own stated extensibility (ADR-0018) and
its one prior exercise of that extension point (ADR-0021's
`retrieval.rerank`).

One bounded revision round followed, addressing five specific review
findings without reopening the accepted architecture:

1. **`INSUFFICIENT_EVIDENCE` semantics tightened** — `insufficiency_reason`
   is now explicitly bounded (may describe the evidence/request
   relationship only; must not carry procedural facts, quantities, dates,
   authority/applicability claims, or uncited advice), so it cannot become
   a second free-text answer channel.
2. **`AnswerPart` identity made application-owned** — removed the
   provider-generated `id` field from the contract examples; the model
   never invents persistent identity, Laravel assigns it at persistence.
3. **Context-budget failure typed explicitly** — introduced
   `GENERATION_CONTEXT_BUDGET_EXCEEDED` as a distinct application-level
   packing failure, structurally and semantically separate from
   `INSUFFICIENT_EVIDENCE`, and excluded from Stage 17.4's semantic
   scoring.
4. **Context-packing ownership refined** — an explicit split (Laravel:
   evidence set, `COMPARE` structure, order, policy; Python:
   provider-specific rendering and token measurement only), with failure
   handling clarified as never an open-ended negotiation.
5. **gpt-5-mini rationale simplified** — removed reasoning that a smaller
   model is a better validation stress test; replaced with the concrete,
   verifiable fact that gpt-5-mini is already ADR-0018's production
   `RetrievalPlanner` model, so this reuses an operational provider
   relationship rather than introducing a new one to evaluate.

One editorial deduplication pass was also performed, removing one genuine
instance of repeated argument (the structural-versus-semantic validation
distinction, restated in "Multi-evidence synthesis" immediately before the
section that already covers it in full) while deliberately preserving
several other repeated themes (provider neutrality, Laravel/Python
ownership) where each occurrence serves a different section's own
self-containedness.

A final pre-acceptance contradiction check confirmed ADR-0023 remains
compatible with every accepted ADR it cites, and the ADR was accepted with
no further changes requested.

## Decisions recorded

`docs/adr/0023-define-the-provider-neutral-grounded-generation-architecture-and-contract.md`
records, in its final accepted form, the provider-neutral `Generator`
boundary; OpenAI/gpt-5-mini as the initial V1 adapter only; generation
owning sufficiency judgement; the three-outcome taxonomy and its structural
invariants; `answer_parts[]` as the sole authoritative generated
representation with application-owned identity; durable, verbatim-text
`EvidenceSnapshot`s; deterministic citation-membership validation;
deterministic context-packing ownership and the
`GENERATION_CONTEXT_BUDGET_EXCEEDED` failure; hostile evidence treated as
untrusted data with no autonomous tool/retrieval capability; a bounded,
non-semantic retry taxonomy; a versioned `generation_fingerprint`; the
`rc1` `generation.answer` extension; Phase 18 streaming deferral; and Stage
17.4 evaluation extending the existing `ModelAssistedEvaluator`/
`RagasEvaluator` boundary — not duplicated here.

## Verification performed

* An independent architectural review of the proposed Phase 17 direction
  was conducted before any ADR drafting began.
* Each of the five bounded revision findings was traced against the ADR's
  own internal consistency (no free-text escape hatch outside
  `answer_parts[]`; `insufficiency_reason` cannot carry substantive
  uncited content; budget failure never scored as semantic insufficiency;
  Laravel/Python ownership boundaries hold; persistent identity is
  application-owned throughout) before acceptance.
* A final pre-acceptance contradiction check confirmed compatibility with
  Laravel/Python ownership (ADR-0002), `rc1` authentication (ADR-0018,
  extended by ADR-0021), provider-neutral boundaries (ADR-0013, ADR-0018,
  ADR-0021), canonical chunk/provenance and extraction-run immutability
  (ADR-0010, ADR-0011), audit and lineage (ADR-0006), and the evaluation
  boundary (ADR-0019, ADR-0020) — confirmed via direct grep/read against
  each cited ADR's actual text, not from memory.
* Confirmed no retrieval, planning, eligibility, fusion, reranking, or
  threshold-calibration content was reopened or altered.
* Confirmed, after acceptance, that only documentation/tracking files
  changed — no application code, migrations, contracts, or provider calls.
* Did not run `make lint` / `make test` / etc. — no application code
  changed in this session, so those checks do not apply.

## Problems or corrections

Two things were corrected during the session rather than at the end:

* An early ADR-acceptance-workflow assumption (placing the draft ADR in
  `docs/adr/gpt_drafts/`) was corrected once it was clarified that folder
  is reserved for the human's own GPT-conversation history, not a general
  drafts location; the ADR was moved to the numbered `docs/adr/` sequence
  as `0023-...` with `Status: Proposed`, matching how the numbering
  convention is actually meant to be used for an unaccepted draft.
* During the bounded revision, an initial description of the
  context-budget failure exchange as "a single round trip" was tightened
  after review: a policy-defined fallback re-proposal, where Laravel's
  configured policy permits one, is a second, bounded exchange — not an
  open-ended negotiation, but not unconditionally "single" either. Fixed
  before acceptance, not left as a known inaccuracy.

Resynchronising `IMPLEMENTATION_GUIDE.md`'s Stage 17.1 content (from a
pre-implementation stub to the completed record) shifted every subsequent
Phase 17 stage's actual line position by 69 lines. `tasks.json`'s
`guide_start_line`/`guide_end_line` (and the duplicate
`implementation_guide_reference.source_lines`) for R17-S01 through R17-S04
and the R17 phase entry were resynchronised against the actual file.
Phase 18's `guide_start_line`/`guide_end_line` references in `tasks.json`
were found to already be stale relative to `IMPLEMENTATION_GUIDE.md` before
this session's change — pre-existing drift unrelated to this session's
edit, confirmed present beforehand, and left untouched as out of scope,
consistent with how R16-S01 handled equivalent pre-existing drift in
earlier phases.

`PROJECT_JOURNEY.md` was not updated for this session: it is a
plain-language milestone narrative for non-technical readers, and this
session produced an accepted architecture decision with no new
user-visible or demonstrable capability yet — the workflow's own guidance
to skip it for "minor or purely internal sessions" was judged to apply
here. It remains the natural place to tell this story once Phase 17
actually produces a working grounded answer.

## Next steps / important takeaways

* Stage 17.2 (Build Grounded Prompt Assembly) now has a settled,
  accepted contract to implement against: the `Generator` boundary,
  `GenerationRequest`/`GenerationResult` shape, the outcome taxonomy and
  its invariants, the context-packing ownership split, and the grounding
  rules. Stage 17.2 does not need to re-derive any of this — only to build
  it.
* Stage 17.2 inherits explicit, tracked implementation obligations from
  ADR-0023: the exact `GenerationRequest`/`GenerationResult`/
  `EvidenceSnapshot`/`AnswerPart` schemas and persistence model; the exact
  evidence-token budget and its units; and the deterministic prompt
  renderer's implementation.
* Confirming gpt-5-mini's strict structured-output reliability for
  generation specifically (not merely for `RetrievalPlanner`'s narrower
  classification task) remains a blocking verification step before R17-S03
  treats it as the accepted implementation adapter.
* The pre-existing Phase 18 line-reference drift noted above remains
  unresolved and out of this session's scope; a future session touching
  Phase 18 planning should expect to resynchronise it.
