# Current-policy pilot — Stop 3a bounded presentation corrections

## Boundary and publication

Reviewed parent: `be2d93b6770086272e452558c95980f20866de74`, on `codex/current-policy-pilot-20261116` in the separate pilot worktree. Stop 3a changes presentation/completion state and the controlled browser runner only. No planner/generation prompts, models, provider retries, retrieval, authority, tenancy, schema, migrations or answer/citation contracts changed. The profile remains default-off outside disposable tests. No provider calls, sealed/reviewer material, live authorisation, push or main merge. The original checkout and unrelated bytecode files are preserved.

Evidence package: `/Users/davidoliver/Desktop/dolved-experiments/current-policy-pilot-20261116/implementation/stop-3a/approved-corrections-01/`. Its `source-identities.json` records the final local commit and source/runner hashes; `checksums.sha256` covers the package. Earlier Stop 3 evidence is not rewritten. No parallel Journey is created.

## Corrections

- For `saved-message-v1`, omit the outcome badge, including legacy `insufficient evidence`. Render the existing canonical saved text and citations without changing their meaning or backend outcomes. Other presentations retain their badge and existing rendering.
- A saved completed run settles the UI only when its matching assistant message exists. Pending/progress/provisional state clears, the completion announcement is set, and a diagnostic completion-path attribute distinguishes SSE, initial saved-state completion and persisted recovery.
- Actual Next routing exposed a lifecycle defect: navigating before the submission receipt could remount into a pending conversation without observing its run, leaving stale or missing completion state. Navigation now follows the submission receipt; a remounted current-policy conversation subscribes to its existing pending run, never submits again. A stream-error persisted read can settle a completed run; later progress cannot overwrite that settled state. This is a bounded completion-state dependency, not a backend or citation-contract change.
- Browser capture waits for the correct run ID, exact saved answer, ordered citation UUIDs, cleared pending/progress state and the correct completion announcement. Backend completion alone is insufficient. No DOM manipulation or forced clearing is used.

## Deadline

Preflight supplies the existing backend maximum, currently **900 seconds**. Immediately before the one Enter submission, the runner records `submitted_at_ms` and computes:

`min(submitted_at_ms + run_timeout_seconds * 1000 + 15_000, overall_deadline_ms)`.

The 15 seconds is a fixed delivery/render allowance. The pure helper also supports a supplied authoritative run deadline, but this API has no such field; the actual runner uses the original submission and configured duration. Setup is bounded by 120 seconds and the same overall deadline. Polling, reconnection, route recovery and reload do not reset the completion bound. No automatic resubmission exists.

Tests cover the last valid instant, exact expiry, past expiry, overall-cap precedence, authoritative-deadline calculation, absent duration, and incomplete/wrong-run presentation. A controlled actual-browser overall-deadline expiry retained a `generating` backend status, DOM, events and original timing, then halted. Its backend later completed once; the harness did not resubmit or turn that into a passing capture.

## Actual application proof

Installed **Next 16.2.11** served the real `/login`, workspace and conversation routes, layouts, authentication, API services and ChatWorkspace. This is not the earlier Vite shell. The disposable Laravel HTTP kernel executed real orchestration and persistence with existing backend fixtures at the HTTP-provider boundary. Synthetic delays used one-shot orchestration processes, not production/evaluation workers. No real provider credentials or network calls were available.

The approved internal/no-egress network held disposable API/AI/web/Qdrant/PostgreSQL services. PostgreSQL 18.4 used tmpfs and no published database port; only existing migrations initialised synthetic data. Production role separation was not exercised. The approved engineering corpus fixture was mounted read-only at SHA-256 `aa4e12f104c5ee78fe96b5909061a33ef6edea1fbad6fe46f025aa9aa8815d8a`. Installed Chromium used empty temporary profiles and loopback-only routes through the established raw test relay. Successful browser records contain no non-loopback page requests. No package, browser or framework was downloaded. Next's external font fetch could not succeed in the no-egress environment; fallback fonts were used.

