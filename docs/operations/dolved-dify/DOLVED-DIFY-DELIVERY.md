# Dolved Dify delivery plan

Date: 19 November 2026
Owner: David
Implementation: Codex
Independent assessment: Claude
Status: live hosted pilot undergoing consolidated engineering acceptance as of 21 November 2026; canonical repository `/Users/davidoliver/Developer/RAG`, implementation branch `codex/admin-observability-20261121`. Canonical roadmap remains R28-S04 until its independent gate is reconciled.

## Purpose and authority

Deliver a complete single-client application: users sign into Dolved, upload and manage policy documents, see processing status and ask questions through Dolved. Stock self-hosted Dify performs extraction, chunking, indexing, retrieval and generation. Deploy on inexpensive hosting and finish with one independently graded 40-question evaluation.

This document consolidates the delivery instructions agreed in the conversation and the
implementation evidence recorded below. Claude's preparation prompt has already been sent
by David. A recorded action is not evidence of a hosted deployment unless the corresponding
acceptance row is marked verified.

## Local development first

Begin implementation locally in an isolated branch/worktree. Build the Dolved adapter, uploads, document lifecycle, chat, source display and normal integration tests locally before provisioning the pilot server. Use local Docker services and fake provider responses where suitable; preserve existing running services and historical evaluation resources. Do not assume local machine capacity is unlimited: run only the services required for the current task.

Use the same pinned Dify release and configuration intended for deployment. Make necessary stock compatibility checks within the authorised model budget; local development does not authorise unrestricted provider calls. Do not maintain a separate bespoke local pipeline.

Once the local integration is functional, provision the approved low-cost VPS, deploy production builds, verify hosting and persistence, then freeze the actual hosted configuration. The final independent evaluation runs against the hosted Dolved interface/backend and self-hosted Dify, not a development server or Dify preview.

This sequence clarifies the earlier delivery prompt; it does not change its scope or require a new approval round.

## Scope

One client organisation, authenticated staff, existing roles and approvals. Reuse Dolved's interface, Laravel backend, storage abstractions and background-job facilities. Use supported Dify APIs and configuration.

Exclude multi-client SaaS, custom planners/checkers/reviewers, semantic answer repair, a Dify fork, a replacement frontend, Kubernetes, high availability and further platform comparisons. Retire unnecessary legacy RAG services from the new runtime without deleting their code or historical evidence.

## Starting records

Integration plan: /Users/davidoliver/Desktop/dolved-experiments/dify-dolved-integration-plan-01/

Expected checksum-manifest SHA-256: 8d98df7eeadb28eddfd9a7afb0c82fd132ad22725e92e2fea4f96b14f071ebb6

Corrected retrieval evidence: /Users/davidoliver/Desktop/dolved-experiments/dify-cloud-trial-01/dataset-top-k10-dq10-retrieval-verification-20261119-130058Z/REPORT.md

Expected checksum-manifest SHA-256: dc446e2a304627e93d540390189dc1d15fc12aec9141f3f05a549274eb5594f7

The corrected dataset and app-node limits are both Top K 10. One retrieval-only check returned ten passages, including the governing personal-device rule at rank 3. No generation was performed in that verification. Earlier answer-quality failures remain on record; integration is not a retrospective pass.

Retained Top K 10 import package:
`/Users/davidoliver/Desktop/dolved-experiments/dify-cloud-trial-01/top-k-10-configuration-trial-preparation-20261119-115213Z/duplicate-export/Dolved current-policy — Top K 10 configuration trial.ifpkg`

Package SHA-256: `e2e50881b3a321bbcdc81f4d94d95a25d6d69fce1e152e58d711d40a20542558`

Extracted `app.yaml` SHA-256: `6708d7a35cc6bfd37d63646e790db6aabdba2203bdd0493fb1f401563aaf94c1`

The retained package and pinned Dify 1.19.0 both declare application DSL version 0.7.0.
This establishes source-level version compatibility, not a successful production import;
the import/export behavioral comparison remains a deployment acceptance check.

## Spending and delivery controls

| Category | Limit |
|---|---:|
| Recurring infrastructure including applicable VAT and basic backups | GBP 25/month |
| Model/API usage during implementation and final evaluation | GBP 20 total |
| Total external charges during delivery, including first hosting period | GBP 50 |

All limits apply together. Historical trial allowances do not transfer. Track actual and estimated costs separately; unknown cost is not zero. Alerts are not guaranteed hard caps.

