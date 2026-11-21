# R30-S03 — Deploy the Invitation-Only Prototype/Pilot

Date: 21 November 2026
Status: In progress — hosted deployment verified; consolidated acceptance remains.

The Dolved frontend and Laravel runtime, pinned stock Dify, persistent application stores,
background services and the private observability stack are deployed on the approved OVH
pilot host. Public access is limited to the intended HTTPS application/API endpoints;
Dify, OTLP, Grafana and Alertmanager specialist interfaces remain on the private backplane
or localhost-only bindings.

The 21 November production release archive has SHA-256
`79ccaef19fd541dbc013960c7d42dfd0a06c7547c9fecfdce094ec82f9a15f66`. Strengthened
preflight validation and the complete provider-free running check passed after deployment.
The public pilot and API returned HTTP 200 and the application, worker, scheduler, Dify and
observability containers reported healthy state.

Deployed image identities recorded at verification:

- API: `sha256:489fc28f3b7f565e733e13ab54e314baf676c96bc4808950b5912d5c50c1998b`
- Web: `sha256:3085765d48d0c9be31437447bcecb849fe529e5798e18b604f2b152e95afad79`
- Collector: `sha256:6ed874ea083d67a3085acfc75343f2ad11d9abe6006d7ea4a16e2dba9af14e49`
- Grafana OTEL LGTM: `sha256:af7242c1a9608faf6d26e6f235392fd0c32b67258228f9a3cfc96e724974930c`
- Alertmanager: `sha256:9e082985f56f4c8c9f724e18f2288c6708f472e56a5286b8863d080434ea065d`

DNS/HTTPS, dedicated Dify credentials, SMTP, configurable tenant branding, document
applicability, bounded Dify conversation memory and private telemetry are active. No paid
retrieval, generation or evaluation call was used for this deployment.

R30-S03 remains in progress until the consolidated hosted acceptance confirms the complete
document lifecycle, permissions, responsive presentation, source-link access, operational
failure states and the final rollback/handoff record. This is not a production-readiness or
pilot go/no-go claim.
