# R28-S02 pre-existing population binding

Status: **Population identities bound; clean-lineage correction implemented and
approved. Execution remains blocked until that correction is committed and
R28-S01 is fully closed.**

R28-S02 has two distinct, narrowly scoped population components. They are not
one population and their results must never be blended.

## Retrieval component

- Path: `tests/evaluation/corpus/v2/corpus.json`
- Identity: `MakeTime retrieval foundation corpus`, version `2`
- Raw-file SHA-256: `3578e6877ff3e33a313774ea83c6d3edbe4749b491ac148d038a3a8475cb82f3`
- Canonical content digest: `0e78f8e57a3d9c358ae08bdf7e97ded151cc4111cf934f48342427a2a187c1af`
- Population: 23 semantic cases and 25 variants
- Split: spent engineering/baseline evidence; neither calibration nor held-out
- Purpose: narrow live hybrid retrieval/provider measurement over authored
  plans and queries. It does not invoke the current live planner and is not a
  complete end-to-end current-product evaluation.
- Historical comparison: `retrieval-v2-offline-baseline` at corrective commit
  `735654291e2f5e085f83e98f1229768c0237edaf`.

The Make target previously supplied `$(git rev-parse HEAD)-dirty`
unconditionally. That was an ordinary lineage defect: `run.py live-hybrid`
accepted the supplied string and did not itself require an exact clean SHA.
The current uncommitted R28-S01 correction rejects tracked changes, passes the
exact 40-character `HEAD`, and independently verifies both conditions inside
the runner. Its five focused provider-free tests pass. This changes lineage
safety only, not evaluation semantics. David approved it on 2026-09-03; it must
still be committed before execution and does not permit R28-S02 to run early.

## Generation security component

- Path: `docs/evaluation/generation/populations/prompt-injection-v1.json`
- Identity: `prompt-injection-v1`
- Raw-file SHA-256: `0906589e204743282c93b65bcc7dae7d836b41d33dce523bc86b7db1cd2ce341`
- Canonical content digest: `753e76c7dd91110c4e5277ed342fbcb83d352f7cf09e06634be7b1ccdcdda119`
- Population: 3 semantic cases; no phrasing-variant layer
- Split: engineering security regression; neither calibration nor held-out
- Purpose: prompt-injection live boundary only
- Binding policy: `live-generation-security-v1`, raw policy SHA-256
  `e0b37cd4edd3af4295f64163bf9bfe77c337b6868da84ece033e1ab134050743`
- Existing maximums: 3 cases, 6 total attempts, 18,432 output tokens and
  600 seconds.

This component cannot honestly measure general grounded-generation quality.
General V4 generation evidence belongs to R28-S04 after the independently
authored V4 population is accepted. Creating another general-generation
population for R28-S02 would change its approved purpose and requires review;
it is not improvised here.
