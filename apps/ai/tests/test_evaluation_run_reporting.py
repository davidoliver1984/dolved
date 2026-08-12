import hashlib
import json
from pathlib import Path
from typing import Any

import pytest

from app.evaluation.run_reporting import (
    generate_run_report,
    update_experiment_index,
    write_comparison,
)


def write_json(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, indent=2) + "\n")


def result(experiment_id: str, *, recall: float = 0.8) -> dict[str, Any]:
    metrics = {
        "recall_at_k": recall,
        "precision_at_k": 0.5,
        "mrr": 0.75,
        "ndcg_at_k": 0.7,
    }
    aggregate = {
        "metrics": metrics,
        "planner_accuracy": 1.0,
        "eligibility_accuracy": 1.0,
        "outcome_accuracy": 1.0,
        "case_count": 1,
    }
    return {
        "schema_version": "v1",
        "experiment_id": experiment_id,
        "executed_at": "2026-08-11T10:00:00Z",
        "candidate_k": 5,
        "lineage": {
            "repository_commit": "a" * 40,
            "corpus_version": "1",
            "corpus_digest": "b" * 64,
            "policy_version": "1",
            "policy_digest": "c" * 64,
            "harness_version": "retrieval-evaluation-v1",
            "matching_algorithm": "normalised-token-coverage-v1",
            "planner": {"provider": "fake", "model": "fixed-v1"},
            "embedding_profile_fingerprint": "d" * 64,
            "chunking_configuration": {"strategy": "structure-aware"},
            "retrieval_configuration": {"method": "hybrid"},
            "evaluator": None,
            "trial_count": 1,
        },
        "aggregate": aggregate,
        "slices": {"CURRENT": aggregate},
        "variants": [
            {
                "case_id": "case.current",
                "variant_id": "direct",
                "metrics": metrics,
                "side_metrics": {"PRIMARY": metrics},
                "covered_evidence_ids": ["evidence.current"],
                "planner_correct": False,
                "eligibility_correct": True,
                "outcome_correct": True,
                "hard_failures": [],
                "operational": {
                    "latency_ms": 12.5,
                    "token_usage": 20,
                    "provider_cost": 0.001,
                    "request_count": 2,
                },
                "text_capture_mode": "BENCHMARK_TEXT",
                "question": "What changed in the current policy?",
                "expected_outcome": "EVIDENCE_FOUND",
                "expected_evidence": [
                    {
                        "evidence_unit_id": "evidence.current",
                        "document_family_id": "family.policy",
                        "document_version_id": "policy.v2",
                        "side": "PRIMARY",
                        "source_path": "documents/policy-v2.md",
                    },
                    {
                        "evidence_unit_id": "evidence.previous",
                        "document_family_id": "family.policy",
                        "document_version_id": "policy.v1",
                        "side": "COMPARISON",
                        "source_path": "documents/policy-v1.md",
                    },
                ],
                "candidate_lineage": [
                    {
                        "candidate_id": "candidate.current",
                        "chunk_id": "11111111-1111-4111-8111-111111111111",
                        "document_family_id": "family.policy",
                        "document_version_id": "policy.v2",
                        "side": "PRIMARY",
                        "dense_rank": 1,
                        "dense_score": 0.81,
                        "sparse_rank": 2,
                        "sparse_score": 4.2,
                        "fused_rank": 1,
                        "fused_score": 0.0325,
                        "reranker_rank": 1,
                        "reranker_score": 0.72,
                        "passed_evidence_threshold": True,
                        "included_in_final_evidence": True,
                        "covered_evidence_unit_ids": ["evidence.current"],
                    },
                    {
                        "candidate_id": "candidate.previous",
                        "chunk_id": "22222222-2222-4222-8222-222222222222",
                        "document_family_id": "family.policy",
                        "document_version_id": "policy.v1",
                        "side": "COMPARISON",
                        "dense_rank": 2,
                        "dense_score": 0.75,
                        "sparse_rank": None,
                        "sparse_score": None,
                        "fused_rank": 2,
                        "fused_score": 0.0161,
                        "reranker_rank": 1,
                        "reranker_score": 0.69,
                        "passed_evidence_threshold": True,
                        "included_in_final_evidence": True,
                        "covered_evidence_unit_ids": ["evidence.previous"],
                    },
                ],
                "candidate_funnel": [
                    {
                        "side": "PRIMARY",
                        "dense_candidate_count": 4,
                        "sparse_candidate_count": 3,
                        "unique_post_fusion_count": 5,
                        "candidates_sent_to_reranker": 5,
                        "candidates_surviving_threshold": 2,
                        "final_evidence_count": 1,
                    },
                    {
                        "side": "COMPARISON",
                        "dense_candidate_count": 3,
                        "sparse_candidate_count": 2,
                        "unique_post_fusion_count": 4,
                        "candidates_sent_to_reranker": 4,
                        "candidates_surviving_threshold": 1,
                        "final_evidence_count": 1,
                    },
                ],
                "planner_evaluation": {
                    "expected_contract": {"temporal_mode": "CURRENT"},
                    "actual_plan": {"temporal_mode": "CLARIFICATION_REQUIRED"},
                    "differences": [
                        {
                            "field": "temporal_mode",
                            "expected": "CURRENT",
                            "actual": "CLARIFICATION_REQUIRED",
                            "classification": "SEMANTIC_AFTER_NORMALISATION",
                        }
                    ],
                    "correct": False,
                },
            }
        ],
        "hard_failures": [],
        "model_assisted": [],
    }


