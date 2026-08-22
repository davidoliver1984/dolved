"""Provider-free integrity verification for immutable generation evidence."""

from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from app.evaluation.generation_evaluation import (
    GenerationCaseObservation,
    GenerationEvaluationResult,
    RecordedGenerationObservation,
    deterministic_evaluate,
    load_generation_population,
)

REQUIRED_RUN_ARTIFACTS = frozenset(
    {
        "application-observations.json",
        "config.json",
        "evaluation-observations.json",
        "population.json",
        "report.html",
        "report.md",
        "result.json",
        "run-manifest.json",
    }
)


@dataclass(frozen=True)
class GenerationEvidenceVerification:
    run_count: int
    case_count: int
    artifact_count: int


def _load_json(path: Path) -> Any:
    try:
        return json.loads(path.read_text())
    except (OSError, json.JSONDecodeError) as error:
        raise ValueError(f"invalid JSON artifact: {path}") from error


def _sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def _checksum_inventory(run_directory: Path) -> dict[str, str]:
    inventory: dict[str, str] = {}
    checksum_path = run_directory / "checksums.sha256"
    if not checksum_path.is_file():
        raise ValueError(f"missing checksum inventory: {checksum_path}")
    for line in checksum_path.read_text().splitlines():
        digest, separator, name = line.partition("  ")
        if (
            not separator
            or len(digest) != 64
            or any(character not in "0123456789abcdef" for character in digest)
            or not name
            or Path(name).name != name
            or name in inventory
        ):
            raise ValueError(f"invalid checksum entry in {checksum_path}: {line}")
        inventory[name] = digest
    if not REQUIRED_RUN_ARTIFACTS.issubset(inventory):
        missing = sorted(REQUIRED_RUN_ARTIFACTS - inventory.keys())
        raise ValueError(f"run checksum inventory omits required artifacts: {missing}")
    checkpoints = [name for name in inventory if name.startswith("checkpoint-")]
    if len(checkpoints) != 1 or not checkpoints[0].endswith(".json"):
        raise ValueError(
            "run must declare exactly one version-owned checkpoint artifact"
        )
    for name, expected in inventory.items():
        path = run_directory / name
        if not path.is_file():
            raise ValueError(f"declared generation artifact is missing: {path}")
        if _sha256(path) != expected:
            raise ValueError(f"generation artifact checksum mismatch: {path}")
    return inventory


