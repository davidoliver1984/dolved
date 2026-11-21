# R30-S03 — Deploy the Invitation-Only Prototype/Pilot

Date: 21 November 2026
Status: In progress — hosted deployment verified; consolidated acceptance remains.

The Dolved frontend and Laravel runtime, pinned stock Dify, persistent application stores,
background services and the private observability stack are deployed on the approved OVH
pilot host. Public access is limited to the intended HTTPS application/API endpoints;
Dify, OTLP, Grafana and Alertmanager specialist interfaces remain on the private backplane
or localhost-only bindings. Grafana and Alertmanager are now additionally available to an
authenticated platform administrator through Dolved's same-origin HTTPS gateway; their raw
host ports are not public.

The 21 November production release archive has SHA-256
`79ccaef19fd541dbc013960c7d42dfd0a06c7547c9fecfdce094ec82f9a15f66`. Strengthened
preflight validation and the complete provider-free running check passed after deployment.
The public pilot and API returned HTTP 200 and the application, worker, scheduler, Dify and
observability containers reported healthy state.

Follow-up archive
`41274cebe19901eb4e87999f56019ff4b6230861475480435000f0d350164f6e`
deployed commit `76bfeb4d`. It corrected the workspace Usage timing failure shown during
acceptance and added protected `/__ops/grafana/` and `/__ops/alerts/` routes. Full
provider-free running verification passed after the update; both routes reject an
unauthenticated external request with HTTP 401, and a read-only live Usage query completed.

A browser acceptance check exposed an authentication-origin handoff issue and an internal
Alertmanager route-prefix mismatch. Follow-up commit `a321b256`, archive SHA-256
`c3a0b67fa7d51b1b20a987d03c0dbbfd3780e9a403e42c72ea365a3f25c899b1`, corrected both.
The production verification passed and Dolved then reported alert state available with zero
active alerts; the public console routes still rejected unauthenticated access.

The host also fronts the separately deployed FAMVAM pilot. A Dolved gateway-file
deployment temporarily omitted that pre-existing virtual host, causing
`pilot.famvam.com` to fail its TLS handshake. The preserved live backups were inspected and
the isolated FAMVAM routes were merged into the managed gateway in commit `3b2706df`
without reverting Dolved's authentication fixes. Both `pilot.famvam.com` and
`pilot.dolved.com` then returned HTTPS 200 with valid certificate verification, and the
full provider-free Dolved production check passed. Future gateway deployments must retain
both virtual hosts.

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
