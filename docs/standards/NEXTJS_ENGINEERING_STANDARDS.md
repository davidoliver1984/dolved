# Next.js Engineering Standards

This document defines the authoritative frontend engineering standards for
Next.js applications.

It shares the same underlying engineering philosophy as the React/Vite SPA
standard: feature ownership, typed transport boundaries, strict
TypeScript, explicit server-state ownership, accessibility, testability
and no speculative abstractions.

This document is self-contained. A contributor working in a Next.js
repository should not need a separate React/Vite standards document in
order to understand or apply these rules.

Where Next.js introduces framework-specific execution boundaries — Server
Components, Client Components, App Router, caching, Route Handlers and
Server Actions — this document defines the applicable rules directly.

## 0. Purpose and scope

Applies to the entire Next.js application: the `app/` directory, Route
Handlers, Server Actions, and every Server or Client Component in it. It
does not redecide product or business architecture — those decisions
belong wherever this project records durable architecture decisions (an
ADR, or equivalent). This document says *how* to build within whatever
stack and backend a project has already chosen; it does not choose them.

Throughout, "Laravel" is used as the concrete example of **this project's
designated backend business authority** — the one place business rules,
authorization, and durable state actually live. If a given project's
backend is something else, substitute it; the ownership principle (a
Next.js app is a client of that backend, not a second one) does not
change.

### Version policy

This document defines stable architectural principles across supported
Next.js projects. Version-sensitive mechanics — including caching,
revalidation, request APIs, route props and experimental framework
capabilities — must follow the project's pinned Next.js major version and
its official documentation. A repository may add a concise
version-specific appendix without weakening the ownership and quality
rules in this document. Where an example below shows a specific API (a
`fetch` cache option, a `revalidateTag` call, a `params` shape), treat the
*pattern* it illustrates as the durable rule and verify the exact syntax
against the project's actual pinned version before relying on it.

### Adopting new dependencies

**Rule:** a library named in this document (React Hook Form, Zod, MSW,
`eslint-plugin-jsx-a11y`, a code-splitting helper, an E2E runner) is
installed in the same bounded change that first exercises and tests it —
never speculatively, ahead of a concrete consumer.

**Rationale:** this document selects which tool to reach for when the need
arises; it does not license installing the full toolset up front. An
installed-but-unused dependency is the same mistake as an empty directory
or a Route Handler with no caller (§19) — architecture built for code that
doesn't exist yet.

**Good example:** an E2E test runner is added in the change that first
needs to verify real Server Component rendering end to end, not in a
preparatory "set up tooling" commit.

**Avoid:** adding React Hook Form, Zod, MSW and an E2E runner in one setup
commit before any route needs any of them.

**Exceptions:** none.

### Repository-specific exceptions

**Rule:** where an accepted ADR in a specific repository establishes a
durable architectural boundary that a default recommendation in this
document would conflict with, the repository documents an explicit,
ADR-referenced exception at the specific rule affected — stating what the
rule normally recommends, why the accepted architecture requires something
different, and what to do instead — rather than silently deviating from
this document in code, or silently weakening the general rule for every
other project that reads it.

**Rationale:** this document is written to be portable across Next.js
projects, not just one. An accepted ADR is, by definition, authoritative
for its own repository (this document's own scope statement above already
establishes that it does not redecide product or business architecture).
Recording the exception in place — next to the rule it modifies — keeps the
general guidance intact for every other reader while making the local
deviation traceable to the decision that requires it.

**Good example:** §10's "Repository exception: Sanctum SPA authentication"
documents, next to the rule it narrows, exactly why this repository does not
follow the Server-Action-baseline recommendation for forms, with a reference
to the ADR that requires the deviation.

**Avoid:** code that quietly diverges from this document with no recorded
reason; a document edit that weakens a rule's general applicability to
accommodate one repository's constraint instead of scoping the exception to
that repository's accepted architecture.

**Exceptions:** none — recording an exception follows this rule; there is no
shortcut for it.

---

## 1. Architectural ownership model

This is the section everything else in this document exists to support.
Get this wrong and no amount of correct file-naming saves the
architecture.

- **Server Components own server-rendered data.** If data is fetched to
  produce the initial HTML for a route and the client never needs to
  independently refetch, mutate, or poll it, it's fetched in a Server
  Component and passed down as props — full stop, no client-side data
  library involved.
- **TanStack Query owns interactive client-side server state.** Once data
  needs to be refetched on demand, mutated with cache invalidation,
  paginated, polled, or optimistically updated from a Client Component,
  TanStack Query owns it — the same as the SPA standard.
- **React local state (`useState`/`useReducer`) owns transient UI state** —
  form input before submit, a toggle, a tab selection. Never modeled as
  server state and never promoted to Context merely for convenience.
- **Context is used only for genuinely cross-cutting client concerns**
  (theme, a client-side feature flag already resolved) — never as a
  parallel store for data that TanStack Query or a Server Component
  already owns.
- **Business logic belongs to Laravel unless explicitly delegated.** A
  Next.js application is a rendering and interaction layer in front of a
  backend, not a second place business rules get implemented. This
  includes validation rules that depend on server-side state (uniqueness,
  authorization, rate limits), not just obvious things like payments or
  data mutation.
- **Route Handlers (`app/**/route.ts`) are not a second business backend.**
  A Route Handler exists to adapt a request for the browser's benefit —
  proxying to Laravel, shaping a response for a specific client need,
  handling a webhook, forwarding cookies — not to reimplement what Laravel
  already does.
- **Server Actions must not silently replace Laravel APIs.** A Server
  Action that starts doing more than "validate shape, call Laravel,
  return/redirect based on the response" is quietly becoming a second
  backend. If a Server Action needs to independently make a decision
  Laravel doesn't already make, that's a durable architecture change and
  requires the same explicit review a durable architecture change always
  requires — never introduced silently because it was convenient in the
  moment.

**Rationale:** Next.js makes it unusually easy to write server-side code
without it being obvious that "server-side" and "business authority" are
different things. Every rule in the rest of this document is really this
ownership model applied to one specific mechanic.

---

## 2. Source directory structure

### Rule: `app/` stays thin; feature code lives in `features/`

