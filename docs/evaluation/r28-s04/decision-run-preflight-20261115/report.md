# Phase A — decision-run deployment and preflight, 15 November 2026

**Not cleared for Phase B. Block 1 HOLD. No provider calls, input release access,
authorisation creation/consumption or live dispatch. Main unchanged.**

## Boundary and deployment

Application commit `15ce7c041f465a2a312c54b30c1bcd62b9805ee7`, hold branch
`codex/block1-extractive-hold-20261112`. All 972 manifest files verified on the
host and the corresponding API/AI mounted files verified. Aggregate:
`b73d054d9492e909cfaeadb642544950cfe940de10bfcbe12b388b089997b901`.
Tracked runtime paths exactly match the manifest. No untracked non-ignored runtime
source remains after David moved the three sync duplicates out of the repository.
Ignored extras are Python bytecode caches (including `__pycache__ 3`) and Laravel
package/service caches; none was deleted or represented as source in the freeze.

Only `dolved-r28-s03` was operated. Existing containers were restarted, retaining
their existing evaluation configuration and the frozen bind-mounted source.
The API entrypoint launches its supplied command; AI lifespan configures logging
and telemetry, not ingestion. Both serving containers have telemetry disabled.
Workers, conversation-worker and publisher remain stopped. Mailpit was also
started as a dependency; it is an inert mail sink, not a worker.

**Deployment-command incident, retained:** `compose start qdrant localstack api ai`
unexpectedly traversed dependencies and started role-bootstrap. This was the wrong
command for the intended no-dependency start. Role-bootstrap exited 2 on PostgreSQL
password authentication, before obtaining a connection; no role/grant/ownership
SQL could execute. It was explicitly stopped. API was then started directly with
`docker start dolved-r28-s03-api-1`. No role correction or migration was attempted.
LocalStack's ordinary ready hook provisions local bucket/queue configuration;
it does not process documents or call providers. No ingestion/rebuild/re-embedding
command was run.

The owner-role migrator used the existing env file and project, compose.yaml,
compose.e2e.yaml and compose.r28-s03.yaml, `run --rm --no-deps`, explicitly
overriding the default to `php artisan migrate:status`: **50 Ran, zero pending**.
Database `dolved_e2e_r28_s03`, host `postgres`; authorised workspace
`5323cd6b-8b7a-40f9-98b0-9413c75f643e` and actor
`ae454ce6-4d91-4c7e-9a40-b4750b696a6b` pass the existing membership query.

Corpus `b24a79c2-875e-4f7c-8fa9-3681db92ae73`, digest
`9b3acad2659bd9d959e41819345123389c9d642029b1215a72b01b12b5f6bf80`:
986 assignments, zero foreign assignments. Qdrant `rag-platform-vectors-v1`
is green; 986 scoped points, zero foreign points in this active generation,
and the complete chunk-ID set equals the database assignments. Dense Voyage
voyage-4-large / 1024 / cosine; sparse fastembed Splade. Exact active space IDs
and vector names are in `verification/index-identities.json` and `qdrant.json`.

## Harness and verification

The harness is copied from **committed** source at the application commit, never
from the dirty `scripts/evaluation/run_r28_r06_governed.py` or its dirty test.
`prepare.py` records the derivation. `checkpoint.py` adds a 34-item gate with
24 fresh / 4 control / 6 regression counts while retaining released file order.
It requires future Phase B approval, exact input/harness/dependency/commit/runtime
identities, exclusive consumption and per-item start/record markers, and checks
runtime/budget before each item. A started item cannot be selectively retried.
No real authority record or question file exists here. Development fixtures use
temporary directories only. Production `dispatch.php` retains the normal
authenticated membership, persisted conversation and orchestration path; its
queue fake prevents duplicate asynchronous execution, as in previous runs.

Typed selector invalid/incomplete output is a safely recorded item outcome only
when production explicitly says `run_stop_required=false` and no proposal was
consumed. Protected, unknown and infrastructure failures stop. Generic HTTP 503
is not sufficient attribution. Existing planner/generation contract-rejection
handling is retained. Production attempt ceilings are untouched.

| Check | Result |
|---|---|
| Harness order, duplicate/second use, identity, budget and typed stop controls | 12 passed |
| Existing AI evaluation-capture and selector fixtures | 28 passed |
| Existing PHP selector/allocation fixtures | 13 passed, 46 assertions |
| Current development orchestration capture fixtures | 5 executed: 3 passed, 2 failed reload-render equality |

