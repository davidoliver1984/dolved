# Dolved production retrieval repair — living work plan

Created: 2026-09-05. Status: **Plan prepared; implementation has not started under this plan.**

Starting code: `35f5c2d2278b2890cd53f8fbd1a5a4c29ceebb5d`.

## Purpose and product requirement

Deliver reliable production interpretation, evidence selection and grounded answers through the normal conversational interface. Users ask questions in free text. **No mandatory forms, date pickers, location selectors or version selectors are part of this repair.** When information is missing, ask a short conversational clarification and use the reply in the existing conversation. Clarification must resolve an actual ambiguity; it must not compensate for treating generic language as a named entity.

Retain current, historical, valid-at-date, comparison, multipart and location/inheritance capabilities. Do not narrow the pilot silently. Preserve Laravel authorisation, workspace isolation, authority timelines, canonical documents, verified indexes and the production conversation path.

This is an execution breakdown supporting Phase 28, not a replacement roadmap, ADR, session journal or evaluation protocol. `tasks.json` owns official session status; accepted ADRs own architecture; the guide/journal record completed work. This file owns the repair checklist, evidence links, decisions and next action. Do not copy the checklist into another tracker.

## Current position and evidence

- Production path and indexes were independently checked in the technical review. Primary/foreign/injection point counts were 982/12/6; reverify runtime identities before execution.
- Latest five-case run exposed temporal and location interpretation failures, exclusion of current supporting evidence from a mixed comparison, and differences between partial-answer semantics and frozen outcome labels.
- The original provider bytes behind `0606-06-06` were not retained. Its origin is unresolved; accepting an unsupported date is established.
- The latest review recommends repairing the current design. It does not establish general retrieval quality or justify a wholesale rewrite.
- Frozen population: `dolved-care-v4-evaluation-population-v2`, 74 cases / 148 utterances, aggregate `adc9aa22646fc0f131ab7aa747dce91874655b95479cebc318653c3173e40f4c`.
- Latest confirmation: `/private/tmp/r28-unseen-confirmation-20260905-v1/observations.json`, SHA-256 `9befbf82b6f6ec60677af0fda1ba8481d1b534737c7be2acb45a372a72d18d3e`.
- Technical review supplied by David: `/Users/davidoliver/.codex/attachments/48556da4-79b6-4864-ab02-388f70e312f9/pasted-text.txt`. Its durable evidence capture is task R00.2; an attachment or `/tmp` path alone is not the final project record.
- Existing [production diagnostic description](production-path-diagnostic.md) describes an earlier boundary. Its dated runtime prerequisite is not proof of current index state.
- Known tracker drift: S04 says `not_started` despite diagnostic execution; S05/S06 remain `not_started`. Do not infer that the full governed S04 evaluation completed. R00.3 resolves this explicitly.

## Execution rules and ownership

David owns product/architecture decisions and commits. He has granted standing permission for routine live-provider work needed for this repair; use the operating bounds in the run register without asking again for each run or changed code identity. Codex implements the accepted section and records evidence. The product reviewer checks decisions and section deliverables. Use the repository's architectural review process once for the changed planning contract; do not create serial audit tasks for every correction or reporting detail.

- Start each task by reading this file, its current section and applicable repository instructions. Work through the section's checklist; report against its IDs.
- States: `not_started`, `in_progress`, `ready_for_review`, `complete`, `blocked`. Checked tasks require evidence; a written proposal is not implementation evidence.
- Before ending a working task, update its checklist, section state, evidence, exact code identity, remaining defect and next action here. Include the document update in the relevant authorised commit.
- Each section closes with its concrete deliverables, focused verification and one review of material findings. Scope changes go into the decision log before implementation. Existing approved decisions do not need repeated approval.
- A material blocker concerns correctness, security, missing required evidence or an unresolved contract. Formatting nits and already-disclosed historical limitations do not trigger another review cycle.
- Compare suitable existing libraries, supported provider features and bounded additional model calls when designing the interpretation/evidence layer. Do not prefer custom code solely because it already exists. A new external data recipient, paid subscription, embedding migration or framework migration requires an explicit design decision; ordinary calls to the existing providers do not. Corpus expansion, tenant-admin work and unrelated cleanup remain outside scope.
- Semantic regex overrides and growing word lists must not decide user intent. Deterministic validation may check syntax, grounded references and registered identities. Language interpretation remains bounded model work, validated before restrictive filters are applied.
- No runtime rule may reference evaluation case IDs, expected answers or exact test questions. Known failures are regression evidence. Different engineering cases are confirmation evidence, not statistically independent held-out evidence.
- Use Docker/Make verification under repository conventions. Run relevant checks while iterating and required broader checks at agreed section/session boundaries. Preserve unrelated work.
- After R01's minimum slice contract is accepted, implement and test R01P before broadening R02–R05. R01 should settle enough to test the uncertain design, not design every later detail in advance. Provider permission does not substitute for architecture acceptance.

