# Explicit calendar notation correction — 14 November 2026

**Superseded verification boundary:** reviewer inspection found that this initial
implementation advanced the Python adapter without updating Laravel's v10 pin.
The focused suites below did not exercise that boundary and were insufficient.
See [the coordinated correction](coordination/report.md) for the repaired consumer,
shared producer-generated artifact, new full-suite comparison and final freeze.
The earlier runtime below was never authorised for a live run or committed.

Implemented on the hold branch from `629a4e3198aeeb3f5404ab9ee48dbe66ed571523`.
**Provider-free verification complete; Block 1 HOLD. No live run or commit.**

## Correction and boundary

The existing planner adapter now canonicalises an explicit English calendar value
before validation, for fact references and authority references alike. It changes
only `value`: month to YYYY-MM and day to YYYY-MM-DD. Numeric years are already
canonical and remain unchanged. Full month names, the existing three-letter month
forms, ordinal days and Month D, YYYY forms are supported only where the existing
date-grounding grammar accepts them. The entire value must match its declared
precision, occur in its source quote, and that quote must occur in its identified
user turn. Calendar validation rejects impossible dates before conversion.

No day/year is invented. Precision, authority kind, operation, source, binding and
all other fields remain untouched. Duration, quantity, named, interval, relative
and already-numeric references are untouched by this new step. Existing relative
calendar handling then runs as before. All schema, calendar, grounding, authority
coherence, catalogue and interpretation checks remain in place. Invalid or
unsupported representations are left unchanged for those checks to reject.

The only new log fields are a fixed event name and a count bounded to six reference
conversions (three permitted needs, two references each). No dates, question text,
raw plans or exceptions are logged by this step. No additional provider call,
retry, model change, retrieval rule or threshold was introduced.

Prompt remains `bounded-evidence-needs-v26`, unchanged byte-for-byte. Provider
schema and application validators are byte-identical to HEAD. Adapter identity is
`structured-chat-needs-v11`; shared contract remains `evidence-needs-plan-v4`.
No Laravel change or migration is required. Do not use an old live authorisation
against this changed runtime. Runtime source aggregate:
`cb45d4db1cee0ab9e1e3edd7cf85aa4a25603f5ca5ecfe60fb5c447e730bf36a`.

## Retained diagnostic replay — limits explicit

`replay.py` and `replay.json` preserve a distinction the original proposal missed:
the observer retained a projection, not the full original plan. It omitted
`clarification`, `central`, `supports_need_id`, `claim_under_test` and
`scope_question`, and introduced a non-contract `fact_reference.kind: null`.
Consequently full original-plan acceptance cannot truthfully be reproduced.

All four retained temporal references pass their real field schemas after exactly
one value conversion each. For the actual grounding method, the replay uses an
explicitly labelled synthetic envelope with documented fillers/defaults for the
omitted fields. No temporal value, relation or captured source quote is invented.

| Retained attempt | Value-only change | Synthetic-envelope grounding |
| --- | --- | --- |
| G2/1 | March 2025 → 2025-03 | PASS |
| G2/2 | March 2025 → 2025-03 | PASS |
| G3/1 | 1 July 2025 → 2025-07-01 | PASS |
| G3/2 | 1 July 2025 → 2025-07-01 | Rejected: temporal_relation_grounding |

G3/2 still proposes `after` without grounding that relation. The conversion does
not repair it. Neither the interpretation checker nor downstream retrieval or
generation ran. These are not fresh model outputs, accepted original plans or
successful answers. Original C2 scores and diagnostic artifacts remain unchanged;
all prior diagnostic checksums verified (`prior-diagnostic-integrity.log`).

## Verification

- Before-change focused baseline: 165 passed (`before.xml`).
- Final focused suite: **200 passed** (`focused-final.xml`), including 35 new
  cases for explicit forms, precision mismatch, invalid dates, missing grounding,
  no input mutation, idempotence, preserved current authority, unchanged numeric
  and noncalendar values, real mock-transport execution and relation rejection.
- Full AI: **1,425 tests: 1,383 passed, 38 failed, 4 skipped**.
- Full Laravel: **927 tests: 2 failures, 9 skipped, 5,629 assertions**.
- `suite-comparison.json`: exact retained 38/2 identities; zero new failures and
  zero resolved claims. The previously non-reproducing inode-substitution test
  reproduced in this run.
- Ruff lint passed; formatting applied and checked on the four changed Python
  files. Scoped Mypy passed with a container-local cache. `git diff --check` passed.

Two existing parametrised tests explicitly expected the adapter to reject quoted
textual authority dates. They now assert the approved conversion yields exactly
the same plan as canonical input, while retaining direct raw-contract rejection
and rejection of an invented exact day. The provider-schema lineage assertion
advances only the adapter version. No unrelated assertions were relaxed.

Initial verification failures are retained, not hidden: `focused.xml` records the
old adapter expectations and a new fixture's missing completion marker; these were
corrected to test the intended boundary. The first full AI invocation used `/app`
instead of the repository layout and failed collection. The first Laravel
invocation used corpus-only runtime mounts and lacked the normal test fixtures.
Final full tests use the established repository layout/base Compose test mounts,
under the same isolated project. Mypy's bind-mounted SQLite cache failed with a
disk I/O error; a container-local cache passed without dependency changes.

## Reproduction and disposition

All Docker commands use `--env-file .env.r28-s03 -p dolved-r28-s03` and
`run --rm --no-deps`. No development stack restart/deployment occurred. Full tests
use `-f compose.yaml`, repository mounted `/workspace`. AI runs from
`/workspace/apps/ai` with OTEL enabled and process-local Git safe.directory set to
`/workspace`. Laravel runs from `/app`, with localhost:3000 origins, mocked OpenAI
generation profile and the existing isolated test bucket; PHPUnit uses SQLite
memory, not the evaluation database. Initial/final raw logs and JUnit are retained.

Focused command: `/app/.venv/bin/pytest tests/test_needs_calendar_notation.py
tests/test_needs_provider_schema.py tests/test_evidence_needs.py
tests/test_needs_validation_retry.py tests/test_needs_correction_feedback.py -q`.
Types: `/app/.venv/bin/mypy --cache-dir=/tmp/r28-mypy-cache
app/retrieval/needs_planner.py`. Replays and identity comparison run with
`PYTHONPATH=/app` using this package's `replay.py /workspace` and
`verify.py /workspace`. No helper calls providers.

Main remains `348f09e053c8ad73676921c5c616cced812111d7`; unrelated edits preserved.
No provider calls, live authorisation, expectation-folder reads, scoring reruns,
main merge or Block 1 closure. Code and evidence are prepared but uncommitted.
The next planned live verification remains the new-runtime Block 1 checkpoint,
with any G2/G3 additions labelled known regressions, not replacement C2 scores.
