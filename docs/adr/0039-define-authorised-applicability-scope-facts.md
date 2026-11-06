# ADR 0039: Define Authorised Applicability Scope Facts

## Status

Accepted by David on 2026-09-08, including the explicitly accepted planner family-binding limitation and the single live acceptance pass recorded below. Acceptance authorises this bounded scope-fact extension, not R01P completion or pilot readiness.

## Date

2026-09-08

## Relationship to prior ADRs

### Extends ADR-0018 for one distinct need purpose; operational eligibility is unchanged

ADR-0018's location resolution flow excludes a version whose recorded applicability does not cover the requested location. That remains the complete rule for every operational retrieval/compare need. This ADR adds one explicit `scope_enquiry` need purpose: Laravel may inspect an otherwise authorised, temporally selected version's applicability snapshot to answer where its recorded scope applies. Only that metadata resolution is exempt from operational applicability exclusion. It does not admit the excluded version's chunks to search, reranking, evidence selection or operational generation.

A failed or empty operational retrieval is never converted into a scope enquiry. The planner must deliberately identify the scope question before execution. Unknown or ambiguous subjects/locations remain unresolved, not guessed or widened. This is an additive exception to ADR-0018's eligibility-purpose contract, not a claim that ADR-0018 already allowed the behaviour.

### Consumes ADR-0017 snapshots unchanged

The sealed per-version applicability snapshot remains the authority. No new applicability fields, mutable scope defaults, reason fields, snapshot construction rules or sealing rules are introduced. Universal scope, direct location membership and descendant inheritance retain their exact meanings. A scope fact is a derived, version-bound observation, not a replacement snapshot or a change to where a document applies.

### Extends ADR-0023's evidence representation, not its ownership boundary

Laravel resolves applicability before rendering; generation never decides or re-evaluates it. A scope fact is not a chunk evidence handle. Its citation identifies the applicability record and selected document version, never an excluded operational passage. The answer layer renders the already-resolved fact deterministically. An OpenAI paraphrase, semantic polarity detector or post-hoc regex is not the safety boundary.

For mixed answers, only operational needs and their selected operational evidence enter provider generation. Scope facts are rendered independently and composed without model rewriting. The ordinary operational response still passes the existing generation and citation-ownership validators. This extends the answer composition and citation representation; it does not weaken those validators or give generation a new authority decision.

### Preserves ADR-0006, ADR-0016 and ADR-0038

Authentication, authorised workspace scope and any access restrictions precede both purposes. Location applicability is not a substitute for access. Cross-tenant catalogue identities cannot become scope facts. Existing corpus/index readiness and temporal authority checks remain required; scope enquiries do not expose drafts, future policies, withdrawn policies or historical versions through a current query. Explicit historical selectors use the existing timeline, with the same unresolved/ambiguous outcomes.

ADR-0038's bounded need count, grounded references, catalogue binding, need ordering and separate coverage remain in force. Scope enquiry is a deliberate purpose, not permission to broaden a document-specific binding or use expected evaluation answers during execution. Existing retrieve/compare semantics and thresholds do not change.

## Context and verified evidence

Round seven ran at `9210c744be40b2517adf6c97e78de7c21bcdf7a9`, runtime aggregate `573b9e97f13043a63c05d31c66fa9e645531fe133b6ee1c2027728c4f870a8ef`. Immutable evidence: `/private/tmp/dolved-confirmation30-v7.cl4ugt` (i01, i16 and i24); bounded follow-up: `/private/tmp/dolved-scoped-policy-diagnosis.LTuxA4/diagnosis.md`.

Document 226, Asbestos Management Procedure, has a specific snapshot listing Riverside House, Moorland View and Oakfield Lodge. Its first cited chunk is 683, public ID `66994773-6821-5584-a3c4-95dbe97d29ef`. The text also expressly excludes Harbour View, Meadow Court and Willow Bank. In i16 the document was eligible and the chunk ranked first (hybrid 0.30952380952380953; reranker 0.953125). In i01 it was absent from the eligible document set, not merely under-ranked. In i24 need n1 returned `no_eligible_evidence` before any search. These are scope-evidence limitations, not proof of deficient negation scoring or fabricated citations.