## Section board and road to execution

| Section | Depends on | Status | Completion deliverable |
| --- | --- | --- | --- |
| R00 — establish repair baseline | — | not_started | Durable diagnosis, reproductions, phase mapping |
| R01 — agree interpretation/evidence contract | R00 | not_started | Accepted contract decision and worked examples |
| R01P — prove one production slice live | R01 | not_started | Working mixed question, varied live examples, expansion decision |
| R02 — implement interpretation and conversational resolution | R01P | not_started | Grounded plans and general entity/date handling |
| R03 — implement scoped retrieval and evidence assembly | R02 | not_started | Mixed evidence scopes survive search and selection |
| R04 — implement answerability and presentation | R03 | not_started | Supported answers, honest partial answers, useful clarification |
| R05 — verify real path and evaluation contract | R04 | not_started | Cross-service proof, bounded traces, versioned scoring and run manifests |
| R06 — live regression and different-case confirmation | R05 | not_started | Two small runs, answer inspection, expansion decision |
| R07 — full production evaluation | R06 | not_started | 74-case / 148-utterance report and pilot-readiness decision |
| R08 — close and reconcile Phase 28 | R07 | not_started | Durable evidence, residual work and truthful tracker closure |

Road: R00 → R01 → **implement and test a small live production slice R01P** → decision → R02 → R03 → R04 → R05 → **broader small live runs R06** → decision → **full live run R07** → R08.

R01P supplies the first real-provider evidence before the wider repair investment. Tests at R02–R05 and small provider experiments develop the accepted design; R06 checks broader generalisation. R07 measures the full engineering population. None alone guarantees performance for an arbitrary tenant. The target remains a usable pilot for the intended tenant; the synthetic engineering corpus alone cannot prove that tenant's corpus, configuration and everyday workflows are ready.

## R00 — establish the baseline

- [ ] R00.1 Record current code, mounted runtime hashes, effective planner/generation/reranker profiles, threshold identity and index generations. Reuse verified indexes; only re-embed if an actual representation change requires it.
- [ ] R00.2 Retain the supplied technical diagnosis and necessary bounded run records in the existing evidence structure. Verify their hashes; preserve the raw-provider-output uncertainty.
- [ ] R00.3 Reconcile diagnostic execution versus full S04 completion with `tasks.json` and the guide. Record where product repair sits under S05's approved-correction workflow without falsely completing S04/S05 or renumbering phases. Identify the required tracker/guide updates for acceptance.
- [ ] R00.4 Reproduce unsupported date acceptance, generic-location restriction, the “our training policy” override and exclusion of single-version current support from a mixed comparison. Fakes belong at provider transport; exercise actual normalization/eligibility code.
- [ ] R00.5 Record central-question presentation and scoring mismatches separately from false factual claims. Link the accepted qualified-answer contract.

**Deliverable:** concise baseline/findings record with exact reproduction commands and first failing predicates. **Gate:** each proposed correction maps to observed code behaviour; missing evidence is named. Do not repeat the full technical review.

## R01 — agree the contract before implementation

