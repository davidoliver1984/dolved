# R01P checkpoint handoff — 2026-09-06

The unchanged v6 production slice completed all eight questions in 0020 with their targeted behaviours supported, following the 0019 targeted pass. This supports continued investment in the existing production services, not general reliability or Phase 28 certification. The two-live-batch correction cycle is finished; no additional run is started.

## Evidence and example

Question 01 now answers both the form rename and the employment-policy lawful bases with verified citations. Question 06 preserves the missing policy-history limitation; 08 preserves the foreign-record limitation. June ordering is answered correctly. See [findings](diagnostic-findings.md) for every case, failed predecessors and immutable hashes.

0020: 50 physical attempts, zero retries, 230,686 input / 17,059 output tokens, 267.8010 seconds, estimated $0.07963724. These are configured-rate estimates, not invoices. Code remains uncommitted on planning HEAD `f1c7e69f3798558ddeef3e1f43aa6df2d0dacc9a`.

## Residual acceptance

- Limitation-first presentation remains unmet. `ChatWorkspace.tsx` visibly renders `unsupported_aspects` below answer parts in muted text. It is not hidden metadata, but neither this nor generated prose consistently meets the accepted first-visible-limitation requirement. Preserve under R04.2/R04.5; no silent waiver or visual sign-off.
- No clarification/reply pair was executed. New-domain paraphrases were fake-transport probes, not live comprehension evidence.
- Ownership checks prevent cross-need citation borrowing, not every semantic error. Low reasoning does not guarantee no truncation.
- Repeated development questions are not held-out evidence. Full148 and pilot-readiness gates remain untouched.

## Next implementation boundary

Reuse the needs schema, catalogue, per-need eligibility, production retrieval and generation—do not rebuild them. R02.1/.2/.3 still need broader input/counterexample/date coverage. R02.4/.5 need unfamiliar or ambiguous registry references followed by a real free-text clarification reply, including cross-conversation/workspace negatives. R02.6 consolidates the retirement/compatibility checks.

The next useful proof is provider-free clarification/reply coverage, not another paid evaluation. Before declaring the slice fully accepted, explicitly resolve the residual presentation requirement and expansion disposition. David's encouragement to continue is not an implicit waiver of that requirement.

Provisional incremental planning allowance: R02 1–2 engineering days; R03 1–2; R04 0.5–1.5; R05 1–2; R06 0.5–1; R07 0.5–1 only if its gate passes. Total 4.5–9.5, excluding new defects, review waits and R08 closure. This assumes repository familiarity and reuse; actual working hours were not instrumented. It is not a delivery promise. Re-estimate at the next concrete proof boundary.

R01P remains in progress pending remaining acceptance/handoff disposition. No session or phase is closed. No application change, provider/AWS call, commit or push occurred during this handoff; unrelated changes remain untouched.
