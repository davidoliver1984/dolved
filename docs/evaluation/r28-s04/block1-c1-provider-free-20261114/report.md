# C1 provider-free component integration — 2026-11-14

Status: component implemented; Block 1 HOLD. No live/provider execution. Hold branch only; main unchanged. This is not pilot readiness or a claim that temporal interpretation is fixed.

## Production boundary and dependencies

- Retrieval: ExplicitTemporalReferences inventories grounded ISO YYYY-MM-DD / English D Month YYYY dates independently of planner authority/fact labels. TemporalEvidenceAlternatives applies only to retrieve needs already matched to one family. It calls the unchanged EligibilityResolver and DocumentAuthorityTimeline with the same tenant, approval, corpus and applicability controls. No topic discovery or discovery-accounting change.
- Allocation: NeedsEvidenceSelection reserves a requested passage and an admitted precautionary target before filling spare slots. Existing limits remain four logical lanes and five final chunks; at most one additional distinct-version search per need. Same-version targets reuse a search. Empty, filtered, ineligible and capacity-limited targets are recorded.
- Generation: request/result envelope 4, bounded temporal_targets and temporal_uses, profile generation-result-v4 / grounded-generation-temporal-v1 / openai-responses-temporal-v1. Original question spans remain available; no query, ranking or threshold change. Existing generator and entailment stage, no extra model call or retry.
- PHP/Python validation: selected target must be available, owned by the part's need, question-grounded, and consistent with the cited family/version and inclusive-start/exclusive-end interval. A current target cannot cite a historical alternative. This does not prove semantic intent.
- Persistence/rendering: nullable generated_answers.temporal_context migration; chosen target, effective interval, requested/precautionary purpose and planner disagreement survive reload. Incomplete targets are disclosed. Existing answers are not backfilled.
- Active temporal checker v3 is retired in favour of the existing general v2 check; historical v3 structures, receipt readers and failed evidence remain. V2 is not a proven temporal safeguard.

The migration and both producer/consumer versions must deploy together before live use. No production migration was executed in this task.

## Verification and evidence

Full suite raw XML and exact failure identities are retained in verification/ and suite-comparison.json:

| Suite | Tests | Failures | New | Resolved |
|---|---:|---:|---:|---:|
| AI | 1380 | 38 | 0 | 0 |
| Laravel | 879 | 2 | 0 | 0 |

Baseline: block1-extractive-20261112/verification/migrated-ready-ai.xml and migrated-ready-api.xml, with their hashes in suite-comparison.json. Earlier iteration XML is retained too. No baseline assertion was weakened to clear failures. Historic temporal-v3 tests now directly validate the retained historical model/function; active-call assertions are covered separately by test_active_checker_uses_general_schema_once_and_retains_usage.

Commands:
- Docker AI pytest --junitxml=.../verification/changed-ai.xml -q, repository mounted /workspace, working directory /workspace/apps/ai.
- Docker API php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --log-junit=.../verification/changed-api.xml, C1_EVIDENCE_ROOT set to this package.
- Docker Python verify_cross_service.py with PYTHONPATH=/workspace/apps/ai validates both actual PHP assembly/persisted-use exports. See cross-service-replay.json.
- Scoped Ruff passed; scoped Pint --test passed (17 files); source/contract git diff --check passed. Raw JUnit retains pytest traceback whitespace verbatim, so the all-artifact whitespace check reports those XML lines; they are deliberately not reformatted.
- Python mypy --no-incremental --cache-dir=/tmp/c1-mypy-final app passed, 166 source files. Web tsc --noEmit --incremental false passed. Default make typecheck hit a bind-mounted cache write error, not a type result; clean-cache commands above are the successful checks. Ruff also reported a cache I/O warning while returning success.

External boundaries substituted: HTTP planner response/catalogue binding, search candidate transport (including ranking scores), generation response and entailment verdicts are controlled fixtures; no embeddings, live reranker, generation, checker or other provider executes. The Python real adapter test supplies a fake Responses transport and verifies only the existing generation plus entailment calls. Database fixtures exercise production authentication/membership, orchestration, timeline/eligibility, hydration, allocation, assembly, result validation, persistence and reloaded rendering. Tests do not establish live retrieval relevance or model answer correctness.

## Required mechanism coverage

