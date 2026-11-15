# Bounded production wiring — 2026-11-15

**Provider-free only; STOP for reviewer review.** No operational authorisation
created/consumed, no provider calls, no application edits or deployment. Block1
HOLD; main unchanged. Earlier Phase A called the mechanical preflight complete
too broadly: its checkpoint was a callback-based executor without production
callback wiring. This addition closes that specific tooling omission.

## Boundary

`runner.py` calls the frozen `execute_released()` adapter, which calls the frozen
`checkpoint.execute()`. Neither is changed. `dispatch.php`, `preflight.php`,
`verify_persistence.php`, `check_record.py` and `C1Capture.php` remain byte-identical
under the original harness manifest. No application or model changes.

The existing C2 `accounting.py`/`run_once.sh` pattern is retained: Docker-only PHP
dispatch, per-item verification, exclusive starts/consumption, hash-linked capture
ledger and receipt accounting. A small fixed-operation pipe connects containerised
Python callbacks to the host shell's Docker commands (VERIFY/DISPATCH/PERSIST,
safe numeric/ASCII IDs only). It does not execute arbitrary received commands,
mount the Docker socket in Python, or require host Python. No services are started
by the wrapper; only isolated `--no-deps` utility containers are used.

### verify

Before consumption and between items: source manifest, original harness and
dependencies, new executable manifest, released-input hash, neutral reviewer hash,
authority pins, git branch/commit, unchanged main and baseline ancestry; isolated
project, mounted API/AI hashes, real database/workspace/generation and actor/no-site
context. Running environment comparison reuses the private verifier; no full
environment or credential output. Override permissions/hash checked, mutable
capture files excluded. Copied PHP executable files are hash-verified before each
item. Unknown verification failures stop, never resume automatically.

### dispatch and capture

Only the existing `dispatch.php` executes a question. One new authenticated
persisted conversation, exact released user turn, existing production retry rules.
The unchanged persistence verifier runs immediately afterward. Runner checks exact
input, ledger hash chain, persisted workspace/actor/turn/run/question, saved versus
reloaded representations individually and temporal reload integrity. Differences
between saved text and rendered text are recorded, not normalised or judged.

Accepted/rejected planner sidecars are required when the serving receipt indicates
they should exist. Generation capture is **rejection-only**: a clean success must
not be rejected because no rejection sidecar exists. Reported generation contract
rejections require their sidecar. Every found sidecar must match request/workspace.
Transport failures can lack a sidecar; the unchanged typed gate decides whether
to halt. Raw internal source-review JSON remains unavailable. Unrestricted PHP
stderr is not retained; exit status, service diagnostics and the ledger remain.

### budget_check

Approved reservation **USD136.737303**; hard stop **USD273.474606**, unchanged.
Per-item maximum comes directly from frozen reservation.json:
`136.73730275 / 34 = USD4.021685375`, including the uncertainty allowance.
Before dispatch the ledger reserves this full amount. Known/retained-rate service
receipt subtotal is kept separately, never summed again with parent run aggregates.
Supplemental observer rerank usage is not counted if a rerank service receipt
already exists. There is no claim of complete invoice/attempt coverage; the full
remaining per-item reserve is retained even after a terminal outcome. Missing
receipts therefore cannot manufacture headroom. More observed cost than the
reserved maximum is saved and halts. Total committed liability plus the next
maximum is checked against the hard stop **before** dispatch. It cannot silently
raise the reservation or spending stop.

Atomic fsync/replace accounting is persisted after reservation and settlement.
An interrupted dispatch leaves its reserved liability and start marker intact.
Restart loads but refuses the prior run state; no continuation/re-dispatch path.
The first pre-consumption budget check and first pre-item check share one pending
reservation, not two. Tests include over-cap refusal before consumption/dispatch.

## Provider-free evidence

- First wiring pass:10 tests +3 subtests passed (`provider-free.txt/xml`).
- Final: **30 tests +10 subtests passed**, including12 new wiring tests and the18
  unchanged checkpoint/stop/release-adapter tests (`provider-free-final.txt/xml`).
- Covers all34 in file order once; prior start/consumption/record refusal; budget
  pre-dispatch refusal; safe model rejection versus unknown HTTP halt; drift
  between items; interrupted/settled accounting preservation; missing receipts;
  no parent double-counting; neutral-confirmation mismatch; generation sidecar rules.
- Substitute host/provider operations only. The real frozen executor, runner
  verification, accounting and persistence/capture checks run in tests. Temporary
  fixture consumption markers are not operational authorisations. No real PHP
  dispatch was exercised and no live readiness inference follows.
- `bash -n run_once.sh` passed. Original harness and runtime SHA manifests checked
  unchanged. Full application suites not run (tooling-only scope).
- `git diff --check` reports only four trailing spaces emitted by Docker Compose
  in the two raw test-output files; these are preserved verbatim as evidence.

## Freeze and next boundary

`identity.json`, `executable.sha256` and `checksums.sha256` bind this addition and
its evidence. Original harness aggregate remains
`607aa1d76a36d97a0db83d77f9f6c7a603d89d39bca429428a1628d856e1c79b`.
Application runtime remains
`b73d054d9492e909cfaeadb642544950cfe940de10bfcbe12b388b089997b901`.
The neutral reviewer confirmation is published unchanged at its permitted path,
SHA256 `75f564ef5b8076bc9b6f7cfe67b647176a2f532704812d5cd9933419ea37fbce`.

After reviewer approval only, the supplied authority must pin the exact published
runner commit, new executable hash, existing dependencies hash, baseline commit,
input/normalised input hashes, reviewer confirmation, context and approved budget.
Runner has **no authority builder**. Its only production entry is
`bash docs/evaluation/r28-s04/decision-run-preflight-20261115/runner/run_once.sh`;
it requires separately supplied `backend-run/authorization.json` and refuses any
prior consumption/start/accounting/items state. This command has NOT been run.

Known presentation defects remain pilot blockers. No grading, PhaseB execution,
main merge, Block1 closure or readiness claim. Stop for review of this runner diff.
