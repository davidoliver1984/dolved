# CAL-EXP-0003 controlled-rejection diagnosis

This is a provider-free reading of the immutable CAL-EXP-0003 observations. It
does not change the benchmark, policy, threshold, retrieval configuration or
application behaviour.

## Metric accounting

The controlled-rejection metric contains ten semantic cases and thirty
variants. Five variants produced their exact expected controlled outcome:

- `v3.fire.compare.route-record-incomplete / colloquial`;
- `v3.payroll.compare.january-2027-incomplete / colloquial`;
- `v3.payroll.insufficient.receipt-attempts / precision`;
- `v3.training.compare.induction-information-governance-incomplete / direct`;
- `v3.training.compare.induction-information-governance-incomplete / colloquial`.

Every case has three variants, so the case-first calculation is exactly
`5 / 30 = 0.16666666666666666`. Twenty-five variants did not produce the exact
expected outcome.

## Important architecture boundary

ADR-0018 deliberately separates temporal/applicability eligibility from topic
relevance. The planner does not infer document families, and a family with no
eligible version is excluded while other eligible workspace families remain.
Likewise, `COMPARE` preserves two workspace-wide temporal scopes; one family
missing evidence on one side does not make that whole side unresolved. Several
V3 controlled expectations assume the opposite. Their accidental success on
some wordings merely means thresholding happened to empty one side.

## Complete variant inventory

Candidate notation is `file D#/S#/F#/R# @ score (margin)`, representing dense,
sparse, fused and reranker ranks. `—` means that no candidate survived the
threshold. The raw observation retains every candidate and full provenance.

### COMPARE cases

All planner results below were valid `COMPARE` plans and both temporal scopes
were resolved. The named EvidenceUnit is present on the populated target side.
The earliest semantic divergence is the benchmark expectation: it treats one
target family lacking a fact as an empty workspace-wide comparison side, which
ADR-0018 does not do.

| Case / variant | Expected → actual | Expected EvidenceUnit | Decisive threshold lineage |
|---|---|---|---|
| complaints response-plan / direct | incomplete → evidence found | PRIMARY `…primary.136` | PRIMARY `handling-v2` D2/S1/F2/R1 @ .53515625 (+.197265625), credited; COMPARISON `outbreak-management-v1` D24/S6/F11/R1 @ .3671875 (+.029296875), uncredited; six further uncredited candidates per side survived |
| complaints response-plan / colloquial | incomplete → evidence found | PRIMARY `…primary.136` | PRIMARY `handling-v2` D1/S1/F1/R1 @ .65625 (+.318359375), credited; COMPARISON `handling-v1` D1/S1/F1/R1 @ .35546875 (+.017578125), uncredited |
| complaints response-plan / precision | incomplete → evidence found | PRIMARY `…primary.136` | PRIMARY `handling-v2` D23/S1/F2/R1 @ .396484375 (+.05859375), credited; COMPARISON `outbreak-management-v1` D34/S3/F7/R1 @ .396484375 (+.05859375), uncredited; additional uncredited candidates survived both sides |
| fire route-record / direct | incomplete → evidence found | PRIMARY `…primary.144` | PRIMARY `fire-drills-v2` D1/S1/F1/R1 @ .5625 (+.224609375), credited; COMPARISON `fire-drills-v1` D2/S1/F1/R1 @ .365234375 (+.02734375), uncredited; three more candidates per side survived |
| fire route-record / colloquial | incomplete → incomplete | PRIMARY `…primary.144` | PRIMARY `fire-drills-v2` D2/S1/F1/R1 @ .5625 (+.224609375), credited; COMPARISON — |
| fire route-record / precision | incomplete → evidence found | PRIMARY `…primary.144` | PRIMARY `fire-drills-v2` D8/S5/F7/R1 @ .39453125 (+.056640625), credited; COMPARISON `peep-v1` D6/S5/F6/R1 @ .375 (+.037109375), uncredited |
| payroll January 2027 / direct | incomplete → evidence found | COMPARISON `…comparison.60` | COMPARISON `calendar-2027-scheduled` D1/S1/F1/R1 @ .9140625 (+.576171875), credited; PRIMARY `calendar-2026` D1/S1/F1/R1 @ .427734375 (+.08984375), uncredited |
| payroll January 2027 / colloquial | incomplete → incomplete | COMPARISON `…comparison.60` | PRIMARY `calendar-2026` D1/S1/F1/R1 @ .369140625 (+.03125), uncredited; COMPARISON — |
| payroll January 2027 / precision | incomplete → evidence found | COMPARISON `…comparison.60` | COMPARISON `calendar-2027-scheduled` D1/S1/F1/R1 @ .69921875 (+.361328125), credited; PRIMARY `medication-competency-v2` D2/S7/F2/R1 @ .3671875 (+.029296875) and `calendar-2026` D1/S1/F1/R2 @ .33984375 (+.001953125), uncredited |
| training information-governance / direct | incomplete → incomplete | COMPARISON `…comparison.212` | PRIMARY `induction-v1` D2/S1/F1/R1 @ .361328125 (+.0234375), uncredited; COMPARISON — |
| training information-governance / colloquial | incomplete → incomplete | COMPARISON `…comparison.212` | PRIMARY three `data-protection-v2` chunks, top D4/S10/F6/R1 @ .400390625 (+.0625), uncredited; COMPARISON — |
| training information-governance / precision | incomplete → evidence found | COMPARISON `…comparison.212` | COMPARISON `induction-v2-scheduled` D1/S1/F1/R1 @ .5546875 (+.216796875), credited; PRIMARY `data-protection-v2` D27/S5/F9/R1 @ .431640625 (+.09375), uncredited; further uncredited candidates survived both sides |

