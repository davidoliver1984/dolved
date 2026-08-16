"""Reconcile application-owned benchmark observations with source-owned truth."""

from __future__ import annotations

import json
from datetime import datetime
from pathlib import Path
from typing import Any, Literal
from uuid import UUID

from app.evaluation.canonical import content_digest
from app.evaluation.harness import RetrievalEvaluationHarness
from app.evaluation.historical_result import ComparisonResult, load_comparison_result
from app.evaluation.matching import candidate_covers
from app.evaluation.models import (
    CandidateFunnel,
    CandidateStageLineage,
    CostBasis,
    EvaluationCase,
    EvaluationCorpus,
    EvaluationTextCaptureMode,
    EvidenceUnit,
    ExpectedEvidenceIdentity,
    ExperimentLineage,
    ExperimentResult,
    OperationalObservation,
    PlannerEvaluationObservation,
    PlannerFailureObservation,
    PlannerFieldDifference,
    PlanningStatus,
    QuestionVariant,
    RetrievalFailureObservation,
    RetrievedCandidate,
    StageUsageObservation,
    VariantObservation,
)
from app.evaluation.planner_comparison import (
    PlannerComparison,
    compare_planner_contract,
)
from app.evaluation.usage_reporting import aggregate_usage

PLANNER_EXPECTATIONS_PATH = Path(
    "/evaluation/planner-expectations/v2/engineering-expectations.json"
)


def compile_application_benchmark_run(
    *,
    raw_path: Path,
    output_directory: Path,
    planner: dict[str, Any],
    historical_baseline_path: Path | None = None,
    planner_expectations_path: Path | None = None,
) -> dict[str, Any]:
    raw = _object(json.loads(raw_path.read_text()), "application observations")
    observations = _list(raw.get("observations"), "observations")
    population = _population_metadata(raw)
    if len(observations) != population["variant_count"]:
        raise ValueError("the engineering run has an unexpected observation count")
    case_data: dict[str, dict[str, Any]] = {}
    for item in observations:
        case = _object(item.get("case"), "case")
        case_data[str(case["case_id"])] = case
    if len(case_data) != population["case_count"]:
        raise ValueError("the engineering run has an unexpected case count")
    if _object(raw.get("planner"), "planner lineage") != planner:
        raise ValueError(
            "persisted planner lineage does not match the compiler environment"
        )
    corpus = _corpus(raw, tuple(case_data.values()))
    observed_identities = {
        (str(item["case"]["case_id"]), str(item["variant"]["variant_id"]))
        for item in observations
    }
    planner_expectations = _planner_expectations(
        planner_expectations_path,
        observed_identities,
        expected_population_id=population["name"],
    )
    document_mapping = _document_mapping(raw)
    location_mapping = _location_mapping(raw)
    dense_observations = tuple(
        _observation(
            item,
            "dense",
            document_mapping,
            location_mapping,
            planner_expectations,
        )
        for item in observations
    )
    hybrid_observations = tuple(
        _observation(
            item,
            "hybrid",
            document_mapping,
            location_mapping,
            planner_expectations,
        )
        for item in observations
    )
    policy = _object(raw.get("policy"), "policy")
    repository = _object(raw.get("repository"), "repository")
    benchmark = _object(raw.get("benchmark"), "benchmark")
    chunking = _object(raw.get("chunking"), "chunking")
    lineage = ExperimentLineage(
        repository_commit=(
            f"{repository['commit']}-dirty"
            if repository["dirty"]
            else str(repository["commit"])
        ),
        corpus_version=str(benchmark["version"]),
        corpus_digest=str(benchmark["digest"]),
        policy_version="evaluation-policy-v1",
        policy_digest=content_digest(policy),
        harness_version=RetrievalEvaluationHarness.VERSION,
        matching_algorithm=corpus.matching_algorithm,
        planner=planner,
        embedding_profile_fingerprint=str(policy["embedding_profile_fingerprint"]),
        chunking_configuration=chunking,
        retrieval_configuration=_retrieval_configuration(policy),
    )
    executed_at = datetime.fromisoformat(str(raw["executed_at"]))
    harness = RetrievalEvaluationHarness()
    dense = harness.evaluate(
        experiment_id=f"{raw['run_id']}-dense-control",
        corpus=corpus,
        observations=dense_observations,
        lineage=lineage,
        candidate_k=int(policy["final_evidence_k"]),
        executed_at=executed_at,
    )
    hybrid = harness.evaluate(
        experiment_id=str(raw["run_id"]),
        corpus=corpus,
        observations=hybrid_observations,
        lineage=lineage,
        candidate_k=int(policy["final_evidence_k"]),
        executed_at=executed_at,
    )
    envelope = {
        "schema_version": "v2",
        "dense": dense.model_dump(mode="json"),
        "hybrid": hybrid.model_dump(mode="json"),
        "policy": policy,
        "operational": {
            "dense": _operational(dense),
            "hybrid": _operational(hybrid),
            "experiment": _experiment_operational(observations, hybrid),
            "usage_note": "Unavailable provider pricing remains null and is never converted to zero.",
        },
        "classifier_and_resolution": _classifier_and_resolution(
            observations,
            planner_expectations,
            document_mapping,
            location_mapping,
        ),
    }
    config = _config(raw, planner)
    within_run_comparison = _comparison(dense, hybrid, envelope)
    comparison = (
        _historical_comparison(
            hybrid,
            envelope,
            historical_baseline_path,
            within_run_comparison,
        )
        if historical_baseline_path is not None
        else within_run_comparison
    )
    mapping = _object(raw.get("mapping"), "mapping")
    output_directory.mkdir(parents=True, exist_ok=True)
    _write(output_directory / "result.json", envelope)
    _write(output_directory / "config.json", config)
    _write(output_directory / "comparison.json", comparison)
    _write(output_directory / "provisioning-mapping.json", mapping)
    notes = output_directory / "notes.md"
    if not notes.exists():
        notes.write_text(
            "# Hypothesis\n\n# Change From Baseline\n\n# What Happened\n\n"
            "# What I Learned\n\n# Decision\n\n# Next Experiment\n"
        )
    return envelope


