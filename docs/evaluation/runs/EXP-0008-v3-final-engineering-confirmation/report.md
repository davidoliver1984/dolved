# Evaluation run: EXP-0008-v3-final-engineering-confirmation

**Status:** EXPERIMENTAL

## Run summary

| Field | Value |
|---|---|
| Description | Exact-commit ADR-0022 full-pipeline engineering experiment |
| Executed at | `2026-08-16T10:43:56Z` |
| Repository commit | `a21431bc0f9137978f3c4d082619954f8814bd9d` |
| Working tree | `clean` |
| Benchmark | `dolved-care-engineering` / `3` |
| Benchmark digest | `d24d61a9aef55c8d3ca8d6609fbb44683665acc22e8d4f9652f00cb4d575d4c3` |
| Corpus | `3` / `d24d61a9aef55c8d3ca8d6609fbb44683665acc22e8d4f9652f00cb4d575d4c3` |
| Split | `v1` / `5be81b3238889f6b68af049e37a28f8de1cf00b4ab7e4883b2e59d630e9dcfbf` |
| Harness | `retrieval-evaluation-v1` |
| Threshold policy | `6626d78bd9445c70fd946a64b0a817b4e77b264a14d945d483ba497f9e681364` |

## Exact tested configuration

### Provider/model lineage

| Component | Configuration |
|---|---|
| dense | `{"adapter_version":"1","dimensions":1024,"embedding_profile_fingerprint":"ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c","model":"voyage-4-large","provider":"voyage"}` |
| fusion | `{"rrf_k":5,"strategy":"rrf","version":"1"}` |
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
| rrf_k | `5` |
| sparse_candidate_k | `40` |

## Classifier and Laravel resolution

```json
{
  "classifier": {
    "confusion_matrix": {
      "COMPARE": {
        "COMPARE": 3
      },
      "CURRENT": {
        "CLARIFICATION_REQUIRED": 1,
        "COMPARE": 1,
        "CURRENT": 22
      },
      "HISTORICAL_REFERENCE": {
        "HISTORICAL_REFERENCE": 3
      },
      "VALID_AT_DATE": {
        "VALID_AT_DATE": 1
      }
    },
    "date_hallucinations": 0,
    "false_compare": 1,
    "false_historical_reference": 0,
    "location_extraction": {
      "expected": 5,
      "matched": 5,
      "precision": 0.8333333333333334,
      "predicted": 6,
      "recall": 1.0
    },
    "mode_accuracy": {
      "COMPARE": {
        "accuracy": 1.0,
        "correct": 3,
        "total": 3
      },
      "CURRENT": {
        "accuracy": 0.9166666666666666,
        "correct": 22,
        "total": 24
      },
      "HISTORICAL_REFERENCE": {
        "accuracy": 1.0,
        "correct": 3,
        "total": 3
      },
      "VALID_AT_DATE": {
        "accuracy": 1.0,
        "correct": 1,
        "total": 1
      }
    },
    "planner_contract_accuracy": 0.9354838709677419,
    "structured_response_reliability": 1.0,
    "temporal_accuracy": 0.9354838709677419
  },
  "laravel_resolution": {
    "clarification_reasons": {
      "unclassifiable_temporal_intent": 1
    },
    "eligibility_correctness": 0.967741935483871,
    "historical_reference_resolution_correctness": 1.0,
    "location_resolution_correctness": 1.0,
    "outcome_correctness": 0.967741935483871,
    "planner_correctness": 0.9354838709677419,
    "temporal_resolution_correctness": 0.967741935483871
  },
  "population": 31
}
```

## Headline metrics

| Metric | Value |
|---|---:|
| Recall@K | 0.9667 |
| Precision@K | 0.2100 |
| MRR | 0.9333 |
| nDCG@K | 0.9157 |
| Planner accuracy | 0.9333 |
| Eligibility accuracy | 0.9667 |
| Outcome accuracy | 0.9667 |

## Planner reliability

| Measure | Value |
|---|---:|
| Total variants | 31 |
| Successful planner variants | 31 |
| Planner failures | 0 |
| Planner reliability | 1.0000 |
| Retrieval metric population | 31 |

Failure categories: None.

Retrieval metrics are computed only over variants where planning succeeded and retrieval ran. Planner hard failures remain separate and cannot be offset by retrieval averages.

## Baseline comparison

Baseline: `EXP-0008-v3-final-engineering-confirmation-dense-control`

| Metric | Baseline | Candidate | Delta |
|---|---:|---:|---:|
| Recall@K | 0.9667 | 0.9667 | +0.0000 |
| Precision@K | 0.2100 | 0.2100 | +0.0000 |
| MRR | 0.9417 | 0.9333 | -0.0083 |
| nDCG@K | 0.9218 | 0.9157 | -0.0062 |

## Slice metrics

| Slice | Cases | Recall | Precision | MRR | nDCG | Planner | Eligibility | Outcome |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| COMPARE | 1 | 1.0000 | 0.2000 | 1.0000 | 1.0000 | 1.0000 | 1.0000 | 1.0000 |
| CURRENT | 8 | 0.9583 | 0.2125 | 0.9167 | 0.8946 | 0.9167 | 0.9583 | 0.9583 |
| VALID_AT_DATE | 1 | 1.0000 | 0.2000 | 1.0000 | 1.0000 | 1.0000 | 1.0000 | 1.0000 |
| applicability | 1 | 1.0000 | 0.4000 | 1.0000 | 0.6131 | 1.0000 | 1.0000 | 1.0000 |
| historical | 1 | 1.0000 | 0.2000 | 1.0000 | 1.0000 | 1.0000 | 1.0000 | 1.0000 |
| location-alias | 1 | 1.0000 | 0.4000 | 1.0000 | 0.6131 | 1.0000 | 1.0000 | 1.0000 |
| multi-evidence | 2 | 1.0000 | 0.3000 | 1.0000 | 0.8066 | 1.0000 | 1.0000 | 1.0000 |
| region-specific | 1 | 1.0000 | 0.4000 | 1.0000 | 0.6131 | 1.0000 | 1.0000 | 1.0000 |
| regional-applicability | 1 | 1.0000 | 0.4000 | 1.0000 | 0.6131 | 1.0000 | 1.0000 | 1.0000 |
| temporal-authority | 2 | 1.0000 | 0.2000 | 1.0000 | 1.0000 | 1.0000 | 1.0000 | 1.0000 |

## Hard failures

- `eligibility_mismatch`
- `outcome_mismatch`
- `planner_mismatch`

## Operational metrics

```json
{
  "dense": {
    "latency_ms": {
      "max": 16802.003341,
      "mean": 6894.35255416129,
      "median": 5901.39992,
      "min": 4591.149252,
      "p95": 12077.408818
    },
    "provider_cost": null,
    "request_count": 0,
    "token_usage": 0,
    "usage": {
      "attempted_variants": 31,
      "cost_complete": false,
      "evidence_producing_variants": 30,
      "generation": {
        "cost_basis": "UNAVAILABLE",
        "cost_usd": null,
        "execution": "NOT_EXECUTED"
      },
      "known_provider_api_cost_usd": 4.644e-05,
      "mean_api_cost_per_attempted_variant_usd": null,
      "mean_api_cost_per_evidence_producing_variant_usd": null,
      "mean_api_cost_per_successfully_planned_variant_usd": null,
      "providers": [
        {
          "cached_input_tokens": 1152,
          "cost_complete": false,
          "input_tokens": 41347,
          "known_cost_usd": 0,
          "latency_ms": {
            "max": 13569.010715000331,
            "mean": 4622.749644647082,
            "min": 2700.509834976401,
            "p50": 3825.7459190208465,
            "p95": 9343.902399996296
          },
          "model": "gpt-5-mini",
          "output_tokens": 14217,
          "pricing_snapshots": [],
          "provider": "openai",
          "request_count": 31,
          "retry_count": 0
        },
        {
          "cached_input_tokens": null,
          "cost_complete": true,
          "input_tokens": null,
          "known_cost_usd": 0,
          "latency_ms": {
            "max": 104.1998760192655,
            "mean": 23.26583616862384,
            "min": 5.57566701900214,
            "p50": 13.36702101980336,
            "p95": 82.14904154592647
          },
          "model": "rag-platform-vectors-v1",
          "output_tokens": null,
          "pricing_snapshots": [],
          "provider": "qdrant",
          "request_count": 34,
          "retry_count": 0
        },
        {
          "cached_input_tokens": null,
          "cost_complete": true,
          "input_tokens": 387,
          "known_cost_usd": 4.644e-05,
          "latency_ms": {
            "max": 321.8462919467129,
            "mean": 279.29343626795645,
            "min": 249.2606660234742,
            "p50": 282.47387550072744,
            "p95": 300.28280379774515
          },
          "model": "voyage-4-large",
          "output_tokens": null,
          "pricing_snapshots": [
            "voyage-pricing-2026-08-12"
          ],
          "provider": "voyage",
          "request_count": 30,
          "retry_count": 0
        }
      ],
      "stages": [
        {
          "execution_count": 30,
          "latency_ms": {
            "max": 321.8462919467129,
            "mean": 279.29343626795645,
            "min": 249.2606660234742,
            "p50": 282.47387550072744,
            "p95": 300.28280379774515
          },
          "request_count": 30,
          "retry_count": 0,
          "stage": "dense_embedding"
        },
        {
          "execution_count": 31,
          "latency_ms": {
            "max": 13569.010715000331,
            "mean": 4622.749644647082,
            "min": 2700.509834976401,
            "p50": 3825.7459190208465,
            "p95": 9343.902399996296
          },
          "request_count": 31,
          "retry_count": 0,
          "stage": "planner"
        },
        {
          "execution_count": 30,
          "latency_ms": {
            "max": 104.1998760192655,
            "mean": 23.26583616862384,
            "min": 5.57566701900214,
            "p50": 13.36702101980336,
            "p95": 82.14904154592647
          },
          "request_count": 34,
          "retry_count": 0,
          "stage": "qdrant_dense_search"
        }
      ],
      "successfully_planned_variants": 31,
      "total_provider_api_cost_usd": null,
      "unavailable_cost_lineage": [
        "openai/gpt-5-mini"
      ]
    }
  },
  "experiment": {
    "attempted_variants": 31,
    "cost_complete": false,
    "evidence_producing_variants": 30,
    "generation": {
      "cost_basis": "UNAVAILABLE",
      "cost_usd": null,
      "execution": "NOT_EXECUTED"
    },
    "known_provider_api_cost_usd": 9.288e-05,
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
          "max": 1334.6696259686723,
          "mean": 603.0389641644433,
          "min": 387.0526670361869,
          "p50": 559.6429375000298,
          "p95": 928.895035482128
        },
        "model": "prithivida/Splade_PP_en_v1",
        "output_tokens": null,
        "pricing_snapshots": [],
        "provider": "fastembed",
        "request_count": 30,
        "retry_count": 0
      },
      {
        "cached_input_tokens": 1152,
        "cost_complete": false,
        "input_tokens": 41347,
        "known_cost_usd": 0,
        "latency_ms": {
          "max": 13569.010715000331,
          "mean": 4622.749644647082,
          "min": 2700.509834976401,
          "p50": 3825.7459190208465,
          "p95": 9343.902399996296
        },
        "model": "gpt-5-mini",
        "output_tokens": 14217,
        "pricing_snapshots": [],
        "provider": "openai",
        "request_count": 31,
        "retry_count": 0
      },
      {
        "cached_input_tokens": null,
        "cost_complete": true,
        "input_tokens": null,
        "known_cost_usd": 0,
        "latency_ms": {
          "max": 130.81095804227516,
          "mean": 32.666860677353625,
          "min": 5.407749966252595,
          "p50": 18.58914553304203,
          "p95": 94.29383598326238
        },
        "model": "rag-platform-vectors-v1",
        "output_tokens": null,
        "pricing_snapshots": [],
        "provider": "qdrant",
        "request_count": 102,
        "retry_count": 0
      },
      {
        "cached_input_tokens": null,
        "cost_complete": false,
        "input_tokens": 99812,
        "known_cost_usd": 0,
        "latency_ms": {
          "max": 545.847792,
          "mean": 343.86878623333337,
          "min": 269.656875,
          "p50": 316.9565835,
          "p95": 543.8266954999999
        },
        "model": "rerank-2.5",
        "output_tokens": null,
        "pricing_snapshots": [],
        "provider": "voyage",
        "request_count": 34,
        "retry_count": 0
      },
      {
        "cached_input_tokens": null,
        "cost_complete": true,
        "input_tokens": 774,
        "known_cost_usd": 9.288e-05,
        "latency_ms": {
          "max": 881.0207089991309,
          "mean": 292.769472366975,
          "min": 249.2606660234742,
          "p50": 277.75656248559244,
          "p95": 322.71915444871405
        },
        "model": "voyage-4-large",
        "output_tokens": null,
        "pricing_snapshots": [
          "voyage-pricing-2026-08-12"
        ],
        "provider": "voyage",
        "request_count": 60,
        "retry_count": 0
      }
    ],
    "stages": [
      {
        "execution_count": 60,
        "latency_ms": {
          "max": 881.0207089991309,
          "mean": 292.769472366975,
          "min": 249.2606660234742,
          "p50": 277.75656248559244,
          "p95": 322.71915444871405
        },
        "request_count": 60,
        "retry_count": 0,
        "stage": "dense_embedding"
      },
      {
        "execution_count": 31,
        "latency_ms": {
          "max": 13569.010715000331,
          "mean": 4622.749644647082,
          "min": 2700.509834976401,
          "p50": 3825.7459190208465,
          "p95": 9343.902399996296
        },
        "request_count": 31,
        "retry_count": 0,
        "stage": "planner"
      },
      {
        "execution_count": 60,
        "latency_ms": {
          "max": 104.1998760192655,
          "mean": 21.755108413829777,
          "min": 5.407749966252595,
          "p50": 11.337270960211754,
          "p95": 92.5323560106335
        },
        "request_count": 68,
        "retry_count": 0,
        "stage": "qdrant_dense_search"
      },
      {
        "execution_count": 30,
        "latency_ms": {
          "max": 130.81095804227516,
          "mean": 54.490365204401314,
          "min": 43.266166001558304,
          "p50": 44.35945849400014,
          "p95": 110.9364626725436
        },
        "request_count": 34,
        "retry_count": 0,
        "stage": "qdrant_sparse_search"
      },
      {
        "execution_count": 30,
        "latency_ms": {
          "max": 545.847792,
          "mean": 343.86878623333337,
          "min": 269.656875,
          "p50": 316.9565835,
          "p95": 543.8266954999999
        },
        "request_count": 34,
        "retry_count": 0,
        "stage": "reranking"
      },
      {
        "execution_count": 30,
        "latency_ms": {
          "max": 1334.6696259686723,
          "mean": 603.0389641644433,
          "min": 387.0526670361869,
          "p50": 559.6429375000298,
          "p95": 928.895035482128
        },
        "request_count": 30,
        "retry_count": 0,
        "stage": "sparse_encoding"
      }
    ],
    "successfully_planned_variants": 31,
    "total_provider_api_cost_usd": null,
    "unavailable_cost_lineage": [
      "openai/gpt-5-mini",
      "voyage/rerank-2.5"
    ]
  },
  "hybrid": {
    "latency_ms": {
      "max": 16802.003341,
      "mean": 6894.35255416129,
      "median": 5901.39992,
      "min": 4591.149252,
      "p95": 12077.408818
    },
    "provider_cost": null,
    "request_count": 30,
    "token_usage": 99812,
    "usage": {
      "attempted_variants": 31,
      "cost_complete": false,
      "evidence_producing_variants": 30,
      "generation": {
        "cost_basis": "UNAVAILABLE",
        "cost_usd": null,
        "execution": "NOT_EXECUTED"
      },
      "known_provider_api_cost_usd": 4.644e-05,
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
            "max": 1334.6696259686723,
            "mean": 603.0389641644433,
            "min": 387.0526670361869,
            "p50": 559.6429375000298,
            "p95": 928.895035482128
          },
          "model": "prithivida/Splade_PP_en_v1",
          "output_tokens": null,
          "pricing_snapshots": [],
          "provider": "fastembed",
          "request_count": 30,
          "retry_count": 0
        },
        {
          "cached_input_tokens": 1152,
          "cost_complete": false,
          "input_tokens": 41347,
          "known_cost_usd": 0,
          "latency_ms": {
            "max": 13569.010715000331,
            "mean": 4622.749644647082,
            "min": 2700.509834976401,
            "p50": 3825.7459190208465,
            "p95": 9343.902399996296
          },
          "model": "gpt-5-mini",
          "output_tokens": 14217,
          "pricing_snapshots": [],
          "provider": "openai",
          "request_count": 31,
          "retry_count": 0
        },
        {
          "cached_input_tokens": null,
          "cost_complete": true,
          "input_tokens": null,
          "known_cost_usd": 0,
          "latency_ms": {
            "max": 130.81095804227516,
            "mean": 37.36737293171851,
            "min": 5.407749966252595,
            "p50": 43.63668750738725,
            "p95": 92.5323560106335
          },
          "model": "rag-platform-vectors-v1",
          "output_tokens": null,
          "pricing_snapshots": [],
          "provider": "qdrant",
          "request_count": 68,
          "retry_count": 0
        },
        {
          "cached_input_tokens": null,
          "cost_complete": false,
          "input_tokens": 99812,
          "known_cost_usd": 0,
          "latency_ms": {
            "max": 545.847792,
            "mean": 343.86878623333337,
            "min": 269.656875,
            "p50": 316.9565835,
            "p95": 543.8266954999999
          },
          "model": "rerank-2.5",
          "output_tokens": null,
          "pricing_snapshots": [],
          "provider": "voyage",
          "request_count": 34,
          "retry_count": 0
        },
        {
          "cached_input_tokens": null,
          "cost_complete": true,
          "input_tokens": 387,
          "known_cost_usd": 4.644e-05,
          "latency_ms": {
            "max": 881.0207089991309,
            "mean": 306.2455084659935,
            "min": 251.7471670289524,
            "p50": 274.0816254809033,
            "p95": 489.73929009225606
          },
          "model": "voyage-4-large",
          "output_tokens": null,
          "pricing_snapshots": [
            "voyage-pricing-2026-08-12"
          ],
          "provider": "voyage",
          "request_count": 30,
          "retry_count": 0
        }
      ],
      "stages": [
        {
          "execution_count": 30,
          "latency_ms": {
            "max": 881.0207089991309,
            "mean": 306.2455084659935,
            "min": 251.7471670289524,
            "p50": 274.0816254809033,
            "p95": 489.73929009225606
          },
          "request_count": 30,
          "retry_count": 0,
          "stage": "dense_embedding"
        },
        {
          "execution_count": 31,
          "latency_ms": {
            "max": 13569.010715000331,
            "mean": 4622.749644647082,
            "min": 2700.509834976401,
            "p50": 3825.7459190208465,
            "p95": 9343.902399996296
          },
          "request_count": 31,
          "retry_count": 0,
          "stage": "planner"
        },
        {
          "execution_count": 30,
          "latency_ms": {
            "max": 95.87916795862839,
            "mean": 20.244380659035716,
            "min": 5.407749966252595,
            "p50": 10.486187500646338,
            "p95": 75.45072052744206
          },
          "request_count": 34,
          "retry_count": 0,
          "stage": "qdrant_dense_search"
        },
        {
          "execution_count": 30,
          "latency_ms": {
            "max": 130.81095804227516,
            "mean": 54.490365204401314,
            "min": 43.266166001558304,
            "p50": 44.35945849400014,
            "p95": 110.9364626725436
          },
          "request_count": 34,
          "retry_count": 0,
          "stage": "qdrant_sparse_search"
        },
        {
          "execution_count": 30,
          "latency_ms": {
            "max": 545.847792,
            "mean": 343.86878623333337,
            "min": 269.656875,
            "p50": 316.9565835,
            "p95": 543.8266954999999
          },
          "request_count": 34,
          "retry_count": 0,
          "stage": "reranking"
        },
        {
          "execution_count": 30,
          "latency_ms": {
            "max": 1334.6696259686723,
            "mean": 603.0389641644433,
            "min": 387.0526670361869,
            "p50": 559.6429375000298,
            "p95": 928.895035482128
          },
          "request_count": 30,
          "retry_count": 0,
          "stage": "sparse_encoding"
        }
      ],
      "successfully_planned_variants": 31,
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

- CURRENT / MRR: -0.0208
- CURRENT / nDCG@K: -0.0154

### Improvements

- COMPARE / MRR: +0.0833
- COMPARE / nDCG@K: +0.0615
- temporal-authority / MRR: +0.0417
- multi-evidence / MRR: +0.0417
- temporal-authority / nDCG@K: +0.0308

## Case-level drill-down

### `v3.health.current.coshh-review-trigger` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: The cleaning chemical changed — can we wait for the annual COSHH review?
- Covered EvidenceUnits: `evidence.v3.engineering.health.safety.coshh.review`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.health.safety.coshh.review` | `family.health-safety.coshh` | `doc.health-safety.coshh.v1` | documents/health-safety/coshh-procedure.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=1 → Final evidence=1

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #1 / 0.576804 | #1 / 13.684876 | #1 / 0.333333 | #1 / 0.621094 | pass | yes | evidence.v3.engineering.health.safety.coshh.review |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #16 / 0.243008 | #2 / 7.010056 | #5 / 0.190476 | #2 / 0.263672 | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #7 / 0.271875 | #3 / 5.853230 | #4 / 0.208333 | #3 / 0.253906 | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #3 / 0.303592 | #23 / 2.134038 | #7 / 0.160714 | #4 / 0.248047 | fail | no | none |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #12 / 0.249576 | #5 / 4.869857 | #8 / 0.158824 | #5 / 0.242188 | fail | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #9 / 0.265898 | #6 / 4.497960 | #6 / 0.162338 | #6 / 0.241211 | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #5 / 0.301514 | #13 / 3.411780 | #9 / 0.155556 | #7 / 0.225586 | fail | no | none |
| `d695dc92-a368-534e-b544-152e640ebdd9`<br>`d695dc92-a368-534e-b544-152e640ebdd9` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #8 / 0.268570 | #26 / 1.601926 | #12 / 0.109181 | #8 / 0.211914 | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #11 / 0.258502 | #9 / 3.513354 | #10 / 0.133929 | #9 / 0.211914 | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #2 / 0.350038 | #7 / 4.283245 | #2 / 0.226190 | #10 / 0.210938 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #4 / 0.302745 | #4 / 5.822416 | #3 / 0.222222 | #11 / 0.204102 | fail | no | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | #6 / 0.297764 | — | #13 / 0.090909 | #12 / 0.204102 | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #13 / 0.246894 | #40 / 0.629447 | #15 / 0.077778 | #13 / 0.199219 | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #14 / 0.246717 | #24 / 1.963568 | #14 / 0.087114 | #14 / 0.194336 | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #10 / 0.262633 | #10 / 3.459439 | #11 / 0.133333 | #15 / 0.183594 | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | #15 / 0.243292 | — | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #17 / 0.237726 | #33 / 1.176132 | — | — | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #18 / 0.235103 | — | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #19 / 0.231701 | — | — | — | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #20 / 0.230642 | #30 / 1.284354 | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #21 / 0.227322 | — | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #22 / 0.223946 | #34 / 0.950263 | — | — | fail | no | none |
| `945c7f18-ad33-59fb-a318-12754178cc65`<br>`945c7f18-ad33-59fb-a318-12754178cc65` | `family.training.fire`<br>`doc.training.fire.v1` | #23 / 0.223356 | — | — | — | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #24 / 0.221792 | — | — | — | fail | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #25 / 0.220955 | — | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #26 / 0.220837 | — | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #27 / 0.218301 | #28 / 1.361856 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #28 / 0.212683 | — | — | — | fail | no | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #29 / 0.207581 | — | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #30 / 0.205404 | — | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #31 / 0.204921 | — | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #32 / 0.203532 | — | — | — | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #33 / 0.203095 | #35 / 0.845333 | — | — | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #34 / 0.202967 | #38 / 0.672100 | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #35 / 0.201771 | #16 / 3.107255 | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #36 / 0.198188 | #29 / 1.295633 | — | — | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #37 / 0.197466 | #18 / 2.769571 | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #38 / 0.195544 | #27 / 1.563315 | — | — | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #39 / 0.195238 | — | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #40 / 0.195221 | #32 / 1.266564 | — | — | fail | no | none |
| `7e5de72c-2361-5b0f-8b2b-25512843e880`<br>`7e5de72c-2361-5b0f-8b2b-25512843e880` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #8 / 3.596376 | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | — | #11 / 3.453671 | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | — | #12 / 3.450754 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #14 / 3.301796 | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | — | #15 / 3.278186 | — | — | fail | no | none |
| `5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305`<br>`5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | — | #17 / 3.082307 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #19 / 2.724808 | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | — | #20 / 2.658910 | — | — | fail | no | none |
| `e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f`<br>`e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | — | #21 / 2.646831 | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | — | #22 / 2.598162 | — | — | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | — | #25 / 1.653667 | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #31 / 1.279223 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #36 / 0.836916 | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | — | #37 / 0.825200 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #39 / 0.664653 | — | — | fail | no | none |

