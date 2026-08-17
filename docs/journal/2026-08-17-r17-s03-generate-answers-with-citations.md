# Session Journal: R17-S03 Generate Answers with Citations

## Date

2026-08-17

## Status

Completed and bounded-live verified. R17-S04 has not begun.

## Contract audit and capability decision

The committed R17-S02 `GenerationRequest`, `GenerationResult`, outcome,
`AnswerPart`, typed context-budget failure, typed provider error and rc1
response-union shapes were retained unchanged. No contradiction with accepted
ADR-0023 was found.

The installed OpenAI SDK is 2.53.0. Its Responses API exposes strict Pydantic
parsing through `responses.parse(..., text_format=...)`. The adapter-private
schema was converted with the installed SDK's strict-schema helper and proved
closed objects, required enum/array fields, nested evidence ID arrays and a
nullable insufficiency reason. The current official gpt-5-mini profile records
structured-output support, a 400,000-token context window and 128,000 maximum
output tokens.

## Implementation

The concrete adapter uses OpenAI gpt-5-mini through the Responses API. The
current versioned prompt is `grounded-generation-v2`; the contract and adapter lineage
are `generation-result-v1` and `openai-responses-v1`. Reasoning effort is
`low`, the configured maximum output is 4,096 tokens, provider storage is off
and truncation is disabled. The complete generation fingerprint is
`40a18f357fbc864ff54781e607300c3374dd65829563fc2b334a2876de19b2f5`.

Rendering is deterministic canonical JSON. It sends the original question,
request-scoped evidence ID, exact evidence text, PRIMARY/COMPARISON side and
resolved temporal/applicability context. It deliberately excludes persistent
chunk/document/claim identity, source provenance, vectors, retrieval scores,
credentials and planner internals. Hostile instructions remain strings inside
the evidence data package. The adapter exposes no tools, browsing or function
calls.

The provider-specific strict result is deterministically mapped to the existing
provider-neutral result, then citation membership is checked against the
request. There is no structural or semantic repair. The adapter measures the
exact rendered instruction, data package and strict schema locally with the
gpt-5-mini tokenizer plus a fixed envelope allowance. It only reports fit; it
never selects, truncates or reorders evidence. An overflow remains the distinct
`GENERATION_CONTEXT_BUDGET_EXCEEDED` shape.

OpenAI SDK retries are disabled. The adapter owns a maximum of three attempts,
2/4-second exponential fallback bounded at 30 seconds with jitter, and honours
bounded Retry-After guidance. Only rate limits, timeouts, connection failures
and retryable server failures retry. Refusal, malformed typed output, contract
validation failure, QUALIFIED and INSUFFICIENT_EVIDENCE do not trigger semantic
retry. Usage normalisation records provider/model/prompt/contract/adapter,
fingerprint, token usage, latency and retry counts. Cost remains null and
explicitly unavailable.

## Provider-free verification

- focused Python adapter/generation/rc1 tests: 23 passed, one opt-in live test
  skipped;
- strict OpenAI schema conversion: passed;
- Python Ruff and formatting for changed code: passed;
- Python Mypy for changed generation/settings/tests: passed;
- Laravel grounded-generation and shared rc1 tests: 14 passed (53 assertions);
- Pint for changed Laravel files: passed;
- Compose configuration validation: passed;
- `git diff --check`: passed.

The broad Python suite was not used as acceptance evidence because its
repository convention requires mounting `/evaluation`, including protected
material intentionally unavailable to this stage. A broad diagnostic attempt
without those mounts produced expected fixture-path failures; no protected
split was mounted to manufacture a green result.

## Bounded live verification

Seven authorised fixture calls traversed the signed rc1 `generation.answer`
boundary. A preliminary authentication attempt made zero provider calls because
the test harness initially encoded the rc1 signature incorrectly; that harness
encoding was corrected without changing application authentication.

The first two provider calls completed, but the initial assertion stopped the
harness before their complete response envelopes were emitted. The first
straightforward fixture returned the expected ANSWERED outcome. The quarantine
duration fixture returned INSUFFICIENT_EVIDENCE rather than the desired
QUALIFIED outcome. Those calls were not repeated.

The five untouched fixtures were then each called once and preserved exactly:

- genuine insufficiency: INSUFFICIENT_EVIDENCE; no dismissal-process evidence;
  721 input, 253 output tokens, 3,173.65 ms, zero retries;
