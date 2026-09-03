# Independent authoring output contract

Contract identity: `dolved-v4-independent-authoring-output-v2`

Version 1 required 72 semantic cases, 144 utterances and exact scopes 60/6/6.
It was not used for a final accepted population. Five independently audited
batches produced 60 accepted cases, after which the final feasibility audit
proved that two more primary cases were the minimum necessary extension. This
v2 contract supersedes v1 for final serialization. The existing 60 accepted
semantic cases remain valid because no schema shape, closed vocabulary,
coverage minimum or evidence rule was weakened. The final population must be
validated entirely under v2; v1 and v2 outputs must never be mixed or presented
as one identity.

The contract is the ordered identity of:

1. `docs/evaluation/r28-s01/access-manifest.json`
2. `contracts/evaluation/v4/independent-authoring-output.schema.json`
3. `docs/evaluation/r28-s01/authoring-coverage-contract.json`
4. `scripts/evaluation/r28_authoring_access.py`
5. `scripts/evaluation/r28_access_guard.py`
6. `scripts/evaluation/validate_r28_authoring_output.py`

Its v2 aggregate SHA-256 is
`57ebb52ae6814f4912583c90ec399c60a65e82dc872cfdb21afe10f57871df68`,
computed by hashing, in the order above, each UTF-8 repository-relative path,
one NUL byte, its file bytes and one NUL byte.

The author writes only to:

`/tmp/dolved-r28-v4-authoring/AUTHOR-V4-YYYYMMDD-XXXXXXXX/`

where the final eight characters are uppercase ASCII letters or digits. The
date is one valid UTC Gregorian calendar date encoded as exactly eight decimal
digits in `YYYYMMDD` order; the suffix is exactly eight uppercase ASCII letters
or decimal digits. The run
directory must be one genuine, non-symlink direct child of the canonical
platform resolution of `/tmp/dolved-r28-v4-authoring/` (normally
`/private/tmp/dolved-r28-v4-authoring/` on macOS). Its basename must equal the
declared `authoring_run_id`. The
directory must contain exactly `population.json`, `coverage-matrix.json`,
`author-declaration.json`, `authoring-report.md` and `checksums.sha256`.
`checksums.sha256` covers the other four files exactly once and never itself.

Exactly 74 semantic cases means 74 unique stable case IDs. Exactly 148
utterances means every semantic case has exactly two independently written,
textually distinct variants (`v1` and `v2`); variants never count as additional
semantic cases or coverage. All 148 utterance texts must also be globally
distinct. Normalize solely for this comparison by applying Unicode NFC, trimming
leading and trailing whitespace, collapsing every internal Unicode whitespace
run to one ASCII space, then applying Unicode case-folding. Do not semantically
deduplicate or rewrite author text.

The exact exclusive scopes are 62 primary, 6 foreign-tenant and 6 security-test
cases. The final two primary cases are a source-backed, non-weakening extension
required to complete inherited-applicability coverage: one is governed by the
Midlands-wide Key Safe Procedure v2 at Oakfield Lodge, and one by the
North-West-only Key Holder Procedure v1 at Riverside House or Moorland View.
This contract does not prescribe authored wording or expected answers.

Historical superseded v1 aggregate:
`c7e4f6bce57be48e69bb6f3c57e6cb34f5130859efd782e9ad5db7503a163e3c`.

The JSON Schema defines the machine shape and closed platform outcome enums.
The validator executes that exact local Draft 2020-12 schema with format
checking under the governed `apps/ai/.venv` runtime before applying the stronger
cross-field rules. `authored_at_utc` must be a valid date-time with an explicit
UTC offset, spelled with `Z` or `+00:00`; non-UTC offsets are rejected. The
strict validator additionally enforces cross-file identity, counts,
context/outcome/evidence invariants, the closed slice vocabulary and coverage
minima, restricted-view membership and hashes, safe relative paths, forbidden
system-output fields, author declaration, exact filenames and checksums.
Every required entry must be one single-linked regular file. Validation opens
entries without following links, relative to an already-opened run directory,
and rechecks file identity before accepting their bytes.

`accessed_input_paths` records both (a) every external neutral input actually
opened and (b) every restricted-view archive member actually opened. External
paths use their exact repository-relative spelling and are confined to the
access manifest, restricted archive, schema, coverage contract, this written
contract, normative access classifier, access guard, output validator and
handoff file. All except the handoff file are mandatory declarations; the
handoff is declared when supplied as a file. Archive members use the exact form
`tests/evaluation/authoring-views/dolved-care-v4/v1/question-author-view.tar.gz!/dolved-care-v4-question-author-view-v1/<member>`.
The archive manifest is mandatory, and every other declared member must exist
in the signed archive. Aliases, uncertain paths and repository-wide paths fail.

## Independent audit seam

A different fresh audit task will receive only the frozen restricted-view
identity, this exact contract identity, the candidate population and checksum
inventory, its coverage matrix and author declaration. Structural validation
is necessary but is not semantic/evidence audit or acceptance. The audit task
will judge evidence fidelity and question quality without seeing Dolved output.
