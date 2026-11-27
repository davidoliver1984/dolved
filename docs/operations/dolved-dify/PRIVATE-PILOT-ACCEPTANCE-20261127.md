# Dolved invitation-only private-pilot acceptance

Date: 27 November 2026
Decision owner: David Oliver
Decision: **APPROVED FOR THE BOUNDED, INVITATION-ONLY PRIVATE PILOT**

David accepts the documented security coverage gaps and residual risks as sufficient for
the bounded, invitation-only private pilot. No further pre-launch testing is required under
this acceptance scope. This decision does not claim public-launch, multi-client or general
production readiness.

This is the canonical current release decision. It does not rewrite any historical
assessment, convert an unexercised control into a pass, send invitations or establish that
participants are active.

## Accepted pilot scope

The accepted release is the currently hosted, single-client Dolved–Dify application for a
small set of named invited users. Its purpose is to collect structured product feedback and
operational experience within the documented host, corpus, cost, monitoring and recovery
boundaries.

Dolved provides the HTTPS product, identity and session boundary, workspace tenancy,
document governance, source permissions, administration, audit, feedback/review controls
and operational views. Private stock self-hosted Dify 1.19.0 supplies the bound retrieval
and generation workflow. Dify administration and data services are not public interfaces.

Pilot users should treat answers as document-grounded assistance, not as a substitute for
professional judgment: check the linked source documents before acting, and report answers
that appear incomplete, unclear or unsupported. The pilot is intended to learn from that
feedback, not to demonstrate unrestricted reliability.

## Evidence supporting the decision

- The hosted Dolved web/API, private Dify backplane, document library, governed upload and
  lifecycle, saved conversations, structured location context, authorised source links,
  administration, feedback/review and operational views are deployed.
- The bounded engineering security review found and corrected the confirmed high-severity
  live mail/request dependency issue. Focused verification passed 64 Laravel tests / 417
  assertions and formatting, and the corrected immutable API image was deployed.
- Hosted synthetic access-control checks covered invitation delivery and acceptance,
  accepted-token replay, revoked/invalid-token denial, recovery and session invalidation,
  member/admin/platform boundaries, cross-workspace substitution, conversation ownership,
  role reduction, membership removal, usage exports and review-policy permissions. No
  authentication or authorisation bypass was found in the exercised rows.
- The original quality and memory evaluations retain their recorded outcomes. The later
  bounded six-case supplementary assessment passed its frozen batch rule. That PASS is
  additional evidence; it does not convert the original 40-question or memory FAIL verdicts
  into passes.
- The isolated supplementary test app and dataset were removed through supported Dify
  lifecycle operations. Post-cleanup verification preserved the production app, dataset,
  11 eligible documents and 248 searchable segments.
- Privacy-safe operational telemetry, same-origin specialist views, backup/rollback
  records and the legacy custom-RAG recovery inventory are retained. The original custom
  Laravel/Python/Qdrant path remains available for deliberate future development; Dify does
  not mark that roadmap complete.

## Specifically accepted residual risks and coverage gaps

David accepts the following for this private-pilot scope, with their existing treatments
unchanged:

- **SEC-02 — browser defence in depth:** the preferred browser-header set is incomplete and
  limited server technology information remains visible. This is deferred hardening, not a
  demonstrated access-control bypass.
- **SEC-03 — API container hardening:** the API process remains root in a writable container
  without the preferred capability drops/read-only root filesystem. No host privilege or
  demonstrated escape path was found; least-privilege changes require separate runtime
  verification.
- **SEC-04 — disabled passkey dependency chain:** advisories remain in dependencies for a
  passkey path that is not enabled. Passkeys must not be enabled until that chain is patched
  and retested.
- **SEC-05 — bounded hosted coverage:** the synthetic actor matrix closed the original
  tenancy/invitation gap, but a separately retained second submission of the exact consumed
  reset link was not captured. Token consumption and session invalidation were observed,
  and deterministic tests cover single use.
- **Source-link fixture gap:** authorised synthetic source delivery, existing-link
  revocation and a real cross-workspace source request were not exercised live because no
  safe provider-free indexed-source fixture exists. Guessed-ID and post-membership-removal
  requests were denied, and deterministic source-delivery tests remain on record.
- **Question-review fixture gap:** restricted synthetic answer opening, its audit event and
  post-revocation reread were not exercised live because no safe provider-free completed
  answer fixture exists. Policy enable/disable, effective date and role boundaries were
  exercised; deterministic opening/audit tests remain on record.
- **SEC-06 — availability boundary:** the pilot uses one VPS, shares a host edge with an
  unrelated application and has no high-availability claim. Backup, rollback and monitoring
  arrangements are proportionate to the named-user feedback pilot; hosting must be
  reassessed before public or multi-client operation.
- The previously recorded Dify memory-limit incident remains in the operational record.
  The service was healthy at later read-back; this decision does not claim the resource
  limit has been redesigned.

These are accepted limits of this release decision, not findings that have been disproved
or tests that have retrospectively passed.

## Operating and recovery boundary

- Invite only named pilot participants after a separate onboarding action; public
  registration and uncontrolled invitations remain out of scope.
- Keep the approved single-client corpus and structured location rules. Corpus, prompt,
  model, memory and retrieval changes remain controlled operations.
- Use the deployed privacy-safe operational signals and alert views during the pilot.
- Preserve the documented backups, immutable image identities and rollback procedures.
- Keep the legacy custom-RAG assets isolated and recoverable. Resuming that path, moving to
  AWS, adding high availability or enabling multi-client hosting each requires a separate
  decision.

## Current and historical records

- [Current architecture](CURRENT-ARCHITECTURE.md)
- [Delivery record](DOLVED-DIFY-DELIVERY.md)
- [Accepted-pilot implementation reconciliation](PILOT-IMPLEMENTATION-RECONCILIATION-20261127.md)
- [Pilot launch and onboarding checklist](PILOT-LAUNCH-CHECKLIST-20261126.md)
- [Security acceptance](PILOT-SECURITY-ACCEPTANCE-20261126.md)
- [Hosted access-control continuation](PILOT-ACCESS-CONTROL-ACCEPTANCE-CONTINUATION-20261126.md)
- [Supplementary six-case quality closure](SIX-CASE-QUALITY-CLOSURE-20261126.md)
- [Legacy custom-RAG recovery record](LEGACY-RAG-RECOVERY.md)

The linked assessment and evidence records remain historical source material with their
original grades, limitations, identities and checksums unchanged.
