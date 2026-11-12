# First capacity checkpoint — stopped at the two-need control

2026-11-12. Direct authorization: commit/push the staged capacity correction and plan, then the frozen five inputs once each, maximum USD 5, retaining the material-regression review gate. Diagnostic prerequisite `47d414781e1e3c55075d9d2706c838a426c1540f` and capacity/plan commit **`34adbcb4d51beb8df3e5dcac270221140d37599d`** are pushed to `origin/main`. No runtime change was made during the run. Unrelated ADR and pre-existing noninteractive-runner/test edits were preserved.

## Result and stop

**Four inputs dispatched once; input 5 unattempted.** All four records have `needs_pipeline_exercised: true`, full production traces, primary-workspace membership and persisted rendered text identical to the displayed text. Four `completed` statuses are not four semantic passes. The existing interactive gate stopped after input 4; the runner's nonzero exit is the deliberate review stop, not an application failure. No selective retry, fix, further population run or readiness claim followed.

| Input | Actual execution | Assessment |
| --- | --- | --- |
| 1: known b06-06/v1 | Three retrieve needs; three lanes; five final slots allocated n1=2, n2=2, n3=1 | Capacity exercised successfully. Partial semantic match: correct first financial escalation and financial-record/audit support, but the planner reads the requested current role as the escalation/contact role and the answer gives Safeguarding Lead, not the expected site Safeguarding Champion title. This is retained as interpretation/coverage work, not a clean end-to-end pass. |
| 2: unfamiliar three | Three bound retrieve needs; three lanes; five slots, each need represented | Initial uniform £55, handbook wellbeing services and financial escalation to Safeguarding Lead are all answered. Separately owned evidence; no cross-need borrowing. |
| 3: named comparison plus two current needs | Three matched-family needs, four lanes; Uniform current and predecessor both retained; five slots | Correct £55/£35 versus £40/£25, independent current handbook services and financial escalation. No topical-family diversion. The fifth slot holds an additional current Uniform passage. |
| 4: working b01-01/v1 control | Two retrieve needs; two lanes; five slots; one native generation validation retry after `entailment_uncertain` | **Hold: unsupported induction-check conclusion.** Accepted answer claims buddy introduction, handbook issuance and follow-ups are the documented checks that the starter knows how to access wellbeing support; cited passages do not establish that link. Both aspect results say supported and unsupported_aspects is empty. |
| 5: overflow | Not dispatched | Live overflow behavior remains unverified; provider-free overflow proof is preserved separately. |

## Why input 4 holds the checkpoint

The literal answer ends: “the buddy introduction, the day-one handbook issuance and the scheduled follow-ups form the documented steps by which the induction checks you know how to access support.” Read the raw record for the exact Unicode text. Its n2 evidence is:

- `d06530fc-13c3-5164-b6a6-1e77c1b70e93`, Induction Buddy Scheme Guidance: mandatory four-week buddy, day-one introduction, check partway through **how the pairing is working**. That is not a recorded check of access to wellbeing services.
- `5db54060-837f-50f6-be66-25b753f0fcec`, New Starter Onboarding Checklist: day-one handbook issue and return of checklist to HR within two weeks, plus DBS/contract/fire/medication/probation tasks. It does not state the wellbeing-access check asserted in the answer.
- `1a3459be-c9ab-5501-949a-8eba14b8dae7`, buddy guidance continuation: policy cross-references, mandatory-assignment example and FAQ; no such check.

These exact chunks were personally checked in the existing frozen [chunk snapshot](../../r07-evidence-units-v4-20261111/ranked-chunk-text-snapshot.json); its original bytes are unchanged. The frozen expectation was an induction check of EAP access/occupational-health contact, not inference from generic onboarding activities. The planner's expanded n2 aspect suggests buddy/onboarding documents; retrieval selected those; generation then presented a stronger conclusion as supported. This identifies the observed route, **not** proof that the capacity code caused it or a completed root-cause diagnosis. There is no clean-HEAD live paired control here. Scope remains bounded: preserve and assign the finding rather than tune the planner or entailment system mid-checkpoint.

Input 1 was reviewed before continuing: finance/escalation/referral statements were supported by selected financial-policy and multi-agency-protocol passages, but the role-title interpretation missed the expected Champion distinction. It was recorded as partial, not hidden as a pass. Input 4's positive claim that the cited activities establish the requested check is the material unsupported-claim stop.

## Usage and budget honesty

[Accounting and case summary](accounting-and-case-summary.json) is reproducible provider-free with `python3 summarize.py --write`; raw receipts remain in each case. Reported external requests: four planner, ten generation/entailment (including the one native validation retry), eleven query embeddings and twelve reranks: **37 requests**, **211,913 input tokens / 10,599 output tokens**. The generation aggregate already includes entailment: do not add its nested receipt again.

Known priced-stage estimate: **USD 0.03267623** (generation/entailment and embeddings). Planner and reranking receipts omit prices; local infrastructure charges are also unavailable. This is a lower bound, not the complete provider invoice. Applying the repository's generation gpt-5-mini snapshot rates to the planner's 72,289 input / 4,576 output tokens, without cache discounts, would add USD 0.02722425, for USD 0.05990048 before unpriced reranking/infrastructure. This is an offline estimate, not a provider billing assertion. No pricing lookup/provider call was added.

For the USD 5 operational ceiling, the reported 222,512 total tokens at a deliberately conservative uniform USD 10/million reserve amount to USD 2.22512, including already priced stages; this is a budget sanity allowance, **not** a measured tariff or independently verified billing cap. No complete invoice is available, so exact total spend cannot honestly be certified from these receipts. No calls occurred after the case-4 stop.

## Freeze, integrity and disposition

- Runtime aggregate before/after: `263ef24beabc4348f573904e3e390a71b3a16bb9c2fd9cd72bb8e65637182727`.
- Runtime/index snapshot before/after: `f271c1ac843e53aaa16f6f0c70f6f3b642499d69dac2467d17e80c42f280d792`.
- Frozen input SHA-256: `00c5838a3a9228ef84f7daeee76c9b286f6b61e5b208734e1080baec0fe209a4`.
- `authorization.json`, `run-manifest.json`, `dispatch.ndjson`, four immutable raw cases, before/after freeze and `checksums.sha256` retain the execution boundary. All dispatches used only primary workspace `5323cd6b-8b7a-40f9-98b0-9413c75f643e`.

The capacity correction has a real live direction signal: three needs and the four-lane mixed comparison execute and survive persistence. It does **not** establish complete answer quality, overflow behavior live, sufficient evidence budgets for every request, or pilot readiness. Block 1 is not closed overall. Stop for review of the control finding; no additional run is implied.
