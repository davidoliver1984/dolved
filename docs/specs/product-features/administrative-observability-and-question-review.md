# Administrative Observability and Question Review

Status: Approved by David on 21 November 2026; requirements, not an implementation or acceptance claim.
Date: 21 November 2026.
Scope: reconcile the hosted single-client Dify pilot with existing Dolved administration, telemetry, usage and audit work. Future multi-client and AWS delivery remain separate.

## Purpose and architectural boundaries

Give operators enough information to keep the service healthy and workspace administrators enough information to understand usage, document processing and gaps in their corpus. Preserve useful questions for controlled review without putting conversation content into ordinary telemetry.

Three distinct records serve three distinct purposes:

1. **Operational telemetry:** allowlisted technical metrics, traces and logs; no prompts, answers, passages, personal details or secrets.
2. **Business usage and audit:** tenant-scoped authoritative events and ledger entries, including controlled actor attribution in the audit store. These are not an alternative tracing backend.
3. **Restricted question review and diagnostics:** explicitly authorised access to conversation content and, separately, opt-in model-input captures. These are sensitive application records, not telemetry.

Related authorities:

- [Observability foundation](../../adr/0012-establish-the-observability-and-telemetry-foundation.md): preserve allowlist-first instrumentation and Collector routing; its opt-in capture exception is the basis for restricted diagnostics.
- [Administration and Tenant Control Plane](administration-and-tenant-control.md): preserve usage-ledger semantics and the separation between tenant usage and platform infrastructure.
- [Conversation and Streaming Domain](conversations-and-streaming.md): conversation ownership is unchanged by administrator status.
- [Document Governance Notifications and Reminders](document-governance-notifications-and-reminders.md): reuse delivery and branding conventions where applicable, without mixing operational alerts with governance reminders.
- [Stock Dify delivery decision](../../adr/0041-use-stock-self-hosted-dify-for-single-client-delivery.md) and [delivery record](../../operations/dolved-dify/DOLVED-DIFY-DELIVERY.md).

This approved specification defines new, explicitly granted review access; it does not silently give workspace administrators access to all private conversations. If implementation requires changing an accepted ownership/security decision, obtain approval for that specific change before implementing it. Do not rewrite historical ADRs or claim unbuilt capabilities exist.

## Reconcile before implementing

Use the current delivery branch, not this draft's source snapshot, to inventory actual routes, permissions, APIs, collectors, dashboards, usage ledgers, alert rules and retention settings. Produce a small keep/extend/defer mapping in the implementation record. Existing verified functionality should be reused, not rebuilt.

The inspected source snapshot contains these Platform Operations screens; retain their responsibilities and navigation:

| Existing screen | Required responsibility |
|---|---|
| `/app/platform/operations` | Global health overview, provisional SLOs, collection freshness and links to detail |
| `/app/platform/operations/telemetry` | Curated global technical signals and protected specialist-console link |
| `/app/platform/operations/alerts` | Active/resolved operational alerts, severity, acknowledgement and delivery state |
| `/app/platform/operations/policy` | Explain effective collection, privacy, retention and alert policy; clearly distinguish read-only policy from editable settings |
| Workspace administration and existing usage views | Tenant membership/governance, usage and cost; add scoped processing summaries and Question review without moving infrastructure consoles here |
| Document library and conversation screens | Remain the authoritative document operations and user conversation surfaces; deep-link only after authorisation |

The current platform overview describes rolling 28-day technical signals as provisional. Preserve that qualification. Do not manufacture an availability SLA from pilot traffic. Source inspection is not verification of the current deployment.

## Roles and visibility

All permissions are enforced by Laravel on reads, filters, exports, jobs and signed download requests. Hiding a tab is not access control.

| Capability | Platform administrator | Workspace administrator | Ordinary member |
|---|---|---|---|
| Global health, dependency status, traces and infrastructure alerts | Yes | No | No |
| Global aggregate usage/cost | Yes, no question content | No | No |
| Individual workspace usage and ingestion status | Explicit workspace drill-down, auditable | Own authorised workspace | Existing document permissions only |
| Workspace question/topic summaries | Only with explicit scoped review grant | Explicit question-review capability and workspace opt-in | No admin summaries |
| Original question and linked answer | Scoped, reasoned support/review grant; never implied by platform role | Explicit review grant under disclosed workspace policy | Own conversations under existing ownership rules |
| Exact model-input diagnostic capture | Time-limited diagnostic grant and authorised capture scope | Only a separately granted diagnostic role, not ordinary admin | No diagnostic console |
| Access and policy audit | Platform security scope | Own workspace audit with sensitive fields restricted | Existing personal activity only |

