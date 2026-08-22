"""Run or compare repository-owned retrieval evaluation experiments."""

from __future__ import annotations

import argparse
import asyncio
import json
import sys
from datetime import UTC, datetime
from pathlib import Path
from typing import Any

from app.evaluation.canonical import content_digest
from app.evaluation.current_retrieval import load_current_retrieval_inputs
from app.evaluation.governance import (
    assess_gate,
    promote_baseline,
    record_gate_decision,
    verify_baseline_identity,
    verify_deterministic_profile_digest,
    verify_promoted_deterministic_baseline,
    verify_checksum_manifest,
)
from app.evaluation.harness import RetrievalEvaluationHarness
from app.evaluation.historical_result import load_comparison_result
from app.evaluation.live_hybrid_retrieval import evaluate_live_hybrid_retrieval
from app.evaluation.model_assisted import (
    FakeModelAssistedEvaluator,
    evaluate_recorded_contexts,
)
from app.evaluation.models import (
    BaselinePromotion,
    EvaluationCorpus,
    EvaluationTextCaptureMode,
    ExperimentLineage,
    ExperimentResult,
    GateDecision,
    QualityGatePolicy,
    VariantObservation,
)
from app.evaluation.reporting import comparison_report, deterministic_candidate_report
from app.retrieval.models import HybridRetrievalConfiguration
from app.settings import get_settings


def load_json(path: Path) -> Any:
    return json.loads(path.read_text())


def write_model(path: Path, model: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(model.model_dump_json(indent=2) + "\n")


def run(args: argparse.Namespace) -> None:
    corpus_data = load_json(args.corpus)
    policy_data = load_json(args.policy)
    input_data = load_json(args.observations)
    corpus = EvaluationCorpus.model_validate(corpus_data)
    policy = QualityGatePolicy.model_validate(policy_data)
    lineage = ExperimentLineage(
        repository_commit=args.repository_commit,
        corpus_version=corpus.corpus_version,
        corpus_digest=content_digest(corpus_data),
        policy_version=policy.policy_version,
        policy_digest=content_digest(policy_data),
        harness_version=RetrievalEvaluationHarness.VERSION,
        matching_algorithm=corpus.matching_algorithm,
        **input_data["lineage"],
    )
    observations = tuple(
        VariantObservation.model_validate(item) for item in input_data["observations"]
    )
    result = RetrievalEvaluationHarness().evaluate(
        experiment_id=input_data["experiment_id"],
        corpus=corpus,
        observations=observations,
        lineage=lineage,
        candidate_k=input_data["candidate_k"],
        executed_at=datetime.now(UTC),
    )
    model_assisted = asyncio.run(
        evaluate_recorded_contexts(
            evaluator=FakeModelAssistedEvaluator(),
            corpus=corpus,
            observations=observations,
        )
    )
    result = result.model_copy(update={"model_assisted": model_assisted})
    write_model(args.output, result)


def compare(args: argparse.Namespace) -> None:
    candidate = ExperimentResult.model_validate(load_json(args.candidate))
    baseline = load_comparison_result(load_json(args.baseline))
    promotion = BaselinePromotion.model_validate(load_json(args.promotion))
    policy = QualityGatePolicy.model_validate(load_json(args.policy))
    verify_baseline_identity(candidate, promotion)
    passed, failures = assess_gate(candidate, baseline, policy)
    report = comparison_report(candidate, baseline)
    report += f"\n## Gate assessment\n\nStatus: **{'PASS' if passed else 'FAIL'}**\n"
    if failures:
        report += "\n" + "\n".join(f"- `{item}`" for item in failures) + "\n"
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(report)
    if not passed:
        print(f"Evaluation policy gate failed: {failures}", file=sys.stderr)
        raise SystemExit(1)


def promote(args: argparse.Namespace) -> None:
    experiment = ExperimentResult.model_validate(load_json(args.experiment))
    promotion = promote_baseline(
        experiment,
        promoted_by=args.promoted_by,
        reason=args.reason,
    )
    write_model(args.output, promotion)


def gate(args: argparse.Namespace) -> None:
    record = record_gate_decision(
        experiment_id=args.experiment_id,
        decision=GateDecision(args.decision),
        reviewer=args.reviewer,
        reason=args.reason,
        decided_at=datetime.now(UTC),
        waiver_expires_at=(
            datetime.fromisoformat(args.waiver_expires_at)
            if args.waiver_expires_at
            else None
        ),
    )
    write_model(args.output, record)


def live_hybrid(args: argparse.Namespace) -> None:
    corpus_data = load_json(args.corpus)
    policy_data = load_json(args.policy)
    result = evaluate_live_hybrid_retrieval(
        settings=get_settings(),
        corpus=EvaluationCorpus.model_validate(corpus_data),
        corpus_data=corpus_data,
        quality_policy=QualityGatePolicy.model_validate(policy_data),
        quality_policy_data=policy_data,
        repository_revision=args.repository_commit,
        evidence_threshold=args.evidence_threshold,
        configuration=HybridRetrievalConfiguration(
            version="experimental-v1",
            dense_candidate_k=40,
            sparse_candidate_k=40,
            fusion_candidate_k=15,
            reranker_candidate_k=15,
            final_evidence_k=5,
            rrf_k=60,
        ),
        rerank_delay_seconds=args.rerank_delay_seconds,
        text_capture_mode=EvaluationTextCaptureMode(args.text_capture_mode),
    )
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result.as_json(), indent=2) + "\n")


