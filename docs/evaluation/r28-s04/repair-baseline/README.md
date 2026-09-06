# R00 — production repair baseline

Recorded 2026-09-05. Code and planning HEAD: `f1c7e69f3798558ddeef3e1f43aa6df2d0dacc9a`; local `origin/main`: `35f5c2d2278b2890cd53f8fbd1a5a4c29ceebb5d`. Main was tracked-clean before this documentation task. The known unpushed planning commit is intentional. No repair implementation or provider execution occurred.

## R00.1 — effective runtime

[API snapshot](api-runtime.json) and [AI/Qdrant snapshot](ai-runtime.json) record the isolated `dolved-r28-s03` runtime, not the ordinary development or fambam stacks. Mounted planner, eligibility and configuration SHA-256 values matched the repository files. Existing active generations have 982/12/6 assigned chunks and exactly the same Qdrant point counts; each generation's wrong-workspace count is zero. Dense profile is Voyage `voyage-4-large`, 1024 dimensions; sparse revision is `efcd182bc7eb351e81a9445752d4388c2bab500b`. No index or database row was modified; no re-embedding is justified by these findings.

Planner: `plan-response-v3` / `query-evidence-contract-v1` / `structured-chat-v5`, OpenAI `gpt-5-mini`. Generation: `generation-result-v1` / `grounded-generation-v3-claim-type` / `openai-responses-v3-claim-type`, same model. Reranking: Voyage `rerank-2.5`, `document-metadata-v2`. Threshold `provisional-r28-query-evidence-contract-v1`: 0.337890625; dense/sparse candidate K 40/40, fused/reranked K 15/15, final K 5, RRF k 5. The threshold remains provisional, not newly calibrated by this inspection.

Runtime capture used Laravel bootstrap plus `SET default_transaction_read_only = on`, selecting only public generation/profile fields, and Python Settings' explicit non-secret fields. Qdrant counts used its local `/collections/rag-platform-vectors-v1/points/count` read endpoint with `exact: true`, generation equality and workspace equality (then workspace inequality). These checks establish identities/counts, not a new vector-quality certification.

## R00.2 — durable evidence

`evidence/` preserves the latest five-case selection, observation index and five case records byte-for-byte (`cmp` passed for all seven JSON files). Their original absolute `/tmp` paths remain historical fields: use the sibling `observations.json.cases/` directory for this durable copy. `checksums.sha256` covers the captured evidence and this baseline's reproducible scripts/results/snapshots, excluding itself and this explanatory README.

The supplied technical diagnosis is retained as `evidence/technical-review.txt`. Source attachment SHA-256: `b5094c9a57328febda3a551053e903b7e7d3bb9af81b48cdf987fb2ade0f9d35`. The documentation copy adds **one final newline only**, SHA-256 `4f97ef31daaa90bb87f697b310d074458f43af4489668dec8341114fb4ef5cdf`; it is not claimed byte-identical. No original evidence was changed. The original provider bytes responsible for `0606-06-06` are unavailable: provider invention versus earlier transformation is unresolved. Unsupported-date acceptance is independently reproduced below.

Verify from this directory: `shasum -a 256 -c checksums.sha256` (use `LC_ALL=C` on this host). Frozen population, old runs and unrelated results remain untouched; only the explicitly relevant five-case records were captured.

## R00.3 — session mapping

`tasks.json` now records R28-S04 **in_progress**, not complete: paid diagnostic executions and repair preparation have happened, but a trustworthy complete production-path 148-utterance evaluation has not passed. S01–S03 remain complete; S05/S06/S07 and R28-GATE retain their existing states and dependencies. No pilot-readiness pass is implied.

Guide Stage 28.4 still defines the full-run deliverable. Stage 28.5 defines diagnosis, David's approval of corrections and normal product implementation review; that safety rule is being applied now to failures encountered while S04 is incomplete, not represented as formal completion of S05. This plan does not renumber sessions or skip the later comparison/gap review.

**Proposed clarification for acceptance with R01:** add to Stage 28.4: “Partial diagnostic execution and approved production repair may occur while this stage is in progress; neither completes the full-run requirement. Apply Stage 28.5's correction-review safeguards before repair; retain its later consolidated diagnosis and Stage 28.6 exact-commit rerun obligations.” No guide wording, downstream numeric guide pointers, roadmap dependency or completed historical evidence is changed in R00. Reconcile formal run/scoring policy once at R05 before paid full evaluation.

## R00.4 — defect reproductions

Run from repository root, against the existing isolated containers:

```sh
docker exec -i -e PYTHONDONTWRITEBYTECODE=1 -e OTEL_SDK_DISABLED=true dolved-r28-s03-ai-1 /app/.venv/bin/python - < docs/evaluation/r28-s04/repair-baseline/reproduce-planner.py
docker exec -i dolved-r28-s03-api-1 php < docs/evaluation/r28-s04/repair-baseline/reproduce-eligibility.php
```

Both exited 0. The Python replay uses actual `StructuredChatRetrievalPlanner` and HTTP `MockTransport`, blocks socket connections, and captures the actual normalised result. An ONNX CPU-vendor warning on stderr was omitted from the JSON result file. These are deliberately **defect-reproducing assertions**, not fixes or quality tests.

| Reproduction | Actual first failing boundary | Observation |
| --- | --- | --- |
| Unsupported date | `apps/ai/app/retrieval/planner.py`, typed validation then `_normalise_temporal_plan` | A fabricated `0606-06-06` survives for a June 2026 question. Valid calendar syntax is not grounding. |
| Generic workplace | Same planner; `EligibilityResolver::resolveLocationReference` | `workplace` survives as a named reference and real authorised registry lookup returns `unresolved_location_reference`. |
| Our training policy | `_normalise_temporal_plan` personal-record regex | Correct fake `valid_at_date` policy interpretation becomes `current` / `personal_record_status`; date moves to `fact_date`. |
| Mixed current support | `EligibilityResolver::distinctComparisonVersions` | Pair family 10 survives; current-only support family 11 is removed before retrieval. Pairing is valid for the comparison, wrong when applied to all question evidence. |

PHP uses real `BuildAuthorisedKnowledgeScope` and the actual isolated registry with a read-only database session. The pairing predicate uses unsaved synthetic Document objects to isolate the failing condition; it is not a full conversation/integration proof. Runtime examples and traces are retained separately. R01P must exercise the complete production path after acceptance.

## R00.5 — interpretation of answer evidence

The mixed ROPA/compliance question retrieved historical/current ROPA evidence but lost single-version current supporting material before search. Raising retrieval K cannot repair that exclusion. The local-feasibility response supplied supported procedural guidance with its central limitation in `unsupported_aspects`, not clearly first in visible answer text. That is a presentation/completeness concern, not demonstrated fabrication of an approval. Citation membership alone also does not establish substantive support.

ADR-0023, “Generation owns sufficiency; no separate LLM sufficiency judge” and “Outcome taxonomy”, permits useful `QUALIFIED` answers with explicit unsupported aspects. A frozen label mismatch must be reported separately from unsupported factual claims; no frozen label was changed here. R05 must version any scoring interpretation against this accepted meaning before confirmation results are seen.

## Gate and handover

R00 evidence checklist is complete. R01 is a proposal for one architectural review; no implementation acceptance is inferred. Next: review [minimum slice contract](../minimum-slice-contract.md) and Proposed ADR-0038. Do not start R01P until accepted; do not launch the full evaluation. Public documentation was read for alternatives; no inference provider or AWS service was called. Unrelated untracked files and existing cache residue were preserved.
