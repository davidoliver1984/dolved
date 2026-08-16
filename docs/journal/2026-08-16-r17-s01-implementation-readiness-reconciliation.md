# Session Journal: R17-S01/R17-S02 Implementation-Readiness Reconciliation

## Date

2026-08-16

## Session mode

Documentation and tracking only. No application code, migrations,
contracts, prompts, provider adapters, evaluation cases or provider calls
were introduced.

ADR-0023 remained `Accepted` and received a narrowly bounded, same-day,
pre-implementation clarification with the repository owner's explicit
approval. The edit records factual repository identity mappings, makes the
accepted Laravel/Python ownership boundary executable and reconciles stage
responsibilities. It does not reverse or alter an accepted architectural
decision.

This is explicitly not a general precedent for editing Accepted ADRs.
Substantive changes to accepted architecture continue to require a new or
superseding ADR.

## Why reconciliation was required

A repository-level readiness review identified five points that otherwise
would have forced R17-S02 to invent architecture during implementation:

1. The R17-S02 guide retained an `apps/ai`-only prompt-assembly boundary,
   although ADR-0023 assigns contracts, context packing, request assembly,
   validation and persistence primarily to Laravel.
2. Exact prompt rendering and wording were assigned inconsistently between
   R17-S02 and R17-S03.
3. `GENERATION_CONTEXT_BUDGET_EXCEEDED` was clearly distinct from business
   outcomes, but its local/Python/`rc1` representation was not explicit.
4. A bare `RetrievalResult` did not carry every already-resolved fact needed
   to assemble the provider-minimised generation context.
5. The initially accepted `EvidenceSnapshot` example used conceptual field
   names that do not exist in the real persistence model.

## Approved ADR-0023 clarification

ADR-0023 now visibly records its 2026-08-16 post-acceptance
implementation-readiness clarification.

### Repository identity mappings

The durable evidence design is unchanged: every cited snapshot stores the
exact cited text plus real repository lineage. The concrete field mapping is:

| Initially accepted concept | Real repository identity |
|---|---|
| canonical chunk identity | `document_chunk_id` → `DocumentChunk.id` |
| document-version identity | `document_id` → `Document.id` |
| durable production-attempt lineage | `ingestion_event_claim_id` → `IngestionEventClaim.id` |

Each version is already a distinct `Document` row within a
`DocumentFamily`. No persistent extraction-run entity exists in the
accepted ingestion model, so none is invented for Phase 17. The citation's
independent durability still comes from `cited_text_verbatim` and its
content digest; the ingestion-event claim supplies the available durable
production lineage.

### Generation assembly input

Laravel assembles generation from one immutable internal snapshot containing:

- the original question;
- the final authorised `RetrievalResult`;
- already-resolved temporal-authority facts;
- already-resolved applicability/location facts;
- authorised workspace scope, retained application-side;
- correlation and quality-lineage context.

Assembly reflects the decisions already made by `EligibilityResolver`. It
must not re-resolve temporal authority, historical references, location,
applicability or eligibility.

### `rc1` response alternatives

The `generation.answer` response has three mutually exclusive semantic
alternatives:

1. completed `GenerationResult` (`ANSWERED`, `QUALIFIED` or
   `INSUFFICIENT_EVIDENCE`);
2. typed `GENERATION_CONTEXT_BUDGET_EXCEEDED` structural failure;
3. typed `GenerationProviderError`.

The context-budget failure is neither a generation business outcome nor a
provider error. Laravel may raise it before an `rc1` call. Python may return
it after deterministic provider rendering/token measurement rejects the
package and before any provider call. The exact JSON schema is R17-S02 work;
the distinction is fixed by ADR-0023.

## Final stage responsibilities

### R17-S02

R17-S02 owns:

- language-neutral generation contracts;
- `GenerationOutcome` and related enums/value objects;
- `GenerationRequest` and `GenerationResult`;
- `answer_parts[]` structural invariants;
- Laravel's deterministic context-packing policy;
- request-scoped evidence-handle mapping;
- `GenerationRequest` assembly;
- deterministic result validation;
- `GeneratedAnswer`, `AnswerPart` and `EvidenceSnapshot` persistence;
- migrations and model relationships;
- the generation configuration/fingerprint contract;
- provider-neutral Python request/result models and `Generator` interface;
- the `rc1` `generation.answer` contract extension.

R17-S02 does not implement provider prompt wording, the OpenAI adapter or a
live provider call.

### R17-S03

R17-S03 owns:

- deterministic provider-specific rendering;
- exact prompt wording and prompt version;
- the OpenAI adapter and initial gpt-5-mini profile;
- generation-specific strict structured-output verification;
- provider-specific token measurement;
- bounded provider retry and provider-failure mapping;
- isolated real-provider verification.

## Verification performed

- Confirmed ADR-0023 still has `Status: Accepted`.
- Confirmed the clarification is visibly dated 2026-08-16 and explicitly
  bounded as a same-day pre-implementation repository mapping.
- Confirmed no architectural decision, retrieval behaviour, planner,
  eligibility, calibration, threshold or benchmark behaviour changed.
- Confirmed `IMPLEMENTATION_GUIDE.md` carries the final R17-S02/R17-S03
  responsibilities above.
- Confirmed `tasks.json` mirrors the guide and remains valid JSON.
- Confirmed `PROJECT_ROADMAP.md` and the historical
  `docs/journal/2026-08-16-r17-s01-define-generation-provider-boundary.md`
  were not changed by this reconciliation.
- Confirmed no R17-S02 implementation began and no provider was called.

## Next step

R17-S02 is ready to implement the provider-neutral generation contracts and
Laravel-owned application foundation without inventing architecture. Stop
before provider-specific prompt rendering or OpenAI integration; those
remain R17-S03.
