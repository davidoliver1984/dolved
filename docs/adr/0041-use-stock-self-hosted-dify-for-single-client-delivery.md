# ADR-0041: Use stock self-hosted Dify behind Dolved for single-client delivery

## Status

Accepted for the bounded single-client pilot.

## Date

2026-11-19

## Context

Dolved already owns authentication, workspace authorisation, document governance,
conversations and the client-facing interface. The delivery requires a maintainable
retrieval and generation runtime without extending the legacy custom RAG pipeline.
The pilot is one client organisation and one Dify workspace. Dify's administration
console is operational tooling, not the product frontend.

## Decision

Run the pinned stock Dify 1.19.0 release as a private backend for extraction,
chunking, indexing, retrieval and generation. Keep Dolved's Next.js frontend and
Laravel API as the only public product boundary. Laravel holds Dify credentials,
validates workspace/site context, maps authoritative Dolved records to Dify identities,
and persists answers and retrieved-source references.

Dolved retains authoritative originals, approval/version/applicability records and
access control. Dify holds searchable copies. Missing site means global-only; a
validated selected site means global plus that exact site. Lifecycle changes fail
closed by disabling ineligible remote copies before exposing replacements.

Deploy one unmodified Dify administration UI bound to loopback. Do not rebrand it or
offer it to client users. Remove its stock plugin-debug host port. Keep Dify databases,
Redis and vector services private and persistent. Preserve stock upstream source and
apply only deployment configuration; do not fork Dify.

## Alternatives considered

- Continue the bespoke Python/Qdrant RAG runtime: rejected for this delivery because
  it keeps the larger custom maintenance surface that this bounded integration replaces.
- Expose or reskin Dify as the client frontend: rejected because it bypasses Dolved's
  governance/security boundary and introduces licence and product-control concerns.
- Build a custom planner, checker or answer-repair layer: rejected as outside scope and
  likely to obscure rather than simplify the final independent evaluation.
- Multi-client Dify tenancy: excluded; future multi-client use requires a new licence
  and isolation decision.

## Consequences

The public experience remains Dolved. Dify upgrades become explicit compatibility and
backup events. Recovery requires Dolved originals/governance data plus Dify relational,
vector and app/plugin state; source code alone is insufficient. The single-host pilot
has an acknowledged availability limit. Dify's modified Apache 2 licence is compatible
with this one-workspace, single-client, unmodified-admin-console scope; broader tenancy
or frontend modification must be reviewed separately.