### `v3.health.current.coshh-review-trigger` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When must a COSHH assessment be reviewed?
- Covered EvidenceUnits: `evidence.v3.engineering.health.safety.coshh.review`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.health.safety.coshh.review` | `family.health-safety.coshh` | `doc.health-safety.coshh.v1` | documents/health-safety/coshh-procedure.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=8 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #1 / 0.664890 | #1 / 20.520548 | #1 / 0.333333 | #1 / 0.902344 | pass | yes | evidence.v3.engineering.health.safety.coshh.review |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #3 / 0.452639 | #2 / 10.364654 | #2 / 0.267857 | #2 / 0.546875 | pass | yes | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #2 / 0.466140 | #6 / 7.463793 | #3 / 0.233766 | #3 / 0.451172 | pass | yes | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #12 / 0.308659 | #4 / 8.223981 | #7 / 0.169935 | #4 / 0.429688 | pass | yes | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #7 / 0.332008 | #10 / 6.876608 | #8 / 0.150000 | #5 / 0.417969 | pass | yes | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | #4 / 0.356124 | — | #11 / 0.111111 | #6 / 0.382812 | pass | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #8 / 0.331646 | #5 / 8.156235 | #5 / 0.176923 | #7 / 0.361328 | pass | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #13 / 0.307903 | #3 / 9.557682 | #4 / 0.180556 | #8 / 0.351562 | pass | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #32 / 0.243589 | #7 / 7.367793 | #12 / 0.110360 | #9 / 0.335938 | fail | no | none |
| `d695dc92-a368-534e-b544-152e640ebdd9`<br>`d695dc92-a368-534e-b544-152e640ebdd9` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #6 / 0.344842 | #23 / 4.282885 | #9 / 0.126623 | #10 / 0.335938 | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #22 / 0.266409 | #8 / 7.188831 | #10 / 0.113960 | #11 / 0.328125 | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #16 / 0.298278 | #11 / 6.831788 | #13 / 0.110119 | #12 / 0.318359 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #5 / 0.349244 | #9 / 6.948976 | #6 / 0.171429 | #13 / 0.289062 | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #11 / 0.310509 | #20 / 4.481614 | #14 / 0.102500 | #14 / 0.283203 | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #10 / 0.310613 | #29 / 4.036667 | #15 / 0.096078 | #15 / 0.257812 | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #9 / 0.321635 | #37 / 3.623720 | — | — | fail | no | none |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #14 / 0.303107 | #19 / 4.932694 | — | — | fail | no | none |
| `945c7f18-ad33-59fb-a318-12754178cc65`<br>`945c7f18-ad33-59fb-a318-12754178cc65` | `family.training.fire`<br>`doc.training.fire.v1` | #15 / 0.299995 | — | — | — | fail | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #17 / 0.281585 | — | — | — | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | #18 / 0.280780 | — | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #19 / 0.272067 | #40 / 3.441704 | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #20 / 0.269526 | — | — | — | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #21 / 0.267446 | — | — | — | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #23 / 0.263609 | #34 / 3.754721 | — | — | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #24 / 0.263102 | #35 / 3.683807 | — | — | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | #25 / 0.261434 | #27 / 4.087143 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #26 / 0.257949 | — | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #27 / 0.247725 | — | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #28 / 0.247462 | #16 / 5.894354 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #29 / 0.247379 | #31 / 3.933893 | — | — | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #30 / 0.244789 | — | — | — | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #31 / 0.244629 | — | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | #33 / 0.241784 | #14 / 6.414106 | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #34 / 0.241373 | #33 / 3.818633 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #35 / 0.233673 | — | — | — | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #36 / 0.232922 | — | — | — | fail | no | none |
| `97dc7b1e-2382-510e-be9d-bc33279603c9`<br>`97dc7b1e-2382-510e-be9d-bc33279603c9` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #37 / 0.232914 | — | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #38 / 0.231812 | #13 / 6.440954 | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #39 / 0.231580 | — | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #40 / 0.228043 | #12 / 6.530652 | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | — | #15 / 5.946023 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #17 / 5.240256 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #18 / 5.200230 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #21 / 4.384245 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #22 / 4.351966 | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | — | #24 / 4.279711 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | — | #25 / 4.182839 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | — | #26 / 4.092372 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #28 / 4.070487 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #30 / 3.963221 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #32 / 3.894905 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #36 / 3.637080 | — | — | fail | no | none |
| `7e5de72c-2361-5b0f-8b2b-25512843e880`<br>`7e5de72c-2361-5b0f-8b2b-25512843e880` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #38 / 3.596610 | — | — | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | — | #39 / 3.595411 | — | — | fail | no | none |

### `v3.health.current.coshh-review-trigger` / `product`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Do we need a new hazardous-substance assessment when a product formulation changes?
- Covered EvidenceUnits: `evidence.v3.engineering.health.safety.coshh.review`
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
| PRIMARY | `evidence.v3.engineering.health.safety.coshh.review` | `family.health-safety.coshh` | `doc.health-safety.coshh.v1` | documents/health-safety/coshh-procedure.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=1 → Final evidence=1

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #1 / 0.485135 | #1 / 15.316639 | #1 / 0.333333 | #1 / 0.820312 | pass | yes | evidence.v3.engineering.health.safety.coshh.review |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #4 / 0.248963 | #6 / 5.993432 | #5 / 0.202020 | #2 / 0.335938 | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #7 / 0.217081 | #2 / 10.311155 | #4 / 0.226190 | #3 / 0.314453 | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #2 / 0.295472 | #3 / 6.354521 | #2 / 0.267857 | #4 / 0.304688 | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #32 / 0.148487 | #9 / 5.054604 | #11 / 0.098456 | #5 / 0.275391 | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #8 / 0.207023 | #5 / 6.187600 | #6 / 0.176923 | #6 / 0.267578 | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #9 / 0.201638 | #22 / 3.165342 | #10 / 0.108466 | #7 / 0.263672 | fail | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #6 / 0.223847 | #12 / 4.536571 | #8 / 0.149733 | #8 / 0.255859 | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #3 / 0.270063 | #4 / 6.242949 | #3 / 0.236111 | #9 / 0.255859 | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #20 / 0.172513 | #14 / 4.515895 | #13 / 0.092632 | #10 / 0.248047 | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #22 / 0.168974 | #13 / 4.520596 | #14 / 0.092593 | #11 / 0.248047 | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #12 / 0.191111 | #7 / 5.691304 | #9 / 0.142157 | #12 / 0.223633 | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #31 / 0.149191 | #10 / 4.989362 | #12 / 0.094444 | #13 / 0.212891 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #5 / 0.237120 | #11 / 4.601133 | #7 / 0.162500 | #14 / 0.210938 | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #11 / 0.193883 | #35 / 1.470509 | #15 / 0.087500 | #15 / 0.202148 | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #10 / 0.196465 | — | — | — | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #13 / 0.189054 | — | — | — | fail | no | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | #14 / 0.188460 | — | — | — | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #15 / 0.187395 | — | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #16 / 0.180604 | #30 / 2.233172 | — | — | fail | no | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #17 / 0.178496 | #38 / 1.027548 | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #18 / 0.175973 | — | — | — | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #19 / 0.172688 | #40 / 0.927905 | — | — | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #21 / 0.170688 | #26 / 2.707296 | — | — | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #23 / 0.168871 | — | — | — | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #24 / 0.166903 | — | — | — | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #25 / 0.165859 | — | — | — | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #26 / 0.164708 | — | — | — | fail | no | none |
| `945c7f18-ad33-59fb-a318-12754178cc65`<br>`945c7f18-ad33-59fb-a318-12754178cc65` | `family.training.fire`<br>`doc.training.fire.v1` | #27 / 0.159359 | #32 / 1.749125 | — | — | fail | no | none |
| `d695dc92-a368-534e-b544-152e640ebdd9`<br>`d695dc92-a368-534e-b544-152e640ebdd9` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #28 / 0.155667 | #31 / 2.093636 | — | — | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | #29 / 0.151282 | #20 / 3.257886 | — | — | fail | no | none |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #30 / 0.151174 | #29 / 2.399519 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #33 / 0.147218 | #33 / 1.563094 | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #34 / 0.145833 | — | — | — | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #35 / 0.143790 | — | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #36 / 0.143421 | — | — | — | fail | no | none |
| `e6c87ef4-bdc9-5b1c-b1f7-ca27505b1d2f`<br>`e6c87ef4-bdc9-5b1c-b1f7-ca27505b1d2f` | `family.payroll.mileage`<br>`doc.payroll.mileage.v1` | #37 / 0.142950 | — | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #38 / 0.141505 | — | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #39 / 0.139341 | #21 / 3.217190 | — | — | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #40 / 0.138343 | — | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | — | #8 / 5.555111 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #15 / 4.166176 | — | — | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | — | #16 / 3.952489 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | — | #17 / 3.605339 | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | — | #18 / 3.438007 | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | — | #19 / 3.431890 | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | — | #23 / 3.002573 | — | — | fail | no | none |
| `5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305`<br>`5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | — | #24 / 2.953130 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #25 / 2.848401 | — | — | fail | no | none |
| `980e0701-e200-52b6-aa4d-4f11701cedc8`<br>`980e0701-e200-52b6-aa4d-4f11701cedc8` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | — | #27 / 2.626631 | — | — | fail | no | none |
| `e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f`<br>`e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | — | #28 / 2.616443 | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | — | #34 / 1.482170 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #36 / 1.156917 | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #37 / 1.130793 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #39 / 0.978643 | — | — | fail | no | none |

### `v3.hr.current.disciplinary-suspension` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Does being suspended mean the allegation is proven?
- Covered EvidenceUnits: `evidence.v3.engineering.hr.disciplinary.suspension`
- Metrics: recall=1.0000, precision=0.2000, MRR=0.5000, nDCG=0.6309
- Hard failures: `none`

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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=0.5000, nDCG=0.6309

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.hr.disciplinary.suspension` | `family.hr.disciplinary` | `doc.hr.disciplinary.v1` | documents/hr/disciplinary-policy.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=3 → Final evidence=3

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #2 / 0.309988 | #2 / 6.069847 | #2 / 0.285714 | #1 / 0.703125 | pass | yes | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #1 / 0.319893 | #1 / 14.291582 | #1 / 0.333333 | #2 / 0.652344 | pass | yes | evidence.v3.engineering.hr.disciplinary.suspension |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #3 / 0.140451 | #11 / 1.001080 | #4 / 0.187500 | #3 / 0.417969 | pass | yes | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #4 / 0.090896 | #7 / 1.761579 | #3 / 0.194444 | #4 / 0.318359 | fail | no | none |
| `dfe7812d-2b92-54c4-916e-85a94e0a731a`<br>`dfe7812d-2b92-54c4-916e-85a94e0a731a` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | #38 / 0.006945 | #3 / 5.946269 | #6 / 0.148256 | #5 / 0.267578 | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #14 / 0.046878 | #17 / 0.774489 | #14 / 0.098086 | #6 / 0.253906 | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #8 / 0.064692 | #21 / 0.570988 | #11 / 0.115385 | #7 / 0.253906 | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #21 / 0.036778 | #4 / 4.233039 | #5 / 0.149573 | #8 / 0.241211 | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #7 / 0.064694 | #34 / 0.346264 | #12 / 0.108974 | #9 / 0.235352 | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #29 / 0.026224 | #6 / 1.806932 | #9 / 0.120321 | #10 / 0.229492 | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #5 / 0.066144 | #25 / 0.531517 | #8 / 0.133333 | #11 / 0.229492 | fail | no | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #16 / 0.045277 | #5 / 1.860294 | #7 / 0.147619 | #12 / 0.228516 | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #15 / 0.046246 | #16 / 0.912782 | #15 / 0.097619 | #13 / 0.215820 | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #30 / 0.023971 | #9 / 1.328206 | #13 / 0.100000 | #14 / 0.183594 | fail | no | none |
| `e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f`<br>`e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | #6 / 0.065792 | #35 / 0.317429 | #10 / 0.115909 | #15 / 0.168945 | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #9 / 0.063064 | — | — | — | fail | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #10 / 0.062692 | — | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #11 / 0.059252 | — | — | — | fail | no | none |
| `8aa6fad2-b29c-5376-8583-c09ad8bcdf41`<br>`8aa6fad2-b29c-5376-8583-c09ad8bcdf41` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #12 / 0.056297 | #27 / 0.494547 | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #13 / 0.055135 | #39 / 0.288430 | — | — | fail | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #17 / 0.041832 | #18 / 0.665322 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | #18 / 0.041638 | #15 / 0.921270 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | #19 / 0.041245 | #31 / 0.417297 | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #20 / 0.038736 | #14 / 0.921303 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #22 / 0.035039 | — | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #23 / 0.033533 | — | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #24 / 0.031292 | #12 / 0.960752 | — | — | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #25 / 0.029827 | — | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #26 / 0.029115 | #24 / 0.533822 | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #27 / 0.028654 | — | — | — | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #28 / 0.028349 | #30 / 0.418957 | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #31 / 0.021107 | — | — | — | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #32 / 0.019192 | #36 / 0.316219 | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #33 / 0.018019 | #32 / 0.397891 | — | — | fail | no | none |
| `97dc7b1e-2382-510e-be9d-bc33279603c9`<br>`97dc7b1e-2382-510e-be9d-bc33279603c9` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #34 / 0.017273 | — | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | #35 / 0.012917 | #40 / 0.286815 | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #36 / 0.011122 | — | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #37 / 0.008238 | #28 / 0.490827 | — | — | fail | no | none |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #39 / 0.006482 | — | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #40 / 0.006353 | #33 / 0.358903 | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | — | #8 / 1.417383 | — | — | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | — | #10 / 1.069395 | — | — | fail | no | none |
| `e6c87ef4-bdc9-5b1c-b1f7-ca27505b1d2f`<br>`e6c87ef4-bdc9-5b1c-b1f7-ca27505b1d2f` | `family.payroll.mileage`<br>`doc.payroll.mileage.v1` | — | #13 / 0.949107 | — | — | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | — | #19 / 0.575980 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #20 / 0.574167 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #22 / 0.565286 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #23 / 0.563844 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #26 / 0.511219 | — | — | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | — | #29 / 0.456434 | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | — | #37 / 0.300464 | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | — | #38 / 0.292531 | — | — | fail | no | none |

### `v3.hr.current.disciplinary-suspension` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is suspension a disciplinary punishment?
- Covered EvidenceUnits: `evidence.v3.engineering.hr.disciplinary.suspension`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.hr.disciplinary.suspension` | `family.hr.disciplinary` | `doc.hr.disciplinary.v1` | documents/hr/disciplinary-policy.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=3 → Final evidence=3

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #1 / 0.423996 | #1 / 19.907558 | #1 / 0.333333 | #1 / 0.878906 | pass | yes | evidence.v3.engineering.hr.disciplinary.suspension |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #2 / 0.284137 | #2 / 8.241773 | #2 / 0.285714 | #2 / 0.601562 | pass | yes | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #17 / 0.118655 | #10 / 1.045185 | #10 / 0.112121 | #3 / 0.361328 | pass | yes | none |
| `8aa6fad2-b29c-5376-8583-c09ad8bcdf41`<br>`8aa6fad2-b29c-5376-8583-c09ad8bcdf41` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #4 / 0.140101 | #9 / 1.238833 | #5 / 0.182540 | #4 / 0.283203 | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #8 / 0.131735 | #3 / 6.687969 | #4 / 0.201923 | #5 / 0.279297 | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #7 / 0.132757 | #31 / 0.220846 | #11 / 0.111111 | #6 / 0.277344 | fail | no | none |
| `dfe7812d-2b92-54c4-916e-85a94e0a731a`<br>`dfe7812d-2b92-54c4-916e-85a94e0a731a` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | #15 / 0.121864 | #4 / 6.580215 | #6 / 0.161111 | #7 / 0.267578 | fail | no | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #29 / 0.094505 | #8 / 1.537016 | #12 / 0.106335 | #8 / 0.263672 | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #3 / 0.157918 | #6 / 1.889473 | #3 / 0.215909 | #9 / 0.261719 | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #13 / 0.123680 | #7 / 1.860120 | #8 / 0.138889 | #10 / 0.255859 | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #24 / 0.102762 | #15 / 0.857642 | #15 / 0.084483 | #11 / 0.242188 | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #5 / 0.139039 | #13 / 0.912950 | #7 / 0.155556 | #12 / 0.236328 | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #30 / 0.091623 | #5 / 2.477300 | #9 / 0.128571 | #13 / 0.235352 | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #6 / 0.135733 | — | #13 / 0.090909 | #14 / 0.229492 | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #18 / 0.112643 | #18 / 0.737790 | #14 / 0.086957 | #15 / 0.218750 | fail | no | none |
| `97dc7b1e-2382-510e-be9d-bc33279603c9`<br>`97dc7b1e-2382-510e-be9d-bc33279603c9` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #9 / 0.131406 | — | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #10 / 0.129232 | — | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #11 / 0.127201 | — | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | #12 / 0.126309 | #34 / 0.194107 | — | — | fail | no | none |
| `e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f`<br>`e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | #14 / 0.122788 | #28 / 0.260928 | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #16 / 0.119762 | — | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #19 / 0.106577 | #35 / 0.191336 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #20 / 0.103907 | #38 / 0.161265 | — | — | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #21 / 0.103804 | #27 / 0.322708 | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #22 / 0.103249 | — | — | — | fail | no | none |
| `aead6f19-4c74-555f-9c5b-f86711197db5`<br>`aead6f19-4c74-555f-9c5b-f86711197db5` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | #23 / 0.103075 | #30 / 0.222731 | — | — | fail | no | none |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #25 / 0.102468 | — | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #26 / 0.098212 | #26 / 0.323609 | — | — | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #27 / 0.096085 | — | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #28 / 0.094936 | — | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #31 / 0.089279 | — | — | — | fail | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #32 / 0.087953 | — | — | — | fail | no | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | #33 / 0.085935 | #37 / 0.161710 | — | — | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | #34 / 0.085665 | — | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #35 / 0.084161 | #12 / 0.963879 | — | — | fail | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #36 / 0.083865 | #19 / 0.553679 | — | — | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #37 / 0.083337 | #23 / 0.350988 | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #38 / 0.081566 | — | — | — | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #39 / 0.081282 | #32 / 0.219882 | — | — | fail | no | none |
| `945c7f18-ad33-59fb-a318-12754178cc65`<br>`945c7f18-ad33-59fb-a318-12754178cc65` | `family.training.fire`<br>`doc.training.fire.v1` | #40 / 0.080649 | — | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #11 / 0.966099 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | — | #14 / 0.857708 | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | — | #16 / 0.846493 | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | — | #17 / 0.776486 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | — | #20 / 0.528601 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #21 / 0.460142 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | — | #22 / 0.449962 | — | — | fail | no | none |
| `e6c87ef4-bdc9-5b1c-b1f7-ca27505b1d2f`<br>`e6c87ef4-bdc9-5b1c-b1f7-ca27505b1d2f` | `family.payroll.mileage`<br>`doc.payroll.mileage.v1` | — | #24 / 0.345936 | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #25 / 0.341855 | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | — | #29 / 0.224042 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #33 / 0.195686 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #36 / 0.174264 | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #39 / 0.151538 | — | — | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | — | #40 / 0.143719 | — | — | fail | no | none |

### `v3.hr.current.disciplinary-suspension` / `review`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How often must a precautionary suspension be reviewed?
- Covered EvidenceUnits: `evidence.v3.engineering.hr.disciplinary.suspension`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.hr.disciplinary.suspension` | `family.hr.disciplinary` | `doc.hr.disciplinary.v1` | documents/hr/disciplinary-policy.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=8 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #1 / 0.409682 | #1 / 19.160467 | #1 / 0.333333 | #1 / 0.867188 | pass | yes | evidence.v3.engineering.hr.disciplinary.suspension |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #6 / 0.289034 | #2 / 10.129626 | #2 / 0.233766 | #2 / 0.593750 | pass | yes | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #4 / 0.305204 | #4 / 7.931727 | #3 / 0.222222 | #3 / 0.476562 | pass | yes | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #2 / 0.325935 | #36 / 3.191948 | #7 / 0.167247 | #4 / 0.439453 | pass | yes | none |
| `d695dc92-a368-534e-b544-152e640ebdd9`<br>`d695dc92-a368-534e-b544-152e640ebdd9` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #3 / 0.305601 | #9 / 5.458384 | #5 / 0.196429 | #5 / 0.371094 | pass | yes | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #8 / 0.258320 | #11 / 4.995892 | #9 / 0.139423 | #6 / 0.343750 | pass | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #13 / 0.247580 | #8 / 6.018638 | #10 / 0.132479 | #7 / 0.343750 | pass | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #5 / 0.301179 | #5 / 7.157461 | #4 / 0.200000 | #8 / 0.339844 | pass | no | none |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #9 / 0.257682 | #3 / 8.104171 | #6 / 0.196429 | #9 / 0.306641 | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #18 / 0.242613 | #17 / 4.307361 | #15 / 0.088933 | #10 / 0.304688 | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #16 / 0.242958 | #16 / 4.352139 | #12 / 0.095238 | #11 / 0.271484 | fail | no | none |
| `dfe7812d-2b92-54c4-916e-85a94e0a731a`<br>`dfe7812d-2b92-54c4-916e-85a94e0a731a` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | — | #6 / 6.651790 | #14 / 0.090909 | #12 / 0.269531 | fail | no | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | #15 / 0.243388 | #19 / 4.148499 | #13 / 0.091667 | #13 / 0.261719 | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #12 / 0.250460 | #7 / 6.473172 | #8 / 0.142157 | #14 / 0.257812 | fail | no | none |
| `7e5de72c-2361-5b0f-8b2b-25512843e880`<br>`7e5de72c-2361-5b0f-8b2b-25512843e880` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #29 / 0.197222 | #10 / 5.353534 | #11 / 0.096078 | #15 / 0.238281 | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #7 / 0.264892 | — | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #10 / 0.253028 | — | — | — | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | #11 / 0.251854 | — | — | — | fail | no | none |
| `8aa6fad2-b29c-5376-8583-c09ad8bcdf41`<br>`8aa6fad2-b29c-5376-8583-c09ad8bcdf41` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #14 / 0.243596 | — | — | — | fail | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #17 / 0.242822 | #38 / 3.074457 | — | — | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #19 / 0.242227 | #18 / 4.244597 | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #20 / 0.240360 | #39 / 3.046169 | — | — | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #21 / 0.239235 | #35 / 3.212746 | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #22 / 0.236024 | — | — | — | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #23 / 0.229710 | #27 / 3.762340 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #24 / 0.226484 | — | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #25 / 0.217671 | #25 / 3.867787 | — | — | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #26 / 0.205339 | — | — | — | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #27 / 0.202559 | — | — | — | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | #28 / 0.201541 | #21 / 4.125603 | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #30 / 0.195268 | — | — | — | fail | no | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #31 / 0.195081 | #30 / 3.591721 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #32 / 0.192337 | #34 / 3.280047 | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #33 / 0.191389 | — | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #34 / 0.190508 | #26 / 3.771228 | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #35 / 0.186362 | — | — | — | fail | no | none |
| `945c7f18-ad33-59fb-a318-12754178cc65`<br>`945c7f18-ad33-59fb-a318-12754178cc65` | `family.training.fire`<br>`doc.training.fire.v1` | #36 / 0.178799 | — | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #37 / 0.177949 | #23 / 3.886931 | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #38 / 0.175424 | #33 / 3.310346 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | #39 / 0.175103 | — | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #40 / 0.173374 | — | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #12 / 4.846305 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #13 / 4.798753 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #14 / 4.749673 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #15 / 4.495284 | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | — | #20 / 4.144165 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #22 / 3.922210 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #24 / 3.870257 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #28 / 3.677000 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #29 / 3.632531 | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | — | #31 / 3.547731 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | — | #32 / 3.429328 | — | — | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | — | #37 / 3.142630 | — | — | fail | no | none |
| `aead6f19-4c74-555f-9c5b-f86711197db5`<br>`aead6f19-4c74-555f-9c5b-f86711197db5` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #40 / 2.913951 | — | — | fail | no | none |

