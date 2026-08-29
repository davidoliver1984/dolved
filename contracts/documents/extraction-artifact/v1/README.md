# Document extraction artifact V1

This directory is the language-neutral ADR-0032 contract for Dolved's
ownership-free, canonical `NormalisedDocument` representation.

`document-extraction-artifact-v1.schema.json` owns the wire shape.
`canonicalisation.json` owns RFC 8785 canonicalisation and the three SHA-256
identities: complete artefact, projection manifest, and warning manifest.
`canonicalisation-vectors.json` is the shared PHP/Python conformance fixture.

Workspace, document, family, and storage identities are intentionally absent
from canonical bytes. Laravel binds those facts externally when persistence is
implemented in R23-S03b.
