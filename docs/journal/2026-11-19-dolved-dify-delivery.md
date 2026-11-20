# Dolved + self-hosted Dify delivery

## Boundary

Implementation began in isolated worktree `RAG-dolved-dify-delivery`, branch
`codex/dolved-dify-delivery-20261119`, from `9e43561cedcf53174d2367fb9c71bfd55a4bab8a`.
The dirty source checkout and historical evidence were not changed.

## Implemented

- Added server-owned Dify workspace, conversation, document and retrieved-source mappings.
- Added exact global-only or global-plus-validated-site context propagation.
- Added Dify chat orchestration with persisted answers, ownership checks and honest
  `Sources retrieved` presentation linked only to authorised Dolved originals.
- Added Dify document synchronisation and fail-closed approval, withdrawal, replacement,
  deletion and retry paths. Multi-scope documents receive one remote searchable copy per
  exact scope.
- Added production API/web images, PostgreSQL, SeaweedFS original storage, workers,
  scheduler, HTTPS edge configuration and storage initialisation.
- Recorded the legacy stack and its data/image/config dependencies in
  `docs/operations/dolved-dify/LEGACY-RAG-RECOVERY.md`; no legacy recovery asset was deleted.

## Verification

- Focused Laravel delivery tests: 10 tests, 62 assertions passed.
- Focused web tests: 3 files, 24 tests passed; TypeScript and ESLint passed.
- Production-shaped Dolved images built and started with PostgreSQL 18.4 and SeaweedFS
  4.53; migrations and object-store versioning/CORS initialisation passed; API and web
  health checks passed.
- Pinned stock Dify 1.19.0 (`8e689e868b63b6dd2b449d0eb568ca5cc7127759`)
  started locally without a provider configuration. The stock API/UI returned HTTP 200;
  API, PostgreSQL, Redis and sandbox health checks passed. The plugin debug port was
  removed and only Dify nginx ports were bound to loopback.
- One local agent secret initially failed the new exact-32-byte/unpadded-base64url check;
  correcting only its disposable local encoding allowed the stock service to start.
- Online production dependency checks are clear: `npm audit --omit=dev` reports zero
  vulnerabilities and `composer audit --locked --no-dev` reports zero advisories after
  bounded production dependency updates. The full npm graph retains 13 development-only
  advisories (9 moderate, 4 high), excluded from the production image.
- Rebuilt production image IDs are API
  `sha256:3e9ae76152a5fc255028b0dabe40aff99cf863d1bc395c5b0aa0429d614ed109`
  and web
  `sha256:028df87d63d4b8953d0008aa40adabd9774638e22efc68ad396ea688eabb9f04`.
- The OVHcloud order configurator confirmed VPS-3 2027, UK Erith, Debian 13, no
  commitment, 6 vCore, 12 GB RAM, 100 GB NVMe and standard daily backup at GBP 10.60 ex
  VAT / GBP 12.72 incl UK VAT per month. The order is prepared but not purchased; no
  hosting charge has occurred.
- A deployment render review found that Linux containers cannot use a Dify listener
  bound only to the host loopback through `host.docker.internal`. The production
  definitions now use one external, internal-only `dolved-dify-backplane` network:
  Dolved API/worker services reach only the Dify nginx alias `dify-nginx`, while Dify's
  host ports remain loopback-only and its data services remain private.
- Both effective Compose configurations render successfully. The live disposable test
  backplane contained exactly `dolved-pilot-api-1`, `dolved-pilot-worker-1` and
  `docker-nginx-1`; HTTP requests from both Dolved containers to `http://dify-nginx/`
  returned the expected `HTTP/1.1 307 Temporary Redirect`. This was a provider-free
  connectivity check, not an application generation or retrieval call.
- Added per-container bounded logging to both production stacks using Docker's rotating
  `local` driver (`max-size=10m`, `max-file=5`) rather than changing the host-wide Docker
  daemon or unrelated services.
