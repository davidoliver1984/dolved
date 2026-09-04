# Checkpoint-19 freeze validation evidence

- Archive SHA-256: `6fa6602935efe8379cc2a7de4ba85af17aa8d8827082ae6de8df959f6e19a06e`
- Raw archive: 752 regular members, zero directories, zero problems.
- Checksum inventory: exactly 751 unique safe relative paths, exact member-set
  equality and 751/751 matching hashes. Both `shasum` under `LC_ALL=C LANG=C`
  and an independent streaming Python standard-library verifier passed.
- Corpus-local runtime: `/tmp/r28-validation.nfifsw/venv`, CPython 3.13.7,
  Darwin 24.2.0 arm64, exact 20-package version set from the archived lock.
- Acquisition: 20 compatible wheels from `https://pypi.org/simple`, served by
  the normal `files.pythonhosted.org` file host. No sdist or source build.
  Distribution hashes are recorded in `/tmp/r28-wheelhouse-evidence.json` for
  this review; the historical checkpoint lock did not contain distribution
  hashes and has not been rewritten.
- Primary validator, twice with identical output: 300 documents, 0 problems,
  0 warnings.
- Version-fidelity validator, twice with identical output: 115 comparisons,
  0 problems.
- Freeze validator, twice with identical output: 0 problems, 751 inventory
  entries and 751 governed ordinary files.
- Aggregate validator, twice with identical output: 318 artefacts,
  `1464082d5096e7123f475d2b32b819ece7d1c7e7b8c1462ec2bda77da2e1b776`.
- Application pipeline: exact image
  `sha256:c5ab90de8d23e73d5b6cf78b4987189947957513c5531be8d8db5895e2509202`,
  network disabled, read-only corpus, 300/300 processed, zero physical errors,
  and exact chunk/warning/profile match.
- Post-validation integrity: zero governed mismatches and zero extra files.

Validator execution after wheel acquisition used the ordinary restricted
execution sandbox, with no network access or credentials. Exact package-version,
interpreter and platform equivalence is established. Byte identity with the
historical installed environment is not claimed: installation-generated RECORD
metadata differs, and the checkpoint itself states those fingerprints are local
environment evidence rather than canonical distribution hashes.

## R28 V4 evaluation-population freeze — 2026-09-04

- Source run: `AUTHOR-V4-20260903-H4K9T2MC`; population:
  `dolved-v4-independent-corrected-74case-v3-r2`.
- Source inventory: exactly five required files; all five approved SHA-256 values
  passed; the internal inventory covered the other four exactly once.
- Source validator exact result: `PASS 74 semantic cases, 148 utterances, coverage
  and provenance complete`.
- Canonical repository identity: `dolved-care-v4-evaluation-population-v1` at
  `tests/evaluation/engineering-populations/dolved-care-v4/v1`.
- Frozen digest: `6254188d7fc7a698641750a81d436eac97eb425244704b64b1daac0c92803161`
  under `r28-frozen-population-digest-v1`.
- Five explicit source-to-repository `cmp` comparisons passed.
- Canonical verifier passed: five byte identities, internal checksums, v3 schema
  and validator, 39 coverage slices/memberships, restricted-view archive identity,
  and contract aggregate at commit `d069337b9fc4b1a0da782ea5df04789eb738021e`.
- Focused boundary/mutation suite: 39 passed; the complete explicit R28 suite was
  131 passed. The first pytest invocation was not
  collected because an unrelated auto-loaded `langsmith` plugin is incompatible
  with the local Python/Pydantic combination; rerunning with
  `PYTEST_DISABLE_PLUGIN_AUTOLOAD=1` passed all tests.
- No provider, network or AWS action occurred. No calibration, held-out, previous
  result, observation, planner-experiment report/config or generated HTML was
  accessed. R28-S02 was not started.

## Final bounded routing and freeze-verifier correction — 2026-09-04

- David approved ADR-0037 and the corrected R28-S04 routing and ceilings.
- All 148 utterances are now assigned to their required route: 106 retrieval,
  at most 96 reranking, 86 generation/judging and 62 deterministic termination.
- The hard ceilings are 314 base provider requests, 628 maximum physical attempts,
  7,416,320 input tokens, 1,056,768 output tokens, USD 30, concurrency 1 and 180
  minutes.
- The canonical freeze verifier enforces immutable/correction/replacement metadata,
  canonical path, prohibited provider execution, 74 cases, 148 utterances, exact
  62/6/6 scopes, access correction rule, identity, digest, routing and ceilings.
- The relevant provider-free R28 suite passed 156 tests, including mutation and
  routing/count arithmetic coverage. The canonical verifier and its embedded v3
  population validation passed without changing population content.
- No provider, network or AWS action occurred. R28-S02 was not started.
