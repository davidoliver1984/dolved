# Ingestion worker HTTP contract — version 1

ADR-0015 and ADR-0016 define eight purpose-scoped operations. Every request
uses the six-field HMAC `v2` string-to-sign and carries `contract_version: 1`.

The shared `canonicalisation.json` file is the language-neutral authority for
chunk content, chunk manifest and point manifest digests. PHP and Python tests
must load the same fixture and reproduce the same values.
`canonicalisation-vectors.json` supplies that shared conformance vector,
including Unicode, nested key ordering and floating-point provenance.

The operations are:

- `ingestion.claim`
- `ingestion.lease.renew`
- `ingestion.chunks.submit`
- `ingestion.chunks.seal`
- `ingestion.attempt.resume`
- `ingestion.publication.authorise`
- `ingestion.complete`
- `ingestion.fail`

All identifiers are canonical lowercase UUID strings. Chunk submission and
resume are bounded and paginated. Chunk text, vectors, credentials and
signatures are never logged.
