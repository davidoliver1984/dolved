# EXP-0006 consolidated engineering confirmation

`EXP-0006-adr0022-v4-consolidated-engineering-confirmation` is an immutable,
engineering-only confirmation run for the bounded location/entity and
temporal/historical corrections made after EXP-0005.

The experiment retains the exact EXP-0005 Benchmark V2 engineering population,
provisioning lineage, hybrid generation, retrieval configuration, and
experimental evidence threshold. Its only intended application-lineage change
is the accepted `adr-0022-v4` planner prompt and the deterministic
Laravel-owned location aliases already committed before this definition.

The API and AI services receive only the engineering snapshot and planner
expectations. Calibration, held-out, broad evaluation, and benchmark-source
paths are forbidden by the runtime preflight.

EXP-0005 remains immutable and is the historical comparison result. The run is
diagnostic: it does not tune or promote retrieval configuration or threshold
policy.
