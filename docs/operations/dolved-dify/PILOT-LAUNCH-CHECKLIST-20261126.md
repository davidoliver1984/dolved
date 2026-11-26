# Dolved invited-pilot launch checklist

Date: 26 November 2026  
Security phase: complete  
Pilot release decision: **not yet recorded**

This is the short outstanding checklist for the invitation-only pilot. It does not
authorise invitations or claim production, public-launch or multi-client readiness.

- [ ] David reviews the residual medium findings and coverage limits in the
  [security-acceptance report](PILOT-SECURITY-ACCEPTANCE-20261126.md).
- [ ] Complete the R30-S04 hands-on checks with authorised pilot accounts: invitation and
  recovery lifecycle, owner/admin/member permissions, direct API denial and session
  invalidation.
- [ ] Exercise two-account cross-workspace denial and source-link/document permissions
  without reading unrelated real-user content.
- [ ] Confirm question-review opt-in, revocation, retention and export permissions using
  the supported pilot workflow.
- [ ] Reconfirm the deployed document inventory and effective Dify configuration without
  changing the frozen quality evidence.
- [ ] Record David's explicit invited-pilot release decision in R30-S04c/R30-S05.
- [ ] Send invitations only after that decision; preserve the bounded pilot audience,
  backup/rollback and monitoring arrangements.

Deferred maintenance that is not a present launch blocker: browser response-header
hardening, least-privilege API container changes, and patching/removing the disabled
passkey dependency chain before that feature is enabled.

