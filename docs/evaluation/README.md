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

The `promote`, `compare`, and `gate` subcommands in
`scripts/evaluation/run.py` keep experiment execution, baseline promotion,
comparison, and the human release decision as separate records. A waiver must
include an expiry timestamp. See `python scripts/evaluation/run.py --help` for
the exact arguments; never promote a result merely because it completed.

Ragas context relevance is advisory. Normal tests use fakes and require no
credentials. The live integration test runs only when explicitly enabled with
`RUN_LIVE_RAGAS_TESTS=1` and evaluator credentials.
