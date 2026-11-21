# R29-S03 — Apply and Visually Verify Configurable Branding

Date: 21 November 2026
Status: In progress — hosted custom identity approved; consolidated matrix remains.

The configurable Cherish identity is deployed on the hosted pilot. David reviewed the
user-facing result, confirmed the corrected branded email arrived in the inbox rather than
spam, and reported that the result looked correct. The deployed refinement centres the
configured identity in correspondence, uses the configured chat heading, supports safe PNG
favicon derivatives, places success/failure feedback above the sticky editor actions and
uses tenant wording in the email footer.

Provider-free deployment checks passed and the effective hosted branding resolver returned
the applied custom revision. The 21 November redeployment preserved the same web image
identity and the public branded sign-in page continued to render the Cherish identity.

Remaining consolidated acceptance:

- confirm favicon persistence after reload;
- inspect custom and Dolved-fallback identities in both light and dark themes;
- inspect the narrow mobile layout and keyboard/focus behaviour;
- confirm invitation/password-reset boundaries and source/document screens retain the
  correct identity.

Mobile email review exposed one further colour defect: a light tenant accent with an
approved black foreground was rejected by an email-only white-text check and silently
replaced with Dolved green; the preferences link was also hard-coded green. Commit
`9c694e8b` now validates the configured accent together with its computed foreground, uses
the tenant primary colour for the link and adds light-colour-scheme hints for mail clients.
The deployed Cherish values resolve to button `#DA95DF`, black button text and link
`#8D2F93`. Twenty-four focused tests / 156 assertions, Pint and full provider-free
production verification passed. A post-fix branding check was sent to David.

R29-GATE remains open until that matrix is explicitly accepted. No evaluation question or
provider call was used.