Read-only snapshot inventory of the 300 primary versions: 278 universal; 22 specific across 18 families; no missing snapshot. Three versions across two families have multiple explicit scope entries: Asbestos documents 141 and 226, and Key Safe Procedure document 234 (Willow Bank Community Service / Midlands Region). Apart from asbestos, this is one additional multi-entry version. This count does **not** establish that both families contain explicit exclusion prose or have independently demonstrated answer failures. One region entry can cover multiple descendant sites.

The real resolver retained the universal Sickness Absence Policy (document 2) for Harbour View, both in topical retrieval and when bound to that family alone. `appliesTo()` returned true. Universal versions are unaffected by the specific-snapshot exclusion mechanism; this is not a claim that all 278 were individually tested end to end.

## Decision

### Explicit purpose and resolved identity

The planner may emit `scope_enquiry` only when the need is about a policy's applicability/coverage, including an applicability subquestion within an operationally phrased request. It must not classify requested working instructions as a scope-only answer, invent a policy identity, or treat the resulting negative scope fact as permission to act. The original question remains observable. Missing operational advice remains explicitly unestablished.

Laravel resolves the authorised family, one requested location and the authoritative version using the existing catalogue/location/timeline boundaries. A topic-only or multiple-family binding cannot silently select one family's snapshot. Missing/ambiguous references return unresolved/clarification. No empty-result fallback exists. Historical scope inquiries resolve the requested historical version; a current version must not be substituted. Unsupported multi-version scope comparisons clarify rather than silently collapse a pair.

### Accepted limitation — planner family-binding judgment

David explicitly accepted this limitation on 2026-09-08. Family-binding for a `scope_enquiry` depends on the language-model planner's judgment about whether the user's wording sufficiently identifies a specific policy in the authorised catalogue. Near-identical standalone and compound questions can fall on different sides of that judgment. When the planner does not identify one policy family, the system correctly asks for clarification rather than guessing a family or widening eligibility. This is a known characteristic of using a language model for this classification step, not a difference between deterministic standalone and compound pipelines.

The i01-versus-i24-n1 investigation in `/private/tmp/dolved-scope-binding-diagnosis.0GG4Vt` verified identical deterministic code paths and catalogues, with exact reconstructed request hashes matching the persisted dispatches. Both plans selected `scope_enquiry`; i01 returned `topic` with no family IDs, whereas i24-n1 returned `matched` with the Asbestos Management Procedure family. The divergence was in the model's joint plan output. No separate aspect-only family-binding invocation or single/multi-need application branch caused it. The evidence does not expose the model's internal reasoning or establish that compound framing alone caused the difference.

Safe clarification on this boundary is accepted behaviour, not an implementation defect or a failed factual answer. It remains an unresolved user request, not a successfully answered scope fact. This acceptance does not relax the single-authorised-family requirement, invent source identity, turn `topic` into an automatic match, or make ordinary guidance retrieval more permissive. Do not tune prompts to force convergence on these two observations. A successful prior sample does not guarantee the same binding or answer on a later sample.

### Post-acceptance addendum — compound operation-selection judgment (2026-11-06)

David separately accepts a second model-judgment limitation: **operation-selection variance**, not the family-binding variance above. Under the same uncommitted `bounded-evidence-needs-v15` instruction, the exact compound i24 turn (“Quick one — does Harbour View need an asbestos check before drilling, and can agency workers still get booked with just a phone call?”) was submitted three additional times to the production needs planner, with only the primary workspace opted in and no retrieval or generation. Its asbestos need n1 was `scope_enquiry`, `retrieve`, `scope_enquiry`, in that order. Both `scope_enquiry` samples matched the Asbestos Management Procedure family; the `retrieve` sample used topic binding. All three plans retained the named Harbour View location, classified the independent agency-booking need n2 as `retrieve`, made one planner request with zero retries, and carried the same v15 planner fingerprint. The three raw planner results were observed in the 2026-11-06 task transcript, not archived as separate case JSON; this paragraph records their bounded result rather than claiming an immutable raw-response package. The earlier full production-path i24 record at `/private/tmp/dolved-j19-live.rxLjkD/cases/i24.json` also selected `retrieve` for n1. Three isolated n1 planner calls in that investigation all selected `scope_enquiry`. These samples establish variance, not the model's internal reason or a deterministic effect of the v15 wording.