```text
src/
├── app/
│   ├── (marketing)/            # route group, no URL segment
│   │   └── page.tsx
│   ├── (app)/                   # route group for the authenticated shell
│   │   ├── layout.tsx
│   │   ├── account/
│   │   │   └── page.tsx
│   │   └── invitations/
│   │       ├── page.tsx
│   │       ├── loading.tsx
│   │       └── error.tsx
│   ├── login/
│   │   └── page.tsx
│   ├── api/
│   │   └── invitations/
│   │       └── route.ts         # thin proxy only — see §7
│   ├── layout.tsx
│   ├── error.tsx
│   ├── not-found.tsx
│   └── global-error.tsx
├── features/
│   └── <feature>/
│       ├── data/                 # server-side data functions, called by Server Components
│       ├── actions/               # Server Actions
│       ├── api/                    # client-side API functions + query-key factory, for TanStack Query
│       ├── hooks/                   # TanStack Query hooks (Client Components only)
│       ├── components/               # Server and Client Components for this feature
│       ├── types/                     # domain/DTO types
│       └── validation/                 # Zod schemas, shared between client and server
├── components/                          # app-wide, non-feature-specific only
├── lib/                                   # shared transport/env/auth helpers (§7, §9, §15)
└── test/
```

**Rationale:** Next.js's file-system routing means `app/` *has* to exist in
a framework-dictated shape — that's not negotiable. What's fully within
this project's control is keeping every `page.tsx`/`layout.tsx`/`route.ts`
file thin: routing glue that imports from `features/`, not a place logic
accumulates. This is the same "pages are orchestration boundaries"
principle as the SPA standard, pushed one level further because Next.js
gives you two kinds of "page" (the Server Component tree and any Route
Handler) that both need the same discipline.

**Good example:**
```tsx
// app/(app)/invitations/page.tsx
import { InvitationsPageContent } from "@/features/invitations/components/InvitationsPageContent";
import { getInvitations } from "@/features/invitations/data/getInvitations";

export default async function InvitationsPage() {
  const invitations = await getInvitations();
  return <InvitationsPageContent initialInvitations={invitations} />;
}
```

**Avoid:** a `page.tsx` file containing the actual fetch call, response
unwrapping, error handling, and full JSX for a complex screen all inline.

**Exceptions:** a `page.tsx` for a genuinely trivial route (a static
marketing page with no data and no meaningful composition) may contain its
JSX directly rather than importing a single-use component for the sake of
it — don't manufacture an extraction that adds a file without adding
clarity.

### Rule: a subfolder exists only when it holds a real file

Same rule as the SPA standard. A feature that only has Server Components
and no client-side interactivity has no `hooks/` or `api/` folder — those
appear only once a Client Component in that feature actually needs
TanStack Query.

**Avoid:** creating `features/x/{data,actions,api,hooks,components,types,validation}/`
up front for a feature that currently needs two of them.

---

## 3. Server Components and Client Components

### Rule: Server Components are the default; `"use client"` marks an entry point into the client module graph

**Rationale:** every component is a Server Component unless it opts out.
Marking a file `"use client"` makes it an entry point into the client
module graph. That file and the modules it imports become part of the
browser bundle and require hydration. Next.js may still prerender their
initial HTML on the server during a full-page load, so the architectural
cost is client JavaScript, serialization and hydration — not the complete
absence of server rendering. That cost is still real (§13), it's just not
"no server rendering happened."

**Good example:** a page's overall layout, data-fetching, and static
presentation stay Server Components; only the specific interactive leaf
(a form, a button with an `onClick`, anything using `useState`/`useEffect`/
browser APIs) is a Client Component, imported as a small island inside the
server-rendered tree.

```tsx
// Server Component — no directive needed
export async function InvitationsPageContent({ initialInvitations }: Props) {
  return (
    <section>
      <h2>Invitations</h2>
      <InvitationList invitations={initialInvitations} />   {/* Client Component island */}
    </section>
  );
}
```

**Avoid:**
```tsx
"use client";
// at the top of a whole page's component tree, because one button
// somewhere inside it needs an onClick handler
```

**Exceptions:** a component that is *inherently* interactive top to bottom
(e.g. a full client-rendered form) may reasonably carry `"use client"` at
its own root rather than being artificially split — the rule is about not
pulling an entire page's static content into the client bundle for the
sake of one interactive element, not about banning `"use client"` outright.

### Rule: push `"use client"` as low in the tree as the actual interactivity requires

**Good example:** a page renders a static list server-side and passes each
item's data to a small `<RevokeButton invitationId={...}>` Client
Component, rather than making the whole list a Client Component because
one button in it needs an event handler.

**Avoid:** a single top-level `"use client"` wrapping a page's layout,
static text, and one interactive control together.

### Rule: Server Components may render Client Components; Client Components must not import Server Component modules

Server Components may import and render Client Component entry points —
this is the normal way to place interactive islands inside a
server-rendered tree. Client Components must not import Server Component
modules.

Values crossing from Server Components to Client Components must use
types supported by the project's pinned React and Next.js serialization
model. Functions, arbitrary class instances and server-only objects must
not cross that boundary. Convert values into explicit, stable DTOs where
their representation or compatibility is unclear. Props crossing this
boundary must be serializable — this is a hard framework constraint, not a
style preference.

**Good example:**
```tsx
// Server Component
import { RevokeButton } from "./RevokeButton"; // "use client" entry point

export function InvitationRow({ invitation }: { invitation: Invitation }) {
  return (
    <li>
      {invitation.email}
      <RevokeButton invitationId={invitation.id} />
    </li>
  );
}
```

**Avoid:** a Client Component attempting to `import` an `async` Server
Component function to call it directly — composition happens by the
Server Component rendering the Client Component as a child/prop, not the
other way around; and passing an event handler or an object of unclear or
unsupported representation as a prop from a Server Component into a Client
Component, instead of converting it to an explicit DTO first.

**Exceptions:** none — this is a framework constraint, not a judgment call.

---

## 4. Routing: layouts, boundaries, route groups, parallel and intercepting routes

### Rule: layouts hold structure and cross-cutting UI, never feature data-fetching that belongs to a specific page

**Rationale:** a `layout.tsx` re-renders far less often than the pages
inside it and is shared across every route it wraps — fetching
page-specific data there couples unrelated routes to each other's data
needs.

**Good example:** `app/(app)/layout.tsx` renders the authenticated shell
(navigation, the current-user fetch needed for that shell) and nothing
route-specific; `app/(app)/invitations/page.tsx` fetches invitations itself.

**Avoid:** a root layout fetching data that only one nested route actually
uses.

### Rule: route groups organize URL structure and shared layouts, not arbitrary code organization

