# Dolved Care V4 evaluation population v2

This immutable directory is the sole authorised V4 population for R28-S04:
`dolved-care-v4-evaluation-population-v2`, digest
`adc9aa22646fc0f131ab7aa747dce91874655b95479cebc318653c3173e40f4c`.
It supersedes V1 only for R28-S04 execution. V1 remains byte-identical historical
evidence and is not relabelled, replaced or rewritten.

The five author-produced files are byte-identical copies of run
`AUTHOR-V4-20260904-C9W2P6LX`. The accepted compatibility checkpoint is
`COMPAT-V2-20260904-R7K3M8QX`, SHA-256
`40b16d20fab1734ac9cd04e65b66cb63f8423cf864bc8f17be4537c79771d4e1`;
the independent verdict is
`R28_V4_COMPARISON_COMPATIBILITY_V2_CANDIDATE_ACCEPTED`, dated 2026-09-04.

V2 retains 74 semantic cases, two variants per case, 148 globally
normalization-distinct utterances, exact scopes 62 primary / 6 foreign tenant /
6 security test, and all 39 audited coverage slices. Relative to V1, exactly 63
comparison-side labels changed across 21 cases, case
`v4.case.corrected-b02-09` was replaced in full, and the other 52 cases are
unchanged. All 22 answerable comparisons now use current authority as PRIMARY
and selected historical authority as COMPARISON; none uses scheduled-future
evidence.

The `r28-frozen-population-digest-v1` input is one ASCII line per candidate file,
ordered by bytewise filename: lowercase SHA-256, two spaces, filename, then LF.
The frozen digest is the SHA-256 of those five concatenated lines. Metadata files
are excluded to avoid circular hashing.

Freezing and binding this population does not authorise provider execution. Verify
provider-free with:

```bash
apps/ai/.venv/bin/python scripts/evaluation/verify_r28_v4_population.py
```
