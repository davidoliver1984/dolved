# ADR 0009: Use HMAC Authentication for AI Worker Lifecycle Requests

## Status

Accepted

## Date

2026-07-29

## Context

ADR 0007 makes Laravel and PostgreSQL authoritative for Document lifecycle
state. ADR 0008 therefore requires the Python ingestion worker to request the
`QUEUED → PROCESSING` transition through an authenticated internal application
boundary rather than updating Laravel-owned tables directly.

Stage 9.3 needs one narrowly scoped machine-to-machine identity. The Python
ingestion worker must authenticate its lifecycle-claim request to Laravel,
protect the request body and routing identifiers from alteration, tolerate
normal secret rotation and reject requests captured outside a short freshness
window.

Browser authentication from ADR 0005 is not appropriate for this boundary.
The worker is not a user, must not own a browser session and must not be given a
user's Sanctum credentials. The platform also does not yet need a general
service-identity or OAuth infrastructure.

Authentication alone does not solve at-least-once delivery. SQS and the
transactional outbox may deliver the same logical event repeatedly. Laravel
must durably recognise the stable `event_id` and make the lifecycle transition
idempotent across worker restarts.

## Decision

### Scope

Use HMAC-SHA256 authentication only for the internal endpoint through which the
Python ingestion worker requests the Document processing claim.

This ADR does not establish a general internal authentication framework and
does not change browser authentication, public API authentication or
authorisation. The only accepted principal is the dedicated ingestion-worker
identity.

The endpoint is internal application API surface. It must not use Sanctum
sessions or CSRF authentication, must not be presented as a user-facing route
and must remain protected by private networking and HTTPS in production.

### Keys and rotation

Laravel holds a key ring supplied through environment-backed secrets
configuration. Every accepted key has:

- a non-secret Key ID;
- a strictly Base64-encoded secret that decodes to at least 32 random bytes;
  and
- the dedicated `ingestion-worker` identity.

The worker selects one active Key ID and secret for signing. Laravel may accept
multiple enabled Key IDs concurrently so a new secret can be deployed before
the old one is retired. Unknown, malformed or disabled Key IDs fail closed.

Secrets must not be stored in source control, fixtures, logs or error
responses. Local examples use an explicitly non-production development
secret. Production secrets are supplied by the deployment platform's secret
management facility.

### Request headers

The worker sends:

```text
X-Ingestion-Worker-Key-ID
X-Ingestion-Worker-Timestamp
X-Ingestion-Worker-Event-ID
X-Ingestion-Worker-Signature
```

`X-Ingestion-Worker-Timestamp` is an unsigned base-10 Unix timestamp in whole
seconds. `X-Ingestion-Worker-Event-ID` is the canonical lowercase UUID of the
logical ingestion event and must equal the `event_id` in both the request path
and JSON body.

The signature header has this form:

```text
v1=<lowercase hexadecimal HMAC-SHA256 digest>
```

### Canonical string-to-sign

The version 1 string-to-sign consists of exactly five UTF-8 fields joined by
one line-feed byte (`\n`) with no trailing line feed:

```text
<timestamp>\n<method>\n<request-path>\n<body-sha256>\n<event-id>
```

The fields are canonicalised as follows:

1. `timestamp` is the exact validated base-10 Unix-seconds header value with no
   whitespace or sign.
2. `method` is the uppercase HTTP method. Stage 9.3 uses `POST`.
3. `request-path` is the absolute, percent-encoded path beginning with `/`,
   exactly as sent, excluding scheme, authority, fragment and query string.
   The endpoint does not accept a query string.
4. `body-sha256` is the lowercase hexadecimal SHA-256 digest of the exact
   request-body bytes sent on the wire. JSON must not be parsed and
   re-serialised before calculating or verifying this digest.
5. `event-id` is the canonical lowercase UUID from
   `X-Ingestion-Worker-Event-ID`.

The signature is the lowercase hexadecimal HMAC-SHA256 digest of that
string-to-sign using the strictly decoded secret bytes selected by the Key ID.
Invalid Base64 or a decoded key shorter than 32 bytes fails closed. Laravel
compares the provided and calculated signatures using a constant-time
comparison.

Binding the method, path, exact body and event identifier prevents a valid
signature from being moved to another endpoint, body or logical event.

The following non-secret protocol test vector is normative for cross-language
signing tests. Its `{}` body deliberately tests signing only and is not a valid
claim payload:

```text
secret (Base64):
MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=

timestamp:
1785326400

method:
POST

request path:
/api/internal/ingestion/events/5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41/claim

exact body:
{}

body SHA-256:
44136fa355b3678a1146ad16f7e8649e94fb4fc21fe77e8310c060f61caaff8a

event ID:
5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41

expected signature:
v1=4b54632a0c852c07c654ef3f4f658fba1759fefe0fa8d5cc3c531b1f83b43da9
```

### Freshness and replay behaviour

Laravel accepts a configurable clock skew of five minutes initially. A request
whose timestamp lies outside that window is rejected before the lifecycle
action runs. Production hosts must maintain synchronised clocks.

The timestamp bounds how long a captured request remains authenticatable. An
exact replay inside the allowed window is rendered harmless by the durable
logical-event idempotency rule below; it must not perform another lifecycle
transition. This avoids adding a separate nonce store whose only purpose would
duplicate the event ledger.

### Authoritative and idempotent lifecycle claim

Laravel validates the authenticated request and the canonical event contract.
The Python worker never writes to Laravel's database.

Laravel durably records the logical `event_id` and performs
`QUEUED → PROCESSING` in one PostgreSQL transaction while holding a row lock on
the Document:

- when no claim exists and the Document is `QUEUED`, Laravel records the event
  claim and transitions the Document to `PROCESSING`;
- when the same `event_id` was already recorded for the same Workspace and
  Document, Laravel returns the prior successful claim idempotently;
- when an existing `event_id` is presented with different tenant or Document
  identity, Laravel rejects the request;
- when the Document has advanced without a matching claim, Laravel reports a
  stale or ineligible event without moving the lifecycle backwards.

The event record, not the SQS transport message identifier, is the durable
idempotency key. A future deliberate retry uses a new logical event ID and must
be governed by an explicitly accepted lifecycle transition.

The worker acknowledges the SQS message only after Laravel confirms a durable
new or previously completed claim. Authentication failure, transient
unavailability and contract failure do not grant the worker permission to
infer or mutate lifecycle state.

### Network and logging requirements

Local Docker Compose traffic uses the private service network. Production
traffic uses HTTPS over private networking and should restrict reachability to
the worker and API workloads.

Logs may contain the Key ID, event ID, correlation ID and verification outcome.
They must never contain the HMAC secret, signature, exact signed body,
credentials or document content. Authentication failures return a generic
response rather than revealing which verification step failed.

## Alternatives considered

### A static bearer token

Rejected. It authenticates possession of a secret but does not bind the HTTP
method, path, body or event identity, and a captured token remains reusable
until rotated.

### Sanctum personal access tokens or browser sessions

Rejected. The ingestion worker is a machine principal, not a user. Reusing
browser-oriented identity would blur Laravel's user and service boundaries.

### OAuth 2.0 client credentials or a general service-identity platform

Deferred. This would provide standard token issuance and broader service
identity, but adds an authorisation server, token lifecycle and operational
surface disproportionate to one internal worker endpoint.

### Mutual TLS

Deferred. It provides strong workload identity but requires certificate
issuance, rotation and proxy/load-balancer integration not otherwise needed
at this stage.

### AWS IAM/SigV4

Rejected for this boundary. It would couple application authentication to AWS
and make the canonical local LocalStack/Docker workflow materially different
from production.

### Direct Python access to Laravel's PostgreSQL tables

Rejected. It violates ADR 0002, ADR 0007 and ADR 0008 by bypassing Laravel's
domain validation and lifecycle authority.

### In-memory worker idempotency

Rejected. It is lost on restart and cannot coordinate duplicate deliveries
across worker instances.

## Consequences

### Positive

- Laravel remains the only authority that can change Document lifecycle state.
- Requests are authenticated and bound to their exact method, path, body and
  logical event.
- Key IDs permit overlap during zero-downtime secret rotation.
- A short timestamp window limits replay exposure.
- Durable event claims make exact replays and duplicate SQS delivery
  idempotent across worker restarts.
- The protocol behaves the same in Docker Compose and AWS.
- The design introduces only the machinery required for the ingestion worker.

### Negative

- Both services must implement the canonicalisation rules identically.
- Hosts require reasonably synchronised clocks.
- Shared secrets require secure distribution, monitoring and rotation.
- HMAC proves possession of a shared secret; it does not provide independently
  verifiable asymmetric identity.
- An exact request may be replayed within the freshness window, although the
  durable event claim prevents another transition.
- If more internal principals or permission scopes appear, this narrow
  protocol should be replaced or superseded rather than expanded into an
  improvised general identity system.