def config(run_id: str, *, status: str = "EXPERIMENTAL") -> dict[str, Any]:
    return {
        "schema_version": "v1",
        "run_id": run_id,
        "description": "Deterministic reporting test",
        "status": status,
        "decision": None,
        "repository": {"commit": "a" * 40, "dirty": False},
        "benchmark": {
            "id": "dolved-care-engineering",
            "version": "1",
            "digest": "e" * 64,
        },
        "corpus": {"version": "1", "digest": "b" * 64},
        "split": {"version": "1", "digest": "f" * 64},
        "harness_version": "retrieval-evaluation-v1",
        "threshold_policy_identity": "threshold-policy-v1",
        "providers": {
            "dense": {
                "provider": "voyage",
                "model": "voyage-4-large",
                "embedding_profile_fingerprint": "d" * 64,
                "dimensions": 1024,
                "adapter_version": "1",
            },
            "sparse": {
                "provider": "fastembed",
                "model": "splade",
                "sparse_profile_fingerprint": "e" * 64,
                "model_revision": "revision-v1",
                "adapter_version": "1",
            },
            "fusion": {"strategy": "rrf", "version": "1", "rrf_k": 60},
            "reranking": {
                "provider": "voyage",
                "model": "rerank-2.5",
                "adapter_version": "1",
            },
        },
        "candidate_pipeline": {
            "dense_candidate_k": 40,
            "sparse_candidate_k": 40,
            "fusion_candidate_k": 15,
            "reranker_candidate_k": 15,
            "evidence_threshold": 0.337890625,
            "final_evidence_k": 5,
        },
    }


def create_run(root: Path, run_id: str, *, recall: float = 0.8) -> Path:
    run_dir = root / run_id
    write_json(run_dir / "config.json", config(run_id))
    write_json(run_dir / "result.json", result(run_id, recall=recall))
    return run_dir


def test_reports_are_deterministic_offline_and_preserve_human_notes(
    tmp_path: Path,
) -> None:
    runs_root = tmp_path / "runs"
    run_dir = create_run(runs_root, "EXP-0001-reporting")
    notes = run_dir / "notes.md"
    notes.write_text("# Human decision\n\nKeep this text.\n")

    generate_run_report(run_dir)
    first_markdown = hashlib.sha256((run_dir / "report.md").read_bytes()).hexdigest()
    first_html = hashlib.sha256((run_dir / "report.html").read_bytes()).hexdigest()
    generate_run_report(run_dir)

    assert (
        hashlib.sha256((run_dir / "report.md").read_bytes()).hexdigest()
        == first_markdown
    )
    assert (
        hashlib.sha256((run_dir / "report.html").read_bytes()).hexdigest() == first_html
    )
    assert notes.read_text() == "# Human decision\n\nKeep this text.\n"
    assert "voyage-4-large" in (run_dir / "report.md").read_text()
    html = (run_dir / "report.html").read_text()
    assert "Plotly.newPlot" in html
    assert '<script src="https://cdn.plot.ly' not in html
    assert "What changed in the current policy?" in html
    assert "Dense" in html
    assert "Sparse" in html
    assert "RRF" in html
    assert "Reranker" in html
    assert "Threshold" in html
    assert "Final evidence" in html
    assert "candidate.current" in html
    assert "PRIMARY" in html
    assert "COMPARISON" in html
    assert "Candidate-stage counts are not present" not in html
    assert "Planner contract comparison" in html
    assert "SEMANTIC_AFTER_NORMALISATION" in html
    assert "Classifier and Laravel resolution" in html