- [ ] R01.1 Define a bounded list of evidence needs per question. Each need records its requested aspect, temporal semantics, applicability reference and relationship to other needs. Choose explicit maximum fan-out and overflow/clarification behaviour; do not introduce an unrestricted agent loop.
- [ ] R01.2 Define dates as exact authority dates, authority periods, transition periods/boundaries or fact/event dates. Record supporting user/conversation spans and precision. “Changed during June” must not fabricate a transition day; resolve actual eligible timeline transitions and clarify material ambiguity.
- [ ] R01.3 Define location-reference interpretation and registry resolution. Separate a generic noun from a named/relational reference. Resolve genuine names, explicit aliases and conversational references within authorised scope. Fuzzy candidates may support a conversational clarification; they must not silently change applicability. Confidence alone is not authorisation.
- [ ] R01.4 Specify comparison lanes per evidence need/family, plus ordinary current supporting evidence. Distinct-version pairing applies to the relevant comparison, not every document supporting the question. Define transitions, valid-at comparisons, missing sides and final-budget behaviour.
- [ ] R01.5 Specify evidence sufficiency by requested aspect and central question. Retain `answered`, `qualified` and `insufficient_evidence` per accepted generation semantics. Qualified answers must lead with any unresolved central fact and must not imply approval/completion.
- [ ] R01.6 Decide the bounded method for evidence assessment using actual content and provenance. Document titles can inform relevance but cannot prove personal-record status. Any additional model call needs an explicit role, ceiling, trace and failure behaviour.
- [ ] R01.7 Produce shared schema/examples and PHP/Python producer-consumer migration notes, identity changes, backwards compatibility and rollback. Retire semantic overrides rather than layering new overrides on top.
- [ ] R01.8 Record alternatives and acceptance through the existing ADR process for the mixed-scope contract extension. Reference ADR-0018, ADR-0022 and ADR-0023 precisely; preserve accepted historical documents. Present one coherent decision for David and the architectural reviewer.
- [ ] R01.9 Produce a short build-versus-adopt comparison for uncertain interpretation and answerability work. Check current primary documentation for credible library/provider options. Explain capability fit, integration effort, data handling, latency, extra calls, expected cost and failure behaviour. Recommend the simplest suitable option and tell David why. Do not assume established tools guarantee integration quality, or introduce a framework without a demonstrated benefit.

**Deliverable:** accepted contract decision, bounded worked examples and migration plan. Examples cover a comparison plus current policy, month transition, dated personal fact, generic workplace, ambiguous genuine site and conversational clarification. **Gate:** product capabilities and semantics are settled before code; no forms or mandatory selectors.

## R01P — prove the uncertain design through one production slice

**Objective:** demonstrate a free-text question requiring a historical comparison plus current supporting evidence through the normal application, before completing the full repair. This section implements only the necessary vertical slice of R02–R04. Its accepted code is reused, not rebuilt in later sections.

- [ ] R01P.1 Fix the slice's expected user behaviour, representative sources and evidence requirements before implementation. Use an existing mixed question as a regression example; retain the original evaluation records. Agree an effort checkpoint and narrow file/contract boundary in the section record.
- [ ] R01P.2 Implement interpretation, per-need authority selection, retrieval, evidence assembly and persisted grounded answering for the slice through `OrchestrateConversationRun`. Use the existing verified indexes. No alternative evaluator-side search algorithm and no case-specific branches.
- [ ] R01P.3 Prove request/response compatibility and tenant/version invariants with focused tests. These checks are prerequisites to useful live feedback; do not finish the full 148-case rehearsal or wider framework first.
- [ ] R01P.4 Freeze a small confirmation selection before seeing its outputs: aim for 6–8 utterances spanning at least two document-family combinations, varied wording/order, a missing-support example and a scope/authority negative control. Use suitable existing engineering questions where possible. New paraphrases are explicitly supplemental development evidence, not a new frozen population or held-out claim. If a category is unavailable, disclose the coverage limitation.
- [ ] R01P.5 Run the slice with real existing providers under standing permission. Record code/configuration identity and inspect the actual answer and cited passages for every requested aspect. Persist enough intermediate evidence to diagnose exclusion, ranking and qualification failures. Keep expectations out of execution input.
- [ ] R01P.6 Report before/after regression behaviour separately from confirmation. To expand: demonstrate correct historical/current authority, inclusion of current-only support, coverage of answerable aspects, an explicit limitation where evidence is missing, persisted supported citations and intact scope boundaries. Evaluate the controls as well as success examples; a headline percentage alone is insufficient.
- [ ] R01P.7 Present the working conversation, exact remaining defects, alternatives if the design failed, measured latency/provider usage and revised estimates for R02–R07. David's next decision concerns expanding implementation, not permission for another penny-scale call. A failed slice pauses wider implementation; any revised approach is recorded with its evidence and effort impact before extension.

