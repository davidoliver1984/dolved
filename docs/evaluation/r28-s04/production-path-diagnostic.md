# R28-S04 production-path diagnostic boundary

Date: 2026-09-05

This boundary replaces the bespoke R28-S04 execution algorithm for the next
diagnostic. Runs 0001–0006 remain immutable historical evidence, but their
runner is not evidence that the production retrieval path was exercised.

## Stage comparison

| Stage | Production implementation | Previous S04 implementation | Delegation and material difference |
| --- | --- | --- | --- |
| Authentication and tenancy | Authenticated conversation endpoint, `FindWorkspaceForUser`, then `BuildAuthorisedKnowledgeScope` | A preloaded evaluation corpus | Duplicated/omitted. The old path did not exercise live membership, active-corpus or tenant concealment checks. |
| Conversation context | `OrchestrateConversationRun` calls the contextualisation service over persisted conversation history | No production contextualisation | Omitted. Clarification and resolved-query behaviour could differ. |
| Planning | `RetrievalClient::plan()` calls the Python retrieval planner | Copied `case.context` into `frozen_plan` | Duplicated and tautological. The old planner-integrity comparison compared frozen truth with the same copied truth. |
| Temporal and applicability eligibility | Laravel `EligibilityResolver` resolves authority timelines, historical/valid-at modes, location aliases, descendants and inherited applicability | `eligible_chunk_indices()` and `side_chunk_indices()` | Duplicated. Database lineage, current authority, aliases and inheritance could produce different candidates. |
| Dense and sparse retrieval | Python `DenseRetriever` embeds the query, searches Qdrant dense and sparse spaces, and performs RRF per side | In-process dense cosine and local sparse scoring | Duplicated. Qdrant filtering, the active generation, sparse representation and production RRF were bypassed. |
| Reranking and evidence selection | `RetrieveWorkspaceEvidence` invokes `RetrievalClient::rerank()`, rechecks eligibility, applies the resolved threshold policy and selects final evidence | Manual fusion, reranking adaptation and final selection | Duplicated. Rechecks, side handling, thresholds and ordering could differ. |
| Controlled outcomes | `OrchestrateConversationRun` maps planner/retrieval outcomes to clarification, refusal or comparison-incomplete results | `derive_deterministic_outcome()` | Duplicated. Production outcome precedence and persisted state were bypassed. |
| Generation and citations | `GenerateGroundedAnswer` assembles the request from selected evidence, calls `GenerationClient`, validates it, and persists `GeneratedAnswer`, parts and evidence snapshots | Direct evaluation adapter invocation | Partly duplicated. Production assembly, evidence authorisation, persistence and citation identity were not exercised end to end. |
| Scoring | A separate post-execution process joins actual observations to frozen truth | Expected context was present during execution | Corrected. The diagnostic input contains only case ID, variant ID and utterance; expected outcomes, evidence, answers, relevance and judgements are absent. |

## Corrected execution boundary

`evaluation:r28:production-path` accepts the question-only diagnostic input,
resolves an existing user/workspace membership, creates an ordinary
conversation, submits each ordinary user message through
`SubmitConversationMessage`, and invokes `OrchestrateConversationRun`. The
queued duplicate is suppressed for this synchronous diagnostic only. The
result contains actual orchestration snapshots and generated-answer evidence;
it contains no frozen truth and performs no scoring.

The call path is:

`FindWorkspaceForUser` → `CreateConversation` → `SubmitConversationMessage` →
`OrchestrateConversationRun` → contextualisation →
`BuildAuthorisedKnowledgeScope` → `RetrieveWorkspaceEvidence` →
`RetrievalClient`/Python retrieval routes → `EligibilityResolver` → dense and
sparse Qdrant search/RRF → reranking → eligibility recheck/threshold →
`GenerateGroundedAnswer`/`GenerationClient` → validated persisted answer and
citations.

## Provider-free proof

The Laravel proof substitutes recording HTTP adapters only at the external
service boundary and runs the real application orchestration. Existing focused
retrieval and conversation tests additionally cover current, historical,
valid-at and comparison resolution; location aliases and inherited
applicability; dense/sparse fusion and reranking; final eligibility rechecks;
controlled outcomes; generation; and persisted citations, including the
recording reranker boundary. The Python proof executes the real
`DenseRetriever` with recording embedding, sparse and vector adapters. No
provider or network call is made.

The fixed diagnostic subset and ceilings are versioned separately under
`tests/evaluation/diagnostics/r28-production-path-12/v1/`. This does not alter
the frozen 74-case population.

## Representative-runtime prerequisite

The S03 materialisation is not representative for the later paid diagnostic:
its dense identity is `deterministic/token-hash-unit-vector-v3` and its sparse
identity is `deterministic/token-hash-sparse-v4`. It does not contain real
`voyage-4-large` corpus vectors. The smallest truthful prerequisite is a new,
isolated corpus generation for the same 1,000 chunks using the frozen
production Voyage embedding profile and unchanged production retrieval
configuration. It must be verified and activated only for the isolated R28
tenant before the 12-case paid diagnostic. The deterministic S03 index must not
be silently presented as provider-representative evidence.