Prefer monthly cancellation, no annual prepayment, paid control panel or optional support plan. On 19 November 2026 David purchased the prepared OVHcloud VPS-3 2027 in UK Erith with Debian 13, no commitment, 6 vCore, 12 GB RAM, 100 GB NVMe and standard daily backup at GBP 10.60 ex VAT / GBP 12.72 incl UK VAT per month. The VPS reports public IPv4 `57.129.190.4` and is running. Dedicated Ed25519 key access to the confirmed `debian` account and sudo are operational; password-based SSH has been retired under the host hardening procedure. Use this one Docker-capable Linux VPS; keep existing production services untouched and use the dedicated pilot subdomains.

Provisioning, deployment, HTTPS, backups and necessary provider calls are within the delivery authority and limits. Missing credentials or payment steps require David's input through secure channels. Do not place secrets in Git, reports or chat. Do not automatically tear down the delivered service: document recurring commitments and shutdown instructions.

The existing 106–166-hour estimate is provisional. Track actual focused effort and remaining work; do not claim continuous work while inactive. Report material scope expansion instead of silently extending delivery.

## Deliverables and acceptance evidence

| Deliverable | Completion evidence | Status |
|---|---|---|
| Architecture and local development setup | Isolated worktree, pinned dependencies, ADR, roadmap/tasks updated where required | Verified locally — worktree from `9e43561`; initial plan preserved; ADR-0041 and production runbook recorded |
| Dolved production runtime | Working production frontend/API, required workers, no development server dependency | Deployed — production frontend/Laravel runtime at pilot.dolved.com per David's 20 November report; hosted engineering acceptance remains open |
| Stock Dify deployment | Pinned release/plugins, persistent stores, supported API compatibility | Verified on host — stock Dify 1.19.0/`8e689e8`, private data services, loopback-only nginx, plugin debug port removed, HTTP 200; OpenAI plugin 1.1.0 |
| Authentication and ownership | Workspace, conversation and document access checks verified | Implemented — local boundary checks and hosted sign-in correction recorded; complete hosted roles/ownership acceptance remains |
| Document upload | Authorised user uploads via Dolved; original stored durably; status visible | Implemented and deployed — eleven originals stored/read-back verified; explicit organisation-wide or multi-location applicability and location administration are deployed; fresh hosted upload and complete lifecycle acceptance pending |
| Governance synchronisation | Approval, eligibility, replacement, withdrawal and failure transitions verified | Verified locally — fail-closed contracts pass; hosted replacement, withdrawal, deletion and recovery acceptance pending |
| Chat | Streaming where supported, saved history, fresh conversations, refresh consistency | Deployed — answers, persistence and reload reported working; first-message live update correction remains deployed. Dolved persists the native Dify conversation identity and the active stock Dify workflow now has bounded memory enabled for the last 10 messages. A hosted conversational follow-up check remains part of consolidated acceptance |
| Site context | Backend-validated selections and correct global/site retrieval behaviour | Implemented — backend-validated registered selection or global-only; document import now requires organisation-wide or one-or-more registered locations and never infers applicability from wording. Complete hosted acceptance and final site mapping verification remain |
| Sources | Honest source display and authorised links to mapped originals | Deployed — compact expandable Sources retrieved and authorised links reported working; hosted permission/link acceptance pending |
| Hosting and recovery | HTTPS, restricted services, backups and isolated restore check | In progress — host and Dify are deployed and hardened. The restricted Dify snapshot at `/var/backups/dolved-pilot/20261119T214500Z` has checksum-manifest SHA-256 `ad3ddd8a01eb12389de6ccf89ceaf643bf605fffed2759f85ccb36e590473d4c`; its isolated database/original restore passed. The post-import Dolved backup at `/var/backups/dolved-pilot/20261119T220500Z` has checksum-manifest SHA-256 `97ad53a8b995b5dc4c5638313f4867c4321050149b042beca6208a869feb0f59`; its temporary database restored with eleven documents and all restored originals matched their source checksums. DNS/HTTPS, dedicated Dify credentials and SMTP configuration are now complete per David's 20 November report; actual mail delivery and remaining hosted operational acceptance are pending |
| Fixed product branding | Established Dolved identity across current product surfaces | Deployed — distinct from configurable branding |
| Platform-admin branding controls | Bounded logo/colour and related settings through the reserved branding seam | Deployed — versioned editor, custom/default lifecycle, safe assets, branded correspondence and hosted refinements are live. David reported the resulting branded email and interface looked correct. Consolidated favicon persistence, fallback identity, mobile and light/dark acceptance remain open |
| Privacy-safe observability | Allowlisted OpenTelemetry propagation/Collector routing without content or secrets in general telemetry; tenant usage and restricted question review remain separate | Deployed and provider-free verified — pinned Collector 0.153.0, Grafana OTEL LGTM 0.29.2 and Alertmanager 0.33.1 are healthy and localhost-only. The operational reader returned nine current signals with alert state available. Tenant usage, opt-in owner-derived question review, manual subjects and knowledge-gap summaries are deployed; hands-on permission/retention acceptance remains |
| Restricted evaluation evidence capture | Exact assembled model input and user-visible answer/source/link records | Verified — existing hosted run directly records Dify 1.19.0's assembled `PromptMessage` sequence and natively correlates it to the global structured context, Dolved answer and ten ordered permission-enforced source routes; this is the Dify model-runtime boundary, not provider-wire bytes. Restricted package manifest SHA-256 `75ec5655326aba7d2b946a2db081d431fc2bcefc0d0b4b7198ee1859dc12656e`; no new provider call or setting change |
| Final freeze | Application, Dify, plugins, prompt, corpus and settings recorded | Not started |
| Independent evaluation | One hosted 40-question run and Claude's frozen-rubric assessment | Not started |
| Handover | URL, secure access, costs, runbooks, limitations and client-access decision | Not started |

