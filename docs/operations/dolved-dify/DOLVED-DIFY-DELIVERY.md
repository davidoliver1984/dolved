# Dolved Dify delivery plan

Date: 19 November 2026
Owner: David
Implementation: Codex
Independent assessment: Claude
Status: approved by David on 27 November 2026 for the bounded, invitation-only private pilot. Invitations and participant onboarding have not yet been recorded. See `PRIVATE-PILOT-ACCEPTANCE-20261127.md` for the current decision and accepted limits.

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
| Dolved production runtime | Working production frontend/API, required workers, no development server dependency | Verified — hosted immutable web/API and required workers passed the bounded engineering/security read-back recorded below |
| Stock Dify deployment | Pinned release/plugins, persistent stores, supported API compatibility | Verified on host — stock Dify 1.19.0/`8e689e8`, private data services, loopback-only nginx, plugin debug port removed, HTTP 200; OpenAI plugin 1.1.0 |
| Authentication and ownership | Workspace, conversation and document access checks verified | Verified — hosted synthetic invitation, recovery/session, role, cross-workspace, conversation-ownership, reduction and removal checks passed; exact remaining fixture gaps are accepted in the release decision |
| Document upload | Authorised user uploads via Dolved; original stored durably; status visible | Verified for the pilot scope — eleven originals and stored bytes were read back; explicit organisation-wide/multi-location applicability and location administration are deployed |
| Governance synchronisation | Approval, eligibility, replacement, withdrawal and failure transitions verified | Implemented and verified through focused contracts and hosted pipeline evidence; no broader production-readiness claim is made |
| Chat | Streaming where supported, saved history, fresh conversations, refresh consistency | Verified for the pilot scope — hosted answers, persistence, reload, conversation identity and bounded ten-message Dify memory are recorded; the supplementary six-case batch passed |
| Site context | Backend-validated selections and correct global/site retrieval behaviour | Verified for the pilot scope — registered-location or global-only context is backend validated and document applicability is explicit |
| Sources | Honest source display and authorised links to mapped originals | Deployed and accepted — source presentation and permission enforcement are implemented; the unexercised provider-free live source-link fixture rows remain documented and accepted |
| Hosting and recovery | HTTPS, restricted services, backups and isolated restore check | Verified for the bounded pilot — private services, HTTPS, credentials, backups and isolated restore evidence are recorded; the single-VPS availability limit remains accepted |
| Fixed product branding | Established Dolved identity across current product surfaces | Deployed — distinct from configurable branding |
| Platform-admin branding controls | Bounded logo/colour and related settings through the reserved branding seam | Deployed — versioned editor, custom/default lifecycle, safe assets, branded correspondence and hosted refinements are live. David reported the resulting branded email and interface looked correct. Consolidated favicon persistence, fallback identity, mobile and light/dark acceptance remain open |
| Privacy-safe observability | Allowlisted OpenTelemetry propagation/Collector routing without content or secrets in general telemetry; tenant usage and restricted question review remain separate | Verified — pinned Collector 0.153.0, Grafana OTEL LGTM 0.29.2 and Alertmanager 0.33.1 are private/healthy; nine operational signals, alert state, usage and bounded question-review permissions were exercised |
| Restricted evaluation evidence capture | Exact assembled model input and user-visible answer/source/link records | Verified — existing hosted run directly records Dify 1.19.0's assembled `PromptMessage` sequence and natively correlates it to the global structured context, Dolved answer and ten ordered permission-enforced source routes; this is the Dify model-runtime boundary, not provider-wire bytes. Restricted package manifest SHA-256 `75ec5655326aba7d2b946a2db081d431fc2bcefc0d0b4b7198ee1859dc12656e`; no new provider call or setting change |
| Final freeze | Application, Dify, plugins, prompt, corpus and settings recorded | Verified — production identities/configuration and the 11-document / 248-segment corpus were preserved through the supplementary assessment and test-resource cleanup |
| Independent evaluation | One hosted 40-question run and Claude's frozen-rubric assessment | Completed — original 40-question and memory verdicts remain unchanged; the later bounded six-case supplementary assessment passed its frozen rule |
| Handover | URL, secure access, costs, runbooks, limitations and client-access decision | Verified — David's bounded pilot acceptance is recorded in `PRIVATE-PILOT-ACCEPTANCE-20261127.md`; invitation sending/onboarding remains a separate action |

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

