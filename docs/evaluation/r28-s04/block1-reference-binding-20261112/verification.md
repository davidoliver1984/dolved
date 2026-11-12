# Block 1 reference identity — provider-free evidence, 2026-11-12

Baseline `ebd79f86dc4ebd850eb26ce9279b360f6f7ee68b`, unrelated ADR/runner edits
preserved. Primary workspace `5323cd6b-8b7a-40f9-98b0-9413c75f643e` was read from
the isolated database: name **Alderbridge Care Group**. `workspaces` has no separate
organisation-name/alias field; the only alias table is organisational_location_aliases,
which cannot be repurposed as tenant identities. Existing legal/group-suffix-derived
short form retained. No hardcoded organisation name added. Foreign/injection workspace
display names are E2E labels, not a source of primary organisation identity.

Catalogue read: `E-Learning Platform Guidance` and `Record of Processing Activities
Form`. Documents have source_filename, not a separate title field. The latter is
not an exact match for “ROPA for employee records”; this repair does not invent an
acronym alias. A diagnostic read initially requested nonexistent original_filename
and failed read-only; schema inspection establishes source_filename. No DB writes.

The resolver previously skipped bare organisation identities, but null-location
disclosure lost that distinction. The repair carries application-owned reference
classifications to disclosure, and records them in the production eligibility trace.
Exact sites/aliases win; whole title/organisation matches cannot swallow another
tenant, a title prefix or an unregistered proper-noun place. Retrieval selection,
authority, checker/prompt and retry ceiling stay unchanged. No model call added.

Focused tests: 7 passed / 60 assertions, including shape variants, foreign identity
and title, site/title collision, alias, org+site, unresolved place, and the actual
RetrieveWorkspaceEvidence→disclosure path. First test run had a missing required
RetrievalPlan constructor argument in the new test; corrected before final tests,
original log retained. PHPUnit result-cache I/O notices are retained, not test failures.

Full suites, raw XML and logs under `verification/`:
- AI 1,347 before/after, same38 failing names, 4 skips.
- Laravel 860→863, same2 failing names, 9 skips; 3 new passing tests.
- Exact new/resolved failure-name sets empty (`suite-comparison.json`).
- 21 governed harness tests pass; scoped Pint passes. No entire-green-suite claim.

Commands: Docker Compose AI pytest with /repo and read-only /workspace mounts,
Git safe.directory /repo, `--junitxml`; Laravel `php -d memory_limit=512M
vendor/bin/phpunit --log-junit`; existing host stdlib governed-runner unittest.
No providers used by verification. Six question-only inputs and separate expectations
frozen before outputs; the runner stage is hash-bound and archived, not silently
absorbed with its pre-existing edits. Live result is pending in this note.

Completion addendum: the initial authorization preflight caught a missing stage
allowlist entry before consuming authorization or making any provider call. The
bounded harness correction adds a positive/negative authorization test; final
suite is **22 passed**, retained in `verification/harness-final.log`. Runtime was
unchanged. The rejected unconsumed authorization is preserved. [Final live report](../r06-governed-evidence/R28-R07-BLOCK1-REFERENCE-BINDING-20261112-01/report.md)
supersedes the pending status above; no original test log or raw run was replaced.
