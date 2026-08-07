"""Run or compare repository-owned retrieval evaluation experiments."""

from __future__ import annotations

import argparse
import asyncio
import json
from datetime import UTC, datetime
from pathlib import Path
from typing import Any

from app.evaluation.canonical import content_digest
from app.evaluation.governance import (
    assess_gate,
    promote_baseline,
    record_gate_decision,
    verify_baseline_identity,
)
from app.evaluation.harness import RetrievalEvaluationHarness
from app.evaluation.model_assisted import (
    FakeModelAssistedEvaluator,
    evaluate_recorded_contexts,
)
from app.evaluation.models import (
    BaselinePromotion,
    EvaluationCorpus,
    ExperimentLineage,
    ExperimentResult,
    GateDecision,
    QualityGatePolicy,
    VariantObservation,
)
from app.evaluation.reporting import comparison_report


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
    baseline = ExperimentResult.model_validate(load_json(args.baseline))
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
    return root


if __name__ == "__main__":
    arguments = parser().parse_args()
    arguments.handler(arguments)