`(marketing)`, `(app)` and similar route groups exist to apply a layout or
separate a section of the URL tree without adding a URL segment. They are
a routing tool, not a substitute for `features/`'s code organization (§2)
— a route group folder still stays thin, per §2's rule.

### Rule: every route with potentially noticeable latency provides an intentional loading experience

Use route-level `loading.tsx`, granular `<Suspense>` boundaries, or both,
according to the streaming and recovery behaviour the route actually
needs. Do not create `loading.tsx` merely for structural symmetry with
sibling routes that don't need one.

**Good example:** `app/(app)/invitations/loading.tsx` exists because that
route's data fetch is genuinely slow enough to need a route-level
fallback; a fast, near-instant route has no `loading.tsx` at all, and that
absence is correct, not an oversight.

**Avoid:** an empty or boilerplate `loading.tsx` added to every route
folder because other routes happen to have one.

**Exceptions:** none — the presence or absence of `loading.tsx` is a
deliberate decision per route's actual latency profile, not a template
applied uniformly.

### Rule: errors are handled at the boundary that can meaningfully recover

**Good example:** `app/(app)/invitations/error.tsx` renders a retry UI
scoped to that route rather than crashing the whole authenticated shell.

**Avoid:** relying on the root `error.tsx`/`global-error.tsx` to catch
every failure in every route — a boundary that's too broad means a failure
in one feature takes down UI that had nothing to do with it.

**Exceptions:** `global-error.tsx` exists specifically to catch failures in
the root layout itself, which nothing more specific can catch — that's its
job, not a catch-all to lean on instead of route-level boundaries.

### Rule: `not-found.tsx` is used for genuine "this doesn't exist" states, not as a generic error fallback

### Rule: parallel routes and intercepting routes are introduced only for a concrete UI requirement they specifically solve

**Rationale:** these are powerful, easy to reach for, and easy to introduce
speculatively. Parallel routes (`@slot` conventions) exist for genuinely
independent regions that need to render — and error/load — simultaneously
and separately (e.g. a dashboard with independently-loading panels).
Intercepting routes (`(.)folder` conventions) exist for the "open in a
modal, but still shareable/refreshable as a full page" pattern (e.g.
opening an item in an overlay from a list, while a direct visit to that
item's URL renders the full page).

**Good example:** a photo grid that opens an image in a modal via an
intercepting route, while `/photos/[id]` still renders the full standalone
page on direct navigation or refresh.

**Avoid:** reaching for parallel or intercepting routes because they exist,
for a UI that a normal nested route and a client-side modal component
would handle just as well with less routing complexity.

**Exceptions:** none — if the concrete requirement isn't there yet, the
simpler mechanism is correct until it is.

---

## 5. Data fetching, caching and revalidation

> The exact caching and revalidation APIs shown in this section (`fetch`'s
> `cache`/`next` options, `revalidatePath`, `revalidateTag`) illustrate the
> pattern this section requires, not a fixed syntax to copy blindly. Next.js
> has changed default caching behaviour and specific API shapes across
> major versions before, and may again. Verify the exact syntax against the
> project's pinned Next.js major version and its official documentation
> before relying on any example below.

### Rule: every server-side fetch states its caching intent explicitly

**Rationale:** the framework's default caching behavior for `fetch()` calls
inside Server Components has changed across major versions. Relying on
"whatever the current default is" is fragile and makes a fetch's actual
behavior invisible at the call site. State it.

**Good example (illustrative — confirm against the pinned Next.js version):**
```ts
// features/invitations/data/getInvitations.ts
export async function getInvitations() {
  const res = await fetch(`${process.env.LARAVEL_API_URL}/api/invitations`, {
    headers: await forwardedAuthHeaders(),
    next: { revalidate: 30, tags: ["invitations"] },
  });
  return unwrap<Invitation[]>(await res.json());
}
```

**Avoid:** a `fetch()` call with no `cache`/`next` option at all, leaving
its caching behavior to be whatever the framework currently defaults to.

**Exceptions:** none.

### Rule: mutations revalidate exactly what they changed, explicitly

After a Server Action or a Route Handler mutates backend state, the
project's pinned revalidation API is called for the specific paths/tags
that mutation affects — not a broad revalidation "just in case," and never
silently skipped.

**Good example (illustrative — confirm against the pinned Next.js version):**
```ts
// features/invitations/actions/issueInvitation.ts
"use server";
export async function issueInvitationAction(formData: FormData) {
  await issueInvitation({ email: formData.get("email") as string });
  revalidateTag("invitations");
}
```

**Avoid:** a mutation that leaves the cached list of invitations stale
until an unrelated navigation happens to revalidate it.

### Rule: don't fetch identical data twice for the same view

If a Server Component has already fetched data X for the initial render,
a Client Component on that same page does not immediately refetch X on
mount via TanStack Query. Resolve this one of two ways:

1. **The data is never independently refetched/mutated on the client** —
   pass it down as a plain prop. It doesn't need TanStack Query at all
   (§1's ownership rule: this isn't "interactive client-side server
   state").
2. **The Client Component genuinely needs to refetch, mutate, or poll it**
   — seed TanStack Query's cache with the Server-Component-fetched data
   (via a hydration boundary) so the client starts warm and only refetches
   per its own stale-time rules, instead of showing a loading state for
   data that's already on the page.

**Avoid:** a Server Component fetching invitations for the initial render,
and a Client Component immediately calling `useInvitationsQuery()` on
mount with no seeded initial data — showing a loading flicker for data
that was already sitting in the HTML.

---

## 6. Server state with TanStack Query

Client-side rules are unchanged from the SPA standard; what's added here
is specifically about how TanStack Query coexists with Server Components.

### Rule: TanStack Query is used only inside Client Components, for data that needs client-side interactivity

Same criterion as §1 and §5: refetch on demand, mutation with cache
invalidation, polling, pagination the user drives, or optimistic updates.
Data fetched once for an initial render and never touched again client-side
does not need a query hook.

### Rule: query keys, per-feature factories, hook naming and mutation-hook-everywhere all carry over unchanged from the SPA standard

- Query keys come only from the owning feature's key factory
  (`features/x/api/xKeys.ts`).
- Query hooks: `useXQuery`. Mutation hooks: `useXMutation`.
- Every mutation that changes server state from a Client Component is a
  `useMutation`, not a bare async function called from inside an event
  handler's own `try`/`catch`.
- Cache invalidation and seeding happen in `onSuccess`.
- Optimistic updates only for low-risk, easily-revertible changes —
  security-sensitive mutations wait for the server's authoritative
  response.
- Loading, error, empty and success states are handled consistently and
  accessibly — a shared presentational primitive may be used where it
  fits; a feature-specific handling is equally valid where it doesn't. The
  requirement is consistent, accessible handling, not one mandatory
  component.

**Good example:**
```ts
// features/invitations/hooks/useInvitationsQuery.ts
"use client";
export function useInvitationsQuery(initialData?: Invitation[]) {
  return useQuery({
    queryKey: invitationKeys.list(),
    queryFn: getInvitationsClient,
    initialData,
    staleTime: 30_000,
  });
}
```

**Avoid:** a Client Component calling `apiClient.get(...)` directly, or
fetching invitations through `useEffect`, instead of a query hook.

---

## 7. HTTP boundaries: Route Handlers and Server Actions

This section exists specifically to enforce §1's ownership rules about
Route Handlers and Server Actions in concrete terms.

### Rule: choose one deliberate strategy per endpoint for how the browser reaches the backend — direct, or proxied — and apply it consistently, not per-component

Two legitimate strategies exist:

1. **Direct-to-backend browser calls** (the same model as the SPA
   standard) — the Client Component's TanStack Query hook calls the
   backend directly via a shared client. Requires the backend to support
   cross-origin requests and credentials from the Next.js app's origin.
2. **Route-Handler proxy** — the browser calls a same-origin Route Handler,
   which forwards the request (and cookies/credentials) to the backend.
   Useful when cross-origin cookie/CORS behavior is undesirable, or when a
   server-only credential needs to be attached that the browser must never
   see.

**Rationale:** the choice has real security and complexity trade-offs
(cross-origin cookie handling in strategy 1; an extra network hop and a
proxy to maintain in strategy 2) and should be made once, deliberately, per
project or per endpoint category — not improvised differently by whichever
component happens to need data next.

**Good example — a Route Handler used correctly, as a thin proxy (cookie handling delegated to a shared helper, see the next rule):**
```ts
// app/api/invitations/route.ts
export async function GET() {
  const res = await fetch(`${process.env.LARAVEL_API_URL}/api/invitations`, {
    headers: await forwardedAuthHeaders(),
  });
  return NextResponse.json(await res.json(), { status: res.status });
}
```
This Route Handler does exactly one thing: forward the request and relay
the response. No business logic, no independent data shaping beyond
transport.

**Avoid:**
```ts
// app/api/invitations/route.ts — do not do this
export async function POST(request: Request) {
  const body = await request.json();
  if (await isEmailAlreadyRegistered(body.email)) { /* ... */ } // reimplementing a Laravel-owned check
  // ...independent business logic that duplicates or diverges from Laravel's own rule
}
```

**Exceptions:** a Route Handler may contain real logic when its job
*is* transport-shaping for the browser's benefit specifically — e.g.
setting an `httpOnly` cookie based on a backend response, or adapting a
webhook payload's shape — as long as the actual business decision still
happened in Laravel, not in the handler.

### Rule: proxy Route Handlers preserve response fidelity deliberately, not automatically

Proxy helpers must deliberately preserve required status, content type and
approved response headers. Authentication proxies must preserve **every**
applicable `Set-Cookie` value using APIs supported by the project's pinned
Next.js runtime. Do not assume that one `headers.get('Set-Cookie')` call
safely represents multiple response cookies — a backend response can, and
in an authentication flow often does, set more than one cookie at once.
Cookie forwarding must be centralised in a tested, version-compatible
proxy helper, not reimplemented inline per Route Handler. Hop-by-hop,
unsafe and unrelated headers must not be copied blindly.

**Rationale:** a naive proxy either breaks correct behavior (silently
dropping a session cookie that happened to be the second `Set-Cookie`
value in the response, because only one was read) or leaks headers the
browser was never meant to see directly from the backend (hop-by-hop
headers, the backend's own CORS headers, internal diagnostic headers).
Neither failure mode is obvious until it causes a real, hard-to-diagnose
bug.

**Good example (illustrative — the multi-cookie forwarding logic itself belongs in a tested helper, not inlined here):**
```ts
// app/api/invitations/route.ts
export async function GET() {
  const res = await fetch(`${env.LARAVEL_API_URL}/api/invitations`, {
    headers: await forwardedAuthHeaders(),
  });

  const headers = new Headers();
  headers.set("Content-Type", res.headers.get("Content-Type") ?? "application/json");
  forwardSetCookieHeaders(res, headers); // centralised, tested helper — must handle multiple Set-Cookie values using the pinned runtime's supported API, not a single headers.get() call

  return new Response(await res.text(), { status: res.status, headers });
}
```

**Avoid:** spreading every header from the backend's response onto the
proxied response unfiltered; returning a response with no explicit
status/content-type/cookie handling at all and hoping the defaults happen
to be right; or reading `Set-Cookie` with a single `headers.get()` call and
assuming that captures every cookie the backend set.

**Exceptions:** none — this rule applies to every proxy Route Handler,
regardless of how thin it otherwise is.

### Rule: Server Actions validate shape, call Laravel, and respond — nothing more

**Good example:**
```ts
// features/invitations/actions/acceptInvitation.ts
"use server";
export async function acceptInvitationAction(input: unknown) {
  const parsed = acceptInvitationSchema.safeParse(input);
  if (!parsed.success) return { fieldErrors: parsed.error.flatten().fieldErrors };

  const result = await acceptInvitation(parsed.data); // calls Laravel
  if (!result.ok) return { fieldErrors: result.fieldErrors };

  redirect("/login");
}
```

**Avoid:** a Server Action that decides on its own whether a password is
strong enough by reimplementing the backend's password policy rather than
sharing the same Zod schema (§10), or one that performs a mutation Laravel
never sees at all.

**Exceptions:** none for business logic. Purely presentational
server-side concerns (formatting a redirect URL, choosing which client
error message maps to which backend status code) are not business logic
and are fine.

---

## 8. Authentication, cookies and session handling

### Rule: session cookies are read and forwarded server-side; never re-implemented client-side

Where the backend uses cookie-based sessions, the Next.js server (Server
Components, Route Handlers, Server Actions, middleware) reads and forwards
the relevant cookies using the framework's server-side cookie APIs. A
Client Component never reads or manipulates an authentication cookie
directly — it doesn't need to, and `httpOnly` cookies aren't visible to
client JavaScript in the first place.

**Avoid:** attempting to read a session cookie via `document.cookie` in a
Client Component.

### Rule: forward only the cookies the backend actually needs

Forward only the allowlisted authentication and CSRF cookies required by
the backend, unless the project's accepted session architecture explicitly
requires forwarding the complete `Cookie` header. Do not couple unrelated
Next.js cookies to the backend by default.

**Rationale:** forwarding the entire cookie jar by default silently
couples every cookie this Next.js app ever sets (UI preferences, feature
flags, anything unrelated to authentication) to whatever the backend does
with an unrecognized cookie — an unnecessary and easily-avoided coupling.

**Good example:**
```ts
// lib/auth/forwardedAuthHeaders.ts
import { cookies } from "next/headers";

const FORWARDED_COOKIE_NAMES = ["laravel_session", "XSRF-TOKEN"]; // the backend's actual session + CSRF cookie names

export async function forwardedAuthHeaders() {
  const cookieStore = await cookies();
  const forwarded = FORWARDED_COOKIE_NAMES
    .map((name) => cookieStore.get(name))
    .filter((cookie) => cookie !== undefined)
    .map((cookie) => `${cookie.name}=${cookie.value}`)
    .join("; ");

  return { Cookie: forwarded };
}
```

**Avoid:** `{ Cookie: cookieStore.toString() }` forwarding every cookie the
browser sent, by default, regardless of whether the backend needs it.

**Exceptions:** a project's accepted session architecture may explicitly
require forwarding the complete `Cookie` header (certain SSO or
reverse-proxy setups genuinely need this) — that's a deliberate, documented
architectural choice, not the default.

### Rule: authentication checks that gate a route happen server-side, before the protected content renders

**Good example:** a Server Component (or middleware, for broader route
matching) checks the current-user fetch's result and redirects before
rendering protected content — the protected UI is never sent to the
browser for an unauthenticated request in the first place, unlike a
client-only guard that briefly renders and then redirects.

**Avoid:** relying solely on a Client Component's `useCurrentUserQuery()`
check to gate a protected page — this ships the protected page's code and
briefly its content to a browser that isn't authenticated, which a
server-side check avoids entirely.

**Exceptions:** a client-side check is still appropriate as a
belt-and-suspenders UX improvement (redirecting promptly on session
expiry mid-visit) — it's just not the sole or first line of defense.

### Rule: no client-side secrets

Only `NEXT_PUBLIC_`-prefixed environment variables reach the browser
bundle. A server-only credential (an API key, a service token, anything
that would matter if leaked) is never imported into a `"use client"` file
and never assigned to a `NEXT_PUBLIC_` variable. See also §15.

---

## 9. Pages, layouts and components

### Rule: pages compose; feature components implement

A `page.tsx` calls a feature's server-side data function (or renders a
Client Component that owns its own query hooks) and composes feature
components — the same "orchestration boundary" principle as the SPA
standard, now split across the server/client boundary.

### Rule: `"use client"` files are named exports, same as the SPA standard; Next.js's own convention files follow Next.js's required export shape

**Rationale:** Next.js requires a default export from `page.tsx`,
`layout.tsx`, `loading.tsx`, `error.tsx`, `not-found.tsx`, `template.tsx`
and `default.tsx`, and requires named HTTP-method exports (`GET`, `POST`,
etc.) from `route.ts` — these are hard framework constraints, not style
choices, and are followed as-is. Every other component, hook, and utility
uses named exports, matching the SPA standard.

**Good example:** `export default function InvitationsPage() { ... }` in
`app/(app)/invitations/page.tsx`; `export function InvitationList() { ... }`
in `features/invitations/components/InvitationList.tsx`.

### Rule: no business logic or transport knowledge in JSX

Same rule as the SPA standard, applied to both Server and Client
Components: derived values and conditionals live in a hook or a typed
helper, not inline in markup; components never reference a URL path,
fetch options, or cache-tag string directly.

---

## 10. Forms and validation

### Rule: Server Actions with native `<form action={...}>` are the baseline; React Hook Form + Zod are the enhancement for forms with real complexity or that are security-sensitive

**Rationale:** a form wired to a Server Action via `<form action={action}>`
works without client JavaScript at all — a genuine, framework-native
progressive-enhancement default that the SPA standard doesn't have access
to. Reach for React Hook Form when a form needs real-time client-side
validation feedback, cross-field validation before submit, or complex
conditional fields — the same complexity/security-sensitivity criterion as
the SPA standard, not a blanket rule either way.

**Good example — a simple form, native Server Action is sufficient:**
```tsx
// a single required field, no cross-field logic
<form action={requestPasswordResetAction}>
  <label htmlFor="email">Email address</label>
  <input id="email" name="email" type="email" autoComplete="email" required />
  <button type="submit">Send reset link</button>
</form>
```

**Good example — a form that warrants React Hook Form + Zod:** a form
collecting a password and confirmation, needing an immediate client-side
match check and sharing a password-policy schema with the server-side
Server Action that will also validate it.

### Repository exception: Sanctum SPA authentication (see ADR-0005)

**Rule:** where a repository's accepted authentication ADR establishes a
first-party SPA calling its backend directly with cookie-based, CSRF-token
authentication — as this repository's `docs/adr/0005-*` does —
authentication and other Laravel-authoritative application forms are
implemented as Client Components calling the backend directly (§7's
"direct-to-backend browser calls" strategy), not as Server Actions with
`<form action={...}>`. This is a documented, ADR-driven exception to the
rule above, not a general weakening of it.

**Rationale:** the rule above assumes a form's submission can reasonably
happen server-side. ADR-0005 deliberately rejected Next.js as a mandatory
backend-for-frontend: the browser is required to obtain Laravel's CSRF
cookie itself, read the frontend-readable `XSRF-TOKEN` cookie via client
JavaScript, and send it back as `X-XSRF-TOKEN` — a mechanism that only works
from the browser. Routing that flow through a Server Action would mean the
Next.js server, not the browser, becomes the party authenticating to
Laravel on the user's behalf for every form submission — reintroducing
exactly the mandatory proxy hop ADR-0005 considered and rejected as an
alternative. This is not a gap in this document; it is what happens when a
repository's accepted authentication architecture and this rule's
assumption genuinely don't hold at the same time. Server Actions remain an
excellent default for many standalone Next.js applications — this exception
exists because this repository specifically is not one of them.

**What this repository does instead:** `apps/web/src/lib/api.ts`'s
`apiFetch` implements the accepted CSRF-cookie flow once, and Client
Components such as `AuthForm` call it directly, with Laravel remaining the
sole authority for validation and authorisation — unchanged from the rule
above and from §1's ownership model. Server-side validation is still
authoritative regardless of what the client checks first; that part of this
document's guidance is unaffected by this exception. Client-side API calls
using the documented Sanctum SPA flow are the correct implementation for
this repository, not a deviation to be corrected toward Server Actions.

**Scope:** this exception applies to this repository, and to any project
that accepts an equivalent first-party-SPA, direct-cookie-auth ADR. It does
not apply generally — most standalone Next.js applications without that
specific constraint should still treat Server Actions with native `<form
action={...}>` as the baseline, per the rule above.

**Exceptions:** none beyond the scope already stated — a repository does
not get to invoke this exception without an accepted ADR that actually
requires it.

### Rule: one Zod schema per form's shape, shared between the client and the server boundary that receives it

**Rationale:** this is the single most important adaptation Next.js
enables over the SPA model — the exact same schema object can validate a
form client-side (via a resolver) and validate the `FormData`/payload
inside the Server Action or Route Handler that receives it, so the
structural validation rule is defined exactly once.

**Good example:**
```ts
// features/invitations/validation/acceptInvitationSchema.ts
export const acceptInvitationSchema = z.object({
  claim_token: z.string(),
  name: z.string().min(1),
  password: passwordSchema,
  password_confirmation: z.string(),
  timezone: z.string(),
}).refine((v) => v.password === v.password_confirmation, {
  path: ["password_confirmation"],
  message: "Passwords do not match",
});
```
used both as the React Hook Form `resolver` on the client and as the
`safeParse` call inside `acceptInvitationAction` on the server (§7).

**Avoid:** the same validation rule (a password-length minimum, a
cross-field check) hand-written independently in a client-side schema and
again inside a Server Action.

### Rule: a schema shared between client and server code remains environment-neutral

A schema shared between client and server code must remain
environment-neutral. It must not import server-only modules, secrets,
database access, backend clients or state-dependent refinements. Shared
schemas validate portable shape, format and cross-field relationships
only. Laravel or the designated backend remains authoritative for
authorization, uniqueness, current-state checks and other business rules.

**Rationale:** a schema file imported by both a Client Component and a
Server Action/Route Handler must be safely includable in the client
bundle. A schema that imports a database client, a server-only secret, or
performs a `.refine()` check against live server state would either break
the client build or silently leak server-only capability into client code
— and duplicates exactly the kind of business-rule check §1 already
assigns to Laravel.

**Good example:** `passwordSchema` and `acceptInvitationSchema` (above)
check length, required-ness, and that two fields match — all portable,
static rules with no dependency on anything server-only.

**Avoid:**
```ts
// features/invitations/validation/acceptInvitationSchema.ts — do not do this
import { db } from "@/lib/db"; // server-only — breaks the client bundle if this file is imported there

export const acceptInvitationSchema = z.object({ email: z.string().email() })
  .refine(async (v) => !(await db.users.exists({ email: v.email })), "Email already registered");
```

**Exceptions:** none — a check that needs server-only capability belongs
in the Server Action/Route Handler after shared-schema validation passes,
not inside the shared schema itself.

### Rule: shared Zod schemas validate shape and format, not backend business rules that depend on server-side state

**Rationale:** a Zod schema can and should check "is this the right shape,
length, and format" — it cannot and should not try to answer "is this
email already invited" or "is this the correct password," which depend on
server-side state or secrets the Next.js app doesn't and shouldn't have.
The Next.js layer is always ready to receive and surface a 422/error
response from Laravel even when local Zod validation passed.

### Rule: server-side validation is authoritative regardless of what the client already checked

Client-side validation (native HTML attributes or a Zod resolver) is a
fast-fail UX layer. The Server Action or Route Handler receiving the
submission validates again — never trusts that a request actually came
through the client form that would have validated it.

### Rule: password-manager compatibility and accessible labeling carry over unchanged

Never `autocomplete="off"`; correct `autocomplete` tokens; never block
paste; every field has an associated `<label>`; error text uses
`role="alert"` or is linked via `aria-describedby`. Identical to the SPA
standard — nothing about Next.js changes this.

---

## 11. TypeScript standards

Carried over from the SPA standard, plus Next.js-specific additions.

### Rule: `strict: true`, `any` avoided except where genuinely unavoidable, `type` over `interface`

Same rules as the SPA standard: `strict: true` in `tsconfig.json`; `unknown`
and narrowing instead of `any` in ordinary code; `type` preferred over
`interface` unless declaration merging or another concrete requirement
(e.g. extending a third-party library's own `interface`) justifies it.

**Exceptions to `any`:** a rare, explicitly-commented case interfacing with
an untyped third-party API or plugin surface that genuinely has no usable
types — narrowed back to a real type at the boundary as soon as possible,
not left as `any` beyond that single interop point.

### Rule: environment variables are typed and validated once, not read ad hoc with `process.env.X`

**Good example:**
```ts
// lib/env.ts
const envSchema = z.object({
  LARAVEL_API_URL: z.string().url(),
  NEXT_PUBLIC_APP_URL: z.string().url(),
});
export const env = envSchema.parse({
  LARAVEL_API_URL: process.env.LARAVEL_API_URL,
  NEXT_PUBLIC_APP_URL: process.env.NEXT_PUBLIC_APP_URL,
});
```

**Avoid:** `process.env.LARAVEL_API_URL` (typed as `string | undefined`,
unchecked) scattered across multiple files.

### Rule: dynamic route params and search params are typed at the page boundary

**Good example (illustrative — confirm the exact `params` shape against the pinned Next.js version, §0):**
```tsx
export default async function InvitationPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  // ...
}
```

### Rule: exhaustiveness checks, literal unions over enum, domain vs. DTO placement — unchanged from the SPA standard

---

## 12. Error handling and boundaries

### Rule: `error.tsx` boundaries are scoped to the smallest route that can meaningfully recover

Covered in §4. Restated here because error handling is where this most
matters: a route-level `error.tsx` with a retry action is almost always
more useful than letting a failure bubble to a broader boundary.

### Rule: Route Handlers and Server Actions normalize errors the same way, once, shared

The same category of shared helper as the SPA standard (`toAppError`,
`toLaravelFieldErrors`) applies here — living in `lib/`, imported by every
Route Handler and Server Action that needs to turn a caught error or a
non-2xx backend response into something a form or a page can render.

**Avoid:** a Server Action's `catch` block discarding the backend's actual
422 field errors and replacing them with one generic string.

### Rule: user-safe messaging, no silently swallowed errors, no sensitive material in logs

Unchanged from the SPA standard: raw server exceptions and stack traces
are never shown to the user; every `catch` handles or rethrows; logs never
contain password or token material.

### Rule: hydration output must be deterministic between server and client

Values participating in hydration must be deterministic between the
server render and the client's first render. Prefer passing a stable
server-generated value, isolating genuinely client-only output, or
updating it after mount where appropriate. Do not use
`suppressHydrationWarning` as a routine escape hatch.

**Rationale:** a hydration mismatch forces React to discard and re-render
the affected output, which is a real performance cost and can cause
visible flicker. `suppressHydrationWarning` silences the symptom without
addressing the mismatch, and overusing it hides genuine future bugs behind
a blanket "expected" label.

**Good example:**
```tsx
// Server Component passes a stable, server-generated value
export function LastUpdated({ at }: { at: string }) {
  return <RelativeTime isoString={at} />; // Client Component formats it after mount
}
```

**Avoid:**
```tsx
<p>Current time: {new Date().toLocaleTimeString()}</p>
```
computed directly during render, where the server and the client will
compute different values; and
```tsx
<p suppressHydrationWarning>{new Date().toLocaleTimeString()}</p>
```
used to silence the resulting warning instead of fixing its cause.

**Exceptions:** `suppressHydrationWarning` has a narrow, legitimate use —
a value that is genuinely expected and acceptable to differ (e.g. content
injected by a browser extension or a third party outside this
application's control) — not a default response to an unexplained
hydration warning.

---

## 13. Performance: rendering, streaming, hydration, code splitting, images

### Rule: wrap genuinely slow, independent data sections in their own `<Suspense>` boundary rather than one all-or-nothing loading state

**Good example:** a dashboard page renders its fast, static shell
immediately and streams in a slower panel inside its own `<Suspense
fallback={<PanelSkeleton />}>`, rather than blocking the entire page behind
`loading.tsx` until the slowest panel resolves.

**Avoid:** a single page-level loading state gating content that was ready
long before the slowest fetch on the page.

### Rule: `next/dynamic` for genuinely heavy, rarely-used Client Components; not as a default

**Good example:** a rich text editor or a charting library, used on one
screen, loaded via `next/dynamic` so its weight isn't in the initial
bundle for every visitor.

**Avoid:** wrapping every Client Component in `next/dynamic` "for
performance" without a measured reason — this adds complexity (loading
states, potential layout shift) that isn't free.

**Exceptions:** none — introduce it when a specific component's size is a
demonstrated problem, per §0's dependency-timing principle applied to
patterns, not just packages.

### Rule: `next/image` for content images; a plain `<img>` only where it adds no value

**Rationale:** automatic sizing, lazy loading, and format negotiation are
close to free with `next/image` and easy to forget by hand.

**Exceptions:** small icons/SVGs where `next/image`'s optimization pipeline
has nothing to add.

### Rule: minimizing `"use client"` scope (§3) is this project's primary bundle-size lever

Restated here because it's the highest-leverage performance rule in the
whole document: every unnecessary `"use client"` boundary is client
JavaScript and hydration work that didn't need to be paid for.

---

## 14. Metadata and SEO

### Rule: define shared metadata at the nearest common layout; override only where a route genuinely differs

Define sensible shared metadata at the nearest common layout. A route
overrides inherited metadata only when its title, indexing policy,
social-sharing presentation, or other metadata genuinely differs. Private,
authenticated routes should normally inherit a no-index policy from their
authenticated layout rather than repeat it in every page.

**Rationale:** metadata is inherited and merged down the layout tree by
design — repeating the same title template or `robots` directive on every
page under an already-correctly-configured layout is pure duplication, and
makes the one page that genuinely needs different metadata harder to spot.

**Good example:**
```tsx
// app/(app)/layout.tsx — sets the shared, inherited policy once
export const metadata: Metadata = {
  title: { template: "%s | Account", default: "Account" },
  robots: { index: false },
};
```
```tsx
// app/(app)/invitations/page.tsx — overrides only what's actually different
export const metadata: Metadata = { title: "Invitations" };
```

**Avoid:** repeating `robots: { index: false }` on every single private
route's own `page.tsx` when the shared layout already sets it.

**Exceptions:** a route with a genuinely different indexing or sharing need
(a public route nested under an otherwise-private section, for instance)
sets its own metadata to override the inherited default — that's the
mechanism working as intended, not an exception to avoid.

---

## 15. Environment variables and runtime considerations

### Rule: `NEXT_PUBLIC_` is a deliberate declaration, not a default

A variable is prefixed `NEXT_PUBLIC_` only when it is genuinely safe for
the browser to see. Everything else stays server-only and is validated
through the shared `env` module (§11).

### Rule: the Edge runtime is an opt-in optimization, not a default

**Rationale:** Edge has real constraints (no full Node.js API surface,
different cold-start/latency trade-offs) and isn't free complexity to take
on without a reason.

**Good example:** middleware that only needs to inspect a cookie and
redirect is a reasonable Edge candidate.

**Avoid:** opting a Route Handler into the Edge runtime with no
demonstrated latency or distribution requirement, discovering later that a
Node.js API it needs isn't available there.

**Exceptions:** adopt Edge when a specific, measured latency or global
distribution need exists — not speculatively.

---

## 16. Testing strategy

### Rule: Client Components are tested the same way as the SPA standard

Vitest + React Testing Library, colocated `*.test.tsx`, testing behavior
rather than implementation details. Unchanged.

### Rule: Server Components are made testable by extracting their logic, not by fighting the framework to render them in a unit test

**Rationale:** an `async` Server Component isn't straightforwardly
unit-testable with React Testing Library's `render()`. Rather than working
around that, keep Server Components themselves thin (§2, §9) and put the
actual logic — the data function in `features/x/data/` — in a plain
`async` function with no JSX, which unit-tests directly and trivially. What
the Server Component actually renders is then verified at the integration/
E2E level (below), where real rendering behavior can actually be observed.

**Good example:**
```ts
// features/invitations/data/getInvitations.test.ts — plain function, no React involved
it("unwraps the Laravel envelope", async () => { ... });
```

### Rule: Route Handlers and Server Actions are tested by extracting and unit-testing the function they delegate to; MSW mocks the backend call

Same extraction principle: the handler/action stays a thin adapter (§7);
what's actually tested is the underlying function, with MSW (Node
environment) standing in for the Laravel call. The cookie-forwarding
helper referenced in §7 is itself unit-tested directly, including the
multi-`Set-Cookie` case, since it's a shared, security-relevant boundary.

### Rule: MSW covers both the Node-side backend boundary and the browser-side Client Component boundary

MSW's dual Node/browser support is directly relevant here: the same
approach mocks Laravel responses inside Route Handler/Server Action/data-
function tests running in Node, and mocks Client Component fetches running
in jsdom — one tool, two boundaries, both required for negative-path
testing (expired tokens, 401/403/422/429 responses) wherever that
requirement exists in this project.

### Rule: end-to-end tests cover what unit tests structurally cannot — real Server Component rendering, streaming, and full-route behavior

**Rationale:** this is a genuine, honest gap relative to the SPA standard:
no amount of extraction fully substitutes for actually rendering a route
through the framework. An E2E runner (introduced per §0's dependency-
timing rule, when the first sufficiently complex route needs it — not
speculatively) closes this gap for the routes where it matters.

**Exceptions:** a simple, mostly-static route doesn't need E2E coverage
just because the capability exists — apply it where a real rendering/
streaming/hydration risk exists, not everywhere by default.

---

## 17. Naming and imports

### Rule: file and symbol naming — unchanged from the SPA standard, plus Next.js's own required file names

- Components: PascalCase files. Hooks/utils/API modules: camelCase.
- Query hooks: `useXQuery`. Mutation hooks: `useXMutation`.
- Next.js convention files (`page.tsx`, `layout.tsx`, `loading.tsx`,
  `error.tsx`, `not-found.tsx`, `template.tsx`, `default.tsx`, `route.ts`)
  keep the exact names and export shapes the framework requires — these
  aren't a naming choice.
- Server Actions: verb-based, `xAction` suffix (`issueInvitationAction`),
  to distinguish them at a glance from the plain data/API functions they
  call.

### Rule: `@/` path alias for cross-feature imports

Already Next.js's own default convention (`tsconfig.json`'s `paths`); kept
exactly as the SPA standard's equivalent rule.

