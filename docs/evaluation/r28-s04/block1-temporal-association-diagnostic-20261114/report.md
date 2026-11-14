# Temporal association correction and production-path diagnostic — 2026-11-14

**In progress; Block 1 HOLD. Hold branch only; no readiness claim.**
Starting commit `02470b24a23ed3001faebe41d9cf94c9c4818180`.

## Reproduction and correction

Before editing, controlled two-need/same-version/shared-paragraph reproductions
returned Python `ValueError: temporal answer-use coverage mismatch` and PHP
`GenerationException: Temporal answer-use coverage is invalid.` The old expected
map overwrites a need at the same part index and forbids repeated indices.
Additionally, whole-paragraph citation ownership prevents independently owned
evidence handles from supporting their respective associations. This confirms a
structural defect, not the contents of item 29's unavailable rejected drafts.

Generation v5 makes each `(part_index, need_id)` explicit, with its own
`evidence_ids`. Every association checks its own grounded quote, target, family,
version, full interval and evidence ownership; the union accounts for all cited
handles. Missing, duplicate, borrowed, unassigned or invalid temporal associations
fail closed. Non-temporal ownership and single-need behaviour remain intact.
The existing entailment call receives all associations; no extra provider stage
or retry was added. PHP and Python versions advance together, including generation
profile/persistence checks. Existing JSON persistence carries v2 association
receipts; rendering displays every association. Historical v1 receipts and the
Python v4 reader remain available without rewriting historical evidence.

## Evaluation-only capture

The authenticated planner/generation routes establish a request-local capture
scope only when explicitly enabled for workspace
`5323cd6b-8b7a-40f9-98b0-9413c75f643e`. The configured destination must be under the
operator-mounted `/evaluation-evidence`; no caller supplies a path. Files use
exclusive creation, bounded allowlisted data and redaction, not raw provider
bodies, headers or arbitrary exception text. Nothing is logged or persisted in
the application database. Other workspaces, disabled settings, outside paths and
symlinks cannot capture. I/O failure does not change the application decision;
the diagnostic preflight and reconciliation must detect missing evidence.

Rejected planner records include original proposed fields and the actual
post-normalisation validator input where available, closed validation details,
and the actual calendar-notation conversion count. Accepted planner records retain
the count, not a redundant model body. Rejected generation records include bounded
parts/temporal uses and the exact allowlisted invariant when available. Missing
parsed fields are explicitly unavailable, never inferred. No feedback or validator
condition changes follow from capture.

## Verification and execution boundary

Focused and full-suite JUnit/logs are retained under `provider-free/` (lossless gzip).
Final AI: **1,450 tests, 1,408 passed, 38 failed, 4 skipped**. Final PHP: **930 tests,
2 failures, 9 skipped, 5,652 assertions**. Exact retained failure identities, zero
new and zero resolved. The historically intermittent inode-substitution test
reproduced here; it is not claimed fixed. Focused AI: **76 passed**; copied harness
stop tests: **4 passed**. Scoped Ruff/format/Mypy and PHP style checks pass.
Initial new-fixture failures (an invalid test handle, a fake Eloquent relation,
PHP fixture bracket, and dependency-override default parameters) were corrected
without changing assertions or application outcomes. Initial Laravel runs lacked
test-process memory headroom, stateful test origins or the retained versioned test
bucket; their logs/JUnit are preserved separately. Final tests use SQLite memory,
the existing isolated `dolved-r28-s03-c1-period-tests-20261114` bucket and 512 MB
test-process memory. No application memory/provider setting changed.
Diagnostic results will be appended after execution.
The diagnostic selection is exactly **G3, 17, 25**, copied byte-for-byte from
retained question-only inputs. It is a new observation, never a replacement for
earlier checkpoint verdicts. No other question or reviewer material is needed.

Items **19 and 28 remain unresolved Block 1 findings**, with implementation owned
by Block 2. This package does not mark them passed or close Block 1.
