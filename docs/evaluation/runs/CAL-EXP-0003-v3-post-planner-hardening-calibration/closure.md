# CAL-EXP-0003 closure

CAL-EXP-0003 completed one provider pass over 44 calibration cases and 132
variants from repository commit
`e3a356d5872f43611572c33c0d8f2ee09e5e8002`. All 132 planner calls produced
valid typed plans, all 132 variants executed retrieval, and no planner,
retrieval, provider or systemic failure invalidated the population.

Post-provider compatibility passed. Provider-free replay evaluated 254 distinct
observed reranker scores, the exact control, and one above-maximum boundary: 256
boundaries in total. No alternative both strictly improved case-first expected
EvidenceUnit recall and satisfied every predeclared non-regression constraint.
The committed policy therefore retained the exact control value:

`evidence_threshold = 0.337890625`

This value is calibrated only for the lineage in `calibration-decision.json`.
It has not been tested against the sealed held-out population, has not been
promoted to production, and is not a universal Voyage or reranker threshold.

At the retained threshold, case-first EvidenceUnit recall was `0.798246`,
benchmark precision `0.198246`, MRR `0.791667`, nDCG `0.774316`, and
controlled-rejection correctness `0.166667`. The replay accepted 127 expected
EvidenceUnits, rejected 29, and accepted 292 candidates without annotated
EvidenceUnit credit. Those candidates are **uncredited / unannotated**, not
authoritative negative judgements.

The authoritative provider observation remains
`a48e1dca7df0aeee10345bedae3aab007f6925c12fdaef5ec17b3e26c54b14e0`.
Provider-free outputs were generated twice with byte-identical hashes. No
provider call or held-out access occurred during closure.
