# Independent reviewer review — Block 1 remaining-verification run (2026-11-14)

Evidence: `../run/` (published 2f0eb9f; runtime cbff39c2…b238). Reviewer: Claude. Execution records unchanged; this file is separate from the run's checksum inventory.

## Implementer review of items 17–32: confirmed

I checked every verdict against the quoted sources and the case records. I agree with all of them.

| | Items |
|---|---|
| Correct | 18, 20, 21, 22, 25, 30 (+ development item 32) |
| Qualified-correct | 23, 27 |
| Wrong | 24, 26, 28 |
| No-answer / clarification | 17, 19, 29 (+ development item 31) |

Item 24 is the closest call. The answer offers "on-call registered manager / senior carer" as alternatives to the on-call manager for a failed fridge. That substitution comes from a different table (temperature out of range), so the answer asserts more than the breakdown procedure supports. Under the existing rule that is wrong, not qualified.

## Known-regression items (graded against the sealed C2 expectations)

- **G2: CORRECT.** 65 degrees Celsius under the current version, via C2-resolved discovery. Precautionary v1 was present and not used for the current claim. The month-precision fact reference `2025-03` was accepted. Whether that came from the canonicaliser or from the model directly is unavailable. This item failed planning in the C2 checkpoint.
- **G3: NO-ANSWER.** Two planner rejections, both `grounding / temporal_relation_grounding`. This is consistent with the diagnostic's observation of authority "after" with a quote lacking "after"; the rejected plans are not retained. The notation fix removed the earlier `fact_date_precision` failure, but the relation-labelling error remains.

## New findings

1. **Item 29: a C1 contract defect, not chance.** `apps/ai/app/generation/temporal_targets.py::validate_temporal_uses` builds `expected = {part_index: need_id}`, so a part linked to two temporal needs keeps only the last need. It then requires exactly one temporal_use per part. An answer part that legitimately serves two temporal needs (here, two needs on the same Key Safe version and date) therefore cannot validate. Both attempts failed with `temporal_target_invalid`, giving a no-answer. This failure mode was introduced by C1. It is attributable from the code, but the rejected generation output was not retained, so the exact trigger is inferred.
2. **`temporal_relation_grounding` recurs.** It caused G3's two rejections and the first planner attempts of items 17 and 25; 17 and 25 recovered on retry. Three of the 18 items are affected. It cannot be diagnosed without the rejected plans, and production capture is currently a Block 4 backlog item.

## Block 1 status

Block 1 remains HOLD. Block 1-attributable or shared failures: 28 (role sense), 29 (C1 contract defect), G3 (relation labelling), 19 (update-selector interpretation, shared with Block 2), 26 (shared). Items 24, 23 and 27 are Block 3 or Block 2/3 classes already on the plan. This cohort is a separate new-runtime measurement. It cannot satisfy any same-runtime 32-item criterion.