### `v3.infection-control.current.midlands-community-specimen-transport` / `coventry`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How must the Coventry community team package a specimen, and what must staff do when handing it to the courier?
- Covered EvidenceUnits: `evidence.v3.engineering.specimen-transport.packaging, evidence.v3.engineering.specimen-transport.handover`
- Metrics: recall=1.0000, precision=0.4000, MRR=1.0000, nDCG=0.6131
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Coventry community team",
      "Coventry"
    ],
    "retrieval_queries": [
      "How must the Coventry community team package a specimen, and what must staff do when handing it to the courier?"
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
      "How must the Coventry community team package a specimen, and what must staff do when handing it to the courier?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=1.0000, precision=0.4000, MRR=1.0000, nDCG=0.6131

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.specimen-transport.packaging` | `family.infection-control.midlands-community-specimen-transport` | `doc.infection-control.midlands-community-specimen-transport.v1` | documents/infection-control/midlands-community-specimen-transport.md |
| PRIMARY | `evidence.v3.engineering.specimen-transport.handover` | `family.infection-control.midlands-community-specimen-transport` | `doc.infection-control.midlands-community-specimen-transport.v1` | documents/infection-control/midlands-community-specimen-transport.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=2 → Final evidence=2

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #1 / 0.622362 | #1 / 18.546988 | #1 / 0.333333 | #1 / 0.890625 | pass | yes | evidence.v3.engineering.specimen-transport.packaging, evidence.v3.engineering.specimen-transport.handover |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #2 / 0.347600 | #25 / 2.189522 | #4 / 0.176190 | #2 / 0.373047 | pass | yes | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #4 / 0.287823 | #8 / 3.935839 | #3 / 0.188034 | #3 / 0.324219 | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #3 / 0.335768 | — | #8 / 0.125000 | #4 / 0.312500 | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #5 / 0.277200 | #2 / 17.101715 | #2 / 0.242857 | #5 / 0.296875 | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #11 / 0.254562 | #17 / 2.618691 | #15 / 0.107955 | #6 / 0.291016 | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #9 / 0.259835 | #9 / 3.688327 | #6 / 0.142857 | #7 / 0.287109 | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #10 / 0.255709 | #16 / 2.857182 | #12 / 0.114286 | #8 / 0.277344 | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #7 / 0.267162 | #19 / 2.463172 | #9 / 0.125000 | #9 / 0.261719 | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #24 / 0.205186 | #3 / 11.733543 | #5 / 0.159483 | #10 / 0.242188 | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #12 / 0.253930 | #10 / 3.320363 | #7 / 0.125490 | #11 / 0.230469 | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #34 / 0.182237 | #7 / 4.210587 | #14 / 0.108974 | #12 / 0.222656 | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #37 / 0.173878 | #5 / 4.761449 | #10 / 0.123810 | #13 / 0.215820 | fail | no | none |
| `85de0be4-0aca-5ddb-a5b2-bc7723fd07e6`<br>`85de0be4-0aca-5ddb-a5b2-bc7723fd07e6` | `family.complaints.advocacy`<br>`doc.complaints.advocacy.v1` | — | #4 / 5.506473 | #13 / 0.111111 | #14 / 0.211914 | fail | no | none |
| `980e0701-e200-52b6-aa4d-4f11701cedc8`<br>`980e0701-e200-52b6-aa4d-4f11701cedc8` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | #31 / 0.186347 | #6 / 4.404103 | #11 / 0.118687 | #15 / 0.202148 | fail | no | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.272436 | — | — | — | fail | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #8 / 0.260735 | — | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #13 / 0.251777 | — | — | — | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #14 / 0.251546 | #26 / 2.151523 | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #15 / 0.245539 | #22 / 2.397800 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #16 / 0.244896 | #32 / 1.949998 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | #17 / 0.229793 | #14 / 3.163955 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #18 / 0.226415 | #11 / 3.207991 | — | — | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #19 / 0.226342 | — | — | — | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #20 / 0.219140 | — | — | — | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #21 / 0.213279 | #23 / 2.270099 | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #22 / 0.207396 | #33 / 1.946785 | — | — | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #23 / 0.205191 | — | — | — | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #25 / 0.196448 | — | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #26 / 0.195235 | — | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #27 / 0.194118 | #34 / 1.915266 | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #28 / 0.193868 | #20 / 2.431847 | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #29 / 0.191596 | #24 / 2.222416 | — | — | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #30 / 0.190760 | #30 / 2.066099 | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #32 / 0.186306 | — | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #33 / 0.184613 | — | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #35 / 0.179169 | — | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #36 / 0.174318 | #39 / 1.519818 | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #38 / 0.172629 | — | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #39 / 0.172265 | #27 / 2.088792 | — | — | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #40 / 0.170452 | — | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #12 / 3.190371 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | — | #13 / 3.169242 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #15 / 2.940206 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #18 / 2.568964 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #21 / 2.431528 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | — | #28 / 2.084457 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #29 / 2.069643 | — | — | fail | no | none |
| `97dc7b1e-2382-510e-be9d-bc33279603c9`<br>`97dc7b1e-2382-510e-be9d-bc33279603c9` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | — | #31 / 1.990318 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #35 / 1.874201 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #36 / 1.822709 | — | — | fail | no | none |
| `aead6f19-4c74-555f-9c5b-f86711197db5`<br>`aead6f19-4c74-555f-9c5b-f86711197db5` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #37 / 1.774790 | — | — | fail | no | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | — | #38 / 1.626124 | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | — | #40 / 1.519373 | — | — | fail | no | none |

### `v3.infection-control.current.midlands-community-specimen-transport` / `hierarchy`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: For the Coventry community team in the Midlands, what specimen packaging and courier-handover controls apply?
- Covered EvidenceUnits: `evidence.v3.engineering.specimen-transport.packaging, evidence.v3.engineering.specimen-transport.handover`
- Metrics: recall=1.0000, precision=0.4000, MRR=1.0000, nDCG=0.6131
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Coventry community team",
      "Midlands"
    ],
    "retrieval_queries": [
      "For the Coventry community team in the Midlands, what specimen packaging and courier-handover controls apply?"
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
      "Coventry",
      "Midlands"
    ],
    "retrieval_queries": [
      "For the Coventry community team in the Midlands, what specimen packaging and courier-handover controls apply?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=1.0000, precision=0.4000, MRR=1.0000, nDCG=0.6131

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.specimen-transport.packaging` | `family.infection-control.midlands-community-specimen-transport` | `doc.infection-control.midlands-community-specimen-transport.v1` | documents/infection-control/midlands-community-specimen-transport.md |
| PRIMARY | `evidence.v3.engineering.specimen-transport.handover` | `family.infection-control.midlands-community-specimen-transport` | `doc.infection-control.midlands-community-specimen-transport.v1` | documents/infection-control/midlands-community-specimen-transport.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=2 → Final evidence=2

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #1 / 0.577376 | #1 / 25.435800 | #1 / 0.333333 | #1 / 0.882812 | pass | yes | evidence.v3.engineering.specimen-transport.packaging, evidence.v3.engineering.specimen-transport.handover |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #2 / 0.323303 | #2 / 20.007359 | #2 / 0.285714 | #2 / 0.341797 | pass | yes | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #9 / 0.248401 | #38 / 0.849229 | #15 / 0.094684 | #3 / 0.324219 | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #4 / 0.308493 | #33 / 1.045406 | #9 / 0.137427 | #4 / 0.318359 | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #3 / 0.315082 | — | #10 / 0.125000 | #5 / 0.300781 | fail | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #7 / 0.258195 | #12 / 2.608765 | #8 / 0.142157 | #6 / 0.300781 | fail | no | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.260543 | #9 / 3.069321 | #4 / 0.162338 | #7 / 0.291016 | fail | no | none |
| `980e0701-e200-52b6-aa4d-4f11701cedc8`<br>`980e0701-e200-52b6-aa4d-4f11701cedc8` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | #16 / 0.217575 | #5 / 6.461092 | #7 / 0.147619 | #8 / 0.269531 | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #22 / 0.201543 | #7 / 3.449092 | #12 / 0.120370 | #9 / 0.267578 | fail | no | none |
| `85de0be4-0aca-5ddb-a5b2-bc7723fd07e6`<br>`85de0be4-0aca-5ddb-a5b2-bc7723fd07e6` | `family.complaints.advocacy`<br>`doc.complaints.advocacy.v1` | #21 / 0.204828 | #4 / 6.559619 | #6 / 0.149573 | #10 / 0.261719 | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #5 / 0.261846 | #13 / 2.416110 | #5 / 0.155556 | #11 / 0.257812 | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #15 / 0.219969 | #3 / 14.263529 | #3 / 0.175000 | #12 / 0.253906 | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #8 / 0.253184 | #26 / 1.339893 | #13 / 0.109181 | #13 / 0.248047 | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #18 / 0.209222 | #11 / 2.858261 | #14 / 0.105978 | #14 / 0.229492 | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #28 / 0.183215 | #6 / 4.046597 | #11 / 0.121212 | #15 / 0.223633 | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #10 / 0.237363 | — | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #11 / 0.236684 | — | — | — | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #12 / 0.233712 | — | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #13 / 0.230711 | #21 / 1.589990 | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #14 / 0.230361 | #32 / 1.086571 | — | — | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #17 / 0.214585 | — | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #19 / 0.207284 | #15 / 1.889612 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | #20 / 0.206355 | — | — | — | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #23 / 0.189993 | — | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #24 / 0.189638 | — | — | — | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #25 / 0.185389 | — | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #26 / 0.184819 | — | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #27 / 0.183285 | — | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #29 / 0.180115 | #20 / 1.647759 | — | — | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #30 / 0.177883 | — | — | — | fail | no | none |
| `97dc7b1e-2382-510e-be9d-bc33279603c9`<br>`97dc7b1e-2382-510e-be9d-bc33279603c9` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #31 / 0.177193 | #23 / 1.533640 | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #32 / 0.171473 | #16 / 1.864271 | — | — | fail | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #33 / 0.170989 | #27 / 1.314503 | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #34 / 0.170490 | #10 / 2.971836 | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #35 / 0.161871 | — | — | — | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #36 / 0.161846 | #35 / 0.979600 | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #37 / 0.159457 | #37 / 0.863048 | — | — | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #38 / 0.156355 | #18 / 1.799256 | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #39 / 0.155908 | — | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #40 / 0.152705 | — | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | — | #8 / 3.368852 | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | — | #14 / 2.108552 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #17 / 1.830445 | — | — | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | — | #19 / 1.722984 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #22 / 1.563132 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | — | #24 / 1.420589 | — | — | fail | no | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | — | #25 / 1.356918 | — | — | fail | no | none |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | — | #28 / 1.270873 | — | — | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | — | #29 / 1.223679 | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | — | #30 / 1.222620 | — | — | fail | no | none |
| `aead6f19-4c74-555f-9c5b-f86711197db5`<br>`aead6f19-4c74-555f-9c5b-f86711197db5` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #31 / 1.185686 | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | — | #34 / 0.994251 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #36 / 0.890264 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | — | #39 / 0.696332 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #40 / 0.690518 | — | — | fail | no | none |

### `v3.infection-control.current.midlands-community-specimen-transport` / `regional`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Under the Midlands regional procedure, how should the Coventry community team package and hand over a specimen?
- Covered EvidenceUnits: `evidence.v3.engineering.specimen-transport.packaging, evidence.v3.engineering.specimen-transport.handover`
- Metrics: recall=1.0000, precision=0.4000, MRR=1.0000, nDCG=0.6131
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [
      "Midlands",
      "Coventry"
    ],
    "retrieval_queries": [
      "Under the Midlands regional procedure, how should the Coventry community team package and hand over a specimen?"
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
      "Midlands",
      "Coventry"
    ],
    "retrieval_queries": [
      "Under the Midlands regional procedure, how should the Coventry community team package and hand over a specimen?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=1.0000, precision=0.4000, MRR=1.0000, nDCG=0.6131

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.specimen-transport.packaging` | `family.infection-control.midlands-community-specimen-transport` | `doc.infection-control.midlands-community-specimen-transport.v1` | documents/infection-control/midlands-community-specimen-transport.md |
| PRIMARY | `evidence.v3.engineering.specimen-transport.handover` | `family.infection-control.midlands-community-specimen-transport` | `doc.infection-control.midlands-community-specimen-transport.v1` | documents/infection-control/midlands-community-specimen-transport.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=2 → Final evidence=2

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #1 / 0.602262 | #1 / 28.095673 | #1 / 0.333333 | #1 / 0.906250 | pass | yes | evidence.v3.engineering.specimen-transport.packaging, evidence.v3.engineering.specimen-transport.handover |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #4 / 0.324776 | #2 / 25.190556 | #2 / 0.253968 | #2 / 0.339844 | pass | yes | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #3 / 0.338731 | #10 / 4.531404 | #4 / 0.191667 | #3 / 0.322266 | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #7 / 0.248063 | #36 / 1.817529 | #11 / 0.107724 | #4 / 0.296875 | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #2 / 0.351535 | #26 / 3.253938 | #6 / 0.175115 | #5 / 0.294922 | fail | no | none |
| `980e0701-e200-52b6-aa4d-4f11701cedc8`<br>`980e0701-e200-52b6-aa4d-4f11701cedc8` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | #8 / 0.245459 | #4 / 9.190503 | #5 / 0.188034 | #6 / 0.294922 | fail | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #19 / 0.216480 | #15 / 3.873438 | #14 / 0.091667 | #7 / 0.287109 | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #5 / 0.259874 | #3 / 20.163584 | #3 / 0.225000 | #8 / 0.285156 | fail | no | none |
| `85de0be4-0aca-5ddb-a5b2-bc7723fd07e6`<br>`85de0be4-0aca-5ddb-a5b2-bc7723fd07e6` | `family.complaints.advocacy`<br>`doc.complaints.advocacy.v1` | #21 / 0.207435 | #5 / 7.659210 | #9 / 0.138462 | #9 / 0.283203 | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #11 / 0.232654 | #30 / 2.732178 | #15 / 0.091071 | #10 / 0.241211 | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #6 / 0.250451 | #23 / 3.413025 | #10 / 0.126623 | #11 / 0.241211 | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #14 / 0.226527 | #6 / 5.675636 | #8 / 0.143541 | #12 / 0.238281 | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #9 / 0.245291 | #24 / 3.381303 | #12 / 0.105911 | #13 / 0.236328 | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #10 / 0.244183 | #8 / 4.953481 | #7 / 0.143590 | #14 / 0.225586 | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | #17 / 0.219473 | #12 / 4.133152 | #13 / 0.104278 | #15 / 0.225586 | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #12 / 0.231495 | #29 / 2.783195 | — | — | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #13 / 0.226941 | — | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #15 / 0.225878 | #35 / 1.846583 | — | — | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #16 / 0.224631 | — | — | — | fail | no | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #18 / 0.217409 | #34 / 2.023037 | — | — | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #20 / 0.213880 | — | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #22 / 0.206086 | #20 / 3.468055 | — | — | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #23 / 0.204197 | — | — | — | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #24 / 0.201014 | — | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #25 / 0.194287 | #25 / 3.371209 | — | — | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #26 / 0.191104 | — | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #27 / 0.189249 | #32 / 2.348854 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #28 / 0.178868 | — | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #29 / 0.178619 | — | — | — | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #30 / 0.177755 | — | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #31 / 0.177475 | — | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #32 / 0.176758 | — | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #33 / 0.176688 | #16 / 3.796308 | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #34 / 0.176642 | #14 / 3.915667 | — | — | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #35 / 0.175784 | — | — | — | fail | no | none |
| `97dc7b1e-2382-510e-be9d-bc33279603c9`<br>`97dc7b1e-2382-510e-be9d-bc33279603c9` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #36 / 0.175262 | #40 / 1.211734 | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #37 / 0.173899 | — | — | — | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #38 / 0.173428 | — | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #39 / 0.169659 | — | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #40 / 0.160943 | #17 / 3.744715 | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | — | #7 / 5.503950 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #9 / 4.777544 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #11 / 4.312101 | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | — | #13 / 3.967334 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | — | #18 / 3.557557 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #19 / 3.494939 | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | — | #21 / 3.421561 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | — | #22 / 3.420237 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #27 / 3.198796 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #28 / 3.007142 | — | — | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | — | #31 / 2.584405 | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | — | #33 / 2.265624 | — | — | fail | no | none |
| `8aa6fad2-b29c-5376-8583-c09ad8bcdf41`<br>`8aa6fad2-b29c-5376-8583-c09ad8bcdf41` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #37 / 1.749879 | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | — | #38 / 1.709125 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #39 / 1.492092 | — | — | fail | no | none |

### `v3.medication.compare.controlled-drugs-discrepancy` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How did the CD stock-mismatch rule change from the old procedure to now?
- Covered EvidenceUnits: `evidence.v3.engineering.medication.controlled-drugs-compare.v1, evidence.v3.engineering.medication.controlled-drugs-compare.current`
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
      "How did the CD stock-mismatch rule change from the old procedure to now?"
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
      "How did the CD stock-mismatch rule change from the old procedure to now?"
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
| PRIMARY | `evidence.v3.engineering.medication.controlled-drugs-compare.current` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v2` | documents/medication/controlled-drugs-v2.md |
| COMPARISON | `evidence.v3.engineering.medication.controlled-drugs-compare.v1` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v1` | documents/medication/controlled-drugs-v1.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=2 → Final evidence=2

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #2 / 0.155150 | #1 / 5.474166 | #2 / 0.309524 | #1 / 0.416016 | pass | yes | evidence.v3.engineering.medication.controlled-drugs-compare.current |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #1 / 0.170781 | #2 / 5.279089 | #1 / 0.309524 | #2 / 0.337891 | pass | yes | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.125770 | — | #12 / 0.090909 | #3 / 0.302734 | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #12 / 0.080387 | #36 / 2.135964 | #15 / 0.083214 | #4 / 0.249023 | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #4 / 0.131007 | — | #9 / 0.111111 | #5 / 0.248047 | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #7 / 0.101661 | #6 / 3.499980 | #4 / 0.174242 | #6 / 0.243164 | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #38 / 0.049873 | #9 / 3.443448 | #11 / 0.094684 | #7 / 0.238281 | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #5 / 0.125967 | #10 / 3.433834 | #5 / 0.166667 | #8 / 0.235352 | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | — | #5 / 3.530658 | #10 / 0.100000 | #9 / 0.223633 | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | #11 / 0.082607 | #3 / 4.908050 | #3 / 0.187500 | #10 / 0.221680 | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #32 / 0.053584 | #11 / 3.312393 | #14 / 0.089527 | #11 / 0.221680 | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #25 / 0.060389 | #4 / 3.606153 | #7 / 0.144444 | #12 / 0.218750 | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #14 / 0.075316 | #7 / 3.457036 | #8 / 0.135965 | #13 / 0.215820 | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #3 / 0.133614 | #28 / 2.793427 | #6 / 0.155303 | #14 / 0.211914 | fail | no | none |
| `e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f`<br>`e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | #10 / 0.083755 | #37 / 1.946624 | #13 / 0.090476 | #15 / 0.202148 | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #8 / 0.101514 | — | — | — | fail | no | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #9 / 0.099521 | — | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #13 / 0.079694 | #35 / 2.221051 | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #15 / 0.075005 | — | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #16 / 0.074045 | #38 / 1.895013 | — | — | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #17 / 0.073722 | #24 / 2.987107 | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #18 / 0.072515 | #27 / 2.802551 | — | — | fail | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #19 / 0.069186 | — | — | — | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #20 / 0.068768 | #31 / 2.701041 | — | — | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #21 / 0.067567 | #33 / 2.624691 | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #22 / 0.066674 | #26 / 2.870139 | — | — | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #23 / 0.064885 | — | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | #24 / 0.064821 | #25 / 2.935579 | — | — | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #26 / 0.058887 | — | — | — | fail | no | none |
| `8aa6fad2-b29c-5376-8583-c09ad8bcdf41`<br>`8aa6fad2-b29c-5376-8583-c09ad8bcdf41` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #27 / 0.058573 | — | — | — | fail | no | none |
| `5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305`<br>`5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | #28 / 0.057986 | #34 / 2.528199 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #29 / 0.057903 | #32 / 2.658360 | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #30 / 0.057595 | — | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #31 / 0.054043 | — | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #33 / 0.050641 | — | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #34 / 0.050599 | — | — | — | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #35 / 0.050298 | #22 / 3.055524 | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #36 / 0.050198 | #13 / 3.294315 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | #37 / 0.050073 | #19 / 3.102576 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #39 / 0.049410 | #20 / 3.088862 | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #40 / 0.048283 | — | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #8 / 3.456091 | — | — | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | — | #12 / 3.301502 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | — | #14 / 3.185968 | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #15 / 3.184743 | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | — | #16 / 3.182235 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #17 / 3.149857 | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | — | #18 / 3.115721 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #21 / 3.068080 | — | — | fail | no | none |
| `016e8751-5c0c-58b9-8695-c190270b5921`<br>`016e8751-5c0c-58b9-8695-c190270b5921` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | — | #23 / 3.011056 | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | — | #29 / 2.728721 | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | — | #30 / 2.706353 | — | — | fail | no | none |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | — | #39 / 1.758368 | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | — | #40 / 1.579451 | — | — | fail | no | none |

#### COMPARISON

Candidate funnel: Dense=13 → Sparse=12 → Unique after RRF=13 → Reranker=13 → Threshold=1 → Final evidence=1

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `dee03403-128d-556b-bb3e-469857e808fd`<br>`dee03403-128d-556b-bb3e-469857e808fd` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #1 / 0.163174 | #1 / 5.570160 | #1 / 0.333333 | #1 / 0.384766 | pass | yes | evidence.v3.engineering.medication.controlled-drugs-compare.v1 |
| `c4979314-9ca2-573f-a219-57ab4773ad1f`<br>`c4979314-9ca2-573f-a219-57ab4773ad1f` | `family.medication.administration`<br>`doc.medication.administration.v1` | #2 / 0.091012 | #9 / 0.668393 | #2 / 0.214286 | #2 / 0.255859 | fail | no | none |
| `886fc5bc-416a-5ed9-9de6-5631b45c167d`<br>`886fc5bc-416a-5ed9-9de6-5631b45c167d` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #6 / 0.056139 | #11 / 0.065117 | #11 / 0.153409 | #3 / 0.221680 | fail | no | none |
| `2dcdbc13-00b3-5e91-997b-19e37ff1c84d`<br>`2dcdbc13-00b3-5e91-997b-19e37ff1c84d` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #11 / 0.032592 | #4 / 2.871991 | #7 / 0.173611 | #4 / 0.221680 | fail | no | none |
| `19700c62-cb1e-5c51-a9cf-8cce818fe9d2`<br>`19700c62-cb1e-5c51-a9cf-8cce818fe9d2` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #8 / 0.052483 | #8 / 1.259231 | #10 / 0.153846 | #5 / 0.218750 | fail | no | none |
| `33321467-60b1-5a2d-8a8d-3779711290aa`<br>`33321467-60b1-5a2d-8a8d-3779711290aa` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #7 / 0.055520 | #3 / 3.236023 | #4 / 0.208333 | #6 / 0.212891 | fail | no | none |
| `64df7c22-d350-5124-b01b-770fb0793050`<br>`64df7c22-d350-5124-b01b-770fb0793050` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #10 / 0.041834 | #2 / 3.479662 | #3 / 0.209524 | #7 / 0.212891 | fail | no | none |
| `6a0b9950-1b65-5430-82cf-a21c2451ebbb`<br>`6a0b9950-1b65-5430-82cf-a21c2451ebbb` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | #4 / 0.068865 | #7 / 2.591039 | #5 / 0.194444 | #8 / 0.210938 | fail | no | none |
| `2de77c06-07f9-5de3-ace0-116bce59fa7d`<br>`2de77c06-07f9-5de3-ace0-116bce59fa7d` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #5 / 0.063562 | #12 / 0.005230 | #9 / 0.158824 | #9 / 0.208984 | fail | no | none |
| `ed110340-2272-5935-843c-391a6a657a01`<br>`ed110340-2272-5935-843c-391a6a657a01` | `family.fire.drills`<br>`doc.fire.drills.v1` | #12 / 0.004810 | #6 / 2.615249 | #12 / 0.149733 | #10 / 0.207031 | fail | no | none |
| `a5f14edc-43c1-589d-a639-36fee9e5f46a`<br>`a5f14edc-43c1-589d-a639-36fee9e5f46a` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #9 / 0.041837 | #5 / 2.865427 | #8 / 0.171429 | #11 / 0.202148 | fail | no | none |
| `d76479fb-dc26-5b4a-9f69-c00aa59cfd06`<br>`d76479fb-dc26-5b4a-9f69-c00aa59cfd06` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #3 / 0.080613 | #10 / 0.101617 | #6 / 0.191667 | #12 / 0.202148 | fail | no | none |
| `2228856a-e242-5d14-bf7a-609592eb08b4`<br>`2228856a-e242-5d14-bf7a-609592eb08b4` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v1` | #13 / -0.009606 | — | #13 / 0.055556 | #13 / 0.193359 | fail | no | none |

### `v3.medication.compare.controlled-drugs-discrepancy` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Compare controlled-drug discrepancy reporting in version 1 with the current procedure.
- Covered EvidenceUnits: `evidence.v3.engineering.medication.controlled-drugs-compare.v1, evidence.v3.engineering.medication.controlled-drugs-compare.current`
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
      "Compare controlled-drug discrepancy reporting in version 1 with the current procedure."
    ],
    "temporal_mode": "COMPARE",
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
      "Compare controlled-drug discrepancy reporting in version 1 with the current procedure."
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "version 1"
    }
  }
}
```

  - COMPARISON: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.medication.controlled-drugs-compare.current` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v2` | documents/medication/controlled-drugs-v2.md |
| COMPARISON | `evidence.v3.engineering.medication.controlled-drugs-compare.v1` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v1` | documents/medication/controlled-drugs-v1.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=7 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #1 / 0.447670 | #1 / 20.153590 | #1 / 0.333333 | #1 / 0.628906 | pass | yes | evidence.v3.engineering.medication.controlled-drugs-compare.current |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.408429 | #3 / 7.345275 | #2 / 0.267857 | #2 / 0.482422 | pass | yes | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.342305 | — | #10 / 0.125000 | #3 / 0.451172 | pass | yes | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #4 / 0.295276 | #12 / 5.707755 | #4 / 0.169935 | #4 / 0.396484 | pass | yes | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #5 / 0.292197 | #19 / 5.222601 | #6 / 0.141667 | #5 / 0.388672 | pass | yes | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #7 / 0.268954 | #23 / 4.845325 | #11 / 0.119048 | #6 / 0.357422 | pass | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #6 / 0.288874 | #8 / 6.376999 | #5 / 0.167832 | #7 / 0.349609 | pass | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #10 / 0.214813 | #17 / 5.279617 | #14 / 0.112121 | #8 / 0.312500 | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #18 / 0.187561 | #2 / 8.799280 | #3 / 0.186335 | #9 / 0.283203 | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #26 / 0.166835 | #7 / 6.428260 | #12 / 0.115591 | #10 / 0.277344 | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #28 / 0.163176 | #5 / 7.033410 | #9 / 0.130303 | #11 / 0.269531 | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #12 / 0.211475 | #15 / 5.418960 | #15 / 0.108824 | #12 / 0.248047 | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #19 / 0.185779 | #9 / 6.353512 | #13 / 0.113095 | #13 / 0.245117 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #17 / 0.189934 | #6 / 6.529242 | #7 / 0.136364 | #14 / 0.238281 | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #39 / 0.122356 | #4 / 7.035490 | #8 / 0.133838 | #15 / 0.221680 | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #8 / 0.259241 | — | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #9 / 0.242353 | — | — | — | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #11 / 0.211654 | #26 / 4.441691 | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #13 / 0.196671 | #36 / 3.660005 | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #14 / 0.191985 | — | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #15 / 0.191771 | #21 / 5.076902 | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #16 / 0.191590 | #27 / 4.429629 | — | — | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #20 / 0.185074 | — | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #21 / 0.183333 | #10 / 6.296379 | — | — | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #22 / 0.178720 | — | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #23 / 0.177316 | #39 / 3.576332 | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #24 / 0.176701 | — | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #25 / 0.172739 | — | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #27 / 0.163910 | #25 / 4.568728 | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #29 / 0.162846 | #29 / 4.222308 | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #30 / 0.151094 | — | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #31 / 0.149422 | #24 / 4.588405 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #32 / 0.148716 | #20 / 5.183832 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #33 / 0.142567 | #33 / 3.909002 | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #34 / 0.138250 | — | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #35 / 0.136697 | #14 / 5.423994 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #36 / 0.129804 | #30 / 4.185777 | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #37 / 0.126379 | — | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #38 / 0.125295 | #35 / 3.697661 | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #40 / 0.120676 | — | — | — | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | — | #11 / 5.846307 | — | — | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | — | #13 / 5.520145 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | — | #16 / 5.346806 | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #18 / 5.279175 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #22 / 5.027548 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #28 / 4.259549 | — | — | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | — | #31 / 4.089156 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #32 / 4.042715 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #34 / 3.715796 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #37 / 3.633208 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #38 / 3.596728 | — | — | fail | no | none |
| `016e8751-5c0c-58b9-8695-c190270b5921`<br>`016e8751-5c0c-58b9-8695-c190270b5921` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | — | #40 / 3.566591 | — | — | fail | no | none |