Eight of these twelve variants failed. They are classified
`BENCHMARK_EXPECTATION_OR_EVIDENCEUNIT_DEFECT` with a secondary
`COMPARE_SIDE_SEMANTICS` symptom. Raising the threshold above the largest
uncredited empty-side survivor (`0.431640625`) would require the next observed
boundary, `0.43359375`; that boundary reduces recall from `0.798246` to
`0.745614`, precision from `0.198246` to `0.187719`, and rejects eight more
expected EvidenceUnits. It is not a valid calibration correction.

### No-authority case

| Variant | Expected → actual | Planner / eligibility | Decisive lineage and earliest divergence |
|---|---|---|---|
| infection outbreak / direct | no eligible evidence → evidence found | expected and actual `CURRENT`; target family has no current authority, but the workspace still has 69 eligible current documents | Earliest divergence is the benchmark's family-empty ⇒ workspace-empty assumption. Four candidates survived; top `respiratory-ppe-procedure` D2/S1/F2/R1 @ .52734375 (+.189453125). |
| infection outbreak / colloquial | no eligible evidence → evidence found | expected and actual `CURRENT`; same workspace-wide scope | Same benchmark expectation defect. Three candidates survived; top `outbreak-restrictions-v2` D1/S1/F1/R1 @ .57421875 (+.236328125). |
| infection outbreak / predecessor-trap | no eligible evidence → evidence found | expected `CURRENT`, actual `COMPARE` | Earliest divergence is `PLANNER_SEMANTIC_ERROR`; the question about whether a predecessor became current was interpreted as a comparison request. Both broad sides then produced candidates. The underlying family-empty expectation is also incompatible with ADR-0018's workspace-wide aggregation. |

The first two failures are classified
`BENCHMARK_EXPECTATION_OR_EVIDENCEUNIT_DEFECT`; the predecessor wording is
classified `PLANNER_SEMANTIC_ERROR` by earliest cause. No predecessor was made
authoritative by Laravel—the problem is the expected whole-request outcome and,
for one wording, planning intent.

### INSUFFICIENT_EVIDENCE cases

These cases have no expected EvidenceUnits because the reviewed sources do not
state the demanded universal number. Except where noted, planning and
eligibility were correct and candidates reached every retrieval stage. The
survivors are topically relevant but do not establish the requested fact.

