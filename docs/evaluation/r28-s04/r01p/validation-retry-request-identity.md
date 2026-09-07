# Validation-retry request identity — controlled live proof

2026-09-07. R28-S04 / R01P remain **in_progress**. No R02, new question set, push or full evaluation.

## Verified baseline and correction

Starting HEAD `72d97f32bd323ffff21d02e2263bd59acd0bef84`; tracked worktree was clean. Runtime aggregate matched `8330d4303fa63ccbe33264596c3653bf14c815a375a03af1076e038f644bf78e` before editing.

Only `GenerateGroundedAnswer::prepare()` production code changes. Once its existing eligibility predicate permits the single validation retry, it constructs a new immutable `GenerationRequest` with a fresh UUID and every other assembled field unchanged. The existing signer therefore signs a different request ID/body digest. No re-planning, re-retrieval, prompt modification, evidence remapping or validator relaxation occurs. Provider-native/transport retries and validation-retry eligibility/ceiling remain unchanged. Correct and controlled outcomes do not gain retries.

Both rejected and accepted receipts record `request_id` and `retry_of_request_id`; the original correlation identity remains stable. Persistence receives the accepted request identity. This separates logical correlation from the new authenticated generation attempt, without adding a new retry subsystem.

Corrected 943-file runtime aggregate, frozen before live dispatch and verified unchanged afterward:
`573b9e97f13043a63c05d31c66fa9e645531fe133b6ee1c2027728c4f870a8ef`.

## Provider-free proof

- Laravel tests execute the actual retry loop with controlled responses. They prove different UUIDs, body digests and signed headers, byte-identical serialized semantic input after removing only `request_id`, stable original correlation, accepted request identity and per-attempt receipt lineage.
- Cases retain one extra attempt only: local/remote validation rejection, exhaustion, transport failure after rejection, explicit service rejection, initial transport failure, and controlled insufficient evidence.
- Python TestClient exercises the **real production authentication/replay boundary and real generation adapter**, with only the provider client replaced by recording responses. First response fails citation ownership. Replaying its identity is still rejected with 401 and no extra provider call; the fresh identity passes authentication and produces a second accepted generation. Replaying that new identity is also rejected. The two fully rendered provider requests, including model instructions/input/schema/settings, are exactly equal.
- Full relevant suites: **278 Python passed; 216 Laravel passed / 941 assertions**. Three optional cross-service bridge tests skipped because that test bridge was not running; the dedicated real-authentication test above did run. Ruff lint/format, scoped Pint, scoped mypy (three unchanged boundary source files), schema tests and `git diff --check` passed. No provider calls during these tests.

## Live proof — deliberately induced rejection, not a natural model error

Evidence root: `/private/tmp/dolved-validation-retry-identity.WgtY7J`.

Exactly one h21 execution ran through the real production conversation path in the existing isolated primary workspace and its unchanged 982-chunk index. A temporary process-local HTTP response middleware preserved the first successful real OpenAI result, then changed only its first answer part's citation handles to unauthorised `ev-99` before Laravel validation. The production validator genuinely rejected that deliberately corrupted response with `answer_parts.evidence_ids / citation_membership`. The harness did not modify either provider request or the second response and was not installed in production code.

**This was controlled fault injection. The original first OpenAI output was successful; it was not a naturally occurring citation failure.** A natural validation rejection cannot be guaranteed on demand. The first original response and explicit injection record are retained separately so this distinction is independently verifiable.

The normal production retry then dispatched a **real second OpenAI call**, through real authentication, and accepted its result:

| Attempt | Request ID | Actual service/provider result | Laravel result |
| --- | --- | --- | --- |
| 1 | `c37e0860-8711-4ba6-bfe6-b6619f8fbcff` | HTTP 200, completed, one OpenAI request | Rejected only after harness citation mutation |
| 2 | `d274954e-48b3-4b9e-aaf4-b37c068e6c84` | HTTP 200, completed, one OpenAI request | Accepted; final outcome answered |

Both semantic-input hashes are `ca2bf2d891fde168c907b0f4ff8f5e1020f21961f01eb518f36be49562959472`. Body hashes differ: `5bd9b426629c942a9ec00ce6caa8b847b23daeb37270426913d6ec41f9cc7fed` then `eb842d0196964d158aa73882a7797e05caa884b17bef2220d00da39222172bb0`. Signed-header hashes also differ; no raw authentication headers are stored. Generation provider/model/prompt/adapter/fingerprint remain identical. Both receipts show `execution=provider_api`, `request_count=1`, `retry_count=0`.

The final answer addresses both fridge temperature and emergency-order procedure; all citations resolve to authorised canonical chunks. Full actual answer, needs, citations, request/response metadata and receipts are in `actual-results.md` and the JSON records. `verify-live-proof.js` independently checks the identity, accounting and acceptance assertions. This proves the transport mechanism, not a general answer-quality or independent-confirmation pass rate.

## Accounting

Seven physical provider requests: one planner, two query embeddings, two rerankings, two generations. Exactly one validation retry; zero provider-native retries. Total: **82,731 input tokens; 2,029 output tokens; 28.373490 seconds; USD 0.01463221 estimated**, within USD 5 / 90 minutes.

- First generation: 25,749 input / 583 output tokens; 7.998650 seconds; USD 0.00627845 estimated.
- Second generation (the retry): 25,749 input / 587 output tokens; 7.255990 seconds; USD 0.00185125 estimated.
- Other execution cost excluding the retry: USD 0.01278096. Wall time minus measured retry boundary latency: 21.117500 seconds, not a counterfactual timing guarantee.

All token/request rows are available; costs combine recorded pricing snapshots and configured-rate estimates, not invoices. No AWS, index changes, re-embedding, selective rerun or additional live case. The immutable historical h16/h21 evidence is untouched.

## Outcome and commit boundary

**The live proof observed a real second provider dispatch succeed.** The previously flagged same-request replay blocker is corrected and demonstrated under an intentionally induced Laravel validation rejection. Natural errors at other validators need not be manufactured to establish this transport property; the provider-free proof covers remote citation-ownership rejection as well.

David authorised one coherent local commit of the narrow implementation, tests and this report/plan/tracker update. No push. R28-S04/R01P remain in progress awaiting the separately prepared independent confirmation; no new questions were authored or selected for that confirmation.
