# Current-policy pilot: Stop 2 backend

David conditionally accepted Stop 1 (`912dc8dd9661210f106c47e8ee9d771c4c5ae05c`) and ADR-0040 on 16 November 2026. Work remains in the separate `codex/current-policy-pilot-20261116` worktree, with no push, main merge or live activation.

Corrected non-admitted dispositions to identify independently grounded affected wording, including ambiguity, invalid scope and dependent quarantine. PHP now runs the established relative-calendar canonicalisation before the current-policy branch; tests exercise future actions and past events at a London month-boundary anchor without changing current authority or question wording.

Implemented the Stop 2 backend service and dedicated signed AI generation contract: current-only production eligibility/retrieval/reranking/threshold checks; first five candidates per admitted need; question-level physical deduplication; compact source context; one bounded generation request without selector, semantic reviewer or answer ownership gates; typed partial disclosures; transactional existing-message/answer/evidence persistence. Source declared effective dates and resolved authority windows are separately labelled. Capacity never silently truncates or reselects passages, and does not relabel an independently unavailable part.

The default-off profile still refuses the legacy conversation delivery route. Stop 3 must explicitly consume the saved final text/citation graph and integrate delivery. No API resource, SSE or web presentation was changed; no historical records were rewritten and no migration introduced.

Provider-free evidence and exact failure-identity comparisons are published under `~/Desktop/dolved-experiments/current-policy-pilot-20261116/implementation/stop-2/approved-implementation-01/`. All tests use the approved isolated containers, SQLite memory database, one tracked read-only engineering fixture, and disposable no-egress Qdrant. Existing baseline failures remain failures, not a green suite. The report maps blocked checks to relevant replacement coverage. No provider calls or semantic-quality claim.

Residual risks: planner scope/interpretation and generation support judgments remain model-dependent. A genuine current question containing a date may still be mislabelled historical, producing an honest interpretation failure; that is a quality failure. No live check is authorised here. The original checkout and its local Journey are preserved; this worktree has no Journey file to update. Stop for bounded backend review.