The first authenticated-browser check exposed two deployment defects without weakening the
boundary: direct console navigation did not carry the trusted SPA origin required by
Sanctum, and the operational reader called Alertmanager without its configured route
prefix. Commit `a321b256` adds the trusted production origin only to the internal
authorisation subrequest and corrects the internal Alertmanager base URL. Its archive
SHA-256 is `c3a0b67fa7d51b1b20a987d03c0dbbfd3780e9a403e42c72ea365a3f25c899b1`.
After deployment, the production verification passed; the reader reported metrics and
alert state available with zero active alerts. Both public console routes continued to
return HTTP 401 without an authenticated Dolved session.

Observed pilot-host capacity at this verification point: Debian GNU/Linux 13 (trixie),
kernel `6.12.100+deb13-cloud-amd64`, six vCPUs, 11 GiB visible RAM plus 2 GiB swap and a
99 GiB root filesystem (33 GiB used, 63 GiB available). Twenty-five containers were running.
These are observed runtime values; the purchased plan remains the recorded VPS-3 2027 with
6 vCore, 12 GB RAM and 100 GB NVMe.

Mobile correspondence review then showed that the email renderer rejected Cherish's light
purple accent because it assumed white button text, substituting Dolved green despite the
branding revision's approved black foreground. Commit `9c694e8b` validates the accent and
foreground as a pair, removes the hard-coded green preferences link and adds mail-client
light-colour-scheme hints. Archive SHA-256
`780a313cd7e6b72eb1c75a964ed3934c798b2ebe5f396961d087f2cbac33ba19`
was deployed. The live resolver returned `#DA95DF` with black button text and a `#8D2F93`
link; 24 tests / 156 assertions, Pint and full provider-free production verification passed.
A post-fix branding check email was sent to David.

The pilot host was found to front an existing, separately deployed FAMVAM pilot through the
same Caddy container. A Dolved gateway deployment temporarily omitted that unrelated
virtual host, producing an SSL protocol error at `pilot.famvam.com`. Preserved pre-change
Caddy backups allowed the FAMVAM holding/API/auth/media/web routes to be restored in commit
`3b2706df`; no FAMVAM data or container was changed. Commit `e9b2f409` then separated route
ownership: Dolved's versioned Caddyfile imports read-only external site fragments, and the
FAMVAM fragment is independently stored at `/etc/dolved/caddy-sites/famvam.caddy`, outside
the Dolved release tree. Recreating the gateway with this boundary preserved returned HTTPS
200 with valid TLS for both pilot hostnames, and the full provider-free Dolved verification
passed. Routine Dolved releases can no longer overwrite the FAMVAM fragment. The two pilots
still share the VPS and Caddy process; full host-level or edge-process fault isolation would
require separate infrastructure and is not claimed.

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

Acceptance review of the scheduled governance digest found that each reminder card used
generic copy despite the notification already retaining its document-family label and
review date. Commit `ee67198c` now renders each review reminder as “{Document title} still
needs to be reviewed” followed by “Due by {date}”, while retaining bounded generic fallback
copy for incomplete historical events. The focused mail suite passed 20 tests / 112
assertions and Pint. Release archive SHA-256
`8874fc3922682d49484aaaf91481cb4b160f12bea74125e2002383d3d7a3fa52` was deployed as API
image `sha256:ab4e7bf8f4751669c247fdc6837ec3b14105908713e3f3906e995525ec50db4f`;
the full provider-free production verification passed. Read-only inspection confirmed all
eleven live reminder members retain their titles and the 4 December 2026 due date. A
corrected digest was sent to David for acceptance review.

