# Record note 3 — K2 pre-dispatch source confirmation (2026-11-15T15:30Z)

Reviewer: Claude. Read-only checks against the isolated dolved-r28-s03 stack (postgres in a READ ONLY transaction, rolled back; Qdrant read via scroll/count). No writes.

- Document 52e08196-c1b5-4783-8c1c-e14f23315067 (faq-data-access-requests.txt, db id 98) belongs to workspace 5323cd6b-8b7a-40f9-98b0-9413c75f643e.
- Its chunk 238d917b-d326-508e-aa36-2bab4075fda8 is assigned to the active primary generation b24a79c2-875e-4f7c-8fa9-3681db92ae73 (it is also in the older generations 77886946… and 795fa5fa…).
- Qdrant collection rag-platform-vectors-v1 has exactly one point with that chunk_id in generation b24a79c2…, workspace 5323cd6b…, document_id 52e08196…, publication_status=published.

Outcome: CONFIRMED. A neutral confirmation, with no source identity, was released to the execution folder.
