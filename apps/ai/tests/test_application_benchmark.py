import json
from copy import deepcopy
from pathlib import Path
from typing import Any
from uuid import uuid4

import pytest

from app.evaluation.application_benchmark import compile_application_benchmark_run
from app.evaluation.historical_result import load_comparison_result


def test_application_benchmark_compiler_enforces_engineering_split_and_writes_both_arms(
    tmp_path: Path,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    cases: list[dict[str, Any]] = []
    observations: list[dict[str, Any]] = []
    for case_number in range(42):
        case = {
            "case_id": f"engineering.case-{case_number:02d}",
            "variants": [
                {
                    "variant_id": f"variant-{variant}",
                    "question": f"Question {case_number} {variant}?",
                }
                for variant in range(3)
            ],
            "slices": ["CURRENT"],
            "planner_expectation": {
                "temporal_mode": "CURRENT",
                "valid_at": None,
                "primary_anchor": None,
                "comparison_anchor": None,
                "applicability_reference": {"input": None},
            },
            "eligibility_expectation": {
                "eligible_versions": [],
                "excluded_versions": [],
                "expected_outcome": "NO_ELIGIBLE_EVIDENCE",
            },
            "retrieval_expectation": {"evidence_units": []},
            "outcome_expectation": {"outcome": "NO_ELIGIBLE_EVIDENCE"},
        }
        cases.append(case)
        for variant in case["variants"]:
            assert isinstance(variant, dict)
            arm = {
                "result": {
                    "outcome": "no_eligible_evidence",
                    "candidates": [],
                    "reason": None,
                },
                "trace": {
                    "plan": {
                        "query": variant["question"],
                        "temporal_mode": "current",
                        "valid_at": None,
                        "primary_anchor": None,
                        "comparison_anchor": None,
                        "applicability_reference": None,
                    },
                    "eligibility": {
                        "outcome": "no_eligible_evidence",
                        "document_public_ids_by_side": [],
                        "reason": None,
                    },
                },
            }
            observations.append(
                {
                    "case": case,
                    "variant": variant,
                    "latency_ms": 12.5,
                    "observed_at": "2026-08-11T12:00:00+00:00",
                    "planning": {
                        "status": "succeeded",
                        "provider": "openai",
                        "model": "gpt-5-mini",
                        "attempt_count": 1,
                    },
                    "retrieval_executed": True,
                    "dense": arm,
                    "hybrid": arm,
                }
            )
    fingerprint = "a" * 64
    raw: dict[str, Any] = {
        "schema_version": "v2",
        "run_id": "EXP-0002-adr0022-corrected-planning-baseline",
        "executed_at": "2026-08-11T12:00:00+00:00",
        "repository": {"commit": "b" * 40, "dirty": False},
        "benchmark": {
            "id": "dolved-care-engineering",
            "version": "v2",
            "digest": "c" * 64,
            "evaluation_clock": "2026-08-01T12:00:00+00:00",
            "split_version": "1",
            "engineering_case_ids": [case["case_id"] for case in cases],
        },
        "mapping": {
            "benchmark": {},
            "workspace": {"public_id": str(uuid4())},
            "locations": {},
            "document_families": {},
            "document_versions": {},
            "generations": {
                "embedding_space": {
                    "public_id": str(uuid4()),
                    "profile_fingerprint": fingerprint,
                    "collection_name": "test",
                    "vector_name": "dense",
                    "dimensions": 1024,
                    "distance": "cosine",
                },
                "sparse_space": {
                    "public_id": str(uuid4()),
                    "profile_fingerprint": "d" * 64,
                },
            },
            "provisioning_mapping_digest": "e" * 64,
            "snapshot_digest": "f" * 64,
        },
        "policy": {
            "version": "experimental",
            "fingerprint": "1" * 64,
            "embedding_profile_fingerprint": fingerprint,
            "sparse_profile_fingerprint": "d" * 64,
            "fusion_strategy": "rrf",
            "fusion_version": "1",
            "rrf_k": 60,
            "dense_candidate_k": 40,
            "sparse_candidate_k": 40,
            "fusion_candidate_k": 15,
            "reranker_candidate_k": 15,
            "evidence_threshold": 0.337890625,
            "final_evidence_k": 5,
            "reranker_provider": "voyage",
            "reranker_model": "rerank-2.5",
            "reranker_adapter_version": "1",
        },
        "chunking": {"strategy_name": "structural", "strategy_version": "1"},
        "planner": {
            "provider": "openai",
            "model": "gpt-5-mini",
            "adapter_version": "1",
        },
        "pricing": {
            "embedding_cost_per_million_tokens_usd": 0.12,
            "embedding_pricing_snapshot": "voyage-pricing-2026-08-12",
            "planner_cost_basis": "unavailable",
            "reranker_cost_basis": "unavailable",
            "generation": "not_executed",
        },
        "observations": observations,
    }
    raw["observations"][2].update(
        {
            "planning": {
                "status": "failed",
                "provider": "openai",
                "model": "gpt-5-mini",
                "failure_category": "invalid_typed_plan",
                "provider_status": 200,
                "attempt_count": 1,
            },
            "retrieval_executed": False,
            "dense": None,
            "hybrid": None,
        }
    )
    for arm_name in ("dense", "hybrid"):
        raw["observations"][3][arm_name]["trace"]["plan"]["temporal_mode"] = (
            "clarification_required"
        )
    raw_path = tmp_path / "application-observations.json"
    raw_path.write_text(json.dumps(raw))
    truth_path = tmp_path / "planner-expectations.json"
    truth_path.write_text(
        json.dumps(
            {
                "schema_version": "v2",
                "scope": "engineering_tuning",
                "expectations": [
                    {
                        "case_id": case["case_id"],
                        "variant_id": variant["variant_id"],
                        "contract": {
                            "contract_version": 2,
                            "temporal_mode": "current",
                            "explicit_date": None,
                            "temporal_reference": None,
                            "location_references": [],
                            "clarification_reason": None,
                        },
                    }
                    for case in cases
                    for variant in case["variants"]
                    if isinstance(variant, dict)
                ],
            }
        )
    )
    monkeypatch.setattr(
        "app.evaluation.application_benchmark.PLANNER_EXPECTATIONS_PATH",
        truth_path,
    )

    result = compile_application_benchmark_run(
        raw_path=raw_path,
        output_directory=tmp_path / "run",
        planner={"provider": "openai", "model": "gpt-5-mini", "adapter_version": "1"},
    )

    assert result["dense"]["aggregate"]["case_count"] == 42
    assert result["hybrid"]["aggregate"]["case_count"] == 42
    assert len(result["hybrid"]["variants"]) == 126
    assert result["hybrid"]["aggregate"]["planner_failure_count"] == 1
    assert result["hybrid"]["aggregate"]["planner_success_count"] == 125
    assert result["hybrid"]["aggregate"]["planner_reliability"] == 125 / 126
    assert result["hybrid"]["aggregate"]["planner_accuracy"] == pytest.approx(124 / 126)
    assert result["hybrid"]["aggregate"]["retrieval_metric_variant_count"] == 125
    assert result["hybrid"]["aggregate"]["planner_failure_categories"] == {
        "invalid_typed_plan": 1
    }
    failed = result["hybrid"]["variants"][2]
    assert failed["metrics"] is None
    assert failed["retrieval_executed"] is False
    assert failed["planner_failure"]["provider_status"] == 200
    assert failed["planner_evaluation"]["actual_plan"] is None
    assert failed["planner_evaluation"]["differences"][0]["field"] == "validated_plan"
    mismatch = result["hybrid"]["variants"][3]
    assert mismatch["planner_evaluation"]["differences"] == [
        {
            "field": "temporal_mode",
            "expected": "CURRENT",
            "actual": "CLARIFICATION_REQUIRED",
            "classification": "SEMANTIC_AFTER_NORMALISATION",
        }
    ]
    known_good = result["hybrid"]["variants"][0]
    assert known_good["planner_evaluation"]["correct"] is True
    assert known_good["planner_evaluation"]["differences"] == []
    assert (
        result["classifier_and_resolution"]["classifier"][
            "structured_response_reliability"
        ]
        == 125 / 126
    )
    assert result["classifier_and_resolution"]["classifier"]["false_compare"] == 0
    assert result["operational"]["experiment"]["generation"]["execution"] == (
        "NOT_EXECUTED"
    )
    assert (tmp_path / "run" / "result.json").is_file()
    assert (tmp_path / "run" / "comparison.json").is_file()
    assert (tmp_path / "run" / "provisioning-mapping.json").is_file()

    baseline_path = tmp_path / "exp-0001-result.json"
    baseline_path.write_text(json.dumps(result))
    compile_application_benchmark_run(
        raw_path=raw_path,
        output_directory=tmp_path / "run-with-historical-baseline",
        planner={"provider": "openai", "model": "gpt-5-mini", "adapter_version": "1"},
        historical_baseline_path=baseline_path,
    )
    comparison = json.loads(
        (tmp_path / "run-with-historical-baseline" / "comparison.json").read_text()
    )
    assert comparison["baseline_experiment_id"] == result["hybrid"]["experiment_id"]
    assert comparison["within_run_dense_comparison"]["baseline_experiment_id"].endswith(
        "-dense-control"
    )
    assert "historical_unavailable_fields" not in comparison

    historical_v1 = deepcopy(result)
    historical_v1["schema_version"] = "v1"
    historical_v1["hybrid"]["schema_version"] = "v1"
    historical_v1_path = tmp_path / "exp-0001-v1-result.json"
    historical_v1_path.write_text(json.dumps(historical_v1))
    compile_application_benchmark_run(
        raw_path=raw_path,
        output_directory=tmp_path / "run-with-v1-historical-baseline",
        planner={"provider": "openai", "model": "gpt-5-mini", "adapter_version": "1"},
        historical_baseline_path=historical_v1_path,
    )
    v1_comparison = json.loads(
        (tmp_path / "run-with-v1-historical-baseline" / "comparison.json").read_text()
    )
    assert v1_comparison["baseline_experiment_id"] == result["hybrid"]["experiment_id"]
    assert v1_comparison["historical_unavailable_fields"] == {
        "generation_usage": None,
        "planner_contract_schema_version": None,
        "pricing": None,
        "stage_usage": None,
    }


def test_comparison_loader_leaves_native_v2_result_unchanged() -> None:
    raw = _minimal_result("v2")
    before = deepcopy(raw)

    loaded = load_comparison_result(raw)

    assert loaded.schema_version == "v2"
    assert loaded.unavailable_fields is None
    assert raw == before


def test_comparison_loader_validates_and_adapts_v1_without_inventing_v2_fields() -> (
    None
):
    raw = _minimal_result("v1")

    loaded = load_comparison_result(raw)

    assert loaded.schema_version == "v1"
    assert loaded.aggregate.metrics is not None
    assert loaded.unavailable_fields == {
        "generation_usage": None,
        "planner_contract_schema_version": None,
        "pricing": None,
        "stage_usage": None,
    }


def _minimal_result(schema_version: str) -> dict[str, Any]:
    return {
        "schema_version": schema_version,
        "experiment_id": "historical-result",
        "executed_at": "2026-08-01T12:00:00+00:00",
        "candidate_k": 5,
        "lineage": {
            "repository_commit": "a" * 40,
            "corpus_version": "v2",
            "corpus_digest": "b" * 64,
            "policy_version": "policy-v1",
            "policy_digest": "c" * 64,
            "harness_version": "harness-v1",
            "matching_algorithm": "matching-v1",
            "planner": {"provider": "openai", "model": "gpt-5-mini"},
            "embedding_profile_fingerprint": "d" * 64,
            "chunking_configuration": {},
            "retrieval_configuration": {},
            "evaluator": None,
            "trial_count": 1,
        },
        "aggregate": _minimal_aggregate(),
        "slices": {"CURRENT": _minimal_aggregate()},
        "variants": [],
        "hard_failures": [],
        "model_assisted": [],
    }


def _minimal_aggregate() -> dict[str, Any]:
    return {
        "metrics": {
            "recall_at_k": 1.0,
            "precision_at_k": 1.0,
            "mrr": 1.0,
            "ndcg_at_k": 1.0,
        },
        "planner_accuracy": 1.0,
        "eligibility_accuracy": 1.0,
        "outcome_accuracy": 1.0,
        "case_count": 1,
        "variant_count": 1,
        "retrieval_metric_variant_count": 1,
        "planner_success_count": 1,
        "planner_failure_count": 0,
        "planner_reliability": 1.0,
        "planner_failure_categories": {},
    }
