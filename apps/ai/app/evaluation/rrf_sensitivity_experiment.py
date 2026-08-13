"""Provider-free RRF sensitivity analysis over persisted candidate observations."""

from __future__ import annotations

import hashlib
import html
import json
import math
from collections import Counter, defaultdict
from collections.abc import Mapping, Sequence
from datetime import UTC, datetime
from pathlib import Path
from statistics import median
from typing import Any

EXPERIMENT_ID = "RRF-EXP-0001-exp0003-engineering-sensitivity"
FUSION_ALGORITHM = "reciprocal-rank-fusion"
FUSION_VERSION = "1"
CONTROL_K = 60
FUSION_LIMIT = 15
TESTED_K = (1, 5, 10, 20, 30, 40, 50, 60, 80, 100)


def run_rrf_sensitivity_experiment(
    *,
    source_result_path: Path,
    source_observations_path: Path,
    output_directory: Path,
    repository_commit: str,
    generated_at: datetime | None = None,
) -> dict[str, Any]:
    """Replay persisted source ranks for the clean engineering fusion cohort."""

    source_result_bytes = source_result_path.read_bytes()
    source_observations_bytes = source_observations_path.read_bytes()
    source = json.loads(source_result_bytes)
    raw = json.loads(source_observations_bytes)
    variants = _cohort(source, raw)
    result_path = output_directory / "result.json"
    if generated_at is None and result_path.exists():
        generated_at = datetime.fromisoformat(
            json.loads(result_path.read_text())["generated_at"]
        )

    replays = {k: _replay(variants, k) for k in TESTED_K}
    control = replays[CONTROL_K]
    _verify_control(variants, control)
    known_losses = _known_losses(variants, replays)
    best_region = _best_stable_region(replays)
    result = {
        "schema_version": "rrf-sensitivity-experiment-v1",
        "experiment_id": EXPERIMENT_ID,
        "generated_at": (generated_at or datetime.now(UTC)).isoformat(),
        "repository_commit": repository_commit,
        "source": {
            "run_id": raw["run_id"],
            "result_path": str(source_result_path),
            "result_sha256": hashlib.sha256(source_result_bytes).hexdigest(),
            "observations_path": str(source_observations_path),
            "observations_sha256": hashlib.sha256(
                source_observations_bytes
            ).hexdigest(),
            "benchmark": raw["benchmark"],
            "snapshot_digest": raw["mapping"]["snapshot_digest"],
        },
        "configuration": {
            "fusion_algorithm": FUSION_ALGORITHM,
            "fusion_version": FUSION_VERSION,
            "equal_modality_weighting": True,
            "dense_candidate_k": 40,
            "sparse_candidate_k": 40,
            "fusion_candidate_limit": FUSION_LIMIT,
            "control_rrf_k": CONTROL_K,
            "tested_rrf_k": list(TESTED_K),
            "reranker_executed": False,
        },
        "cohort": {
            "definition": (
                "Engineering EXP-0003 variants with correct eligibility, retrieval "
                "executed, expected evidence, and persisted Dense and Sparse lists"
            ),
            "variant_count": len(variants),
            "expected_evidence_unit_count": sum(
                len(variant["expected"]) for variant in variants
            ),
            "side_counts": dict(
                sorted(
                    Counter(
                        item["side"]
                        for variant in variants
                        for item in variant["expected"]
                    ).items()
                )
            ),
        },
        "sensitivity": [_with_regressions(replays[k], control) for k in TESTED_K],
        "known_fusion_losses": known_losses,
        "strong_dense_only": _strong_dense_only(variants, replays),
        "dual_modality": _dual_modality(variants, replays),
        "slices": _slice_results(variants, replays),
        "rank_distributions": _rank_distributions(replays, best_region),
        "conclusion": _conclusion(replays, best_region),
    }
    output_directory.mkdir(parents=True, exist_ok=True)
    _write_artifacts(output_directory, result)
    return result


