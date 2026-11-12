# Block 1 provider-free verification — 2026-11-12

Baseline: clean committed application source at `d27900160bc2dba0131ea0bc9961f6f90a5b0ef8`; unrelated ADR/runner edits and untracked fixtures preserved. Changed runtime aggregate: `716430fb0be16f0ee1607fb2a2abab30b71b20c34141f1ba6c9be1ceb4da6de6`.

| Suite | Baseline | Changed | Failure-name difference |
| --- | --- | --- | --- |
| AI | 1,222 passed / 38 failed / 4 skipped | 1,233 passed / 38 failed / 4 skipped | none added or resolved |
| Laravel | 831 passed / 2 failed / 9 skipped | 837 passed / 2 failed / 9 skipped | none added or resolved |
| Governed runner | — | 14 passed | fixed question-only seven-input stage and existing safety checks |

Raw final JUnit: `baseline-ai.xml`, `baseline-api.xml`, `changed-ai.xml`, `changed-api.xml`; exact failing names and set differences: `suite-comparison.json`. Reproduce comparison with `python3 compare-suites.py`. Both full-suite commands exit nonzero because of retained failures; this is zero-new-failure attribution, not an all-green claim. Changed Python Ruff and explicit-file PHP Pint checks pass; `git diff --check` passes.

Provider-free coverage includes oversubscribed three ordinary needs and a comparison pair plus two multi-passage needs, unchanged candidate selection, per-need bounded cut counts/export, budget-limited gap rendering, service duration/quantity grounding with no fact-date filter, before/after leap/month/year/day boundaries, follow-up turn grounding, real timeline selection, rejected reversed/during interpretations, strict catalogue/tenant/quote gates, invalid internal output using at most two completed attempts, and provider failures retaining their original path. Full AI suite includes the existing operation-boundary fixture. Scripted planner tests prove contract/adapter behaviour, not model judgement.

Preliminary files are retained and explicitly not substituted for final attribution: `baseline-initial-mount-*` lacked the legacy `/workspace` mount; `changed-initial-*` predates the final changes; `changed-before-retry-expectations-*` exposed two old one-attempt expectations, updated for the approved two-attempt semantics. `changed-transient-git-*` and `changed-unsafe-directory-*` include a frozen-population test's Git-read failure. Direct reproduction identified `fatal: detected dubious ownership in repository at '/repo'`; final commands explicitly set per-container Git `safe.directory=/repo` (not a repository/application change). The final test set again matches the original baseline exactly. The final Python change after the preliminary runs spells `re.IGNORECASE` instead of its equivalent `re.I` alias for lint.

Allocation and FINAL_K remain unchanged. Compare cut counts count complete same-family passage pairs, not distinct source facts. Disclosure uses existing persisted unsupported-aspect data and links; it does not pool evidence, infer source absence, or solve arbitrary unsupported assertions elsewhere in answer parts (Block 3). No live input has been used to tune this increment. Relative dates and the input-4 induction finding remain excluded.
