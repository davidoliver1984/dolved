# R28-S01 freeze and closure-preparation journal

Date: 2026-09-04  
Repository commit: `d069337b9fc4b1a0da782ea5df04789eb738021e`  
State: **IN PROGRESS — ADR-0037 and the corrected R28-S04 routing and ceilings
were approved by David on 2026-09-04; final narrow audit remains pending.**

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

## Remaining gate

The bounded correction requires one final narrow independent audit before commit
and R28-S01 closure. This journal does not mark R28-S01 complete or assert
`PILOT_READY`.