Current milestone: consolidated hosted acceptance. Remaining hands-on checks are the
custom/default branding matrix (including favicon, mobile and light/dark), role and direct
API concealment, document upload/replacement/withdrawal/deletion, explicit multi-location
applicability, conversational follow-up/reload, source-link permissions, Question review
opt-in/revocation/retention, and the operator screens. After those checks, re-verify the
11-document inventory and effective Top K 10 settings, freeze one readiness package and
hand it to Claude. The 40 questions remain sealed and have not been requested or run.

### Active-pipeline interface cleanup — 23 November 2026

The approved reversible cleanup from
`ACTIVE-PIPELINE-INTERFACE-AUDIT-20261123.md` is deployed from source revision
`cc72a8da699cb3bbe11b847785379dd7a59c0f99`. The running API/worker/scheduler image is
`sha256:cf7e2d818fedc026de1d7c6c31a580d1eee5e352330a1c83e09c2b8e1e0a6644`; the running web
image is `sha256:94bd2e9bdb81b91751afe59aa364adafca319621b07d8df48d6047cde30dea4b`.
Each carries the matching OCI revision and repository-source label. Preflight and the full
provider-free running check passed.

For Dify workspaces, extracted text is now presented only for an exact indexed version with
a compatible published projection, and content comparison only when both versions have
matching published projection identities. Version history and governance remain available.
Dify chat uses neutral progress and an honest `Stop waiting` action; custom pipeline events
and cancellation remain retained. The delete confirmation now describes retrieved passages
retained with historical conversation evidence rather than claim citations. Tenant-facing
assistant/readiness/authentication/browser/error labels use the trusted brand resolver;
Platform administration remains Dolved.

`Indexed chunks` is sourced from cached, bounded observations of Dify's supported segments
API. The live observation covers 11 available bindings and totals 248 segments, with the
oldest observation recorded at `2026-11-23 19:15:24+00`. Missing or stale observations are
not rendered as zero. The Usage view separately reports documents currently failing from
Dify binding state; historical Dify attempt failures remain unavailable because there is no
attempt ledger. Existing custom corpus and attempt metrics remain preserved.

Verification passed 24 Laravel tests / 195 assertions, 5 web files / 36 tests, Pint, the
production build and diff checks; ESLint reported no errors. The hosted pass confirmed
public Cherish recovery branding/title and a 390-pixel layout without overflow, live
capability and Usage aggregates, retained-run interaction profiles, deployed wording and
running-image labels without a provider call. No custom tenant favicon is configured, so
the available Dolved fallback favicon remains in use. No admin/member accounts or
two-version family currently
exist, so those exact hosted presentations remain explicit gaps covered only by focused
role/policy and comparison tests. Broader lifecycle, source-link and role acceptance remains
open; this cleanup does not close R30-S03 or readiness.

Rollback is the pre-deployment environment/database package at
`/srv/dolved-pilot/backups/20261123-active-pipeline-predeploy` plus the prior immutable image
identities recorded in the R30-S03 journal. No custom pipeline source, contract, data,
recovery material or historical evidence was removed.

### Bounded supplementary-assessment correction — 26 November 2026

The focused correction at revision
`7b4c1df3b4f1437010601774021f1f2175d766be` is deployed as API image
`sha256:bff6314f3f77d905b4f6568b9a2294dffc25c889e66b2736a39d62db4465bfa4`
and web image
`sha256:1e5ad967280629b06df3106712b6f943e2b41d9068c88ce2adddb2e32c42b6ee`.
Dify application `32199630-6a11-40a0-96ca-618560e6e394` now points to workflow
`5a85aa42-bca1-408f-953d-ecc08a130e6a`, version `5`, with canonical graph SHA-256
`a53b32ad396f68c433e837cdaf73579f31334ae21bd0145720109f0fb0cc9561`.
Lossless read-back against version 4 proves the system-prompt text is the only changed
graph path. The frozen corpus remains 11 eligible documents / 248 searchable segments at
digest `733a20a23390f9e1a02116922aa9613c3afd3e128a5645c9eba4710140ccba71`.
The complete provider-free production gate passed after a clean Dify API restart.

