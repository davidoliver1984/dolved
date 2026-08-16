# Evaluation run: EXP-0002-adr0022-corrected-planning-baseline

**Status:** EXPERIMENTAL

## Run summary

| Field | Value |
|---|---|
| Description | First exact-commit ADR-0022 full-pipeline engineering baseline |
| Executed at | `2026-08-12T09:23:20Z` |
| Repository commit | `99c038687716816e52c29583279c1fdf941e1364` |
| Working tree | `clean` |
| Benchmark | `dolved-care-engineering` / `v2` |
| Benchmark digest | `aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d` |
| Corpus | `v2` / `aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d` |
| Split | `1` / `fca770615b5fbf20e81b494454969d54dbab2bfa66abf728455e95832b57465f` |
| Harness | `retrieval-evaluation-v1` |
| Threshold policy | `11386497e6316bf199abb75ad0c6ca8baaafe759c297d5044dfc7ce07630eb21` |

## Exact tested configuration

### Provider/model lineage

| Component | Configuration |
|---|---|
| dense | `{"adapter_version":"1","dimensions":1024,"embedding_profile_fingerprint":"ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c","model":"voyage-4-large","provider":"voyage"}` |
| fusion | `{"rrf_k":60,"strategy":"rrf","version":"1"}` |
| reranking | `{"adapter_version":"1","model":"rerank-2.5","provider":"voyage"}` |
| sparse | `{"adapter_version":"1","model":"prithivida/Splade_PP_en_v1","model_revision":"efcd182bc7eb351e81a9445752d4388c2bab500b","provider":"fastembed","sparse_profile_fingerprint":"e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d"}` |

### Candidate pipeline

| Setting | Value |
|---|---:|
| dense_candidate_k | `40` |
| evidence_threshold | `0.337890625` |
| final_evidence_k | `5` |
| fusion_candidate_k | `15` |
| reranker_candidate_k | `15` |
| rrf_k | `60` |
| sparse_candidate_k | `40` |

## Classifier and Laravel resolution

```json
{
  "classifier": {
    "confusion_matrix": {
      "COMPARE": {
        "COMPARE": 22
      },
      "CURRENT": {
        "CLARIFICATION_REQUIRED": 2,
        "COMPARE": 1,
        "CURRENT": 83,
        "PLANNER_FAILURE": 2
      },
      "HISTORICAL_REFERENCE": {
        "HISTORICAL_REFERENCE": 7
      },
      "VALID_AT_DATE": {
        "VALID_AT_DATE": 9
      }
    },
    "date_hallucinations": 0,
    "false_compare": 1,
    "false_historical_reference": 0,
    "location_extraction": {
      "expected": 15,
      "matched": 12,
      "precision": 0.7058823529411765,
      "predicted": 17,
      "recall": 0.8
    },
    "mode_accuracy": {
      "COMPARE": {
        "accuracy": 1.0,
        "correct": 22,
        "total": 22
      },
      "CURRENT": {
        "accuracy": 0.9431818181818182,
        "correct": 83,
        "total": 88
      },
      "HISTORICAL_REFERENCE": {
        "accuracy": 1.0,
        "correct": 7,
        "total": 7
      },
      "VALID_AT_DATE": {
        "accuracy": 1.0,
        "correct": 9,
        "total": 9
      }
    },
    "planner_contract_accuracy": 0.8809523809523809,
    "structured_response_reliability": 0.9841269841269841,
    "temporal_accuracy": 0.9603174603174603
  },
  "laravel_resolution": {
    "clarification_reasons": {
      "ambiguous_authority_window_for_period": 3,
      "ambiguous_location_reference": 1,
      "unclassifiable_temporal_intent": 2,
      "unresolved_location_reference": 7
    },
    "eligibility_correctness": 0.8467741935483871,
    "historical_reference_resolution_correctness": 0.7142857142857143,
    "location_resolution_correctness": 0.5384615384615384,
    "outcome_correctness": 0.11290322580645161,
    "planner_correctness": 0.8809523809523809,
    "temporal_resolution_correctness": 0.8467741935483871
  },
  "population": 126
}
```

## Headline metrics

| Metric | Value |
|---|---:|
| Recall@K | 0.1389 |
| Precision@K | 0.0183 |
| MRR | 0.0913 |
| nDCG@K | 0.1398 |
| Planner accuracy | 0.8810 |
| Eligibility accuracy | 0.8492 |
| Outcome accuracy | 0.1111 |

## Planner reliability

| Measure | Value |
|---|---:|
| Total variants | 126 |
| Successful planner variants | 124 |
| Planner failures | 2 |
| Planner reliability | 0.9841 |
| Retrieval metric population | 124 |

Failure categories: `invalid_typed_plan`: 2

Retrieval metrics are computed only over variants where planning succeeded and retrieval ran. Planner hard failures remain separate and cannot be offset by retrieval averages.

## Baseline comparison

Baseline: `EXP-0001-alderbridge-initial-hybrid`

| Metric | Baseline | Candidate | Delta |
|---|---:|---:|---:|
| Recall@K | 0.0569 | 0.1389 | +0.0820 |
| Precision@K | 0.0008 | 0.0183 | +0.0174 |
| MRR | 0.0041 | 0.0913 | +0.0872 |
| nDCG@K | 0.0569 | 0.1398 | +0.0829 |

## Slice metrics

| Slice | Cases | Recall | Precision | MRR | nDCG | Planner | Eligibility | Outcome |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| COMPARE | 7 | 0.2857 | 0.0571 | 0.2857 | 0.2857 | 0.9048 | 0.9524 | 0.2857 |
| COSHH-alias | 1 | 0.3333 | 0.0667 | 0.3333 | 0.3333 | 1.0000 | 1.0000 | 0.3333 |
| CURRENT | 30 | 0.1278 | 0.0122 | 0.0611 | 0.1290 | 0.8889 | 0.8444 | 0.0889 |
| ICO-alias | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 1.0000 | 1.0000 | 0.0000 |
| MCA-alias | 3 | 0.2222 | 0.0444 | 0.2222 | 0.2222 | 1.0000 | 1.0000 | 0.2222 |
| RIDDOR-alias | 2 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 1.0000 | 0.8333 | 0.0000 |
| VALID_AT_DATE | 5 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.8000 | 0.7333 | 0.0000 |
| abbreviation | 4 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 1.0000 | 1.0000 | 0.0000 |
| adversarial | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 1.0000 | 1.0000 | 0.0000 |
| ambiguous-alias | 1 | 1.0000 | 0.0000 | 0.0000 | 1.0000 | 1.0000 | 0.0000 | 0.6667 |
| applicability | 4 | 0.2500 | 0.0000 | 0.0000 | 0.2500 | 0.7500 | 0.5833 | 0.1667 |
| clarification | 1 | 1.0000 | 0.0000 | 0.0000 | 1.0000 | 1.0000 | 0.0000 | 0.6667 |
| colloquial | 2 | 0.1667 | 0.0333 | 0.1667 | 0.1667 | 1.0000 | 1.0000 | 0.1667 |
| conflicting-guidance | 3 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.8889 | 0.7778 | 0.0000 |
| descendant-site | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.6667 | 0.6667 | 0.0000 |
| forms | 4 | 0.0833 | 0.0167 | 0.0833 | 0.0833 | 0.8333 | 0.9167 | 0.0833 |
| historical | 5 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.8000 | 0.7333 | 0.0000 |
| keyword-stuffing | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 1.0000 | 1.0000 | 0.0000 |
| location-alias | 3 | 0.1111 | 0.0222 | 0.1111 | 0.1111 | 0.7778 | 0.6667 | 0.1111 |
| long-form | 1 | 0.3333 | 0.0667 | 0.3333 | 0.3333 | 1.0000 | 1.0000 | 0.3333 |
| multi-document | 3 | 0.0556 | 0.0222 | 0.1111 | 0.0681 | 0.6667 | 0.6667 | 0.1111 |
| multi-evidence | 16 | 0.2188 | 0.0437 | 0.2188 | 0.2211 | 0.8958 | 0.9167 | 0.2292 |
| near-duplicate | 2 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.6667 | 0.8333 | 0.0000 |
| near-numeric-values | 5 | 0.0667 | 0.0133 | 0.0667 | 0.0667 | 0.8000 | 0.8667 | 0.0667 |
| near-time-values | 7 | 0.1429 | 0.0286 | 0.1429 | 0.1429 | 0.9524 | 0.8571 | 0.1429 |
| near-version-duplicate | 14 | 0.0714 | 0.0143 | 0.0714 | 0.0714 | 0.9048 | 0.9762 | 0.0714 |
| negative-exclusion | 2 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.8333 | 1.0000 | 0.0000 |
| negative-instruction | 3 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.8889 | 0.8889 | 0.0000 |
| never-authoritative | 2 | 0.1667 | 0.0167 | 0.0833 | 0.1667 | 1.0000 | 1.0000 | 0.1667 |
| numeric-boundary | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.3333 | 0.6667 | 0.0000 |
| numeric-range | 1 | 0.1667 | 0.0667 | 0.3333 | 0.2044 | 0.3333 | 0.3333 | 0.3333 |
| predecessor-resurrection | 1 | 1.0000 | 0.0000 | 0.0000 | 1.0000 | 1.0000 | 0.0000 | 0.0000 |
| prose | 2 | 0.1667 | 0.0333 | 0.1667 | 0.1667 | 0.8333 | 1.0000 | 0.1667 |
| region-specific | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.6667 | 0.6667 | 0.0000 |
| regional-applicability | 1 | 0.3333 | 0.0667 | 0.3333 | 0.3333 | 1.0000 | 0.3333 | 0.3333 |
| role-alias | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 1.0000 | 1.0000 | 0.0000 |
| scheduled-future | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.6667 | 1.0000 | 0.0000 |
| site-specific | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.6667 | 1.0000 | 0.0000 |
| table-evidence | 3 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 1.0000 | 1.0000 | 0.0000 |
| tables | 3 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.6667 | 0.7778 | 0.0000 |
| temporal-authority | 1 | 0.0000 | 0.0000 | 0.0000 | 0.0000 | 0.3333 | 0.6667 | 0.0000 |
| withdrawn | 2 | 0.5000 | 0.0000 | 0.0000 | 0.5000 | 0.6667 | 0.3333 | 0.0000 |
| withdrawn-before-authority | 1 | 0.3333 | 0.0333 | 0.1667 | 0.3333 | 1.0000 | 1.0000 | 0.3333 |
| zero-evidence | 2 | 1.0000 | 0.0000 | 0.0000 | 1.0000 | 1.0000 | 0.0000 | 0.3333 |

## Hard failures

- `eligibility_mismatch`
- `outcome_mismatch`
- `planner_failure:invalid_typed_plan:pilot.current.scheduled-medication-version:scheduled`
- `planner_failure:invalid_typed_plan:safeguarding.allegations.current-hr-timing:contrast`
- `planner_mismatch`

## Operational metrics

```json
{
  "dense": {
    "latency_ms": {
      "max": 14442.231382,
      "mean": 6837.821320904762,
      "median": 6408.973587,
      "min": 3914.988127,
      "p95": 10547.2037855
    },
    "provider_cost": null,
    "request_count": 2,
    "token_usage": 0,
    "usage": {
      "attempted_variants": 126,
      "cost_complete": false,
      "evidence_producing_variants": 28,
      "generation": {
        "cost_basis": "UNAVAILABLE",
        "cost_usd": null,
        "execution": "NOT_EXECUTED"
      },
      "known_provider_api_cost_usd": 3.564e-05,
      "mean_api_cost_per_attempted_variant_usd": null,
      "mean_api_cost_per_evidence_producing_variant_usd": null,
      "mean_api_cost_per_successfully_planned_variant_usd": null,
      "providers": [
        {
          "cached_input_tokens": 0,
          "cost_complete": false,
          "input_tokens": 75502,
          "known_cost_usd": 0,
          "latency_ms": {
            "max": 13343.422964004276,
            "mean": 5627.197626269614,
            "min": 2826.892542994756,
            "p50": 5343.145856499177,
            "p95": 8797.647784504079
          },
          "model": "gpt-5-mini",
          "output_tokens": 43705,
          "pricing_snapshots": [],
          "provider": "openai",
          "request_count": 126,
          "retry_count": 0
        },
        {
          "cached_input_tokens": null,
          "cost_complete": true,
          "input_tokens": null,
          "known_cost_usd": 0,
          "latency_ms": {
            "max": 121.9227080073324,
            "mean": 50.10864449962225,
            "min": 5.014250004023779,
            "p50": 17.96304150047945,
            "p95": 109.20460854322299
          },
          "model": "rag-platform-vectors-v1",
          "output_tokens": null,
          "pricing_snapshots": [],
          "provider": "qdrant",
          "request_count": 40,
          "retry_count": 0
        },
        {
          "cached_input_tokens": null,
          "cost_complete": true,
          "input_tokens": 297,
          "known_cost_usd": 3.564e-05,
          "latency_ms": {
            "max": 350.6092079987866,
            "mean": 282.30155971505155,
            "min": 255.70958299795166,
            "p50": 275.28068750325474,
            "p95": 319.8018916500587
          },
          "model": "voyage-4-large",
          "output_tokens": null,
          "pricing_snapshots": [
            "voyage-pricing-2026-08-12"
          ],
          "provider": "voyage",
          "request_count": 28,
          "retry_count": 0
        }
      ],
      "stages": [
        {
          "execution_count": 28,
          "latency_ms": {
            "max": 350.6092079987866,
            "mean": 282.30155971505155,
            "min": 255.70958299795166,
            "p50": 275.28068750325474,
            "p95": 319.8018916500587
          },
          "request_count": 28,
          "retry_count": 0,
          "stage": "dense_embedding"
        },
        {
          "execution_count": 126,
          "latency_ms": {
            "max": 13343.422964004276,
            "mean": 5627.197626269614,
            "min": 2826.892542994756,
            "p50": 5343.145856499177,
            "p95": 8797.647784504079
          },
          "request_count": 126,
          "retry_count": 0,
          "stage": "planner"
        },
        {
          "execution_count": 28,
          "latency_ms": {
            "max": 121.9227080073324,
            "mean": 50.10864449962225,
            "min": 5.014250004023779,
            "p50": 17.96304150047945,
            "p95": 109.20460854322299
          },
          "request_count": 40,
          "retry_count": 0,
          "stage": "qdrant_dense_search"
        }
      ],
      "successfully_planned_variants": 124,
      "total_provider_api_cost_usd": null,
      "unavailable_cost_lineage": [
        "openai/gpt-5-mini"
      ]
    }
  },
  "experiment": {
    "attempted_variants": 126,
    "cost_complete": false,
    "evidence_producing_variants": 13,
    "generation": {
      "cost_basis": "UNAVAILABLE",
      "cost_usd": null,
      "execution": "NOT_EXECUTED"
    },
    "known_provider_api_cost_usd": 5.34e-05,
    "mean_api_cost_per_attempted_variant_usd": null,
    "mean_api_cost_per_evidence_producing_variant_usd": null,
    "mean_api_cost_per_successfully_planned_variant_usd": null,
    "providers": [
      {
        "cached_input_tokens": null,
        "cost_complete": true,
        "input_tokens": null,
        "known_cost_usd": 0.0,
        "latency_ms": {
          "max": 1126.4367510011652,
          "mean": 712.9899318580491,
          "min": 583.6784580023959,
          "p50": 686.5350420011964,
          "p95": 911.353592452724
        },
        "model": "prithivida/Splade_PP_en_v1",
        "output_tokens": null,
        "pricing_snapshots": [],
        "provider": "fastembed",
        "request_count": 14,
        "retry_count": 0
      },
      {
        "cached_input_tokens": 0,
        "cost_complete": false,
        "input_tokens": 75502,
        "known_cost_usd": 0,
        "latency_ms": {
          "max": 13343.422964004276,
          "mean": 5627.197626269614,
          "min": 2826.892542994756,
          "p50": 5343.145856499177,
          "p95": 8797.647784504079
        },
        "model": "gpt-5-mini",
        "output_tokens": 43705,
        "pricing_snapshots": [],
        "provider": "openai",
        "request_count": 126,
        "retry_count": 0
      },
      {
        "cached_input_tokens": null,
        "cost_complete": true,
        "input_tokens": null,
        "known_cost_usd": 0,
        "latency_ms": {
          "max": 131.1356669975794,
          "mean": 63.887955464062024,
          "min": 5.014250004023779,
          "p50": 67.6117709990649,
          "p95": 129.46434350487834
        },
        "model": "rag-platform-vectors-v1",
        "output_tokens": null,
        "pricing_snapshots": [],
        "provider": "qdrant",
        "request_count": 84,
        "retry_count": 0
      },
      {
        "cached_input_tokens": null,
        "cost_complete": false,
        "input_tokens": 56373,
        "known_cost_usd": 0,
        "latency_ms": {
          "max": 577.700833,
          "mean": 450.19307157142856,
          "min": 268.272333,
          "p50": 511.828125,
          "p95": 576.2585376
        },
        "model": "rerank-2.5",
        "output_tokens": null,
        "pricing_snapshots": [],
        "provider": "voyage",
        "request_count": 14,
        "retry_count": 0
      },
      {
        "cached_input_tokens": null,
        "cost_complete": true,
        "input_tokens": 445,
        "known_cost_usd": 5.34e-05,
        "latency_ms": {
          "max": 350.6092079987866,
          "mean": 282.4611013339087,
          "min": 252.19037500210106,
          "p50": 278.80074950371636,
          "p95": 319.1862577037682
        },
        "model": "voyage-4-large",
        "output_tokens": null,
        "pricing_snapshots": [
          "voyage-pricing-2026-08-12"
        ],
        "provider": "voyage",
        "request_count": 42,
        "retry_count": 0
      }
    ],
    "stages": [
      {
        "execution_count": 42,
        "latency_ms": {
          "max": 350.6092079987866,
          "mean": 282.4611013339087,
          "min": 252.19037500210106,
          "p50": 278.80074950371636,
          "p95": 319.1862577037682
        },
        "request_count": 42,
        "retry_count": 0,
        "stage": "dense_embedding"
      },
      {
        "execution_count": 126,
        "latency_ms": {
          "max": 13343.422964004276,
          "mean": 5627.197626269614,
          "min": 2826.892542994756,
          "p50": 5343.145856499177,
          "p95": 8797.647784504079
        },
        "request_count": 126,
        "retry_count": 0,
        "stage": "planner"
      },
      {
        "execution_count": 42,
        "latency_ms": {
          "max": 121.9227080073324,
          "mean": 54.185597356817674,
          "min": 5.014250004023779,
          "p50": 22.580853998078965,
          "p95": 105.07576453819638
        },
        "request_count": 62,
        "retry_count": 0,
        "stage": "qdrant_dense_search"
      },
      {
        "execution_count": 14,
        "latency_ms": {
          "max": 131.1356669975794,
          "mean": 92.99502978579507,
          "min": 43.83075000077952,
          "p50": 128.48597899937886,
          "p95": 130.3173982501903
        },
        "request_count": 22,
        "retry_count": 0,
        "stage": "qdrant_sparse_search"
      },
      {
        "execution_count": 14,
        "latency_ms": {
          "max": 577.700833,
          "mean": 450.19307157142856,
          "min": 268.272333,
          "p50": 511.828125,
          "p95": 576.2585376
        },
        "request_count": 14,
        "retry_count": 0,
        "stage": "reranking"
      },
      {
        "execution_count": 14,
        "latency_ms": {
          "max": 1126.4367510011652,
          "mean": 712.9899318580491,
          "min": 583.6784580023959,
          "p50": 686.5350420011964,
          "p95": 911.353592452724
        },
        "request_count": 14,
        "retry_count": 0,
        "stage": "sparse_encoding"
      }
    ],
    "successfully_planned_variants": 124,
    "total_provider_api_cost_usd": null,
    "unavailable_cost_lineage": [
      "openai/gpt-5-mini",
      "voyage/rerank-2.5"
    ]
  },
  "hybrid": {
    "latency_ms": {
      "max": 14442.231382,
      "mean": 6837.821320904762,
      "median": 6408.973587,
      "min": 3914.988127,
      "p95": 10547.2037855
    },
    "provider_cost": null,
    "request_count": 16,
    "token_usage": 56373,
    "usage": {
      "attempted_variants": 126,
      "cost_complete": false,
      "evidence_producing_variants": 13,
      "generation": {
        "cost_basis": "UNAVAILABLE",
        "cost_usd": null,
        "execution": "NOT_EXECUTED"
      },
      "known_provider_api_cost_usd": 1.776e-05,
      "mean_api_cost_per_attempted_variant_usd": null,
      "mean_api_cost_per_evidence_producing_variant_usd": null,
      "mean_api_cost_per_successfully_planned_variant_usd": null,
      "providers": [
        {
          "cached_input_tokens": null,
          "cost_complete": true,
          "input_tokens": null,
          "known_cost_usd": 0.0,
          "latency_ms": {
            "max": 1126.4367510011652,
            "mean": 712.9899318580491,
            "min": 583.6784580023959,
            "p50": 686.5350420011964,
            "p95": 911.353592452724
          },
          "model": "prithivida/Splade_PP_en_v1",
          "output_tokens": null,
          "pricing_snapshots": [],
          "provider": "fastembed",
          "request_count": 14,
          "retry_count": 0
        },
        {
          "cached_input_tokens": 0,
          "cost_complete": false,
          "input_tokens": 75502,
          "known_cost_usd": 0,
          "latency_ms": {
            "max": 13343.422964004276,
            "mean": 5627.197626269614,
            "min": 2826.892542994756,
            "p50": 5343.145856499177,
            "p95": 8797.647784504079
          },
          "model": "gpt-5-mini",
          "output_tokens": 43705,
          "pricing_snapshots": [],
          "provider": "openai",
          "request_count": 126,
          "retry_count": 0
        },
        {
          "cached_input_tokens": null,
          "cost_complete": true,
          "input_tokens": null,
          "known_cost_usd": 0,
          "latency_ms": {
            "max": 131.1356669975794,
            "mean": 77.66726642850179,
            "min": 9.730792000482325,
            "p50": 95.46987549765618,
            "p95": 129.835477301458
          },
          "model": "rag-platform-vectors-v1",
          "output_tokens": null,
          "pricing_snapshots": [],
          "provider": "qdrant",
          "request_count": 44,
          "retry_count": 0
        },
        {
          "cached_input_tokens": null,
          "cost_complete": false,
          "input_tokens": 56373,
          "known_cost_usd": 0,
          "latency_ms": {
            "max": 577.700833,
            "mean": 450.19307157142856,
            "min": 268.272333,
            "p50": 511.828125,
            "p95": 576.2585376
          },
          "model": "rerank-2.5",
          "output_tokens": null,
          "pricing_snapshots": [],
          "provider": "voyage",
          "request_count": 14,
          "retry_count": 0
        },
        {
          "cached_input_tokens": null,
          "cost_complete": true,
          "input_tokens": 148,
          "known_cost_usd": 1.776e-05,
          "latency_ms": {
            "max": 318.12874999741325,
            "mean": 282.78018457162295,
            "min": 252.19037500210106,
            "p50": 282.08449999874574,
            "p95": 302.9263874999742
          },
          "model": "voyage-4-large",
          "output_tokens": null,
          "pricing_snapshots": [
            "voyage-pricing-2026-08-12"
          ],
          "provider": "voyage",
          "request_count": 14,
          "retry_count": 0
        }
      ],
      "stages": [
        {
          "execution_count": 14,
          "latency_ms": {
            "max": 318.12874999741325,
            "mean": 282.78018457162295,
            "min": 252.19037500210106,
            "p50": 282.08449999874574,
            "p95": 302.9263874999742
          },
          "request_count": 14,
          "retry_count": 0,
          "stage": "dense_embedding"
        },
        {
          "execution_count": 126,
          "latency_ms": {
            "max": 13343.422964004276,
            "mean": 5627.197626269614,
            "min": 2826.892542994756,
            "p50": 5343.145856499177,
            "p95": 8797.647784504079
          },
          "request_count": 126,
          "retry_count": 0,
          "stage": "planner"
        },
        {
          "execution_count": 14,
          "latency_ms": {
            "max": 104.5582910082885,
            "mean": 62.33950307120852,
            "min": 9.730792000482325,
            "p50": 94.0245000019786,
            "p95": 102.23573310177017
          },
          "request_count": 22,
          "retry_count": 0,
          "stage": "qdrant_dense_search"
        },
        {
          "execution_count": 14,
          "latency_ms": {
            "max": 131.1356669975794,
            "mean": 92.99502978579507,
            "min": 43.83075000077952,
            "p50": 128.48597899937886,
            "p95": 130.3173982501903
          },
          "request_count": 22,
          "retry_count": 0,
          "stage": "qdrant_sparse_search"
        },
        {
          "execution_count": 14,
          "latency_ms": {
            "max": 577.700833,
            "mean": 450.19307157142856,
            "min": 268.272333,
            "p50": 511.828125,
            "p95": 576.2585376
          },
          "request_count": 14,
          "retry_count": 0,
          "stage": "reranking"
        },
        {
          "execution_count": 14,
          "latency_ms": {
            "max": 1126.4367510011652,
            "mean": 712.9899318580491,
            "min": 583.6784580023959,
            "p50": 686.5350420011964,
            "p95": 911.353592452724
          },
          "request_count": 14,
          "retry_count": 0,
          "stage": "sparse_encoding"
        }
      ],
      "successfully_planned_variants": 124,
      "total_provider_api_cost_usd": null,
      "unavailable_cost_lineage": [
        "openai/gpt-5-mini",
        "voyage/rerank-2.5"
      ]
    }
  },
  "usage_note": "Unavailable provider pricing remains null and is never converted to zero."
}
```

## Strongest improvements and regressions

### Regressions

- table-evidence / Recall@K: -0.1111
- table-evidence / nDCG@K: -0.1111
- table-evidence / MRR: -0.0556
- table-evidence / Precision@K: -0.0111

### Improvements

- withdrawn-before-authority / nDCG@K: +0.3333
- withdrawn-before-authority / Recall@K: +0.3333
- regional-applicability / nDCG@K: +0.3333
- regional-applicability / MRR: +0.3333
- regional-applicability / Recall@K: +0.3333

## Case-level drill-down

