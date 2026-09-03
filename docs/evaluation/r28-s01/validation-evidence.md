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
