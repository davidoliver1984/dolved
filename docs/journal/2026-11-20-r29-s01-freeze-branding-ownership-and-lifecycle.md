# R29-S01 — Freeze Branding Ownership and Lifecycle

Date: 20 November 2026
Status: Verified for the separately authorised pilot implementation track; canonical later gates remain unchanged.

The product contract is `docs/specs/product-features/tenant-branding.md`, SHA-256
`90121361327f7452ae499f7843d37e48c7233c795dcb2407e3baee27d222f417` at implementation
start. It is a product specification, not an ADR. No new consequential architecture choice
was required: the implementation uses the existing Workspace as the stable tenant identity,
the existing platform-administrator authority, existing storage/audit conventions and the
existing immutable governance-email branding seam.

The accepted lifecycle is default/custom with a private editable draft, optimistic version
precondition, immutable applied revisions, retained last custom revision and explicit
default/custom switches. Platform administration remains Dolved-branded. Tenant branding
cannot affect identity, authorisation, governance, retrieval, answers or Dify configuration.
Anonymous branding requires a trusted deployment or invitation context; ambiguous contexts
fall back to Dolved.

No ADR was added. The next action was implementation under R29-S02, followed by hosted
light/dark, desktop/mobile and fallback review under R29-S03.