def _corpus(raw: dict[str, Any], cases: tuple[dict[str, Any], ...]) -> EvaluationCorpus:
    benchmark = _object(raw.get("benchmark"), "benchmark")
    v3 = "engineering_population" in _object(
        raw.get("experiment") or {}, "experiment lineage"
    )
    return EvaluationCorpus(
        schema_version="v2",
        corpus_version=str(benchmark["version"]) if v3 else "2",
        title=(
            "Dolved Care Engineering Benchmark V3 engineering regression population"
            if v3
            else "Dolved Care Engineering Benchmark V2 engineering split"
        ),
        matching_algorithm=(
            str(benchmark["matching_algorithm"])
            if v3
            else "normalised-token-coverage-v1"
        ),
        cases=tuple(
            EvaluationCase(
                case_id=str(case["case_id"]),
                variants=tuple(
                    QuestionVariant(
                        variant_id=str(variant["variant_id"]),
                        question=str(variant["question"]),
                    )
                    for variant in _list(case.get("variants"), "variants")
                ),
                slices=tuple(
                    str(value) for value in _list(case.get("slices"), "slices")
                ),
                evidence_units=tuple(
                    EvidenceUnit(
                        evidence_id=str(unit["evidence_id"]),
                        document_family_id=str(unit["document_family_id"]),
                        document_version_id=str(unit["document_version_id"]),
                        side=_side(unit["side"]),
                        source_path=str(unit["source_path"]),
                        canonical_excerpts=tuple(unit["canonical_excerpts"]),
                        relevance_grade=int(unit["relevance_grade"]),
                        minimum_token_coverage=float(unit["minimum_token_coverage"]),
                        notes=unit.get("notes"),
                    )
                    for unit in case["retrieval_expectation"]["evidence_units"]
                ),
                expected_temporal_mode=case["planner_expectation"]["temporal_mode"],
                expected_outcome=case["outcome_expectation"]["outcome"],
            )
            for case in sorted(cases, key=lambda value: value["case_id"])
        ),
    )


def _observation(
    raw: dict[str, Any],
    arm: str,
    documents: dict[str, tuple[str, str]],
    locations: dict[str, str],
    planner_expectations: dict[tuple[str, str], dict[str, Any]],
) -> VariantObservation:
    case = _object(raw.get("case"), "case")
    variant = _object(raw.get("variant"), "variant")
    units = tuple(
        EvidenceUnit(
            evidence_id=str(unit["evidence_id"]),
            document_family_id=str(unit["document_family_id"]),
            document_version_id=str(unit["document_version_id"]),
            side=_side(unit["side"]),
            source_path=str(unit["source_path"]),
            canonical_excerpts=tuple(unit["canonical_excerpts"]),
            relevance_grade=int(unit["relevance_grade"]),
            minimum_token_coverage=float(unit["minimum_token_coverage"]),
            notes=unit.get("notes"),
        )
        for unit in case["retrieval_expectation"]["evidence_units"]
    )
    planning = _object(raw.get("planning"), "planning observation")
    if planning.get("status") == "failed":
        category = str(planning.get("failure_category", "unclassified_planner_failure"))
        case_id = str(case["case_id"])
        variant_id = str(variant["variant_id"])
        attempts = int(planning.get("attempt_count") or 1)
        comparison = compare_planner_contract(
            planner_expectations[(case_id, variant_id)],
            None,
            str(variant["question"]),
        )
        return VariantObservation(
            case_id=case_id,
            variant_id=variant_id,
            candidates=(),
            planner_correct=False,
            eligibility_correct=None,
            outcome_correct=None,
            hard_failures=(f"planner_failure:{category}:{case_id}:{variant_id}",),
            operational=OperationalObservation(
                latency_ms=float(raw["latency_ms"]),
                request_count=attempts,
                stage_usage=(
                    StageUsageObservation(
                        stage="planner",
                        provider=str(planning["provider"]),
                        model=str(planning["model"]),
                        execution="PROVIDER_API",
                        request_count=attempts,
                        retry_count=max(0, attempts - 1),
                        latency_ms=float(raw["latency_ms"]),
                        cost_basis=CostBasis.UNAVAILABLE,
                    ),
                ),
            ),
            text_capture_mode=EvaluationTextCaptureMode.BENCHMARK_TEXT,
            question=str(variant["question"]),
            expected_evidence=tuple(
                ExpectedEvidenceIdentity(
                    evidence_unit_id=unit.evidence_id,
                    document_family_id=unit.document_family_id,
                    document_version_id=unit.document_version_id,
                    side=unit.side,
                    source_path=unit.source_path,
                )
                for unit in units
            ),
            expected_outcome=str(case["outcome_expectation"]["outcome"]),
            planning_status=PlanningStatus.FAILED,
            retrieval_executed=False,
            contributes_retrieval_metrics=False,
            planner_failure=PlannerFailureObservation(
                provider=str(planning["provider"]),
                model=str(planning["model"]),
                category=category,
                provider_status=(
                    int(planning["provider_status"])
                    if planning.get("provider_status") is not None
                    else None
                ),
                attempt_count=attempts,
                occurred_at=datetime.fromisoformat(str(raw["observed_at"])),
            ),
            planner_evaluation=_planner_evaluation(comparison),
        )
    if (
        planning.get("status") != "succeeded"
        or raw.get("retrieval_executed") is not True
    ):
        raise ValueError("an observation has an unsupported planning state")
    arm_data = _object(raw.get(arm), arm)
    result = _object(arm_data.get("result"), "result")
    trace = _object(arm_data.get("trace"), "trace")
    candidates, lineage, funnel = _candidate_records(
        trace=trace,
        result=result,
        units=units,
        documents=documents,
        dense_only=arm == "dense",
    )
    planner_key = (str(case["case_id"]), str(variant["variant_id"]))
    planner_comparison = compare_planner_contract(
        planner_expectations[planner_key],
        trace.get("plan"),
        str(variant["question"]),
        expected_location_identity=_expected_location_identity(case, locations),
        actual_location_identity=_actual_location_identity(trace),
    )
    planner_correct = planner_comparison.correct
    eligibility_correct = _eligibility_correct(
        case["eligibility_expectation"], trace.get("eligibility"), documents
    )
    outcome_correct = str(result.get("outcome", "")).upper() == str(
        case["outcome_expectation"]["outcome"]
    )
    retrieval_failure = (
        RetrievalFailureObservation.model_validate(trace["failure"])
        if isinstance(trace.get("failure"), dict)
        else None
    )
    hard_failures = tuple(
        name
        for name, correct in (
            ("planner_mismatch", planner_correct),
            ("eligibility_mismatch", eligibility_correct),
            ("outcome_mismatch", outcome_correct),
        )
        if not correct
    ) + (
        (f"retrieval_failure:{retrieval_failure.stage}:{retrieval_failure.category}",)
        if retrieval_failure is not None
        else ()
    )
    reranked = trace.get("reranked")
    tokens = (
        int(reranked.get("provider_input_tokens") or 0)
        if isinstance(reranked, dict)
        else 0
    )
    return VariantObservation(
        case_id=str(case["case_id"]),
        variant_id=str(variant["variant_id"]),
        candidates=candidates,
        planner_correct=planner_correct,
        eligibility_correct=eligibility_correct,
        outcome_correct=outcome_correct,
        hard_failures=hard_failures,
        operational=OperationalObservation(
            latency_ms=float(raw["latency_ms"]),
            token_usage=tokens,
            request_count=1 if reranked is not None else 0,
            stage_usage=_trace_usage(trace),
        ),
        text_capture_mode=EvaluationTextCaptureMode.BENCHMARK_TEXT,
        question=str(variant["question"]),
        expected_evidence=tuple(
            ExpectedEvidenceIdentity(
                evidence_unit_id=unit.evidence_id,
                document_family_id=unit.document_family_id,
                document_version_id=unit.document_version_id,
                side=unit.side,
                source_path=unit.source_path,
            )
            for unit in units
        ),
        expected_outcome=str(case["outcome_expectation"]["outcome"]),
        candidate_lineage=lineage,
        candidate_funnel=funnel,
        planning_status=PlanningStatus.SUCCEEDED,
        retrieval_executed=True,
        contributes_retrieval_metrics=retrieval_failure is None,
        planner_evaluation=_planner_evaluation(planner_comparison),
        retrieval_failure=retrieval_failure,
    )