### Rule: named exports throughout, except where Next.js requires otherwise (§9)

### Rule: barrel files are complete, or don't exist

Unchanged from the SPA standard.

---

## 18. Accessibility

Unchanged from the SPA standard, restated:

- Semantic HTML first; ARIA only where semantic HTML can't express the
  state.
- Full keyboard operability; no click-only handlers on non-interactive
  elements.
- Focus management on navigation — including client-side transitions
  between routes, which don't get a full page reload's natural focus
  reset.
- Every field has an associated `<label>`; errors linked via
  `aria-describedby` or announced via `role="alert"`.
- Async state changes announced via a status region, not conveyed by
  appearance alone.
- Colour-independent meaning; `prefers-reduced-motion` respected for any
  non-essential animation.
- `eslint-plugin-jsx-a11y` added and wired into the lint config in the same
  change that introduces it (§0) — not installed and left unconfigured.
- Progressive enhancement (§10) is itself an accessibility win: a form that
  works via a native Server Action submission before any client JavaScript
  library is layered on top degrades far better for users on poor
  connections or with JavaScript-impacting assistive tech than a
  client-only form ever can — except where §10's repository exception for
  Sanctum SPA authentication applies; there, that trade-off is a deliberate,
  ADR-driven cost of the accepted authentication architecture, not an
  accessibility oversight to fix.