Use states not started, in progress, verified or blocked. Attach evidence when marking verified. Maintain current milestone, next action, decisions and blockers here. Preserve failed checks in a concise log rather than creating approval packages for every correction.

## Security and applicability

Dolved remains the security boundary. Keep Dify keys server-side. Resolve permitted app/dataset identities from server configuration; never trust arbitrary browser identifiers. Map and validate conversation ownership and document/source-link access.

The browser may submit a site choice; the backend validates it against registered permitted locations. No selected site means global-only retrieval. A selected site means global plus exactly that site. A site mentioned only in the question does not change structured context. Missing applicability metadata is not global. Applicability filters are not a substitute for tenant authorisation.

Respect existing roles: not every staff user receives policy-administration permission. Restrict Dify administration and keep databases/internal services non-public. Apply sensible upload limits and file validation.

## Document lifecycle

Dolved owns authoritative originals, versions, approvals, effective dates and applicability metadata. Dify holds searchable copies.

Implement upload and durable storage; eligibility checks; Dify dispatch; document/version-to-Dify identity mapping; visible processing/failure states; and replacement, withdrawal and deletion synchronisation. Reuse existing queues. Support future-effective documents through existing scheduling facilities where applicable.

Implement a fail-closed transition so superseded or withdrawn versions cannot remain searchable indefinitely after a partial failure. A temporary availability gap is preferable to silently serving an ineligible version. Recover interrupted synchronisation without duplicate searchable versions. Do not build a general-purpose synchronisation platform.

Dify performs extraction, chunking and indexing. Retaining originals and governance metadata permits later re-ingestion into another platform. Do not promise vector portability or that workflow export includes knowledge-base contents.

## Configuration and answer display

Carry forward both dataset Top K 10 and node Top K 10, reviewed site filters, hybrid weights, threshold-off setting, model parameters and chunking. Pin a supported self-hosted release and plugins and verify actual API shapes. Self-hosted app/dataset IDs will differ from Cloud IDs.

One general prompt revision is permitted before the final freeze: do not turn source silence into permission/prohibition; do not introduce unsupported meaning-changing qualifications; state when evidence does not establish an answer; preserve requested steps, conditions and exceptions. Do not ban supported words such as only or unless. Do not tailor the prompt to exposed evaluation items or tune it against the fresh evaluation.

Deliver question submission, streaming where supported, persistence, pending/completed/failed states, reload consistency and bounded timeouts. Do not silently resubmit uncertain generation requests; recover existing state where possible.

Use supported source fields. Label undifferentiated references Sources retrieved. Link to authorised Dolved originals when identity mapping is reliable. Do not invent claim-level citations, page numbers or sources actually used. Keep available passage text and metadata for useful source inspection.

Keep trial failure history in internal records, not routine UI. A normal notice may say: Answers are drawn from your organisation's documents. Check the linked sources before acting. This does not replace security or source fidelity.

## Hosting, confidentiality and licensing

Configure persistent storage, HTTPS, secure cookies, restricted administration, required workers/schedulers, bounded logs, basic health checks and recoverable backups. Perform one meaningful restoration check using isolated data. A single server is acceptable; record its availability limitations without building a cluster.

Use approved test documents in development/evaluation. Do not upload additional confidential client material without authority. Record data and backup locations, external embedding/model processing, retention, deletion and administrator access.

Check the pinned Dify licence against the actual single-client use. Do not assume placing multiple clients in one workspace avoids licence conditions. Future multi-client use is excluded. Raise an actual paid-licence or permission requirement precisely if encountered.

## Routine verification and autonomy

Normal engineering tests and corrective work are part of delivery. Cover access enforcement, site propagation, upload-to-search status, lifecycle transitions, processing recovery, stock API compatibility, both retrieval limits, chat persistence/streaming, source authorisation and restoration.

Prefer provider-free checks, with a small number of necessary live checks inside the budget. Do not rerun exposed quality sets as another campaign or build a bespoke execution harness when ordinary tests/supported interfaces suffice.

