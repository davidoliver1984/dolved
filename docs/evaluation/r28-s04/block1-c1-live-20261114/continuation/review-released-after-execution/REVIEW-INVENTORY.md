# Independent review — RELEASED AFTER EXECUTION

Run `R28-C1-LIVE-20261114-01`. Execution commit `44219e2`, runtime aggregate `4dde6707…b338`, released input `e1a54d05…a9659`.

The files in this directory were held outside the repository by the reviewer until all five items had completed. They were never available to the implementer or to execution. They are copied here byte-for-byte; `released-files.sha256` matches the reviewer's original hash records. This is a separate review inventory. The execution freeze, inputs, records, authorisation, ledgers and the parent `checksums.sha256` are unchanged.

| File | Role | SHA-256 |
|---|---|---|
| expectations.json | Frozen expectations, authored before any C1 output | `e518c6a0705ec642a78186dc519c9191a48e9d7c5de70b1b67c09d16fb55e5bd` |
| amendment-1-exposure.json | Exposure history; F1/F4 reclassified as exposed contrasts | `ccc36505993fe6b7c1ff18a31f152dcf536662f9d45cc262bf4b44d60a26e6b8` |
| amendment-2-pre-dispatch-verification.json (+ pre-dispatch-check.php, pre-dispatch-ingested-result.json) | Ingested source and timeline verification at 55f4ed6 | `c66b38f4b400879bba3aa8ed6274534d1f2263b7707781a49b13fd919a009298` |
| amendment-3-route-verification-c84ccd4.json (+ route-check files) | Route classification at the period-extension freeze | `713a8a414cb2a2781a14f74be5a8fe1a0d06cbb8bee871957a29e24f4b0a5ce4` |
| assessment-R28-C1-LIVE-20261114-01.json | Source-based verdicts | `a162f4e0c235f16d9a97b2545b44fc1617f4868d8c702989949667cd14b610dc` |
| operational-incident-20261114.md | Reviewer's accidental dev-stack postgres start/read/stop; no evidence of changed contents | `b4c06d8f329fe36b18e9a291641b6f1fe13f3cf5eb57afa4404a90704a7604f5` |
| PRE-DISPATCH-CHECKS.md | Reviewer checklist | `ff239de85a6eb92d82496adf18c013f7facdda56daa4e1419000aa90429827bc` |

The questions and expectations were never edited. All corrections were made through amendments, each recorded before execution.

## C1 conclusion (accepted by David as a limited milestone)

- Four answers are correct (F1, F2, F3, F4). F5 is partial and **not a pass**. There are no wrong answers or no-answers.
- Two items exercised C1. **F2** demonstrated current-answer protection with historical evidence present in the generation input. **F3** demonstrated dated-target selection with competing evidence (v1 in 3 of 5 slots, not misused).
- The run did **not** demonstrate live rescue from a confident planner error that excluded the required historical version: F3's selected dated version was also the current version. That rescue path has provider-free integration evidence only.
- No C1 stop condition was observed.
- C1 is accepted as a bounded component demonstration for named-document questions. It is not evidence of broad temporal reliability or pilot readiness. Block 1 remains HOLD.
- C1 will not be rerun to obtain a particular planner mistake.

## F5 attribution (verdict unchanged)

The actual assembled generation request (`cases/F5.json` → `production_trace.assembled_generation_request`, contract v3) contained:

- the full question, including "Under the current Continence Care Policy";
- v2 evidence labelled `side: primary`, `authority_start 2026-05-01`, `authoritative_at_evaluated_at: true`;
- v1 evidence labelled `side: comparison`, `authority_start 2025-05-01`, `authoritative_at_evaluated_at: false`;
- requested evidence type `current_versus_historical_comparison`.

The current version's identity and effective date therefore reached generation, and the answer still resolved the conflict silently with "No".

**Attribution:** primarily generation (conflict disclosure). F5 is retained as a named Block 3 conflict-disclosure acceptance case, linked to T12. There is one minor upstream contribution: the plan has no representation of a conflicting selector. It records authority `period 2026-01` with operation compare, and the "current" selector appears only in the question text. That is the A-side relationship representation of design C, which was not implemented in C1. It did not withhold any fact the answer needed. The frozen verdict and the final correctness standard are unchanged.
