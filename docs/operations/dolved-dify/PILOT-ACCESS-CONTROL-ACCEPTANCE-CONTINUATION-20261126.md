# Dolved invited-pilot access-control acceptance continuation

> Historical assessment record. See the
> [private-pilot acceptance](PRIVATE-PILOT-ACCEPTANCE-20261127.md) for the current release
> decision. The results and limitations below retain their original status.

Date: 26 November 2026  
Outcome: **HOSTED ACTOR MATRIX COMPLETED; FIXTURE-DEPENDENT CONTROLS REMAIN — NOT YET READY FOR DAVID'S INVITED-PILOT RELEASE DECISION**

This continuation preserves the earlier incomplete acceptance addendum and records only
the previously outstanding hosted work. It does not authorise pilot invitations, launch,
answer-quality testing, production-corpus changes or a production/public-launch claim.

## Setup obstacle and bounded resolution

The original blocker was not missing authority. The product has no public workspace-
creation route, although its first-party `CreateWorkspace` application action is the
supported lifecycle used by the platform. Two empty, clearly labelled synthetic Dolved
workspaces were created through that action after confirming that it does not provision
Dify resources or start provider work. Three distinct verified Dolved identities used
Gmail plus-addresses delivered to one controlled inbox; the application preserves those
addresses as distinct identities.

The same inspection found no safe provider-free production mechanism for creating an
indexed source or a completed generated-answer/review fixture. The E2E bootstrap is
environment-guarded and was not enabled in production. No test endpoint, fabricated model
run, production document, Dify binding or provider call was introduced to fill that gap.
Empty conversations were sufficient for conversation-ownership checks.

## Previously outstanding result matrix

| Area | Result | Evidence | Observed result / limitation |
|---|---|---|---|
| Controlled invitation receipt and acceptance | PASS | Hosted | Owner/admin/member invitations were delivered to controlled aliases and accepted only by the matching verified account with the intended role. |
| Accepted, revoked and invalid invitation tokens | PASS, except genuine expiry retained as deterministic evidence | Hosted plus retained deterministic evidence | Replaying the accepted token returned the same membership without duplication; random and revoked tokens returned 404. Genuine expiry was not forced or awaited. |
| Member/admin invitation and elevation restrictions | PASS | Hosted | Member invitation returned 403; admin-to-admin invitation and admin role elevation returned 409; admin could invite and revoke only an ordinary member. |
| Recovery and credential change | PASS | Hosted | The synthetic member completed recovery. The old credential failed, the replacement credential succeeded, the pre-reset session returned 401, the new session logged out with 204, and subsequent protected access returned 401. The reset record was absent after use and the remember-token digest changed. |
| Explicit second submission of the same reset link | NOT EXERCISED | Hosted state plus automated/source evidence | Live state proves token consumption, but a second browser submission of the exact controlled link was not retained as a separate request result. Existing engineering tests cover single use; this is not represented as a hosted pass. |
| Member, workspace-admin and platform-admin boundaries | PASS | Hosted direct API requests | Member administration/usage/review requests were denied; workspace admin could use allowed workspace administration/usage/review operations but platform operations returned 404; ordinary owner access to platform operations also returned 404. |
| Cross-workspace ID substitution | PASS | Hosted direct API requests | Workspace A actors received 404 when substituting Workspace B identifiers. Admin export against Workspace B also returned 404. |
| Conversation ownership and membership removal | PASS | Hosted provider-free empty conversations | A member could read their own empty conversation. Owner/admin reads of another actor's conversation returned 404. After membership removal, both workspace and conversation reads returned 404. No generation or retrieval occurred. |
| Role reduction and membership removal | PASS | Hosted | Promotion enabled admin usage; demotion restored 403; removal immediately concealed the workspace, conversation and guessed source route. |
| Usage export scope and cache contract | PASS | Hosted | Owner/admin exports succeeded only in Workspace A, member and cross-workspace requests were denied, and responses used `Cache-Control: no-store, private`. The export is synchronous; no retained export object exists. |
| Review policy opt-in and effective date | PASS | Hosted | Owner enablement produced a current effective/review-from timestamp with no retrospective inclusion. Admin/member policy mutation or access was denied as specified. Disablement caused review index access to return 403. |
| Restricted review-content opening, audit and post-revocation read | NOT EXERCISED | Automated/source evidence only | No provider-free generated-answer fixture exists. No real question content was opened and no model/provider call was made. |
| Authorised source delivery, existing-link revocation and cross-workspace real source | NOT EXERCISED | Automated/source evidence only | Guessed-ID and post-membership-removal source requests returned 404, but no safe synthetic indexed source could be created. The original 11-document corpus was not touched. |
| Production identity and corpus preservation | PASS | Live/read-back plus retained cleanup verifier | API/worker/scheduler remain revision `dd3d640bb59b5f32f3b8be0ba2d6001d502e6e42`; web remains `7b4c1df3b4f1437010601774021f1f2175d766be`. The last Dify cleanup verifier recorded the unchanged production app/dataset at 11 eligible documents and 248 searchable segments with zero active bound runs. This continuation did not call Dify. |

No confirmed authentication or authorisation bypass was found in the newly exercised
rows. The original quality verdicts and supplementary six-case PASS are unchanged.

## Synthetic-resource cleanup

The application lifecycle removed the temporary production-workspace membership and the
synthetic admin/member memberships, revoked all pending synthetic invitations and password
reset tokens, rotated remember tokens, disabled all three synthetic users and invalidated
their database sessions. Read-back at `2026-11-26T19:27:23Z` found zero synthetic sessions,
zero synthetic reset tokens, zero synthetic memberships in the production workspace, zero
pending invitations and no pending disablement reconciliation. The queue was empty. The
single retained failed job predates this continuation (`2026-11-25T09:01:55Z`) and is not a
cleanup job.

The two empty synthetic workspaces are retained because Dolved has no supported workspace
deletion/disable lifecycle and their owner is now disabled. Workspace A contains only two
provider-free empty conversations and its preserved audit/policy history; Workspace B is
empty. Both have zero documents and zero Dify bindings. They are inert and remain owned by
the disabled synthetic owner for audit traceability. Direct database deletion was not used.

## Remaining decision blocker and residual risks

The two fixture-dependent controls above remain precise coverage gaps: real authorised
source/link behaviour and restricted question-review content opening/audit/revocation.
Single-use reset-link denial also lacks a separately retained second hosted submission.
Because the instruction requires unexercised controls to be labelled honestly, this
addendum does not conclude that the invited-pilot release decision is ready.

Residual medium findings `SEC-02` through `SEC-06` remain visible and require David's
explicit risk decision. No new critical or high finding was added.

## Restricted evidence

The new sanitised detailed package is stored outside Git at:

`~/Desktop/dolved-experiments/dolved-pilot-synthetic-access-acceptance-20261126T192723Z/`

Its `CHECKSUMS.sha256` identity is
`eb6403b0624f662492d80a65a3b5486be03ddb2c942be369d34c7df6e005b40b`.
The directory is mode `0700` and files are mode `0600`. It contains no password, reset
link, session cookie, invitation token, signed storage URL or personal message content.
Earlier security, quality and incomplete-acceptance packages remain unchanged.