For the pilot, the current tenant owner receives the initial question-review capability once the workspace opts in. This is derived from tenant ownership, not from a named Dolved account or platform-administrator status. When ownership transfers, the former owner loses this derived review capability unless it was separately granted, and the new owner receives it under the same workspace policy. A platform administrator may also be separately granted workspace review access, but platform status alone never grants question-content access. Revocation must invalidate subsequent reads/exports immediately; re-check queued export authorisation at execution and download. No cross-tenant ranking, staff performance scoring or user leaderboard.

## Metrics and definitions

Every tile exposes its unit, definition, time basis, source, last successful collection, coverage and availability. Current-state gauges are never summed as historical activity. Define rates using explicit denominators; zero traffic means “No observations”, not 100% success.

| Area | Pilot measurements | Authority and limitations |
|---|---|---|
| Health | API/web readiness, Dify reachability, database/storage/queue health, disk capacity, last successful backup | Safe dependency checks and instrumentation; never perform a paid model call as a health probe. Backup success is not proof of restore success |
| Chat delivery | Accepted submissions, started/completed/failed/cancelled/uncertain runs; time to first visible response and completion p50/p95; stream/reload failures | Dolved run lifecycle with one logical run per count; retries/physical provider requests recorded separately when observable |
| Retrieval | Returned passage count, empty retrieval, retrieval latency, effective configuration identity | Dify metadata where available; a configured Top K is not proof of how many passages the model received |
| Usage | Chat/run counts, active-user aggregate where permitted, input/output tokens, embedding usage, model/provider breakdown | Durable ledger and native receipts; mark unavailable fields, do not infer absent tokens are zero |
| Cost | Known priced subtotal, estimates, unknown-cost events, model/rate/currency and rate version | Distinguish estimated costs from provider invoices and approved budgets. Never show an incomplete subtotal as total spend; deduplicate native receipts |
| Ingestion | Uploads, bytes, processing/sync queue depth and oldest age, available/failed/stuck documents, duration, replacement/withdrawal/deletion propagation | Dolved document/version and Dify sync states; never count an uploaded or indexed file as governed/searchable before its lifecycle permits it |
| Corpus improvement | Reviewed unanswered/incomplete questions, feedback, selected subject counts, review status and follow-up document tasks | Human review labels and structured outcomes. “Completed” is not “correct”; abstention is not automatically failure; search miss is not proof the corpus lacks an answer |

Use stable safe failure codes and correlation IDs instead of exception messages. Workspace dimensions belong in authorised application queries; no raw user, tenant, conversation, document or request IDs as metric labels. Trace correlation is separately allowlisted and access-restricted. Infrastructure totals shared across tenants must not be presented as a tenant's allocated resource bill.

## Question review: preserve useful prompts safely

David's requirement is to retain useful question records for error tracking and subject-level corpus improvement. Implement that in a clearly labelled restricted workspace feature, separate from ordinary telemetry.

- Reuse the existing stored user question and answer through an authorised view/reference; do not create an unlimited second transcript archive.
- Workspace-wide review is opt-in, recorded with policy version and effective date, with a clear user-facing notice explaining who can read questions and why. Retrospective review is optional and defaults off. During activation, the tenant owner may explicitly include still-retained questions from up to the preceding 30 days after a clear privacy warning; the selected start time, actor and policy version are audited. Without that selection, review begins at the opt-in time. Enabling or widening retrospective access later requires another deliberate, audited confirmation. Deleted, expired or otherwise unavailable conversations are never restored for review. Explicitly shared feedback can be reviewed within its narrower grant.
- Default the review list to timestamp, safe outcome, feedback/review state and an optional human-assigned subject. Original wording opens through a separately authorised, audited action. Do not expose staff identity by default.
- Preserve original wording when legitimately reviewed: automatic redaction is not guaranteed to remove all personal information. Restricted records must be treated as potentially containing personal or sensitive data. Do not label pseudonymous records “anonymous”.
- Review labels: unreviewed, answered adequately, incomplete, unsupported answer, missing evidence, retrieval issue, question outside scope, technical failure. Record who labelled an item and when; keep labels distinct from model outcomes.
- Subject grouping uses a bounded workspace-managed list and human assignment for the pilot. Start with “Unclassified”; no new model classifier, embeddings pipeline or extra provider calls. Do not use free-form questions or names as telemetry dimensions.
- A reviewer can link an improvement task or document/version reference and mark the review resolved. It must not automatically edit, approve, upload or publish policies. Document governance remains intact.
- Search of original question text is limited to the restricted review feature and authorised workspace. No global content search for platform administrators.
- Topic counts must state review coverage and sample size. Suppress groups below five records in ordinary aggregate summaries; authorised reviewers can still inspect individual records in the restricted feature. Do not claim suppression makes sensitive data anonymous.

