# R30-S02 — Establish Privacy-Safe Dolved–Dify Pilot Observability

Date: 21 November 2026
Status: Verified implementation and deployment; hands-on acceptance remains in R30-S04.

## Reconciliation

The existing Platform Operations overview, telemetry, alerts and policy routes remain the
global operator surfaces. Existing workspace usage remains the tenant surface and was
extended with chat, retrieval/source and Dify document-processing summaries. The approved
Question review specification is implemented as a separate restricted application feature;
question content is not telemetry.

## Delivered

- Pinned OpenTelemetry Collector 0.153.0, Grafana OTEL LGTM 0.29.2 and Alertmanager 0.33.1
  added to the production pilot definition.
- OTLP, Grafana and Alertmanager host ports restricted to `127.0.0.1`; bounded resources and
  logs retained.
- The existing allowlist-first Laravel instrumentation is enabled. The Collector accepts
  traces and metrics only; it does not export customer questions, answers, prompts,
  passages, cookies, credentials or headers.
- Existing Prometheus rules and Alertmanager routing are mounted unchanged. Specialist
  consoles remain private. Raw ports are localhost-only; platform administrators may use
  the authenticated same-origin gateway paths `/__ops/grafana/` and `/__ops/alerts/`
  without a laptop tunnel.
- Restricted Question review is opt-in, initially derives review permission from current
  tenant ownership, defaults retrospective access off, supports a separately confirmed
  maximum 30-day retrospective window, audits wording reads and maintains manual subjects,
  review labels and knowledge-gap summaries.

## Verification

- 25 focused Laravel tests / 213 assertions passed across telemetry, Platform Operations,
  Question review, workspace locations and usage.
- 3 focused frontend files / 16 tests passed.
- Production Compose and strengthened preflight validation passed.
- Release archive SHA-256:
  `79ccaef19fd541dbc013960c7d42dfd0a06c7547c9fecfdce094ec82f9a15f66`.
- Provider-free live verification passed. All three observability services and the
  application containers were healthy. The operational reader returned nine current
  signals and available alert state.
- The authenticated gateway release passed Caddy validation and full production
  verification. External unauthenticated requests to both specialist paths returned HTTP
  401, while raw Grafana and Alertmanager host ports remained bound to `127.0.0.1`.
- A workspace Usage timing type error discovered in hands-on acceptance was corrected and
  regression-tested; the deployed 30-day query completed successfully.
- The first browser check then showed that console authorisation lacked Sanctum's trusted
  SPA origin and that the alert reader omitted Alertmanager's configured route prefix.
  Commit `a321b256` corrected only those routes. Provider-free production verification
  passed; alert state was available with zero active alerts, and unauthenticated console
  requests remained HTTP 401.
- No retrieval, generation, credential-validation or paid provider call was made.

The remaining R30-S04 work is a hands-on permission, retention, failure-state and responsive
acceptance run. This journal does not claim an availability SLA, answer quality or a closed
pilot gate.
