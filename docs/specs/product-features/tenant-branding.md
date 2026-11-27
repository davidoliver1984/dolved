# Tenant Branding and Correspondence

Status: Draft for David's review; not implemented or a completed R29 gate.
Date: 20 November 2026
Owner: David
Delivery: R29-S01 specification, R29-S02 implementation, R29-S03 visual acceptance within the separately authorised single-client pilot track. Canonical gate statuses are unchanged.

## Purpose

Platform administrators can give each tenant its own visual identity through Platform Admin → Tenants → [tenant] → Branding. They can select a logo replacing the Dolved product mark, configure colours, favicon and bounded presentation copy, preview the result, apply it, and switch back to Dolved defaults without losing the saved custom profile.

The pilot operates one client workspace. Branding must be keyed to the existing workspace/tenant identity and resolved centrally so later multi-client use does not require replacing the model. This does not implement tenant signup, billing, custom domains, delegated tenant administration or multi-client Dify operation.

## Related specifications and existing seam

- [Administration and Tenant Control Plane](administration-and-tenant-control.md)
- [Product Interface and Design System](product-interface-and-design-system.md)
- [Document Governance Notifications and Reminders](document-governance-notifications-and-reminders.md#email-templates-and-tenant-branding-seam)
- [Stock Dify delivery decision](../../adr/0041-use-stock-self-hosted-dify-for-single-client-delivery.md)
- [Pilot delivery record](../../operations/dolved-dify/DOLVED-DIFY-DELIVERY.md)

The existing Laravel `ResolveDocumentGovernanceEmailBranding` seam accepts immutable configuration/accent identities and returns a brand name, accent and optional logo URL. Its current Dolved resolver is the fallback. Extend/reuse that boundary; do not add independent per-template branding rules. Other correspondence paths must use the same resolved branding contract.

## Product contract

### Location and permission

Branding is a section of each tenant detail in Platform Admin. The existing platform-administrator permission is required for reading administrative drafts, uploading assets, saving, applying and switching modes. Workspace membership or workspace-owner status alone grants no branding administration in this release.

The page always identifies the tenant by its stable administrative identity, separately from its editable display brand. Platform administration itself retains Dolved branding, so the operator does not lose track of which system/tenant is being edited. The tenant's user-facing experience is skinned.

Backend policy checks and tenant-scoped reads/writes are mandatory. A browser tenant ID is not authority. Rendering public branding conveys no workspace membership or document access.

### Modes and retained profile

Each tenant has an effective mode: `default` or `custom`.

- Default resolves the versioned, approved Dolved identity.
- Custom resolves the selected validated immutable custom revision.
- Saving a draft does not change the live tenant experience.
- Applying a valid draft creates an immutable revision and switches to custom atomically.
- Switching to default changes the effective mode but preserves the last applied custom revision and editable draft.
- Switching back to custom reuses the last applied valid revision without uploading everything again.
- If no custom revision exists, the custom apply action is unavailable until validation succeeds.
- Resetting draft fields is separate from switching the live tenant to defaults. Discarding a draft requires confirmation when changes would be lost.

No arbitrary collection of named themes, scheduling or full theme-history editor is required. Keep version history necessary for audit and email snapshot integrity, and provide the simple last-applied custom/default switch.

## Branding form

The following bounds are proposed implementation requirements, not claims about existing UI.

| Section | Fields | Rules |
|---|---|---|
| Identity | Display brand/product name | Required for custom mode; plain text, 1–80 characters; does not rename the workspace's legal identity or change its slug |
| Logo | Primary logo and optional dark-background variant; accessible logo label | Primary logo required for custom mode; preserve aspect ratio; fit established responsive logo areas; no forced distortion |
| Icon | Favicon | Optional; if absent use a neutral initial-based icon for the custom brand, not an unrelated tenant's asset |
| Colours | Main/primary colour and accent colour; light/dark previews | Opaque six-digit hex colours; map to approved theme tokens, not arbitrary CSS |
| Welcome copy | Sign-in heading and introduction; chat welcome heading and introduction | Optional plain text, headings at most 120 characters and descriptions at most 400 |
| Correspondence | Sender display name, footer text, support contact | Optional plain text; sender display name at most 80 characters, footer at most 300; support contact is a validated email address |

Blank optional copy uses an approved template with the resolved brand name, not stale hard-coded Dolved prose. Empty optional support contact retains the deployment's configured support route; no arbitrary URL field is needed.

The editor does not change policy content, model prompts, answer disclaimers, permission messages, legal text, authentication security wording or contractual sender details. No administrator-authored HTML, CSS, JavaScript or email subject/body templates. Required legal/attribution notices remain.

### Form behaviour

Show current live mode and revision, draft changes, field-level errors and a preview using the existing design system. Provide Save draft, Apply custom branding, Use Dolved defaults and Discard unsaved changes. Applying or switching the live identity requires a concise confirmation naming the affected tenant; do not require a second reviewer.

Provide light/dark, desktop/mobile and email previews. Previews do not change live settings, send emails or make provider calls. Warn about unsaved navigation. On success show the applied mode/revision and refresh the visible effective preview.

Reject conflicting edits using a revision/version precondition rather than silently overwriting another administrator's changes. A failed upload, save or apply leaves the current live revision intact.

## Assets and accessibility

Accept static PNG, JPEG or WebP logos and PNG favicon uploads up to 2 MiB. Decode and validate content server-side, cap decoded dimensions, strip metadata and re-encode to safe derivatives; favicon derivatives are resized where needed and capped at 512 KiB. Do not accept raw SVG, HTML, animated assets or remote image URLs in this release. State accepted formats visibly rather than silently rejecting them.

Use server-generated identifiers and filenames. Keep original/staged uploads private. Publish only safe branding derivatives through a controlled route suitable for the web and email; do not expose object-storage URLs, directory listing or arbitrary files. Brand assets embedded in email must be treated as recipient-visible/publicly fetchable presentation assets, not confidential uploads. Do not use per-recipient image tokens or tracking pixels.

Keep immutable, versioned asset references so cached browsers and queued emails do not silently render a different logo. Retain assets referenced by pending/retained correspondence for the existing retention contract. Do not eagerly delete an old logo on replacement or reset. Clean abandoned drafts separately.

Keep semantic danger/warning/success colours independent of customer accents. Derive accessible foregrounds and bounded interaction states centrally. Validate relevant text and UI contrast in both themes; block invalid application and identify the failing preview. Do not claim that any selected hex value is automatically accessible. Missing dark logo may reuse the primary on a neutral contrasting container; never blindly invert it.

If custom configuration or an asset is invalid/unavailable at runtime, render the safe Dolved fallback and keep core actions usable. Record bounded diagnostic metadata without storing document/question content. A missing decorative asset must not break login, chat or email delivery.

## Tenant resolution and propagation

Use one backend-owned resolver returning effective mode, configuration revision, safe display values, resolved tokens and asset references. Cache by tenant and revision; invalidate after apply/default switch. Never use one process-global tenant branding cache.

Authenticated tenant routes resolve from the authorised workspace context. Switching workspace must not carry the previous tenant's logo, favicon or copy into the next workspace. Refresh resolved branding on workspace changes and navigation; revalidate open pages on focus without requiring logout. Do not hard-refresh or interrupt an active chat stream just to update branding.

Anonymous sign-in/password-reset pages require a trusted tenant context. For this single-client pilot, use a server-configured deployment-to-workspace binding, not a caller-supplied tenant ID. Validated invitation/reset context can identify a tenant where the existing authentication contract permits it. Without trustworthy unambiguous context, show Dolved defaults. Do not look up and reveal tenant identity merely from an entered email address. Platform-admin sign-in and cross-tenant platform screens remain Dolved-branded.

This resolution boundary must allow a later trusted host/tenant mapping, but this release does not build custom-domain management or infer tenancy from untrusted host/query values.

Apply resolved branding to the tenant shell, navigation, logo areas, sign-in/invitation/reset screens where context is trusted, page titles, favicon, chat welcome states, document-management surfaces and tenant notifications. Colours skin established components; they do not change layout or business behaviour. Dify's private admin console is not rebranded.

## Correspondence

All tenant-scoped correspondence emitted by Dolved uses the same resolved identity: invitations, verification/reset/security emails when tenant context is established, governance notifications, reminders and digests. Inventory actual mail/notification paths during implementation; do not claim universal coverage from the governance template alone. Tenant-neutral platform/security mail stays Dolved-branded.

Use the brand name/logo, accessible accent, approved bounded footer/support text and plain-text equivalent. Setting the sender display name does not change the authenticated From address, SMTP account, DKIM/SPF configuration or Reply-To. Those remain controlled deployment settings; customer-domain sending is outside scope.

Preserve the existing governance envelope contract: snapshot configuration identity and resolved rendering inputs no later than sealing; retries reuse that snapshot. New envelopes use the current active revision. Switching branding must not rewrite pending or historical envelope identities or resend messages. Other queued tenant emails must likewise retain sufficient branding identity at their established queue/render boundary for deterministic retries.

The correspondence inventory must record each path's trusted tenant source and snapshot boundary. Unknown/ambiguous tenant context uses Dolved fallback rather than the last visited workspace.

Existing required service/legal footers are retained. Replacing the Dolved product mark on tenant surfaces does not authorise removing licence-required notices or impersonating an unverified sending domain. A missing image falls back safely according to the existing rendering contract without changing recipients, authorisation, content eligibility or dispatch identity.

## Data, audit and technical boundary

Store draft, mode and applied revision against the existing workspace/tenant model; do not introduce a second tenant concept. Persist assets and immutable revisions with tenant ownership. Use migrations and existing storage/audit conventions rather than a deployment-only JSON settings file.

Audit draft save, custom apply and default/custom switches with actor, tenant, prior/new revision or mode, timestamp and safe change summary. No secrets or raw image bytes in audit records. Branding never affects authentication, tenant membership, document eligibility, source links, retrieval, generation, roles, billing or audit event meaning.

Reads for ordinary UI return only safe resolved presentation fields. Admin endpoints enforce platform permission and scope; implementation owns concrete route/resource names consistent with current tenant-admin routes. The spec does not invent a parallel settings framework.

## Acceptance checklist

- Platform admin opens Branding from the selected tenant and sees default/custom state and the correct tenant identity.
- Non-platform users cannot administer branding, including through direct requests or changed tenant/asset IDs.
- Uploads are validated server-side; unsafe/oversized images and malformed colours/copy cannot change live state.
- Draft previews affect no other user and send no mail.
- Apply changes the tenant shell, page title/favicon and relevant correspondence consistently after refresh/navigation.
- Default immediately becomes the effective resolved profile, retains saved custom settings, and custom can be reapplied for demonstration.
- Failed saves/uploads and concurrent edits preserve the last valid live state.
- Light/dark and narrow mobile views retain readable text, logo proportions, visible focus and stable layout; David reviews the actual presentation.
- Switching between two synthetic tenant contexts in provider-free tests cannot leak branding or grant access. This is isolation coverage, not a second live-client deployment.
- Anonymous pages use only trusted tenant context, otherwise Dolved defaults; platform administration stays recognisably Dolved.
- Each actual tenant correspondence path uses the right identity; already-sealed/queued messages retain their snapshot across a branding change and retry.
- Lost assets use safe fallback without blocking delivery; retained snapshots still resolve their asset versions.
- Branding does not modify Dify configuration, answers, corpus, governance, permissions or source-link authorisation.
- Persistence survives restart; branding records/assets are included in existing backup and restore coverage.

Use ordinary focused engineering checks and synthetic preview/mail evidence. No additional answer-quality campaign or access to the sealed 40 questions. No live emails to clients without the existing delivery authority; use the approved test inbox when hosted delivery verification is needed.

## Non-goals and review boundary

No arbitrary theme builder, typography upload, page editor, free-form email templates, authenticated sender-domain setup, tenant billing/self-service, multi-client hosting or automatic Dify rebranding. These may become separately specified work later.

This is the first draft implementing David's requested product direction. Specific field bounds, asset formats, anonymous-context policy and propagation behaviour above are concrete proposed defaults for review. The specification does not assert that the feature is built or approved visually, and does not advance canonical R28/R29 gates.
