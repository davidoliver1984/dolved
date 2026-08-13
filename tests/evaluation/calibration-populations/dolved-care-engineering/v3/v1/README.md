# Benchmark V3 calibration population V1

This immutable population projection contains 44 reviewed semantic cases and
132 wording variants from the Benchmark V3 authoring release. It is owned by
the evidence-threshold calibration workflow and is not a benchmark-level split
release.

The population includes every reviewed V3 case except
`v3.infection.compare.outbreak-current-v2`. That case is deliberately
acceptance-only: eligibility establishes the missing primary comparison scope
before reranking, so it cannot supply threshold-calibration evidence. The case
remains in Benchmark V3 and is not altered.

`corpus.json` is the human-readable frozen input. `population-manifest.json`
and `composition-compatibility.json` are deterministic provider-free outputs
from `scripts/evaluation/compile_v3_calibration_population.py`. Composition
compatibility must pass before provider execution; execution compatibility is
evaluated separately after the single provider pass.

No engineering or sealed held-out cases are present in this population. Their
identity sets are represented as empty in this V3 authoring release, and no
protected corpus content is required to compile or validate these artefacts.
