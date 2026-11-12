# Block 1 — organisation/title classification, 2026-11-12

**Recommendation: HOLD whole-block closure.** The organisation repair is live-verified,
registered-site narrowing and unmatched-place disclosure remain intact. Whole-title
classification passes provider-free production-path tests but was **not exercised
live**: both title questions planned generic applicability. Also, “ROPA for employee
records” is not an exact registered title and remains unresolved. Do not call all
nine historical questions repaired or silently move that remaining reference issue
to another block. No replacement questions, selective rerun or additional repair.

## Change and verification

Repair commit **`279cebe1838140a3dee8067ffd741d987fafd274`**, pushed before dispatch.
Baseline **`ebd79f86dc4ebd850eb26ce9279b360f6f7ee68b`**. No planner/checker/prompt,
ranking, threshold, version timeline, tenancy or citation validator change; no
location gate and no extra provider call. The old organisation exemption existed
but null resolved-location metadata lost the difference between “not a site” and
“unknown site,” so even the bare organisation could receive the coverage notice.

The authorised workspace's name is **Alderbridge Care Group**. Its existing
legal/organisation-suffix short form is retained, with bounded whole-reference
preposition/article, possessive, staff/employees/sites wrappers. No arbitrary name
prefixes or hardcoded tenant string. No tenant-level alias registry exists; site
aliases are not repurposed as organisation aliases. Whole family titles come from
the same workspace-scoped, non-tombstoned catalogue used by the planner. Documents
have source filenames, not a separate title field. Exact sites/aliases take
precedence over non-site matching; unmatched references retain existing heuristics.

`applicability_reference_classifications` survives retrieval to the disclosure
renderer; `reference_classifications` is retained in eligibility traces. Only a
nonempty set of positive organisation/document classifications suppresses the
notice. Missing legacy metadata, mixed unresolved references and unknown types do
not. The new metadata is not sent in the generation need request. See
[contract](../../../../../contracts/http/retrieval-needs/v3/reference-classification-v1.md).

[Provider-free verification](../../block1-reference-binding-20261112/verification.md):
7 focused tests / 60 assertions, including synthetic organisation forms, full vs
partial names, another tenant's organisation/title, whole titles with the/under,
site/title collision, site alias, unknown place and organisation+real site. The
real retrieval→disclosure boundary is tested, not only a string helper.
Full AI: 1,347 before/after, **same 38 failures**. Laravel: 860→863, **same 2 failures**.
Exact new/resolved failing-name sets empty; all four raw JUnit XML and logs retained.
Scoped Pint passes. Final harness suite **22 tests passes**. Existing baseline
failures remain limitations, not a green-suite claim.

Initial focused test had a missing required constructor argument in the new test;
corrected before final tests, original log retained. Preflight then caught an omitted
new-stage selective-authorization allowlist entry: no authorization consumption or
provider call occurred. The bounded harness wiring and a positive/negative
authorization test corrected that; the unconsumed rejected authorization is retained
in the verification folder. Runtime did not change. The exact mixed-worktree runner
is archived here instead of absorbing unrelated pre-existing edits.

## Governed execution

All six frozen inputs ran once, primary-workspace authenticated membership only,
USD 2 ceiling, no selective reruns. Inputs 1/2 are development regressions; 3–6 are
new wordings frozen before outputs, not a new corpus. [Question-only selection](../../block1-reference-binding-20261112/execution-input.json)
SHA-256 **`9291d76dd754ff09d6f2e4d154845bd95be032ff978803e443d0786f7a37587c`**;
[separate expectations](../../block1-reference-binding-20261112/expectations.md).
[One-use authorization](authorization.json), [manifest](run-manifest.json),
[dispatch log](dispatch.ndjson), [all-six literal answers/plans/evidence](all-six.json).

Before/after runtime aggregate **`227e3f7068f74daf71ede100214e940cce2de1c8a421bbced3d8f60f7dbec751`**;
corpus/config digest **`f271c1ac843e53aaa16f6f0c70f6f3b642499d69dac2467d17e80c42f280d792`**.
Both unchanged, independently rechecked. Original runner completion freeze/inventory
preserved separately from later analysis. All records contain the production trace
and `needs_pipeline_exercised: true`. No application edits after dispatch started.

## Every input: binding, notice, answer and exercised mechanism

