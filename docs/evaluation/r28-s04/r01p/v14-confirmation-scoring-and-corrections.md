# Fourteenth independent confirmation (v14): scoring and corrections

2026-11-07. R28-S04 / R01P remain **in progress**. Written by Claude (Sonnet 5), independent reviewer, as a scoring record after execution — not an execution handoff and not a correctness benchmark.

## What this covers

22 freshly-authored questions (`q01`–`q22`), set ID `R28-INDEPENDENT-CONFIRMATION-20261106-V14`, built from 14 ordinary families independently confirmed (via the live roster cross-referenced against every prior round's usage this session) to have never been cited or deliberately tested anywhere in this R01P arc. Questions-only file: `docs/evaluation/r28-s04/r01p/confirmation-22-v14-questions-ONLY.json`, SHA-256 `23af9d53de48c8b351ae0aa2aa1cc7cc57790cd5835d86bcc0f5e8c61e4f95b9`. Private answer key held back throughout. Dispatched once via `docs/evaluation/r28-s04/r01p/confirmation-v14-evidence-20261106/` against HEAD `f8295d11b4d75afe436b19de863a439b7c3b4a94`; checksums, questions-file byte-identity and runtime-source aggregate independently re-verified.

## Result: 22 of 22 clean — no findings

Ten single-fact questions (PEEP review cycle, exit-interview process and its FAQ, risk-assessment review cycle, phishing verification channel and its no-penalty FAQ, compliments logging, EAP/mental-health-first-aider confidentiality, supplier due-diligence threshold) all matched the real source text exactly, including the specific £36m threshold figure in q12.

Three multi-version families (gas safety, pest control, lone worker devices) were scored with particular care for whether the system reported the *current*, most-recent state rather than an earlier version's: all three passed. q13/q20 correctly identified the 24-hour Estates Helpdesk as the current internal gas-emergency escalation contact, explicitly noting it replaced the earlier Facilities Lead line. q15/q19 correctly gave the current fortnightly kitchen-inspection frequency, not the superseded monthly figure. q16 correctly identified the same-day rodent-logging requirement introduced in the current version. q17/q19 correctly identified that lone-worker device eligibility now extends beyond community visits to single-staffed night shifts. q18 went further than required: it correctly distinguished two separate, related policies — the Lone Worker Devices Policy's device-escalation timeline (15/30 minutes) and the separate Willow Bank Lone Working Policy's welfare-call timeline (also 15/30 minutes but a different mechanism) — rather than conflating them, which the question's generic phrasing could easily have invited.

The deliberate no-change trap (q14, legionella control procedure — "sections reordered... no requirement changed") passed cleanly, both standalone and inside its compound pairing (q20).

Both negative controls were handled safely. q07 (a single-dated, now-past bank-holiday closure notice — a new temporal shape not tried before this round) correctly scoped the closure to its specific date without implying an ongoing closure. q09 (an unadopted car-parking proposal) correctly gave `insufficient_evidence`. In its compound form (q21), the same car-parking need was correctly flagged via the structured `unsupported_aspects` field — *"No supplied evidence records a decision or guidance that Central Support Office proceeded with reserving car parking spaces specifically for pool vehicles"* — while the closure half of the same compound question was answered correctly; worth noting explicitly since checking `displayed_text` alone is not sufficient to score a `qualified` outcome correctly, the structured fields must be checked too.

The l23 location-extraction generalisation check (q22, a new document title — "Phishing Awareness Guidance" — never tested against the fix before) resolved normally with no regression.

## Final tally

22 of 22 clean. This is the first fully clean round with zero findings since round 10 — a meaningful data point in itself, given rounds 11, 12 and 13 each surfaced at least one genuine thing to investigate.

## Evidence roots

- `docs/evaluation/r28-s04/r01p/confirmation-v14-evidence-20261106/` — full 22-case run.

No further correction, paid run, new questions or R02 work follows this report automatically.
