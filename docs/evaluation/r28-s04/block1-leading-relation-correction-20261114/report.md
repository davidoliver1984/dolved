# Grounded leading-relation correction — 2026-11-14

Block 1 **HOLD**. Provider-free implementation only; no provider calls, deployment,
live diagnostic, main merge or closure. Starting hold-branch HEAD
`c21c586152e6b74833bb9ac32b059f54ecc56476`; implementation
`2dc6bfc3ffad5cdd9291ca60786f4fa648ee60a0`.

## Boundary and changes

At the existing Python provider-adaptation boundary, a `period` authority becomes
`before`/`after` only when its source starts with that word under the unchanged
authority validator's leading-word predicate, the exact quote belongs to its
authorised user turn, and precision is day/month/year. Only kind changes. Dates,
precision, source, operation and bindings remain untouched. The helper deep-copies
its input and is idempotent. Three-need bound is unchanged; oversized proposals
still face validation. All schema, grounding, calendar, catalogue and interpretation
checks remain in place; `needs.py` is unchanged.

The existing first-attempt `temporal_relation_grounding` feedback now includes
exactly David's fixed explanatory hint. No rejected text, exception text or user
text supplies that hint. All other codes retain their existing feedback. This is
improved corrective feedback, not evidence of reliable future interpretation.
The two-attempt ceiling, model and provider limits do not change.

`leading_relation_conversion_count` is additive to the existing default-off,
workspace-restricted evaluation sidecar (0–3; unavailable if parsing never reached
the boundary). No new ordinary-log text or raw data is emitted. Existing disabled,
foreign-workspace, destination and capture-on/off tests still pass.

Producer/consumer advance together to `structured-chat-needs-v12`. Prompt
`bounded-evidence-needs-v26` and schema `evidence-needs-plan-v4` are unchanged.
Current producer fixtures and the real-adapter cross-service artifact are updated.
Normal tests reproduce/compare the artifact, never regenerate it; explicit
regeneration was used once with controlled HTTP transport and interpretation.
PHP verifies the actual generated fingerprint and rejects obsolete v10 and v11
identities for new execution. Historical v3/v9 reader and archived receipts remain
unchanged. A future deployment must coordinate producer and consumer; none occurred.

Runtime aggregate (961 tracked runtime/contract files):
`d5e0b5caf13ec428919d0020fb1bd701620b467c16c5310b13d7eabe04758279`.
See `runtime-source.sha256`, `runtime-identity.json` and `verify.py`.

## Five retained attempts: structural replay, not original-plan acceptance

| Captured attempt | Conversion | Production validation result with labelled synthetic envelope |
|---|---|---|
| 17/1 | period → before; 2026-01/month unchanged | Schema, grounding, catalogue and execution compilation accepted |
| 25/1 | period → before; 2026-01/month unchanged | Same structural acceptance |
| 25/2 | period → before; 2026-01/month unchanged | Same structural acceptance |
| G3/1 | None; after/2025-07-01/day retained | grounding/root/temporal_relation_grounding |
| G3/2 | None; after/2025-07-01/day retained | grounding/root/temporal_relation_grounding |

`replay.py` runs the real `_plan_needs_once` correction/validation path with
controlled transport, one request per retained attempt. Original captures are
read-only and SHA-bound in `replay.json`. They omit `clarification`, diagnostic
`aspect`, `supports_need_id`, binding `ownership` and `ownership_source`.
The replay explicitly supplies null clarification/support/ownership_source,
aspect equal to the retained question span, and authorised_workspace ownership.
It uses original released user text, a fixed synthetic evaluation anchor (no
relative dates here), and an empty synthetic catalogue (all retained bindings
are topic/empty). The first incomplete-envelope replay's missing-ownership
rejection is preserved in `replay-incomplete-envelope.json`.

This is **not full original-plan acceptance**, semantic completeness proof or
checker acceptance. No checker/model, retrieval, generation or source scoring
occurs in the replay. G3's “once it started” is not automatically equivalent to
after the named day; the helper does not reinterpret it. Its current rejection
remains, and whether the fixed feedback makes a future model choose correctly is
unmeasured. The three original period proposals remain invalid under the original
contract; neither their old scores nor records were rewritten.

## Verification

| Check | Actual result |
|---|---|
| Focused Python: leading relations, notation, feedback, capture, provider schema | 131 passed (`focused-final.xml`) |
| Full AI | 1,471 tests: 1,429 passed, 38 failed, 4 skipped |
| Full Laravel | 931 tests: 2 failed, 9 skipped; 5,658 assertions |
| Exact retained baseline identities | Zero new; zero absent/resolved failures, both suites |
| Intermittent inode-substitution test | Reproduced; not claimed fixed |
| Scoped Ruff/format, Mypy, PHP Pint | Passed |

Raw full JUnit, logs and exact failure-name sets are retained. Baseline identities
and hashes are those already retained in the preceding package, not a newly chosen
baseline. New tests cover leading before/after, no/mid-quote relation, unknown turn,
ungrounded quote, forbidden precision, all other authority kinds, kind-only/no
mutation/idempotence, count receipt, hint isolation, unchanged retry ceiling and
background-event fact context retaining current authority. No assertion relaxed.

Execution notes: Docker-only verification. The first isolated PHP test invocation
could not interpolate an unset corpus mount; after setting it, that test container
lacked engineering test mounts and inherited an inappropriate deterministic profile
(8 errors/5 failures, preserved in `api-full.xml/log`). The established base test
container has those fixtures; final runs used its PHPUnit SQLite test environment
with the established OpenAI profile/CORS test settings and 512MB test-process limit.
No application configuration or corpus was changed to fix these setup errors.
Mypy initially hit a bind-mounted SQLite cache I/O error; a container-local cache
passed. Initial scoped lint reported import/unused-import formatting only, corrected
before final checks. The Perl identity rewrite initially hit an unavailable locale;
rerunning with `LC_ALL=C` completed the mechanical fixture changes.

Reproduction commands: AI `docker compose run --rm --no-deps` with repository mounted
at `/workspace`, working directory `/workspace/apps/ai`, `uv run pytest -q --junitxml=...`
(full suite with OTEL enabled and Git safe.directory set). PHP
`docker exec ... rag-platform-api-1 php -d memory_limit=512M vendor/bin/phpunit
--do-not-cache-result --log-junit ...`. `verify.py` computes exact sets and the runtime
freeze; `replay.py` writes the per-attempt structural results. Only fake provider
boundaries were used. No paid authorisation was consumed.

## Unresolved findings and next-run boundary

17's separate family-selection failure stays open. Items 19 (temporal selector/
period routing), 28 (role interpretation), 24 (contact/condition conflation), and
26 (missing encryption evidence/incorrect association) remain shared Block 1
acceptance findings, even when implementation belongs to other packages.
Item 29 remains provider-free verified, not live demonstrated.

The earlier three-input diagnostic already used the intended small diagnostic
opportunity. No replacement is scheduled. The eventual decision run retains its
agreed fresh/control cohorts and six known regressions **19, 24, 26, 28, 29, G3**.
Adding 17 or 25 would require an explicit proposal; neither displaces the harder
regressions nor silently enlarges that run.

## Investment accounting

The private boundary record was read. Its activation status is still not activated
in that record; approved start, working-time definition, deadline and remaining
allowance are not recorded. This instruction authorises this correction, not a
silent activation/reset/extension of that allowance. No start date, consumed focused
days or remaining allowance is inferred. `work-accounting.json` records this task's
measured wall-clock interval from the first recorded clock observation after initial
inspection; initial inspection before that observation is not timed. It is not a
conversion into focused working days. Journey is updated locally only.
