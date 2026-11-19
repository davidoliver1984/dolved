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

The full web suite has one pre-existing branding failure in frozen historical evidence.
The host-side full Laravel suite requires repository-external mounts/configuration and is
not a reliable delivery regression signal; focused delivery tests are green. No
model/provider call or charge was incurred in this work.

## Next

Complete the OVH account/payment handoff, provision the prepared VPS and bounded
DNS/secure secrets, deploy the frozen configuration, perform an isolated restore check,
freeze the corpus and configuration, then execute the single independently graded hosted
evaluation. Client invitation remains a separate explicit decision.

