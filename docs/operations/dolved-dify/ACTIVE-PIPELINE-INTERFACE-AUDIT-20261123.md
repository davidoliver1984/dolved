# Active-pipeline interface audit — Dolved with self-hosted Dify

**Date:** 23 November 2026  
**Scope:** Read-only interface, route, policy and action review; no product, configuration or deployment changes  
**Decision boundary:** Recommendations only. Nothing in this report authorises hiding, deleting, reactivating or changing a control.

## A. Executive summary

The current Dolved interface is not broadly obsolete. Authentication, workspace and conversation ownership, document governance, applicability, lifecycle synchronisation, authorised originals, Dify sync state, chat history, retrieved-source disclosure, usage/accounting, branding, correspondence, question review and platform observability remain Dolved responsibilities and should stay.

The confirmed Dify-fit problems are narrower:

1. **Usage can report false zeroes.** `Indexed chunks` and `Ingestion failures` read only the preserved custom-pipeline tables, although the page otherwise contains Dify-aware sync, run, source and usage data.
2. **Extracted text and content comparison promise custom-pipeline projections which Dify ingestion does not create.** These controls should be hidden for the current pipeline, not deleted.
3. **Cancel and progress wording overstate Dify behaviour.** A dispatched blocking Dify request is not cancelled; its result is discarded when it later returns. The visible Dify stages also do not correspond to separately observed retrieval and generation phases.
4. **Several tenant screens still say “Dolved” under a custom tenant brand.** Public password recovery is also not consistently branded. These are shared branding defects, not reasons to remove branding.
5. **One deletion message calls retained retrieved passages “cited”.** The chat itself correctly labels them as retrieved context, not claim-level citations.

No user-facing pipeline switch, concurrent pipeline operation or generic plugin framework is warranted. The smallest safe mechanism is a **server-resolved capability profile** derived from the trusted document-processing driver plus the workspace’s enabled Dify binding. It should affect presentation and supported actions only; existing policies must continue to authorise every backend operation.

The proposed reversible cleanup is to fix/hide only the confirmed items above. Preserve the custom components, API contracts, tests, specifications and recovery assets. No control was found that merits permanent removal now.

## Baseline and evidence

- Canonical working copy: `/Users/davidoliver/Developer/RAG`.
- Current checked-out branch/commit: `codex/dolved-dify-delivery-20261119` at `a4929dc9a99ae97b43699ed0a435987afc0a817d`.
- The working tree was already modified. Four governance-email files and the untracked administrative-observability specification were preserved untouched.
- Current hosted-source branch available locally: `codex/admin-observability-20261121` at `01ff80c25b4634d2e143f0a4521dd4ab36f6544a`. A read-only archive of that commit supplied the source references below.
- Hosted inventory at 18:13 UTC showed Dolved API/worker/scheduler configured as `dolved-api:febedb4`, a web container with image digest `sha256:3085765…`, Dify `1.19.0`, plugin daemon `0.6.14-local`, sandbox `0.2.15`, Weaviate `1.39.13`, OpenTelemetry LGTM `0.29.2`, collector `0.153.0`, and Alertmanager `0.33.1`.
- **Provenance limitation:** `febedb4` resolves locally to an older commit, while hosted functionality includes later work. The mutable image tag and unlabelled web digest do not prove one deployed source commit. Treat the container digests as deployment identity and record immutable OCI source-revision labels in later deployment work.
- Architecture baseline: ADR 0041 makes stock private self-hosted Dify the extraction/chunking/indexing/retrieval/generation engine for the single-client pilot; Dolved remains the product, security and governance boundary. The legacy recovery guide preserves the custom pipeline at commit `9e43561…`, its images, configuration references and required data/volumes for isolated recovery.
- The branding specification found in source still labels itself a draft even though branding implementation is present. It is planning evidence, not proof of acceptance. The administrative-observability/question-review specification is approved requirements; implementation was traced, but protected live acceptance was not repeated in this audit.

## B. Coverage and limitations

| Surface / role | Evidence | Coverage |
|---|---|---|
| Public authentication | Hosted UI and source | Login and forgot-password screens inspected without mutation. Login showed the active tenant brand; forgot-password reverted to Dolved/generic copy. |
| Workspace owner/admin | Source, routes, policies and actions | Navigation, documents/imports, governance, usage, locations, branding exposure, question review and chat traced. Protected live session was unavailable without disrupting the user's active Chrome session. |
| Ordinary member | Source, routes and policies | Read-only categories, documents, chat, source access and capability gating traced. Live member session unavailable. |
| Platform administrator | Source, routes and policies; hosted container inventory | Branding and platform-operations gating, telemetry, alerts and console links traced. Protected live page acceptance not repeated. |
| Narrow/mobile | Responsive source classes only | No independent hosted mobile session was available in the authorised background browser. |
| Mutating flows | Deliberately not exercised | No upload, retry, indexing, generation, notification, deletion, role or configuration action was invoked. |