def _candidate_records(
    *,
    trace: dict[str, Any],
    result: dict[str, Any],
    units: tuple[EvidenceUnit, ...],
    documents: dict[str, tuple[str, str]],
    dense_only: bool,
) -> tuple[
    tuple[RetrievedCandidate, ...],
    tuple[CandidateStageLineage, ...],
    tuple[CandidateFunnel, ...],
]:
    stages: dict[tuple[str, str], dict[str, Any]] = {}
    search = trace.get("search") if isinstance(trace.get("search"), dict) else {}
    diagnostics = search.get("diagnostics") if isinstance(search, dict) else []
    for diagnostic in diagnostics if isinstance(diagnostics, list) else []:
        side = str(diagnostic["side"]).upper()
        for stage_name, rank_name, score_name in (
            ("dense_candidates", "dense_rank", "dense_score"),
            ("sparse_candidates", "sparse_rank", "sparse_score"),
            ("fused_candidates", "fused_rank", "fused_score"),
        ):
            values = diagnostic.get(stage_name)
            for candidate in values if isinstance(values, list) else []:
                key = (side, str(candidate["chunk_id"]))
                stages.setdefault(key, {"side": side, **candidate})
                stages[key][rank_name] = int(candidate["rank"])
                stages[key][score_name] = float(candidate["score"])
    reranked = trace.get("reranked")
    reranked_candidates = (
        reranked.get("candidates") if isinstance(reranked, dict) else []
    )
    for candidate in (
        reranked_candidates if isinstance(reranked_candidates, list) else []
    ):
        key = (str(candidate["side"]).upper(), str(candidate["chunk_id"]))
        stages.setdefault(key, {"side": key[0], **candidate})
        stages[key]["reranker_rank"] = int(candidate["rank"])
        stages[key]["reranker_score"] = float(candidate["score"])
    qualified_keys = _stage_keys(trace.get("threshold_qualified"))
    final_keys = _stage_keys(trace.get("final_evidence"))
    raw_final_candidates = result.get("candidates")
    final_candidates: list[dict[str, Any]] = (
        [item for item in raw_final_candidates if isinstance(item, dict)]
        if isinstance(raw_final_candidates, list)
        else []
    )
    if dense_only:
        selected: list[dict[str, Any]] = []
        by_side: dict[str, int] = {}
        for candidate in final_candidates:
            side = str(candidate["side"]).upper()
            if by_side.get(side, 0) < 5:
                selected.append(candidate)
                by_side[side] = by_side.get(side, 0) + 1
        final_candidates = selected
        final_keys = _stage_keys(final_candidates)
    retrieved = tuple(
        _retrieved(candidate, documents) for candidate in final_candidates
    )
    lineage = []
    for key, candidate in sorted(stages.items()):
        document_id = str(candidate.get("document_id", ""))
        if document_id not in documents:
            raise ValueError(f"unmapped application document: {document_id}")
        family_id, version_id = documents[document_id]
        text = str(candidate.get("chunk_text", ""))
        observed = RetrievedCandidate(
            candidate_id=str(candidate["chunk_id"]),
            document_family_id=family_id,
            document_version_id=version_id,
            rank=int(candidate.get("rank", 1)),
            text=text,
            side=_side(key[0]),
        )
        lineage.append(
            CandidateStageLineage(
                candidate_id=str(candidate["chunk_id"]),
                chunk_id=UUID(str(candidate["chunk_id"])),
                document_family_id=family_id,
                document_version_id=version_id,
                side=_side(key[0]),
                dense_rank=candidate.get("dense_rank"),
                dense_score=candidate.get("dense_score"),
                sparse_rank=candidate.get("sparse_rank"),
                sparse_score=candidate.get("sparse_score"),
                fused_rank=candidate.get("fused_rank"),
                fused_score=candidate.get("fused_score"),
                reranker_rank=candidate.get("reranker_rank"),
                reranker_score=candidate.get("reranker_score"),
                passed_evidence_threshold=(
                    key in qualified_keys if not dense_only else None
                ),
                included_in_final_evidence=key in final_keys,
                covered_evidence_unit_ids=tuple(
                    unit.evidence_id
                    for unit in units
                    if candidate_covers(unit, observed)
                ),
            )
        )
    sides = sorted({item.side for item in lineage} | {unit.side for unit in units})
    search_observed = isinstance(trace.get("search"), dict)
    reranker_observed = isinstance(trace.get("reranked"), dict)
    threshold_observed = "threshold_qualified" in trace
    final_observed = "final_evidence" in trace or (
        dense_only and result.get("outcome") != "retrieval_failed"
    )
    funnel = tuple(
        CandidateFunnel(
            side=side,
            dense_candidate_count=(
                sum(
                    item.side == side and item.dense_rank is not None
                    for item in lineage
                )
                if search_observed
                else None
            ),
            sparse_candidate_count=(
                None
                if dense_only or not search_observed
                else sum(
                    item.side == side and item.sparse_rank is not None
                    for item in lineage
                )
            ),
            unique_post_fusion_count=(
                None
                if dense_only or not search_observed
                else sum(
                    item.side == side and item.fused_rank is not None
                    for item in lineage
                )
            ),
            candidates_sent_to_reranker=(
                None
                if dense_only or not reranker_observed
                else sum(
                    item.side == side and item.reranker_rank is not None
                    for item in lineage
                )
            ),
            candidates_surviving_threshold=(
                None
                if dense_only or not threshold_observed
                else sum(
                    item.side == side and item.passed_evidence_threshold is True
                    for item in lineage
                )
            ),
            final_evidence_count=(
                sum(
                    item.side == side and item.included_in_final_evidence
                    for item in lineage
                )
                if final_observed
                else None
            ),
        )
        for side in sides
    )
    return retrieved, tuple(lineage), funnel


