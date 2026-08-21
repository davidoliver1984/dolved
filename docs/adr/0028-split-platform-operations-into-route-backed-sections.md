# ADR 0028: Split Platform Operations into Route-Backed Sections

## Status

Accepted

## Date

2026-08-21

## Relationship to prior ADRs

### Consumes ADR-0026 unchanged

Every telemetry, alerting, SLO, policy-reconciliation and platform-administrator
authorization decision ADR-0026 makes is treated as settled fact. This ADR
redistributes *where* already-accepted curated operational content is
rendered; it does not decide what that content is, how it is computed, who
may mutate it, or which system owns acknowledgement/silencing. In
particular, this ADR relies on and does not revisit ADR-0026's own explicit
rejections of a Grafana iframe and of building alert
acknowledgement/silencing into Dolved, and its "curated main view versus
specialist console" governing distinction.

### Extends ADR-0027 narrowly, on one specific point

ADR-0027 (Accepted) fixed the full route hierarchy for chat, documents and
workspace administration, and defined a stable-primary-region /
contextual-region sidebar model for two contexts: chat and workspace
administration. For Platform Operations specifically, ADR-0027 fixed exactly
one thing — the single route `/app/platform/operations` — and left its
contextual navigation undefined (Platform Operations was not one of the two
contexts ADR-0027's contextual-region model enumerates). Everything else
ADR-0027 decided (the adaptive shell, semantic tokens, shadcn/Radix
component rules, Lucide icon policy, theme behaviour, WCAG 2.2 AA baseline,
live-region policy, no-dead-control rule, and the platform-administrator
authority boundary it inherits from ADR-0026) is consumed unchanged. This
ADR extends ADR-0027 only by: (a) subdividing the single
`/app/platform/operations` route into a small nested route family, and (b)
defining a third contextual-sidebar context — platform-operations context —
alongside the two ADR-0027 already defined.

### Supersedes nothing

No telemetry, security, policy, ownership, or ADR-0027 shell/token/component
decision is superseded. The current single-page implementation is not
treated as a defect against ADR-0027 — it is a correct, accepted
implementation of a decision ADR-0027 deliberately left narrow. This ADR is
the explicit extension that decision needs before Platform Operations grows
a contextual navigation region, exactly because ADR-0027 requires such an
extension to be its own decision record rather than something silently
added during a later visual-acceptance session.

## Context

### Verified current implementation

Platform Operations lives at `apps/web/src/app/app/platform/operations/page.tsx`
(one server component, no nested `layout.tsx`/`loading.tsx`/`error.tsx`),
rendering, in order: a header (health status, freshness, an unconditional
"Open specialist console" link to `grafana_url`); a "Service-level status"
section (per-SLO cards); an "Active alerts" section (alert cards, a
conditional "Open alert console" link to `alertmanager_url`, per-alert
conditional runbook links); an "Operational metrics" section (a grid built
from the `metrics` map); and `OperationalPolicyPanel`, a separate client
component rendering the desired-policy form and per-target reconciliation
state. An `unavailable` snapshot status short-circuits all of this to a
single degraded notice.

All of the above is sourced from **one** server-side call,
`platformOperations()` in `apps/web/src/lib/server-api.ts`, which fetches
`GET /api/platform/operations/health` once and returns the full
`PlatformOperationsSnapshot` (`health_status`, `as_of`, `freshness`,
`metrics`, `slos`, `alerts`, `grafana_url`, `alertmanager_url`,
`operational_policy`). Access is gated twice: `hasPlatformOperationsAccess()`
(a separate `GET /api/platform/operations/access` call) controls whether the
shell shows the "Platform operations" nav link at all, and the page's own
loader independently maps the health-endpoint response — `401` →
redirect to `/login?next=/app/platform/operations`, `403` → redirect to
`/app`, any other failure → the degraded-notice state, matching the
`unavailable`/forbidden/unauthorized handling already established for this
route. `hasPlatformOperationsAccess()` similarly returns `false` on any
non-`ok` response.

In `apps/web/src/components/AppShell.tsx`, the contextual sidebar region is
keyed off a `workspaceId` extracted from the pathname
(`/^\/app\/workspaces\/([^/]+)/`). Because Platform Operations lives at
`/app/platform/operations` — outside `/app/workspaces/...` entirely —
`workspaceId` is `null` there, so the contextual region renders as an empty
spacer today: **no contextual content exists for Platform Operations**,
unlike chat context ("Recent" conversations) and administration context
(`Overview`/`People & roles`/`Invitations`/`Usage` plus a "Back to chat"
link). There is no "return to workspace/chat" affordance anywhere on the
Platform Operations page today.

### The problem

The single page combines four operationally distinct concerns — health/SLO
overview, active alerts, global telemetry, and operational policy — into one
long scroll. This makes it hard to navigate to a specific concern, and gives
each concern no independent loading/error/unavailable state, no deep link,
and no page-specific orientation (title, landmark). Codex correctly
identified that fixing this is a genuine information-architecture decision —
introducing multiple routes and a third contextual-navigation context is
exactly the kind of product-wide navigation decision ADR-0027 itself says
belongs in an ADR, not something to add silently during R21-S04's visual
acceptance pass.

### A known implementation defect against ADR-0026, corrected by this ADR

ADR-0026 (Accepted) states it "extends" ADR-0006's cross-tenant
`404`-not-`403` concealment discipline "to platform-operational surfaces."
The verified current implementation does not do this: both the Laravel API
(`GET /api/platform/operations/*`) and the Next.js frontend's
`platformOperations()` produce and act on a literal HTTP `403` for an
authenticated non-platform-administrator, redirecting to `/app` instead of
concealing the surface's existence. An accepted architectural decision is
authoritative over an existing implementation that has drifted from it — the
existing behaviour cannot stand simply because it is what currently ships.
This is a genuine, verified implementation defect against ADR-0026, not a
discrepancy this ADR is entitled to leave unresolved or accept as an
alternative convention. Section "Authority and security boundaries" below
states the corrected behaviour precisely, including the exact implementation
boundary between Laravel and the browser, and "Implementation boundary"
allocates the fix to R21-S05 alongside the route split this ADR otherwise
introduces.

## Decision

### 1. Route hierarchy

| Route | Destination |
|---|---|
| `/app/platform/operations` | **Overview** — remains the bare route directly, not `/app/platform/operations/overview`. This mirrors the precedent ADR-0027 already established for workspace administration, whose `Administration overview` likewise lives at the bare `/app/workspaces/{workspace}/administration` route with no `/overview` suffix. |
| `/app/platform/operations/alerts` | Active alerts |
| `/app/platform/operations/telemetry` | Global telemetry |
| `/app/platform/operations/policy` | Operational policy |

All four are genuine Next.js App Router routes (real `page.tsx` files under
real directory segments) — never client-only tabs, fragment/anchor links,
query-parameter-selected views, or conditional sections gated by local
component state. The route is the authoritative owner of the active
Platform Operations destination: direct links work, refresh reloads the same
section, browser back/forward moves between sections normally, active
navigation state and `aria-current` derive from the current route (not from
any separately-tracked "selected section" variable), and each route
independently defines its own loading, error and unavailable states rather
than inheriting one shared page-level state machine for all four concerns.

### 2. Navigation model

**Stable primary region** (unchanged): "Platform operations" remains exactly
one capability-gated entry in the stable primary sidebar, exactly as
ADR-0027 defined it — still visually distinct from workspace-scoped links,
still gated on `canOperatePlatform`, still using its existing `Activity`
icon and its existing `pathname.startsWith("/app/platform/operations")`
active-state check (which correctly stays active across all four nested
routes, since it is asking "is the user anywhere in Platform Operations,"
not "is the user on the Overview route specifically" — see "URL and active
state" below for why the *contextual* Overview link needs a different check).

**Contextual region** (new — the third context ADR-0027's model gains):
when the user is anywhere under `/app/platform/operations`, the contextual
sidebar region renders:

- Overview (`Gauge` icon — already used for the current "Service-level
  status" section, a natural fit for a summary/overview destination)
- Active alerts (`BellRing` icon — already used for the current alerts
  section, unchanged)
- Global telemetry (`LineChart` icon — new; `Activity` is not reused here
  because it already means "Platform operations" at the stable-region level,
  and reusing it for one specific nested section would make the same icon
  mean two different things in the same sidebar)
- Operational policy (`SlidersHorizontal` icon — new; distinct from `Save`,
  which remains the policy form's *submit-button* icon, not a section
  identity)

This is a semantic icon *choice*, not a new architectural rule: ADR-0027's
existing Lucide-only icon policy governs it unchanged, and a future icon
substitution for stylistic reasons would not need its own ADR.

**Return affordance.** Rather than inventing a new authority boundary or a
new UI pattern, the contextual region reuses the exact mechanism workspace
administration's contextual region already established: a "Back to chat"
link beneath the four section links, styled identically
(`text-foreground-muted` underline-on-hover pattern already used by
administration's own back link), pointing at `/app` — the generic app-root
destination, requiring no new "last visited workspace" tracking. This
affordance is only ever reachable by a user who already holds
platform-administrator authority (it renders inside an authorised page), so
it is unaffected by, and unrelated to, the authorisation correction in
"Authority and security boundaries" below.

**Mobile**: no separate navigation system. The same off-canvas
sheet/drawer ADR-0027 already defines renders this contextual region
exactly as it renders chat's or administration's — same trigger, same
focus-trapping, same touch-target floor.

### 3. Page responsibilities

**Overview** (`/app/platform/operations`) is a concise summary and
navigation surface, matching ADR-0026's own description of what belongs on
"Dolved's main platform admin": overall health status and freshness; the
provisional/unmeasured explanation where applicable; SLO status summaries;
an active-alert **count or brief summary** (severity/subsystem/duration at a
glance — not the full alert list); a policy-application summary (for
example, "N of M settings active"); and links to the three specialist
routes below. It does **not** continue rendering the complete Alerts,
Telemetry or Policy interfaces underneath these summaries — each summary
card links to its authoritative detailed route instead of duplicating that
route's full content. Where Overview and another route would otherwise
independently compute the same derived value (for example, `health_status`
and `freshness`), both consume a single shared, authorised summary
projection rather than each re-deriving it.

**Active alerts** (`/app/platform/operations/alerts`) owns: the full active
alert list and its empty/loading/unavailable/failure states; the alert
detail fields ADR-0026 already permits (severity, subsystem, start
time/duration, impact); the existing conditional "Open alert console" link
to `alertmanager_url`; and per-alert conditional runbook links. No
acknowledgement, dismissal, incident-ownership or mutation workflow is
introduced — ADR-0026 already critiqued and rejected building that into
Dolved, and this ADR does not reopen that critique.

**Global telemetry** (`/app/platform/operations/telemetry`) owns: the
existing "Operational metrics" grid (global, non-tenant-identifying
signals only — already enforced today via the telemetry attribute allowlist
ADR-0012/ADR-0026 establish, unchanged by this ADR); partial/unavailable/
stale/unmeasured presentation for individual metrics, as already
implemented; and the existing "Open specialist console" link to
`grafana_url`, relocated here from Overview's header since Telemetry is
where a user actually wants the deep-diagnostic jump-off point. No iframe
embedding is introduced — ADR-0026 already rejected this explicitly ("blurs
the curated-versus-specialist distinction this document's governing
principle depends on").

**Operational policy** (`/app/platform/operations/policy`) owns the
existing `OperationalPolicyPanel` content unchanged: desired policy
versions, trace-sampling percentage, slow-operation threshold, log/trace/
metric retention settings, per-setting/per-target acknowledgement and
reconciliation state (including partial activation, stale, retrying, failed
and conflicting states), and the existing submit action. No policy
semantics, enforcement targets, acknowledgement trust boundary, activation
rule, or reconciliation behaviour changes from ADR-0026's design.

### 4. Authority and security boundaries

Every one of the four routes is restricted by the same
platform-administrator capability the current single page already enforces
— no route relaxes or narrows that gate relative to today. Concretely: each
route's own server-side loader independently calls the same
health/access-checking mechanism `platformOperations()` already uses (see
"Data and loading ownership" below), so a direct request to
`/app/platform/operations/policy` is authorised exactly as strictly as a
direct request to `/app/platform/operations` is today — navigation hiding is
never the security boundary, consistent with ADR-0006's "hidden convenience
must never carry security meaning" and ADR-0027's identical treatment of
every other route it defines.

**Corrected behaviour, applied identically to all four routes.** ADR-0026's
accepted concealment decision is authoritative; the current implementation's
`403`-plus-redirect behaviour for an authenticated non-platform-administrator
is a defect against it (see "Context," above), not an alternative this ADR
is free to keep. The behaviour every one of the four routes must produce:

- **Unauthenticated request** — redirect to `/login?next=<that route's own
  path>`, validated against the closed login-return allowlist defined below.
  Concealment is not at issue here; there is no authenticated actor yet for
  "resource exists but you can't see it" to distinguish from "resource
  doesn't exist," so a safe `next` redirect remains the right mechanism,
  applied per-route (each route's own path, not a single shared path) —
  this ADR only extends which exact paths that mechanism accepts, per the
  next paragraph.

**Closed login-return allowlist, extended from one path to four.** The
existing login flow (`apps/web/src/app/login/page.tsx`,
`apps/web/src/components/AuthForm.tsx`) already validates a `next`/`returnTo`
value against a closed allowlist rather than accepting an arbitrary
`next` parameter — verified directly: `login/page.tsx` currently checks
`next === "/app/platform/operations"`, a single literal string, and
`AuthForm.tsx`'s `returnTo` prop is typed as that same single literal. This
ADR extends the allowlist to contain **exactly** the four routes it
introduces:

- `/app/platform/operations`
- `/app/platform/operations/alerts`
- `/app/platform/operations/telemetry`
- `/app/platform/operations/policy`

These are exact accepted pathnames, checked by exact string equality against
this closed set — not a prefix/`startsWith` check. An arbitrary nested
Platform Operations path is not accepted merely because it begins with
`/app/platform/operations`: a value like
`/app/platform/operations/anything-else` is rejected exactly as an
unrelated path would be, falling through to the existing safe post-login
default (`/app`, or `/verify-email` where applicable), not silently
"close enough" accepted. External URLs, protocol-relative URLs
(`//evil.example`), percent-encoded or otherwise obfuscated bypass attempts,
and any `next` value carrying an unexpected query string or fragment beyond
the bare pathname are all rejected the same way — this remains a closed
allowlist of exact values, never a permissive validator, and this ADR grants
no license to widen it into a general arbitrary-path `next` parameter or an
open redirect. The originally requested one of the four valid routes is
preserved through login precisely because it is now a member of the
allowlist, not because the check was loosened. Route and authentication
tests (extending the existing `login/page.test.tsx` and `AuthForm.test.tsx`
coverage) must cover all four allowed routes individually plus
representative rejected values (a nested-but-unlisted path, an external
URL, a protocol-relative URL, and a value carrying a query string or
fragment).
- **Authenticated, but not a platform administrator** — the accepted
  not-found/concealment treatment, on every route: the browser renders the
  same not-found presentation already used for cross-workspace concealment
  elsewhere in the product (for example
  `app/app/workspaces/[workspacePublicId]/not-found.tsx`), not a redirect to
  `/app` and not a page that discloses "you lack permission for this."
- **Platform administrator** — unchanged: the route's normal content.
- **Health check unreachable/failing** (for a platform administrator who is
  otherwise authorised) — unchanged: the existing degraded "Health data is
  unavailable" notice, not concealment; unavailability is not the same
  condition as unauthorised access.

**Exact implementation boundary — this is a Laravel change, not only a
Next.js remapping.** Verified directly against the real implementation: the
Laravel route group at `apps/api/routes/api.php` applies
`can:access-platform-operations` (`Illuminate\Auth\Middleware\Authorize`)
across `/api/platform/operations/{access,health,policy}`, backed by the
plain boolean `Gate::define('access-platform-operations', ...)` in
`apps/api/app/Providers/AppServiceProvider.php`. A plain boolean gate
closure gives Laravel's default `AuthorizationException` handling, which
resolves to a genuine `403` — there is no existing status customisation
anywhere in `apps/api` (confirmed by inspection of `bootstrap/app.php` and
`app/Exceptions/`) for Next.js to have mis-mapped. The API itself must be
the one to emit the concealed response; the browser cannot conceal what the
API has already disclosed via a distinguishable `403`. The correction is
therefore two-sided and scoped narrowly to this one gate/route group — not a
Laravel-wide `AuthorizationException`-to-`404` change, which would be a much
larger, unrelated blast radius this ADR does not authorise.

**A `404` from this protected endpoint is intentionally, uniformly the safe
concealed outcome — never something the browser tries to sub-classify.**
Every `404` `/api/platform/operations/*` response is treated as
authority-concealment, full stop; the browser makes no attempt to
distinguish "this specific resource is genuinely absent" from "this
resource exists but is concealed from you" by inspecting response wording,
an error code, or any other signal in the body. That distinction is exactly
what the concealment discipline exists to deny an unauthorised caller, so a
browser-side heuristic that tried to recover it would itself defeat the
purpose. This holds for every route's initial page-load check and for the
Policy mutation alike — see below.

- **Laravel**: the `access-platform-operations` authorization outcome for
  `/api/platform/operations/*` must respond `404`, with a generic
  not-found body carrying no "forbidden"/"permission" wording, for an
  authenticated non-platform-administrator — scoped to this gate/route
  group only, leaving every other gate's existing `403` behaviour (account
  disabled, workspace-role authorization, etc.) untouched.
- **Next.js — the shared data layer stays framework-neutral; only the owning
  route navigates.** `apps/web/src/lib/server-api.ts`'s `platformOperations()`
  is a shared function every one of the four routes calls; it must not call
  Next.js's `notFound()` (or `redirect()`) itself — a shared data-fetching
  utility performing framework navigation as a side effect is exactly the
  kind of responsibility-boundary violation this correction must not
  introduce. Instead, `platformOperations()` maps a `404` from these
  endpoints into a new, explicit typed result — `{ status: "concealed" }` —
  distinct from `unavailable`, so a caller can never confuse "authority was
  revoked/absent" with "the backend/health-check is unreachable"; the
  existing `unauthorized`/`forbidden`/`unavailable`/`ok` result shape gains
  this fourth variant rather than overloading one of the existing ones (and
  `forbidden`, the prior name for the now-superseded `403` case, is retired
  in favour of `concealed`). Each owning route's own page component — the
  Next.js-specific layer — then performs the actual mapping:
  - `unauthorized` → `redirect()` to `/login?next=<that route's own path>`,
    the validated exact path from the closed allowlist above;
  - `concealed` → `notFound()`, called from the route's own server
    component, never from the shared data layer;
  - `unavailable` → the existing degraded "Health data is unavailable"
    notice.

  Since all four routes need the same `notFound()`-triggered presentation
  and the four routes are sibling directories under
  `apps/web/src/app/app/platform/operations/` with no existing shared
  `layout.tsx`, a shared `not-found.tsx` at that directory level (introducing
  a minimal pass-through `layout.tsx` there if one is needed for the
  not-found boundary to cover all four sibling routes) is the correct,
  non-duplicative place for the rendered concealment UI, mirroring the
  existing workspace-scoped `not-found.tsx` pattern rather than inventing a
  new one. Each route still independently calls `notFound()` itself on
  receiving `concealed` — the shared `not-found.tsx` is what renders as a
  result of that call, not a mechanism that calls it on the routes' behalf.
- **Unchanged, needs no edit**: `hasPlatformOperationsAccess()` (used only
  to decide whether `AppShell` shows the "Platform operations" nav link at
  all) already checks `response.ok`, which is `false` for both `403` today
  and `404` after this correction — its nav-hiding behaviour is unaffected
  either way, consistent with navigation hiding never being the security
  boundary in the first place.

**Mid-session policy-authorisation loss.** `OperationalPolicyPanel`'s save
action (`createOperationalPolicy()`, a direct browser mutation against
`POST /api/platform/operations/policy`) is currently the **only** existing
Platform Operations mutation — there is no alert-acknowledgement,
alert-silencing or other mutation to extend this behaviour to, and this ADR
does not invent one. Platform-administrator authority can be revoked between
the Policy route loading and this mutation being submitted, so the mutation
can itself receive the same concealed `404` a page load would.

**`createOperationalPolicy()` mirrors `platformOperations()`'s typed-result
pattern — it also returns an explicit `concealed` outcome, and also never
calls `notFound()` itself.** `apps/web/src/lib/api.ts`'s
`createOperationalPolicy()` is the client-side counterpart to the shared
server data layer's `platformOperations()`, and the same responsibility
boundary applies to it: on a `404`, it does not throw a generic `ApiError`
that a submit handler would have to inspect and pattern-match by status
code, and it does not attempt any framework navigation itself (there is no
server component here for it to hand off to synchronously the way a route's
loader can — see below for how the mapping still ends at `notFound()`).
Instead it resolves to the same explicit `{ status: "concealed" }` shape
`platformOperations()` uses, so `OperationalPolicyPanel`'s submit handler
can branch on a typed result rather than parsing a caught error's status
code or message text.

The required behaviour when the mutation resolves to `concealed`:

- it is treated as **authority loss/concealment**, a distinct condition from
  an ordinary validation error, an ordinary policy-reconciliation
  state (stale/retrying/failed/conflicting), or backend unavailability — it
  must not be folded into any of those existing error presentations;
- the form must not display wording such as "permission denied,"
  "forbidden," or a generic "policy save failed" message that misrepresents
  the condition as a validation or transient failure the user might
  meaningfully retry;
- the mutation is **not** automatically retried — authority that has been
  revoked will not become valid by resubmitting the same request;
- no optimistic policy state is retained as though the save had succeeded —
  any optimistic UI update the form may have applied is discarded, not left
  showing a value that was never actually persisted;
- the client triggers a route revalidation/refresh (for example, Next.js's
  `router.refresh()`) rather than handling the concealment itself;
- that refresh causes the Policy route's own **server** loader to re-run its
  live authorisation check (per ADR-0026's "checked live... on every
  request, never cached" requirement, which this ADR does not weaken), which
  receives the same typed `concealed` result defined above and maps it to
  the route-level `notFound()` presentation exactly as an initial page load
  would;
- the **client-side submit handler must not call Next.js's `notFound()`
  directly** — that remains the owning server route's responsibility alone,
  the same framework-neutral/owning-route boundary established above for
  the page-load case, not a special case the mutation path is allowed to
  bypass.

Tests must cover authority revocation occurring between initial Policy-route
page load and mutation submission — asserting the mutation's failure
response triggers a refresh rather than a client-rendered permission
message, and that the resulting server-loader re-check is what ultimately
produces the `notFound()` presentation.

Navigation hiding remains only a usability convenience — this correction
does not change that principle, it makes the *actual* boundary (the API
response every route's server-side loader independently receives) match
ADR-0026 for the first time, consistent with ADR-0006's "hidden convenience
must never carry security meaning" and ADR-0027's identical treatment of
every other route it defines. Every one of the four routes performs this
check independently; none inherits authorisation from another, and none is
authorised merely because navigation to it was hidden or shown.

No route acquires a workspace identifier in its path; all four remain
platform-level routes exactly as `/app/platform/operations` is today.
Global telemetry continues to carry no tenant identifiers or tenant
content — this is an existing, unchanged invariant enforced by the
telemetry attribute allowlist, not something this ADR is introducing or
re-verifying. Existing authentication/session rules are untouched.

### 5. Data and loading ownership

**No new API endpoint is required.** The existing single endpoint,
`GET /api/platform/operations/health`, already returns the full snapshot
(`health_status`, `slos`, `alerts`, `metrics`, `grafana_url`,
`alertmanager_url`, `operational_policy`) in one authorised, tenant-free
response. Each of the four routes' own server component calls the existing
`platformOperations()` function independently and renders only the subset
of the returned snapshot relevant to that route — Overview renders a
condensed projection of `health_status`/`slos`/`alerts.values.length`/
`operational_policy`'s summary counts; Alerts renders `data.alerts`
in full; Telemetry renders `data.metrics` in full; Policy renders
`data.operational_policy` in full, unchanged, still via
`OperationalPolicyPanel`. This is a genuine, named trade-off: each route
fetches the complete snapshot even though it renders only part of it, in
exchange for introducing zero new backend surface and not reopening
ADR-0026's API contract. A future session may split the backend response by
concern as a pure performance optimisation if the over-fetch proves costly
in practice — that would not be an authority or contract change, only a
payload-shape change, and is explicitly **not** required by this ADR.

Where a value is genuinely shared across routes (the health/freshness
summary Overview shows in full and the other three routes may echo in a
condensed header), it is computed once, in one shared, authorised
projection/component, and consumed by every route that needs it — never
independently recomputed per route. This keeps Overview a projection of
already-authoritative operational state, never a second source of truth for
it.

### 6. State and accessibility requirements

Each route implements only the states genuinely applicable to its own data
— Policy's route does not need an "alerts unavailable" state, and Alerts'
route does not need a "policy conflicting" state; ADR-0027's requirement
that every *applicable* state (loading, healthy/success, empty, unmeasured,
partial, stale, unavailable, authorisation loss, backend failure) be
handled is preserved without forcing inapplicable states onto pages where
they have no meaning.

Every other ADR-0027 requirement carries over unchanged and is not
restated in full here: dark/light theme tokens, the shadcn/Radix component
rules, the Lucide-only icon policy, keyboard operability, focus management,
the adaptive sidebar/mobile-drawer behaviour, the WCAG 2.2 AA baseline, the
bounded live-region policy, and the no-dead-control rule. Each of the four
routes carries a distinct page `<title>` and heading (`h1`)/landmark
structure (for example, "Alerts · Platform operations · Dolved") so the
current section is identifiable independently of colour or icon, exactly as
ADR-0027 already requires for every other routed destination.

### 7. URL and active-state behaviour

- `/app/platform/operations` remains the Overview route directly, as
  decided above — the preferred option, and the one consistent with
  workspace administration's existing precedent.
- **Active-state matching is exact per contextual link, not prefix-based.**
  The stable-region "Platform operations" entry correctly keeps using
  `pathname.startsWith("/app/platform/operations")`, since it means "is the
  user anywhere in this area" — that check is unchanged and correct. The
  new *contextual* Overview link, however, must use an **exact** match
  (`pathname === "/app/platform/operations"`), not a prefix match — a
  prefix match would incorrectly mark Overview active on
  `/app/platform/operations/alerts` too, since that path also starts with
  the Overview path. Alerts/Telemetry/Policy each use their own suffix
  match (`pathname.endsWith("/alerts")`, etc.), mirroring the exact pattern
  `AppShell.tsx` already uses for administration's contextual links today
  (`pathname.endsWith("/people")`, `pathname.endsWith("/usage")`, etc.) —
  no new active-state mechanism is invented, only the existing one applied
  correctly to a route where "the bare parent path" and "the currently
  selected default section" happen to be the same URL.
- Trailing-slash and canonical-URL handling follow the existing, unmodified
  Next.js convention already in effect (`apps/web/next.config.ts` sets no
  `trailingSlash` option and defines no custom `redirects()`/`rewrites()`);
  this ADR introduces no change to that configuration.
- An invalid nested Platform Operations path (for example
  `/app/platform/operations/nonexistent`) resolves through Next.js's
  ordinary not-found handling for an unmatched route segment — there is no
  tenant-concealment concern here (no cross-workspace identifier is
  involved), so no special-cased response is needed beyond the framework
  default.
- No historical `/app/operations` compatibility redirect exists anywhere in
  the repository today (verified: no `middleware.ts`, no `redirects()`
  entry, no remaining route at that path) — none is introduced by this ADR
  either; this stays exactly as ADR-0027 already left it.

## Alternatives considered

**Retaining the single long page.** Not a defect against ADR-0027, but
rejected as the ongoing shape: it makes independent orientation, deep
linking and per-concern loading/error states impossible, which is the
concrete problem this ADR exists to solve.

**Anchor links within the current page.** Rejected: does not shorten the
page, does not give any section its own loading/error/unavailable state,
and does not produce a genuinely deep-linkable, bookmarkable destination —
only a scroll position.

**Client-only tabs.** Rejected for the same reason ADR-0027 rejected them
for chat/administration: no real URL, no refresh/deep-link/back-forward
behaviour, and no route-derived active state.

**Query-parameter-selected sections.** Rejected: shares the tabs
alternative's weaknesses and is inconsistent with the sub-route pattern
workspace administration already establishes for an analogous problem
(`/administration/people`, `/administration/invitations`, etc.).

**Placing Platform Operations under workspace Administration.** Rejected
outright: ADR-0026 and ADR-0027 both explicitly require Platform Operations
to remain a separate authority plane, never nested under, or conditional
on, workspace administration.

**Embedding Grafana in an iframe.** Already rejected by ADR-0026 for the
reasons stated there (frame-protection/embed fragility, blurs the
curated-versus-specialist distinction); this ADR does not reopen that
question.

**Rendering every specialist route's full detail on Overview as well as its
own page.** Rejected: duplicates rendering logic and data derivation across
two surfaces, invites the two copies to drift, and contradicts the
"Overview is a projection, not a second source of truth" principle this ADR
adopts.

**Silently changing ADR-0027 during R21-S04 without a new decision
record.** Rejected — this is precisely why this ADR exists: a product-wide
navigation change (a new contextual context, a new route family) is
architectural scope, not visual-acceptance scope.

## Implementation boundary

A focused **R21-S05**, after R21-S04, is recommended — not a reopening or
expansion of visual acceptance. R21-S05 is bounded to:

- introducing the nested Platform Operations routes and their contextual
  navigation entries;
- redistributing the existing page's content and its single existing
  data-loader call into the four routes' own ownership, per section 3/5
  above;
- reducing Overview to the concise summary this ADR defines, removing the
  full-detail sections it currently renders inline;
- **correcting the current base Platform Operations route's non-compliant
  `403`-plus-redirect handling to the accepted `404` concealment behaviour,
  applied to all four routes**, per "Authority and security boundaries"
  above — scoped to the `access-platform-operations` gate/route group in
  Laravel; the shared server/API data-result types gaining the typed
  `concealed` outcome (`platformOperations()` in
  `apps/web/src/lib/server-api.ts`, retiring `forbidden`, and
  `createOperationalPolicy()` in `apps/web/src/lib/api.ts`, mirroring the
  same shape); each route's own `notFound()`/`redirect()`/degraded-notice
  mapping (owned by the route, never by either shared data function); and a
  shared `not-found.tsx` — and to no other gate or route;
- **extending the login-return allowlist** from the single literal
  `/app/platform/operations` to the closed four-route set defined above, in
  `apps/web/src/app/login/page.tsx` and `apps/web/src/components/AuthForm.tsx`;
- **implementing the Policy mutation's typed concealment and route-refresh
  behaviour** defined above — `createOperationalPolicy()` resolving to
  `concealed` rather than throwing a generic error, `OperationalPolicyPanel`
  branching on that typed result (no misleading wording, no auto-retry, no
  retained optimistic state) and triggering a route refresh, and the Policy
  route's own server loader's live re-check producing the actual
  `notFound()` presentation as a result of that refresh;
- preserving every existing security, authorization and observability
  *decision* unchanged (who counts as a platform administrator, the
  live-per-request check, every other gate's existing behaviour, every
  ADR-0026 telemetry/alerting/policy semantic) — the deliberate, in-scope
  changes are the *response shape* for an authenticated
  non-platform-administrator (corrected to match ADR-0026) and the number of
  routes the existing safe-login-return mechanism accepts (extended from one
  to four, still closed);
- route, accessibility, responsive and regression testing: `AppShell.test.tsx`'s
  platform-operations-link assertions continue to pass unmodified;
  `platform/operations/page.test.tsx` and `server-api.test.ts`'s
  platform-operations coverage are extended to the four new routes, the new
  `concealed` typed result, and each route's own navigation mapping;
  `api.test.ts` gains direct coverage of `createOperationalPolicy()`'s own
  typed `concealed` result; `OperationalPolicyPanel.test.tsx` gains the
  mid-session-revocation case defined above; `login/page.test.tsx`/`AuthForm.test.tsx` are extended to
  cover all four allowlisted routes plus representative rejected `next`
  values; and `apps/api/tests/Feature/PlatformOperationsTest.php`'s existing
  `assertForbidden()` assertions are updated to assert the corrected `404`
  concealment response, not left asserting the now-superseded `403`;
- visual acceptance of the four resulting surfaces, in both themes, at the
  representative viewports ADR-0027 already defines.

R21-S05 does not add any new operational capability and does not reopen
ADR-0026 — correcting an implementation defect so it matches an already-
accepted decision is conformance, not revision, and changes no telemetry,
alerting, policy, or authorization-outcome decision ADR-0026 makes.

### Tracker reconciliation at acceptance

The 2026-08-21 acceptance boundary indexed ADR-0028 in
`docs/adr/README.md`, added the focused R21-S05 session to `tasks.json` and
`IMPLEMENTATION_GUIDE.md` with `requires_architecture_review: true`, and
reconciled `PROJECT_ROADMAP.md`'s Phase 21 task list. R21-S04 remains the
active visual-acceptance session; R21-S05 follows it and does not begin as a
side effect of accepting this ADR.

## Consequences

### Positive

- Each operational concern gets its own orientable, deep-linkable,
  independently-stateful route, directly solving the navigation-difficulty
  problem without touching any accepted telemetry, alerting or policy
  decision.
- Extends ADR-0027's stable/contextual sidebar model to a third context
  using the exact same mechanism chat and administration already use — no
  new navigation paradigm, no new authority mechanism.
- No new API endpoint and no new backend contract — the one Laravel change
  this ADR requires corrects an existing gate's failure-response status to
  match ADR-0026, it does not add, remove or reshape an endpoint. No
  reopening of ADR-0026's telemetry, alerting or policy decisions —
  genuinely small, bounded scope.
- Overview becomes a real orientation surface instead of the top of an
  undifferentiated long scroll, while remaining a projection of existing
  authoritative state rather than a second source of truth.

### Negative

- Four route files (plus a slightly more complex `AppShell` contextual-
  region branch) to maintain where one page existed before.
- Each route fetches the full operational snapshot even though most render
  only part of it — an accepted, named over-fetch trade-off in exchange for
  introducing no new backend surface; a future optimisation remains
  available without revisiting this ADR.
- A small additional testing surface (four routes' worth of loading/error/
  unavailable states, plus the new contextual region and its exact-vs-
  suffix active-state matching) that R21-S05 must cover before it can be
  considered complete.
- Relocating the "Open specialist console" (Grafana) link from the current
  page's header to the Telemetry route specifically is a small, deliberate
  behaviour change from today's implementation, worth calling out
  explicitly to whoever reviews this ADR even though it is presentation-only
  and changes no authority or data.
- The `403`-to-`404` concealment correction touches Laravel authorization
  code (`apps/api/app/Providers/AppServiceProvider.php`'s
  `access-platform-operations` gate) and an existing Laravel feature test's
  assertions, not only frontend/component code — a small but genuine
  backend change bundled into what is otherwise a frontend information-
  architecture ADR, because the defect it corrects cannot be fixed from the
  frontend alone (see "Authority and security boundaries").