| Shape | Provider-free evidence / limit |
|---|---|
| Wrong current plan with dated question | test_c1_wrong_current_plan_preserves_dated_alternative_and_persisted_answer_use, modified_input16 and varied_development; actual exports included |
| Correct historical request | c1AdmissionCases historical: old requested/deduplicated dated target plus current precautionary target, two searches |
| Current target with old background | Python/PHP target-use tests accept current evidence and reject historical evidence under current target; semantic background interpretation remains unmeasured |
| Two dates | c1AdmissionCases two_dates inventories both; one alternative admitted and other explicitly capacity-limited; this is incomplete, not proof both facts are answered |
| Existing historical/current comparison | Existing comparison suite retained; compare-pair allocation test passes; C1 does not expand compare/scope contracts |
| Missing/invalid/event dates | ExplicitTemporalReferences unit test rejects invalid calendar date and invents no event date. Existing temporal clarification validators unchanged |
| Conflicting meaning | No automatic semantic disambiguator added. Existing planner clarification and generation's explicit unresolved-choice instructions remain; provider-free structural tests cannot demonstrate language judgement |
| Wrong tenant/family/version/lane/target | c1AdmissionCases foreign_family performs zero searches; TemporalAnswerUseTest and test_temporal_use_rejects_wrong_identity_or_unavailable_evidence reject identity, interval, quote and coverage mutations |
| Missing/ineligible/applicability | c1AdmissionCases missing_history, ineligible, applicability exclude old versions without widening scope |
| Empty search | c1AdmissionCases empty_search records empty targets, no invented evidence |
| Lane/slot saturation | capacity has three requested lanes plus one admitted alternative; two targets capacity-limited. NeedsEvidenceSelection guard slot test preserves alternative against higher-ranked spare passages within five |
| Token budget | Integration replay reduces context budget: local controlled insufficient-evidence disclosure, zero generation calls; no claim that missing context means missing source facts |
| Multiple passages / qualifiers | varied_development keeps two dated passages including a separate qualifier through actual assembly and persisted citations |
| Persistence/citations | Both exports reload GeneratedAnswer and validate temporal ownership; cross-service-replay.json independently parses PHP payloads with Python v4 models |
| Old receipts | Retained historical v3 validation tests and unchanged PHP historical receipt reader; new active-v2 schema/usage test |

## Modified diagnostic replay, not a complete user-journey fix

modified_input16.json uses the retained input16 question/plan, with a single bound fixture family injected solely in the test. Its original topic-only record is unchanged:
r06-governed-evidence/R28-R07-BLOCK1-EXTRACTIVE-FINAL-20261113-01/cases/01-v4.case.corrected-b03-07-v1.json.

The export demonstrates current and historical candidates carrying distinct target labels into the generation request, the controlled historical answer selecting its dated target, planner_disagreement=true, and persistence/reloaded time-basis rendering. The input fixture is labelled MODIFIED DIAGNOSTIC FIXTURE. No benchmark family mapping exists in production. Original input16 still requires C2.

## Remaining judgement and limits

The application verifies identities and dates, not the meaning of prose. A model could select a structurally valid dated target for a question that actually asks about current policy, misstate a source, or mishandle conflict. Existing entailment review receives target metadata but remains model-assisted; no claim of general reliability follows. Missing necessary dates/conflicting meaning must remain conversationally unresolved, not invented; this has not been live-tested here. An internal target validation failure uses the existing bounded retry/safe insufficient-evidence path, not a request to repeat an already supplied date.

Only exact explicit days in grounded question/context spans are inventoried. Month/year-only periods, relative expressions, ambiguous numeric dates and event/anaphoric expressions are not newly recognised. Existing relative-calendar rules remain unchanged. One alternative per need and five chunks can still leave a relevant version/qualifier unrepresented; disclosure means incomplete coverage, not success. More than nine target metadata records or an over-budget context uses local insufficient-evidence disclosure.

No new unexplained full-suite failure was observed. Known 38/2 failures remain owned by their existing blocks. The component has not been exercised live, nor have live ranking, semantic role selection, conflicting-date clarification, overhead, or source-based answer correctness been measured.

## Freeze, next action and stop

See code-freeze.json, runtime-source.sha256, changed-files.txt and checksums.sha256 for identities. The implementation parent is d51ba5a2e53bc5c453b8ab1fd08f84030fe93473. Pre-existing ADR-0039 and governed-runner/test edits are excluded; unrelated untracked material is untouched. PROJECT_JOURNEY.md is updated locally only and remains ignored.

The supplied sealed hashes are recorded as reviewer-declared in the repair plan. Sealed questions/expectations were not located, opened, searched for or hashed. No confirmation questions or family identities were used to develop this component.

Next: independent reviewer verifies source support, ingested chunks, application timelines and exposure history against the preserved seal, after implementation freeze. Any correction needs a versioned amendment. Only then can a separately authorised small production checkpoint use normally bound explicitly named documents, preserve every result, and measure requested-history versus exercised precautionary routes, slot/use handling, citations, cost and source correctness. Do not use injected plans in that live confirmation. C2 remains separate and mandatory for topic-only questions.

No live authorization consumed; provider cost USD 0. Main unchanged, Block 1 HOLD. Stop here before provider execution.
