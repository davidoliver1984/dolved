# Dolved invited-pilot access-control acceptance addendum

> Historical assessment record. See the
> [private-pilot acceptance](PRIVATE-PILOT-ACCEPTANCE-20261127.md) for the current release
> decision. The matrix below is preserved and its unexercised rows remain unexercised.

Date: 26 November 2026  
Outcome: **HANDS-ON ACCEPTANCE INCOMPLETE — NOT YET READY FOR DAVID'S INVITED-PILOT RELEASE DECISION**

This addendum records the final bounded access-control acceptance session. It does not
authorise invitations, launch the pilot, reopen answer-quality evaluation or claim
production, public-launch, multi-client or high-availability readiness.

No new critical or high vulnerability was found. The blocker is coverage: the hosted
environment has one workspace, no explicitly synthetic workspace and one verified
controlled account. A supported second synthetic workspace/account set was therefore not
available for the required live cross-workspace and role-reduction checks. Existing real
identities were not modified to manufacture coverage.

## Result matrix

| Area | Result | Method | Evidence and limitation |
|---|---|---|---|
| Deployed identity and current health | PASS | Live read-back | Dolved API/worker/scheduler run revision `dd3d640bb59b5f32f3b8be0ba2d6001d502e6e42`; web runs `7b4c1df3b4f1437010601774021f1f2175d766be`. API, web and Dify API were healthy. The historical Dify API `OOMKilled=true` flag remains recorded and was not cleared. |
| Recovery response privacy | PASS | Live hosted requests | Known and unknown controlled addresses received the same generic HTTP 200 response. The known controlled account produced one reset record. No account-existence detail was disclosed. |
| Recovery mail receipt, password change, token reuse and session revocation | NOT EXERCISED | Setup unavailable | The known-account reset email was submitted, but mailbox receipt and the reset link were not opened. No password was changed. Engineering tests cover single-use reset, database-session invalidation and remember-token rotation, but this was not a hosted lifecycle pass. The session-created reset record was revoked through Laravel's password broker and verified absent. |
| Owner invitation issue and revoke | PASS | Existing hosted audit/state evidence | Sanitised aggregate read-back found two owner-issued invitation audit events, one owner-revoked event, and current states of one pending and one revoked invitation. No token, recipient or message content was read. |
| Invitation acceptance, already-used/revoked/invalid token denial and genuine expiry | NOT EXERCISED | Automated/source evidence only | Focused tests cover exact-recipient verification, accepted-token idempotence, revoked/invalid denial and scheduler-materialised expiry. No fresh hosted recipient account was created in this session. |
| Member/admin invitation and elevation boundaries | NOT EXERCISED | Automated/source evidence only | Focused tests enforce owner/admin/member rules and restrict admins to ordinary-member invitations. Production currently has only one controlled owner membership, so no live ordinary-member/admin actor was available. |
| Platform-admin, workspace-admin and ordinary-member denial | NOT EXERCISED | Automated/source evidence only | Policies and focused tests cover server-side denial. The required hosted actor matrix was unavailable. UI hiding alone is not counted as evidence. |
| Cross-workspace ID substitution and conversation ownership | NOT EXERCISED | Automated/source evidence only | Focused tests cover concealed workspace lookup, conversation scoping and membership-revoked streaming. Production has only one workspace, so a genuine hosted A/B test could not be performed. |
| Original document/source delivery and guessed-ID denial | NOT EXERCISED | Automated/source evidence only | Focused delivery tests cover tenant concealment before storage metadata, ranged delivery and fail-closed behaviour. No harmless provider-free synthetic source existed in two workspaces; the production corpus was not touched. |
| Direct storage/signed-URL boundary | PASS | Source/automated evidence | Original files are delivered through authorised Dolved endpoints; upload URLs are content-type bound and time limited. The previous live review found no public storage listing. No hosted cross-workspace link-revocation test was possible. |
| Question-review opt-in and audited content opening | NOT EXERCISED | Automated/source evidence only | Tests establish off-by-default policy, owner-only enablement, hidden content and audited restricted opening. No synthetic question content was created and real question content was not inspected. |
| Question-review revocation and retention expiry | NOT EXERCISED | Configuration/source evidence only | Live schedule read-back confirmed daily derivative cleanup. Source fixes review retention at 30 days, audit retention at 400 days and removes expired/revoked grants after 30 days. No production record was force-expired. |
| Usage export authorisation | NOT EXERCISED | Automated/source evidence only | Tests establish owner/admin-only, tenant-scoped export data and ordinary-member denial. The export is generated synchronously with `Cache-Control: private, no-store`; there is no retained export object whose storage expiry could be exercised. |
| Telemetry/notification content minimisation | PASS | Source/automated evidence | The reviewed telemetry paths record bounded identifiers/counts rather than question/answer bodies; the acceptance suite covered the privacy boundary. No real private content was read to prove it. |
| Retention configuration | PASS | Live read-back | Password reset expiry is 60 minutes, session lifetime 120 minutes, import retention seven days and provisional delivery-event retention 86,400 seconds. Relevant cleanup commands were present in the live scheduler. Configuration inspection is not claimed as proof of every deletion. |
| Six-case isolated resource cleanup | PASS | Supported live cleanup plus post-verification | The exact test app and dataset were removed through Dify's supported lifecycle. All targeted live records are zero; fixture names are absent; production remains 11 eligible documents / 248 searchable segments with unchanged production identities and corpus timestamps. |

## Synthetic-resource ledger

No account, workspace, membership, document, file, Dify app or dataset was created by this
session. One password-reset record was created for the controlled primary account and was
deleted through Laravel's supported password broker after evidence capture; read-back
confirmed zero remaining records for that account. Pre-existing pending/revoked invitation
records were not altered because they were not created by this session.

## Residual risks and release blocker

The confirmed medium findings `SEC-02` through `SEC-06` in the security-acceptance report
remain unchanged. No new confirmed vulnerability was added.

The invited-pilot decision is blocked on one concrete prerequisite: provide a supported,
clearly synthetic setup with at least two workspaces and owner/admin/member accounts in
controlled mailboxes, then perform the still-unexercised hosted rows above. Do not satisfy
that prerequisite by changing a real user's database record, weakening verification or
using real private content.

## Evidence and cleanup

Detailed sanitised command/result summaries are retained outside Git at:

`~/Desktop/dolved-experiments/dolved-pilot-final-access-acceptance-20261126T162315Z/`

The package uses directory mode `0700` and file mode `0600`. Its checksum-manifest identity
is `3cd2c04b20094c9bb906bc63e24afe761bbd4684e140f90f21f467f6856617b3`.
The earlier security and quality packages remain unchanged.

