# Generation needs result v2 — ADR-0038

Canonical `document_title` (source filename) and `document_family_title` (family name), each bounded to 500 characters, now accompany IDs in each item's existing `temporal_authority` metadata. Laravel reloads these from the authorised document, not model-supplied labels. They are evidence data, not instructions or proof of subject match. Needs prompt v2 explicitly distinguishes version-pair validity from semantic answerability and requires a gap rather than substituting a different policy. This is a generation guard, not a deterministic family-resolution guarantee or a retrieval-quality claim; wire schema and response fields are unchanged.

The request body is implemented in `request.schema.json` and `GenerationNeedsRequest`, with a synthetic `request.example.json`. Laravel's assembler emits v2 only for a needs-bearing retrieval result. Each evidence handle has exactly one need owner and versioned family provenance. Empty need evidence lists preserve mechanical gaps; they cannot borrow another need's evidence. Each supplied comparison family must retain distinct versions on both sides. The slice allows three needs, four search lanes and five evidence items (David-approved capacity correction, 2026-11-12; previously two needs). Whole selected units must fit the existing context-character budget; otherwise assembly returns the existing typed budget failure rather than truncating one side. Descriptive support edges are excluded by the producer whitelist and rejected by the consumer schema.

This is a request acceptance expansion, not a new evidence ownership model. Deploy both consumers and producer together; the needs execution pin is `bounded-needs-slice-v20` and selection lineage `bounded-needs-k5-v3`. Existing one/two-need persisted results retain their shape and render unchanged. Old in-flight pins fail the existing deployment-identity check rather than silently execute under new capacity. The planner already permits n1–n3. Scope facts remain typed metadata outside generation search evidence and consume zero search lanes; every ordinary need consumes one, each comparison two. Five slots guarantee minimum lane allocation for supported shapes, not completeness of every possible multi-passage answer.

The signed complete-result endpoint `/api/internal/retrieval/generation/answer-needs` uses the distinct rc1 purpose `generation.answer-needs` and the strict tagged `response.schema.json`. Its adapter carries needs into the actual prompt and structured-output schema while retaining existing retry, token-budget, usage and cost handling. Identities are `generation-result-v2`, `grounded-generation-needs-v2`, and `openai-responses-needs-v1`. Existing v1 endpoints reject a v2 body rather than silently interpreting it as v1. `legacy_request()` only reuses common shape/citation/rendering code; the needs adapter appends the actual needs and selects the v2 output model, never dispatching a substitute v1 generation call.

`result.schema.json` describes the versioned result body. `GenerationNeedsResult` is the Python producer model; Laravel independently consumes it through `ValidateGenerationResult::handleNeeds`. New submissions opt in only through `CONVERSATION_NEEDS_WORKSPACE_IDS` (empty by default). They pin the needs profile in the run; retries retain it, disabling opt-in affects future submissions only, and configuration drift fails closed before dispatch. Historical null-profile runs retain the old path. JSON object key ordering does not alter identity. The slice uses the existing complete-result delivery mode, not a v1 stream that would drop aspect links. V1 streaming remains unchanged; v2 provisional streaming is not claimed.

`OrchestrateConversationRun` passes bounded authorised user turns directly to the needs planner without a preceding LLM rewrite, then uses per-need production retrieval and the versioned generation endpoint. The validated full plan is retained in retrieval snapshot lineage, including diagnostic support edges; those edges are excluded from generation. Deploy both additive migrations before opting in a workspace. No workspace has been activated by the component tests. Full cross-service fake-provider proof and the live operating envelope remain prerequisites for the fixed slice.

Both consumers additionally enforce the accepted semantic **structure**: outcome array invariants, request-scoped citation membership, duplicate citation rejection, exact requested need coverage, in-range unique indices, complete answer/gap mapping and outcome/coverage consistency. Schema validation alone cannot enforce all these relationships or prove entailment. The 23 shared cases in `contracts/fixtures/generation-aspect-coverage/v1.json` are exercised through both v2 consumers, not only the original standalone coverage helpers.

The provider output adds only `aspect_links`. `aspect_results` is forbidden in its output and derived by Laravel. The persistence action stores both the validated links and derived projection alongside the unchanged authoritative answer parts, gaps and evidence snapshots. Existing rows retain null coverage. Deploy the additive migration before enabling any v2 writes; rollback the profile for new runs before dropping these columns, which would discard v2 coverage history. No rollback or activation is performed by this component checkpoint.

The strict provider schema reuses canonical `EvidenceId` and the existing answer-part shape. No malformed citation is repaired. Textual limitations and their placement remain generation-owned; a fixture verifies exact supplied limitation-first text survives persistence, not that an actual model has already produced it. No additional sufficiency judge or independent generated answer/status is introduced.

## Claim-under-test extension (2026-11-08)

The optional `claim_under_test` field on a generation need carries a neutral,
versioned, user-grounded claimed value. It is not a location constraint or
evidence source. The active needs generation identity is now
`grounded-generation-needs-v7` / `openai-responses-needs-v3`. The provider is
instructed to confirm, contradict with an explicitly supported alternative,
or mark the need insufficient using only its owned evidence. The citation and
need-ownership validators remain unchanged. Synthetic adapter tests prove
transport and schema handling, not model entailment.

## Entailment-gated result extension (2026-11-08)

The signed request and response envelope remain wire version 2, but a completed
needs result is now `generation-result-v3` under prompt
`grounded-generation-needs-v9` and adapter `openai-responses-needs-v4`.
`entailment_checks` must contain one `supported` verdict per answer part, in
order. Laravel rejects absent, incomplete or non-supported verdicts before
persisting any answer. The Python adapter first applies a narrow conditional
obligation guard to cited sentences, then asks a separate structured reviewer
to check every material claim in each answer part against its cited passages
and application-resolved metadata. Unsupported or uncertain review results
fail closed as bounded validation errors; the reviewer cannot add evidence,
change eligibility or rewrite the answer. Its measured usage is included in
the generation receipt. This is an additional fallible check, not a proof that
all English-language claims are entailed. Provider-free tests verify the guard,
contract and failure routing; independent live accuracy remains to be measured.
