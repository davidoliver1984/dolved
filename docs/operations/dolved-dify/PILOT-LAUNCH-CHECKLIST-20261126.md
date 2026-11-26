# Dolved invited-pilot launch checklist

Date: 26 November 2026  
Security phase: complete; hosted actor matrix complete; fixture-dependent controls remain
Pilot release decision: **not yet recorded**

This is the short outstanding checklist for the invitation-only pilot. It does not
authorise invitations or claim production, public-launch or multi-client readiness.

- [ ] David reviews the residual medium findings and coverage limits in the
  [security-acceptance report](PILOT-SECURITY-ACCEPTANCE-20261126.md).
- [x] Resolve the concrete actor-matrix prerequisite through supported lifecycle actions:
  two clearly synthetic Dolved workspaces and controlled owner/admin/member identities.
- [x] Complete hosted invitation acceptance/replay/revocation/invalid-token, recovery,
  session invalidation, role reduction/removal and direct API denial checks. Genuine
  invitation expiry remains supported by retained deterministic evidence.
- [x] Exercise cross-workspace conversation and synchronous usage-export denial with the
  synthetic actor matrix.
- [ ] Retain a separate hosted result for a second submission of the same consumed reset
  link; current hosted state proves consumption and engineering tests cover single use.
- [ ] Exercise authorised source delivery, existing-link revocation and cross-workspace
  source denial. No safe provider-free indexed synthetic-source mechanism currently
  exists; do not touch the production corpus to manufacture coverage.
- [ ] Exercise restricted question-review content opening, audit and post-revocation read.
  Policy/effective-date/role checks passed, but no safe provider-free generated-answer
  fixture exists. Retention expiry may continue to use deterministic evidence.
- [x] Reconfirm the deployed document inventory and effective Dify configuration without
  changing frozen quality evidence: the six-case cleanup verifier preserved production at
  11 eligible documents / 248 searchable segments with unchanged identities and corpus
  timestamps.
- [x] Remove the isolated six-case test app/dataset through Dify's supported lifecycle and
  preserve checksummed cleanup evidence.
- [ ] Record David's explicit invited-pilot release decision in R30-S04c/R30-S05.
- [ ] Send invitations only after that decision; preserve the bounded pilot audience,
  backup/rollback and monitoring arrangements.

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