No assessment question, retrieval probe or generation call was made. The fixture files
and release receipt are not yet present at Claude's neutral handoff location, so the
isolated app/dataset and fixture ingestion remain pending rather than being substituted or
partially fabricated. The complete identities, rollback references, prerequisite and
later capture procedure are recorded in
`SIX-CASE-PREPARATION-READINESS-20261126.md`.

### S5–S6 isolated fixture readiness — 26 November 2026

Claude's exact two-file release was verified and ingested only into private Dify app
`0c9885e5-5c77-4b1f-a7b8-6600c781fcb0` and dataset
`a4bb7857-5be3-4bd8-aa27-507221ef46bf`. Both documents are completed with five segments
each and `scope_key = global`; each released marker paragraph survived inside one stored
segment. The copied filter remains `global OR {{trusted_site}}` and is bound to the test
dataset's own metadata field. Runtime graph parity is exact apart from the required test
dataset/metadata identities; Dify's supported copy also omitted only the non-runtime
editor viewport.

No retrieval, generation, validation or assessment submission occurred. Production remains
workflow v5 with the same graph, prompt and app-key hashes and the same 11-document / 248-
segment corpus. The full identities, per-segment hashes, cost accounting and capture plan
are in `S5-S6-FIXTURE-READINESS-20261126.md`; the neutral package checksum manifest is
`e0085c2eb15f86e5dca45c629e60cc400c2bb4525d5ef02776e6668ae4861d13`.

### Conversation-state correction batch — 25 November 2026

A provider-free local correction batch is prepared on revision
`c80c45256112b7e919298d8123ec954e952aeede` without a commit, push, deployment or Dify
publish. It removes the duplicate current-query insertion from the prepared Dify node
configuration, makes conversations with divergent remote history read-only, and blocks
new turns when a conversation's fixed location is retired. Retrieval, corpus, Top K,
ranking, models and the sealed 40-question section are unchanged. The additive schema
change and exact release constraints are recorded in
`CONVERSATION-STATE-CORRECTIONS-20261125.md`. Hosted behaviour remains unchanged until a
separately authorised application deployment, migration and supported Dify publish occur.

### Bounded pilot security acceptance — 26 November 2026

The authorised pilot security review is complete. The sanitised findings and coverage
limits are in the [pilot security acceptance](PILOT-SECURITY-ACCEPTANCE-20261126.md); the
genuinely outstanding release items are in the
[pilot launch checklist](PILOT-LAUNCH-CHECKLIST-20261126.md). One confirmed high-severity blocker in
the live mail/request dependency path was corrected by the narrow lock-file commit
`dd3d640bb59b5f32f3b8be0ba2d6001d502e6e42`. Focused verification passed 64 Laravel
tests / 417 assertions and Pint, and the patched Composer audit now retains advisories
only in the disabled passkey dependency chain.

The Dolved API, worker and scheduler use immutable image
`sha256:ff0e5545bc002a6ee3c7bb32128dea332aa9cf3e633cc284bf679892fc139405`
with the matching OCI revision. The web image, production data and Dify prompt, model,
memory, retrieval, corpus and evaluation configuration were not changed. The
pre-deployment backup is
`/srv/dolved-pilot/backups/20261126-security-acceptance-predeploy`; its checksum verified.
The general running verifier stopped on the retained Dify API `OOMKilled` flag from the
known memory-limit incident, despite Dify API being currently healthy. That evidence was
not cleared by a restart. Focused provider-free verification of the changed Dolved
services, image identity, private backplane, HTTPS/API denial, storage boundary and backup
checksums passed.
The restricted evidence package and its manifest are retained outside Git at
`~/Desktop/dolved-experiments/dolved-pilot-security-acceptance-20261126/`; the manifest
SHA-256 is `09db0c9b711c0d7c72a0e3652a5b806448235ed9ea4e512442f071dd7f7e019d`.