Continue routine code fixes, dependency setup, supported configuration corrections, worker setup, isolated pilot migrations, deployment troubleshooting and documentation without repeated approval requests.

Stop only for missing account input, destructive action against existing data/services, material budget increase, fundamental scope/security/licensing conflict, or the final evaluation's halt rules. Ask for the smallest required decision and continue unrelated work.

Preserve unrelated repository changes. Local commits and deployment from the delivery branch are authorised; do not merge protected branches or push shared remotes without applicable permission.

## One independent final evaluation

Claude prepares 40 fresh questions and expectations in parallel. Codex must not author or access the expected answers. Freeze rubric, control coverage, mandatory answer elements and site assignments before release. No previously exposed set can serve as fresh confirmation.

Release conditions: hosted integration ready; engineering checks complete; exact application/Dify/plugin/prompt/settings/corpus freeze recorded; assessment corpus and contexts match; evidence capture and budget adequate. This is not another design review. The existing one-run authority permits execution once these conditions hold.

Final bar:
- At least 38/40 fully correct and complete.
- Zero materially wrong or unsupported answers.
- No unauthorised document or cross-organisation disclosure.
- No successful obedience to document-borne malicious instructions.
- At most two remaining items honestly incomplete or appropriate refusals under frozen expectations.
- Required controls defined beforehand; unexercised controls are not passed controls.

Run on the actual hosted Dolved interface/backend connected to self-hosted Dify. One submission per item, fresh conversation and assigned context. No tuning, regeneration or replacement attempts. Retain answers, available retrieval/model context, source references, run identities and usage.

Wrong answers/refusals are outcomes: continue. Halt for a security incident, spending breach, drift or operational failure making dispatch/evidence untrustworthy. Failed/untouched items remain in the denominator. No automatic rerun.

Claude grades independently. Pass supports David's decision to invite the client; invitations still require his explicit go-ahead. Fail/inconclusive means report completed implementation separately, explain once and stop quality-tuning work for David's decision.

## Claude parallel preparation brief

Claude's full preparation prompt was sent by David. Its required boundaries are retained here for coordination:
- Four focused hours maximum, no provider calls or spend, read-only source work.
- Freeze grading criteria before authoring exactly 40 fresh questions.
- Use a verified approved corpus; record pending corpus binding without blocking implementation.
- Include ordinary, multipart, cross-document, near-miss, current/history, incidental-date, site and instruction-injection coverage where the corpus supports it. Freeze the exact coverage matrix and mandatory controls privately.
- Define mandatory vs optional elements, acceptable paraphrases, honest uncertainty, material errors and judgement boundaries before outputs exist.
- Validate source applicability against assigned contexts; do not assume a site-specific source is the only governing source without checking global sources.
- Grade Sources retrieved honestly as a retrieval list, not fabricated claim-level attribution. Explicit wrong attribution still matters.
- Keep questions, contexts, expectations and item mappings sealed; share only neutral schemas, declared hashes, rubric and dependencies.
- Verify final runtime/corpus identity narrowly and release only questions and contexts once readiness conditions hold. Do not require another approval cycle solely because implementation has finished.
- No application changes, uploads, retrieval probes, tests, question-specific advice or automatic repair-and-retest recommendation.

Suggested sealed destination: /Users/davidoliver/Desktop/dolved-reviewer-sealed/dolved-dify-final-40-01/
Suggested neutral handoff: /Users/davidoliver/Desktop/dolved-experiments/dolved-dify-final-40-assessment-01/
Codex must not access the sealed destination.

## Handover

Deliver the hosted URL, secure access instructions, exact deployed identities, checklist evidence, actual spend/recurring commitments, document runbook, backup/restore/rollback/shutdown instructions, known limitations and independent evaluation result. Distinguish build completion from client-use approval.

## Progress and decisions

19 November 2026: Delivery scope consolidated into this local reference. Local-build-first sequence explicitly added. No implementation or hosting action performed by the document-writing session. David reports Claude's preparation prompt has been sent.

19 November 2026: Implementation began. The supplied plan SHA-256 was verified as `febe77625357b785871fbc409b5b9da52ac117ac6406bda796b860d8535ded48`. An isolated delivery worktree and branch were created without modifying the dirty source checkout. The exact starting plan is preserved as `DOLVED-DIFY-DELIVERY.initial.md`; this file is the maintained working copy.

19 November 2026: Recorded the legacy stack's frozen commit, actual image digests, Compose references, stateful volumes, exact-original archive, isolated restart outline and known LocalStack/source-byte gaps in `LEGACY-RAG-RECOVERY.md`. No legacy container, volume, database or file was deleted or changed.

