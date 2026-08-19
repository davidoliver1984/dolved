# Document deletion requested

`v1.schema.json` is the authoritative language-neutral event contract for the
bounded vector cleanup authorised by Laravel under ADR-0025. Laravel fixes and
persists every `vector_scope`; Python may only delete and verify those scopes.

Breaking changes require a new schema version. The event never authorises
collection removal and contains no source or chunk text.