**Deliverable:** a working demonstrable production slice with real-provider evidence on varied questions, or a precise failed-design diagnosis, plus the remaining implementation estimate. **Gate:** broader repair proceeds only after the slice supports the design and David reviews the expansion decision. Do not promise general product reliability from this sample.

## R02 — implement interpretation and conversational resolution

- [ ] R02.1 Implement accepted shared planning schema in the real planner and Laravel consumer. Preserve raw structured interpretation and normalized fields in bounded diagnostic records.
- [ ] R02.2 Remove demonstrated broad semantic regex overrides. Add counterexamples showing that policy questions remain policy questions and personal facts retain their meaning across paraphrases.
- [ ] R02.3 Validate date interpretations against user text or authorised conversation context. Preserve precision and provenance; reject unsupported dates and route through the accepted bounded repair/clarification policy. Do not patch one malformed date.
- [ ] R02.4 Implement registry-backed reference resolution, ancestor/descendant consistency and ambiguous-reference handling. Cover generic nouns, unfamiliar names, genuine aliases, pronouns, context changes and sibling conflicts.
- [ ] R02.5 Exercise a clarification followed by a free-text answer through persisted conversation context. Ensure resolved information does not leak to another conversation or workspace.
- [ ] R02.6 Run real producer/consumer contract tests and retirement regressions. Record which shortcuts were removed and why the replacements generalise.

**Deliverable:** interpretable, grounded production plans and a working conversational clarification journey. **Gate:** all known interpretation regressions reproduced before repair now pass, alongside different synthetic wording and identities.

## R03 — scoped retrieval and evidence assembly

- [ ] R03.1 Resolve authorised document scopes per evidence need through existing timeline/applicability services. Record exclusion reasons. A fact date must not accidentally become a document-authority filter.
- [ ] R03.2 Search each accepted need through production dense/SPLADE/Qdrant/RRF/reranking. Preserve need IDs and side/family identities. Reuse equivalent work where safe and account for bounded fan-out.
- [ ] R03.3 Keep comparison-pair validity per family while allowing supporting single-version current policies. Test multiple comparison families, current-only support, missing sides and later unrelated versions.
- [ ] R03.4 Carry bounded authorised titles/headings and source provenance into reranking. Verify provider representation identities and that embedded instructions cannot act as trusted metadata.
- [ ] R03.5 Apply final evidence limits across needs with an explicit coverage policy. After truncation/deduplication, verify required pairs and identify uncovered aspects; do not silently discard the second half of a question.
- [ ] R03.6 Preserve all workspace, active-generation, approval and applicability checks at search and final selection. Never expand into another tenant to resolve an unanswered question.
- [ ] R03.7 Inspect candidate recall and ranking only after proving expected eligible material reached search. Record any remaining threshold-calibration dependency for R05, rather than choosing a score to pass a known case.

**Deliverable:** production evidence bundles with traceable need/side/family coverage. **Gate:** mixed comparison/current support and several families survive final limits; invalid pairs and cross-tenant content are rejected.

## R04 — answerability and user-visible answers

- [ ] R04.1 Assemble generation requests from validated evidence needs, coverage and actual source content. General requirements cannot establish an individual's completion or local feasibility without supporting records.
- [ ] R04.2 Enforce the accepted output contract for central and secondary aspects. An honest partial answer begins with what cannot be established, then supplies relevant supported guidance. Do not force a refusal solely to match an erroneous retrieval label.
- [ ] R04.3 Verify substantive claim/citation relationships in the chosen assessment path; distinguish this from deterministic citation-ID membership. No unsupported conclusion may become “answered” because every citation ID exists.
- [ ] R04.4 Prove persisted answers, citations, usage and failure handling through the actual application transaction. Retain latency and bounded-reporting repairs.
- [ ] R04.5 Exercise the user-facing rendering of answered, qualified, insufficient and clarification outcomes. Verify users see the central limitation in the message, not only in hidden metadata. Keep interaction free-text.