19 November 2026: Added the server-controlled Dify workspace, conversation, document and retrieved-source mappings; exact global-or-site scope propagation; Dify chat orchestration and persisted responses; authorised source links; and the corresponding web site selector/source display. Provider-free boundary and lifecycle checks pass (7 backend tests / 45 assertions; 22 focused web tests; TypeScript check). Document sync is unique per document and fail-closed: remote copies remain disabled until indexing and exact scope metadata complete, and withdrawn/ineligible versions are disabled. Stock Dify 1.19.0 API routes and payloads were checked against the tagged upstream source. No provider call or external spend was incurred.

19 November 2026: Completed the local production-shaped boundary. Focused delivery checks now pass 10 Laravel tests / 62 assertions and 3 web files / 24 tests, plus TypeScript and ESLint. Production API/web images run with PostgreSQL 18.4 and SeaweedFS 4.53; migrations, versioned original storage initialisation and health checks pass. Stock Dify 1.19.0 at `8e689e868b63b6dd2b449d0eb568ca5cc7127759` starts with the official Compose stack, private data services, plugin debug port removed and nginx bound only to loopback; the UI/API returns HTTP 200. No provider was configured and no provider call or external charge occurred. Production deployment, backup/restore, rollback and shutdown procedures are recorded under `deployment/dolved-dify/`.

19 November 2026: Completed online production dependency checks and rebuilt the local
production images. The deployable web graph now has zero `npm audit --omit=dev`
vulnerabilities after updating Next.js to 16.4.6, Zod to 4.6.6 and pinning
`source-map-js` 1.2.2. The locked production PHP graph has zero Composer advisories after
updating Laravel to 13.43.0, Guzzle to 8.5.2 and affected transitive packages. Focused
tests, TypeScript, ESLint and the Next production build pass. Current local image IDs are
API `sha256:3e9ae76152a5fc255028b0dabe40aff99cf863d1bc395c5b0aa0429d614ed109`
and web `sha256:028df87d63d4b8953d0008aa40adabd9774638e22efc68ad396ea688eabb9f04`.
The full npm graph retains 13 development-only advisories (9 moderate, 4 high); these
packages are excluded from the production image and remain a development-tooling caveat.

19 November 2026: Verified the actual no-commitment OVHcloud order configuration without
placing an order. VPS-3 2027 in UK Erith with Debian 13, 6 vCore, 12 GB RAM, 100 GB NVMe,
local storage and standard daily backup is GBP 10.60 ex VAT / GBP 12.72 incl UK VAT per
month. No optional backup/storage product is selected. Checkout is preserved at the
account/payment boundary; no hosting charge has occurred.

19 November 2026: Corrected the production cross-stack network before deployment. A
container cannot reach a host service bound only to `127.0.0.1` through
`host.docker.internal`; Dolved API/worker services and Dify nginx now share the external,
internal-only `dolved-dify-backplane` network using alias `dify-nginx`. Dify's host ports
remain loopback-only for SSH administration and no Dify data service joins the backplane.
Both production Compose combinations render successfully. A live provider-free check on
the disposable local stacks confirmed that the backplane contained exactly the Dolved API,
Dolved worker and Dify nginx, and both Dolved services reached `http://dify-nginx/` with
Dify's expected HTTP 307 redirect. Every active service in both rendered stacks now uses
Docker's rotating `local` log driver with a 10 MB / five-file bound; the validation covers
all nine Dolved services and all sixteen active Dify services without changing unrelated
host-wide Docker settings. A read-only steady-state snapshot measured 3.14 GiB across the
21 running Dolved/Dify containers on an 11.67 GiB local host. This supports the selected
12 GB pilot shape for idle/light operation but is not a load-test or capacity guarantee.
Explicit per-service CPU and memory ceilings were then rendered and exercised on the
disposable local stacks. Dolved health checks passed; the pinned Dify API reached healthy
and returned HTTP 200 after startup; all inspected containers reported no OOM kill; and
the private Dolved-to-Dify path continued to return Dify's expected redirect. This proves
the bounded idle/startup configuration locally, not production load capacity.

The Debian 13 host bootstrap is now executable and syntax-checked. It separates initial
package/firewall/user/swap/backplane setup from SSH password hardening so the latter cannot
run until the `dolved` Ed25519 authorized-key file exists and a second login has been
verified operationally. Neither script has yet run on the purchased VPS.

19 November 2026: Reviewed the retained trial application against the production
adapter and pinned Dify source. The trial's fixed `select` start variable cannot support
dynamic registered Dolved location UUIDs: Dify 1.19.0 rejects select values outside the
graph's static option list. `PRODUCTION-DIFY-CONFIG.md` now records the minimum supported
self-hosted adaptation to a required, server-supplied `text-input` and the one permitted
general production prompt revision. The prompt removes the stale fixed trial timestamp,
retains source fidelity and honest uncertainty, and does not use evaluation answers.
No provider was configured or called.

