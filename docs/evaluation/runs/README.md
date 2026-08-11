# Persisted evaluation runs

Each persisted experiment has an immutable, stable run ID and its own directory:

```text
EXP-0001-short-description/
├── result.json       # authoritative machine-readable evaluation result
├── config.json       # exact tested configuration and lineage
├── comparison.json   # optional saved baseline comparison and gate data
├── report.md         # generated Git-reviewable projection
├── report.html       # generated self-contained interactive projection
└── notes.md          # optional human interpretation and decision record
```

`result.json`, `config.json` and, when present, `comparison.json` are the saved
inputs. Reports are deterministic projections and may be regenerated without
calling an embedding, reranking, Ragas or other external provider. Human notes
are never overwritten by report generation.

Each observed case variant may retain its expected EvidenceUnit identities,
expected outcome, per-side candidate funnel and candidate lineage across dense,
sparse, RRF, reranking, threshold and final-evidence stages. `COMPARE` stores
`PRIMARY` and `COMPARISON` as independent sides. Stage values are nullable: a
report displays unavailable data as an em dash and never reconstructs it from a
later rank or configuration limit.

Question text capture is privacy-safe by default. `DISABLED` stores no question,
`REDACTED` stores no raw question, and `BENCHMARK_TEXT` is an explicit opt-in for
fictional corpora such as `dolved-care-engineering`. Expected source paths are
retained only with benchmark-text capture. Candidate/document text is not copied
into stage lineage. Future customer-corpus runners must retain the default or
explicitly select redaction unless a separately approved privacy policy permits
raw text persistence. These controls complement ADR-0012; they do not weaken its
log and telemetry restrictions.

Run IDs use `EXP-NNNN-short-description`. Do not reuse or rename an ID after its
artefacts have been reviewed. A superseding experiment receives a new directory;
history remains intact.

## Required `config.json` shape

```json
{
  "schema_version": "v1",
  "run_id": "EXP-0001-first-engineering-benchmark-run",
  "description": "First full engineering benchmark retrieval run",
  "status": "EXPERIMENTAL",
  "decision": null,
  "repository": {
    "commit": "<full commit hash>",
    "dirty": false
  },
  "benchmark": {
    "id": "dolved-care-engineering",
    "version": "1",
    "digest": "<benchmark digest>"
  },
  "corpus": {
    "version": "1",
    "digest": "<compiled corpus digest>"
  },
  "split": {
    "version": "1",
    "digest": "<split digest>"
  },
  "harness_version": "retrieval-evaluation-v1",
  "threshold_policy_identity": "<policy fingerprint or null>",
  "result_selector": "hybrid",
  "providers": {
    "dense": {
      "provider": "voyage",
      "model": "voyage-4-large",
      "embedding_profile_fingerprint": "<fingerprint>",
      "dimensions": 1024,
      "adapter_version": "1"
    },
    "sparse": {
      "provider": "fastembed",
      "model": "prithivida/Splade_PP_en_v1",
      "sparse_profile_fingerprint": "<fingerprint>",
      "model_revision": "<revision>",
      "adapter_version": "1"
    },
    "fusion": {
      "strategy": "rrf",
      "version": "1",
      "rrf_k": 60
    },
    "reranking": {
      "provider": "voyage",
      "model": "rerank-2.5",
      "adapter_version": "1"
    }
  },
  "candidate_pipeline": {
    "dense_candidate_k": 40,
    "sparse_candidate_k": 40,
    "fusion_candidate_k": 15,
    "reranker_candidate_k": 15,
    "evidence_threshold": 0.337890625,
    "final_evidence_k": 5
  }
}
```

`status` is deliberately maintained by the human evaluation workflow. Report
generation never promotes an experiment or infers `ACCEPTED`/`PROMOTED`.

## Generate a report

```bash
make evaluation-report RUN=EXP-0001-first-engineering-benchmark-run
```

To persist comparison data on first generation:

```bash
make evaluation-report \
  RUN=EXP-0001-first-engineering-benchmark-run \
  BASELINE=docs/evaluation/baselines/v2/experiment-result.json
```

Subsequent report generation reads the saved `comparison.json`; it does not need
the baseline path again. Open `report.html` directly in a browser. It embeds the
Plotly runtime and does not require a report server, application stack or CDN.
