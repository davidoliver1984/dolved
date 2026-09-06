# ADR-0038: Define bounded evidence needs for conversational retrieval

## Status

Accepted by David on 2026-09-06, including the three corrections following Claude's R01 review. Acceptance authorises the bounded R01P slice, not a full evaluation or pilot-readiness claim.

## Date

2026-09-05

## Context

The [R00 baseline](../evaluation/r28-s04/repair-baseline/README.md) demonstrates that one question-wide temporal/evidence classification can exclude current supporting policies from a comparison. Broad semantic overrides also replace valid model interpretations. These are not reasons to replace Laravel authority or the hybrid retriever.

## Decision

Adopt the [R01 minimum-slice contract](../evaluation/r28-s04/minimum-slice-contract.md), including its shared schema and examples. Bound interpretation to at most three evidence needs and six retrieval lanes; implement only two needs/three lanes in the first mixed-question slice. Overflow requires conversational clarification, never silent dropping. An LLM interprets language; deterministic application services validate grounded references and decide authority, tenancy and eligible documents. No forms or mandatory selectors.

This extends ADR-0018's “RetrievalPlan” single-plan representation and ADR-0022 Decision 1–4's question-wide temporal/reference shape to per-need semantics. It preserves ADR-0018/0022's planner-versus-EligibilityResolver ownership boundary and ADR-0017 authority timelines. It extends the input/coverage representation of ADR-0023 “Generation assembly input” and “The end-to-end flow”, while preserving “Generation owns sufficiency; no separate LLM sufficiency judge” and its outcome taxonomy. Accepted historical ADRs are not rewritten or wholly superseded.

Review clarification, 2026-09-06: this explicitly supersedes **ADR-0022 Decision 3's PRIMARY-always-CURRENT clause only for the new `transition_period` and `transition_boundary` needs**. PRIMARY is the actual after-transition attained version and COMPARISON the actual before-transition version; both may be historical. Later versions must not be substituted. All other comparison kinds and the legacy V1 path retain PRIMARY=CURRENT; authorisation and timeline validity remain unchanged. This accepted exception does not claim that ADR-0022 already permitted it.

`supports_need_id` is validated descriptive plan/diagnostic metadata only for the slice, with no effect on execution or generation input. Per-need `aspect_results` are derived by Laravel from validated `aspect_links` into the authoritative `answer_parts` and `unsupported_aspects`, never independently generated factual/status records. The contract specifies reference coverage and outcome-consistency checks that reject contradictions before persistence. No second answer text, claims array or live sufficiency-judge call is introduced; semantic correctness still requires the slice's evidence inspection.

Retain existing OpenAI/Pydantic adapters rather than add a framework. No new provider, model, index, infrastructure, external telemetry recipient or general agent loop. The small slice must demonstrate comparison plus current-only support through `OrchestrateConversationRun` and useful limitation-first qualification. Broader repair remains gated on that evidence and David's expansion decision.

## Alternatives considered

- More regexes or aliases: cannot represent independent temporal/evidence requirements and would retain the demonstrated semantic overrides.
- Mandatory UI selectors: contrary to the conversational product requirement.
- Pydantic AI native structured output: credible alternative adapter layer, but does not implement Laravel's authority or mixed evidence scope; switching adds retry/profile integration work without demonstrated slice benefit.
- LangGraph: useful orchestration primitives, but no demonstrated need to replace the existing transaction/queue/run lifecycle for a fixed bounded pipeline.
- Whole retrieval-stack replacement: does not address the earliest evidenced exclusions and has no supporting comparative result.

## Consequences

New producer/consumer schemas, bounded per-need retrieval and coverage-aware generation require tests and versioned identities. Syntax validation cannot prove semantic correctness; the live slice is an investment checkpoint, not certification. Old plans/runs remain readable, new contract activation is explicit, rollback affects new runs only, and no in-flight plan is silently reinterpreted. Policy/threshold certification remains R05–R07, not this slice.

David accepted the corrected contract after the recorded architectural review. R01P may start; broader implementation still requires the slice evidence and expansion decision.
