# Dolved Dify delivery plan

Date: 19 November 2026
Owner: David
Implementation: Codex
Independent assessment: Claude
Status: implementation in progress in isolated worktree `RAG-dolved-dify-delivery` on branch `codex/dolved-dify-delivery-20261119`.

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

Prefer monthly cancellation, no annual prepayment, paid control panel or optional support plan. On 19 November 2026 David purchased the prepared OVHcloud VPS-3 2027 in UK Erith with Debian 13, no commitment, 6 vCore, 12 GB RAM, 100 GB NVMe and standard daily backup at GBP 10.60 ex VAT / GBP 12.72 incl UK VAT per month. The VPS reports public IPv4 `57.129.190.4` and is running. The first read-only SSH checks reached the host and recorded its ED25519 host key, but the dedicated deployment key was rejected for both `root` and the confirmed `debian` account. No password was requested or disclosed; key installation through the OVH control/console path is pending. Use this one Docker-capable Linux VPS; keep existing production services untouched and use a dedicated pilot subdomain.

Provisioning, deployment, HTTPS, backups and necessary provider calls are within the delivery authority and limits. Missing credentials or payment steps require David's input through secure channels. Do not place secrets in Git, reports or chat. Do not automatically tear down the delivered service: document recurring commitments and shutdown instructions.

The existing 106–166-hour estimate is provisional. Track actual focused effort and remaining work; do not claim continuous work while inactive. Report material scope expansion instead of silently extending delivery.

## Deliverables and acceptance evidence

| Deliverable | Completion evidence | Status |
|---|---|---|
| Architecture and local development setup | Isolated worktree, pinned dependencies, ADR, roadmap/tasks updated where required | Verified locally — worktree from `9e43561`; initial plan preserved; ADR-0041 and production runbook recorded |
| Dolved production runtime | Working production frontend/API, required workers, no development server dependency | Verified locally — production API/web images, PostgreSQL 18.4, SeaweedFS 4.53, migrations, storage initialisation and health checks pass |
| Stock Dify deployment | Pinned release/plugins, persistent stores, supported API compatibility | Verified locally — stock Dify 1.19.0/`8e689e8`, private services, loopback-only nginx, HTTP 200; hosted deployment pending |
| Authentication and ownership | Workspace, conversation and document access checks verified | In progress — provider-free conversation ownership and source-link authorisation tests pass |
| Document upload | Authorised user uploads via Dolved; original stored durably; status visible | Verified locally — versioned original storage, Dify processing/failure projection and storage initialisation covered; hosted end-to-end check pending |
| Governance synchronisation | Approval, eligibility, replacement, withdrawal and failure transitions verified | Verified locally — fail-closed global/multi-site, replacement, withdrawal, deletion and retry contracts pass; hosted recovery check pending |
| Chat | Streaming where supported, saved history, fresh conversations, refresh consistency | Verified locally — Dify orchestration, session identity, persisted answers and reload contract implemented; hosted provider check pending |
| Site context | Backend-validated selections and correct global/site retrieval behaviour | Verified locally — explicit registered selection or global-only, immutable per conversation; backend and web tests pass |
| Sources | Honest source display and authorised links to mapped originals | Verified locally — mapped authorised originals only and `Sources retrieved` wording; backend and web tests pass |
| Hosting and recovery | HTTPS, restricted services, backups and isolated restore check | In progress — production Compose, HTTPS edge, private Dify override, persistent-state inventory and restore procedure written; bounded no-commitment OVH VPS checkout prepared at GBP 12.72 incl VAT/month; account/payment, deployment and restore execution pending |
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

Current milestone: hosting provision and production deployment.

Next action: install the dedicated public key for the confirmed `debian` account, verify
key-only sudo-capable access and the Debian 13 image, then run the bounded host bootstrap.
After the service is reachable, obtain the dedicated pilot DNS action and deploy without
touching existing production services.