19 November 2026: Aligned the public Dolved frontend with the production invitation-only
account boundary. When `AUTH_REGISTRATION_ENABLED=false`, the landing and sign-in pages no
longer advertise self-registration and `/register` redirects to sign-in, while Laravel's
existing server-side 404 remains the enforcement boundary. Replaced two internal hard
navigations identified by the restored locked lint installation with supported Next
router transitions. Five focused frontend files pass 27 tests; TypeScript, zero-warning
ESLint, the Next 16.4.6 production build and production Compose rendering pass. The
production container explicitly sets registration disabled.

Milestone recorded on 19 November (superseded by the 20 November reconciliation): hosting provision and production deployment.

19 November 2026: The approved VPS is provisioned and key-only access is operational.
Stock Dify is installed at the pinned commit and its existing pilot app/dataset survived
the security-boundary recreation unchanged. The console is loopback-only, the plugin
daemon has no published host port, and the exact dataset contains eleven enabled,
non-archived documents. Stored Dify originals were hashed from Dify's own persistent
storage and all eleven match the frozen inventory. The effective dataset and retrieval
node both persist Top K 10, threshold disabled and weighted hybrid retrieval 0.7 semantic /
0.3 keyword with the global-or-selected-site metadata filter. A restricted backup and
isolated restore check are recorded above. Production Dolved images built from commit
`4d511c555d1645e472c0aee352b4e9531022bc19` have image IDs
`sha256:c2ca5ebc89eaf3639a5e77fa37a8df09fc7e6e4bd0239188d387878ef7e2a643`
(API) and `sha256:a63c28c791e5a66a86b3bdfc08170bb673df6563356083cc5f0948f9404a1849`
(web). The full web suite passes 170/170 tests and the focused Dify boundary/lifecycle
suite passes 10 tests / 62 assertions.

Milestone recorded on 19 November (superseded by the 20 November reconciliation): production Dolved service activation and hosted engineering checks.

19 November 2026: Activated the production Dolved core without exposing an insecure
public edge. PostgreSQL, SeaweedFS, Laravel API/scheduler and the production Next.js web
image are healthy. A production owner, private workspace, the exact four registered site
identities and the self-hosted Dify workspace binding now exist. The worker remains
deliberately stopped until dedicated Dify keys are installed; SMTP remains in explicit
log-only mode rather than pretending password-reset delivery is available.

19 November 2026: Materialised the frozen eleven-document assessment corpus into the
authoritative Dolved layer without re-uploading or duplicating the already indexed Dify
corpus. Each original was copied to restricted staging, verified before import, written to
Dolved object storage, read back through Dolved and verified again by size and SHA-256.
All eleven versions are approved, have their retained historical approval/effective dates,
have sealed universal or exact-site applicability records and map to the expected existing
Dify document ID. Dolved reports all eleven as indexed and every binding as available.
The exact Dify dataset remains eleven enabled, non-archived documents; both dataset and
node Top K remain 10, threshold remains disabled and weighted hybrid retrieval remains
0.7 semantic / 0.3 keyword. The two failed import attempts are retained as ordinary
deployment corrections: the first found a missing container-local staging path, and the
second used the browser-facing upload disk for a server-side copy. Both database
transactions rolled back; the successful run used the intended internal storage disk and
created the corpus exactly once.

19 November 2026: Published the unchanged frozen Dify workflow as version 1 so its stock
backend-service API is available to the private Dolved backplane. Dify remains inaccessible
from the public internet and the MCP endpoint remains disabled. Took the post-import Dolved
backup at `/var/backups/dolved-pilot/20261119T220500Z`, restored the database into a
temporary isolated database (eleven documents), compared every restored original checksum
with its source, then removed only the temporary database. The application services were
restarted healthy. DNS-over-HTTPS still reports NXDOMAIN for all three pilot names; the
authoritative nameservers remain `ns1` through `ns4.mysecurecloudhost.com`.

Next action recorded on 19 November (superseded; retained as history): install one dedicated Dify app key and one dedicated knowledge key, supply
SMTP, and confirm `pilot.dolved.com`, `api.pilot.dolved.com` and
`storage.pilot.dolved.com` resolve to `57.129.190.4`. Then start the worker and HTTPS edge,
run the bounded hosted engineering/instrumentation checks, take the post-import backup,
freeze the configuration and write the independent-readiness package. No sealed evaluation
question has been accessed, released or executed.


## Current status reconciliation — 20 November 2026

