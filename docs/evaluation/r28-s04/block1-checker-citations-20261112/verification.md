# Provider-free verification — checker citations v2

Clean runtime baseline `ac280e2c61eda0ce364c6e6cececaae760d11643`; unrelated
ADR, living plan and runner edits preserved. Both full suites ran before runtime
changes, then again after changes, with raw JUnit retained under `verification/`.

AI baseline 1,327 tests / 38 failures / 4 skipped; changed 1,347 / same38 / 4 skipped.
Laravel baseline 859 tests / 2 failures / 9 skipped; changed860 / same2 / 9 skipped.
**Zero new or resolved failing names**, verified with `compare-suites.py` and
`suite-comparison.json`. Existing failures are not called a green suite.

Focused checker tests passed32 before the final lossless-merge test; all33 pass
in the full suite. Mirrored PHP6 tests /137 assertions pass. One PHPUnit result-cache
write notice occurred in the focused Docker run; raw log retained, tests passed.
Twenty governed-runner tests pass. Scoped Ruff and PHP Pint pass. Mypy 2.3.0
crashed internally; `verification/static.log` is retained, no clean typecheck claim.

Coverage: citation-valid and unverifiable flags for each code; first/second-attempt
unverifiable flags retain the plan without an extra attempt; verified rejection
still exhausts at two; no quotation text in planner correction feedback; forged
quotes/IDs/fields, quotation in another turn, app-grounded month/year/quarter values,
all four frozen omission probes (including whole-span copies), lossless merging,
lossy merge/whole-span omission, and unchanged transport/malformed failure paths.
PHP retains bounded quote/plan/verification diagnostics without raw provider prose.
Tests explicitly show that a citation can verify while a semantic verdict is wrong.
No fake-adapter result is claimed as proof that the real model detects an omission.

Commands: Docker Compose AI with full `/repo` and `/workspace` mounts and Git
safe.directory `/repo`; `/app/.venv/bin/python -m pytest tests -q --junitxml=...`.
Laravel `php -d memory_limit=512M vendor/bin/phpunit --log-junit ...`.
Existing host stdlib governed unittest suite. No provider calls in these checks.
Initial lint-only findings were corrected before tests; the original log is retained.

The live six-input manifest/expectations were written before outputs. Every
unverifiable flag will be manually reviewed. A citation-valid wrong flag that
causes retry fails the checkpoint and stops further dispatch, even if the final
answer is correct. No extra model call, no checker tuning to these inputs.
