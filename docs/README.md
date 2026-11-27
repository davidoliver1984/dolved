# Documentation

Use the document type that matches the material's purpose. Requirements and behaviour belong in specifications; significant choices and their rationale belong in architecture decision records; sequenced work belongs in delivery plans; operating procedures belong in runbooks.

Implementation tasks should link to the specification that defines their behaviour,
constraints, interfaces and acceptance criteria. Ordinary specification maintenance does
not require an ADR. Add an ADR only when the work makes a significant architectural choice
whose rationale and alternatives need a durable decision record.

## Product and feature specifications

See [current Dolved and Dify-facing product requirements](specs/README.md#current-dolved-and-dify-facing-product-requirements)
and [shared governance and product requirements](specs/README.md#shared-governance-and-product-requirements).

## Data and API contracts

See the [specification applicability index](specs/README.md), which distinguishes
Dolved-owned contracts from retained custom RAG contracts.

## Quality specifications

See the quality entries under [retained custom RAG contracts](specs/README.md#retained-custom-rag-contracts).

## Architecture and decision records

See the [ADR index](adr/README.md). ADRs record consequential choices and rationale; they are not the default home for detailed feature requirements.

## Implementation and delivery plans

- [Project roadmap](../PROJECT_ROADMAP.md)
- [Implementation guide](../IMPLEMENTATION_GUIDE.md)
- [Project journey](PROJECT-PROGRESSION.md)
- [Dolved–Dify delivery plan](operations/dolved-dify/DOLVED-DIFY-DELIVERY.md)

## Operations and deployment

- [Operations documentation](operations/)
- [Private-pilot acceptance and release decision](operations/dolved-dify/PRIVATE-PILOT-ACCEPTANCE-20261127.md)
- [Current Dolved–Dify pilot architecture](operations/dolved-dify/CURRENT-ARCHITECTURE.md)
- [Pilot security acceptance](operations/dolved-dify/PILOT-SECURITY-ACCEPTANCE-20261126.md)
- [Invited-pilot acceptance and onboarding checklist](operations/dolved-dify/PILOT-LAUNCH-CHECKLIST-20261126.md)
- [Dolved–Dify production configuration](operations/dolved-dify/PRODUCTION-DIFY-CONFIG.md)
- [Legacy custom RAG recovery](operations/dolved-dify/LEGACY-RAG-RECOVERY.md)

The bounded invitation-only pilot is approved under its documented risk and coverage
boundary. Invitations and onboarding remain separate operational actions. The accepted
pilot architecture uses stock self-hosted Dify behind Dolved. Legacy custom RAG recovery
material remains available for recovery and traceability; it is not the Dify implementation.

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

## Forward workflow: bounded branding

The next bounded platform-admin branding task starts from the
[Product Interface and Design System](specs/product-features/product-interface-and-design-system.md),
[Administration and Tenant Control Plane](specs/product-features/administration-and-tenant-control.md)
and the branding seam in
[Document Governance Notifications and Reminders](specs/product-features/document-governance-notifications-and-reminders.md).
Specify or extend the bounded behaviour and acceptance criteria before implementation.
[ADR-0041](adr/0041-use-stock-self-hosted-dify-for-single-client-delivery.md)
continues to constrain the single-client Dify delivery architecture. A further ADR is
needed only if branding introduces a new consequential architectural choice.
