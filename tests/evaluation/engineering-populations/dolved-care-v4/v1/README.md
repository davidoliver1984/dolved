# Dolved Care V4 evaluation population v1

This immutable directory is the sole authorised V4 population for R28-S04:
`dolved-care-v4-evaluation-population-v1`, digest
`6254188d7fc7a698641750a81d436eac97eb425244704b64b1daac0c92803161`.
It is distinct from every historical Benchmark V3 population and from both
legacy R28-S02 population components.

The five author-produced files are byte-identical copies of run
`AUTHOR-V4-20260903-H4K9T2MC`; repository freeze metadata is kept separately in
`freeze-manifest.json`. The population contains 74 semantic cases, two variants
per case, 148 globally normalization-distinct utterances, and exact scopes
62 primary / 6 foreign tenant / 6 security test. Its coverage matrix retains all
39 audited slice values and memberships.

The `r28-frozen-population-digest-v1` input is one ASCII line per candidate file,
ordered by bytewise filename: lowercase SHA-256, two spaces, filename, then LF.
The frozen digest is the SHA-256 of those five concatenated lines. Metadata files
are excluded so their documentation can be checked without circular hashing.

This directory's immutability is established by its reviewed versioned identity,
checksums and Git commit history; ordinary filesystem writability before commit is
not itself an integrity defect. Any correction requires a new population identity
and version, and no later run may silently replace it. Freezing does not authorise
provider execution. Verify provider-free with:

```bash
apps/ai/.venv/bin/python scripts/evaluation/verify_r28_v4_population.py
```
