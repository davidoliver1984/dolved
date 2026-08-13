# Evaluation contract V3

Evaluation contract V3 defines the contract foundation for the immutable
`dolved-care-engineering` Benchmark V3 release. It does not contain benchmark
documents, cases, split assignments, reviews or run artefacts.

## Release lifecycle

Benchmark V3 develops through explicit immutable stages:

- `FOUNDATION` binds the Benchmark V2 parent, taxonomy, organisation,
  document catalogue, source checksums and a dedicated catalogue review. It
  contains zero semantic cases and cannot claim case, split or final compiled
  artefacts.
- `AUTHORING` adds cases incrementally. Split assignment may remain incomplete,
  while taxonomy, parent lineage and catalogue integrity remain enforced.
- `COMPLETE` requires cases, a complete split, case authoring review, compiled
  corpus, authority windows, split identities and final checksums.
- `BASELINED` has the same completeness requirements as `COMPLETE` and records
  that the immutable complete release has become an accepted benchmark
  baseline.

The catalogue review is separate from case authoring review. It binds the
taxonomy, organisation, catalogue, every canonical source digest, the parent
V2 digest and reviewed document identities before any case needs to exist.

## Ownership

- `benchmark-taxonomy.schema.json` owns the permitted domain, intrinsic-slice
  and evaluation-facet identifiers.
- `benchmark-catalogue-review.schema.json` owns the machine and human evidence
  required to approve a case-free catalogue foundation.
- `taxonomy.v1.json` owns their human-readable definitions, categories,
  permitted scopes and lifecycle status.
- benchmark cases will own their intrinsic labels, facets, EvidenceUnits and
  expected outcomes.
- the calibration population specification may require representation of
  taxonomy identifiers, but may not introduce private taxonomy.
- split manifests will own assignment only; they do not redefine case truth.
- authoring reviews will bind human judgements to exact case and source
  digests. A passed schema never substitutes for human review.

Every contract that uses taxonomy identifiers binds the taxonomy identity,
version and SHA-256 digest. Domain, slice and facet references are exact and
case-sensitive. Alias or fuzzy matching is not permitted.

## Versioning and deprecation

Identifiers are immutable within contract V3. A deprecated identifier remains
declared and names an explicit replacement where one exists. Removing or
redefining an identifier requires a new major evaluation-contract version.
Adding a taxonomy entry requires a new taxonomy version and updated binding
digest; it must not silently alter an immutable benchmark release.

The V2 contract and Benchmark V2 remain historical and independently
validatable. V3 release lineage will identify its V2 parent, but no V2 file is
rewritten or migrated in place.

## Compilation ownership

The version-neutral command in
`scripts/evaluation/compile_engineering_benchmark.py` dispatches to separate V2
and V3 implementations under `apps/ai/app/evaluation/benchmark/`. Shared code
is limited to deterministic JSON, digest, schema and ADR-0017 authority helpers.
The V2 implementation retains its historical output format. The V3 compiler
owns taxonomy binding, scoped facet validation, source digests, relationships,
split identities, semantic/leakage grouping and release lineage.

V3 lineage is complete rather than selective: every parent and target case and
document-version identity must appear exactly once as retained, enriched,
revised, retired or new. The compiler verifies the accepted parent Benchmark V2
digest and every file covered by its checksum record before accepting lineage.
It never rewrites the parent release.
