# Retrieval Call protocol (`rc1`)

This directory is the language-neutral contract for ADR-0018's synchronous
Laravel-to-Python retrieval calls. It is independent from the ingestion-worker
protocol.

Laravel signs every request as the `retrieval-caller` principal. Python verifies
the signature, freshness, purpose, signed/body identity and freshness-window replay
cache before processing the body.

## Endpoints and purposes

| Endpoint | Purpose | Schema |
|---|---|---|
| `POST /api/internal/retrieval/plan` | `retrieval.plan` | request: `plan-v1.schema.json`; response: `plan-response-v2.schema.json` |
| `POST /api/internal/retrieval/search` | `retrieval.search` | `search-v1.schema.json` |
| `POST /api/internal/retrieval/rerank` | `retrieval.rerank` | `rerank-v1.schema.json` |
| `POST /api/internal/retrieval/corpus/rebuild-batch` | `retrieval.corpus.rebuild` | `corpus-rebuild-batch-v1.schema.json` |
| `POST /api/internal/retrieval/corpus/verify` | `retrieval.corpus.verify` | `corpus-verify-v1.schema.json` |
| `POST /api/internal/retrieval/generation/answer` | `generation.answer` | request: `generation-answer-v1.schema.json`; response: `generation-answer-response-v1.schema.json` |

## Signed headers

- `X-Retrieval-Caller-Key-ID`
- `X-Retrieval-Caller-Timestamp`
- `X-Retrieval-Caller-Workspace-ID`
- `X-Retrieval-Caller-Request-ID`
- `X-Retrieval-Caller-Purpose`
- `X-Retrieval-Caller-Signature`

The canonical UTF-8 string is seven fields separated by one newline:

```text
<timestamp>\n<method>\n<request-path>\n<body-sha256>\n<workspace_id>\n<purpose>\n<request_id>
```

The signature header is `rc1=` followed by the lowercase HMAC-SHA256 hex digest.
Request bodies use exact compact JSON bytes for signing. Production transport must
use authenticated TLS; HMAC does not provide confidentiality.

The rerank request contains canonical chunk text that Laravel has already hydrated
and rechecked for eligibility. Request bodies, questions, chunk text, credentials
and signatures must never be recorded in logs, traces or error responses.
For `COMPARE`, Laravel may submit the same canonical chunk on both independently
resolved sides. Candidate and result identity is therefore the pair `(side,
chunk_id)`: the provider is called separately for each side, ranks are contiguous
within each side, and `top_k` is applied independently to each side. The sides are
never merged into one reranking list.

The planning request remains version 1. Its additive response version 2 carries
ADR-0022's typed temporal selector, plural unresolved location references and a
privacy-safe classifier-lineage fingerprint. Python classifies language only;
Laravel resolves dates, authority, aliases, applicability and eligible scope.

Corpus rebuild is an out-of-request rollout operation. Laravel supplies bounded
canonical chunk batches; Python computes both vector axes behind the existing
provider boundaries. Verification compares the complete expected point identity
set, payload lineage and vector schema before Laravel atomically activates the
new generation.

`canonicalisation-vectors.json` contains ADR-0018's normative cross-language test
vector. Every deliberate retry uses a fresh request ID and a fresh signature.

`generation.answer` preserves the same authentication, freshness, replay and
body-integrity rules. Its response is exactly one of a completed business result,
a structural `GENERATION_CONTEXT_BUDGET_EXCEEDED` failure, or a typed provider
error. The structural failure is neither `INSUFFICIENT_EVIDENCE` nor a provider
error.
