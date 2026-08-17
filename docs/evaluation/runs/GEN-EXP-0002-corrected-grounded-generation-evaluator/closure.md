# GEN-EXP-0002 closure decision

## Decision

Accepted on 2026-08-17 as the evaluator-only corrected Stage 17.4 replay.

GEN-EXP-0001 remains the immutable original provider-backed generation
baseline. GEN-EXP-0002 made zero generation provider calls and reused the
byte-identical application observations from GEN-EXP-0001:

```text
SHA-256: bee2cdf0ddfda896cf23f8c3a27ec7b250db9d202180ed52b4d3ab6cf0f6c38e
Bytes:   34414
```

Only the corrected answer evaluator was executed. This was not a selective
generation rerun. The corrected result is:

```text
SHA-256: 5d460d05d2eb744bb15eda1964f35e72e1cbc49e809b8ef4aba03464fbb06ae1
```

## Result

Deterministic evaluation recorded 13/13 outcome correctness: 6/6 ANSWERED,
5/5 QUALIFIED and 2/2 INSUFFICIENT_EVIDENCE. Citation membership passed for
13/13 cases and all 11 AnswerParts were valid. There were no invented evidence
handles, over-refusals, overclaims, unsupported quantitative/date/actor/
authority/procedure inventions, internal-identifier leaks or hostile-evidence
leaks.

The corrected advisory semantic evaluator scored groundedness, factual
precision and completeness at 1.0000 with 11/11 coverage; QUALIFIED usefulness
at 1.0000 over 5/5 cases; and insufficiency correctness at 1.0000 over 2/2
cases. It recorded no evaluator failures and no unevaluable AnswerParts.

These semantic scores are advisory engineering evidence, not unseen
generalisation evidence. Generation and evaluation used the same base model,
OpenAI gpt-5-mini, so this result is not proof of universal generation quality.

## Evaluator boundary

Stage 17 uses the provider-neutral `ModelAssistedEvaluator` boundary with:

- implementation: `openai-grounded-answer-evaluator`;
- version: `v1`;
- provider/model: OpenAI `gpt-5-mini`;
- prompt: `grounded-answer-evaluator-v1`;
- representation: `generation-result-evaluation-v1`;
- harness: `generation-evaluation-v2`;
- fingerprint: `9d923db3b89472a9f832b37117a6f22b45b3553bff962a88935db6ac82d9ead7`.

Ragas remains available for its established Phase 16 context-relevance role.
GEN-EXP-0001 retains the original Ragas answer-metric behaviour as historical
evidence; Ragas is not the final Stage 17 grounded-answer evaluator.
