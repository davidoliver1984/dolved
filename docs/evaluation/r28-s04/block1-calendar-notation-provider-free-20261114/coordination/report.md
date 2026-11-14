# Coordinated calendar-notation correction — 14 November 2026

**Ready for the authorised hold-branch commit. Block 1 remains HOLD.**

Reviewer inspection found a real defect in the initial uncommitted correction:
Python emitted `structured-chat-needs-v11`, while Laravel's new-execution reader
still pinned v10. The earlier provider-free verification did not establish that
the two services could communicate. No live run used that incomplete change.
This record supersedes the initial completion claim, not the retained test logs.

## Coordinated fix

- Laravel `EvidenceNeedsPlan::fromArray` now requires v11 for new extractive v4
  plans, with unchanged prompt v26, schema v4 and fingerprint validation.
- The four PHP test files representing the current producer now emit v11. The
  new negative test deliberately supplies v10 with a recomputed valid fingerprint
  and requires an identity rejection.
- The v3/v9 compatibility branch is unchanged, matching the previous v9→v10
  transition pattern. Stored v10 receipts and evidence are not rewritten or
  re-executed. The only production caller of `fromArray` is the live retrieval
  client; this is not a historical rendering migration.
- The contract README documents v11, notation-only conversion and coordinated
  deployment. No shared schema, prompt, retry or temporal-meaning rule changed.

## Cross-service regression

`test_current_python_adapter_matches_shared_php_consumption_fixture` invokes the
real `StructuredChatNeedsPlanner` using controlled HTTP transport, interpretation
receipt and clock. Its complete plan, actual lineage/fingerprint and usage must
equal `contracts/http/retrieval-needs/v4/python-produced-plan.json` on every normal
test run. Explicit regeneration is separate; normal tests cannot update it.

`EvidenceNeedsPlanTest::test_real_python_adapter_artifact_passes_consumer_identity_validation`
consumes that same artifact through the real PHP `fromArray` method. It checks the
normalised month, original quote and unchanged current authority, then verifies
that an old v10 identity cannot authorise new execution.

This pairing detects future producer drift in the Python suite and a stale
consumer pin in the PHP suite. The artifact is produced by the adapter, not a
manually assigned PHP lineage. No live provider or semantic-quality claim follows.

The first explicit generation attempt correctly encountered the read-only
`/contracts` mount. Regeneration then used the normal writable repository mount;
no runtime mount permissions or service configuration were changed.

## Verification and freeze

- Focused AI: **201 passed**.
- Focused PHP: **203 tests, 1,735 assertions, 3 skips, no failures**.
- Full AI: **1,426 tests: 1,384 passed, 38 failed, 4 skipped**.
- Full PHP: **928 tests, 5,635 assertions, 2 failures, 9 skips**.
- Exact retained 38 AI / 2 Laravel failure identities; zero new or resolved
  failures. The previously intermittent inode-substitution failure reproduced.
- Ruff lint/format, scoped Mypy with container-local cache, Pint and
  `git diff --check` passed. Logs, full JUnit and comparison are alongside this file.
- Raw logs are losslessly gzip-compressed (`.log.gz`) so their original terminal
  whitespace is preserved without adding whitespace errors to the source diff.
  JUnit files are likewise retained as `.xml.gz`. Parent `original-log-bytes.sha256`
  and `original-junit-bytes.sha256` record the uncompressed hashes. The comparison
  helper reads compressed JUnit directly; no test output was edited.
- Source and test hashes are in `code-freeze.json`.
- Runtime aggregate: `cbff39c23c64c7226ca3592248816884f2af8a5dcf7d5672f125d524078ba238`.

The original replay limitations still apply: only retained temporal fields and
explicit synthetic-envelope grounding were replayed. G3/2's unsupported `after`
relation remains rejected. C2 no-answer scores are unchanged.

All tests used one-off `--no-deps` containers in `dolved-r28-s03`, the established
base Compose test mounts, controlled providers and the existing isolated test
settings documented in the parent report. No live deployment, migration, provider
call or authorisation occurred. Python and PHP must be deployed together before
a future newly authorised checkpoint.

David authorised committing and pushing only this correction, version coordination,
tests, documentation and evidence to `codex/block1-extractive-hold-20261112`.
Exclude the pre-existing ADR-0039 and two governed-runner edits. Main stays at
`348f09e053c8ad73676921c5c616cced812111d7`. Journey is local only.
The next Codex task can start from the resulting hold-branch commit and this freeze;
it must retain the existing Block 1 HOLD and live-run approval boundary.