#### COMPARISON

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=3 → Final evidence=3

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `dee03403-128d-556b-bb3e-469857e808fd`<br>`dee03403-128d-556b-bb3e-469857e808fd` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #1 / 0.421421 | #1 / 21.765951 | #1 / 0.333333 | #1 / 0.679688 | pass | yes | evidence.v3.engineering.medication.controlled-drugs-compare.v1 |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #2 / 0.295276 | #10 / 5.707755 | #2 / 0.209524 | #2 / 0.396484 | pass | yes | none |
| `c4979314-9ca2-573f-a219-57ab4773ad1f`<br>`c4979314-9ca2-573f-a219-57ab4773ad1f` | `family.medication.administration`<br>`doc.medication.administration.v1` | #4 / 0.262313 | #11 / 5.581377 | #4 / 0.173611 | #3 / 0.361328 | pass | yes | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #7 / 0.214813 | #16 / 5.279617 | #9 / 0.130952 | #4 / 0.312500 | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #8 / 0.211654 | #22 / 4.441691 | #14 / 0.113960 | #5 / 0.292969 | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #20 / 0.166835 | #6 / 6.428260 | #10 / 0.130909 | #6 / 0.277344 | fail | no | none |
| `2de77c06-07f9-5de3-ace0-116bce59fa7d`<br>`2de77c06-07f9-5de3-ace0-116bce59fa7d` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #3 / 0.269389 | — | #11 / 0.125000 | #7 / 0.275391 | fail | no | none |
| `2dcdbc13-00b3-5e91-997b-19e37ff1c84d`<br>`2dcdbc13-00b3-5e91-997b-19e37ff1c84d` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #18 / 0.181230 | #2 / 9.113444 | #3 / 0.186335 | #8 / 0.271484 | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #23 / 0.163176 | #4 / 7.033410 | #7 / 0.146825 | #9 / 0.269531 | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #9 / 0.211475 | #14 / 5.418960 | #12 / 0.124060 | #10 / 0.248047 | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #15 / 0.185779 | #7 / 6.353512 | #8 / 0.133333 | #11 / 0.245117 | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #12 / 0.191771 | #19 / 5.076902 | #15 / 0.100490 | #12 / 0.242188 | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #17 / 0.183333 | #8 / 6.296379 | #13 / 0.122378 | #13 / 0.242188 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #14 / 0.189934 | #5 / 6.529242 | #5 / 0.152632 | #14 / 0.238281 | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #35 / 0.122356 | #3 / 7.035490 | #6 / 0.150000 | #15 / 0.221680 | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #5 / 0.259241 | — | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #6 / 0.242353 | — | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #10 / 0.196671 | #36 / 3.660005 | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #11 / 0.191985 | — | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #13 / 0.191590 | #23 / 4.429629 | — | — | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #16 / 0.185074 | — | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #19 / 0.172739 | — | — | — | fail | no | none |
| `19700c62-cb1e-5c51-a9cf-8cce818fe9d2`<br>`19700c62-cb1e-5c51-a9cf-8cce818fe9d2` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #21 / 0.164517 | — | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #22 / 0.163910 | #21 / 4.568728 | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #24 / 0.162846 | #25 / 4.222308 | — | — | fail | no | none |
| `d76479fb-dc26-5b4a-9f69-c00aa59cfd06`<br>`d76479fb-dc26-5b4a-9f69-c00aa59cfd06` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #25 / 0.159155 | #26 / 4.221721 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #26 / 0.149422 | #20 / 4.588405 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #27 / 0.142567 | #32 / 3.909002 | — | — | fail | no | none |
| `ed110340-2272-5935-843c-391a6a657a01`<br>`ed110340-2272-5935-843c-391a6a657a01` | `family.fire.drills`<br>`doc.fire.drills.v1` | #28 / 0.141454 | #33 / 3.844549 | — | — | fail | no | none |
| `a211aa74-052b-50af-909f-b876d7a840e7`<br>`a211aa74-052b-50af-909f-b876d7a840e7` | `family.infection.outbreak-management`<br>`doc.infection.outbreak-management.v1` | #29 / 0.139271 | #12 / 5.538177 | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #30 / 0.136697 | #13 / 5.423994 | — | — | fail | no | none |
| `64df7c22-d350-5124-b01b-770fb0793050`<br>`64df7c22-d350-5124-b01b-770fb0793050` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #31 / 0.135787 | #30 / 4.077758 | — | — | fail | no | none |
| `a5f14edc-43c1-589d-a639-36fee9e5f46a`<br>`a5f14edc-43c1-589d-a639-36fee9e5f46a` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #32 / 0.131376 | #34 / 3.794491 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #33 / 0.129804 | #28 / 4.185777 | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #34 / 0.126379 | — | — | — | fail | no | none |
| `886fc5bc-416a-5ed9-9de6-5631b45c167d`<br>`886fc5bc-416a-5ed9-9de6-5631b45c167d` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #36 / 0.120880 | — | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #37 / 0.120676 | — | — | — | fail | no | none |
| `016e8751-5c0c-58b9-8695-c190270b5921`<br>`016e8751-5c0c-58b9-8695-c190270b5921` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | #38 / 0.118984 | #39 / 3.566591 | — | — | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #39 / 0.116038 | #9 / 5.846307 | — | — | fail | no | none |
| `33321467-60b1-5a2d-8a8d-3779711290aa`<br>`33321467-60b1-5a2d-8a8d-3779711290aa` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #40 / 0.113092 | #27 / 4.187834 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | — | #15 / 5.346806 | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #17 / 5.279175 | — | — | fail | no | none |
| `6a0b9950-1b65-5430-82cf-a21c2451ebbb`<br>`6a0b9950-1b65-5430-82cf-a21c2451ebbb` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | — | #18 / 5.155345 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #24 / 4.259549 | — | — | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | — | #29 / 4.089156 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #31 / 4.042715 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #35 / 3.715796 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #37 / 3.633208 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #38 / 3.596728 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #40 / 3.536392 | — | — | fail | no | none |

### `v3.medication.compare.controlled-drugs-discrepancy` / `versioned`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What changed between version 1 and the current controlled-drugs procedure when the stock count is wrong?
- Covered EvidenceUnits: `evidence.v3.engineering.medication.controlled-drugs-compare.v1, evidence.v3.engineering.medication.controlled-drugs-compare.current`
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
      "What changed between version 1 and the current controlled-drugs procedure when the stock count is wrong?"
    ],
    "temporal_mode": "COMPARE",
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
      "What changed between version 1 and the current controlled-drugs procedure when the stock count is wrong?"
    ],
    "temporal_mode": "COMPARE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "version 1"
    }
  }
}
```

  - COMPARISON: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.medication.controlled-drugs-compare.current` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v2` | documents/medication/controlled-drugs-v2.md |
| COMPARISON | `evidence.v3.engineering.medication.controlled-drugs-compare.v1` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v1` | documents/medication/controlled-drugs-v1.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=6 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #1 / 0.510430 | #1 / 15.577112 | #1 / 0.333333 | #1 / 0.625000 | pass | yes | evidence.v3.engineering.medication.controlled-drugs-compare.current |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.442802 | #2 / 10.197851 | #2 / 0.285714 | #2 / 0.464844 | pass | yes | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.411820 | — | #11 / 0.125000 | #3 / 0.447266 | pass | yes | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #4 / 0.360563 | #8 / 5.914062 | #4 / 0.188034 | #4 / 0.390625 | pass | yes | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #5 / 0.358582 | #13 / 5.500750 | #6 / 0.155556 | #5 / 0.365234 | pass | yes | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #7 / 0.291659 | #9 / 5.833878 | #7 / 0.154762 | #6 / 0.337891 | pass | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #6 / 0.310532 | #3 / 6.761801 | #3 / 0.215909 | #7 / 0.330078 | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #10 / 0.281336 | #4 / 6.605886 | #5 / 0.177778 | #8 / 0.320312 | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #14 / 0.210224 | #6 / 6.221859 | #8 / 0.143541 | #9 / 0.279297 | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #11 / 0.266096 | #24 / 4.356703 | #14 / 0.096983 | #10 / 0.255859 | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #13 / 0.243512 | #32 / 3.683145 | #15 / 0.082583 | #11 / 0.242188 | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #18 / 0.196380 | #12 / 5.519489 | #13 / 0.102302 | #12 / 0.232422 | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #16 / 0.198725 | #7 / 6.005309 | #10 / 0.130952 | #13 / 0.228516 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #15 / 0.199854 | #10 / 5.711490 | #12 / 0.116667 | #14 / 0.222656 | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #26 / 0.163287 | #5 / 6.235351 | #9 / 0.132258 | #15 / 0.218750 | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #8 / 0.290845 | — | — | — | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #9 / 0.285073 | — | — | — | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #12 / 0.244570 | — | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #17 / 0.197676 | #34 / 3.634803 | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #19 / 0.191988 | #36 / 3.598823 | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #20 / 0.179029 | #19 / 4.776962 | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #21 / 0.177294 | — | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #22 / 0.176291 | #18 / 4.924320 | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #23 / 0.171889 | #38 / 3.582683 | — | — | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #24 / 0.168389 | #25 / 4.282221 | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #25 / 0.166953 | — | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #27 / 0.163000 | #21 / 4.458021 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #28 / 0.159903 | #30 / 3.875169 | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #29 / 0.159224 | #33 / 3.662344 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #30 / 0.157812 | #29 / 3.911979 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #31 / 0.157501 | #16 / 5.041895 | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #32 / 0.156590 | — | — | — | fail | no | none |
| `016e8751-5c0c-58b9-8695-c190270b5921`<br>`016e8751-5c0c-58b9-8695-c190270b5921` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | #33 / 0.154533 | — | — | — | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #34 / 0.152446 | — | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #35 / 0.149617 | — | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #36 / 0.148426 | #22 / 4.407534 | — | — | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #37 / 0.145517 | #27 / 4.036128 | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #38 / 0.141720 | #23 / 4.379722 | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #39 / 0.138901 | #40 / 3.540822 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #40 / 0.138272 | #17 / 5.001659 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #11 / 5.533198 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | — | #14 / 5.242062 | — | — | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | — | #15 / 5.046372 | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #20 / 4.516105 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #26 / 4.271914 | — | — | fail | no | none |
| `e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f`<br>`e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | — | #28 / 3.950201 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #31 / 3.718497 | — | — | fail | no | none |
| `5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305`<br>`5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | — | #35 / 3.606840 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #37 / 3.598216 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #39 / 3.576703 | — | — | fail | no | none |

#### COMPARISON

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=2 → Final evidence=2

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `dee03403-128d-556b-bb3e-469857e808fd`<br>`dee03403-128d-556b-bb3e-469857e808fd` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #1 / 0.506851 | #1 / 16.061770 | #1 / 0.333333 | #1 / 0.550781 | pass | yes | evidence.v3.engineering.medication.controlled-drugs-compare.v1 |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #4 / 0.291659 | #7 / 5.833878 | #4 / 0.194444 | #2 / 0.337891 | pass | yes | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #7 / 0.281336 | #2 / 6.605886 | #3 / 0.226190 | #3 / 0.320312 | fail | no | none |
| `c4979314-9ca2-573f-a219-57ab4773ad1f`<br>`c4979314-9ca2-573f-a219-57ab4773ad1f` | `family.medication.administration`<br>`doc.medication.administration.v1` | #2 / 0.330515 | #6 / 5.846618 | #2 / 0.233766 | #4 / 0.318359 | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #5 / 0.290845 | — | #12 / 0.100000 | #5 / 0.289062 | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #11 / 0.210224 | #4 / 6.221859 | #5 / 0.173611 | #6 / 0.279297 | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #8 / 0.266096 | #21 / 4.356703 | #11 / 0.115385 | #7 / 0.255859 | fail | no | none |
| `2de77c06-07f9-5de3-ace0-116bce59fa7d`<br>`2de77c06-07f9-5de3-ace0-116bce59fa7d` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #3 / 0.298179 | #31 / 3.829846 | #8 / 0.152778 | #8 / 0.248047 | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #9 / 0.243512 | #34 / 3.683145 | #13 / 0.097070 | #9 / 0.242188 | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #15 / 0.196380 | #10 / 5.519489 | #10 / 0.116667 | #10 / 0.232422 | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #13 / 0.198725 | #5 / 6.005309 | #7 / 0.155556 | #11 / 0.228516 | fail | no | none |
| `a211aa74-052b-50af-909f-b876d7a840e7`<br>`a211aa74-052b-50af-909f-b876d7a840e7` | `family.infection.outbreak-management`<br>`doc.infection.outbreak-management.v1` | #26 / 0.155720 | #11 / 5.269549 | #15 / 0.094758 | #12 / 0.223633 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #12 / 0.199854 | #8 / 5.711490 | #9 / 0.135747 | #13 / 0.222656 | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #17 / 0.179029 | #15 / 4.776962 | #14 / 0.095455 | #14 / 0.222656 | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #19 / 0.163287 | #3 / 6.235351 | #6 / 0.166667 | #15 / 0.218750 | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #6 / 0.285073 | — | — | — | fail | no | none |
| `19700c62-cb1e-5c51-a9cf-8cce818fe9d2`<br>`19700c62-cb1e-5c51-a9cf-8cce818fe9d2` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #10 / 0.210959 | — | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #14 / 0.197676 | #35 / 3.634803 | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #16 / 0.191988 | #37 / 3.598823 | — | — | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #18 / 0.168389 | #22 / 4.282221 | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #20 / 0.163000 | #18 / 4.458021 | — | — | fail | no | none |
| `2dcdbc13-00b3-5e91-997b-19e37ff1c84d`<br>`2dcdbc13-00b3-5e91-997b-19e37ff1c84d` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #21 / 0.162965 | #14 / 4.963091 | — | — | fail | no | none |
| `a5f14edc-43c1-589d-a639-36fee9e5f46a`<br>`a5f14edc-43c1-589d-a639-36fee9e5f46a` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #22 / 0.161698 | #26 / 3.985494 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #23 / 0.159903 | #29 / 3.875169 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #24 / 0.157812 | #28 / 3.911979 | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #25 / 0.156590 | — | — | — | fail | no | none |
| `016e8751-5c0c-58b9-8695-c190270b5921`<br>`016e8751-5c0c-58b9-8695-c190270b5921` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | #27 / 0.154533 | — | — | — | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #28 / 0.152446 | — | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #29 / 0.149617 | — | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #30 / 0.148426 | #19 / 4.407534 | — | — | fail | no | none |
| `d76479fb-dc26-5b4a-9f69-c00aa59cfd06`<br>`d76479fb-dc26-5b4a-9f69-c00aa59cfd06` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #31 / 0.147168 | #32 / 3.778538 | — | — | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #32 / 0.145517 | #25 / 4.036128 | — | — | fail | no | none |
| `ed110340-2272-5935-843c-391a6a657a01`<br>`ed110340-2272-5935-843c-391a6a657a01` | `family.fire.drills`<br>`doc.fire.drills.v1` | #33 / 0.145346 | #40 / 3.460560 | — | — | fail | no | none |
| `64df7c22-d350-5124-b01b-770fb0793050`<br>`64df7c22-d350-5124-b01b-770fb0793050` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #34 / 0.144890 | #30 / 3.833507 | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #35 / 0.141720 | #20 / 4.379722 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #36 / 0.138272 | #13 / 5.001659 | — | — | fail | no | none |
| `33321467-60b1-5a2d-8a8d-3779711290aa`<br>`33321467-60b1-5a2d-8a8d-3779711290aa` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #37 / 0.126347 | #24 / 4.089423 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | #38 / 0.125902 | — | — | — | fail | no | none |
| `886fc5bc-416a-5ed9-9de6-5631b45c167d`<br>`886fc5bc-416a-5ed9-9de6-5631b45c167d` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #39 / 0.119000 | — | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #40 / 0.118779 | — | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #9 / 5.533198 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | — | #12 / 5.242062 | — | — | fail | no | none |
| `6a0b9950-1b65-5430-82cf-a21c2451ebbb`<br>`6a0b9950-1b65-5430-82cf-a21c2451ebbb` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | — | #16 / 4.713222 | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #17 / 4.516105 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #23 / 4.271914 | — | — | fail | no | none |
| `e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f`<br>`e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | — | #27 / 3.950201 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #33 / 3.718497 | — | — | fail | no | none |
| `5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305`<br>`5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | — | #36 / 3.606840 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #38 / 3.598216 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #39 / 3.576703 | — | — | fail | no | none |

### `v3.medication.current.controlled-drugs-discrepancy` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: The CD count is wrong — what do we do straight away?
- Covered EvidenceUnits: `evidence.v3.engineering.medication.cd.immediate.escalation`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.medication.cd.immediate.escalation` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v2` | documents/medication/controlled-drugs-v2.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=4 → Final evidence=4

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #1 / 0.264648 | #20 / 1.382044 | #3 / 0.206667 | #1 / 0.691406 | pass | yes | evidence.v3.engineering.medication.cd.immediate.escalation |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.233244 | #1 / 4.415714 | #1 / 0.291667 | #2 / 0.394531 | pass | yes | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.243746 | #2 / 3.130529 | #2 / 0.285714 | #3 / 0.353516 | pass | yes | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #14 / 0.142467 | #13 / 1.566789 | #12 / 0.108187 | #4 / 0.349609 | pass | yes | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #15 / 0.140304 | #18 / 1.402228 | #15 / 0.093478 | #5 / 0.333984 | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #5 / 0.177549 | #30 / 1.122506 | #7 / 0.128571 | #6 / 0.332031 | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #4 / 0.207405 | — | #11 / 0.111111 | #7 / 0.320312 | fail | no | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.164283 | #4 / 3.002718 | #4 / 0.202020 | #8 / 0.314453 | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #13 / 0.143700 | #7 / 2.082277 | #6 / 0.138889 | #9 / 0.302734 | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #11 / 0.149661 | #14 / 1.539577 | #10 / 0.115132 | #10 / 0.302734 | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #17 / 0.129746 | #9 / 1.950347 | #9 / 0.116883 | #11 / 0.300781 | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #35 / 0.099651 | #3 / 3.027430 | #5 / 0.150000 | #12 / 0.275391 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #30 / 0.102667 | #6 / 2.088047 | #8 / 0.119481 | #13 / 0.269531 | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #29 / 0.102935 | #10 / 1.871284 | #14 / 0.096078 | #14 / 0.255859 | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #5 / 2.499157 | #13 / 0.100000 | #15 / 0.207031 | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #7 / 0.155225 | — | — | — | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #8 / 0.152206 | — | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #9 / 0.151437 | — | — | — | fail | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #10 / 0.150150 | — | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #12 / 0.143949 | — | — | — | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #16 / 0.133783 | #28 / 1.159653 | — | — | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #18 / 0.129675 | — | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #19 / 0.127512 | #21 / 1.368593 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #20 / 0.122619 | #16 / 1.486465 | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #21 / 0.121359 | #22 / 1.354823 | — | — | fail | no | none |
| `980e0701-e200-52b6-aa4d-4f11701cedc8`<br>`980e0701-e200-52b6-aa4d-4f11701cedc8` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | #22 / 0.119740 | — | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #23 / 0.116195 | #34 / 1.063556 | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #24 / 0.111842 | #17 / 1.468049 | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #25 / 0.110501 | — | — | — | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #26 / 0.109662 | — | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #27 / 0.106552 | #38 / 0.954371 | — | — | fail | no | none |
| `e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f`<br>`e5c536e1-5b9a-5c01-b72e-9c8dfb7f9c9f` | `family.payroll.pension`<br>`doc.payroll.pension.v1` | #28 / 0.104786 | — | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #31 / 0.102334 | #40 / 0.899861 | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #32 / 0.101940 | — | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #33 / 0.101115 | #37 / 1.010540 | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #34 / 0.100629 | — | — | — | fail | no | none |
| `8aa6fad2-b29c-5376-8583-c09ad8bcdf41`<br>`8aa6fad2-b29c-5376-8583-c09ad8bcdf41` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #36 / 0.097634 | — | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #37 / 0.094722 | — | — | — | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #38 / 0.094694 | #32 / 1.088547 | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #39 / 0.094336 | #27 / 1.248026 | — | — | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #40 / 0.091303 | — | — | — | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | — | #8 / 1.966694 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | — | #11 / 1.752062 | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | — | #12 / 1.636159 | — | — | fail | no | none |
| `7e5de72c-2361-5b0f-8b2b-25512843e880`<br>`7e5de72c-2361-5b0f-8b2b-25512843e880` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #15 / 1.520198 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #19 / 1.390227 | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | — | #23 / 1.280957 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #24 / 1.259747 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #25 / 1.258449 | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | — | #26 / 1.255017 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #29 / 1.143965 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #31 / 1.114332 | — | — | fail | no | none |
| `97dc7b1e-2382-510e-be9d-bc33279603c9`<br>`97dc7b1e-2382-510e-be9d-bc33279603c9` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | — | #33 / 1.083140 | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | — | #35 / 1.057711 | — | — | fail | no | none |
| `016e8751-5c0c-58b9-8695-c190270b5921`<br>`016e8751-5c0c-58b9-8695-c190270b5921` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | — | #36 / 1.029112 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | — | #39 / 0.900269 | — | — | fail | no | none |

### `v3.medication.current.controlled-drugs-discrepancy` / `contrast`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Can a controlled drugs stock mismatch wait until shift end?
- Covered EvidenceUnits: `evidence.v3.engineering.medication.cd.immediate.escalation`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.medication.cd.immediate.escalation` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v2` | documents/medication/controlled-drugs-v2.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=4 → Final evidence=4

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #1 / 0.504482 | #1 / 19.077290 | #1 / 0.333333 | #1 / 0.828125 | pass | yes | evidence.v3.engineering.medication.cd.immediate.escalation |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.466629 | #4 / 8.352658 | #3 / 0.253968 | #2 / 0.531250 | pass | yes | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.466222 | #2 / 9.390614 | #2 / 0.267857 | #3 / 0.492188 | pass | yes | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #4 / 0.393905 | #6 / 7.051151 | #4 / 0.202020 | #4 / 0.402344 | pass | yes | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #5 / 0.369130 | #9 / 6.333933 | #5 / 0.171429 | #5 / 0.335938 | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #7 / 0.326558 | #25 / 3.018990 | #12 / 0.116667 | #6 / 0.332031 | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #27 / 0.193852 | #3 / 8.759748 | #7 / 0.156250 | #7 / 0.277344 | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #10 / 0.271581 | #10 / 6.151975 | #9 / 0.133333 | #8 / 0.275391 | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #6 / 0.330349 | #19 / 3.932190 | #10 / 0.132576 | #9 / 0.271484 | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #8 / 0.298267 | #15 / 4.484695 | #11 / 0.126923 | #10 / 0.257812 | fail | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #9 / 0.293643 | #5 / 8.287170 | #6 / 0.171429 | #11 / 0.253906 | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #17 / 0.222832 | #11 / 5.913354 | #13 / 0.107955 | #12 / 0.208984 | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #12 / 0.250297 | #8 / 6.832906 | #8 / 0.135747 | #13 / 0.202148 | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #11 / 0.257439 | #29 / 2.477909 | #15 / 0.091912 | #14 / 0.172852 | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #36 / 0.156071 | #7 / 6.978479 | #14 / 0.107724 | #15 / 0.163086 | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #13 / 0.248767 | #30 / 2.371117 | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #14 / 0.246626 | #22 / 3.387697 | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #15 / 0.241036 | — | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #16 / 0.230983 | — | — | — | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #18 / 0.219351 | #27 / 2.753316 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #19 / 0.210529 | #16 / 4.472342 | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #20 / 0.210068 | #18 / 3.964851 | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #21 / 0.209264 | — | — | — | fail | no | none |
| `8aa6fad2-b29c-5376-8583-c09ad8bcdf41`<br>`8aa6fad2-b29c-5376-8583-c09ad8bcdf41` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #22 / 0.208297 | #14 / 4.928265 | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #23 / 0.208235 | #26 / 2.949847 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #24 / 0.201221 | #28 / 2.726735 | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #25 / 0.195556 | #34 / 1.962578 | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #26 / 0.194467 | — | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #28 / 0.189427 | #20 / 3.876951 | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #29 / 0.173783 | — | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #30 / 0.169111 | #40 / 1.634799 | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #31 / 0.166999 | #13 / 5.009995 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #32 / 0.166578 | — | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #33 / 0.161411 | #33 / 2.249981 | — | — | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #34 / 0.159150 | — | — | — | fail | no | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | #35 / 0.157265 | #23 / 3.360042 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #37 / 0.146207 | — | — | — | fail | no | none |
| `d695dc92-a368-534e-b544-152e640ebdd9`<br>`d695dc92-a368-534e-b544-152e640ebdd9` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #38 / 0.145893 | #39 / 1.639627 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #39 / 0.142919 | #21 / 3.728942 | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #40 / 0.142447 | — | — | — | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | — | #12 / 5.527693 | — | — | fail | no | none |
| `dfe7812d-2b92-54c4-916e-85a94e0a731a`<br>`dfe7812d-2b92-54c4-916e-85a94e0a731a` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | — | #17 / 4.146173 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #24 / 3.300721 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #31 / 2.324961 | — | — | fail | no | none |
| `7e5de72c-2361-5b0f-8b2b-25512843e880`<br>`7e5de72c-2361-5b0f-8b2b-25512843e880` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #32 / 2.259210 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #35 / 1.954988 | — | — | fail | no | none |
| `aead6f19-4c74-555f-9c5b-f86711197db5`<br>`aead6f19-4c74-555f-9c5b-f86711197db5` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #36 / 1.926976 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #37 / 1.867129 | — | — | fail | no | none |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | — | #38 / 1.811678 | — | — | fail | no | none |

