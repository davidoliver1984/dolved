# Independent review — RELEASED AFTER EXECUTION (2026-11-14T11:56:50Z)

Run `R28-C2-LIVE-20261114-01`. Execution HEAD `829bbf1`, implementation `84f22f8`, runtime aggregate `7f20f123…73af`, released input `7603fc82…899f`.

The reviewer held these files outside the repository until all six items had completed. They were never available to the implementer or to execution. They are copied here byte-for-byte; `released-files.sha256` matches the reviewer's original hash records. This is a separate review inventory. The execution records, ledgers, authorisation and the parent `checksums.sha256` are unchanged. **The G2/G3 planner-contract diagnostic task must not read this folder.**

| File | Role | SHA-256 |
|---|---|---|
| expectations.json | Frozen expectations, authored before any C2 output was seen | `8ab2dc0212ae235e4b58f226656c95e87ff5f64b02b0bd0addb25aaed37f0bed` |
| verification-check.php / verification-result.json | Read-only ingested source and timeline verification at authoring | `3e48b278…e49e` / `bae69ecd…fcac` |
| amendment-1-route-c2-fca272e.json | Route classification; finding that unresolved discovery returned no-answer | `d3df7537fda7f5e986d063d098d99a793bfd05aa1767c0996a091bfdc01e4061` |
| amendment-2-route-c2-84f22f8.json | Finding resolved by the fallback fix; C2 scoring rule; release | `ab01b753e6b01bc373554cb27d52d6223be74b0f655f10926afc02b4c9574c4e` |
| assessment-R28-C2-LIVE-20261114-01.json | Source-based verdicts | `bb5cb3cb6b430f33e781770ada7352940fb0e0f6ed2ded15643a18ca8a2cd4ad` |

The questions and expectations were never edited.

## Result

- **Fresh items:** G1 and G4 are correct. G2 and G3 are no-answers (interpretation failure). These no-answers are product failures, not exclusions.
- **Conflict control:** G5 is correct and makes the disagreement explicit.
- **Known regression:** R16 is correct, and it is a live rescue. The planner repeated the original confident error (current authority with the date as a fact reference). C2 discovery resolved the family, C1 preserved v1, and generation used it, with planner disagreement recorded.
- No wrong answers. No C1/C2 hold condition was triggered.

**C2 component demonstration:** passes within its scope, on G1, G4 n2 and R16. The G4 n1 fallback answered by the existing route; it is not C2 protection.

**Block 1 blockers that remain:** G2 and G3, clear dated questions rejected by planner validation (`fact_date_precision`; `temporal_relation_grounding`). The rejected plans were not retained, so whether the model's representation or the validator was at fault is undetermined. This is under bounded diagnosis, with no rule loosened. Block 1 remains HOLD.