Both paths retain the deterministic safety boundaries: `scope_enquiry` can publish only the resolved applicability fact, while `retrieve` cannot admit the excluded Asbestos Management Procedure's operational chunks for Harbour View or infer that drilling is safe. **The `retrieve` path remains a real answer-quality gap, not a cosmetic wording difference.** In the recorded full i24 run it was marked `answered` and gave generic check-first guidance qualified as applying “at an in-scope site,” rather than the requested Harbour View scope fact; a user asking this exact compound question may therefore receive a vague or nonresponsive answer instead of the correct recorded-scope explanation. Acceptance of this observed model variance does not recast that answer as a correct scope fact, weaken eligibility, or guarantee that future outputs are equally well qualified. David elects not to tune the instruction further against these samples. This addendum does not change the original 2026-09-08 acceptance evidence, the operation contract, or any product-readiness threshold.

### Typed scope fact representation

The derived record includes a representation/renderer version, need ID, authorised workspace identity, policy family identity/title, selected version identity, sealed snapshot identity/digest, requested location identity/name, selected temporal context, evaluation time, result (`applies`, `does_not_apply`, `unresolved`), and basis (`universal`, `direct_match`, `inherited_match`, `outside_recorded_scope`, or an explicit unresolved reason). For inheritance, retain the actual matching ancestor identity and path used at resolution. A later hierarchy change cannot rewrite the persisted explanation of an earlier answer.

Scope facts never carry operational chunk text or invented ingestion lineage. Recheck source identity, workspace membership, snapshot sealing, temporal authority and corpus identity before publication; a changed authority/access/corpus boundary fails closed. Durable citations retain the evaluated fact and source snapshot digest. Authorised citation resolution reveals the scope record, not a fabricated chunk citation; historical answer evidence remains historical and labelled with its evaluated version/time.

### Hard safety boundary — load-bearing

A scope fact records **only where the selected policy version's snapshot says it applies**. It must never be rendered, merged or interpreted by this mechanism as an operational or safety conclusion, regardless of leading user wording.

Permitted: “The Asbestos Management Procedure's recorded scope does not include Harbour View.”

Forbidden: “You do not need to check for asbestos at Harbour View”; “there is no asbestos risk”; “it is safe to drill”; or any inference that an underlying risk is absent, resolved or irrelevant.

Use fixed, versioned rendering of typed fields, with an explicit limitation that recorded applicability does not establish what action is safe or required. Neither user text nor operational excerpts are interpolated as conclusions. Metadata labels are treated as quoted data and safely escaped. No model receives a scope fact for free-form rewriting. The safety guarantee concerns this mechanism's output, not a guarantee that no user could personally misinterpret a statement.

The snapshot has no recorded rationale field. For “why doesn't it apply here”, state that the reason is not established by the scope record. Do not infer a rationale from excluded chunks, document titles, negative membership or assumptions about a building. Outside recorded scope is not evidence of absent physical risk or absence of other applicable policies.

### Mixed answers and persisted evidence

Keep the typed scope result and ordinary operational result in separately labelled need sections. Scope-only execution requires no query embedding, reranking or generation provider call after planning. A mixed question generates only its ordinary operational need(s), from their existing eligible evidence, then deterministically composes the sections. The operational generation request must not invite an answer to the scope need or a rewrite of it. No shared model-written conclusion bridges the sections.

Persist the fact, its citation, its need-to-answer linkage and renderer identity with the answer. Rejected/unresolved scope evidence must not be reported as a supported operational answer. Legacy chunk citations and results remain readable. Contract/profile changes are explicitly versioned; in-flight requests are not reinterpreted and rollback changes only new runs.

## Non-goals

- No relaxation of operational applicability, access, tenancy, approval, temporal authority, indexing or thresholds.
- No automatic widening after `no_eligible_evidence`.
- No free-form “why” extraction from operational chunks or new applicability data fields.
- No scope inference by embeddings, lexical boosts, regexes, or generation.
- No migration/re-embedding of the corpus, new provider, full evaluation, or R02 work.
- No reinterpretation of the independent confirmation as scored success or modification of its evidence.

