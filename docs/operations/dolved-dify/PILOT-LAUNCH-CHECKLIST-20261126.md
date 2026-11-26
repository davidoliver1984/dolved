# Dolved invited-pilot launch checklist

Date: 26 November 2026  
Security phase: complete; final hands-on access-control acceptance incomplete
Pilot release decision: **not yet recorded**

This is the short outstanding checklist for the invitation-only pilot. It does not
authorise invitations or claim production, public-launch or multi-client readiness.

- [ ] David reviews the residual medium findings and coverage limits in the
  [security-acceptance report](PILOT-SECURITY-ACCEPTANCE-20261126.md).
- [ ] Resolve the concrete test-setup prerequisite in the
  [access-control acceptance addendum](PILOT-ACCESS-CONTROL-ACCEPTANCE-ADDENDUM-20261126.md):
  provision, through a supported workflow, at least two clearly synthetic workspaces and
  controlled owner/admin/member accounts. Do not repurpose a real identity.
- [ ] Complete the still-unexercised hosted invitation acceptance/reuse/expiry, recovery
  completion/session invalidation, role reduction/removal and direct API denial checks.
- [ ] Exercise cross-workspace conversation, source/document and export denial with the
  synthetic actor matrix; verify link behaviour after permission removal.
- [ ] Exercise question-review opt-in, audited opening, revocation and permission denial
  with synthetic content only. Retention expiry may continue to rely on deterministic
  evidence where waiting is impractical.
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

