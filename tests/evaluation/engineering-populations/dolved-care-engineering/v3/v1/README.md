# Benchmark V3 engineering population V1

This is the first independent engineering population for Benchmark V3. It is
derived only from historical Benchmark V2 `engineering_tuning` cases whose
semantic and leakage boundaries remain independent of the spent V3 calibration
population.

The mechanical audit covers all 42 historical engineering cases:

- 7 are retained with V3 metadata enrichment;
- 1 uses an already-reviewed V3 reconciliation;
- 34 remain blocked because their family, leakage group or applicability
  semantic boundary is calibration-owned;
- none is silently copied, renamed across a protected boundary, or retired.

The resulting population contains 8 semantic cases and 24 variants. Its small
size is an independence result, not a quota decision. It directly exercises
the corrected controlled-drugs historical semantics. The medication error-form
questions provide an independent actor/recipient-versus-place analogue. The
Coventry, Midlands and South West aliases, hierarchy/applicability inheritance,
exact-date fidelity and moving/handling comparison corrections are not
represented without crossing the calibration boundary.

`provisioning-definition.json` is deliberately `DEFINITION_ONLY`. It binds the
current organisation, complete 71-family/93-version catalogue, source digests,
planned deterministic platform identities, chunking configuration and accepted
dense/sparse profile lineage. It does not fabricate canonical chunk or Qdrant
point identities before the normal ingestion workflow has created them. Dense
materialisation requires Voyage and was not run during this provider-free task.

## Split isolation

`independence.json` records case, semantic-cluster and leakage-group digests for
engineering and calibration and proves zero intersections. Held-out remains
unassigned and unavailable; no held-out content or identity list is created.
The future runtime override mounts only the engineering corpus, expectations,
organisation, catalogue and provisioning definition as individual read-only
files. It does not mount calibration, CAL-EXP artefacts, held-out or a broad
benchmark/evaluation directory.

## Process note

During earlier metadata inspection, the V3 medication authoring file was
accidentally printed. The calibration population was already spent development
evidence. Inspection stopped immediately; no tuning, case selection or
modification was made from the displayed content. Held-out was not accessed.
Calibration artefacts remain unchanged.
