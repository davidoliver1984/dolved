# Documentation

Use the document type that matches the material's purpose. Requirements and behaviour belong in specifications; significant choices and their rationale belong in architecture decision records; sequenced work belongs in delivery plans; operating procedures belong in runbooks.

## Product and feature specifications

See [Product and feature specifications](specs/README.md#product-and-feature-specifications).

## Data and API contracts

See [Data and API contract specifications](specs/README.md#data-and-api-contract-specifications).

## Quality specifications

See [Quality specifications](specs/README.md#quality-specifications).

## Architecture and decision records

See the [ADR index](adr/README.md). ADRs record consequential choices and rationale; they are not the default home for detailed feature requirements.

## Implementation and delivery plans

- [Project roadmap](../PROJECT_ROADMAP.md)
- [Implementation guide](../IMPLEMENTATION_GUIDE.md)
- [Dolved–Dify delivery plan](operations/dolved-dify/DOLVED-DIFY-DELIVERY.md)

## Operations and deployment

- [Operations documentation](operations/)
- [Dolved–Dify production configuration](operations/dolved-dify/PRODUCTION-DIFY-CONFIG.md)
- [Legacy custom RAG recovery](operations/dolved-dify/LEGACY-RAG-RECOVERY.md)

The accepted pilot architecture uses stock self-hosted Dify behind Dolved. Legacy custom RAG recovery material remains available for recovery and traceability; it is not the Dify implementation.

## Acceptance and reference material

- [Evaluation evidence](evaluation/README.md)
- [Testing documentation](testing/README.md)
- [Engineering journal](journal/README.md)

Evidence packages, journals and historical delivery records retain the terminology and identifiers that were true when they were created.

## Historical and superseded material

- Former ADR paths for reclassified specifications are retained as forwarding records.
- Drafting material under `adr/gpt_drafts/` is non-authoritative historical work.
- Files explicitly marked `initial`, archived, sealed or checksummed remain historical evidence and are not current navigation targets.

See [Documentation Classification](DOCUMENTATION_CLASSIFICATION.md) for the classification and provenance map.
