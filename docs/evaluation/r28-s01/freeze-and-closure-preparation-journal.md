# R28-S01 freeze and closure-preparation journal

Date: 2026-09-04  
Frozen-boundary commit: `d96dc438793e684cd1ebe15cf69aa263361d36d8`
State: **COMPLETED — the exact frozen boundary passed final independent audit and
was committed and pushed before this mechanical closure record.**

## Safety and preflight

- Branch `main`; `HEAD == origin/main == d069337b9fc4b1a0da782ea5df04789eb738021e`.
- Tracked worktree clean at entry; unrelated untracked files preserved and not
  opened.
- Access began from `docs/evaluation/r28-s01/access-manifest.json`. Every content
  command named explicit files. No repository-wide content search occurred.
- The prohibited planner-experiment files, calibration, held-out, historical
  results, generated HTML and observations were not opened or searched.
- No provider, network or AWS call occurred. R28-S02 was not started.

## Candidate verification and freeze

The source directory contained exactly the five required ordinary files. All five
approved SHA-256 values matched, and `checksums.sha256` named each of the other four
exactly once. The committed v3 validator returned exactly:

```text
PASS 74 semantic cases, 148 utterances, coverage and provenance complete
```

The files were copied byte-for-byte to
`tests/evaluation/engineering-populations/dolved-care-v4/v1`. Five explicit `cmp`
checks passed. The canonical identity is
`dolved-care-v4-evaluation-population-v1`; the
`r28-frozen-population-digest-v1` digest is
`6254188d7fc7a698641750a81d436eac97eb425244704b64b1daac0c92803161`.

Provider-free reconstruction passed the five source hashes, internal checksums,
v3 schema and semantic validator, all 39 coverage memberships, restricted-view
identity, and the six-input contract aggregate at the recorded contract commit.

## Closure preparation

- The separate R28-S04 access boundary authorises only the exact frozen V4
  path/identity/digest and keeps R28-S02 populations distinct. The authoring
  access manifest remains byte-identical because it is part of the approved
  contract aggregate.
- Accepted ADR-0037 narrowly distinguishes ordinary mechanical phase closure from
  Phase 28 pilot readiness. David approved it on 2026-09-04.
- David approved the corrected, fully accounted R28-S04 routing and hard ceilings
  on 2026-09-04. Provider execution remains prohibited.
- Focused access and mutation tests cover tampering, substitution, missing files,
  every required freeze/access metadata field, identity drift, exact routing and
  ceiling arithmetic, alias rejection and authoring-mode separation. Together with
  the complete authoring-validator suite, 156 tests passed.

## Final audit and closure

- Final independent audit verdict:
  `R28_S01_FINAL_BOUNDARY_READY_TO_COMMIT`.
- The exact audited 16-file boundary was committed and pushed as
  `d96dc438793e684cd1ebe15cf69aa263361d36d8`.
- Frozen identity: `dolved-care-v4-evaluation-population-v1`; frozen digest:
  `6254188d7fc7a698641750a81d436eac97eb425244704b64b1daac0c92803161`.
- Population: 74 semantic cases, 148 utterances and exact scopes 62 primary / 6
  foreign tenant / 6 security test.
- ADR-0037 was Accepted on 2026-09-04. The R28-S04 controlling monetary hard
  ceiling is USD 30.
- The canonical frozen-population verifier and embedded v3 validation passed;
  the relevant provider-free R28 suite passed 156 tests.
- No provider, network or AWS execution occurred.

R28-S01 is complete. R28-S02 remains `not_started`; its dependency is now
satisfied, but it was not executed by this closure. Phase 28 and `R28-GATE`
remain open. This record does not assert `PILOT_READY`.

## Superseding pre-execution compatibility correction — 2026-09-04

R28-S04's provider-free preflight later found that V1's comparison-side labels
were incompatible with ADR-0022 V1. This does not reopen R28-S01 or rewrite its
historical freeze evidence. V1 remains byte-identical at identity
`dolved-care-v4-evaluation-population-v1`, digest
`6254188d7fc7a698641750a81d436eac97eb425244704b64b1daac0c92803161`.

The independently accepted checkpoint `COMPAT-V2-20260904-R7K3M8QX`, SHA-256
`40b16d20fab1734ac9cd04e65b66cb63f8423cf864bc8f17be4537c79771d4e1`,
was integrated as `dolved-care-v4-evaluation-population-v2`, digest
`adc9aa22646fc0f131ab7aa747dce91874655b95479cebc318653c3173e40f4c`.
Its verdict is `R28_V4_COMPARISON_COMPATIBILITY_V2_CANDIDATE_ACCEPTED`.
R28-S04 is now bound exclusively to V2. The correction changes 63 side labels
across 21 comparison cases and fully replaces `v4.case.corrected-b02-09`; 52
cases, all counts, scopes, coverage, routing and provider ceilings are unchanged.
No corpus was rematerialised and no provider or AWS action occurred.