| # / raw record | Applicability and deterministic result | Notice / answer | Verification judgment |
|---|---|---|---|
| 1 [confidential support](cases/01-block1.citations-service-v1.json), development | named “Alderbridge staff” → `organisation`; null site is deliberate | No notice. EAP, confidential external counselling/support, direct access at any time for telephone counselling/self-help. | **Organisation path exercised and fixed.** Answer supported by Staff Wellbeing passages. No specific phone number supplied or invented; practical direct contact detail remains unavailable in source. |
| 2 [this-quarter e-learning](cases/02-block1.relative-current-quarter-v1.json), development | generic on both attempts; `reference_classifications: []`; app period 2026-10-01/2026-12-31 | No notice. IT service desk, not Training Team, with period stated. | **Title path unexercised.** Correct answer from current guidance. One justified checker correction; full question/period retained. |
| 3 [organisation wording](cases/03-block1.reference-organisation-v1.json), new | named “At Alderbridge Care Group” → `organisation` | No notice. £55 initial allowance in first month's pay. | **Organisation wrapper path exercised.** Current Uniform Allowance source supports answer. |
| 4 [policy title](cases/04-block1.reference-title-v1.json), new | generic; `reference_classifications: []` | No notice. Replacement allowance paid every April. | Correct answer, **title path unexercised**, not counted as a live classifier pass. |
| 5 [registered site](cases/05-block1.reference-site-v1.json), new | named Riverside House → `site`, ID `c2db9bba-c9ee-4736-b8bb-1ade44e1a766` | No notice. Guidance applies to every site including Riverside; £55 first month's pay. | **Registered narrowing exercised and retained.** Source explicitly says every site; registry establishes this site identity. |
| 6 [unregistered place](cases/06-block1.reference-unregistered-v1.json), new | named Oakfield outreach → `unresolved`; null site | **Notice retained**. £55 policy amount, followed by explicit unestablished coverage. | **Non-blocking unmatched-place path retained**, never classified non-site or as another registered place. Lead wording remains too definitive; see answer-assembly finding below. |

Literal input 6: “A new starter in a uniformed role at Oakfield outreach would
receive a one-off initial uniform allowance of £55 in their first month's pay,
per the Uniform Allowance Guidance which applies to every site.” Then:
“Location coverage not established … The guidance above is general; its
applicability to that location has not been established.” The amount is supported;
the named entity's coverage is not. **Do not count the lead as proof of coverage or
the whole answer as unqualifiedly correct.** The disclosure requirement passed,
but the site-specific lead versus disclaimer inconsistency is recorded for
**Block 3 answer assembly**, alongside its existing evidence-to-claim targets.
It appeared on the final input; no further live execution or inline fix followed.
No operational, cross-tenant or citation violation was observed. We do not use the
presence of the footer as a blanket waiver for categorical claims in the lead.

[Source excerpts](source-excerpts.json) retain the prior frozen chunk snapshot
identity, quoted texts and missing selected IDs explicitly. The cited uniform
passage `c943d6d2…` says £55/first month and £35/every April and applies to every
site. The EAP passage `910854cb…` says every employee/direct confidential access;
the e-learning passage `e6eef862…` names IT service desk. Missing snapshot chunk
`070dfce2…` was selected but is not needed to establish those reviewed claims.

## Checker reporting — no tuning

[Every check and full reviewed plan](all-checks-and-plans.json) is retained.
Seven checker calls for six questions: six `accepted`, one `scope_added`.
**No unverifiable flags**, so none was silently discarded or exempted from manual
review. **Zero retry-triggering false flags** under the predeclared definition.

Input 2 attempt 1: verified `scope_added`, n1 aspect quote **“and any named contact
point or escalation route specified for those issues.”** This exact phrase is in
the plan and not in the question. Independent review agrees: the user asked which
team, not additional contacts or escalation routes. The second plan removes the
addition and keeps the full question, login problem, named guidance and quarter.
That is a justified flag, not merely a citation-valid verdict assumed correct.
All other first plans accepted. No third attempt, new checker call, or prompt edit.

Checker overhead: 7,316 input / 929 output tokens; 14,688.43 ms total (six questions,
one existing retry). Per-call receipts retained; input 2 has 3,988.24 +2,305.67 ms.
This PHP classifier adds no provider tokens. Four omission probes were not rerun
live; prior retained real catches and latest provider-free citation proof remain
linked evidence, not a claim this six-input sample measures omission recall.

