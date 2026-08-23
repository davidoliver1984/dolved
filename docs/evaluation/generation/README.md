# Grounded-generation evaluation

Stage 17.4 evaluates an already-assembled `GenerationRequest`; it does not run
or re-score retrieval. The repository-owned population under `populations/`
contains fictional, authored evidence packages with known answer semantics.
It does not use calibration or sealed held-out material.

`grounded-generation-v1` contains 13 semantic cases: six ANSWERED, five
QUALIFIED and two INSUFFICIENT_EVIDENCE. The five cases prefixed
`generation.regression` preserve the bounded R17-S03 live surfaces and are
development regression evidence, not unseen generalisation evidence. The
remaining cases independently exercise multi-evidence and multi-document
synthesis, CURRENT/historical/COMPARE evidence, modality, grounded absence,
counts, applicability context and hostile source instructions.

Deterministic evaluation owns schema/outcome invariants, citation membership,
evidence-handle validity, over-refusal/overclaiming counts and mechanically
detectable internal-ID or hostile-instruction leakage. It does not claim to
prove semantic entailment.

Answer-dependent semantic evaluation extends the existing application-owned
`ModelAssistedEvaluator` boundary. The immutable GEN-EXP-0001 baseline used
Ragas faithfulness per AnswerPart and Ragas factual precision/recall against
the authored reference answer. Its flattening omitted the outcome and
`unsupported_aspects`, and its metric failures reduced the reported
denominator. Those historical results remain unchanged.

The corrected `generation-evaluation-v2` boundary sends one deterministic,
versioned semantic object per case to the repository-owned
`OpenAIAnswerEvaluator`: the outcome, indexed AnswerParts and their evidence
handles, every `unsupported_aspect`, the insufficiency reason, cited evidence
with PRIMARY/COMPARISON sides, and the authored reference contract. Ragas is
retained for the established retrieval context-relevance metric; unstable
Stage 17 answer semantics are evaluated behind the same provider-neutral
`ModelAssistedEvaluator` protocol. The corrected evaluator identity is
`openai-grounded-answer-evaluator/v1`, representation
`generation-result-evaluation-v1`, prompt `grounded-answer-evaluator-v1`.

Reports distinguish total, scored, failed and unevaluable AnswerParts and do
the same for each semantic metric. Failed evaluator calls retain typed,
privacy-safe status, affected AnswerPart indices, latency, retry and token
telemetry. Unavailable provider cost remains null. Semantic scores remain
advisory; deterministic contract, citation and leakage checks remain
authoritative and are never overwritten by evaluator disagreement.

The initial evaluator uses the configured OpenAI judge model with Ragas-owned
metric prompts, never the grounded-generation system prompt. If the same base
model as generation is used, that independence limitation is recorded in run
lineage rather than concealed. Ragas internal provider call/token/cost data is
recorded as unavailable when the framework does not expose it; it is never
reported as zero.

Provider-backed runs use a distinct `GEN-EXP-NNNN-*` identity under
`docs/evaluation/runs/`. The runner refuses to overwrite an existing run and
does not support selective semantic retry. Ordinary tests use injected fakes
and make no provider calls.

The separate `prompt-injection-v1` population is security regression material,
not calibration or held-out evidence. Its three independently authored cases
exercise system-instruction disclosure, cross-tenant exfiltration requests and
attempted control-field mutation. Deterministic adapter tests prove hostile
document text is rendered in the untrusted evidence/content position rather
than the system-instruction position. Contract and orchestration tests prove
that text cannot structurally replace the authoritative workspace, required
retrieval sides, evidence handles or valid outcome vocabulary. The population
itself is reserved for the optional real-model measurement: provider-free tests
do not prove model resistance or universal prompt-injection immunity.

`make evaluation-generation-live` is an optional, non-gating live measurement.
It requires an explicit opt-in and a fresh `GEN-SEC-LIVE-*` identity, refuses a
dirty tracked worktree, binds the committed population and generation
fingerprint, and enforces ceilings of three cases, six total provider
attempts, 4,096 generation and 2,048 evaluator output tokens per case, 18,432
total output tokens and ten minutes wall time. The security measurement permits
exactly one generation attempt and one evaluator attempt per case, so all six
possible provider calls are represented in that token ceiling.
Missing credentials produce an explicit zero-call skip. The target is separate
from `make test-e2e`; normal CI and E2E execution remain provider-free.

`reevaluate_generation.py` binds the immutable GEN-EXP-0001 application
observation SHA-256, validates every recorded request against the population,
and invokes only the corrected evaluator. It cannot invoke a generator and
refuses to overwrite an existing corrected evaluation pass.