def _cohort(source: Mapping[str, Any], raw: Mapping[str, Any]) -> list[dict[str, Any]]:
    raw_by_key = {
        (item["case"]["case_id"], item["variant"]["variant_id"]): item
        for item in raw["observations"]
    }
    cohort: list[dict[str, Any]] = []
    for variant in source["hybrid"]["variants"]:
        if not (
            variant["eligibility_correct"]
            and variant["retrieval_executed"]
            and variant["expected_evidence"]
        ):
            continue
        candidates = variant["candidate_lineage"]
        if not candidates or not any(
            item["dense_rank"] is not None for item in candidates
        ):
            continue
        if not any(item["sparse_rank"] is not None for item in candidates):
            continue
        raw_item = raw_by_key[(variant["case_id"], variant["variant_id"])]
        expected = [
            {
                "identity": _evidence_identity(variant, item),
                "evidence_unit_id": item["evidence_unit_id"],
                "side": item["side"],
                "relevance_grade": _relevance_grade(raw_item, item["evidence_unit_id"]),
            }
            for item in variant["expected_evidence"]
        ]
        cohort.append(
            {
                "case_id": variant["case_id"],
                "variant_id": variant["variant_id"],
                "question": variant["question"],
                "mode": variant["planner_evaluation"]["actual_plan"]["temporal_mode"],
                "slices": sorted(set(raw_item["case"]["slices"])),
                "expected": expected,
                "candidates": candidates,
            }
        )
    return cohort


def _relevance_grade(raw_item: Mapping[str, Any], evidence_id: str) -> int:
    units = raw_item["case"]["retrieval_expectation"]["evidence_units"]
    return int(
        next(
            item["relevance_grade"]
            for item in units
            if item["evidence_id"] == evidence_id
        )
    )


def _evidence_identity(variant: Mapping[str, Any], expected: Mapping[str, Any]) -> str:
    return "::".join(
        (
            str(variant["case_id"]),
            str(variant["variant_id"]),
            str(expected["side"]),
            str(expected["evidence_unit_id"]),
        )
    )


def _rank_candidates(
    candidates: Sequence[Mapping[str, Any]], k: int
) -> list[dict[str, Any]]:
    ranked: list[dict[str, Any]] = []
    for candidate in candidates:
        source_ranks = [
            int(rank)
            for rank in (candidate["dense_rank"], candidate["sparse_rank"])
            if rank is not None
        ]
        if not source_ranks:
            continue
        ranked.append(
            {
                "chunk_id": candidate["chunk_id"],
                "side": candidate["side"],
                "covered_evidence_unit_ids": candidate["covered_evidence_unit_ids"],
                "dense_rank": candidate["dense_rank"],
                "sparse_rank": candidate["sparse_rank"],
                "score": sum(1.0 / (k + rank) for rank in source_ranks),
                "best_source_rank": min(source_ranks),
            }
        )
    ranked.sort(
        key=lambda item: (-item["score"], item["best_source_rank"], item["chunk_id"])
    )
    for rank, item in enumerate(ranked, start=1):
        item["rank"] = rank
    return ranked


