# Session Journal: R13-S01 — Define Embedding Provider Boundary

## Date

2026-08-03

## Session mode

Architecture and documentation only. No embedding implementation, provider
dependency, credential, external API call or vector persistence was added.

## What happened

Phase 13 begins the transition from deterministic document processing to
provider-produced semantic vectors. ADR-0013 was prepared to establish that
boundary before a provider client entered the Python service. It was reviewed
against the Phase 13 acceptance criteria, ADR-0006's tenancy boundary,
ADR-0011's immutable chunk contract, ADR-0012's observability/privacy rules
and the current official Voyage documentation.

The review confirmed the provider-neutral direction and Voyage selection but
found two details that needed correction before implementation could safely
begin. The amended and accepted ADR now defines one compatible profile for
both document and query embeddings and makes the complete V1 profile explicit.

## Decisions recorded

ADR-0013 establishes:

* an application-owned `Embedder` protocol as the only pipeline-facing
  provider boundary;
* Voyage as the initial hosted provider, using `voyage-4-large`, 1,024 float
  dimensions, unit-length normalisation and disabled provider truncation;
* one immutable profile containing both the document and query input-mode
  mappings, with one canonical snapshot and fingerprint for their shared
  compatible embedding space;
* a separate per-request/per-result purpose that records whether a document
  or query vector was produced without creating incompatible fingerprints;
* local source-ID retention and positional association with Voyage's ordered
  batch response, with explicit count, dimension, finite-value and profile
  validation;
* typed permanent and transient failures, with bounded retries only for rate
  limits, timeouts and temporary provider unavailability;
* controlled re-embedding into a new vector generation whenever a
  consequential profile field changes;
* a deterministic fake provider for ordinary automated tests and isolated,
  opt-in live Voyage verification;
* separation of semantic embedding results from operational telemetry, with
  raw text and vectors excluded from telemetry by default.

Vector storage topology, generation activation, retrieval, reranking,
evaluation and grounded generation remain deferred to their named phases.

## Review findings and corrections

The prepared profile initially treated a request's individual task mode as a
fingerprinted profile field. That would have given query and document vectors
different compatibility fingerprints even though they are the two sides of
one retrieval space. The accepted model now fingerprints the pair of purpose
mappings once and records the selected purpose separately on each operation.

The prepared model/dimensions example was also non-binding even though Stage
13.1 requires an explicit selection. The ADR now selects `voyage-4-large`,
1,024-dimensional float output, unit normalisation and `truncation=false`.
The last setting turns oversized input into a visible typed failure rather
than silently embedding incomplete chunk text.

The batch wording was tightened so the platform does not overstate what the
provider verifies. Voyage does not understand local chunk UUIDs; the adapter
preserves them locally and associates returned vectors using Voyage's
documented response order.

## Verification performed

* Read ADR-0013 in full after the amendments.
* Confirmed the ADR is marked Accepted and linked from the ADR index.
* Checked every Stage 13.1 acceptance criterion against an explicit ADR
  decision or invariant.
* Verified the current Voyage model, dimensions, input modes, float output,
  normalisation, batch limits and truncation behaviour using Voyage's official
  documentation on 2026-08-03.
* Confirmed the profile and controlled-re-embedding rules remain compatible
  with the later vector-generation decision reserved for R14-S01.
* Confirmed no application, dependency, secret, environment or infrastructure
  file changed, so application tests and live paid-provider calls were not
  applicable to this architecture-only stage.
* Validated repository whitespace and `tasks.json` syntax before commit.

## Important takeaways

An embedding's dimensions are not enough to identify the semantic space it
belongs to. Provider, model, configuration and both retrieval-purpose mappings
must travel together as one compatibility profile.

Provider neutrality makes replacement possible, not free. Any consequential
profile change requires a new vector generation and controlled re-embedding.

The provider boundary should promise only what can be verified. Local chunk
identity remains trustworthy because the platform owns it throughout the
batch; the provider supplies ordered vectors, not domain identity.

## Next step

R13-S02 implements the immutable embedding contracts, deterministic fake,
Voyage adapter, validation, typed failures, bounded retries and focused tests.
It must not introduce Qdrant persistence or retrieval behaviour.
