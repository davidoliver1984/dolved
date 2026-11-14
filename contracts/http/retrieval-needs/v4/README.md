# Extractive needs — v4

New producer: bounded-evidence-needs-v26 / structured-chat-needs-v10.
Transport envelope remains v2; the plan schema identity is evidence-needs-plan-v4.
Read compatibility includes v3; the live client/producer rejects legacy execution.
Archived v3 plans/answers are not rewritten, re-executed, or reinterpreted on read.

Every need's aspect is diagnostic model output only. A bounded context_spans list
(at most four) and question_span quote authorised user turns exactly. Context
quotations precede the requested clause, then any authority/fact/applicability/claim
source quote not already contained in a preceding part. Join with one space.
No generated connecting words or resolved noun substitutions. Earlier-turn nouns
are quoted to supply pronoun context; missing context is not invented. Calendar
arithmetic remains in validated typed fields, not prose. Compiled text is capped
at the existing 8,000-character downstream request limit.

Python checks the compiled executable plan. PHP independently validates all source
spans and compiles the same text before search, reranking, family review, coverage
and generation. Raw aspects remain only in diagnostic needs_plan. No raw aspect
can be used as a fallback for v4. Grounded quotes are provenance, not proof of
semantic completeness; omission/context correctness still needs interpretation
checking and independent assessment. Capacity, authority and citations unchanged.

Generation envelope v3 uses request_text_version=extractive-request-v1, the same
context/span/typed fields and compiled aspect. Python rejects a mismatched compiled
aspect. Historical generation envelope v2 remains readable; no stored row changes.

Historical interpretation receipt v3 adds mandatory `temporal_assessments` (one per need,
maximum three): need_id, temporal_intent_preserved (yes/no/unclear), and at most
eight references (turn_id, exact quote, role authority/fact_context/both/ambiguous).

2026-11-14 C1 retires active v3 production emission: the existing general check
emits v2 again, without temporal assessments. Historical v3 models, PHP receipt
readers and failed evidence remain available. Restored v2 is not a demonstrated
temporal safeguard. Bound-family explicit-day alternatives and answer-use are
instead described in generation-needs/v4; topic-only discovery remains C2.
The existing checker call assesses the structured temporal lanes; it receives only
turns and the executable plan, never catalogue or source expectations. The
application enforces unique complete need coverage, quote grounding, closed enums
and consistency (accepted cannot include no/unclear). These checks establish
structure and provenance, not semantic correctness. Existing citation verification,
unverifiable-flag disposition, two planner attempts and 4096 checker token limit
remain unchanged. Laravel retains bounded assessments in usage diagnostics; it
does not alter authority or timeline resolution. V1/v2 receipts remain readable.