## Knowledge gaps within Question review

Provide a **Knowledge gaps** tab for authorised workspace reviewers. It highlights topics where staff repeatedly need information that the available knowledge base may not adequately cover. It is a prioritised review queue, not an automated assertion that a policy does not exist.

### Topic overview

For each human-assigned topic, show:

- Topic name and applicable site/global context; never combine site-specific gaps into a claim that organisation-wide guidance is missing.
- Distinct question/run count in the selected period, number reviewed, number still unreviewed and number confirmed as a corpus gap. Count each logical question once within a topic; disclose that questions assigned to multiple topics can make topic totals overlap.
- Separate counts for incomplete coverage, retrieval problems, unsupported answers, technical failures and out-of-scope requests.
- First/last occurrence, unresolved count, nominated owner, priority and action status.
- Change against the preceding equal-length period only when comparable retained data exists; otherwise show “Comparison unavailable”.
- A drill-down to the authorised question reviews and any linked document/version or improvement task. Do not show raw question excerpts in ordinary dashboards, notifications or aggregate exports.

Default ordering is unresolved confirmed corpus gaps by descending question count, then most recent occurrence. Reviewers may filter by topic, site, date, issue type, owner and status, or sort by explicitly assigned priority. Priority is a human decision, not a fabricated model confidence score. Unclassified questions remain visible in their own queue rather than disappearing from the summary.

### Distinguish candidate gaps from confirmed gaps

A safe structured no-answer outcome, negative feedback or manual flag may create a **Needs review** candidate. A successful response can also be manually flagged as incomplete. Do not infer gaps by keyword-matching answer prose or assume every unflagged answer is adequate.

A reviewer assigns a topic and checks the authorised current corpus and applicable site scope before confirming:

- **Missing content:** required guidance is absent from the applicable corpus.
- **Incomplete or unclear content:** guidance exists but does not adequately answer the need.
- **Retrieval/applicability issue:** relevant guidance exists but was not supplied, including indexing or site-context problems.
- **Answer-generation issue:** the supplied guidance was sufficient but the answer was deficient.
- **Technical failure / outside scope / not a gap:** a different action or no corpus change is appropriate.

Keep “Not yet established” available when evidence is insufficient. An empty retrieval result alone cannot confirm missing content. Show the reviewer, assessment date and supporting document references for a confirmed finding. Review notes are restricted application content, not telemetry.

### Improvement workflow

Use simple states: **Needs review → Confirmed → Action planned → Awaiting verification → Resolved**, with **Not a gap** and reopening supported. Link an owner and a proposed document update/upload task without creating another task-management system. Existing document approval, versioning and publishing rules still apply.

Uploading a document must not automatically resolve the gap. Resolution records the reviewer and evidence that the applicable approved content is available; it does not claim future answers will necessarily be correct. No model call, question rerun or automatic evaluation is triggered by reviewing or resolving a gap. Later reported problems may reopen it.

For the pilot, aggregation and highlighting are automatic once review labels and subjects are assigned; subject assignment and confirmation remain human-led. Automated semantic topic discovery is deferred, not a hidden prerequisite for this feature. The overview explicitly says it reflects recorded and reviewed questions, not a complete audit of the knowledge base.

All Question review permissions, opt-in, audit, retention and deletion rules apply. Small topics may be visible to authorised reviewers in this restricted tab; ordinary aggregate summaries retain the five-record suppression rule. When source records expire or are deleted, remove related excerpts and links and recompute counts from retained authorised records; do not retain content indefinitely through a topic record.

## Exact diagnostic capture

Question review usually needs the stored question/answer and outcome, not every system instruction and retrieved passage. Full model-runtime capture is a separate opt-in diagnostic facility.

Reuse the verified Dify model-runtime capture boundary. Record the exact system/user messages and supplied passages, model/configuration identity, site context, native run/request IDs, usage and finish reason when available. Clearly label it as model-runtime input, not provider-wire bytes. A capture failure must be visible; never reconstruct text and present it as exact capture.

Scope capture to an authorised workspace/run or bounded time window; default continuous full capture off. Capture ownership, encryption, restricted storage, access audit, expiry and deletion are mandatory. Dify's own content-bearing logs, run history, backups and temporary exports must be included in the data map—switching off Dolved capture does not erase them. Routine health collection must never read these payloads.

