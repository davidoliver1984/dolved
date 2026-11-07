# Original r17/r19 location diagnosis

These two JSON files are byte-identical copies of the original, unscored v15 production observations at `docs/evaluation/r28-s04/r01p/confirmation-v15-evidence-20261107/run/cases/`. They are retained here so the location correction's commit has a focused, self-contained original-evidence boundary without committing the other 20 v15 cases or treating their separate findings as part of this fix.

In the original r17 observation, n1 selected `scope_enquiry`, matched the Key Holder Procedure family and quoted `North West` as named applicability. In r19, n1 matched the same family but quoted `North West Region sites`. Both ended at `unresolved_location_reference`. The isolated primary-workspace registry had `North West Region` without an alias or `reference_descriptor`.

Code inspection established that `scopeFact()` passed exact-reference mode to `resolveLocations()`. That mode bypassed article/suffix stripping; `LocationReferenceCandidates::shortened()` matched only explicitly designated stems. The suffix regex listed singular qualifiers, not `sites`, `teams`, `homes`, `offices` or `regions`. Thus the plural regex gap was real, but editing that regex alone would not resolve r19 while exact mode bypassed it. No provider call was needed for this diagnosis.

The follow-up regression and planner-only variance evidence are in the adjacent `north-west-location-regression-evidence-20261107/` and `r08-r17-binding-variance-evidence-20261107/` directories. The latter found r08 matched binding in 2/5 and r17 n1 in 1/5 under the current prompt. That sampling shows model-judgment variance but cannot estimate a pre/post prompt-induced change in rate from single earlier samples.