## Verification and acceptance

Provider-free proof must exercise explicit planner shape, actual resolver and deterministic rendering: universal/direct/inherited/outside scope; unknown and ambiguous family/location; foreign tenant; draft/future/withdrawn/current and historical resolution; missing/unsealed snapshot; authority/corpus change before publication; citation resolution and persistence; ordinary retrieval unchanged; no excluded chunk or scope text entering operational generation; and independent mixed-need coverage.

An adversarial “so I don't need to check, right?” input must produce only the fixed recorded-scope fact plus limitation, never an operational or safety inference. Changing the adversarial wording must not change this deterministic rendering. Recheck Key Safe Procedure's real two-entry scope and the universal Sickness Absence Policy as non-asbestos controls.

Only after provider-free proof passes, run i01/i16/i24 once under the existing bounded authority, record exact planner purposes and source identities, actual answers/citations, retries, tokens, cost and elapsed time. By David's explicit pre-execution acceptance decision on 2026-09-08, i01 may either publish a truthful scope fact **with an explicit operational limitation** or safely clarify when the planner does not identify a specific family. Label the latter as the accepted binding limitation, not a failure and not an answered fact. This replaces the earlier fact-only criterion for i01 explicitly; prior runs and their original assessments remain immutable. Neither outcome permits an inference that drilling is safe or a check unnecessary. i16 must preserve its correct coverage conclusion. i24's scope and agency-booking needs must remain separate and agency-booking evidence operationally independent. Do not tune against partial results or claim semantic proof from synthetic planner fixtures. This bounded ADR acceptance is not a changed frozen evaluation threshold, full-population correctness pass, R01P closure or pilot-readiness conclusion.

## Acceptance evidence — 2026-09-08

David authorised documenting the binding boundary without another prompt change and one i01/i16/i24 execution. The runtime source remained `65eb3a1c467bb7f059fb2b6e1842e2f0eced010edcc27e30236e6c2ae1116d30` before and after the pass, over base HEAD `9210c744be40b2517adf6c97e78de7c21bcdf7a9`. Evidence: `/private/tmp/dolved-scope-acceptance.zHpSLk`; durable report: [Scope-fact acceptance](../evaluation/r28-s04/r01p/scope-fact-acceptance.md).

Actual outcomes: i01 bound the Asbestos Management Procedure and published its outside-recorded-scope fact with the explicit safety limitation; i16 published the correct Oakfield coverage fact; i24 published separately labelled scope and operational agency-booking answers with distinct source citations. **i01 did not clarify in this pass**. Its prior clarification remains the accepted limitation evidence, not a result rewritten to match this pass. All three executed once, with no tuning or reruns. This confirms a bounded successful sample, not deterministic semantic convergence across future phrasings.

Provider-free verification: 174 Python tests; 214 Laravel tests / 1,025 assertions; three optional cross-service bridge tests skipped. Live usage: six provider requests, zero retries, 56,235 input / 2,633 output tokens, estimated USD 0.01851474, 39.133930 seconds. No re-embedding, index/threshold/model change, AWS or further evaluation. Existing authority, ambiguity, safety and tenancy boundaries remain enforced.

## Alternatives considered

- Lowering thresholds or boosting exclusions: cannot recover a document excluded before ranking and risks unrelated retrieval quality.
- Ignoring location for ordinary retrieval: reopens wrong-site operational advice.
- Making scoped corpus fixtures universal: falsifies the governed source data.
- Supplying entire excluded chunks with a prompt warning: makes the safety boundary depend on model compliance and exposes unrelated instructions.
- Generating a scope answer then policing prose: repeats the rejected fragile semantic-pattern approach.
- Scope facts without source citations: cannot support durable independent verification.

## Consequences

A narrowly bounded metadata-evidence purpose and typed citation path are added. It is a real answer-contract extension, not a scoring tweak, and needs cross-service/profile/persistence tests. It does not make an out-of-scope policy's instructions applicable. It cannot answer why without a source record that establishes why. Operational needs and their existing safeguards retain their prior behaviour. David accepts the binding-judgment limitation and this bounded implementation; broader R01P and pilot-readiness gates remain open.
