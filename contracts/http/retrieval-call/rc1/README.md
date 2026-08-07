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
| `POST /api/internal/retrieval/plan` | `retrieval.plan` | `plan-v1.schema.json` |
| `POST /api/internal/retrieval/search` | `retrieval.search` | `search-v1.schema.json` |

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

`canonicalisation-vectors.json` contains ADR-0018's normative cross-language test
vector. Every deliberate retry uses a fresh request ID and a fresh signature.
