# Dolved operational alert runbooks

Status: Active operational guidance
Owner: Platform operator
Policy: `dolved-operational-slo-v1`

These runbooks implement Accepted ADR-0026. Alerts describe platform impact,
not product-quality judgements. A controlled no-answer, clarification, user
cancellation or isolated recovered provider timeout is not an incident.

## Response model

`urgent` means a user-facing capability is degraded now. It receives prompt,
same-day best-effort investigation; it does not imply a staffed 24/7 rotation.
`warning` is a leading indicator reviewed on the regular operator cadence.
Alertmanager owns grouping, inhibition, acknowledgement and silencing. Dolved's
platform operations page is read-only and links to Alertmanager/Grafana.

For every alert:

1. confirm Alertmanager state and telemetry freshness;
2. determine user impact before changing anything;
3. correlate metrics with traces by operation stage and correlation ID;
4. preserve typed failures and durable state-machine truth;
5. avoid manual completion, retry amplification or mid-incident tuning;
6. close only when the rule's recovery condition is sustained.

## `DolvedApiTechnicalErrorRateHigh`

- Impact: authenticated operations may fail.
- Signal: eligible API request rate is non-trivial and five-minute technical
  availability is below 95% for ten minutes.
- Immediate checks: deployment lineage, API logs, database/object-store health,
  dependency alerts and representative failing traces.
- Remediation: roll back a confirmed bad deployment or restore the failed
  dependency through its normal operational procedure.
- Escalation: treat persistent cross-route failures as an urgent platform
  incident.
- Recovery: API technical availability remains at or above 95% for one complete
  evaluation interval and representative requests succeed.
- Noise note: expected 4xx rejections are excluded; low traffic is gated.

## `DolvedConversationTechnicalSuccessSloBreach`

- Impact: users may not reach any valid terminal conversation outcome.
- Signal: eligible terminal runs fall below 99% technical success for fifteen
  minutes.
- Immediate checks: `generation_run` failure categories, queue progress,
  provider/dependency state and correlated traces.
- Remediation: restore the failing technical component. Do not change evidence
  thresholds, retrieval, planner or generation semantics during response.
- Recovery: eligible terminal runs return to at least 99% technical success.
- Noise note: `completed`, `retrieval_no_answer` and
  `clarification_required` are successes; `cancelled` is absent from both
  numerator and denominator.

## `DolvedQueueBacklogGrowing`

- Impact: asynchronous ingestion or generation may complete late.
- Signal: queue depth remains above 100 for fifteen minutes.
- Checks: oldest age, worker health, lease ownership, redelivery and downstream
  dependencies.
- Remediation: restore unhealthy workers/dependencies; change concurrency only
  after capacity evidence.
- Recovery: depth falls below 100 and continues trending down.

## `DolvedQueueOldestMessageStale`

- Impact: user work may be stuck.
- Signal: oldest message age exceeds five minutes for ten minutes.
- Checks: visibility/lease duration, active worker, poison-message typed error,
  queue and DLQ counts.
- Remediation: use normal lease expiry/idempotent reclaim; never edit attempts
  or document state manually.
- Recovery: oldest age remains below five minutes and work progresses.

## `DolvedDeadLetterMessagesPresent`

- Impact: at least one document cannot complete normally.
- Signal: a bounded DLQ has any visible message for one minute.
- Checks: typed failure category, original event/attempt lineage, provider and
  dependency state.
- Remediation: diagnose first; recover only through the normal worker path and
  stop on any non-transient correctness failure.
- Recovery: DLQ is empty and corresponding durable attempts are truthful.

## `DolvedIngestionFailureSpike`

- Impact: new documents may fail to become searchable.
- Signal: failures exceed 10% of completed-or-failed ingestion outcomes for
  fifteen minutes.
- Checks: failure categories, source formats, worker/provider/dependency state,
  canonical chunk and queue reconciliation.
- Remediation: correct a demonstrated systemic cause; do not weaken ingestion
  contracts or manually mark documents complete.
- Recovery: failure ratio remains at or below 10% and queue progress is normal.

## `DolvedStuckOperation`

- Impact: ingestion, generation or deletion may never reach a terminal state.
- Signal: any stage reports a stale operation for ten minutes.
- Checks: named stage, lease/timeout, owning worker, durable state and last
  correlated trace.
- Remediation: restore normal state-machine progress or allow bounded lease
  recovery. Never fabricate completion.
- Recovery: stale count is zero and affected operations reconcile normally.

## `DolvedDependencyUnavailable`

- Impact: operations using the labelled dependency may fail.
- Signal: dependency availability remains below one for five minutes.
- Checks: probe the dependency directly, then examine call latency, typed errors
  and recent configuration/deployment changes.
- Remediation: restore the dependency or its connection configuration.
- Recovery: the availability gauge remains one and representative operations
  complete.

## `DolvedProviderRateLimitingSustained`

- Impact: retrieval/generation may slow or exhaust retry budgets.
- Signal: bounded `rate_limited` failures continue for ten minutes.
- Checks: provider attempts, 429 count, Retry-After/reset headers, shared
  cooldown delays and Laravel outer retries.
- Remediation: respect the configured cooldown. Do not increase attempts,
  shorten delays or tune retrieval during diagnosis.
- Recovery: provider operations complete without terminal rate-limit failure.

## `DolvedOperationLatencyHigh`

- Impact: platform operations may feel slow.
- Signal: stage p95 exceeds the current five-second operational slow threshold
  for fifteen minutes.
- Checks: identify the stage; compare dependency, queue and provider timings.
- Remediation: address the measured bottleneck without changing correctness
  semantics.
- Recovery: stage p95 remains below the reconciled threshold.
- Noise note: this is a provisional diagnostic threshold, not a final production
  latency SLO. The rule value must remain aligned with operational policy.

## Deferred alert families

Object-storage capacity and host/disk capacity alerts are not enabled in V1:
the current local stack exposes no truthful bounded capacity signal and no
production capacity has been selected. Adding arbitrary byte limits would
create false confidence. Production infrastructure selection in Phase 22 must
add capacity metrics, measured thresholds and matching runbooks before enabling
those alerts. Multi-window burn-rate alerts and final latency objectives are
also deferred until representative traffic supports calibration. A
telemetry-absence alert is likewise deferred until deployment-environment
identity can distinguish an idle local stack from an unavailable production
pipeline; backend adapter failure already appears as `Unavailable` rather than
being fabricated as a healthy empty result.
