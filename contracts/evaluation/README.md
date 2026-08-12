# Evaluation contracts

Version `v1` contains the repository-owned formats defined by ADR-0019 and
ADR-0020. External evaluation frameworks must translate at the adapter boundary;
they do not own these schemas.

Version `v2` is an additive contract for named engineering benchmarks. It keeps
planner, eligibility, retrieval-evidence and outcome expectations distinct and
adds schemas for the organisation blueprint, document catalogue, split and
benchmark manifest. Existing V1 corpus and governance artefacts remain unchanged.
The V2 experiment-result schema also adds stage-level usage and cost observations.
It distinguishes provider-reported or snapshot-estimated cost from unavailable
pricing and genuine local zero-cost execution; unavailable values are never
silently represented as zero.