### `v3.medication.current.controlled-drugs-discrepancy` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When must a controlled-drug discrepancy be escalated now?
- Covered EvidenceUnits: `evidence.v3.engineering.medication.cd.immediate.escalation`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.medication.cd.immediate.escalation` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v2` | documents/medication/controlled-drugs-v2.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=8 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #1 / 0.535649 | #1 / 19.771893 | #1 / 0.333333 | #1 / 0.910156 | pass | yes | evidence.v3.engineering.medication.cd.immediate.escalation |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.526505 | #4 / 5.110691 | #2 / 0.253968 | #2 / 0.605469 | pass | yes | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.471860 | — | #7 / 0.125000 | #3 / 0.574219 | pass | yes | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #4 / 0.371169 | #13 / 3.867911 | #5 / 0.166667 | #4 / 0.478516 | pass | yes | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.362996 | #6 / 4.720240 | #4 / 0.181818 | #5 / 0.414062 | pass | yes | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #5 / 0.367080 | #2 / 6.556073 | #3 / 0.242857 | #6 / 0.359375 | pass | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #9 / 0.314459 | #29 / 3.199369 | #12 / 0.100840 | #7 / 0.359375 | pass | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #8 / 0.331194 | #33 / 2.872291 | #9 / 0.103239 | #8 / 0.339844 | pass | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #39 / 0.195920 | #3 / 5.187457 | #6 / 0.147727 | #9 / 0.285156 | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #40 / 0.194454 | #8 / 4.298670 | #14 / 0.099145 | #10 / 0.267578 | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #24 / 0.236930 | #10 / 4.126003 | #10 / 0.101149 | #11 / 0.261719 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #29 / 0.230475 | #9 / 4.161102 | #11 / 0.100840 | #12 / 0.215820 | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #16 / 0.265841 | #16 / 3.769833 | #15 / 0.095238 | #13 / 0.210938 | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #38 / 0.203530 | #7 / 4.374981 | #8 / 0.106589 | #14 / 0.195312 | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | — | #5 / 4.720619 | #13 / 0.100000 | #15 / 0.177734 | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #7 / 0.361598 | — | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #10 / 0.291160 | #32 / 2.916918 | — | — | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #11 / 0.289035 | #37 / 2.588993 | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #12 / 0.272371 | — | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #13 / 0.269039 | — | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #14 / 0.268661 | #25 / 3.441593 | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #15 / 0.267869 | — | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #17 / 0.259603 | #21 / 3.608008 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #18 / 0.258963 | #17 / 3.743018 | — | — | fail | no | none |
| `97dc7b1e-2382-510e-be9d-bc33279603c9`<br>`97dc7b1e-2382-510e-be9d-bc33279603c9` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #19 / 0.257541 | — | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #20 / 0.255631 | #38 / 2.583623 | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #21 / 0.253492 | #26 / 3.370035 | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #22 / 0.248360 | — | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #23 / 0.248208 | — | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #25 / 0.233310 | — | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #26 / 0.232048 | #39 / 2.401705 | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #27 / 0.231605 | #18 / 3.723540 | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #28 / 0.231030 | — | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #30 / 0.228237 | — | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #31 / 0.227708 | #23 / 3.496842 | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #32 / 0.222674 | — | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | #33 / 0.221812 | #11 / 4.088837 | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #34 / 0.220314 | — | — | — | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #35 / 0.213643 | #12 / 3.992094 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #36 / 0.208156 | #20 / 3.651919 | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #37 / 0.204347 | — | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #14 / 3.800647 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #15 / 3.781197 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #19 / 3.658389 | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | — | #22 / 3.562146 | — | — | fail | no | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | — | #24 / 3.464215 | — | — | fail | no | none |
| `7e5de72c-2361-5b0f-8b2b-25512843e880`<br>`7e5de72c-2361-5b0f-8b2b-25512843e880` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #27 / 3.367175 | — | — | fail | no | none |
| `5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305`<br>`5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | — | #28 / 3.316363 | — | — | fail | no | none |
| `d695dc92-a368-534e-b544-152e640ebdd9`<br>`d695dc92-a368-534e-b544-152e640ebdd9` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | — | #30 / 2.978188 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #31 / 2.961423 | — | — | fail | no | none |
| `aead6f19-4c74-555f-9c5b-f86711197db5`<br>`aead6f19-4c74-555f-9c5b-f86711197db5` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #34 / 2.794506 | — | — | fail | no | none |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | — | #35 / 2.736879 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #36 / 2.682829 | — | — | fail | no | none |
| `dfe7812d-2b92-54c4-916e-85a94e0a731a`<br>`dfe7812d-2b92-54c4-916e-85a94e0a731a` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | — | #40 / 2.375908 | — | — | fail | no | none |

### `v3.medication.current.error-form` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What details do I write down after a meds mistake?
- Covered EvidenceUnits: `evidence.v3.engineering.medication.error.form.fields`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.medication.error.form.fields` | `family.medication.errors` | `doc.medication.errors.v1` | documents/medication/medication-error-form.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=9 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #1 / 0.591844 | #1 / 10.455982 | #1 / 0.333333 | #1 / 0.843750 | pass | yes | evidence.v3.engineering.medication.error.form.fields |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.442467 | #12 / 4.495942 | #7 / 0.183824 | #2 / 0.570312 | pass | yes | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #4 / 0.414356 | #8 / 5.566614 | #6 / 0.188034 | #3 / 0.515625 | pass | yes | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #7 / 0.369409 | #3 / 7.871176 | #4 / 0.208333 | #4 / 0.503906 | pass | yes | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.464643 | #4 / 6.956634 | #2 / 0.253968 | #5 / 0.498047 | pass | yes | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.387289 | #5 / 6.386161 | #5 / 0.190909 | #6 / 0.460938 | pass | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #5 / 0.393451 | #7 / 5.652341 | #8 / 0.183333 | #7 / 0.431641 | pass | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #10 / 0.349748 | #27 / 2.091706 | #13 / 0.097917 | #8 / 0.412109 | pass | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #8 / 0.363721 | #2 / 7.914874 | #3 / 0.219780 | #9 / 0.384766 | pass | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #13 / 0.290564 | #9 / 5.345214 | #10 / 0.126984 | #10 / 0.326172 | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #9 / 0.360738 | #13 / 4.385537 | #11 / 0.126984 | #11 / 0.310547 | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #12 / 0.306083 | #6 / 5.811165 | #9 / 0.149733 | #12 / 0.308594 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #18 / 0.244955 | #15 / 3.618893 | #15 / 0.093478 | #13 / 0.283203 | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #16 / 0.252673 | #10 / 5.228799 | #12 / 0.114286 | #14 / 0.263672 | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #19 / 0.234059 | #14 / 4.022443 | #14 / 0.094298 | #15 / 0.263672 | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #11 / 0.337049 | #38 / 1.291375 | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #14 / 0.272707 | #34 / 1.402484 | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #15 / 0.263464 | #30 / 1.817635 | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #17 / 0.251712 | — | — | — | fail | no | none |
| `945c7f18-ad33-59fb-a318-12754178cc65`<br>`945c7f18-ad33-59fb-a318-12754178cc65` | `family.training.fire`<br>`doc.training.fire.v1` | #20 / 0.227978 | — | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #21 / 0.217163 | #28 / 1.990381 | — | — | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #22 / 0.216734 | #23 / 2.480828 | — | — | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | #23 / 0.215947 | — | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #24 / 0.210106 | — | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #25 / 0.206882 | #24 / 2.196322 | — | — | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #26 / 0.199528 | — | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #27 / 0.191273 | #33 / 1.441454 | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #28 / 0.188759 | #22 / 2.524624 | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #29 / 0.187270 | — | — | — | fail | no | none |
| `e6c87ef4-bdc9-5b1c-b1f7-ca27505b1d2f`<br>`e6c87ef4-bdc9-5b1c-b1f7-ca27505b1d2f` | `family.payroll.mileage`<br>`doc.payroll.mileage.v1` | #30 / 0.185594 | — | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #31 / 0.185325 | — | — | — | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #32 / 0.184719 | #31 / 1.531005 | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #33 / 0.184021 | #17 / 3.263759 | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #34 / 0.175194 | #35 / 1.400480 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #35 / 0.174965 | #20 / 2.953557 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #36 / 0.173258 | — | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #37 / 0.172365 | #21 / 2.877897 | — | — | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #38 / 0.169364 | — | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #39 / 0.168786 | — | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #40 / 0.168666 | #11 / 4.616481 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #16 / 3.432953 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #18 / 3.084991 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #19 / 2.957813 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #25 / 2.164574 | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | — | #26 / 2.093948 | — | — | fail | no | none |
| `5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305`<br>`5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | — | #29 / 1.897945 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #32 / 1.442286 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | — | #36 / 1.397659 | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | — | #37 / 1.364590 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | — | #39 / 1.287911 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | — | #40 / 1.209103 | — | — | fail | no | none |

### `v3.medication.current.error-form` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What goes on the medication error form?
- Covered EvidenceUnits: `evidence.v3.engineering.medication.error.form.fields`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.medication.error.form.fields` | `family.medication.errors` | `doc.medication.errors.v1` | documents/medication/medication-error-form.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=8 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #1 / 0.689863 | #1 / 19.625801 | #1 / 0.333333 | #1 / 0.902344 | pass | yes | evidence.v3.engineering.medication.error.form.fields |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #5 / 0.414139 | #13 / 5.154427 | #7 / 0.155556 | #2 / 0.498047 | pass | yes | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #4 / 0.415769 | #3 / 8.968097 | #2 / 0.236111 | #3 / 0.472656 | pass | yes | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.457209 | #7 / 8.148899 | #3 / 0.226190 | #4 / 0.451172 | pass | yes | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #10 / 0.364344 | #4 / 8.819050 | #5 / 0.177778 | #5 / 0.435547 | pass | yes | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #7 / 0.395301 | #2 / 12.431400 | #4 / 0.226190 | #6 / 0.410156 | pass | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #8 / 0.388853 | #10 / 7.620471 | #10 / 0.143590 | #7 / 0.373047 | pass | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #3 / 0.432612 | #14 / 5.084178 | #6 / 0.177632 | #8 / 0.359375 | pass | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #14 / 0.312962 | #6 / 8.591703 | #11 / 0.143541 | #9 / 0.330078 | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #9 / 0.373957 | #8 / 8.027584 | #9 / 0.148352 | #10 / 0.330078 | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #11 / 0.349902 | #11 / 6.682038 | #13 / 0.125000 | #11 / 0.304688 | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #15 / 0.308323 | #5 / 8.775151 | #8 / 0.150000 | #12 / 0.277344 | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #12 / 0.328144 | #15 / 4.859133 | #15 / 0.108824 | #13 / 0.267578 | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #6 / 0.396688 | #17 / 4.383813 | #12 / 0.136364 | #14 / 0.261719 | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #19 / 0.277330 | #9 / 7.756079 | #14 / 0.113095 | #15 / 0.261719 | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #13 / 0.314671 | #16 / 4.518015 | — | — | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #16 / 0.290674 | #20 / 3.943619 | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #17 / 0.285335 | #26 / 1.997652 | — | — | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #18 / 0.282539 | #21 / 3.178040 | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #20 / 0.275228 | #12 / 5.540107 | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #21 / 0.272393 | #33 / 1.618203 | — | — | fail | no | none |
| `945c7f18-ad33-59fb-a318-12754178cc65`<br>`945c7f18-ad33-59fb-a318-12754178cc65` | `family.training.fire`<br>`doc.training.fire.v1` | #22 / 0.271560 | — | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #23 / 0.270744 | #35 / 1.471901 | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #24 / 0.268630 | #22 / 2.359317 | — | — | fail | no | none |
| `e6c87ef4-bdc9-5b1c-b1f7-ca27505b1d2f`<br>`e6c87ef4-bdc9-5b1c-b1f7-ca27505b1d2f` | `family.payroll.mileage`<br>`doc.payroll.mileage.v1` | #25 / 0.267888 | #18 / 4.175503 | — | — | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | #26 / 0.265198 | — | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | #27 / 0.251964 | #19 / 4.125760 | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #28 / 0.250341 | — | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #29 / 0.244060 | — | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #30 / 0.242671 | — | — | — | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #31 / 0.241668 | — | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #32 / 0.233882 | #29 / 1.812903 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | #33 / 0.223430 | — | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #34 / 0.223087 | #30 / 1.738621 | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #35 / 0.222253 | — | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #36 / 0.217841 | — | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #37 / 0.217119 | #31 / 1.734666 | — | — | fail | no | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | #38 / 0.216192 | #40 / 1.344043 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #39 / 0.213061 | #25 / 2.057417 | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #40 / 0.211729 | — | — | — | fail | no | none |
| `980e0701-e200-52b6-aa4d-4f11701cedc8`<br>`980e0701-e200-52b6-aa4d-4f11701cedc8` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | — | #23 / 2.246077 | — | — | fail | no | none |
| `aead6f19-4c74-555f-9c5b-f86711197db5`<br>`aead6f19-4c74-555f-9c5b-f86711197db5` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #24 / 2.074344 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | — | #27 / 1.965973 | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | — | #28 / 1.832880 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #32 / 1.718959 | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | — | #34 / 1.482799 | — | — | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | — | #36 / 1.447143 | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | — | #37 / 1.440035 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | — | #38 / 1.429054 | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #39 / 1.409215 | — | — | fail | no | none |

### `v3.medication.current.error-form` / `priority`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Should I finish the medicines incident form before calling for clinical advice?
- Covered EvidenceUnits: `evidence.v3.engineering.medication.error.form.fields`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.medication.error.form.fields` | `family.medication.errors` | `doc.medication.errors.v1` | documents/medication/medication-error-form.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=9 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #1 / 0.530112 | #1 / 14.730902 | #1 / 0.333333 | #1 / 0.824219 | pass | yes | evidence.v3.engineering.medication.error.form.fields |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.474459 | #2 / 12.347262 | #2 / 0.285714 | #2 / 0.640625 | pass | yes | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #4 / 0.419303 | #12 / 7.108754 | #7 / 0.169935 | #3 / 0.482422 | pass | yes | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #7 / 0.384962 | #3 / 10.729283 | #4 / 0.208333 | #4 / 0.460938 | pass | yes | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #5 / 0.406972 | #4 / 9.707484 | #3 / 0.211111 | #5 / 0.445312 | pass | yes | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.447007 | #8 / 8.276127 | #5 / 0.201923 | #6 / 0.435547 | pass | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #8 / 0.374661 | #5 / 9.192084 | #6 / 0.176923 | #7 / 0.371094 | pass | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #6 / 0.385268 | #11 / 7.160558 | #8 / 0.153409 | #8 / 0.351562 | pass | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #11 / 0.345737 | #27 / 4.927704 | #15 / 0.093750 | #9 / 0.345703 | pass | no | none |
| `97dc7b1e-2382-510e-be9d-bc33279603c9`<br>`97dc7b1e-2382-510e-be9d-bc33279603c9` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #29 / 0.263167 | #7 / 8.750044 | #13 / 0.112745 | #10 / 0.328125 | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #10 / 0.346240 | #13 / 6.943173 | #11 / 0.122222 | #11 / 0.324219 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #9 / 0.358125 | #14 / 6.183732 | #10 / 0.124060 | #12 / 0.324219 | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #15 / 0.306254 | #10 / 7.345437 | #12 / 0.116667 | #13 / 0.324219 | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #12 / 0.333478 | #15 / 6.140487 | #14 / 0.108824 | #14 / 0.292969 | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #17 / 0.303917 | #6 / 9.172562 | #9 / 0.136364 | #15 / 0.287109 | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #13 / 0.325943 | #32 / 3.841107 | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #14 / 0.308086 | #40 / 3.450078 | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #16 / 0.304521 | #17 / 5.965631 | — | — | fail | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #18 / 0.300622 | #26 / 4.982962 | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #19 / 0.292898 | — | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #20 / 0.287409 | #23 / 5.154037 | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #21 / 0.286015 | #16 / 6.133651 | — | — | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #22 / 0.275124 | #33 / 3.832925 | — | — | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #23 / 0.275066 | #25 / 5.031039 | — | — | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #24 / 0.274628 | #38 / 3.655278 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #25 / 0.274559 | #18 / 5.785955 | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #26 / 0.270358 | #36 / 3.754916 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #27 / 0.269707 | — | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #28 / 0.266739 | #30 / 4.580947 | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #30 / 0.257704 | — | — | — | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #31 / 0.254491 | — | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #32 / 0.248924 | #22 / 5.419125 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #33 / 0.248516 | — | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #34 / 0.247631 | — | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #35 / 0.245587 | #31 / 4.203591 | — | — | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #36 / 0.241692 | #39 / 3.651207 | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #37 / 0.236029 | — | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #38 / 0.234844 | — | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #39 / 0.234759 | #19 / 5.752686 | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #40 / 0.233997 | #21 / 5.538192 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #9 / 7.892475 | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | — | #20 / 5.635427 | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | — | #24 / 5.128980 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #28 / 4.807023 | — | — | fail | no | none |
| `980e0701-e200-52b6-aa4d-4f11701cedc8`<br>`980e0701-e200-52b6-aa4d-4f11701cedc8` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | — | #29 / 4.622115 | — | — | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | — | #34 / 3.820560 | — | — | fail | no | none |
| `016e8751-5c0c-58b9-8695-c190270b5921`<br>`016e8751-5c0c-58b9-8695-c190270b5921` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | — | #35 / 3.781827 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | — | #37 / 3.677134 | — | — | fail | no | none |

### `v3.medication.current.prn-prechecks` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: It is on the meds chart as needed — is that enough to give it?
- Covered EvidenceUnits: `evidence.v3.engineering.medication.prn.prechecks`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.medication.prn.prechecks` | `family.medication.prn` | `doc.medication.prn.v1` | documents/medication/prn-protocol.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=10 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #1 / 0.587149 | #3 / 6.130915 | #2 / 0.291667 | #1 / 0.855469 | pass | yes | evidence.v3.engineering.medication.prn.prechecks |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.522757 | #1 / 7.180180 | #1 / 0.309524 | #2 / 0.675781 | pass | yes | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.505387 | #9 / 5.484822 | #5 / 0.196429 | #3 / 0.597656 | pass | yes | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #4 / 0.484708 | #7 / 5.851653 | #6 / 0.194444 | #4 / 0.484375 | pass | yes | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #12 / 0.367138 | #10 / 5.041748 | #12 / 0.125490 | #5 / 0.464844 | pass | yes | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #7 / 0.476891 | #2 / 6.980371 | #3 / 0.226190 | #6 / 0.455078 | pass | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #6 / 0.481223 | #13 / 4.031799 | #9 / 0.146465 | #7 / 0.433594 | pass | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #5 / 0.483057 | #4 / 6.110392 | #4 / 0.211111 | #8 / 0.421875 | pass | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #8 / 0.443089 | #6 / 5.879208 | #7 / 0.167832 | #9 / 0.378906 | pass | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #9 / 0.431849 | #11 / 4.907069 | #11 / 0.133929 | #10 / 0.369141 | pass | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #22 / 0.273400 | #14 / 2.345282 | #14 / 0.089669 | #11 / 0.332031 | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #10 / 0.427132 | #5 / 5.953292 | #8 / 0.166667 | #12 / 0.316406 | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #11 / 0.377821 | #8 / 5.487463 | #10 / 0.139423 | #13 / 0.316406 | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #13 / 0.360940 | #12 / 4.528010 | #13 / 0.114379 | #14 / 0.275391 | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #15 / 0.329182 | #24 / 1.485382 | #15 / 0.084483 | #15 / 0.183594 | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #14 / 0.331858 | #32 / 1.096961 | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #16 / 0.307723 | #38 / 0.837618 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | #17 / 0.300783 | #25 / 1.453284 | — | — | fail | no | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | #18 / 0.299272 | #29 / 1.187701 | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #19 / 0.297753 | — | — | — | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #20 / 0.294460 | #20 / 1.742186 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #21 / 0.291582 | #28 / 1.204129 | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #23 / 0.272018 | — | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #24 / 0.269183 | #27 / 1.217089 | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #25 / 0.267629 | — | — | — | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #26 / 0.261213 | — | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #27 / 0.257812 | #36 / 0.914767 | — | — | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | #28 / 0.255732 | — | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #29 / 0.253992 | #17 / 1.961956 | — | — | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #30 / 0.240971 | — | — | — | fail | no | none |
| `d695dc92-a368-534e-b544-152e640ebdd9`<br>`d695dc92-a368-534e-b544-152e640ebdd9` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #31 / 0.237459 | — | — | — | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #32 / 0.235065 | — | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #33 / 0.233086 | — | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #34 / 0.232737 | — | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #35 / 0.230984 | — | — | — | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #36 / 0.230033 | — | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #37 / 0.228006 | #35 / 0.945144 | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #38 / 0.225586 | — | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #39 / 0.225516 | — | — | — | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | #40 / 0.224949 | — | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | — | #15 / 2.320142 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #16 / 2.281380 | — | — | fail | no | none |
| `980e0701-e200-52b6-aa4d-4f11701cedc8`<br>`980e0701-e200-52b6-aa4d-4f11701cedc8` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | — | #18 / 1.834402 | — | — | fail | no | none |
| `aead6f19-4c74-555f-9c5b-f86711197db5`<br>`aead6f19-4c74-555f-9c5b-f86711197db5` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #19 / 1.819789 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #21 / 1.696271 | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | — | #22 / 1.615080 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #23 / 1.498309 | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | — | #26 / 1.229433 | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | — | #30 / 1.157807 | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #31 / 1.130976 | — | — | fail | no | none |
| `8aa6fad2-b29c-5376-8583-c09ad8bcdf41`<br>`8aa6fad2-b29c-5376-8583-c09ad8bcdf41` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #33 / 1.034819 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #34 / 1.000242 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | — | #37 / 0.909152 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #39 / 0.807278 | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | — | #40 / 0.761185 | — | — | fail | no | none |

