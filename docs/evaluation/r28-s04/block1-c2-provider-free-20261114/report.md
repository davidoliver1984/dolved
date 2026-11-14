# C2 — topic-only temporal family discovery, provider-free freeze

2026-11-14. Parent `3c389738f275cab4a57a8be72732c72b963768e8`.
Hold branch only; Block 1 HOLD. No provider calls, spend, live authorisation,
confirmation-set authoring or access. Main is unchanged.

Implementation: `fca272e96b083c9849a1591f6dfe5d8efd6bb092`.
Runtime aggregate: `fb81a4cafa619b4a4e3c89daadab33a277a92a922d164d1e1a67bfb0b47c29ac`.

## Boundary and implementation

The existing plan records the dependency boundary before implementation.
Only `RetrieveWorkspaceEvidence.php` changes at runtime. Topic-only retrieve
needs containing a C1-supported explicit calendar reference use the existing
current-side discovery, reranker and evidence-anchored family reviewer. One
validated family becomes the application binding consumed by unchanged C1.
Plain non-temporal retrieval and existing comparison execution are unchanged.

The existing reviewer prompt, HTTP contract and Python/PHP identity/quotation
checks are reused unchanged. A selected family without history does not cause
another family to be tried. Ambiguous/no-match/rejected selection or unavailable
review gives insufficient evidence through existing answer/disclosure machinery,
not date expansion across families. No model-selected version IDs are introduced.

Current discovery candidates are reused only after the bound eligibility and
corpus checks, with the original rerank scores, threshold and final-K policy.
Failure to establish equivalent current eligibility is disclosed as capacity
limited; no replacement current search is issued. The requested plan, timeline,
permissions, per-version applicability, C1 admission/allocator, generation
contracts and historical receipt readers are unchanged. No migration is needed.

Discovery call counters and completed-stage usage are additive lineage. The
counters count application endpoint dispatches, not hidden provider-native
retries; native usage/retry receipts remain in the existing usage records.
`charged_to_logical_lane_limit=false` explicitly records the unchanged policy.
Do not add the diagnostic copy of completed usage to the top-level usage again.
The four logical lanes, five slots and one additional distinct-version lane per
need are unchanged. This does not claim a global four-HTTP-search limit:
discovery admission accounting is still a separate product decision.

## Verification

| Check | Result |
|---|---|
| Focused C1/C2/topic-comparison tests | 40 tests, 647 assertions, pass |
| Full AI | 1,382 tests; 1,340 passed, 38 failed, 4 skipped |
| Full Laravel | 903 tests; 2 failed, 9 skipped; 5,439 assertions |
| Exact baseline failure identities | AI new/resolved 0/0; Laravel new/resolved 0/0 |
| PHP assembly/persistence → Python contract replay | 5/5 valid, including original topic-only input16 |
| Changed PHP files, Pint --test | pass |
| Changed source diff whitespace check | pass |

Raw `changed-ai.xml`, `changed-api.xml`, `focused-api-final.xml` and
`suite-comparison.json` are retained. Baseline paths/hashes and every failing
test name are in the comparison. No assertion was loosened or skipped. Named
C1, temporal ownership/current-versus-history guards, and historical receipt
compatibility remain in the full suites. Web/typecheck were not rerun: no web,
Python implementation or cross-service wire schema changed.

The original input16 fixture preserves authority=current,
fact_reference=2026-02-01, topic binding and empty family_ids. The real retrieval,
eligibility, orchestration, generation assembly/validation and persistence run
with controlled planner/search/family-review/generation HTTP boundaries. Its
family is selected from actual authorised candidates, never injected into its
planner binding. Both versions survive; controlled generation uses the historical
target and the saved/reopened answer retains its date/version and disagreement
receipt. `c2_original_input16.json` contains the request, plan and persisted use.
This is development wiring evidence, not a live wrong-planner rescue.