**Deliverable:** reviewable example conversations and persisted evidence-backed answers across all outcome types. **Gate:** useful partial guidance is distinguishable from an unsupported answer, and missing core evidence is obvious to the user.

## R05 — cross-service proof, scoring and execution readiness

- [ ] R05.1 Run integration journeys with the real Python and PHP services, database and Qdrant in isolation; substitute external providers only at transport. Do not replace eligibility or selection with fake expected outcomes.
- [ ] R05.2 Retain bounded original/normalized plan fields, exclusion reasons, dense/sparse/fused/reranked IDs and scores, final selections, coverage, answer and citation snapshots. Do not retain secrets or unrestricted raw exceptions.
- [ ] R05.3 Demonstrate incremental output, memory-safe final reporting and recovery from interruption using existing mechanisms. Completed calls/results must not be silently replayed. No new general orchestration framework.
- [ ] R05.4 Version scoring interpretations against accepted product semantics. Separate plan fidelity, scope correctness, expected-evidence coverage, substantive answer correctness/completeness, citation support, outcome labels and safety. Preserve frozen source expectations; document justified interpretation changes before live results.
- [ ] R05.5 Review the existing precision metric's attainable denominator and the changed reranker representation. State which metrics are valid gates, directional diagnostics or unresolved. If calibration is required, define a separate engineering calibration set and approval before using it; do not open protected calibration/held-out material.
- [ ] R05.6 Bind exact numeric acceptance criteria from accepted policy or present explicitly justified amendments to David before scoring confirmation. Do not invent new thresholds after observing results. Record remaining provisional threshold limitations.
- [ ] R05.7 Preselect two small-run manifests: known regression cases and different engineering confirmation cases, each with stated coverage and exposure history. Proposed sizes: 8 regression utterances and 16 confirmation utterances; final IDs/counts fixed here. Cover ordinary current questions, genuine/generic locations and conversational resolution, historical/valid-at/fact dates, transitions, mixed evidence needs, missing facts/partial answers, tenant isolation and injection. Overlap is allowed and disclosed; select the variant not just the case ID.
- [ ] R05.8 Select confirmation cases not used to implement this repair where available. Previous reviewers may have seen the engineering population: label that honestly. Do not claim statistical hold-out independence. If no remaining case exercises a required feature, record the gap and use a separately declared supplemental test instead of pretending coverage.
- [ ] R05.9 Prepare executable manifests/commands for R06 and the full 74-case/148-utterance R07 run. Derive calls, native/outer retries, tokens, concurrency, time and spending envelopes from the real new fan-out. Old 321-call arithmetic is not authority for a changed pipeline.
- [ ] R05.10 Exercise all 148 input shapes through production orchestration with transport fakes to prove execution compatibility, persistence and scoring. This is a plumbing proof, not evidence of language understanding or retrieval quality.
- [ ] R05.11 Run required repository boundary checks once after the integrated change stabilises. Record specific failures rather than substituting synthetic test totals for a live readiness claim.

**Deliverable:** reproducible cross-service proof, versioned scoring specification, three run manifests and their commands/envelopes. **Gate:** execution and evaluation agree on the product contract; no unresolved field, runtime or policy decision is deferred to a paid run.

## R06 — small live runs and expansion decision

- [ ] R06.1 Record the standing provider authority, exact small-run commit/profiles/manifests and combined envelope. Reuse verified 982/12/6 indexes and confirm actual runtime source/configuration identity. No new spending-approval prompt is required within the operating bounds.
- [ ] R06.2 Execute the preselected regression run once. Inspect actual answers and cited passages. Known-case success proves regression repair only.
- [ ] R06.3 If R05's predetermined regression prerequisites pass, execute the different-case confirmation once on the same commit/configuration. If they fail, preserve results and report; no automatic code change or retrying the whole sample.
- [ ] R06.4 Report all cases, not just aggregates: requested aspects, retrieved support, authority/family/side correctness, answer usefulness, unsupported claims, clarification/refusal quality and operational completion. Include every attempt and accounting uncertainty.
- [ ] R06.5 Apply accepted criteria and make an explicit expansion decision. A small sample can reveal failure and support expansion; it cannot establish overall product viability. Any material unresolved security, authority or unsupported-claim failure blocks expansion. A non-safety miss is assessed against the predeclared criteria, not an improvised 4/5 rule.