---

## 19. Prohibited patterns

- **Business logic in Route Handlers.** A Route Handler forwards and
  shapes transport; it does not make decisions Laravel should be making
  (§1, §7).
- **Unnecessarily large `"use client"` boundaries.** Every component is a
  Server Component by default; push the client boundary as low as the
  actual interactivity requires — each unnecessary boundary is client
  JavaScript and hydration cost that didn't need to be paid (§3).
- **Fetching identical data in both a Server Component and a client-side
  TanStack Query hook** on the same page without a deliberate ownership or
  hydration decision (§5, §6).
- **Unnecessary Zustand/Redux/other global client-state stores** for data
  that's actually server state (owned by a Server Component or TanStack
  Query) or transient UI state (owned by local `useState`) (§1).
- **Inline `fetch`/`axios` calls from components** — Server or Client —
  instead of going through a feature's data function or API module (§2,
  §7).
- **Duplicated validation** — the same rule hand-written independently in
  a client schema, a Server Action, and a form component, instead of one
  shared Zod schema (§10).
- **A shared Zod schema that imports server-only capability** — a
  database client, a secret, a state-dependent `.refine()` — breaking its
  environment-neutrality and quietly leaking server-only capability toward
  the client bundle (§10).
- **Client-side secrets** — a server-only credential imported into a
  `"use client"` file, or mistakenly exposed via a `NEXT_PUBLIC_` variable
  (§8, §15).
