# R23-S03d — Source and extracted-text delivery

## Outcome

The provider-free ADR-0032 browser delivery boundary is complete.

Implemented:

- an authenticated, tenant-concealed source route that authorises every GET and
  HEAD request before reading storage metadata;
- exact support for closed, open-ended and suffix single byte ranges, including
  clamping, zero-length behavior, invalid/multiple-range rejection and GET/HEAD
  header parity;
- bounded fixed-size source streaming with deterministic `Content-Type`, RFC
  5987-safe `Content-Disposition`, `Accept-Ranges` and `nosniff` headers;
- inline delivery only for the accepted browser-safe formats and attachment-only
  delivery for DOC/DOCX/RTF;
- no browser presigned source URLs and no separate cheaper HEAD/range path;
- a read-only active-published-projection endpoint with the required honest
  label and layout disclaimer, deterministic cursor pagination, bounded
  warnings/changes and selected presentation-safe fields only;
- document-scoped PostgreSQL full-text search over the generated projection
  index, with a provider-free SQLite test fallback;
- privacy-safe source-delivery telemetry containing only stable public
  workspace/document identity, result status, sanitised requested range and
  byte count.

No retrieval, authority, projection publication, extraction or browser UI
behavior was changed.

## Verification

- Focused range, delivery, storage and privacy-safe logging tests: 20 passed,
  85 assertions.
- Full Laravel suite: 413 passed, 3 skipped, 2,209 assertions.
- Laravel Pint: passed for 522 files.
- JSON validation and `git diff --check`: passed.

No providers or email services were called. Calibration and held-out material
were not accessed.

## Next

R23-S03e may complete ADR-0032's provider-free acceptance evidence, including
the remaining cross-language, atomic-publication, range, tenant-concealment and
deletion/cleanup regression matrix. Clone orchestration remains blocked until
that evidence is accepted.