No sealed assessment material, prompt, answer, passage, personal data or provider call was accessed. Review duration remained within the two-hour cap.

## C. Prioritised findings

| Pri. | Screen / visible control | Role | Applicability | Action | Verified | Finding, evidence and consequence |
|---|---|---|---|---|---|---|
| P0 | Administration → Usage → **Indexed chunks** | Owner/admin | PIPELINE-DEPENDENT PRESENTATION/BEHAVIOUR | RECONNECT/FIX | Source | UI always renders a number (`apps/web/src/components/WorkspaceUsage.tsx:25-35`), but the query reads only `workspace_corpus_generation_chunks` / `document_chunks` and returns `0` if the custom active corpus generation is absent (`apps/api/app/Queries/Workspaces/GetWorkspaceUsage.php:23-35`). A fully indexed Dify corpus can therefore appear to have zero chunks. **Keep the control and source its active value from Dify through a supported read-only API.** Return explicitly unavailable only when Dify cannot provide a trustworthy count; never substitute zero. |
| P0 | Administration → Usage → **Ingestion failures** | Owner/admin | PIPELINE-DEPENDENT PRESENTATION/BEHAVIOUR | RECONNECT/FIX | Source | UI always presents the custom `ingestion_event_claims` failure count (`WorkspaceUsage.tsx:38`; `GetWorkspaceUsage.php:80-87`). Dify failures live in `dify_document_bindings`, whose sync statuses are separately and correctly shown at `GetWorkspaceUsage.php:72-73,102`. A green zero may conceal Dify failures. **Keep the metric and aggregate Dify's persisted sync/failure state for the active pipeline**; use unavailable only if that state cannot be established. |
| P1 | Source page / version page → **Extracted text** | All source-authorised users | CUSTOM-PIPELINE-SPECIFIC | HIDE FOR CURRENT PIPELINE | Source | Links are unconditional for the source route and shown for indexed versions (`apps/web/src/app/app/workspaces/[workspacePublicId]/documents/[documentPublicId]/page.tsx:22-23`; `apps/web/src/components/DocumentFamilyDetail.tsx:72-76`). The API requires a published `activeExtractionProjectionGeneration` (`apps/api/app/Http/Controllers/DocumentContentController.php:136-182`), which stock Dify ingestion does not populate. Current users are sent to a 404/unavailable projection. Preserve route, controller and tests for a future compatible projection. |
| P1 | Document family → **Compare versions** | All metadata-authorised users | CUSTOM-PIPELINE-SPECIFIC | HIDE FOR CURRENT PIPELINE | Source | The control appears whenever history has more than one version (`DocumentFamilyDetail.tsx:108-112`), but content comparison loads each version's custom extraction projection (`DocumentContentController.php:32-74,238-250`). Under current Dify it cannot supply the promised structured extracted-content comparison. Preserve metadata history. Re-enable content comparison only after both versions expose a compatible, authorised projection. |
| P1 | Chat → **Cancel** / “Cancelling answer generation” | Members and admins | PIPELINE-DEPENDENT PRESENTATION/BEHAVIOUR | RELABEL | Source | Once started, cancel only sets `cancellation_requested` (`apps/api/app/Actions/Conversation/CancelGenerationRun.php:19-51`). The blocking Dify call continues (`OrchestrateDifyConversationRun.php:45-60`) and its result is discarded after return (`:113-125`). “Cancel” implies provider work/spend stops. For Dify use an honest label such as “Stop waiting”, with a brief consequence, or hide it after dispatch. Preserve true custom-pipeline cancellation semantics. |
| P1 | Chat response, readiness panel and tenant notices → **Dolved / Ask Dolved** | All tenant users | SHARED | RECONNECT/FIX | Hosted public + source | Custom branding does not reach assistant labels, streaming announcements or readiness calls to action (`apps/web/src/components/ChatWorkspace.tsx:331-334,576-577,621-624`; `KnowledgeReadinessPanel.tsx:46-69`). Further tenant error/preference strings are hard-coded in `GovernanceNotificationPreferences.tsx`, `DocumentLibraryTable.tsx` and `BulkOperationDetail.tsx`. Resolve the trusted active display name centrally; keep platform administration branded Dolved. |
| P1 | Forgot/reset/register authentication screens and browser metadata | Public user | SHARED | RECONNECT/FIX | Hosted public + source | Hosted login showed tenant branding while forgot-password reverted to a Dolved wordmark and generic Laravel copy. `AuthForm.tsx` applies custom headings/introduction only in login mode (`apps/web/src/components/AuthForm.tsx:124-136`), and root metadata defaults to Dolved (`apps/web/src/app/layout.tsx:18-19`). Apply trusted deployment branding consistently to public recovery/invitation/reset states, titles and favicon; do not infer a workspace from untrusted input. |
| P1 | Documents → Technical ingestion controls → Delete confirmation | Owner/admin | PIPELINE-DEPENDENT PRESENTATION/BEHAVIOUR | RELABEL | Source | The confirmation says “Passages already cited … remain preserved” (`apps/web/src/components/DocumentAdministration.tsx:37`). In Dify presentation these are retrieved context, not claim citations. Use driver-neutral wording such as “Recorded source references retained with completed conversations”. The asynchronous Dify deletion itself is correctly implemented and should stay. |
| P2 | Chat progress: **Finding eligible evidence / Preparing a grounded answer** | Members and admins | PIPELINE-DEPENDENT PRESENTATION/BEHAVIOUR | RELABEL | Source | UI maps distinct stages (`ChatWorkspace.tsx:88-96`), but Dify retrieval and generation occur inside one blocking call while `generating` is emitted only after it returns (`OrchestrateDifyConversationRun.php:45-60`). The stages are not observed Dify internals. Use a capability-specific neutral status such as “Searching and preparing an answer…” unless Dify exposes reliable events. Keep richer stages for the custom pipeline. |
| P2 | Documents → **Technical ingestion controls** | Owner/admin | SHARED with pipeline-specific details | KEEP, RELABEL selectively | Source | The active route deliberately nests the server-authorised retry/delete/status panel under a technical disclosure (`apps/web/src/app/app/workspaces/[workspacePublicId]/documents/page.tsx:31`). Retry and delete branch to Dify, and searchable-copy state comes from Dify bindings. Keep actions. Consider renaming the section “Processing and recovery” for product clarity; do not expose Dify or custom tuning. |
| P2 | Hosted deployment identity | Platform operator | SHARED | RECONNECT/FIX | Hosted inventory + repository | Image digests were observable, but the API tag `febedb4` does not establish the later deployed source and the web image has no readable commit label. This makes UI-to-source verification uncertain. Later deployment work should use immutable image tags/digests and OCI revision metadata; it is not an interface cleanup item. |
| P3 | Readiness copy → “ordinary ingestion pipeline” / ten readiness states | All tenant users | SHARED | KEEP | Source | The seven import stages and readiness states describe Dolved's product workflow, not old worker internals. `ImportWorkflow.tsx:178-201` explicitly avoids claiming finer ingestion sub-stages. No custom planner/checker/Qdrant/LocalStack setting is exposed here. Optional terminology polish is cosmetic. |

