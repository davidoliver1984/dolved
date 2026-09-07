# h16/h21 — missing history and generation-retry diagnostics

2026-09-07. R28-S04 / R01P remain **in_progress**. This is a bounded correction and known-case regression, not independent confirmation or a correctness pass rate. No R02, push, new questions, index change or re-embedding.

## Identity and preserved evidence

- Starting HEAD: `4083d99f358853610a50888d39d5b054a8edaf58`.
- Starting 941-file source aggregate: `915361f671c95b4af9a2529021dbd955f96d358ad8c41022957cb74ac5c10056`.
- Corrected 943-file runtime aggregate, frozen before and verified unchanged after execution: `8330d4303fa63ccbe33264596c3653bf14c815a375a03af1076e038f644bf78e`.
- Evidence root: `/private/tmp/dolved-h16-h21-correction.64BvoY`. Includes full four-case JSON observations, answers, planner fields, citations, dispatches, receipts, freeze, manifest, checksums and `actual-results.md`.
- Following the host restart, the original host temporary directory was missing. The surviving container round-six records were copied, not modified, into `/private/tmp/dolved-round6-recovered.RVkU7z`. All 30 raw case records survived; the recovered question file matches `379ba24897b66837d13dcf098ee4d5bec7fe735ef1dc65cf4dd7569e3911a087`. This recovery is not a claim that the lost outer host package was recovered byte-for-byte.

## h16: verified absence is not ambiguity

The recorded planner bound both needs to family `532f0a90-e66a-4cfe-a61b-c179320d5144`, with `authority.kind=previous`. Need n1 reached `HistoricalReferenceResolver` and stopped at `historical_reference_unresolved`; generation never ran. The isolated database confirms exactly one retained document, `42107dbf-dcdd-4713-b530-34b57d43212e`, indexed, effective 2025-04-01, not withdrawn.

`EligibilityResolver` now emits `no_previous_version` only for a matched, single authorised family with exactly one retained and attained version which is currently eligible under the existing index/applicability checks. No unavailable, ambiguous, multi-family or multi-version scope is widened. The historical resolver itself is unchanged. Per-need gaps remain explicit; the aggregate controlled response uses this reason only when every need has the same verified missing-history reason and no evidence is selected. Mixed answerable needs retain their evidence.

The controlled response is deliberately library-scoped: “Only one version of the requested document is available in this knowledge library, so there is no earlier version available to compare.” It does not claim that an earlier version never existed anywhere.

## h21: two different rejection layers

Historical attempt 1 received OpenAI HTTP 200 and was rejected by `answer_parts.evidence_ids / citation_ownership`. Its receipt recorded 25,810 input tokens, 541 output tokens and USD 0.0062097 estimated cost. The rejected answer parts were not persisted. The exact original cited-ID/need-ID mismatch remains **unavailable**, not reconstructed or claimed proven.

Historical attempt 2 used request ID `3f08b368-f967-4dfe-bf3c-b76131844ddb` and body digest `b85b7e78ba95186f5ea7f8f5a84527bb5f616d920f658146a5072f9d9203dc6a`, identical to attempt 1. The preserved AI log at `2026-09-07T18:00:23.986026+00:00` records `verification_outcome=replay`, followed by HTTP 401 on `generation/answer-needs`. It never reached OpenAI. The old generic `GenerationException` was then incorrectly labelled `laravel_result_validation / unclassified_validation`.

The correction adds an allowlisted replay response marker after authenticated replay detection, a typed Laravel service-authentication exception, truthful service-layer category/code/status, and a zero-provider-attempt/token/cost receipt for that rejection. An unmarked 401 remains `authentication_rejected`, not an assumed replay. No response body, credential or header content is persisted. Round-six's 127 dispatch count must not be equated with provider attempts: the replay dispatch accounts for one of them.

Both Python and Laravel citation-ownership checks now capture the first rejected need ID, zero-based answer-part index, cited evidence handles and allowed handles. The capture is strictly bounded (n1–n3, index 0–127, at most five evidence handles per list, canonical handle format and maximum 16 characters); arbitrary fields/prose are excluded. Invalid diagnostic data cannot relax the rejection. The shared response schema is synchronized. Citation ownership and membership enforcement are unchanged; no output is repaired or remapped.

## Retry identity finding — follow-up required before round 7

`GenerateGroundedAnswer::prepare()` assembles `$request` once before the validation-attempt loop. Both calls reuse this object. `GenerationClient` signs the same request ID/body again. The existing provider-free test explicitly asserted identical request bodies: preserving identical validation input was intentional in this implementation. However, there is no implemented separation between stable semantic input and fresh validation-attempt identity.

This is **not** a response-idempotency cache. `ReplayCache` stores previously accepted request IDs and rejects them during its freshness window; it does not return a prior successful generation. The test's fake HTTP responses bypassed that real authentication boundary and therefore allowed a second response that the actual service could reject.

The validation loop distinguishes whether a returned response was rejected before allowing another call, but does not construct a distinct attempt identity. A Laravel transport failure with no payload is not eligible for this validation retry; provider-native retries occur inside the adapter after service authentication. Thus this is a same-request construction defect in the validation path, not a demonstrated deliberate transport-replay recovery design.

**Request identity remains unchanged in this commit, as David explicitly required.** A separate bounded correction is needed before relying on validation retry in round 7: fresh authenticated identity for a new validation-triggered generation while preserving the same authorised semantic input, with real signed-boundary proof and truthful lineage/accounting. No transport replay guarantee should be assumed. h21 succeeding without a retry in this regression does not resolve that defect.

## Verification and live results

Provider-free: 277 Python tests; 216 Laravel tests / 907 assertions; three optional cross-service bridge cases skipped because the test bridge was not running. Ruff lint/format, five-source-file mypy, scoped Pint, generated JSON-schema equality and `git diff --check` passed. Tests cover single-version direct response, retained real history/support, unchanged citation rejection with safe ownership detail, unsafe diagnostic exclusion, authentication-versus-replay distinction, and identical-request validation retry rejected with zero provider usage.

Exactly h16/h21/g18/g19 ran once, in that order, using the existing primary workspace and 982-chunk generation. g18 and g19 were verified previously completed two-need controls from round five; no new questions were authored.

| Case | Retrieval | Generation | Result | Retry |
| --- | --- | --- | --- | --- |
| h16 | insufficient_evidence, verified no_previous_version | not invoked | Direct library-scoped no-earlier-version response | None |
| h21 | evidence_found | answered | Fridge temperature and emergency-order questions answered | None |
| g18 | evidence_found | answered | Handover and outbreak questions answered | None |
| g19 | evidence_found | answered | Waste and social-media questions answered | None |

All persisted citations resolve to canonical chunks in the authorised primary workspace. Full actual text and citation identities are in the evidence package. These observed results are not an independent correctness score.

Usage: 19 provider requests; zero native or validation retries; 158,874 input tokens; 4,824 output tokens; 71.286390 seconds; USD 0.04103789 estimated cost, within USD 5 / 90 minutes. No unknown usage rows. Configured-rate estimates are not invoices. No AWS calls or additional question runs. No source tuning during execution.

David authorised one coherent local correction commit after verification. Plan/tracker remain R28-S04/R01P in progress, with the retry-identity follow-up explicitly held for review before another confirmation. Unrelated untracked material is excluded. No push.
