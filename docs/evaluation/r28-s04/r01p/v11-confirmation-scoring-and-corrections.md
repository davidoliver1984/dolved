# Eleventh independent confirmation (v11): scoring and corrections

2026-11-06. R28-S04 / R01P remain **in progress**. Written by Claude (Sonnet 5), independent reviewer, as a scoring record after execution — not an execution handoff and not a correctness benchmark.

## What this covers

24 freshly-authored questions (`m01`–`m24`), set ID `R28-INDEPENDENT-CONFIRMATION-20261106-V11`, deliberately themed around two negative-control shapes — tenant isolation (8 foreign-tenant families) and non-current-authority (6 ordinary draft/superseded/retired/proposal families) — selected from the family-coverage reconciliation's unresolved list (`/private/tmp/dolved-r01p-family-coverage.IANAMV/`), minus three ordinary families and all six original injection-pack baits excluded on the reviewer's own conflicting (unprovable but specific) memory of prior use. Questions-only file: `docs/evaluation/r28-s04/r01p/confirmation-24-v11-questions-ONLY.json`, SHA-256 `abdb74aab4b5c0669464cede93f5474944a08bdc43f356ca56401d88ab1fc6f7`. Private answer key held back throughout. Dispatched once via `docs/evaluation/r28-s04/r01p/confirmation-v11-evidence-20261106/` against HEAD `4d1ffe58b506903ad2bc53748e846a85f7199088`; checksums, questions-file byte-identity and runtime-source aggregate independently re-verified, not taken from Codex's own report.

## Headline result: zero tenant-isolation leaks

Every one of the 24 cases — including 4 compound questions and 4 deliberately-tricky trailing "under/according to the [named] policy" phrasings — cited only Alderbridge Care Group families, verified directly from each case's `needs_final_evidence` document-family IDs. No Thornfield document, number, or reference ever surfaced, under harder stress (compound turns, trailing document-title phrasing, an explicit "under our own policy" ownership assertion in m24) than any prior round. Several cases were strong, substantive passes rather than safe non-answers: m01, m05, m06, m09, m10, m11, m16, m17 all found the real Alderbridge figure and explicitly contradicted the embedded foreign/stale canary.

## Findings and resolutions

- **m19 — genuine mechanical defect, fixed and committed.** `scope_record_unavailable` had no case in `EligibilityClarificationReason` and no renderer mapping, so it crashed to `internal_failure` with blank displayed text instead of degrading. Diagnosed precisely (`RetrieveWorkspaceEvidence.php:84-90`, `ControlledResponseRenderer.php:40-54`, `OrchestrateConversationRun.php:224`), fixed by routing it to a controlled no-answer instead of a clarification (the existing hardcoded clarification question was wrong anyway — it asked the user to re-identify information they'd already given). Committed as `da83e4166c33c6a70e516a071109d179676edee3`. Independently verified: checksums, aggregate, and raw case JSON for the live regression (m19 now `retrieval_no_answer`; m18 and three controls unchanged).
- **m18 — not a defect.** Shares m19's underlying authority state (the family's approved version was withdrawn 1 January 2025; the only other version is an unapproved draft), but reaches it through ordinary `retrieve`, which already degrades safely to a no-answer. Confirmed directly against the live DB by Codex; same accepted shape as the k08/apprenticeship-policy pattern.
- **m07 — genuine architectural gap, documented, not fixed in-session.** Investigated across three rounds: an initial misdiagnosis as an l02-class operation-selection bias, a failed narrow prompt-wording attempt (`bounded-evidence-needs-v18`, 5 of 6 live cases still misrouted, reverted uncommitted), and finally a correct diagnosis — the evidence-needs-plan contract has no way to represent a disputed fact value (a claimed number/name to check against evidence) separately from an applicability location (a site that gates eligibility), so any unresolved named reference blocks regardless of which role it's actually playing. Root-caused to `EvidenceNeedsPlan.php:73` and `EligibilityResolver.php:172`, both independently verified by the reviewer. "Brambleford Office" turned out to be the foreign tenant's own office name (from `ft.hr.grievance-procedure`), not an ambiguous Alderbridge site — the same canary shape as m01's embedded number, which answered cleanly while m07 blocked. Documented as a new ADR-0039 section, explicitly distinguished from the operation-selection judgment limitations (deterministic contract gap, not stochastic model variance), flagged for dedicated design work rather than pursued further this session. Committed as `4c1f37f` (ADR-0039 addendum).
- **m12 — reconfirms an already-known, unaddressed behavior.** One need's `clarification_required` (the Cinderhill House location, correctly unresolved since it's a genuine foreign site) blocked the independently-fine second need (the grievance question) in the same compound turn. Not new — already flagged as out of scope when l23 was fixed.

## Final tally

21 of 24 clean (including the two strong negative-control themes holding perfectly). m19 fixed and verified. m18 not a defect. m07 is a real, well-understood, deliberately-deferred architectural gap, not an open mystery. m12 is a known, already-flagged limitation recurring, not new information.

## Commits from this investigation

| Commit | Summary |
| --- | --- |
| `da83e4166c33c6a70e516a071109d179676edee3` | Return no answer for unavailable scope facts (m19 fix) |
| `4c1f37f3b963b7520a0a425efebb12dd86711f52` | Document ADR-0039 disputed-fact-value/applicability-location contract gap |

## Evidence roots

- `docs/evaluation/r28-s04/r01p/confirmation-v11-evidence-20261106/` — full 24-case run.
- `docs/evaluation/r28-s04/r01p/m19-noanswer-regression-evidence-20261106/` — m19 fix live regression.
- `docs/evaluation/r28-s04/r01p/m07-planner-diagnosis-evidence-20261106/` — 6 planner-only calls isolating the office-phrase trigger.
- `docs/evaluation/r28-s04/r01p/m07-office-regression-evidence-20261106/` — failed prompt-wording attempt, reverted.

No further correction, paid run, new questions or R02 work follows this report automatically.