Existing sealed evaluation evidence remains separately governed and untouched. Evaluation-specific evidence requirements are not a mandate to capture every future staff conversation indefinitely.

## Date ranges, filters and exports

Pilot date presets: last 24 hours, 7 days, 28 days, and custom dates within retained history. Store UTC, display the selected timezone (default deployment Europe/London), and use explicit inclusive start/exclusive end boundaries. Show the timezone and exact period on exports; handle daylight-saving changes correctly.

Platform filters: environment/service, safe failure class, model/provider and, for business usage only, authorised workspace. Tenant filters: date, site, lifecycle/outcome, model, review status and subject. Site is applicability context, not access control. Global/no-site is distinct from unknown/missing context. Disallow arbitrary labels or query expressions.

Export scoped aggregate usage/cost/ingestion as CSV, with the same definitions, completeness markers and authorisation as the screen. Include generated-at time, interval, filters, currency and source coverage. Prevent CSV formula injection and avoid raw identifiers unnecessarily. Cap interactive exports at 10,000 rows; offer an authorised background export above that rather than truncating silently.

No prompt/answer/passage text in ordinary CSV or email exports. Content-bearing diagnostic export requires separate permission, explicit selection and purpose, an audited restricted download, and a short expiry (proposed 24 hours). Never email attachments containing transcripts, use public object URLs or expose credentials in exports.

## Alerts and notifications

Reuse existing alert infrastructure and delivery conventions. Separate platform incidents from workspace document/usage notifications; use one deduplicated incident key, state transitions, acknowledgement and recovery. Acknowledgement records ownership but does not resolve the condition. Do not let an unavailable collector appear healthy.

Proposed pilot defaults, to reconcile with existing rules before activation:

| Signal | Trigger | Recipient/action |
|---|---|---|
| Essential dependency unavailable | Three failed safe checks over at least two minutes | Platform critical; one opening and one recovery notification |
| Disk capacity | Above 80% for 10 minutes warning; above 90% for 5 minutes critical | Platform only |
| Chat technical failures | More than 10% over 10 minutes with at least 20 completed/failed attempts | Platform warning; below minimum volume show counts, not a rate-based alarm |
| Ingestion/sync stalled | No progress beyond the configured stage deadline plus grace; initially 15-minute grace | Platform operational alert; affected workspace sees safe document status |
| Observability unavailable | No expected collection for five minutes | Platform monitoring alert, where an independent heartbeat supports it; otherwise show unavailable explicitly |
| Usage budget | 80% and 100% of the configured period allowance | Authorised budget recipients; disclose incomplete cost accounting |
| Backup overdue | No success within configured schedule plus grace | Platform alert; separate restore-verification status |

Budget notifications are not hard spend enforcement. Any automatic request blocking needs an explicit business rule and must not be introduced through an alert toggle. No automatic quality alarm based on a model's unsupported confidence or guessed answer correctness.

Default delivery: in-app plus email for actionable incidents, digest for repeated non-urgent workspace issues. Respect verified recipients and role removal; deduplicate reminders (proposed at most hourly per active critical incident). Configure quiet hours for non-critical messages; critical operational notifications bypass them. Email contains safe status, severity and an authenticated deep link, never a question, answer, document passage or raw exception. Delivery failures are recorded and visible without recursive notification storms.

## Retention, access and audit

These are proposed pilot defaults for new stores, not permission to purge existing records or override accepted retention contracts:

| Record | Proposed retention |
|---|---|
| Content-free detailed traces and operational logs | 14 days |
| Operational time-series aggregates | 90 days |
| Business usage/cost ledger | Existing accepted policy; expose the actual effective window, do not replace it with telemetry expiry |
| Question-review access window | 30 days, bounded further by source conversation deletion/retention |
| Explicit full model-input diagnostic captures | 7 days unless a separately approved investigation/evaluation policy applies |
| Temporary export artifacts | 24 hours |
| Review/access/configuration/export audit | 400 days proposed; reconcile with existing audit policy before activation |

Question review expiration removes access through this feature and its derived excerpts, not the user's conversation by stealth. Conversation deletion must remove review content/references and diagnostic copies unless a separately authorised retention obligation applies. Retain only approved content-free audit facts. Backup deletion follows the disclosed backup expiry, not a false promise of immediate erasure from backups.

