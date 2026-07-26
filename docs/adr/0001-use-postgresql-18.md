# ADR 0001: Use PostgreSQL 18

## Status

Accepted

## Date

2026-07-26

## Context

The platform requires a relational database for identity, tenancy, document metadata,
conversation state and operational records. The project roadmap already selects
PostgreSQL, but the major version must be fixed before persistent development data is
created.

PostgreSQL 18 is the current stable major release. It is supported by the PostgreSQL
project until November 2030. PostgreSQL 17 remains supported until November 2029 and
was the principal conservative alternative.

The PostgreSQL Docker Official Image changed its storage layout for PostgreSQL 18 and
later. Its default `PGDATA` is version-specific and its declared volume is
`/var/lib/postgresql`. Using the historical `/var/lib/postgresql/data` mount would be
incorrect for this version.

## Decision

Use PostgreSQL 18 for local development and the initial production architecture.

The development environment will:

* pin the Docker image to `postgres:18.4-alpine`;
* mount its named data volume at `/var/lib/postgresql`;
* use the Compose service name `postgres` on internal port 5432;
* publish the host port only on `127.0.0.1`;
* require password authentication;
* keep pgvector out of the relational database because vector storage is a separate
  future decision.

The patch-level image pin will be reviewed deliberately when PostgreSQL publishes
minor security and bug-fix releases.

## Alternatives considered

### PostgreSQL 17

PostgreSQL 17 is mature, broadly supported and avoids the PostgreSQL 18 Docker storage
layout change. It was rejected because this is a new system with no existing database
volume to migrate, and PostgreSQL 18 provides an additional year of upstream support.

### Floating `postgres:18` image tag

A floating major tag would automatically receive minor releases. It was rejected for
the initial reproducibility boundary because two clean builds at different times could
use different database binaries without a repository change.

### PostgreSQL with pgvector

Adding pgvector now could consolidate relational and vector storage. It was rejected
because the roadmap currently assigns vector retrieval to Qdrant, and the vector-store
architecture will receive its own ADR.

## Consequences

### Positive

* The platform begins on the current stable PostgreSQL major release.
* Upstream support runs until November 2030.
* Patch upgrades remain explicit and reviewable.
* The volume layout supports the PostgreSQL 18 Docker image and future `pg_upgrade`
  workflows.

### Negative

* The team must track and apply PostgreSQL minor releases deliberately.
* Deployment tooling must use the PostgreSQL 18 volume path rather than examples
  written for PostgreSQL 17 and earlier.
* A future major-version upgrade will require a planned database migration.

## References

* [PostgreSQL versioning policy](https://www.postgresql.org/support/versioning/)
* [PostgreSQL 18 release notes](https://www.postgresql.org/docs/18/release-18.html)
* [Docker Official Image PostgreSQL storage documentation](https://github.com/docker-library/docs/blob/master/postgres/README.md#pgdata)