def _replay(variants: Sequence[Mapping[str, Any]], k: int) -> dict[str, Any]:
    observations: list[dict[str, Any]] = []
    retained: set[str] = set()
    all_expected: set[str] = set()
    gains: list[tuple[float, float]] = []
    reciprocal_ranks: list[float] = []
    top_counts = {rank: 0 for rank in (1, 3, 5, 10, 15)}
    precision_numerator = 0
    precision_denominator = 0

    for variant in variants:
        for side in sorted({item["side"] for item in variant["expected"]}):
            expected = [item for item in variant["expected"] if item["side"] == side]
            side_ranked = _rank_candidates(
                [item for item in variant["candidates"] if item["side"] == side], k
            )
            expected_ids = {item["evidence_unit_id"] for item in expected}
            rank_by_id: dict[str, int] = {}
            score_by_id: dict[str, float] = {}
            for candidate in side_ranked:
                for evidence_id in candidate["covered_evidence_unit_ids"]:
                    if evidence_id in expected_ids and evidence_id not in rank_by_id:
                        rank_by_id[evidence_id] = candidate["rank"]
                        score_by_id[evidence_id] = candidate["score"]

            top = side_ranked[:FUSION_LIMIT]
            credited: set[str] = set()
            side_gains: list[int] = []
            grades = {
                item["evidence_unit_id"]: item["relevance_grade"] for item in expected
            }
            for candidate in top:
                newly_covered = [
                    evidence_id
                    for evidence_id in candidate["covered_evidence_unit_ids"]
                    if evidence_id in expected_ids and evidence_id not in credited
                ]
                credited.update(newly_covered)
                side_gains.append(
                    max((grades[item] for item in newly_covered), default=0)
                )
            precision_numerator += len(credited)
            precision_denominator += FUSION_LIMIT
            ideal = sorted(grades.values(), reverse=True)[:FUSION_LIMIT]
            gains.append((_dcg(side_gains), _dcg(ideal)))
            first = min((rank_by_id[item] for item in credited), default=None)
            reciprocal_ranks.append(0.0 if first is None else 1.0 / first)

            for item in expected:
                identity = item["identity"]
                all_expected.add(identity)
                evidence_rank = rank_by_id.get(item["evidence_unit_id"])
                is_retained = (
                    evidence_rank is not None and evidence_rank <= FUSION_LIMIT
                )
                if is_retained:
                    retained.add(identity)
                for cutoff in top_counts:
                    if evidence_rank is not None and evidence_rank <= cutoff:
                        top_counts[cutoff] += 1
                observations.append(
                    {
                        "identity": identity,
                        "case_id": variant["case_id"],
                        "variant_id": variant["variant_id"],
                        "side": side,
                        "evidence_unit_id": item["evidence_unit_id"],
                        "rank": evidence_rank,
                        "score": score_by_id.get(item["evidence_unit_id"]),
                        "retained": is_retained,
                    }
                )

    expected_count = len(all_expected)
    return {
        "rrf_k": k,
        "expected_evidence_unit_count": expected_count,
        "retained_count": len(retained),
        "lost_count": expected_count - len(retained),
        "metrics": {
            "recall_at_15": len(retained) / expected_count,
            "precision_at_15": precision_numerator / precision_denominator,
            "mrr": sum(reciprocal_ranks) / len(reciprocal_ranks),
            "ndcg_at_15": sum(dcg / idcg if idcg else 1.0 for dcg, idcg in gains)
            / len(gains),
        },
        "top_expected_evidence_rate": {
            f"top_{cutoff}": count / expected_count
            for cutoff, count in top_counts.items()
        },
        "retained_identities": sorted(retained),
        "observations": observations,
    }


def _with_regressions(
    candidate: Mapping[str, Any], control: Mapping[str, Any]
) -> dict[str, Any]:
    candidate_retained = set(candidate["retained_identities"])
    control_retained = set(control["retained_identities"])
    recovered = sorted(candidate_retained - control_retained)
    regressed = sorted(control_retained - candidate_retained)
    return {
        key: value
        for key, value in candidate.items()
        if key not in ("retained_identities", "observations")
    } | {
        "recovered_vs_k60": recovered,
        "regressed_vs_k60": regressed,
        "recovered_count": len(recovered),
        "regressed_count": len(regressed),
        "net_retained_vs_k60": len(recovered) - len(regressed),
    }


def _verify_control(
    variants: Sequence[Mapping[str, Any]], control: Mapping[str, Any]
) -> None:
    persisted: set[str] = set()
    for variant in variants:
        for expected in variant["expected"]:
            if any(
                expected["evidence_unit_id"] in candidate["covered_evidence_unit_ids"]
                and candidate["fused_rank"] is not None
                and candidate["fused_rank"] <= FUSION_LIMIT
                and candidate["side"] == expected["side"]
                for candidate in variant["candidates"]
            ):
                persisted.add(expected["identity"])
    replayed = set(control["retained_identities"])
    if replayed != persisted:
        raise ValueError("k=60 replay does not reproduce persisted EXP-0003 fusion")


