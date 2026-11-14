# C1 month/year extension — provider-free implementation freeze, 2026-11-14

Block 1 HOLD. Hold branch only. No provider execution or live authorization. No sealed material accessed.

## Identity and scope

Implementation: b1e94fe5ba57baa147bf174e55c30597208ef400, based on 55f4ed601cd721e596e95c4b443eeda8c5bdd796.
Runtime aggregate: 4dde6707235e66ac707ed1cba90be8845738240dff9e4e6f2a37cc309989b338.
Main remains 348f09e053c8ad73676921c5c616cced812111d7. Unrelated ADR-0039 and governed-runner/test edits remain excluded.

This is an explicitly approved extension of the day-only C1 freeze, not a claim that the earlier implementation handled periods.

## Changed boundaries

- ExplicitTemporalReferences: independent inventory, longest-form scan, original quote/turn, precision, normalised value and whole-period bounds.
- TemporalEvidenceAlternatives: period alternatives use existing CalendarPeriod requests and TemporalPeriodResolver through EligibilityResolver. The alternative's internal authority interpretation is whole-period, not an inherited before/after endpoint rule; original planner need remains unchanged.
- Python temporal_targets / PHP ValidateTemporalAnswerUse: period references require at=null and bounds; each cited version must cover the full half-open interval. Existing day metadata remains readable.
- GenerationResult: saved/reloaded temporal basis names the whole month/year and its bounds, in addition to the cited version's effective interval. Existing unavailable/capacity disclosure remains.
- Shared generation-needs/v4 request schema: optional precision/value/bounds plus nullable reference.at. Response envelope unchanged. Prompt/adapter lineage advances to grounded-generation-temporal-v2 / openai-responses-temporal-v2 in both services.
- Tests: inventory/numeric boundaries, period admission, period-use rejection, actual production orchestration/persistence and cross-service replays. No unrelated assertion changes.

No additional model call, retry, migration, ranking change, topic discovery or accounting change. Existing nullable temporal_context JSON stores the new metadata; coordinated API/AI deployment is required. C1's original column migration must already be deployed.

## Supported syntax and limits

- Existing ISO YYYY-MM-DD and English D Month YYYY, with calendar validation.
- Full English Month YYYY, case-insensitive, without a leading-zero year.
- Standalone four-digit years 1000–9998. Month periods must have a representable exclusive end; December 9999 is excluded.
- Longest date form consumes nested month/year tokens, including invalid full dates, so an invalid day is not silently downgraded to a month/year.
- Identifier characters, slash/colon/hyphen numeric fragments, decimal/comma numeric adjacency, percentages and adjacent year/month/week/day/hour/minute/second units are excluded. This is a bounded syntactic rule, not a general numeric-language classifier.

No abbreviated months, relative-expression redesign, free-form ranges, event-date inference or interval segmentation. Bare calendar syntax is not semantic proof: an old month/year may only be background to a current request.

## Authority and capacity behaviour

A whole period is available only through the unchanged period/timeline and eligibility services. A transition, partial coverage, absent attained version or ineligible version remains unavailable; no endpoint or nearest-version fallback. Original precision is retained, and no month/year is represented as an invented exact day.

Requested/precautionary labels, one bound family, one additional distinct-version search per need, same-version reuse, four logical lanes/five final slots, tenant/approval/applicability/corpus rechecks and ownership remain in force. Period capacity loss is explicit. Current-target use of historical evidence remains rejected. Structural guards cannot prove semantic role selection or source entailment.

## Verification

| Suite | Cases | Failures | New identities | Resolved identities |
|---|---:|---:|---:|---:|
| AI | 1382 | 38 | 0 | 0 |
| Laravel | 889 | 2 | 0 | 0 |

Raw final XML: changed-ai.xml / changed-api.xml. Exact names, baseline hashes and set differences: suite-comparison.json. Both full suites exit nonzero because the retained failures remain. No assertion was weakened to preserve these counts.