- Reverified the retained Top K 10 package before self-hosted import: package SHA-256
  `e2e50881b3a321bbcdc81f4d94d95a25d6d69fce1e152e58d711d40a20542558`, extracted
  `app.yaml` SHA-256
  `6708d7a35cc6bfd37d63646e790db6aabdba2203bdd0493fb1f401563aaf94c1`. The package
  declares DSL 0.7.0, exactly matching pinned Dify 1.19.0's
  `CURRENT_APP_DSL_VERSION`. The disposable console remains at its administrator
  initialization gate; no account was invented and no package/provider call was made.
- A read-only steady-state snapshot of the running disposable combined stack measured
  3.14 GiB across 21 Dolved/Dify containers on an 11.67 GiB host. This supports the
  selected 12 GB pilot size for idle/light operation but is not a load or capacity result;
  production resource pressure and disk use still require monitoring.
- Added explicit per-service CPU and memory ceilings to both production Compose layers.
  Both effective configurations render with a non-zero ceiling for every active service.
  Recreated the disposable Dolved and pinned Dify stacks under those exact caps: Dolved
  API/web/PostgreSQL/object storage became healthy, all long-running services remained up,
  Dify API reached `healthy`/HTTP 200 after ordinary startup, every inspected container
  reported `OOMKilled=false`, and both Dolved API and worker still reached Dify nginx over
  the private backplane. One unrelated Compose orphan was observed and deliberately left
  untouched.
- Added Debian 13 host bootstrap and post-verification SSH hardening scripts. The bootstrap
  is fail-fast on the OS/version and Ed25519 key, installs Docker from its official Debian
  repository, creates the named deploy user and restricted service directories, enables
  the current SSH port plus HTTP/HTTPS in UFW, fail2ban and unattended updates, creates
  2 GB swap and the internal Docker backplane. Password SSH is disabled only by the
  separate hardening script after a second key-authenticated session succeeds. Both scripts
  pass POSIX shell syntax checks; execution awaits the delivered VPS.

The historical branding fixture remains preserved, while the active product branding is
now regression-bound. The full current web suite passes 170/170 tests, TypeScript and
ESLint pass, and the production build completes. The focused Dify boundary/lifecycle suite
passes 10 tests / 62 assertions.

## Hosted implementation update

- Purchased and activated the approved OVH VPS at GBP 12.72 including VAT per month,
  including its standard daily backup. Key-only SSH is enforced.
- Deployed pinned stock Dify 1.19.0 at
  `8e689e868b63b6dd2b449d0eb568ca5cc7127759`, with Dify nginx loopback-only and data/plugin
  services unexposed. The OpenAI plugin is pinned at 1.1.0.
- Built and deployed the branded Dolved images from
  `4d511c555d1645e472c0aee352b4e9531022bc19`: API
  `sha256:c2ca5ebc89eaf3639a5e77fa37a8df09fc7e6e4bd0239188d387878ef7e2a643`
  and web
  `sha256:a63c28c791e5a66a86b3bdfc08170bb673df6563356083cc5f0948f9404a1849`.
- Created the private Dolved pilot workspace and exact four registered site identities.
  Authentication, registration-disabled behaviour, ownership, site-context and source-link
  controls remain covered by provider-free tests.
- Verified the eleven Dify originals directly from persistent storage against the frozen
  inventory. The dataset contains exactly eleven enabled, non-archived documents.
- Imported those same exact originals into authoritative Dolved object storage and
  governance using application actions. Read-back size and SHA-256 match for all eleven;
  every version is approved/current, has its exact universal or site-specific applicability
  and maps to the pre-existing Dify document ID. No duplicate searchable document was
  created.
- Verified effective Dify retrieval: dataset Top K 10, node Top K 10, threshold off,
  hybrid 0.7 semantic / 0.3 keyword, required server-supplied site context and manual
  global-or-selected-site filter. The app is private and unpublished.
- Created a restricted Dify backup at
  `/var/backups/dolved-pilot/20261119T214500Z`; its checksum-manifest SHA-256 is
  `ad3ddd8a01eb12389de6ccf89ceaf643bf605fffed2759f85ccb36e590473d4c`.
  An isolated database restore and original-byte extraction/hash check passed.
