# Session Journal: R17-S04 Add Answer Evaluation

## Date

2026-08-17

## Status

Completed. Phase 17 is closed; R18-S01 is next and has not begun.

## What changed

The provider-neutral `ModelAssistedEvaluator` boundary was extended with
generation-result context and per-metric observations. Stage 17 evaluation now
owns a versioned 13-case synthetic population, deterministic outcome/citation/
leakage checks, a repository-owned grounded-answer evaluator, and deterministic
Markdown/HTML reporting with explicit metric denominators.

GEN-EXP-0001 is preserved as the immutable original provider-backed baseline.
Its Ragas answer-evaluation behaviour exposed an incomplete representation of
QUALIFIED results and evidence/citation/side context, denominator corruption
when metric calls failed, and demonstrated false negatives on supported
answers. Evaluator disagreement was therefore not classified automatically as
a generation defect.

GEN-EXP-0002 reused GEN-EXP-0001's byte-identical application observations
(`bee2cdf0ddfda896cf23f8c3a27ec7b250db9d202180ed52b4d3ab6cf0f6c38e`,
34,414 bytes), made zero generation calls, and executed only the corrected
evaluator. It is not a selective generation rerun.

## Accepted evaluator boundary

Ragas remains valid for the established Phase 16 context-relevance role. Stage
17 answer semantics use `openai-grounded-answer-evaluator/v1`, OpenAI
`gpt-5-mini`, prompt `grounded-answer-evaluator-v1`, representation
`generation-result-evaluation-v1`, harness `generation-evaluation-v2`, and
fingerprint
`9d923db3b89472a9f832b37117a6f22b45b3553bff962a88935db6ac82d9ead7`.

Reports preserve total, successfully scored, evaluator-failed and unevaluable
AnswerParts. Every semantic metric separately preserves eligible, scored,
failed and unevaluable counts, coverage, and a mean over successful scores.
Unavailable provider/evaluator cost remains null.

## Accepted result

The deterministic boundary recorded 13/13 correct outcomes (6 ANSWERED, 5
QUALIFIED, 2 INSUFFICIENT_EVIDENCE), 13/13 citation membership, 11/11 valid
AnswerParts, and no over-refusal, overclaiming, invented evidence handles,
unsupported fact invention, hostile-evidence leakage or internal-identifier
leakage.

The corrected advisory semantic replay recorded 1.0000 groundedness, factual
precision and completeness over 11/11 eligible AnswerParts, 1.0000 QUALIFIED
usefulness over 5/5 cases and 1.0000 insufficiency correctness over 2/2 cases,
with no evaluator failures or unevaluable AnswerParts. Because generation and
evaluation use the same base model, these scores remain advisory engineering
evidence rather than proof of universal generation quality.

## Phase boundary

ADR-0023 remains authoritative and no new ADR was required. Phase 17 now
connects authorised retrieved evidence to deterministic GenerationRequest
assembly, a provider-neutral Generator, the OpenAI adapter, typed grounded
outcomes, citation-bound natural AnswerParts, Laravel validation and durable
answer/evidence lineage, and bounded answer-quality evaluation. Phase 18
implementation did not begin.

## Verification

- focused model-assisted, generation-evaluation and OpenAI evaluator tests:
  19 passed, one opt-in live test skipped;
- deterministic report regeneration test: passed without provider calls;
- Python 3.14 Ruff lint and formatting: passed;
- Mypy for the eight changed evaluation/script modules: passed;
- Python 3.14 compile verification: passed;
- GEN-EXP-0001 and GEN-EXP-0002 persisted checksums: passed;
- JSON/tracker validation, documentation-reference checks and
  `git diff --check`: passed.

No Laravel implementation changed, so no Laravel suite was required for this
closure. No generator or evaluator provider was called during verification.

## Artefact storage

The largest new run artefact is 78,045 bytes; no file approaches a hosting
limit. Each run contains one progressive checkpoint file. Its canonical JSON
is byte-for-byte represented by the authoritative `result.json` observations
array. Together the two checkpoints are 119,398 bytes. They are retained as
small bounded recovery evidence; there are no per-case checkpoint directories
or large duplicated observation sets.