### `complaints.handling.compare` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did complaint handling get faster?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did complaint handling get faster?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did complaint handling get faster?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `complaints.compare-current` | `family.complaints.handling` | `doc.complaints.handling.v2` | documents/complaints/handling-v2.md |
| COMPARISON | `complaints.compare-old` | `family.complaints.handling` | `doc.complaints.handling.v1` | documents/complaints/handling-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `complaints.handling.compare` / `compare`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare version 1 and version 2 complaint deadlines.
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare version 1 and version 2 complaint deadlines."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare version 1 and version 2 complaint deadlines."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `complaints.compare-current` | `family.complaints.handling` | `doc.complaints.handling.v2` | documents/complaints/handling-v2.md |
| COMPARISON | `complaints.compare-old` | `family.complaints.handling` | `doc.complaints.handling.v1` | documents/complaints/handling-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `complaints.handling.compare` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How did complaint response times change from the old policy?
- Covered EvidenceUnits: `complaints.compare-old, complaints.compare-current`
- Metrics: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How did complaint response times change from the old policy?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How did complaint response times change from the old policy?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `complaints.compare-current` | `family.complaints.handling` | `doc.complaints.handling.v2` | documents/complaints/handling-v2.md |
| COMPARISON | `complaints.compare-old` | `family.complaints.handling` | `doc.complaints.handling.v1` | documents/complaints/handling-v1.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=5 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `5a87d328-f076-5953-aa2e-8d7963341f74`<br>`5a87d328-f076-5953-aa2e-8d7963341f74` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #1 / 0.425073 | #1 / 16.251038 | #1 / 0.032787 | #1 / 0.671875 | pass | yes | complaints.compare-current |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #11 / 0.153634 | #11 / 4.903469 | #9 / 0.028169 | #2 / 0.425781 | pass | yes | none |
| `018c7c48-f558-5416-8a50-2043b3d3b7b8`<br>`018c7c48-f558-5416-8a50-2043b3d3b7b8` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | #7 / 0.160823 | #16 / 3.755893 | #10 / 0.028083 | #3 / 0.404297 | pass | yes | none |
| `893f68e3-e8d2-5acd-9a73-8f30912e2431`<br>`893f68e3-e8d2-5acd-9a73-8f30912e2431` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | #4 / 0.224274 | #27 / 2.644031 | #14 / 0.027119 | #4 / 0.398438 | pass | yes | none |
| `3175f7bd-0838-5056-a1da-341d951720ed`<br>`3175f7bd-0838-5056-a1da-341d951720ed` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #15 / 0.133272 | #3 / 8.076344 | #6 / 0.029206 | #5 / 0.345703 | pass | yes | none |
| `249cc883-6c9a-5099-bdbb-974f04227e23`<br>`249cc883-6c9a-5099-bdbb-974f04227e23` | `family.complaints.form`<br>`doc.complaints.form.v1` | #3 / 0.263179 | #2 / 9.670268 | #2 / 0.032002 | #6 / 0.332031 | fail | no | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #6 / 0.163192 | #20 / 3.461353 | #12 / 0.027652 | #7 / 0.324219 | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #9 / 0.159106 | #8 / 5.585562 | #7 / 0.029199 | #8 / 0.316406 | fail | no | none |
| `f4b9f291-51c7-5e35-9335-b7e3dd2b37ef`<br>`f4b9f291-51c7-5e35-9335-b7e3dd2b37ef` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #2 / 0.279386 | #5 / 5.964001 | #3 / 0.031514 | #9 / 0.308594 | fail | no | none |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #22 / 0.119876 | #10 / 5.084647 | #15 / 0.026481 | #10 / 0.298828 | fail | no | none |
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #10 / 0.158428 | #12 / 4.285662 | #8 / 0.028175 | #11 / 0.296875 | fail | no | none |
| `65dda7f5-3688-515f-8d78-25e87c41a7e0`<br>`65dda7f5-3688-515f-8d78-25e87c41a7e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | #12 / 0.147093 | #15 / 3.840849 | #13 / 0.027222 | #12 / 0.291016 | fail | no | none |
| `2dc51247-e552-5a57-91c3-9408e34f5d94`<br>`2dc51247-e552-5a57-91c3-9408e34f5d94` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #8 / 0.159966 | #4 / 5.996923 | #4 / 0.030331 | #13 / 0.291016 | fail | no | none |
| `b4f8b48b-d6bb-55bf-9808-e81c551b09f8`<br>`b4f8b48b-d6bb-55bf-9808-e81c551b09f8` | `family.complaints.advocacy`<br>`doc.complaints.advocacy.v1` | #5 / 0.173904 | #7 / 5.943255 | #5 / 0.030310 | #14 / 0.279297 | fail | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #19 / 0.123258 | #6 / 5.960930 | #11 / 0.027810 | #15 / 0.242188 | fail | no | none |
| `3cc16b3c-7d04-53a9-a273-eddea88a3ccb`<br>`3cc16b3c-7d04-53a9-a273-eddea88a3ccb` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #13 / 0.136443 | — | — | — | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #14 / 0.134699 | — | — | — | fail | no | none |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #16 / 0.132690 | #28 / 2.556882 | — | — | fail | no | none |
| `635ff5e9-ecb1-559b-8683-4b7a96ea7bd9`<br>`635ff5e9-ecb1-559b-8683-4b7a96ea7bd9` | `family.fire.drills`<br>`doc.fire.drills.v2` | #17 / 0.129990 | #18 / 3.531086 | — | — | fail | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #18 / 0.127945 | #38 / 1.749186 | — | — | fail | no | none |
| `f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e`<br>`f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #20 / 0.122829 | #30 / 2.476970 | — | — | fail | no | none |
| `f193cb26-bd92-5fb8-a0b1-ba2c829f658b`<br>`f193cb26-bd92-5fb8-a0b1-ba2c829f658b` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #21 / 0.122110 | #25 / 2.805881 | — | — | fail | no | none |
| `ee3bb1bd-f03f-5314-b408-a1895aaadc2e`<br>`ee3bb1bd-f03f-5314-b408-a1895aaadc2e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #23 / 0.117843 | #36 / 1.808166 | — | — | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #24 / 0.113205 | #24 / 2.820335 | — | — | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #25 / 0.097925 | — | — | — | fail | no | none |
| `ebda80a6-77c7-557b-9450-fbddfdb16e02`<br>`ebda80a6-77c7-557b-9450-fbddfdb16e02` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #26 / 0.097411 | #29 / 2.483210 | — | — | fail | no | none |
| `5cf87b03-5514-55ae-9cac-0aa6b7c572d3`<br>`5cf87b03-5514-55ae-9cac-0aa6b7c572d3` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #27 / 0.094585 | #17 / 3.692458 | — | — | fail | no | none |
| `f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0`<br>`f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | #28 / 0.088811 | — | — | — | fail | no | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | #29 / 0.085075 | #13 / 4.226178 | — | — | fail | no | none |
| `6ba08511-5e10-530d-9a62-17ffed9e9bc4`<br>`6ba08511-5e10-530d-9a62-17ffed9e9bc4` | `family.training.induction`<br>`doc.training.induction.v1` | #30 / 0.084081 | — | — | — | fail | no | none |
| `1f7baac6-5792-5b2a-9399-26ad4c21d6e4`<br>`1f7baac6-5792-5b2a-9399-26ad4c21d6e4` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #31 / 0.082604 | — | — | — | fail | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #32 / 0.080219 | #19 / 3.514495 | — | — | fail | no | none |
| `85950010-d571-5bd3-9c8e-78b2687219d7`<br>`85950010-d571-5bd3-9c8e-78b2687219d7` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | #33 / 0.078541 | #33 / 2.201613 | — | — | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #34 / 0.075082 | — | — | — | fail | no | none |
| `40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc`<br>`40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | #35 / 0.074787 | #32 / 2.298652 | — | — | fail | no | none |
| `1a330d42-d249-5bf6-ba4b-066222bc5f5b`<br>`1a330d42-d249-5bf6-ba4b-066222bc5f5b` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #36 / 0.072332 | #31 / 2.474556 | — | — | fail | no | none |
| `42e10f18-8de2-53bd-8487-f46c454bf735`<br>`42e10f18-8de2-53bd-8487-f46c454bf735` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #37 / 0.070384 | — | — | — | fail | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #38 / 0.066836 | #37 / 1.806985 | — | — | fail | no | none |
| `fc1749ce-678f-5b79-9a27-41ca33d2043c`<br>`fc1749ce-678f-5b79-9a27-41ca33d2043c` | `family.medication.prn`<br>`doc.medication.prn.v1` | #39 / 0.064996 | #35 / 1.939658 | — | — | fail | no | none |
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | #40 / 0.062798 | #34 / 2.004335 | — | — | fail | no | none |
| `3e50e8ee-575c-52c9-a368-f1c6d1c814e1`<br>`3e50e8ee-575c-52c9-a368-f1c6d1c814e1` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | — | #9 / 5.377051 | — | — | fail | no | none |
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | — | #14 / 3.979249 | — | — | fail | no | none |
| `919b1651-7a62-5792-b47f-6ac4fc784017`<br>`919b1651-7a62-5792-b47f-6ac4fc784017` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | — | #21 / 3.455244 | — | — | fail | no | none |
| `14ab94b0-4ade-5c5c-b5bd-77eae8daf94d`<br>`14ab94b0-4ade-5c5c-b5bd-77eae8daf94d` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #22 / 3.451189 | — | — | fail | no | none |
| `5a0ad7a9-b4c1-5072-a3b8-d527805bad81`<br>`5a0ad7a9-b4c1-5072-a3b8-d527805bad81` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | — | #23 / 3.036346 | — | — | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | — | #26 / 2.695853 | — | — | fail | no | none |
| `aeb0ea01-92b2-5418-ad27-c95cacb3b030`<br>`aeb0ea01-92b2-5418-ad27-c95cacb3b030` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | — | #39 / 1.731437 | — | — | fail | no | none |
| `10c0d44a-0caf-50df-a02a-2ff58404be9d`<br>`10c0d44a-0caf-50df-a02a-2ff58404be9d` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #40 / 1.578134 | — | — | fail | no | none |

#### COMPARISON

Candidate funnel: Dense=13 → Sparse=13 → Unique after RRF=13 → Reranker=13 → Threshold=4 → Final evidence=4

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `817f4ea7-115c-58d5-9a46-dbaef434a1f2`<br>`817f4ea7-115c-58d5-9a46-dbaef434a1f2` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #1 / 0.401591 | #1 / 12.189806 | #1 / 0.032787 | #1 / 0.640625 | pass | yes | complaints.compare-old |
| `13c0e838-be23-5fac-a03d-3c9478b3f41f`<br>`13c0e838-be23-5fac-a03d-3c9478b3f41f` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #4 / 0.145725 | #3 / 4.477633 | #4 / 0.031498 | #2 / 0.367188 | pass | yes | none |
| `72a23d19-05d6-5fe0-8918-f0442b392f2d`<br>`72a23d19-05d6-5fe0-8918-f0442b392f2d` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v1` | #3 / 0.151558 | #2 / 7.833226 | #2 / 0.032002 | #3 / 0.349609 | pass | yes | none |
| `07ab0a1c-21e8-5a07-b4ed-3110898b35ca`<br>`07ab0a1c-21e8-5a07-b4ed-3110898b35ca` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #5 / 0.134897 | #10 / 1.667132 | #7 / 0.029670 | #4 / 0.343750 | pass | yes | none |
| `14b1c8c3-190a-531d-b13e-5666a56b9ac7`<br>`14b1c8c3-190a-531d-b13e-5666a56b9ac7` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #2 / 0.184358 | #4 / 4.175284 | #3 / 0.031754 | #5 / 0.287109 | fail | no | none |
| `2d65a97b-9023-5d91-8a35-5d78b3934084`<br>`2d65a97b-9023-5d91-8a35-5d78b3934084` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #9 / 0.072246 | #11 / 1.318161 | #11 / 0.028577 | #6 / 0.263672 | fail | no | none |
| `254c3933-94f2-510b-aa2d-9ab1942de8a7`<br>`254c3933-94f2-510b-aa2d-9ab1942de8a7` | `family.medication.administration`<br>`doc.medication.administration.v1` | #8 / 0.080809 | #7 / 3.514905 | #8 / 0.029631 | #7 / 0.250000 | fail | no | none |
| `3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f`<br>`3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f` | `family.fire.drills`<br>`doc.fire.drills.v1` | #6 / 0.118347 | #6 / 3.770016 | #6 / 0.030303 | #8 / 0.236328 | fail | no | none |
| `3f7a6eba-f048-598f-8340-aed3172f8361`<br>`3f7a6eba-f048-598f-8340-aed3172f8361` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | #11 / 0.059220 | #8 / 2.553846 | #9 / 0.028790 | #9 / 0.222656 | fail | no | none |
| `5b68e998-3a65-5808-bc5b-73e28613adc9`<br>`5b68e998-3a65-5808-bc5b-73e28613adc9` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #7 / 0.110441 | #5 / 4.008644 | #5 / 0.030310 | #10 / 0.222656 | fail | no | none |
| `11a5a524-8a6e-5f08-9a8c-4c470aae9086`<br>`11a5a524-8a6e-5f08-9a8c-4c470aae9086` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #12 / 0.044342 | #12 / 0.529503 | #12 / 0.027778 | #11 / 0.211914 | fail | no | none |
| `80ddc068-0955-5bb4-92c0-4b1586792c84`<br>`80ddc068-0955-5bb4-92c0-4b1586792c84` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #10 / 0.063215 | #9 / 2.429930 | #10 / 0.028778 | #12 / 0.206055 | fail | no | none |
| `369ceff0-142f-5215-817d-ddafe27e7ace`<br>`369ceff0-142f-5215-817d-ddafe27e7ace` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #13 / 0.042179 | #13 / 0.375267 | #13 / 0.027397 | #13 / 0.195312 | fail | no | none |

### `complaints.handling.current-deadlines` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How quickly should we reply to a complaint now?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How quickly should we reply to a complaint now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How quickly should we reply to a complaint now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `complaints.handling.v2-deadlines` | `family.complaints.handling` | `doc.complaints.handling.v2` | documents/complaints/handling-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `complaints.handling.current-deadlines` / `contrast`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is the acknowledgement deadline three days or two?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Is the acknowledgement deadline three days or two?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Is the acknowledgement deadline three days or two?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `complaints.handling.v2-deadlines` | `family.complaints.handling` | `doc.complaints.handling.v2` | documents/complaints/handling-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `complaints.handling.current-deadlines` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What are the current complaint acknowledgement and response targets?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What are the current complaint acknowledgement and response targets?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What are the current complaint acknowledgement and response targets?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `complaints.handling.v2-deadlines` | `family.complaints.handling` | `doc.complaints.handling.v2` | documents/complaints/handling-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.breach.ico-owner` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do I tell the regulator myself after a privacy incident?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do I tell the regulator myself after a privacy incident?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do I tell the regulator myself after a privacy incident?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.breach.dpo-decision` | `family.gdpr.breach` | `doc.gdpr.breach.v1` | documents/gdpr/personal-data-breach.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.breach.ico-owner` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Who decides whether a data breach is reported to the ICO?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Who decides whether a data breach is reported to the ICO?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Who decides whether a data breach is reported to the ICO?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.breach.dpo-decision` | `family.gdpr.breach` | `doc.gdpr.breach.v1` | documents/gdpr/personal-data-breach.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.breach.ico-owner` / `timing`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Should frontline staff contact the ICO within 72 hours?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Should frontline staff contact the ICO within 72 hours?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Should frontline staff contact the ICO within 72 hours?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.breach.dpo-decision` | `family.gdpr.breach` | `doc.gdpr.breach.v1` | documents/gdpr/personal-data-breach.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.data-protection.compare` / `change`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did data-loss reporting change from 24 hours to four hours?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did data-loss reporting change from 24 hours to four hours?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did data-loss reporting change from 24 hours to four hours?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.policy.compare-current` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v2` | documents/gdpr/data-protection-v2.md |
| COMPARISON | `gdpr.policy.compare-old` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v1` | documents/gdpr/data-protection-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.data-protection.compare` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare the old and current privacy incident reporting deadlines.
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare the old and current privacy incident reporting deadlines."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare the old and current privacy incident reporting deadlines."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.policy.compare-current` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v2` | documents/gdpr/data-protection-v2.md |
| COMPARISON | `gdpr.policy.compare-old` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v1` | documents/gdpr/data-protection-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.data-protection.compare` / `history`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What changed in the data protection policy?
- Covered EvidenceUnits: `gdpr.policy.compare-old, gdpr.policy.compare-current`
- Metrics: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What changed in the data protection policy?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What changed in the data protection policy?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.policy.compare-current` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v2` | documents/gdpr/data-protection-v2.md |
| COMPARISON | `gdpr.policy.compare-old` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v1` | documents/gdpr/data-protection-v1.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=6 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #1 / 0.369401 | #1 / 15.324278 | #1 / 0.032787 | #1 / 0.687500 | pass | yes | gdpr.policy.compare-current |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #2 / 0.286220 | #2 / 15.012804 | #2 / 0.032258 | #2 / 0.523438 | pass | yes | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #4 / 0.243911 | #39 / 1.682558 | #13 / 0.025726 | #3 / 0.500000 | pass | yes | none |
| `ee3bb1bd-f03f-5314-b408-a1895aaadc2e`<br>`ee3bb1bd-f03f-5314-b408-a1895aaadc2e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #3 / 0.250159 | #3 / 12.600013 | #3 / 0.031746 | #4 / 0.466797 | pass | yes | none |
| `018c7c48-f558-5416-8a50-2043b3d3b7b8`<br>`018c7c48-f558-5416-8a50-2043b3d3b7b8` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | #16 / 0.128287 | #4 / 8.468743 | #7 / 0.028783 | #5 / 0.427734 | pass | yes | none |
| `4d1f0d61-d751-52f0-87dd-0327ea89db4e`<br>`4d1f0d61-d751-52f0-87dd-0327ea89db4e` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | #13 / 0.134791 | #29 / 2.868411 | #15 / 0.024935 | #6 / 0.378906 | pass | no | none |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #18 / 0.123545 | #9 / 4.300568 | #9 / 0.027313 | #7 / 0.304688 | fail | no | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #11 / 0.144162 | #6 / 7.023029 | #4 / 0.029236 | #8 / 0.287109 | fail | no | none |
| `3e50e8ee-575c-52c9-a368-f1c6d1c814e1`<br>`3e50e8ee-575c-52c9-a368-f1c6d1c814e1` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #32 / 0.093652 | #5 / 7.472886 | #12 / 0.026254 | #9 / 0.271484 | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #17 / 0.123583 | #8 / 4.727993 | #8 / 0.027693 | #10 / 0.267578 | fail | no | none |
| `0318f8f9-9107-50ab-9afd-a65ee1687c77`<br>`0318f8f9-9107-50ab-9afd-a65ee1687c77` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #20 / 0.121048 | #16 / 3.787968 | #14 / 0.025658 | #11 / 0.263672 | fail | no | none |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #22 / 0.119585 | #7 / 6.283790 | #10 / 0.027120 | #12 / 0.261719 | fail | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #8 / 0.159181 | #10 / 4.277451 | #5 / 0.028992 | #13 / 0.253906 | fail | no | none |
| `40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc`<br>`40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | #7 / 0.166224 | #26 / 3.158003 | #11 / 0.026553 | #14 / 0.249023 | fail | no | none |
| `919b1651-7a62-5792-b47f-6ac4fc784017`<br>`919b1651-7a62-5792-b47f-6ac4fc784017` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | #6 / 0.166946 | #13 / 4.109393 | #6 / 0.028850 | #15 / 0.225586 | fail | no | none |
| `f4b9f291-51c7-5e35-9335-b7e3dd2b37ef`<br>`f4b9f291-51c7-5e35-9335-b7e3dd2b37ef` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #5 / 0.181560 | — | — | — | fail | no | none |
| `85950010-d571-5bd3-9c8e-78b2687219d7`<br>`85950010-d571-5bd3-9c8e-78b2687219d7` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | #9 / 0.154048 | — | — | — | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #10 / 0.149935 | — | — | — | fail | no | none |
| `42e10f18-8de2-53bd-8487-f46c454bf735`<br>`42e10f18-8de2-53bd-8487-f46c454bf735` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #12 / 0.140757 | #35 / 2.306565 | — | — | fail | no | none |
| `20575c0a-658b-508a-a009-60706b3fde3c`<br>`20575c0a-658b-508a-a009-60706b3fde3c` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #14 / 0.134628 | — | — | — | fail | no | none |
| `f85e71bc-4d62-57d9-b403-b13b1a9ff199`<br>`f85e71bc-4d62-57d9-b403-b13b1a9ff199` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #15 / 0.132044 | #30 / 2.793329 | — | — | fail | no | none |
| `5cf87b03-5514-55ae-9cac-0aa6b7c572d3`<br>`5cf87b03-5514-55ae-9cac-0aa6b7c572d3` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #19 / 0.121931 | — | — | — | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #21 / 0.120413 | #37 / 2.080268 | — | — | fail | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #23 / 0.118891 | #19 / 3.456309 | — | — | fail | no | none |
| `aeb0ea01-92b2-5418-ad27-c95cacb3b030`<br>`aeb0ea01-92b2-5418-ad27-c95cacb3b030` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #24 / 0.117668 | #40 / 1.580382 | — | — | fail | no | none |
| `5a87d328-f076-5953-aa2e-8d7963341f74`<br>`5a87d328-f076-5953-aa2e-8d7963341f74` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #25 / 0.112214 | #34 / 2.679121 | — | — | fail | no | none |
| `ac335280-6bca-5150-bd9b-db2d198ca588`<br>`ac335280-6bca-5150-bd9b-db2d198ca588` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #26 / 0.111385 | — | — | — | fail | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #27 / 0.109832 | — | — | — | fail | no | none |
| `249cc883-6c9a-5099-bdbb-974f04227e23`<br>`249cc883-6c9a-5099-bdbb-974f04227e23` | `family.complaints.form`<br>`doc.complaints.form.v1` | #28 / 0.109309 | — | — | — | fail | no | none |
| `40b84d12-bb43-5dc3-a182-d80b51693330`<br>`40b84d12-bb43-5dc3-a182-d80b51693330` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #29 / 0.105346 | — | — | — | fail | no | none |
| `2dc51247-e552-5a57-91c3-9408e34f5d94`<br>`2dc51247-e552-5a57-91c3-9408e34f5d94` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #30 / 0.103431 | #23 / 3.252316 | — | — | fail | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #31 / 0.102043 | #21 / 3.405431 | — | — | fail | no | none |
| `8d8de832-6d4c-5368-b209-2ece5159b021`<br>`8d8de832-6d4c-5368-b209-2ece5159b021` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #33 / 0.093559 | #20 / 3.433086 | — | — | fail | no | none |
| `ebda80a6-77c7-557b-9450-fbddfdb16e02`<br>`ebda80a6-77c7-557b-9450-fbddfdb16e02` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #34 / 0.092796 | — | — | — | fail | no | none |
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | #35 / 0.092353 | #12 / 4.139772 | — | — | fail | no | none |
| `3cc16b3c-7d04-53a9-a273-eddea88a3ccb`<br>`3cc16b3c-7d04-53a9-a273-eddea88a3ccb` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #36 / 0.092322 | #14 / 4.044055 | — | — | fail | no | none |
| `92b627e2-da75-52c3-88b6-cdc01aa3b9ef`<br>`92b627e2-da75-52c3-88b6-cdc01aa3b9ef` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #37 / 0.089423 | #17 / 3.724461 | — | — | fail | no | none |
| `b4f8b48b-d6bb-55bf-9808-e81c551b09f8`<br>`b4f8b48b-d6bb-55bf-9808-e81c551b09f8` | `family.complaints.advocacy`<br>`doc.complaints.advocacy.v1` | #38 / 0.088958 | #28 / 2.891977 | — | — | fail | no | none |
| `1839469e-5726-503f-a711-a010a97420fd`<br>`1839469e-5726-503f-a711-a010a97420fd` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #39 / 0.088685 | #25 / 3.242029 | — | — | fail | no | none |
| `65dda7f5-3688-515f-8d78-25e87c41a7e0`<br>`65dda7f5-3688-515f-8d78-25e87c41a7e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | #40 / 0.085991 | #18 / 3.711077 | — | — | fail | no | none |
| `f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e`<br>`f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | — | #11 / 4.246134 | — | — | fail | no | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | — | #15 / 3.951465 | — | — | fail | no | none |
| `d24f4e43-6251-56d5-b470-c23242fe6873`<br>`d24f4e43-6251-56d5-b470-c23242fe6873` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #22 / 3.331119 | — | — | fail | no | none |
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | — | #24 / 3.247155 | — | — | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | — | #27 / 3.000387 | — | — | fail | no | none |
| `3175f7bd-0838-5056-a1da-341d951720ed`<br>`3175f7bd-0838-5056-a1da-341d951720ed` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #31 / 2.776010 | — | — | fail | no | none |
| `6a0fb733-bff0-55d1-a5e7-d322ef9e53a9`<br>`6a0fb733-bff0-55d1-a5e7-d322ef9e53a9` | `family.training.matrix`<br>`doc.training.matrix.v1` | — | #32 / 2.731689 | — | — | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | — | #33 / 2.703222 | — | — | fail | no | none |
| `955ca35a-ad9d-57fb-8c12-e79c9190c2cd`<br>`955ca35a-ad9d-57fb-8c12-e79c9190c2cd` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #36 / 2.166843 | — | — | fail | no | none |
| `6ba08511-5e10-530d-9a62-17ffed9e9bc4`<br>`6ba08511-5e10-530d-9a62-17ffed9e9bc4` | `family.training.induction`<br>`doc.training.induction.v1` | — | #38 / 1.711439 | — | — | fail | no | none |

#### COMPARISON

Candidate funnel: Dense=13 → Sparse=7 → Unique after RRF=13 → Reranker=13 → Threshold=1 → Final evidence=1

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `14b1c8c3-190a-531d-b13e-5666a56b9ac7`<br>`14b1c8c3-190a-531d-b13e-5666a56b9ac7` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #1 / 0.421892 | #1 / 15.513094 | #1 / 0.032787 | #1 / 0.636719 | pass | yes | gdpr.policy.compare-old |
| `13c0e838-be23-5fac-a03d-3c9478b3f41f`<br>`13c0e838-be23-5fac-a03d-3c9478b3f41f` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #6 / 0.098138 | #3 / 3.675369 | #3 / 0.031025 | #2 / 0.291016 | fail | no | none |
| `817f4ea7-115c-58d5-9a46-dbaef434a1f2`<br>`817f4ea7-115c-58d5-9a46-dbaef434a1f2` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #5 / 0.102992 | #6 / 2.687288 | #4 / 0.030536 | #3 / 0.279297 | fail | no | none |
| `254c3933-94f2-510b-aa2d-9ab1942de8a7`<br>`254c3933-94f2-510b-aa2d-9ab1942de8a7` | `family.medication.administration`<br>`doc.medication.administration.v1` | #9 / 0.066084 | #4 / 3.305821 | #5 / 0.030118 | #4 / 0.261719 | fail | no | none |
| `3f7a6eba-f048-598f-8340-aed3172f8361`<br>`3f7a6eba-f048-598f-8340-aed3172f8361` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | #3 / 0.120780 | — | #8 / 0.015873 | #5 / 0.255859 | fail | no | none |
| `5b68e998-3a65-5808-bc5b-73e28613adc9`<br>`5b68e998-3a65-5808-bc5b-73e28613adc9` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #2 / 0.148706 | #2 / 4.147908 | #2 / 0.032258 | #6 / 0.249023 | fail | no | none |
| `07ab0a1c-21e8-5a07-b4ed-3110898b35ca`<br>`07ab0a1c-21e8-5a07-b4ed-3110898b35ca` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #8 / 0.073545 | #7 / 0.147640 | #7 / 0.029631 | #7 / 0.248047 | fail | no | none |
| `72a23d19-05d6-5fe0-8918-f0442b392f2d`<br>`72a23d19-05d6-5fe0-8918-f0442b392f2d` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v1` | #10 / 0.049450 | #5 / 2.795578 | #6 / 0.029670 | #8 / 0.242188 | fail | no | none |
| `11a5a524-8a6e-5f08-9a8c-4c470aae9086`<br>`11a5a524-8a6e-5f08-9a8c-4c470aae9086` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #4 / 0.109922 | — | #9 / 0.015625 | #9 / 0.238281 | fail | no | none |
| `369ceff0-142f-5215-817d-ddafe27e7ace`<br>`369ceff0-142f-5215-817d-ddafe27e7ace` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #7 / 0.092734 | — | #10 / 0.014925 | #10 / 0.232422 | fail | no | none |
| `3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f`<br>`3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f` | `family.fire.drills`<br>`doc.fire.drills.v1` | #12 / 0.028457 | — | #12 / 0.013889 | #11 / 0.232422 | fail | no | none |
| `2d65a97b-9023-5d91-8a35-5d78b3934084`<br>`2d65a97b-9023-5d91-8a35-5d78b3934084` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #11 / 0.037707 | — | #11 / 0.014085 | #12 / 0.230469 | fail | no | none |
| `80ddc068-0955-5bb4-92c0-4b1586792c84`<br>`80ddc068-0955-5bb4-92c0-4b1586792c84` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #13 / 0.008086 | — | #13 / 0.013699 | #13 / 0.221680 | fail | no | none |