| Controlled scenario | Observed completion | Result |
| --- | --- | --- |
| History decline | persisted state | Exact canonical decline once; no contradictory badge; reload parity |
| Mixed answer with real unresolved-location disclosure | SSE | Real resolver disclosure once, one ordered citation, cleared progress and reload parity |
| Capacity outcome | persisted state | Exact capacity disclosure once; no contradictory badge; reload parity |
| Generation failure | persisted state | Exact safe saved failure text once; no contradictory badge; reload parity |
| 40-second fake generation delay | SSE, 65.621 seconds from submission to settled capture | Beyond former 30-second browser wait; exact text/citation parity |
| Missed final event, 90-second fake delay | persisted recovery, 116.584 seconds (empty credentialed stream) and 116.414 seconds (non-empty comment stream) | No final event required; saved state settles the application; exact reload parity |
| Short overall deadline | halted while backend `generating` | Timing/backend/DOM/events retained; no resubmit |

`browser-summary.json` gives per-run timing, IDs, network results, submission counts, generation calls, saved-answer counts and completion-event counts. Successful scenarios each have one message submission, one user message, one assistant message, one saved answer and one completion event. History decline has zero generation calls; the answer-attempt scenarios have one. Screenshots and full completion/reload DOM are retained. Text is compared without normalisation; citation UUID order is compared exactly. The location screenshot was visually inspected with its source card expanded.

The first short missed-event scenario settled during route loading and is not claimed as reconnect-path coverage. Longer fixture iterations are preserved: empty synthetic responses without the real credentialed CORS headers modelled a fatal stream error and expired. Adding the real loopback CORS boundary allowed genuine reconnect/persisted recovery. The final non-empty SSE-comment fixture also passed, checking a stream ending without a final event without sending a message to the application or changing its state. Individual directories preserve their actual results; failed attempts are not overwritten.

## Verification and limitations

- Focused ChatWorkspace: **16 passed**, including three badge cases, immediate saved completion, missed-event recovery with late-progress rejection, pending-run remount and deferred navigation. Existing legacy/citation tests remain intact.
- Deadline/settled-capture module: **3 passed**. Governed-runner tests: **8 passed**. An initial runner-test command used the wrong Python import path; the error is recorded, then the same tests passed with the correct module path. No assertion was relaxed.
- Full suites compared against retained Stop 3 JUnit: AI **144 failure identities unchanged** (1,570 tests; 1,422 passed, four skipped); Laravel **63 unchanged** (nine assertion failures and 54 errors in 995 cases, with warnings/skips retained); web **one unchanged** failure (`branding.test.ts`, Git metadata unavailable inside the isolated mount), **161 passed**. Exact old/new lists, with empty added/resolved sets, are in `verification/final-failure-identities.json`.
- Repository lint/format/type/static checks are retained, not called green: two existing PHP style issues; three existing Python import-order issues; eight existing MyPy errors in four test files. Web lint passes. Running actual Next generated an additional type-check surface: `.next/dev/types/app/login/page.ts(37,29)` rejects the unchanged login page's optional `Props` signature. Parent/current login source hashes are identical. This is newly exposed by actual Next, not silently fixed or hidden; production build/type readiness remains blocked by it.
- Full-suite missing-model, restricted-fixture/service and Git-metadata failures remain explicit in raw JUnit. No missing asset was downloaded or forbidden data mounted. This is controlled integration evidence, not provider quality, deployment, role-separation or pilot-readiness evidence.
- Earlier setup failures are preserved: stale fixture clock/session cookies, cookie-name mismatch against Next's existing allowlist, SSR-before-hydration submission timing, and ambiguous selection of the separate knowledge-readiness `role=status` node. The runner now waits for real hydration and targets the chat's own announcement; it does not rewrite the DOM.

All five disposable containers, their internal network, tmpfs database/index storage, temporary browser profiles and the owned loopback relay were removed/stopped. The temporary PostgreSQL credential file was removed. No persistent volumes were created; no pre-existing development/evaluation resources were removed. Cleanup receipts and final identities are recorded in the external package. Stop for bounded Stop 3a re-review. No live development smoke or sealed confirmation has been executed or authorised by this work.
