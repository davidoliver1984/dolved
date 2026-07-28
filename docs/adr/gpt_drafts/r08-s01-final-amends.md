This is the final amendment pass for ADR 0007.

Do not rewrite the ADR.

Do not change its structure, headings, decisions, rationale or scope.

Treat the current document as accepted architecture unless a requested amendment explicitly changes wording.

Only perform the following refinement:

In the "Document lifecycle" section, revise the wording of the `UPLOADED` state.

Current wording:

> UPLOADED — the file is confirmed present in object storage. This is the point at which a Document has real content behind it, regardless of how that content arrived.

Replace it with wording that reflects the architectural principle established elsewhere in the ADR:

- A Document is distinct from an uploaded file.
- Future document sources (Google Drive, SharePoint, URLs, Git, etc.) should fit the same lifecycle without requiring new states.
- The lifecycle should therefore describe the presence of the authoritative source content rather than referring specifically to "the file."

Preferred wording (feel free to improve the prose without changing the meaning):

> UPLOADED — the authoritative source content has been confirmed present in object storage. This is the point at which a Document has confirmed source content available for downstream processing, regardless of how that content originated.

This is intended purely as a terminology refinement to keep the ADR internally consistent with its future source-agnostic architecture.

Return only the amended section and a short summary of the wording change.