def _trace_usage(
    trace: dict[str, Any],
    container_names: tuple[str, ...] = ("plan", "search", "reranked", "failure"),
) -> tuple[StageUsageObservation, ...]:
    values: list[StageUsageObservation] = []
    for container_name in container_names:
        container = trace.get(container_name)
        raw_usage = container.get("usage") if isinstance(container, dict) else None
        for item in raw_usage if isinstance(raw_usage, list) else []:
            if isinstance(item, dict):
                normalised = dict(item)
                if isinstance(normalised.get("execution"), str):
                    normalised["execution"] = normalised["execution"].upper()
                if isinstance(normalised.get("cost_basis"), str):
                    normalised["cost_basis"] = normalised["cost_basis"].upper()
                values.append(StageUsageObservation.model_validate(normalised))
    return tuple(values)


def _retrieved(
    candidate: dict[str, Any], documents: dict[str, tuple[str, str]]
) -> RetrievedCandidate:
    family_id, version_id = documents[str(candidate["document_id"])]
    return RetrievedCandidate(
        candidate_id=str(candidate["chunk_id"]),
        document_family_id=family_id,
        document_version_id=version_id,
        rank=int(candidate["rank"]),
        text=str(candidate["chunk_text"]),
        side=_side(candidate["side"]),
    )


def _stage_keys(value: Any) -> set[tuple[str, str]]:
    return (
        {
            (str(item["side"]).upper(), str(item["chunk_id"]))
            for item in value
            if isinstance(value, list) and isinstance(item, dict)
        }
        if isinstance(value, list)
        else set()
    )


def _planner_evaluation(
    comparison: PlannerComparison,
) -> PlannerEvaluationObservation:
    return PlannerEvaluationObservation(
        expected_contract=comparison.expected_contract,
        actual_plan=comparison.actual_plan,
        differences=tuple(
            PlannerFieldDifference.model_validate(value)
            for value in comparison.differences
        ),
        correct=comparison.correct,
    )


def _eligibility_correct(
    expected: dict[str, Any], actual: Any, documents: dict[str, tuple[str, str]]
) -> bool:
    if not isinstance(actual, dict):
        return False
    expected_outcome = str(expected.get("expected_outcome"))
    actual_outcome = {
        "evidence_found": "ELIGIBLE_SCOPE_READY",
        "no_eligible_evidence": "NO_ELIGIBLE_EVIDENCE",
        "clarification_required": "CLARIFICATION_REQUIRED",
        "comparison_scope_incomplete": "COMPARISON_SCOPE_INCOMPLETE",
    }.get(str(actual.get("outcome")), str(actual.get("outcome", "")).upper())
    if actual_outcome != expected_outcome:
        return False
    actual_by_side: dict[str, set[str]] = {}
    for side, public_ids in (actual.get("document_public_ids_by_side") or {}).items():
        actual_by_side[str(side).upper()] = {
            documents[str(public_id)][1]
            for public_id in public_ids
            if str(public_id) in documents
        }
    for item in expected.get("eligible_versions", []):
        if str(item["document_version_id"]) not in actual_by_side.get(
            str(item["side"]), set()
        ):
            return False
    actual_all = set().union(*actual_by_side.values()) if actual_by_side else set()
    return all(
        str(item["document_version_id"]) not in actual_all
        for item in expected.get("excluded_versions", [])
    )