### `gdpr.data-protection.current-reporting` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How fast do I tell privacy about a data mistake?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How fast do I tell privacy about a data mistake?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How fast do I tell privacy about a data mistake?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.policy.v2-four-hours` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v2` | documents/gdpr/data-protection-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.data-protection.current-reporting` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How quickly must suspected personal-data loss be reported now?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How quickly must suspected personal-data loss be reported now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How quickly must suspected personal-data loss be reported now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.policy.v2-four-hours` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v2` | documents/gdpr/data-protection-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `gdpr.data-protection.current-reporting` / `email`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: I sent information to the wrong person — when do I report it?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "I sent information to the wrong person — when do I report it?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "I sent information to the wrong person — when do I report it?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `gdpr.policy.v2-four-hours` | `family.gdpr.data-protection` | `doc.gdpr.data-protection.v2` | documents/gdpr/data-protection-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.accident.current-riddor-timing` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How soon do we tell safety about something that might need RIDDOR reporting?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How soon do we tell safety about something that might need RIDDOR reporting?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How soon do we tell safety about something that might need RIDDOR reporting?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.riddor.v2-one-day` | `family.health-safety.accident-reporting` | `doc.health-safety.accident-reporting.v2` | documents/health-safety/accident-reporting-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.accident.current-riddor-timing` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How quickly must a possible RIDDOR incident reach the health and safety lead now?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How quickly must a possible RIDDOR incident reach the health and safety lead now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How quickly must a possible RIDDOR incident reach the health and safety lead now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.riddor.v2-one-day` | `family.health-safety.accident-reporting` | `doc.health-safety.accident-reporting.v2` | documents/health-safety/accident-reporting-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.accident.current-riddor-timing` / `expanded`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What is the current deadline for escalating a potentially reportable incident?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What is the current deadline for escalating a potentially reportable incident?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What is the current deadline for escalating a potentially reportable incident?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.riddor.v2-one-day` | `family.health-safety.accident-reporting` | `doc.health-safety.accident-reporting.v2` | documents/health-safety/accident-reporting-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.accident.valid-at-date` / `contrast`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Was the safety-lead deadline two working days in 2024?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Was the safety-lead deadline two working days in 2024?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "2024"
    }
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Was the safety-lead deadline two working days in 2024?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "2024"
    }
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.riddor.v1-two-days` | `family.health-safety.accident-reporting` | `doc.health-safety.accident-reporting.v1` | documents/health-safety/accident-reporting-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.accident.valid-at-date` / `dated`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What RIDDOR escalation deadline applied in January 2024?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What RIDDOR escalation deadline applied in January 2024?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "January 2024"
    }
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What RIDDOR escalation deadline applied in January 2024?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "January 2024"
    }
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.riddor.v1-two-days` | `family.health-safety.accident-reporting` | `doc.health-safety.accident-reporting.v1` | documents/health-safety/accident-reporting-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.accident.valid-at-date` / `historical`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How long did managers have under the old accident procedure?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How long did managers have under the old accident procedure?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "old accident procedure"
    }
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How long did managers have under the old accident procedure?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "old accident procedure"
    }
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.riddor.v1-two-days` | `family.health-safety.accident-reporting` | `doc.health-safety.accident-reporting.v1` | documents/health-safety/accident-reporting-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.coshh.review-trigger` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: The cleaning chemical changed — can we wait for the annual COSHH review?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "The cleaning chemical changed — can we wait for the annual COSHH review?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "The cleaning chemical changed — can we wait for the annual COSHH review?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.coshh.review` | `family.health-safety.coshh` | `doc.health-safety.coshh.v1` | documents/health-safety/coshh-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.coshh.review-trigger` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When must a COSHH assessment be reviewed?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "When must a COSHH assessment be reviewed?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "When must a COSHH assessment be reviewed?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.coshh.review` | `family.health-safety.coshh` | `doc.health-safety.coshh.v1` | documents/health-safety/coshh-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.coshh.review-trigger` / `product`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do we need a new hazardous-substance assessment when a product formulation changes?
- Covered EvidenceUnits: `health-safety.coshh.review`
- Metrics: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do we need a new hazardous-substance assessment when a product formulation changes?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do we need a new hazardous-substance assessment when a product formulation changes?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.coshh.review` | `family.health-safety.coshh` | `doc.health-safety.coshh.v1` | documents/health-safety/coshh-procedure.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=1 → Final evidence=1

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #1 / 0.485135 | #1 / 15.316639 | #1 / 0.032787 | #1 / 0.820312 | pass | yes | health-safety.coshh.review |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #4 / 0.248963 | #6 / 5.993432 | #5 / 0.030777 | #2 / 0.335938 | fail | no | none |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #7 / 0.217081 | #2 / 10.311155 | #4 / 0.031054 | #3 / 0.314453 | fail | no | none |
| `3ffac08e-eebd-5bf7-963c-116ad06e0312`<br>`3ffac08e-eebd-5bf7-963c-116ad06e0312` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #2 / 0.295472 | #3 / 6.354521 | #2 / 0.032002 | #4 / 0.304688 | fail | no | none |
| `f85e71bc-4d62-57d9-b403-b13b1a9ff199`<br>`f85e71bc-4d62-57d9-b403-b13b1a9ff199` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #31 / 0.148487 | #9 / 5.054604 | #13 / 0.025482 | #5 / 0.275391 | fail | no | none |
| `56745918-8c2b-5490-a300-4c18bf32a5c6`<br>`56745918-8c2b-5490-a300-4c18bf32a5c6` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #8 / 0.207023 | #5 / 6.187600 | #6 / 0.030090 | #6 / 0.267578 | fail | no | none |
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | #9 / 0.201638 | #22 / 3.165342 | #10 / 0.026688 | #7 / 0.263672 | fail | no | none |
| `1a330d42-d249-5bf6-ba4b-066222bc5f5b`<br>`1a330d42-d249-5bf6-ba4b-066222bc5f5b` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #6 / 0.223847 | #12 / 4.536570 | #8 / 0.029040 | #8 / 0.255859 | fail | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #3 / 0.270063 | #4 / 6.242949 | #3 / 0.031498 | #9 / 0.255859 | fail | no | none |
| `92b627e2-da75-52c3-88b6-cdc01aa3b9ef`<br>`92b627e2-da75-52c3-88b6-cdc01aa3b9ef` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #20 / 0.172513 | #14 / 4.515895 | #11 / 0.026014 | #10 / 0.248047 | fail | no | none |
| `0318f8f9-9107-50ab-9afd-a65ee1687c77`<br>`0318f8f9-9107-50ab-9afd-a65ee1687c77` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #22 / 0.168974 | #13 / 4.520596 | #12 / 0.025894 | #11 / 0.248047 | fail | no | none |
| `0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6`<br>`0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #12 / 0.191111 | #7 / 5.691304 | #9 / 0.028814 | #12 / 0.223633 | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #30 / 0.149191 | #10 / 4.989362 | #14 / 0.025397 | #13 / 0.212891 | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #5 / 0.237120 | #11 / 4.601133 | #7 / 0.029469 | #14 / 0.210938 | fail | no | none |
| `20575c0a-658b-508a-a009-60706b3fde3c`<br>`20575c0a-658b-508a-a009-60706b3fde3c` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #11 / 0.193883 | #35 / 1.470509 | #15 / 0.024611 | #15 / 0.202148 | fail | no | none |
| `40b84d12-bb43-5dc3-a182-d80b51693330`<br>`40b84d12-bb43-5dc3-a182-d80b51693330` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #10 / 0.196465 | — | — | — | fail | no | none |
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | #13 / 0.189054 | — | — | — | fail | no | none |
| `6a0fb733-bff0-55d1-a5e7-d322ef9e53a9`<br>`6a0fb733-bff0-55d1-a5e7-d322ef9e53a9` | `family.training.matrix`<br>`doc.training.matrix.v1` | #14 / 0.188460 | — | — | — | fail | no | none |
| `fc1749ce-678f-5b79-9a27-41ca33d2043c`<br>`fc1749ce-678f-5b79-9a27-41ca33d2043c` | `family.medication.prn`<br>`doc.medication.prn.v1` | #15 / 0.187395 | — | — | — | fail | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #16 / 0.180604 | #30 / 2.233172 | — | — | fail | no | none |
| `799b04a0-74e1-5134-a911-0c2ccbda4c15`<br>`799b04a0-74e1-5134-a911-0c2ccbda4c15` | `family.medication.administration`<br>`doc.medication.administration.v2` | #17 / 0.178496 | #38 / 1.027548 | — | — | fail | no | none |
| `ac335280-6bca-5150-bd9b-db2d198ca588`<br>`ac335280-6bca-5150-bd9b-db2d198ca588` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #18 / 0.175973 | — | — | — | fail | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #19 / 0.172688 | #40 / 0.927905 | — | — | fail | no | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | #21 / 0.170688 | #26 / 2.707296 | — | — | fail | no | none |
| `b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef`<br>`b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef` | `family.medication.administration`<br>`doc.medication.administration.v2` | #23 / 0.168871 | — | — | — | fail | no | none |
| `8d8de832-6d4c-5368-b209-2ece5159b021`<br>`8d8de832-6d4c-5368-b209-2ece5159b021` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #24 / 0.166903 | — | — | — | fail | no | none |
| `47a813db-42a0-5b2b-9631-4c30ef6d0306`<br>`47a813db-42a0-5b2b-9631-4c30ef6d0306` | `family.medication.storage`<br>`doc.medication.storage.v1` | #25 / 0.164708 | — | — | — | fail | no | none |
| `b3036236-deaa-5719-ad41-3c5d87bbe7d8`<br>`b3036236-deaa-5719-ad41-3c5d87bbe7d8` | `family.training.fire`<br>`doc.training.fire.v1` | #26 / 0.159359 | #32 / 1.749125 | — | — | fail | no | none |
| `5a0ad7a9-b4c1-5072-a3b8-d527805bad81`<br>`5a0ad7a9-b4c1-5072-a3b8-d527805bad81` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #27 / 0.155667 | #31 / 2.093636 | — | — | fail | no | none |
| `6ba08511-5e10-530d-9a62-17ffed9e9bc4`<br>`6ba08511-5e10-530d-9a62-17ffed9e9bc4` | `family.training.induction`<br>`doc.training.induction.v1` | #28 / 0.151282 | #20 / 3.257886 | — | — | fail | no | none |
| `3e50e8ee-575c-52c9-a368-f1c6d1c814e1`<br>`3e50e8ee-575c-52c9-a368-f1c6d1c814e1` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #29 / 0.151174 | #29 / 2.399519 | — | — | fail | no | none |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #32 / 0.147218 | #33 / 1.563094 | — | — | fail | no | none |
| `249cc883-6c9a-5099-bdbb-974f04227e23`<br>`249cc883-6c9a-5099-bdbb-974f04227e23` | `family.complaints.form`<br>`doc.complaints.form.v1` | #33 / 0.145833 | — | — | — | fail | no | none |
| `801b4c5b-787b-5e04-99ca-83dd8844448d`<br>`801b4c5b-787b-5e04-99ca-83dd8844448d` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #34 / 0.143790 | — | — | — | fail | no | none |
| `aeb0ea01-92b2-5418-ad27-c95cacb3b030`<br>`aeb0ea01-92b2-5418-ad27-c95cacb3b030` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #35 / 0.143421 | — | — | — | fail | no | none |
| `ad9c7253-2c23-5a18-bb60-bcfc0859e149`<br>`ad9c7253-2c23-5a18-bb60-bcfc0859e149` | `family.payroll.mileage`<br>`doc.payroll.mileage.v1` | #36 / 0.142950 | — | — | — | fail | no | none |
| `635ff5e9-ecb1-559b-8683-4b7a96ea7bd9`<br>`635ff5e9-ecb1-559b-8683-4b7a96ea7bd9` | `family.fire.drills`<br>`doc.fire.drills.v2` | #37 / 0.141505 | — | — | — | fail | no | none |
| `42e10f18-8de2-53bd-8487-f46c454bf735`<br>`42e10f18-8de2-53bd-8487-f46c454bf735` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #38 / 0.139341 | #21 / 3.217190 | — | — | fail | no | none |
| `f1b2325d-4bb3-581b-8d14-7b8cdd43f216`<br>`f1b2325d-4bb3-581b-8d14-7b8cdd43f216` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #39 / 0.138343 | — | — | — | fail | no | none |
| `d24f4e43-6251-56d5-b470-c23242fe6873`<br>`d24f4e43-6251-56d5-b470-c23242fe6873` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | #40 / 0.136836 | #15 / 4.166176 | — | — | fail | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | — | #8 / 5.555110 | — | — | fail | no | none |
| `5cf87b03-5514-55ae-9cac-0aa6b7c572d3`<br>`5cf87b03-5514-55ae-9cac-0aa6b7c572d3` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | — | #16 / 3.952489 | — | — | fail | no | none |
| `ee3bb1bd-f03f-5314-b408-a1895aaadc2e`<br>`ee3bb1bd-f03f-5314-b408-a1895aaadc2e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | — | #17 / 3.605339 | — | — | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | — | #18 / 3.438007 | — | — | fail | no | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | — | #19 / 3.431891 | — | — | fail | no | none |
| `f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e`<br>`f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | — | #23 / 3.002573 | — | — | fail | no | none |
| `919b1651-7a62-5792-b47f-6ac4fc784017`<br>`919b1651-7a62-5792-b47f-6ac4fc784017` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | — | #24 / 2.953130 | — | — | fail | no | none |
| `65dda7f5-3688-515f-8d78-25e87c41a7e0`<br>`65dda7f5-3688-515f-8d78-25e87c41a7e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #25 / 2.848401 | — | — | fail | no | none |
| `ccc94945-e377-526e-93c2-5fd324619661`<br>`ccc94945-e377-526e-93c2-5fd324619661` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | — | #27 / 2.626631 | — | — | fail | no | none |
| `40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc`<br>`40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | — | #28 / 2.616443 | — | — | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | — | #34 / 1.482170 | — | — | fail | no | none |
| `955ca35a-ad9d-57fb-8c12-e79c9190c2cd`<br>`955ca35a-ad9d-57fb-8c12-e79c9190c2cd` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #36 / 1.156917 | — | — | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #37 / 1.130793 | — | — | fail | no | none |
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #39 / 0.978643 | — | — | fail | no | none |

### `health-safety.moving-handling.compare` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did the old policy say two staff for every hoist?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did the old policy say two staff for every hoist?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "old policy"
    }
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did the old policy say two staff for every hoist?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "old policy"
    }
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.hoist.v2-assessed` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v2` | documents/health-safety/moving-handling-v2.md |
| COMPARISON | `health-safety.hoist.v1-universal-two` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v1` | documents/health-safety/moving-handling-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.moving-handling.compare` / `compare`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare old and current requirements for two-person hoist transfers.
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare old and current requirements for two-person hoist transfers."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare old and current requirements for two-person hoist transfers."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.hoist.v2-assessed` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v2` | documents/health-safety/moving-handling-v2.md |
| COMPARISON | `health-safety.hoist.v1-universal-two` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v1` | documents/health-safety/moving-handling-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.moving-handling.compare` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How did the hoist staffing rule change from the previous policy?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How did the hoist staffing rule change from the previous policy?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How did the hoist staffing rule change from the previous policy?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.hoist.v2-assessed` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v2` | documents/health-safety/moving-handling-v2.md |
| COMPARISON | `health-safety.hoist.v1-universal-two` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v1` | documents/health-safety/moving-handling-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.moving-handling.current-staffing` / `assessment`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What decides whether one or two trained staff perform a hoist transfer?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What decides whether one or two trained staff perform a hoist transfer?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What decides whether one or two trained staff perform a hoist transfer?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.hoist.assessment-controls-staffing` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v2` | documents/health-safety/moving-handling-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.moving-handling.current-staffing` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is two carers always the rule for using a hoist?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Is two carers always the rule for using a hoist?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Is two carers always the rule for using a hoist?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.hoist.assessment-controls-staffing` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v2` | documents/health-safety/moving-handling-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `health-safety.moving-handling.current-staffing` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do all hoist transfers require two staff under the current policy?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do all hoist transfers require two staff under the current policy?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do all hoist transfers require two staff under the current policy?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `health-safety.hoist.assessment-controls-staffing` | `family.health-safety.moving-handling` | `doc.health-safety.moving-handling.v2` | documents/health-safety/moving-handling-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.annual-leave.compare` / `allowance`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How did the leave allowance and booking notice change?
- Covered EvidenceUnits: `hr.leave.compare-old, hr.leave.compare-current`
- Metrics: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How did the leave allowance and booking notice change?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How did the leave allowance and booking notice change?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.compare-current` | `family.hr.annual-leave` | `doc.hr.annual-leave.v2` | documents/hr/annual-leave-v2.md |
| COMPARISON | `hr.leave.compare-old` | `family.hr.annual-leave` | `doc.hr.annual-leave.v1` | documents/hr/annual-leave-v1.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=3 → Final evidence=3

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `3175f7bd-0838-5056-a1da-341d951720ed`<br>`3175f7bd-0838-5056-a1da-341d951720ed` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #1 / 0.450441 | #3 / 6.424794 | #2 / 0.032266 | #1 / 0.570312 | pass | yes | hr.leave.compare-current |
| `ebda80a6-77c7-557b-9450-fbddfdb16e02`<br>`ebda80a6-77c7-557b-9450-fbddfdb16e02` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #2 / 0.407409 | #1 / 7.260410 | #1 / 0.032522 | #2 / 0.427734 | pass | yes | none |
| `f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0`<br>`f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | #3 / 0.342018 | #2 / 6.677679 | #3 / 0.032002 | #3 / 0.349609 | pass | yes | none |
| `65dda7f5-3688-515f-8d78-25e87c41a7e0`<br>`65dda7f5-3688-515f-8d78-25e87c41a7e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | #4 / 0.275136 | #6 / 3.459155 | #5 / 0.030777 | #4 / 0.267578 | fail | no | none |
| `1f7baac6-5792-5b2a-9399-26ad4c21d6e4`<br>`1f7baac6-5792-5b2a-9399-26ad4c21d6e4` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #8 / 0.223663 | #7 / 3.311565 | #6 / 0.029631 | #5 / 0.267578 | fail | no | none |
| `919b1651-7a62-5792-b47f-6ac4fc784017`<br>`919b1651-7a62-5792-b47f-6ac4fc784017` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | #7 / 0.224899 | #8 / 2.962533 | #7 / 0.029631 | #6 / 0.255859 | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #14 / 0.161385 | #27 / 0.626872 | #13 / 0.025008 | #7 / 0.242188 | fail | no | none |
| `10c0d44a-0caf-50df-a02a-2ff58404be9d`<br>`10c0d44a-0caf-50df-a02a-2ff58404be9d` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | #10 / 0.207269 | #28 / 0.619154 | #12 / 0.025649 | #8 / 0.241211 | fail | no | none |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #25 / 0.128905 | #9 / 2.600572 | #11 / 0.026257 | #9 / 0.235352 | fail | no | none |
| `40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc`<br>`40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | #6 / 0.234019 | #4 / 4.362410 | #4 / 0.030777 | #10 / 0.232422 | fail | no | none |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #28 / 0.119336 | #5 / 4.127763 | #10 / 0.026748 | #11 / 0.232422 | fail | no | none |
| `893f68e3-e8d2-5acd-9a73-8f30912e2431`<br>`893f68e3-e8d2-5acd-9a73-8f30912e2431` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | #22 / 0.148025 | #26 / 0.654980 | #15 / 0.023823 | #12 / 0.221680 | fail | no | none |
| `5a87d328-f076-5953-aa2e-8d7963341f74`<br>`5a87d328-f076-5953-aa2e-8d7963341f74` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #15 / 0.160349 | #31 / 0.468131 | #14 / 0.024322 | #13 / 0.211914 | fail | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #13 / 0.162650 | #15 / 1.494644 | #9 / 0.027032 | #14 / 0.202148 | fail | no | none |
| `3e50e8ee-575c-52c9-a368-f1c6d1c814e1`<br>`3e50e8ee-575c-52c9-a368-f1c6d1c814e1` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #16 / 0.152763 | #11 / 2.221685 | #8 / 0.027242 | #15 / 0.199219 | fail | no | none |
| `aeb0ea01-92b2-5418-ad27-c95cacb3b030`<br>`aeb0ea01-92b2-5418-ad27-c95cacb3b030` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #5 / 0.256535 | — | — | — | fail | no | none |
| `ad9c7253-2c23-5a18-bb60-bcfc0859e149`<br>`ad9c7253-2c23-5a18-bb60-bcfc0859e149` | `family.payroll.mileage`<br>`doc.payroll.mileage.v1` | #9 / 0.210145 | — | — | — | fail | no | none |
| `2dc51247-e552-5a57-91c3-9408e34f5d94`<br>`2dc51247-e552-5a57-91c3-9408e34f5d94` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #11 / 0.178261 | — | — | — | fail | no | none |
| `42e10f18-8de2-53bd-8487-f46c454bf735`<br>`42e10f18-8de2-53bd-8487-f46c454bf735` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #12 / 0.168339 | — | — | — | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #17 / 0.152435 | — | — | — | fail | no | none |
| `6a0fb733-bff0-55d1-a5e7-d322ef9e53a9`<br>`6a0fb733-bff0-55d1-a5e7-d322ef9e53a9` | `family.training.matrix`<br>`doc.training.matrix.v1` | #18 / 0.152015 | — | — | — | fail | no | none |
| `85950010-d571-5bd3-9c8e-78b2687219d7`<br>`85950010-d571-5bd3-9c8e-78b2687219d7` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | #19 / 0.151521 | #39 / 0.287024 | — | — | fail | no | none |
| `5cf87b03-5514-55ae-9cac-0aa6b7c572d3`<br>`5cf87b03-5514-55ae-9cac-0aa6b7c572d3` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #20 / 0.150466 | — | — | — | fail | no | none |
| `6ba08511-5e10-530d-9a62-17ffed9e9bc4`<br>`6ba08511-5e10-530d-9a62-17ffed9e9bc4` | `family.training.induction`<br>`doc.training.induction.v1` | #21 / 0.150231 | — | — | — | fail | no | none |
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #23 / 0.145109 | #40 / 0.264122 | — | — | fail | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #24 / 0.133387 | — | — | — | fail | no | none |
| `f4b9f291-51c7-5e35-9335-b7e3dd2b37ef`<br>`f4b9f291-51c7-5e35-9335-b7e3dd2b37ef` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #26 / 0.121712 | — | — | — | fail | no | none |
| `5a0ad7a9-b4c1-5072-a3b8-d527805bad81`<br>`5a0ad7a9-b4c1-5072-a3b8-d527805bad81` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #27 / 0.119936 | — | — | — | fail | no | none |
| `1a330d42-d249-5bf6-ba4b-066222bc5f5b`<br>`1a330d42-d249-5bf6-ba4b-066222bc5f5b` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #29 / 0.117695 | — | — | — | fail | no | none |
| `b1b209d9-8945-557c-9456-0649dd6eb76a`<br>`b1b209d9-8945-557c-9456-0649dd6eb76a` | `family.fire.peep`<br>`doc.fire.peep.v1` | #30 / 0.116755 | — | — | — | fail | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #31 / 0.112986 | #34 / 0.406753 | — | — | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #32 / 0.112738 | #32 / 0.467146 | — | — | fail | no | none |
| `635ff5e9-ecb1-559b-8683-4b7a96ea7bd9`<br>`635ff5e9-ecb1-559b-8683-4b7a96ea7bd9` | `family.fire.drills`<br>`doc.fire.drills.v2` | #33 / 0.111677 | — | — | — | fail | no | none |
| `955ca35a-ad9d-57fb-8c12-e79c9190c2cd`<br>`955ca35a-ad9d-57fb-8c12-e79c9190c2cd` | `family.visitors.general`<br>`doc.visitors.general.v1` | #34 / 0.107724 | #16 / 1.046932 | — | — | fail | no | none |
| `b3036236-deaa-5719-ad41-3c5d87bbe7d8`<br>`b3036236-deaa-5719-ad41-3c5d87bbe7d8` | `family.training.fire`<br>`doc.training.fire.v1` | #35 / 0.106915 | — | — | — | fail | no | none |
| `249cc883-6c9a-5099-bdbb-974f04227e23`<br>`249cc883-6c9a-5099-bdbb-974f04227e23` | `family.complaints.form`<br>`doc.complaints.form.v1` | #36 / 0.106231 | — | — | — | fail | no | none |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #37 / 0.101853 | — | — | — | fail | no | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | #38 / 0.098353 | #30 / 0.504668 | — | — | fail | no | none |
| `3ffac08e-eebd-5bf7-963c-116ad06e0312`<br>`3ffac08e-eebd-5bf7-963c-116ad06e0312` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #39 / 0.096115 | — | — | — | fail | no | none |
| `3cc16b3c-7d04-53a9-a273-eddea88a3ccb`<br>`3cc16b3c-7d04-53a9-a273-eddea88a3ccb` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #40 / 0.094956 | — | — | — | fail | no | none |
| `ee3b92cf-7201-50f5-9315-841d5bceb277`<br>`ee3b92cf-7201-50f5-9315-841d5bceb277` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | — | #10 / 2.566387 | — | — | fail | no | none |
| `b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef`<br>`b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef` | `family.medication.administration`<br>`doc.medication.administration.v2` | — | #12 / 1.934309 | — | — | fail | no | none |
| `f1b2325d-4bb3-581b-8d14-7b8cdd43f216`<br>`f1b2325d-4bb3-581b-8d14-7b8cdd43f216` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | — | #13 / 1.678700 | — | — | fail | no | none |
| `799b04a0-74e1-5134-a911-0c2ccbda4c15`<br>`799b04a0-74e1-5134-a911-0c2ccbda4c15` | `family.medication.administration`<br>`doc.medication.administration.v2` | — | #14 / 1.508542 | — | — | fail | no | none |
| `ee3bb1bd-f03f-5314-b408-a1895aaadc2e`<br>`ee3bb1bd-f03f-5314-b408-a1895aaadc2e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | — | #17 / 0.961482 | — | — | fail | no | none |
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | — | #18 / 0.948376 | — | — | fail | no | none |
| `018c7c48-f558-5416-8a50-2043b3d3b7b8`<br>`018c7c48-f558-5416-8a50-2043b3d3b7b8` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #19 / 0.864908 | — | — | fail | no | none |
| `fc1749ce-678f-5b79-9a27-41ca33d2043c`<br>`fc1749ce-678f-5b79-9a27-41ca33d2043c` | `family.medication.prn`<br>`doc.medication.prn.v1` | — | #20 / 0.853001 | — | — | fail | no | none |
| `14ab94b0-4ade-5c5c-b5bd-77eae8daf94d`<br>`14ab94b0-4ade-5c5c-b5bd-77eae8daf94d` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #21 / 0.799483 | — | — | fail | no | none |
| `256e756b-7110-5070-9432-97bb1923a202`<br>`256e756b-7110-5070-9432-97bb1923a202` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | — | #22 / 0.744946 | — | — | fail | no | none |
| `be5c3624-95a2-5d5d-9f05-a9fb635d68a6`<br>`be5c3624-95a2-5d5d-9f05-a9fb635d68a6` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | — | #23 / 0.712874 | — | — | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | — | #24 / 0.683472 | — | — | fail | no | none |
| `20575c0a-658b-508a-a009-60706b3fde3c`<br>`20575c0a-658b-508a-a009-60706b3fde3c` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | — | #25 / 0.669827 | — | — | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | — | #29 / 0.590294 | — | — | fail | no | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | — | #33 / 0.460227 | — | — | fail | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | — | #35 / 0.387199 | — | — | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | — | #36 / 0.376553 | — | — | fail | no | none |
| `f193cb26-bd92-5fb8-a0b1-ba2c829f658b`<br>`f193cb26-bd92-5fb8-a0b1-ba2c829f658b` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | — | #37 / 0.348628 | — | — | fail | no | none |
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | — | #38 / 0.301907 | — | — | fail | no | none |

#### COMPARISON

Candidate funnel: Dense=13 → Sparse=12 → Unique after RRF=13 → Reranker=13 → Threshold=1 → Final evidence=1

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `72a23d19-05d6-5fe0-8918-f0442b392f2d`<br>`72a23d19-05d6-5fe0-8918-f0442b392f2d` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v1` | #1 / 0.400056 | #1 / 4.557951 | #1 / 0.032787 | #1 / 0.458984 | pass | yes | hr.leave.compare-old |
| `2d65a97b-9023-5d91-8a35-5d78b3934084`<br>`2d65a97b-9023-5d91-8a35-5d78b3934084` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #2 / 0.220098 | #2 / 2.292002 | #2 / 0.032258 | #2 / 0.257812 | fail | no | none |
| `3f7a6eba-f048-598f-8340-aed3172f8361`<br>`3f7a6eba-f048-598f-8340-aed3172f8361` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | #5 / 0.147457 | #8 / 0.250991 | #5 / 0.030090 | #3 / 0.225586 | fail | no | none |
| `817f4ea7-115c-58d5-9a46-dbaef434a1f2`<br>`817f4ea7-115c-58d5-9a46-dbaef434a1f2` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #3 / 0.161833 | #5 / 0.604340 | #4 / 0.031258 | #4 / 0.211914 | fail | no | none |
| `254c3933-94f2-510b-aa2d-9ab1942de8a7`<br>`254c3933-94f2-510b-aa2d-9ab1942de8a7` | `family.medication.administration`<br>`doc.medication.administration.v1` | #12 / 0.065212 | #6 / 0.537436 | #10 / 0.029040 | #5 / 0.208984 | fail | no | none |
| `13c0e838-be23-5fac-a03d-3c9478b3f41f`<br>`13c0e838-be23-5fac-a03d-3c9478b3f41f` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #11 / 0.081493 | — | #13 / 0.014085 | #6 / 0.202148 | fail | no | none |
| `5b68e998-3a65-5808-bc5b-73e28613adc9`<br>`5b68e998-3a65-5808-bc5b-73e28613adc9` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #4 / 0.157460 | #3 / 1.297836 | #3 / 0.031498 | #7 / 0.198242 | fail | no | none |
| `07ab0a1c-21e8-5a07-b4ed-3110898b35ca`<br>`07ab0a1c-21e8-5a07-b4ed-3110898b35ca` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #8 / 0.095582 | #7 / 0.275021 | #8 / 0.029631 | #8 / 0.198242 | fail | no | none |
| `3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f`<br>`3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f` | `family.fire.drills`<br>`doc.fire.drills.v1` | #7 / 0.098328 | #10 / 0.070149 | #9 / 0.029211 | #9 / 0.198242 | fail | no | none |
| `11a5a524-8a6e-5f08-9a8c-4c470aae9086`<br>`11a5a524-8a6e-5f08-9a8c-4c470aae9086` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #10 / 0.084104 | #4 / 0.760098 | #6 / 0.029911 | #10 / 0.195312 | fail | no | none |
| `14b1c8c3-190a-531d-b13e-5666a56b9ac7`<br>`14b1c8c3-190a-531d-b13e-5666a56b9ac7` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #6 / 0.134326 | #9 / 0.243645 | #7 / 0.029644 | #11 / 0.189453 | fail | no | none |
| `80ddc068-0955-5bb4-92c0-4b1586792c84`<br>`80ddc068-0955-5bb4-92c0-4b1586792c84` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #9 / 0.087122 | #12 / 0.044103 | #11 / 0.028382 | #12 / 0.184570 | fail | no | none |
| `369ceff0-142f-5215-817d-ddafe27e7ace`<br>`369ceff0-142f-5215-817d-ddafe27e7ace` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #13 / 0.050940 | #11 / 0.058543 | #12 / 0.027783 | #13 / 0.174805 | fail | no | none |

### `hr.annual-leave.compare` / `change`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What changed for booking a week off?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What changed for booking a week off?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What changed for booking a week off?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.compare-current` | `family.hr.annual-leave` | `doc.hr.annual-leave.v2` | documents/hr/annual-leave-v2.md |
| COMPARISON | `hr.leave.compare-old` | `family.hr.annual-leave` | `doc.hr.annual-leave.v1` | documents/hr/annual-leave-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.annual-leave.compare` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare the current annual leave notice rule with the previous policy.
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare the current annual leave notice rule with the previous policy."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare the current annual leave notice rule with the previous policy."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.compare-current` | `family.hr.annual-leave` | `doc.hr.annual-leave.v2` | documents/hr/annual-leave-v2.md |
| COMPARISON | `hr.leave.compare-old` | `family.hr.annual-leave` | `doc.hr.annual-leave.v1` | documents/hr/annual-leave-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.annual-leave.current-notice` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How early should I book five days off?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How early should I book five days off?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How early should I book five days off?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.v2-six-weeks` | `family.hr.annual-leave` | `doc.hr.annual-leave.v2` | documents/hr/annual-leave-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.annual-leave.current-notice` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How much notice do I need now for a week of annual leave?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How much notice do I need now for a week of annual leave?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How much notice do I need now for a week of annual leave?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.v2-six-weeks` | `family.hr.annual-leave` | `doc.hr.annual-leave.v2` | documents/hr/annual-leave-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.annual-leave.current-notice` / `table`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What is the current notice period for five working days' holiday?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What is the current notice period for five working days' holiday?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What is the current notice period for five working days' holiday?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.v2-six-weeks` | `family.hr.annual-leave` | `doc.hr.annual-leave.v2` | documents/hr/annual-leave-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.annual-leave.valid-at-date` / `contrast`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Was the allowance 28 days in 2024?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Was the allowance 28 days in 2024?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "2024"
    }
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Was the allowance 28 days in 2024?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "2024"
    }
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.v1-allowance` | `family.hr.annual-leave` | `doc.hr.annual-leave.v1` | documents/hr/annual-leave-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.annual-leave.valid-at-date` / `dated`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How much leave did full-time staff receive in June 2024?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How much leave did full-time staff receive in June 2024?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "June 2024"
    }
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How much leave did full-time staff receive in June 2024?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "June 2024"
    }
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.v1-allowance` | `family.hr.annual-leave` | `doc.hr.annual-leave.v1` | documents/hr/annual-leave-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.annual-leave.valid-at-date` / `old`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What was the annual leave allowance under version 1?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What was the annual leave allowance under version 1?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "version 1"
    }
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What was the annual leave allowance under version 1?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "version 1"
    }
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.leave.v1-allowance` | `family.hr.annual-leave` | `doc.hr.annual-leave.v1` | documents/hr/annual-leave-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.disciplinary.suspension-neutral` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Does being suspended mean the allegation is proven?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Does being suspended mean the allegation is proven?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Does being suspended mean the allegation is proven?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.disciplinary.suspension` | `family.hr.disciplinary` | `doc.hr.disciplinary.v1` | documents/hr/disciplinary-policy.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.disciplinary.suspension-neutral` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is suspension a disciplinary punishment?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Is suspension a disciplinary punishment?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Is suspension a disciplinary punishment?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.disciplinary.suspension` | `family.hr.disciplinary` | `doc.hr.disciplinary.v1` | documents/hr/disciplinary-policy.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.disciplinary.suspension-neutral` / `review`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How often must a precautionary suspension be reviewed?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How often must a precautionary suspension be reviewed?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How often must a precautionary suspension be reviewed?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.disciplinary.suspension` | `family.hr.disciplinary` | `doc.hr.disciplinary.v1` | documents/hr/disciplinary-policy.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.lone-worker.coventry-overdue` / `alias`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What happens when a Coventry community worker misses their check-out?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Coventry"
    ],
    "retrieval_queries": [
      "What happens when a Coventry community worker misses their check-out?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Coventry"
    ],
    "retrieval_queries": [
      "What happens when a Coventry community worker misses their check-out?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.lone-worker.overdue-sequence` | `family.hr.lone-worker-welfare` | `doc.hr.lone-worker-welfare.v1` | documents/hr/midlands-lone-worker-welfare.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `hr.lone-worker.coventry-overdue` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Our lone worker is 15 minutes late checking in — what next?