Outcome: **READY FOR DAVID'S PILOT RELEASE DECISION**. This does not complete R30-S04,
R30-S05 or R30-GATE, launch the pilot, send invitations, claim production readiness or
accept the residual medium risks on David's behalf.

### Final access-control acceptance boundary — 26 November 2026

The final bounded hands-on session is recorded in
`PILOT-ACCESS-CONTROL-ACCEPTANCE-ADDENDUM-20261126.md`. Live checks confirmed generic
recovery privacy for known/unknown controlled addresses, created and then revoked the exact
known-account reset record, and reconciled existing owner issue/revoke invitation audit
evidence. Current production has one workspace, no explicitly synthetic workspace and one
verified controlled account, so the required two-workspace owner/admin/member matrix could
not be exercised through normal workflows. R30-S04 remains in progress and David's release
decision remains open; deterministic controls are not represented as hosted passes.

The isolated supplementary test app and dataset were removed using Dify's supported
lifecycle after the security dependency was released. Post-cleanup verification found all
targeted live test records absent while preserving production's app/workflow/dataset,
11 eligible documents, 248 searchable segments and corpus timestamps. The quality verdicts
remain unchanged.

### Access-control continuation — 26 November 2026

The authorised continuation is recorded in
`PILOT-ACCESS-CONTROL-ACCEPTANCE-CONTINUATION-20261126.md`. The earlier incomplete
addendum is preserved. Controlled plus-address identities and two empty Dolved workspaces
closed the hosted invitation, recovery/session, role, cross-workspace, conversation,
membership-removal, usage-export and review-policy gaps without creating Dify resources or
calling a provider. No bypass was found.

The product has no safe provider-free production mechanism for an indexed source or a
completed generated-answer/review fixture. Authorised source/link behaviour and restricted
review-content opening/audit/revocation therefore remain honestly unexercised, as does a
separately retained second submission of the same consumed reset link. R30-S04 remains in
progress and David's invited-pilot release decision remains open.

Cleanup used supported application lifecycles: all three synthetic users are disabled,
their sessions/reset tokens and temporary production/admin/member memberships are gone,
and no pending synthetic invitation or reconciliation remains. Two empty workspaces are
retained inert under the disabled synthetic owner because no supported workspace deletion
lifecycle exists; neither contains documents or a Dify binding. Production revisions and
the previously verified 11-document / 248-segment corpus were unchanged.
Restricted continuation evidence is retained at
`~/Desktop/dolved-experiments/dolved-pilot-synthetic-access-acceptance-20261126T192723Z/`;
the checksum-manifest SHA-256 is
`eb6403b0624f662492d80a65a3b5486be03ddb2c942be369d34c7df6e005b40b`.

### Private-pilot release decision — 27 November 2026

David reviewed the completed engineering/security work, hosted access-control matrix,
original quality outcomes, supplementary six-case PASS and the exact residual risks and
coverage gaps. He accepted them as sufficient for the bounded, invitation-only private
pilot. No further pre-launch testing is required within that accepted scope.

The canonical decision is
[`PRIVATE-PILOT-ACCEPTANCE-20261127.md`](PRIVATE-PILOT-ACCEPTANCE-20261127.md). It records
the accepted browser/container/passkey/availability risks, the provider-free source and
restricted-review fixture gaps, the retained reset-link evidence limitation and the
historical Dify memory-limit incident. Those controls remain accurately labelled; they are
not converted into passes.

This closes the Phase 30 review, verification, handoff and release-decision activities.
Invitation sending and participant onboarding remain open operational actions. The decision
does not claim public launch, multi-client hosting, general production readiness or high
availability, and it does not complete the preserved custom-RAG roadmap or later visual and
AWS gates.

The repository-history reconciliation that restored the complete accepted implementation
to `main` without changing the hosted system is recorded in
[`PILOT-IMPLEMENTATION-RECONCILIATION-20261127.md`](PILOT-IMPLEMENTATION-RECONCILIATION-20261127.md).