- multi-evidence synthesis: ANSWERED with both `ev-01` and `ev-02`; 781 input,
  196 output tokens, 3,272.96 ms, zero retries;
- hostile evidence: ANSWERED, ignored the hostile text and cited `ev-01`; 730
  input, 198 output tokens, 2,436.85 ms, zero retries;
- modality preservation: ANSWERED, preserving required witness and optional
  manager review; 734 input, 198 output tokens, 2,583.17 ms, zero retries;
- unsupported numeric request: INSUFFICIENT_EVIDENCE rather than the desired
  QUALIFIED result; 732 input, 221 output tokens, 2,597.14 ms, zero retries.

The exact emitted envelopes for those five calls remain in the task execution
record; no run artefact or benchmark was created. All results were structurally
valid and citation-safe. No rate limit or provider retry occurred.

## Review finding

The adapter and transport contract worked, including structured parsing,
natural prose, multi-evidence citations, hostile-evidence containment, modality
preservation, rc1 authentication and usage lineage. The bounded live sample
also showed that the initial `grounded-generation-v1`/gpt-5-mini profile could choose bare
INSUFFICIENT_EVIDENCE where ADR-0023 prefers useful QUALIFIED output for an
unsupported requested quantity plus a supported operational rule. That is a
semantic prompt/model behaviour finding, not a reason to weaken the typed
contract or retry to success. It requires review before the R17-S03 commit.

## Qualified-outcome boundary correction

Review confirmed there was no adapter mapping bug: the provider outcome is
mapped one-for-one into `GenerationOutcome`. The V1 prompt said to prefer
useful qualification and defined insufficiency as an inability to materially
answer, but it did not encode the two-step decision order. The provider schema
also supplied no field descriptions reinforcing that boundary. A model could
therefore treat an unsupported exact requested fact as the whole task and
choose insufficiency even when a supported operational rule was materially
responsive.

`grounded-generation-v2` now requires this general order: first determine
whether evidence materially addresses the question; only no reaches
INSUFFICIENT_EVIDENCE. If yes, complete support reaches ANSWERED and incomplete
support reaches QUALIFIED. It explicitly covers missing quantities, durations,
counts, dates, actors, thresholds and procedural details while retaining the
prohibition on invented answers. Matching descriptions were added only to the
adapter-private strict output fields. Provider, model, contract, adapter,
reasoning effort, output limit and application contracts are unchanged.

No retrieval, planner, benchmark, threshold, calibration, held-out, authority
or applicability behaviour changed.

## Grounded-generation-v2 bounded live verification

After the provider-free gate passed, five authorised fixtures each traversed
the signed rc1 `generation.answer` route once. There was no semantic retry and
no transport retry. All five outcomes matched their predeclared expectation:

* quarantine-duration regression: QUALIFIED; the answer cited `ev-01`, stated
  the supported pharmacy-advice release condition and identified the absent
  fixed duration without inventing one; 1,032 input and 273 output tokens,
  3,728.67 ms;
* lone-worker numeric regression: QUALIFIED; the answer cited `ev-01`, stated
  the supported immediate escalation rule and identified the absent numeric
  minute threshold; 1,035 input and 417 output tokens, 3,944.89 ms;
* independent fully supported control: ANSWERED; the answer cited `ev-01` and
  preserved the end-of-shift requirement; 1,028 input and 193 output tokens,
  2,259.23 ms;
* independent non-responsive-evidence control: INSUFFICIENT_EVIDENCE; it
  produced no answer parts and did not turn unrelated fire-safety evidence
  into a dismissal-appeal answer; 1,024 input and 248 output tokens,
  3,220.32 ms;
* independent missing-actor control: QUALIFIED; the answer cited `ev-01`,
  preserved the infection-control review condition and identified the absent
  authorising person or role; 1,032 input and 347 output tokens, 3,599.65 ms.

Every response was structurally valid, citation-safe and recorded the
`grounded-generation-v2` prompt plus fingerprint
`40a18f357fbc864ff54781e607300c3374dd65829563fc2b334a2876de19b2f5`.
The independent insufficiency control remained INSUFFICIENT_EVIDENCE, so this
bounded evidence does not indicate a blanket conversion of refusals into
QUALIFIED. The two exact V1 failures remain historical evidence; V2 did not
rewrite or rerun them as part of the original sample.