| Case / variant | Expected → actual | Decisive threshold lineage |
|---|---|---|
| GDPR deletion / direct | insufficient → evidence found | `data-protection-v2` D5/S—/F14/R1 @ .412109375 (+.07421875), then D2/S1/F1/R2 @ .396484375 |
| GDPR deletion / colloquial | insufficient → evidence found | `data-protection-v2` D2/S2/F2/R1 @ .345703125 (+.0078125) |
| GDPR deletion / precision | insufficient → evidence found | `data-protection-v2` D2/S40/F6/R1 @ .34765625 (+.009765625) |
| lone-working police / direct | insufficient → evidence found | `midlands-lone-worker-welfare` D1/S1/F1/R1 @ .78515625 (+.447265625); three more survivors |
| lone-working police / colloquial | insufficient → evidence found | same source D1/S1/F1/R1 @ .6796875 (+.341796875); two more survivors |
| lone-working police / precision | insufficient → evidence found | same source D1/S3/F1/R1 @ .6484375 (+.310546875); two more survivors |
| medication quarantine / direct | insufficient → evidence found | `storage-temperature-procedure` D1/S1/F1/R1 @ .6328125 (+.294921875); four more survivors |
| medication quarantine / colloquial | insufficient → clarification required | Planner extracted `the fridge` as a location; Laravel rejected it as `unresolved_location_reference`; retrieval did not execute |
| medication quarantine / numeric-demand | insufficient → evidence found | `fridge-monitoring-reference` D1/S2/F2/R1 @ .6953125 (+.357421875); two more survivors |
| payroll receipt attempts / direct | insufficient → evidence found | `expenses-procedure` D1/S1/F1/R1 @ .51171875 (+.173828125) |
| payroll receipt attempts / colloquial | insufficient → evidence found | `expenses-procedure` D1/S1/F1/R1 @ .50390625 (+.166015625) |
| payroll receipt attempts / precision | insufficient → insufficient | no candidate cleared the threshold |
| visitor badge minutes / direct | insufficient → evidence found | `visitors-contractors` D1/S1/F1/R1 @ .4140625 (+.076171875); two more survivors |
| visitor badge minutes / colloquial | insufficient → evidence found | `visitors-contractors` D1/S1/F1/R1 @ .435546875 (+.09765625); one more survivor |
| visitor badge minutes / precision | insufficient → evidence found | `midlands-lone-worker-welfare` D2/S23/F4/R1 @ .421875 (+.083984375), then `visitors-contractors` D14/S3/F5/R2 @ .37890625 |

The medication colloquial variant is `PLANNER_SEMANTIC_ERROR`: a common noun
phrase was incorrectly emitted as a location reference, so divergence occurred
before eligibility completed. The other thirteen failures are
`RERANKER_RELEVANCE_VS_SUFFICIENCY`. They are genuinely threshold-observable,
but not evidence that the chosen scalar boundary is simply too low. Rejecting
all their strongest candidates requires a threshold above `0.78515625`; the
next boundary (`0.7890625`) collapses expected-EvidenceUnit recall to
`0.310526`, precision to `0.090351`, MRR to `0.328947`, and rejects 102 of 156
expected units.

## Cause counts and conclusion

Of the 25 incorrect variants:

- 2 diverged at planning (`PLANNER_SEMANTIC_ERROR`);
- 10 encode family-specific emptiness as a workspace-wide controlled outcome,
  contrary to ADR-0018 (`BENCHMARK_EXPECTATION_OR_EVIDENCEUNIT_DEFECT`), eight
  of them presenting as `COMPARE_SIDE_SEMANTICS`;
- 13 reached thresholding with topically relevant but answer-insufficient
  evidence (`RERANKER_RELEVANCE_VS_SUFFICIENCY`).

Thus twelve failures diverged before threshold evaluation was meaningfully
applicable, while thirteen expose a genuine relevance-versus-answer-sufficiency
boundary. None demonstrates a temporal resolver, applicability resolver,
final-K, final eligibility recheck or scalar-threshold implementation bug.

The evidence does **not** support changing `0.337890625`, adding a new model, or
altering retrieval now. The smallest next application action is a separate,
bounded design review of how answer sufficiency should be established after
retrieval, preceded by correcting the two planner behaviours and reconciling
the ten benchmark expectations with accepted ADR-0018 semantics. No correction
is implemented here.
