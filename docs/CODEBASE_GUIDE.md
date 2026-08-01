# Codebase Guide

## Purpose of this document

This guide exists to help you (David) immerse yourself in the RAG Platform codebase and navigate it confidently, especially the parts implemented with AI assistance under the teaching workflow described in `CONTRIBUTING.md`.

It documents **meaningful application code**, not every repository file. Vendor directories, `node_modules`, lockfiles, build artefacts, caches and routine framework boilerplate are deliberately excluded or only summarised. `PROJECT_ROADMAP.md`, `IMPLEMENTATION_GUIDE.md`, `tasks.json`, `docs/adr/`, `docs/journal/`, `PROJECT_JOURNEY.md` and `CONTRIBUTING.md` remain the authoritative documents for *what* will be built, *how* it was built, *what session is current*, *why* decisions were made, *what happened* in a session, and *the plain-language story* respectively — this guide does not duplicate them, only cross-references them.

**The repository and its source code remain authoritative.** Where this guide and the code disagree, trust the code and treat this guide as stale.

**Commit inspected**: `747815a81443994c6d9875e2f604188c86ba2f53` (2026-07-31, "Verify cross-service trace propagation and telemetry privacy" — the close of Phase 12, Observability Foundation). At the time of writing, `tasks.json` reports the tracker at **R13-S01 (Define Embedding Provider Boundary)**, not yet started.

---

# 1. System Overview

## Applications and services

The platform is a monorepo of three independently buildable, independently deployable applications, plus supporting infrastructure, connected by explicit contracts (ADR-0002):

| Application | Technology | Owns |
|---|---|---|
| `apps/web` | Next.js / React / TypeScript (App Router) | Browser interface and presentation logic only |
| `apps/api` | Laravel 13 / PHP | System of record: identity, authentication, tenancy, document metadata, ingestion orchestration, authorisation. The security boundary. |
| `apps/ai` | Python / FastAPI | AI workloads: ingestion consumption, text extraction, normalisation, chunking (embeddings/retrieval not yet built) |

Supporting infrastructure runs as Docker Compose services: PostgreSQL 18 (system-of-record database, ADR-0001), LocalStack 4.14 (local S3 + SQS emulation, ADR-0004), Mailpit (local mail capture), an OpenTelemetry Collector plus Grafana `otel-lgtm` stack (ADR-0012), and two additional application processes — a Laravel `publisher` (outbox → SQS) and a Python `worker` (SQS → claim) — that run the same images as `api`/`ai` but with different entrypoints.

## Communication paths

```text
Browser → Next.js → Laravel API → PostgreSQL
Browser → Next.js → Laravel API → S3 (LocalStack, presigned PUT, browser uploads directly)
Laravel (outbox publisher) → SQS → Python ingestion worker → Laravel (signed internal claim) → PostgreSQL
```

The browser **never** talks to the Python AI service directly (`contracts/http/README.md`). Next.js may selectively server-fetch from Laravel for rendering, but is not a mandatory backend-for-frontend and makes no authorisation decisions of its own (ADR-0005). The Python AI service is reachable only from Laravel's internal claim endpoint and consumes SQS directly; it has no browser-facing surface beyond its own `/health` check.

## Key infrastructure dependencies

- **PostgreSQL 18** — the sole system of record for identity, tenancy, documents and the outbox (ADR-0001).
- **LocalStack (S3 + SQS)** locally, real AWS in production — object storage for uploaded documents and the ingestion transport queue with a dead-letter queue (ADR-0004).
- **OpenTelemetry Collector + Grafana `otel-lgtm`** — the vendor-neutral telemetry backend; application code depends only on the OpenTelemetry SDK, never a backend-specific exporter (ADR-0012).
- **Mailpit** — captures Fortify's verification/reset emails locally.

## Architectural boundaries established by accepted ADRs

- **ADR-0002**: three-service split; Laravel is the only system of record for core business entities.
- **ADR-0005**: Sanctum stateful-session + Fortify authentication; Laravel owns every security decision, Next.js guards are UX only.
- **ADR-0006**: Workspace is the tenancy/isolation boundary; pooled multi-tenancy with defence-in-depth (routes, membership, policies, tenant-scoped queries, and — not yet implemented — PostgreSQL RLS).
- **ADR-0007**: Document lifecycle is a state machine (`UPLOADING → UPLOADED → QUEUED → PROCESSING → INDEXED`, with `FAILED` and `DELETING → DELETED` branches), superseded in part by ADR-0008 for what "entering `QUEUED`" means.
- **ADR-0008**: transactional outbox pattern for publishing ingestion events — PostgreSQL owns durable publish *intent*; SQS delivery is eventual and at-least-once.
- **ADR-0009**: HMAC-SHA256 authentication for the Python worker's internal Laravel claim request — a narrow, single-purpose machine identity, not a general auth framework.
- **ADR-0010**: canonical, immutable `ExtractedDocument`/`Element` contract — extraction preserves semantic structure; only later stages may discard information.
- **ADR-0011**: deterministic, immutable `ChunkingStrategy`/`ChunkingResult` contract — chunking operates only on `NormalisedDocument`, no context wrapper.
- **ADR-0012**: OpenTelemetry as the canonical, vendor-neutral instrumentation API; a strict attribute **allowlist** (not a denylist) guards against sensitive data ever reaching telemetry.

## Implemented vs. planned

| Capability | Status |
|---|---|
| Auth (Sanctum + Fortify), Workspace tenancy, Document lifecycle + upload, Event-driven ingestion (outbox → SQS → claim), Extraction (plain text/PDF/DOCX), Normalisation, Chunking, OpenTelemetry observability | **Implemented** |
| PostgreSQL Row-Level Security | **Planned but not present** — ADR-0006 names it as an accepted, required defence-in-depth layer, but explicitly forbids describing it as active until Stage 7.2-equivalent work implements and verifies it. No RLS policies exist in the migrations inspected. |
| Embeddings, Vector Storage (Qdrant), Retrieval, Grounded Generation, Conversation/Streaming, Administration, further Observability/Ops, Testing & Quality Strategy, CI/CD, final documentation phases | **Planned but not present** (Phases 13–22 in `PROJECT_ROADMAP.md`) |
| `contracts/http/` OpenAPI spec | **Placeholder** — README only |
| `infrastructure/terraform/` | **Placeholder** — README only |
| `tests/end-to-end/` | **Scaffold only** — README describes intent; the one real cross-service acceptance check currently lives in `scripts/telemetry/verify-cross-service.sh`, not under `tests/end-to-end/` |

---

# 2. Repository Tree

```text
apps/
├── web/                     Next.js browser application (App Router, TypeScript)
│   └── src/
│       ├── app/              Routed pages (login, register, workspace, etc.)
│       ├── components/       Client-side React components
│       └── lib/               API client, server-side fetch helpers, upload helpers
├── api/                     Laravel 13 system of record
│   ├── app/
│   │   ├── Actions/          One state-changing use case per class
│   │   ├── Console/Commands/ Artisan commands (outbox publisher loop)
│   │   ├── Contracts/        Interfaces for infra abstractions (ingestion publisher)
│   │   ├── Enums/            DocumentStatus, WorkspaceRole, IngestionClaimOutcome
│   │   ├── Exceptions/       Typed, renderable domain exceptions
│   │   ├── Http/              Controllers, Middleware, Requests, Resources, Responses
│   │   ├── Models/            Eloquent models (immutability enforced in `booted()`)
│   │   ├── Policies/          Authorisation gates
│   │   ├── Providers/         Service providers (telemetry, Fortify, app boot)
│   │   ├── Queries/           Tenant-scoped reads
│   │   ├── Services/          External/infra capability wrappers
│   │   ├── Support/           Small stateless helpers and value objects
│   │   └── Telemetry/         OpenTelemetry wiring, allowlist enforcement
│   ├── config/                Environment-driven configuration (documents, ingestion, telemetry, sanctum...)
│   ├── database/              Migrations, factories, seeders
│   ├── routes/                api.php, web.php, console.php
│   └── tests/                 Feature and Unit PHPUnit tests
├── ai/                      Python 3.14 / FastAPI AI service
│   └── app/
│       ├── chunking/          ChunkingStrategy contract, baseline chunker, tokenizer
│       ├── extraction/        ExtractedDocument contract, plain text/PDF/DOCX extractors
│       ├── ingestion/         SQS consumer, HMAC signer, claim client, worker loop
│       ├── normalisation/     NormalisedDocument contract, structural normaliser
│       ├── main.py            FastAPI app (HTTP entrypoint, /health)
│       ├── worker.py          Ingestion worker process entrypoint
│       ├── settings.py        Pydantic settings
│       ├── telemetry.py       OpenTelemetry SDK wiring, attribute allowlists
│       └── structured_logging.py  JSON log formatter
contracts/
├── events/                  Versioned, language-neutral event contracts (JSON Schema)
│   └── document-ingestion-requested/  v1 schema, example, invalid fixtures
└── http/                    HTTP contract intent (OpenAPI spec not yet written)
docs/
├── adr/                     12 accepted, numbered Architecture Decision Records
├── journal/                 Per-session engineering journal entries
└── evaluation/              Chunking-quality evaluation notes (R11-S03)
infrastructure/
├── localstack/init/ready.d/ Idempotent local S3/SQS provisioning script
├── opentelemetry/           Collector routing configuration
└── terraform/                Placeholder for future IaC
scripts/
├── localstack/               LocalStack resource verification script
└── telemetry/                Telemetry smoke-test, outage, and cross-service verification scripts
tests/
└── end-to-end/               README only; no test runner yet
tasks.json, PROJECT_ROADMAP.md, IMPLEMENTATION_GUIDE.md, PROJECT_JOURNEY.md, CONTRIBUTING.md
compose.yaml, makefile
```

- **`apps/`** — the three independently runnable services; see ADR-0002 for why they are separate rather than combined.
- **`contracts/`** — the explicit, versioned seam between Laravel and Python; a cross-service change must update both sides and their tests together (per `contracts/events/README.md`).
- **`docs/`** — the decision record, the session-by-session history, and (separately) the chunking evaluation output.
- **`infrastructure/`** — everything that makes local AWS emulation and telemetry routing reproducible without a cloud account.
- **`scripts/`** — repeatable verification that doesn't belong inside an application (LocalStack health, telemetry propagation).
- **`tests/`** — reserved for genuinely cross-service, end-to-end tests; each application's own unit/feature tests live inside that application.

---

# 3. Application and Service Guides

## 3.1 Next.js Web Application (`apps/web`)

The web app is a thin presentation layer. It holds no authorisation logic of its own — every protected page re-verifies access against Laravel on every request (`platformAccess()`), consistent with ADR-0005's "Next.js guards are UX only" rule. There is no client-side global auth state/store; each server component fetches what it needs directly from Laravel using the incoming request's cookies.