A varied current-with-old-date fixture answers from the current target and
persists only current citations, with planner_disagreement=false. A historical
requested-plan fixture reuses discovery current evidence without another current
search. Ambiguous, no-match, wrong identity, wrong quotation, unavailable review,
empty discovery, no history, changed eligibility, saturated capacity and hybrid
threshold paths have individual `c2-discovery-*.json` receipts.

### Measured controlled work (one handleNeeds invocation per fixture)

| Fixture(s) | Search dispatches | Reranker dispatches | Family-review dispatches | Result |
|---|---:|---:|---:|---|
| selected/current background; historical; eligibility_changed | 2 | 0 | 1 | One family; history retained; changed current eligibility disclosed |
| hybrid | 2 | 2 | 1 | Original scores/threshold retained on reuse |
| threshold | 2 | 2 | 1 | Below-threshold evidence remains filtered |
| ambiguous; no_match; wrong_identity; wrong_quote; unavailable | 1 | 0 | 1 | No family/date expansion |
| empty | 1 | 0 | 0 | No reviewer invocation |
| no_history | 1 | 0 | 1 | Current retained, history ineligible; no fallback family |
| capacity (three needs) | 4 | 0 | 3 | Three discovery searches + one historical search; two dated alternatives disclosed capacity-limited |

The original input16 path uses one discovery search, one family review and one
dated search; current evidence is reused. Its test also runs direct assembly,
full orchestration, token-budget exhaustion and target-rejection controls; those
extra controlled invocations are not live usage. No latency/cost/model accuracy
inference is made from fake boundaries.

## Limitations and retained development observations

- No live C2 family-selection correctness or production ranking is established.
  The independent reviewer freezes the separate topic-only set after this freeze.
- Input16's synthetic fixture has no registered Harbour View location. The saved
  assistant display therefore includes the existing non-blocking location notice;
  `GeneratedAnswer::renderedText()` alone contains temporal text but not that
  orchestration-added notice. Both are retained, not described as byte-identical.
  C2 does not alter that pre-existing rendering distinction or resolve site scope.
- Controlled current-answer generation proves admissible wiring, not semantic
  model reliability. C1/F5/T12 and other Block 2/3 limitations remain owned there.
- Early new hybrid fake responses omitted rank/accounting fields; these fixtures
  were corrected to the existing contract. One new assertion initially expected
  `empty` rather than existing `filtered`. A later fake-handler ordering mistake
  is retained in `focused-api.xml`; final focused/full results pass. Production
  ranking/threshold rules and existing assertions were not adjusted to these tests.
- The first cross-service helper invocation lacked PYTHONPATH; rerun with the
  application module path passed. This was test invocation, not application code.

## Reproduction

Use isolated Compose project `dolved-r28-s03`, `.env.r28-s03`, `compose.yaml`,
one-off `run --rm --no-deps` containers, repository mounted at `/workspace`.
Laravel PHPUnit uses SQLite memory. Full API environment overrides:
FRONTEND_URL/CORS_ALLOWED_ORIGINS=http://localhost:3000,
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000,
AWS_BUCKET=dolved-r28-s03-c1-period-tests-20261114,
C1_EVIDENCE_ROOT=/workspace/docs/evaluation/r28-s04/block1-c2-provider-free-20261114.
Run `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --log-junit=.../changed-api.xml`.
Focused filter: `test_c2_|test_c1_|test_topic_only_compare`.

AI: OTEL_SDK_DISABLED=false; process-local Git safe.directory=/workspace;
working directory /workspace/apps/ai; `/app/.venv/bin/pytest --junitxml=.../changed-ai.xml -q`.
Run `compare_suites.py` to reproduce the exact failure-set diff. With
PYTHONPATH=/workspace/apps/ai, run `verify_cross_service.py` for captured request/
result validation. No clients/providers are instantiated by either helper.

## Disposition

Provider-free implementation freeze only. Publish to the hold branch, stop before
provider execution. Independent confirmation and Block 1 criteria remain pending.
See `code-freeze.json` for implementation/runtime identities and `checksums.sha256`
for every evidence file except the checksum file itself.
