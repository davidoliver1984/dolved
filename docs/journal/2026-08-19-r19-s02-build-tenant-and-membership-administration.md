# R19-S02 — Build Tenant and Membership Administration

**Date:** 2026-08-19
**Status:** Completed
**Architecture:** ADR-0025 (Accepted)

## What changed

The workspace now has an authoritative membership-administration surface.
Owners and administrators can inspect current members and invitations, but each
mutation remains constrained by the fixed ADR-0025 role matrix. Owners alone can
create or alter administrators and transfer ownership; administrators can
manage ordinary members only. Ordinary members cannot inspect the directory but
can leave voluntarily, while an owner must transfer ownership before leaving.

Invitations are durable independently of email delivery. Laravel stores only a
SHA-256 token digest, returns the secure link once, binds acceptance to the
authenticated user's verified normalized email and materializes expiration on
a scheduled path. Reissuing closes the previous pending invitation, and a
partial database index prevents two pending invitations for one workspace/email
identity.

## Important correctness details

Ownership transfer locks the actor and target memberships and works with the
existing non-deferrable one-owner constraint by demoting the outgoing owner to
administrator before promoting the target. Mutation commands carry durable
idempotency identities, while sensitive successes and relevant business
failures produce content-free audit events.

Membership revocation now also reaches connections that were already open. The
SSE loop periodically checks current membership and emits a safe terminal
authorization event before delivering any further answer content. New requests
continue to resolve membership live as before.

Removing a member never transfers or deletes workspace content. Documents,
conversations and evidence remain owned by the workspace.

## Verification

- Laravel focused verification: 38 tests, 199 assertions passed.
- Laravel broad container verification: 297 passed and 2 skipped; eight
  historical V3 engineering tests require the absent isolated engineering
  mount and are unrelated to this stage.
- Web verification: 12 files and 36 tests passed; ESLint, TypeScript and the
  production build passed.
- Invitation acceptance received a local browser layout check.
- Pint and `git diff --check` passed.
- No provider calls were made.

## Next

R19-S03 — Add Usage Visibility.
