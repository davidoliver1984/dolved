# ADR 0005: Use Sanctum and Fortify for First-Party SPA Authentication

## Status

Accepted

## Date

2026-07-26

## Context

The platform needs browser authentication before document, retrieval and account
features are built. The first-party user interface is a Next.js application and the
system of record is the Laravel API.

The authentication boundary must remain clear. Laravel must own identities, sessions,
email verification, password recovery and authorisation. Next.js must not become a
mandatory backend-for-frontend (BFF) or a second authorisation layer, although it may
perform selective server-side fetches for rendering and user experience.

Laravel describes Sanctum's stateful cookie authentication as its recommended
approach for a first-party SPA. This mode requires the SPA and API to share the same
top-level domain. The intended production hosts are `app.maketime.ai` and
`api.maketime.ai`.

Laravel Fortify provides maintained authentication actions, password brokers,
notifications and email-verification mechanics without prescribing a user interface.
Those mechanics can sit beneath a stable, application-owned JSON API.

## Decision

Use Laravel Sanctum's stateful session-cookie authentication for the first-party SPA,
with headless Laravel Fortify beneath the platform's `/api/auth/*` JSON contract.

The design is:

* the browser may call Laravel directly using credentialed requests;
* Next.js remains the presentation layer and may selectively fetch server-side, but
  is neither a mandatory BFF nor an independent authorisation authority;
* Laravel owns every security decision;
* local development uses `localhost` with explicit ports in Sanctum's stateful-domain
  configuration;
* production uses hosts under one top-level domain, initially `app.maketime.ai` and
  `api.maketime.ai`, with a shared `.maketime.ai` session-cookie domain;
* Fortify's actions, password broker, notifications and verification mechanics are
  reused rather than reimplemented;
* verification notifications link to Laravel's temporary signed verification
  endpoint; Laravel verifies the user and redirects to a Next.js result page;
* open registration is controlled by configuration so invite-only registration can
  be introduced without changing the API contract;
* email input is trimmed and lowercased in one shared canonicalisation component
  before authentication, recovery or persistence, and the canonical database value
  has a unique constraint;
* sessions are stored in PostgreSQL with an initial lifetime of 120 minutes;
* a later session-store move to Redis is an operational change, not an authentication
  architecture change;
* the Laravel session cookie is `HttpOnly` and cannot be read by frontend JavaScript;
* Sanctum's separate `XSRF-TOKEN` cookie is intentionally frontend-readable so its
  value can be returned in the `X-XSRF-TOKEN` request header;
* production cookies are secure and use `SameSite=Lax`;
* the initial password rule is at least 12 characters with upper- and lowercase
  letters, a number and a symbol;
* a successful password reset invalidates all existing database sessions for that
  user and rotates the remember token;
* authentication and password-recovery responses are generic where account
  enumeration would otherwise be possible, while malformed inputs and actionable
  registration validation errors remain explicit;
* Mailpit captures local verification and password-reset mail.

### Route protection

The complete pre-verification allow-list is:

Unauthenticated:

* `GET /sanctum/csrf-cookie`
* `POST /api/auth/register` when registration is enabled
* `POST /api/auth/login`
* `POST /api/auth/forgot-password`
* `POST /api/auth/reset-password`

Authenticated but not yet verified:

* `GET /api/auth/user`
* `POST /api/auth/logout`
* `GET /api/auth/email/verify/{id}/{hash}` with a valid temporary signature
* `POST /api/auth/email/verification-notification`

Every platform-functionality route outside that allow-list is protected in Laravel by
both `auth:sanctum` and `verified`. Next.js route guards are user-experience aids only.

## Alternatives considered

### Personal access or JWT bearer tokens in the browser

Rejected for the first-party SPA because they expose long-lived credentials to
frontend JavaScript and duplicate browser-session capabilities already provided by
Sanctum.

### Next.js as a mandatory BFF

Rejected because it adds a required network hop and risks splitting authentication
and authorisation responsibilities across two applications. Selective server-side
fetching remains available where it improves rendering.

### Custom Laravel authentication implementation

Rejected because reimplementing password brokers, reset tokens, notifications and
verification would add security-sensitive code where Fortify already provides tested
framework mechanics.

### Third-party identity provider

Deferred. It may become appropriate for social login, enterprise federation or
managed identity, but is unnecessary for the initial first-party email/password
experience.

## Consequences

### Positive

* Laravel is the single security authority.
* Browser credentials use normal secure session and CSRF protections.
* The application owns a consistent JSON contract without duplicating Fortify's
  security mechanisms.
* The web application remains deployable independently and is not forced to proxy
  every API request.
* Local email flows are observable without sending external mail.
* Registration policy and session storage can change later through configuration.

### Negative

* The production SPA and API must remain on the same top-level domain.
* Cross-origin credentialed requests require careful CORS, cookie and Sanctum-domain
  configuration.
* Server-side Next.js fetches must deliberately forward the browser's cookies.
* Invalidating all sessions on password reset prioritises security over preserving
  other signed-in devices.
* A strict initial password policy may add friction for users without password
  managers.

## References

* [Laravel Sanctum SPA authentication](https://laravel.com/docs/13.x/sanctum#spa-authentication)
* [Laravel Fortify](https://laravel.com/docs/13.x/fortify)
* [Laravel email verification](https://laravel.com/docs/13.x/verification)
* [Laravel password reset](https://laravel.com/docs/13.x/passwords)
* [Next.js backend-for-frontend guide](https://nextjs.org/docs/app/guides/backend-for-frontend)
