# R01P authorised scope facts — implementation and regression

Date: 2026-09-08. **Not ready to commit.** R28-S04/R01P remain in progress. ADR-0039 remains Proposed; no acceptance, commit or push.

## Identity and boundary

Started on clean tracked HEAD `9210c744be40b2517adf6c97e78de7c21bcdf7a9`; verified starting runtime aggregate `573b9e97f13043a63c05d31c66fa9e645531fe133b6ee1c2027728c4f870a8ef`.

The authorised three-case run used frozen uncommitted aggregate `346d012716b1302fcbc11e1ac1062c3296c09ce32dbfa28b9d6b5e2b2da19c5e` (951 runtime files), verified unchanged after all three calls and before the local accounting follow-through. Input subset SHA-256: `a239cd9a6a6b25b34df15eabad2dc1f5f54cc7912007a32c9aacbb587c681fbb`. Final source after provider-free accounting correction: `a698bf058298fc68496b8f4f4c0a11a6dfab0205afd82eb7ee3367b1fce17486`. These are different, explicitly separated identities; the final source has not had a new paid run.

Evidence root: `/private/tmp/dolved-scope-fact-correction.Zy7IR3`. Full records, planner fields, typed scope observations, dispatch/response hashes, citations and receipts are in `run/` and `observations-and-citations.json`; `code-freeze.json` records the live identity, `post-run-verification.json` the subsequent source identity. No answer key or future v8 questions were accessed.

## Implemented boundary

[ADR-0039](../../../adr/0039-define-authorised-applicability-scope-facts.md) describes the additive explicit-purpose mechanism and ADR-0017/0018/0023/0038 relationships. No operational eligibility, tenant/catalogue binding, approval, temporal rules, thresholds or vector indexing changed.

Planner contract v3 deliberately emits `scope_enquiry`. Laravel resolves one authorised family/version/location, reads its sealed applicability snapshot and emits a typed fact with snapshot digest, inherited/direct basis, temporal/corpus identity and a separate applicability citation. Publication rechecks these bindings. Excluded chunks never enter this path. Fixed rendering explicitly says recorded applicability does not establish what action is safe or required and cannot establish a rationale absent from the snapshot. Mixed operational needs alone go through the existing generation contract with only their own question/evidence. Scope-only generation has zero provider dispatches. API/event scope citations are separate; no browser citation-card UI was added or claimed.

Active identities: `evidence-needs-plan-v3`, `bounded-evidence-needs-v13`, `structured-chat-needs-v6`, `bounded-needs-slice-v13`, `applicability-scope-fact-v1`, `scope-fact-renderer-v1`, `scope-composed-answer-v1`. Ordinary generation contract/prompt/model unchanged.

## Real corpus controls (provider-free, read-only)

- Asbestos document 226: Harbour View outside recorded scope, ordinary operational eligibility false; Oakfield Lodge direct match, ordinary eligibility true.
- Key Safe document 234: Willow Bank Community Service direct match (preferred over its also-listed ancestor); Oakfield Lodge inherited through Midlands Region; Harbour View outside scope. Ordinary eligibility respectively true/true/false.
- Sickness document 2: universal scope; Harbour View remains operationally eligible.

See `real-corpus-proof-v2.json` and reproducible `real-proof.php`. The earlier proof remains retained; it identified ancestor-first basis reporting where both direct and ancestor entries matched, corrected before the live freeze with a synthetic regression. The observed count is 278 universal / 22 specific versions across 18 specific-scope families. Three multi-entry versions in two families are not proof that both families have explicit exclusion prose; ADR-0039 records that distinction.

## Provider-free verification

Before live: 169 Python tests; 213 Laravel tests / 1,015 assertions; three optional cross-service bridge tests skipped. Focused contract/schema, planner and generation tests passed, as did Ruff, Pint, scoped mypy and whitespace checks.

After the accounting follow-through: **169 Python tests; 214 Laravel tests / 1,025 assertions**, same three bridge skips. The additional test runs the full synthetic `OrchestrateConversationRun` through persistence and workspace usage, mocks only the planner HTTP response and forbids any generation/search dispatch. It proves the zero-request generation receipt is local/application/zero-cost and the answer publishes. The initial added fixture used an incorrect response-key name; this fixture was corrected to the real `classifier_lineage` envelope before the final passing run. It is not represented as a provider defect.

Suites: Python `test_evidence_needs`, `test_needs_provider_schema`, `test_needs_validation_retry`, `test_generation_needs_request`, `test_generation_needs_output`, `test_generation_needs_transport`. Laravel `EvidenceNeedsRetrievalTest`, `GroundedGenerationFoundationTest`, `ConversationOrchestrationTest`, `EvidenceNeedsClientTest`, `EvidenceNeedsPlanTest`, `NeedsEvidenceSelectionTest`, `SemanticRetrievalTest`, `DocumentVersioningAndTemporalAuthorityTest`.