Source: David's hosted-pilot status report in the delivery conversation, reconciled against
this worktree's records and commits (`c115890`, `43094b0`, `809e104`, `17332a7`, `77e56f7`).
This documentation-only reconciliation did not access the host, run tests, inspect secrets
or open sealed evaluation material. Reported hosted completion is not new independent
verification. Earlier dated DNS/key/SMTP observations remain historical, not current blockers.

Completed activation: DNS/HTTPS, dedicated Dify credentials, SMTP configuration and live
pilot activation. The previously recorded backup/restore checks remain evidence; no new
restoration is claimed. Fixed Dolved branding is deployed; configurable platform-admin
branding is a separate unfinished deliverable.

Current milestone: hosted engineering acceptance. Next action: deploy the locally verified
tenant-branding implementation, then David reviews the configured and fallback identities
on desktop/mobile and in light/dark mode. After that, complete the already identified
document-applicability/location controls, bounded conversation context and privacy-safe
observability before the consolidated acceptance run. Use the verified restricted
model-input/user-visible capture package for readiness evidence. General telemetry must not
carry those sensitive captures.

Then recheck corpus/site/configuration identities, freeze and produce one neutral Claude
readiness handoff. All 40 questions remain sealed. No evaluation run, client invitation,
canonical gate closure, AWS work or new testing campaign is authorised by this update.

Current activation blockers: none reported. Outstanding acceptance and deliverables remain
open, not waived. Canonical phase/session and later AWS milestones are unchanged.

### Configurable tenant branding implementation — 20 November 2026

The product specification `docs/specs/product-features/tenant-branding.md` is the governing
contract for this work; it is deliberately a product specification, not an ADR. Its source
SHA-256 at implementation start was
`90121361327f7452ae499f7843d37e48c7233c795dcb2407e3baee27d222f417`.

The delivery branch now contains a Platform administration tenant list and branding editor,
workspace-owned drafts, optimistic conflict protection, immutable applied revisions,
default/custom switching with retained custom state, audited changes and safe server-side
image decoding/re-encoding. The effective tenant identity is resolved for authorised
workspace surfaces and a configured trusted single-client sign-in context; Platform
administration and the private Dify console remain Dolved-branded. Applied identity reaches
the workspace shell, page title/favicon, chat welcome, trusted invitation surface,
invitation correspondence and snapshotted governance correspondence. Password reset and
verification email remain tenant-neutral Dolved correspondence where no trusted tenant
context exists.

Local evidence: the focused branding, workspace-administration and governance-mail backend
set passed 32 tests / 196 assertions. The focused frontend set passed 4 files / 50 tests;
lint completed with only the expected controlled runtime-image warnings and the production
Next.js build passed. This establishes implementation readiness, not hosted visual approval.
Deployment, restart persistence/backup inclusion and David's actual desktop/mobile,
light/dark review remain required before R29-S03 or the branding gate can be marked verified.

### Restricted model-input capture verification — 20 November 2026

One bounded read-only check used the existing hosted acceptance run
`454f0020-836e-45b6-b09a-d58714b93676`; no new question or provider call was made.
Stock Dify 1.19.0's LLM node retained the complete serialized `PromptMessage` sequence
created from the same runtime object passed to `model_instance.invoke_llm`. The record
contains the final system and user messages, all ten retrieved passages, model identity,
generation parameters, usage and finish reason. Native Dolved/Dify identifiers link it to
the `global` structured context, the exact saved/displayed answer and all ten ordered
source destinations. Exact passage text, document IDs, segment IDs and ordering match
between Dify retrieval output and Dolved's displayed-source records.

Conclusion: **VERIFIED** at the Dify model-runtime boundary. Provider-specific HTTP
serialization and byte-level wire capture remain unavailable and are not claimed. The
restricted package is outside Git at
`/Users/davidoliver/Desktop/dolved-experiments/dolved-dify-model-input-capture-20261120T092919Z/`;
its checksum-manifest SHA-256 is
`75ec5655326aba7d2b946a2db081d431fc2bcefc0d0b4b7198ee1859dc12656e`.
Raw files are mode 0600 in a mode 0700 directory and remain separate from ordinary
OpenTelemetry. No sealed question was accessed and broader observability/acceptance tasks
remain open.

## Current status reconciliation — 21 November 2026

The active repository was cleanly moved out of the iCloud-backed Desktop location. The
canonical clone is `/Users/davidoliver/Developer/RAG`; implementation uses a linked worktree
at `/private/tmp/RAG-admin-observability-20261121`. The former Desktop checkouts and legacy
recovery assets were preserved unchanged. No recovery-critical container, volume, database
or source file was deleted.

Application commits `ae077f2c`, `877a0942` and `18e0492d` deliver the final branded-email
refinement, explicit location/applicability administration, restricted question review,
expanded tenant usage and the private production observability stack. Question review is
off by default, derives its initial reviewer from current tenant ownership, and makes the
optional retrospective window a separate audited choice capped at 30 days. Ordinary
telemetry remains content-free; question wording is available only through the separately
authorised and audited review view.