### Confirmed controls that are not obsolete

- **Sources retrieved** is the correct Dify presentation. It explicitly says passages were supplied as context and are not claim-level citations (`ChatWorkspace.tsx:578-589`). Dify results preserve order, score, document/chunk identity and text in `OrchestrateDifyConversationRun.php:196-255`; access-controlled routes are produced by `ConversationResource.php:61-68,107-129`. **DIFY-SPECIFIC / KEEP.**
- **Searchable-copy status, Retry indexing and Delete** are supported Dify lifecycle controls. `RetryDocumentIngestion.php` branches to Dify; `SyncDifyDocument.php` applies eligibility/scope and only enables completed copies; `CompleteDifyDocumentDeletion.php` disables/deletes the remote copy and retained source. **SHARED with driver-specific action / KEEP.**
- **Question review** is manual, restricted and provider-free. It hides wording until an audited view, makes retrospective access optional/off by default, follows current workspace ownership, and labels subjects as human managed (`QuestionReviewAdministration.tsx:34-111`). **SHARED / KEEP.**
- **Platform operations, telemetry and alerts** are generic operational controls, already restricted to platform operators and separate from tenant usage. They do not expose prompts or question content. **SHARED / KEEP.**
- No active tenant route was found exposing old planner/checker/reviewer configuration, custom model settings, Qdrant, LocalStack, index-generation selection or a runtime pipeline switch. Their remaining code/configuration is recovery/development implementation, not current workspace UI.

## D. Shared controls that must remain

