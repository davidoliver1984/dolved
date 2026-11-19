# Dolved + stock Dify production deployment

This directory deploys the public Dolved web/API boundary. Dify remains a stock,
private backend and administration console. Client users never use the Dify web UI.

## Pinned identities

- Dolved source baseline: `9e43561cedcf53174d2367fb9c71bfd55a4bab8a`
- Dify tag: `1.19.0`
- Dify commit: `8e689e868b63b6dd2b449d0eb568ca5cc7127759`
- Dify official `docker/docker-compose.yaml` SHA-256:
  `c2a0ebc758e651e7ff298a8f121fc8a6ae8a64d5ae7a1c872048e6db05fa1dfe`
- Dify official `docker/.env.example` SHA-256:
  `ff2e6a7909506f487284dbc53025602b36dccb3f8204338ea852a29796cbd154`

Use the upstream compose file from that exact commit. Do not copy or fork its
services into this repository. Apply `compose.dify-security.yaml` after the stock
file to remove the plugin debug port, bind Dify nginx's host ports to loopback and
attach that proxy to the isolated Dolved–Dify backplane.

## Prepare the host

1. Use a supported Docker Engine and Docker Compose v2.35 or later.
2. Put this checkout and the pinned Dify checkout in separate restricted directories.
3. Copy `.env.production.example` to a root-readable production env file for Dolved.
4. Copy `dify.env.production.example` to Dify's `docker/.env` and replace every
   `SECURE_STORE_REQUIRED` value from secure storage. Never commit either real file.
5. Generate `DIFY_AGENT_SERVER_SECRET_KEY` as exactly 32 random bytes encoded as
   unpadded base64url. Other keys must be independently random and at least 32 bytes.
6. Create the private shared network once: `docker network create --internal
   dolved-dify-backplane`. It is the only cross-project path from Dolved to Dify;
   both stacks retain their own default networks for required outbound traffic.
7. Verify DNS for the dedicated Dolved, API and storage subdomains before starting Caddy.

Both Compose definitions use Docker's rotating `local` log driver with a 10 MB / five-file
limit per container. Do not replace it with an unbounded driver in production.

The Dify console is available only through an administrator SSH tunnel, for example
local port 18080 to server `127.0.0.1:8080`. It must not be added to public Caddy routes.

## Start and verify

From the pinned Dify `docker` directory:

```sh
docker compose -p dolved-dify \
  -f docker-compose.yaml \
  -f /path/to/compose.dify-security.yaml \
  config --quiet
docker compose -p dolved-dify \
  -f docker-compose.yaml \
  -f /path/to/compose.dify-security.yaml up -d
```

Confirm the effective port list contains only `127.0.0.1:8080` and
`127.0.0.1:8443`; the plugin daemon, PostgreSQL, Redis and Weaviate must have no
host-published ports. Confirm Dify nginx has alias `dify-nginx` on the external
`dolved-dify-backplane` network and no other Dify service is attached to it.
Confirm every active service reports the `local` logging driver with `max-size=10m` and
`max-file=5` in the rendered Compose configuration.

From this directory:

```sh
docker compose --env-file /secure/path/dolved.env \
  -f compose.production.yaml config --quiet
docker compose --env-file /secure/path/dolved.env \
  -f compose.production.yaml up -d --build
```

Verify migrations and storage initialisation completed, API and web health checks
are healthy, HTTPS redirects correctly, registration is disabled, Dify keys never
appear in browser payloads, and the API/worker can resolve `dify-nginx` while the
Dify console remains unreachable on the server's non-loopback interfaces. Then verify
one authorised upload can progress through indexing.

## Persistent state and backups

Recovery requires more than source code. Preserve together:

- Dolved PostgreSQL volume and logical dump.
- Dolved SeaweedFS `object_data` volume containing authoritative originals.
- Dify PostgreSQL bind data, Redis data, Weaviate data, app storage and plugin data.
- The exact Dolved and Dify commits, compose files, override, image digests and
  secret-store references.
- The frozen app export, prompt/settings record, corpus manifest and document mappings.

Take a consistent backup during a maintenance window: stop Dolved workers/scheduler
and Dify workers, create PostgreSQL logical dumps for both databases, archive the
persistent object/vector/app/plugin directories, then restart workers. Encrypt the
bundle and copy it off-host. Record hashes and retention; do not place the bundle in Git.

For the required isolated restoration check, restore into new project names and new
volumes on a non-public network, restore both databases and file/vector stores, start
Dify before Dolved, and verify: an original's hash, its governance record, Dify document
binding, one existing conversation reload, and authorised source download. Do not use
the production volumes as restore targets.

## Rollback and shutdown

Before an application rollback, take a fresh backup. Re-deploy the prior immutable
Dolved image tags and the prior compatible migration boundary; database rollback is a
restore decision, not an automatic `migrate:rollback`. Keep Dify at the frozen release
unless the rollback record explicitly includes a compatible Dify change.

To stop without deleting state, use `docker compose stop` for Dolved and Dify. Never
use `down --volumes`. A permanent shutdown requires a final encrypted off-host backup,
hash manifest, secret revocation and explicit approval before deleting any volume.

## Known operational limits

This is a single-host pilot: host failure interrupts service. Backups provide recovery,
not high availability. Dify vector data is implementation-specific; authoritative
originals and governance records are the portable recovery source. Provider billing
may be delayed and must not be reported as zero when unavailable.