- **Blindly forwarding the entire cookie jar to the backend.** Forward only
  the allowlisted cookies the backend actually needs, unless the project's
  session architecture explicitly requires the full header (§8).
- **A proxy Route Handler that drops status, content type, or required
  response headers** — including any of multiple `Set-Cookie` values, or
  that assumes a single header read safely captures them all (§7).
- **Speculative abstractions** — parallel routes, intercepting routes, the
  Edge runtime, or a code-splitting boundary introduced before a concrete
  requirement demonstrably needs them (§4, §13, §15).
- **Large shared `utils/`/`lib/` folders** collecting unrelated helpers
  instead of feature-scoped or genuinely cross-cutting placement (§2).
- **Creating architecture for code that doesn't exist yet** — an empty
  route group, an empty feature folder, a Route Handler with no caller, a
  Server Action stubbed out ahead of the form that will use it (§0, §2).
- **A Server Action silently replacing a Laravel endpoint's business
  logic** without the same explicit review any durable architecture change
  requires (§1, §7).
- **Routine use of `suppressHydrationWarning`** to silence a hydration
  mismatch instead of fixing its cause (§12).
- **Relying on implicit/default fetch caching behavior** instead of
  stating cache and revalidation intent explicitly at the call site,
  using the syntax the project's pinned Next.js version actually supports
  (§0, §5).
