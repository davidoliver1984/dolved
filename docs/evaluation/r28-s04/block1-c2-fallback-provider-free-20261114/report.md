# C2 bounded fallback correction — provider-free report

2026-11-14; parent `c0006be7aece93de733dc72f6a6df24274e98c08`.
Hold branch only. Block 1 HOLD, main unchanged, no provider calls or spend, no
sealed confirmation access. This report supersedes the unresolved-discovery
disposition in the earlier C2 report; its frozen raw evidence remains unchanged.

Implementation: `84f22f862e5001be998e6a8fe027f56a17db6941`.
Runtime aggregate: `7f20f123cb9e4af9567e0ea0240ebf715d54755ae2611dc66ecd6719756773af`.

## Correction and retained boundaries

For inventoried-date, topic-only retrieve needs, ambiguity, genuine no_match,
empty discovery and rejected selections now fall through to the original unbound
`RetrievalPlan`. Neither the selected/rejected family, discovery candidates nor
the current-discovery temporal mode enters fallback. The ordinary executor still
performs its original eligibility, query, search, ranking, threshold and final
selection checks. Historical-request fallback is tested against the original
historical eligibility scope, not the discovery current scope.

Each fallback records original/effective reviewer decision and rejection code,
plus `temporal_guard: {status: not_run, reason: discovery_unresolved |
discovery_unavailable, fallback: original_unbound}`. Failed discovery is not
recorded as a genuine reviewer no_match: effective decision is `unavailable`,
original decision unknown/null. Consumer-rejected selections retain original
`selected`, effective `no_match` and a closed identity/quotation rejection code.
The diagnostic exporter retains the guard stage; retrieval snapshots retain the
lineage, including when the ordinary fallback later fails.

Typed optional timeout/connection/service-unavailability failures permit fallback.
Authentication/authorisation, final-eligibility/integrity failures, quota or budget
failure observations, invalid output and unknown failures do not. No exception
text is classified and no Throwable catch was added. A typed outer HTTP 401 cannot
be overridden by an inner observation claiming a timeout/503. A completed reviewer
selection rejected by the existing consumer checks has its own exception subtype;
the checks and required-comparison execution behaviour are unchanged.

The AI endpoint previously flattened both invalid output and provider errors to
the same 503. It now adds a closed `X-Comparison-Review-Failure` header derived
from the actual exception/cause type and HTTP status. Its existing status/body
remain identical. C2 alone consumes the header. Unknown/absent metadata on a
reviewer 503 fails closed, including with an older AI deployment. Required
comparison calls ignore the header and retain their existing handling/retries.
No prompt, model, token ceiling, timeout configuration, ranking or threshold changed.

The existing production run start + `conversation.run_timeout_seconds` supplies
the fallback deadline. An expired deadline blocks fallback before another search;
a production-orchestration test advances the clock during review and verifies the
saved `deadline_exhausted` receipt. Direct calls may pass the same explicit deadline.
No token/money ledger is invented: this application path has no shared aggregate
monetary/token budget object. Existing governed reservations remain external.
Request/token/money/wall-budget failure payloads are not outage allowlisted (the
current failure schema rejects those unrecognised category values); they retain
hard-failure handling, as do quota responses. Nothing resets existing budgets or
adds retries. This is not a claim that C2 implements a new aggregate budget meter.

Already-completed discovery usage is retained. Typed failed attempts retain their
existing accumulated request/usage receipts. Controlled timeout fixtures observe
two native attempts and retain both (14 synthetic input tokens), then one ordinary
fallback search. They do not run discovery again. Unavailable provider token/cost
figures remain unavailable, not zero. All controlled fixtures cost zero provider USD.

Successful family selection and C1 temporal alternatives remain unchanged. The
four logical lanes/five slots, one additional distinct-version search, authority,
applicability, ownership, citation checks and discovery-admission accounting remain.
Fallback is **not successful C2 protection**, regardless of whether it answers.

## Provider-free verification

