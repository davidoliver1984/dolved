# Specifications

Specifications define required behaviour, data shapes, contracts and acceptance boundaries. Their descriptive titles and paths are their current identities. Legacy ADR identifiers and former paths are retained in [Documentation Classification](../DOCUMENTATION_CLASSIFICATION.md).

Applicability is not the same as historical acceptance. The notes below describe how each specification relates to the current Dolved application and the bounded Dify pilot; they do not revoke an accepted requirement or declare a document obsolete.

## Current Dolved and Dify-facing product requirements

These specifications govern Dolved-owned behaviour around the private Dify services. They do not imply that Dify implements Dolved's tenancy, governance or user-interface rules internally.

- [Administration and Tenant Control Plane](product-features/administration-and-tenant-control.md) — Dolved-owned access, administration and tenant-control behaviour.
- [Product Interface and Design System](product-features/product-interface-and-design-system.md) — current Dolved product presentation and interaction requirements.
- [Document Family and Version Metadata](data-api-contracts/document-family-and-version-metadata.md) — Dolved-owned document and governance metadata, including records mapped to Dify documents.
- [Version Governance, Content Reuse and Family Deletion](product-features/version-governance-content-reuse-and-family-deletion.md) — governance behaviour retained at the Dolved boundary; custom-pipeline reuse details are not a description of Dify internals.
- [Knowledge Library Navigation](product-features/knowledge-library-navigation.md) — current Dolved knowledge-library behaviour.
- [Import Staging and Promotion](product-features/import-staging-and-promotion.md) — Dolved import and promotion behaviour; the current Dify delivery adds an integration behind this boundary.
- [Conversation and Streaming Domain](product-features/conversations-and-streaming.md) — Dolved-facing conversation, persistence and delivery requirements remain relevant; its custom RAG generation protocol is not the stock Dify implementation.

## Planned pilot branding

- [Tenant Branding and Correspondence](product-features/tenant-branding.md) — R29 product specification for platform-admin-managed workspace branding; implementation is in progress on the authorised pilot delivery track.

## Shared governance and product requirements

These remain repository requirements or planned product behaviour but are not, by themselves, evidence that every capability is active in the bounded pilot.

- [Document Versioning and Temporal Authority](data-api-contracts/document-versioning-and-temporal-authority.md) — authoritative Dolved version and applicability rules; integration-specific indexing remains separate.
- [Bulk Document Operations](product-features/bulk-document-operations.md) — governed bulk-operation behaviour.
- [Document Governance Notifications and Reminders](product-features/document-governance-notifications-and-reminders.md) — notification, reminder and email behaviour, including the reserved branding seam.

## Retained custom RAG contracts

These specifications remain authoritative for the repository's retained custom RAG implementation and recovery path. They do not describe stock Dify's extraction, indexing, retrieval or generation internals.

- [Canonical Extracted Document Contract](data-api-contracts/canonical-extracted-document.md)
- [Chunking Architecture and Contract](data-api-contracts/chunking-contract.md)
- [Ingestion Orchestration and Worker Result Contracts](data-api-contracts/ingestion-orchestration-and-worker-results.md)
- [Ingestion Publication and Recovery Semantics](data-api-contracts/ingestion-publication-and-recovery.md)
- [Retrieval Planning, Eligibility and Retriever Contract](data-api-contracts/retrieval-planning-eligibility-and-retriever.md)
- [Retrieval Planning Temporal and Location Reference Contract](data-api-contracts/retrieval-planning-temporal-and-location-reference.md)
- [Provider-Neutral Grounded Generation Contract](data-api-contracts/grounded-generation.md)
- [Retrieval Evaluation and Quality Gates](quality/retrieval-evaluation-and-quality-gates.md)
- [Retrieval Evaluation Evidence and Model-Assisted Evaluation Semantics](quality/retrieval-evaluation-evidence-and-model-assisted-semantics.md)

## Profile with integration-dependent applicability

- [Current-Policy Question-Pool Conversation Profile](product-features/current-policy-question-pool.md) — accepted for the custom current-policy conversation path. Whether its profile-specific behaviour is carried forward through the Dify pilot is not established by the reclassification and must be confirmed against the integration before reuse.

The current Dify architecture and delivery status are recorded by
[ADR-0041](../adr/0041-use-stock-self-hosted-dify-for-single-client-delivery.md), the
[Dolved–Dify delivery plan](../operations/dolved-dify/DOLVED-DIFY-DELIVERY.md) and the
[private-pilot acceptance](../operations/dolved-dify/PRIVATE-PILOT-ACCEPTANCE-20261127.md).
The [legacy recovery guide](../operations/dolved-dify/LEGACY-RAG-RECOVERY.md) preserves the
custom system without presenting it as the Dify implementation.