def _known_losses(
    variants: Sequence[Mapping[str, Any]], replays: Mapping[int, Mapping[str, Any]]
) -> list[dict[str, Any]]:
    control = replays[CONTROL_K]
    known = [item for item in control["observations"] if not item["retained"]]
    candidate_found: set[str] = set()
    for variant in variants:
        for expected in variant["expected"]:
            if any(
                expected["evidence_unit_id"] in candidate["covered_evidence_unit_ids"]
                and (
                    candidate["dense_rank"] is not None
                    or candidate["sparse_rank"] is not None
                )
                and candidate["side"] == expected["side"]
                for candidate in variant["candidates"]
            ):
                candidate_found.add(expected["identity"])
    losses = [item for item in known if item["identity"] in candidate_found]
    output = []
    for item in losses:
        variant = next(
            candidate
            for candidate in variants
            if candidate["case_id"] == item["case_id"]
            and candidate["variant_id"] == item["variant_id"]
        )
        matching = [
            candidate
            for candidate in variant["candidates"]
            if item["evidence_unit_id"] in candidate["covered_evidence_unit_ids"]
            and candidate["side"] == item["side"]
        ]
        dense_rank = min(
            (
                candidate["dense_rank"]
                for candidate in matching
                if candidate["dense_rank"] is not None
            ),
            default=None,
        )
        sparse_rank = min(
            (
                candidate["sparse_rank"]
                for candidate in matching
                if candidate["sparse_rank"] is not None
            ),
            default=None,
        )
        control_top = {
            candidate["chunk_id"]
            for candidate in _rank_candidates(
                [
                    candidate
                    for candidate in variant["candidates"]
                    if candidate["side"] == item["side"]
                ],
                CONTROL_K,
            )[:FUSION_LIMIT]
        }
        ranks = []
        for k in TESTED_K:
            observation = next(
                observation
                for observation in replays[k]["observations"]
                if observation["identity"] == item["identity"]
            )
            alternative_top = {
                candidate["chunk_id"]
                for candidate in _rank_candidates(
                    [
                        candidate
                        for candidate in variant["candidates"]
                        if candidate["side"] == item["side"]
                    ],
                    k,
                )[:FUSION_LIMIT]
            }
            ranks.append(
                {
                    "rrf_k": k,
                    "rank": observation["rank"],
                    "score": observation["score"],
                    "retained": observation["retained"],
                    "displaced_from_k60_top15": sorted(control_top - alternative_top),
                    "entered_vs_k60_top15": sorted(alternative_top - control_top),
                }
            )
        output.append(
            {
                "identity": item["identity"],
                "case_id": item["case_id"],
                "variant_id": item["variant_id"],
                "side": item["side"],
                "evidence_unit_id": item["evidence_unit_id"],
                "dense_rank": dense_rank,
                "sparse_rank": sparse_rank,
                "ranks": ranks,
            }
        )
    return output


def _expected_source_population(
    variants: Sequence[Mapping[str, Any]],
) -> list[dict[str, Any]]:
    population: list[dict[str, Any]] = []
    for variant in variants:
        for expected in variant["expected"]:
            matches = [
                candidate
                for candidate in variant["candidates"]
                if expected["evidence_unit_id"]
                in candidate["covered_evidence_unit_ids"]
                and candidate["side"] == expected["side"]
            ]
            dense_rank = min(
                (
                    int(item["dense_rank"])
                    for item in matches
                    if item["dense_rank"] is not None
                ),
                default=None,
            )
            sparse_rank = min(
                (
                    int(item["sparse_rank"])
                    for item in matches
                    if item["sparse_rank"] is not None
                ),
                default=None,
            )
            population.append(
                {
                    **expected,
                    "case_id": variant["case_id"],
                    "variant_id": variant["variant_id"],
                    "dense_rank": dense_rank,
                    "sparse_rank": sparse_rank,
                }
            )
    return population