def _document_mapping(raw: dict[str, Any]) -> dict[str, tuple[str, str]]:
    mapping = _object(raw.get("mapping"), "mapping")
    families = _object(mapping.get("document_families"), "document families")
    family_by_public = {str(value): str(key) for key, value in families.items()}
    result: dict[str, tuple[str, str]] = {}
    for version_id, value in _object(
        mapping.get("document_versions"), "document versions"
    ).items():
        item = _object(value, "document version mapping")
        family_id = str(item["family_id"])
        if (
            family_id not in families
            or family_by_public[str(families[family_id])] != family_id
        ):
            raise ValueError("document-family mapping is inconsistent")
        result[str(item["public_id"])] = (family_id, str(version_id))
    return result


def _location_mapping(raw: dict[str, Any]) -> dict[str, str]:
    mapping = _object(raw.get("mapping"), "mapping")
    values = mapping.get("locations")
    if values is None:
        return {}
    return {
        str(location_id): str(public_id)
        for location_id, public_id in _object(values, "locations").items()
    }


def _expected_location_identity(
    case: dict[str, Any], locations: dict[str, str]
) -> str | None:
    planner = case.get("planner_expectation")
    if not isinstance(planner, dict):
        return None
    applicability = planner.get("applicability_reference")
    location_id = (
        applicability.get("resolved_location_id")
        if isinstance(applicability, dict)
        else None
    )
    if location_id is None:
        eligibility = case.get("eligibility_expectation")
        eligible_value = (
            eligibility.get("eligible_versions")
            if isinstance(eligibility, dict)
            else None
        )
        eligible = eligible_value if isinstance(eligible_value, list) else []
        requested = {
            item.get("applicability", {}).get("requested_location_id")
            for item in eligible
            if isinstance(item, dict) and isinstance(item.get("applicability"), dict)
        }
        requested.discard(None)
        location_id = next(iter(requested)) if len(requested) == 1 else None
    return locations.get(str(location_id)) if location_id is not None else None


def _actual_location_identity(trace: dict[str, Any]) -> str | None:
    eligibility = trace.get("eligibility")
    if not isinstance(eligibility, dict):
        return None
    value = eligibility.get("resolved_location_public_id")
    return str(value) if value is not None else None


def _retrieval_configuration(policy: dict[str, Any]) -> dict[str, Any]:
    return {
        key: policy[key]
        for key in (
            "dense_candidate_k",
            "sparse_candidate_k",
            "fusion_candidate_k",
            "reranker_candidate_k",
            "rrf_k",
            "final_evidence_k",
            "evidence_threshold",
        )
    }


def _config(raw: dict[str, Any], planner: dict[str, Any]) -> dict[str, Any]:
    policy = _object(raw.get("policy"), "policy")
    benchmark = _object(raw.get("benchmark"), "benchmark")
    mapping = _object(raw.get("mapping"), "mapping")
    generations = _object(mapping.get("generations"), "generations")
    embedding = _object(generations.get("embedding_space"), "embedding space")
    sparse = _object(generations.get("sparse_space"), "sparse space")
    repository = _object(raw.get("repository"), "repository")
    population = _population_metadata(raw)
    split_ids = benchmark["engineering_case_ids"]
    split_digest = content_digest(split_ids)
    return {
        "schema_version": "v2",
        "run_id": raw["run_id"],
        "description": _experiment_description(str(raw["run_id"])),
        "status": "EXPERIMENTAL",
        "decision": None,
        "repository": repository,
        "benchmark": {
            "id": benchmark["id"],
            "version": benchmark["version"],
            "digest": benchmark["digest"],
        },
        "corpus": {"version": benchmark["version"], "digest": benchmark["digest"]},
        "split": {
            "version": benchmark["split_version"],
            "digest": split_digest,
            "name": population["name"],
            "case_count": population["case_count"],
            "variant_count": population["variant_count"],
        },
        "harness_version": RetrievalEvaluationHarness.VERSION,
        "threshold_policy_identity": policy["fingerprint"],
        "result_selector": "hybrid",
        "baseline_result_selector": "dense",
        "provisioning_mapping_digest": mapping["provisioning_mapping_digest"],
        "provisioning_snapshot_digest": mapping["snapshot_digest"],
        "planner": planner,
        "reliability": _object(raw.get("reliability"), "reliability lineage"),
        "pricing": _object(raw.get("pricing"), "pricing lineage"),
        "providers": {
            "dense": {
                "provider": "voyage",
                "model": "voyage-4-large",
                "embedding_profile_fingerprint": policy[
                    "embedding_profile_fingerprint"
                ],
                "dimensions": embedding["dimensions"],
                "adapter_version": "1",
            },
            "sparse": {
                "provider": "fastembed",
                "model": "prithivida/Splade_PP_en_v1",
                "sparse_profile_fingerprint": sparse["profile_fingerprint"],
                "model_revision": "efcd182bc7eb351e81a9445752d4388c2bab500b",
                "adapter_version": "1",
            },
            "fusion": {
                "strategy": policy["fusion_strategy"],
                "version": policy["fusion_version"],
                "rrf_k": policy["rrf_k"],
            },
            "reranking": {
                "provider": policy["reranker_provider"],
                "model": policy["reranker_model"],
                "adapter_version": policy["reranker_adapter_version"],
            },
        },
        "candidate_pipeline": _retrieval_configuration(policy),
        "threshold_status": "CALIBRATING_EXPERIMENTAL_UNPROMOTED",
    }