Focused first pass: 24 Laravel tests / 328 assertions; Python temporal-use suite 14 passed. Final full runs include the added numeric-limit assertions (Laravel 5,186 assertions). Seven changed PHP files pass Pint --test; three changed Python files pass Ruff lint/format checks. Full mypy passes for 166 source files. Shared JSON schemas are checked against the implemented models by the full AI suite. No web source changed; web-specific checks were not needed for this extension.

Coverage:
- month/year wholly within an eligible version;
- month/year transition, partial coverage, no attained version;
- leap February, December/year rollover, explicit-day regression and overlapping date forms;
- numeric identifiers, decimals, durations, percentages and multiple references;
- current requests with old period background (structural current-target protection, not a live semantic claim);
- four-lane/five-slot preservation, period capacity state and unresolved disclosure;
- full production eligibility/allocation/assembly/result validation/persistence/reloaded rendering;
- PHP partial-period/invalid-bounds rejection and Python start/end coverage/current-history rejection;
- old day-only metadata and historical receipts retained.

month_replay.json and year_replay.json are actual PHP assembly/persistence exports from synthetic development fixtures. The original C1 varied-day and modified bound-family input16 fixtures also pass unchanged in intent. cross-service-replay.json validates all four exports using the Python request/result contracts. Planner/catalogue response, search candidate transport/ranks, generation and entailment HTTP responses are controlled boundaries. The database/application services and orchestration are real. These tests do not measure live ranking, model judgement or confirmation correctness.

## Isolated test environment and initial-run attribution

Only dolved-r28-s03 was inspected/used. Its running API config reported pgsql / postgres / dolved_e2e_r28_s03. Tests ran as one-off --no-deps containers under that Compose project, with repository mount /workspace; Laravel phpunit.xml forces testing and SQLite :memory: with empty DB_URL. Main development stack was not queried or started.

All commands use:
docker compose --env-file .env.r28-s03 -p dolved-r28-s03 -f compose.yaml run --rm --no-deps

AI full command: mount repository /workspace, working directory /workspace/apps/ai, OTEL_SDK_DISABLED=false, GIT_CONFIG_COUNT=1, GIT_CONFIG_KEY_0=safe.directory, GIT_CONFIG_VALUE_0=/workspace, /app/.venv/bin/pytest --junitxml=<package>/changed-ai.xml -q.
PHP full command: php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --log-junit=<package>/changed-api.xml, C1_EVIDENCE_ROOT=<package>; origins/Sanctum use the tests' expected localhost:3000. AWS_BUCKET points to dedicated isolated dolved-r28-s03-c1-period-tests-20261114, created/versioned for the existing real-LocalStack storage test, not corpus storage.

Initial isolated settings caused four AI telemetry failures (OTEL disabled), three PHP authentication/origin failures (3200 rather than expected 3000), and one PHP storage error (configured dolved-r28-s03-document-uploads bucket returned 404). After test-process settings and a dedicated test bucket, a remaining AI fixture failed because Git rejected /workspace's bind-mount ownership; a process-local safe.directory resolved it. Raw initial-isolated-*.xml and git-ownership-ai.xml are preserved. iteration-*.xml preserve the subsequent 38/2 run before final numeric-boundary assertions. No application patch, skipped test or weakened assertion was used for environment attribution. No evaluation corpus objects/database were altered; dedicated test storage remains isolated.

## Handoff

The approved source seal remains reviewer-declared and unchanged. Sealed questions/expectations were not located, opened, searched or hashed. Only separate development fixtures were used. No provider calls: USD 0.

The component freeze is ready for the independent reviewer's narrow source/timeline/exposure and route check, not live acceptance. The reviewer must assess which sealed questions actually exercise C1 after this freeze. Coordinated deployment precedes any separately authorised live checkpoint. No authorization created/consumed, no inputs17–32, no C2/Block3 start, no main merge or Block1 closure.