### `v3.medication.current.prn-prechecks` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What must I check before giving a PRN medicine?
- Covered EvidenceUnits: `evidence.v3.engineering.medication.prn.prechecks`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.medication.prn.prechecks` | `family.medication.prn` | `doc.medication.prn.v1` | documents/medication/prn-protocol.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=12 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #1 / 0.664430 | #1 / 21.479332 | #1 / 0.333333 | #1 / 0.945312 | pass | yes | evidence.v3.engineering.medication.prn.prechecks |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #3 / 0.528530 | #2 / 13.606075 | #2 / 0.267857 | #2 / 0.828125 | pass | yes | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.598595 | #6 / 10.921230 | #3 / 0.233766 | #3 / 0.824219 | pass | yes | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #4 / 0.525998 | #5 / 11.290424 | #5 / 0.211111 | #4 / 0.539062 | pass | yes | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.519255 | #10 / 7.644630 | #7 / 0.157576 | #5 / 0.535156 | pass | yes | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #5 / 0.524034 | #3 / 13.514707 | #4 / 0.225000 | #6 / 0.531250 | pass | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #7 / 0.495107 | #33 / 3.691545 | #12 / 0.109649 | #7 / 0.500000 | pass | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #12 / 0.378367 | #13 / 6.804403 | #11 / 0.114379 | #8 / 0.494141 | pass | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #10 / 0.437276 | #9 / 8.562525 | #8 / 0.138095 | #9 / 0.482422 | pass | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #11 / 0.418204 | #4 / 11.614460 | #6 / 0.173611 | #10 / 0.402344 | pass | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #8 / 0.443959 | #19 / 5.717970 | #9 / 0.118590 | #11 / 0.396484 | pass | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #9 / 0.438698 | #29 / 4.038135 | #15 / 0.100840 | #12 / 0.343750 | pass | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #36 / 0.229328 | #8 / 8.700461 | #14 / 0.101313 | #13 / 0.316406 | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #25 / 0.285192 | #7 / 9.937598 | #10 / 0.116667 | #14 / 0.294922 | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #18 / 0.302961 | #11 / 7.439464 | #13 / 0.105978 | #15 / 0.250000 | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #13 / 0.342097 | #26 / 4.544027 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #14 / 0.319398 | — | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #15 / 0.317425 | #28 / 4.210032 | — | — | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #16 / 0.313445 | #40 / 2.828361 | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #17 / 0.304179 | — | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #19 / 0.298125 | #25 / 4.605065 | — | — | fail | no | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | #20 / 0.297189 | — | — | — | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #21 / 0.295874 | — | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #22 / 0.295844 | #22 / 5.321926 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #23 / 0.290058 | #20 / 5.582375 | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #24 / 0.286497 | — | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | #26 / 0.277718 | #14 / 6.503729 | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #27 / 0.274521 | — | — | — | fail | no | none |
| `d695dc92-a368-534e-b544-152e640ebdd9`<br>`d695dc92-a368-534e-b544-152e640ebdd9` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #28 / 0.271160 | #12 / 6.949967 | — | — | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #29 / 0.265817 | — | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #30 / 0.264269 | #15 / 6.423474 | — | — | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | #31 / 0.255418 | — | — | — | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #32 / 0.252515 | — | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #33 / 0.250467 | — | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | #34 / 0.250453 | #23 / 5.120100 | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #35 / 0.248161 | #32 / 3.703370 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #37 / 0.228752 | #18 / 5.960594 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #38 / 0.227532 | — | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #39 / 0.225287 | — | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #40 / 0.223173 | #24 / 4.874820 | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | — | #16 / 6.228128 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #17 / 6.163578 | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | — | #21 / 5.381443 | — | — | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | — | #27 / 4.368119 | — | — | fail | no | none |
| `aead6f19-4c74-555f-9c5b-f86711197db5`<br>`aead6f19-4c74-555f-9c5b-f86711197db5` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #30 / 4.021852 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #31 / 4.020131 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | — | #34 / 3.452041 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #35 / 3.433799 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #36 / 3.316762 | — | — | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | — | #37 / 3.087995 | — | — | fail | no | none |
| `7e5de72c-2361-5b0f-8b2b-25512843e880`<br>`7e5de72c-2361-5b0f-8b2b-25512843e880` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #38 / 3.003450 | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | — | #39 / 2.833910 | — | — | fail | no | none |

### `v3.medication.current.prn-prechecks` / `expanded`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What information is needed before giving when-required medication?
- Covered EvidenceUnits: `evidence.v3.engineering.medication.prn.prechecks`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.medication.prn.prechecks` | `family.medication.prn` | `doc.medication.prn.v1` | documents/medication/prn-protocol.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=12 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #1 / 0.624056 | #1 / 11.855199 | #1 / 0.333333 | #1 / 0.886719 | pass | yes | evidence.v3.engineering.medication.prn.prechecks |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #2 / 0.557316 | #3 / 10.267913 | #2 / 0.267857 | #2 / 0.738281 | pass | yes | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #5 / 0.508642 | #6 / 8.076818 | #5 / 0.190909 | #3 / 0.726562 | pass | yes | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #7 / 0.493803 | #22 / 5.451187 | #10 / 0.120370 | #4 / 0.558594 | pass | yes | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #4 / 0.522383 | #5 / 8.810383 | #4 / 0.211111 | #5 / 0.531250 | pass | yes | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #8 / 0.491306 | #4 / 10.179545 | #6 / 0.188034 | #6 / 0.531250 | pass | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #6 / 0.497447 | #11 / 6.882366 | #8 / 0.153409 | #7 / 0.523438 | pass | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #12 / 0.379658 | #13 / 6.677701 | #11 / 0.114379 | #8 / 0.511719 | pass | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #9 / 0.461015 | #7 / 7.959827 | #7 / 0.154762 | #9 / 0.482422 | pass | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #3 / 0.527164 | #2 / 10.554900 | #3 / 0.267857 | #10 / 0.443359 | pass | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #10 / 0.434519 | #16 / 6.144386 | #12 / 0.114286 | #11 / 0.429688 | pass | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #11 / 0.402650 | #9 / 7.695722 | #9 / 0.133929 | #12 / 0.427734 | pass | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #13 / 0.365288 | #18 / 5.947489 | #14 / 0.099034 | #13 / 0.316406 | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #27 / 0.293231 | #8 / 7.772245 | #13 / 0.108173 | #14 / 0.314453 | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #16 / 0.334208 | #19 / 5.839675 | #15 / 0.089286 | #15 / 0.300781 | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #14 / 0.353817 | #38 / 3.022423 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #15 / 0.336688 | #35 / 3.510065 | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #17 / 0.333544 | — | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #18 / 0.332517 | — | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | #19 / 0.329074 | #21 / 5.525013 | — | — | fail | no | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | #20 / 0.327336 | #34 / 3.706245 | — | — | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #21 / 0.323136 | #32 / 3.871645 | — | — | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | #22 / 0.317543 | — | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | #23 / 0.316898 | #27 / 4.194498 | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #24 / 0.307378 | #30 / 3.930816 | — | — | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #25 / 0.296545 | #40 / 2.810954 | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #26 / 0.296185 | — | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #28 / 0.291581 | — | — | — | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #29 / 0.289106 | — | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #30 / 0.286936 | #33 / 3.781449 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | #31 / 0.282802 | #36 / 3.158546 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #32 / 0.282208 | — | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #33 / 0.277591 | #20 / 5.646154 | — | — | fail | no | none |
| `945c7f18-ad33-59fb-a318-12754178cc65`<br>`945c7f18-ad33-59fb-a318-12754178cc65` | `family.training.fire`<br>`doc.training.fire.v1` | #34 / 0.276457 | — | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #35 / 0.275118 | — | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #36 / 0.272326 | — | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #37 / 0.268126 | — | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #38 / 0.262960 | #12 / 6.718309 | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #39 / 0.257437 | #17 / 6.001020 | — | — | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #40 / 0.251297 | — | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | — | #10 / 7.651953 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #14 / 6.452093 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #15 / 6.357799 | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | — | #23 / 5.270827 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | — | #24 / 5.147418 | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | — | #25 / 4.796628 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #26 / 4.386432 | — | — | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | — | #28 / 4.007356 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #29 / 3.939141 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | — | #31 / 3.905888 | — | — | fail | no | none |
| `aead6f19-4c74-555f-9c5b-f86711197db5`<br>`aead6f19-4c74-555f-9c5b-f86711197db5` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #37 / 3.106812 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #39 / 2.866430 | — | — | fail | no | none |

### `v3.medication.historical.controlled-drugs-v1` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What did the old CD procedure say about when to report a stock discrepancy?
- Covered EvidenceUnits: `evidence.v3.medication.historical.controlled-drugs-v1.shift-end`
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
      "What did the old CD procedure say about when to report a stock discrepancy?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "old CD procedure"
    }
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": null,
    "location_references": [],
    "retrieval_queries": [
      "What did the old CD procedure say about when to report a stock discrepancy?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "old CD procedure"
    }
  }
}
```

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.medication.historical.controlled-drugs-v1.shift-end` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v1` | documents/medication/controlled-drugs-v1.md |

#### PRIMARY

Candidate funnel: Dense=13 → Sparse=13 → Unique after RRF=13 → Reranker=13 → Threshold=1 → Final evidence=1

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `dee03403-128d-556b-bb3e-469857e808fd`<br>`dee03403-128d-556b-bb3e-469857e808fd` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #1 / 0.311738 | #1 / 14.770220 | #1 / 0.333333 | #1 / 0.648438 | pass | yes | evidence.v3.medication.historical.controlled-drugs-v1.shift-end |
| `c4979314-9ca2-573f-a219-57ab4773ad1f`<br>`c4979314-9ca2-573f-a219-57ab4773ad1f` | `family.medication.administration`<br>`doc.medication.administration.v1` | #6 / 0.167066 | #11 / 0.556222 | #8 / 0.153409 | #2 / 0.275391 | fail | no | none |
| `2dcdbc13-00b3-5e91-997b-19e37ff1c84d`<br>`2dcdbc13-00b3-5e91-997b-19e37ff1c84d` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #2 / 0.213277 | #2 / 7.062314 | #2 / 0.285714 | #3 / 0.271484 | fail | no | none |
| `886fc5bc-416a-5ed9-9de6-5631b45c167d`<br>`886fc5bc-416a-5ed9-9de6-5631b45c167d` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #9 / 0.140636 | #9 / 1.417550 | #10 / 0.142857 | #4 / 0.263672 | fail | no | none |
| `d76479fb-dc26-5b4a-9f69-c00aa59cfd06`<br>`d76479fb-dc26-5b4a-9f69-c00aa59cfd06` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #3 / 0.186239 | #8 / 2.477252 | #4 / 0.201923 | #5 / 0.263672 | fail | no | none |
| `64df7c22-d350-5124-b01b-770fb0793050`<br>`64df7c22-d350-5124-b01b-770fb0793050` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #5 / 0.170376 | #3 / 4.472796 | #3 / 0.225000 | #6 / 0.228516 | fail | no | none |
| `19700c62-cb1e-5c51-a9cf-8cce818fe9d2`<br>`19700c62-cb1e-5c51-a9cf-8cce818fe9d2` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #10 / 0.140098 | #10 / 0.584373 | #12 / 0.133333 | #7 / 0.218750 | fail | no | none |
| `33321467-60b1-5a2d-8a8d-3779711290aa`<br>`33321467-60b1-5a2d-8a8d-3779711290aa` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #7 / 0.158444 | #4 / 4.427591 | #5 / 0.194444 | #8 / 0.208984 | fail | no | none |
| `ed110340-2272-5935-843c-391a6a657a01`<br>`ed110340-2272-5935-843c-391a6a657a01` | `family.fire.drills`<br>`doc.fire.drills.v1` | #4 / 0.173692 | #7 / 3.319406 | #6 / 0.194444 | #9 / 0.206055 | fail | no | none |
| `2de77c06-07f9-5de3-ace0-116bce59fa7d`<br>`2de77c06-07f9-5de3-ace0-116bce59fa7d` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #8 / 0.151064 | #12 / 0.492203 | #11 / 0.135747 | #10 / 0.202148 | fail | no | none |
| `6a0b9950-1b65-5430-82cf-a21c2451ebbb`<br>`6a0b9950-1b65-5430-82cf-a21c2451ebbb` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | #12 / 0.107279 | #6 / 3.492819 | #9 / 0.149733 | #11 / 0.188477 | fail | no | none |
| `a5f14edc-43c1-589d-a639-36fee9e5f46a`<br>`a5f14edc-43c1-589d-a639-36fee9e5f46a` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #11 / 0.110320 | #5 / 4.132648 | #7 / 0.162500 | #12 / 0.184570 | fail | no | none |
| `2228856a-e242-5d14-bf7a-609592eb08b4`<br>`2228856a-e242-5d14-bf7a-609592eb08b4` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v1` | #13 / 0.023911 | #13 / 0.388980 | #13 / 0.111111 | #13 / 0.176758 | fail | no | none |

### `v3.medication.historical.controlled-drugs-v1` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Under version 1 of the controlled-drugs procedure, when did a discrepancy have to be reported?
- Covered EvidenceUnits: `evidence.v3.medication.historical.controlled-drugs-v1.shift-end`
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
      "Under version 1 of the controlled-drugs procedure, when did a discrepancy have to be reported?"
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
      "Under version 1 of the controlled-drugs procedure, when did a discrepancy have to be reported?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "version 1"
    }
  }
}
```

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.medication.historical.controlled-drugs-v1.shift-end` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v1` | documents/medication/controlled-drugs-v1.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=4 → Final evidence=4

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `dee03403-128d-556b-bb3e-469857e808fd`<br>`dee03403-128d-556b-bb3e-469857e808fd` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #1 / 0.519490 | #1 / 22.439697 | #1 / 0.333333 | #1 / 0.886719 | pass | yes | evidence.v3.medication.historical.controlled-drugs-v1.shift-end |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #4 / 0.337191 | #11 / 6.220686 | #4 / 0.173611 | #2 / 0.400391 | pass | yes | none |
| `c4979314-9ca2-573f-a219-57ab4773ad1f`<br>`c4979314-9ca2-573f-a219-57ab4773ad1f` | `family.medication.administration`<br>`doc.medication.administration.v1` | #3 / 0.337845 | #13 / 5.984204 | #3 / 0.180556 | #3 / 0.384766 | pass | yes | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #6 / 0.323322 | #33 / 4.893353 | #10 / 0.117225 | #4 / 0.347656 | pass | yes | none |
| `2dcdbc13-00b3-5e91-997b-19e37ff1c84d`<br>`2dcdbc13-00b3-5e91-997b-19e37ff1c84d` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #9 / 0.274341 | #2 / 9.205996 | #2 / 0.214286 | #5 / 0.322266 | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #7 / 0.298468 | #38 / 4.748842 | #13 / 0.106589 | #6 / 0.316406 | fail | no | none |
| `2de77c06-07f9-5de3-ace0-116bce59fa7d`<br>`2de77c06-07f9-5de3-ace0-116bce59fa7d` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #2 / 0.339952 | #30 / 5.069355 | #5 / 0.171429 | #7 / 0.304688 | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #13 / 0.260137 | #12 / 6.004299 | #12 / 0.114379 | #8 / 0.304688 | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #24 / 0.228290 | #3 / 7.753019 | #7 / 0.159483 | #9 / 0.296875 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #20 / 0.240910 | #4 / 7.620660 | #8 / 0.151111 | #10 / 0.255859 | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #8 / 0.275866 | #7 / 6.931869 | #6 / 0.160256 | #11 / 0.250000 | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #14 / 0.258188 | #14 / 5.926963 | #14 / 0.105263 | #12 / 0.250000 | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #15 / 0.253271 | #5 / 7.382634 | #9 / 0.150000 | #13 / 0.242188 | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #33 / 0.201583 | #8 / 6.789789 | #15 / 0.103239 | #14 / 0.236328 | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #34 / 0.199147 | #6 / 7.378051 | #11 / 0.116550 | #15 / 0.236328 | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #5 / 0.327509 | — | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #10 / 0.274222 | — | — | — | fail | no | none |
| `d76479fb-dc26-5b4a-9f69-c00aa59cfd06`<br>`d76479fb-dc26-5b4a-9f69-c00aa59cfd06` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #11 / 0.267674 | #23 / 5.350842 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #12 / 0.266769 | #32 / 4.949235 | — | — | fail | no | none |
| `ed110340-2272-5935-843c-391a6a657a01`<br>`ed110340-2272-5935-843c-391a6a657a01` | `family.fire.drills`<br>`doc.fire.drills.v1` | #16 / 0.251332 | #24 / 5.308953 | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #17 / 0.246291 | #19 / 5.735712 | — | — | fail | no | none |
| `64df7c22-d350-5124-b01b-770fb0793050`<br>`64df7c22-d350-5124-b01b-770fb0793050` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #18 / 0.245819 | #37 / 4.795084 | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #19 / 0.243425 | #27 / 5.121738 | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #21 / 0.236008 | #22 / 5.362904 | — | — | fail | no | none |
| `19700c62-cb1e-5c51-a9cf-8cce818fe9d2`<br>`19700c62-cb1e-5c51-a9cf-8cce818fe9d2` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #22 / 0.233587 | — | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #23 / 0.232249 | #26 / 5.220866 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #25 / 0.227080 | #21 / 5.435783 | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #26 / 0.223034 | #15 / 5.822390 | — | — | fail | no | none |
| `33321467-60b1-5a2d-8a8d-3779711290aa`<br>`33321467-60b1-5a2d-8a8d-3779711290aa` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #27 / 0.220162 | #16 / 5.768430 | — | — | fail | no | none |
| `886fc5bc-416a-5ed9-9de6-5631b45c167d`<br>`886fc5bc-416a-5ed9-9de6-5631b45c167d` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #28 / 0.219651 | #39 / 4.656701 | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #29 / 0.217284 | #18 / 5.757988 | — | — | fail | no | none |
| `a211aa74-052b-50af-909f-b876d7a840e7`<br>`a211aa74-052b-50af-909f-b876d7a840e7` | `family.infection.outbreak-management`<br>`doc.infection.outbreak-management.v1` | #30 / 0.213155 | #9 / 6.615748 | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #31 / 0.212047 | — | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #32 / 0.209081 | — | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #35 / 0.194957 | #36 / 4.822089 | — | — | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #36 / 0.193511 | — | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #37 / 0.190210 | — | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | #38 / 0.189050 | #28 / 5.099069 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | #39 / 0.184688 | #17 / 5.761346 | — | — | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | #40 / 0.184335 | — | — | — | fail | no | none |
| `6a0b9950-1b65-5430-82cf-a21c2451ebbb`<br>`6a0b9950-1b65-5430-82cf-a21c2451ebbb` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | — | #10 / 6.235061 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | — | #20 / 5.565593 | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #25 / 5.236522 | — | — | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | — | #29 / 5.088203 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #31 / 5.017591 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #34 / 4.860331 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #35 / 4.857595 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | — | #40 / 4.598687 | — | — | fail | no | none |

### `v3.medication.historical.controlled-drugs-v1` / `exact-date`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: On 15 June 2024, when did a controlled-drug discrepancy have to be reported?
- Covered EvidenceUnits: `evidence.v3.medication.historical.controlled-drugs-v1.shift-end`
- Metrics: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000
- Hard failures: `none`

Planner contract comparison:

```json
{
  "actual_plan": {
    "clarification_reason": null,
    "explicit_date": "2024-06-15",
    "location_references": [],
    "retrieval_queries": [
      "On 15 June 2024, when did a controlled-drug discrepancy have to be reported?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": null
  },
  "correct": true,
  "differences": [],
  "expected_contract": {
    "clarification_reason": null,
    "explicit_date": "2024-06-15",
    "location_references": [],
    "retrieval_queries": [
      "On 15 June 2024, when did a controlled-drug discrepancy have to be reported?"
    ],
    "temporal_mode": "VALID_AT_DATE",
    "temporal_reference": null
  }
}
```

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.medication.historical.controlled-drugs-v1.shift-end` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v1` | documents/medication/controlled-drugs-v1.md |

#### PRIMARY

Candidate funnel: Dense=18 → Sparse=18 → Unique after RRF=15 → Reranker=15 → Threshold=2 → Final evidence=2

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `dee03403-128d-556b-bb3e-469857e808fd`<br>`dee03403-128d-556b-bb3e-469857e808fd` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #1 / 0.426790 | #1 / 19.441540 | #1 / 0.333333 | #1 / 0.628906 | pass | yes | evidence.v3.medication.historical.controlled-drugs-v1.shift-end |
| `c4979314-9ca2-573f-a219-57ab4773ad1f`<br>`c4979314-9ca2-573f-a219-57ab4773ad1f` | `family.medication.administration`<br>`doc.medication.administration.v1` | #3 / 0.316466 | #3 / 6.104495 | #2 / 0.250000 | #2 / 0.367188 | pass | yes | none |
| `d76479fb-dc26-5b4a-9f69-c00aa59cfd06`<br>`d76479fb-dc26-5b4a-9f69-c00aa59cfd06` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #4 / 0.285156 | #4 / 6.086317 | #4 / 0.222222 | #3 / 0.308594 | fail | no | none |
| `2de77c06-07f9-5de3-ace0-116bce59fa7d`<br>`2de77c06-07f9-5de3-ace0-116bce59fa7d` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #2 / 0.321236 | #8 / 4.648645 | #5 / 0.219780 | #4 / 0.308594 | fail | no | none |
| `2dcdbc13-00b3-5e91-997b-19e37ff1c84d`<br>`2dcdbc13-00b3-5e91-997b-19e37ff1c84d` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #5 / 0.271639 | #2 / 7.573383 | #3 / 0.242857 | #5 / 0.291016 | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #11 / 0.209683 | #17 / 2.927358 | #14 / 0.107955 | #6 / 0.269531 | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | #9 / 0.223264 | #12 / 3.890864 | #10 / 0.130252 | #7 / 0.267578 | fail | no | none |
| `64df7c22-d350-5124-b01b-770fb0793050`<br>`64df7c22-d350-5124-b01b-770fb0793050` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #8 / 0.236002 | #16 / 3.076781 | #12 / 0.124542 | #8 / 0.257812 | fail | no | none |
| `886fc5bc-416a-5ed9-9de6-5631b45c167d`<br>`886fc5bc-416a-5ed9-9de6-5631b45c167d` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #7 / 0.249391 | #6 / 5.580268 | #6 / 0.174242 | #9 / 0.255859 | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | #12 / 0.203125 | #9 / 4.533580 | #11 / 0.130252 | #10 / 0.255859 | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | #15 / 0.169858 | #13 / 3.755263 | #15 / 0.105556 | #11 / 0.250000 | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | #13 / 0.193675 | #11 / 4.271870 | #13 / 0.118056 | #12 / 0.241211 | fail | no | none |
| `19700c62-cb1e-5c51-a9cf-8cce818fe9d2`<br>`19700c62-cb1e-5c51-a9cf-8cce818fe9d2` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #10 / 0.211588 | #10 / 4.455399 | #9 / 0.133333 | #13 / 0.230469 | fail | no | none |
| `6a0b9950-1b65-5430-82cf-a21c2451ebbb`<br>`6a0b9950-1b65-5430-82cf-a21c2451ebbb` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | #16 / 0.142512 | #5 / 5.582501 | #8 / 0.147619 | #14 / 0.221680 | fail | no | none |
| `ed110340-2272-5935-843c-391a6a657a01`<br>`ed110340-2272-5935-843c-391a6a657a01` | `family.fire.drills`<br>`doc.fire.drills.v1` | #6 / 0.253474 | #7 / 4.720917 | #7 / 0.174242 | #15 / 0.218750 | fail | no | none |
| `33321467-60b1-5a2d-8a8d-3779711290aa`<br>`33321467-60b1-5a2d-8a8d-3779711290aa` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #14 / 0.185571 | #14 / 3.683740 | — | — | fail | no | none |
| `a5f14edc-43c1-589d-a639-36fee9e5f46a`<br>`a5f14edc-43c1-589d-a639-36fee9e5f46a` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #17 / 0.134805 | #18 / 2.022066 | — | — | fail | no | none |
| `2228856a-e242-5d14-bf7a-609592eb08b4`<br>`2228856a-e242-5d14-bf7a-609592eb08b4` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v1` | #18 / 0.130520 | #15 / 3.499318 | — | — | fail | no | none |

### `v3.medication.historical.controlled-drugs-v1` / `precision`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Did controlled-drugs procedure version 1 allow reporting a discrepancy by the end of the shift?
- Covered EvidenceUnits: `evidence.v3.medication.historical.controlled-drugs-v1.shift-end`
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
      "Did controlled-drugs procedure version 1 allow reporting a discrepancy by the end of the shift?"
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
      "Did controlled-drugs procedure version 1 allow reporting a discrepancy by the end of the shift?"
    ],
    "temporal_mode": "HISTORICAL_REFERENCE",
    "temporal_reference": {
      "kind": "HISTORICAL_REFERENCE",
      "value": "version 1"
    }
  }
}
```

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.medication.historical.controlled-drugs-v1.shift-end` | `family.medication.controlled-drugs` | `doc.medication.controlled-drugs.v1` | documents/medication/controlled-drugs-v1.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=6 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `dee03403-128d-556b-bb3e-469857e808fd`<br>`dee03403-128d-556b-bb3e-469857e808fd` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #1 / 0.478939 | #1 / 23.075163 | #1 / 0.333333 | #1 / 0.894531 | pass | yes | evidence.v3.medication.historical.controlled-drugs-v1.shift-end |
| `2dcdbc13-00b3-5e91-997b-19e37ff1c84d`<br>`2dcdbc13-00b3-5e91-997b-19e37ff1c84d` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #12 / 0.222759 | #2 / 13.704334 | #3 / 0.201681 | #2 / 0.447266 | pass | yes | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #20 / 0.191562 | #4 / 9.588639 | #6 / 0.151111 | #3 / 0.423828 | pass | yes | none |
| `c4979314-9ca2-573f-a219-57ab4773ad1f`<br>`c4979314-9ca2-573f-a219-57ab4773ad1f` | `family.medication.administration`<br>`doc.medication.administration.v1` | #2 / 0.310454 | #19 / 4.705274 | #5 / 0.184524 | #4 / 0.386719 | pass | yes | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #4 / 0.298236 | #5 / 8.825202 | #2 / 0.211111 | #5 / 0.382812 | pass | yes | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #7 / 0.260658 | #26 / 3.868715 | #10 / 0.115591 | #6 / 0.355469 | pass | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #3 / 0.307746 | — | #8 / 0.125000 | #7 / 0.328125 | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #21 / 0.191307 | #8 / 6.610512 | #11 / 0.115385 | #8 / 0.308594 | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #8 / 0.250832 | #9 / 6.028336 | #7 / 0.148352 | #9 / 0.277344 | fail | no | none |
| `2de77c06-07f9-5de3-ace0-116bce59fa7d`<br>`2de77c06-07f9-5de3-ace0-116bce59fa7d` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #5 / 0.296603 | — | #15 / 0.100000 | #10 / 0.253906 | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #9 / 0.238585 | #3 / 10.614342 | #4 / 0.196429 | #11 / 0.249023 | fail | no | none |
| `33321467-60b1-5a2d-8a8d-3779711290aa`<br>`33321467-60b1-5a2d-8a8d-3779711290aa` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #25 / 0.177801 | #6 / 7.661562 | #9 / 0.124242 | #12 / 0.243164 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #14 / 0.214871 | #12 / 5.588173 | #13 / 0.111455 | #13 / 0.230469 | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #13 / 0.221563 | #13 / 5.584798 | #14 / 0.111111 | #14 / 0.221680 | fail | no | none |
| `ed110340-2272-5935-843c-391a6a657a01`<br>`ed110340-2272-5935-843c-391a6a657a01` | `family.fire.drills`<br>`doc.fire.drills.v1` | #15 / 0.213030 | #11 / 5.620729 | #12 / 0.112500 | #15 / 0.215820 | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | #6 / 0.265942 | — | — | — | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | #10 / 0.226355 | — | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #11 / 0.223062 | — | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #16 / 0.211963 | #32 / 3.625669 | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #17 / 0.206399 | #15 / 5.034361 | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #18 / 0.199973 | #27 / 3.866825 | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #19 / 0.197976 | #22 / 4.307831 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #22 / 0.186930 | #21 / 4.309786 | — | — | fail | no | none |
| `19700c62-cb1e-5c51-a9cf-8cce818fe9d2`<br>`19700c62-cb1e-5c51-a9cf-8cce818fe9d2` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #23 / 0.184391 | — | — | — | fail | no | none |
| `64df7c22-d350-5124-b01b-770fb0793050`<br>`64df7c22-d350-5124-b01b-770fb0793050` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #24 / 0.183421 | #30 / 3.687838 | — | — | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #26 / 0.176682 | #14 / 5.443798 | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #27 / 0.176381 | #24 / 4.087710 | — | — | fail | no | none |
| `016e8751-5c0c-58b9-8695-c190270b5921`<br>`016e8751-5c0c-58b9-8695-c190270b5921` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | #28 / 0.171899 | #39 / 3.133336 | — | — | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #29 / 0.170877 | — | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #30 / 0.169080 | #20 / 4.439630 | — | — | fail | no | none |
| `d76479fb-dc26-5b4a-9f69-c00aa59cfd06`<br>`d76479fb-dc26-5b4a-9f69-c00aa59cfd06` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #31 / 0.168863 | #25 / 4.007868 | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #32 / 0.168434 | #10 / 5.661940 | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | #33 / 0.164170 | — | — | — | fail | no | none |
| `a211aa74-052b-50af-909f-b876d7a840e7`<br>`a211aa74-052b-50af-909f-b876d7a840e7` | `family.infection.outbreak-management`<br>`doc.infection.outbreak-management.v1` | #34 / 0.159378 | #18 / 4.759525 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | #35 / 0.157614 | #28 / 3.755014 | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #36 / 0.151869 | #40 / 3.035556 | — | — | fail | no | none |
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #37 / 0.151455 | — | — | — | fail | no | none |
| `a5f14edc-43c1-589d-a639-36fee9e5f46a`<br>`a5f14edc-43c1-589d-a639-36fee9e5f46a` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #38 / 0.147227 | #33 / 3.494489 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | #39 / 0.138308 | #37 / 3.237160 | — | — | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #40 / 0.137915 | #31 / 3.678983 | — | — | fail | no | none |
| `6a0b9950-1b65-5430-82cf-a21c2451ebbb`<br>`6a0b9950-1b65-5430-82cf-a21c2451ebbb` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | — | #7 / 6.978993 | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #16 / 4.923780 | — | — | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | — | #17 / 4.766736 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | — | #23 / 4.236620 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #29 / 3.696912 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #34 / 3.375696 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #35 / 3.287578 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #36 / 3.287406 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #38 / 3.193237 | — | — | fail | no | none |

