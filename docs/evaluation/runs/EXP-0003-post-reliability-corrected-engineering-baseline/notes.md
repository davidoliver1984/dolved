# Hypothesis

The verified provider retry and shared-cooldown corrections improve operational
reliability without changing retrieval semantics or configuration.

# Change From Baseline

The evaluated application and retrieval lineage is
`0adf1619d1a46f79a0b39b210152dcbae066bdcb`. After 30 durable observations,
the PHP runner exhausted its 128 MiB process memory limit because it retained
every decoded observation. The bounded-memory checkpoint/resume harness
correction is commit `c3bc27190a36a0a6a98a6d6609a5835ae6c62bf7`.

The runner-only correction did not change classifier, retrieval, benchmark,
provider, policy, threshold, eligibility, ranking, or reliability behaviour.
The immutable run manifest therefore continues to identify `0adf1619...` as
the evaluated application/retrieval commit. The separate
`resume-harness-lineage.json` records the execution-harness lineage truthfully.

# What Happened

All 30 existing checkpoints passed integrity and lineage validation and were
skipped before provider-facing work. The runner executed the 96 missing
engineering variants and produced exactly 126 unique, valid checkpoints.
Final application observations were streamed in deterministic benchmark order.

# What I Learned

Checkpoint durability and bounded-memory compilation are necessary evaluation
harness properties when candidate lineage makes individual observations large.
The correction held API-container memory approximately flat during the live
resume instead of allowing memory to grow with checkpoint count.

# Decision

Pending manual review of EXP-0003. No policy or retrieval configuration is
promoted by this run.

# Next Experiment

No next experiment is authorised until EXP-0003 has been reviewed.