def retrieval_current(args: argparse.Namespace) -> None:
    settings = get_settings()
    providers = {
        settings.embedding_provider,
        settings.sparse_embedding_provider,
        settings.reranker_provider,
        settings.retrieval_planner_provider,
    }
    if settings.environment not in {"e2e", "evaluation-current"} or providers != {
        "deterministic"
    }:
        raise SystemExit(
            "Current retrieval evaluation requires the complete isolated deterministic profile."
        )
    if any(
        secret.get_secret_value().strip()
        for secret in (
            settings.voyage_api_key,
            settings.retrieval_planner_api_key,
            settings.contextualiser_api_key,
            settings.generation_openai_api_key,
        )
    ):
        raise SystemExit(
            "Provider credentials must be absent from current retrieval evaluation."
        )

    policy_data = load_json(args.policy)
    inputs = load_current_retrieval_inputs(
        snapshot_path=args.corpus,
        document_catalog_path=args.document_catalog,
        organisation_path=args.organisation,
        source_root=args.source_root,
        checksums_path=args.source_checksums,
        plan_catalogue_path=args.plan_catalogue,
        eligibility_artifact_path=args.eligibility_artifact,
        repository_commit=args.repository_commit,
    )
    corpus_data = inputs.corpus_data
    corpus = inputs.corpus
    questions = {variant.question for case in corpus.cases for variant in case.variants}
    if inputs.plan_questions != questions:
        missing = sorted(questions - inputs.plan_questions)
        unexpected = sorted(inputs.plan_questions - questions)
        raise SystemExit(
            f"Authored plan catalogue identity mismatch; missing={missing}, unexpected={unexpected}"
        )

    result = evaluate_live_hybrid_retrieval(
        settings=settings,
        corpus=corpus,
        corpus_data=corpus_data,
        quality_policy=QualityGatePolicy.model_validate(policy_data),
        quality_policy_data=policy_data,
        repository_revision=args.repository_commit,
        evidence_threshold=0.337890625,
        configuration=HybridRetrievalConfiguration(
            version="deterministic-current-v1",
            dense_candidate_k=40,
            sparse_candidate_k=40,
            fusion_candidate_k=15,
            reranker_candidate_k=15,
            final_evidence_k=5,
            rrf_k=5,
        ),
        rerank_delay_seconds=0,
        text_capture_mode=EvaluationTextCaptureMode.BENCHMARK_TEXT,
        plan_catalogue_checksum=inputs.plan_catalogue_checksum,
        experiment_id_prefix="r22-s03-deterministic-current",
        evaluation_chunks=inputs.chunks,
        eligibility_scopes=inputs.scopes,
        eligibility_outcomes={
            identity: entry.outcome for identity, entry in inputs.resolutions.items()
        },
        eligibility_correctness=inputs.eligibility_correct,
        current_lineage=inputs.lineage,
    )
    verify_deterministic_profile_digest(result.hybrid)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result.as_json(), indent=2) + "\n")
    write_model(args.candidate_output, result.hybrid)
    args.report_output.parent.mkdir(parents=True, exist_ok=True)
    args.report_output.write_text(
        deterministic_candidate_report(result.hybrid, operational=result.operational)
    )


def compare_deterministic(args: argparse.Namespace) -> None:
    verify_checksum_manifest(
        args.checksums,
        required=frozenset({"experiment-result.json", "baseline-promotion.json"}),
    )
    candidate = ExperimentResult.model_validate(load_json(args.candidate))
    baseline = ExperimentResult.model_validate(load_json(args.baseline))
    promotion = BaselinePromotion.model_validate(load_json(args.promotion))
    policy = QualityGatePolicy.model_validate(load_json(args.policy))
    verify_promoted_deterministic_baseline(baseline, promotion)
    verify_deterministic_profile_digest(candidate)
    verify_baseline_identity(candidate, promotion)
    passed, failures = assess_gate(candidate, baseline, policy)
    report = comparison_report(candidate, baseline)
    report += f"\n## Gate assessment\n\nStatus: **{'PASS' if passed else 'FAIL'}**\n"
    if failures:
        report += "\n" + "\n".join(f"- `{item}`" for item in failures) + "\n"
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(report)
    if not passed:
        print(f"Deterministic retrieval gate failed: {failures}", file=sys.stderr)
        raise SystemExit(1)


