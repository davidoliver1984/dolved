# Current-policy pilot — Stop 1 implementation

David approved the baseline dependency amendment: only the tracked V2 engineering corpus file may be separately mounted read-only; a disposable Qdrant may run on an internal no-egress network with ephemeral storage. The first baseline remains preserved. No reviewer or sealed files were inspected and no provider calls were made.

## Delivered boundary

Added `current-policy-needs-v1`, signed request envelope 3, with Python models/provider schema and PHP revalidation. Existing envelope 2/v4 execution remains intact. Requested information is distinct from authority. Mixed needs have bounded grounded partitions; deterministic checks reject duplicates, overlap, ungrounded/ambiguous occurrences, historical context contamination and loss of current restrictions. Same existing planner/checker calls and two-attempt ceiling; scope annotation failure receives a closed retry code. Base grounding, binding and reference checks precede scope recovery. On the second attempt a malformed annotation is quarantined, and dependent needs cannot execute; independent valid current needs survive.

Laravel projects only admitted current clauses, keeps typed decline/ambiguity/interpretation-failure dispositions and fixed wording. Current-only needs cannot retain historical authority. Event records and background dates are covered by general scope instructions and controlled fixtures, not special-case question rules. A citation-grounded semantic checker remains fallible; tests establish contracts and routing, not real-model correctness.

Server-only configuration selects the new run pin; deployment drift fails closed. Profile is default-off and deliberately refuses conversation execution until Stop 2 implements the product retrieval/generation path. No legacy-path fallback for an incomplete pilot. Added Proposed ADR-0040, adapted from the external draft to the approved minimum. No new storage, migration, selector, reviewer call or ownership gate.

## Verification and limits

Shared synthetic fixture and a Python-produced response are revalidated by PHP through schema, lineage and current retrieval projection. Focused Python selection: 123 passed (19 new scope tests plus legacy planner/retry checks). Focused PHP: eight tests, 19 assertions, no failures; all report the existing missing-local-env warning. Changed-file Ruff/Pint checks pass. MyPy retains the same eight errors in four pre-existing test files.

Ran `make format-check lint typecheck test` targets separately on both trees. Aggregate targets short-circuit on existing failures; API/AI full suites were also executed directly to retain complete JUnit. Approved baseline: AI 144 failed / 1,390 passed / four skipped; Laravel 63 failures/errors / 125 passes / 764 warnings. Final tree: AI 144 failed / 1,409 passed / four skipped; Laravel 63 failures/errors / 125 passes / 772 warnings. Final failed-test identity sets are identical; no resolved failures claimed. An intermediate run additionally failed the untouched governance-email HTML test when Faker generated an apostrophe-bearing company name; its raw failure and comparison remain preserved, not erased by the final match.

The corpus hash is `aa4e12f104c5ee78fe96b5909061a33ef6edea1fbad6fe46f025aa9aa8815d8a`. Qdrant tag v1.18.1 resolved to repository digest `sha256:45f8e3ddc2570a4d029877e1b5ec1045c19b3852b4e22a55c7f43b05aea0ca89`, image ID `sha256:934474a62b86e4dbe4bad07fccee7495fd968eef36c88ab69705b122f487a2e8`. Test containers were `dolved-current-policy-checks-approved-{api,ai,web,qdrant}-1`; API used SQLite `:memory:`. API/AI/Qdrant used the internal-only test network; web had networking disabled. No evaluation database, private override, provider credential mount or existing Qdrant volume was used. Qdrant storage/snapshots were tmpfs. All approved-test containers/network were removed afterward.

Unavailable checks are listed individually with missing-path reasons in the evidence comparison: 98 AI and eight API failure records have explicit missing external-input/connectivity evidence. Other failures remain failures, not automatically excused. Web branding cannot inspect git metadata under the amended no-other-external-files mount rule. No external prerequisites were silently added, and no assertions were weakened. This is not a green-suite or pilot-readiness claim.

## Review stop

Evidence is published below `current-policy-pilot-20261116/implementation/stop-1/approved-implementation-01/`, preserving earlier baseline files. Scope contract and projection are ready for bounded review. Stop 2 retrieval/pooling/generation/persistence and Stop 3 presentation/readiness are not started. Actual conversation execution and live evaluation remain unauthorised. IMPLEMENTATION_GUIDE/tasks updates are reserved for Stop 3; Journey untouched. Original hold checkout/main unchanged; local pilot-branch commit only, no push.