def _experiment_description(run_id: str) -> str:
    if run_id.startswith("EXP-0003-"):
        return "Post-reliability corrected full-pipeline engineering baseline"
    if run_id.startswith("EXP-0004-"):
        return (
            "Controlled engineering RRF experiment: rrf_k=60 control versus "
            "rrf_k=5 treatment with all other retrieval variables frozen"
        )
    if run_id.startswith("EXP-0005-"):
        return "ADR-0022-v2 consolidated full-pipeline engineering baseline"
    if run_id.startswith("EXP-0006-"):
        return "ADR-0022-v4 consolidated full-pipeline engineering confirmation"
    if run_id.startswith("EXP-0007-"):
        return "Benchmark V3 engineering regression confirmation"
    return "Exact-commit ADR-0022 full-pipeline engineering experiment"


def _comparison(
    dense: ExperimentResult, hybrid: ExperimentResult, envelope: dict[str, Any]
) -> dict[str, Any]:
    metrics = ("recall_at_k", "precision_at_k", "mrr", "ndcg_at_k")
    return {
        "schema_version": "v1",
        "baseline_experiment_id": dense.experiment_id,
        "baseline_repository_commit": dense.lineage.repository_commit,
        "baseline_result_digest": content_digest(dense.model_dump(mode="json")),
        "candidate_result_digest": content_digest(envelope),
        "metrics": {
            metric: {
                "baseline": _aggregate_metric(dense, metric),
                "candidate": _aggregate_metric(hybrid, metric),
                "delta": _aggregate_metric(hybrid, metric)
                - _aggregate_metric(dense, metric),
            }
            for metric in metrics
        },
        "slices": {
            name: {
                metric: (
                    getattr(hybrid.slices[name].metrics, metric)
                    - getattr(dense.slices[name].metrics, metric)
                    if hybrid.slices[name].metrics is not None
                    and dense.slices[name].metrics is not None
                    else None
                )
                for metric in metrics
            }
            for name in sorted(set(dense.slices) & set(hybrid.slices))
        },
        "gate": None,
        "comparison_note": "Equal-K comparison: dense and hybrid are both evaluated at final K=5.",
    }


def _historical_comparison(
    candidate: ExperimentResult,
    envelope: dict[str, Any],
    baseline_path: Path,
    within_run: dict[str, Any],
) -> dict[str, Any]:
    baseline_raw = _object(json.loads(baseline_path.read_text()), "historical result")
    baseline_value = baseline_raw.get("hybrid", baseline_raw)
    baseline = load_comparison_result(
        _object(baseline_value, "historical hybrid result")
    )
    if (
        baseline.corpus_version != candidate.lineage.corpus_version
        or baseline.corpus_digest != candidate.lineage.corpus_digest
    ):
        raise ValueError(
            "historical baseline and candidate do not share benchmark lineage"
        )
    comparison = _historical_comparison_values(baseline, candidate, envelope)
    comparison["comparison_note"] = (
        f"{baseline.experiment_id} versus {candidate.experiment_id} uses the same "
        "benchmark lineage. Interpret quality deltas alongside planner, resolution, "
        "retrieval-completion and provider-failure populations in result.json."
    )
    comparison["within_run_dense_comparison"] = within_run
    return comparison


def _historical_comparison_values(
    baseline: ComparisonResult,
    candidate: ExperimentResult,
    envelope: dict[str, Any],
) -> dict[str, Any]:
    metrics = ("recall_at_k", "precision_at_k", "mrr", "ndcg_at_k")
    if baseline.aggregate.metrics is None:
        raise ValueError("historical comparison requires retrieval metric observations")
    comparison: dict[str, Any] = {
        "schema_version": "v1",
        "baseline_experiment_id": baseline.experiment_id,
        "baseline_repository_commit": baseline.repository_commit,
        "baseline_result_digest": baseline.source_digest,
        "candidate_result_digest": content_digest(envelope),
        "metrics": {
            metric: {
                "baseline": float(getattr(baseline.aggregate.metrics, metric)),
                "candidate": _aggregate_metric(candidate, metric),
                "delta": _aggregate_metric(candidate, metric)
                - float(getattr(baseline.aggregate.metrics, metric)),
            }
            for metric in metrics
        },
        "slices": {
            name: {
                metric: (
                    getattr(candidate.slices[name].metrics, metric)
                    - getattr(baseline.slices[name].metrics, metric)
                    if candidate.slices[name].metrics is not None
                    and baseline.slices[name].metrics is not None
                    else None
                )
                for metric in metrics
            }
            for name in sorted(set(baseline.slices) & set(candidate.slices))
        },
        "gate": None,
    }
    if baseline.unavailable_fields is not None:
        comparison["historical_unavailable_fields"] = baseline.unavailable_fields
    return comparison


def _aggregate_metric(result: ExperimentResult, metric: str) -> float:
    if result.aggregate.metrics is None:
        raise ValueError(
            "dense/hybrid comparison requires retrieval metric observations"
        )
    return float(getattr(result.aggregate.metrics, metric))


