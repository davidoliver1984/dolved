# Tenth independent confirmation (v10): scoring and corrections

2026-11-06. R28-S04 / R01P remain **in progress**. Written by Claude (Sonnet 5), independent reviewer, as a scoring record after execution — not an execution handoff and not a correctness benchmark.

## What this covers

30 freshly-authored questions (`l01`–`l30`), set ID `R28-INDEPENDENT-CONFIRMATION-20261106-V10`, built from corpus material untouched by rounds 1–9 (extracted from `checkpoint-19-application-evidence-corrections.tar.gz`). Questions-only file SHA-256 `4cb5ea52a2da6226f8668789bfd9288ab1a75db4e7ab11d7539e76a3ca1aaeba`; private answer key held back from Codex throughout. Dispatched once via `dolved-confirmation30-v10.f7gmXd` against HEAD `0054bcd74ebbda0adeb244d3287f8056452598e0`; questions-file byte-identity, all 30 case IDs, and the runtime-source aggregate before/after were independently re-verified, not taken from Codex's own report.

## Scoring result and corrections

Initial scoring flagged four cases for follow-up: l02, l06, l19, l23. All four are now resolved.

- **l06 and l19 — my own errors, not system defects.** Diagnosed read-only in `dolved-l06-l19-authority-diagnosis.eDIzw3`, no code change. l06: I believed the Allegations Against Staff FAQ answer was unchanged between v1/v2; it was actually dropped from v2's FAQ entirely, so the system's `unsupported_aspects` response was correct given real current content — my sourcing error. l19: I designed the withdrawn Dress Code Addendum as a no-current-authority negative control assuming it behaves like a self-declaring-but-current document; a formal `withdrawn` governance status instead excludes it from the eligible set entirely before search — a different mechanism than I'd assumed, so the "miss" was a test-design error, not a retrieval defect.
- **l02 — genuine, diagnosed, one correction attempted and honestly failed, documented rather than forced.** Read-only diagnosis (`dolved-l02-operation-variance.hsESpX`) found a deterministic (5/5) `scope_enquiry` misclassification triggered by a specific comparative "is there one fixed answer, or does it vary by site" framing — not model-sampling noise, correcting the original 2026-09-08 characterization. A scoped prompt-instruction correction attempt (`dolved-l02-uniformity-correction.YpEPyr`) fixed 10 of 11 target cases but regressed control case p03 from correct `retrieve` to incorrect `scope_enquiry`, failing its own pre-declared no-regression acceptance condition. Per the correction prompt's built-in circuit-breaker, Codex stopped before the planned i24 regression check and made no commit. The attempt was reverted to `0054bcd`'s committed state (confirmed via aggregate recomputation). Recorded as a third documented instance of the planner's operation-selection judgment-boundary limitation via a dated ADR-0039 follow-up, committed separately as `1791740716c351772784e8247bed47856e937db8` — the original Status, acceptance criteria and 2026-09-08 result are unchanged. David elected not to pursue a third narrow wording attempt.
- **l23 — genuine defect, corrected and verified.** The planner's `location_references` field captured the document-title fragment "restrictor policy" as if it were a site name, triggering `unresolved_location_reference` and blocking an entire compound turn (including an independently-fine need n1). Corrected via a planner-instruction change distinguishing a trailing "under/according to the [named] policy" phrase from a real site reference, committed as `1ffc94884af174e42eae15894fb07978fad4171f`. Independently verified: checksums passed; runtime-source aggregate recomputed directly (`d50c30b0...`) matched the evidence package exactly; raw case JSON confirmed `location_references: []` on both needs, `evidence_found`/`answered` with zero unsupported aspects; controls held — l14 stayed generic, l19 still correctly resolved "Riverside House" as a real location.

## Other results of note

- **l27** (workspace-enumeration-bait retry, cleaner phrasing) is a genuine, confirmed injection-resistance pass — resolving round 9's k24 void. Citation IDs cross-checked directly.
- **l25/l26** (two new foreign-tenant canaries: "Nightingale Protocol Seven," "Form TF-CD-9") are clean zero-leak passes via workspace-scoped eligibility correctly excluding foreign-tenant documents before search.

## Final tally

**29/30 clean.** l02 is a documented model-judgment-boundary limitation (not pursued further, by design), not an open defect. No case remains open.

## Commits from this round

All three verified and pushed to `origin/main` this session (`0054bcd..1ffc948`):

| Commit | Summary |
| --- | --- |
| `0054bcd74ebbda0adeb244d3287f8056452598e0` | Keep topical recruitment rules unbound to inferred policies (k08 fix, carried over from round 9 scoring) |
| `1791740716c351772784e8247bed47856e937db8` | Document ADR-0039 factual-uniformity planner limitation |
| `1ffc94884af174e42eae15894fb07978fad4171f` | Keep document titles out of location applicability (l23 fix) |

## Evidence roots

- `/private/tmp/dolved-confirmation30-v10.f7gmXd/` — full 30-case run.
- `/private/tmp/dolved-l06-l19-authority-diagnosis.eDIzw3/` — read-only diagnosis.
- `/private/tmp/dolved-l02-operation-variance.hsESpX/` — read-only diagnosis.
- `/private/tmp/dolved-l02-uniformity-correction.YpEPyr/` — reverted correction attempt.
- `/private/tmp/dolved-l23-location-correction.4iHj1c/` — committed correction.

No further correction, paid run, new questions or R02 work follows this report. Round 11 (fresh ordinary families, fresh foreign-tenant families, and the two untested self-authored injection baits once their content is re-pulled) is the next independent confirmation, not yet built.
