# Retrieval evaluation

The evaluation harness is owned by this repository. ADR-0019 defines its
architecture and ADR-0020 defines the evidence and model-assisted semantics.

The checked-in corpus and policy are under `tests/evaluation`. They contain only
synthetic content. Run an offline experiment with:

```bash
make evaluation-run
```

This writes a machine-readable result to
`/tmp/rag-platform-evaluation/result.json`. Running
an experiment does not promote it. Promotion is a separate, deliberate command,
and a manual accepted/rejected/time-bounded-waiver record remains required.
The command records the current Git `HEAD`; do not promote its result while the
evaluation implementation, corpus, policy or observations have uncommitted
changes.

The initial accepted baseline must be generated from a committed implementation
so its `repository_commit` lineage is truthful. It is therefore produced after
the implementation commit and recorded separately before the stage tag is
created; a pre-commit working-tree run is verification evidence, not an accepted
baseline.

The current accepted baseline is corpus V2 under `baselines/v2`. It supersedes
the immutable historical V1 baseline because V1's
`temporal.predecessor-resurrection` expectation contradicted ADR-0017. V1 is
retained unchanged for auditability and must not be used as the current
comparison baseline.

The `promote`, `compare`, and `gate` subcommands in
`scripts/evaluation/run.py` keep experiment execution, baseline promotion,
comparison, and the human release decision as separate records. A waiver must
include an expiry timestamp. See `python scripts/evaluation/run.py --help` for
the exact arguments; never promote a result merely because it completed.

Ragas context relevance is advisory. Normal tests use fakes and require no
credentials. The live integration test runs only when explicitly enabled with
`RUN_LIVE_RAGAS_TESTS=1` and evaluator credentials.

## Persisted experiment reports

Durable experiment artefacts live under `docs/evaluation/runs/`. Each run keeps
its raw result, exact configuration and optional saved comparison separate from
generated Markdown/HTML projections and human notes. See
`docs/evaluation/runs/README.md` for the contract and commands.

Generate or regenerate a report without provider calls using:

```bash
make evaluation-report RUN=EXP-0001-short-description
```

The generated `docs/evaluation/EXPERIMENTS.md` index retains every run directory.
Report generation never accepts or promotes an experiment and never changes the
retrieval configuration or policy.
