# Fifteenth independent confirmation (v15): scoring and corrections

2026-11-08. R28-S04 / R01P remain **in progress**. Written by Claude (Sonnet 5), independent reviewer, as a scoring record after execution — not an execution handoff and not a correctness benchmark.

## What this covers

22 freshly-authored questions (`r01`–`r22`), set ID `R28-INDEPENDENT-CONFIRMATION-20261106-V15`, built from 14 ordinary families confirmed to have never been cited or deliberately tested anywhere in this R01P arc. Questions-only file: `docs/evaluation/r28-s04/r01p/confirmation-22-v15-questions-ONLY.json`, SHA-256 `726ee546161436315121536f5e6818a150edd94f488456271f2b9f4f86e4fd52`. Private answer key held back throughout. Dispatched once via `docs/evaluation/r28-s04/r01p/confirmation-v15-evidence-20261107/` against HEAD `2a3421d4c02b55293b4f705abf8006e3a07b5406`; checksums, questions-file byte-identity and runtime-source aggregate independently re-verified.

## Result: 18 of 22 clean on first dispatch; three distinct findings, all now resolved or formally documented

Ten single-fact and comparison questions matched the real source text exactly, including a genuinely new test shape this round: **site-specific document resolution** (r03/r04), an inverse-check pair against two separate fire-evacuation documents for Riverside House and Oakfield Lodge, confirming the system resolves a named site to the correct one of two similarly-themed documents rather than a generic or swapped answer. Six real version-to-version changes (key-holder region extension, mobile-device BYOD withdrawal for health-record roles, medication-ordering's move to an electronic urgent-flag system, overtime's TOIL introduction and bank-holiday rate, and fridge-breakdown's added pharmacy-call requirement) were all correctly identified against their current state. A subtle wording-tightening change (r13, ladder/stepladder substitution now explicitly prohibited) and a rename-only trap (r14, ROPA form's "Legal basis" relabelled "Lawful basis" with no substantive change) both passed cleanly. r04 in particular handled a nuance better than the answer key's own bar required, correctly flagging as unsupported a detail the site-specific document genuinely doesn't state, rather than over- or under-claiming it.

Three findings surfaced, all clustered around "Key Holder Procedure"/"North West Region":

1. **r08 and r17 — family-binding judgment variance, folded into the existing accepted limitation.** Both used an informal paraphrase ("the key holder arrangement") rather than the document's title. Diagnosed and quantified for the first time via an authorised 10-call repeat-sampling check: the phrasing bound to the correct family in 2 of 5 (r08) and 1 of 5 (r17) repeats. This is the same mechanism already documented in ADR-0039's original 2026-09-08 acceptance (planner family-binding judgment for `scope_enquiry`) — now measured with real repeat data rather than a single-sample comparison, not a new discovery. Folded into that existing ADR-0039 entry as a dated follow-up rather than treated as new.

2. **r16 — a new-position instance of the l23-class bug, fixed as a side effect of finding 3's correction.** "Does the fridge breakdown procedure still only need the on-call manager called" captured "fridge breakdown procedure" (a mid-sentence subject reference to a document, not a trailing "under X policy" qualifier) as a named-applicability/location candidate, blocking the whole compound turn including an unrelated, independently-fine need. The planner-instruction fix applied for finding 3 generalised to this case too; confirmed fixed directly in the raw trace after the fix (`applicability.kind` now correctly `generic`, both needs of the compound resolve and answer).

3. **r17 and r19 — genuine, fixed defect: North West Region location matching.** Both correctly identified the right document and a genuine location word, then failed to resolve it — r17 used the bare "North West," r19 used "North West Region sites" with an incidental trailing word captured into the quote. Diagnosed to three contributing, independently-fixable causes (missing region alias, planner over-capturing a trailing word, and `scope_enquiry`'s exact-reference mode skipping the leniency ordinary retrieval already has, including a regex that only handled singular trailing-qualifier forms). Fixed and committed as `82a1b25bcb0f14b93d729ce871c2df4c70f42493`, independently verified: r19 confirmed resolving correctly in the raw trace; r16 (finding 2) confirmed fixed as a beneficial side effect; r17's continued clarification after the fix confirmed, via the quantified sampling in finding 1, to be the separate binding-variance issue recurring, not a regression of this fix.

## Final tally

22 of 22 cases now resolved or correctly accounted for. One genuine defect found and fixed (North West Region matching, commit `82a1b25`), one additional case fixed as a side effect of that same correction (r16), and one finding (r08/r17's binding variance) folded into an already-accepted ADR-0039 limitation with real quantified data rather than treated as new. No open, uncharacterised issues remain from this round.

## Commits from this investigation

| Commit | Summary |
| --- | --- |
| `82a1b25bcb0f14b93d729ce871c2df4c70f42493` | Resolve North West Region location references |
| (ADR-0039 update) | Documents both the binding-variance follow-up and the location-matching fix |

## Evidence roots

- `docs/evaluation/r28-s04/r01p/confirmation-v15-evidence-20261107/` — full 22-case run.
- `docs/evaluation/r28-s04/r01p/north-west-location-original-diagnosis-20261107/` — original diagnosis.
- `docs/evaluation/r28-s04/r01p/north-west-location-regression-evidence-20261107/` — live regression after the fix.
- `docs/evaluation/r28-s04/r01p/r08-r17-binding-variance-evidence-20261107/` — quantified binding-variance sampling.

No further correction, paid run, new questions or R02 work follows this report automatically.
