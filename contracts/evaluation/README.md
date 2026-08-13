# Evaluation contracts

The V2 calibration-governance contracts include:

- `calibration-population-specification.schema.json` for the versioned
  threshold-selection population, semantic diversity and independence rules;
- `calibration-independence-evidence.schema.json` for privacy-safe mechanical
  case and semantic-cluster split comparisons;
- `calibration-threshold-execution-evidence.schema.json` for privacy-safe,
  post-provider reranker-lineage and score-adequacy evidence;
- `calibration-compatibility-requirements.schema.json` for versioned,
  policy-owned population requirements and explicit slice groups;
- `calibration-population-manifest.schema.json` for deterministic,
  privacy-safe case composition metadata;
- `calibration-compatibility-result.schema.json` for the provider-free,
  fail-closed preflight decision.

These contracts govern whether a calibration population is adequate. They do
not change retrieval, threshold application or provider behaviour.

The population manifest binds intrinsic-slice and evaluation-facet taxonomy,
mechanical split-independence evidence and a versioned human authoring-review
artefact. Compatibility results bind the exact compatibility-policy digest so
future run definitions and manifests can reproduce the decision.

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

Version `v3` defines the schema and taxonomy foundation for the staged immutable
Benchmark V3 release. Its taxonomy owns exact domain, intrinsic-slice and
evaluation-facet identifiers and their permitted case, variant or document
scope. Calibration population specifications may require those identifiers but
must not create a private vocabulary. V3 does not mutate or supersede V2 run
artefacts; each benchmark release remains bound to its original contract. The
current case-free `FOUNDATION` release binds the retained V2 source catalogue
and dedicated catalogue review before case authoring begins.
