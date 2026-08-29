# Ingestion worker HTTP contract — version 1

ADR-0015, ADR-0016 and ADR-0032 define eleven purpose-scoped operations. Every callback
request uses the six-field HMAC `v2` string-to-sign and carries
`contract_version: 1`; the claim request is the unchanged
`document.ingestion.requested` v1 event body.

The twenty-two `*-request-v1.schema.json` and `*-response-v1.schema.json`
files are the shared wire contracts for those operations.
`worker-operation-vectors.json` is the single cross-language fixture source:
Laravel and Python validate every canonical request and response plus the same
unsupported-version, unknown-field, missing-field and invalid-enum mutations.
Version 1 is the only supported worker HTTP version; unsupported versions fail
closed.

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
- `ingestion.attempt.cancel`
- `ingestion.extraction-artifact.authorise`
- `ingestion.extraction-artifact.acknowledge`

All identifiers are canonical lowercase UUID strings. Chunk submission and
resume are bounded and paginated. Chunk text, vectors, credentials and
signatures are never logged. The claim response carries the durable lease
generation. ADR-0032 upload authorisation binds that generation to one exact,
conditional-create object key; acknowledgement is accepted only after Laravel
independently streams and verifies the stored artifact identity and contract
version.
