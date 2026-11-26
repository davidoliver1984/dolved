# Current Dolved–Dify pilot architecture

The current delivery is an invitation-only, single-client pilot. Dolved owns the user
experience, identity/session boundary, workspace membership and policy enforcement,
document governance, source-link permissions, feedback/review controls and operational
views. Stock self-hosted Dify performs the bound retrieval and generation workflow behind
Dolved's server-side integration.

```text
Pilot user
  -> HTTPS Dolved web
  -> Dolved API (identity, tenancy, governance, audit and provider boundary)
  -> private backplane
  -> stock self-hosted Dify (workflow, retrieval and generation orchestration)
  -> configured external model/embedding providers only when an authorised workflow runs
```

Dify administration, databases, vector storage and plugin services are not public pilot
interfaces. Provider credentials remain server-side. External providers may receive the
bounded workflow input and retrieved context when an authorised live question or ingestion
operation requires it; they do not receive Dolved session credentials or unrestricted
workspace data.

This architecture does not make the pilot production-certified, highly available or ready
for public/multi-client hosting. See [ADR-0041](../../adr/0041-use-stock-self-hosted-dify-for-single-client-delivery.md),
the [delivery record](DOLVED-DIFY-DELIVERY.md), the
[security acceptance](PILOT-SECURITY-ACCEPTANCE-20261126.md), and the
[legacy custom-RAG recovery guide](LEGACY-RAG-RECOVERY.md).

The repository's custom Laravel/Python/Qdrant RAG pipeline remains preserved as an
independent development and recovery path. Dify providing current pilot capability does
not complete or delete that roadmap.