### `src/lib/api.ts`

#### File responsibilities

The single client-side HTTP client used by every browser-side component. It exists so that CSRF handling, credentialed requests and error shaping are implemented exactly once rather than being reimplemented per component. You would modify this when the API's error shape changes, or when a new cross-cutting request behaviour (e.g. a new header) needs to apply everywhere.

#### Technical description

Exports `ApiError` (carries HTTP `status` and field-level `errors`), the `User`/`Workspace`/`WorkspaceRole` types, `apiFetch<T>()`, and `firstError()`. `apiFetch` reads `NEXT_PUBLIC_API_URL`, always sends `credentials: "include"`, and — for any unsafe method (not GET/HEAD/OPTIONS) — first calls Laravel's `/sanctum/csrf-cookie`, reads the `XSRF-TOKEN` cookie, and forwards it as `X-XSRF-TOKEN`. Non-2xx responses are thrown as `ApiError` using the JSON body's `message`/`errors`.

#### Relationships

Called by: `AuthForm`, `LogoutButton`, `ResendVerificationButton`, `document-upload.ts`.
Calls: Laravel `/sanctum/csrf-cookie` and any `/api/*` route, credentialed.
Tested by: `src/lib/api.test.ts`.

#### Status

Implemented.

---

### `src/lib/server-api.ts`

#### File responsibilities

The server-side counterpart to `api.ts`, used only inside React Server Components (`import "server-only"`). It exists because server components must forward the browser's session cookie manually — there is no browser to do it implicitly. You would touch this when adding a new server-rendered page that needs to read authenticated state from Laravel before rendering.

#### Technical description