| Verification | Result |
|---|---|
| Focused PHP C1/C2/topic-comparison/diagnostics | 70 tests pass; final JUnit retained |
| Python reviewer/attribution tests | 27 pass |
| Full Laravel final | 927 tests, 5,629 assertions, 2 failures, 9 skipped |
| Full AI initial | 1,390 tests; 38 failed, 1,348 passed, 4 skipped |
| Full AI final | 1,390 tests; 37 failed, 1,349 passed, 4 skipped |
| New failures against retained baseline | Zero in either suite |
| Genuinely resolved failures | Zero claimed |
| Cross-service successful-path request/persistence replay | 5/5 valid |
| Python changed-file Ruff and routes MyPy | Pass |

The final AI identity difference is
`tests.test_r28_authoring_output_validator::test_rejects_check_open_inode_substitution`:
it failed in this task's first full run and passed in the final run, with no change
to that test or its implementation. Both raw JUnit files are retained. The machine
comparison calls it `resolved` in that final sample; this report classifies it as
non-reproduction, not a genuine repair. The 38/2 baseline remains the reference.
All other failure identities match; no unrelated assertions were weakened.

Varied development fixtures cover:

- Ordinary ambiguous/no-match/empty and Python/PHP rejected identity/quotation
  selections. Current fallback includes the original broader current family set,
  not the rejected family's narrowed set; no temporal targets are attached.
- Compiled query and eligibility-side/document-ID equality to the original
  pre-C2 `retrievalPlan()` + `EligibilityResolver` path, including historical mode.
- Review timeout, connection failure, attributable service outage, discovery-search
  timeout, and typed-header timeout. Already-made attempts remain accounted for.
- HTTP 401/403, conflicting inner-timeout/outer-401, integrity, unknown, malformed
  budget observations and local execution timeouts (not provider outages),
  provider-quota metadata, invalid-output metadata,
  unclassified legacy 503, and expired deadlines: no fallback search.
- Failure in the subsequent ordinary search remains a retrieval failure.
- Saved/reloaded retrieval snapshots retain guard/original-decision/rejection
  metadata; the real diagnostic export projection retains the guard.
- All previous successful C2 fixtures, original topic-only input16 orchestration,
  named-family C1, threshold/capacity and persistence tests remain exercised.

Raw per-scenario records contain original query/scope, actual requests, discovery
attempts, result and trace. Fake HTTP/model boundaries are explicit: these tests
prove routing/validation/persistence, not live family relevance or answer quality.

Initial development corrections were confined to the new tests/attribution:
native attempts are two for timeout/connection (not one); the Python review error
wraps its actual exception, so classification unwraps that known cause by type.
It never reads cause_message. A Ruff import-order finding was corrected. The first
and final full suites are both retained; no live tuning or selective rerun occurred.

## Reproduction and freeze

Use the same isolated `dolved-r28-s03` Compose project as C2, one-off --no-deps
API/AI containers, repository at /workspace. Laravel tests use SQLite memory,
not the pilot database. Retained full API environment: FRONTEND_URL and
CORS_ALLOWED_ORIGINS=http://localhost:3000;
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000;
AWS_BUCKET=dolved-r28-s03-c1-period-tests-20261114.
Set C1_EVIDENCE_ROOT and C2_FALLBACK_EVIDENCE_ROOT to this package for captured
controlled records. PHPUnit: `php -d memory_limit=512M vendor/bin/phpunit
--do-not-cache-result --log-junit=.../changed-api-complete.xml`.
Focused filter: `test_c2_|test_c1_|test_topic_only_compare|CompareFamilyDiagnostics`.

AI: OTEL_SDK_DISABLED=false, process-local Git safe.directory=/workspace;
`/app/.venv/bin/pytest --junitxml=.../changed-ai-final.xml -q`.
`compare_suites.py` reproduces the exact identity diff against the retained raw
baseline. With PYTHONPATH=/workspace/apps/ai, `verify_cross_service.py` revalidates
the five captured PHP request/persisted-use fixtures. Neither helper calls providers.

See code-freeze.json and checksums.sha256. Deploy the additive header producer and
C2 consumer together. No live authorisation created/consumed; no main merge.
The reviewer may now repeat the narrow route check before releasing the unchanged
questions-only input. This correction does not close Block 1.