Coverage includes adversarial leading safety wording, direct/inherited/universal/outside scope, foreign identities, draft/future/withdrawn authority, historical snapshots, source/corpus tampering, mixed evidence separation, and separate citation persistence. Synthetic planner shape tests are not a claim of reliable live semantic classification.

## Live results — each question once, no reruns

### i01

Question: Does Harbour View need an asbestos register check before drilling into a wall there?

Status: **failed**; retrieval `evidence_found`; generation `not published`; failure `internal_failure`.

Planner operations: n1=scope_enquiry. Full need/authority/applicability fields retained in the case JSON.

Published answer:

None — no answer was published.

The planner explicitly emitted scope_enquiry and Laravel resolved the expected outside-recorded-scope snapshot fact. No embedding, reranking or generation provider call followed. Publication failed with `internal_failure`; this is **not a passing live case**.

Retries: none (planner/generation validation and native); requests 1, elapsed 12.203742 seconds, configured-rate estimate USD 0.00548900.

### i16

Question: Does the asbestos register procedure cover Oakfield Lodge?

Status: **failed**; retrieval `evidence_found`; generation `not published`; failure `internal_failure`.

Planner operations: n1=scope_enquiry. Full need/authority/applicability fields retained in the case JSON.

Published answer:

None — no answer was published.

The planner explicitly emitted scope_enquiry and Laravel resolved the expected direct-match snapshot fact. No embedding, reranking or generation provider call followed. Publication failed with `internal_failure`; this is **not a passing live case**.

Retries: none (planner/generation validation and native); requests 1, elapsed 14.733471 seconds, configured-rate estimate USD 0.00535775.

### i24

Question: Quick one — does Harbour View need an asbestos check before drilling, and can agency workers still get booked with just a phone call?

Status: **completed**; retrieval `evidence_found`; generation `qualified`; failure `none`.

Planner operations: n1=retrieve, n2=retrieve. Full need/authority/applicability fields retained in the case JSON.

Published answer:

Agency bookings must be confirmed in writing by email before the shift — the policy removed the previous allowance for verbal telephone confirmation and requires a written record for every booking regardless of urgency; even in genuinely urgent gaps the written-confirmation, DBS and training verification standard still applies (the registered manager may contact a wider agency pool only with the Alderbridge Quality Lead's direct approval in those cases). Agency staff must be booked only through the two approved framework agencies and the agency is contractually responsible for confirming DBS and training status before the shift is confirmed.

The planner emitted retrieve for BOTH needs. Therefore no scope fact was produced; n1 remained unsupported and n2 supported. The agency-booking answer cited document 187, canonical chunks 588/589/590/591, all resolved in the primary workspace. **This is not successful live mixed-scope verification.** An intermediate commentary incorrectly said a scope statement was included; the persisted export disproved that and the user was corrected promptly.

Retries: none (planner/generation validation and native); requests 4, elapsed 25.657301 seconds, configured-rate estimate USD 0.00862107.

## Concrete remaining findings and commit decision

1. **Scope-only usage publication integration:** the zero-cost local renderer initially omitted `execution=local` / `cost_basis=zero_cost_local`. Production `normalisedUsage` consequently labelled it provider_api/unavailable while retaining cost=0; actual `RecordWorkspaceUsage::validate` rejected that exact combination with `InvalidArgumentException: The workspace usage entry is invalid.` A provider-free invocation of those real methods reproduced the rejection. The local receipt now carries its explicit correct labels. Full synthetic conversation publication and accounting pass. Existing failed runs were not altered or resumed. No new paid run confirms the corrected source.
2. **Compound planner-purpose selection:** i24 n1 remained retrieve, unlike i01's scope_enquiry. The instruction currently distinguishes scope-dependent requirements from working instructions, but this live output did not select the intended explicit purpose in the compound question. No hidden post-retrieval reclassification or eligibility widening was added. This needs a bounded planner instruction/decomposition refinement and verification, not a threshold boost or removal of the safety boundary. No such additional tuning or paid rerun was performed.

Commit is withheld because the requested live acceptance is not clean; HEAD remains the starting SHA. Do not treat implementation or ADR as accepted. Next work should address only the evidenced compound-purpose gap, retain the accounting follow-through, and obtain the next bounded live verification instruction. No general audit or R02 expansion is proposed.

## Accounting and preservation

Six physical provider requests: three OpenAI planner, one Voyage query embedding, one Voyage reranker and one OpenAI generation. Zero native/validation retries; 55,663 input tokens / 2,972 output tokens. Configured-rate/adapter estimate **USD 0.01946782** against USD 5; **52.597202 seconds** against 90 minutes. No re-embedding, vector changes, additional questions, AWS or push. Runtime migration added only nullable scope-fact answer storage in the isolated evaluation database via the owner migrator; runtime privileges were not widened.

All 37 previously inventoried unrelated untracked files remain byte-identical and untracked. The separately present v8 question file was not opened. Prior evidence and this three-case run remain preserved. Tracked worktree intentionally contains this unfinished correction; nothing is staged.
