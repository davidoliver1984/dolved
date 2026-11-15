# Phase B backend decision run — stopped, ungraded

Run `R28-DECISION-BACKEND-20261115-01`; one-use authority
`R28-DECISION-BACKEND-AUTH-20261115-01`, created and exclusively consumed under
David's exact approval. Executed runner commit
`6a2d52248e2ddfc224f72fab3233b37831d91a0a`; baseline
`b98569487046d1649003ccffa429c90678482c1c`. No rerun/resume. No correctness grading
or expectations/reviewer-assessment access. Block1 HOLD; main unchanged.

## Outcome and stop

First actual dispatch: **2026-11-15 16:02:34 Europe/London (UTC+00:00)**.
Stopped **16:10:42.835537 UTC**, during H2 post-dispatch validation.

- **14/34 dispatched**, all fresh-cohort items O1–O12,H1,H2.
- **13/34 finalised** by the frozen runner: O1–O12,H1.
- H2 production status `completed`, dispatcher exit0, ledger-copy exit0,
  persistence receipt produced, accounting settled. Its runner integrity/capture
  finalisation raised **JSONDecodeError**, so no H2 checkpoint record/verdict was
  published by the executor. Its raw output/ledger/persistence are preserved.
- **20 not dispatched:** fresh H3,H4,C1–C4,M1–M4; controls K1–K4; regressions
  R19,R24,R26,R28,R29,G3. No later item was started.
- Dispatched production statuses: **4 completed / 10 retrieval_no_answer**.
  Finalised subset:3 completed /10 retrieval_no_answer. These are literal statuses,
  not correctness/quality judgements or a full-population result.

Exact observed stop is an unclassified JSON parse failure in post-dispatch
validation, not a provider failure attributed from a generic HTTP status. The
runner's bounded stop receipt retains exception type, time and callback count,
not a traceback or failing filename. Read-only examination after termination found
all retained JSON parseable and all retained ledger lines/sequence/hash chains
valid under the same splitting convention. Therefore **the exact read/underlying
cause is inconclusive**. No race, truncation, semantic defect or provider cause is
claimed. No failed check has been waived, no H2 finalisation fabricated, and no
runner/validator repair attempted. The strict unknown-failure stop was honoured.

Full per-item/cohort table: `per-item-status.md`; detailed machine-readable rows:
`summary.json`. Original files were not overwritten by analysis. `summarise.py`
reads retained records only and produces ungraded summaries, not a new diagnostic
dispatch or alternative execution result.

## Frozen identities and integrity

- Runtime `b73d054d9492e909cfaeadb642544950cfe940de10bfcbe12b388b089997b901`.
- Executable harness `f9bdcc4bd6158ada0d6f7d1c4e16d73f98f261cab114fa780abaeb68465ff407`.
- Original harness `607aa1d76a36d97a0db83d77f9f6c7a603d89d39bca429428a1628d856e1c79b`.
- Dependencies `907dc0bf43095dd390d3da24caf85bbb08f07d17bb76ceb30be1ef922d03a2fb`.
- Released input `4c6ad785d33fe032b57541fb6fcf9b06f81a48391bd4bf9e7b43bcc852769048`.
- Normalised input `5f7acc7bba8921eb6c2bd5e7f2f7e82283401c94a79ed408c6472389be70cfbd`.
- Neutral K2 confirmation `75f564ef5b8076bc9b6f7cfe67b647176a2f532704812d5cd9933419ea37fbce`.

All dispatched items used isolated `dolved-r28-s03`, actor
`ae454ce6-4d91-4c7e-9a40-b4750b696a6b`, owner membership/no site assignment,
workspace `5323cd6b-8b7a-40f9-98b0-9413c75f643e`, active generation
`b24a79c2-875e-4f7c-8fa9-3681db92ae73`. One fresh conversation/one exact released
user turn per dispatched item. Existing production retries unchanged.

`integrity/1` is pre-consumption verification; subsequent checks precede each
dispatch. Frozen mounted files, actual environment/override/capture, database,
actor/workspace/generation and executable identities were checked. Mutable capture
outputs were excluded from configuration fingerprints. Final mounted API/AI and
harness checks passed after the stop. No deployment, migrations, corpus/index,
roles/grants, model/settings or application changes. Necessary isolated services
remain healthy; background workers/scheduler/publisher/role-bootstrap not started.

## Accounting, attempts and capture

Approved reservation **USD136.737303**, hard stop **USD273.474606**.
Exact per-item reserved liability **USD4.021685375**;14 items charged
**USD56.303595250** conservative committed liability. Known/retained-rate receipt
subtotal **approximately USD0.17873925**; retained uncertainty
**approximately USD56.12485600**. These are not complete invoiced costs or expected
total spend. H2 accounting remains present despite its later validation failure.
No unused liability was released to make more room; no spending authority remains
reusable under this stopped one-use run.

`accounting.json` retains raw cost receipts/bases and unknown liabilities per item.
`service-receipts.json` retains each service's actual available attempts/retries,
latency, tokens, errors and metadata. Service-receipt token subtotal622,130 input /
27,560 output; it is not a reconstructed complete provider invoice. Parent run
aggregates are not added again. Capture shows14 accepted planner-attempt receipts
and7 rejected attempt sidecars across the dispatched items; see per-item stage,
attempt and code details. Rejections are not assumed to be extra physical retries:
missing physical-attempt receipts remain unavailable, not zero.

Saved assistant text, reloaded rendered text, parts/citations, unsupported aspects,
generation responses/available insufficiency fields, routes/discovery/selector,
allocation/temporal/evidence associations and workspace identities remain in raw
item records and hash-linked ledgers. Sidecars are the approved allowlisted
workspace-bound captures only. The pre-existing synthetic Phase A smoke file and
JUnit remain clearly separate from live items and are not counted as attempts.

**Raw internal source-review JSON remains unavailable.** Categorical diagnostics
do not reconstruct it. Unrestricted PHP stderr was deliberately not retained;
subprocess exit codes and structured evidence remain. Backend saved/reloaded
differences are preserved, not normalised. Neither representation establishes
what the web displayed. Known presentation defects remain pilot blockers.

Published partial evidence is for independent reviewer assessment. No gate-pass,
Block1 closure or readiness claim; no automatic next repair/run is authorised.
