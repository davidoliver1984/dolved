# Generation field-level diagnostic — 2026-09-07

R28-S04 / R01P remain in progress. **Diagnostic step completed; no behavioural fix or post-fix run claimed.**

## Verified starting state

HEAD `6fdc2981732773ff08be8e3b5c1f505112501c87`; runtime-source aggregate `c2ba8af7c305e9ca69b245d6d2b595ef3b23728f31fc656fa56f180584577de2`; prior diagnostic-capture/topical-binding/decomposition changes present and uncommitted. No unexpected source drift. All 33 unrelated untracked files remain unchanged.

## Actual failure origin and change

The premise that the recorded category originated in Laravel's `ValidateGenerationResult` was incorrect. Python's `OpenAIGenerator._generate()` catches output adaptation/validation errors and returns a `provider_error` envelope; Laravel's `GenerationClient` raises `GenerationProviderException`, recorded as `provider_unavailable`. Laravel's own `ValidateGenerationResult` instead raises `GenerationException`, mapped to `generation_contract_invalid`. The d04 detached-polarity guard is in Python `GenerationNeedsResult.validate_shape()`.

Added bounded static rule/field capture at that Python validation catch, an optional typed `validation` object in the internal error response, and allowlisted propagation through Laravel. Laravel's separate result/coverage exceptions now also retain static diagnostics, including the underlying coverage exception rather than only its wrapper. No exception text, generated prose, credentials, headers or raw response is persisted. Unknown messages become generic diagnostics; field/code pairs from the internal service are independently allowlisted in Laravel. Existing acceptance predicates, retry settings, provider-visible output schema, prompts, models, thresholds and indexes are unchanged. This is an additive diagnostic contract change, not a new generation behaviour/profile.

Known mapped rules include detached polarity, citation membership/ownership/duplicates, answer shape, outcome invariants, need identity/coverage, index range/list validity, empty needs and unmapped content. Schema failures not matching a known static predicate remain explicitly generic; this is not a promise to identify every future validation condition. The diagnostic helper never uses arbitrary exception text as persisted content.

## Provider-free verification

- **228 Python tests passed**: evidence-needs, needs provider schema, generation needs request/transport/output, aspect coverage, generation contract, OpenAI generation adapter and new diagnostic tests.
- **211 Laravel tests / 888 assertions passed** across conversation orchestration, temporal authority, needs client/retrieval, semantic retrieval, grounded generation and evidence/aspect contract suites.
- The three environment-gated cross-service tests were separately enabled and **all passed / 37 assertions** with a fresh provider-free recording bridge.
- Real adapter mutation tests exercise detached framing, unknown/duplicate/cross-need citations, unsupported-aspect and insufficiency-reason invariant violations, incomplete coverage, invalid indices, empty needs and unmapped parts. Safe error envelopes contain no synthetic private content.
- Real Laravel result-validator mutations exercise schema/fields, part shape, citation membership, outcome, usage and aspect-reference failures. Conversation tests prove field/code persistence from the internal envelope and sanitisation of unrecognised fields/codes.
- Ruff lint/format, Pint, scoped mypy (four production Python files), shared response JSON/schema agreement and `git diff --check` passed. No provider calls occurred during these tests.

## Four diagnostic attempts, exactly once

Diagnostic runtime aggregate: `94d90369816f5f606fd2508de76f70e7445d059e7ca053879732a02f684c4b28` (937 source files). Frozen before dispatch, verified in both mounted services, unchanged afterward. Source question-file SHA-256 `4d789472dd2e20feab3669a278643dd74f55ab29a8ec64632ba9374835157dce`; exact four-case questions-only subset SHA-256 `f4da99c64c10d2d09748565c6502000489ad0cd3f4ff65cc42b5d87edc68da3a`.

| Case | Actual earliest failure | Persisted rule | Result |
| --- | --- | --- | --- |
| e08 | Python generation validation, after evidence found | `answer_parts.text / detached_polarity` | No answer; one generation attempt; HTTP status unavailable. |
| e16 | Python generation validation, after evidence found | `answer_parts.text / detached_polarity` | No answer; one generation attempt; HTTP status unavailable. |
| e26 | Python generation validation, after evidence found | `answer_parts.text / detached_polarity` | No answer; one generation attempt; HTTP status unavailable. |
| e23 | Planner catalogue binding validation | `root / binding_coverage` | HTTP 200; finish reason `stop`; one planner attempt; retrieval/generation not reached. |

**Three share the proven d04 framing rejection. The fourth did not reproduce its former generation failure and cannot be assigned that cause.** e23's earlier generation failure still has no field-level evidence; its new planner failure is a separate named finding. No original response was reconstructed and no question was repeated.

## Why no speculative fix followed

The static guard rejects an answer part beginning with a standalone yes/no sentence/token; it does not inspect the number of needs. Its diagnostic proves the structural rejection, not that the rejected prose was semantically correct or that the guard over-triggered on a valid consolidated answer. The authorised category/field/code-only capture deliberately does not retain that prose. Narrowing acceptance from this evidence alone could admit the very detached-headline failure David previously prohibited. Accordingly, no guard relaxation, polarity rewrite/normalisation, prompt change or further paid regression was performed.

The smallest next correction to evaluate is **generation-format compliance with the existing framing contract**, not citation/eligibility relaxation. A claimed false-positive correction needs evidence distinguishing a valid connected answer from a genuinely detached headline; that distinction is not present in this metadata. Separately, e23 needs a bounded provider-free trace of the `binding_coverage` predicate before any planner change. These are proposals, not implemented or newly authorised runs. No additional controls were run because shared validation semantics were not changed.

## Usage, preservation and boundary

13 provider attempts, zero recorded retries; 84,846 known input tokens / 2,205 known output tokens; **USD 0.02132267 partial configured-rate estimate**, not total billed cost. Three failed generation calls have no retained token/cost receipts. Conservative budget charge **USD 3.00528625**, below USD 5; elapsed **72.678 seconds**, below 90 minutes. Existing 982-chunk primary index reused; no re-embedding, judge, AWS, new question selection, held-back answer key or R02 access.

Evidence: `/tmp/dolved-generation-diagnostic.IgahFO/` contains original four cases/dispatches/accounting, manifest, full observations, safe diagnostics, source freeze, questions, summary and checksum inventory. Previous third-confirmation and eight-case evidence remain byte-identical. All changes remain uncommitted on the same HEAD; no push. Stop here for David's review of the two distinct findings and the next correction boundary.
