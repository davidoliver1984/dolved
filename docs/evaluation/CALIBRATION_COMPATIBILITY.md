# Calibration population compatibility

An authoritative evidence-threshold calibration may start provider-backed
execution only after a provider-free compatibility preflight succeeds. This
implements the corpus/policy boundary established by ADR-0019, ADR-0020 and
ADR-0021; it does not change retrieval behaviour or those accepted decisions.

The compatibility boundary has independently versioned inputs and outputs:

- the threshold-selection policy identifies the rule that will select a
  threshold;
- `CALIBRATION_POPULATION_V1` references benchmark-owned labels and facets and
  defines minimum semantic-case populations, threshold-evaluable coverage,
  controlled-rejection, domain and independence requirements;
- compatibility requirements bind that specification and the threshold policy,
  and define the treatment of observed pre-threshold failures;
- a population manifest is compiled deterministically from the isolated
  snapshot's case identities, variant counts, intrinsic slice labels and
  expected outcomes;
- a compatibility result binds both inputs and reports whether provider
  execution is permitted.

The exact compatibility-requirements SHA-256 is also an input to validation
and an output of the result. A future calibration definition and run manifest
must bind that digest before execution; policy ID/version alone is not exact
lineage.

Cases are the adequacy unit. Multiple phrasing variants never inflate a slice's
population. Intrinsic benchmark labels remain unchanged. A policy group matches
only labels explicitly listed in its versioned definition; fuzzy, substring and
similarity matching are forbidden.

Controlled-rejection correctness is unavailable when its threshold-sensitive
population is empty; it must never be represented as a successful `1.0`.
Pre-retrieval acceptance outcomes are reported separately and never count as
threshold-selection evidence.

The benchmark owns both the intrinsic slice vocabulary and the controlled
`evaluation_facets` vocabulary. Compatibility fails when a required label or
facet is absent from the bound taxonomy, or when a required facet is declared
but unused by every matching population case.

Split independence is established mechanically outside the provider-capable
runtime. Its privacy-safe evidence binds calibration, engineering and held-out
case and semantic-cluster digests, the comparison method/version and exact
overlap counts. It contains no questions, EvidenceUnit text or document text.

Compile that evidence from three identity-only split artefacts before the
calibration runtime is created:

```bash
PYTHONPATH=apps/ai python scripts/evaluation/compile_calibration_independence_evidence.py \
  --calibration-identities /path/to/calibration-identities.json \
  --engineering-identities /path/to/engineering-identities.json \
  --held-out-identities /path/to/held-out-identities.json \
  --population-frozen-before-provider-execution \
  --output /path/to/private/independence-evidence.json
```

Each identity input contains only unique `case_ids` and
`semantic_cluster_ids`. The provider-capable runtime receives the compiled
evidence, not the protected split contents.

## Failure taxonomy

A missing or corrupt observation is distinct from a durable typed pipeline
failure:

- `lost_evaluation_case`;
- `planner_failure_before_threshold`;
- `retrieval_failure_before_threshold`;
- `provider_failure_before_threshold`;
- `incomplete_pre_threshold_lineage`.

The V1 compatibility requirements invalidate all of these for authoritative
calibration. Only controlled category and count metadata enters the manifest;
questions, provider responses, credentials, document content and evidence text
do not.

## Provider-free preflight

Run the validator before starting provider-capable calibration containers:

```bash
PYTHONPATH=apps/ai python scripts/evaluation/validate_calibration_compatibility.py \
  --snapshot /path/to/isolated/corpus.json \
  --threshold-policy tests/evaluation/policies/evidence-threshold-calibration/v1/policy.json \
  --requirements tests/evaluation/policies/calibration-compatibility/v1/requirements.json \
  --population-specification tests/evaluation/population-specifications/evidence-threshold-calibration/v1/specification.json \
  --independence-evidence /path/to/private/independence-evidence.json \
  --benchmark-taxonomy /path/to/private/benchmark-taxonomy.json \
  --authoring-review-evidence /path/to/private/authoring-review.json \
  --expected-compatibility-policy-sha256 <sha256-from-run-definition> \
  --population-manifest /path/to/private/population-manifest.json \
  --compatibility-result /path/to/private/compatibility-result.json
```

Exit status `0` and `compatible: true` are both required. Future calibration
run definitions must bind the compatibility-policy and compatibility-result
digests and recheck both before provider execution. Post-provider completeness validation supplies durable
observations with `--observations` and a privacy-safe
`--threshold-execution-evidence` artefact. Observed failures, missing lineage,
insufficient group representation and an unusable score distribution fail
closed before threshold replay.

CAL-EXP-0001 predates this boundary. Its immutable metadata is retained as a
regression fixture and must continue to produce `compatible: false`; its
historical artefacts, policy and split are not repaired retrospectively.

## Authoring review boundary

The validator mechanically enforces identities, digests, EvidenceUnit/source
lineage presence where required, expected outcomes, split overlap and execution
lineage. Semantic quality, representative coverage, author rationale and
governance quality remain human judgements. A versioned, digested review
artefact must attest those judgements for the exact population case digest;
the validator checks that binding and never claims to automate the review.