def _verify_run(
    run_directory: Path,
    canonical_population_path: Path,
) -> tuple[int, int]:
    inventory = _checksum_inventory(run_directory)
    manifest = _load_json(run_directory / "run-manifest.json")
    config = _load_json(run_directory / "config.json")
    if not isinstance(manifest, dict) or not isinstance(config, dict):
        raise TypeError("generation manifest and configuration must be objects")
    if manifest.get("experiment_id") != run_directory.name:
        raise ValueError("generation run directory does not match manifest identity")
    for key, value in manifest.items():
        if key in config and config[key] != value:
            raise ValueError(f"generation config disagrees with manifest field: {key}")
    if manifest.get("retrieval_executed") is not False:
        raise ValueError("generation evidence unexpectedly records retrieval execution")
    if manifest.get("sealed_held_out_accessed") is not False:
        raise ValueError("generation evidence records sealed held-out access")

    canonical_population = load_generation_population(canonical_population_path)
    run_population = load_generation_population(run_directory / "population.json")
    if run_population != canonical_population:
        raise ValueError("run population differs from committed generation population")
    population_digest = canonical_population.digest()
    if manifest.get("population_id") != canonical_population.population_id:
        raise ValueError("generation population identity mismatch")
    if manifest.get("population_digest") != population_digest:
        raise ValueError("generation manifest population digest mismatch")

    application_values = _load_json(run_directory / "application-observations.json")
    if not isinstance(application_values, list):
        raise TypeError("generation application observations must be an array")
    recorded = tuple(
        RecordedGenerationObservation.model_validate(value)
        for value in application_values
    )
    case_by_id = {case.case_id: case for case in canonical_population.cases}
    if {item.case_id for item in recorded} != set(case_by_id):
        raise ValueError("generation observations do not cover the bound population")
    if len(recorded) != len(case_by_id):
        raise ValueError("generation observations contain duplicate case identities")

    result_value = _load_json(run_directory / "result.json")
    if not isinstance(result_value, dict):
        raise TypeError("generation result must be an object")
    result_experiment_id: Any
    result_population_id: Any
    result_population_digest: Any
    if manifest.get("schema_version") == "v2":
        result = GenerationEvaluationResult.model_validate(result_value)
        result_observations = result.observations
        result_experiment_id = result.experiment_id
        result_population_id = result.population_id
        result_population_digest = result.population_digest
        aggregate: Any = result.aggregate
    elif manifest.get("schema_version") == "v1":
        result_observations = tuple(
            GenerationCaseObservation.model_validate(value)
            for value in result_value.get("observations", [])
        )
        result_experiment_id = result_value.get("experiment_id")
        result_population_id = result_value.get("population_id")
        result_population_digest = result_value.get("population_digest")
        aggregate = result_value.get("aggregate")
        if not isinstance(aggregate, dict):
            raise ValueError("historical generation aggregate must be an object")
    else:
        raise ValueError("unsupported generation run schema version")
    if result_experiment_id != manifest["experiment_id"]:
        raise ValueError("generation result identity mismatch")
    if result_population_id != canonical_population.population_id:
        raise ValueError("generation result population identity mismatch")
    if result_population_digest != population_digest:
        raise ValueError("generation result population digest mismatch")
    result_by_id = {item.case_id: item for item in result_observations}
    if len(result_by_id) != len(result_observations) or set(result_by_id) != set(
        case_by_id
    ):
        raise ValueError("generation result observations do not match population")

    evaluation_values = _load_json(run_directory / "evaluation-observations.json")
    if not isinstance(evaluation_values, list):
        raise TypeError("generation evaluation observations must be an array")
    evaluation_by_id = {
        value["case_id"]: value
        for value in evaluation_values
        if isinstance(value, dict) and isinstance(value.get("case_id"), str)
    }
    if len(evaluation_by_id) != len(evaluation_values) or set(evaluation_by_id) != set(
        case_by_id
    ):
        raise ValueError("generation evaluation observations do not match population")

    deterministic_values = []
    for item in recorded:
        case = case_by_id[item.case_id]
        if (
            item.cohort is not case.cohort
            or item.question != case.question
            or item.expected_outcome is not case.expected_outcome
        ):
            raise ValueError(f"recorded case metadata mismatch: {item.case_id}")
        observed = result_by_id[item.case_id]
        if observed.request != item.request or observed.result != item.result:
            raise ValueError(
                f"result altered recorded generation output: {item.case_id}"
            )
        recomputed = deterministic_evaluate(case, item.request, item.result)
        deterministic_values.append(recomputed)
        if observed.deterministic != recomputed:
            raise ValueError(
                f"deterministic result is not reproducible: {item.case_id}"
            )
        if evaluation_by_id[item.case_id].get("deterministic") != recomputed.model_dump(
            mode="json"
        ):
            raise ValueError(
                f"deterministic evaluation observation is not reproducible: {item.case_id}"
            )
    expected_aggregate = {
        "total_cases": len(recorded),
        "outcome_accuracy": sum(value.outcome_correct for value in deterministic_values)
        / len(recorded),
        "citation_correctness": sum(
            value.citation_membership_correct for value in deterministic_values
        )
        / len(recorded),
        "over_refusal_count": sum(value.over_refusal for value in deterministic_values),
        "overclaiming_count": sum(value.overclaiming for value in deterministic_values),
        "hostile_failures": sum(
            bool(value.prohibited_fragment_leakage) for value in deterministic_values
        ),
    }
    for field, expected in expected_aggregate.items():
        actual = (
            aggregate.get(field)
            if isinstance(aggregate, dict)
            else getattr(aggregate, field)
        )
        if actual != expected:
            raise ValueError(f"deterministic aggregate is not reproducible: {field}")

    checkpoint_name = next(name for name in inventory if name.startswith("checkpoint-"))
    checkpoint = _load_json(run_directory / checkpoint_name)
    if not isinstance(checkpoint, list) or len(checkpoint) != len(recorded):
        raise ValueError("generation checkpoint does not cover the complete population")

    source_digest = manifest.get("source_generation_observations_sha256")
    if source_digest is not None:
        observation_path = run_directory / "application-observations.json"
        if source_digest != _sha256(observation_path):
            raise ValueError("source generation observation digest binding mismatch")
        if (
            manifest.get("source_generation_observations_bytes")
            != observation_path.stat().st_size
        ):
            raise ValueError("source generation observation size binding mismatch")

    return len(recorded), len(inventory)


def verify_generation_evidence(
    *, generation_root: Path, runs_root: Path
) -> GenerationEvidenceVerification:
    readme = generation_root / "README.md"
    population_path = generation_root / "populations/grounded-generation-v1.json"
    if not readme.is_file() or not population_path.is_file():
        raise ValueError("generation population documentation is incomplete")
    run_directories = sorted(
        path for path in runs_root.glob("GEN-EXP-*") if path.is_dir()
    )
    if not run_directories:
        raise ValueError("no immutable generation runs were found")
    case_count = 0
    artifact_count = 0
    for run_directory in run_directories:
        run_cases, run_artifacts = _verify_run(run_directory, population_path)
        case_count += run_cases
        artifact_count += run_artifacts
    return GenerationEvidenceVerification(
        run_count=len(run_directories),
        case_count=case_count,
        artifact_count=artifact_count,
    )
