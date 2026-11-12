# Block 1 follow-up — implementation and bounded live result

2026-11-12. Stop after this report: no Block 2 implementation, cross-need reuse, full evaluation or readiness declaration.

## Identity and scope

- `3fe55786c754a2d897762c021852a737ebbd6f7c`: committed the earlier capacity checkpoint and dual assignment of its induction-control finding.
- `d27900160bc2dba0131ea0bc9961f6f90a5b0ef8`: fixed frozen-overflow-only runner.
- `b00b4a346cfce980987e5a328c9748f2ca29d90b`: Block 1 duration/temporal, honest failure, budget-coverage/disclosure correction and provider-free evidence.
- `c3ad51916d88919b91b2214768e43d02d7d91456`: tooling-only interpretation-outcome detection and fixed continuation for never-attempted inputs 4–7.

All pushed to origin/main. Application runtime for the seven-input checkpoint and its continuation: `716430fb0be16f0ee1607fb2a2abab30b71b20c34141f1ba6c9be1ceb4da6de6`. Corpus/index snapshot before/after: `f271c1ac843e53aaa16f6f0c70f6f3b642499d69dac2467d17e80c42f280d792`. Primary workspace `5323cd6b-8b7a-40f9-98b0-9413c75f643e` and authenticated primary user throughout. Three pre-existing tracked edits remain untouched/unstaged: ADR-0039 and the runner/test noninteractive-review changes; unrelated untracked files remain untouched. Runtime source is clean. The local `PROJECT_JOURNEY.md` remains gitignored, updated but not force-added wholesale.

## Frozen overflow input 5

[Evidence](r06-governed-evidence/R28-R07-CAPACITY-OVERFLOW-20261112-01/report.md): ran once, unchanged original five-input hash. Three comparisons required six lanes, exceeding four; zero search/generation, no dropped/partial comparison. Exact response:

> I can handle up to three requested aspects and four searches at once; a version comparison uses two searches. Which parts would you like me to address first?

One planner request; USD 0.0073845 offline estimate, pricing unavailable in receipt, separate USD 1 ceiling. No inputs 1–4 repeated.

## Changes and verification

The selector exposes complete-unit cut counts per need without changing FINAL_K, ordering, thresholds or minimum coverage. Qualified linked gaps with cuts now disclose selected-evidence limits using persisted unsupported-aspect rendering; arbitrary contradictory claims elsewhere remain Block 3 work, not silently solved by this disclosure.

Duration/quantity context is explicitly distinct from calendar fact dates in both languages and generated schemas. `before`/`after` retains the relation and calendar selector; existing Laravel timeline chooses the adjacent authorised version. David chose immediately-preceding semantics for before a named period. Leap/day/month/year boundaries and grounded follow-ups are tested. Relative dates remain out of scope.

Completed invalid planner output uses the existing maximum two attempts and an honest controlled interpretation-failed outcome, rather than turning invalid model output into a missing-date question. Provider failures retain the existing failure path; validated ambiguity and capacity remain distinct. Grounding and catalogue validation are not relaxed.

[Provider-free evidence](block1-followup-20261112/verification.md), [exact failure lists](block1-followup-20261112/suite-comparison.json), and raw baseline/changed JUnit are retained. AI: 1,222→1,233 passing, same 38 failures and 4 skips. Laravel: 831→837 passing, same 2 failures and 9 skips. **Zero added or resolved failing names.** Full suite exits remain nonzero. Fifteen governed-runner tests plus the PHP harness predicate's five positive/negative controls pass. Changed-source Ruff/Pint and source-only diff whitespace checks pass. Raw test logs intentionally retain their original whitespace and are not claimed to pass a whole-artifact whitespace check.

## Seven once-only live inputs

The [first package](r06-governed-evidence/R28-R07-BLOCK1-CHECKPOINT-20261112-01/report.md) holds original inputs 1–3. A harness accounting stop on input 3 was corrected provider-free without changing runtime or rewriting its false `needs_pipeline_exercised` flag. The [continuation](r06-governed-evidence/R28-R07-BLOCK1-REMAINING-20261112-01/report.md) holds only the four never-attempted inputs. This is not represented as one uninterrupted run.

| Input | Actual route / result | Repaired mechanism exercised |
| --- | --- | --- |
| b03-05/v1 | Two current retrieves; handbook evidence; three weeks' notice and leaving steps. | Duration yes; budget-cut counts yes; gap disclosure unnecessary. |
| b05-01/v1 | Current retrieve plus before-July compare; correct e-learning v1/v2. Training Team before, IT service desk now. | Adjacent-before yes. |
| b01-04/v1 | Two rejected `temporal_relation_grounding` attempts; no search; honest “couldn't interpret reliably” no-answer. | Exhausted-invalid fallback yes; successful temporal execution no. |
| b01-10/v1 | Valid previous compare; reviewer `selected` rejected `quote_not_in_candidate`, effective `no_match`; controlled no-answer. | Prior reference-grounding symptom absent; fallback unexercised. Different Block 2 obstruction. |
| Unfamiliar duration | Matched current handbook; four-year duration; four weeks and return of items. | Duration yes. |
| Unfamiliar after | Matched e-learning, after July; correct v2 and IT service desk. | Adjacent-after yes. |
| b01-07/v1 control | April transition compare; both uniform versions; £40→£55 and £25→£35. | Existing working path retained. |

Five real answers, two no-answers, no clarifications. Literal text, needs/bindings, selected evidence and receipts are in each raw case and inventory. Six original flags are true; input 3's false flag is the preserved harness bug, while its two completed needs-planner attempts are explicit in the raw diagnostic. No safety/authority/citation breach identified in this bounded read. No replacement, selective retry or tuning against these outputs.

## Accounting, findings and next task

Seven-input known priced subtotal: USD 0.03213392. Including offline OpenAI estimates gives USD 0.08756492, **not a complete bill**. Ten Voyage reranking and twenty Qdrant infrastructure receipts remain unpriced; no price is invented. Recorded provider requests: 43; input/output tokens: 311,717 / 11,845. Each package has a reproducible accounting script, nested generation review usage counted only in its aggregate. The seven-input USD 5 authorization is cumulative; overflow has its separate USD 1 bound.

Remaining Block 1: `b01-04` repeated invalid temporal representation despite the new contract (safe fallback works, answerability does not); duration/quantity breadth beyond the evidenced shapes; relative-date decisions; general interpretation consistency, including the prior input-4 added examples. Block 3 still owns that induction answer's unsupported conclusion and broader evidence/answer assembly. Current `b01-10` quotation rejection is assigned to Block 2; the three historical effective `no_match` causes cannot be reconstructed and remain unknown.

**Proposed next task, not begun:** provider-free diagnosis of the remaining b01-04 relation/transition interpretation boundary and whether bounded field/code feedback in the existing correction attempt is sufficient, with a design decision before any new live authorization. Do not relax the guard or force benchmark-specific versions. Keep Block 2's newly legible quotation-rejection investigation separate. Update the living plan, journal and journey together; do not call Block 1 or pilot readiness complete.
