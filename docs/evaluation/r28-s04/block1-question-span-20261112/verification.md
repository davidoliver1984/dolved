# Provider-free verification

Baseline: `0f931126ec58bffa8f15d32540ad2395da57953c`; runtime clean, three unrelated
tracked edits preserved (ADR-0039, governed runner and its test). Baseline suites
completed before runtime changes. Raw JUnit and exact failure names are retained.

AI: baseline 1,309 tests, 1,267 passed / 38 failed / 4 skipped; changed 1,312 tests,
1,270 passed / same 38 failed / 4 skipped. Laravel: baseline 854 tests, 843 passed /
2 failed / 9 skipped; changed 857 tests, 846 passed / same 2 failed / 9 skipped.
**Zero new or resolved failing names.** Nonzero suite exits are not called green.

Focused Python request/output/transport: 68 passed. Focused PHP generation
foundation: 80 passed, 363 assertions. New parameterised tests cover qualifier
narrowing, example scopes and omitted subparts, confirming exact span transport
into the actual adapter input and entailment input, unchanged ownership rejection
and PHP rejection of invented chunk text. Existing borrowed-citation and tenant
tests remain active. These are provider-free wiring/invariant proofs, not model
correctness claims. The initial Python test used unsorted JSON against canonical
sorted JSON; its failure log is retained. No application correction was needed.

The first changed PHP suite exposed a synthetic mixed-scope fixture that copied
the scope need's question_span into the operational need while changing only the
aspect. The fixture now supplies the actual operational quote and asserts it in
both question and evidence_needs, retaining scope-fact isolation assertions. The
initial raw JUnit is retained as changed-api-before-fixture-span.xml.

17 governed harness tests pass without runner changes. Scoped Ruff and PHP Pint
pass. Targeted mypy reports three aspect_links attribute errors on the existing
OpenAIGenerationOutput annotation in openai_adapter/entailment; the accesses and
annotation also exist at baseline (git-show inspected), not repaired in this scope.
No claim of clean repository-wide static typing. Container health checked.

Commands: Docker Compose run with `/repo` and `/workspace` mounts, Git safe.directory
`/repo`; AI `/repo/apps/ai`, `/app/.venv/bin/python -m pytest tests -q --junitxml=...`;
Laravel default `/app`, `php -d memory_limit=512M vendor/bin/phpunit --log-junit ...`.
`verification/compare-suites.py` reproduces exact-name comparison. Harness uses
the existing standard-library unittest command. Shared JSON schema generated
from GenerationNeedsRequest.model_json_schema via writable `/repo` mount (the
initial `/contracts` write was correctly rejected as read-only). No providers used.
