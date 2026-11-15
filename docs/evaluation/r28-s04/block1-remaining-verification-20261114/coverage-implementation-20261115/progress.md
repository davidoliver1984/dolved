# Coverage package — implementation in progress, 15 November 2026

Starting commit: `73655047625d17d5b2ca5c62c67682919bb3d631`, hold branch only.
No provider calls or live runs. Main and unrelated edits preserved.

David withdrew investment-cap accounting on 15 November: no charged/remaining
allowance or cap checks. He manages the 20 November deadline. Repeated old cap
wording in the implementation brief is treated as carried-over, not reinstatement.

Approved boundary: one optional coverage proposal, independently validated shared
passage associations, source-support-review capacity verification, and the other
authorised package obligations. Item 19 and G3 remain open. No claim that allocation
alone repairs item 26's absent receiving-need retrieval.

Initial controlled checks: 52 Python tests passed (selector plus existing support
and temporal tests); 83 PHP tests passed before the new sharing-persistence test.
The first new sharing-persistence fixture attempted to mutate an immutable chunk
and correctly failed; it was changed to create a fresh canonical fixture instead.
These are development checks, not the full-suite gate or publication evidence.

Output-budget finding: a schema-valid source receipt with five short claims,
five parts and 1,060 answer characters serialises to 829 o200k tokens, excluding
all reasoning. Larger valid receipts exceed 1,024. No provider measurement made.
David approved a measured bounded increase. The candidate ceiling is 32,768,
with an explicit 4,096-token reasoning allowance in the realistic large-receipt
sizing test. Support checks, call count and retries are unchanged. Incomplete
receipts, including partially parsed ones, are rejected and their usage retained.
This is provider-free sizing, not a guarantee of model completion or a live result.
