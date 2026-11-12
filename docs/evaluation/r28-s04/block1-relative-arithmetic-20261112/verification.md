# Provider-free verification

Baseline commit `6a46ace605995fe4e7c024296a602cdae5fb05b5`; no runtime edits at
baseline. Initial AI command omitted the complete `/workspace` fixture mount:
109 failures are retained as `baseline-ai-incomplete-mount.*`, not attributed to
source. Corrected baseline mounts clean-HEAD application/tests exported with git
archive over `/repo`, full repository at `/workspace`, and safe.directory `/repo`.
Laravel baseline completed before edits. Unrelated changes were preserved.

Full AI: baseline 1,312 tests / 38 failures / 4 skipped; changed 1,327 tests /
same 38 failures / 4 skipped. Laravel: baseline 857 tests / 2 failures / 9 skipped;
changed 859 tests / same 2 failures / 9 skipped. **Zero new or resolved failure
names**, verified by `verification/compare-suites.py`; raw JUnit retained.
These nonzero suite exits are not described as green suites.

Focused Python calendar suite: 28 passed. Initial adapter tests omitted the
standard fake interpretation fixture; retained log, fixture corrected, no provider
request. Mirrored PHP focused suite passed 5 tests / 78 assertions before the
additional fromArray-consumption assertion, which passes in the full suite.
Nineteen governed-runner tests pass, including fixed six-case identities and the
existing injection workspace for b01-06. Python scoped Ruff and PHP Pint pass.
Mypy 2.3.0 crashes internally, both with and without incremental cache; logs
retained, no clean static-type claim.

One initial changed-suite regression altered malformed JSON's diagnostic after
pre-parsing. The original schema parser is now retained on malformed input;
`changed-ai-before-json-diagnostic.*` records that regression and the final full
suite proves its exact failure set removed. No validator was relaxed.

Tests cover real fake-provider adaptation for authority and fact references, this
year/last year/this quarter, last month inside a sentence, leap/January/quarter
and GMT/BST boundaries; grounded prior-turn references; ambiguous follow-up and
event-date non-inference; conflicting/repeated phrases and forged quotes. The
strict direct validator still rejects incorrect arithmetic. The adaptation owns
only arithmetic; intent/kind, quotes, retrieval, checker and retries are unchanged.

Commands: Docker Compose run; AI `/app/.venv/bin/python -m pytest tests -q
--junitxml=...`, Laravel `php -d memory_limit=512M vendor/bin/phpunit --log-junit ...`.
The existing host stdlib unittest harness command ran 19 tests. No providers in
verification. Six-input questions and independent expectations frozen before live.
