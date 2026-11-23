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
the isolated FAMVAM routes were restored without reverting Dolved's authentication fixes
in commit `3b2706df`. Commit `e9b2f409` then separated configuration ownership: Dolved's
release imports independently managed, read-only site fragments, while FAMVAM's route now
lives outside the Dolved release at `/etc/dolved/caddy-sites/famvam.caddy`. A subsequent
Dolved gateway recreation returned HTTPS 200 with valid certificate verification for both
`pilot.famvam.com` and `pilot.dolved.com`, and the full provider-free Dolved production
check passed. This prevents a routine Dolved release from overwriting FAMVAM's route. Both
pilots still share the host and Caddy process, so complete infrastructure fault isolation
is not claimed.

Deployed image identities recorded at verification:

- API: `sha256:489fc28f3b7f565e733e13ab54e314baf676c96bc4808950b5912d5c50c1998b`
- Web: `sha256:3085765d48d0c9be31437447bcecb849fe529e5798e18b604f2b152e95afad79`
- Collector: `sha256:6ed874ea083d67a3085acfc75343f2ad11d9abe6006d7ea4a16e2dba9af14e49`
- Grafana OTEL LGTM: `sha256:af7242c1a9608faf6d26e6f235392fd0c32b67258228f9a3cfc96e724974930c`
- Alertmanager: `sha256:9e082985f56f4c8c9f724e18f2288c6708f472e56a5286b8863d080434ea065d`

DNS/HTTPS, dedicated Dify credentials, SMTP, configurable tenant branding, document
applicability, bounded Dify conversation memory and private telemetry are active. No paid
retrieval, generation or evaluation call was used for this deployment.

On 22 November, acceptance review of the scheduled governance digest found generic review
cards even though each event retained the document title and due date. Commit `ee67198c`
now renders “{Document title} still needs to be reviewed” with “Due by {date}” beneath it.
The focused mail suite passed 20 tests / 112 assertions and Pint. Archive SHA-256
`8874fc3922682d49484aaaf91481cb4b160f12bea74125e2002383d3d7a3fa52` was deployed as API
image `sha256:ab4e7bf8f4751669c247fdc6837ec3b14105908713e3f3906e995525ec50db4f`,
and the full provider-free production verification passed. All eleven live reminders had
retained titles and a 4 December 2026 due date; a corrected digest was sent to David.

R30-S03 remains in progress until the consolidated hosted acceptance confirms the complete
document lifecycle, permissions, responsive presentation, source-link access, operational
failure states and the final rollback/handoff record. This is not a production-readiness or
pilot go/no-go claim.

## Active-pipeline interface cleanup — 23 November 2026

The bounded cleanup approved from
`docs/operations/dolved-dify/ACTIVE-PIPELINE-INTERFACE-AUDIT-20261123.md` was implemented
from clean baseline `01ff80c25b4634d2e143f0a4521dd4ab36f6544a` in an isolated worktree.
The canonical working copy and its unrelated changes were not modified. Implementation
commit `ab42cada` adds a server-owned capability profile, capability-gated extracted-text
and comparison presentation, truthful cached Dify segment observations, separate current
and historical failure semantics, neutral Dify progress/stop wording, corrected source
retention wording and tenant-brand propagation. Commit `cc72a8da` adds immutable OCI source
revision labels to the production application images. Both commits were pushed to
`origin/codex/active-pipeline-cleanup-20261123`.

The custom pipeline remains preserved. Extracted text still requires an indexed exact
version with an active published extraction projection. Structured comparison still
requires two authorised published projections with matching extractor and normaliser
identities. Custom progress, cancellation, citation, extraction-warning and corpus gauge
contracts remain present and permission checked. Restoring a legacy backend alone does not
establish that a future UI is compatible; the focused gates in the audit's reinstatement
map still apply.

Focused verification passed 24 Laravel tests / 195 assertions and 5 web test files / 36
tests. Pint, the production Next build and `git diff --check` passed. ESLint completed with
zero errors and six existing controlled runtime-image warnings. Dify/provider boundaries
used fakes; no retrieval, generation or model call was made.

Before deployment, the exact environment and a PostgreSQL custom-format dump were retained
under `/srv/dolved-pilot/backups/20261123-active-pipeline-predeploy` with restricted
permissions and checksums. Previous rollback identities remain:

- API/worker/scheduler: `dolved-api:febedb4`, image
  `sha256:ab4e7bf8f4751669c247fdc6837ec3b14105908713e3f3906e995525ec50db4f`.
- Web: `dolved-web:febedb4`, image
  `sha256:3085765d48d0c9be31437447bcecb849fe529e5798e18b604f2b152e95afad79`.

One deployment installed migration
`2026_11_23_000001_add_dify_usage_observations` and started immutable tag `cc72a8da`.
The actual running API, worker and scheduler use image
`sha256:cf7e2d818fedc026de1d7c6c31a580d1eee5e352330a1c83e09c2b8e1e0a6644`; the web uses
`sha256:94bd2e9bdb81b91751afe59aa364adafca319621b07d8df48d6047cde30dea4b`.
Every running application image reports OCI revision
`cc72a8da699cb3bbe11b847785379dd7a59c0f99` and source
`https://github.com/davidoliver1984/dolved`. The production preflight and complete
provider-free running verification passed after deployment.

The one hosted acceptance pass used no upload, re-index, question, retrieval, generation,
recovery email or sealed material. A bounded supported-API observation refresh recorded 11
available Dify bindings and no unavailable observations. The live Usage projection reports
248 indexed segments, an available oldest observation at `2026-11-23 19:15:24+00`, zero
documents currently failing processing, and historical Dify processing-attempt failures as
`Unavailable`, not zero. The document inventory contains 11 available bindings plus the
withdrawn/deleted acceptance document. All 12 retained document records lack a compatible
custom extraction projection, so extracted text is honestly unavailable. All 20 retained
runs identify `dify.chat-messages` and resolve to `Stop waiting`. The deployed web bundle
contains the neutral progress, post-dispatch cost warning and corrected historical-evidence
wording; the former `already cited` phrase is absent.

Public recovery branding and the browser title rendered as Cherish, and a 390 by 844
viewport had no horizontal overflow. The trusted deployment-brand endpoint currently has
no tenant favicon configured, so the supported Dolved fallback `/favicon.ico` was used and
returned HTTP 200; a custom Cherish favicon appearance was therefore not claimed. No
existing workspace-admin or
ordinary-member membership exists in this single-user pilot, and the available background
browser had no authorised protected session. Protected role presentation was therefore not
invented as a hosted pass; it remains covered by the focused role/policy tests and is a
specific acceptance gap. The corpus also has no family with two retained versions, so live
comparison concealment could not be exercised; exact-version and two-version prerequisites
passed focused tests. Stop/reload mechanics were verified with fakes because a live check
would have required a provider call. These gaps do not close the broader consolidated
R30-S03 acceptance, readiness or pilot go/no-go gates.
