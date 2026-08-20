# ADR 0026: Operationalise Platform Observability and Incident Response

## Status

Accepted

## Date

2026-08-20

## Relationship to prior ADRs

### Consumes ADR-0012's OpenTelemetry foundation unchanged; this document operationalises it, it does not rebuild or replace it

ADR-0012 already establishes, as settled architecture this document does not
reopen: OpenTelemetry as *"the platform's one, canonical instrumentation API
and vendor-neutral boundary"*; the Collector as the sole routing/batching/
filtering/sampling/export boundary (*"Application code depends on the
OpenTelemetry SDK and never on any specific backend's exporter configuration
or credentials"*); an allowlist-first privacy posture (*"an explicit
**allowlist** of safe attributes, not a denylist of known-sensitive ones"*);
a metric-cardinality discipline (*"No metric carries an unbounded-cardinality
label"*); the deliberate non-unification of the durable, contract-level
`correlation_id` with the OpenTelemetry trace ID (*"the two serve different
layers with different lifecycles"*); and the invariant that *"a telemetry or
instrumentation failure never causes a user-facing request to fail."*
Verified directly against `apps/api/app/Telemetry/TelemetrySdkFactory.php`
and `apps/ai/app/telemetry.py`: this is real, working code today, not
aspirational text — both fall back to no-op providers on any setup failure,
confirming the invariant is actually implemented, not just documented.

ADR-0012 also explicitly, by name, declined to decide the exact territory
this document now covers (*"Scope boundaries"*): *"dashboards, alerting
thresholds, or on-call procedures — later operational concerns already named
elsewhere in the roadmap"*; *"which specific observability backend(s) the
Collector exports to — a Collector-configuration decision, not an
application-architecture decision"*; and *"sampling strategy or telemetry
retention policy."* This document is exactly that later operational pass —
it inherits ADR-0012's emission-side architecture unchanged and defines the
consumption side: dashboards, alerting, sampling, retention, and incident
response. Where this document names a concrete local backend (the bundled
`grafana/otel-lgtm` image), that is cited as verified implementation fact
from `compose.yaml`, not attributed to ADR-0012, which deliberately never
named a backend.

**A prior revision of this document contradicted the ownership boundary
quoted above and is corrected here.** That revision selected an SDK-side
`ParentBased(TraceIdRatioBased)` sampler configured inside Laravel and
Python, which would have made application code, not the Collector, the
component actually deciding which traces are retained — directly against
ADR-0012's explicit *"The Collector — not application code — owns
routing, batching, filtering, **sampling** and backend-specific export."*
That selection is withdrawn. **This document does not supersede ADR-0012's
ownership boundary; it exercises the one specific decision ADR-0012
explicitly left open under that same, unchanged ownership — *which*
sampling strategy the Collector applies, and at what baseline rate** — see
"Sampling strategy" below for the corrected, Collector-owned design, and
for the concrete verification that the pinned Collector distribution
already contains the component this requires.

### Narrows and completes ADR-0025's explicit Phase 20 reservation; does not reopen its administration or audit model

ADR-0025 repeatedly and consistently reserves exactly this document's
territory to "Phase 20," most directly in its own "Audit and observability
boundary" section: *"Business audit is not operational logging or tracing.
**Phase 20 remains responsible for queue health, service latency, worker
throughput, trace visualisation, infrastructure alerting, and service-level
metrics — Phase 19's usage visibility must not rebuild any of that.**"* This
document is that reservation being fulfilled. It does not reopen
ADR-0025's `OWNER`/`ADMIN`/`MEMBER` workspace-scoped capability model, its
content-free historical activity records, or its business-audit design —
those remain exactly as ADR-0025 defined them, workspace-scoped, tenant-
facing, and explicitly not telemetry. This document instead introduces a
**platform administrator** concept ADR-0025 never defined and explicitly
never needed to: ADR-0025's roles are uniformly workspace-scoped, and its
own text repeatedly forbids cross-workspace visibility even for a workspace
`OWNER` (*"no cross-workspace cache keys, ever"*; *"Usage figures must never
allow one tenant to infer another tenant's activity"*). Verified directly
against the current codebase (`apps/api/app/Models/User.php`,
`apps/api/database/migrations`, and an exhaustive grep for
`platform_admin`/`Gate::define`/`super_admin`): **no platform-wide
administrator concept exists anywhere in this codebase today.** Introducing
one is new, first-instance architecture this document is responsible for
defining minimally, not something it can assume or reuse from ADR-0025.

### Consumes ADR-0006's tenancy invariants and its unresolved "platform-admin controlled" reference

ADR-0006 fixes workspace roles (`owner`/`admin`/`member`) and states that
*"Workspace provisioning is currently platform-admin controlled... An
authorised platform process creates the workspace"* — but never formalises
this into a role, table, or authorization check; it remains, in ADR-0006's
own words, only *"an authorised platform process."* ADR-0006 also
establishes the three-audit-layers framing (business / Search-RAG /
database audit) this document does not redefine, and the cross-tenant
`404`-not-`403` concealment discipline this document extends to platform-
operational surfaces. This document is the first to formalise "platform
administrator" as an actual, checkable authorization concept — narrowly, as
required below — rather than leaving it as an undefined process.

### Consumes ADR-0008/ADR-0009's correlation-ID and signed-logging discipline; extends, does not redefine, both

ADR-0008 requires outbox records to carry *"a correlation identifier
connecting the originating request, the outbox record, the published event
and downstream logs"* and already frames outbox records as *"inherently an
audit and observability surface (unpublished count, oldest unpublished age,
attempt and failure history)"* — this document's queue-backlog/oldest-
message-age metrics are a direct continuation of that framing, not a new
idea. ADR-0009's exact logging rule — *"Logs may contain the Key ID, event
ID, correlation ID and verification outcome. They must never contain the
HMAC secret, signature, exact signed body, credentials or document
content"* — is the rule ADR-0012 itself cites as the origin of its own
allowlist posture; this document inherits it a second time, unchanged,
rather than restating a third, independently-derived version of the same
policy.

### Consumes ADR-0023/ADR-0024's outcome and failure taxonomies as the authoritative source for SLI numerator/denominator boundaries

This document does not invent a new definition of "success" or "failure"
for retrieval and generation — it reuses ADR-0023's and ADR-0024's already-
accepted taxonomies exactly, because they already draw precisely the
distinction an SLO needs. ADR-0023: `ANSWERED`/`QUALIFIED`/
`INSUFFICIENT_EVIDENCE` are *"three first-class business outcomes, all
ordinary successful `GenerationResult`s, never provider errors."*
`GENERATION_CONTEXT_BUDGET_EXCEEDED` is explicitly *"not `INSUFFICIENT_
EVIDENCE`, and must never be treated as though it were"* — a third,
structural category, neither a business outcome nor a provider failure.
ADR-0024 extends this to conversation/streaming with the same discipline
applied to `GenerationRun` terminal states (`completed`, `retrieval_
no_answer`, `clarification_required`, `failed`, `cancelled`): *"Business
answer outcomes and execution failures are never collapsed... `RETRIEVAL_
NO_ANSWER` is a legitimate, controlled terminal state, not a failure."*
`CANCELLED` is deliberately excluded from the `failure_code` enum: *"`CANCELLED`
is exclusively a lifecycle terminal state... duplicating it as a failure
code would blur 'the user stopped this' with 'something went wrong.'"*
Section "SLOs and error budgets" below applies this taxonomy directly,
verbatim, to numerator/denominator selection — this document's only
addition is which query counts where, not a new definition of what counts.
ADR-0024's existing "Observability and privacy" section (*"Inherits
ADR-0012 unchanged... per-stage timings (queued, first progress,
first-part-accepted-for-display, completed)"*) is likewise reused directly
— `first-part-accepted-for-display` is the existing named field this
document's "time to first accepted part" metric is built on, not a new
concept.

## Context

Phase 12 (ADR-0012) built the observability *foundation*: the instrumentation
API, the Collector boundary, the privacy allowlist mechanism, and the
correctness-isolation invariant. Verified directly against the current
codebase — not the old Phase 20 guide stub, which predates ADR-0012 and
which `PROJECT_ROADMAP.md` itself already flags as stale (*"Stage 20.2 and
Stage 20.3 in particular predate Phase 12's OpenTelemetry foundation
(ADR-0012) and are expected to be rescoped before this phase starts"*) —
that foundation is real, working code, but its coverage stopped growing
after the phases that were current when it was built:

- **Solidly instrumented**: HTTP requests (both Laravel and Python), Laravel
  database queries, the outbox→SQS→ingestion-worker path, and query/ingest-
  time embedding calls. Traces, metrics, and (for the ingestion worker
  process only) structured JSON logs all exist here.
- **Traced but not metered**: retrieval planning, dense/sparse vector
  search, and RRF fusion have spans but no purpose-built counters or
  histograms.
- **No telemetry of any kind — no spans, no metrics, no logs**: the entire
  generation path (the OpenAI adapter call itself), the entire conversation/
  contextualisation path, the reranking provider call, `ExecuteGenerationRun`
  queued-job execution, ingestion retry, document deletion/quiescence,
  invitation delivery, and every Phase 19 platform-admin endpoint beyond
  the generic HTTP middleware. The `gen_ai.*` attribute keys ADR-0012's
  successor allowlist already reserves for generation have never actually
  been populated by any code path.
- **A structural propagation gap, not just a coverage gap**: Laravel never
  injects a `traceparent` header into any of its synchronous rc1 calls to
  Python (retrieval, generation, contextualisation) — verified by reading
  `RetrievalCallSigner`, `GenerationClient`, and `ContextualisationClient`
  in full; none inject trace context, only HMAC signing headers. Python's
  FastAPI service is architecturally ready to *continue* an inbound trace
  (it calls `extract()` on every request) but never receives one to
  continue — every retrieval/generation/conversation call today starts a
  brand-new, disconnected root trace on the Python side. This is the
  single most consequential trace-coverage finding in this document.
- **Allowlist drift, not just missing coverage**: attributes the retrieval/
  reranking code already sets on spans today — `rag.retrieval.fusion.*`,
  `rag.retrieval.reranker.*`, `rag.retrieval.sparse_candidate_count` — are
  silently stripped before export because `TRACE_ATTRIBUTE_ALLOWLIST` was
  never updated past its Phase-12/13 contents. The mechanism works exactly
  as designed; it just hasn't been kept current.
- **No sampling configured anywhere.** The Collector config
  (`infrastructure/opentelemetry/collector.yaml`) has exactly one processor,
  `batch`; no SDK sets an explicit sampler. Every span and metric point
  created today is exported and retained at whatever the bundled backend's
  own defaults are. There is no Prometheus-native scrape endpoint anywhere
  in this codebase — metrics reach the backend exclusively via OTLP push
  into the bundled `grafana/otel-lgtm:0.29.2` image, which contains its own
  internal Prometheus, Tempo, and Loki, reachable only from other
  containers on the compose network; only Grafana's UI port
  (`127.0.0.1:3001`) is published to the host, and that Grafana instance is
  currently configured for **anonymous Admin access** (`GF_AUTH_ANONYMOUS_
  ENABLED: "true"`, `GF_AUTH_ANONYMOUS_ORG_ROLE: Admin`, login form
  disabled) — safe only because it is loopback-bound today, and named here
  explicitly as a configuration this document requires be corrected before
  any non-loopback exposure, not something Phase 22 can silently inherit.
- **No platform administrator concept exists.** Every administrative
  surface in this codebase (ADR-0025) is workspace-scoped. There is no
  `platform_admin` column, no cross-workspace `Gate::define`, no superuser
  check anywhere. A curated, cross-tenant operational dashboard needs an
  authorization concept that does not yet exist.

**The governing principle**, stated once and applied throughout: *Dolved
exposes a curated operational picture for routine platform supervision,
while private specialist tooling provides deep logs and traces; both
consume the existing OpenTelemetry foundation rather than creating a second
observability system.*

```text
Applications (unchanged, ADR-0012)
    → OpenTelemetry SDK → Collector → otel-lgtm (Prometheus/Tempo/Loki/Grafana)

Dolved platform-admin dashboard              Private ops console (Grafana)
    → curated, bounded, read-only               → arbitrary exploration,
      operational-metrics adapter                 log search, trace
    → "is the platform healthy, and                waterfalls
      which area needs attention?"               → "exactly where and why
                                                      did this fail?"
```

This document does not introduce: a proprietary telemetry SDK wrapper; a
second trace/context system; tenant identifiers as metric labels;
application correctness depending on telemetry availability; a bespoke
replacement for Grafana/Prometheus/Tempo/Loki; raw logs or traces copied
into Laravel's relational database; operational telemetry inside ordinary
workspace administration; a public Grafana deployment; or browser-supplied
PromQL/arbitrary backend queries.

## What this ADR decides and does not decide

This ADR defines: the four-signal-type distinction (business audit / logs /
metrics / traces) and their separate authority/lifecycle; the rescoped
R20-S01–S04 session allocation; the structured-logging field vocabulary and
privacy boundary; a new, minimal platform-administrator authorization
concept and its main operational dashboard; the operational-metrics reader
adapter boundary between Laravel and the metrics backend; the explicit
separation between global operational metrics and ADR-0025's tenant usage;
the private Grafana operations console's access model; the sampling and
retention desired-state/reconciliation model exposed from platform admin;
the honest sampling strategy and its actual guarantees; the metrics/trace
coverage matrix and priority gaps; provisional SLIs/SLOs and their
numerator/denominator rules reusing ADR-0023/0024's taxonomy; the alert
family/runbook model and severity scheme; the main-admin alert/SLO
presentation boundary; telemetry-failure behaviour; and explicit
deferrals.

It does not decide: exact Laravel controller/action/migration class names
beyond what's structurally required to state the decision; exact Grafana
dashboard panel layouts; exact PromQL/LogQL query text; final production
latency SLO thresholds (calibration is required first); a commercial
observability vendor; production network topology, identity-aware proxy
implementation, or paging-vendor selection (Phase 22); a granular
operations permission engine beyond the three-tier separation named and
deferred below; and billing/invoicing. It does not redecide anything
ADR-0006, ADR-0008, ADR-0009, ADR-0012, ADR-0023, ADR-0024, or ADR-0025
already settled.

## Decision

### Four signal types, kept structurally distinct

| Signal | Purpose | Authority/lifecycle |
|---|---|---|
| Business audit | Who performed a sensitive domain action? | Durable, application-owned records (ADR-0006/ADR-0025); unaffected by telemetry retention |
| Logs | What precisely happened? | Searchable diagnostic output; bounded retention (this document) |
| Metrics | Is platform behaviour becoming unhealthy? | Aggregated, low-cardinality time series; bounded retention |
| Traces | Where did a request/run spend time or fail? | Sampled cross-service diagnostic journeys; bounded retention |

Business-audit records (ADR-0006/ADR-0025) are not telemetry and do not
inherit this document's log/trace/metric retention targets — they remain
durable application records with their own lifecycle, unaffected by
whatever this document sets for observability data. ADR-0025's tenant
usage records are likewise not a substitute for operational metrics, and
this document's operational metrics are not a substitute for them — see
"Global operational metrics versus tenant usage" below for the precise
boundary. Logs are never used as metrics (no log-derived counter becomes
an SLI without an explicit, named metric instrument); metrics are never
used as business audit (no metric answers "who did this," only "how often
did this class of thing happen"); traces are never used as durable
application lineage (a `GenerationRun`'s authoritative history lives in
Postgres per ADR-0024, not in a sampled trace that may not have been
retained).

### Rescoped R20 session allocation

**Corrected from the first draft's allocation, which put platform-
administrator authorization inside R20-S02 alongside metrics and dashboards
without making explicit that S02 must establish that authority *before*
S03's policy controls can depend on it.** The allocation below states this
sequencing directly, and moves Collector-owned sampling and reconciliation
into S03, alongside the trace-coverage work that architecturally
determines it (the Collector cannot make a consistent sampling decision
until the trace-propagation gaps S03 also fixes are closed).

**R20-S01 — Standardise Structured Logging.** Unchanged: Laravel/Python/
Next.js server-side structured logging; the shared field vocabulary;
the privacy/redaction boundary, including safe exception/stack-frame
handling; negative privacy tests covering both ordinary fields and
exception handling. No platform-administrator or policy-reconciliation
schema is introduced in S01.

**R20-S02 — Operational Metrics, Platform Operations Foundation and
Dashboard.** Rescoped and renamed from "Operational Metrics, SLIs and
Dashboards" to make explicit that this session must establish platform-
level authority before S03 can depend on it. Implemented, in this order:

1. `users.public_id` and `users.disabled_at` (schema, with existing-row
   backfill for `public_id`);
2. `users.platform_role` (nullable, `ADMINISTRATOR` only in V1);
3. the platform-administrator Gate/policy layer, checked live per request;
4. the dedicated platform-administration deployment credential (key
   identifier/secret handling, rotation/versioning);
5. the non-browser bootstrap/grant/revoke/recovery command;
6. `PlatformAdministrationCommand` and `PlatformAdministrationAuditEvent`,
   committed atomically with every role mutation;
7. last-active-administrator protection;
8. session invalidation on disable/delete;
9. the operational-metrics coverage audit-and-complete pass (HTTP/DB/
   ingestion already solid; retrieval needs counters added alongside its
   existing spans; generation, conversation, deletion, and reranking need
   first-instance metrics);
10. the bounded operational-metrics reader adapter;
11. the curated main platform-admin health dashboard;
12. tenancy and platform-authorization negative tests.

**This session is a prerequisite, not a peer, of R20-S03** — S03's
Collector-sampling and policy-reconciliation surfaces are gated behind the
platform-administrator authority S02 establishes, so S02 must be able to
complete and be verified independently before S03 begins consuming it.

**R20-S03 — Trace Coverage, Collector Sampling and Operational-Policy
Reconciliation.** Rescoped and renamed from "Complete Trace Coverage and
Production Diagnostics" to make explicit that Collector-owned sampling and
its reconciliation protocol are this session's work, not S02's. The
OpenTelemetry/W3C-trace-context technology choice was already made by
ADR-0012 and is not reopened. This session's work:

- fixing the Laravel-to-Python rc1 trace-context propagation gap (the
  highest-priority item in this document, and a prerequisite for the
  Collector's sampling decision to be trace-consistent at all);
- fixing the retry/deletion outbox trace-context gaps;
- extending the trace-attribute allowlist to stop silently dropping
  already-emitted fusion/reranking attributes;
- adding first-instance spans to generation, conversation, reranking's
  provider call, `ExecuteGenerationRun`, and deletion;
- adding the Collector's `probabilisticsamplerprocessor` configuration,
  against the verified-present pinned distribution component, plus its
  own configuration/component-presence validation test;
- desired sampling/retention policy persistence and its required-target
  manifests;
- reconciliation plans, deployment attempts, and acknowledgements (the
  full append-only attempt model, compare-and-set current-attempt
  semantics, and per-setting/per-target state defined above);
- the dedicated observability-policy reconciliation HMAC credential and
  protocol family;
- the sampling/retention status UI and API surface in platform admin;
- local/test proof that reconciliation actually reaches `ACTIVE` end to
  end against the real pinned Collector image.

**R20-S04 — Define SLOs, Alerts and Runbooks.** Unchanged: SLIs and
provisional SLOs; alert families; the Alertmanager-compatible receiver
seam; runbooks; the main-admin alert/SLO presentation surface; Grafana
deep links; no custom Dolved-side alert acknowledgement/silencing system.

This allocation reflects the brief's requested restructuring; the
governing constraint — S02 must be independently completable before S03
consumes the platform-administrator authority it establishes — is now
stated directly rather than left implicit in session ordering alone.

### Structured logging boundary

Architectural direction, unchanged from the brief and consistent with what
already exists for the Python worker process: structured JSON emitted to
stdout/stderr by every server-side component; deployment/runtime
infrastructure (not application code) owns shipping and retention;
application code never imports a Loki-specific or other backend-specific
logging SDK; logs correlate with traces and durable business operations
via the fields below; browser console output is not centrally collected by
default.

**Verified current state, not assumed:** Python's `JsonFormatter`
(`apps/ai/app/structured_logging.py`) already implements exactly this
shape but is wired only into the ingestion/deletion worker process
(`python -m app.worker`) — the FastAPI HTTP service (retrieval/generation/
conversation) never calls `configure_structured_logging()` and emits
Python's default, non-JSON log format. Laravel has no JSON formatter or
custom Monolog processor at all (`config/logging.php` is the stock
skeleton; the `stderr` channel used in `compose.yaml` uses Monolog's plain-
text `LineFormatter`). Next.js has zero server-side logging of any kind
(no `console.*` calls, no logging library, no `instrumentation.ts`) and
zero browser-side error tracking. **R20-S01's actual work is therefore:
extend Python's already-proven `JsonFormatter` to the FastAPI process; add
an equivalent JSON formatter to Laravel; add minimal structured server-side
logging to Next.js's server components/route handlers** — not designing a
new format from nothing, since one already exists and works.

**Common field vocabulary** — critiqued against genuine safety/utility, not
copied verbatim from the brief's candidate list:

| Field | Include? | Why |
|---|---|---|
| timestamp | Yes | Required for any correlation |
| severity/level | Yes | Required for filtering |
| service name | Yes | Required to attribute the log line |
| deployment environment | Yes | Required to avoid conflating local/staging/prod |
| event name | Yes | Stable, versioned identifier — see below |
| trace ID | Yes, where available | Correlates with traces; may be absent (e.g. background work with no active span) |
| span ID | Yes, where available | Same caveat |
| durable correlation ID | Yes, where available (ADR-0008) | Survives even if trace export failed |
| request ID | Yes, where distinct from correlation ID | HTTP-layer replay-protection identifier (ADR-0018's `request_id`), not a duplicate of correlation ID |
| operation kind | Yes | e.g. `retry`, `deletion.claim`, `generation.answer` |
| bounded outcome/failure class | Yes | The typed enum values already defined by ADR-0023/0024, never free text |
| duration | Yes | Numeric, explicit unit |
| safe workspace ID | Yes | Already allowlisted (ADR-0012: *"Identifiers are not payload"*) |
| safe document/conversation/run ID | Yes | Same reasoning — identifies the subject without revealing its content |
| exception type | Yes | The class/type name only |

**Explicitly not logged**, matching the brief's list exactly, because each
item is exactly the kind of "unanticipated sensitive field" ADR-0012's
allowlist-not-denylist posture exists to exclude by default: document
text; chunk text; filenames where they may contain sensitive data; prompts;
user questions; generated answers; retrieved evidence; provider request/
response bodies; credentials; cookies or authorization headers; HMAC
signatures or signed bodies; invitation tokens; raw personal email
addresses; arbitrary exception messages capable of carrying source/
provider content.

**Stack-trace and exception handling, corrected from the first draft's
"full stack traces are permitted" claim — a fail-closed rule, not a
permissive one.** In ordinary Python, PHP, and JavaScript logging, a
rendered stack trace routinely *includes* the exception message and can
include local variables, call arguments, or other contextual data captured
at each frame — the first draft's blanket permission did not account for
this and is withdrawn. The corrected rule:

- **May be logged**: the exception's type/class name; a bounded, typed
  failure code (the same enum-based vocabulary this document already
  requires for outcome/failure classes generally); and allowlisted frame
  metadata only — safe fields such as file/module name, function name, and
  line number, never the frame's local variables or arguments.
- **Excluded by default**: the raw exception **message** — a message is
  free text the throwing code chose, capable of interpolating anything
  (a filename, a fragment of a request body, a provider error string) the
  logging boundary cannot verify is safe, so it is treated exactly like
  any other unallowlisted field.
- **Never serialized into logs, under any circumstance**: locals,
  call arguments, request bodies, provider payloads, prompts, questions,
  evidence, or document content — whether reached via an explicit log
  field or incidentally via a stack frame.
- **Logging a throwable/exception object directly is not assumed safe.**
  Calling a logger with the exception object itself (e.g. `logger.
  error(e)`, or passing an `Exception`/`Throwable` straight into a context
  array) must not be treated as safe by default — most logging libraries'
  default exception rendering includes exactly the message and frame
  contents this rule excludes, so the exception object must pass through
  the same allowlisting boundary as everything else, extracting only the
  type/code/frame-metadata fields above, never serializing the object
  wholesale.
- **A known-safe, bounded message may be included only when it originates
  from an application-owned enum or template that cannot interpolate
  uncontrolled content** — the same discipline ADR-0009 already requires
  (*"Authentication failures return a generic response rather than
  revealing which verification step failed"*) applied to logging
  specifically: a fixed string like `"vector store unavailable"` selected
  from a closed set of known failure classes is safe; `str(exception)` or
  an f-string built from request data is not, regardless of how
  reasonable it looks in a given case.
- **Negative privacy tests must exercise both paths**: the existing
  redaction test requirement (below) is extended to explicitly cover
  exception/stack handling, not only ordinary structured-log fields —
  a test proving a known-sensitive value never appears in an ordinary log
  field does not, by itself, prove the same value can't leak through an
  unguarded exception-message or stack-frame path.

**Verbosity:** production defaults to `INFO` and above; local development
may enable `DEBUG`. This mirrors Python's existing `JsonFormatter` default
(`INFO`) and requires no new concept.

**Event-name/version stability:** event names are a stable, explicitly
versioned vocabulary (e.g. `document.ingestion.claimed.v1`), never
freeform strings — the same discipline ADR-0008's `event_type`/
`event_version` fields already establish for the outbox contract, reused
here rather than invented separately.

**Cross-language convergence without shared libraries:** Laravel, Python,
and Next.js converge on the same *field vocabulary and event-name
convention*, documented once, each implemented with its own idiomatic JSON
logging mechanism (Laravel: a custom Monolog formatter; Python: the
existing `JsonFormatter`, extended; Next.js: a minimal server-side JSON
log helper) — never a shared logging package, since that would be exactly
the kind of proprietary cross-language wrapper ADR-0012 already rejects
for telemetry generally.

**Redaction/allowlist tests:** every service gets a negative test proving
a known-sensitive value never appears in an emitted log line, matching the
pattern ADR-0012 already requires for trace/metric attributes (*"a
negative test proves a known-sensitive value... never appears"*) — the
same discipline, applied to the one signal type ADR-0012 didn't originally
cover in its own test requirement. Per the stack-trace rule above, this
coverage explicitly includes a test that raises an exception carrying a
known-sensitive value in its message/locals and asserts the emitted log
line contains only the type/code/frame-metadata fields the rule permits —
not only tests against ordinary structured-log fields.

**Both redaction and failure isolation are enforced centrally, at the
logging helper/formatter/handler boundary — never something every call
site must remember to do itself.** A domain call site logs a plain event
name and a small set of typed fields (or, on failure, the exception
object, per the rule above); it is the shared formatter/handler in each
language (Laravel's custom Monolog formatter, Python's `JsonFormatter`,
Next.js's minimal JSON log helper) that applies the field allowlist, that
extracts only type/code/frame-metadata from an exception rather than
serializing it wholesale, and that catches and swallows its own failures.
This mirrors exactly how `TelemetryAttributeAllowlist` already centralises
allowlisting for trace/metric attributes today — the same shape, applied
to the logging boundary specifically — precisely so this document does not
require auditing every individual logging call site across three
languages for correct redaction and failure-isolation behaviour; auditing
the one shared boundary per language is sufficient.

**Logs remain useful when trace export is unavailable:** because logs are
written to stdout/stderr directly (not routed through the Collector), a
Collector or backend outage does not affect log emission at all — logs are
the signal least coupled to the rest of this document's infrastructure,
by design.

**Why logging failure cannot fail a request:** the same reasoning ADR-0012
already established for telemetry generally — *"Observability is a
secondary concern relative to the request it is observing."* A logging
call that itself throws (e.g. a full disk, a broken stdout pipe) is caught
inside the shared formatter/handler boundary above, never at each
individual call site, and never propagates into request/job handling —
extending, not reinventing, the existing no-op-fallback pattern both SDK
factories already implement for trace/metric export.

### A new, minimal platform administrator concept

Verified: no cross-workspace administrative authorization exists anywhere
in this codebase today. This document introduces the smallest such concept
that the required curated dashboard needs, deliberately not a general
permission engine.

**Verified schema gap, corrected from an assumption an earlier revision
made implicitly.** The current `users` table has no immutable public
identifier, no account-disable state, and no platform role of any kind.
Earlier language in this document that referred to "the user's immutable
public identifier" or to disabling/deleting a user "removing their
effective platform access" implicitly assumed schema that does not yet
exist. This document now defines that schema explicitly, as required new
work, not as something already present to build on.

**User identity and effective-access model — new schema this document
requires:**

- **`users.public_id`**: a new, immutable, unique identifier column.
  Existing rows receive a deterministic, safe backfill (generating one
  value per existing row) in the same migration pass, before the column
  is made required — the same expand-then-require sequencing this
  document already uses elsewhere for schema changes with existing data.
- **`users.platform_role`**: nullable, with exactly one non-null V1 value,
  `ADMINISTRATOR`. Entirely separate from `WorkspaceRole` — a platform
  administrator is not automatically a member, owner, or admin of any
  workspace, and a workspace owner/admin is not automatically a platform
  administrator.
- **`users.disabled_at`**: a new, nullable timestamp — chosen over a
  bounded status enum because it follows this codebase's own existing
  convention for exactly this shape of fact (e.g. `email_verified_at`),
  rather than introducing a differently-shaped status concept for this
  one case.
- **Effective platform-administrator access requires all three**: the
  user row still exists; `disabled_at IS NULL`; `platform_role =
  'ADMINISTRATOR'`. All three are evaluated **live, against current
  database state, on every request** — never cached, never trusted
  indefinitely from a session payload, the same discipline ADR-0006
  already requires for workspace membership, applied here to the platform
  plane.
- **Hard deletion of a user row naturally removes their effective access**
  — there is no separate "is deleted" flag to check, since the Gate's
  first condition (the row still exists) already fails once the row is
  gone.
- **Session invalidation on disable/delete**: disabling or deleting an
  account does two things, not one — (a) the live Gate check above means
  the very next request from that account fails platform-administrator
  authorization immediately, without waiting for any session to expire;
  and (b) because full account disablement is broader than platform
  authorization specifically, the account's active session record(s) are
  also proactively invalidated at disable/delete time, rather than merely
  left to lapse — so a still-open connection does not continue trusting a
  session that was valid when it was established but no longer reflects
  current account state.

**The three-tier future-compatible separation, named but not built beyond
tier one in V1:**
- **Platform administrator** — full operational visibility and the ability
  to set desired sampling/retention policy (below). The only tier this
  document actually implements.
- **Read-only operations viewer** — dashboard/alert visibility, no policy-
  mutation capability. Named for future use; not built in V1 because
  nothing in this document's scope currently needs more than one
  authorized human role, and building an unused permission tier would be
  exactly the premature granular-permission engine the brief warns
  against.
- **Operations editor/responder** — Grafana/Alertmanager-side
  acknowledgement/response capability, deliberately kept in specialist
  tooling (see "Alert acknowledgement" below), not a Dolved-side role at
  all in V1.

**Provisioning and revocation.**

- **Platform-administrator authority is a completely separate authority
  plane from workspace `OWNER`/`ADMIN`/`MEMBER`, in both directions.** A
  workspace owner cannot grant platform-administrator authority to
  anyone — not to themselves, not to another member — because that
  authority does not derive from, and is not reachable through, any
  workspace-scoped action at all. This is distinct from, and must not be
  confused with, ADR-0025's rule that a workspace *owner* may create a
  workspace *admin*: that is one authority plane (workspace) granting a
  role within the same plane; platform-administrator provisioning never
  crosses from the workspace plane into the platform plane in either
  direction.
- **Signup, invitations, and ordinary profile updates can never self-
  assign it.** No browser-reachable, user-initiated action sets
  `platform_role` — not registration, not accepting an invitation, not any
  self-service profile/settings change. If a code path that touches
  `platform_role` is ever reachable from an authenticated user's own
  request context without an explicit, separate platform-administrator-
  only authorization check, that is a defect against this invariant, not
  an acceptable convenience.
- **The initial platform administrator is provisioned through an
  explicitly deployment/operator-controlled mechanism — a non-browser
  Laravel command — never a web-reachable endpoint.** This mirrors
  ADR-0006's own existing pattern for the analogous bootstrap problem
  (*"Workspace provisioning is currently platform-admin controlled... an
  authorised platform process creates the workspace"*) rather than
  inventing a different bootstrap philosophy for this one case. See the
  dedicated bootstrap-credential subsection below for exactly what
  authorizes this command.
- **Subsequent grants and revocations remain deployment/operator-
  controlled in V1**, via the same mechanism as initial provisioning,
  **unless a secure platform-admin management screen is explicitly
  justified** by a demonstrated operational need this document does not
  currently have evidence for — consistent with the brief's own "do not
  build a granular permission engine unless required" instruction, applied
  here to the management UI itself, not only to the role model.
- **The operation is idempotent and identifies the intended existing user
  unambiguously** — by `users.public_id`, not a free-text or fuzzy lookup
  (e.g. an email match alone is not sufficient identification, since email
  addresses can be reassigned); granting to an already-administrator user
  is a safe no-op, not a duplicate-grant error.
- **Removing the final active platform administrator is rejected unless an
  explicit replacement/controlled recovery procedure is being performed in
  the same operation** — the same "never ownerless" discipline ADR-0025
  already applies to workspace ownership, applied here to the platform
  plane: a revocation that would leave zero active platform administrators
  is refused by default, with recovery requiring the same operator-
  controlled bootstrap mechanism used for initial provisioning — the
  dedicated deployment credential below, never an unauthenticated database
  edit — not a silent gap in coverage.
- **No platform role grants implicit membership or authority in any
  workspace** — restated from the model above because it is the invariant
  every other rule in this subsection exists to protect: the two authority
  planes never leak into each other, in either direction, at any point in
  the grant/revoke lifecycle.

**Platform-level command and audit records — corrected from the first
draft's claim that provisioning "reuses ADR-0025's existing administrative-
audit shape," which is withdrawn.** ADR-0025's business-audit records
require a `workspace_id`, and their actor is always an authenticated
Dolved user — neither holds for platform-administrator bootstrap, since
bootstrap can and must occur before any platform-administrator user exists
to authorize it, and its actor is a deployment/operator identity, not a
workspace user. Forcing platform-scoped events into a workspace-shaped
table would mean fabricating a fictional `workspace_id` for a fact that
has nothing to do with any workspace. This document instead defines new,
genuinely platform-scoped records:

**`PlatformAdministrationCommand`** — a durable idempotency/command
record, carrying at least: command public ID; command type (e.g.
`GRANT`/`REVOKE`/`BOOTSTRAP`/`RECOVER`); an idempotency key; a request
digest; actor kind; actor identifier; target user public ID; correlation
ID; a bounded result; and timestamps.

**`PlatformAdministrationAuditEvent`** — an append-only, platform-level
business-audit record, carrying at least: event public ID; **no workspace
foreign key at all**; actor kind; actor identifier; action; target type;
the target's immutable public ID; bounded before/after state; correlation
ID; and an occurrence timestamp. This is the platform-plane counterpart of
`DocumentGovernanceAuditEvent`'s existing shape (actor, action, before/
after, timestamp) — the *pattern* is reused, not the workspace-scoped
table itself.

**Actor kinds, defined explicitly:**
- **`DEPLOYMENT_CREDENTIAL`** — for bootstrap/operator-command actions,
  the only actor kind V1 actually produces.
- **`PLATFORM_USER`** — reserved for a possible future authenticated
  platform-admin management surface (see "read-only operations viewer"/
  "management screen" above); not required, and not producible, in V1.

**For `DEPLOYMENT_CREDENTIAL` actors specifically**: only the stable
credential/key identifier is stored — never the credential secret. This
document does **not** claim that identifies the individual human who
invoked the command; a shared deployment credential's use by a specific
person is a fact the deployment system's own access audit is responsible
for, not Dolved. **Dolved's authoritative claim is limited to which
authenticated deployment credential performed the command** — a narrower,
honest claim, not an inflated one.

**Atomicity**: the `PlatformAdministrationCommand` record, the
`users.platform_role`/`disabled_at` mutation it authorizes, and the
corresponding `PlatformAdministrationAuditEvent` all commit in one
database transaction. A crash between these steps must never leave a
platform-role grant or revocation that took effect without a matching
audit record, or an audit record describing a mutation that never actually
committed.

**The platform-administration bootstrap credential — a concrete V1 trust
boundary, not an inferred one.**

- Provisioning, grants, revocations, and recovery all occur through a
  **non-browser Laravel command**, never a web-reachable endpoint.
- That command requires a **dedicated platform-administration deployment
  credential** — separate from ingestion HMAC, deletion HMAC, and the
  observability-reconciliation credential defined above. Four distinct
  credential families exist in total across this ADR's scope; none is
  shared across purposes merely by varying a purpose string.
- The credential has a **stable, non-secret key identifier** and a
  **protected secret** — mirroring the existing ingestion-worker key-ID/
  secret pattern already proven in this codebase, reused structurally,
  not the same secret.
- It is authorized **only** for platform-administration bootstrap/grant/
  revoke/recovery purposes — purpose-scoped, matching the discipline
  already established for every other HMAC-authenticated boundary in this
  system.
- **The command validates the credential before performing any
  mutation** — an invalid or missing credential aborts before touching
  `users` or writing any command/audit record.
- **The secret is supplied through a protected deployment secret/file or
  standard-input mechanism** — never persisted in the command or audit
  records, and never placed where it would land in shell history or
  process-argument listings (i.e., never passed as a plain CLI argument).
- The audit actor recorded is `DEPLOYMENT_CREDENTIAL` plus the
  authenticated key identifier — never a raw secret, never an inferred
  human identity.
- **Signup, workspace invitation, workspace ownership, and browser
  profile endpoints never possess this credential and structurally cannot
  invoke this authority** — restated from "signup... can never self-
  assign it" above, now anchored to a concrete credential boundary rather
  than a policy statement alone.
- Grant/revoke operations remain idempotent, per the command-identity
  model above.
- **Recovery from zero effective administrators requires this same
  deployment credential** — never an unauthenticated database edit, and
  never a different, weaker recovery path than ordinary provisioning uses.

**Credential rotation and versioning**, at the architectural level: each
credential is identified by a key identifier and version, mirroring the
multi-key registry pattern this codebase's ingestion-worker HMAC
authentication already implements (a keyed lookup of secrets by key ID,
not a single global secret). Rotation introduces a new key identifier/
version, optionally with a bounded overlap window during which both the
outgoing and incoming credentials validate, followed by explicit
revocation of the outgoing one; a revoked credential is rejected at
validation time (a live status lookup, not merely a static secret
comparison) and is never accepted again afterward, including for
recovery.

### Dolved's main platform-admin operational dashboard

Answers *"Is Dolved healthy, and which broad area needs attention?"* —
distinct from, and never a replacement for, the private Grafana console
below.

**Verified against actual current instrumentation, section by section —
not a promise the current signals can't support:**

| Candidate section | Supportable today? | Basis |
|---|---|---|
| Overall platform health | Partial | Existing `/up`/`/health` are shallow liveness only — no dependency check. New composite health signal is R20-S02 work. |
| API request volume, error rate, latency | Yes | `http.server.request.count`/`.duration` already exist on both services. |
| Ingestion throughput and failure rate | Yes | `rag.ingestion.*` counters/histograms already exist; failure counting reuses ADR-0025's established `IngestionEventClaim`-entering-`Failed` counter. |
| Queue depth and oldest-message age | New metric needed | ADR-0008 already frames this as an outbox observability surface conceptually; no metric instrument exists yet. |
| Stuck ingestion and document-deletion operations | New metric needed | ADR-0025 already names the exact signal ("surfaced as visibly stuck in the read model") but as a read-model concept, not yet an exported metric. |
| Document processing duration | Partial | Spans exist for some stages; no end-to-end duration histogram yet. |
| Retrieval outcomes and latency | New metric needed | Spans exist; no counters/histograms yet (traced-but-not-metered, per Context). |
| Generation outcomes and latency | New instrumentation needed | Zero telemetry of any kind exists today for generation. |
| Time to first accepted streamed answer part | New metric needed, existing field to build on | ADR-0024 already names and persists `first-part-accepted-for-display`; no metric derived from it yet. |
| Completed/timed-out/cancelled GenerationRuns | New metric needed | The terminal-state enum already exists (ADR-0024); no counter keyed on it yet. |
| Provider availability, latency, rate limiting | Partial | Voyage embedding calls are spanned; reranking and OpenAI generation calls have none. |
| Database/object-storage/vector-store health | New instrumentation needed | No dependency-health metric exists for any of the three. |
| Active alert summary / SLO state | New, depends on R20-S04 | Cannot exist before alerts/SLOs are defined. |
| Storage/capacity indicators | Deferred | No current signal; likely depends on Phase 22 infrastructure decisions. |
| Telemetry freshness | New, small | A last-successful-scrape/query timestamp on the adapter response. |
| Current sampling/retention policy and status | New | Defined below (desired-state/reconciliation model). |

No dashboard promise is made beyond what this table supports; sections
marked "New instrumentation needed" are R20-S02/S03 implementation work
this document names as a prerequisite, not an assumption.

### Operational-metrics reader: the narrowest adapter seam

```text
Applications
    → OpenTelemetry SDK
    → Collector
    → otel-lgtm (internal Prometheus, Tempo, Loki; only Grafana's UI
      port is published to the host, loopback-only)

Dolved platform-admin API
    → bounded operational-metrics reader
    → Prometheus's native HTTP query API, reached over the compose-
      internal network (never a host-exposed port, never browser-supplied)
```

**Verified constraint that shapes this design:** there is no Prometheus-
native scrape endpoint anywhere in this application — metrics arrive at
the bundled backend exclusively via OTLP push through the Collector. The
bundled `otel-lgtm` image's internal Prometheus is reachable only
container-to-container; Laravel, running inside the same compose network,
can reach it directly by internal DNS name without any new port exposure.
This is the narrowest adapter seam available: Laravel calls Prometheus's
own HTTP query API internally, never the browser, and never through a
publicly reachable port.

**Required properties**, matching the brief exactly: platform-
administrator authorization (the Gate above); predefined, server-owned,
allowlisted queries only — no PromQL, and no other backend query language,
ever accepted from the browser; bounded time windows and result sizes;
short server-side caching to avoid repeated expensive backend queries;
freshness/as-of timestamps on every response; unavailable data rendered as
explicitly unavailable, never as zero (the same discipline ADR-0025 already
requires for tenant usage, applied here); strict timeouts and fail-soft
behaviour; a metrics-backend failure never affects ordinary Dolved use,
including ordinary workspace administration; no log or trace payload ever
copied into Laravel; optional deep links into Grafana for further
investigation, never an embedded iframe (see "Private Grafana operations
console" below).

OpenTelemetry itself defines no general backend query API — this backend-
specific adapter is a deliberate, isolated exception to vendor-neutral
emission, exactly as the brief anticipates: *"a backend-specific read
adapter may be justified even though emission remains vendor-neutral."*
Keeping it as one small, isolated class (analogous in spirit to
`QdrantVectorStore` being the one place Qdrant-specific code lives) means a
future backend change replaces one adapter, not PromQL spread through the
application.

### Global operational metrics versus ADR-0025 tenant usage

```text
Global platform health
    → telemetry metrics backend (this document)

Per-workspace usage/activity
    → ADR-0025 content-free usage/activity records

Deep technical diagnosis
    → private Grafana logs, metrics and traces (this document)
```

Prometheus/OpenTelemetry metrics never use workspace, user, document,
message, conversation, run, or correlation IDs as metric labels — this was
already ADR-0012's rule (*"No metric carries an unbounded-cardinality
label"*), restated here because Phase 20 is the first phase where the
temptation to break it (a platform administrator legitimately wanting a
workspace breakdown) is concrete. Where a platform administrator needs a
workspace-specific breakdown, the answer is ADR-0025's own application
records, queried through an appropriately authorised platform-admin query
— never a high-cardinality metric label. Ordinary workspace owners/admins
never gain access to global platform health or another tenant's activity
through this dashboard — the platform-administrator Gate above is strictly
additive authorization, never a relaxation of ADR-0006's or ADR-0025's
existing tenant-isolation invariants.

### Private Grafana operations console

Answers *"Exactly where and why did this fail?"* — the specialist
environment, not the routine main-admin experience. Used for: arbitrary
metric exploration; log searching; trace waterfalls; correlation-ID
searches; individual span inspection; provider-call timing; queue/worker
diagnosis; dashboard/rule editing; advanced time comparisons.

**Access model, critiqued against actual current state rather than
assumed:** the brief's candidate direction (separate `ops.<domain>` URL,
linked never embedded, federated identity/SSO plus MFA plus an operator
group, optionally an identity-aware proxy or VPN) is architecturally sound
and adopted, but **this document does not claim any of the identity/SSO/
MFA/operator-group machinery already exists — because it does not.**
Verified: Grafana today runs with anonymous access enabled and anonymous
visitors granted the `Admin` organisation role, login form disabled
entirely (`compose.yaml`: `GF_AUTH_ANONYMOUS_ENABLED: "true"`,
`GF_AUTH_ANONYMOUS_ORG_ROLE: Admin`, `GF_AUTH_DISABLE_LOGIN_FORM: "true"`).
This is safe *only* because the port is loopback-bound in every
environment that exists today (local development). **This document
requires, as an architectural invariant, that this configuration must
never survive into any environment reachable beyond loopback** — production/
staging Grafana access requires real authentication before it is exposed
at all, and Phase 22's production-infrastructure work inherits this as a
hard precondition, not a nice-to-have. Local development may continue
using documented loopback-only access with development credentials (the
current anonymous-admin setup is acceptable *as a local-dev convenience*,
provided it is explicitly documented as local-only and never copied
verbatim into a production compose/deployment configuration). The exact
SSO/MFA/identity-aware-proxy mechanism is Phase 22 infrastructure work
this document does not select a vendor for — deferred, not decided against.
Production Grafana/Prometheus/Tempo/Loki/Alertmanager ports are never
publicly exposed; that invariant holds regardless of which identity
mechanism Phase 22 eventually selects.

Grafana is opened in its own tab/window — never embedded in an iframe,
which would either require disabling Grafana's own frame-protection
headers (a real security regression) or silently break, and would blur
exactly the "curated main dashboard versus specialist console" distinction
this document exists to preserve.

The three-tier separation is defined above under "platform administrator";
V1 builds only the platform-administrator tier for Dolved's own Gate,
while Grafana's own org-role system (once real authentication replaces
anonymous access) is the natural place read-only-viewer/editor-responder
distinctions eventually live, without Dolved needing to model them itself.

### Sampling and retention: desired-state from main admin, reconciled by infrastructure

**Verified constraint:** the Collector's config
(`infrastructure/opentelemetry/collector.yaml`) is a static file, mounted
read-only into the container; it has exactly one processor (`batch`), no
sampling processor configured yet, and changing it today requires editing
the file and restarting the container — there is no hot-reload mechanism
in the current setup. **Nothing about the current Collector is runtime-
reconfigurable**, so a "desired sampling percentage" set from platform
admin cannot honestly claim to take effect immediately, and R20-S03 adds
the `probabilisticsamplerprocessor` this design requires (see "Sampling
strategy" below, which also verifies the pinned Collector distribution
already contains that component). **Corrected from an intervening
revision, which had SDK-side application processes as sampling's
enforcement target: sampling's enforcement target is the Collector, per
ADR-0012's already-accepted ownership boundary, restored below.**

**Ownership boundary**, matching the brief exactly: Laravel's platform
admin owns desired operational policy and its authorization — a versioned,
audited record of what the operator *wants*. Enforcement is owned by
whichever component actually implements each setting (below) — for
sampling specifically, that is the Collector alone, never Laravel and
never any application service. Saving a desired value in Laravel never
claims any enforcement target has applied it. Configuration is validated,
versioned, and audited. A **telemetry runtime/infrastructure reconciler**
— a broader term than "the Collector," used deliberately, since applying
one desired policy version can still require coordinating multiple
independent components across different settings (sampling via the
Collector; each retention target via its own backend) — applies it
safely; in V1, given the static-configuration constraints above, this
reconciler is honestly an infrastructure-owned deployment process
(redeploying/restarting whichever Collector or backend a given setting
requires), not a live controller API. Phase 20 defines and tests the
state/acknowledgement boundary described below; Phase 22 may later
automate the deployment pipeline that drives it without changing that
boundary. Failure to apply operational configuration never affects
ordinary application correctness — this is a pure extension of ADR-0012's
existing isolation invariant.

**Enforcement target for every setting — corrected to restore ADR-0012's
Collector-ownership boundary for sampling specifically:**

| Setting | Enforcement target | Notes |
|---|---|---|
| Normal trace sampling percentage | **The Collector's `probabilisticsamplerprocessor`** — never an application-service SDK sampler | Application services (Laravel, Python, and any later-instrumented service such as Next.js) emit every span unfiltered and must not run a competing ratio sampler of their own; see "Sampling strategy" below. Changing this requires redeploying/restarting the Collector with updated processor configuration. |
| Slow-operation threshold | Two distinct consumers, each its own target: (a) the application-side instrumentation in Laravel/Python that tags a bounded "slow" diagnostic event/metric when an operation's duration exceeds the threshold; (b) the Prometheus/Alertmanager rule configuration that fires an alert against that threshold | This is a threshold for *detecting and surfacing* slow operations through the normal, unsampled metrics/logs path (R20-S02/S04) — it does not cause an otherwise-unsampled slow trace to be retained, and must never be described as providing tail-sampling-like behaviour. |
| Log retention | The effective log backend/runtime configuration — currently Loki, within the local bundled LGTM topology | |
| Trace retention | The trace backend — currently Tempo, within the local bundled LGTM topology | |
| Metric retention | The metrics backend — currently Prometheus, within the local bundled LGTM topology | |
| Collector configuration generally | The Collector itself, for whatever it actually owns: receiving, batching, sampling, and exporting telemetry | Sampling is now correctly included here as a Collector-owned responsibility; other Collector-owned concerns (batching, export) remain as before. |

**Reconciliation is per-setting and per-target, not one blanket operation.**
Different settings can require different, and sometimes multiple, targets
(the slow-operation threshold requires both an application-side target and
an alerting-rule target). The reconciler's job for a given desired version
is to drive **every required target for every setting in that version**
toward the matching configuration and report back per target — not to
perform one action and assume every setting is thereby covered.

**A required-target manifest, not an assumed one.** Which components are
*required* to acknowledge a given setting is itself explicit, versioned
state, not inferred implicitly from whichever services happen to exist:

- **For V1 sampling, the Collector is the sole required target.** Laravel
  and Python are **not** sampling acknowledgement targets — restored
  ownership means they have nothing to acknowledge for this setting; they
  remain required targets only for their own trace-context-propagation
  correctness, which is verified through the trace-coverage work in
  R20-S03, not through this reconciliation-acknowledgement mechanism.
- **A future additional Collector instance, if this platform ever scales
  to multiple replicas, must be added to the required-target manifest
  before a future sampling version can become `ACTIVE` across the whole
  deployment** — extending required-target coverage is itself a
  reviewable, versioned change to this manifest, not something a new
  instance inherits automatically by existing.

**Concrete behaviour this produces**, addressing the brief's examples
under the corrected model: the Collector applies and acknowledges a new
sampling percentage — sampling reaches `ACTIVE` once that single required
target matches, with no dependency on Laravel or Python acknowledging
anything for this setting. The slow-operation threshold requires *both*
its application-side target and its alerting-rule target to acknowledge
before it reaches `ACTIVE` — a change applied to Laravel/Python's
diagnostic-event logic but not yet to the Alertmanager rule leaves this
setting `PENDING`. Log retention's backend acknowledges successfully while
trace retention's backend cannot yet be verified — log retention may
independently reach `ACTIVE` while trace retention remains its own,
independently `PENDING` state; one setting's success never implies
another's.

**The `ACTIVE` transition is machine-verified, never self-declared.**
Neither the browser nor a platform administrator can mark a desired policy
applied by asserting it; a **setting** (not the policy as a whole) becomes
`ACTIVE` only once *every* target required for that setting has produced a
matching, successful acknowledgement. The overall desired policy version
is fully active only when every one of its settings is active — the
main-admin surface may show a clearly-labelled aggregate ("N of M settings
active"), but never collapses partial application into one blanket
`ACTIVE`.

**Acknowledgement identity, corrected a second time — the previous
revision's `(environment, desired policy version, setting key, target)`
tuple is still one level too coarse.** That tuple lets Laravel's, Python's,
and each backend's acknowledgements coexist, but it also means the *first*
acknowledgement ever recorded for a given `(environment, version, setting,
target)` permanently occupies that identity — a genuinely new, later
attempt to apply the same still-current policy (for example: Python's
first attempt at sampling version 7 fails, an operator fixes the
underlying configuration, and Python attempts version 7 again) would
either collide with the first attempt's identity or be misread as a
conflicting report about it, rather than being recognised as its own,
separate, legitimate attempt. **This document now separates three
concepts explicitly, using an append-only attempt/acknowledgement model:**

1. **A logical deployment/application attempt** — one concrete, operator-
   or reconciler-initiated effort to apply a specific setting to a
   specific target under a specific desired policy version. Each attempt
   is a new, immutable record once its outcome is reported.
2. **A network delivery retry of an acknowledgement from that same
   attempt** — a retransmission of the identical report for an attempt
   that already exists (e.g. a timed-out HTTP call resent by the
   reconciler), never a new attempt in its own right.
3. **The derived current reconciliation state** for a given `(environment,
   desired policy version, setting key, target)` — a separately computed
   projection over the attempt history, not an attempt record itself; this
   is what the main-admin surface actually displays as that target's
   `ACTIVE`/`PENDING`/`FAILED` status.

**Identity, corrected accordingly**: `(environment, desired policy
version, setting key, target, deployment_attempt_id)`. Each acknowledgement
carries at least:

- environment;
- desired policy version;
- setting key (e.g. `trace_sampling_ratio`, `log_retention_days`);
- enforcement target/component (e.g. `laravel`, `python`, `loki`,
  `tempo`, `prometheus`);
- a **`deployment_attempt_id`**, minted fresh for each genuinely new
  logical attempt (never reused across attempts, even against the same
  still-current version/setting/target);
- the observed effective value for that setting on that target;
- an expected-versus-observed configuration digest, established as
  described next;
- application/reconciliation time;
- a bounded success/failure state;
- a correlation/audit identity, tying the acknowledgement back to the
  business-audit record of who requested the change.

**Delivery retry versus application retry versus policy revision — the
terms this document now uses consistently, rather than the single word
"retry" for three different things:**

- **Acknowledgement delivery retry** means retransmitting the identical
  report for an *existing* `deployment_attempt_id` — a network-layer
  concern, not a new attempt.
- **Deployment/application retry** means a genuinely new attempt — a new
  `deployment_attempt_id` — applying the same still-current desired policy
  version to the same setting/target, typically after a prior attempt
  failed. A failed attempt never requires minting a new desired-policy
  version merely to retry applying the same, unchanged policy.
- **Policy revision** means the *desired value itself* changed — this, and
  only this, produces a new, immutable desired-policy version. Retrying an
  application of an unchanged desired value is never modelled as a policy
  revision.

**Idempotency and conflict rules, scoped to the full five-part identity:**
a delivery retry — the same `deployment_attempt_id` reporting identical
content a second time — is a safe no-op; it matches the existing immutable
attempt record exactly and changes nothing. Two acknowledgements sharing
the same `deployment_attempt_id` but disagreeing on payload, observed
value, digest, or outcome are a **conflict** and fail closed — this should
never legitimately happen, since a fresh attempt always gets a fresh
identity, so any occurrence indicates a defect or a replay attack and is
surfaced for investigation rather than silently resolved either way.

**State-transition rules, and how a target recovers from a failed
attempt:**

- A valid failed attempt records failure **for that attempt** — an
  immutable fact — and, if it was the currently-authorised attempt at the
  time it reported, may set the target's *derived current* reconciliation
  state to `FAILED`.
- A later, explicitly-authorised deployment/application retry (a new
  `deployment_attempt_id`) for the same still-current desired policy
  version may retry the target — this is the recovery path for the
  example above: Python's Attempt A fails and is recorded, permanently, as
  a failed attempt; an operator authorises Attempt B against the same
  version 7; Attempt B is a distinct record from the start and does not
  collide with Attempt A.
- A matching, successful later attempt may move the target's derived
  current state from `FAILED` or `PENDING` to `ACTIVE` — Attempt B
  succeeding moves Python's derived state forward even though Attempt A's
  own record remains, unchanged, exactly as it reported.
- **Historical failed attempts remain immutable for audit** — Attempt A is
  never edited, deleted, or overwritten once recorded; it simply stops
  being the attempt the derived current state is based on.

**Concurrency control: only the currently-authorised attempt may derive
the target's present state.** Concurrent different attempts against the
same `(environment, desired policy version, setting, target)` must not
race silently — for example, a slow, late-arriving report from an
abandoned or superseded attempt must not overwrite a more recent attempt's
already-recorded outcome. This document requires an explicit **current-
attempt pointer** (equivalently, a lease or generation counter) per
`(environment, desired policy version, setting, target)`: authorising a
new attempt advances this pointer to that attempt's
`deployment_attempt_id` *before* the attempt runs; when an acknowledgement
is processed, it is applied to the derived current state via a
compare-and-set against that pointer — only an acknowledgement whose
`deployment_attempt_id` still matches the current pointer at the moment of
processing is allowed to set the derived state. An acknowledgement for an
attempt that has since been superseded by a newer authorised attempt is
still recorded, in full, as an immutable historical attempt — it is simply
inert with respect to the derived current state, the same treatment
already given to a stale acknowledgement for a superseded desired-policy
version below.

- **An attempt for a superseded desired-policy version remains recorded
  but inert** — its `(environment, version, setting, target,
  deployment_attempt_id)` key no longer corresponds to the currently
  outstanding version for that setting/target at all, so it cannot affect
  the current derived state regardless of the compare-and-set rule above.
- **A failed attempt does not require creating a new desired-policy
  version merely to retry applying the same, unchanged policy** — restated
  from "Delivery retry versus application retry versus policy revision"
  above, because it is the rule this whole correction exists to make
  concrete: version 7 stays version 7 across Attempt A and Attempt B.
- **If the desired value itself changes, that is a new, immutable desired-
  policy version, not another attempt against the old one** — a policy
  revision and an application retry are never the same operation, even
  though both eventually result in a fresh `deployment_attempt_id`.

**A setting becomes `ACTIVE` only when every required target's derived
current state is backed by a successful, valid attempt for that same
desired-policy version** — not merely "some attempt, at some point,
succeeded," and not carried forward from a prior version's success once
the desired version has moved on.

**Establishing an honest expected/observed digest — corrected from the
first draft's overclaim.** The first draft implied Laravel could detect
that a reconciler applied the *wrong* rendered configuration purely by
comparing a reported digest against the desired record — this is only
possible if an expected digest was actually established before or during
reconciliation, which the first draft never defined. The corrected,
honest mechanism:

1. The reconciler reads one specific, immutable desired-policy version.
2. It derives a canonical **per-target reconciliation plan** from that
   version: desired policy version, setting key, target component, the
   desired effective value, and an expected configuration/value digest
   computed from that plan — this is the digest Laravel will later expect
   to see echoed back, not merely repeated desired values.
3. It applies that target's configuration according to the plan.
4. **Where the target technically supports it**, the reconciler rereads
   or otherwise observes the target's actual effective runtime
   configuration and verifies it against the plan before reporting
   success.
5. It submits the authenticated, purpose-scoped acknowledgement carrying
   the plan identity, the observed effective value, and the digest.
6. Laravel validates the request's authentication and purpose scope, then
   the desired version, setting, target, plan identity, and reported
   values against its own copy of the plan, before advancing that
   setting's state.

**The observability-reconciliation credential and protocol, defined
explicitly — the previous revision required "an authenticated, purpose-
scoped channel" without deciding what actually authenticates it.** This
document selects a concrete V1 protocol, reusing this codebase's
established HMAC canonical-signing and validation discipline (the same
family of technique the ingestion-worker and rc1 protocols already use)
but as a **new, dedicated purpose-scoped protocol family with its own
credential** — never a reused ingestion, deletion, or platform-
administration bootstrap secret, and never a purpose string layered onto
an existing credential merely to distinguish call sites. Least privilege
requires a genuinely separate credential per authority boundary, not four
purposes sharing one secret.

- A dedicated **observability-policy reconciliation credential** —
  distinct from ingestion HMAC, deletion HMAC, and the platform-
  administration bootstrap credential defined below.
- Bounded purposes: `observability.policy.plan.read` (the reconciler
  fetches one specific, immutable reconciliation plan) and
  `observability.policy.reconcile` (the reconciler reports a per-setting/
  per-target deployment attempt's outcome).
- **Only the infrastructure reconciler holds this credential.** Laravel
  owns desired-policy persistence, plan identity, and state transitions;
  the reconciler owns applying and observing Collector/backend
  configuration. Neither the browser nor Python participates in this
  protocol at all — Python emits telemetry and has no role in policy
  reconciliation, consistent with the corrected sampling-ownership
  boundary above.
- The signed request binds, at minimum: protocol/version; purpose; HTTP
  method/path where applicable; credential key identifier/version;
  environment; desired-policy version; setting key; target;
  `deployment_attempt_id`; plan identity; a canonical body digest;
  timestamp; a nonce/request identifier; and the correlation identifier.
- **Required properties**: constant-time signature verification; bounded
  clock-skew tolerance; replay detection via the nonce/request identifier
  (rejecting a previously-seen one outside the idempotent-delivery-retry
  case already defined above); canonical serialization (so the signed
  digest is computed identically on both sides); purpose-mismatch
  rejection; environment-mismatch rejection; revoked/unknown-credential
  rejection; bounded request-body size and schema validation; a generic
  external failure response on any authentication failure (never
  revealing which specific check failed, the same discipline ADR-0009
  already requires); no secret, signature, or raw request body ever
  logged; idempotent handling of an identical delivery retry (defined
  above); fail-closed handling of a genuine conflict (defined above); and
  the compare-and-set/current-attempt semantics already defined above
  govern which acknowledgement, once authenticated, is actually permitted
  to advance state.
- **Only a successfully authenticated reconciliation acknowledgement can
  advance a setting/target's state** — an unauthenticated or failed-
  authentication report is rejected outright and never reaches the
  compare-and-set/state-transition logic at all.

**Honest limits on independent verification, stated plainly rather than
implied away:** where a target cannot expose independently observable
effective configuration (for instance, if a given backend offers no way to
read back its currently-applied retention window), the acknowledgement
proves only that the trusted reconciler *applied and verified as much as
that component supports* — Laravel is never independently inspecting the
Collector, an application process, or a backend itself; it is trusting a
validated report from the one component that already has that access.
**That setting's status remains `PENDING`, not `ACTIVE`, wherever
effective application cannot be sufficiently verified this way** — this
document does not claim cryptographic detection of a wrongly-applied
configuration in any case where no genuine expected digest exists to
compare against.

**Named behaviours for the remaining cases the brief specifically asks
about:**

- **A newer desired version appears while an older one is still being
  applied**: the older version's plan is superseded; any of its
  acknowledgements that arrive afterward no longer match the (now-newer)
  outstanding desired record for that setting and are treated as stale —
  recorded for audit, but inert, never activating anything.
- **The Collector, or any other target, later reverts to an older
  configuration after being acknowledged `ACTIVE`** (e.g. a stale
  redeploy or a manual revert): this design confirms application *at the
  moment a valid acknowledgement is received* — it does not continuously
  monitor for later drift. Detecting silent reversion after acknowledgement
  requires a periodic reconciliation-verification check (re-deriving and
  comparing an effective-configuration digest on a schedule), named here
  as a real, honest V1 limitation and a natural small extension for
  Phase 22, not built as part of this document's V1 scope.
- **Conflict is precise, not "any disagreement for the same setting/
  target."** Two *different* `deployment_attempt_id`s for the same
  version/setting/target reporting different outcomes is not a conflict —
  it is the ordinary failed-then-retried (or racing-concurrent-attempts)
  case already handled by the state-transition and current-attempt-pointer
  rules above. A genuine **conflict**, as defined there, is narrower and
  rarer: the *same* `deployment_attempt_id` reporting inconsistent
  content across two acknowledgements — this fails closed and requires
  investigation, since a fresh attempt always gets a fresh identity and
  this should not legitimately occur.

**Main admin exposes**, per the brief, per-setting and, where relevant,
per-target: normal trace sample percentage; slow-operation threshold; log
retention target; trace retention target; metric retention target; desired
configuration version; each setting's required targets and their
individual acknowledgement status; each setting's own `ACTIVE`/`PENDING`/
`FAILED` state; last application/reconciliation time per setting; a
clearly-labelled "N of M settings active" aggregate, never a substitute
for the per-setting detail; actor/change audit (reusing the same business-
audit shape ADR-0025 already established for administrative actions, not
a new audit mechanism).

**Initial targets**, adopted as given, stated as product defaults
consistent with this document's own commitment to not overclaim: local
development retains/samples everything practical; normal production trace
sample 10%; log retention 30 days; trace retention 14 days; metric
retention 90 days. These retention targets apply to telemetry only — never
to business audit, tenant usage, or any other required application
record, which retain their own, separate, durability commitments
unaffected by anything in this document.

### Sampling strategy: the honest V1 answer

**Corrected from the first draft, which proposed an unsound mechanism.**
The first draft recommended head sampling *"plus an explicit, separate,
always-sampled category for spans that self-report an error status at
creation time,"* reasoning that a span could be marked `STATUS_ERROR` "as
soon as the failure it represents is known" and thereby escape its trace's
sampling decision. **This does not work, and the claim is withdrawn.**
OpenTelemetry's sampling decision is made once, when a span is *created*
(the `Sampler` is invoked at span-start, before the operation it describes
has run), and that decision cannot be reversed later by setting the span's
status or recording an exception on it — by the time an operation is known
to have failed, the sampling decision for that span (and, under parent-
based sampling, for its whole trace) was already made and, if "not
sampled," the span was never recorded for export in the first place. There
is no mechanism by which recording an error after the fact makes an
already-unsampled span exportable. The first draft's claim was simply
wrong, not merely optimistic.

**Corrected a second time: sampling is Collector-owned, not SDK-owned —
restoring ADR-0012's already-accepted ownership boundary.** The previous
revision recommended an SDK-side `ParentBased(TraceIdRatioBased)` sampler
configured inside Laravel and Python. That directly contradicted ADR-0012's
explicit *"The Collector — not application code — owns routing, batching,
filtering, sampling and backend-specific export"* and is withdrawn. The
corrected design keeps sampling exactly where ADR-0012 already put it:

- **Application services (Laravel, Python, and any later-instrumented
  service such as Next.js) emit every span they create, unfiltered, with
  no competing ratio sampler of their own.** SDK sampler configuration in
  every service is `AlwaysOn` (equivalently, `ParentBased(AlwaysOn)`) —
  application code does not decide what fraction of traces survive, and
  must not contain a second, independent 10% sampler that would either
  duplicate or fight the Collector's decision.
- Application services continue to **propagate W3C trace context
  correctly** — this remains essential, and fixing the Laravel→Python
  `traceparent` gap remains this document's top R20-S03 priority, for a
  precise reason: the Collector can only make one *consistent* decision
  for a whole trace if every span belonging to that trace carries the same
  trace ID when it arrives at the Collector. Propagation is what makes a
  cross-service trace *identifiable as one trace* to the Collector; it is
  not what decides whether that trace is retained.
- **The Collector owns the production trace-retention sampling decision**,
  via a `probabilisticsamplerprocessor` in its traces pipeline, at a
  configured baseline: 10% in production, 100% where practical locally.
- This processor makes its decision by hashing each span's **trace ID**,
  so **every span belonging to the same trace receives the same keep/drop
  decision** — spans are never independently sampled one at a time. This
  hash-based decision is a pure function of the trace ID: it requires no
  buffering of the trace and no coordination between multiple Collector
  instances, because any replica computes the identical decision for the
  identical trace ID independently — a materially different, and better,
  scaling property than tail sampling (below), which does require either
  one instance or a trace-routing layer.
- **Laravel stores and authorises desired sampling policy only — it does
  not perform telemetry sampling itself.** Python emits telemetry only —
  it does not read or interpret Dolved's tenant or platform-administration
  policy at all. Neither service dynamically alters its own sampler based
  on anything Laravel's platform-admin surface stores; the desired-policy
  value platform admin holds is realised solely by reconfiguring the
  Collector (see "Sampling and retention" above), never by application
  code branching on it.
- **Phase 20 does not guarantee that every error trace or every slow trace
  is retained.** A trace that happens to fall outside the configured
  percentage produces no retained record even if it later turns out to
  contain an error — this is a real, named limitation of probabilistic
  sampling generally (head or Collector-applied), not a gap this document
  papers over.
- **If a trace that happened to be sampled also contains an error, its
  complete trace remains available normally** — the trace-ID-hash decision
  is made independently of the trace's eventual content, so a sampled
  trace's error status is retained and visible exactly like any other
  sampled trace's would be.
- **This document does not create a standalone "error span"/"error
  trace"** outside the ordinary sampling decision, and does not imply any
  such artefact reconstructs the discarded journey of an unsampled trace.
  An error inside an unsampled trace has no salvageable trace-level
  record; only unsampled metrics and privacy-safe structured logs describe
  it — see below.
- **Errors and latency degradation remain independently observable without
  relying on trace sampling at all.** Operational metrics (R20-S02) are
  not sampled the way traces are and cover *every* eligible request/
  operation — they remain the authoritative source for rates and latency
  distributions regardless of whether any given request's trace was
  retained. Structured logs (R20-S01) cover bounded lifecycle, warning,
  and failure events per the event vocabulary defined above; logs are
  **not** required to emit one line for every successful request unless an
  explicitly justified access-log policy is separately adopted, and this
  document does not adopt one. **Logs are never used as an SLI
  denominator** — metrics own that role, per "Four signal types" above.
- **Guaranteed retention of every error/slow trace requires Collector tail
  sampling, or a production backend with equivalent capability, and
  remains a deferred, future Phase 22 enhancement**, not part of this
  document's V1 design.

**Collector-applied probabilistic sampling versus Collector tail sampling,
critiqued against the brief's exact criteria — this is now a choice
between two mechanisms that both live in the Collector, not a choice
between the SDK and the Collector:**

- **Cross-service trace integrity**: the trace-ID-hash decision this
  document selects keeps a trace's outcome consistent across every service
  it touches (any Collector instance derives the same decision from the
  same trace ID), without requiring the whole trace to be assembled first.
  Tail sampling (buffering a whole trace and deciding afterward) requires
  every span belonging to one trace to reach the *same* Collector instance
  before a decision can be made.
- **Errors and slow traces only fully known at the end**: this remains
  tail sampling's genuine advantage — a tail-sampling policy (retain if
  any span had error status, or total duration exceeded a threshold,
  decided once the whole trace is assembled) can honestly guarantee "all
  errors retained" in a way the selected hash-based design cannot, at the
  cost of buffering the whole trace before deciding.
- **Collector memory/state and multiple Collector replicas**: this is
  specifically a tail-sampling constraint, not one the selected
  trace-ID-hash design shares — tail sampling requires either a single
  Collector instance or a load-balancing layer that routes all spans of
  one trace to the same replica (real additional infrastructure this
  repository does not have and this document does not build); the
  trace-ID-hash processor this document selects needs neither, since its
  decision requires no cross-span buffering or replica affinity at all.
  Verified: today there is exactly one Collector instance in
  `compose.yaml`, so this distinction is currently latent, but it is real
  and is the reason full tail sampling is deferred rather than adopted
  now, independent of today's single-instance topology.
- **Restart behaviour**: a tail-sampling Collector holds in-flight trace
  buffers in memory; a restart loses whatever hadn't yet been decided.
  The selected trace-ID-hash processor holds no such state — a restart
  affects nothing about its decisions, since each is recomputed fresh,
  identically, from the trace ID alone.
- **Production scale and vendor neutrality**: the `probabilisticsampler`
  processor is a standard, vendor-neutral OpenTelemetry Collector
  component, consistent with ADR-0012's own posture, and is the mechanism
  that actually honours ADR-0012's Collector-ownership decision while
  still delivering trace-consistent sampling.

**Verified pinned Collector distribution capability — not assumed, not
deferred to implementation.** The repository pins
`ghcr.io/open-telemetry/opentelemetry-collector-releases/opentelemetry-collector:0.153.0`
(`compose.yaml`) — the official core `otelcol` distribution, not
`otelcol-contrib`. Checked directly against that exact distribution's own
release manifest for `v0.153.0`
(`distributions/otelcol/manifest.yaml` in
`open-telemetry/opentelemetry-collector-releases`): its processor set
already includes `github.com/open-telemetry/opentelemetry-collector-
contrib/processor/probabilisticsamplerprocessor` at the matching
`v0.153.0` version, alongside `batchprocessor`, `memorylimiterprocessor`,
`attributesprocessor`, `resourceprocessor`, `spanprocessor`, and
`filterprocessor`. It does **not** include `tailsamplingprocessor` — tail
sampling would genuinely require a distribution change, consistent with
it being deferred to Phase 22 rather than designed here. **The pinned core
distribution already contains the required component: `probabilistic
samplerprocessor`. No distribution or image change is required for V1
sampling.** This document does not introduce a proprietary sampler or an
application-owned sampling workaround; it configures a real, existing,
already-pinned Collector processor. R20-S03 is required to add a
configuration/component-presence validation test against the actual
pinned image (not a newer release, not an assumption) as part of shipping
this design, so a future image-version bump that ever dropped this
component would be caught rather than silently breaking sampling.

**The Collector is the sole required acknowledgement target for the
sampling-percentage setting.** Laravel and Python no longer acknowledge
sampling enforcement at all — see the corrected required-target manifest
in "Sampling and retention" above, which this section's design directly
implements.

Full tail sampling (or an equivalent-capability managed backend) is named
as a legitimate future upgrade once the buffering/replica-routing
trade-offs above are worth taking on — Phase 22 territory, not designed
here.

### Metrics and SLI coverage matrix

Audited against the verified findings in Context above, organised by
domain, `EXISTS`/`GAP` marked precisely:

**HTTP/application**: request count — EXISTS (both services); error rate
by bounded status class — EXISTS (status code already an allowlisted
attribute; a dedicated bounded-class metric is R20-S02 work); latency
distributions — EXISTS; authentication/authorization failure class without
identity labels — GAP, new metric.

**Ingestion and deletion**: queue depth, oldest-message age — GAP, new
metric (ADR-0008 already frames the need conceptually); claim/lease
duration — GAP; redelivery, DLQ count — GAP; extraction/chunking/embedding
duration — GAP for extraction/chunking (embedding itself has a request/
duration metric, but not a chunking-stage-scoped one); ingestion outcomes
— EXISTS (`rag.processing.outcome` already allowlisted); stuck processing
— GAP, new metric building on ADR-0025's existing "visibly stuck in the
read model" concept; cancellation/quiescence duration — GAP (zero
telemetry exists for the deletion orchestrator at all); document-deletion
duration and failure stage — GAP, same reason; vector-cleanup outcomes —
GAP, same reason.

**Retrieval and generation**: contextualisation latency/outcomes — GAP
(zero telemetry exists for conversation/contextualisation); retrieval
latency/outcomes — GAP as *metrics* specifically (spans exist, counters/
histograms do not); embedding/reranking provider latency — EXISTS for
embedding, GAP for reranking's actual provider call (only a coarser route-
level span exists, and its own reranker-specific attributes are currently
dropped by allowlist drift); evidence counts as bounded histograms — GAP,
new metric (candidate/eligible-scope counts are currently trace attributes
only, not exported as metrics); generation latency/outcomes — GAP, zero
telemetry exists; provider retries/rate limiting — GAP; time to first
accepted part — GAP, new metric on an existing field; whole-run duration —
GAP; streaming completion/retraction/cancellation/timeout — GAP, zero
telemetry exists for `ExecuteGenerationRun` or SSE delivery.

**Dependencies**: database availability/latency — partial (query-duration
histogram exists; no dedicated up/down signal); object-storage
availability — GAP; queue availability — GAP; vector-store availability/
latency — GAP (Qdrant search calls have no span or metric of their own,
only the retriever-level wrapper spans); external provider availability/
rate limiting — GAP for OpenAI and reranking; partial for Voyage embedding
(spans exist; no dedicated availability signal).

This document does not duplicate ADR-0025's tenant-usage/cost persistence
as metric machinery — the coverage above is exclusively operational
(latency, error rate, throughput, dependency health), never a second
accounting of the same token/cost figures ADR-0025 already durably
persists for tenant-facing purposes. Metric names are stable, units
explicit (seconds for duration, not milliseconds mixed with seconds),
histogram boundaries justified by the actual latency profile of each
operation rather than copied uniformly across unrelated domains, and every
label bounded per ADR-0012's existing cardinality rule.

### Trace coverage and sampling: priority findings

The flow-by-flow audit (Context above) surfaces one structural gap and
several coverage gaps, in priority order for R20-S03:

1. **Laravel→Python rc1 trace-context propagation is entirely missing** —
   `RetrievalCallSigner`, `GenerationClient`, and `ContextualisationClient`
   inject only HMAC signing headers, never `traceparent`. Every retrieval/
   generation/conversation call starts a disconnected root trace on the
   Python side. Fixing this is the single highest-value change in this
   document, since it's what makes every other cross-service trace in this
   system actually cohere into one journey.
2. **Ingestion retry and document deletion silently drop trace context at
   the outbox boundary** — `RetryDocumentIngestion` and the deletion
   outbox write both omit `traceparent`/`tracestate` on the `OutboxEvent`
   row, unlike the original `RequestDocumentIngestion` path. A narrow,
   mechanical fix (inject context the same way the original path already
   does), not a design change.
3. **Allowlist drift**: `rag.retrieval.fusion.*`, `rag.retrieval.
   reranker.*`, and `rag.retrieval.sparse_candidate_count` are already set
   on spans and already silently dropped. Extending
   `TRACE_ATTRIBUTE_ALLOWLIST`/`METRIC_ATTRIBUTE_ALLOWLIST` to include them
   (after the same allowlist-safety review every new attribute already
   requires) recovers coverage that already exists in code.
4. **First-instance spans required** for: generation (the OpenAI adapter
   call itself); conversation/contextualisation; reranking's actual
   provider call (`apps/ai/app/reranking/voyage.py`, currently
   uninstrumented even though the coarser route-level span around it
   exists); `ExecuteGenerationRun` job execution (including injecting
   trace context at dispatch time so a queued run can be linked back to
   the web request that triggered it); ingestion retry's own operation;
   the deletion orchestrator; invitation delivery; and Phase 19 admin
   endpoints beyond the generic HTTP-middleware span — **bounded operation
   kind, outcome, subsystem, and correlation identifier only, corrected
   from the first draft's "acting actor and target resource" proposal; see
   "Actor identity stays in business audit, not telemetry" below for why.**

**Preserved from ADR-0012 unchanged**: W3C trace propagation as the
mechanism (fixing the gaps above extends its reach, never replaces it);
the durable `correlation_id` as a separate business identifier, never
unified with the trace ID; no prompt/question/document/answer/evidence
content in any span; external provider spans carry call metadata only,
never request/response bodies; telemetry failure isolation, unchanged.

**Actor identity stays in business audit, not telemetry — corrected from
the first draft, which proposed attaching "the acting actor and target
resource" to Phase 19 admin-endpoint spans.** That reads, on reflection,
as exactly the business-audit/telemetry conflation "Four signal types"
above exists to prevent: *who* performed a sensitive administrative action
is a business-audit fact (ADR-0006/ADR-0025), with its own durable
lifecycle, not an operational-diagnostic one. The corrected rule:

- Actor identity and sensitive administrative target lineage (which
  document, which member, which workspace an admin action touched) live
  exclusively in the existing durable business-audit record — this
  document adds no new place that duplicates that fact.
- Telemetry for these endpoints carries only bounded, already-allowlisted
  operation kind, outcome, subsystem, and the correlation identifier —
  exactly the same shape every other span in this document already uses,
  no richer for admin endpoints than for any other flow.
- No raw email address ever appears in a span or metric attribute, for an
  actor or otherwise.
- Actor identity is never duplicated into logs or traces as a convenience
  — it may be added only where a concrete diagnostic need is demonstrated
  (not assumed in advance) and the specific identifier is explicitly
  reviewed and added to the allowlist, the same bar every other new
  attribute in this document already has to clear.
- Where an operator genuinely needs to connect a specific trace to the
  human who took the corresponding administrative action (for example,
  investigating a reported deletion), the durable correlation identifier
  already present on both the span and the business-audit record is the
  connective link — an operator with legitimate access follows the
  correlation ID from the trace to the audit record, rather than the
  trace carrying the actor directly.

External provider spans (OpenAI, Voyage) never include request/response
body content — verified already true for the one provider call that is
instrumented (Voyage embedding); the same discipline extends to the
provider calls this document adds instrumentation for.

### SLOs and error budgets

**Candidate service journeys**, adopted from the brief: authenticated API
availability; document ingestion from accepted upload to terminal outcome;
conversation submission to terminal technical outcome; first accepted
streamed answer part; document deletion to verified completion; provider
dependency availability.

**Numerator/denominator rules, reusing ADR-0023/ADR-0024's taxonomy
directly rather than inventing a new one** — this is the section where
that taxonomy reuse (see "Relationship to prior ADRs" above) becomes
concrete:

- **Conversation submission to terminal technical outcome** — corrected
  wording from the first draft, which stated the denominator as "any
  terminal state" and only excluded `cancelled` afterward, self-
  contradicting itself. Stated once, correctly: **the denominator is every
  *eligible* terminal `GenerationRun` — every terminal state except
  `cancelled`.** `cancelled` is excluded from both numerator and
  denominator entirely, from the start, because it represents deliberate
  user intent, not a system outcome to be graded either way, matching
  ADR-0024's own reasoning for excluding it from the `failure_code` enum.
  Within that eligible-terminal-run denominator, the numerator (success)
  is every state that is *not* a genuine technical failure: `completed`,
  `retrieval_no_answer`, and `clarification_required` all count as
  successful technical execution, because ADR-0024 already defines them as
  controlled, correctly-reached outcomes, not failures. Only `failed`
  counts against the SLO.
- **Generation quality is explicitly not what this SLO measures** —
  `ANSWERED`/`QUALIFIED`/`INSUFFICIENT_EVIDENCE` are all instances of
  `completed` and all count as successful technical execution regardless
  of which one occurred; whether the evidence was actually sufficient is a
  product/evaluation question (ADR-0019's territory), not an availability
  SLO's.
- **`GENERATION_CONTEXT_BUDGET_EXCEEDED`** counts as a technical failure
  for SLO purposes (it is a `failure_code` value under ADR-0024), while
  remaining, for evaluation purposes, distinct from `INSUFFICIENT_
  EVIDENCE` exactly as ADR-0023 already requires — the SLO and the
  evaluator are allowed to classify the same event differently because
  they're answering different questions.
- **Rejected invalid input and unauthorized requests are excluded from the
  API-availability denominator — but not every `4xx` response is
  automatically irrelevant, a distinction the first draft's "a `4xx`
  correctly returned... is the system working as designed" wording
  blurred.** The exclusion applies specifically to `4xx` responses that
  reflect the system *correctly* rejecting a genuinely invalid or
  unauthorized request — that is the case that represents no reliability
  problem. A `4xx` produced by a genuine platform defect (for example, a
  broken validation rule incorrectly rejecting well-formed input, or an
  authorization check malfunctioning and denying a legitimately authorized
  request) is not "the system working as designed" and must not be
  silently excluded merely because its status code happens to start with
  4 — that would hide a real defect behind the same exclusion meant only
  for expected, correct rejections. Distinguishing the two in practice is
  R20-S02 metric-design work (e.g. keyed on a bounded, reviewed set of
  expected-rejection reasons rather than on status-code range alone), not
  something this document resolves by treating the whole `4xx` class as
  one undifferentiated case.
- **Document deletion to verified completion** — the denominator is every
  `DocumentDeletionOperation` reaching a terminal state; the numerator is
  every one reaching verified `DELETED`. A deletion stuck awaiting
  quiescence beyond a bounded threshold counts against the SLO once that
  threshold is crossed, reusing ADR-0025's own "visibly stuck" concept
  rather than inventing a separate staleness definition.

**Calibration, not invention:** latency targets are **not** fixed by this
document. This document establishes the SLI (what is measured, and its
numerator/denominator) and requires calibration from measured staging/
local evidence before any final latency threshold is set — consistent
with the brief's explicit instruction not to present a pre-production
objective as an achieved historical guarantee. **Provisional, explicitly-
labelled-as-unmeasured initial targets**, offered for discussion rather
than asserted as final: 99.0% authenticated-API availability and 99.0%
conversation-technical-success, calibrated over a rolling 28-day window —
chosen as a deliberately modest, single-nines target appropriate for a
pre-production, sole-operator system, not a promise this document has any
evidence to back yet. The trade-off: a laxer target reduces false alarms
during exactly the period (early production) when the operator can least
afford alert fatigue, at the cost of not yet meaningfully constraining
genuinely degraded behaviour — acceptable for V1, revisited once real
traffic produces real measurements.

**Error-budget treatment**: a standard rolling-window burn-rate model is
adopted conceptually (the SLO defines an acceptable failure rate over a
window; consuming that budget faster than the window allows signals a
problem before the SLO is technically breached). **Multi-window burn-rate
alerting (the common SRE pattern of combining a fast, short-window and a
slow, long-window check to catch both sudden and slow-burning problems) is
named as the right eventual shape but not built for V1** — it requires
calibrated thresholds this document does not yet have evidence for, and a
single-window "consumed budget exceeds N% with sustained duration" check
is the honest, smallest V1 alerting rule (see next section).

### Alerts and runbooks

**Candidate alert families**, adopted from the brief, each required to
define: user/platform impact; severity; signal/query; threshold and
duration; owner/response expectation; immediate checks; remediation;
escalation; recovery condition; false-positive/noise considerations; and
Grafana/dashboard/trace deep links where possible. This document does not
enumerate the full text of every runbook (that is R20-S04 implementation
content, appropriately living in `IMPLEMENTATION_GUIDE.md`/a runbook
document, not this ADR) — it requires every alert to carry these fields
and forbids shipping an alert without them.

Families: API availability/error-rate degradation; sustained latency/SLO
burn; queue backlog or oldest-message age; DLQ messages; ingestion failure
spike; stuck leases; stuck document deletions; database unavailable;
object storage unavailable; vector store unavailable; provider failures/
rate limiting; generation timeout increase; streaming technical failure
increase; telemetry pipeline/backend unavailable; storage/capacity
concern.

**Explicitly excluded from paging**, per the brief and consistent with the
SLO numerator/denominator rules above (these are precisely the controlled
outcomes just excluded from failure counting, so alerting on them
individually would contradict the SLO design): one isolated provider
timeout; one controlled no-answer; one user cancellation; expected local-
development outages.

**Transport and vendor scope**: Alertmanager-compatible rules and a
configurable receiver seam are built now; commitment to a specific paging/
Slack/email vendor is deferred to Phase 22's production-infrastructure
selection — consistent with ADR-0012's own precedent of separating
emission architecture from backend/vendor selection. Locally, alerts must
be inspectable and testable (a rule firing against synthetic/local data,
visible in Grafana/Alertmanager's own UI) without contacting a real
person.

**Severity and response model, honest about scale**: two severities,
`warning` and `urgent`. `Urgent` means user-facing platform capability is
degraded or unavailable now; `warning` means a leading indicator of future
degradation (budget burn, approaching capacity) that does not yet affect
users. This document does **not** claim a 24/7 staffed on-call rotation —
it defines a response model appropriate for a small/sole-operator product:
`urgent` alerts are delivered promptly (channel selection deferred to
Phase 22) with an expectation of same-day, best-effort response; `warning`
alerts are reviewed on a regular cadence, not paged individually. This is
named explicitly so no future reader mistakes the alerting configuration
for evidence of a staffed incident-response team that does not exist.

### Main-admin alert and SLO presentation

Dolved's main platform admin shows curated: active alert summary; severity;
affected subsystem; start time/duration; SLO status; current error-budget
state where available; telemetry freshness; a deep link to the relevant
Grafana/Alertmanager view. Raw logs or trace data are never copied into
the main admin — this is a summary/status surface, not a second copy of
the specialist tooling's data.

**Alert acknowledgement/silencing, critiqued as requested**: the brief's
preliminary preference (main admin displays status only; Grafana/
Alertmanager owns acknowledgement/silencing; Dolved links there rather
than rebuilding alert management) is adopted as-is. The critique: building
acknowledgement/silencing into Dolved V1 would require Dolved to either
proxy Alertmanager's own API (real integration work for a capability
Alertmanager already provides for free) or maintain a second, parallel
notion of "acknowledged" that could drift out of sync with Alertmanager's
own state — exactly the "second observability system" this document's
governing principle exists to prevent. Linking out is not a compromise; it
is the architecturally correct choice given the governing principle,
independent of how much V1 effort acknowledgement UI would take.

### Privacy, tenancy and security

Reaffirms ADR-0012's allowlist-first posture, extended to logs (which
ADR-0012 itself did not originally cover — see "Structured logging
boundary" above) and to this document's new dashboard/adapter surfaces.

**Required negative coverage**: no document text in logs/spans; no
questions/prompts/answers/evidence; no invitation tokens; no
authorization/HMAC material; no user-supplied arbitrary metric queries (the
adapter's predefined-query-only design above); no workspace IDs as metric
labels; no workspace-admin access to platform operations (the new
platform-administrator Gate is additive authorization, never inherited
from any workspace role); no public production observability ports; no
backend credentials returned to browsers; no telemetry configuration
mutation by ordinary workspace roles (only platform administrators may set
desired sampling/retention policy); no cross-tenant usage visible through
the operational dashboard (global metrics only, per the boundary section
above).

Operational identifiers in logs/traces (workspace/document/conversation/
run IDs) require restricted access and bounded retention even though they
are not document content — restricted, because in aggregate they could
reveal usage patterns; bounded, because "safe to log" is not the same
claim as "safe to retain indefinitely."

### Failure behaviour

Extends ADR-0012's existing correctness-isolation invariant to every new
surface this document introduces, rather than inventing a parallel
principle:

| Failure | Required behaviour |
|---|---|
| Collector unavailable | Existing no-op fallback (ADR-0012); unchanged |
| Metric export times out | Existing SDK backpressure behaviour; unchanged |
| Log shipping unavailable | No effect — logs write to stdout/stderr directly, independent of the Collector path |
| Metrics query backend unavailable | Adapter returns explicitly-unavailable, never zero; dashboard shows staleness, not false health |
| Grafana unavailable | Main-admin deep links degrade gracefully (a broken/unavailable-labelled link, never a silent failure); ordinary Dolved use is entirely unaffected, since Grafana is never in the request path of anything else |
| Desired policy cannot be applied | Remains visibly `PENDING`/`FAILED`; never silently claimed as active |
| Configuration reconciliation partially fails | Surfaced per the versioned desired-state model above; ordinary application correctness unaffected |
| Alert delivery fails | Diagnosable via Alertmanager's own state; does not create a recursive dependency on the telemetry pipeline it's alerting about, since Alertmanager's own liveness is itself one of the alert families above |
| Dashboard query times out | Bounded timeout (adapter requirement above); explicit timeout state shown, not a hang |
| Trace context malformed/missing | New root trace/span; never a hard failure of the request carrying it |

The ordinary Dolved application continues operating through every failure
mode above without exception — this table is the concrete instantiation of
the governing principle's implicit promise, not a new commitment.

### Explicitly deferred work

Deliberate V1 boundaries: selecting a commercial production observability
vendor; public Grafana access; embedding Grafana; a custom log-search or
trace-waterfall UI (Grafana already provides this); billing/invoicing; a
fully staffed 24/7 on-call programme; vendor-specific paging integrations;
production network topology and identity-aware-proxy implementation
(Phase 22); final production capacity planning; final latency SLO
thresholds until measured evidence exists; organisation-wide custom
dashboard builders; arbitrary tenant-defined telemetry queries; browser
session replay; capture of prompts/questions/answers for debugging (would
require an explicit, separately-scoped, opt-in mechanism per ADR-0012's
own precedent, not built here); automatic remediation that mutates domain
state; the read-only-viewer and editor-responder authorization tiers
beyond their naming; full Collector tail sampling and multi-replica
Collector scaling; multi-window burn-rate alerting.

## Architectural invariants

- OpenTelemetry remains the platform's one canonical instrumentation API;
  this document adds no second SDK, wrapper, or trace/context system.
- No metric ever carries workspace, user, document, message, conversation,
  run, or correlation ID as a label; per-entity granularity stays in
  traces, never in metrics.
- Business audit, tenant usage (ADR-0025), and operational telemetry
  (this document) remain three distinct systems with three distinct
  retention lifecycles; none substitutes for another.
- A telemetry, logging, dashboard, or alerting failure never causes a
  user-facing request or background job to fail, and never affects
  ordinary Dolved application correctness, including ordinary workspace
  administration.
- The operational-metrics reader accepts no browser-supplied query
  language; every query it can run is predefined and server-owned.
- Platform-administrator authorization is strictly additive and forms a
  completely separate authority plane from workspace roles: no workspace
  role — including a workspace owner — can grant it, and holding it grants
  no workspace membership or authority in return. Effective access is
  derived live, on every request, from `users.public_id`/`platform_role`/
  `disabled_at`, never cached or trusted from a session payload.
- The initial platform administrator, and every subsequent grant or
  revocation absent an explicitly justified management screen, is
  provisioned only through a non-browser Laravel command authenticated by
  a dedicated platform-administration deployment credential — never a
  browser-reachable, self-service, or invitation-driven path, and never
  the same credential used for ingestion, deletion, or observability-
  reconciliation purposes. Revoking the last active platform administrator
  is refused unless an explicit replacement/recovery procedure — itself
  requiring the same deployment credential — is underway.
- Every platform-role grant or revocation, its `PlatformAdministrationCommand`
  idempotency record, and its `PlatformAdministrationAuditEvent` commit in
  one transaction; a crash never leaves an unaudited platform-role
  mutation. Platform-level audit records carry no workspace foreign key
  and are never forced into ADR-0025's workspace-scoped audit shape.
- Anonymous Grafana admin access may exist only where the port is
  loopback-bound; it must never survive into any non-loopback-reachable
  environment.
- A desired sampling/retention value saved in Laravel is never presented
  as applied until every target required for that specific setting has
  produced a matching, authenticated reconciliation acknowledgement against
  the outstanding desired record; a stale, mismatched, replayed, or
  conflicting acknowledgement never advances that setting's state, and
  each setting/target pair carries its own status rather than one blanket
  `ACTIVE` for the whole policy or for any single component (such as the
  Collector) standing in for all of them.
- A logical deployment/application attempt always carries its own fresh
  `deployment_attempt_id` and is recorded as an immutable, append-only
  history; a failed attempt never blocks or is overwritten by a later
  attempt against the same still-current policy version, and only the
  attempt currently held by that setting/target's current-attempt pointer
  may derive its present `ACTIVE`/`PENDING`/`FAILED` state.
- **Sampling enforcement belongs to the Collector, per ADR-0012's
  unchanged ownership boundary — restored here after an intervening
  revision briefly misplaced it in application-service SDK configuration.**
  Application services emit every span unfiltered and run no competing
  ratio sampler of their own; the Collector alone decides retention, via a
  trace-ID-hash decision so every span of one trace shares the same
  outcome. A setting's `ACTIVE` state is never claimed from a digest
  comparison unless a genuine expected digest was established for that
  target in advance, and only a successfully authenticated reconciliation
  acknowledgement can advance it.
- A trace's retention decision is a deterministic function of its trace
  ID, made independently of whether the operation it describes is known
  to succeed or fail, and is never retroactively altered by a later
  status/exception recorded on an already-unsampled span; Phase 20
  guarantees neither that every error trace nor that every slow trace is
  retained.
- Unavailable operational data is always shown as unavailable, never as
  zero.
- SLI numerator/denominator selection reuses ADR-0023/ADR-0024's existing
  outcome taxonomy exactly; a controlled outcome (`retrieval_no_answer`,
  `clarification_required`, any of `ANSWERED`/`QUALIFIED`/
  `INSUFFICIENT_EVIDENCE`) is never counted as a technical failure, and
  `cancelled` is never counted as either a success or a failure. A `4xx`
  response is excluded from an availability denominator only when it
  reflects a correctly-rejected invalid/unauthorized request, never by
  status-code range alone.
- Actor identity and sensitive administrative target lineage live in
  business audit, never in telemetry by default; a span or metric never
  carries a raw email address.
- No production observability port (Collector, Prometheus, Tempo, Loki,
  Grafana, Alertmanager) is ever publicly exposed.

## Alternatives considered

### Rebuilding all observability in Phase 20

Rejected. ADR-0012's foundation is real, working, tested code — rebuilding
it would discard a proven investment to solve a problem (dashboards,
alerting, sampling, retention, coverage completeness) that doesn't require
touching the foundation at all.

### Exposing only Grafana with no Dolved operational dashboard

Rejected. Grafana requires specialist query-language literacy for even
routine "is the platform healthy" questions, and would force every
operational glance through the private, higher-friction console this
document deliberately reserves for deep diagnosis. A curated main-admin
view answers the routine question cheaply; Grafana remains for when the
routine view isn't enough.

### Embedding Grafana in an iframe

Rejected. Requires disabling Grafana's own frame-protection or accepting a
fragile, likely-broken embed, and blurs the curated-versus-specialist
distinction this document's governing principle depends on. A deep link
that opens Grafana in its own tab achieves the same practical outcome
without either cost.

### Copying operational time series into PostgreSQL

Rejected. The metrics backend is already the authoritative, purpose-built
store for time-series data; duplicating it into Postgres would create a
second, inevitably-drifting source of truth for exactly the kind of high-
volume data Postgres is not designed for, for no benefit the adapter
pattern above doesn't already provide.

### Allowing browser-authored PromQL

Rejected. Removes every safety property the bounded, server-owned,
allowlisted-query adapter provides — arbitrary query cost, arbitrary
information disclosure across whatever the backend can see, and a direct
path for a platform administrator's browser session (if ever compromised)
to pivot into unrestricted backend access.

### Using workspace IDs as metric labels

Rejected. Directly violates ADR-0012's existing cardinality discipline and
would make workspace-count-scale label cardinality a metrics-backend
storage/query-cost problem; ADR-0025's own application records already
serve the legitimate version of this need.

### Centralising browser console logs by default

Rejected. No browser-side error tracking exists today and this document
does not add it by default — doing so would risk capturing user-entered
content (questions, form input) in a client-side environment this
document's server-side allowlist discipline doesn't reach. Named
explicitly as deferred, not silently omitted.

### Choosing a backend-specific SDK in application code

Rejected for emission, exactly as ADR-0012 already decided; the one
deliberate, isolated exception is the read-side operational-metrics
adapter, justified above precisely because OpenTelemetry defines no
general query API and the exception is kept in one narrow class.

### Retrofitting an always-sampled exception for spans that self-report an error after creation

Rejected — this was an early draft's actual recommendation, and it is
withdrawn as unsound, not merely insufficient. An OpenTelemetry sampling
decision is made once, before the outcome of the operation is known, and
cannot be reversed by later setting `STATUS_ERROR` or recording an
exception on an already-unsampled span. No design in this document,
Collector-owned or otherwise, relies on this mechanism.

### SDK-side application sampling (Laravel/Python each running their own ratio sampler)

Rejected — this was an intervening draft's actual V1 recommendation, and
it is withdrawn as a direct contradiction of already-accepted architecture,
not merely a style preference. ADR-0012 explicitly assigns sampling
ownership to the Collector, not application code; an SDK-side sampler in
Laravel and Python would have made those services, not the Collector, the
component actually deciding trace retention. **Collector-owned
`probabilisticsamplerprocessor` sampling — verified present in the pinned
Collector distribution, applying a trace-ID-consistent decision, with no
competing application-side ratio sampler — is the adopted V1 design
instead**, restoring ADR-0012's ownership boundary while still exercising
the one decision (which strategy, at what baseline) ADR-0012 explicitly
left open. This does not guarantee error/slow-trace retention any more
than the withdrawn SDK-side design would have — that limitation is
independent of which side of the pipeline applies the decision, and is
accepted honestly either way.

### Tail sampling in the Collector

Rejected for V1 — evaluated in full above; the strongest error/slow-trace
guarantee available, but requires either a single Collector instance or a
trace-routing layer this repository does not have, making it a legitimate
future upgrade rather than a safe V1 default given the current
single-instance, unscaled Collector. This constraint is specific to tail
sampling's need to buffer a whole trace before deciding — it does not
apply to the trace-ID-hash `probabilisticsamplerprocessor` this document
actually adopts, which requires no buffering and no replica affinity.

### Retaining 100% of production traces indefinitely

Rejected. Directly contradicts the stated retention targets and would make
storage cost scale with total request volume rather than a deliberately
bounded, trace-ID-consistent sample — the traced information per request
is diagnostic, not the kind of record this platform commits to keeping
forever.

### Treating controlled no-answer/cancellation as failures

Rejected — evaluated in full under "SLOs and error budgets" above; would
directly contradict ADR-0023/ADR-0024's own settled outcome taxonomy and
would make ordinary, correctly-functioning product behaviour look like a
reliability problem.

### Setting arbitrary latency SLOs before measurement

Rejected. Evaluated under "SLOs and error budgets" above — this document
defines the SLI and requires calibration before any final threshold,
consistent with not presenting a pre-production guess as an achieved
guarantee.

### Paging on every isolated failure

Rejected. Named explicitly under "Alerts and runbooks" above as excluded —
would produce alert fatigue disproportionate to actual user impact for a
system where isolated provider timeouts and controlled outcomes are
expected, routine behaviour.

### Letting the main admin directly rewrite application-SDK, Collector, or backend configuration

Rejected. Evaluated under "Sampling and retention" above — none of the
verified enforcement targets (application-process SDK configuration,
Collector configuration, or backend retention settings) is hot-reloadable
from a running web request today; building an endpoint that claims to
rewrite any of them directly would either be dishonest about what actually
happens or would require unsafely triggering a process/container mutation
from a web request. This applies uniformly across every enforcement
target this document identifies, not only the Collector — an earlier,
narrower framing of this alternative considered only the Collector and is
corrected here to match the full multi-target model. The desired-state/
reconciliation split, with per-setting, per-target acknowledgement, is the
only design that doesn't overclaim.

### Building alert acknowledgement/silencing into Dolved V1

Rejected — evaluated in full under "Main-admin alert and SLO presentation"
above; would duplicate Alertmanager's own, already-adequate capability and
risk state drift between two competing notions of "acknowledged."

### Treating business audit and telemetry as the same data

Rejected. Evaluated in full under "Four signal types" above and already
settled by ADR-0025's own explicit boundary statement; conflating the two
would give business-audit records the wrong retention lifecycle (too
short) or telemetry the wrong one (indefinite, unbounded storage growth).

## Consequences

### Positive

- The single highest-value fix in this document — Laravel→Python trace
  propagation — is small, mechanical, and unlocks coherent cross-service
  tracing for every flow this platform has, not just the ones this
  document happens to add new spans to.
- Every new capability (dashboard, adapter, sampling/retention control,
  alerting) reuses an existing, proven pattern — the outbox's own
  observability framing, ADR-0025's audit shape, ADR-0023/0024's outcome
  taxonomy, ADR-0012's allowlist/isolation invariants — rather than
  inventing parallel mechanisms.
- Naming the anonymous-Grafana-admin configuration and the platform-
  administrator gap explicitly, now, means Phase 22 inherits an accurate
  precondition list instead of discovering both as surprises during
  production-readiness work.
- The coverage matrix gives R20-S02/S03 a precise, verified punch list
  instead of a vague "add metrics/tracing" mandate.

### Negative

- The sampling strategy is honestly weaker than "all errors retained" in
  the whole-trace sense — a real, named limitation of probabilistic
  sampling generally, not a solved problem, until tail sampling is
  eventually built.
- The sampling/retention reconciler is, in V1, a deployment-time process,
  not a live control — "configurable from the main platform-administration
  area" is honestly a desired-state form, not an instant lever, given that
  none of the verified enforcement targets (the Collector, or the
  retention backends) is hot-reloadable today. Coordinating per-setting,
  per-target acknowledgements (some settings requiring more than one
  target, such as the slow-operation threshold's application-side and
  alerting-rule consumers) with honestly-scoped digest verification, plus
  a dedicated authenticated reconciliation protocol, is itself genuine new
  protocol and persistence work — materially more surface than a single
  Collector-restart model would have been, in exchange for not overclaiming
  what one restart actually covers, and for keeping sampling ownership
  where ADR-0012 already put it.
- Introducing a platform-administrator concept is substantial new
  authorization surface this document specifies but does not implement:
  new `users` schema (`public_id`, `disabled_at`, `platform_role`), a
  dedicated bootstrap deployment credential with its own rotation model, a
  non-browser provisioning/grant/revoke/recovery command, and new
  platform-scoped command/audit record types — genuinely more than a
  single Gate check, not a wiring exercise or a browser-facing feature.
- First-instance instrumentation for generation, conversation, reranking's
  provider call, `ExecuteGenerationRun`, and deletion is substantial new
  work across two languages, not incremental tuning of what already
  exists.
- The Grafana anonymous-admin finding, while currently safe, is a real
  configuration debt item that must be tracked through to Phase 22 rather
  than assumed fixed by this document alone, since this document does not
  implement the SSO/MFA replacement itself.

## Scope boundaries

This document does not define: exact Laravel/Python class or migration
names beyond what's structurally required to state each decision; exact
Grafana dashboard panel layouts or PromQL/LogQL query text; final
production latency SLO thresholds; a commercial observability vendor;
production network topology, identity-aware-proxy implementation, or
paging-vendor selection (Phase 22); a granular operations permission
engine beyond the three-tier separation named and mostly deferred; or
billing/invoicing. It does not redecide anything ADR-0006, ADR-0008,
ADR-0009, ADR-0012, ADR-0023, ADR-0024, or ADR-0025 already settled.
`tasks.json`, `PROJECT_ROADMAP.md`, `IMPLEMENTATION_GUIDE.md`, and the ADR
index are not modified by this document — those updates, and marking this
ADR `Accepted`, remain for a separate review step.