**Deliverable:** immutable regression/confirmation evidence, answer inspection and proceed/hold recommendation. **Gate:** David reviews the evidence before full execution. A hold produces one consolidated diagnosis/change request with effort impact; this plan does not authorise an indefinite repair/rerun loop.

## R07 — full production run

- [ ] R07.1 Confirm R06's expansion decision, full manifest and accepted policies. Standing provider permission covers execution within the operating bounds; it does not bypass the quality gate. Reconcile Phase 28 session ownership from R00; do not relabel the old bespoke run as a comparable production baseline.
- [ ] R07.2 Execute all 74 cases / 148 utterances through authenticated membership, persisted conversation, real planner, Laravel eligibility, real Qdrant retrieval/reranking and production generation/persistence. Keep expected answers/evidence out of execution decisions.
- [ ] R07.3 Keep primary, foreign and security execution scopes distinct. Preserve the negative/import-fixture evidence under its separate S03/S07 boundary.
- [ ] R07.4 Score after observations are fixed. Report retrieval and answers separately, slice denominators, small-slice uncertainty, operational failures, authority/citation integrity, tenant/injection behaviour and all unsupported aspects.
- [ ] R07.5 Inspect all material safety/authority failures and a predetermined stratified answer sample. If a model judge is used, report its identity and limitations; it is not infallible ground truth.
- [ ] R07.6 Produce the accepted pilot-readiness classification and specific limitations. Provider completion is not a quality pass. Broader deployment/Phase 29 requirements still apply.

**Deliverable:** durable full-run artefacts, costs, per-case/slice results and an evidence-backed pilot-readiness decision. **Gate:** accepted metrics and absolute-failure rules determine the result; unresolved failures remain visible.

## R08 — close and reconcile

- [ ] R08.1 Reconcile S04–S07 and R28-GATE against what actually completed. Preserve prior runs and decisions; no retroactive success claims.
- [ ] R08.2 Record residual issues by user impact, severity, owner and required evidence. Separate pilot blockers from later improvements.
- [ ] R08.3 Update the implementation guide, factual journal and journey at the appropriate stage boundary, linking evidence rather than copying it repeatedly.
- [ ] R08.4 Record final commits/runtime/population/policy identities and the command for verifying durable artefacts without providers. Obtain normal closure/commit approval.

**Deliverable:** truthful Phase 28 closure or an explicit incomplete state with remaining work. **Gate:** no required work is hidden by a completed checkbox.

## Run authorisation register

David's instruction on 2026-09-05 grants standing permission to use the existing OpenAI and Voyage providers for development, verification and planned evaluation within this repair. Do not ask again for each call, small experiment, changed commit or routine rerun. This is new standing authority, not reuse of an old consumed authorisation.

Conservative operating bounds chosen for execution management: USD 5 per small experiment and USD 30 cumulative provider spend under this repair plan, including R07. These are working safeguards, not amounts quoted by David or a reason to build a new budget subsystem. Track usage using existing receipts and estimates, accounting for native retries. If anticipated spend exceeds a bound, raise it once with an estimate and reason. New subscriptions, external data recipients and infrastructure remain separate decisions.

Before each experiment, record its hypothesis, fixed inputs, configuration identity and expected calls. Changes of model/prompt/configuration create a new result identity; preserve prior results. Routine evidence-driven experiments within an accepted section need no further spending approval. Do not repeat a run unchanged to seek a preferred output. Time/effort checkpoints and section scope still limit iteration: cheap calls do not justify days of unbounded agent work.

