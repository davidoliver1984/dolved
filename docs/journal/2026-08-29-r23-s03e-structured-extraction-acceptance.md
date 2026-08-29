# R23-S03e — Structured extraction acceptance evidence

## Outcome

ADR-0032's provider-free structured-extraction boundary is accepted for use by
the remaining Phase 23 governance work.

The acceptance pass closed the remaining boundedness and lifecycle gaps:

- the worker now enforces an explicit 300-second ingestion-processing deadline
  as a typed retryable failure, without reporting an unconfirmed terminal
  failure;
- artifact authorisation binds the accepted contract version and exact limits
  for canonical bytes, element count, per-element UTF-8 text bytes and warning
  count;
- Laravel independently enforces those limits while building a projection and
  applies a separate 300-second projection deadline;
- complete document deletion removes source storage, extraction artifact
  storage, artifact records, every projection generation and its dependent
  elements/warnings, and the active projection pointer;
- full GET/HEAD parity, closed/open/suffix single ranges, `416` behavior,
  zero-length sources and stream closure are covered by provider-free tests.

No retrieval, authority, ranking, threshold, calibration or held-out behavior
changed. No provider or email service was called.

## Accepted limits

| Boundary | Limit | Rationale |
| --- | ---: | --- |
| Source document | 25 MiB | Existing accepted upload boundary. |
| Canonical artifact | 50 MiB | Allows the ownership-free JSON envelope and repeated structural text while remaining explicitly bounded. |
| Elements | 100,000 | Covers the measured high-cardinality text fixture exactly and prevents unbounded row creation. |
| Element text | 1 MiB UTF-8 bytes | Allows unusually large atomic elements while preventing a single projection row from consuming unbounded memory/storage. |
| Warnings | 10,000 | Keeps diagnostic evidence materially above observed extraction output while bounded. |
| Artifact contract | `document-extraction-artifact-v1` | Unknown schema versions fail closed. |
| Ingestion processing | 300 seconds | Materially above the slowest measured provider-free extraction while still terminating stalled work. |
| Projection build | 300 seconds | Materially above the measured 100,000-row provider-free projection while still bounding the transaction. |

## Provider-free measurements

Measurements used the repository's Python 3.14 AI runtime. They are acceptance
envelope evidence, not provider or production-capacity benchmarks.

| Fixture | Source bytes | Elements | Artifact bytes | Elapsed | Python traced peak |
| --- | ---: | ---: | ---: | ---: | ---: |
| Valid 500-page padded PDF | 26,214,400 | 500 | 258,908 | 3.836 s | 77,977,984 bytes |
| Structured DOCX | 54,763 | 5,000 | 5,333,258 | 4.657 s | 37,341,029 bytes |
| High-cardinality plain text | 2,800,000 | 100,000 | 39,919,471 | 17.034 s | 491,219,851 bytes |

The largest artifact had SHA-256
`527a1ec00a2a967a9e902a75e8dbd6d060f48efcd962f970021ed797c600fba9`.
Laravel streamed its checksum in 0.229 seconds and parsed 100,000 elements in
1.156 seconds. A provider-free SQLite transaction inserted the same 100,000
projection rows in 1.234 seconds. SQLite insertion is deterministic acceptance
evidence only; it is not a claim about PostgreSQL production performance.

A 16-client lower-level range exercise completed 1,600 64-KiB partial reads in
0.015 seconds. HTTP feature tests separately prove exact `206`, `Content-Range`,
HEAD parity, `416` and resource-closure behavior.

## Verification

- Focused Python ingestion/artifact suite: 25 passed.
- Focused Laravel extraction, deletion, delivery, range and contract suite:
  39 passed, 289 assertions.
- Full Python suite: 644 passed, 4 skipped.
- Full Laravel suite: 419 passed, 3 skipped, 2,244 assertions.
- Ruff lint/format, Mypy, Laravel Pint, JSON validation and
  `git diff --check`: passed.
- Limit failures are typed and fail closed for canonical bytes, elements,
  element text, warnings, schema version and projection timeout.
- Deterministic deletion and delivery regressions pass without external calls.

## Next

R23-S02b may now implement clone compatibility and orchestration against this
verified ADR-0032 identity. It must not weaken the accepted artifact,
projection, tenancy or source-delivery boundaries.
