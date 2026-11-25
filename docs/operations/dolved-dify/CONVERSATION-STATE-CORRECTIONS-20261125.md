# Conversation-state corrections — provider-free handoff

**Date:** 25 November 2026
**Application baseline:** `c80c45256112b7e919298d8123ec954e952aeede` on
`codex/pilot-unified-design-20261125`
**Status:** implemented and focused-tested locally; not committed, pushed, deployed or
published to Dify

This is the bounded correction handoff for the three issues confirmed in
`CONVERSATION-MEMORY-MECHANISM-20261125.md`. It contains no live provider or retrieval
test, no conversation-data repair and no evaluation question. The original sealed
40-question section was not opened or changed.

## Corrections

### 1. Current question appeared twice

**Cause.** The published memory-enabled LLM node renders an explicit user-role
`{{#sys.query#}}` prompt entry, while stock Dify 1.19.0 appends the current query through
its memory prompt-assembly path.

**Prepared correction.** Remove only the explicit user-role prompt entry. The before and
corrected node snapshots are in
`deployment/dolved-dify/workflow-config/20261125/`. This is a structural configuration
change, not arbitrary text deduplication: identical wording in earlier turns is retained.
The system prompt, retrieved context, location input, model, parameters and memory setting
are unchanged. The hosted workflow has not been edited or published.

The provider-free unit check proves the exact configuration diff. It does not exercise
Dify's live prompt assembly. The source path recorded by the preceding audit establishes
why this single removal causes stock Dify to insert the current query once; a later
authorised captured live turn must verify the deployed runtime boundary before freeze.

### 2. Invisible remote history could affect a later turn

**Cause.** Dolved could discard or remain uncertain about a Dify response while retaining
the same remote conversation identity. A later request could then use remote history that
the user could not see.

**Correction.** Dolved now distinguishes preparation from actual dispatch. Cancellation
before dispatch remains safe. Post-dispatch cancellation against established remote
history, late completion after cancellation, and uncertain dispatch mark the conversation
non-continuable. Existing conversations with equivalent durable session/run evidence are
blocked lazily without a remote scan. The conversation remains readable; another message,
retry or direct API request receives the safe user message:

> This conversation could not be safely continued after the interrupted response. Start a new conversation to continue.

No discarded answer is revealed, no uncertain request is retried or replaced, and no
remote history is deleted or rewritten. Unknown usage and cost remain unknown.

The continuation check runs under the conversation lock when a message is accepted, on
retry, and immediately before provider dispatch. Existing idempotent replay returns its
already accepted run and does not create a new turn. The web workspace renders the reason,
disables sending/retry and offers **Start new conversation**.

### 3. A selected location could be retired

**Cause.** The selected location identity was fixed on the conversation, but continued
availability was not revalidated on every new turn and immediately before dispatch.

**Correction.** A retired or unavailable location makes that conversation read-only. Its
historical name remains visible and is labelled **retired**. The user must start a new
conversation and choose an available location. Dolved never changes the conversation to
global or another site, and never infers a replacement from question wording. Existing
membership and source-access checks still govern historical readability. Global
conversations retain the existing global-only rule.

If retirement wins the pre-dispatch recheck, no provider request is made. A request that
has already been dispatched cannot be recalled by changing its label; its real outcome is
retained and later submissions are blocked by the same location rule.

## Files and configuration changed

Application:

- `apps/api/database/migrations/2026_11_25_000001_add_continuation_safety_to_conversations.php`
- `apps/api/app/Services/Conversation/ConversationContinuationPolicy.php`
- `apps/api/app/Actions/Conversation/SubmitConversationMessage.php`
- `apps/api/app/Actions/Conversation/RetryGenerationRun.php`
- `apps/api/app/Actions/Conversation/CancelGenerationRun.php`
- `apps/api/app/Actions/Conversation/OrchestrateDifyConversationRun.php`
- `apps/api/app/Actions/Conversation/ReconcileStaleGenerationRuns.php`
- `apps/api/app/Services/Dify/DifyChatClient.php`
- `apps/api/app/Enums/GenerationRunFailureCode.php`
- `apps/api/app/Models/Conversation.php`
- `apps/api/app/Http/Controllers/ConversationController.php`
- `apps/api/app/Http/Resources/ConversationResource.php`
- `apps/web/src/lib/conversations.ts`
- `apps/web/src/components/ChatWorkspace.tsx`

Focused verification:

- `apps/api/tests/Feature/ConversationContinuationSafetyTest.php`
- `apps/api/tests/Unit/DifyMemoryPromptConfigurationTest.php`
- `apps/web/src/components/ChatWorkspace.test.tsx`

Dify provider-free snapshots:

- `deployment/dolved-dify/workflow-config/20261125/published-llm-node.before.json`
- `deployment/dolved-dify/workflow-config/20261125/published-llm-node.corrected.json`
- `deployment/dolved-dify/workflow-config/20261125/README.md`

## Schema and release requirements

The additive migration adds nullable `continuation_blocked_at` and
`continuation_block_reason` fields to conversations. New divergent-history findings are
marked durably. Older affected conversations can also be denied from existing durable
run/session evidence without mutating them or scanning Dify.

Required release sequence:

1. Back up the application database and export the currently published Dify workflow.
2. Apply the additive database migration.
3. Deploy API, worker, scheduler and web from the same reviewed application revision.
4. Through supported Dify controls, remove only the explicit user-role `sys.query` prompt
   entry and publish once.
5. Read back/export the configuration and run provider-free application/API/UI checks.
6. Only under separate authority, capture a live clean turn at the model-runtime boundary
   before the evaluation freeze.

Do not roll back the migration while code expecting these columns is running. An
application rollback may retain the nullable columns. Reverting the Dify correction means
restoring the original single user-role prompt entry from the before snapshot; it does not
require changing memory. Conversation safety markers already recorded should be retained,
not cleared as part of code rollback.

## Provider-free verification

- Laravel focused checks: **14 tests, 89 assertions — passed** (including the existing
  workspace/idempotency/single-active-run boundary).
- Web workspace focused checks: **24 tests — passed**.
- ESLint for changed web files: **passed**.
- PHP Pint for changed PHP files: **passed**.
- Configuration diff verifies that only the explicit user-role current-query entry is
  removed; earlier identical wording is not rewritten.
- Fake HTTP transport proves generation submission still receives the exact latest
  question and clean Dify sessions retain their remote conversation identity.
- Feature coverage includes queued and prepared pre-dispatch cancellation, post-dispatch
  cancellation with a discarded late answer, transport uncertainty, lazy legacy evidence,
  direct-API denial, new-conversation isolation and retired-location pre-dispatch denial.
- Web coverage includes reload-safe read-only presentation, retired-location labelling and
  the start-new-conversation action.

The ordinary Next production build could not be completed in this local execution
environment: Google Font fetching is unavailable without network, Turbopack cannot bind
its internal port, and the webpack fallback did not complete in a reasonable bounded
time. No source compile error was emitted. Focused tests and lint passed, but a normal
release build remains a deployment preflight requirement.

An older broader backend selection also encountered existing host-specific tests with
hard-coded `/contracts/...` paths absent from this worktree. The changed Dify boundary and
new safety tests pass; this batch does not claim that unrelated host-fixture limitation is
resolved.

## Confirmed unchanged

- Retrieval query construction: the exact latest question only, with no history or rewrite.
- Dataset and LLM-node Top K `10`, score threshold, hybrid weights, ranking and reranking.
- Embedding and generation models and generation parameters.
- Corpus, document text, metadata, approval/effective state and location assignments.
- Substantive system instructions, answer-quality rules and newly retrieved context.
- Memory enabled state and record window size `10`.
- Global/site retrieval filters and the immutable selected-location identity.
- Custom-pipeline behaviour and recovery assets unrelated to these defects.
- Sealed evaluation content.

## Memory and evidence semantics for Claude

Dify window size `10` counts Dify message database records. The current incomplete record
occupies one selected slot and is removed from history, so generation receives at most
nine completed prior turns, normally expanded to 18 chronological user/assistant prompt
messages. Dify's token budget may shorten that further and there is no summary preserving
older turns. The current question is supplied separately. Previous retrieved passages and
source lists are not replayed; each turn retrieves afresh using only its latest question.

For each completed local turn Dolved retains the user message, visible assistant message,
generation run and correlation ID, provider message/task identifiers when returned,
remote Dify conversation identity, generated-answer lineage, retrieved-source references,
usage when reported, dispatch state and delivery events. The previously verified runtime
capture mechanism can record Dify's assembled prompt sequence, but its retained capture
predates this prepared correction and cannot prove the corrected live prompt.

## Local versus hosted and remaining work

All changes in this record are local and uncommitted. The hosted application remains on
the baseline revision and the hosted Dify workflow remains the published version with the
duplicate prompt entry. No migration, deployment, Dify publish, provider call, retrieval
call, remote mutation or spend occurred in this batch.

Before the live evaluation freeze: review and commit the batch; complete the release
sequence above; verify provider-free readback and UI/API behaviour; then obtain separate
authority for one captured live memory smoke/evaluation path. Reconfirm the 11-document
inventory and frozen retrieval/configuration settings after deployment. Claude may prepare
the separate sealed memory section from these stated semantics, but should not execute or
freeze it until the hosted correction and captured prompt boundary are verified.