Use the existing governed authorisation mechanism if required by code. Generate fresh records from this standing authority and current identities; do not bypass it or ask David to copy another hash solely because the commit changed. Diagnostic records remain separate from formal frozen-run policy. Any material conflict with the existing formal evaluation protocol must be reconciled once in R05, not rediscovered at dispatch.

| Run | Purpose | Planned size | Commit/profiles | Request/token/time/cost envelope | Approval | Result |
| --- | --- | --- | --- | --- | --- | --- |
| R01P slice | Test uncertain design early | 6–8 varied utterances; finalise R01P | pending | derive from slice within standing bounds | standing; R01 design gate remains | not run |
| R06 regression | Known failure patterns | 8 utterances, finalise R05 | pending | derive R05 within standing bounds | standing; R05 gate remains | not run |
| R06 confirmation | Different engineering questions | 16 utterances, finalise R05 | pending | derive R05 within standing bounds | standing; regression gate remains | not run |
| R07 full | Full frozen engineering population | 74 cases / 148 utterances | pending | derive R05; reconcile cumulative budget | standing; R06 expansion gate remains | not run |

Record calibration/provider experiments here or link their existing manifest inventory. Engineering calibration may be approved as part of the accepted design; protected calibration/held-out access remains prohibited. No full question-authoring programme is implied.

## Effort and drift control

The technical review estimated 8–15 engineer-days; the conversation proposed a 2–4 focused-day investment window. Neither is a delivery guarantee. R01 must replace those competing estimates with section-level estimates based on the accepted contract. Track actual effort/time per section so David can decide using evidence.

- First checkpoint: R00–R01 yields the implementation contract and estimates; no promise of accuracy yet.
- Early investment checkpoint: R01P demonstrates the hardest uncertain slice with real providers before the remainder is built. Use its evidence to accept, revise or reject the approach and update effort estimates.
- Middle checkpoint: R02–R04 demonstrates the complete repaired behaviours in the actual services.
- Direction checkpoint: R06 shows whether improvement carries to different live questions.
- Quality checkpoint: R07 measures the full engineering population.

If a section exceeds its estimate, record the reason, remaining deliverable and revised estimate before expanding it. New symptoms are not permission to broaden scope silently. Escalate an architectural change as one consolidated decision; ordinary defects within the accepted section remain implementation work. Never choose either a rewrite or abandonment solely from a tiny sample's percentage.

## Section completion record — fill for each section

| Section | State | Commit / files | Deliverables and evidence links | Tests actually run | Actual effort / remaining estimate | Material issues | Next action |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Plan preparation | ready_for_review | document only; uncommitted | This checklist; product interaction requirement captured | Markdown scope/whitespace checks only | Implementation estimate pending R01 | Architectural contract not yet accepted | Start R00, then prepare R01 decision |

## Decision and scope-change log

| Date | Decision | Authority / status | Consequence |
| --- | --- | --- | --- |
| 2026-09-05 | Free-text questions; conversational clarification; no mandatory forms/selectors | Explicit David instruction | Applies to every section and acceptance example |
| 2026-09-05 | Prepare a living drill-down plan with deliverables and a route to live runs | Explicit David instruction | This document is the working checklist; no code/run approval inferred |
| 2026-09-05 | Prove a small production slice live before the wider repair | Explicit David instruction, supersedes deferred-first-live sequence | R01P gates broader implementation; retain natural-language interaction |
| 2026-09-05 | Explain suitable third-party/library/provider alternatives where custom logic falls short | Explicit David instruction | R01.9 compares alternatives and makes trade-offs visible |
| 2026-09-05 | Routine live-provider work need not repeatedly request permission | Explicit David instruction, supersedes earlier per-run spending prompts | Standing OpenAI/Voyage authority with recorded operating bounds; scope/design gates retained |
| Pending R01 | Mixed evidence needs and grounded interpretation contract | Proposed; acceptance required | Implementation R02–R05 depends on this decision |

## Current next action

**R00: establish the baseline without repeating the technical review; R01: agree the minimum slice contract and alternatives; then R01P: implement and demonstrate it live.** Do not complete the wider repair before obtaining that evidence. Subsequent tasks should update this section rather than create a replacement plan.