### `v3.safeguarding.current.body-map` / `cause`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Should staff write what they think caused a bruise on the body map?
- Covered EvidenceUnits: `evidence.v3.engineering.safeguarding.body.map.facts.only`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.safeguarding.body.map.facts.only` | `family.safeguarding.body-map` | `doc.safeguarding.body-map.v1` | documents/safeguarding/body-map-form.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=4 → Final evidence=4

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #1 / 0.497576 | #1 / 9.838580 | #1 / 0.333333 | #1 / 0.812500 | pass | yes | evidence.v3.engineering.safeguarding.body.map.facts.only |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #3 / 0.269868 | — | #11 / 0.125000 | #2 / 0.410156 | pass | yes | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #10 / 0.237119 | #4 / 5.614104 | #3 / 0.177778 | #3 / 0.345703 | pass | yes | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #2 / 0.281809 | #8 / 4.423052 | #2 / 0.219780 | #4 / 0.343750 | pass | yes | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #8 / 0.241229 | #5 / 4.924257 | #4 / 0.176923 | #5 / 0.300781 | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #12 / 0.230962 | #7 / 4.543982 | #10 / 0.142157 | #6 / 0.287109 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #4 / 0.257583 | #22 / 3.350334 | #8 / 0.148148 | #7 / 0.283203 | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #14 / 0.226699 | #14 / 3.884685 | #12 / 0.105263 | #8 / 0.283203 | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #9 / 0.238142 | #27 / 2.656108 | #13 / 0.102679 | #9 / 0.271484 | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | #17 / 0.218189 | #3 / 5.701668 | #5 / 0.170455 | #10 / 0.269531 | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #5 / 0.255688 | — | #14 / 0.100000 | #11 / 0.267578 | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | #7 / 0.252688 | #9 / 4.210852 | #6 / 0.154762 | #12 / 0.245117 | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #6 / 0.255029 | #11 / 4.141704 | #7 / 0.153409 | #13 / 0.241211 | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #19 / 0.214134 | #13 / 3.920550 | #15 / 0.097222 | #14 / 0.218750 | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | — | #2 / 6.264102 | #9 / 0.142857 | #15 / 0.212891 | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #11 / 0.236562 | — | — | — | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #13 / 0.228321 | — | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #15 / 0.226559 | — | — | — | fail | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #16 / 0.223423 | #16 / 3.824288 | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #18 / 0.218184 | — | — | — | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #20 / 0.210404 | — | — | — | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | #21 / 0.207813 | — | — | — | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #22 / 0.205639 | #20 / 3.549882 | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #23 / 0.204158 | #35 / 2.111872 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | #24 / 0.204033 | #15 / 3.861232 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #25 / 0.201980 | #34 / 2.208477 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | #26 / 0.195753 | #18 / 3.634094 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | #27 / 0.195269 | — | — | — | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #28 / 0.193874 | #23 / 3.301356 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #29 / 0.190980 | #10 / 4.162262 | — | — | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #30 / 0.190155 | #25 / 2.947251 | — | — | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #31 / 0.189192 | — | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | #32 / 0.186707 | #24 / 3.093448 | — | — | fail | no | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #33 / 0.186638 | — | — | — | fail | no | none |
| `945c7f18-ad33-59fb-a318-12754178cc65`<br>`945c7f18-ad33-59fb-a318-12754178cc65` | `family.training.fire`<br>`doc.training.fire.v1` | #34 / 0.185944 | — | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #35 / 0.177387 | #31 / 2.416353 | — | — | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #36 / 0.176285 | — | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #37 / 0.176270 | — | — | — | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #38 / 0.173149 | #19 / 3.594299 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #39 / 0.170574 | #21 / 3.437155 | — | — | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #40 / 0.170545 | — | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #6 / 4.691578 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | — | #12 / 3.966695 | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | — | #17 / 3.660033 | — | — | fail | no | none |
| `016e8751-5c0c-58b9-8695-c190270b5921`<br>`016e8751-5c0c-58b9-8695-c190270b5921` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | — | #26 / 2.790033 | — | — | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | — | #28 / 2.570563 | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | — | #29 / 2.445781 | — | — | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | — | #30 / 2.416466 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #32 / 2.322237 | — | — | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | — | #33 / 2.221114 | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | — | #36 / 1.923294 | — | — | fail | no | none |
| `85de0be4-0aca-5ddb-a5b2-bc7723fd07e6`<br>`85de0be4-0aca-5ddb-a5b2-bc7723fd07e6` | `family.complaints.advocacy`<br>`doc.complaints.advocacy.v1` | — | #37 / 1.840194 | — | — | fail | no | none |
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | — | #38 / 1.837566 | — | — | fail | no | none |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | — | #39 / 1.823198 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | — | #40 / 1.806643 | — | — | fail | no | none |

### `v3.safeguarding.current.body-map` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
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
| PRIMARY | `evidence.v3.engineering.safeguarding.body.map.facts.only` | `family.safeguarding.body-map` | `doc.safeguarding.body-map.v1` | documents/safeguarding/body-map-form.md |

#### PRIMARY

Candidate funnel: Dense=— → Sparse=— → Unique after RRF=— → Reranker=— → Threshold=— → Final evidence=—

### `v3.safeguarding.current.body-map` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: What should be recorded on an injury body map?
- Covered EvidenceUnits: `evidence.v3.engineering.safeguarding.body.map.facts.only`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.safeguarding.body.map.facts.only` | `family.safeguarding.body-map` | `doc.safeguarding.body-map.v1` | documents/safeguarding/body-map-form.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=2 → Final evidence=2

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `0b98a9fa-9cbf-5a75-b4c6-8fe24be13892`<br>`0b98a9fa-9cbf-5a75-b4c6-8fe24be13892` | `family.safeguarding.body-map`<br>`doc.safeguarding.body-map.v1` | #1 / 0.656138 | #1 / 21.170357 | #1 / 0.333333 | #1 / 0.921875 | pass | yes | evidence.v3.engineering.safeguarding.body.map.facts.only |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #4 / 0.330906 | #2 / 9.313922 | #2 / 0.253968 | #2 / 0.345703 | pass | yes | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #8 / 0.287210 | #24 / 3.025923 | #14 / 0.111406 | #3 / 0.333984 | fail | no | none |
| `88087832-edfc-5653-b88e-6e75fd61418e`<br>`88087832-edfc-5653-b88e-6e75fd61418e` | `family.complaints.form`<br>`doc.complaints.form.v1` | #2 / 0.345586 | #10 / 4.845942 | #3 / 0.209524 | #4 / 0.332031 | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | #5 / 0.301786 | #22 / 3.335397 | #8 / 0.137037 | #5 / 0.294922 | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #3 / 0.341669 | #15 / 4.409150 | #4 / 0.175000 | #6 / 0.292969 | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #6 / 0.298228 | #11 / 4.801610 | #5 / 0.153409 | #7 / 0.285156 | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #7 / 0.291344 | #17 / 4.152530 | #11 / 0.128788 | #8 / 0.283203 | fail | no | none |
| `2e7f93be-5411-5387-af47-d3c8ba489502`<br>`2e7f93be-5411-5387-af47-d3c8ba489502` | `family.infection.isolation`<br>`doc.infection.isolation.v1` | #11 / 0.278322 | #29 / 2.758788 | #15 / 0.091912 | #9 / 0.263672 | fail | no | none |
| `945c7f18-ad33-59fb-a318-12754178cc65`<br>`945c7f18-ad33-59fb-a318-12754178cc65` | `family.training.fire`<br>`doc.training.fire.v1` | #9 / 0.285796 | #9 / 4.857510 | #7 / 0.142857 | #10 / 0.257812 | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #14 / 0.247114 | #8 / 5.578725 | #9 / 0.129555 | #11 / 0.253906 | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #35 / 0.187446 | #6 / 5.844626 | #13 / 0.115909 | #12 / 0.245117 | fail | no | none |
| `5b147f65-836f-5799-8745-c90cea1d3e95`<br>`5b147f65-836f-5799-8745-c90cea1d3e95` | `family.complaints.feedback`<br>`doc.complaints.feedback.v1` | #25 / 0.210658 | #4 / 6.196408 | #6 / 0.144444 | #13 / 0.243164 | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #29 / 0.200257 | #5 / 6.017218 | #10 / 0.129412 | #14 / 0.221680 | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | — | #3 / 7.181412 | #12 / 0.125000 | #15 / 0.218750 | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #10 / 0.278449 | — | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | #12 / 0.267755 | — | — | — | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #13 / 0.254043 | — | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #15 / 0.238379 | #26 / 2.915824 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #16 / 0.234585 | — | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | #17 / 0.232164 | — | — | — | fail | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #18 / 0.227554 | — | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #19 / 0.225147 | — | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #20 / 0.224015 | #32 / 2.387690 | — | — | fail | no | none |
| `97dc7b1e-2382-510e-be9d-bc33279603c9`<br>`97dc7b1e-2382-510e-be9d-bc33279603c9` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #21 / 0.217183 | — | — | — | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #22 / 0.216477 | — | — | — | fail | no | none |
| `e6c87ef4-bdc9-5b1c-b1f7-ca27505b1d2f`<br>`e6c87ef4-bdc9-5b1c-b1f7-ca27505b1d2f` | `family.payroll.mileage`<br>`doc.payroll.mileage.v1` | #23 / 0.214359 | #25 / 2.928335 | — | — | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | #24 / 0.214325 | #34 / 2.286871 | — | — | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #26 / 0.206245 | #18 / 4.141859 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #27 / 0.203642 | — | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #28 / 0.203070 | — | — | — | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #30 / 0.198400 | #13 / 4.536743 | — | — | fail | no | none |
| `fa3d7fba-9042-5961-a541-f0fd3d4ba3c3`<br>`fa3d7fba-9042-5961-a541-f0fd3d4ba3c3` | `family.infection-control.midlands-community-specimen-transport`<br>`doc.infection-control.midlands-community-specimen-transport.v1` | #31 / 0.196238 | #19 / 3.657999 | — | — | fail | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #32 / 0.192191 | #40 / 1.982560 | — | — | fail | no | none |
| `ba40d4f7-7c17-592b-9413-6b5f6ad0fe18`<br>`ba40d4f7-7c17-592b-9413-6b5f6ad0fe18` | `family.infection.clinical-waste`<br>`doc.infection.clinical-waste.v1` | #33 / 0.188731 | — | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | #34 / 0.188501 | — | — | — | fail | no | none |
| `980e0701-e200-52b6-aa4d-4f11701cedc8`<br>`980e0701-e200-52b6-aa4d-4f11701cedc8` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | #36 / 0.187189 | — | — | — | fail | no | none |
| `dfe7812d-2b92-54c4-916e-85a94e0a731a`<br>`dfe7812d-2b92-54c4-916e-85a94e0a731a` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | #37 / 0.185476 | #23 / 3.262442 | — | — | fail | no | none |
| `0be5b5b7-f75a-529e-82f3-d7f4b98de119`<br>`0be5b5b7-f75a-529e-82f3-d7f4b98de119` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #38 / 0.182004 | #38 / 2.056568 | — | — | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | #39 / 0.180223 | #33 / 2.378659 | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #40 / 0.179631 | #36 / 2.237241 | — | — | fail | no | none |
| `8aa6fad2-b29c-5376-8583-c09ad8bcdf41`<br>`8aa6fad2-b29c-5376-8583-c09ad8bcdf41` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #7 / 5.745048 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #12 / 4.653223 | — | — | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | — | #14 / 4.475686 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #16 / 4.354597 | — | — | fail | no | none |
| `15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7`<br>`15da79a7-071b-5d0b-8fa1-34c2c3f5dcd7` | `family.medication.fridge-reference`<br>`doc.medication.fridge-reference.v1` | — | #20 / 3.571087 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #21 / 3.460003 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | — | #27 / 2.903042 | — | — | fail | no | none |
| `d49ac336-f7b3-5306-a556-fd5489be5ecc`<br>`d49ac336-f7b3-5306-a556-fd5489be5ecc` | `family.medication.covert`<br>`doc.medication.covert.v1` | — | #28 / 2.800028 | — | — | fail | no | none |
| `4e8032c8-f443-5895-9aba-5bb7ef989a94`<br>`4e8032c8-f443-5895-9aba-5bb7ef989a94` | `family.infection.laundry`<br>`doc.infection.laundry.v1` | — | #30 / 2.515654 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | — | #31 / 2.475201 | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | — | #35 / 2.283467 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #37 / 2.058352 | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | — | #39 / 2.040613 | — | — | fail | no | none |

### `v3.training.current.fire-marshal-refresh` / `colloquial`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: When does the fire warden course need renewing?
- Covered EvidenceUnits: `evidence.v3.engineering.training.fire.marshal.interval`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.training.fire.marshal.interval` | `family.training.matrix` | `doc.training.matrix.v1` | documents/training/mandatory-training-matrix.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=3 → Final evidence=3

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | #1 / 0.581914 | #1 / 12.637688 | #1 / 0.333333 | #1 / 0.628906 | pass | yes | evidence.v3.engineering.training.fire.marshal.interval |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #2 / 0.507523 | #5 / 9.031254 | #3 / 0.242857 | #2 / 0.419922 | pass | yes | none |
| `945c7f18-ad33-59fb-a318-12754178cc65`<br>`945c7f18-ad33-59fb-a318-12754178cc65` | `family.training.fire`<br>`doc.training.fire.v1` | #3 / 0.503871 | #4 / 9.581289 | #4 / 0.236111 | #3 / 0.388672 | pass | yes | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | #5 / 0.399211 | #2 / 11.668429 | #2 / 0.242857 | #4 / 0.318359 | fail | no | none |
| `d695dc92-a368-534e-b544-152e640ebdd9`<br>`d695dc92-a368-534e-b544-152e640ebdd9` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #6 / 0.377916 | #8 / 7.756642 | #6 / 0.167832 | #5 / 0.316406 | fail | no | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #7 / 0.374966 | #15 / 3.881550 | #10 / 0.133333 | #6 / 0.291016 | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | #9 / 0.336091 | #9 / 7.135553 | #8 / 0.142857 | #7 / 0.291016 | fail | no | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #4 / 0.468692 | #3 / 10.479125 | #5 / 0.236111 | #8 / 0.277344 | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | #16 / 0.298543 | #6 / 8.609176 | #9 / 0.138528 | #9 / 0.261719 | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #8 / 0.358048 | — | #15 / 0.076923 | #10 / 0.249023 | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #12 / 0.326265 | #14 / 3.928033 | #14 / 0.111455 | #11 / 0.242188 | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #11 / 0.326842 | #10 / 6.990798 | #11 / 0.129167 | #12 / 0.238281 | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #10 / 0.332838 | #7 / 7.811546 | #7 / 0.150000 | #13 / 0.236328 | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #13 / 0.313678 | #12 / 6.375341 | #12 / 0.114379 | #14 / 0.232422 | fail | no | none |
| `016e8751-5c0c-58b9-8695-c190270b5921`<br>`016e8751-5c0c-58b9-8695-c190270b5921` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | #15 / 0.303269 | #11 / 6.435192 | #13 / 0.112500 | #15 / 0.225586 | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #14 / 0.308330 | — | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #17 / 0.288567 | — | — | — | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #18 / 0.276448 | — | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #19 / 0.271497 | — | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #20 / 0.253414 | #34 / 2.304456 | — | — | fail | no | none |
| `980e0701-e200-52b6-aa4d-4f11701cedc8`<br>`980e0701-e200-52b6-aa4d-4f11701cedc8` | `family.reference.emergency-numbers`<br>`doc.reference.emergency-numbers.v1` | #21 / 0.244461 | — | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #22 / 0.243920 | — | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #23 / 0.238886 | #31 / 2.543076 | — | — | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #24 / 0.236436 | — | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #25 / 0.235825 | #29 / 2.878547 | — | — | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #26 / 0.233933 | — | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #27 / 0.229108 | — | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #28 / 0.227673 | — | — | — | fail | no | none |
| `dfe7812d-2b92-54c4-916e-85a94e0a731a`<br>`dfe7812d-2b92-54c4-916e-85a94e0a731a` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | #29 / 0.227635 | #30 / 2.826974 | — | — | fail | no | none |
| `5c27b377-cca3-54a9-b2f9-6c7fa37c2857`<br>`5c27b377-cca3-54a9-b2f9-6c7fa37c2857` | `family.visitors.contractor-sign-in`<br>`doc.visitors.contractor-sign-in.v1` | #30 / 0.226603 | #33 / 2.308755 | — | — | fail | no | none |
| `97dc7b1e-2382-510e-be9d-bc33279603c9`<br>`97dc7b1e-2382-510e-be9d-bc33279603c9` | `family.reference.contacts`<br>`doc.reference.contacts.v1` | #31 / 0.225251 | — | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #32 / 0.222999 | #20 / 3.317569 | — | — | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #33 / 0.216185 | — | — | — | fail | no | none |
| `7e5de72c-2361-5b0f-8b2b-25512843e880`<br>`7e5de72c-2361-5b0f-8b2b-25512843e880` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #34 / 0.214617 | #27 / 2.957573 | — | — | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #35 / 0.213138 | #26 / 2.962016 | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #36 / 0.212023 | — | — | — | fail | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #37 / 0.210268 | — | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #38 / 0.210241 | — | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #39 / 0.207359 | — | — | — | fail | no | none |
| `8aa6fad2-b29c-5376-8583-c09ad8bcdf41`<br>`8aa6fad2-b29c-5376-8583-c09ad8bcdf41` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #40 / 0.206211 | — | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | — | #13 / 4.097333 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #16 / 3.629422 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #17 / 3.617780 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #18 / 3.576319 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #19 / 3.478470 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #21 / 3.259412 | — | — | fail | no | none |
| `aead6f19-4c74-555f-9c5b-f86711197db5`<br>`aead6f19-4c74-555f-9c5b-f86711197db5` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #22 / 3.192522 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #23 / 3.170891 | — | — | fail | no | none |
| `5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305`<br>`5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | — | #24 / 3.035403 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | — | #25 / 3.008437 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | — | #28 / 2.889721 | — | — | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | — | #32 / 2.541290 | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | — | #35 / 2.301190 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #36 / 2.291208 | — | — | fail | no | none |
| `e3a6a6a7-4dd1-5359-8131-eab08d91f137`<br>`e3a6a6a7-4dd1-5359-8131-eab08d91f137` | `family.safeguarding.dols`<br>`doc.safeguarding.dols.v1` | — | #37 / 2.185959 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #38 / 2.164059 | — | — | fail | no | none |
| `3d2fff08-3094-57ef-912c-59c2afc942f9`<br>`3d2fff08-3094-57ef-912c-59c2afc942f9` | `family.complaints.duty-candour`<br>`doc.complaints.duty-candour.v1` | — | #39 / 2.002020 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | — | #40 / 1.983650 | — | — | fail | no | none |

### `v3.training.current.fire-marshal-refresh` / `contrast`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `False`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: Is fire marshal refresher training yearly or every two years?
- Covered EvidenceUnits: `evidence.v3.engineering.training.fire.marshal.interval`
- Metrics: recall=1.0000, precision=0.1000, MRR=0.5000, nDCG=1.0000
- Hard failures: `planner_mismatch`

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
      "Is fire marshal refresher training yearly or every two years?"
    ],
    "temporal_mode": "CURRENT",
    "temporal_reference": null
  }
}
```

  - COMPARISON: recall=1.0000, precision=0.0000, MRR=0.0000, nDCG=1.0000
  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.training.fire.marshal.interval` | `family.training.matrix` | `doc.training.matrix.v1` | documents/training/mandatory-training-matrix.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=5 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | #1 / 0.567517 | #1 / 21.194044 | #1 / 0.333333 | #1 / 0.792969 | pass | yes | evidence.v3.engineering.training.fire.marshal.interval |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #3 / 0.464219 | #6 / 8.454721 | #4 / 0.215909 | #2 / 0.460938 | pass | yes | none |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #2 / 0.479641 | #2 / 12.659353 | #2 / 0.285714 | #3 / 0.363281 | pass | yes | none |
| `d695dc92-a368-534e-b544-152e640ebdd9`<br>`d695dc92-a368-534e-b544-152e640ebdd9` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #6 / 0.356479 | #5 / 8.950489 | #6 / 0.190909 | #4 / 0.359375 | pass | yes | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #5 / 0.375172 | #9 / 6.322133 | #7 / 0.171429 | #5 / 0.343750 | pass | yes | none |
| `945c7f18-ad33-59fb-a318-12754178cc65`<br>`945c7f18-ad33-59fb-a318-12754178cc65` | `family.training.fire`<br>`doc.training.fire.v1` | #4 / 0.459955 | #4 / 9.743365 | #3 / 0.222222 | #6 / 0.335938 | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | #7 / 0.355971 | #3 / 11.094320 | #5 / 0.208333 | #7 / 0.322266 | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #9 / 0.324419 | #38 / 1.051035 | #15 / 0.094684 | #8 / 0.316406 | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | #10 / 0.315038 | #8 / 6.420405 | #8 / 0.143590 | #9 / 0.271484 | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #8 / 0.342368 | #15 / 4.999524 | #9 / 0.126923 | #10 / 0.263672 | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | #24 / 0.222535 | #11 / 5.785890 | #14 / 0.096983 | #11 / 0.263672 | fail | no | none |
| `7e5de72c-2361-5b0f-8b2b-25512843e880`<br>`7e5de72c-2361-5b0f-8b2b-25512843e880` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #19 / 0.232343 | #7 / 6.605403 | #10 / 0.125000 | #12 / 0.248047 | fail | no | none |
| `016e8751-5c0c-58b9-8695-c190270b5921`<br>`016e8751-5c0c-58b9-8695-c190270b5921` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | #21 / 0.229612 | #12 / 5.631241 | #13 / 0.097285 | #13 / 0.242188 | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #11 / 0.274040 | #14 / 5.409596 | #12 / 0.115132 | #14 / 0.232422 | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #15 / 0.255127 | #10 / 5.899369 | #11 / 0.116667 | #15 / 0.229492 | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #12 / 0.271184 | #32 / 1.490868 | — | — | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #13 / 0.263645 | — | — | — | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #14 / 0.259434 | — | — | — | fail | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #16 / 0.249523 | — | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #17 / 0.246752 | — | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #18 / 0.236629 | — | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #20 / 0.230839 | — | — | — | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #22 / 0.229284 | #13 / 5.599365 | — | — | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #23 / 0.227156 | #18 / 3.162802 | — | — | fail | no | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #25 / 0.221528 | — | — | — | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #26 / 0.220639 | #39 / 1.019666 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #27 / 0.219039 | #21 / 2.850773 | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #28 / 0.215106 | — | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #29 / 0.208517 | #31 / 1.781410 | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #30 / 0.206822 | — | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #31 / 0.205486 | #36 / 1.120165 | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | #32 / 0.203712 | #19 / 3.149306 | — | — | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | #33 / 0.203431 | #35 / 1.141387 | — | — | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #34 / 0.202201 | — | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #35 / 0.201933 | — | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #36 / 0.201637 | — | — | — | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | #37 / 0.199201 | #24 / 2.535583 | — | — | fail | no | none |
| `8aa6fad2-b29c-5376-8583-c09ad8bcdf41`<br>`8aa6fad2-b29c-5376-8583-c09ad8bcdf41` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #38 / 0.193684 | #27 / 2.172424 | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #39 / 0.190987 | — | — | — | fail | no | none |
| `dfe7812d-2b92-54c4-916e-85a94e0a731a`<br>`dfe7812d-2b92-54c4-916e-85a94e0a731a` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | #40 / 0.190623 | #16 / 3.610335 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #17 / 3.321485 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #20 / 3.002930 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #22 / 2.570169 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #23 / 2.561990 | — | — | fail | no | none |
| `aead6f19-4c74-555f-9c5b-f86711197db5`<br>`aead6f19-4c74-555f-9c5b-f86711197db5` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #25 / 2.372989 | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | — | #26 / 2.201323 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | — | #28 / 2.084250 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #29 / 2.015417 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #30 / 1.919211 | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | — | #33 / 1.235540 | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | — | #34 / 1.200315 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #37 / 1.103636 | — | — | fail | no | none |
| `5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305`<br>`5fc7e1a8-7b77-5269-bbe4-0f1dc0f01305` | `family.payroll.calendar`<br>`doc.payroll.calendar.v1` | — | #40 / 0.871017 | — | — | fail | no | none |

