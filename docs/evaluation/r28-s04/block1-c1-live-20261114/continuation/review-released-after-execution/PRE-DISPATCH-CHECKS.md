# C1 confirmation: reviewer pre-dispatch checks (run only after C1 is frozen)

Seal retained. Originals: questions-only.json e1a54d05…a9659, expectations.json e518c6a0…5e5bd, amendment-1-exposure.json ccc36505…26e6b8.

1. Confirm C1 freeze: exact pushed hold-branch SHA and runtime aggregate recorded by Codex. Checks below are run against that state.
2. Source text, all five: read the ingested authorised chunks (primary workspace, established primary actor) for both versions of each family. F2 and F3 are PDFs whose text has not yet been read; confirm every quoted sentence in expectations.json verbatim, plus any material condition (F2 emergency 72-hour route; F3 "before self-administration begins"; F4 v1 pharmacy/manufacturer advice; F5 "should"/"must").
3. Timelines, all five: invoke the production DocumentAuthorityTimeline with the production date constructor (`createFromFormat('!Y-m-d')`, UTC) after the production membership query. Required: F1 2026-03-10 → v1; F2 current → v2; F3 2026-05-20 → v1 and 2026-06-10 → v2; F4 the whole of November 2025 → v1 only (period resolver must not clarify) and current → v2; F5 January 2026 → v1 and current → v2. Confirm indexed/approved, no withdrawal, universal applicability, active corpus.
4. Catalogue binding: confirm each named title matches the authorised catalogue title exactly, so the planner can bind it.
5. Any defect: record in amendment-2 with reason and new hashes, before execution. Never edit originals or amendment-1.
6. Hand Codex questions-only.json only, after these checks pass.
