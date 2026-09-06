# R01 — minimum production slice contract

Date: 2026-09-05. **Accepted by David on 2026-09-06, including the three review amendments.** Decision: [ADR-0038](../../adr/0038-define-bounded-evidence-needs-for-conversational-retrieval.md). Baseline: [R00](repair-baseline/README.md). Planning HEAD `f1c7e69f3798558ddeef3e1f43aa6df2d0dacc9a` remains local and unpushed. This is not an implementation or live-quality claim.

**Dated implementation-slice amendment — 2026-09-06:** David approved extending R01P from two needs/three lanes to two needs/**four lanes**, so two independently requested comparisons remain intact. This supersedes the three-lane R01P references below for subsequent execution only; the broader ADR ceiling is unchanged. K=5, two needs, production authority/tenancy, models and existing thresholds are unchanged. The revised conservative envelope is recorded in `r01p/live-envelope.md`; prior results/manifests remain historical evidence. No wider R02+ expansion or commit authority is implied.

Review corrections: 2026-09-06. Three bounded clarifications below address the architectural review: explicit transition-pair exception to ADR-0022 Decision 3, descriptive-only supporting edges, and derived aspect status with validated references to authoritative answer fields. David accepted these amendments on 2026-09-06; no independent re-review or completed implementation is claimed.

## Accepted decision

Keep the production stack. Replace question-wide semantic shortcuts with a small typed list of evidence needs, interpreted by the existing model and grounded/authorised by the application. First prove **one comparison plus a current supporting-policy need** through the real conversation path. Do not build the whole redesign before that test.

Implement the minimum slice, its boundaries and failure behaviour below; retain R02–R05 for the remaining implementation. Acceptance alone does not activate a new runtime profile. No full evaluation is authorised by this acceptance.

## 1. Shared interpretation contract (R01.1–R01.3)

The proposed [evidence-needs-plan-v1 schema](minimum-slice-plan.schema.json) and examples are documentation assets, not active HTTP contracts. All fields are strict; unknown fields fail. The shared JSON Schema handles shape; **the following semantic invariants are mandatory PHP/Python contract tests**, not assertions that a schema can judge language. `decision=plan` requires one to three needs and null clarification; `decision=clarify` requires no needs and a nonempty question. Authority `current` alone permits null value/source and `none` precision. Other authority references require nonempty grounded source/value; exact dates use ISO day syntax, months YYYY-MM, years YYYY, explicit intervals two ISO dates separated by `/`, and named references retain their wording. Calendar validity, ordering and quote-supported precision are checked after shape validation. A fact reference uses the same date/precision rules and never changes the authority field implicitly.

Each need contains `need_id`, `aspect`, `central`, `question_span`, `operation`, `authority`, `fact_reference`, `applicability`, `supports_need_id`. IDs are answer-local `n1`–`n3`, assigned in question order, unique. A supporting edge references another need, never itself; cycles fail. Every need must remain visible in coverage, including missing/ambiguous ones. At least one need is central. Source quotes must be exact substrings of a supplied, authorised user turn; `turn_id` must belong to this conversation. Document text and system messages cannot establish a user instruction or applicability reference. Repeated substrings are acceptable only when their relevant interpretation is identical; otherwise clarify.

**Limits:** three needs, one retrieval query per need, at most two lanes per need / six overall; one query embedding per distinct need (batch safely where supported), one production search per lane, one rerank per nonempty lane. No recursive decomposition or automatic agent/tool loop. A plan with unrepresented requested aspects or fan-out overflow returns clarification before retrieval, never silently truncates. R01P implements two needs, maximum three lanes (comparison pair plus current support); other shapes remain on their explicitly versioned existing path, not falsely declared repaired.

Authority kinds:

| Kind | Meaning / required grounding | Application action |
| --- | --- | --- |
| current | Current authority at the application evaluation time; no date invented | Existing current timeline resolution |
| date | Exact user authority date, day precision | Existing valid-at resolution |
| period | User authority month/year or explicit interval, preserved precision | Resolve whole period; clarify if material authority changes prevent a single requested answer |
| previous | Relative historical version reference, quote required | Existing predecessor/history resolution per relevant family |
| historical_reference | A named historical policy/version reference, quote required | Existing authorised historical-reference resolution; ambiguous references clarify |
| transition_period | “Changed during June 2026”, month precision, no day | Find actual timeline transitions within the interval per relevant family; PRIMARY is the actual after state and COMPARISON the before state, under the narrow accepted exception to ADR-0022 Decision 3 below |
| transition_boundary | User explicitly names a transition date | Resolve actual adjacent authority states; PRIMARY is the after state and COMPARISON the before state under the same accepted exception, not necessarily today's current version |

Comparison uses current versus requested historical/date state, or the actual adjacent transition pair. `current` with operation `compare` and no historical reference is invalid; an unqualified “previous versus now” uses `previous`. Multiple material transitions in a relevant family that cannot fit one requested pair require clarification (no choice based on whichever date retrieves best). No transitions yields an explicit absence of comparable change; never fabricates a pair. An authority period is not silently converted to its first day. An ambiguous date such as `06/07/26` requires known user convention or clarification.

**Explicit accepted amendment:** ADR-0022 Decision 3 requires PRIMARY=CURRENT for V1. ADR-0038 supersedes that clause **only for `transition_period` and `transition_boundary` under the new needs contract**: PRIMARY is the actually attained after-transition version and COMPARISON its before-transition version in the same family. Both may now be historical. A later September version must not replace the after side of a June transition; both sides retain their actual authority dates in evidence and presentation. Ordinary previous/date/named-history comparisons and the legacy V1 path keep PRIMARY=CURRENT. Existing authorisation, governance and timeline validity checks still apply to both sides. Missing sides, multiple unresolved transitions and unknown user intent retain the stated absence/clarification behaviour; no silent current-version substitution. Required regression: with March, June and September attained transitions, a June comparison uses the June-adjacent pair, while an ordinary current-versus-previous comparison uses September versus its predecessor.

`fact_reference` is separate: “Was my training completed by 15 December?” dates an event/record, not necessarily the policy in force. Keep current policy scope unless a separate authority reference is requested. “Our training policy on 15 December 2031” is an authority-date policy need, not a personal-completion request. Schema-valid but unsupported dates are rejected; deterministic checks verify spans, precision and supported date conversion, not broad regex inference over the question. No `0606-06-06` special case.

**Locations:** `generic` does not add a location filter; it does not remove existing authenticated scope or bypass ambiguity caused by genuinely different local policies. `named` and `relational` references carry user quotes; Laravel resolves them against the authorised workspace registry, explicit aliases and established conversation referents. No model-supplied database IDs. Do not build a global alias word list. Normalisation may handle case/whitespace, not strip meaningful entity words. Fuzzy candidates may suggest authorised names in a short clarification; fuzzy score/confidence never selects applicability. Colliding aliases, unknown genuine names, siblings and unresolved “there” clarify. Ancestor/descendant compatibility uses existing rules. A clarification reply is reinterpreted with authorised conversation turns, never cross-conversation cached scope. Foreign names are concealed under the same unresolved response.

## 2. Production resolution and evidence selection (R01.4)

Call path stays `OrchestrateConversationRun` → existing authorised context/planning boundary → Laravel `EligibilityResolver` per need → `RetrieveWorkspaceEvidence` / `RetrievalClient` → production dense/SPLADE/Qdrant/RRF/reranker → final scoped evidence → production generation and persistence. No evaluator-side eligibility, local cosine search or expected-result branches.

Laravel attaches `need_id`, lane and family/version identity. Distinct-version pairing is required **within each comparison need and family**, never across every document answering the question. Current-only support can therefore survive in a separate need. Current support does not substitute for a missing historical side. Recheck workspace, active generation, approval, timeline and applicability at final assembly, exactly as for the existing path.

`supports_need_id` is **descriptive metadata only in this slice**: retain the validated edge in the plan/diagnostic record, but do not use it to change eligibility, applicability, pairing, query dispatch, budget priority, answer ordering, coverage or outcome. Do not pass the edge as an instruction to generation. Thus n2 may describe support for n1 while remaining independently eligible and answerable even when n1 has no historical evidence. Existing edge validation rejects nonexistent/self/cyclic references; no dependency execution is implied. A future behavioural use requires an explicit decision, not an implementer inference. Required regression: adding/removing a valid descriptive edge leaves resolved scopes, selected evidence and generation input unchanged (apart from retained diagnostic plan metadata).

Keep current candidate/threshold parameters and final evidence K=5 for the slice; do not tune them to this example. Allocate one valid pair to a comparison need and one evidence unit to an answerable current-support need before assigning remaining slots by existing rank with stable ID tie-breaking. Treat comparison pairs as atomic units; retain family/side mappings even if content is deduplicated. Verify pairs **after** all truncation. If more families or aspects need evidence than fit, record missing coverage and qualify; never imply the omitted family was compared. A covered need requires substantive support, not just allocation of a slot. This allocation is an explicit selection-contract change under this proposal, not a threshold calibration claim.

Missing history cannot produce a comparison assertion; an answer may still qualify with independently supported current guidance. If the question genuinely needs a user choice to select a family/timeline, clarify instead of guessing. Do not ask for a selector merely because the existing code cannot carry two needs.

## 3. Sufficiency, generation and visible answers (R01.5–R01.6)

Preserve ADR-0023's **generation-owned sufficiency, no separate live LLM sufficiency judge**. The existing single generation call sees validated needs, actual selected content, family/version provenance and mechanical coverage gaps. It writes the supported answer and unsupported aspects and links those existing fields to the requested needs. Titles/headings are relevance context, not proof of record status or personal completion. Policy obligations cannot prove an individual's actions or local feasibility.

Proposed response extension: the provider emits `aspect_links`, exactly one entry per requested need: `{need_id, answer_part_indices, unsupported_aspect_indices}`. Indices are zero-based references into that same response's authoritative `answer_parts` and `unsupported_aspects`. Links contain **no status, factual text, reason or separate evidence-ID list**. The provider must not emit `aspect_results`; it is a Laravel-derived projection only. `answer_parts` remain the sole substantive answer representation, and `unsupported_aspects` remain the existing limited description of gaps. Evidence references are derived from the cited answer parts, not generated again.

Before persistence, Python's producer validation and Laravel's independent consumer validation require: exact need-ID coverage with no duplicate/unknown IDs; integer, in-range indices with no duplicates within each list; at least one reference for each need; and every answer part and unsupported-aspect entry referenced by at least one need. A single part may support multiple needs. Laravel then derives the following status, without accepting a separately model-reported aspect status:

| Referenced answer parts | Referenced unsupported aspects | Derived status |
| --- | --- | --- |
| Nonempty | Empty | supported |
| Nonempty | Nonempty | partial |
| Empty | Nonempty | unsupported |
| Empty | Empty | Invalid output; reject |

The provider's existing first-class outcome is retained, but must agree with both ADR-0023's existing array invariants and this projection: `answered` requires every need supported; `qualified` requires at least one supported/partial need and at least one partial/unsupported need; `insufficient_evidence` requires all needs unsupported and the existing empty-answer/nonempty-gap/non-null-reason shape. Contradictions fail typed output validation before persistence, never silently relabel or repair the answer. The existing citation membership and duplicate-ID checks remain. Clarification remains the controlled retrieval outcome.

For example, n1 linking part 0 and gap 0 derives `partial`, never `supported`; a claimed `answered` outcome is rejected. An unmapped gap, unknown need, out-of-range index or provider-supplied `aspect_results` is rejected. `qualified` with n1 mapped only to gap 0 and n2 mapped only to part 0 is valid, as is `insufficient_evidence` with every need mapped only to its gap. These are required shared positive/negative producer-consumer fixtures for R01P. The checks prevent contradictory structural summaries; they do not prove that text was semantically linked to the right need or entails a claim. That remains the generation judgement and the slice's answer/citation inspection, not a new deterministic language oracle.

If a **central fact** is unsupported, the first visible answer part states the limitation, then supported guidance follows. Unsupported limitations do not receive invented citations. Persist and render this text, not merely `unsupported_aspects`. Membership and coverage checks are deterministic; substantive entailment remains fallible model assessment and must be inspected in the slice. No rule claims that schema validity or a citation ID proves truth. R01P manually examines every answer/cited passage, provider-free after execution; no additional paid judge is needed. A later model judge remains evaluation-only under R05's accepted policy.

A provider refusal, invalid output or service error preserves existing typed failure behaviour, not a made-up insufficiency answer. Schema-valid but ungrounded interpretation clarifies and records a diagnostic reason; no semantic self-repair loop in this slice. Existing transport retry rules remain bounded and accounted; a retry is not an invitation to change the intended interpretation. Retire the demonstrated personal-record regex and blanket comparison filtering on the new contract path, not layer compensating regexes over them.

## 4. Versioning, migration and rollback (R01.7–R01.8)

Propose a versioned rc1 purpose `retrieval.plan-needs` with `evidence-needs-plan-v1` request/response schema identities, reusing rc1 authentication, tracing and existing planner adapter, not a new orchestration service. Existing `retrieval.plan` remains pinned for old runs during migration. The new purpose must be implemented in both producer/consumer registries and shared fixtures together; clients never silently fall back after an invalid new-contract response.

Version generation assembly/output to carry need coverage (proposed `generation-result-v2` and matching request schema); retain old snapshot deserialisation. Bump planner/generation adapter and prompt identities and fingerprints when their actual schemas/prompts change. Final hashes belong to implemented files, not invented here. No embedding or reranker provider/model change; no re-indexing. Changed evidence selection receives a distinct execution/configuration identity and no false comparability with old S04 scores.

Enable the new profile for the isolated evaluation workspace first through ordinary production configuration. No case-ID or exact-question routing. In-flight runs pin their original profile; no plan conversion during execution. Rollback disables the new profile for future runs, retains old readers and all new evidence; interrupted work follows the existing run lifecycle rather than silently replaying providers. Broader activation waits for the slice decision. New-contract unsupported shapes produce honest clarification in the slice, not a hidden degraded answer.

Accepted ADRs remain unchanged. ADR-0038 is one coherent proposed extension, not a new full repair phase. The R00 guide clarification was accepted with this contract; no retrospective phase completion is implied.

## 5. Worked examples (not frozen-population edits)

Machine-readable representative outputs are in [examples](minimum-slice-examples.json); only supplied user turns are execution inputs. These examples express intended semantics, not observed model output.

| Free-text input | Intended plan / behaviour |
| --- | --- |
| “What changed in the ROPA, and what does current policy require for employment records?” | Comparison need plus current-policy need. One-version current support is eligible independently. |
| “What changed during June 2026?” | Transition-period reference, not June 1 or a guessed day. Actual relevant timeline ambiguity triggers a short question. |
| “Had I completed training by 15 December 2031?” | Fact date separate from current authority. Requirements alone cannot establish completion. |
| “What do we do about a new workplace hazard?” | Generic workplace, no invented registry location. Apply authorised scope and real applicability rules. |
| “Which policy applies at Riverside?” with two authorised matching locations | Named reference; Laravel returns ambiguity, no fuzzy silent selection. Ask which Riverside. |
| Reply “The outreach site” to that clarification | Use only this conversation's authorised candidates; resolve a unique referent or ask again. No global context memory. |

Dates/entities in examples are semantic fixtures only. They do not add or change frozen engineering answers or evidence. Future regression assertions must include unfamiliar names and wording, not just these strings.

## 6. Build versus adopt (R01.9)

Primary documentation checked 2026-09-05; integration effort below is an engineering estimate, not a vendor claim.

| Option | Fit and incremental work | Calls, latency, data and failure |
| --- | --- | --- |
| **Existing OpenAI structured output + existing Pydantic contracts (recommended)** | Already integrated. Add needs/coverage schema and Laravel resolution; no framework migration. | One interpretation and one generation call for an answerable slice question, plus bounded retrieval providers. Same recipients as today. Schema shape is constrained, meaning can still be wrong; ground/clarify and measure. |
| Pydantic AI native output | Supplies typed output and validation facilities; does not supply Dolved's authority, applicability or evidence pairing. Estimated additional 0.5–1.5 engineering days to integrate/test profiles, failure and retry ownership, before domain work. | No inherent reduction in needed model calls. Validation retries can add calls/latency unless explicitly bounded. Would keep the same direct provider and leave optional telemetry disabled; no new recipient is approved. |
| LangGraph | Supports explicit workflow/state orchestration; existing Laravel already owns that lifecycle. Estimated 1–3 additional engineering days for parity/spike, with no demonstrated benefit to this fixed slice. | Call count depends on graph, not package. Checkpoint/tracing integration adds another lifecycle concern; no LangSmith/external trace export is authorised. Language/entity mistakes still require the same contract. |

OpenAI documents both schema enforcement and remaining semantic mistakes: [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs). Pydantic AI documents native-schema output and validation/retry behaviour: [Output](https://pydantic.dev/docs/ai/core-concepts/output/). LangGraph describes workflow orchestration/durable execution: [overview](https://docs.langchain.com/oss/python/langgraph/overview). These capabilities do not establish comparative retrieval accuracy for Dolved.

Illustrative **OpenAI-only** nominal slice cost at 8,000 input + 2,000 output tokens per question is USD 0.006 using the currently published GPT-5-mini rates (USD 0.25/M input, USD 2/M output): [model pricing](https://developers.openai.com/api/docs/models/gpt-5-mini). Eight questions would be USD 0.048 before Voyage, contextualisation and retries. This is not a quote or a complete run envelope. R01P records actual native/outer retry ceilings, extra contextualisation if used, maximum tokens, elapsed limit and Voyage costs before dispatch; it must remain inside the plan's USD 5 experiment / USD 30 cumulative safeguards. No call is authorised merely by this illustration.

## 7. R01P boundary and go/hold decision

Only after acceptance: implement two needs/three lanes, real per-need eligibility, pair-preserving evidence selection and limitation-first persisted generation. Expected production files are planner models/adapter, rc1 schemas/fixtures, Laravel plan consumer/resolver/retrieval assembly, generation models/assembly and their focused tests. Do not rebuild Qdrant, add agents, change thresholds or rewrite unrelated application code.

Preselect 6–8 utterances before seeing output: the known mixed regression; a different family combination; reordered/paraphrased mixed wording; comparison with missing history; current support genuinely missing; wrong-scope/authority negative; and, if available within the slice, a clarification/reply pair. Record exact IDs/turns and expected source coverage at R01P.1 before implementation/dispatch. Use existing engineering material when it covers the category; otherwise label supplemental synthetic examples and expose the gap. No claim of held-out independence. This contract does not pretend those exact inputs have already been selected.

Prerequisites: real producer/consumer schemas, HTTP-transport fakes, actual Laravel timelines/applicability/search/selection, no expected values in execution, final pair after cap, citation membership and all controlled outcomes persisted. Full 148-case compatibility rehearsal belongs later. Then one live slice under standing permission, all cases reported. **Expand only if** correct authority, current support retention, supported claims/citations and visible missing-evidence limitations are demonstrated; any security/authority/unsupported-claim defect holds expansion. David reviews the evidence and remaining effort, not another automatic audit loop.

## 8. Effort checkpoint

### 2026-09-06 — approved bounded subject/ownership correction

David approved trying the bounded correction following controls 0013: bind the requested document before version selection; separate tenant ownership from organisational location. Implement in the existing needs-planner call, not a new judge or evaluation-side resolver. Laravel supplies only the current authorised workspace's live family IDs/titles, capped at 500 entries / 40,000 JSON bytes; user turns remain capped at 20,000 JSON bytes. Overflow fails closed without provider dispatch. Titles are untrusted metadata, never authority or answer evidence.

The versioned `evidence-needs-plan-v2` / `bounded-evidence-needs-v8` interpretation returns one subject binding per need: matched catalogue identities, genuinely ambiguous alternatives, unavailable named subject, or broad topic without a specific document. PHP and Python validate complete binding coverage, supplied identities and grounded external-ownership spans. Laravel filters matched families **before** temporal/version eligibility and rechecks them after retrieval. Missing history remains a comparison gap; another paired policy cannot substitute. External-tenant ownership is an unavailable need before location resolution, without widening scope or suppressing independent support. A genuine document ambiguity requests clarification. Model interpretation remains fallible; live controls are required, not assumed passed.

No additional model call, K change, threshold change, index rebuild or provider change. Execution pin advances to `bounded-needs-slice-v4`; old v1 wire files remain historical. First retest the two failures, then repeat all eight unchanged inputs on the same code if they pass. No commit/push or wider repair expansion is implied by this approval.

Provisional focused engineering estimates (including tests, excluding review waiting): R01P 1–2 days; remaining R02 1–2; R03 1–2; R04 0.5–1; R05 1–2; R06 0.5–1; R07–R08 0.5–1. Total after acceptance **5.5–11 days**, conditional on the slice and no new infrastructure defect. These are not elapsed delivery promises or accuracy probabilities. The first investment decision is after R01P, not after spending the whole range. Stop and revise the remaining estimate if the slice exceeds two focused days. Actual wall-clock/billable effort has not been instrumented for R00/R01; record this task as one preparation session, not fabricated hours. Replace estimates with measured slice experience before expansion.