The two failures are `modified_input16` and `c2_original_input16` in
`test_c1_wrong_current_plan_preserves_dated_alternative_and_persisted_answer_use`.
Their assistant display includes **Location coverage not established** and the
generic-guidance notice; `GeneratedAnswer::renderedText()` after reload omits that
notice. Both representations are retained in the corresponding `capture-*.json`.
Temporal context survives reload. This is a newly observed provider-free
representation discrepancy, not a live correctness verdict. No application fix
or deeper diagnosis was undertaken. The equality assertion remains failing.

The first PHP invocation used the wrong dependency root (`/workspace/apps/api`)
and failed loading framework configuration. The corrected `/app` invocation and
its raw JUnit are retained separately. A later repeat only moved capture-file
writing before the failing assertion so both failed fixtures are retained; no
assertion was weakened. PHP result-cache I/O notices are retained, not counted as
test failures. The first index script used the wrong payload key; corrected to
the production `chunk_id` key before the successful read-only verification.
No full application suite rerun was required or claimed for this harness-only work.

## Capture availability and remaining preparation

- Existing observer retains accepted plans, routes, C1/C2/discovery, selection
  lineage/proposals/unmet coverage, final candidates and receiving associations,
  generation request/evidence, answers/citations, temporal targets/uses,
  persistence/reload representations and service/stage usage.
- Serving application sidecar supports rejected planner/generation allowlists,
  exact normalisation counts and closed rejection reasons. Existing authenticated
  route fixtures prove workspace/off-state gating and unchanged outcomes/retries.
- Accepted source-review results expose derived entailment checks and usage;
  rejected attempts expose allowed generation fields and closed diagnostics.
  **Raw internal source-review JSON, including all anchors, is not retained by
  this capture.** A raw incomplete model receipt cannot be reconstructed. This
  is explicitly unavailable, not inferred from a categorical failure code.
- Capture is currently **off on the serving AI**, as found in the retained
  container configuration. `capture.override.yaml` is prepared, not applied:
  the Phase B evidence destination must be bound and capture enabled/verified
  before dispatch. No sidecar records are claimed from an unexecuted route.
- Reviewer inputs/context and Phase B authority have not been released. Exact
  final input hashes, the reviewer-specified context and the authenticated shell
  adapter binding remain to be supplied. The frozen gate and existing dispatch
  boundary are not represented as a consumed or executable live authorisation.

The two render-equality failures and these capture limitations must be visible to
David before Phase B. No application change is authorised by this preflight.
Necessary isolated services remain running for read-only reviewer source/timeline
checks; background workers and role-bootstrap are stopped.

## Reservation proposal

`reservation.json` / `reservation.py` give the reproducible 34-item calculation.
Retained gpt-5-mini rates are USD0.25/M input, USD2/M output (2026-08-19);
Voyage embedding USD0.12/M (2026-08-12), reranking USD0.05/M (2026-09-04).
These are retained assumptions, **not freshly verified prices**; no cache savings.

Per-item maxima: 4 planner calls, 4 interpretation calls, 6 family-review calls,
1 selector, 6 generation calls and 6 source-review calls. Source review and
entailment are the **same call**, not counted twice. The generation/review pair
is sequential on the existing client, with 3 transport attempts inside up to 2
validation attempts; SDK retries remain zero. Source review output ceiling is
17,408, generation 4,096, planner 25,000, checker/family/selector 4,096.
Selector input is capped at30,000. Other LLM inputs reserve the whole400,000-token
model context separately from output, deliberately conservative.

Retrieval reserves 10 search units (4 ordinary, 3 discovery, 3 possible distinct
version alternatives), 2 enclosing attempts, 4 embedding and 3 reranking attempts
per unit. Some maxima cannot co-occur. Query bound8000 Unicode characters is
reserved as32,000 UTF-8 bytes/tokens; reranking reserves40 candidates and repeats
the full query for every candidate, with measured maximum chunk size2798 bytes.
Local sparse/Qdrant is not counted as another paid provider call.

Subtotal **USD259.71308**; uncertainty allowance **USD64.92827** (25%, exceeding
the USD10 floor) covers unpriced/local costs, rate uncertainty and unmetered usage
provisionally. Reservation **USD324.64135**; ceiling proposal
`max(50, 2 × reservation)` = **USD649.28270**. Operational rounding upward gives
**USD324.65 reserved / USD649.29 ceiling**. Unknown costs are not zero; this is a
loose bound, not expected spend. Revalidate corpus/limits/rates before approval.
Provider-free sizing gives no guarantee that a maximal receipt completes inside
the unchanged120-second provider or480-second enclosing timeout.

No provider calls or live authorisations occurred. No main merge, Block1 closure
or readiness claim. Publication is evidence/harness/this plan note only.