def _strong_dense_only(
    variants: Sequence[Mapping[str, Any]], replays: Mapping[int, Mapping[str, Any]]
) -> dict[str, Any]:
    population = _expected_source_population(variants)
    bands = ((1, 5), (6, 10), (11, 20))
    output = []
    for lower, upper in bands:
        members = [
            item
            for item in population
            if item["dense_rank"] is not None
            and lower <= item["dense_rank"] <= upper
            and item["sparse_rank"] is None
        ]
        output.append(
            {
                "band": f"dense_{lower}_{upper}_sparse_absent",
                "count": len(members),
                "identities": [item["identity"] for item in members],
                "retained_by_k": {
                    str(k): sum(
                        item["identity"] in set(replays[k]["retained_identities"])
                        for item in members
                    )
                    for k in TESTED_K
                },
            }
        )
    return {"bands": output}


def _dual_modality(
    variants: Sequence[Mapping[str, Any]], replays: Mapping[int, Mapping[str, Any]]
) -> dict[str, Any]:
    population = _expected_source_population(variants)

    def category(item: Mapping[str, Any]) -> str:
        dense, sparse = item["dense_rank"], item["sparse_rank"]
        if dense is not None and sparse is not None:
            if dense <= 5 and sparse <= 5:
                return "both_top_5"
            if dense <= 5 or sparse <= 5:
                return "one_top_5_other_lower"
            return "both_moderate"
        if dense is not None:
            return "dense_only"
        if sparse is not None:
            return "sparse_only"
        return "neither"

    grouped: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for item in population:
        grouped[category(item)].append(item)
    return {
        name: {
            "count": len(items),
            "retained_by_k": {
                str(k): sum(
                    item["identity"] in set(replays[k]["retained_identities"])
                    for item in items
                )
                for k in TESTED_K
            },
        }
        for name, items in sorted(grouped.items())
    }


def _slice_results(
    variants: Sequence[Mapping[str, Any]], replays: Mapping[int, Mapping[str, Any]]
) -> dict[str, Any]:
    requested = {
        "CURRENT": lambda item: item["mode"] == "CURRENT",
        "COMPARE": lambda item: item["mode"] == "COMPARE",
        "VALID_AT_DATE": lambda item: item["mode"] == "VALID_AT_DATE",
        "HISTORICAL_REFERENCE": lambda item: item["mode"] == "HISTORICAL_REFERENCE",
        "multi-evidence": lambda item: "multi-evidence" in item["slices"],
        "multi-document": lambda item: "multi-document" in item["slices"],
        "tables": lambda item: bool({"tables", "table-evidence"} & set(item["slices"])),
        "adversarial": lambda item: "adversarial" in item["slices"],
        "temporal-authority": lambda item: "temporal-authority" in item["slices"],
    }
    output: dict[str, Any] = {}
    for name, predicate in requested.items():
        members = [variant for variant in variants if predicate(variant)]
        identities = {
            item["identity"] for variant in members for item in variant["expected"]
        }
        output[name] = {
            "variant_count": len(members),
            "expected_evidence_unit_count": len(identities),
            "recall_at_15_by_k": {
                str(k): (
                    sum(
                        identity in set(replays[k]["retained_identities"])
                        for identity in identities
                    )
                    / len(identities)
                    if identities
                    else None
                )
                for k in TESTED_K
            },
        }
    return output


def _rank_distributions(
    replays: Mapping[int, Mapping[str, Any]], best_region: Sequence[int]
) -> list[dict[str, Any]]:
    selected = sorted({CONTROL_K, *best_region})
    output = []
    for k in selected:
        ranks = [
            item["rank"]
            for item in replays[k]["observations"]
            if item["rank"] is not None
        ]
        output.append(
            {
                "rrf_k": k,
                "median_rank": median(ranks),
                "p90_rank": _percentile(ranks, 0.9),
                "outside_top_15": sum(rank > 15 for rank in ranks),
                "ranks_16_20": sum(16 <= rank <= 20 for rank in ranks),
                "ranks_21_30": sum(21 <= rank <= 30 for rank in ranks),
                "ranks_over_30": sum(rank > 30 for rank in ranks),
            }
        )
    return output


