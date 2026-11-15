# Needs generation v6 — independently authorised physical sharing

Extends v5; historical envelopes remain readable. A physical passage is supplied
once. Its `temporal_authority.validated_need_associations` maps each allowed need
to its own `temporal_authority`, `applicability_context`, and `side`.

`evidence_needs[].evidence_ids` may overlap only in v6 and only where that passage
has the corresponding validated association. All family/version/target/interval
checks use the receiving association, never the first owner's authority.
Non-temporal paragraph citation ownership remains strict. v5 temporal-use receipts
continue to partition paragraph citations by (part, need); v6 result echoes v6.

Five physical passages, three needs and four search lanes remain the limits.
Assembly checks canonical workspace/document/chunk/ingestion/text integrity and
versioned family identity. Selected per-need associations survive persistence in
`temporal_context.evidence_associations`; source snapshots remain physical and
citations remain explicit. Old receipts need no rewriting.
