# Needs generation v5: temporal associations

New temporal requests/results use envelope 5, `generation-result-v5`,
`grounded-generation-temporal-v3`, and `openai-responses-temporal-v3`.
Non-temporal execution is unchanged. Deploy PHP and Python together.

`temporal_uses` contains exactly one receipt per `(part_index, need_id)` linked
association with temporal targets, not one per paragraph. Each receipt adds a
non-empty, unique `evidence_ids` list. These IDs must be cited by that paragraph,
owned by that need, and valid for that need's selected target, family, version and
half-open interval. The union must account for every citation on the paragraph.
No evidence or temporal authority may be borrowed from another association.
Non-temporal need ownership remains unchanged.

PHP persists the validated associations with their targets in the existing
`temporal_context` JSON column as `temporal-answer-use-v2`. Rendering displays each
association's temporal basis. No migration is required. Historical v1 receipts
remain readable without rewriting records; Python's v4 request/result reader is
retained. Historical schema snapshots under v4 are unchanged.

This changes paragraph association structure, not temporal interpretation,
retrieval, ranking, eligibility, retries, or the planner's validators.