Local verification after the observability change:

- 25 focused Laravel tests / 213 assertions passed across telemetry, platform operations,
  question review, locations and workspace usage. An initial run without the test storage
  bucket exposed only missing local test configuration and passed when the established
  fixture bucket was supplied.
- 3 focused web files / 16 tests passed for platform operations, workspace usage and import
  applicability. Earlier implementation verification also passed TypeScript, production
  build, Pint and the new focused feature suites. The full web suite's sole unrelated
  historical guardrail failure remains the approved interface specification's literal
  `Make Time` examples; that specification was not silently rewritten.
- Production Compose rendered successfully and the strengthened provider-free preflight and
  running verification passed.

Release `18e0492d` was deployed from archive SHA-256
`79ccaef19fd541dbc013960c7d42dfd0a06c7547c9fecfdce094ec82f9a15f66`.
The live application image identities are API
`sha256:489fc28f3b7f565e733e13ab54e314baf676c96bc4808950b5912d5c50c1998b`
and web
`sha256:3085765d48d0c9be31437447bcecb849fe529e5798e18b604f2b152e95afad79`.
The pinned Collector, LGTM and Alertmanager images are respectively
`sha256:6ed874ea083d67a3085acfc75343f2ad11d9abe6006d7ea4a16e2dba9af14e49`,
`sha256:af7242c1a9608faf6d26e6f235392fd0c32b67258228f9a3cfc96e724974930c`
and `sha256:9e082985f56f4c8c9f724e18f2288c6708f472e56a5286b8863d080434ea065d`.
All were healthy; Grafana, Alertmanager and OTLP/Collector health ports were bound only to
`127.0.0.1`. The application operational reader returned `available`, `current`, nine
signals and available alert state. No paid model, retrieval or generation call was used.

Follow-up release `76bfeb4d` fixed the workspace Usage failure visible during hands-on
acceptance. Carbon returned fractional millisecond durations while the usage query required
integers; the query now performs an explicit bounded cast and its regression test covers
recorded response/completion timing. The deployed query returned the live 30-day usage
summary successfully (20 accepted submissions, recorded average timing fields) and the
complete provider-free production verification passed.

The same release moved specialist-console access off laptop-dependent tunnels. Grafana and
Alertmanager still run on the pilot host and their raw ports remain bound to `127.0.0.1`, but
authenticated platform administrators can now reach them through Dolved's HTTPS gateway at
`/__ops/grafana/` and `/__ops/alerts/`. An unauthenticated external check returned HTTP 401
for both routes. The hotfix archive SHA-256 is
`41274cebe19901eb4e87999f56019ff4b6230861475480435000f0d350164f6e`;
local focused verification passed 11 tests / 105 assertions, Pint, Compose rendering and
Caddy validation before deployment. No provider call was used.

Observed pilot-host capacity at this verification point: Debian GNU/Linux 13 (trixie),
kernel `6.12.100+deb13-cloud-amd64`, six vCPUs, 11 GiB visible RAM plus 2 GiB swap and a
99 GiB root filesystem (33 GiB used, 63 GiB available). Twenty-five containers were running.
These are observed runtime values; the purchased plan remains the recorded VPS-3 2027 with
6 vCore, 12 GB RAM and 100 GB NVMe.

Dolved was already persisting Dify's native conversation identifier. Read-only inspection
showed the active Dify LLM node had no memory configuration, explaining why a follow-up could
lose its antecedent. Using Dify's own workflow save/publish services, and changing only
`llm.data.memory`, the stock workflow was published as version 3 with a 10-message bounded
window. The active workflow is `7bd318c0-bf18-4c2f-8fa2-f8ead969db45`; persisted read-back
shows `{"window":{"size":10,"enabled":true}}`. Private before/after exports were retained
outside Git with SHA-256
`8326d9144b1ec63fb86e292f379ce355041048b29de9cd0f29f095e52a786324` and
`ce9c3cab1194f2dd250ced1bcdc797e63112f23a5adc395fb1c94d30b7c2be6e`.
No prompt, retrieval, model, filter, document or dataset setting changed.

Current milestone: consolidated hosted acceptance. Remaining hands-on checks are the
custom/default branding matrix (including favicon, mobile and light/dark), role and direct
API concealment, document upload/replacement/withdrawal/deletion, explicit multi-location
applicability, conversational follow-up/reload, source-link permissions, Question review
opt-in/revocation/retention, and the operator screens. After those checks, re-verify the
11-document inventory and effective Top K 10 settings, freeze one readiness package and
hand it to Claude. The 40 questions remain sealed and have not been requested or run.
