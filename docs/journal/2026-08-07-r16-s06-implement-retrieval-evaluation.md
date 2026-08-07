# Session Journal: R16-S06 — Implement Retrieval Evaluation

## Date

2026-08-07

## Session mode

Implementation and verification against accepted ADR-0019 and ADR-0020.

## What happened

The session implemented the repository-owned retrieval-evaluation capability
without extending into hybrid retrieval, reranking, answer generation or Phase
17. The work established immutable application contracts and shared JSON
Schemas, a synthetic versioned corpus, source-anchored `EvidenceUnit` matching,
deterministic metrics, sliced/case-first aggregation, experiment lineage,
baseline governance and an isolated model-assisted evaluation boundary.

The corpus was expanded during review from a useful initial set to the complete
mandatory V1 surface. Its final 23 cases and 25 variants cover current,
historical and comparison retrieval; scheduled, late-approved, withdrawn,
never-authoritative, authority-gap and predecessor cases; universal, site,
region-descendant, alias and ambiguous applicability; membership,
cross-workspace concealment and empty outcomes; prose, tables, multi-evidence,
paraphrases, synonyms and adversarial inputs.

Ragas 0.4.3 is the first concrete model-assisted adapter. Its types remain
inside `RagasEvaluator`; callers use application-owned request/results and
inject the evaluator model/client. Context relevance is advisory. Ordinary
tests use a deterministic fake and the real provider test is opt-in.

The implementation was committed first as
`bcd04346eb3e662bcf79279e589fe1a1ce2063d5`. Only then was the accepted result
generated, preserving truthful repository-commit lineage. Experiment execution,
manual gate, promotion and comparison were recorded as separate artefacts. The
accepted experiment is `retrieval-v1-offline-baseline`; its corpus digest is
`d7c44d45780dc327870458224c995f71fa1ad98117706f489164f48999665ba0` and its
policy digest is
`f362010a8cc5239e8ce36759b5fa8eee2d3b5d22b69717ec8a2c199acb80b83f`.

David Oliver recorded an `ACCEPTED` manual gate and deliberately promoted that
exact result as the initial baseline. The comparison report is intentionally a
self-comparison: it establishes the zero-delta reference future candidates will
be assessed against; it does not claim the baseline is universally optimal.

## Verification performed

* All lint, formatting and type-check commands passed.
* Frontend: 26 tests passed.
* Laravel: 177 tests and 736 assertions passed.
* Python: 229 tests passed; the live Ragas and Voyage tests were skipped because
  their explicit credentials/enable flags were absent.
* Focused evaluation: 20 tests passed; one opt-in live Ragas test skipped.
* The Next.js production build passed with `NODE_ENV=production`.
* Docker Compose configuration and all health-checked services passed.
* The offline evaluation completed 23 cases/25 variants across 33 slices with no
  hard failures and 25 controlled advisory evaluator results.
* Promotion, manual-gate and comparison commands completed independently; the
  comparison gate passed.
* The accepted result's `repository_commit` was checked byte-for-byte against
  the immutable implementation commit before the closure artefacts were added.
* JSON Schemas, source excerpts, corpus-family coverage, digests and Git diff
  whitespace checks passed.

## Problems and corrections

Ragas's `scikit-network` dependency has no Python 3.14 ARM wheel. A compiler-only
dependency stage was insufficient for an existing Compose venv that needed to
refresh from the new lockfile, so the development image was given the required
build toolchain and the shared volume was synchronised through a one-off
container before rerunning the full gate.

The initial metric implementation correctly detected combined multi-chunk
coverage for recall but did not award its first ranking credit when the prefix
became complete. Comparison ground truth also did not initially carry explicit
side identity. Final review caught both: prefix coverage now determines credit,
`EvidenceUnit` carries `PRIMARY`/`COMPARISON`, and per-side metrics are preserved.

An initial Next.js build was invoked inside the development service's
non-production `NODE_ENV` and failed during global-error prerendering. The same
build passed when rerun with the correct production build environment; no
frontend code was changed.

## Important takeaways

* A baseline is governance evidence, not merely a successful experiment run.
* Ground truth belongs to stable source meaning, not incidental pipeline IDs.
* Comparison sides and multi-chunk evidence need explicit metric semantics.
* Deterministic invariants and advisory model judgements remain separate.
* Ragas is a replaceable adapter; the repository owns evaluation and release
  decisions.
* R16-S07 is next, but no hybrid retrieval or reranking work began here.
