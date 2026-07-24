# Event Contracts

This directory contains versioned, language-neutral event contracts shared
between Laravel and the Python services.

These contracts will eventually be defined as JSON Schema documents.

Planned events include:

- Document Ingestion Requested
- Document Ingestion Completed
- Document Ingestion Failed

Principles:

- Versioned
- JSON only
- Language-neutral
- Tenant aware
- Traceable
- Idempotent

Laravel job serialization is **not** a cross-language contract.