def test_comparison_is_persisted_and_reports_metric_deltas(tmp_path: Path) -> None:
    run_dir = create_run(tmp_path / "runs", "EXP-0002-candidate", recall=0.9)
    baseline_path = tmp_path / "baseline.json"
    write_json(baseline_path, result("baseline-v1", recall=0.7))

    comparison = write_comparison(run_dir, baseline_path)
    generate_run_report(run_dir)

    assert comparison["metrics"]["recall_at_k"]["delta"] == pytest.approx(0.2)
    assert json.loads((run_dir / "comparison.json").read_text()) == comparison
    assert "baseline-v1" in (run_dir / "report.md").read_text()


def test_planner_failure_is_prominent_and_has_no_fabricated_retrieval_metrics(
    tmp_path: Path,
) -> None:
    run_dir = tmp_path / "runs/EXP-0001-planner-failure"
    failed = result("EXP-0001-planner-failure")
    failed["aggregate"] = {
        "metrics": None,
        "planner_accuracy": 0.0,
        "eligibility_accuracy": None,
        "outcome_accuracy": None,
        "case_count": 1,
        "variant_count": 1,
        "retrieval_metric_variant_count": 0,
        "planner_success_count": 0,
        "planner_failure_count": 1,
        "planner_reliability": 0.0,
        "planner_failure_categories": {"invalid_typed_plan": 1},
    }
    failed["slices"] = {"CURRENT": failed["aggregate"]}
    variant = failed["variants"][0]
    variant.update(
        {
            "metrics": None,
            "side_metrics": {},
            "covered_evidence_ids": [],
            "planner_correct": False,
            "eligibility_correct": None,
            "outcome_correct": None,
            "hard_failures": ["planner_failure:invalid_typed_plan:case.current:direct"],
            "candidate_lineage": [],
            "candidate_funnel": [],
            "planning_status": "FAILED",
            "retrieval_executed": False,
            "contributes_retrieval_metrics": False,
            "planner_failure": {
                "provider": "openai",
                "model": "gpt-5-mini",
                "category": "invalid_typed_plan",
                "provider_status": 200,
                "attempt_count": 1,
                "occurred_at": "2026-08-11T10:00:00Z",
            },
        }
    )
    failed["hard_failures"] = variant["hard_failures"]
    write_json(run_dir / "config.json", config(run_dir.name))
    write_json(run_dir / "result.json", failed)

    generate_run_report(run_dir)

    markdown = (run_dir / "report.md").read_text()
    html = (run_dir / "report.html").read_text()
    assert "Planner reliability" in markdown
    assert "invalid_typed_plan" in markdown
    assert "Retrieval executed: `False`" in markdown
    assert "Metrics: recall=n/a" in markdown
    assert "Planner reliability" in html
    assert "contributes retrieval metrics:</strong> false" in html


def test_experiment_index_retains_every_run(tmp_path: Path) -> None:
    runs_root = tmp_path / "runs"
    create_run(runs_root, "EXP-0001-first")
    second = create_run(runs_root, "EXP-0002-second")
    second_config = config("EXP-0002-second", status="PROMOTED")
    second_config["decision"] = "Promoted after human review"
    write_json(second / "config.json", second_config)
    index_path = tmp_path / "EXPERIMENTS.md"

    update_experiment_index(runs_root, index_path)

    index = index_path.read_text()
    assert "EXP-0001-first" in index
    assert "EXP-0002-second" in index
    assert "promoted" in index


def test_run_id_must_match_directory_name(tmp_path: Path) -> None:
    run_dir = tmp_path / "runs/EXP-0003-correct"
    write_json(run_dir / "config.json", config("EXP-9999-wrong"))
    write_json(run_dir / "result.json", result("result-v1"))

    try:
        generate_run_report(run_dir)
    except ValueError as error:
        assert "run_id must match" in str(error)
    else:
        raise AssertionError("mismatched run identity was accepted")