## Accounting

[Full receipts](accounting.json): priced stages **USD 0.04104078**; plus offline
OpenAI planner/checker estimates **USD 0.08655553**. This is not a complete invoice:
Voyage reranking has 30,281 recorded input tokens across six requests but unavailable
pricing; Qdrant infrastructure pricing is also unavailable. Estimates use retained
USD 0.25/M input, USD 2/M output GPT-5-mini rates, not a new price lookup. Nested
checker/entailment receipts are included in parent totals, never double-counted.
Checker share estimate USD 0.003687 is already included. No observed budget stop;
complete billed cost cannot be asserted from these receipts. Missing pricing stays Block 4.

## Block 1 completion criteria

| Plan task / criterion (quoted where applicable) | Position |
|---|---|
| “align producers/consumers at three needs/four search lanes/five final slots”; prove allocation including comparison plus support | **Met**, retained `34adbcb` capacity proof and live inputs 1–3. This sample does not re-exercise multi-need allocation. |
| “distinguish genuine overflow from ambiguity”; “overflow is honest” | **Met**, retained once-only capacity overflow run, not rerun here. |
| “run first five-input live checkpoint before the remaining tasks” | **Met** across original stopped four plus separately authorized fifth; not rewritten as one passing batch. |
| “separate duration/calendar/authority roles and honest invalid-output/provider-failure outcomes” | **Met for approved bounded shapes**, retained `b00b4a3`, `817c307`, `2456a08` proofs. Here the relative quarter executes correctly. Period spanning authority changes remains Block 2. |
| “supported shapes execute with all needs represented and sides intact” / interpretation consistency | **Partly met overall.** All six retain requested parts; corrected input 2 retains full question. Organisation misbinding now corrected at execution. Whole-title path lacks live exercise, and the nonmatching ROPA wording remains a Block 1 limitation. No general semantic-completeness guarantee. |
| “no new permission, authority, ownership or material correctness failure” | No permission/authority/citation breach or operational failure observed. **Not a blanket answer-correctness pass:** final input's site-specific lead is stronger than its disclosure; retained as a Block 3 assembly finding, not waived. |
| “Budget-limited support must not be described as absent source evidence” | **Met on retained closing-tail inputs 3/12/13**, no new branch proof claimed from this run. Their separate overclaim findings remain open in Block 3. |
| Approved zero false flags; every unverifiable flag/plan reviewed | **Met in this six-input sample:** one justified verified correction; no unverifiable flags; no false retry. Small-sample evidence only. |
| Two-attempt ceiling, no added checker call, no question-specific prompt rules | **Met**, unchanged source/contracts and per-attempt receipts. |
| Four omission probes still caught and overhead retained | Latest provider-free verified-citation proof and earlier real catches retained; **not re-measured live** here. All current checker tokens/latencies recorded. |
| “Material allocation/design/safety failures hold this block”; unrelated failures preserved/assigned | **Hold closure**, not another automatic tuning round: title route unexercised and ROPA wording outside the deterministic data match. Organisation/site/unregistered mechanics pass their exercised checks. No scope/authority/permission relaxation proposed to close the gap. |

Existing before/after/date decisions, Finance-variance disposition, b01-04/b01-10
Block 2 quotation rejections, Block 3 offered→required/induction findings and
26+5 safety-test maintenance, and Block 4 remaining7 AI/2 Laravel baseline failures
are not changed. No reference issue is reassigned. The new output-consistency
finding is a separate generation/assembly mechanism, not a pretext for changing
this resolver further. Stop for David's decision; no Block 2 implementation.

## Evidence preservation

`completion-checksums.sha256` preserves the runner's original inventory. Final
`checksums.sha256` covers the raw cases plus report, offline consolidation, exact
runner/test snapshots and independent freeze. Raw records remain byte-identical.
[Read-only identity replay](../../block1-reference-binding-20261112/identity-replay.json)
confirms actual-data organisation/title/site decisions and the unresolved ROPA
wording, without a provider call; it is explicitly **not live-route proof**.
Plan, build guide, journal and local journey updated together. Unrelated ADR and
mixed runner edits remain untouched by scoped commits.
