# Dolved Care Engineering Benchmark V3

This directory is the in-progress `AUTHORING` release of Benchmark V3.

It contains the immutable catalogue boundary required before case authoring:

- the unchanged Benchmark V2 organisation model;
- the V3 taxonomy binding;
- 71 document families and 93 document versions;
- all 93 canonical V2 Markdown sources retained byte-for-byte;
- V3 source digests, document-scoped facets, relationship identities and
  leakage groups;
- V2-to-V3 document lineage;
- the dedicated catalogue review;
- deterministic source checksums;
- 44 reviewed semantic cases and one explicitly draft case across 11 care and
  operational domains, with digest-bound reviews for every reviewed case.

Every document version is classified as `METADATA_ENRICHED`: its canonical
Markdown is unchanged, while its catalogue record gains V3 metadata. No source
is classified as revised, retired or new.

The authored cases remain pre-split at the benchmark-release level. A separate
44-case calibration population is frozen under
`tests/evaluation/calibration-populations/dolved-care-engineering/v3/v1`; it
excludes the one acceptance-only comparison case that cannot reach reranking.
That frozen population and all CAL-EXP artefacts retain their historical case
content. Post-calibration review returned
`v3.infection.current.outbreak-no-authority` to draft because its family-empty
expectation cannot represent ADR-0018's workspace-wide eligible scope; four
COMPARE cases now credit only their source-backed side rather than treating a
missing fact in one family as an empty workspace-wide side.
No aggregate benchmark authoring review, authority-window artefact or final
release checksum exists at this lifecycle stage. A future source revision or
genuinely new source must receive a new identity and explicit release lineage;
it must not rewrite the catalogue foundation in place.