Enforce expiry with scheduled deletion, evidence of success/failure and alerting on overdue cleanup. Avoid indefinite “temporary” server copies. Audit content reads, exports, grants/revocations, policy changes, subject/review edits and capture activation using actor, workspace, action, reason, timestamp and safe identifiers—never the payload being viewed. Audit data itself contains controlled identity information and must not be exported to ordinary telemetry.

## OpenTelemetry and privacy implementation

Continue the existing web/API/queue propagation and Collector architecture. Correlate Dolved and Dify with native IDs where supported; never invent parent/child trace relationships across a boundary without propagation evidence. Use stock Dify configuration/integration, not a maintained patch or replacement pipeline.

Instrumentation must use explicit safe-field allowlists at emission and defence-in-depth Collector filtering. Disable automatic body capture and remove query strings, cookies, authorisation headers, email addresses, filenames, free-text exception messages and provider payloads. Browser error reporting needs the same controls. Do not add conversation bodies to spans, baggage, metric labels, logs or error tracking breadcrumbs. Internal pseudonymous identifiers remain restricted and must not appear on public dashboards.

Telemetry exporter failures cannot fail a user's chat or document operation. Use bounded buffering/timeouts/drop counters; expose data loss and sampling. Usage and audit durability must not depend on sampled traces. Provider/model exceptions become safe typed categories with restricted correlation links.

## UI states

- **Loading:** skeletons with accessible status, no fabricated zeroes or healthy badge.
- **Empty:** distinguish no activity in range, no matching filters and no collection configured; offer the relevant next action.
- **Delayed/stale:** show last successful update and expected refresh interval; retain visibly stale values without implying they are current.
- **Partial:** mark missing sources, models or intervals and cost coverage; no misleading combined total.
- **Failure:** safe message and retry for fetching data only; never replay a chat or ingestion operation to refresh a dashboard.
- **Unauthorised/revoked:** remove restricted content and downloads, with existing concealed-resource behaviour for foreign tenants.
- **Small sample:** show counts and qualification instead of unstable quality claims or percentiles unsupported by the sample.

Support responsive layouts, keyboard navigation, accessible tables/charts and existing Dolved/tenant branding. Platform operations retain Dolved branding; workspace views use the active tenant profile.

## Pilot delivery and deferred scope

Pilot must deliver the existing operations screens coherently, safe tenant usage/cost and ingestion summaries, practical alerts, retention visibility/enforcement, a restricted opt-in question review workflow with manual topics, and bounded diagnostic capture/access. Reuse existing storage and services wherever possible. Missing native data is labelled unavailable, not a reason to build a second analytics system.

Defer automated topic modelling, AI grading, cross-tenant content analytics, per-employee productivity metrics, custom dashboard builders, tenant billing/invoicing, fleet-level SLO commitments, multi-region monitoring, AWS service integrations and full multi-client hosting. Tenant-scoped keys, policies and queries must still be correct now. Do not change Dify prompts, retrieval, documents or evaluation settings as part of this work.

## Acceptance and bounded implementation instructions

1. Record the existing-to-target mapping and explicit policy decisions; preserve screens and already verified behaviours.
2. Verify platform/workspace/member permission matrices including direct API, export and cross-workspace negative cases using synthetic data.
3. Verify safe instrumentation contains none of planted prompt/answer/passage/credential/personal-data test markers, including exceptions, browser events and exporter failure paths.
4. Verify metric definitions, deduplication, unknown cost, date boundaries, no-traffic and delayed/partial states against deterministic fixtures.
5. Verify Knowledge gaps grouping, distinct counts, site separation, candidate/confirmed distinction, unclassified and small-sample states, reviewer resolution and deletion/expiry behaviour. Verify question-review opt-in, no retrospective grant, content-read audit, revoked access, source deletion and expiry; verify diagnostic capture separately from ordinary telemetry.
6. Verify alert opening/deduplication/acknowledgement/recovery, scoped recipients and notification delivery failure with fakes or the established safe acceptance route.
7. Demonstrate the existing hosted model-input capture can be reached only through restricted access and remains correlated without copying it into telemetry. Use retained authorised evidence where sufficient.
8. Demonstrate exports, retention cleanup and responsive/error states; record unavailable integrations honestly.
9. Update the delivery record/tasks with actual completion and remaining work. Do not mark a canonical gate complete merely because this specification exists.

This authorises a specification only. Implementation should follow one agreed delivery scope with ordinary engineering verification, not another answer-quality experiment or open-ended diagnostic loop. No sealed questions, provider calls, infrastructure spending, live alert activation, permission broadening, data deletion or deployment is authorised by drafting this document.
