# R28-S02 live retrieval baseline closure

## Decision

David approved this exact first-pass execution on 4 September 2026 for durable
recording as a successful execution and lineage baseline. It is not a Phase 28
pilot-readiness pass.

The immutable run identity is `R28-S02-LIVE-RETRIEVAL-BASELINE-0001`, executed
once at repository commit
`b8366fc5711eb253a9b69e366120267216417153`. No case was selectively rerun and
sealed held-out material was not accessed.

## Bound population and execution lineage

- legacy V2 retrieval population: 23 cases / 25 variants;
- corpus SHA-256:
  `3578e6877ff3e33a313774ea83c6d3edbe4749b491ac148d038a3a8475cb82f3`;
- canonical corpus digest:
  `0e78f8e57a3d9c358ae08bdf7e97ded151cc4111cf934f48342427a2a187c1af`;
- Voyage dense embedding: `voyage-4-large`, 1024 dimensions;
- local sparse encoding: `prithivida/Splade_PP_en_v1` at revision
  `efcd182bc7eb351e81a9445752d4388c2bab500b`;
- Voyage reranking: `rerank-2.5`;
- frozen threshold: `0.337890625`;
- execution policy SHA-256:
  `3d4f7271c96ed00844bc9f3a59c342e0df0df9aba551bc56cc0e8d4ef660305f`;
- quality policy SHA-256:
  `a66796efa4e5e3eec65e2ecf495dcb66db8ea7a668e4f998902a30369da564e8`.

The population uses recorded plans. This run does not exercise the current
live planner; its reported planner accuracy means the recorded planning inputs
matched the legacy population expectations.

## Results

| Retrieval path | Recall@K | Precision@K | MRR | nDCG@K |
| --- | ---: | ---: | ---: | ---: |
| Dense only | 0.9565 | 0.1739 | 0.5000 | 0.9405 |
| Hybrid | 1.0000 | 0.1130 | 0.5014 | 0.9516 |

Planner, eligibility and outcome accuracy were each `1.0`; absolute failures
were zero.

Hybrid retrieval achieved complete recall on this narrow population. Ranking
precision and reciprocal-rank performance remain materially weaker. Hybrid
improved recall but returned more non-target material than dense-only.

This is not the V4 end-to-end evaluation. It does not establish a Phase 28
threshold or pilot-readiness conclusion.

## Operations and cost

- Voyage provider attempts: 18 (2 embedding and 16 reranker);
- retries: 0;
- rate-limit events: 0;
- input tokens: 2,528;
- output tokens: 0;
- embedding estimated cost: USD 0.00003852;
- reranker estimated cost: USD 0.00011035;
- retrieval estimated cost: USD 0.00014887.

Costs are estimates derived from recorded usage and the frozen pricing
snapshots. The run stayed within every approved request, token, time and cost
ceiling.

## Provider-free verification

From this directory:

```sh
shasum -a 256 -c checksums.sha256
```

The result and run manifest contain the complete per-variant retrieval lineage,
provider profiles, timings, usage, policy and population bindings.