Exports `platformAccess()`, `currentUser()`, `userWorkspaces()`, `userWorkspace(publicId)`. `serverFetch` reads `cookies()` from `next/headers`, forwards them as a raw `Cookie` header plus an explicit `Origin` header (required for Sanctum's stateful-domain check), and disables caching (`cache: "no-store"`). Uses `API_INTERNAL_URL` (the Docker-internal `http://api:8000`), distinct from the browser-facing `NEXT_PUBLIC_API_URL`.

#### Relationships

Called by: `app/app/page.tsx`, `app/app/workspaces/[workspacePublicId]/page.tsx`.
Calls: Laravel `/api/platform/status`, `/api/auth/user`, `/api/workspaces`, `/api/workspaces/{id}`.

#### Status

Implemented.

---

### `src/lib/document-upload.ts`

#### File responsibilities

Encapsulates the entire client-side document-upload workflow: fetching upload configuration, initialising an upload, validating a file against server-declared rules before spending bandwidth, performing the actual presigned PUT, and confirming completion. This is where you'd change client-side validation rules or upload concurrency behaviour.

#### Technical description

Exports types `Document`, `DocumentStatus`, `DocumentUploadConfiguration`, `PresignedUpload`, `InitialisedDocumentUpload`, and functions `documentUploadConfiguration()`, `initialiseDocumentUpload()`, `completeDocumentUpload()`, `validateDocumentFile()`, `uploadToPresignedUrl()` (raw `XMLHttpRequest` for upload-progress events; fetch does not expose these), and `runWithConcurrency()` (a simple worker-pool limiter for parallel uploads). Validation checks extension against server-declared MIME types, empty files, and max size — mirroring, not replacing, Laravel's own `InitializeDocumentUploadRequest` validation.

#### Relationships

Called by: `DocumentUploadPanel.tsx`.
Calls: Laravel `/api/workspaces/{id}/documents/uploads/configuration`, `.../uploads`, `.../uploads/complete`; then PUTs directly to the presigned S3/LocalStack URL.
Tested by: `src/lib/document-upload.test.ts`.

#### Status

Implemented.

---

### `src/components/AuthForm.tsx`

#### File responsibilities

One shared form component driving all four auth flows (login, register, forgot-password, reset-password) rather than four near-identical components. You'd modify this to add a field to registration, change post-login redirect behaviour, or adjust password-recovery copy.

#### Technical description

Client component (`"use client"`). A `mode: "login" | "register" | "forgot" | "reset"` prop selects copy and the Laravel endpoint from a `content` lookup table. Submits `FormData` as JSON via `apiFetch`. On success: `register` routes to `/verify-email`; `reset` routes to `/login?reset=complete`; `forgot` shows a static (deliberately non-revealing) success message; `login` routes to `/app`. Errors render via `firstError()`.

#### Relationships

Called by: `login/page.tsx`, `register/page.tsx`, `forgot-password/page.tsx`, `reset-password/page.tsx`.
Calls: `apiFetch` → Laravel `/api/auth/{login,register,forgot-password,reset-password}`.

#### Status

Implemented.

---

### `src/components/DocumentUploadPanel.tsx`

#### File responsibilities

The workspace document-upload UI: drag-and-drop or file-picker selection, a per-file progress queue, and batch upload with bounded concurrency. This is the most stateful component in the app and the one you'd touch for any upload-UX change.

#### Technical description

Client component with local `UploadItem[]` state machine per file (`waiting → initialising → uploading → verifying → complete | failed`). Accepts injectable `initialise`/`complete`/`transport` props (defaulting to the real `document-upload.ts` functions) specifically to make the component unit-testable without network calls. Loads upload configuration on mount unless provided, validates files client-side via `validateDocumentFile`, and runs the actual uploads through `runWithConcurrency` bounded by the server-declared `upload_concurrency`.

#### Relationships

Called by: `app/app/workspaces/[workspacePublicId]/page.tsx`.
Calls: `document-upload.ts` functions.
Tested by: `src/components/DocumentUploadPanel.test.tsx` (uses the injectable props to fake the network layer).

#### Status

Implemented.

---

### `src/components/WorkspaceSwitcher.tsx`

#### File responsibilities

Renders the list of workspaces a user belongs to and highlights the active one. Purely presentational — it carries no authorisation weight itself (per ADR-0006, "current workspace" is a UX convenience only; every request is still membership-checked server-side).

#### Technical description

Server-renderable, stateless. Takes `activeWorkspace` and `workspaces: Workspace[]` props, renders `<Link>`s to `/app/workspaces/{public_id}`, marking the active one with `aria-current`.

#### Relationships

Called by: `app/app/workspaces/[workspacePublicId]/page.tsx`.
Tested by: `src/components/WorkspaceSwitcher.test.tsx`.

#### Status

Implemented.

---

### `src/components/LogoutButton.tsx` / `src/components/ResendVerificationButton.tsx`

#### File responsibilities

Small, single-purpose client components: sign the user out and redirect to `/login`; resend the Fortify email-verification notification with basic status feedback.

#### Technical description

Both are `"use client"` components calling `apiFetch` (`POST /api/auth/logout`, `POST /api/auth/email/verification-notification`) with local `pending`/`status` state.

#### Relationships

Called by: workspace pages (`LogoutButton`), `verify-email/page.tsx` (`ResendVerificationButton`).

#### Status

Implemented.

---

### `src/app/layout.tsx`

#### Page responsibilities

The root layout: fonts, global metadata (`title` template, description), and the `<html>`/`<body>` shell every route renders inside.

#### Technical description

Loads `Geist`/`Geist_Mono` via `next/font/google`, exports `metadata: Metadata`, imports `globals.css`.

#### Relationships

Called by: the Next.js App Router for every route.

#### Status

Implemented.

---

### `src/app/page.tsx` (landing page)

#### Page responsibilities

The public marketing/landing page — the only route that requires no authentication and does no server fetch.

#### Technical description

Static server component; links to `/login` and `/register`.

#### Status

Implemented.

---

### `src/app/login/page.tsx`, `register/page.tsx`, `forgot-password/page.tsx`

#### Page responsibilities

Thin route wrappers that render `AuthForm` in the corresponding mode. There is intentionally no logic here beyond mode selection.

#### Technical description

Each is a one-line default export rendering `<AuthForm mode="..." />`.

#### Status

Implemented.

---

### `src/app/reset-password/page.tsx`

#### Page responsibilities

Renders the reset-password form pre-filled with the `token`/`email` query parameters from Fortify's reset-link email.

#### Technical description

Async server component; awaits `searchParams` (Next.js 15+ convention — search params are a `Promise`), passes `token`/`email` into `AuthForm`.

#### Status

Implemented.

---

### `src/app/verify-email/page.tsx` / `src/app/verify-email/result/page.tsx`

#### Page responsibilities

`verify-email` is the "check your inbox" holding page with a resend action. `verify-email/result` is where Laravel's signed verification link (`VerifyEmailRedirectResponse.php`) redirects to, showing success or failure.

#### Technical description

`result/page.tsx` reads a `status` query param (`"verified"` or anything else) to switch copy and CTA target (`/app` vs. `/verify-email`).

#### Relationships

Called by: Laravel's `VerifyEmailRedirectResponse` (external redirect, not a Next.js link).

#### Status

Implemented.

---

### `src/app/app/page.tsx`

#### Page responsibilities

The "no workspace resolved yet" landing point after login: verifies platform access, redirects to the user's first workspace if one exists, or shows a "no workspace assigned" state (workspace provisioning is platform-admin controlled per ADR-0006 — there is deliberately no self-service "create workspace" button here).

#### Technical description

Async server component. Calls `platformAccess()` first and redirects to `/login` (401) or `/verify-email` (403) before doing anything else — this is the enforcement point that makes every page under `/app` effectively protected. Then fetches `currentUser()` and `userWorkspaces()`; redirects to the first workspace's page if any exist.

#### Relationships

Calls: `server-api.ts` (`platformAccess`, `currentUser`, `userWorkspaces`).
Uses: `LogoutButton`.

#### Status

Implemented.

---

### `src/app/app/workspaces/[workspacePublicId]/page.tsx`

#### Page responsibilities

The main workspace screen: shows the workspace switcher, the active workspace's name/role, and the document-upload panel. This is the primary "logged-in" screen of the product today — there is no document *list* or retrieval UI yet, since those phases haven't been built.

#### Technical description

Async server component; re-verifies `platformAccess()`, then fetches `currentUser()`, `userWorkspaces()` and `userWorkspace(workspacePublicId)` in parallel via `Promise.all`. Calls Next.js `notFound()` if the workspace doesn't resolve (i.e. the user isn't an active member — Laravel returns 404, not 403, per ADR-0006's tenant-existence-concealment invariant). Renders `WorkspaceSwitcher` and `DocumentUploadPanel`.

#### Relationships

Calls: `server-api.ts`.
Uses: `WorkspaceSwitcher`, `DocumentUploadPanel`, `LogoutButton`.

#### Status

Implemented. Document list/status view is **not present** — only upload, no way yet to see previously uploaded documents in the UI.

---

## 3.2 Laravel API (`apps/api`)

Laravel follows the layered convention documented in `CONTRIBUTING.md`: thin controllers → Form Requests → Actions (one state-changing use case each) → Queries (tenant-scoped reads) → Services (infra capabilities) → API Resources (response shape). Every Eloquent model in this codebase enforces its own immutability invariants inside `booted()` static hooks rather than trusting callers — a repeated pattern worth recognising once (`Document`, `Workspace`, `OutboxEvent`, `IngestionEventClaim`).

### Actions (`app/Actions/`)

#### `Documents/CreateDocument.php`

**Responsibilities**: creates the `Document` row in `Uploading` state and computes its server-controlled, workspace-prefixed storage key (`workspaces/{workspace}/documents/{document}/source[.ext]`) — the client never chooses where a file is stored. You'd touch this to change the storage-key scheme or add new required document metadata.

**Technical description**: `handle(Workspace, User, sourceFilename, mediaType, sizeBytes, ?extension)`. Validates non-empty filename/media type, non-negative size, and a strict `[a-z0-9]+` extension pattern. Generates the public UUID and associates workspace/creator.

**Relationships** — Called by: `InitializeDocumentUpload`. Uses: `Document` model. Tested by: `tests/Feature/DocumentPersistenceTest.php`, `DocumentUploadWorkflowTest.php`.

**Status**: Implemented.

#### `Documents/InitializeDocumentUpload.php`

**Responsibilities**: the orchestration action behind "start an upload" — creates the Document row and generates a presigned upload URL in one transaction, so a document is never left referencing a storage key nobody was given permission to write to.

**Technical description**: `handle(...)` wraps `CreateDocument` + `DocumentObjectStorage::createUploadRequest()` in `DB::transaction()`. Returns `{document, upload}`.

**Relationships** — Called by: `DocumentUploadController::store`. Calls: `CreateDocument`, `DocumentObjectStorage`. Tested by: `DocumentUploadWorkflowTest.php`.

**Status**: Implemented.

#### `Documents/CompleteDocumentUpload.php`

**Responsibilities**: the "did the browser actually finish uploading" verification step — confirms the object exists in S3/LocalStack and its size matches what was authorised before transitioning `Uploading → Uploaded`. This directly implements ADR-0007's "object storage is reconciled toward the relational record" principle.

**Technical description**: Short-circuits if already `Uploaded` (idempotent). Rejects any state other than `Uploading`. Calls `DocumentObjectStorage::objectSize()` *before* opening a transaction (avoids holding a row lock during a slow storage call), then re-checks state under `lockForUpdate()` inside the transaction to guard the race between the size check and the write.

**Relationships** — Called by: `DocumentUploadController::complete`. Calls: `DocumentObjectStorage`. Throws: `DocumentUploadException`. Tested by: `DocumentUploadWorkflowTest.php`.

**Status**: Implemented.

#### `Documents/RequestDocumentIngestion.php`

**Responsibilities**: implements ADR-0008's outbox write — atomically transitions a Document `Uploaded → Queued` **and** writes the durable outbox record in the same PostgreSQL transaction. This is the exact seam the whole transactional-outbox ADR exists to protect.

**Technical description**: `handle(Document, correlationId)`. Locks the Document row, is idempotent for documents already `Queued`/`Processing` (returns as-is), rejects any other state via `DocumentIngestionException`. Builds the payload via `DocumentIngestionRequestedPayload`, validates it against the canonical JSON Schema via `DocumentIngestionContractValidator` *before* committing, captures the current OpenTelemetry trace context (`traceparent`/`tracestate`) so the eventual SQS-side span can be correlated, then creates the `OutboxEvent` row.

**Relationships** — Called by: `DocumentIngestionController::store`. Calls: `DocumentIngestionRequestedPayload`, `DocumentIngestionContractValidator`. Publishes (durable intent only): an `OutboxEvent` row. Tested by: `DocumentIngestionPublicationTest.php`.

**Status**: Implemented.

#### `Ingestion/ClaimDocumentIngestion.php`

**Responsibilities**: the authoritative, idempotent `Queued → Processing` transition requested by the Python worker via the internal HMAC-authenticated endpoint (ADR-0009). This is the only place in the codebase where an external process can move a Document into `Processing`.

**Technical description**: `handle(event, payloadSha256)`. Double-checked locking pattern: checks for an existing `IngestionEventClaim` by `event_id` before *and* after resolving the Document row (both under `lockForUpdate()`), to close the race between two concurrent claim attempts for the same event. Returns an `IngestionClaimResult` with one of four `IngestionClaimOutcome`s: `Claimed` (first successful claim), `AlreadyClaimed` (exact-replay, same identity — idempotent success), `StaleEvent` (Document already moved past `Queued`), `IneligibleState` (Document was never `Queued`, e.g. still `Uploaded`). Throws `IngestionClaimException::eventIdentityReused()` if the same `event_id` is replayed with *different* workspace/document/correlation/payload — this is what stops event-ID collision from corrupting a different claim.

**Relationships** — Called by: `DocumentIngestionClaimController::store`. Uses: `IngestionEventClaim`, `Document` models. Tested by: `DocumentIngestionClaimTest.php`.

**Status**: Implemented.

#### `Ingestion/PublishIngestionOutbox.php`

**Responsibilities**: the outbox publisher's core batch-processing loop — the "separate process, not request-cycle code" ADR-0008 requires. This is the single most instrumented class in the API: every publication attempt gets a trace span and two metrics.

**Technical description**: `handle(): array{claimed,published,retryable,failed}`. `claimNextEvent()` uses `SELECT ... FOR UPDATE SKIP LOCKED` on PostgreSQL (falls back to plain `lockForUpdate()` otherwise) to safely claim one unpublished, unfailed, not-currently-leased `OutboxEvent`, respecting `next_attempt_at` for retry backoff and a claim-lease expiry so a crashed publisher's claim eventually becomes reclaimable. Re-validates the payload against the contract before publishing (a poison payload is marked `failed_at` permanently, not retried). On publish success, marks `published_at`; on transient failure, computes exponential backoff (`retryDelay`) and reschedules. Every attempt opens an OTel span parented from the event's own stored `traceparent`/`tracestate` (propagating the *original* HTTP request's trace, not the publisher's own), and records `rag.ingestion.outbox.publication.count`/`.duration` metrics through `TelemetryAttributeAllowlist`.

**Relationships** — Called by: `PublishIngestionOutboxCommand`. Calls: `DocumentIngestionContractValidator`, `IngestionEventPublisher` (bound to `SqsIngestionEventPublisher`). Instrumented by: `maketime.laravel.ingestion-publisher` tracer/meter. Consumes: `OutboxEvent` rows. Tested by: `DocumentIngestionPublicationTest.php`.

**Status**: Implemented.

#### `Workspaces/CreateWorkspace.php`

**Responsibilities**: the only place a Workspace and its owner Membership are created — always together, atomically, per ADR-0006's "never one without the other" invariant. There is no user-facing "create workspace" endpoint yet; this is currently only exercised by the `DevelopmentWorkspaceSeeder`.

**Technical description**: `handle(User creator, name)` in one `DB::transaction()`: creates the `Workspace` with a generated public UUID and a de-duplicated slug (`uniqueSlug` appends `-2`, `-3`, ... on collision), then creates a `WorkspaceMembership` with role `Owner`.

**Relationships** — Called by: `DevelopmentWorkspaceSeeder`. Tested by: `WorkspacePersistenceTest.php`.

**Status**: Implemented. Self-service creation is explicitly **planned but not present** (ADR-0006: "platform-admin controlled").

#### `Fortify/CreateNewUser.php` / `ResetUserPassword.php` / `PasswordValidationRules.php`

**Responsibilities**: Fortify's pluggable user-creation and password-reset actions, customised for this platform's canonical-email and password-policy rules. `ResetUserPassword` additionally invalidates all of a user's existing database sessions on reset — a deliberate ADR-0005 security choice (favours security over "stay logged in on other devices").

**Technical description**: Both use `CanonicalEmail::from()` before validation/persistence. `PasswordValidationRules` centralises the 12-char/mixed-case/number/symbol rule shared with `AppServiceProvider`'s global `Password::defaults()`.

**Relationships** — Bound via: `FortifyServiceProvider`. Tested by: `AuthenticationTest.php`.

**Status**: Implemented.

---

### Console (`app/Console/Commands/PublishIngestionOutboxCommand.php`)

**Responsibilities**: the entrypoint that turns `PublishIngestionOutbox` into the long-running `publisher` Compose service (`php artisan ingestion:publish`), or a single-batch run for `make publish-ingestion`.

**Technical description**: `ingestion:publish {--once}`. Loops calling `PublishIngestionOutbox::handle()`; sleeps `poll_interval_seconds` between empty batches. Waits (rather than failing) if the `outbox_events` table doesn't exist yet, unless `--once` is passed, in which case it fails fast — useful for CI/scripted verification.

**Relationships** — Calls: `PublishIngestionOutbox`. Invoked by: `compose.yaml`'s `publisher` service, `make publish-ingestion`.

**Status**: Implemented.

---

### Enums (`app/Enums/`)

`DocumentStatus` (`Uploading, Uploaded, Queued, Processing, Indexed, Failed, Deleting, Deleted`) is the literal state machine from ADR-0007. `WorkspaceRole` (`Owner, Admin, Member`) is the fixed role set from ADR-0006 — no custom roles yet. `IngestionClaimOutcome` (`Claimed, AlreadyClaimed, StaleEvent, IneligibleState`) drives both the claim controller's HTTP status mapping and the Python worker's acknowledge/retry/poison decision. All three are plain backed PHP enums with no behaviour — the logic lives in the Actions that consume them. **Status**: Implemented.

---

### Policies (`app/Policies/`)

`DocumentPolicy` (`requestIngestion`, `completeUpload`) and `WorkspacePolicy` (`uploadDocuments`) both currently reduce to the same check: is the authenticated user an active member of the owning workspace. This is intentionally coarse — ADR-0006's three-role model doesn't yet gate these particular actions by role, only by membership. **Status**: Implemented (membership-level authorisation only; no role-based restriction yet on these specific actions).

---

### Queries (`app/Queries/`)

`Documents/FindDocumentForWorkspace` and `Workspaces/FindWorkspaceForUser`/`ListWorkspacesForUser` are the tenant-scoping boundary ADR-0006 requires to sit at the query layer, not only in a policy. `FindWorkspaceForUser` resolves through the `WorkspaceMembership` relation (`whereBelongsTo($user)` joined to a workspace matching the public ID) and calls `firstOrFail()` — a non-member or non-existent workspace both surface as a 404, matching ADR-0006's "cross-tenant requests return 404, not 403" invariant. **Status**: Implemented.

---

### Services (`app/Services/`)

#### `Documents/DocumentObjectStorage.php`

**Responsibilities**: the only class that talks to S3/LocalStack for document bytes. It exists to keep the presigned-URL scheme and size-verification logic in one infrastructure-facing service rather than scattered across Actions.

**Technical description**: `createUploadRequest(Document)` calls Laravel's `temporaryUploadUrl()` on the `s3_uploads` disk (a disk configured with the browser-reachable `AWS_UPLOAD_ENDPOINT`, distinct from the internal `s3` disk Laravel itself uses — see `config/filesystems.php`), stripping `Host`/`Content-Length` from the returned headers since a browser sets those itself. `objectSize(Document)` checks existence/size on the internal `s3` disk. Both wrap storage exceptions into `DocumentUploadException::storageUnavailable()` (503) rather than letting a raw SDK exception leak out.

**Relationships** — Called by: `InitializeDocumentUpload`, `CompleteDocumentUpload`. Uses: Laravel's `FilesystemFactory` (S3 driver against LocalStack locally, real AWS in production).

**Status**: Implemented.

#### `Ingestion/DocumentIngestionContractValidator.php`

**Responsibilities**: the single place that loads and validates against the canonical `contracts/events/document-ingestion-requested/v1.schema.json` — both the publisher and the internal claim endpoint use this same class, so Laravel never has two independent ideas of what a valid event looks like.

**Technical description**: Loads the schema once in the constructor from `config('ingestion.contract_path')` (mounted read-only at `/contracts` in the container). `validate(array $payload)` round-trips the payload through JSON encode/decode (so PHP array types match JSON Schema's object semantics) and validates via `opis/json-schema`; throws `InvalidIngestionEvent` with a flattened error message on failure.

**Relationships** — Called by: `RequestDocumentIngestion`, `PublishIngestionOutbox`, `DocumentIngestionClaimController`. Consumes: `contracts/events/document-ingestion-requested/v1.schema.json`.

**Status**: Implemented.

#### `Ingestion/IngestionWorkerRequestAuthenticator.php`

**Responsibilities**: implements ADR-0009's HMAC-SHA256 verification exactly — this is a security-critical file; any change here must stay byte-for-byte compatible with the Python-side `IngestionWorkerSigner`, since ADR-0009 ships a normative cross-language test vector precisely to keep the two in sync.

**Technical description**: `verify(Request)` rejects any query string outright, validates the four `X-Ingestion-Worker-*` headers' formats, checks the route's `{eventId}` matches the header, enforces the configurable clock-skew window, looks up the Key ID in `config('ingestion.worker_auth.keys')` (rejecting non-canonical or short Base64 secrets), recomputes the canonical string-to-sign (`timestamp\nMETHOD\npath\nbody-sha256\neventId`) and compares with `hash_equals()` (constant-time). Every failure path calls a single `fail(reason): never` that throws `IngestionWorkerAuthenticationException` — reasons are logged but never returned to the caller (ADR-0009: "generic response rather than revealing which verification step failed").

**Relationships** — Called by: `AuthenticateIngestionWorker` middleware. Mirrors: `apps/ai/app/ingestion/signing.py`. Tested by: `DocumentIngestionClaimTest.php`.

**Status**: Implemented.

#### `Ingestion/SqsIngestionEventPublisher.php`

**Responsibilities**: the concrete `IngestionEventPublisher` implementation — the only class in Laravel that actually calls SQS. Exists to keep `PublishIngestionOutbox` testable against the `IngestionEventPublisher` interface without a real queue.

**Technical description**: `publish(payload): string`. Resolves Laravel's `sqs` queue connection, injects the current OTel trace context into SQS `MessageAttributes` via `TraceContextPropagator` (so the Python worker can continue the same trace), and calls `pushRaw()`. Throws if SQS doesn't return a message ID.

**Relationships** — Implements: `IngestionEventPublisher`. Bound in: `AppServiceProvider`. Called by: `PublishIngestionOutbox`.

**Status**: Implemented.

---

### Support (`app/Support/`)

`CanonicalEmail::from()` (trim + lowercase) is the single email-canonicalisation point used by the `CanonicalizeEmail` middleware, `CreateNewUser`, `ResetUserPassword`, `FortifyServiceProvider`'s rate limiters, and the password-reset URL builder — deliberately one function rather than repeated `strtolower(trim())` calls. `CorrelationId::resolve()` accepts a client-supplied `X-Correlation-ID` only if it's already a valid UUID, otherwise mints a fresh one — never trusts client input blindly but doesn't discard a legitimate one either. `Ingestion/DocumentIngestionRequestedPayload::build()` is the exact shape-builder for the v1 event contract (see the Document Upload → Ingestion flow in §4). `Ingestion/IngestionClaimResult` is a small immutable `readonly` DTO pairing an `IngestionClaimOutcome` with the resulting `DocumentStatus`. **Status**: Implemented.

---

### Telemetry (`app/Telemetry/`)

This is where ADR-0012's privacy allowlist is actually enforced, not just documented.

- **`TelemetryAttributeAllowlist.php`** — `trace()`/`metric()` each `array_intersect_key` the given attributes against `config('telemetry.trace_attributes')`/`.metric_attributes` (see `config/telemetry.php`). Any attribute not explicitly listed is silently dropped. This is the concrete implementation of "an explicit allowlist, not a denylist" from ADR-0012.
- **`TelemetrySdkFactory.php`** — builds the real `TracerProvider`/`MeterProvider` (OTLP/HTTP+protobuf exporters to the Collector) or falls back to `NoopTracerProvider`/`NoopMeterProvider` if `telemetry.enabled` is false or SDK construction throws — this is the concrete mechanism behind "a telemetry failure must never fail a user-facing request."
- **`TelemetryLifecycle.php`** — `flush()`/`shutdown()`, both best-effort (swallow `Throwable`), called from `TraceHttpRequests::terminate()` and application termination.
- **`DatabaseTelemetry.php`** — listens to `QueryExecuted` and emits a `db.*`-named span (backdated to the query's actual start time via `Clock`) plus a `db.client.operation.duration` histogram, classifying only `SELECT/INSERT/UPDATE/DELETE` (else `OTHER`) as the operation name.

**Relationships** — Registered by: `TelemetryServiceProvider`. Consumed by: `TraceHttpRequests` middleware, `PublishIngestionOutbox`. Tested by: `TelemetryTest.php`.

**Status**: Implemented.

---

### Providers (`app/Providers/`)

`TelemetryServiceProvider` binds the SDK `TracerProvider`/`MeterProvider` as singletons (aliased to the API interfaces OTel application code actually type-hints against), wires the `DB::listen()` hook to `DatabaseTelemetry`, and registers a `terminating()` shutdown hook. `AppServiceProvider` binds `IngestionEventPublisher → SqsIngestionEventPublisher`, sets the global `Password::defaults()` policy, and overrides Fortify's `ResetPassword::createUrlUsing()` to point at the Next.js `/reset-password` page rather than a Laravel view. `FortifyServiceProvider` disables Fortify's own routes (`Fortify::ignoreRoutes()` — this app defines its own routes in `routes/api.php`), rebinds every Fortify response contract to a JSON-returning class (see Responses below), wires the two custom actions, and configures three named rate limiters (`login`, `registration`, `password-reset-link`) keyed by canonical email + IP where relevant.

**Status**: Implemented.

---

### HTTP layer (`app/Http/`)

#### Controllers

- **`DocumentUploadController`** — `configuration()` (returns allowed formats/max size/concurrency from `config/documents.php`), `store()` (calls `InitializeDocumentUpload`, 201), `complete()` (calls `CompleteDocumentUpload`). All three resolve the workspace via `FindWorkspaceForUser` and authorise via `Gate::authorize`.
- **`DocumentIngestionController`** — single `store()` action; resolves the correlation ID (from `X-Correlation-ID` or freshly minted), calls `RequestDocumentIngestion`, returns 202 with the correlation ID echoed back as a response header.
- **`Internal/DocumentIngestionClaimController`** — the HMAC-protected internal endpoint. Parses and contract-validates the raw request body, cross-checks the event ID appears identically in the URL, the signed header, *and* the body, then calls `ClaimDocumentIngestion`. Maps `IngestionClaimOutcome` to HTTP status: `Claimed`/`AlreadyClaimed` → 200, `StaleEvent`/`IneligibleState` → 409.
- **`WorkspaceController`** — `index()` (list current user's workspaces) and `show()` (single workspace by public ID), both delegating entirely to Queries.

**Status**: Implemented.

#### Middleware

- **`AuthenticateIngestionWorker`** — wraps `IngestionWorkerRequestAuthenticator::verify()`; on failure, logs only *validated-format* Key ID/Event ID (never raw, unvalidated header input) plus the failure reason, and returns a generic 401.
- **`CanonicalizeEmail`** — merges a canonicalised `email` input back into the request before validation runs, so every auth endpoint sees the same normalised value.
- **`RequireOpenRegistration`** — 404s the register route when `fortify.registration_enabled` is false (a config-driven kill switch for open registration, per ADR-0005).
- **`TraceHttpRequests`** — the HTTP-level counterpart to `DatabaseTelemetry`: extracts incoming trace context, starts a `SERVER`-kind span named after the eventually-matched route template (updated after routing resolves, since the template isn't known up front), records `http.server.request.count`/`.duration`, and flushes telemetry in `terminate()`.

**Status**: Implemented.

#### `Requests/InitializeDocumentUploadRequest.php`

Form Request validating `filename`/`size_bytes`/`media_type` against `config('documents.formats')`, plus an `after()` hook cross-checking the declared media type actually matches the extension's allowed set — this is the server-side twin of `document-upload.ts`'s `validateDocumentFile`, and the one that's actually authoritative. **Status**: Implemented.

#### `Resources/DocumentResource.php` / `WorkspaceResource.php`

Standard API Resources shaping `Document` and `WorkspaceMembership` (note: `WorkspaceResource` wraps a *membership*, not a bare workspace, so it can expose `role` alongside the workspace fields) into the public JSON shape. **Status**: Implemented.

#### `Responses/ApiLoginResponse.php`, `ApiRegisterResponse.php`, `GenericPasswordResetLinkResponse.php`, `VerifyEmailRedirectResponse.php`

Fortify's pluggable response contracts, all rebound in `FortifyServiceProvider` to return JSON (or, for email verification, redirect to the Next.js result page) instead of Fortify's default Blade-view redirects — this is what makes Fortify usable behind a JSON API rather than a server-rendered app. `GenericPasswordResetLinkResponse` deliberately implements *both* the success and failure contracts identically, returning the same generic message either way, to prevent account enumeration via the forgot-password endpoint. **Status**: Implemented.

---

### Contracts, Exceptions, Models, routes, config, migrations

- **`app/Contracts/Ingestion/IngestionEventPublisher.php`** — a one-method interface (`publish(payload): string`) whose entire purpose is to let `PublishIngestionOutbox` depend on an abstraction rather than the concrete SQS client, per the "no controller/model talks to SQS directly" rule in ADR-0008.
- **`app/Exceptions/*`** — five small, typed, `render()`-implementing exceptions (`DocumentIngestionException`, `DocumentUploadException`, `IngestionClaimException`, `IngestionWorkerAuthenticationException`, `InvalidIngestionEvent`), each mapping a specific domain failure to the correct HTTP status without a generic catch-all. All implement empty `report(): void {}` — these are expected business-flow outcomes, not exceptions worth logging to an error tracker.
- **`app/Models/*`** — `Document`, `Workspace`, `WorkspaceMembership`, `User`, `OutboxEvent`, `IngestionEventClaim`. Every one that represents an append-only or lifecycle-governed record enforces immutability of its identity/provenance fields inside a `booted()` `saving`/`updating` hook that throws `LogicException` on a disallowed mutation attempt — this is a deliberate, repeated pattern, not incidental to one model.
- **`routes/api.php`** — the entire public and internal API surface in one file: `auth/*` (mostly unauthenticated or `guest:web`-gated), the internal HMAC-protected claim route (no `auth:sanctum` — a completely separate auth mechanism), and everything else behind `auth:sanctum` + `verified`.
- **`routes/web.php`** — just the signed email-verification link (`auth:web` + `signed` + throttled) and a default `welcome` view (unused by the actual product).
- **`bootstrap/app.php`** — wires `statefulApi()` (Sanctum), appends `TraceHttpRequests` and `CanonicalizeEmail` globally, redirects unauthenticated web-guard requests to the Next.js `/login` (not a Laravel view), and forces JSON error rendering for any `api/*` path.
- **`config/documents.php`** — the single source of truth for supported formats/MIME types, max upload size, presigned URL lifetime, and upload concurrency; consumed by both the upload controller and (via the API response) the frontend.
- **`config/ingestion.php`** — outbox publisher tuning (batch size, poll interval, claim lease, retry backoff bounds) and the HMAC worker-auth key ring, built from environment variables with `array_filter` dropping any unset key.
- **`config/telemetry.php`** — the actual allowlist arrays `TelemetryAttributeAllowlist` reads, plus exporter endpoint/protocol/timeout.
- **`config/sanctum.php`**, **`config/session.php`**, **`config/filesystems.php`** — implement ADR-0005's stateful-domain list, database session driver with 120-minute lifetime, and the `s3`/`s3_uploads` dual-disk split (see `DocumentObjectStorage` above) respectively.
- **Migrations** — `users`/`cache`/`jobs` (Laravel defaults), `workspaces`, `workspace_memberships` (with a raw partial unique index enforcing "at most one owner per workspace" — `CREATE UNIQUE INDEX ... WHERE role = 'owner'`, a database-level enforcement of an ADR-0006 invariant that no Eloquent-level code could guarantee alone), `documents` (with Postgres `CHECK` constraints for non-negative size and "failed status requires failure details"), `outbox_events` (with `CHECK` constraints preventing simultaneous `published_at`/`failed_at` and enforcing the claim-pair invariant), `ingestion_event_claims` (with a `CHECK` regex-validating the stored SHA-256 hex digest), and a later migration adding nullable `traceparent`/`tracestate` columns to `outbox_events` for trace propagation. **No RLS policy migration exists** — confirms ADR-0006's "must not describe RLS as active until implemented."
- **`database/seeders/DevelopmentWorkspaceSeeder.php`** — creates two deterministic local-dev workspaces/users/memberships via `CreateWorkspace`, useful for manually exercising the workspace-switcher UI.

**Status**: Implemented (RLS explicitly not present, per above).

---

### Test suites (`apps/api/tests/`)

`Feature/AuthenticationTest.php` exercises the full Sanctum+Fortify flow. `WorkspaceAccessTest.php`/`WorkspacePersistenceTest.php` verify membership-scoped access and the owner-membership invariant. `DocumentPersistenceTest.php`/`DocumentUploadWorkflowTest.php` cover the upload lifecycle end-to-end including size-mismatch and storage-unavailable paths. `DocumentIngestionPublicationTest.php` verifies the outbox write and publisher batch behaviour (including retry/backoff and poison-payload handling). `DocumentIngestionClaimTest.php` covers the HMAC-authenticated claim endpoint's four outcomes and identity-reuse rejection. `TelemetryTest.php` verifies the allowlist actually filters attributes and that a telemetry failure doesn't break a request. `Unit/DocumentIngestionRequestedContractTest.php` validates the shared JSON Schema fixtures (mirrored by the Python-side equivalent — this is the cross-language contract test ADR-0008/the contract README calls for). `Unit/DocumentStatusTest.php` covers the enum. **Status**: Implemented.

---

## 3.3 Python AI Service (`apps/ai`)

This service currently has two entrypoints sharing one codebase: `app/main.py` (a nearly-empty FastAPI HTTP app, `/health` only) and `app/worker.py` (a standalone long-running SQS consumer, run as the `worker` Compose service — not part of the FastAPI process). The four pipeline packages (`ingestion`, `extraction`, `normalisation`, `chunking`) are otherwise plain, dependency-injectable Python with no FastAPI coupling — they are pure pipeline stages that a future retrieval/embedding phase will call into, not currently wired to any HTTP route.

### `app/main.py`

**Responsibilities**: the FastAPI application shell. Today it does almost nothing product-wise — no ingestion, extraction or chunking endpoint exists — but it is where OpenTelemetry HTTP instrumentation lives, and where a future HTTP surface (if one is ever needed beyond the internal claim boundary) would be added.

**Technical description**: `lifespan()` calls `configure_telemetry()` on startup and `.shutdown()` on teardown. A `@app.middleware("http")` hook extracts incoming trace context, starts a `SERVER`-kind span (route template resolved *after* the handler runs, matching Laravel's `TraceHttpRequests` pattern), and records `http.server.request.count`/`.duration`. `/health` returns `{"status": "ok"}` — used by `compose.yaml`'s healthcheck.

**Relationships** — Instrumented by: `app.telemetry`. Tested by: `tests/test_health.py`.

**Status**: Implemented (minimal — health check and telemetry scaffolding only).

---

### `app/worker.py`

**Responsibilities**: the process entrypoint for the `worker` Compose service — wires together the SQS queue, the HMAC signer, and the claim client into a running `IngestionWorker`, and handles graceful shutdown on `SIGTERM`/`SIGINT`.

**Technical description**: `build_worker()` constructs a `boto3` SQS client, `SqsIngestionQueue`, `IngestionWorkerSigner` (from `INGESTION_WORKER_HMAC_KEY_ID`/`_SECRET`), `IngestionClaimClient`, and `IngestionWorker`. `main()` supports `--once` (process one receive batch and exit, used by `make consume-ingestion`) or runs forever. Signal handlers set a `threading.Event` the worker's loop checks.

**Relationships** — Calls: `app.ingestion.*`. Tested by: `tests/test_worker_entrypoint.py`.

**Status**: Implemented.

---

### `app/settings.py`, `app/telemetry.py`, `app/structured_logging.py`

- **`settings.py`** — a `pydantic-settings` `Settings` class covering SQS tuning, the worker's HMAC identity, and OTel exporter config, cached via `@lru_cache`.
- **`telemetry.py`** — `configure_telemetry()` builds real OTLP/HTTP `TracerProvider`/`MeterProvider` (falling back to no-op on any setup failure — mirrors the Laravel factory's fail-safe philosophy) and exports `TRACE_ATTRIBUTE_ALLOWLIST`/`METRIC_ATTRIBUTE_ALLOWLIST` frozensets plus `trace_attributes()`/`metric_attributes()` filter functions — the Python-side equivalent of Laravel's `TelemetryAttributeAllowlist`, implemented as plain functions rather than a class.
- **`structured_logging.py`** — a `JsonFormatter` that serialises every log record (plus any `extra=` fields) as one JSON line; `configure_structured_logging()` installs it as the sole root handler.

**Relationships** — Consumed by every module in `ingestion/` for spans/metrics/logs. Tested by: `tests/test_telemetry.py`.

**Status**: Implemented.

---

### `app/ingestion/` — SQS consumption and the internal claim boundary

#### `sqs.py` (`SqsIngestionQueue`, `IngestionQueueMessage`)

**Responsibilities**: the only file that calls `boto3` SQS directly. `receive()` fetches up to `batch_size` messages, extracts `traceparent`/`tracestate` from SQS message attributes for trace continuation, and defensively drops (logs + skips, doesn't crash) any message missing a required field. `acknowledge()` deletes a message by receipt handle.

**Relationships** — Called by: `IngestionWorker`. Instrumented by: `maketime.python.ingestion.sqs` tracer/meter. Tested by: `tests/test_ingestion_sqs.py`.

**Status**: Implemented.

#### `contract.py` (`parse_and_validate_event`, `InvalidIngestionEvent`)

**Responsibilities**: the Python mirror of Laravel's `DocumentIngestionContractValidator` — loads the *same* `v1.schema.json` file (via `find_contract_directory()`, which walks up parent directories looking for `contracts/events/document-ingestion-requested`, matching the read-only `/contracts` mount) so both languages validate against one physical file, never a copy.

**Technical description**: Uses `jsonschema`'s `Draft202012Validator` with a `FormatChecker`. Validates schema-on-import (fails fast at process start if the schema itself is malformed).

**Relationships** — Called by: `IngestionWorker._process_message`. Tested by: `tests/test_document_ingestion_requested_contract.py` (the Python side of the shared contract test pair).

**Status**: Implemented.

#### `signing.py` (`IngestionWorkerSigner`, `SignedHeaders`)

**Responsibilities**: the exact byte-for-byte Python implementation of ADR-0009's HMAC signing scheme — must stay in lockstep with `IngestionWorkerRequestAuthenticator.php`. Rejects malformed Key IDs, non-canonical/short-decoded Base64 secrets, and non-canonical-lowercase event UUIDs at construction/sign time rather than producing a signature that Laravel would reject anyway.

**Relationships** — Called by: `IngestionClaimClient`. Mirrors: `apps/api/app/Services/Ingestion/IngestionWorkerRequestAuthenticator.php`. Tested by: `tests/test_ingestion_signing.py` (includes ADR-0009's normative test vector).

**Status**: Implemented.

#### `claim_client.py` (`IngestionClaimClient`, `ClaimDisposition`)

**Responsibilities**: turns Laravel's claim-endpoint HTTP response into one of three worker decisions — `ACKNOWLEDGE` (delete the SQS message), `RETRY` (leave it for redelivery), `POISON` (also leave it — it will eventually dead-letter via SQS's redrive policy rather than being force-deleted). This mapping is the concrete implementation of the transport-vs-domain retry split ADR-0007/0008 describe conceptually.

**Technical description**: `claim()` signs the request via `IngestionWorkerSigner`, injects OTel context into headers, POSTs to `/api/internal/ingestion/events/{event_id}/claim`. `_disposition()` maps: 200 + `claimed`/`already_claimed` → ACKNOWLEDGE; 409 + `stale_event` → ACKNOWLEDGE (the event is legitimately obsolete, not an error); 401/403/429/5xx → RETRY; 404/409(other)/422 → POISON; anything else → RETRY (fail toward retry, not data loss).

**Relationships** — Called by: `IngestionWorker._process_message`. Calls: Laravel `/api/internal/ingestion/events/{id}/claim`. Instrumented by: `maketime.python.ingestion.claim` tracer/meter. Tested by: `tests/test_ingestion_claim_client.py`.

**Status**: Implemented.

#### `worker.py` (`IngestionWorker`)

**Responsibilities**: the actual poll-parse-validate-claim-acknowledge loop, tying `sqs.py`, `contract.py` and `claim_client.py` together. This is the class `app/worker.py` (the entrypoint) constructs and runs.

**Technical description**: `run_once()` receives a batch and processes each message; `run()` loops `run_once()` forever with an error-backoff wait on unhandled exceptions. `_process_message()` parses/validates the event (a contract failure logs and leaves the message **unacknowledged** — deliberately poison, not silently dropped), then calls `IngestionClaimClient.claim()`, acknowledging only on `ACKNOWLEDGE`. Every message gets a `CONSUMER`-kind span parented from the message's own carried trace context, plus `rag.ingestion.message.count`/`messaging.process.duration` metrics.

**Relationships** — Called by: `app/worker.py`. Calls: `SqsIngestionQueue`, `IngestionClaimClient`, `contract.parse_and_validate_event`. Tested by: `tests/test_ingestion_worker.py`.

**Status**: Implemented.

---

### `app/extraction/` — the canonical extraction contract (ADR-0010)

#### `models.py`

**Responsibilities**: defines the entire `ExtractedDocument`/`Element` contract from ADR-0010 in Pydantic. This is the single most important file to read to understand what "extraction" actually produces in this codebase.

**Technical description**: `ImmutableModel` (base class, `frozen=True, extra="forbid"`) underlies everything. `SourceLocation` is a sealed-ish polymorphic base with format-specific subclasses `TextSourceLocation`, `PdfSourceLocation` (PDF geometry in points, rotation, crop-box distinction), `DocxSourceLocation` (body-block index, optional table row/column). `Element` (`id: UUID`, `kind`, `source_location`, `confidence`) is subclassed by `ParagraphElement`, `HeadingElement` (with `level`), `TableElement` (with `rows: tuple[TableRow,...]`, each row validated for contiguous, rectangular cell indexing), and `UnknownElement` — the ADR-0010-mandated safe-fallback type for anything a consumer doesn't recognise. `ExtractedDocument` is the top-level immutable contract: `workspace_id`, `document_id`, `source_media_type`, `source_byte_size`, `extractor: ExtractorIdentity`, `text`, `elements` (min length 1), `warnings`, `metadata`.

**Relationships** — Produced by: `plain_text.py`, `pdf/pdfplumber.py`, `docx/python_docx.py`. Consumed by: `normalisation/structural.py`.

**Status**: Implemented.

#### `errors.py`, `limits.py`

`ExtractionFailure` (carries `kind: ExtractionFailureKind.TRANSIENT|PERMANENT`, a machine-readable `code`, and a human-readable `user_message`) is the typed failure ADR-0010 requires — every extractor raises this, never a bare exception, for any unrecoverable input. `limits.py` just defines `DEFAULT_MAX_SOURCE_BYTES = 25 MiB`, shared across all three extractors. **Status**: Implemented.

#### `plain_text.py` (`PlainTextExtractor`)

**Responsibilities**: the simplest extractor — splits raw text into paragraph elements by blank-line boundaries, tracking exact character/line ranges for provenance.

**Technical description**: Decodes as `utf-8-sig` (strict — rejects non-UTF-8 with a permanent `invalid_encoding` failure), normalises line endings, then walks lines building `_ParagraphRange`s. Raises `empty_content` if no non-blank paragraphs exist.

**Relationships** — Tested by: `tests/test_plain_text_extraction.py`.

**Status**: Implemented.

#### `pdf/pdfplumber.py` (`PdfPlumberExtractor`), `pdf/protocol.py`, `pdf/factory.py`

**Responsibilities**: the most complex extractor. Detects and extracts tables (via `pdfplumber`'s line-based table strategy) *and* paragraph text per page, resolves which text boxes belong to a detected table vs. sit beside one (with a confidence-based "ambiguous overlap" warning path rather than silently guessing), reconstructs reading order by sorting blocks by vertical-then-horizontal position, and preserves precise PDF geometry (page dimensions, rotation, crop-box distinctness) per element for future citation use.

**Technical description**: `extract()` enforces `max_pages`/`max_source_bytes`/`max_extracted_characters` (raising typed permanent failures, not silent truncation). Distinguishes `encrypted_pdf` (password-protected — caught via `PDFPasswordIncorrect`), `invalid_pdf` (unparseable), `ocr_required` (no text but images present — a distinct, actionable failure code from plain `empty_content`). `protocol.py` defines the `PdfExtractor` `Protocol` (structural typing, no inheritance required); `factory.py`'s `create_pdf_extractor()` is the one indirection point for swapping the PDF library later without touching callers.

**Relationships** — Implements: `pdf.protocol.PdfExtractor`. Tested by: `tests/test_pdf_extraction.py`.

**Status**: Implemented.

#### `docx/python_docx.py` (`PythonDocxExtractor`), `docx/protocol.py`, `docx/factory.py`

**Responsibilities**: extracts paragraphs (classifying headings via style-name regex `Heading ?([1-9])`), tables (including recursive flattening of nested tables into their containing cell, with a `nested_table_flattened` warning rather than silent loss), and document core-properties metadata from a `.docx` package.

**Technical description**: Rejects legacy/encrypted OLE-compound-file `.doc` packages by magic-byte signature (`invalid_docx`/`unsupported_word_package`) before attempting to parse as a zip. Warns (doesn't fail) when inline images are present, since image content isn't extracted. Same factory/protocol indirection pattern as the PDF extractor.

**Relationships** — Implements: `docx.protocol.DocxExtractor`. Tested by: `tests/test_docx_extraction.py`.

**Status**: Implemented.

---

### `app/normalisation/` — structural normalisation (ADR-0010's third stage)

#### `models.py`

Defines `NormalisedDocument`/`NormalisedElement` (and typed subclasses mirroring the extraction-side `Element` hierarchy: `NormalisedParagraphElement`, `NormalisedHeadingElement`, `NormalisedTableElement`, `NormalisedUnknownElement`) plus `NormalisationChange` (a record of *what* was normalised away and why — required by ADR-0010's traceability rule). Every `NormalisedElement` retains `source_element_ids` back to the `ExtractedDocument` elements it derived from. **Status**: Implemented.

#### `structural.py` (`StructuralNormaliser`)

**Responsibilities**: the concrete, deterministic normaliser — Unicode NFC normalisation, line-ending/whitespace canonicalisation, removal of *semantically empty* elements (recorded as a `NormalisationChange`, never silently dropped), and a conservative repeated-header/footer-removal heuristic specific to multi-page PDFs (requires the *same* normalised text to appear at the same page-relative boundary position on every page before removing it — a deliberately narrow rule to avoid false positives).

**Technical description**: `normalise(ExtractedDocument) -> NormalisedDocument`. Builds deterministic element IDs via `uuid5` over (`normaliser_version`, source element ID, kind, normalised text) — so re-running normalisation over the same extraction produces identical IDs, per ADR-0010/0011's replayability principle. `_repeated_boundary_changes()` only activates when there are ≥3 pages with ≥2 elements each, and only removes a boundary element if it's an *identical* `ParagraphElement` at every single page's header/footer position — anything less certain is left alone.

**Relationships** — Called by: (not yet wired into the ingestion worker — see Status). Consumes: `ExtractedDocument`. Produces: `NormalisedDocument`, consumed by `chunking/baseline.py`. Tested by: `tests/test_structural_normalisation.py`.

**Status**: Implemented as a pipeline stage, but **not yet invoked by `IngestionWorker`** — the worker currently stops at claiming the Document for `PROCESSING`; nothing in `app/ingestion/worker.py` calls extraction, normalisation or chunking yet. These pipeline packages exist, are tested in isolation, and are ready to be wired in, but the actual `PROCESSING → INDEXED` orchestration is not yet built.

---

### `app/chunking/` — the deterministic chunking contract (ADR-0011)

#### `protocol.py`, `models.py`

`ChunkingStrategy` is a one-method `Protocol`: `chunk(document: NormalisedDocument) -> ChunkingResult` — no context wrapper, exactly as ADR-0011 mandates, since `NormalisedDocument` is already self-describing. `models.py` defines `TokenizerIdentity`, `BaselineChunkingConfiguration` (with a `fingerprint()` method — a SHA-256 of the canonical JSON serialisation, used for deterministic chunk-identity derivation), `ChunkContribution` (per-element provenance within a chunk, tagged `role: "primary"|"overlap"`), `Chunk`, `ChunkingWarning`, `ChunkingResult` (validates its own `configuration_fingerprint` matches `configuration.fingerprint()`, and that chunk ordinals are contiguous from zero — a model-level self-check, not just a strategy-level convention). **Status**: Implemented.

#### `baseline.py` (`BaselineStructuralChunker`)

**Responsibilities**: the one concrete `ChunkingStrategy` implementation (Stage 11.2's baseline). Groups normalised elements into token-bounded chunks, splits oversized elements deterministically at sentence/paragraph/word boundaries (row boundaries for tables) without ever discarding content, and computes token-bounded overlap between adjacent chunks.

**Technical description**: `_split_element()` uses the tokenizer's `largest_prefix_end`/`smallest_suffix_start` to find safe split points within the token budget, preferring sentence boundaries (`_SENTENCE_BOUNDARY` regex) over raw truncation. `_group_primary_pieces()` packs pieces up to `target_tokens`, with special-case handling so a heading is never left stranded alone at the end of a chunk if pairing it with the next piece would still fit. `_overlap_from()` walks backward through the previous chunk's pieces to build a token-bounded overlap prefix for the next chunk, shrinking the budget until it fits under `max_tokens`. `_build_chunk()` derives a deterministic `uuid5` chunk ID from workspace/document/strategy/version/configuration-fingerprint/ordinal/provenance/text — satisfying ADR-0011's "identity reflects what a chunk actually is." `_assert_complete()` is the ADR-0011 completeness check: walks every normalised element's *primary* contributions and raises `UnrepresentableContentError` if any character range isn't fully covered — a structural guarantee, not just a warning.

**Relationships** — Implements: `ChunkingStrategy`. Consumes: `NormalisedDocument`. Uses: `tokenizer.Tokenizer`. Tested by: `tests/test_baseline_chunking.py`, `tests/test_chunking_evaluation.py` (see `docs/evaluation/r11-s03-baseline-chunking.md`).

**Status**: Implemented as a pipeline stage; **not yet wired into the ingestion worker** (same caveat as normalisation above).

#### `tokenizer.py` (`TiktokenTokenizer`)

**Responsibilities**: pins the *exact* tokenizer behaviour chunking relies on for reproducibility — this is what makes ADR-0011's determinism guarantee actually hold in practice, not just in principle.

**Technical description**: Asserts the installed `tiktoken` version matches `TIKTOKEN_VERSION` exactly at construction (fails loudly rather than silently drifting). Computes a `vocabulary_fingerprint` by hashing every `(token, rank)` pair in the loaded encoding's merge ranks, since tiktoken exposes no public vocabulary-revision identifier otherwise. `largest_prefix_end`/`smallest_suffix_start` binary-search-like scan token-decode offsets to find safe character split points; both raise `UnrepresentableContentError` if the tokenizer can't round-trip the text exactly (a correctness guard against silent corruption from token boundary mismatches).

**Relationships** — Used by: `BaselineStructuralChunker`. Tested by: implicitly via `test_baseline_chunking.py`.

**Status**: Implemented.

#### `errors.py`

`ChunkingError` (base) / `UnrepresentableContentError` — the ADR-0011-mandated typed failure for content that cannot be safely chunked, raised instead of returning an incomplete "successful" result. **Status**: Implemented.

---

### Test suites (`apps/ai/tests/`)

Grouped by the stage they verify: `test_health.py`, `test_telemetry.py`, `test_worker_entrypoint.py` (process wiring); `test_ingestion_sqs.py`, `test_ingestion_signing.py`, `test_ingestion_claim_client.py`, `test_ingestion_worker.py`, `test_document_ingestion_requested_contract.py` (the ingestion pipeline, including the shared-schema contract test mirrored in Laravel); `test_plain_text_extraction.py`, `test_pdf_extraction.py`, `test_docx_extraction.py` (extraction, using fixtures under `tests/pdf_fixtures.py`/`tests/docx_fixtures.py`/`tests/fixtures/`); `test_structural_normalisation.py`; `test_baseline_chunking.py`, `test_chunking_evaluation.py` (chunking, the latter driven by `tests/fixtures/chunking/corpus.json` and written up in `docs/evaluation/r11-s03-baseline-chunking.md`). **Status**: Implemented.

---

## 3.4 Shared Contracts (`contracts/`)

#### `contracts/events/README.md`

Documents the general principles for every event contract in this directory (versioned, JSON-only, language-neutral, tenant-aware, traceable, idempotent) and states the rule that most directly shaped ADR-0008: **"Laravel job serialization is not a cross-language contract."** **Status**: Implemented (as documentation).

#### `contracts/events/document-ingestion-requested/v1.schema.json`, `v1.example.json`, `README.md`, `fixtures/*`

**File responsibilities**: the actual, physical JSON Schema both Laravel (`DocumentIngestionContractValidator`) and Python (`contract.py`) load and validate against — genuinely the same file on disk via each service's read-only `/contracts` mount, not two independently maintained copies. This is the concrete artefact ADR-0008 and ADR-0010's cross-service-contract reasoning point at.

**Technical description**: `additionalProperties: false` — even a purely additive optional field requires a new `v2` schema, a deliberate strict/fail-closed choice explained in the README (small blast radius: one producer, one consumer, one monorepo). `fixtures/` holds four deliberately-invalid payloads (`invalid-missing-workspace-id.json`, `invalid-unknown-field.json`, `invalid-unsupported-version.json`, `invalid-zero-byte-size.json`), each exercising one negative case shared by both languages' test suites.

**Relationships** — Consumed by: `DocumentIngestionContractValidator.php`, `contract.py`. Tested by: `DocumentIngestionRequestedContractTest.php` and `test_document_ingestion_requested_contract.py` (a matched pair, run against the same fixtures).

**Status**: Implemented.

#### `contracts/http/README.md`

States the intended future artefact (an OpenAPI spec for the Python AI service's private HTTP interface) and the browser-never-talks-to-Python rule. **Status**: Placeholder — no OpenAPI spec exists yet.

---

## 3.5 Infrastructure

#### `infrastructure/localstack/init/ready.d/10-provision-aws.sh`

**File responsibilities**: the idempotent bootstrap that makes `make up`/`make bootstrap` produce a working S3 bucket + SQS queue + DLQ + redrive policy on every fresh LocalStack container, without manual setup — this is what ADR-0004's "account-free one-command onboarding" actually depends on.

**Technical description**: POSIX `sh`, `set -eu`. Creates the document-upload bucket only if it doesn't already exist (`head-bucket` check first), sets a CORS policy scoped to `DOCUMENT_UPLOAD_CORS_ALLOWED_ORIGIN` (PUT/HEAD only, matching the presigned-upload flow), creates the DLQ then the main queue, and attaches a `RedrivePolicy` referencing the DLQ's ARN with `SQS_MAX_RECEIVE_COUNT` (default 3).

**Relationships** — Run by: LocalStack's `ready.d` init hook on every start, and via `make aws-provision`. Verified by: `scripts/localstack/verify.sh` (`make aws-status`), and LocalStack's own Compose healthcheck in `compose.yaml`.

**Status**: Implemented.

#### `infrastructure/opentelemetry/collector.yaml`

**File responsibilities**: the Collector configuration that makes ADR-0012's "backend choice is a Collector-configuration change, not an application-code change" actually true — Laravel and Python both export unconditionally to this one Collector; only this file decides where telemetry ultimately lands.

**Technical description**: OTLP receiver (gRPC :4317, HTTP :4318), a `batch` processor, and one `otlp_http/local_backend` exporter (to `OTEL_BACKEND_OTLP_ENDPOINT`, the local Grafana `otel-lgtm` stack) with a `sending_queue` and `retry_on_failure` enabled — both pipelines (`traces`, `metrics`) route through it. A `health_check` extension backs the Compose healthcheck.

**Relationships** — Consumes from: `apps/api` (`TelemetrySdkFactory`), `apps/ai` (`telemetry.py`). Exports to: `otel-lgtm` (Grafana). Verified by: `scripts/telemetry/*`.

**Status**: Implemented.

#### `infrastructure/terraform/README.md`

States intent for future cloud IaC. **Status**: Planned but not present.

---

## 3.6 End-to-End / Cross-Service Verification

#### `scripts/telemetry/verify-cross-service.sh`, `smoke-test.sh`, `verify-collector-outage.sh`

**File responsibilities**: these three scripts are, today, the platform's only genuinely cross-service acceptance tests — they exercise the real running Compose topology rather than mocking a boundary. `verify-cross-service.sh` is explicitly the check ADR-0012's "context propagation ... verified by tests" and "negative test proves a known-sensitive value never appears" requirements point at; `verify-collector-outage.sh` proves the "a telemetry failure never fails a user-facing request" invariant against a genuinely stopped Collector, not just a mocked failure; `smoke-test.sh` is the fast day-to-day "is the Collector→Grafana pipe alive" check.

**Relationships** — Run by: `make telemetry-verify`, `make telemetry-smoke`, `make telemetry-outage`. Documented as the de facto content of `tests/end-to-end/` by that directory's own README, despite living under `scripts/` — a structural inconsistency worth knowing about rather than silently resolving.

**Status**: Implemented.

#### `infrastructure/localstack` verification — `scripts/localstack/verify.sh`

Confirms the document bucket and both SQS queues (plus redrive policy) exist and are reachable; run via `make aws-status`. **Status**: Implemented.

#### `tests/end-to-end/README.md`

States the intended future home for browser-driven, full-journey tests once a later phase defines a runner and fixtures. **Status**: Scaffold only (documentation of intent; no test runner or fixtures present).

---

# 4. Important End-to-End Flows

### Flow A — Authentication (register → verify → login)

```text
Browser (AuthForm) → Next.js apiFetch()
  → Laravel POST /api/auth/register (CanonicalizeEmail middleware → CreateNewUser action)
  → Fortify sends a signed verification email → Mailpit
  → user opens link → Laravel GET /api/auth/email/verify/{id}/{hash} (signed, throttled)
    → VerifyEmailRedirectResponse redirects to Next.js /verify-email/result?status=verified
  → Browser AuthForm login → Laravel POST /api/auth/login (Sanctum stateful session)
  → Next.js server components call platformAccess()/currentUser() on every subsequent page
```

Files involved: `AuthForm.tsx`, `api.ts`, `routes/api.php`'s `auth` group, `CanonicalizeEmail`, `CreateNewUser`, `VerifyEmailRedirectResponse`, `FortifyServiceProvider`. Validation: Fortify's password rules (`AppServiceProvider::boot()`), canonical-email uniqueness. Authorisation: Sanctum session cookie + CSRF token pair; `auth:sanctum` + `verified` middleware protects everything downstream. No OpenTelemetry span is specific to auth beyond the generic `TraceHttpRequests` HTTP span. The flow currently ends at an authenticated session with no workspace guarantee — see Flow B.

### Flow B — Workspace resolution

```text
Browser → GET /app/workspaces/{id} (Next.js server component)
  → platformAccess() [401→/login, 403→/verify-email]
  → currentUser(), userWorkspaces(), userWorkspace(id) in parallel
    → Laravel FindWorkspaceForUser (membership-joined query) → 404 if not an active member
```

Files: `app/app/workspaces/[workspacePublicId]/page.tsx`, `server-api.ts`, `WorkspaceController`, `FindWorkspaceForUser`/`ListWorkspacesForUser`. Authorisation is entirely server-side, per-request — there is no cached "current workspace" trusted client-side (ADR-0006).

### Flow C — Document Upload

```text
Browser (DocumentUploadPanel)
  → GET .../documents/uploads/configuration
  → POST .../documents/uploads  [InitializeDocumentUpload: CreateDocument + presigned PUT URL, one transaction]
  → Browser PUTs file bytes directly to S3/LocalStack (uploadToPresignedUrl)
  → POST .../documents/{id}/uploads/complete  [CompleteDocumentUpload: verify object exists+size, Uploading→Uploaded]
```

Files: `DocumentUploadPanel.tsx`, `document-upload.ts`, `DocumentUploadController`, `InitializeDocumentUpload`, `CompleteDocumentUpload`, `DocumentObjectStorage`. Validation: client-side (`validateDocumentFile`) and authoritative server-side (`InitializeDocumentUploadRequest`). The browser never talks to Laravel and S3 in the same call — the upload itself is a direct browser→S3 PUT, bypassing Laravel's request cycle entirely for the bytes.

### Flow D — Document Ingestion Request → Publish → Claim (asynchronous boundary)

```text
Browser → POST .../documents/{id}/ingestion-requests
  → RequestDocumentIngestion: lock Document, Uploaded→Queued + OutboxEvent row, ONE Postgres transaction
     (contract-validated before commit; trace context captured into the outbox row)
  ⋯ asynchronous boundary ⋯
publisher process → PublishIngestionOutbox.handle()
  → claims one unpublished OutboxEvent (SKIP LOCKED)
  → re-validates contract → SqsIngestionEventPublisher.publish() → SQS (trace context injected as message attributes)
  → marks published_at
  ⋯ asynchronous boundary ⋯
worker process → IngestionWorker.run()
  → SqsIngestionQueue.receive() (trace context extracted from message attributes)
  → contract.parse_and_validate_event()
  → IngestionClaimClient.claim() → HMAC-signed POST /api/internal/ingestion/events/{id}/claim
    → AuthenticateIngestionWorker middleware → IngestionWorkerRequestAuthenticator.verify()
    → ClaimDocumentIngestion: idempotent Queued→Processing, durable IngestionEventClaim row
  → acknowledge SQS message only after a durable claim response
```

This is the flow with the most instrumentation and the most explicit failure-mode handling in the whole codebase — see ADR-0008/0009 for the reasoning, and `PublishIngestionOutbox`/`ClaimDocumentIngestion`/`IngestionWorker` for the implementation. **The flow currently ends at `Processing`** — nothing yet calls extraction, normalisation or chunking from the worker, so no Document ever reaches `Indexed` today (see the Status note under `normalisation/structural.py` above).

### Flow E — Extraction → Normalisation → Chunking (pipeline stages, not yet orchestrated)

```text
bytes → PlainTextExtractor|PdfPlumberExtractor|PythonDocxExtractor .extract() → ExtractedDocument
      → StructuralNormaliser.normalise() → NormalisedDocument
      → BaselineStructuralChunker.chunk() → ChunkingResult (chunks with provenance)
```

Each stage is fully implemented and independently tested, but as of this commit **no code path calls this sequence from the ingestion worker** — it exists as tested, ready-to-use building blocks for the orchestration Phase 13+ (or a later session) will add. Do not assume a Document can currently reach `Indexed`.

---

# 5. Cross-Cutting Concerns

| Concern | Where implemented | Status |
|---|---|---|
| Authentication | Sanctum stateful sessions + Fortify, `apps/api/app/{Providers/FortifyServiceProvider,Http/Responses/*,Actions/Fortify/*}` | Implemented |
| Authorisation | `app/Policies/*` (membership-level), `Gate::authorize` in controllers | Implemented (coarse — membership only, no per-role gating yet) |
| Workspace tenancy | `WorkspaceMembership`, `Queries/Workspaces/*`, workspace-scoped routes | Implemented (application layer) |
| PostgreSQL Row-Level Security | — | **Planned, not present** (ADR-0006 requires it; no policy exists) |
| Validation | Form Requests (`InitializeDocumentUploadRequest`), JSON Schema (`DocumentIngestionContractValidator`/`contract.py`), Pydantic models throughout `apps/ai` | Implemented |
| Error handling | Typed, `render()`-implementing Laravel exceptions; typed `ExtractionFailure`/`ChunkingError` in Python | Implemented |
| Queues / retries | Transactional outbox (`PublishIngestionOutbox`), SQS + DLQ redrive (`infrastructure/localstack`), worker retry/poison disposition (`claim_client.py`) | Implemented |
| Storage abstraction | `DocumentObjectStorage` (dual S3 disk split for internal vs. browser-presigned access) | Implemented |
| Extraction pipeline | `apps/ai/app/extraction/*` | Implemented as a stage; not yet orchestrated end-to-end |
| Normalisation | `apps/ai/app/normalisation/structural.py` | Implemented as a stage; not yet orchestrated end-to-end |
| Chunking | `apps/ai/app/chunking/*` | Implemented as a stage; not yet orchestrated end-to-end |
| Telemetry / OpenTelemetry | `apps/api/app/Telemetry/*`, `apps/ai/app/telemetry.py`, `infrastructure/opentelemetry/collector.yaml` | Implemented |
| Configuration | `apps/api/config/*`, `apps/ai/app/settings.py`, `.env.example` files | Implemented |
| Testing | PHPUnit (`apps/api/tests`), Pytest (`apps/ai/tests`), Vitest (`apps/web/src/**/*.test.*`), shell-script cross-service checks (`scripts/telemetry/*`) | Implemented per-service; genuine end-to-end browser tests are scaffold-only |
| Security boundaries | Laravel is the sole authority for lifecycle state; Python never writes to Laravel tables directly; HMAC-authenticated internal boundary (ADR-0009); cross-tenant access fails closed with 404 | Implemented |

---

# 6. Recommended Reading Order

1. **`docs/adr/0001` through `0012`, in order** — each ADR explicitly builds on the last; reading them in sequence tracks how the architecture actually accreted, rather than presenting it as if it existed all at once.
2. **`PROJECT_ROADMAP.md`** — orients you on what phase you're in and what's deliberately not built yet.
3. **`IMPLEMENTATION_GUIDE.md`** — the durable build log; useful once you want to know *how* a specific stage was actually implemented and verified, not just *why*.
4. **`compose.yaml` and `makefile`** — the fastest way to see every running service and every command available, before reading any application code.
5. **`apps/api/routes/api.php`** — the entire public+internal HTTP surface in one file; a map of every entry point before you read a single controller.
6. **`apps/api/app/Http/Controllers/*` → `app/Actions/*` → `app/Models/*`** — follow one request (start with `DocumentUploadController` → `InitializeDocumentUpload` → `Document`) top-to-bottom through the layered convention; this is the fastest way to internalise the Controller→Action→Model pattern used everywhere else.
7. **`app/Actions/Ingestion/*` + `app/Services/Ingestion/*` alongside ADR-0008 and ADR-0009** — the outbox and HMAC-claim mechanics are the most architecturally dense part of the API; read the ADRs and the code side by side.
8. **`contracts/events/document-ingestion-requested/`** — the schema itself, then both `DocumentIngestionContractValidator.php` and `contract.py` side by side, to see the same contract enforced twice.
9. **`apps/ai/app/ingestion/*`** — the Python mirror of step 7; read `worker.py` (the entrypoint) first, then trace into `sqs.py`, `contract.py`, `claim_client.py`, `signing.py`.
10. **`apps/ai/app/extraction/models.py`, then one extractor (`plain_text.py` is the simplest)**, then **`normalisation/structural.py`**, then **`chunking/baseline.py`** — read ADR-0010 and ADR-0011 immediately before this block; the code is dense without that context.
11. **`apps/web/src/lib/server-api.ts` and one page (`app/app/workspaces/[workspacePublicId]/page.tsx`)** — see how the frontend re-derives authorisation on every request rather than trusting client state.
12. **Tests last, but not skipped** — `DocumentIngestionClaimTest.php`, `test_ingestion_worker.py`, and `test_chunking_evaluation.py` in particular encode edge-case behaviour (stale events, poison payloads, completeness guarantees) that isn't obvious from the production code alone.

---

# 7. Glossary

**ADR** — Architecture Decision Record; a numbered, immutable document in `docs/adr/` recording a durable architectural decision, its alternatives, and its consequences.

**Chunk / Chunking** — splitting a `NormalisedDocument` into retrieval-sized units (`Chunk`s) that a future embedding stage will vectorise. Governed by ADR-0011; implemented by `BaselineStructuralChunker`.

**ChunkingResult** — the immutable output of one chunking run: the produced chunks, the strategy identity/version, and a configuration snapshot + fingerprint, kept separate from operational/execution telemetry.

**Claim (ingestion claim)** — the Python worker's request to Laravel to durably record that it has taken ownership of processing a Document, transitioning it `Queued → Processing`. Idempotent and authenticated via HMAC (ADR-0009).

**Contract (event/HTTP contract)** — a versioned, language-neutral definition (JSON Schema for events; intended OpenAPI for HTTP) under `contracts/`, validated identically by every producer and consumer rather than inferred from one side's implementation.

**Correlation ID** — a stable identifier threading one causal chain (HTTP request → outbox record → SQS event → downstream logs), distinct from an OpenTelemetry trace ID (see ADR-0012).

**Defence in depth** — ADR-0006's tenancy strategy of stacking multiple independent isolation layers (routes, membership, policies, scoped queries, RLS, constraints) so no single layer's failure alone causes a cross-tenant leak.

**Document lifecycle** — the state machine `UPLOADING → UPLOADED → QUEUED → PROCESSING → INDEXED`, with `FAILED` and `DELETING → DELETED` branches, defined in ADR-0007 and encoded as `DocumentStatus`.

**ExtractedDocument / Element** — the canonical, immutable, format-agnostic output of any extractor (plain text, PDF, DOCX), defined in ADR-0010. Extraction preserves as much semantic structure as practical; later stages, not extraction, decide what to discard.

**Extraction confidence** — an optional per-element float (0–1) an extractor may populate, reserved for future quality-control use; not currently consumed by anything.

**HMAC worker authentication** — the narrow, single-purpose signed-request scheme (ADR-0009) authenticating only the Python worker's internal Laravel claim requests; not a general auth framework.

**Idempotency (ingestion)** — the guarantee that redelivering or republishing the same logical `event_id` never performs a lifecycle transition twice; enforced via the durable `IngestionEventClaim` row, not SQS's transport-level message ID.

**NormalisedDocument / NormalisedElement** — the deterministic, structurally-cleaned-up representation produced from an `ExtractedDocument` by the `StructuralNormaliser`; self-describing (carries its own `workspace_id`/`document_id`), which is why chunking needs no context wrapper (ADR-0011).

**Outbox (transactional outbox)** — the ADR-0008 pattern where Laravel durably records "an event must be published" in the same PostgreSQL transaction as the Document's lifecycle transition, decoupling durable intent from eventual, best-effort SQS delivery.

**OTLP** — OpenTelemetry Protocol; the wire format both Laravel and Python export traces/metrics in, over HTTP/protobuf, to the Collector.

**RAG** — Retrieval-Augmented Generation; the platform's overall product shape (not yet implemented past chunking).

**RLS (Row-Level Security)** — a PostgreSQL feature ADR-0006 designates as a required defence-in-depth tenancy layer; **not yet implemented** in this codebase.

**Trace context propagation** — passing OpenTelemetry `traceparent`/`tracestate` across every hop (HTTP → outbox row → SQS message attributes → Python worker → internal claim request) so one logical operation remains one correlated trace end to end (ADR-0012); verified live by `scripts/telemetry/verify-cross-service.sh`.

**Workspace** — the platform's tenancy, collaboration and data-isolation boundary (ADR-0006); not synonymous with a user, and not currently self-service to create.