def _best_stable_region(replays: Mapping[int, Mapping[str, Any]]) -> list[int]:
    best = max(item["retained_count"] for item in replays.values())
    return [k for k in TESTED_K if replays[k]["retained_count"] == best]


def _conclusion(
    replays: Mapping[int, Mapping[str, Any]], best_region: Sequence[int]
) -> dict[str, Any]:
    control = replays[CONTROL_K]
    best_retained = max(item["retained_count"] for item in replays.values())
    candidates = [
        k
        for k in best_region
        if not (
            set(control["retained_identities"]) - set(replays[k]["retained_identities"])
        )
    ]
    proposed = max(candidates) if candidates else None
    return {
        "best_retained_count": best_retained,
        "best_stable_region": list(best_region),
        "control_retained_count": control["retained_count"],
        "supports_future_controlled_change_experiment": proposed is not None
        and proposed != CONTROL_K,
        "proposed_rrf_k": proposed if proposed != CONTROL_K else None,
        "statement": (
            "Engineering-only offline evidence; no production change is authorised "
            "without a controlled end-to-end experiment."
        ),
    }


def _dcg(gains: Sequence[int]) -> float:
    return sum(
        (2**gain - 1) / math.log2(rank + 1) for rank, gain in enumerate(gains, 1)
    )


def _percentile(values: Sequence[int], percentile: float) -> int:
    ordered = sorted(values)
    return ordered[max(0, math.ceil(percentile * len(ordered)) - 1)]


def _write_artifacts(output_directory: Path, result: Mapping[str, Any]) -> None:
    result_path = output_directory / "result.json"
    result_path.write_text(json.dumps(result, indent=2, sort_keys=True) + "\n")
    config = {
        "experiment_id": result["experiment_id"],
        "source": result["source"],
        "configuration": result["configuration"],
        "cohort": result["cohort"],
    }
    (output_directory / "config.json").write_text(
        json.dumps(config, indent=2, sort_keys=True) + "\n"
    )
    report = _markdown_report(result)
    (output_directory / "report.md").write_text(report)
    (output_directory / "report.html").write_text(_html_report(result, report))
    (output_directory / "notes.md").write_text(
        "# Notes\n\n"
        "Provider-free engineering sensitivity replay. No production configuration, "
        "EXP-0003 artefact, calibration case, or held-out case was changed or accessed.\n"
    )
    checksums = {
        name: hashlib.sha256((output_directory / name).read_bytes()).hexdigest()
        for name in (
            "config.json",
            "notes.md",
            "report.html",
            "report.md",
            "result.json",
        )
    }
    (output_directory / "checksums.json").write_text(
        json.dumps(checksums, indent=2, sort_keys=True) + "\n"
    )