def parser() -> argparse.ArgumentParser:
    root = argparse.ArgumentParser()
    commands = root.add_subparsers(required=True)
    run_parser = commands.add_parser("run")
    run_parser.add_argument("--corpus", type=Path, required=True)
    run_parser.add_argument("--policy", type=Path, required=True)
    run_parser.add_argument("--observations", type=Path, required=True)
    run_parser.add_argument("--output", type=Path, required=True)
    run_parser.add_argument("--repository-commit", required=True)
    run_parser.set_defaults(handler=run)
    compare_parser = commands.add_parser("compare")
    compare_parser.add_argument("--candidate", type=Path, required=True)
    compare_parser.add_argument("--baseline", type=Path, required=True)
    compare_parser.add_argument("--promotion", type=Path, required=True)
    compare_parser.add_argument("--policy", type=Path, required=True)
    compare_parser.add_argument("--output", type=Path, required=True)
    compare_parser.set_defaults(handler=compare)
    promote_parser = commands.add_parser("promote")
    promote_parser.add_argument("--experiment", type=Path, required=True)
    promote_parser.add_argument("--promoted-by", required=True)
    promote_parser.add_argument("--reason", required=True)
    promote_parser.add_argument("--output", type=Path, required=True)
    promote_parser.set_defaults(handler=promote)
    gate_parser = commands.add_parser("gate")
    gate_parser.add_argument("--experiment-id", required=True)
    gate_parser.add_argument(
        "--decision", choices=[item.value for item in GateDecision], required=True
    )
    gate_parser.add_argument("--reviewer", required=True)
    gate_parser.add_argument("--reason", required=True)
    gate_parser.add_argument("--waiver-expires-at")
    gate_parser.add_argument("--output", type=Path, required=True)
    gate_parser.set_defaults(handler=gate)
    live_parser = commands.add_parser("live-hybrid")
    live_parser.add_argument("--corpus", type=Path, required=True)
    live_parser.add_argument("--policy", type=Path, required=True)
    live_parser.add_argument("--output", type=Path, required=True)
    live_parser.add_argument("--repository-commit", required=True)
    live_parser.add_argument("--evidence-threshold", type=float, required=True)
    live_parser.add_argument("--rerank-delay-seconds", type=float, default=25)
    live_parser.add_argument(
        "--text-capture-mode",
        choices=tuple(EvaluationTextCaptureMode),
        default=EvaluationTextCaptureMode.DISABLED,
        help=(
            "Defaults to privacy-safe disabled capture; BENCHMARK_TEXT is only "
            "for approved fictional corpora."
        ),
    )
    live_parser.set_defaults(handler=live_hybrid)
    current_parser = commands.add_parser("retrieval-current")
    current_parser.add_argument("--corpus", type=Path, required=True)
    current_parser.add_argument("--policy", type=Path, required=True)
    current_parser.add_argument("--plan-catalogue", type=Path, required=True)
    current_parser.add_argument("--document-catalog", type=Path, required=True)
    current_parser.add_argument("--organisation", type=Path, required=True)
    current_parser.add_argument("--source-root", type=Path, required=True)
    current_parser.add_argument("--source-checksums", type=Path, required=True)
    current_parser.add_argument("--eligibility-artifact", type=Path, required=True)
    current_parser.add_argument("--output", type=Path, required=True)
    current_parser.add_argument("--candidate-output", type=Path, required=True)
    current_parser.add_argument("--report-output", type=Path, required=True)
    current_parser.add_argument("--repository-commit", required=True)
    current_parser.set_defaults(handler=retrieval_current)
    deterministic_parser = commands.add_parser("compare-deterministic")
    deterministic_parser.add_argument("--candidate", type=Path, required=True)
    deterministic_parser.add_argument("--baseline", type=Path, required=True)
    deterministic_parser.add_argument("--promotion", type=Path, required=True)
    deterministic_parser.add_argument("--checksums", type=Path, required=True)
    deterministic_parser.add_argument("--policy", type=Path, required=True)
    deterministic_parser.add_argument("--output", type=Path, required=True)
    deterministic_parser.set_defaults(handler=compare_deterministic)
    return root


if __name__ == "__main__":
    arguments = parser().parse_args()
    arguments.handler(arguments)
