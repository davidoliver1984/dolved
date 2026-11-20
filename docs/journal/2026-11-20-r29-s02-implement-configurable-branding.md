# R29-S02 — Implement Configurable Branding

Date: 20 November 2026
Status: In progress — implemented and deployed; hosted visual acceptance remains.

Implemented on the Dolved–Dify delivery branch:

- Platform administration tenant list and tenant branding editor.
- Workspace-owned branding profile, safe assets and immutable revisions.
- Draft/save/apply/default/restore lifecycle with conflict protection and audit events.
- Static PNG/JPEG/WebP validation, decoded-dimension limits, animation rejection and safe PNG re-encoding.
- Bounded identity, colour, sign-in/chat copy and correspondence controls with responsive previews.
- Effective branding resolution for workspace shell, title/favicon, trusted sign-in, chat welcome and invitation acceptance.
- Immutable governance-email branding snapshot reuse plus tenant-branded invitation correspondence; ambiguous tenant-neutral authentication email remains Dolved.
- Unsaved-navigation warning, confirmed destructive UI choices and safe Dolved fallbacks.

Verification:

- Backend focus: 32 tests passed, 196 assertions. The command wrapper also reported pre-existing missing root-mounted contract fixture warnings; there were no test failures.
- Frontend focus: 4 files / 50 tests passed.
- ESLint: zero errors; controlled runtime tenant-image warnings only.
- Production Next.js build: passed, including the tenant list and branding editor routes.

Hosted review on 20 November identified four presentation defects. The follow-up correction:

- uses the configured chat welcome heading and introduction in the page header as well as the empty state;
- centres default and custom identities in the email preview and governance email header;
- accepts PNG favicon sources up to 2 MiB, resizes them to at most 512 pixels and caps the safe derivative at 512 KiB;
- keeps green success and red failure feedback directly above the sticky editor actions.

Follow-up verification: 19 chat tests passed; 4 branding tests / 43 assertions passed;
the focused tenant-email rendering test passed; ESLint reported zero errors, PHP Pint passed,
and the production Next.js build passed. A broader email test-file run encountered an unrelated
pre-existing Faker apostrophe escaping assertion; the changed branding-email case passed alone.

Remaining before verification: deploy the follow-up correction, confirm favicon persistence and
obtain David's visual approval of custom and fallback branding on desktop/mobile and light/dark surfaces.
