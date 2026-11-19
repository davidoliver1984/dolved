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

The full web suite has one pre-existing branding failure in frozen historical evidence.
The host-side full Laravel suite requires repository-external mounts/configuration and is
not a reliable delivery regression signal; focused delivery tests are green. No
model/provider call or charge was incurred in this work.

## Next

Complete the OVH account/payment handoff, provision the prepared VPS and bounded
DNS/secure secrets, deploy the frozen configuration, perform an isolated restore check,
freeze the corpus and configuration, then execute the single independently graded hosted
evaluation. Client invitation remains a separate explicit decision.