- Published the unchanged frozen workflow as Dify version 1, enabling its stock backend
  API on the private backplane while leaving MCP disabled and Dify unexposed publicly.
- Took the post-import Dolved backup at
  `/var/backups/dolved-pilot/20261119T220500Z`; checksum-manifest SHA-256
  `97ad53a8b995b5dc4c5638313f4867c4321050149b042beca6208a869feb0f59`.
  A temporary isolated database restored with eleven documents, every restored original
  matched the source checksum list, and only the temporary database was then dropped.
- Public DNS still returns NXDOMAIN for `pilot.dolved.com`, `api.pilot.dolved.com` and
  `storage.pilot.dolved.com`; the authoritative zone is served by
  `ns1`–`ns4.mysecurecloudhost.com`.

No sealed final-evaluation question, expected answer, control identity or source mapping was
accessed. The neutral Claude handoff and its frozen criteria/checksums were verified only.

## 20 November hosted authentication correction

- Diagnosed the first production password-reset sign-in without changing the account or
  repeating the reset. The browser login request succeeded, but the following Next.js
  server requests were unauthenticated.
- The deployed API had derived `dolved-session` from `APP_NAME`, while the web server's
  deliberate forwarding allowlist accepts `rag-platform-session`. The web container also
  lacked its public `FRONTEND_URL`, so internal authenticated requests supplied the local
  default origin instead of `https://pilot.dolved.com`.
- Froze both values explicitly in production Compose, retained the previous server Compose
  file, recreated only the Dolved application services and verified API/web health.
  The public CSRF endpoint now returns `rag-platform-session` and `XSRF-TOKEN` scoped to
  `.pilot.dolved.com`, with secure/HTTP-only/SameSite attributes as applicable and the
  expected credentialled CORS origin. Added a preflight invariant to prevent this web/API
  session handoff from drifting again.
- No credential value, Dify setting, corpus state or sealed evaluation material was read or
  changed during the correction.

## Next recorded before the 20 November status reconciliation

Finish the three external activation dependencies: DNS for the pilot/API/storage names,
two dedicated Dify API keys, and SMTP. Then start the worker and HTTPS edge, complete one
benign instrumentation check plus ordinary hosted engineering/mobile checks, take the
post-import backup, freeze the configuration and prepare the neutral independent-readiness
handoff. Stop before question release or evaluation execution. Client invitation remains a
separate explicit decision.


## 20 November status reconciliation

Reconciled the pilot delivery track from David's current status report and local delivery
commits. DNS/HTTPS, dedicated Dify credentials, SMTP configuration and pilot activation
are no longer blockers. The existing fixed product branding is deployed; the separate
platform-admin branding editor remains unbuilt. Actual SMTP delivery and hosted lifecycle,
permissions, mobile and source-link acceptance remain pending.

The first-message live-update fix at `c115890` is reported deployed with 18 regression
tests, lint, TypeScript and production build passed. David's hosted no-refresh confirmation
is still required; this entry does not claim that confirmation occurred. Import verification
fixes and the compact source display are recorded in the delivery branch. Prior backup and
isolated restore evidence is retained; no new restore was performed.

Updated `tasks.json`, `PROJECT_ROADMAP.md` and the delivery checklist, preserving R28-S04,
all canonical phase/session/gate statuses and the future AWS sequence. Removed only stale
activation blockers. The earlier Next section and dated negative DNS observations are
historical and superseded, not erased. Existing uncommitted edits were preserved.

Next: finish hands-on defects and hosted acceptance; implement bounded admin branding;
complete planned privacy-safe observability and separate restricted model-input/user-visible
capture; recheck corpus/settings; freeze one neutral readiness handoff for Claude. The 40
questions remain sealed and no release/execution or client invitation is implied.

This was a documentation reconciliation only: no application edits, live host checks,
provider calls, tests, deployment or sealed-material access. David's supplied hosted status
is attributed rather than represented as newly independently verified evidence.
