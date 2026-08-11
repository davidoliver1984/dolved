# Dolved Care Engineering Benchmark

`dolved-care-engineering` is a permanent, repository-owned synthetic benchmark
for retrieval engineering. It models the deliberately fictional Alderbridge
Care Group and is designed to coexist with the smaller foundation corpus and
future separately governed customer corpora.

The benchmark is authored in controlled releases. Version 1 contains the
complete initial engineering corpus, including its organisation blueprint,
document catalogue, canonical Markdown documents, semantic cases and frozen
split assignments.

The catalogue's 71-family, 93-version plan and the 92-case split targets are
coverage guides, not filler quotas. A planned item may be revised or removed
before the first complete release when it does not add deliberate retrieval
difficulty; a baselined release is immutable.

## Sources of truth

- `organisation.json` defines the fixed evaluation clock, location hierarchy,
  aliases and shared terminology.
- `document-catalog.json` plans every family/version and owns governance,
  lineage, applicability and relationship metadata.
- `documents/` contains canonical Markdown source documents. Derived PDF or
  DOCX files may be added later, but cannot replace these sources.
- `cases/` contains reviewable case shards. `compiled/corpus.json` is generated
  deterministically from them.
- `splits/v1.json` owns the 42 engineering/tuning, 28 threshold-calibration and
  22 sealed held-out case assignments. Related variants and semantic clusters
  remain together, and held-out cases must not be used during tuning or
  threshold calibration.

`case_id` is the stable semantic case identifier required by ADR-0019. It must
not be coupled to a phrasing variant, chunk, extracted element or normalised
element.

Run `make evaluation-benchmark-compile` to validate and regenerate the compiled
benchmark and checksums. This command performs no retrieval optimisation and
makes no provider calls.
