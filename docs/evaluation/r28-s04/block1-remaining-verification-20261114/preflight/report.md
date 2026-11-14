# Remaining-verification preflight — no dispatch

Date: 2026-11-14. Block 1 HOLD. This is not a checkpoint result.

## Verified boundary

- HEAD: `1f30cef2c9e7b97527f9d63aff0c84ddcb1590b1`, branch `codex/block1-extractive-hold-20261112`.
- Recomputed tracked application/contract inventory aggregate: `cbff39c23c64c7226ca3592248816884f2af8a5dcf7d5672f125d524078ba238`.
- Checkpoint execution-input checksum: `ee4fb0903fc0feff93510266455a3c8a520938787d25b5c7027a680e402dd951`; intended items 17–32, then known regressions G2/G3 from questions-only checksum `7603fc82fc2f95f9852f584f28cc6a3d4949a2a30e17b8ab3b56a52ce4d2899f`.
- Read-only stack preflight verified `dolved-r28-s03`, PostgreSQL `postgres/dolved_e2e_r28_s03`, primary workspace `5323cd6b-8b7a-40f9-98b0-9413c75f643e`, actor `ae454ce6-4d91-4c7e-9a40-b4750b696a6b`, membership, active corpus `b24a79c2-875e-4f7c-8fa9-3681db92ae73`, 986 assigned chunks and zero foreign chunks.

David's amendment removes rejected-plan capture and calendar-normalisation counts as execution preconditions. Both remain **unavailable**. No inference from event occurrence, accepted plans or answers is permitted. Available rejection receipts would be retained; no separate planner or replay is authorised. Correctness grading is deferred; safe item-level contract rejection is not itself a stop.

## Budget preflight limitation

No fitting conservative reservation has been established. The broad configured-token envelope for generation alone exceeds USD 10:

| Term | Verified value |
|---|---:|
| Questions | 18 |
| Laravel generation validation attempts | 2 |
| Python generation provider attempts per service request | 3 |
| SDK automatic retries | 0 |
| Configured context-window tokens | 400,000 |
| Generation output-token ceiling | 4,096 |
| Configured retained uncached input rate, USD/million | 0.25 |
| Configured retained output rate, USD/million | 2.00 |

Calculation: `18 × 2 × 3 × ((400000 − 4096) × 0.25 + 4096 × 2) / 1000000 = USD 11.574144`.

This is a **loose maximum-token reservation calculation, not expected spend, an invoice, or proof that these particular inputs can attain the full context ceiling**. The assembler's separate evidence/context bounds may permit a tighter defensible reservation; that tighter whole-path bound has not been established in this preflight. In particular, this must not be cited as a minimum required budget. Prior observed spend is also not a maximum. Planner/checker, discovery/search/reranking/review, entailment and uncertainty are not included in this subtotal, so it is not a completed all-stage reservation.

Source boundaries: `GenerateGroundedAnswer.php::prepare()` has two validation attempts; `OpenAIGenerator.generate` checks measured input plus output against the configured window then uses its configured provider-attempt loop; its SDK client has `max_retries=0`. Read-only AI settings confirmed generation attempts 3, embedding attempts 4 and reranker attempts 3. The existing API preflight confirmed the generation profile's 400,000/4,096 limits. Rates above are retained application configuration, not a newly verified price quotation.

Stopped before authorisation consumption rather than assuming a USD 10 reservation fits. Actual provider spend: USD 0. No calls or cases executed. No controls reduced.

## Work not performed

No authorisation issued/consumed, executable run harness frozen, deployment/restart, owner-role migration-status command, mounted post-restart verification, provider-free capture test, live run, answer grading or cohort metric computation. The existing committed C2 capture was inspected, not executed for a new conversation. Uncommitted governed-runner edits were not executed. No application source, earlier evidence or expectations changed; protected reviewer material was not read.

All live closure evidence requested from this new cohort remains unavailable, including same-runtime all-32 Recall@5 and correctness/false-flag criteria. Earlier inputs 1–16 cannot be combined with an unexecuted cohort to claim a pass. G2/G3 original verdicts remain unchanged.

## Bounded observability backlog (not a new gate)

Before Block 4's execution boundary is frozen, assess production-path rejected-plan capture and normalisation-count reporting. No implementation or additional readiness gate is authorised here.