| Area | Keep because |
|---|---|
| Authentication, roles, invitations, workspace selection | Dolved is the product/security boundary. UI visibility does not replace server policies. |
| Conversation ownership, history and selected site | Dolved owns access and persists the trusted context; Dify receives the structured site value. |
| Import staging, verification, matching and review | These are governance and durable-original steps before any processing driver runs. |
| Versions, approval, effective dates, replacement and withdrawal | Dify eligibility depends on Dolved's current approved authority. |
| Organisation-wide / multiple-location applicability and location administration | Dolved creates the trusted metadata/filter scope; question wording must not change it. |
| Original source view/download and permission-checked links | Dify does not replace source authorisation. |
| Dify processing state, retry, withdrawal/deletion synchronisation | The active driver needs product-visible recovery and honest failure state. |
| Sources retrieved disclosure | Correctly describes context without inventing claim-level support. |
| Usage, costs and audit | Keep the structure and “unavailable is not zero” rule; repair only driver-mismatched counters. |
| Tenant branding, correspondence and notification preferences | Shared product capability; fix propagation rather than remove it. |
| Restricted question review | Human governance workflow independent of retrieval engine. |
| Platform health, telemetry, alerts and specialist consoles | Shared operator responsibility; keep outside workspace administration. |
| Categories, saved views and member read-only metadata | Product organisation features, not retrieval implementation controls. |

Future AWS/multi-client work remains deferred. It should not be exposed as if available, but it is not obsolete.

## E. Custom-pipeline preservation and reinstatement map

| Control / behaviour | Preserved implementation | Backend capability required before re-enabling | Permission and validation gate |
|---|---|---|---|
| Extracted text | Source links/pages, `DocumentContentController::extracted`, extraction projection models/tests | Published, immutable projection for the exact authorised version; supported element kinds and pagination | Existing source-content policy plus workspace/version ownership; test no cross-workspace access, correct version and safe rendering. |
| Structured version comparison | `DocumentComparisonView`, comparison API, `AlignDocumentComparison`, projection/alignment records/tests | Compatible published projections for both versions and explicit alignment version | Metadata/content authorisation for both versions; test unavailable/partial/truncated states and no stale projection substitution. |
| Detailed generation progress and streaming | `ChatWorkspace` event presentation, custom orchestrator delivery events/tests | Driver declares reliable observed stages and/or accepted streaming parts | Conversation/run ownership; validate ordering, reconnect, terminal state and that labels match actual stages. |
| Provider cancellation | Cancellation endpoint/action, run state machine and tests | Driver-specific abort/acknowledgement capability, or explicit best-effort semantics | Run ownership; verify provider cancellation, cost semantics, race handling and audit trail. |
| Claim-level citations and structured answer parts | Conditional legacy branches in `ChatWorkspace`, `ConversationResource`, answer/citation records/tests | Generated answer contract with validated evidence-to-claim associations | Source-link authorisation; validate every displayed association and preserve “retrieved context” distinction. |
| Extraction warnings and custom materialisation activity | Conditional warnings, ingestion claims and usage records | Active custom ingestion publications/claims with current schema | Admin-only recovery actions; verify failure provenance and do not merge unavailable Dify observations into zero. |
| Custom indexed-chunk gauge | Custom corpus-generation/chunk tables and usage query | An active, published custom corpus generation | Admin view; validate generation identity and active-document eligibility. |

Reinstatement must be driven by a trusted server capability result, not by making hidden routes visible. Restoring the legacy runtime in isolation is also not proof that its old UI contracts are compatible with the future custom pipeline.

## F. Proposed small, reversible Dify cleanup batch

Subject to David's approval, one bounded implementation batch should:

1. Add a minimal server-owned capability profile derived from the configured document driver and enabled workspace Dify binding. Suggested capabilities: `extracted_text`, `content_comparison`, `provider_cancel`, `progress_profile`, `indexed_chunk_metric_source`, and `ingestion_failure_metric_source`. Return presentation capabilities separately from policy authorisation.
2. Hide **Extracted text** and content **Compare versions** when their published-projection capability is false. Preserve routes, controllers, tests, documentation and recovery references.
3. Reconnect Usage to supported Dify data: calculate **Indexed chunks** from Dify's current dataset/document segment records and **Ingestion failures** from persisted Dify sync/failure state, scoped to the workspace's authorised bindings. Keep the controls. Use `unavailable`, never zero, only when the Dify observation cannot be established; retain the existing document-sync breakdown.
4. Give Dify neutral progress wording and honest post-dispatch stop semantics; retain the richer custom event path.
5. Replace “cited” in the delete confirmation with “recorded source references” or equivalent.
6. Route tenant assistant/readiness/error labels and public recovery metadata through the trusted active brand.
7. Add focused tests for capability-gated visibility, unavailable metrics, Dify cancel wording and custom-brand propagation. Do not add a pipeline chooser or broaden permissions.