#### COMPARISON

Candidate funnel: Dense=13 → Sparse=13 → Unique after RRF=13 → Reranker=13 → Threshold=2 → Final evidence=2

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `ed110340-2272-5935-843c-391a6a657a01`<br>`ed110340-2272-5935-843c-391a6a657a01` | `family.fire.drills`<br>`doc.fire.drills.v1` | #1 / 0.460089 | #1 / 12.948647 | #1 / 0.333333 | #1 / 0.371094 | pass | yes | none |
| `2de77c06-07f9-5de3-ace0-116bce59fa7d`<br>`2de77c06-07f9-5de3-ace0-116bce59fa7d` | `family.training.medication-competency`<br>`doc.training.medication-competency.v1` | #2 / 0.365522 | #3 / 6.316950 | #2 / 0.267857 | #2 / 0.347656 | pass | yes | none |
| `19700c62-cb1e-5c51-a9cf-8cce818fe9d2`<br>`19700c62-cb1e-5c51-a9cf-8cce818fe9d2` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v1` | #3 / 0.348005 | #12 / 1.273274 | #5 / 0.183824 | #3 / 0.304688 | fail | no | none |
| `c4979314-9ca2-573f-a219-57ab4773ad1f`<br>`c4979314-9ca2-573f-a219-57ab4773ad1f` | `family.medication.administration`<br>`doc.medication.administration.v1` | #4 / 0.289247 | #8 / 2.125260 | #4 / 0.188034 | #4 / 0.238281 | fail | no | none |
| `2228856a-e242-5d14-bf7a-609592eb08b4`<br>`2228856a-e242-5d14-bf7a-609592eb08b4` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v1` | #8 / 0.194292 | #2 / 6.409922 | #3 / 0.219780 | #5 / 0.232422 | fail | no | none |
| `2dcdbc13-00b3-5e91-997b-19e37ff1c84d`<br>`2dcdbc13-00b3-5e91-997b-19e37ff1c84d` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v1` | #6 / 0.229266 | #6 / 2.575927 | #6 / 0.181818 | #6 / 0.232422 | fail | no | none |
| `6a0b9950-1b65-5430-82cf-a21c2451ebbb`<br>`6a0b9950-1b65-5430-82cf-a21c2451ebbb` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v1` | #10 / 0.180148 | #5 / 2.866684 | #7 / 0.166667 | #7 / 0.199219 | fail | no | none |
| `64df7c22-d350-5124-b01b-770fb0793050`<br>`64df7c22-d350-5124-b01b-770fb0793050` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v1` | #11 / 0.152252 | #10 / 1.893567 | #13 / 0.129167 | #8 / 0.194336 | fail | no | none |
| `dee03403-128d-556b-bb3e-469857e808fd`<br>`dee03403-128d-556b-bb3e-469857e808fd` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v1` | #5 / 0.246867 | #13 / 0.844507 | #9 / 0.155556 | #9 / 0.193359 | fail | no | none |
| `886fc5bc-416a-5ed9-9de6-5631b45c167d`<br>`886fc5bc-416a-5ed9-9de6-5631b45c167d` | `family.complaints.handling`<br>`doc.complaints.handling.v1` | #13 / 0.130384 | #4 / 3.306936 | #8 / 0.166667 | #10 / 0.189453 | fail | no | none |
| `33321467-60b1-5a2d-8a8d-3779711290aa`<br>`33321467-60b1-5a2d-8a8d-3779711290aa` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v1` | #12 / 0.146161 | #9 / 1.929959 | #12 / 0.130252 | #11 / 0.189453 | fail | no | none |
| `a5f14edc-43c1-589d-a639-36fee9e5f46a`<br>`a5f14edc-43c1-589d-a639-36fee9e5f46a` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v1` | #7 / 0.211867 | #11 / 1.312326 | #11 / 0.145833 | #12 / 0.189453 | fail | no | none |
| `d76479fb-dc26-5b4a-9f69-c00aa59cfd06`<br>`d76479fb-dc26-5b4a-9f69-c00aa59cfd06` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v1` | #9 / 0.192270 | #7 / 2.316382 | #10 / 0.154762 | #13 / 0.188477 | fail | no | none |

### `v3.training.current.fire-marshal-refresh` / `direct`

- Planning status: `SUCCEEDED`
- Planner failure: `none`
- Provider status: `not recorded`
- Planner attempts: `not recorded`
- Retrieval executed: `True`
- Retrieval failure stage/category: `none` / `none`
- Retrieval failure service/model: `not recorded` / `not recorded`
- Retrieval failure HTTP/retries/requests: `not recorded` / `not recorded` / `not recorded`
- Provider retries / outer-service retries: `not recorded` / `not recorded`
- Failure window / retry wait: `not recorded` to `not recorded` / `not recorded` ms
- Provider cooldown: `not recorded` seconds via `not recorded`
- Candidate lineage produced before failure: `not applicable`
- Contributes retrieval metrics: `True`
- Planner correct: `True`
- Eligibility correct: `True`
- Outcome correct: `True`
- Expected outcome: `EVIDENCE_FOUND`
- Text capture: `BENCHMARK_TEXT`
- Question: How often must a fire marshal repeat practical training?
- Covered EvidenceUnits: `evidence.v3.engineering.training.fire.marshal.interval`
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

  - PRIMARY: recall=1.0000, precision=0.2000, MRR=1.0000, nDCG=1.0000

Expected evidence:

| Side | EvidenceUnit | Family | Version | Source |
|---|---|---|---|---|
| PRIMARY | `evidence.v3.engineering.training.fire.marshal.interval` | `family.training.matrix` | `doc.training.matrix.v1` | documents/training/mandatory-training-matrix.md |

#### PRIMARY

Candidate funnel: Dense=40 → Sparse=40 → Unique after RRF=15 → Reranker=15 → Threshold=5 → Final evidence=5

| Candidate / chunk | Family / version | Dense | Sparse | RRF | Reranker | Threshold | Final | EvidenceUnits |
|---|---|---:|---:|---:|---:|---|---|---|
| `d885262a-92f8-5d5e-9888-72e996f55aa5`<br>`d885262a-92f8-5d5e-9888-72e996f55aa5` | `family.training.matrix`<br>`doc.training.matrix.v1` | #1 / 0.548028 | #1 / 17.920742 | #1 / 0.333333 | #1 / 0.863281 | pass | yes | evidence.v3.engineering.training.fire.marshal.interval |
| `94770add-6ab9-56a7-bc10-88de6c59958d`<br>`94770add-6ab9-56a7-bc10-88de6c59958d` | `family.fire.drills`<br>`doc.fire.drills.v2` | #3 / 0.419443 | #2 / 13.199096 | #2 / 0.267857 | #2 / 0.445312 | pass | yes | none |
| `f9d1c281-e919-519b-ad96-ab81d305167a`<br>`f9d1c281-e919-519b-ad96-ab81d305167a` | `family.training.safeguarding`<br>`doc.training.safeguarding.v1` | #4 / 0.392472 | #5 / 9.222688 | #5 / 0.211111 | #3 / 0.402344 | pass | yes | none |
| `945c7f18-ad33-59fb-a318-12754178cc65`<br>`945c7f18-ad33-59fb-a318-12754178cc65` | `family.training.fire`<br>`doc.training.fire.v1` | #2 / 0.509539 | #4 / 10.532485 | #3 / 0.253968 | #4 / 0.396484 | pass | yes | none |
| `d9acd793-c84d-5667-9a55-f3057ed306ef`<br>`d9acd793-c84d-5667-9a55-f3057ed306ef` | `family.training.medication-competency`<br>`doc.training.medication-competency.v2` | #5 / 0.360081 | #7 / 8.002714 | #6 / 0.183333 | #5 / 0.349609 | pass | yes | none |
| `d695dc92-a368-534e-b544-152e640ebdd9`<br>`d695dc92-a368-534e-b544-152e640ebdd9` | `family.health-safety.equipment-checks`<br>`doc.health-safety.equipment-checks.v1` | #7 / 0.309087 | #6 / 9.015020 | #7 / 0.174242 | #6 / 0.333984 | fail | no | none |
| `b23e5252-5564-5363-82be-6b512216d673`<br>`b23e5252-5564-5363-82be-6b512216d673` | `family.training.induction`<br>`doc.training.induction.v1` | #6 / 0.332236 | #3 / 12.818072 | #4 / 0.215909 | #7 / 0.330078 | fail | no | none |
| `e8aa72c4-9673-55d1-888b-d6d70b7dbc4f`<br>`e8aa72c4-9673-55d1-888b-d6d70b7dbc4f` | `family.health-safety.moving-handling`<br>`doc.health-safety.moving-handling.v2` | #10 / 0.275794 | #9 / 7.117978 | #9 / 0.138095 | #8 / 0.308594 | fail | no | none |
| `7e887caa-86c9-5024-9f74-84915727b2f8`<br>`7e887caa-86c9-5024-9f74-84915727b2f8` | `family.fire.peep`<br>`doc.fire.peep.v1` | #9 / 0.286048 | #10 / 6.241128 | #8 / 0.138095 | #9 / 0.306641 | fail | no | none |
| `8d0d1fa5-bd7e-5f9b-84d1-6657518666a9`<br>`8d0d1fa5-bd7e-5f9b-84d1-6657518666a9` | `family.visitors.general`<br>`doc.visitors.general.v1` | #22 / 0.204159 | #8 / 7.517679 | #12 / 0.113960 | #10 / 0.255859 | fail | no | none |
| `18782dfe-dce2-55fb-a592-453ae50f292a`<br>`18782dfe-dce2-55fb-a592-453ae50f292a` | `family.fire.south-west-evacuation`<br>`doc.fire.south-west-evacuation.v1` | #13 / 0.242327 | #13 / 5.776345 | #14 / 0.111111 | #11 / 0.242188 | fail | no | none |
| `016e8751-5c0c-58b9-8695-c190270b5921`<br>`016e8751-5c0c-58b9-8695-c190270b5921` | `family.fire.harbour-view-evacuation`<br>`doc.fire.harbour-view-evacuation.v1` | #15 / 0.220893 | #11 / 5.984406 | #13 / 0.112500 | #12 / 0.236328 | fail | no | none |
| `beedfaed-54d3-58fb-a39e-6f6ddafb1ee2`<br>`beedfaed-54d3-58fb-a39e-6f6ddafb1ee2` | `family.fire.midlands-evacuation`<br>`doc.fire.midlands-evacuation.v1` | #11 / 0.270820 | #14 / 5.357381 | #11 / 0.115132 | #13 / 0.218750 | fail | no | none |
| `f43d0e49-6b39-52e7-b51f-a31f3a61bded`<br>`f43d0e49-6b39-52e7-b51f-a31f3a61bded` | `family.fire.north-west-evacuation`<br>`doc.fire.north-west-evacuation.v1` | #12 / 0.261897 | #12 / 5.847756 | #10 / 0.117647 | #14 / 0.217773 | fail | no | none |
| `7f95f220-e025-5338-80d3-7b03ba266b23`<br>`7f95f220-e025-5338-80d3-7b03ba266b23` | `family.medication.prn`<br>`doc.medication.prn.v1` | #18 / 0.211088 | #17 / 5.191892 | #15 / 0.088933 | #15 / 0.211914 | fail | no | none |
| `92a2ee02-39b8-5f45-98c6-136d7223926e`<br>`92a2ee02-39b8-5f45-98c6-136d7223926e` | `family.health-safety.coshh`<br>`doc.health-safety.coshh.v1` | #8 / 0.293148 | — | — | — | fail | no | none |
| `d4825c34-786d-5d7f-80cc-fe26e71b49ee`<br>`d4825c34-786d-5d7f-80cc-fe26e71b49ee` | `family.health-safety.risk-assessment`<br>`doc.health-safety.risk-assessment.v1` | #14 / 0.235011 | — | — | — | fail | no | none |
| `12b916c7-640c-503b-b61f-bfacb74c2965`<br>`12b916c7-640c-503b-b61f-bfacb74c2965` | `family.health-safety.lone-working`<br>`doc.health-safety.lone-working.v1` | #16 / 0.216873 | — | — | — | fail | no | none |
| `18dc4c98-5f8f-5bb0-940f-4feb0711379e`<br>`18dc4c98-5f8f-5bb0-940f-4feb0711379e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #17 / 0.216098 | #39 / 2.231645 | — | — | fail | no | none |
| `dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67`<br>`dd6e053b-b3ac-575c-a69c-b3ffbe5f2f67` | `family.safeguarding.adult-reporting`<br>`doc.safeguarding.adult-reporting.v1` | #19 / 0.209522 | — | — | — | fail | no | none |
| `d172dbd7-0626-5703-a46d-fd0799b13e0b`<br>`d172dbd7-0626-5703-a46d-fd0799b13e0b` | `family.medication.administration`<br>`doc.medication.administration.v2` | #20 / 0.205977 | #38 / 2.253001 | — | — | fail | no | none |
| `b59a32cb-ef08-5cd8-ba0c-999ca32c084e`<br>`b59a32cb-ef08-5cd8-ba0c-999ca32c084e` | `family.hr.lone-worker-welfare`<br>`doc.hr.lone-worker-welfare.v1` | #21 / 0.205773 | #32 / 2.924554 | — | — | fail | no | none |
| `d6652d0a-4abb-5c30-9ffa-05e4e3363d66`<br>`d6652d0a-4abb-5c30-9ffa-05e4e3363d66` | `family.medication.controlled-drugs`<br>`doc.medication.controlled-drugs.v2` | #23 / 0.201462 | #19 / 4.770779 | — | — | fail | no | none |
| `d3f240be-a743-5e01-b638-51555aef0d90`<br>`d3f240be-a743-5e01-b638-51555aef0d90` | `family.medication.errors`<br>`doc.medication.errors.v1` | #24 / 0.200016 | — | — | — | fail | no | none |
| `02a7aca4-b50c-5c17-923d-23bf6aa21c8e`<br>`02a7aca4-b50c-5c17-923d-23bf6aa21c8e` | `family.medication.administration`<br>`doc.medication.administration.v2` | #25 / 0.199158 | — | — | — | fail | no | none |
| `3ebd9d75-7158-5d95-bc6a-b03e18600e17`<br>`3ebd9d75-7158-5d95-bc6a-b03e18600e17` | `family.health-safety.accident-reporting`<br>`doc.health-safety.accident-reporting.v2` | #26 / 0.196833 | #30 / 3.197539 | — | — | fail | no | none |
| `15b09c2b-8407-5133-8b0d-3809dc994f52`<br>`15b09c2b-8407-5133-8b0d-3809dc994f52` | `family.health-safety.slips-trips`<br>`doc.health-safety.slips-trips.v1` | #27 / 0.196665 | — | — | — | fail | no | none |
| `2d930dad-9e70-5175-9658-b291b1185c79`<br>`2d930dad-9e70-5175-9658-b291b1185c79` | `family.infection.respiratory-ppe`<br>`doc.infection.respiratory-ppe.v1` | #28 / 0.191074 | — | — | — | fail | no | none |
| `4fef370a-7ee7-5053-8841-522760b33367`<br>`4fef370a-7ee7-5053-8841-522760b33367` | `family.reference.abbreviations`<br>`doc.reference.abbreviations.v1` | #29 / 0.185846 | — | — | — | fail | no | none |
| `a91e05e6-a248-5380-b8c1-96a65eb90a6d`<br>`a91e05e6-a248-5380-b8c1-96a65eb90a6d` | `family.medication.self-administration`<br>`doc.medication.self-administration.v1` | #30 / 0.185289 | — | — | — | fail | no | none |
| `fd3081c5-985c-5fed-8a0b-df701a242cbd`<br>`fd3081c5-985c-5fed-8a0b-df701a242cbd` | `family.infection.hand-hygiene`<br>`doc.infection.hand-hygiene.v2` | #31 / 0.184642 | — | — | — | fail | no | none |
| `0d176f6c-43fa-5b3e-8390-118fb0a3fb9b`<br>`0d176f6c-43fa-5b3e-8390-118fb0a3fb9b` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | #32 / 0.177059 | — | — | — | fail | no | none |
| `87947e31-1301-56b2-b5ad-cd577479b668`<br>`87947e31-1301-56b2-b5ad-cd577479b668` | `family.infection.ppe`<br>`doc.infection.ppe.v1` | #33 / 0.176424 | — | — | — | fail | no | none |
| `eb30f43c-6344-5b74-8452-f00e906a0b0e`<br>`eb30f43c-6344-5b74-8452-f00e906a0b0e` | `family.payroll.overtime`<br>`doc.payroll.overtime.v1` | #34 / 0.174980 | #25 / 3.917259 | — | — | fail | no | none |
| `7e5de72c-2361-5b0f-8b2b-25512843e880`<br>`7e5de72c-2361-5b0f-8b2b-25512843e880` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | #35 / 0.174875 | #15 / 5.355734 | — | — | fail | no | none |
| `ea27ab1f-00f7-5ad6-b40c-c627a5194f43`<br>`ea27ab1f-00f7-5ad6-b40c-c627a5194f43` | `family.safeguarding.missing-person`<br>`doc.safeguarding.missing-person.v1` | #36 / 0.172174 | — | — | — | fail | no | none |
| `95d0637d-226b-54d6-90fb-0f91e474b7a7`<br>`95d0637d-226b-54d6-90fb-0f91e474b7a7` | `family.medication.administration`<br>`doc.medication.administration.v2` | #37 / 0.170521 | — | — | — | fail | no | none |
| `6c2ac700-8dd3-5559-ab5a-31c493607cc1`<br>`6c2ac700-8dd3-5559-ab5a-31c493607cc1` | `family.hr.recruitment`<br>`doc.hr.recruitment.v1` | #38 / 0.170181 | #40 / 2.076088 | — | — | fail | no | none |
| `b427ff5a-ff1b-5d85-b720-508a713e9189`<br>`b427ff5a-ff1b-5d85-b720-508a713e9189` | `family.safeguarding.whistleblowing`<br>`doc.safeguarding.whistleblowing.v1` | #39 / 0.166586 | — | — | — | fail | no | none |
| `2be6c8de-18de-590f-b51e-32181d86b26c`<br>`2be6c8de-18de-590f-b51e-32181d86b26c` | `family.hr.new-starter-form`<br>`doc.hr.new-starter-form.v1` | #40 / 0.162301 | — | — | — | fail | no | none |
| `b78c33b4-bed9-5520-ab7f-60e53e335fe2`<br>`b78c33b4-bed9-5520-ab7f-60e53e335fe2` | `family.hr.disciplinary`<br>`doc.hr.disciplinary.v1` | — | #16 / 5.232604 | — | — | fail | no | none |
| `4c742841-a2fb-538f-87a1-3220bac131c3`<br>`4c742841-a2fb-538f-87a1-3220bac131c3` | `family.payroll.expenses`<br>`doc.payroll.expenses.v1` | — | #18 / 4.861616 | — | — | fail | no | none |
| `cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e`<br>`cfda9d3d-ee7f-5f3b-8019-dcf6ba7dfb7e` | `family.complaints.handling`<br>`doc.complaints.handling.v2` | — | #20 / 4.477539 | — | — | fail | no | none |
| `e023ac66-af09-57bc-a10e-c7de234b7fd5`<br>`e023ac66-af09-57bc-a10e-c7de234b7fd5` | `family.gdpr.data-protection`<br>`doc.gdpr.data-protection.v2` | — | #21 / 4.439316 | — | — | fail | no | none |
| `540ce899-af96-507c-b3c1-41589d80309d`<br>`540ce899-af96-507c-b3c1-41589d80309d` | `family.hr.sickness-absence`<br>`doc.hr.sickness-absence.v2` | — | #22 / 4.409059 | — | — | fail | no | none |
| `8c1a0372-53db-551c-84ee-0ac73d71e764`<br>`8c1a0372-53db-551c-84ee-0ac73d71e764` | `family.hr.grievance`<br>`doc.hr.grievance.v1` | — | #23 / 4.210609 | — | — | fail | no | none |
| `a173f712-8402-50ce-833c-88315c9494e0`<br>`a173f712-8402-50ce-833c-88315c9494e0` | `family.hr.flexible-working`<br>`doc.hr.flexible-working.v1` | — | #24 / 4.097516 | — | — | fail | no | none |
| `338e005b-3129-5efb-bd25-f6d791b2a245`<br>`338e005b-3129-5efb-bd25-f6d791b2a245` | `family.visitors.outbreak-restrictions`<br>`doc.visitors.outbreak-restrictions.v2` | — | #26 / 3.557996 | — | — | fail | no | none |
| `dfe7812d-2b92-54c4-916e-85a94e0a731a`<br>`dfe7812d-2b92-54c4-916e-85a94e0a731a` | `family.gdpr.retention`<br>`doc.gdpr.retention.v1` | — | #27 / 3.530135 | — | — | fail | no | none |
| `f917e38d-9990-53c0-a5d0-1620c9e37874`<br>`f917e38d-9990-53c0-a5d0-1620c9e37874` | `family.gdpr.subject-access`<br>`doc.gdpr.subject-access.v1` | — | #28 / 3.417348 | — | — | fail | no | none |
| `1a8a973b-338c-56f0-b86b-8eacf25fc069`<br>`1a8a973b-338c-56f0-b86b-8eacf25fc069` | `family.gdpr.cctv`<br>`doc.gdpr.cctv.v1` | — | #29 / 3.337126 | — | — | fail | no | none |
| `aead6f19-4c74-555f-9c5b-f86711197db5`<br>`aead6f19-4c74-555f-9c5b-f86711197db5` | `family.hr.family-leave`<br>`doc.hr.family-leave.v1` | — | #31 / 3.109793 | — | — | fail | no | none |
| `af33ef5c-de96-50df-aff7-c39169062b2d`<br>`af33ef5c-de96-50df-aff7-c39169062b2d` | `family.safeguarding.allegations-staff`<br>`doc.safeguarding.allegations-staff.v2` | — | #33 / 2.820189 | — | — | fail | no | none |
| `34ace103-6749-5efd-849e-920147ebd55e`<br>`34ace103-6749-5efd-849e-920147ebd55e` | `family.gdpr.breach`<br>`doc.gdpr.breach.v1` | — | #34 / 2.710792 | — | — | fail | no | none |
| `8aa6fad2-b29c-5376-8583-c09ad8bcdf41`<br>`8aa6fad2-b29c-5376-8583-c09ad8bcdf41` | `family.hr.annual-leave`<br>`doc.hr.annual-leave.v2` | — | #35 / 2.650353 | — | — | fail | no | none |
| `21cff828-f290-58ed-a01b-faf1547b7403`<br>`21cff828-f290-58ed-a01b-faf1547b7403` | `family.medication.storage`<br>`doc.medication.storage.v1` | — | #36 / 2.370940 | — | — | fail | no | none |
| `0fb5713d-4931-5179-8f6d-f4f9dda3f76b`<br>`0fb5713d-4931-5179-8f6d-f4f9dda3f76b` | `family.safeguarding.mental-capacity`<br>`doc.safeguarding.mental-capacity.v1` | — | #37 / 2.325145 | — | — | fail | no | none |


## Available and missing stage lineage

Available: case_id, variant_id, correctness flags, final per-case metrics, side metrics, covered EvidenceUnit IDs and final operational observations.
Available: question/expectation context, exact candidate-stage lineage and per-side candidate funnels from result.json.

## Decision

Status: **EXPERIMENTAL**

Decision: No human decision recorded.

Generated from `result.json`, `config.json` and optional `comparison.json`; raw JSON is authoritative.