- Covered EvidenceUnits: `hr.lone-worker.overdue-sequence`
- Metrics: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Our lone worker is 15 minutes late checking in — what next?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Our lone worker is 15 minutes late checking in — what next?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.lone-worker.overdue-sequence` | `family.hr.lone-worker-welfare` | `doc.hr.lone-worker-welfare.v1` | documents/hr/midlands-lone-worker-welfare.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=5 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `f193cb26-bd92-5fb8-a0b1-ba2c829f658b`<br>`f193cb26-bd92-5fb8-a0b1-ba2c829f658b` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #1 / 0.604561 | #1 / 16.600967 | #1 / 0.032787 | #1 / 0.929688 | pass | yes | hr.lone-worker.overdue-sequence |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #2 / 0.487191 | #2 / 12.221460 | #2 / 0.032258 | #2 / 0.542969 | pass | yes | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #3 / 0.455853 | #28 / 3.318815 | #7 / 0.027237 | #3 / 0.535156 | pass | yes | none |
| `b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef`<br>`b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.317815 | #9 / 6.236403 | #4 / 0.029644 | #4 / 0.378906 | pass | yes | none |
| `1f7baac6-5792-5b2a-9399-26ad4c21d6e4`<br>`1f7baac6-5792-5b2a-9399-26ad4c21d6e4` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #4 / 0.345119 | #6 / 6.980538 | #3 / 0.030777 | #5 / 0.339844 | pass | yes | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #13 / 0.281676 | #23 / 3.703609 | #10 / 0.025747 | #6 / 0.328125 | fail | no | none |
| `893f68e3-e8d2-5acd-9a73-8f30912e2431`<br>`893f68e3-e8d2-5acd-9a73-8f30912e2431` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | #37 / 0.223448 | #5 / 7.015209 | #11 / 0.025694 | #7 / 0.318359 | fail | no | none |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #12 / 0.286384 | #12 / 5.373512 | #5 / 0.027778 | #8 / 0.314453 | fail | no | none |
| `2dc51247-e552-5a57-91c3-9408e34f5d94`<br>`2dc51247-e552-5a57-91c3-9408e34f5d94` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #14 / 0.280576 | #20 / 4.081731 | #9 / 0.026014 | #9 / 0.306641 | fail | no | none |
| `799b04a0-74e1-5134-a911-0c2ccbda4c15`<br>`799b04a0-74e1-5134-a911-0c2ccbda4c15` | `family.medication.administration`<br>`doc.medication.administration.v2` | #18 / 0.274329 | #8 / 6.379434 | #6 / 0.027526 | #10 / 0.302734 | fail | no | none |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #7 / 0.315407 | #34 / 2.794283 | #12 / 0.025564 | #11 / 0.296875 | fail | no | none |
| `14ab94b0-4ade-5c5c-b5bd-77eae8daf94d`<br>`14ab94b0-4ade-5c5c-b5bd-77eae8daf94d` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #11 / 0.292598 | #30 / 3.097683 | #13 / 0.025196 | #12 / 0.275391 | fail | no | none |
| `5a87d328-f076-5953-aa2e-8d7963341f74`<br>`5a87d328-f076-5953-aa2e-8d7963341f74` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #22 / 0.260442 | #7 / 6.537991 | #8 / 0.027120 | #13 / 0.255859 | fail | no | none |
| `fc1749ce-678f-5b79-9a27-41ca33d2043c`<br>`fc1749ce-678f-5b79-9a27-41ca33d2043c` | `family.medication.prn`<br>`doc.medication.prn.v1` | #24 / 0.256888 | #17 / 4.883999 | #15 / 0.024892 | #14 / 0.245117 | fail | no | none |
| `6ba08511-5e10-530d-9a62-17ffed9e9bc4`<br>`6ba08511-5e10-530d-9a62-17ffed9e9bc4` | `family.training.induction`<br>`doc.training.induction.v1` | #29 / 0.243992 | #13 / 5.246195 | #14 / 0.024935 | #15 / 0.238281 | fail | no | none |
| `256e756b-7110-5070-9432-97bb1923a202`<br>`256e756b-7110-5070-9432-97bb1923a202` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #5 / 0.335596 | — | — | — | fail | no | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #8 / 0.311369 | — | — | — | fail | no | none |
| `ee3b92cf-7201-50f5-9315-841d5bceb277`<br>`ee3b92cf-7201-50f5-9315-841d5bceb277` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #9 / 0.308029 | — | — | — | fail | no | none |
| `f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e`<br>`f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #10 / 0.295537 | #37 / 2.501713 | — | — | fail | no | none |
| `635ff5e9-ecb1-559b-8683-4b7a96ea7bd9`<br>`635ff5e9-ecb1-559b-8683-4b7a96ea7bd9` | `family.fire.drills`<br>`doc.fire.drills.v2` | #15 / 0.277824 | #27 / 3.470624 | — | — | fail | no | none |
| `ee3bb1bd-f03f-5314-b408-a1895aaadc2e`<br>`ee3bb1bd-f03f-5314-b408-a1895aaadc2e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #16 / 0.277469 | #35 / 2.719106 | — | — | fail | no | none |
| `3cc16b3c-7d04-53a9-a273-eddea88a3ccb`<br>`3cc16b3c-7d04-53a9-a273-eddea88a3ccb` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #17 / 0.275597 | — | — | — | fail | no | none |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #19 / 0.267569 | — | — | — | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #20 / 0.261524 | — | — | — | fail | no | none |
| `b3036236-deaa-5719-ad41-3c5d87bbe7d8`<br>`b3036236-deaa-5719-ad41-3c5d87bbe7d8` | `family.training.fire`<br>`doc.training.fire.v1` | #21 / 0.261084 | — | — | — | fail | no | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | #23 / 0.257644 | — | — | — | fail | no | none |
| `f1b2325d-4bb3-581b-8d14-7b8cdd43f216`<br>`f1b2325d-4bb3-581b-8d14-7b8cdd43f216` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #25 / 0.254966 | #21 / 4.070410 | — | — | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #26 / 0.254952 | — | — | — | fail | no | none |
| `955ca35a-ad9d-57fb-8c12-e79c9190c2cd`<br>`955ca35a-ad9d-57fb-8c12-e79c9190c2cd` | `family.visitors.general`<br>`doc.visitors.general.v1` | #27 / 0.251125 | #33 / 2.910535 | — | — | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #28 / 0.244150 | #24 / 3.657783 | — | — | fail | no | none |
| `ccc94945-e377-526e-93c2-5fd324619661`<br>`ccc94945-e377-526e-93c2-5fd324619661` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | #30 / 0.237978 | — | — | — | fail | no | none |
| `be5c3624-95a2-5d5d-9f05-a9fb635d68a6`<br>`be5c3624-95a2-5d5d-9f05-a9fb635d68a6` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | #31 / 0.235189 | — | — | — | fail | no | none |
| `aeb0ea01-92b2-5418-ad27-c95cacb3b030`<br>`aeb0ea01-92b2-5418-ad27-c95cacb3b030` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #32 / 0.234101 | #18 / 4.707696 | — | — | fail | no | none |
| `8d8de832-6d4c-5368-b209-2ece5159b021`<br>`8d8de832-6d4c-5368-b209-2ece5159b021` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #33 / 0.230849 | — | — | — | fail | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #34 / 0.230814 | #32 / 2.946804 | — | — | fail | no | none |
| `1839469e-5726-503f-a711-a010a97420fd`<br>`1839469e-5726-503f-a711-a010a97420fd` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #35 / 0.229197 | — | — | — | fail | no | none |
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | #36 / 0.226169 | — | — | — | fail | no | none |
| `b1b209d9-8945-557c-9456-0649dd6eb76a`<br>`b1b209d9-8945-557c-9456-0649dd6eb76a` | `family.fire.peep`<br>`doc.fire.peep.v1` | #38 / 0.220435 | — | — | — | fail | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #39 / 0.217440 | #22 / 3.905928 | — | — | fail | no | none |
| `5cf87b03-5514-55ae-9cac-0aa6b7c572d3`<br>`5cf87b03-5514-55ae-9cac-0aa6b7c572d3` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #40 / 0.211889 | #29 / 3.271141 | — | — | fail | no | none |
| `10c0d44a-0caf-50df-a02a-2ff58404be9d`<br>`10c0d44a-0caf-50df-a02a-2ff58404be9d` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #3 / 7.224404 | — | — | fail | no | none |
| `3175f7bd-0838-5056-a1da-341d951720ed`<br>`3175f7bd-0838-5056-a1da-341d951720ed` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #4 / 7.179223 | — | — | fail | no | none |
| `65dda7f5-3688-515f-8d78-25e87c41a7e0`<br>`65dda7f5-3688-515f-8d78-25e87c41a7e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #10 / 6.051150 | — | — | fail | no | none |
| `6a0fb733-bff0-55d1-a5e7-d322ef9e53a9`<br>`6a0fb733-bff0-55d1-a5e7-d322ef9e53a9` | `family.training.matrix`<br>`doc.training.matrix.v1` | — | #11 / 5.485010 | — | — | fail | no | none |
| `5a0ad7a9-b4c1-5072-a3b8-d527805bad81`<br>`5a0ad7a9-b4c1-5072-a3b8-d527805bad81` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | — | #14 / 5.172496 | — | — | fail | no | none |
| `3e50e8ee-575c-52c9-a368-f1c6d1c814e1`<br>`3e50e8ee-575c-52c9-a368-f1c6d1c814e1` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | — | #15 / 5.171027 | — | — | fail | no | none |
| `85950010-d571-5bd3-9c8e-78b2687219d7`<br>`85950010-d571-5bd3-9c8e-78b2687219d7` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | — | #16 / 5.069585 | — | — | fail | no | none |
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #19 / 4.187913 | — | — | fail | no | none |
| `f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0`<br>`f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #25 / 3.501925 | — | — | fail | no | none |
| `ebda80a6-77c7-557b-9450-fbddfdb16e02`<br>`ebda80a6-77c7-557b-9450-fbddfdb16e02` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #26 / 3.489698 | — | — | fail | no | none |
| `018c7c48-f558-5416-8a50-2043b3d3b7b8`<br>`018c7c48-f558-5416-8a50-2043b3d3b7b8` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #31 / 3.026393 | — | — | fail | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | — | #36 / 2.549086 | — | — | fail | no | none |
| `1a330d42-d249-5bf6-ba4b-066222bc5f5b`<br>`1a330d42-d249-5bf6-ba4b-066222bc5f5b` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | — | #38 / 2.368390 | — | — | fail | no | none |
| `ad9c7253-2c23-5a18-bb60-bcfc0859e149`<br>`ad9c7253-2c23-5a18-bb60-bcfc0859e149` | `family.payroll.mileage`<br>`doc.payroll.mileage.v1` | — | #39 / 2.213812 | — | — | fail | no | none |
| `3ffac08e-eebd-5bf7-963c-116ad06e0312`<br>`3ffac08e-eebd-5bf7-963c-116ad06e0312` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | — | #40 / 2.168068 | — | — | fail | no | none |

### `hr.lone-worker.coventry-overdue` / `timing`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When does the Midlands coordinator escalate an overdue welfare check?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Midlands"
    ],
    "retrieval_queries": [
      "When does the Midlands coordinator escalate an overdue welfare check?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Midlands"
    ],
    "retrieval_queries": [
      "When does the Midlands coordinator escalate an overdue welfare check?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `hr.lone-worker.overdue-sequence` | `family.hr.lone-worker-welfare` | `doc.hr.lone-worker-welfare.v1` | documents/hr/midlands-lone-worker-welfare.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `infection.outbreak.valid-before-withdrawal` / `contrast`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Was twice-daily symptom monitoring authoritative in January 2026?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Was twice-daily symptom monitoring authoritative in January 2026?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "January 2026"
    }
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Was twice-daily symptom monitoring authoritative in January 2026?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "January 2026"
    }
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `infection.outbreak.v2.twice-daily` | `family.infection.outbreak-management` | `doc.infection.outbreak-management.v2` | documents/infection-control/outbreak-management-v2-withdrawn.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `infection.outbreak.valid-before-withdrawal` / `dated`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What outbreak monitoring applied on 1 January 2026?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": "1016-01-01",
    "location_references": [],
    "retrieval_queries": [
      "What outbreak monitoring applied on 1 January 2026?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": null
  },
  "correct": false,
  "differences": [
    {
      "actual": "1016-01-01",
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": "2026-01-01",
      "field": "explicit_date"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": "2026-01-01",
    "location_references": [],
    "retrieval_queries": [
      "What outbreak monitoring applied on 1 January 2026?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `infection.outbreak.v2.twice-daily` | `family.infection.outbreak-management` | `doc.infection.outbreak-management.v2` | documents/infection-control/outbreak-management-v2-withdrawn.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `infection.outbreak.valid-before-withdrawal` / `historical`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Before it was withdrawn, what did outbreak version 2 require?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Before it was withdrawn, what did outbreak version 2 require?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "Before it was withdrawn"
    }
  },
  "correct": false,
  "differences": [
    {
      "actual": {
        "kind": "HISTORICAL_REFERENCE",
        "value": "Before it was withdrawn"
      },
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": {
        "kind": "HISTORICAL_REFERENCE",
        "value": "before it was withdrawn, outbreak version 2"
      },
      "field": "temporal_reference"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Before it was withdrawn, what did outbreak version 2 require?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "before it was withdrawn, outbreak version 2"
    }
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `infection.outbreak.v2.twice-daily` | `family.infection.outbreak-management` | `doc.infection.outbreak-management.v2` | documents/infection-control/outbreak-management-v2-withdrawn.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.controlled-drugs.current-discrepancy` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: The CD count is wrong — what do we do straight away?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "The CD count is wrong — what do we do straight away?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "The CD count is wrong — what do we do straight away?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.cd.immediate-escalation` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v2` | documents/medication/controlled-drugs-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.controlled-drugs.current-discrepancy` / `contrast`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Can a controlled drugs stock mismatch wait until shift end?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Can a controlled drugs stock mismatch wait until shift end?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Can a controlled drugs stock mismatch wait until shift end?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.cd.immediate-escalation` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v2` | documents/medication/controlled-drugs-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.controlled-drugs.current-discrepancy` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When must a controlled-drug discrepancy be escalated now?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "When must a controlled-drug discrepancy be escalated now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "When must a controlled-drug discrepancy be escalated now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.cd.immediate-escalation` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v2` | documents/medication/controlled-drugs-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.controlled-drugs.valid-at-date` / `contrast`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did the 2023 procedure allow reporting by the end of the shift?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did the 2023 procedure allow reporting by the end of the shift?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "2023 procedure"
    }
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did the 2023 procedure allow reporting by the end of the shift?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "2023 procedure"
    }
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.cd.v1.shift-end` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v1` | documents/medication/controlled-drugs-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.controlled-drugs.valid-at-date` / `dated`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: In January 2024, when did a controlled-drug discrepancy have to be reported?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "In January 2024, when did a controlled-drug discrepancy have to be reported?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "January 2024"
    }
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "In January 2024, when did a controlled-drug discrepancy have to be reported?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "January 2024"
    }
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.cd.v1.shift-end` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v1` | documents/medication/controlled-drugs-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.controlled-drugs.valid-at-date` / `historical`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What was the old CD stock discrepancy deadline?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What was the old CD stock discrepancy deadline?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "old"
    }
  },
  "correct": false,
  "differences": [
    {
      "actual": {
        "kind": "HISTORICAL_REFERENCE",
        "value": "old"
      },
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": {
        "kind": "HISTORICAL_REFERENCE",
        "value": "old CD stock discrepancy deadline"
      },
      "field": "temporal_reference"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What was the old CD stock discrepancy deadline?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "old CD stock discrepancy deadline"
    }
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.cd.v1.shift-end` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v1` | documents/medication/controlled-drugs-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.covert.capacity-requirements` / `abbreviation`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Does covert medication need an MCA and best-interests decision?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Does covert medication need an MCA and best-interests decision?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Does covert medication need an MCA and best-interests decision?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.covert.required-decisions` | `family.medication.covert` | `doc.medication.covert.v1` | documents/medication/covert-administration-policy.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.covert.capacity-requirements` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What approvals are required before medicine can be given covertly?
- Covered EvidenceUnits: `medication.covert.required-decisions`
- Metrics: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What approvals are required before medicine can be given covertly?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What approvals are required before medicine can be given covertly?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.covert.required-decisions` | `family.medication.covert` | `doc.medication.covert.v1` | documents/medication/covert-administration-policy.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=8 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | #1 / 0.581901 | #1 / 16.507647 | #1 / 0.032787 | #1 / 0.843750 | pass | yes | medication.covert.required-decisions |
| `799b04a0-74e1-5134-a911-0c2ccbda4c15`<br>`799b04a0-74e1-5134-a911-0c2ccbda4c15` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.441449 | #2 / 11.992738 | #2 / 0.032258 | #2 / 0.503906 | pass | yes | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | #5 / 0.392667 | #3 / 9.800572 | #3 / 0.031258 | #3 / 0.441406 | pass | yes | none |
| `fc1749ce-678f-5b79-9a27-41ca33d2043c`<br>`fc1749ce-678f-5b79-9a27-41ca33d2043c` | `family.medication.prn`<br>`doc.medication.prn.v1` | #3 / 0.417962 | #6 / 9.188005 | #4 / 0.031025 | #4 / 0.431641 | pass | yes | none |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #8 / 0.370737 | #5 / 9.319480 | #5 / 0.030090 | #5 / 0.402344 | pass | yes | none |
| `b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef`<br>`b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef` | `family.medication.administration`<br>`doc.medication.administration.v2` | #4 / 0.401055 | #10 / 7.589439 | #7 / 0.029911 | #6 / 0.388672 | pass | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #6 / 0.374274 | #7 / 8.289824 | #6 / 0.030077 | #7 / 0.363281 | pass | no | none |
| `1839469e-5726-503f-a711-a010a97420fd`<br>`1839469e-5726-503f-a711-a010a97420fd` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #10 / 0.327376 | #15 / 6.430093 | #11 / 0.027619 | #8 / 0.353516 | pass | no | none |
| `56745918-8c2b-5490-a300-4c18bf32a5c6`<br>`56745918-8c2b-5490-a300-4c18bf32a5c6` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #7 / 0.372307 | #22 / 4.642180 | #13 / 0.027120 | #9 / 0.328125 | fail | no | none |
| `1a330d42-d249-5bf6-ba4b-066222bc5f5b`<br>`1a330d42-d249-5bf6-ba4b-066222bc5f5b` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #9 / 0.349263 | #11 / 7.483384 | #8 / 0.028577 | #10 / 0.302734 | fail | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #24 / 0.213132 | #9 / 8.011988 | #15 / 0.026398 | #11 / 0.294922 | fail | no | none |
| `f85e71bc-4d62-57d9-b403-b13b1a9ff199`<br>`f85e71bc-4d62-57d9-b403-b13b1a9ff199` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #12 / 0.298028 | #17 / 6.074970 | #14 / 0.026876 | #12 / 0.283203 | fail | no | none |
| `aeb0ea01-92b2-5418-ad27-c95cacb3b030`<br>`aeb0ea01-92b2-5418-ad27-c95cacb3b030` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #25 / 0.204506 | #4 / 9.570769 | #12 / 0.027390 | #13 / 0.263672 | fail | no | none |
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | #11 / 0.318522 | #13 / 6.858134 | #10 / 0.027783 | #14 / 0.253906 | fail | no | none |
| `47a813db-42a0-5b2b-9631-4c30ef6d0306`<br>`47a813db-42a0-5b2b-9631-4c30ef6d0306` | `family.medication.storage`<br>`doc.medication.storage.v1` | #13 / 0.274687 | #8 / 8.172644 | #9 / 0.028405 | #15 / 0.243164 | fail | no | none |
| `0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6`<br>`0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #14 / 0.274481 | #39 / 2.364075 | — | — | fail | no | none |
| `d24f4e43-6251-56d5-b470-c23242fe6873`<br>`d24f4e43-6251-56d5-b470-c23242fe6873` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | #15 / 0.266576 | — | — | — | fail | no | none |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #16 / 0.256765 | #16 / 6.305388 | — | — | fail | no | none |
| `4d1f0d61-d751-52f0-87dd-0327ea89db4e`<br>`4d1f0d61-d751-52f0-87dd-0327ea89db4e` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | #17 / 0.242524 | #34 / 2.559713 | — | — | fail | no | none |
| `92b627e2-da75-52c3-88b6-cdc01aa3b9ef`<br>`92b627e2-da75-52c3-88b6-cdc01aa3b9ef` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #18 / 0.235619 | #32 / 2.741646 | — | — | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #19 / 0.231453 | #26 / 3.553411 | — | — | fail | no | none |
| `801b4c5b-787b-5e04-99ca-83dd8844448d`<br>`801b4c5b-787b-5e04-99ca-83dd8844448d` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #20 / 0.231074 | #21 / 4.806843 | — | — | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #21 / 0.228589 | #12 / 7.427447 | — | — | fail | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #22 / 0.225933 | #31 / 2.795061 | — | — | fail | no | none |
| `6a0fb733-bff0-55d1-a5e7-d322ef9e53a9`<br>`6a0fb733-bff0-55d1-a5e7-d322ef9e53a9` | `family.training.matrix`<br>`doc.training.matrix.v1` | #23 / 0.213198 | #29 / 3.126264 | — | — | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #26 / 0.203750 | — | — | — | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #27 / 0.200802 | #25 / 3.812605 | — | — | fail | no | none |
| `5cf87b03-5514-55ae-9cac-0aa6b7c572d3`<br>`5cf87b03-5514-55ae-9cac-0aa6b7c572d3` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #28 / 0.197052 | — | — | — | fail | no | none |
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #29 / 0.191127 | #19 / 5.420937 | — | — | fail | no | none |
| `40b84d12-bb43-5dc3-a182-d80b51693330`<br>`40b84d12-bb43-5dc3-a182-d80b51693330` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #30 / 0.190771 | — | — | — | fail | no | none |
| `f193cb26-bd92-5fb8-a0b1-ba2c829f658b`<br>`f193cb26-bd92-5fb8-a0b1-ba2c829f658b` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #31 / 0.189875 | — | — | — | fail | no | none |
| `8d8de832-6d4c-5368-b209-2ece5159b021`<br>`8d8de832-6d4c-5368-b209-2ece5159b021` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #32 / 0.184668 | — | — | — | fail | no | none |
| `0318f8f9-9107-50ab-9afd-a65ee1687c77`<br>`0318f8f9-9107-50ab-9afd-a65ee1687c77` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #33 / 0.182920 | — | — | — | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #34 / 0.178736 | — | — | — | fail | no | none |
| `1f7baac6-5792-5b2a-9399-26ad4c21d6e4`<br>`1f7baac6-5792-5b2a-9399-26ad4c21d6e4` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #35 / 0.177583 | #23 / 4.430169 | — | — | fail | no | none |
| `b1b209d9-8945-557c-9456-0649dd6eb76a`<br>`b1b209d9-8945-557c-9456-0649dd6eb76a` | `family.fire.peep`<br>`doc.fire.peep.v1` | #36 / 0.176082 | — | — | — | fail | no | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #37 / 0.175976 | — | — | — | fail | no | none |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #38 / 0.171103 | #38 / 2.376608 | — | — | fail | no | none |
| `3e50e8ee-575c-52c9-a368-f1c6d1c814e1`<br>`3e50e8ee-575c-52c9-a368-f1c6d1c814e1` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #39 / 0.170961 | — | — | — | fail | no | none |
| `f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e`<br>`f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #40 / 0.164536 | — | — | — | fail | no | none |
| `10c0d44a-0caf-50df-a02a-2ff58404be9d`<br>`10c0d44a-0caf-50df-a02a-2ff58404be9d` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #14 / 6.461824 | — | — | fail | no | none |
| `65dda7f5-3688-515f-8d78-25e87c41a7e0`<br>`65dda7f5-3688-515f-8d78-25e87c41a7e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #18 / 5.429531 | — | — | fail | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | — | #20 / 4.827263 | — | — | fail | no | none |
| `2dc51247-e552-5a57-91c3-9408e34f5d94`<br>`2dc51247-e552-5a57-91c3-9408e34f5d94` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | — | #24 / 3.824194 | — | — | fail | no | none |
| `919b1651-7a62-5792-b47f-6ac4fc784017`<br>`919b1651-7a62-5792-b47f-6ac4fc784017` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | — | #27 / 3.540569 | — | — | fail | no | none |
| `3175f7bd-0838-5056-a1da-341d951720ed`<br>`3175f7bd-0838-5056-a1da-341d951720ed` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #28 / 3.465283 | — | — | fail | no | none |
| `6ba08511-5e10-530d-9a62-17ffed9e9bc4`<br>`6ba08511-5e10-530d-9a62-17ffed9e9bc4` | `family.training.induction`<br>`doc.training.induction.v1` | — | #30 / 2.937233 | — | — | fail | no | none |
| `14ab94b0-4ade-5c5c-b5bd-77eae8daf94d`<br>`14ab94b0-4ade-5c5c-b5bd-77eae8daf94d` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #33 / 2.562503 | — | — | fail | no | none |
| `955ca35a-ad9d-57fb-8c12-e79c9190c2cd`<br>`955ca35a-ad9d-57fb-8c12-e79c9190c2cd` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #35 / 2.556921 | — | — | fail | no | none |
| `ad9c7253-2c23-5a18-bb60-bcfc0859e149`<br>`ad9c7253-2c23-5a18-bb60-bcfc0859e149` | `family.payroll.mileage`<br>`doc.payroll.mileage.v1` | — | #36 / 2.499537 | — | — | fail | no | none |
| `ac335280-6bca-5150-bd9b-db2d198ca588`<br>`ac335280-6bca-5150-bd9b-db2d198ca588` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | — | #37 / 2.490827 | — | — | fail | no | none |
| `5a0ad7a9-b4c1-5072-a3b8-d527805bad81`<br>`5a0ad7a9-b4c1-5072-a3b8-d527805bad81` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | — | #40 / 2.175523 | — | — | fail | no | none |

### `medication.covert.capacity-requirements` / `refusal`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Can we hide medicine in food because a resident refuses it?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Can we hide medicine in food because a resident refuses it?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Can we hide medicine in food because a resident refuses it?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.covert.required-decisions` | `family.medication.covert` | `doc.medication.covert.v1` | documents/medication/covert-administration-policy.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.error-form.immediate-safety` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What details do I write down after a meds mistake?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What details do I write down after a meds mistake?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What details do I write down after a meds mistake?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.error-form.fields` | `family.medication.errors` | `doc.medication.errors.v1` | documents/medication/medication-error-form.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.error-form.immediate-safety` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What goes on the medication error form?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What goes on the medication error form?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What goes on the medication error form?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.error-form.fields` | `family.medication.errors` | `doc.medication.errors.v1` | documents/medication/medication-error-form.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.error-form.immediate-safety` / `priority`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Should I finish the medicines incident form before calling for clinical advice?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Should I finish the medicines incident form before calling for clinical advice?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Should I finish the medicines incident form before calling for clinical advice?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.error-form.fields` | `family.medication.errors` | `doc.medication.errors.v1` | documents/medication/medication-error-form.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.fridge.boundary-table` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is eight okay but just over eight too warm?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Is eight okay but just over eight too warm?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": false,
  "differences": [
    {
      "actual": "COMPARE",
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": "CURRENT",
      "field": "temporal_mode"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Is eight okay but just over eight too warm?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.fridge.boundaries` | `family.medication.fridge-reference` | `doc.medication.fridge-reference.v1` | documents/medication/fridge-monitoring-reference.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=2 → Final evidence=0

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `801b4c5b-787b-5e04-99ca-83dd8844448d`<br>`801b4c5b-787b-5e04-99ca-83dd8844448d` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #1 / 0.284679 | #1 / 6.847204 | #1 / 0.032787 | #1 / 0.769531 | pass | no | medication.fridge.boundaries |
| `47a813db-42a0-5b2b-9631-4c30ef6d0306`<br>`47a813db-42a0-5b2b-9631-4c30ef6d0306` | `family.medication.storage`<br>`doc.medication.storage.v1` | #2 / 0.224086 | #2 / 6.273887 | #2 / 0.032258 | #2 / 0.628906 | pass | no | none |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #4 / 0.111658 | #24 / 0.594619 | #6 / 0.027530 | #3 / 0.210938 | fail | no | none |
| `799b04a0-74e1-5134-a911-0c2ccbda4c15`<br>`799b04a0-74e1-5134-a911-0c2ccbda4c15` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.099143 | #31 / 0.477859 | #11 / 0.026141 | #4 / 0.206055 | fail | no | none |
| `ebda80a6-77c7-557b-9450-fbddfdb16e02`<br>`ebda80a6-77c7-557b-9450-fbddfdb16e02` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #29 / 0.030711 | #3 / 2.242310 | #8 / 0.027109 | #5 / 0.193359 | fail | no | none |
| `aeb0ea01-92b2-5418-ad27-c95cacb3b030`<br>`aeb0ea01-92b2-5418-ad27-c95cacb3b030` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #9 / 0.084981 | #4 / 2.073265 | #3 / 0.030118 | #6 / 0.174805 | fail | no | none |
| `fc1749ce-678f-5b79-9a27-41ca33d2043c`<br>`fc1749ce-678f-5b79-9a27-41ca33d2043c` | `family.medication.prn`<br>`doc.medication.prn.v1` | #18 / 0.044811 | #16 / 0.922396 | #13 / 0.025978 | #7 / 0.171875 | fail | no | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | #27 / 0.035707 | #7 / 1.726103 | #10 / 0.026420 | #8 / 0.166016 | fail | no | none |
| `56745918-8c2b-5490-a300-4c18bf32a5c6`<br>`56745918-8c2b-5490-a300-4c18bf32a5c6` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #8 / 0.090747 | #18 / 0.895171 | #7 / 0.027526 | #9 / 0.155273 | fail | no | none |
| `ee3b92cf-7201-50f5-9315-841d5bceb277`<br>`ee3b92cf-7201-50f5-9315-841d5bceb277` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #26 / 0.035904 | #11 / 1.108727 | #14 / 0.025712 | #10 / 0.151367 | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #14 / 0.056526 | #20 / 0.858496 | #12 / 0.026014 | #11 / 0.149414 | fail | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #10 / 0.065862 | #9 / 1.197368 | #5 / 0.028778 | #12 / 0.147461 | fail | no | none |
| `1a330d42-d249-5bf6-ba4b-066222bc5f5b`<br>`1a330d42-d249-5bf6-ba4b-066222bc5f5b` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #11 / 0.061068 | #21 / 0.851248 | #9 / 0.026430 | #13 / 0.143555 | fail | no | none |
| `1839469e-5726-503f-a711-a010a97420fd`<br>`1839469e-5726-503f-a711-a010a97420fd` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #12 / 0.058342 | #6 / 1.806711 | #4 / 0.029040 | #14 / 0.143555 | fail | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #36 / 0.011294 | #8 / 1.654156 | #15 / 0.025123 | #15 / 0.137695 | fail | no | none |
| `b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef`<br>`b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.145226 | — | — | — | fail | no | none |
| `5cf87b03-5514-55ae-9cac-0aa6b7c572d3`<br>`5cf87b03-5514-55ae-9cac-0aa6b7c572d3` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #5 / 0.109841 | — | — | — | fail | no | none |
| `0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6`<br>`0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #7 / 0.095820 | — | — | — | fail | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #13 / 0.057717 | — | — | — | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #15 / 0.056304 | #35 / 0.319569 | — | — | fail | no | none |
| `635ff5e9-ecb1-559b-8683-4b7a96ea7bd9`<br>`635ff5e9-ecb1-559b-8683-4b7a96ea7bd9` | `family.fire.drills`<br>`doc.fire.drills.v2` | #16 / 0.051984 | — | — | — | fail | no | none |
| `1f7baac6-5792-5b2a-9399-26ad4c21d6e4`<br>`1f7baac6-5792-5b2a-9399-26ad4c21d6e4` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #17 / 0.047346 | #29 / 0.495617 | — | — | fail | no | none |
| `f193cb26-bd92-5fb8-a0b1-ba2c829f658b`<br>`f193cb26-bd92-5fb8-a0b1-ba2c829f658b` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #19 / 0.044170 | #37 / 0.267550 | — | — | fail | no | none |
| `40b84d12-bb43-5dc3-a182-d80b51693330`<br>`40b84d12-bb43-5dc3-a182-d80b51693330` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #20 / 0.041355 | — | — | — | fail | no | none |
| `ccc94945-e377-526e-93c2-5fd324619661`<br>`ccc94945-e377-526e-93c2-5fd324619661` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | #21 / 0.041117 | — | — | — | fail | no | none |
| `919b1651-7a62-5792-b47f-6ac4fc784017`<br>`919b1651-7a62-5792-b47f-6ac4fc784017` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | #22 / 0.039270 | — | — | — | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #23 / 0.038318 | #22 / 0.850804 | — | — | fail | no | none |
| `d24f4e43-6251-56d5-b470-c23242fe6873`<br>`d24f4e43-6251-56d5-b470-c23242fe6873` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | #24 / 0.037816 | — | — | — | fail | no | none |
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | #25 / 0.036769 | #15 / 0.936255 | — | — | fail | no | none |
| `be5c3624-95a2-5d5d-9f05-a9fb635d68a6`<br>`be5c3624-95a2-5d5d-9f05-a9fb635d68a6` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | #28 / 0.032594 | #39 / 0.226211 | — | — | fail | no | none |
| `256e756b-7110-5070-9432-97bb1923a202`<br>`256e756b-7110-5070-9432-97bb1923a202` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #30 / 0.029064 | — | — | — | fail | no | none |
| `85950010-d571-5bd3-9c8e-78b2687219d7`<br>`85950010-d571-5bd3-9c8e-78b2687219d7` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | #31 / 0.026024 | — | — | — | fail | no | none |
| `6a0fb733-bff0-55d1-a5e7-d322ef9e53a9`<br>`6a0fb733-bff0-55d1-a5e7-d322ef9e53a9` | `family.training.matrix`<br>`doc.training.matrix.v1` | #32 / 0.021397 | — | — | — | fail | no | none |
| `5a0ad7a9-b4c1-5072-a3b8-d527805bad81`<br>`5a0ad7a9-b4c1-5072-a3b8-d527805bad81` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #33 / 0.017539 | #17 / 0.897015 | — | — | fail | no | none |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #34 / 0.017426 | — | — | — | fail | no | none |
| `6ba08511-5e10-530d-9a62-17ffed9e9bc4`<br>`6ba08511-5e10-530d-9a62-17ffed9e9bc4` | `family.training.induction`<br>`doc.training.induction.v1` | #35 / 0.013434 | — | — | — | fail | no | none |
| `14ab94b0-4ade-5c5c-b5bd-77eae8daf94d`<br>`14ab94b0-4ade-5c5c-b5bd-77eae8daf94d` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #37 / 0.010675 | — | — | — | fail | no | none |
| `20575c0a-658b-508a-a009-60706b3fde3c`<br>`20575c0a-658b-508a-a009-60706b3fde3c` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #38 / 0.010089 | #30 / 0.479592 | — | — | fail | no | none |
| `b4f8b48b-d6bb-55bf-9808-e81c551b09f8`<br>`b4f8b48b-d6bb-55bf-9808-e81c551b09f8` | `family.complaints.advocacy`<br>`doc.complaints.advocacy.v1` | #39 / 0.009878 | — | — | — | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #40 / 0.009810 | #13 / 0.958126 | — | — | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | — | #5 / 1.970522 | — | — | fail | no | none |
| `65dda7f5-3688-515f-8d78-25e87c41a7e0`<br>`65dda7f5-3688-515f-8d78-25e87c41a7e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #10 / 1.136451 | — | — | fail | no | none |
| `3ffac08e-eebd-5bf7-963c-116ad06e0312`<br>`3ffac08e-eebd-5bf7-963c-116ad06e0312` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | — | #12 / 1.085755 | — | — | fail | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | — | #14 / 0.938030 | — | — | fail | no | none |
| `ad9c7253-2c23-5a18-bb60-bcfc0859e149`<br>`ad9c7253-2c23-5a18-bb60-bcfc0859e149` | `family.payroll.mileage`<br>`doc.payroll.mileage.v1` | — | #19 / 0.890631 | — | — | fail | no | none |
| `b3036236-deaa-5719-ad41-3c5d87bbe7d8`<br>`b3036236-deaa-5719-ad41-3c5d87bbe7d8` | `family.training.fire`<br>`doc.training.fire.v1` | — | #23 / 0.823915 | — | — | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | — | #25 / 0.562275 | — | — | fail | no | none |
| `249cc883-6c9a-5099-bdbb-974f04227e23`<br>`249cc883-6c9a-5099-bdbb-974f04227e23` | `family.complaints.form`<br>`doc.complaints.form.v1` | — | #26 / 0.551885 | — | — | fail | no | none |
| `ee3bb1bd-f03f-5314-b408-a1895aaadc2e`<br>`ee3bb1bd-f03f-5314-b408-a1895aaadc2e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | — | #27 / 0.547972 | — | — | fail | no | none |
| `3cc16b3c-7d04-53a9-a273-eddea88a3ccb`<br>`3cc16b3c-7d04-53a9-a273-eddea88a3ccb` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | — | #28 / 0.498247 | — | — | fail | no | none |
| `0318f8f9-9107-50ab-9afd-a65ee1687c77`<br>`0318f8f9-9107-50ab-9afd-a65ee1687c77` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | — | #32 / 0.384135 | — | — | fail | no | none |
| `4d1f0d61-d751-52f0-87dd-0327ea89db4e`<br>`4d1f0d61-d751-52f0-87dd-0327ea89db4e` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #33 / 0.324558 | — | — | fail | no | none |
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | — | #34 / 0.323174 | — | — | fail | no | none |
| `f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0`<br>`f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #36 / 0.297478 | — | — | fail | no | none |
| `018c7c48-f558-5416-8a50-2043b3d3b7b8`<br>`018c7c48-f558-5416-8a50-2043b3d3b7b8` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #38 / 0.248325 | — | — | fail | no | none |
| `2dc51247-e552-5a57-91c3-9408e34f5d94`<br>`2dc51247-e552-5a57-91c3-9408e34f5d94` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | — | #40 / 0.175157 | — | — | fail | no | none |

#### COMPARISON

Candidate funnel: Dense=13 → Sparse=7 → Unique after RRF=13 → Reranker=13 → Threshold=0 → Final evidence=0

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `2d65a97b-9023-5d91-8a35-5d78b3934084`<br>`2d65a97b-9023-5d91-8a35-5d78b3934084` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #6 / 0.044161 | #4 / 0.657115 | #4 / 0.030777 | #1 / 0.207031 | fail | no | none |
| `254c3933-94f2-510b-aa2d-9ab1942de8a7`<br>`254c3933-94f2-510b-aa2d-9ab1942de8a7` | `family.medication.administration`<br>`doc.medication.administration.v1` | #4 / 0.050022 | #1 / 1.791103 | #1 / 0.032018 | #2 / 0.199219 | fail | no | none |
| `72a23d19-05d6-5fe0-8918-f0442b392f2d`<br>`72a23d19-05d6-5fe0-8918-f0442b392f2d` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v1` | #11 / -0.020603 | #7 / 0.001313 | #7 / 0.029010 | #3 / 0.153320 | fail | no | none |
| `3f7a6eba-f048-598f-8340-aed3172f8361`<br>`3f7a6eba-f048-598f-8340-aed3172f8361` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | #1 / 0.074954 | — | #8 / 0.016393 | #4 / 0.151367 | fail | no | none |
| `369ceff0-142f-5215-817d-ddafe27e7ace`<br>`369ceff0-142f-5215-817d-ddafe27e7ace` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #8 / 0.027931 | #6 / 0.055014 | #5 / 0.029857 | #5 / 0.149414 | fail | no | none |
| `07ab0a1c-21e8-5a07-b4ed-3110898b35ca`<br>`07ab0a1c-21e8-5a07-b4ed-3110898b35ca` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #13 / -0.045059 | #2 / 1.336709 | #6 / 0.029828 | #6 / 0.149414 | fail | no | none |
| `11a5a524-8a6e-5f08-9a8c-4c470aae9086`<br>`11a5a524-8a6e-5f08-9a8c-4c470aae9086` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #2 / 0.063750 | #5 / 0.070586 | #2 / 0.031514 | #7 / 0.145508 | fail | no | none |
| `3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f`<br>`3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f` | `family.fire.drills`<br>`doc.fire.drills.v1` | #5 / 0.049526 | — | #10 / 0.015385 | #8 / 0.143555 | fail | no | none |
| `817f4ea7-115c-58d5-9a46-dbaef434a1f2`<br>`817f4ea7-115c-58d5-9a46-dbaef434a1f2` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #10 / -0.011415 | — | #12 / 0.014286 | #9 / 0.137695 | fail | no | none |
| `5b68e998-3a65-5808-bc5b-73e28613adc9`<br>`5b68e998-3a65-5808-bc5b-73e28613adc9` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #7 / 0.029233 | #3 / 1.098796 | #3 / 0.030798 | #10 / 0.137695 | fail | no | none |
| `80ddc068-0955-5bb4-92c0-4b1586792c84`<br>`80ddc068-0955-5bb4-92c0-4b1586792c84` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #3 / 0.059869 | — | #9 / 0.015873 | #11 / 0.137695 | fail | no | none |
| `13c0e838-be23-5fac-a03d-3c9478b3f41f`<br>`13c0e838-be23-5fac-a03d-3c9478b3f41f` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #12 / -0.040621 | — | #13 / 0.013889 | #12 / 0.135742 | fail | no | none |
| `14b1c8c3-190a-531d-b13e-5666a56b9ac7`<br>`14b1c8c3-190a-531d-b13e-5666a56b9ac7` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #9 / 0.015682 | — | #11 / 0.014493 | #13 / 0.129883 | fail | no | none |

