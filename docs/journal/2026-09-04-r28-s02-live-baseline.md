# R28-S02 — Approved current-code live baseline

Date: 4 September 2026
Status: Complete

## Outcome

David approved the completed first-pass R28-S02 live baseline for durable
recording and session closure. Both separately identified components executed
exactly once at commit
`b8366fc5711eb253a9b69e366120267216417153`, passed their execution boundaries
and retained complete lineage. This is a successful execution/lineage baseline,
not a pilot-readiness pass.

The retrieval run `R28-S02-LIVE-RETRIEVAL-BASELINE-0001` used the bound legacy
V2 population of 23 cases / 25 variants and recorded plans. Hybrid retrieval
reported Recall@K `1.0000`, Precision@K `0.1130`, MRR `0.5014` and nDCG@K
`0.9516`. Dense-only reported Recall@K `0.9565`, Precision@K `0.1739`, MRR
`0.5000` and nDCG@K `0.9405`. Planner, eligibility and outcome accuracy were
each `1.0`; absolute failures were zero.

Hybrid retrieval achieved complete recall on this narrow population, while
ranking precision and reciprocal-rank performance remain materially weaker.
It improved recall but returned more non-target material than dense-only. The
run does not exercise the current live planner and must not be represented as
the V4 end-to-end evaluation.

The generation-security run `GEN-SEC-LIVE-R28-S02-BASELINE-0001` passed all 3/3
hostile-evidence smoke cases. Unsupported claims and unsafe injection compliance
were zero. Groundedness, factual precision, completeness, citation correctness
and outcome accuracy were each `1.0`. This evidence is limited to those three
smoke cases.

## Usage and ceilings

- Voyage attempts: 18;
- OpenAI generation requests: 3;
- OpenAI evaluator requests: 3;
- combined attempts: 24 of 33;
- retries: 0;
- input tokens: 7,768 of 600,000;
- output tokens: 1,565 of 18,432;
- retrieval estimated cost: USD 0.00014887;
- OpenAI estimated cost: USD 0.00444;
- combined estimated cost: USD 0.00458887 of USD 15;
- selective reruns: none.

All monetary figures are recorded as estimates from provider usage and frozen
pricing snapshots. No sealed held-out material was accessed.

## Durable evidence

The retrieval and generation-security artefacts are retained separately at:

- `docs/evaluation/runs/R28-S02-LIVE-RETRIEVAL-BASELINE-0001`;
- `docs/evaluation/runs/GEN-SEC-LIVE-R28-S02-BASELINE-0001`.

Each directory contains a deterministic checksum inventory, immutable run
manifest, executable policy snapshot, results and a closure statement. The
generation directory additionally retains raw application/evaluation
observations, config, population and rendered reports. Provider-free checksum,
lineage, arithmetic, JSON/Markdown, secret-boundary and separation checks
passed before closure.

## Gate interpretation

R28-S02 is complete. Phase 28 remains in progress; R28-S03 and R28-GATE remain
not started. R28-S02 alone establishes no Phase 28 threshold or pilot-readiness
conclusion. No R28-S03 work is included in this closure.
