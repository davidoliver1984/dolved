# Dolved invited-pilot launch checklist

Date: 26 November 2026  
Security phase: complete; hosted actor matrix complete
Pilot release decision: **accepted by David on 27 November 2026**

This is the acceptance and onboarding checklist for the invitation-only pilot. The
canonical decision is the
[private-pilot acceptance](PRIVATE-PILOT-ACCEPTANCE-20261127.md). It approves the bounded
pilot but does not itself send invitations or claim production, public-launch or
multi-client readiness.

- [x] David reviewed and accepted the residual medium findings and coverage limits in the
  [security-acceptance report](PILOT-SECURITY-ACCEPTANCE-20261126.md).
- [x] Resolve the concrete actor-matrix prerequisite through supported lifecycle actions:
  two clearly synthetic Dolved workspaces and controlled owner/admin/member identities.
- [x] Complete hosted invitation acceptance/replay/revocation/invalid-token, recovery,
  session invalidation, role reduction/removal and direct API denial checks. Genuine
  invitation expiry remains supported by retained deterministic evidence.
- [x] Exercise cross-workspace conversation and synchronous usage-export denial with the
  synthetic actor matrix.
- [x] Record the missing separately retained second submission of the exact consumed reset
  link as an accepted coverage gap, not a hosted pass. Current state proves consumption and
  engineering tests cover single use.
- [x] Record authorised source delivery, existing-link revocation and real cross-workspace
  source access as an accepted coverage gap, not a hosted pass. No safe provider-free
  indexed synthetic-source mechanism exists; the production corpus was not altered to
  manufacture coverage.
- [x] Record restricted question-review content opening, audit and post-revocation read as
  an accepted coverage gap, not a hosted pass. Policy/effective-date/role checks passed,
  but no safe provider-free generated-answer fixture exists.
- [x] Reconfirm the deployed document inventory and effective Dify configuration without
  changing frozen quality evidence: the six-case cleanup verifier preserved production at
  11 eligible documents / 248 searchable segments with unchanged identities and corpus
  timestamps.
- [x] Remove the isolated six-case test app/dataset through Dify's supported lifecycle and
  preserve checksummed cleanup evidence.
- [x] Record David's explicit invited-pilot release decision in R30-S04c/R30-S05.
- [ ] Send invitations through the separately authorised onboarding action; preserve the
  bounded pilot audience, backup/rollback and monitoring arrangements.
- [ ] Record invited participants and the pilot start date when onboarding actually occurs.

Deferred maintenance that is not a present launch blocker: browser response-header
hardening, least-privilege API container changes, and patching/removing the disabled
passkey dependency chain before that feature is enabled.

Completed live in the final session: deployed identity/health read-back, generic recovery
privacy for known and unknown controlled addresses, known-account reset issuance and
cleanup, aggregate owner invitation issue/revoke evidence, retention/scheduler read-back,
and isolated Dify test-resource cleanup. Controls described as automated/source-only in the
addendum are not counted as hosted passes.

Restricted final-session evidence:
`~/Desktop/dolved-experiments/dolved-pilot-final-access-acceptance-20261126T162315Z/`;
checksum-manifest SHA-256
`3cd2c04b20094c9bb906bc63e24afe761bbd4684e140f90f21f467f6856617b3`.

Continuation evidence and the updated matrix are recorded in
[PILOT-ACCESS-CONTROL-ACCEPTANCE-CONTINUATION-20261126.md](PILOT-ACCESS-CONTROL-ACCEPTANCE-CONTINUATION-20261126.md).
The synthetic accounts were disabled and their sessions/tokens/memberships retired. The
two empty workspaces remain inert because no supported deletion lifecycle exists.
Restricted continuation evidence:
`~/Desktop/dolved-experiments/dolved-pilot-synthetic-access-acceptance-20261126T192723Z/`;
checksum-manifest SHA-256
`eb6403b0624f662492d80a65a3b5486be03ddb2c942be369d34c7df6e005b40b`.