### `medication.fridge.boundary-table` / `decimal`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What action is required at 8.1 degrees?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": "unclassifiable_temporal_intent",
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What action is required at 8.1 degrees?"
    ],
    "temporal_mode": "CLARIFICATION_REQUIRED",
    "temporal_reference": null
  },
  "correct": false,
  "differences": [
    {
      "actual": "CLARIFICATION_REQUIRED",
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": "CURRENT",
      "field": "temporal_mode"
    },
    {
      "actual": "unclassifiable_temporal_intent",
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": null,
      "field": "clarification_reason"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What action is required at 8.1 degrees?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.fridge.boundaries` | `family.medication.fridge-reference` | `doc.medication.fridge-reference.v1` | documents/medication/fridge-monitoring-reference.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.fridge.boundary-table` / `upper`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is a medicines fridge reading of exactly 8°C in range?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Is a medicines fridge reading of exactly 8°C in range?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Is a medicines fridge reading of exactly 8°C in range?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.fridge.boundaries` | `family.medication.fridge-reference` | `doc.medication.fridge-reference.v1` | documents/medication/fridge-monitoring-reference.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.prn.minimum-interval` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: It is on the meds chart as needed — is that enough to give it?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "It is on the meds chart as needed — is that enough to give it?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "It is on the meds chart as needed — is that enough to give it?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.prn.prechecks` | `family.medication.prn` | `doc.medication.prn.v1` | documents/medication/prn-protocol.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.prn.minimum-interval` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What must I check before giving a PRN medicine?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What must I check before giving a PRN medicine?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What must I check before giving a PRN medicine?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.prn.prechecks` | `family.medication.prn` | `doc.medication.prn.v1` | documents/medication/prn-protocol.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `medication.prn.minimum-interval` / `expanded`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What information is needed before giving when-required medication?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What information is needed before giving when-required medication?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What information is needed before giving when-required medication?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.prn.prechecks` | `family.medication.prn` | `doc.medication.prn.v1` | documents/medication/prn-protocol.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.adversarial.visitor-negative` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Can a visitor use the lift in a fire and skip the reception book?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Can a visitor use the lift in a fire and skip the reception book?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Can a visitor use the lift in a fire and skip the reception book?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `visitors.fire-instructions` | `family.visitors.general` | `doc.visitors.general.v1` | documents/visitors/visitors-contractors.md |
| PRIMARY | `visitors.sign-in` | `family.visitors.general` | `doc.visitors.general.v1` | documents/visitors/visitors-contractors.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.adversarial.visitor-negative` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What must a visitor do when the fire alarm sounds?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What must a visitor do when the fire alarm sounds?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What must a visitor do when the fire alarm sounds?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `visitors.fire-instructions` | `family.visitors.general` | `doc.visitors.general.v1` | documents/visitors/visitors-contractors.md |
| PRIMARY | `visitors.sign-in` | `family.visitors.general` | `doc.visitors.general.v1` | documents/visitors/visitors-contractors.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.adversarial.visitor-negative` / `sign-in`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do visitors have to sign in and what happens during an evacuation?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do visitors have to sign in and what happens during an evacuation?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do visitors have to sign in and what happens during an evacuation?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `visitors.fire-instructions` | `family.visitors.general` | `doc.visitors.general.v1` | documents/visitors/visitors-contractors.md |
| PRIMARY | `visitors.sign-in` | `family.visitors.general` | `doc.visitors.general.v1` | documents/visitors/visitors-contractors.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.applicability.ambiguous-home` / `ambiguous`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `True`
- Expected outcome: `CLARIFICATION_REQUIRED`
- Text capture: `BENCHMARK_TEXT`
- Question: Where is the fire assembly point at the home?
- Covered EvidenceUnits: `none`
- Metrics: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
- Hard failures: `eligibility_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "the home"
    ],
    "retrieval_queries": [
      "Where is the fire assembly point at the home?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "the home"
    ],
    "retrieval_queries": [
      "Where is the fire assembly point at the home?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```


### `pilot.applicability.ambiguous-home` / `pronoun`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `CLARIFICATION_REQUIRED`
- Text capture: `BENCHMARK_TEXT`
- Question: Which evacuation plan applies there?
- Covered EvidenceUnits: `none`
- Metrics: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
- Hard failures: `eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Which evacuation plan applies there?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Which evacuation plan applies there?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=10 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `256e756b-7110-5070-9432-97bb1923a202`<br>`256e756b-7110-5070-9432-97bb1923a202` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #3 / 0.455703 | #3 / 16.558386 | #4 / 0.031746 | #1 / 0.773438 | pass | yes | none |
| `ee3b92cf-7201-50f5-9315-841d5bceb277`<br>`ee3b92cf-7201-50f5-9315-841d5bceb277` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #1 / 0.496520 | #4 / 14.268437 | #2 / 0.032018 | #2 / 0.746094 | pass | yes | none |
| `14ab94b0-4ade-5c5c-b5bd-77eae8daf94d`<br>`14ab94b0-4ade-5c5c-b5bd-77eae8daf94d` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #4 / 0.437410 | #2 / 17.298508 | #3 / 0.031754 | #3 / 0.742188 | pass | yes | none |
| `be5c3624-95a2-5d5d-9f05-a9fb635d68a6`<br>`be5c3624-95a2-5d5d-9f05-a9fb635d68a6` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | #5 / 0.436990 | #5 / 13.103787 | #5 / 0.030769 | #4 / 0.695312 | pass | yes | none |
| `b1b209d9-8945-557c-9456-0649dd6eb76a`<br>`b1b209d9-8945-557c-9456-0649dd6eb76a` | `family.fire.peep`<br>`doc.fire.peep.v1` | #2 / 0.476256 | #1 / 17.684975 | #1 / 0.032522 | #5 / 0.625000 | pass | yes | none |
| `955ca35a-ad9d-57fb-8c12-e79c9190c2cd`<br>`955ca35a-ad9d-57fb-8c12-e79c9190c2cd` | `family.visitors.general`<br>`doc.visitors.general.v1` | #12 / 0.269108 | #6 / 9.677496 | #7 / 0.029040 | #6 / 0.466797 | pass | no | none |
| `b3036236-deaa-5719-ad41-3c5d87bbe7d8`<br>`b3036236-deaa-5719-ad41-3c5d87bbe7d8` | `family.training.fire`<br>`doc.training.fire.v1` | #7 / 0.318363 | #8 / 6.247156 | #6 / 0.029631 | #7 / 0.464844 | pass | no | none |
| `635ff5e9-ecb1-559b-8683-4b7a96ea7bd9`<br>`635ff5e9-ecb1-559b-8683-4b7a96ea7bd9` | `family.fire.drills`<br>`doc.fire.drills.v2` | #8 / 0.318291 | #14 / 4.464152 | #9 / 0.028219 | #8 / 0.417969 | pass | no | none |
| `5a0ad7a9-b4c1-5072-a3b8-d527805bad81`<br>`5a0ad7a9-b4c1-5072-a3b8-d527805bad81` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #17 / 0.248359 | #7 / 7.524100 | #12 / 0.027912 | #9 / 0.384766 | pass | no | none |
| `ccc94945-e377-526e-93c2-5fd324619661`<br>`ccc94945-e377-526e-93c2-5fd324619661` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | #15 / 0.260152 | #15 / 4.306828 | #14 / 0.026667 | #10 / 0.349609 | pass | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #6 / 0.339359 | #17 / 3.871256 | #10 / 0.028139 | #11 / 0.294922 | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #10 / 0.285386 | #13 / 4.485633 | #11 / 0.027984 | #12 / 0.285156 | fail | no | none |
| `f193cb26-bd92-5fb8-a0b1-ba2c829f658b`<br>`f193cb26-bd92-5fb8-a0b1-ba2c829f658b` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #9 / 0.312135 | #11 / 4.844622 | #8 / 0.028577 | #13 / 0.283203 | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #13 / 0.266045 | #18 / 3.853383 | #15 / 0.026519 | #14 / 0.263672 | fail | no | none |
| `1839469e-5726-503f-a711-a010a97420fd`<br>`1839469e-5726-503f-a711-a010a97420fd` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #18 / 0.241777 | #9 / 5.590486 | #13 / 0.027313 | #15 / 0.257812 | fail | no | none |
| `5cf87b03-5514-55ae-9cac-0aa6b7c572d3`<br>`5cf87b03-5514-55ae-9cac-0aa6b7c572d3` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #11 / 0.274272 | #30 / 1.674645 | — | — | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #14 / 0.262378 | #22 / 2.962240 | — | — | fail | no | none |
| `0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6`<br>`0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #16 / 0.253216 | #34 / 1.079838 | — | — | fail | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #19 / 0.234213 | #23 / 2.855198 | — | — | fail | no | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #20 / 0.229815 | #16 / 4.139702 | — | — | fail | no | none |
| `3ffac08e-eebd-5bf7-963c-116ad06e0312`<br>`3ffac08e-eebd-5bf7-963c-116ad06e0312` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #21 / 0.227220 | — | — | — | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #22 / 0.220849 | #39 / 0.780163 | — | — | fail | no | none |
| `f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0`<br>`f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | #23 / 0.208104 | #27 / 2.534985 | — | — | fail | no | none |
| `3e50e8ee-575c-52c9-a368-f1c6d1c814e1`<br>`3e50e8ee-575c-52c9-a368-f1c6d1c814e1` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #24 / 0.206865 | #20 / 3.430312 | — | — | fail | no | none |
| `b4f8b48b-d6bb-55bf-9808-e81c551b09f8`<br>`b4f8b48b-d6bb-55bf-9808-e81c551b09f8` | `family.complaints.advocacy`<br>`doc.complaints.advocacy.v1` | #25 / 0.205466 | #25 / 2.816355 | — | — | fail | no | none |
| `92b627e2-da75-52c3-88b6-cdc01aa3b9ef`<br>`92b627e2-da75-52c3-88b6-cdc01aa3b9ef` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #26 / 0.204060 | — | — | — | fail | no | none |
| `3cc16b3c-7d04-53a9-a273-eddea88a3ccb`<br>`3cc16b3c-7d04-53a9-a273-eddea88a3ccb` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #27 / 0.195126 | — | — | — | fail | no | none |
| `0318f8f9-9107-50ab-9afd-a65ee1687c77`<br>`0318f8f9-9107-50ab-9afd-a65ee1687c77` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #28 / 0.192446 | — | — | — | fail | no | none |
| `8d8de832-6d4c-5368-b209-2ece5159b021`<br>`8d8de832-6d4c-5368-b209-2ece5159b021` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #29 / 0.192332 | #38 / 0.806800 | — | — | fail | no | none |
| `6ba08511-5e10-530d-9a62-17ffed9e9bc4`<br>`6ba08511-5e10-530d-9a62-17ffed9e9bc4` | `family.training.induction`<br>`doc.training.induction.v1` | #30 / 0.188355 | — | — | — | fail | no | none |
| `85950010-d571-5bd3-9c8e-78b2687219d7`<br>`85950010-d571-5bd3-9c8e-78b2687219d7` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | #31 / 0.186115 | — | — | — | fail | no | none |
| `6a0fb733-bff0-55d1-a5e7-d322ef9e53a9`<br>`6a0fb733-bff0-55d1-a5e7-d322ef9e53a9` | `family.training.matrix`<br>`doc.training.matrix.v1` | #32 / 0.185040 | — | — | — | fail | no | none |
| `ee3bb1bd-f03f-5314-b408-a1895aaadc2e`<br>`ee3bb1bd-f03f-5314-b408-a1895aaadc2e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #33 / 0.183813 | — | — | — | fail | no | none |
| `f1b2325d-4bb3-581b-8d14-7b8cdd43f216`<br>`f1b2325d-4bb3-581b-8d14-7b8cdd43f216` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #34 / 0.181284 | #33 / 1.141104 | — | — | fail | no | none |
| `f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e`<br>`f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #35 / 0.179915 | #26 / 2.775257 | — | — | fail | no | none |
| `4d1f0d61-d751-52f0-87dd-0327ea89db4e`<br>`4d1f0d61-d751-52f0-87dd-0327ea89db4e` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | #36 / 0.178962 | — | — | — | fail | no | none |
| `2dc51247-e552-5a57-91c3-9408e34f5d94`<br>`2dc51247-e552-5a57-91c3-9408e34f5d94` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #37 / 0.175139 | #31 / 1.476322 | — | — | fail | no | none |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #38 / 0.175059 | — | — | — | fail | no | none |
| `40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc`<br>`40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | #39 / 0.175031 | — | — | — | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #40 / 0.173937 | — | — | — | fail | no | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | — | #10 / 4.899652 | — | — | fail | no | none |
| `b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef`<br>`b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef` | `family.medication.administration`<br>`doc.medication.administration.v2` | — | #12 / 4.521369 | — | — | fail | no | none |
| `5a87d328-f076-5953-aa2e-8d7963341f74`<br>`5a87d328-f076-5953-aa2e-8d7963341f74` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #19 / 3.648408 | — | — | fail | no | none |
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | — | #21 / 3.283076 | — | — | fail | no | none |
| `42e10f18-8de2-53bd-8487-f46c454bf735`<br>`42e10f18-8de2-53bd-8487-f46c454bf735` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | — | #24 / 2.836440 | — | — | fail | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | — | #28 / 2.394431 | — | — | fail | no | none |
| `1f7baac6-5792-5b2a-9399-26ad4c21d6e4`<br>`1f7baac6-5792-5b2a-9399-26ad4c21d6e4` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | — | #29 / 2.215050 | — | — | fail | no | none |
| `ebda80a6-77c7-557b-9450-fbddfdb16e02`<br>`ebda80a6-77c7-557b-9450-fbddfdb16e02` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #32 / 1.143118 | — | — | fail | no | none |
| `3175f7bd-0838-5056-a1da-341d951720ed`<br>`3175f7bd-0838-5056-a1da-341d951720ed` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #35 / 0.967549 | — | — | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #36 / 0.942572 | — | — | fail | no | none |
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #37 / 0.848646 | — | — | fail | no | none |
| `018c7c48-f558-5416-8a50-2043b3d3b7b8`<br>`018c7c48-f558-5416-8a50-2043b3d3b7b8` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #40 / 0.444938 | — | — | fail | no | none |

### `pilot.applicability.ambiguous-home` / `underspecified`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `True`
- Expected outcome: `CLARIFICATION_REQUIRED`
- Text capture: `BENCHMARK_TEXT`
- Question: What should visitors do when the alarm sounds at our care home?
- Covered EvidenceUnits: `none`
- Metrics: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
- Hard failures: `eligibility_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "our care home"
    ],
    "retrieval_queries": [
      "What should visitors do when the alarm sounds at our care home?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "our care home"
    ],
    "retrieval_queries": [
      "What should visitors do when the alarm sounds at our care home?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```


### `pilot.applicability.bristol-conflict` / `conflict`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Why do the South West and Bristol procedures name different assembly points?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "South West",
      "Bristol"
    ],
    "retrieval_queries": [
      "Why do the South West and Bristol procedures name different assembly points?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "South West",
      "Bristol"
    ],
    "retrieval_queries": [
      "Why do the South West and Bristol procedures name different assembly points?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.south-west.fallback-condition` | `family.fire.south-west-evacuation` | `doc.fire.south-west-evacuation.v1` | documents/fire-safety/south-west-evacuation.md |
| PRIMARY | `fire.harbour-view.local-override` | `family.fire.harbour-view-evacuation` | `doc.fire.harbour-view-evacuation.v1` | documents/fire-safety/harbour-view-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.applicability.bristol-conflict` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What regional and local fire instructions apply at Harbour View?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Harbour View"
    ],
    "retrieval_queries": [
      "What regional and local fire instructions apply at Harbour View?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Harbour View"
    ],
    "retrieval_queries": [
      "What regional and local fire instructions apply at Harbour View?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.south-west.fallback-condition` | `family.fire.south-west-evacuation` | `doc.fire.south-west-evacuation.v1` | documents/fire-safety/south-west-evacuation.md |
| PRIMARY | `fire.harbour-view.local-override` | `family.fire.harbour-view-evacuation` | `doc.fire.harbour-view-evacuation.v1` | documents/fire-safety/harbour-view-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.applicability.bristol-conflict` / `multi-document`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Show me both applicable evacuation instructions for the Bristol home.
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Bristol home"
    ],
    "retrieval_queries": [
      "Show me both applicable evacuation instructions for the Bristol home."
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": false,
  "differences": [
    {
      "actual": [
        "Bristol home"
      ],
      "classification": "POTENTIAL_ALIAS_OR_REPRESENTATION_MISMATCH",
      "expected": [
        "the Bristol home"
      ],
      "field": "location_references"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "the Bristol home"
    ],
    "retrieval_queries": [
      "Show me both applicable evacuation instructions for the Bristol home."
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.south-west.fallback-condition` | `family.fire.south-west-evacuation` | `doc.fire.south-west-evacuation.v1` | documents/fire-safety/south-west-evacuation.md |
| PRIMARY | `fire.harbour-view.local-override` | `family.fire.harbour-view-evacuation` | `doc.fire.harbour-view-evacuation.v1` | documents/fire-safety/harbour-view-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.applicability.regional-exeter` / `alias`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Which evacuation procedure applies at the Exeter home?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Exeter home"
    ],
    "retrieval_queries": [
      "Which evacuation procedure applies at the Exeter home?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": false,
  "differences": [
    {
      "actual": [
        "Exeter home"
      ],
      "classification": "POTENTIAL_ALIAS_OR_REPRESENTATION_MISMATCH",
      "expected": [
        "the Exeter home"
      ],
      "field": "location_references"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "the Exeter home"
    ],
    "retrieval_queries": [
      "Which evacuation procedure applies at the Exeter home?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.south-west.regional-fallback` | `family.fire.south-west-evacuation` | `doc.fire.south-west-evacuation.v1` | documents/fire-safety/south-west-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.applicability.regional-exeter` / `canonical`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Where does Meadow Court assemble under the regional procedure?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Meadow Court"
    ],
    "retrieval_queries": [
      "Where does Meadow Court assemble under the regional procedure?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Meadow Court"
    ],
    "retrieval_queries": [
      "Where does Meadow Court assemble under the regional procedure?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.south-west.regional-fallback` | `family.fire.south-west-evacuation` | `doc.fire.south-west-evacuation.v1` | documents/fire-safety/south-west-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.applicability.regional-exeter` / `inheritance`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Does the South West fire procedure cover Meadow Court?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "South West",
      "Meadow Court"
    ],
    "retrieval_queries": [
      "Does the South West fire procedure cover Meadow Court?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "South West",
      "Meadow Court"
    ],
    "retrieval_queries": [
      "Does the South West fire procedure cover Meadow Court?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.south-west.regional-fallback` | `family.fire.south-west-evacuation` | `doc.fire.south-west-evacuation.v1` | documents/fire-safety/south-west-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.compare.medication-administration` / `change`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What changed in the checks before giving medication?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What changed in the checks before giving medication?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What changed in the checks before giving medication?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v2.seven-checks` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |
| COMPARISON | `medication.v1.six-checks` | `family.medication.administration` | `doc.medication.administration.v1` | documents/medication/safe-administration-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.compare.medication-administration` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What extra check was added to the newer meds policy?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What extra check was added to the newer meds policy?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What extra check was added to the newer meds policy?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v2.seven-checks` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |
| COMPARISON | `medication.v1.six-checks` | `family.medication.administration` | `doc.medication.administration.v1` | documents/medication/safe-administration-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.compare.medication-administration` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare the current medicine checks with the previous policy.
- Covered EvidenceUnits: `medication.v1.six-checks, medication.v2.seven-checks`
- Metrics: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
- Hard failures: `planner_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare the current medicine checks with the previous policy."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "previous policy"
    }
  },
  "correct": false,
  "differences": [
    {
      "actual": {
        "kind": "HISTORICAL_REFERENCE",
        "value": "previous policy"
      },
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": null,
      "field": "temporal_reference"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare the current medicine checks with the previous policy."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v2.seven-checks` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |
| COMPARISON | `medication.v1.six-checks` | `family.medication.administration` | `doc.medication.administration.v1` | documents/medication/safe-administration-v1.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=10 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.383076 | #3 / 11.369364 | #2 / 0.032002 | #1 / 0.585938 | pass | yes | medication.v2.seven-checks |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #1 / 0.442020 | #4 / 11.191341 | #1 / 0.032018 | #2 / 0.488281 | pass | yes | none |
| `799b04a0-74e1-5134-a911-0c2ccbda4c15`<br>`799b04a0-74e1-5134-a911-0c2ccbda4c15` | `family.medication.administration`<br>`doc.medication.administration.v2` | #5 / 0.331695 | #1 / 12.593025 | #3 / 0.031778 | #3 / 0.470703 | pass | yes | none |
| `47a813db-42a0-5b2b-9631-4c30ef6d0306`<br>`47a813db-42a0-5b2b-9631-4c30ef6d0306` | `family.medication.storage`<br>`doc.medication.storage.v1` | #10 / 0.284395 | #5 / 9.276242 | #7 / 0.029670 | #4 / 0.429688 | pass | yes | none |
| `b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef`<br>`b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.331179 | #11 / 6.711873 | #9 / 0.029236 | #5 / 0.417969 | pass | yes | none |
| `fc1749ce-678f-5b79-9a27-41ca33d2043c`<br>`fc1749ce-678f-5b79-9a27-41ca33d2043c` | `family.medication.prn`<br>`doc.medication.prn.v1` | #8 / 0.318756 | #6 / 8.903975 | #5 / 0.029857 | #6 / 0.398438 | pass | no | none |
| `1a330d42-d249-5bf6-ba4b-066222bc5f5b`<br>`1a330d42-d249-5bf6-ba4b-066222bc5f5b` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #3 / 0.362258 | #12 / 5.927285 | #6 / 0.029762 | #7 / 0.388672 | pass | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #7 / 0.324663 | #8 / 7.349423 | #8 / 0.029631 | #8 / 0.361328 | pass | no | none |
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | #4 / 0.340557 | #2 / 11.824585 | #4 / 0.031754 | #9 / 0.353516 | pass | no | none |
| `801b4c5b-787b-5e04-99ca-83dd8844448d`<br>`801b4c5b-787b-5e04-99ca-83dd8844448d` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #12 / 0.260441 | #7 / 7.662415 | #10 / 0.028814 | #10 / 0.351562 | pass | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #23 / 0.169957 | #9 / 7.348856 | #14 / 0.026541 | #11 / 0.322266 | fail | no | none |
| `56745918-8c2b-5490-a300-4c18bf32a5c6`<br>`56745918-8c2b-5490-a300-4c18bf32a5c6` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #9 / 0.298652 | #15 / 4.737642 | #11 / 0.027826 | #12 / 0.294922 | fail | no | none |
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | #11 / 0.269678 | #13 / 5.087882 | #12 / 0.027783 | #13 / 0.271484 | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #17 / 0.191486 | #10 / 7.292416 | #13 / 0.027273 | #14 / 0.250000 | fail | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #14 / 0.234082 | #17 / 4.321057 | #15 / 0.026501 | #15 / 0.242188 | fail | no | none |
| `f85e71bc-4d62-57d9-b403-b13b1a9ff199`<br>`f85e71bc-4d62-57d9-b403-b13b1a9ff199` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #13 / 0.242067 | #19 / 3.868482 | — | — | fail | no | none |
| `d24f4e43-6251-56d5-b470-c23242fe6873`<br>`d24f4e43-6251-56d5-b470-c23242fe6873` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | #15 / 0.192815 | — | — | — | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #16 / 0.192209 | #22 / 3.425157 | — | — | fail | no | none |
| `0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6`<br>`0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #18 / 0.188912 | #34 / 1.079870 | — | — | fail | no | none |
| `5a0ad7a9-b4c1-5072-a3b8-d527805bad81`<br>`5a0ad7a9-b4c1-5072-a3b8-d527805bad81` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #19 / 0.184289 | #16 / 4.397446 | — | — | fail | no | none |
| `1f7baac6-5792-5b2a-9399-26ad4c21d6e4`<br>`1f7baac6-5792-5b2a-9399-26ad4c21d6e4` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #20 / 0.182494 | — | — | — | fail | no | none |
| `249cc883-6c9a-5099-bdbb-974f04227e23`<br>`249cc883-6c9a-5099-bdbb-974f04227e23` | `family.complaints.form`<br>`doc.complaints.form.v1` | #21 / 0.182303 | #40 / 0.760778 | — | — | fail | no | none |
| `f193cb26-bd92-5fb8-a0b1-ba2c829f658b`<br>`f193cb26-bd92-5fb8-a0b1-ba2c829f658b` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #22 / 0.178894 | #14 / 4.796384 | — | — | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #24 / 0.169297 | #20 / 3.682231 | — | — | fail | no | none |
| `1839469e-5726-503f-a711-a010a97420fd`<br>`1839469e-5726-503f-a711-a010a97420fd` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #25 / 0.168800 | — | — | — | fail | no | none |
| `8d8de832-6d4c-5368-b209-2ece5159b021`<br>`8d8de832-6d4c-5368-b209-2ece5159b021` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #26 / 0.167294 | — | — | — | fail | no | none |
| `6a0fb733-bff0-55d1-a5e7-d322ef9e53a9`<br>`6a0fb733-bff0-55d1-a5e7-d322ef9e53a9` | `family.training.matrix`<br>`doc.training.matrix.v1` | #27 / 0.166007 | #37 / 0.868542 | — | — | fail | no | none |
| `2dc51247-e552-5a57-91c3-9408e34f5d94`<br>`2dc51247-e552-5a57-91c3-9408e34f5d94` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #28 / 0.165806 | #18 / 4.241626 | — | — | fail | no | none |
| `635ff5e9-ecb1-559b-8683-4b7a96ea7bd9`<br>`635ff5e9-ecb1-559b-8683-4b7a96ea7bd9` | `family.fire.drills`<br>`doc.fire.drills.v2` | #29 / 0.157611 | — | — | — | fail | no | none |
| `ac335280-6bca-5150-bd9b-db2d198ca588`<br>`ac335280-6bca-5150-bd9b-db2d198ca588` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #30 / 0.155447 | #29 / 1.615727 | — | — | fail | no | none |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #31 / 0.154712 | #31 / 1.548819 | — | — | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #32 / 0.149650 | #23 / 3.424647 | — | — | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #33 / 0.147852 | #32 / 1.174134 | — | — | fail | no | none |
| `f4b9f291-51c7-5e35-9335-b7e3dd2b37ef`<br>`f4b9f291-51c7-5e35-9335-b7e3dd2b37ef` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #34 / 0.132939 | #36 / 0.930811 | — | — | fail | no | none |
| `ebda80a6-77c7-557b-9450-fbddfdb16e02`<br>`ebda80a6-77c7-557b-9450-fbddfdb16e02` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #35 / 0.132155 | — | — | — | fail | no | none |
| `40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc`<br>`40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | #36 / 0.130721 | — | — | — | fail | no | none |
| `5cf87b03-5514-55ae-9cac-0aa6b7c572d3`<br>`5cf87b03-5514-55ae-9cac-0aa6b7c572d3` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #37 / 0.127573 | — | — | — | fail | no | none |
| `92b627e2-da75-52c3-88b6-cdc01aa3b9ef`<br>`92b627e2-da75-52c3-88b6-cdc01aa3b9ef` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #38 / 0.123916 | #30 / 1.559797 | — | — | fail | no | none |
| `3e50e8ee-575c-52c9-a368-f1c6d1c814e1`<br>`3e50e8ee-575c-52c9-a368-f1c6d1c814e1` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #39 / 0.123684 | — | — | — | fail | no | none |
| `3ffac08e-eebd-5bf7-963c-116ad06e0312`<br>`3ffac08e-eebd-5bf7-963c-116ad06e0312` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #40 / 0.120310 | #35 / 1.066657 | — | — | fail | no | none |
| `10c0d44a-0caf-50df-a02a-2ff58404be9d`<br>`10c0d44a-0caf-50df-a02a-2ff58404be9d` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #21 / 3.642190 | — | — | fail | no | none |
| `3175f7bd-0838-5056-a1da-341d951720ed`<br>`3175f7bd-0838-5056-a1da-341d951720ed` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #24 / 3.270535 | — | — | fail | no | none |
| `5a87d328-f076-5953-aa2e-8d7963341f74`<br>`5a87d328-f076-5953-aa2e-8d7963341f74` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #25 / 3.256068 | — | — | fail | no | none |
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #26 / 3.135596 | — | — | fail | no | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | — | #27 / 3.112577 | — | — | fail | no | none |
| `f1b2325d-4bb3-581b-8d14-7b8cdd43f216`<br>`f1b2325d-4bb3-581b-8d14-7b8cdd43f216` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | — | #28 / 2.295775 | — | — | fail | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | — | #33 / 1.174050 | — | — | fail | no | none |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | — | #38 / 0.820292 | — | — | fail | no | none |
| `42e10f18-8de2-53bd-8487-f46c454bf735`<br>`42e10f18-8de2-53bd-8487-f46c454bf735` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | — | #39 / 0.787054 | — | — | fail | no | none |

#### COMPARISON

Candidate funnel: Dense=13 → Sparse=12 → Unique after RRF=13 → Reranker=13 → Threshold=3 → Final evidence=3

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `254c3933-94f2-510b-aa2d-9ab1942de8a7`<br>`254c3933-94f2-510b-aa2d-9ab1942de8a7` | `family.medication.administration`<br>`doc.medication.administration.v1` | #1 / 0.384837 | #1 / 11.813923 | #1 / 0.032787 | #1 / 0.542969 | pass | yes | medication.v1.six-checks |
| `80ddc068-0955-5bb4-92c0-4b1586792c84`<br>`80ddc068-0955-5bb4-92c0-4b1586792c84` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #2 / 0.355341 | #3 / 5.815397 | #3 / 0.032002 | #2 / 0.384766 | pass | yes | none |
| `11a5a524-8a6e-5f08-9a8c-4c470aae9086`<br>`11a5a524-8a6e-5f08-9a8c-4c470aae9086` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #3 / 0.324428 | #2 / 7.832066 | #2 / 0.032002 | #3 / 0.359375 | pass | yes | none |
| `2d65a97b-9023-5d91-8a35-5d78b3934084`<br>`2d65a97b-9023-5d91-8a35-5d78b3934084` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #5 / 0.188879 | — | #13 / 0.015385 | #4 / 0.250000 | fail | no | none |
| `369ceff0-142f-5215-817d-ddafe27e7ace`<br>`369ceff0-142f-5215-817d-ddafe27e7ace` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #13 / 0.098772 | #11 / 0.229863 | #11 / 0.027783 | #5 / 0.238281 | fail | no | none |
| `3f7a6eba-f048-598f-8340-aed3172f8361`<br>`3f7a6eba-f048-598f-8340-aed3172f8361` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | #7 / 0.141687 | #9 / 0.641068 | #9 / 0.029418 | #6 / 0.230469 | fail | no | none |
| `5b68e998-3a65-5808-bc5b-73e28613adc9`<br>`5b68e998-3a65-5808-bc5b-73e28613adc9` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #4 / 0.221800 | #4 / 4.404536 | #4 / 0.031250 | #7 / 0.222656 | fail | no | none |
| `817f4ea7-115c-58d5-9a46-dbaef434a1f2`<br>`817f4ea7-115c-58d5-9a46-dbaef434a1f2` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #10 / 0.116203 | #6 / 3.248618 | #8 / 0.029437 | #8 / 0.218750 | fail | no | none |
| `14b1c8c3-190a-531d-b13e-5666a56b9ac7`<br>`14b1c8c3-190a-531d-b13e-5666a56b9ac7` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #8 / 0.135281 | #7 / 3.083607 | #5 / 0.029631 | #9 / 0.211914 | fail | no | none |
| `72a23d19-05d6-5fe0-8918-f0442b392f2d`<br>`72a23d19-05d6-5fe0-8918-f0442b392f2d` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v1` | #11 / 0.107109 | #5 / 3.312239 | #6 / 0.029469 | #10 / 0.206055 | fail | no | none |
| `07ab0a1c-21e8-5a07-b4ed-3110898b35ca`<br>`07ab0a1c-21e8-5a07-b4ed-3110898b35ca` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #9 / 0.128334 | #8 / 1.518199 | #10 / 0.029199 | #11 / 0.206055 | fail | no | none |
| `13c0e838-be23-5fac-a03d-3c9478b3f41f`<br>`13c0e838-be23-5fac-a03d-3c9478b3f41f` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #12 / 0.100056 | #12 / 0.015588 | #12 / 0.027778 | #12 / 0.200195 | fail | no | none |
| `3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f`<br>`3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f` | `family.fire.drills`<br>`doc.fire.drills.v1` | #6 / 0.151106 | #10 / 0.532881 | #7 / 0.029437 | #13 / 0.187500 | fail | no | none |

### `pilot.current.medication-administration` / `abbreviation`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What does the current policy say about signing a MAR?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What does the current policy say about signing a MAR?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What does the current policy say about signing a MAR?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.mar.sign-after-observation` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.current.medication-administration` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do I tick the meds chart before or after the resident takes it?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do I tick the meds chart before or after the resident takes it?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do I tick the meds chart before or after the resident takes it?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.mar.sign-after-observation` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.current.medication-administration` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When should I sign the MAR after giving a medicine?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "When should I sign the MAR after giving a medicine?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "When should I sign the MAR after giving a medicine?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.mar.sign-after-observation` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.current.scheduled-medication-version` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do we have to add the incident number to the meds chart now?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do we have to add the incident number to the meds chart now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do we have to add the incident number to the meds chart now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v2.omission-current-rule` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.current.scheduled-medication-version` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do omitted doses currently need an electronic incident reference on the MAR?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do omitted doses currently need an electronic incident reference on the MAR?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do omitted doses currently need an electronic incident reference on the MAR?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v2.omission-current-rule` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.current.scheduled-medication-version` / `scheduled`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Has the October electronic MAR rule started yet?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:pilot.current.scheduled-medication-version:scheduled`

Planner contract comparison:

```json
{
  "actual_plan": null,
  "correct": false,
  "differences": [
    {
      "actual": "UNAVAILABLE",
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": "AVAILABLE",
      "field": "validated_plan"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Has the October electronic MAR rule started yet?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v2.omission-current-rule` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |

#### PRIMARY

### `pilot.current.withdrawn-before-authority` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do I email triage first or tell the home manager straight away?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do I email triage first or tell the home manager straight away?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do I email triage first or tell the home manager straight away?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.immediate-manager-report` | `family.safeguarding.adult-reporting` | `doc.safeguarding.adult-reporting.v1` | documents/safeguarding/adult-reporting-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.current.withdrawn-before-authority` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Who must staff tell immediately about a safeguarding concern?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Who must staff tell immediately about a safeguarding concern?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Who must staff tell immediately about a safeguarding concern?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.immediate-manager-report` | `family.safeguarding.adult-reporting` | `doc.safeguarding.adult-reporting.v1` | documents/safeguarding/adult-reporting-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.current.withdrawn-before-authority` / `scheduled`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did the proposed central safeguarding mailbox replace reporting to the manager?
- Covered EvidenceUnits: `safeguarding.immediate-manager-report`
- Metrics: recall=1.0000, precision=0.1000, MRR=0.5000, nDCG=1.0000
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did the proposed central safeguarding mailbox replace reporting to the manager?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did the proposed central safeguarding mailbox replace reporting to the manager?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.immediate-manager-report` | `family.safeguarding.adult-reporting` | `doc.safeguarding.adult-reporting.v1` | documents/safeguarding/adult-reporting-v1.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=3 → Final evidence=3

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #2 / 0.377329 | #1 / 13.929648 | #1 / 0.032522 | #1 / 0.468750 | pass | yes | safeguarding.immediate-manager-report |
| `f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e`<br>`f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #1 / 0.391505 | #2 / 12.286797 | #2 / 0.032522 | #2 / 0.388672 | pass | yes | none |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #3 / 0.332627 | #5 / 9.037687 | #3 / 0.031258 | #3 / 0.341797 | pass | yes | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #14 / 0.208711 | #3 / 11.037639 | #5 / 0.029387 | #4 / 0.328125 | fail | no | none |
| `3cc16b3c-7d04-53a9-a273-eddea88a3ccb`<br>`3cc16b3c-7d04-53a9-a273-eddea88a3ccb` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #4 / 0.320150 | #24 / 3.867868 | #8 / 0.027530 | #5 / 0.312500 | fail | no | none |
| `b4f8b48b-d6bb-55bf-9808-e81c551b09f8`<br>`b4f8b48b-d6bb-55bf-9808-e81c551b09f8` | `family.complaints.advocacy`<br>`doc.complaints.advocacy.v1` | #9 / 0.248376 | #27 / 3.682337 | #15 / 0.025987 | #6 / 0.300781 | fail | no | none |
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #5 / 0.316985 | #9 / 6.263682 | #4 / 0.029877 | #7 / 0.294922 | fail | no | none |
| `3e50e8ee-575c-52c9-a368-f1c6d1c814e1`<br>`3e50e8ee-575c-52c9-a368-f1c6d1c814e1` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #16 / 0.193048 | #4 / 9.140407 | #6 / 0.028783 | #8 / 0.292969 | fail | no | none |
| `6ba08511-5e10-530d-9a62-17ffed9e9bc4`<br>`6ba08511-5e10-530d-9a62-17ffed9e9bc4` | `family.training.induction`<br>`doc.training.induction.v1` | #29 / 0.155954 | #7 / 6.762540 | #12 / 0.026161 | #9 / 0.279297 | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #15 / 0.198426 | #18 / 4.423649 | #13 / 0.026154 | #10 / 0.277344 | fail | no | none |
| `893f68e3-e8d2-5acd-9a73-8f30912e2431`<br>`893f68e3-e8d2-5acd-9a73-8f30912e2431` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | #10 / 0.224006 | #21 / 4.326466 | #10 / 0.026631 | #11 / 0.269531 | fail | no | none |
| `5a87d328-f076-5953-aa2e-8d7963341f74`<br>`5a87d328-f076-5953-aa2e-8d7963341f74` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #20 / 0.179784 | #14 / 5.094898 | #14 / 0.026014 | #12 / 0.263672 | fail | no | none |
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | #27 / 0.156718 | #8 / 6.532420 | #11 / 0.026200 | #13 / 0.238281 | fail | no | none |
| `65dda7f5-3688-515f-8d78-25e87c41a7e0`<br>`65dda7f5-3688-515f-8d78-25e87c41a7e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | #19 / 0.183581 | #6 / 7.210464 | #7 / 0.027810 | #14 / 0.223633 | fail | no | none |
| `42e10f18-8de2-53bd-8487-f46c454bf735`<br>`42e10f18-8de2-53bd-8487-f46c454bf735` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #13 / 0.214495 | #13 / 5.272773 | #9 / 0.027397 | #15 / 0.221680 | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #6 / 0.279141 | #34 / 2.918547 | — | — | fail | no | none |
| `f4b9f291-51c7-5e35-9335-b7e3dd2b37ef`<br>`f4b9f291-51c7-5e35-9335-b7e3dd2b37ef` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #7 / 0.273934 | #38 / 2.765391 | — | — | fail | no | none |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #8 / 0.266660 | #29 / 3.576469 | — | — | fail | no | none |
| `ee3bb1bd-f03f-5314-b408-a1895aaadc2e`<br>`ee3bb1bd-f03f-5314-b408-a1895aaadc2e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #11 / 0.223197 | #33 / 2.932081 | — | — | fail | no | none |
| `40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc`<br>`40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | #12 / 0.219322 | #37 / 2.772556 | — | — | fail | no | none |
| `f193cb26-bd92-5fb8-a0b1-ba2c829f658b`<br>`f193cb26-bd92-5fb8-a0b1-ba2c829f658b` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #17 / 0.192345 | — | — | — | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #18 / 0.186164 | #16 / 4.783222 | — | — | fail | no | none |
| `ee3b92cf-7201-50f5-9315-841d5bceb277`<br>`ee3b92cf-7201-50f5-9315-841d5bceb277` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #21 / 0.169453 | — | — | — | fail | no | none |
| `249cc883-6c9a-5099-bdbb-974f04227e23`<br>`249cc883-6c9a-5099-bdbb-974f04227e23` | `family.complaints.form`<br>`doc.complaints.form.v1` | #22 / 0.165065 | — | — | — | fail | no | none |
| `2dc51247-e552-5a57-91c3-9408e34f5d94`<br>`2dc51247-e552-5a57-91c3-9408e34f5d94` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #23 / 0.161152 | — | — | — | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #24 / 0.160928 | #19 / 4.374722 | — | — | fail | no | none |
| `ccc94945-e377-526e-93c2-5fd324619661`<br>`ccc94945-e377-526e-93c2-5fd324619661` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | #25 / 0.157535 | — | — | — | fail | no | none |
| `1f7baac6-5792-5b2a-9399-26ad4c21d6e4`<br>`1f7baac6-5792-5b2a-9399-26ad4c21d6e4` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #26 / 0.157041 | #20 / 4.358936 | — | — | fail | no | none |
| `f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0`<br>`f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | #28 / 0.156233 | — | — | — | fail | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #30 / 0.150769 | #31 / 3.176620 | — | — | fail | no | none |
| `1839469e-5726-503f-a711-a010a97420fd`<br>`1839469e-5726-503f-a711-a010a97420fd` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #31 / 0.149483 | #10 / 5.915686 | — | — | fail | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #32 / 0.148928 | — | — | — | fail | no | none |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #33 / 0.147185 | #28 / 3.615102 | — | — | fail | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #34 / 0.146557 | — | — | — | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #35 / 0.144505 | #35 / 2.905136 | — | — | fail | no | none |
| `14ab94b0-4ade-5c5c-b5bd-77eae8daf94d`<br>`14ab94b0-4ade-5c5c-b5bd-77eae8daf94d` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #36 / 0.144324 | — | — | — | fail | no | none |
| `f85e71bc-4d62-57d9-b403-b13b1a9ff199`<br>`f85e71bc-4d62-57d9-b403-b13b1a9ff199` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #37 / 0.143425 | #11 / 5.834258 | — | — | fail | no | none |
| `4d1f0d61-d751-52f0-87dd-0327ea89db4e`<br>`4d1f0d61-d751-52f0-87dd-0327ea89db4e` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | #38 / 0.142411 | #36 / 2.815755 | — | — | fail | no | none |
| `ebda80a6-77c7-557b-9450-fbddfdb16e02`<br>`ebda80a6-77c7-557b-9450-fbddfdb16e02` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #39 / 0.141516 | — | — | — | fail | no | none |
| `6a0fb733-bff0-55d1-a5e7-d322ef9e53a9`<br>`6a0fb733-bff0-55d1-a5e7-d322ef9e53a9` | `family.training.matrix`<br>`doc.training.matrix.v1` | #40 / 0.139956 | #23 / 4.124612 | — | — | fail | no | none |
| `10c0d44a-0caf-50df-a02a-2ff58404be9d`<br>`10c0d44a-0caf-50df-a02a-2ff58404be9d` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #12 / 5.600668 | — | — | fail | no | none |
| `3175f7bd-0838-5056-a1da-341d951720ed`<br>`3175f7bd-0838-5056-a1da-341d951720ed` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #15 / 4.866564 | — | — | fail | no | none |
| `8d8de832-6d4c-5368-b209-2ece5159b021`<br>`8d8de832-6d4c-5368-b209-2ece5159b021` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | — | #17 / 4.477787 | — | — | fail | no | none |
| `aeb0ea01-92b2-5418-ad27-c95cacb3b030`<br>`aeb0ea01-92b2-5418-ad27-c95cacb3b030` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | — | #22 / 4.305351 | — | — | fail | no | none |
| `d24f4e43-6251-56d5-b470-c23242fe6873`<br>`d24f4e43-6251-56d5-b470-c23242fe6873` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #25 / 3.776420 | — | — | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | — | #26 / 3.742600 | — | — | fail | no | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | — | #30 / 3.573907 | — | — | fail | no | none |
| `5cf87b03-5514-55ae-9cac-0aa6b7c572d3`<br>`5cf87b03-5514-55ae-9cac-0aa6b7c572d3` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | — | #32 / 3.042092 | — | — | fail | no | none |
| `635ff5e9-ecb1-559b-8683-4b7a96ea7bd9`<br>`635ff5e9-ecb1-559b-8683-4b7a96ea7bd9` | `family.fire.drills`<br>`doc.fire.drills.v2` | — | #39 / 2.761816 | — | — | fail | no | none |
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | — | #40 / 2.739832 | — | — | fail | no | none |

#### COMPARISON

Candidate funnel: Dense=13 → Sparse=11 → Unique after RRF=13 → Reranker=13 → Threshold=1 → Final evidence=1

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `13c0e838-be23-5fac-a03d-3c9478b3f41f`<br>`13c0e838-be23-5fac-a03d-3c9478b3f41f` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #1 / 0.314559 | #1 / 9.414451 | #1 / 0.032787 | #1 / 0.341797 | pass | yes | none |
| `07ab0a1c-21e8-5a07-b4ed-3110898b35ca`<br>`07ab0a1c-21e8-5a07-b4ed-3110898b35ca` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #3 / 0.200654 | #2 / 8.248656 | #2 / 0.032002 | #2 / 0.306641 | fail | no | none |
| `11a5a524-8a6e-5f08-9a8c-4c470aae9086`<br>`11a5a524-8a6e-5f08-9a8c-4c470aae9086` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #7 / 0.130739 | #5 / 4.934194 | #5 / 0.030310 | #3 / 0.269531 | fail | no | none |
| `817f4ea7-115c-58d5-9a46-dbaef434a1f2`<br>`817f4ea7-115c-58d5-9a46-dbaef434a1f2` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #4 / 0.167146 | #4 / 5.152070 | #4 / 0.031250 | #4 / 0.257812 | fail | no | none |
| `14b1c8c3-190a-531d-b13e-5666a56b9ac7`<br>`14b1c8c3-190a-531d-b13e-5666a56b9ac7` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #2 / 0.223635 | #3 / 6.017856 | #3 / 0.032002 | #5 / 0.248047 | fail | no | none |
| `3f7a6eba-f048-598f-8340-aed3172f8361`<br>`3f7a6eba-f048-598f-8340-aed3172f8361` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | #8 / 0.127428 | #8 / 3.122316 | #8 / 0.029412 | #6 / 0.241211 | fail | no | none |
| `254c3933-94f2-510b-aa2d-9ab1942de8a7`<br>`254c3933-94f2-510b-aa2d-9ab1942de8a7` | `family.medication.administration`<br>`doc.medication.administration.v1` | #10 / 0.089532 | #11 / 0.867564 | #11 / 0.028370 | #7 / 0.241211 | fail | no | none |
| `2d65a97b-9023-5d91-8a35-5d78b3934084`<br>`2d65a97b-9023-5d91-8a35-5d78b3934084` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #5 / 0.154353 | #7 / 3.937189 | #6 / 0.030310 | #8 / 0.236328 | fail | no | none |
| `3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f`<br>`3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f` | `family.fire.drills`<br>`doc.fire.drills.v1` | #9 / 0.109209 | #9 / 2.607059 | #10 / 0.028986 | #9 / 0.206055 | fail | no | none |
| `5b68e998-3a65-5808-bc5b-73e28613adc9`<br>`5b68e998-3a65-5808-bc5b-73e28613adc9` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #6 / 0.136879 | #10 / 1.434339 | #7 / 0.029437 | #10 / 0.198242 | fail | no | none |
| `72a23d19-05d6-5fe0-8918-f0442b392f2d`<br>`72a23d19-05d6-5fe0-8918-f0442b392f2d` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v1` | #12 / 0.060780 | #6 / 4.399882 | #9 / 0.029040 | #11 / 0.177734 | fail | no | none |
| `80ddc068-0955-5bb4-92c0-4b1586792c84`<br>`80ddc068-0955-5bb4-92c0-4b1586792c84` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #13 / 0.026471 | — | #13 / 0.013699 | #12 / 0.171875 | fail | no | none |
| `369ceff0-142f-5215-817d-ddafe27e7ace`<br>`369ceff0-142f-5215-817d-ddafe27e7ace` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #11 / 0.084640 | — | #12 / 0.014085 | #13 / 0.155273 | fail | no | none |

### `pilot.current.withdrawn-no-resurrection` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `NO_ELIGIBLE_EVIDENCE`
- Text capture: `BENCHMARK_TEXT`
- Question: Which outbreak rules do we use now that the newer one was pulled?
- Covered EvidenceUnits: `none`
- Metrics: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
- Hard failures: `eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Which outbreak rules do we use now that the newer one was pulled?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Which outbreak rules do we use now that the newer one was pulled?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```


### `pilot.current.withdrawn-no-resurrection` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `NO_ELIGIBLE_EVIDENCE`
- Text capture: `BENCHMARK_TEXT`
- Question: What is the current respiratory outbreak procedure?
- Covered EvidenceUnits: `none`
- Metrics: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
- Hard failures: `eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What is the current respiratory outbreak procedure?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What is the current respiratory outbreak procedure?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```


### `pilot.current.withdrawn-no-resurrection` / `withdrawn`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `NO_ELIGIBLE_EVIDENCE`
- Text capture: `BENCHMARK_TEXT`
- Question: After version 2 was withdrawn, did the old outbreak procedure become current again?
- Covered EvidenceUnits: `none`
- Metrics: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
- Hard failures: `eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "After version 2 was withdrawn, did the old outbreak procedure become current again?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "After version 2 was withdrawn, did the old outbreak procedure become current again?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```


### `pilot.location-alias.bristol` / `alias`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Where do visitors assemble during a fire at the Bristol home?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Bristol home"
    ],
    "retrieval_queries": [
      "Where do visitors assemble during a fire at the Bristol home?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": false,
  "differences": [
    {
      "actual": [
        "Bristol home"
      ],
      "classification": "POTENTIAL_ALIAS_OR_REPRESENTATION_MISMATCH",
      "expected": [
        "the Bristol home"
      ],
      "field": "location_references"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "the Bristol home"
    ],
    "retrieval_queries": [
      "Where do visitors assemble during a fire at the Bristol home?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.harbour-view.assembly-point` | `family.fire.harbour-view-evacuation` | `doc.fire.harbour-view-evacuation.v1` | documents/fire-safety/harbour-view-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.location-alias.bristol` / `canonical`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What is the Harbour View fire assembly point?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Harbour View"
    ],
    "retrieval_queries": [
      "What is the Harbour View fire assembly point?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Harbour View"
    ],
    "retrieval_queries": [
      "What is the Harbour View fire assembly point?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.harbour-view.assembly-point` | `family.fire.harbour-view-evacuation` | `doc.fire.harbour-view-evacuation.v1` | documents/fire-safety/harbour-view-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.location-alias.bristol` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: If the alarm goes at Bristol, where should visitors wait outside?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Bristol"
    ],
    "retrieval_queries": [
      "If the alarm goes at Bristol, where should visitors wait outside?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Bristol"
    ],
    "retrieval_queries": [
      "If the alarm goes at Bristol, where should visitors wait outside?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `fire.harbour-view.assembly-point` | `family.fire.harbour-view-evacuation` | `doc.fire.harbour-view-evacuation.v1` | documents/fire-safety/harbour-view-evacuation.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.multi-document.medication-storage` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: The meds fridge is too warm — can I still give the medicine and who do I call?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "meds fridge"
    ],
    "retrieval_queries": [
      "The meds fridge is too warm — can I still give the medicine and who do I call?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": false,
  "differences": [
    {
      "actual": [
        "meds fridge"
      ],
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": [],
      "field": "location_references"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "The meds fridge is too warm — can I still give the medicine and who do I call?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.administration.fridge-gate` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |
| PRIMARY | `medication.storage.out-of-range-response` | `family.medication.storage` | `doc.medication.storage.v1` | documents/medication/storage-temperature-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.multi-document.medication-storage` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What should I do if the medicines fridge reads 9°C before the drug round?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "medicines fridge"
    ],
    "retrieval_queries": [
      "What should I do if the medicines fridge reads 9°C before the drug round?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": false,
  "differences": [
    {
      "actual": [
        "medicines fridge"
      ],
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": [],
      "field": "location_references"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What should I do if the medicines fridge reads 9°C before the drug round?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.administration.fridge-gate` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |
| PRIMARY | `medication.storage.out-of-range-response` | `family.medication.storage` | `doc.medication.storage.v1` | documents/medication/storage-temperature-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.multi-document.medication-storage` / `numeric`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What do the policy and storage procedure require for a 9 degree fridge reading?
- Covered EvidenceUnits: `medication.storage.out-of-range-response`
- Metrics: recall=0.5000, precision=0.2000, MRR=1.0000, nDCG=0.6131
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What do the policy and storage procedure require for a 9 degree fridge reading?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What do the policy and storage procedure require for a 9 degree fridge reading?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.5000, precision=0.2000, MRR=1.0000, nDCG=0.6131

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.administration.fridge-gate` | `family.medication.administration` | `doc.medication.administration.v2` | documents/medication/safe-administration-v2.md |
| PRIMARY | `medication.storage.out-of-range-response` | `family.medication.storage` | `doc.medication.storage.v1` | documents/medication/storage-temperature-procedure.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=2 → Final evidence=2

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `47a813db-42a0-5b2b-9631-4c30ef6d0306`<br>`47a813db-42a0-5b2b-9631-4c30ef6d0306` | `family.medication.storage`<br>`doc.medication.storage.v1` | #2 / 0.498971 | #1 / 17.190609 | #1 / 0.032522 | #1 / 0.679688 | pass | yes | medication.storage.out-of-range-response |
| `801b4c5b-787b-5e04-99ca-83dd8844448d`<br>`801b4c5b-787b-5e04-99ca-83dd8844448d` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #1 / 0.515132 | #2 / 10.459700 | #2 / 0.032522 | #2 / 0.562500 | pass | yes | none |
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #22 / 0.175368 | #11 / 3.786900 | #9 / 0.026280 | #3 / 0.249023 | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #13 / 0.199449 | #5 / 4.574161 | #3 / 0.029083 | #4 / 0.242188 | fail | no | none |
| `799b04a0-74e1-5134-a911-0c2ccbda4c15`<br>`799b04a0-74e1-5134-a911-0c2ccbda4c15` | `family.medication.administration`<br>`doc.medication.administration.v2` | #7 / 0.247959 | #13 / 3.571804 | #6 / 0.028624 | #5 / 0.236328 | fail | no | none |
| `56745918-8c2b-5490-a300-4c18bf32a5c6`<br>`56745918-8c2b-5490-a300-4c18bf32a5c6` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #8 / 0.227634 | #34 / 2.524493 | #15 / 0.025344 | #6 / 0.228516 | fail | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #6 / 0.254776 | #17 / 3.122557 | #7 / 0.028139 | #7 / 0.225586 | fail | no | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | #5 / 0.257150 | #15 / 3.218704 | #5 / 0.028718 | #8 / 0.210938 | fail | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #10 / 0.219741 | #8 / 3.968321 | #4 / 0.028992 | #9 / 0.202148 | fail | no | none |
| `92b627e2-da75-52c3-88b6-cdc01aa3b9ef`<br>`92b627e2-da75-52c3-88b6-cdc01aa3b9ef` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #31 / 0.164119 | #6 / 4.091245 | #10 / 0.026141 | #10 / 0.200195 | fail | no | none |
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | #11 / 0.203896 | #20 / 2.974987 | #8 / 0.026585 | #11 / 0.188477 | fail | no | none |
| `955ca35a-ad9d-57fb-8c12-e79c9190c2cd`<br>`955ca35a-ad9d-57fb-8c12-e79c9190c2cd` | `family.visitors.general`<br>`doc.visitors.general.v1` | #28 / 0.168040 | #9 / 3.964911 | #12 / 0.025856 | #12 / 0.187500 | fail | no | none |
| `635ff5e9-ecb1-559b-8683-4b7a96ea7bd9`<br>`635ff5e9-ecb1-559b-8683-4b7a96ea7bd9` | `family.fire.drills`<br>`doc.fire.drills.v2` | #12 / 0.202685 | #27 / 2.739693 | #14 / 0.025383 | #13 / 0.183594 | fail | no | none |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #27 / 0.168059 | #10 / 3.915946 | #13 / 0.025780 | #14 / 0.181641 | fail | no | none |
| `1f7baac6-5792-5b2a-9399-26ad4c21d6e4`<br>`1f7baac6-5792-5b2a-9399-26ad4c21d6e4` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #18 / 0.187305 | #16 / 3.187715 | #11 / 0.025978 | #15 / 0.171875 | fail | no | none |
| `b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef`<br>`b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.393526 | — | — | — | fail | no | medication.administration.fridge-gate |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #4 / 0.274359 | — | — | — | fail | no | none |
| `fc1749ce-678f-5b79-9a27-41ca33d2043c`<br>`fc1749ce-678f-5b79-9a27-41ca33d2043c` | `family.medication.prn`<br>`doc.medication.prn.v1` | #9 / 0.220081 | — | — | — | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #14 / 0.196807 | — | — | — | fail | no | none |
| `14ab94b0-4ade-5c5c-b5bd-77eae8daf94d`<br>`14ab94b0-4ade-5c5c-b5bd-77eae8daf94d` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #15 / 0.192457 | #31 / 2.579162 | — | — | fail | no | none |
| `256e756b-7110-5070-9432-97bb1923a202`<br>`256e756b-7110-5070-9432-97bb1923a202` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #16 / 0.190489 | #30 / 2.587747 | — | — | fail | no | none |
| `ccc94945-e377-526e-93c2-5fd324619661`<br>`ccc94945-e377-526e-93c2-5fd324619661` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | #17 / 0.187405 | — | — | — | fail | no | none |
| `1a330d42-d249-5bf6-ba4b-066222bc5f5b`<br>`1a330d42-d249-5bf6-ba4b-066222bc5f5b` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #19 / 0.185810 | — | — | — | fail | no | none |
| `ee3b92cf-7201-50f5-9315-841d5bceb277`<br>`ee3b92cf-7201-50f5-9315-841d5bceb277` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #20 / 0.180477 | #37 / 2.415808 | — | — | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #21 / 0.179578 | — | — | — | fail | no | none |
| `0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6`<br>`0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #23 / 0.174995 | — | — | — | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #24 / 0.174134 | #19 / 3.042982 | — | — | fail | no | none |
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | #25 / 0.171867 | — | — | — | fail | no | none |
| `f85e71bc-4d62-57d9-b403-b13b1a9ff199`<br>`f85e71bc-4d62-57d9-b403-b13b1a9ff199` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #26 / 0.171109 | — | — | — | fail | no | none |
| `ee3bb1bd-f03f-5314-b408-a1895aaadc2e`<br>`ee3bb1bd-f03f-5314-b408-a1895aaadc2e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #29 / 0.167131 | #23 / 2.929525 | — | — | fail | no | none |
| `2dc51247-e552-5a57-91c3-9408e34f5d94`<br>`2dc51247-e552-5a57-91c3-9408e34f5d94` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #30 / 0.165880 | #12 / 3.612779 | — | — | fail | no | none |
| `5a0ad7a9-b4c1-5072-a3b8-d527805bad81`<br>`5a0ad7a9-b4c1-5072-a3b8-d527805bad81` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #32 / 0.163042 | — | — | — | fail | no | none |
| `4d1f0d61-d751-52f0-87dd-0327ea89db4e`<br>`4d1f0d61-d751-52f0-87dd-0327ea89db4e` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | #33 / 0.161536 | — | — | — | fail | no | none |
| `5cf87b03-5514-55ae-9cac-0aa6b7c572d3`<br>`5cf87b03-5514-55ae-9cac-0aa6b7c572d3` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #34 / 0.160007 | — | — | — | fail | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #35 / 0.158243 | #28 / 2.682054 | — | — | fail | no | none |
| `f193cb26-bd92-5fb8-a0b1-ba2c829f658b`<br>`f193cb26-bd92-5fb8-a0b1-ba2c829f658b` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #36 / 0.155719 | #18 / 3.073168 | — | — | fail | no | none |
| `b3036236-deaa-5719-ad41-3c5d87bbe7d8`<br>`b3036236-deaa-5719-ad41-3c5d87bbe7d8` | `family.training.fire`<br>`doc.training.fire.v1` | #37 / 0.153535 | — | — | — | fail | no | none |
| `1839469e-5726-503f-a711-a010a97420fd`<br>`1839469e-5726-503f-a711-a010a97420fd` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #38 / 0.151059 | — | — | — | fail | no | none |
| `ac335280-6bca-5150-bd9b-db2d198ca588`<br>`ac335280-6bca-5150-bd9b-db2d198ca588` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #39 / 0.150737 | — | — | — | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #40 / 0.148626 | #7 / 4.038187 | — | — | fail | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | — | #3 / 5.070760 | — | — | fail | no | none |
| `10c0d44a-0caf-50df-a02a-2ff58404be9d`<br>`10c0d44a-0caf-50df-a02a-2ff58404be9d` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #4 / 4.756361 | — | — | fail | no | none |
| `d24f4e43-6251-56d5-b470-c23242fe6873`<br>`d24f4e43-6251-56d5-b470-c23242fe6873` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #14 / 3.223811 | — | — | fail | no | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | — | #21 / 2.964641 | — | — | fail | no | none |
| `aeb0ea01-92b2-5418-ad27-c95cacb3b030`<br>`aeb0ea01-92b2-5418-ad27-c95cacb3b030` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | — | #22 / 2.949214 | — | — | fail | no | none |
| `be5c3624-95a2-5d5d-9f05-a9fb635d68a6`<br>`be5c3624-95a2-5d5d-9f05-a9fb635d68a6` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | — | #24 / 2.917415 | — | — | fail | no | none |
| `018c7c48-f558-5416-8a50-2043b3d3b7b8`<br>`018c7c48-f558-5416-8a50-2043b3d3b7b8` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #25 / 2.915689 | — | — | fail | no | none |
| `f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e`<br>`f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | — | #26 / 2.750943 | — | — | fail | no | none |
| `65dda7f5-3688-515f-8d78-25e87c41a7e0`<br>`65dda7f5-3688-515f-8d78-25e87c41a7e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #29 / 2.667293 | — | — | fail | no | none |
| `3175f7bd-0838-5056-a1da-341d951720ed`<br>`3175f7bd-0838-5056-a1da-341d951720ed` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #32 / 2.558365 | — | — | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | — | #33 / 2.528373 | — | — | fail | no | none |
| `40b84d12-bb43-5dc3-a182-d80b51693330`<br>`40b84d12-bb43-5dc3-a182-d80b51693330` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | — | #35 / 2.491583 | — | — | fail | no | none |
| `893f68e3-e8d2-5acd-9a73-8f30912e2431`<br>`893f68e3-e8d2-5acd-9a73-8f30912e2431` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #36 / 2.449947 | — | — | fail | no | none |
| `20575c0a-658b-508a-a009-60706b3fde3c`<br>`20575c0a-658b-508a-a009-60706b3fde3c` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | — | #38 / 2.396295 | — | — | fail | no | none |
| `f4b9f291-51c7-5e35-9335-b7e3dd2b37ef`<br>`f4b9f291-51c7-5e35-9335-b7e3dd2b37ef` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | — | #39 / 2.337537 | — | — | fail | no | none |
| `5a87d328-f076-5953-aa2e-8d7963341f74`<br>`5a87d328-f076-5953-aa2e-8d7963341f74` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #40 / 2.306354 | — | — | fail | no | none |

### `pilot.table.training-refresh` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When does the fire warden course need renewing?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "When does the fire warden course need renewing?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "When does the fire warden course need renewing?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.fire-marshal.interval` | `family.training.matrix` | `doc.training.matrix.v1` | documents/training/mandatory-training-matrix.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.table.training-refresh` / `contrast`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is fire marshal refresher training yearly or every two years?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Is fire marshal refresher training yearly or every two years?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Is fire marshal refresher training yearly or every two years?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.fire-marshal.interval` | `family.training.matrix` | `doc.training.matrix.v1` | documents/training/mandatory-training-matrix.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.table.training-refresh` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How often must a fire marshal repeat practical training?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How often must a fire marshal repeat practical training?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How often must a fire marshal repeat practical training?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.fire-marshal.interval` | `family.training.matrix` | `doc.training.matrix.v1` | documents/training/mandatory-training-matrix.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.valid-at-date.medication-administration` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What did the old meds policy say to put on the chart when someone refused?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What did the old meds policy say to put on the chart when someone refused?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "old meds policy"
    }
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What did the old meds policy say to put on the chart when someone refused?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "old meds policy"
    }
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v1.refused-code` | `family.medication.administration` | `doc.medication.administration.v1` | documents/medication/safe-administration-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.valid-at-date.medication-administration` / `dated`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What MAR code applied to a refused dose on 1 June 2024?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": "2024-06-01",
    "location_references": [],
    "retrieval_queries": [
      "What MAR code applied to a refused dose on 1 June 2024?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": "2024-06-01",
    "location_references": [],
    "retrieval_queries": [
      "What MAR code applied to a refused dose on 1 June 2024?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v1.refused-code` | `family.medication.administration` | `doc.medication.administration.v1` | documents/medication/safe-administration-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `pilot.valid-at-date.medication-administration` / `historical`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: In June 2024, how did staff record medicine refusal?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "In June 2024, how did staff record medicine refusal?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "June 2024"
    }
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "In June 2024, how did staff record medicine refusal?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": {
      "kind": "CALENDAR_PERIOD",
      "value": "June 2024"
    }
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `medication.v1.refused-code` | `family.medication.administration` | `doc.medication.administration.v1` | documents/medication/safe-administration-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.allegations.compare-process` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did HR used to be told later than they are now?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did HR used to be told later than they are now?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did HR used to be told later than they are now?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.allegations.v2-hr` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v2` | documents/safeguarding/allegations-staff-v2.md |
| COMPARISON | `safeguarding.allegations.v1-hr` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v1` | documents/safeguarding/allegations-staff-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.allegations.compare-process` / `compare`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare the old and current deadlines for telling HR about a staff allegation.
- Covered EvidenceUnits: `safeguarding.allegations.v1-hr, safeguarding.allegations.v2-hr`
- Metrics: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare the old and current deadlines for telling HR about a staff allegation."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare the old and current deadlines for telling HR about a staff allegation."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.allegations.v2-hr` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v2` | documents/safeguarding/allegations-staff-v2.md |
| COMPARISON | `safeguarding.allegations.v1-hr` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v1` | documents/safeguarding/allegations-staff-v1.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=6 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #1 / 0.412684 | #2 / 10.162714 | #1 / 0.032522 | #1 / 0.718750 | pass | yes | safeguarding.allegations.v2-hr |
| `5a87d328-f076-5953-aa2e-8d7963341f74`<br>`5a87d328-f076-5953-aa2e-8d7963341f74` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #6 / 0.269277 | #11 / 5.253590 | #5 / 0.029236 | #2 / 0.500000 | pass | yes | none |
| `893f68e3-e8d2-5acd-9a73-8f30912e2431`<br>`893f68e3-e8d2-5acd-9a73-8f30912e2431` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | #3 / 0.290157 | #8 / 5.733540 | #3 / 0.030579 | #3 / 0.464844 | pass | yes | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #4 / 0.281639 | #14 / 4.776266 | #8 / 0.029139 | #4 / 0.412109 | pass | yes | none |
| `f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e`<br>`f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #2 / 0.324577 | #33 / 3.086822 | #15 / 0.026882 | #5 / 0.382812 | pass | yes | none |
| `2dc51247-e552-5a57-91c3-9408e34f5d94`<br>`2dc51247-e552-5a57-91c3-9408e34f5d94` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #9 / 0.265723 | #1 / 11.165953 | #2 / 0.030886 | #6 / 0.359375 | pass | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #7 / 0.267761 | #17 / 4.701155 | #12 / 0.027912 | #7 / 0.335938 | fail | no | none |
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #8 / 0.266136 | #7 / 5.926226 | #4 / 0.029631 | #8 / 0.306641 | fail | no | none |
| `f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0`<br>`f61cc256-e23f-5cb2-8cbb-4cab9bb0c1e0` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | #12 / 0.245731 | #13 / 5.146770 | #14 / 0.027588 | #9 / 0.294922 | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #10 / 0.255760 | #10 / 5.464426 | #9 / 0.028571 | #10 / 0.287109 | fail | no | none |
| `3175f7bd-0838-5056-a1da-341d951720ed`<br>`3175f7bd-0838-5056-a1da-341d951720ed` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #16 / 0.230091 | #9 / 5.718964 | #13 / 0.027651 | #11 / 0.277344 | fail | no | none |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #11 / 0.253414 | #6 / 5.986567 | #6 / 0.029236 | #12 / 0.267578 | fail | no | none |
| `1f7baac6-5792-5b2a-9399-26ad4c21d6e4`<br>`1f7baac6-5792-5b2a-9399-26ad4c21d6e4` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #18 / 0.227362 | #5 / 6.192374 | #10 / 0.028205 | #13 / 0.263672 | fail | no | none |
| `65dda7f5-3688-515f-8d78-25e87c41a7e0`<br>`65dda7f5-3688-515f-8d78-25e87c41a7e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | #15 / 0.230426 | #3 / 7.328196 | #7 / 0.029206 | #14 / 0.257812 | fail | no | none |
| `ebda80a6-77c7-557b-9450-fbddfdb16e02`<br>`ebda80a6-77c7-557b-9450-fbddfdb16e02` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #21 / 0.214976 | #4 / 7.016129 | #11 / 0.027971 | #15 / 0.255859 | fail | no | none |
| `ee3bb1bd-f03f-5314-b408-a1895aaadc2e`<br>`ee3bb1bd-f03f-5314-b408-a1895aaadc2e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #5 / 0.273411 | — | — | — | fail | no | none |
| `3cc16b3c-7d04-53a9-a273-eddea88a3ccb`<br>`3cc16b3c-7d04-53a9-a273-eddea88a3ccb` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #13 / 0.243167 | — | — | — | fail | no | none |
| `919b1651-7a62-5792-b47f-6ac4fc784017`<br>`919b1651-7a62-5792-b47f-6ac4fc784017` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | #14 / 0.234835 | — | — | — | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #17 / 0.227612 | — | — | — | fail | no | none |
| `aeb0ea01-92b2-5418-ad27-c95cacb3b030`<br>`aeb0ea01-92b2-5418-ad27-c95cacb3b030` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #19 / 0.220728 | #28 / 3.479325 | — | — | fail | no | none |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #20 / 0.216550 | #39 / 2.667994 | — | — | fail | no | none |
| `3e50e8ee-575c-52c9-a368-f1c6d1c814e1`<br>`3e50e8ee-575c-52c9-a368-f1c6d1c814e1` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #22 / 0.212737 | #20 / 4.253651 | — | — | fail | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #23 / 0.209643 | #16 / 4.705083 | — | — | fail | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #24 / 0.207999 | #12 / 5.227235 | — | — | fail | no | none |
| `6ba08511-5e10-530d-9a62-17ffed9e9bc4`<br>`6ba08511-5e10-530d-9a62-17ffed9e9bc4` | `family.training.induction`<br>`doc.training.induction.v1` | #25 / 0.202967 | — | — | — | fail | no | none |
| `42e10f18-8de2-53bd-8487-f46c454bf735`<br>`42e10f18-8de2-53bd-8487-f46c454bf735` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #26 / 0.202739 | #21 / 4.251698 | — | — | fail | no | none |
| `85950010-d571-5bd3-9c8e-78b2687219d7`<br>`85950010-d571-5bd3-9c8e-78b2687219d7` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | #27 / 0.201259 | #31 / 3.171255 | — | — | fail | no | none |
| `018c7c48-f558-5416-8a50-2043b3d3b7b8`<br>`018c7c48-f558-5416-8a50-2043b3d3b7b8` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | #28 / 0.198669 | #32 / 3.166767 | — | — | fail | no | none |
| `635ff5e9-ecb1-559b-8683-4b7a96ea7bd9`<br>`635ff5e9-ecb1-559b-8683-4b7a96ea7bd9` | `family.fire.drills`<br>`doc.fire.drills.v2` | #29 / 0.195604 | #37 / 2.725575 | — | — | fail | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #30 / 0.192624 | #30 / 3.203415 | — | — | fail | no | none |
| `f193cb26-bd92-5fb8-a0b1-ba2c829f658b`<br>`f193cb26-bd92-5fb8-a0b1-ba2c829f658b` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #31 / 0.191681 | #15 / 4.757512 | — | — | fail | no | none |
| `6a0fb733-bff0-55d1-a5e7-d322ef9e53a9`<br>`6a0fb733-bff0-55d1-a5e7-d322ef9e53a9` | `family.training.matrix`<br>`doc.training.matrix.v1` | #32 / 0.190456 | #26 / 3.739878 | — | — | fail | no | none |
| `249cc883-6c9a-5099-bdbb-974f04227e23`<br>`249cc883-6c9a-5099-bdbb-974f04227e23` | `family.complaints.form`<br>`doc.complaints.form.v1` | #33 / 0.182153 | — | — | — | fail | no | none |
| `f4b9f291-51c7-5e35-9335-b7e3dd2b37ef`<br>`f4b9f291-51c7-5e35-9335-b7e3dd2b37ef` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #34 / 0.180296 | — | — | — | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #35 / 0.178625 | — | — | — | fail | no | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | #36 / 0.178055 | #38 / 2.704592 | — | — | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #37 / 0.175787 | #40 / 2.616048 | — | — | fail | no | none |
| `40b84d12-bb43-5dc3-a182-d80b51693330`<br>`40b84d12-bb43-5dc3-a182-d80b51693330` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #38 / 0.170742 | — | — | — | fail | no | none |
| `f85e71bc-4d62-57d9-b403-b13b1a9ff199`<br>`f85e71bc-4d62-57d9-b403-b13b1a9ff199` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #39 / 0.170025 | — | — | — | fail | no | none |
| `b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef`<br>`b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef` | `family.medication.administration`<br>`doc.medication.administration.v2` | #40 / 0.168198 | #19 / 4.304176 | — | — | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | — | #18 / 4.659154 | — | — | fail | no | none |
| `955ca35a-ad9d-57fb-8c12-e79c9190c2cd`<br>`955ca35a-ad9d-57fb-8c12-e79c9190c2cd` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #22 / 4.249773 | — | — | fail | no | none |
| `fc1749ce-678f-5b79-9a27-41ca33d2043c`<br>`fc1749ce-678f-5b79-9a27-41ca33d2043c` | `family.medication.prn`<br>`doc.medication.prn.v1` | — | #23 / 4.026332 | — | — | fail | no | none |
| `d24f4e43-6251-56d5-b470-c23242fe6873`<br>`d24f4e43-6251-56d5-b470-c23242fe6873` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #24 / 4.008316 | — | — | fail | no | none |
| `14ab94b0-4ade-5c5c-b5bd-77eae8daf94d`<br>`14ab94b0-4ade-5c5c-b5bd-77eae8daf94d` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #25 / 3.833858 | — | — | fail | no | none |
| `3ffac08e-eebd-5bf7-963c-116ad06e0312`<br>`3ffac08e-eebd-5bf7-963c-116ad06e0312` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | — | #27 / 3.573221 | — | — | fail | no | none |
| `10c0d44a-0caf-50df-a02a-2ff58404be9d`<br>`10c0d44a-0caf-50df-a02a-2ff58404be9d` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #29 / 3.421498 | — | — | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | — | #34 / 2.996473 | — | — | fail | no | none |
| `92b627e2-da75-52c3-88b6-cdc01aa3b9ef`<br>`92b627e2-da75-52c3-88b6-cdc01aa3b9ef` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | — | #35 / 2.994608 | — | — | fail | no | none |
| `47a813db-42a0-5b2b-9631-4c30ef6d0306`<br>`47a813db-42a0-5b2b-9631-4c30ef6d0306` | `family.medication.storage`<br>`doc.medication.storage.v1` | — | #36 / 2.831393 | — | — | fail | no | none |

#### COMPARISON

Candidate funnel: Dense=13 → Sparse=13 → Unique after RRF=13 → Reranker=13 → Threshold=2 → Final evidence=2

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `13c0e838-be23-5fac-a03d-3c9478b3f41f`<br>`13c0e838-be23-5fac-a03d-3c9478b3f41f` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #1 / 0.397675 | #1 / 15.479404 | #1 / 0.032787 | #1 / 0.660156 | pass | yes | safeguarding.allegations.v1-hr |
| `817f4ea7-115c-58d5-9a46-dbaef434a1f2`<br>`817f4ea7-115c-58d5-9a46-dbaef434a1f2` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #2 / 0.279985 | #4 / 5.468790 | #2 / 0.031754 | #2 / 0.480469 | pass | yes | none |
| `14b1c8c3-190a-531d-b13e-5666a56b9ac7`<br>`14b1c8c3-190a-531d-b13e-5666a56b9ac7` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #3 / 0.267500 | #7 / 4.388462 | #6 / 0.030798 | #3 / 0.330078 | fail | no | none |
| `07ab0a1c-21e8-5a07-b4ed-3110898b35ca`<br>`07ab0a1c-21e8-5a07-b4ed-3110898b35ca` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #4 / 0.246123 | #5 / 5.159652 | #5 / 0.031010 | #4 / 0.320312 | fail | no | none |
| `2d65a97b-9023-5d91-8a35-5d78b3934084`<br>`2d65a97b-9023-5d91-8a35-5d78b3934084` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #5 / 0.216442 | #2 / 6.394357 | #3 / 0.031514 | #5 / 0.263672 | fail | no | none |
| `72a23d19-05d6-5fe0-8918-f0442b392f2d`<br>`72a23d19-05d6-5fe0-8918-f0442b392f2d` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v1` | #6 / 0.193039 | #3 / 5.851049 | #4 / 0.031025 | #6 / 0.255859 | fail | no | none |
| `254c3933-94f2-510b-aa2d-9ab1942de8a7`<br>`254c3933-94f2-510b-aa2d-9ab1942de8a7` | `family.medication.administration`<br>`doc.medication.administration.v1` | #10 / 0.158095 | #9 / 4.108486 | #9 / 0.028778 | #7 / 0.229492 | fail | no | none |
| `11a5a524-8a6e-5f08-9a8c-4c470aae9086`<br>`11a5a524-8a6e-5f08-9a8c-4c470aae9086` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #8 / 0.187980 | #8 / 4.169450 | #8 / 0.029412 | #8 / 0.218750 | fail | no | none |
| `3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f`<br>`3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f` | `family.fire.drills`<br>`doc.fire.drills.v1` | #9 / 0.187186 | #10 / 3.532906 | #10 / 0.028778 | #9 / 0.215820 | fail | no | none |
| `5b68e998-3a65-5808-bc5b-73e28613adc9`<br>`5b68e998-3a65-5808-bc5b-73e28613adc9` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #7 / 0.189719 | #6 / 4.530531 | #7 / 0.030077 | #10 / 0.206055 | fail | no | none |
| `369ceff0-142f-5215-817d-ddafe27e7ace`<br>`369ceff0-142f-5215-817d-ddafe27e7ace` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #11 / 0.157290 | #11 / 2.688302 | #11 / 0.028169 | #11 / 0.199219 | fail | no | none |
| `80ddc068-0955-5bb4-92c0-4b1586792c84`<br>`80ddc068-0955-5bb4-92c0-4b1586792c84` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #13 / 0.141792 | #13 / 0.737546 | #13 / 0.027397 | #12 / 0.198242 | fail | no | none |
| `3f7a6eba-f048-598f-8340-aed3172f8361`<br>`3f7a6eba-f048-598f-8340-aed3172f8361` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | #12 / 0.148840 | #12 / 1.154124 | #12 / 0.027778 | #13 / 0.195312 | fail | no | none |

### `safeguarding.allegations.compare-process` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How did the HR notification rule change between allegations procedures?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How did the HR notification rule change between allegations procedures?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How did the HR notification rule change between allegations procedures?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.allegations.v2-hr` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v2` | documents/safeguarding/allegations-staff-v2.md |
| COMPARISON | `safeguarding.allegations.v1-hr` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v1` | documents/safeguarding/allegations-staff-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.allegations.current-hr-timing` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How quickly do we tell HR when a staff safeguarding allegation comes in?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How quickly do we tell HR when a staff safeguarding allegation comes in?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How quickly do we tell HR when a staff safeguarding allegation comes in?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.allegations.hr-immediate` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v2` | documents/safeguarding/allegations-staff-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.allegations.current-hr-timing` / `contrast`

- Planning status: `FAILED`
- Planner failure: `invalid_typed_plan`
- Provider status: `200`
- Planner attempts: `1`
- Retrieval executed: `False`
- Contributes retrieval metrics: `False`
- Planner correct: `False`
- Eligibility correct: `None`
- Outcome correct: `None`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Can the manager wait one working day before telling HR?
- Covered EvidenceUnits: `none`
- Metrics: recall=n/a, precision=n/a, MRR=n/a, nDCG=n/a
- Hard failures: `planner_failure:invalid_typed_plan:safeguarding.allegations.current-hr-timing:contrast`

Planner contract comparison:

```json
{
  "actual_plan": null,
  "correct": false,
  "differences": [
    {
      "actual": "UNAVAILABLE",
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": "AVAILABLE",
      "field": "validated_plan"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Can the manager wait one working day before telling HR?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```


Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.allegations.hr-immediate` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v2` | documents/safeguarding/allegations-staff-v2.md |

#### PRIMARY

### `safeguarding.allegations.current-hr-timing` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When must HR be informed about an allegation against staff now?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "When must HR be informed about an allegation against staff now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "When must HR be informed about an allegation against staff now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.allegations.hr-immediate` | `family.safeguarding.allegations-staff` | `doc.safeguarding.allegations-staff.v2` | documents/safeguarding/allegations-staff-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.body-map.observable-facts` / `cause`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Should staff write what they think caused a bruise on the body map?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Should staff write what they think caused a bruise on the body map?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Should staff write what they think caused a bruise on the body map?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.body-map.facts-only` | `family.safeguarding.body-map` | `doc.safeguarding.body-map.v1` | documents/safeguarding/body-map-form.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.body-map.observable-facts` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `False`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do I guess how the mark happened or just describe what I can see?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, eligibility_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": "unclassifiable_temporal_intent",
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do I guess how the mark happened or just describe what I can see?"
    ],
    "temporal_mode": "CLARIFICATION_REQUIRED",
    "temporal_reference": null
  },
  "correct": false,
  "differences": [
    {
      "actual": "CLARIFICATION_REQUIRED",
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": "CURRENT",
      "field": "temporal_mode"
    },
    {
      "actual": "unclassifiable_temporal_intent",
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": null,
      "field": "clarification_reason"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Do I guess how the mark happened or just describe what I can see?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.body-map.facts-only` | `family.safeguarding.body-map` | `doc.safeguarding.body-map.v1` | documents/safeguarding/body-map-form.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.body-map.observable-facts` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What should be recorded on an injury body map?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What should be recorded on an injury body map?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What should be recorded on an injury body map?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.body-map.facts-only` | `family.safeguarding.body-map` | `doc.safeguarding.body-map.v1` | documents/safeguarding/body-map-form.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.capacity.unwise-decision` / `MCA`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Under the MCA, is capacity assessed once for every decision?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Under the MCA, is capacity assessed once for every decision?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Under the MCA, is capacity assessed once for every decision?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.capacity.decision-specific` | `family.safeguarding.mental-capacity` | `doc.safeguarding.mental-capacity.v1` | documents/safeguarding/mental-capacity-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.capacity.unwise-decision` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Can we say a resident has no capacity just because we disagree with their choice?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Can we say a resident has no capacity just because we disagree with their choice?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Can we say a resident has no capacity just because we disagree with their choice?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.capacity.decision-specific` | `family.safeguarding.mental-capacity` | `doc.safeguarding.mental-capacity.v1` | documents/safeguarding/mental-capacity-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.capacity.unwise-decision` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Does making an unwise decision mean someone lacks capacity?
- Covered EvidenceUnits: `safeguarding.capacity.decision-specific`
- Metrics: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Does making an unwise decision mean someone lacks capacity?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Does making an unwise decision mean someone lacks capacity?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `safeguarding.capacity.decision-specific` | `family.safeguarding.mental-capacity` | `doc.safeguarding.mental-capacity.v1` | documents/safeguarding/mental-capacity-procedure.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=4 → Final evidence=4

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `d24f4e43-6251-56d5-b470-c23242fe6873`<br>`d24f4e43-6251-56d5-b470-c23242fe6873` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | #1 / 0.446353 | #1 / 20.359106 | #1 / 0.032787 | #1 / 0.937500 | pass | yes | safeguarding.capacity.decision-specific |
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | #3 / 0.222680 | #2 / 10.593425 | #2 / 0.032002 | #2 / 0.472656 | pass | yes | none |
| `56745918-8c2b-5490-a300-4c18bf32a5c6`<br>`56745918-8c2b-5490-a300-4c18bf32a5c6` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #2 / 0.274303 | #3 / 7.870559 | #3 / 0.032002 | #3 / 0.462891 | pass | yes | none |
| `1839469e-5726-503f-a711-a010a97420fd`<br>`1839469e-5726-503f-a711-a010a97420fd` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #4 / 0.218887 | #26 / 0.780824 | #12 / 0.027253 | #4 / 0.357422 | pass | yes | none |
| `0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6`<br>`0b1fdb16-a5aa-5495-bb7a-3ad01e5912f6` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #5 / 0.204785 | #4 / 5.299381 | #4 / 0.031010 | #5 / 0.332031 | fail | no | none |
| `f85e71bc-4d62-57d9-b403-b13b1a9ff199`<br>`f85e71bc-4d62-57d9-b403-b13b1a9ff199` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #17 / 0.069623 | #5 / 4.785245 | #6 / 0.028372 | #6 / 0.306641 | fail | no | none |
| `799b04a0-74e1-5134-a911-0c2ccbda4c15`<br>`799b04a0-74e1-5134-a911-0c2ccbda4c15` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.124871 | #19 / 1.035726 | #9 / 0.027810 | #7 / 0.271484 | fail | no | none |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #28 / 0.046908 | #7 / 2.106721 | #13 / 0.026289 | #8 / 0.243164 | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #12 / 0.085126 | #22 / 0.959180 | #15 / 0.026084 | #9 / 0.236328 | fail | no | none |
| `55583402-4a65-5981-a851-30e8cd77775f`<br>`55583402-4a65-5981-a851-30e8cd77775f` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #16 / 0.071323 | #9 / 1.956574 | #10 / 0.027651 | #10 / 0.228516 | fail | no | none |
| `3e50e8ee-575c-52c9-a368-f1c6d1c814e1`<br>`3e50e8ee-575c-52c9-a368-f1c6d1c814e1` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #11 / 0.088426 | #12 / 1.614795 | #7 / 0.027973 | #11 / 0.222656 | fail | no | none |
| `fc1749ce-678f-5b79-9a27-41ca33d2043c`<br>`fc1749ce-678f-5b79-9a27-41ca33d2043c` | `family.medication.prn`<br>`doc.medication.prn.v1` | #9 / 0.091664 | #16 / 1.267065 | #11 / 0.027651 | #12 / 0.222656 | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #7 / 0.120622 | #17 / 1.177128 | #8 / 0.027912 | #13 / 0.218750 | fail | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #15 / 0.074785 | #6 / 2.708771 | #5 / 0.028485 | #14 / 0.202148 | fail | no | none |
| `b1b209d9-8945-557c-9456-0649dd6eb76a`<br>`b1b209d9-8945-557c-9456-0649dd6eb76a` | `family.fire.peep`<br>`doc.fire.peep.v1` | #19 / 0.063808 | #14 / 1.389048 | #14 / 0.026172 | #15 / 0.174805 | fail | no | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | #8 / 0.109781 | #32 / 0.509831 | — | — | fail | no | none |
| `40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc`<br>`40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | #10 / 0.088865 | — | — | — | fail | no | none |
| `b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef`<br>`b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef` | `family.medication.administration`<br>`doc.medication.administration.v2` | #13 / 0.077627 | #27 / 0.725322 | — | — | fail | no | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #14 / 0.077031 | #30 / 0.597572 | — | — | fail | no | none |
| `1a330d42-d249-5bf6-ba4b-066222bc5f5b`<br>`1a330d42-d249-5bf6-ba4b-066222bc5f5b` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #18 / 0.069245 | — | — | — | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #20 / 0.062438 | #21 / 0.966033 | — | — | fail | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #21 / 0.060670 | #35 / 0.246676 | — | — | fail | no | none |
| `5cf87b03-5514-55ae-9cac-0aa6b7c572d3`<br>`5cf87b03-5514-55ae-9cac-0aa6b7c572d3` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #22 / 0.059996 | — | — | — | fail | no | none |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #23 / 0.058024 | #13 / 1.397219 | — | — | fail | no | none |
| `f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e`<br>`f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #24 / 0.055683 | #20 / 1.014298 | — | — | fail | no | none |
| `65dda7f5-3688-515f-8d78-25e87c41a7e0`<br>`65dda7f5-3688-515f-8d78-25e87c41a7e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | #25 / 0.055504 | #29 / 0.663713 | — | — | fail | no | none |
| `6a0fb733-bff0-55d1-a5e7-d322ef9e53a9`<br>`6a0fb733-bff0-55d1-a5e7-d322ef9e53a9` | `family.training.matrix`<br>`doc.training.matrix.v1` | #26 / 0.048803 | — | — | — | fail | no | none |
| `b3036236-deaa-5719-ad41-3c5d87bbe7d8`<br>`b3036236-deaa-5719-ad41-3c5d87bbe7d8` | `family.training.fire`<br>`doc.training.fire.v1` | #27 / 0.047252 | — | — | — | fail | no | none |
| `14ab94b0-4ade-5c5c-b5bd-77eae8daf94d`<br>`14ab94b0-4ade-5c5c-b5bd-77eae8daf94d` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #29 / 0.044041 | #40 / 0.193565 | — | — | fail | no | none |
| `256e756b-7110-5070-9432-97bb1923a202`<br>`256e756b-7110-5070-9432-97bb1923a202` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #30 / 0.043338 | — | — | — | fail | no | none |
| `40b84d12-bb43-5dc3-a182-d80b51693330`<br>`40b84d12-bb43-5dc3-a182-d80b51693330` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #31 / 0.042682 | #37 / 0.206069 | — | — | fail | no | none |
| `3ffac08e-eebd-5bf7-963c-116ad06e0312`<br>`3ffac08e-eebd-5bf7-963c-116ad06e0312` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #32 / 0.041241 | #34 / 0.268461 | — | — | fail | no | none |
| `2dc51247-e552-5a57-91c3-9408e34f5d94`<br>`2dc51247-e552-5a57-91c3-9408e34f5d94` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #33 / 0.040741 | — | — | — | fail | no | none |
| `0318f8f9-9107-50ab-9afd-a65ee1687c77`<br>`0318f8f9-9107-50ab-9afd-a65ee1687c77` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #34 / 0.040390 | — | — | — | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #35 / 0.036466 | #23 / 0.873902 | — | — | fail | no | none |
| `8d8de832-6d4c-5368-b209-2ece5159b021`<br>`8d8de832-6d4c-5368-b209-2ece5159b021` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #36 / 0.034533 | #18 / 1.132993 | — | — | fail | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #37 / 0.031436 | — | — | — | fail | no | none |
| `10c0d44a-0caf-50df-a02a-2ff58404be9d`<br>`10c0d44a-0caf-50df-a02a-2ff58404be9d` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | #38 / 0.031226 | — | — | — | fail | no | none |
| `ebda80a6-77c7-557b-9450-fbddfdb16e02`<br>`ebda80a6-77c7-557b-9450-fbddfdb16e02` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #39 / 0.030687 | #8 / 2.044070 | — | — | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #40 / 0.029453 | — | — | — | fail | no | none |
| `85950010-d571-5bd3-9c8e-78b2687219d7`<br>`85950010-d571-5bd3-9c8e-78b2687219d7` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | — | #10 / 1.705390 | — | — | fail | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | — | #11 / 1.621712 | — | — | fail | no | none |
| `6ba08511-5e10-530d-9a62-17ffed9e9bc4`<br>`6ba08511-5e10-530d-9a62-17ffed9e9bc4` | `family.training.induction`<br>`doc.training.induction.v1` | — | #15 / 1.350400 | — | — | fail | no | none |
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | — | #24 / 0.857534 | — | — | fail | no | none |
| `249cc883-6c9a-5099-bdbb-974f04227e23`<br>`249cc883-6c9a-5099-bdbb-974f04227e23` | `family.complaints.form`<br>`doc.complaints.form.v1` | — | #25 / 0.800984 | — | — | fail | no | none |
| `82da54df-1b15-546d-81c8-b9cdb538cac5`<br>`82da54df-1b15-546d-81c8-b9cdb538cac5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #28 / 0.712402 | — | — | fail | no | none |
| `92b627e2-da75-52c3-88b6-cdc01aa3b9ef`<br>`92b627e2-da75-52c3-88b6-cdc01aa3b9ef` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | — | #31 / 0.580304 | — | — | fail | no | none |
| `be5c3624-95a2-5d5d-9f05-a9fb635d68a6`<br>`be5c3624-95a2-5d5d-9f05-a9fb635d68a6` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | — | #33 / 0.363961 | — | — | fail | no | none |
| `5a87d328-f076-5953-aa2e-8d7963341f74`<br>`5a87d328-f076-5953-aa2e-8d7963341f74` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #36 / 0.241510 | — | — | fail | no | none |
| `801b4c5b-787b-5e04-99ca-83dd8844448d`<br>`801b4c5b-787b-5e04-99ca-83dd8844448d` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | — | #38 / 0.205173 | — | — | fail | no | none |
| `4d1f0d61-d751-52f0-87dd-0327ea89db4e`<br>`4d1f0d61-d751-52f0-87dd-0327ea89db4e` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #39 / 0.199200 | — | — | fail | no | none |

### `safeguarding.covert-medication.multi-document` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: If someone cannot decide about tablets, what do both the MCA and meds rules require?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "If someone cannot decide about tablets, what do both the MCA and meds rules require?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "If someone cannot decide about tablets, what do both the MCA and meds rules require?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `covert-medication.medicines-controls` | `family.medication.covert` | `doc.medication.covert.v1` | documents/medication/covert-administration-policy.md |
| PRIMARY | `covert-medication.capacity-controls` | `family.safeguarding.mental-capacity` | `doc.safeguarding.mental-capacity.v1` | documents/safeguarding/mental-capacity-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.covert-medication.multi-document` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What capacity and medicines evidence is needed for covert administration?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What capacity and medicines evidence is needed for covert administration?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What capacity and medicines evidence is needed for covert administration?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `covert-medication.medicines-controls` | `family.medication.covert` | `doc.medication.covert.v1` | documents/medication/covert-administration-policy.md |
| PRIMARY | `covert-medication.capacity-controls` | `family.safeguarding.mental-capacity` | `doc.safeguarding.mental-capacity.v1` | documents/safeguarding/mental-capacity-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `safeguarding.covert-medication.multi-document` / `multi`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Which policies together govern hiding medicine in food?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Which policies together govern hiding medicine in food?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Which policies together govern hiding medicine in food?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `covert-medication.medicines-controls` | `family.medication.covert` | `doc.medication.covert.v1` | documents/medication/covert-administration-policy.md |
| PRIMARY | `covert-medication.capacity-controls` | `family.safeguarding.mental-capacity` | `doc.safeguarding.mental-capacity.v1` | documents/safeguarding/mental-capacity-procedure.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `training.medication.compare` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did med sign-off change from three rounds to four?
- Covered EvidenceUnits: `training.medication.compare-old, training.medication.compare-current`
- Metrics: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did med sign-off change from three rounds to four?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Did med sign-off change from three rounds to four?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.medication.compare-current` | `family.training.medication-competency` | `doc.training.medication-competency.v2` | documents/training/medication-competency-v2.md |
| COMPARISON | `training.medication.compare-old` | `family.training.medication-competency` | `doc.training.medication-competency.v1` | documents/training/medication-competency-v1.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=3 → Final evidence=3

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `1a330d42-d249-5bf6-ba4b-066222bc5f5b`<br>`1a330d42-d249-5bf6-ba4b-066222bc5f5b` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #1 / 0.306194 | #2 / 13.311461 | #1 / 0.032522 | #1 / 0.625000 | pass | yes | training.medication.compare-current |
| `ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01`<br>`ba609fb5-4a26-55ca-9bdc-c1d6a7e87d01` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.290744 | #1 / 15.311777 | #2 / 0.032522 | #2 / 0.359375 | pass | yes | none |
| `4f41fcb6-f79c-5930-8671-7bd4a1a3d992`<br>`4f41fcb6-f79c-5930-8671-7bd4a1a3d992` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.239029 | #7 / 5.242905 | #5 / 0.030798 | #3 / 0.357422 | pass | yes | none |
| `799b04a0-74e1-5134-a911-0c2ccbda4c15`<br>`799b04a0-74e1-5134-a911-0c2ccbda4c15` | `family.medication.administration`<br>`doc.medication.administration.v2` | #5 / 0.211347 | #3 / 9.726298 | #3 / 0.031258 | #4 / 0.320312 | fail | no | none |
| `4ebf09ad-9335-5e6b-858f-1d79ad72d59a`<br>`4ebf09ad-9335-5e6b-858f-1d79ad72d59a` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #4 / 0.225305 | #5 / 6.897190 | #4 / 0.031010 | #5 / 0.316406 | fail | no | none |
| `b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef`<br>`b2a4f30f-b0ad-596c-9dc3-8ef11aae81ef` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.207198 | #4 / 7.702914 | #6 / 0.030777 | #6 / 0.312500 | fail | no | none |
| `fc1749ce-678f-5b79-9a27-41ca33d2043c`<br>`fc1749ce-678f-5b79-9a27-41ca33d2043c` | `family.medication.prn`<br>`doc.medication.prn.v1` | #7 / 0.184908 | #11 / 4.501598 | #8 / 0.029010 | #7 / 0.257812 | fail | no | none |
| `da5d308b-8313-5322-9b2f-8b06390f3b63`<br>`da5d308b-8313-5322-9b2f-8b06390f3b63` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #16 / 0.144403 | #20 / 2.383632 | #14 / 0.025658 | #8 / 0.255859 | fail | no | none |
| `56745918-8c2b-5490-a300-4c18bf32a5c6`<br>`56745918-8c2b-5490-a300-4c18bf32a5c6` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #13 / 0.155310 | #14 / 3.445128 | #10 / 0.027212 | #9 / 0.248047 | fail | no | none |
| `08447fe4-42e8-50a1-9357-66e117e25340`<br>`08447fe4-42e8-50a1-9357-66e117e25340` | `family.medication.errors`<br>`doc.medication.errors.v1` | #10 / 0.162624 | #10 / 4.751249 | #9 / 0.028571 | #10 / 0.242188 | fail | no | none |
| `3e50e8ee-575c-52c9-a368-f1c6d1c814e1`<br>`3e50e8ee-575c-52c9-a368-f1c6d1c814e1` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #21 / 0.132813 | #21 / 2.250439 | #15 / 0.024691 | #11 / 0.222656 | fail | no | none |
| `5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e`<br>`5a5280b4-d0bd-5ccb-88f2-c4bc0ab46a7e` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #8 / 0.176027 | #24 / 1.873543 | #11 / 0.026611 | #12 / 0.218750 | fail | no | none |
| `3dc99e86-2393-5151-a204-84a019c4478d`<br>`3dc99e86-2393-5151-a204-84a019c4478d` | `family.medication.covert`<br>`doc.medication.covert.v1` | #11 / 0.161791 | #6 / 6.077698 | #7 / 0.029236 | #13 / 0.218750 | fail | no | none |
| `f1b2325d-4bb3-581b-8d14-7b8cdd43f216`<br>`f1b2325d-4bb3-581b-8d14-7b8cdd43f216` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #23 / 0.123486 | #13 / 3.705707 | #13 / 0.025747 | #14 / 0.217773 | fail | no | none |
| `f85e71bc-4d62-57d9-b403-b13b1a9ff199`<br>`f85e71bc-4d62-57d9-b403-b13b1a9ff199` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #20 / 0.138029 | #15 / 3.333537 | #12 / 0.025833 | #15 / 0.208984 | fail | no | none |
| `f193cb26-bd92-5fb8-a0b1-ba2c829f658b`<br>`f193cb26-bd92-5fb8-a0b1-ba2c829f658b` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #9 / 0.168276 | — | — | — | fail | no | none |
| `419352e8-908f-58e0-96bb-bf195915b010`<br>`419352e8-908f-58e0-96bb-bf195915b010` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #12 / 0.158069 | — | — | — | fail | no | none |
| `1f7baac6-5792-5b2a-9399-26ad4c21d6e4`<br>`1f7baac6-5792-5b2a-9399-26ad4c21d6e4` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #14 / 0.153544 | — | — | — | fail | no | none |
| `635ff5e9-ecb1-559b-8683-4b7a96ea7bd9`<br>`635ff5e9-ecb1-559b-8683-4b7a96ea7bd9` | `family.fire.drills`<br>`doc.fire.drills.v2` | #15 / 0.147918 | #33 / 0.503207 | — | — | fail | no | none |
| `aeb0ea01-92b2-5418-ad27-c95cacb3b030`<br>`aeb0ea01-92b2-5418-ad27-c95cacb3b030` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #17 / 0.143334 | — | — | — | fail | no | none |
| `6a0fb733-bff0-55d1-a5e7-d322ef9e53a9`<br>`6a0fb733-bff0-55d1-a5e7-d322ef9e53a9` | `family.training.matrix`<br>`doc.training.matrix.v1` | #18 / 0.142071 | #30 / 0.888740 | — | — | fail | no | none |
| `6ba08511-5e10-530d-9a62-17ffed9e9bc4`<br>`6ba08511-5e10-530d-9a62-17ffed9e9bc4` | `family.training.induction`<br>`doc.training.induction.v1` | #19 / 0.139429 | — | — | — | fail | no | none |
| `ff66a4d2-2f74-5eb9-a45d-32c39e102800`<br>`ff66a4d2-2f74-5eb9-a45d-32c39e102800` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #22 / 0.131429 | — | — | — | fail | no | none |
| `19af6371-d756-5e1a-bf22-8f54335a4a58`<br>`19af6371-d756-5e1a-bf22-8f54335a4a58` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #24 / 0.122332 | #32 / 0.519486 | — | — | fail | no | none |
| `85950010-d571-5bd3-9c8e-78b2687219d7`<br>`85950010-d571-5bd3-9c8e-78b2687219d7` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | #25 / 0.118765 | #26 / 1.500815 | — | — | fail | no | none |
| `65dda7f5-3688-515f-8d78-25e87c41a7e0`<br>`65dda7f5-3688-515f-8d78-25e87c41a7e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | #26 / 0.117677 | #19 / 2.646712 | — | — | fail | no | none |
| `955ca35a-ad9d-57fb-8c12-e79c9190c2cd`<br>`955ca35a-ad9d-57fb-8c12-e79c9190c2cd` | `family.visitors.general`<br>`doc.visitors.general.v1` | #27 / 0.111757 | #16 / 3.004690 | — | — | fail | no | none |
| `f4b9f291-51c7-5e35-9335-b7e3dd2b37ef`<br>`f4b9f291-51c7-5e35-9335-b7e3dd2b37ef` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #28 / 0.111737 | — | — | — | fail | no | none |
| `92b627e2-da75-52c3-88b6-cdc01aa3b9ef`<br>`92b627e2-da75-52c3-88b6-cdc01aa3b9ef` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #29 / 0.110098 | #37 / 0.296089 | — | — | fail | no | none |
| `1839469e-5726-503f-a711-a010a97420fd`<br>`1839469e-5726-503f-a711-a010a97420fd` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #30 / 0.109327 | — | — | — | fail | no | none |
| `3175f7bd-0838-5056-a1da-341d951720ed`<br>`3175f7bd-0838-5056-a1da-341d951720ed` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #31 / 0.106032 | — | — | — | fail | no | none |
| `b1b209d9-8945-557c-9456-0649dd6eb76a`<br>`b1b209d9-8945-557c-9456-0649dd6eb76a` | `family.fire.peep`<br>`doc.fire.peep.v1` | #32 / 0.106005 | — | — | — | fail | no | none |
| `5cf87b03-5514-55ae-9cac-0aa6b7c572d3`<br>`5cf87b03-5514-55ae-9cac-0aa6b7c572d3` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #33 / 0.105742 | — | — | — | fail | no | none |
| `b3036236-deaa-5719-ad41-3c5d87bbe7d8`<br>`b3036236-deaa-5719-ad41-3c5d87bbe7d8` | `family.training.fire`<br>`doc.training.fire.v1` | #34 / 0.099437 | — | — | — | fail | no | none |
| `ad9c7253-2c23-5a18-bb60-bcfc0859e149`<br>`ad9c7253-2c23-5a18-bb60-bcfc0859e149` | `family.payroll.mileage`<br>`doc.payroll.mileage.v1` | #35 / 0.099354 | — | — | — | fail | no | none |
| `893f68e3-e8d2-5acd-9a73-8f30912e2431`<br>`893f68e3-e8d2-5acd-9a73-8f30912e2431` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | #36 / 0.099151 | — | — | — | fail | no | none |
| `e396df5b-f0b7-5731-9ead-d56f0449b653`<br>`e396df5b-f0b7-5731-9ead-d56f0449b653` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #37 / 0.098270 | #17 / 2.753409 | — | — | fail | no | none |
| `f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e`<br>`f8f4fce4-f47d-59c5-9bdb-1f06ff217a1e` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #38 / 0.095026 | — | — | — | fail | no | none |
| `be5c3624-95a2-5d5d-9f05-a9fb635d68a6`<br>`be5c3624-95a2-5d5d-9f05-a9fb635d68a6` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | #39 / 0.093965 | #18 / 2.741809 | — | — | fail | no | none |
| `547688c1-a1d4-5686-af1f-ae2830f97852`<br>`547688c1-a1d4-5686-af1f-ae2830f97852` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #40 / 0.092897 | #27 / 1.014388 | — | — | fail | no | none |
| `919b1651-7a62-5792-b47f-6ac4fc784017`<br>`919b1651-7a62-5792-b47f-6ac4fc784017` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | — | #8 / 4.893642 | — | — | fail | no | none |
| `47a813db-42a0-5b2b-9631-4c30ef6d0306`<br>`47a813db-42a0-5b2b-9631-4c30ef6d0306` | `family.medication.storage`<br>`doc.medication.storage.v1` | — | #9 / 4.823264 | — | — | fail | no | none |
| `801b4c5b-787b-5e04-99ca-83dd8844448d`<br>`801b4c5b-787b-5e04-99ca-83dd8844448d` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | — | #12 / 3.988550 | — | — | fail | no | none |
| `40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc`<br>`40b1f7a9-ed83-5ea7-8848-8cffdbe7b8bc` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | — | #22 / 2.107858 | — | — | fail | no | none |
| `14ab94b0-4ade-5c5c-b5bd-77eae8daf94d`<br>`14ab94b0-4ade-5c5c-b5bd-77eae8daf94d` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #23 / 1.883573 | — | — | fail | no | none |
| `ee3b92cf-7201-50f5-9315-841d5bceb277`<br>`ee3b92cf-7201-50f5-9315-841d5bceb277` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | — | #25 / 1.856268 | — | — | fail | no | none |
| `ac335280-6bca-5150-bd9b-db2d198ca588`<br>`ac335280-6bca-5150-bd9b-db2d198ca588` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | — | #28 / 0.992962 | — | — | fail | no | none |
| `3533a299-e35b-5981-8622-453d11ee03d7`<br>`3533a299-e35b-5981-8622-453d11ee03d7` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | — | #29 / 0.891675 | — | — | fail | no | none |
| `249cc883-6c9a-5099-bdbb-974f04227e23`<br>`249cc883-6c9a-5099-bdbb-974f04227e23` | `family.complaints.form`<br>`doc.complaints.form.v1` | — | #31 / 0.665636 | — | — | fail | no | none |
| `6b466675-819e-5e52-b9ee-aab5cd63fab2`<br>`6b466675-819e-5e52-b9ee-aab5cd63fab2` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | — | #34 / 0.407030 | — | — | fail | no | none |
| `46aef083-cd2b-5c1f-8608-2fe802b98c6d`<br>`46aef083-cd2b-5c1f-8608-2fe802b98c6d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | — | #35 / 0.386651 | — | — | fail | no | none |
| `8d8de832-6d4c-5368-b209-2ece5159b021`<br>`8d8de832-6d4c-5368-b209-2ece5159b021` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | — | #36 / 0.347846 | — | — | fail | no | none |
| `10c0d44a-0caf-50df-a02a-2ff58404be9d`<br>`10c0d44a-0caf-50df-a02a-2ff58404be9d` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #38 / 0.293278 | — | — | fail | no | none |
| `5a87d328-f076-5953-aa2e-8d7963341f74`<br>`5a87d328-f076-5953-aa2e-8d7963341f74` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #39 / 0.282336 | — | — | fail | no | none |
| `1c5f4c28-3884-518a-9a36-f103e328ba79`<br>`1c5f4c28-3884-518a-9a36-f103e328ba79` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | — | #40 / 0.272774 | — | — | fail | no | none |

#### COMPARISON

Candidate funnel: Dense=13 → Sparse=13 → Unique after RRF=13 → Reranker=13 → Threshold=1 → Final evidence=1

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `80ddc068-0955-5bb4-92c0-4b1586792c84`<br>`80ddc068-0955-5bb4-92c0-4b1586792c84` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #1 / 0.300053 | #1 / 12.603944 | #1 / 0.032787 | #1 / 0.457031 | pass | yes | training.medication.compare-old |
| `254c3933-94f2-510b-aa2d-9ab1942de8a7`<br>`254c3933-94f2-510b-aa2d-9ab1942de8a7` | `family.medication.administration`<br>`doc.medication.administration.v1` | #2 / 0.242911 | #2 / 7.763030 | #2 / 0.032258 | #2 / 0.328125 | fail | no | none |
| `11a5a524-8a6e-5f08-9a8c-4c470aae9086`<br>`11a5a524-8a6e-5f08-9a8c-4c470aae9086` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #3 / 0.230472 | #3 / 7.361108 | #3 / 0.031746 | #3 / 0.306641 | fail | no | none |
| `2d65a97b-9023-5d91-8a35-5d78b3934084`<br>`2d65a97b-9023-5d91-8a35-5d78b3934084` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #5 / 0.145379 | #11 / 0.200179 | #6 / 0.029469 | #4 / 0.253906 | fail | no | none |
| `817f4ea7-115c-58d5-9a46-dbaef434a1f2`<br>`817f4ea7-115c-58d5-9a46-dbaef434a1f2` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #11 / 0.086386 | #8 / 0.281600 | #11 / 0.028790 | #5 / 0.222656 | fail | no | none |
| `5b68e998-3a65-5808-bc5b-73e28613adc9`<br>`5b68e998-3a65-5808-bc5b-73e28613adc9` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #4 / 0.176059 | #4 / 1.469863 | #4 / 0.031250 | #6 / 0.218750 | fail | no | none |
| `07ab0a1c-21e8-5a07-b4ed-3110898b35ca`<br>`07ab0a1c-21e8-5a07-b4ed-3110898b35ca` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #12 / 0.082220 | #5 / 0.852492 | #8 / 0.029274 | #7 / 0.218750 | fail | no | none |
| `72a23d19-05d6-5fe0-8918-f0442b392f2d`<br>`72a23d19-05d6-5fe0-8918-f0442b392f2d` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v1` | #8 / 0.095672 | #10 / 0.253225 | #9 / 0.028992 | #8 / 0.211914 | fail | no | none |
| `3f7a6eba-f048-598f-8340-aed3172f8361`<br>`3f7a6eba-f048-598f-8340-aed3172f8361` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | #9 / 0.091184 | #12 / 0.186619 | #12 / 0.028382 | #9 / 0.206055 | fail | no | none |
| `3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f`<br>`3d45adf7-2e3b-52fd-b4e4-d3bab5b7d64f` | `family.fire.drills`<br>`doc.fire.drills.v1` | #6 / 0.116196 | #7 / 0.423100 | #5 / 0.030077 | #10 / 0.204102 | fail | no | none |
| `369ceff0-142f-5215-817d-ddafe27e7ace`<br>`369ceff0-142f-5215-817d-ddafe27e7ace` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #7 / 0.100502 | #9 / 0.257845 | #7 / 0.029418 | #11 / 0.199219 | fail | no | none |
| `13c0e838-be23-5fac-a03d-3c9478b3f41f`<br>`13c0e838-be23-5fac-a03d-3c9478b3f41f` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #13 / 0.059355 | #6 / 0.483238 | #10 / 0.028850 | #12 / 0.199219 | fail | no | none |
| `14b1c8c3-190a-531d-b13e-5666a56b9ac7`<br>`14b1c8c3-190a-531d-b13e-5666a56b9ac7` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #10 / 0.086664 | #13 / 0.153396 | #13 / 0.027984 | #13 / 0.189453 | fail | no | none |

### `training.medication.compare` / `compare`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare old and current observed-round requirements.
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `planner_mismatch, outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare old and current observed-round requirements."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "old"
    }
  },
  "correct": false,
  "differences": [
    {
      "actual": {
        "kind": "HISTORICAL_REFERENCE",
        "value": "old"
      },
      "classification": "SEMANTIC_AFTER_NORMALISATION",
      "expected": null,
      "field": "temporal_reference"
    }
  ],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Compare old and current observed-round requirements."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.medication.compare-current` | `family.training.medication-competency` | `doc.training.medication-competency.v2` | documents/training/medication-competency-v2.md |
| COMPARISON | `training.medication.compare-old` | `family.training.medication-competency` | `doc.training.medication-competency.v1` | documents/training/medication-competency-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `training.medication.compare` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How did the medication competency assessment change?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How did the medication competency assessment change?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How did the medication competency assessment change?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.medication.compare-current` | `family.training.medication-competency` | `doc.training.medication-competency.v2` | documents/training/medication-competency-v2.md |
| COMPARISON | `training.medication.compare-old` | `family.training.medication-competency` | `doc.training.medication-competency.v1` | documents/training/medication-competency-v1.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

#### COMPARISON

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `training.medication.current-rounds` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How many med rounds do I need signed off?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How many med rounds do I need signed off?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How many med rounds do I need signed off?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.medication.v2-rounds` | `family.training.medication-competency` | `doc.training.medication-competency.v2` | documents/training/medication-competency-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `training.medication.current-rounds` / `controlled`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Must the medication competency include a controlled-drug round?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Must the medication competency include a controlled-drug round?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "Must the medication competency include a controlled-drug round?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.medication.v2-rounds` | `family.training.medication-competency` | `doc.training.medication-competency.v2` | documents/training/medication-competency-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0

### `training.medication.current-rounds` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `False`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How many observed medication rounds are required now?
- Covered EvidenceUnits: `none`
- Metrics: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000
- Hard failures: `outcome_mismatch`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How many observed medication rounds are required now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "How many observed medication rounds are required now?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=0.0000, precision=0.0000, MRR=0.0000, nDCG=0.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `training.medication.v2-rounds` | `family.training.medication-competency` | `doc.training.medication-competency.v2` | documents/training/medication-competency-v2.md |

#### PRIMARY

Candidate funnel: Dense=0 → Sparse=0 → Unique after RRF=0 → Reranker=0 → Threshold=0 → Final evidence=0


## Available and missing stage lineage

Available: case_id, variant_id, correctness flags, final per-case metrics, side metrics, covered EvidenceUnit IDs and final operational observations.
Available: question/expectation context, exact candidate-stage lineage and per-side candidate funnels from result.json.

## Decision

Status: **EXPERIMENTAL**

Decision: No human decision recorded.

Generated from `result.json`, `config.json` and optional `comparison.json`; raw JSON is authoritative.