This batch is reversible because it changes capability-driven visibility/presentation, not the preserved custom implementations or data.

## G. Separate defects and decisions

### Functional defects for later work

- Public auth branding is inconsistent across login and recovery states.
- Tenant chat/readiness/error strings bypass active brand resolution.
- Dify-specific usage values can be falsely reassuring zeroes.
- Hosted build provenance is not deterministically traceable to one source commit.

### Decisions required from David

1. Approve or reject the minimal server capability profile. A generic plugin framework and a user-facing pipeline switch are explicitly not proposed.
2. Decide whether version comparison should be entirely hidden under Dify or retained as a clearly metadata-only comparison. The current extracted-content promise should not remain.
3. Choose Dify stop semantics: honest **Stop waiting** after dispatch, or no stop control after dispatch.
4. Decide whether a supported Dify parsed-text view is a future product requirement. Do not connect directly to Dify internals merely to preserve the old label.
5. Approve immutable deployment revision labelling as separate operational hygiene.

### Uncertainty retained

Protected owner/member/platform screens were not re-run live, and no mutating control was exercised. Source and policy tracing establishes the behaviour above, but a later acceptance pass should confirm capability presentation for each role and narrow viewport. The mismatch between hosted image labels and later source also means source-only findings must be verified against the next immutable deployment before closure.

**Audit result:** no permanent removals recommended. Prioritise false usage zeroes, unsupported custom-projection controls and misleading Dify cancellation semantics; then correct branding and wording. Preserve the custom pipeline for deliberate, compatibility-checked reinstatement.

## H. Approved cleanup implementation and reinstatement status

**Implementation date:** 23 November 2026  
**Implementation baseline:** `codex/admin-observability-20261121` at `01ff80c25b4634d2e143f0a4521dd4ab36f6544a` in an isolated worktree.  
**Scope:** The approved small reversible batch in section F. The original audit findings and evidence above remain unchanged.

| Finding / preserved control | Implemented state | Reinstatement or availability evidence |
|---|---|---|
| Extracted text | Hidden unless the exact document version has an active, published extraction projection. The API remains permission checked and returns unavailable when no compatible projection exists. | `ActivePipelineCapabilities::documentPresentation`, `DocumentAdministrationResource`, `DocumentVersionResource`, source/version UI gates, and focused content/governance tests. |
| Structured version comparison | Hidden unless both selected versions have published projections with matching extractor and normaliser identities. An incompatible direct API request returns an explicit unavailable result rather than substituting content. | `ActivePipelineCapabilities::comparable`, `DocumentVersionGovernanceController`, `DocumentContentController`, and focused governance/content tests. |
| Dify indexed-segment gauge | Reconnected to cached observations obtained through Dify's supported document-segment API. Page rendering makes no Dify call. Missing or failed observations are unavailable, never a synthetic zero; the observation timestamp and source are exposed. | `DifyKnowledgeClient::segmentCount`, `SyncDifyDocument`, `dify:refresh-usage-observations`, persisted observation fields, `GetWorkspaceUsage`, and usage tests. |
| Current versus historical failures | Current Dify binding failures are reported separately. Historical custom-pipeline failed attempts are unavailable while Dify is active rather than shown as zero. | `GetWorkspaceUsage` response model and owner/admin usage presentation tests. |
| Dify progress and stop semantics | Dify receives neutral “Searching and preparing…” wording. Its post-dispatch action is “Stop waiting”; the UI warns that provider work and cost may continue and discards a late result. The custom pipeline retains its detailed stages and cancellation wording. | Server-resolved run interaction in `ConversationResource`, `ChatWorkspace`, and focused remount/stop tests. |
| Delete wording | Reworded to refer to retrieved passages retained with historical conversation evidence, without calling them claim citations. | `DocumentAdministration`. |
| Tenant branding | Assistant labels, readiness calls to action, public authentication modes, browser title/favicon and generic tenant errors no longer force the Dolved product name. Platform administration remains Dolved. | `RoutedChatWorkspace`, `ChatWorkspace`, `AuthForm`, `KnowledgeReadinessPanel`, generic error/preference components and focused web tests. |
| Custom pipeline | Preserved. No custom route, model, projection, comparison, event presentation, cancellation action, contract, test, recovery asset or historical data was deleted. | Capability results control presentation only; policy checks and preserved implementations remain the reactivation boundary described in section E. |

The new capability profile is server owned and derived from configured processing plus trusted workspace bindings. It is not a user-facing pipeline switch and does not broaden any policy authorisation. Deployment identity and hosted acceptance evidence are recorded in the delivery journal after the immutable build is deployed.

