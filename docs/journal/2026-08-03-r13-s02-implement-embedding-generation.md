# Session Journal: R13-S02 — Implement Embedding Generation

## Date

2026-08-03

## Session mode

Implementation in teaching mode against accepted ADR-0013. The session was
bounded to provider-neutral embedding generation and did not add vector
persistence, Qdrant, retrieval or ingestion-pipeline integration.

## What happened

The provider-neutral architecture from R13-S01 was turned into executable
Python contracts. The service can now describe one compatible embedding
space, embed document chunks in controlled batches and verify that provider
responses still correspond exactly to the requested chunks.

The implementation uses the repository's existing `httpx` dependency rather
than adding a Voyage SDK. That keeps all vendor-specific HTTP behaviour inside
one adapter and allows its request, response, retry and privacy behaviour to be
tested with an injected transport. Pipeline callers depend only on the local
`Embedder` protocol.

## Implementation details

The immutable embedding profile records every consequential V1 setting and
has a fixed SHA-256 fingerprint derived from canonical JSON. Requests retain
workspace, document, correlation and source-chunk identity locally while only
the text and required semantic settings are sent to Voyage. Results preserve
the profile, fingerprint, purpose, source identity, dimensions, vector and
provider input-token metadata.

`ChunkEmbeddingGenerator` splits a `ChunkingResult` into configurable batches,
rejects empty or blank content and combines verified results without changing
chunk order. The Voyage adapter checks model identity, response count, ordered
indices, dimensions, finite values and unit length before it accepts vectors.

Failures are translated into platform-owned types. Rate limits, timeouts and
temporary availability errors retry with bounded capped backoff and jitter;
input, credential, malformed-response, dimension and profile faults fail
immediately. A deterministic fake supplies stable unit vectors and arbitrary
typed failures for ordinary tests.

## Security and observability

The Voyage credential is supplied through environment configuration and held
as `SecretStr`. It is never placed in a request model, log, trace, fixture or
persisted result. Provider payloads exclude workspace, document, correlation
and chunk identifiers.

Embedding telemetry extends ADR-0012's allowlist with controlled provider,
model, purpose, count, retry, duration, token and cost facts. Correlation,
workspace and document identifiers may appear on traces and structured logs,
but raw text, vectors, credentials and provider response bodies do not.

## Verification performed

* Python formatting and Ruff lint passed across the complete Python tree.
* Mypy passed with no issues across all 64 checked source files.
* The full Python suite passed with 168 tests; the isolated live-provider test
  was skipped because its explicit opt-in variables were absent.
* Focused tests cover deterministic profile fingerprinting, immutability,
  document/query purpose mapping, deterministic fake output, batching,
  identity/order retention, empty input, typed configuration failures,
  payload minimisation, response validation, retries and telemetry privacy.
* Compose configuration validation passed.
* Next.js, Laravel and Python lint passed; Pint checked all 114 Laravel files.
* Repository whitespace validation passed.
* A subsequent attempt to repeat the Docker gate after pinning the previously
  calculated profile fingerprint stalled before execution when Docker Desktop
  became unresponsive. It was interrupted and was not counted as a successful
  command; the changed assertion itself passed local formatting and lint.

## Important takeaways

The provider abstraction protects code structure, while the profile
fingerprint protects semantic compatibility. Both are necessary: replacing an
adapter is mechanically easy, but replacing an embedding space still requires
controlled re-embedding.

Batching must not weaken lineage. Voyage returns ordered numeric results, not
platform domain identity, so the adapter validates the provider indices and
the platform restores each local chunk UUID by position.

Operational facts and semantic facts have different lifetimes. Profile and
source lineage belong in the immutable result; timing, retries and estimated
cost belong in telemetry.

## Next step

R14-S01 defines the vector-storage architecture and the generation topology
that will preserve profile compatibility in Qdrant. It must decide the
physical isolation, payload, activation, re-indexing and deletion rules before
vectors are persisted.