def _markdown_report(result: Mapping[str, Any]) -> str:
    lines = [
        "# RRF sensitivity experiment",
        "",
        f"Experiment: `{result['experiment_id']}`",
        "",
        "This is a provider-free replay over immutable EXP-0003 Dense and Sparse ranks. It does not run reranking or authorise a production change.",
        "",
        "## Cohort",
        "",
        f"- Variants: {result['cohort']['variant_count']}",
        f"- Expected EvidenceUnit instances: {result['cohort']['expected_evidence_unit_count']}",
        f"- Fusion limit: {result['configuration']['fusion_candidate_limit']}",
        "",
        "## Sensitivity",
        "",
        "| k | Retained | Recall@15 | Precision@15 | MRR | nDCG@15 | Top1 | Top3 | Top5 | Top10 | Recovered | Regressed | Net |",
        "|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|",
    ]
    for item in result["sensitivity"]:
        metrics = item["metrics"]
        top = item["top_expected_evidence_rate"]
        lines.append(
            f"| {item['rrf_k']} | {item['retained_count']} | {metrics['recall_at_15']:.4f} | "
            f"{metrics['precision_at_15']:.4f} | {metrics['mrr']:.4f} | {metrics['ndcg_at_15']:.4f} | "
            f"{top['top_1']:.4f} | {top['top_3']:.4f} | {top['top_5']:.4f} | "
            f"{top['top_10']:.4f} | {item['recovered_count']} | {item['regressed_count']} | "
            f"{item['net_retained_vs_k60']:+d} |"
        )
    lines.extend(["", "## Known fusion losses", ""])
    for loss in result["known_fusion_losses"]:
        ranks = ", ".join(
            f"k={item['rrf_k']}: rank {item['rank']}" for item in loss["ranks"]
        )
        lines.append(
            f"- `{loss['identity']}` — Dense `{loss['dense_rank']}`, Sparse "
            f"`{loss['sparse_rank']}`; {ranks}"
        )
    lines.extend(["", "## Source-rank populations", ""])
    for name, population in result["dual_modality"].items():
        retained = population["retained_by_k"]
        lines.append(
            f"- `{name}`: n={population['count']}; retained at k=5 "
            f"{retained['5']}, at k=60 {retained['60']}"
        )
    lines.extend(["", "## Engineering slices", ""])
    lines.extend(
        [
            "| Slice | Variants | EvidenceUnits | Recall@15 k=5 | Recall@15 k=60 |",
            "|---|---:|---:|---:|---:|",
        ]
    )
    for name, value in result["slices"].items():
        lines.append(
            f"| {name} | {value['variant_count']} | {value['expected_evidence_unit_count']} | "
            f"{value['recall_at_15_by_k']['5']:.4f} | {value['recall_at_15_by_k']['60']:.4f} |"
        )
    lines.extend(["", "## Expected-rank distributions", ""])
    for value in result["rank_distributions"]:
        lines.append(
            f"- k={value['rrf_k']}: median={value['median_rank']}, p90={value['p90_rank']}, "
            f"outside top 15={value['outside_top_15']}, ranks 16–20={value['ranks_16_20']}, "
            f"21–30={value['ranks_21_30']}, >30={value['ranks_over_30']}"
        )
    conclusion = result["conclusion"]
    lines.extend(
        [
            "",
            "## Conclusion",
            "",
            f"- Best retained-count region: `{conclusion['best_stable_region']}`",
            f"- Proposed k for a controlled end-to-end engineering experiment: `{conclusion['proposed_rrf_k']}`",
            f"- {conclusion['statement']}",
            "",
        ]
    )
    return "\n".join(lines)


def _html_report(result: Mapping[str, Any], report: str) -> str:
    rows = "".join(
        "<tr>"
        f"<td>{item['rrf_k']}</td><td>{item['retained_count']}</td>"
        f"<td>{item['metrics']['recall_at_15']:.4f}</td>"
        f"<td>{item['metrics']['precision_at_15']:.4f}</td>"
        f"<td>{item['metrics']['mrr']:.4f}</td>"
        f"<td>{item['metrics']['ndcg_at_15']:.4f}</td>"
        f"<td>{item['recovered_count']}</td><td>{item['regressed_count']}</td>"
        "</tr>"
        for item in result["sensitivity"]
    )
    return (
        '<!doctype html><html><head><meta charset="utf-8"><title>RRF sensitivity</title>'
        "<style>body{font:16px system-ui;max-width:1100px;margin:2rem auto;padding:0 1rem}"
        "table{border-collapse:collapse;width:100%}th,td{border:1px solid #bbb;padding:.45rem;text-align:right}"
        "th:first-child,td:first-child{text-align:left}pre{white-space:pre-wrap;background:#f5f5f5;padding:1rem}</style>"
        "</head><body><h1>RRF sensitivity experiment</h1>"
        f"<p>Provider-free replay; {result['cohort']['variant_count']} variants and "
        f"{result['cohort']['expected_evidence_unit_count']} expected EvidenceUnit instances.</p>"
        "<table><thead><tr><th>k</th><th>Retained</th><th>Recall@15</th>"
        "<th>Precision@15</th><th>MRR</th><th>nDCG@15</th><th>Recovered</th>"
        f"<th>Regressed</th></tr></thead><tbody>{rows}</tbody></table>"
        f"<pre>{html.escape(report)}</pre></body></html>"
    )