def _operational(result: ExperimentResult) -> dict[str, Any]:
    latencies = sorted(item.operational.latency_ms for item in result.variants)
    planned = sum(
        item.planning_status is PlanningStatus.SUCCEEDED for item in result.variants
    )
    evidence = sum(
        any(
            candidate.included_in_final_evidence for candidate in item.candidate_lineage
        )
        for item in result.variants
    )
    return {
        "latency_ms": {
            "min": min(latencies),
            "mean": sum(latencies) / len(latencies),
            "median": latencies[len(latencies) // 2],
            "p95": _percentile(latencies, 0.95),
            "max": max(latencies),
        },
        "token_usage": sum(item.operational.token_usage for item in result.variants),
        "provider_cost": None,
        "request_count": sum(
            item.operational.request_count for item in result.variants
        ),
        "usage": aggregate_usage(
            (
                (
                    item.planning_status is PlanningStatus.SUCCEEDED,
                    any(
                        candidate.included_in_final_evidence
                        for candidate in item.candidate_lineage
                    ),
                    usage,
                )
                for item in result.variants
                for usage in item.operational.stage_usage
            ),
            attempted_variants=len(result.variants),
            successfully_planned_variants=planned,
            evidence_producing_variants=evidence,
        ),
    }


def _experiment_operational(
    raw_observations: list[Any], hybrid: ExperimentResult
) -> dict[str, Any]:
    """Aggregate actual calls across both arms without double-counting planning."""
    usage: list[tuple[bool, bool, StageUsageObservation]] = []
    successful = 0
    evidence = 0
    by_key = {(item.case_id, item.variant_id): item for item in hybrid.variants}
    for raw_value in raw_observations:
        raw = _object(raw_value, "experiment observation")
        case = _object(raw.get("case"), "case")
        variant = _object(raw.get("variant"), "variant")
        result = by_key[(str(case["case_id"]), str(variant["variant_id"]))]
        planned = result.planning_status is PlanningStatus.SUCCEEDED
        produced = any(
            candidate.included_in_final_evidence
            for candidate in result.candidate_lineage
        )
        successful += planned
        evidence += produced
        if not planned:
            usage.extend(
                (False, False, item) for item in result.operational.stage_usage
            )
            continue
        dense = _object(raw.get("dense"), "dense arm")
        hybrid_arm = _object(raw.get("hybrid"), "hybrid arm")
        dense_trace = _object(dense.get("trace"), "dense trace")
        hybrid_trace = _object(hybrid_arm.get("trace"), "hybrid trace")
        # The two arms share exactly one classified plan. Search is executed once
        # per arm and reranking only for the hybrid arm.
        items = (
            *_trace_usage(hybrid_trace, ("plan",)),
            *_trace_usage(dense_trace, ("search",)),
            *_trace_usage(hybrid_trace, ("search", "reranked")),
        )
        usage.extend((True, produced, item) for item in items)
    return aggregate_usage(
        usage,
        attempted_variants=len(raw_observations),
        successfully_planned_variants=successful,
        evidence_producing_variants=evidence,
    )


def _classifier_and_resolution(
    raw_observations: list[Any],
    planner_expectations: dict[tuple[str, str], dict[str, Any]],
    documents: dict[str, tuple[str, str]],
    locations: dict[str, str],
) -> dict[str, Any]:
    total = len(raw_observations)
    planned = 0
    planner_correct = 0
    temporal_correct = 0
    false_compare = 0
    false_historical = 0
    date_hallucinations = 0
    mode_totals: dict[str, int] = {}
    mode_correct: dict[str, int] = {}
    confusion: dict[str, dict[str, int]] = {}
    expected_locations = predicted_locations = matched_locations = 0
    eligibility_values: list[bool] = []
    temporal_resolution_values: list[bool] = []
    historical_resolution_values: list[bool] = []
    location_resolution_values: list[bool] = []
    outcome_values: list[bool] = []
    clarification_reasons: dict[str, int] = {}
    for raw_value in raw_observations:
        raw = _object(raw_value, "experiment observation")
        case = _object(raw.get("case"), "case")
        variant = _object(raw.get("variant"), "variant")
        key = (str(case["case_id"]), str(variant["variant_id"]))
        expected = planner_expectations[key]
        expected_mode = str(expected["temporal_mode"]).upper()
        mode_totals[expected_mode] = mode_totals.get(expected_mode, 0) + 1
        planning = _object(raw.get("planning"), "planning observation")
        if planning.get("status") != "succeeded":
            confusion.setdefault(expected_mode, {})["PLANNER_FAILURE"] = (
                confusion.setdefault(expected_mode, {}).get("PLANNER_FAILURE", 0) + 1
            )
            continue
        planned += 1
        hybrid = _object(raw.get("hybrid"), "hybrid arm")
        trace = _object(hybrid.get("trace"), "hybrid trace")
        actual = _object(trace.get("plan"), "validated plan")
        comparison = compare_planner_contract(
            expected,
            actual,
            str(variant["question"]),
            expected_location_identity=_expected_location_identity(case, locations),
            actual_location_identity=_actual_location_identity(trace),
        )
        planner_correct += comparison.correct
        actual_mode = str(actual.get("temporal_mode", "")).upper()
        temporal_match = actual_mode == expected_mode
        temporal_correct += temporal_match
        mode_correct[expected_mode] = mode_correct.get(expected_mode, 0) + int(
            temporal_match
        )
        confusion.setdefault(expected_mode, {})[actual_mode] = (
            confusion.setdefault(expected_mode, {}).get(actual_mode, 0) + 1
        )
        false_compare += actual_mode == "COMPARE" and expected_mode != "COMPARE"
        false_historical += (
            actual_mode == "HISTORICAL_REFERENCE"
            and expected_mode != "HISTORICAL_REFERENCE"
        )
        date_hallucinations += (
            expected.get("explicit_date") is None
            and actual.get("explicit_date") is not None
        )
        expected_refs = set(expected.get("location_references") or [])
        actual_refs = set(actual.get("location_references") or [])
        expected_locations += len(expected_refs)
        predicted_locations += len(actual_refs)
        exact_location_matches = len(expected_refs & actual_refs)
        expected_location_identity = _expected_location_identity(case, locations)
        actual_location_identity = _actual_location_identity(trace)
        matched_locations += (
            max(exact_location_matches, min(len(expected_refs), len(actual_refs)))
            if expected_location_identity is not None
            and expected_location_identity == actual_location_identity
            else exact_location_matches
        )
        eligibility = _eligibility_correct(
            _object(case.get("eligibility_expectation"), "eligibility expectation"),
            trace.get("eligibility"),
            documents,
        )
        eligibility_values.append(eligibility)
        temporal_resolution_values.append(eligibility)
        if expected_mode == "HISTORICAL_REFERENCE":
            historical_resolution_values.append(eligibility)
        if expected_refs:
            location_resolution_values.append(eligibility)
        result = _object(hybrid.get("result"), "hybrid result")
        outcome_values.append(
            str(result.get("outcome", "")).upper()
            == str(case["outcome_expectation"]["outcome"])
        )
        eligibility_trace = trace.get("eligibility")
        if isinstance(eligibility_trace, dict) and eligibility_trace.get("reason"):
            reason = str(eligibility_trace["reason"])
            clarification_reasons[reason] = clarification_reasons.get(reason, 0) + 1
    return {
        "population": total,
        "classifier": {
            "structured_response_reliability": planned / total,
            "planner_contract_accuracy": planner_correct / total,
            "temporal_accuracy": temporal_correct / total,
            "mode_accuracy": {
                mode: {
                    "correct": mode_correct.get(mode, 0),
                    "total": count,
                    "accuracy": mode_correct.get(mode, 0) / count,
                }
                for mode, count in sorted(mode_totals.items())
            },
            "confusion_matrix": {
                mode: dict(sorted(values.items()))
                for mode, values in sorted(confusion.items())
            },
            "false_compare": false_compare,
            "false_historical_reference": false_historical,
            "date_hallucinations": date_hallucinations,
            "location_extraction": {
                "matched": matched_locations,
                "expected": expected_locations,
                "predicted": predicted_locations,
                "precision": (
                    matched_locations / predicted_locations
                    if predicted_locations
                    else None
                ),
                "recall": (
                    matched_locations / expected_locations
                    if expected_locations
                    else None
                ),
            },
        },
        "laravel_resolution": {
            "planner_correctness": planner_correct / total,
            "temporal_resolution_correctness": _boolean_mean(
                temporal_resolution_values
            ),
            "historical_reference_resolution_correctness": _boolean_mean(
                historical_resolution_values
            ),
            "location_resolution_correctness": _boolean_mean(
                location_resolution_values
            ),
            "eligibility_correctness": _boolean_mean(eligibility_values),
            "outcome_correctness": _boolean_mean(outcome_values),
            "clarification_reasons": dict(sorted(clarification_reasons.items())),
        },
    }


def _boolean_mean(values: list[bool]) -> float | None:
    return sum(values) / len(values) if values else None


def _percentile(values: list[float], quantile: float) -> float:
    if len(values) == 1:
        return values[0]
    position = (len(values) - 1) * quantile
    lower = int(position)
    upper = min(lower + 1, len(values) - 1)
    fraction = position - lower
    return values[lower] + ((values[upper] - values[lower]) * fraction)


def _planner_expectations(
    path: Path | None = None,
    expected_identities: set[tuple[str, str]] | None = None,
    *,
    expected_population_id: str | None = None,
) -> dict[tuple[str, str], dict[str, Any]]:
    payload = _object(
        json.loads((path or PLANNER_EXPECTATIONS_PATH).read_text()),
        "planner expectations",
    )
    if (
        payload.get("schema_version") == "v2"
        and payload.get("scope") == "engineering_tuning"
    ):
        values = {
            (str(item["case_id"]), str(item["variant_id"])): _object(
                item.get("contract"), "planner expectation contract"
            )
            for item in _list(payload.get("expectations"), "planner expectations")
        }
        if len(values) != 126:
            raise ValueError(
                "planner expectation v2 must contain 126 engineering variants"
            )
    elif (
        payload.get("schema_version") == "v1"
        and expected_population_id is not None
        and payload.get("population_id") == expected_population_id
    ):
        values = {}
        for item in _list(payload.get("expectations"), "planner expectations"):
            contract = dict(
                _object(item.get("planner_expectation"), "planner expectation contract")
            )
            contract["contract_version"] = 2
            contract.pop("expected_outcome", None)
            values[(str(item["case_id"]), str(item["variant_id"]))] = contract
    elif payload.get("schema_version") == "v1":
        raise ValueError("V3 planner expectations do not match the bound population")
    else:
        raise ValueError("planner expectations use an unsupported schema")

    if expected_identities is not None and set(values) != expected_identities:
        raise ValueError("planner expectation identities do not match observations")

    return values


def _population_metadata(raw: dict[str, Any]) -> dict[str, Any]:
    experiment = _object(raw.get("experiment") or {}, "experiment lineage")
    population = experiment.get("engineering_population")
    if isinstance(population, dict):
        return {
            "name": str(population["id"]),
            "case_count": int(population["case_count"]),
            "variant_count": int(population["variant_count"]),
        }
    split = experiment.get("engineering_split")
    if isinstance(split, dict):
        return {
            "name": "engineering_tuning",
            "case_count": int(split["case_count"]),
            "variant_count": int(split["variant_count"]),
        }
    return {"name": "engineering_tuning", "case_count": 42, "variant_count": 126}


def _write(path: Path, value: Any) -> None:
    path.write_text(
        json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + "\n"
    )


def _object(value: Any, label: str) -> dict[str, Any]:
    if not isinstance(value, dict):
        raise TypeError(f"{label} must be an object")
    return value


def _list(value: Any, label: str) -> list[Any]:
    if not isinstance(value, list):
        raise TypeError(f"{label} must be a list")
    return value


def _side(value: Any) -> Literal["PRIMARY", "COMPARISON"]:
    normalised = str(value).upper()
    if normalised not in {"PRIMARY", "COMPARISON"}:
        raise ValueError(f"unsupported retrieval side: {value}")
    return "PRIMARY" if normalised == "PRIMARY" else "COMPARISON"
