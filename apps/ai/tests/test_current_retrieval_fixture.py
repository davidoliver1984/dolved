from __future__ import annotations

import hashlib
import json
from pathlib import Path
from typing import Any

EVALUATION = (
    Path(__file__).resolve().parents[3] / "tests/evaluation"
    if len(Path(__file__).resolve().parents) > 3
    else Path("/evaluation")
)
FIXTURE = EVALUATION / "current-retrieval/v1"
V2_SNAPSHOT = (
    EVALUATION / "engineering-snapshots/dolved-care-engineering/v2/corpus.json"
)
V2_PLANS = EVALUATION / "planner-expectations/v2/engineering-expectations.json"
V2_ORGANISATION = EVALUATION / "benchmarks/dolved-care-engineering/v2/organisation.json"
V3_ORGANISATION = EVALUATION / "benchmarks/dolved-care-engineering/v3/organisation.json"


def _load(path: Path) -> dict[str, Any]:
    value = json.loads(path.read_bytes())
    assert isinstance(value, dict)
    return value


def _variants(payload: dict[str, Any]) -> dict[tuple[str, str], dict[str, Any]]:
    return {
        (case["case_id"], variant["variant_id"]): variant
        for case in payload["cases"]
        for variant in case["variants"]
    }


def _plans(payload: dict[str, Any]) -> dict[tuple[str, str], dict[str, Any]]:
    return {
        (item["case_id"], item["variant_id"]): item for item in payload["expectations"]
    }


def _content_digest(value: Any) -> str:
    return hashlib.sha256(
        json.dumps(
            value, ensure_ascii=False, sort_keys=True, separators=(",", ":")
        ).encode()
    ).hexdigest()


def test_current_retrieval_fixture_has_self_consistent_deterministic_lineage() -> None:
    corpus = _load(FIXTURE / "corpus.json")
    plans = _load(FIXTURE / "plans.json")
    lineage = _load(FIXTURE / "lineage.json")
    corpus_digest = corpus.pop("snapshot_digest")
    plans_digest = plans.pop("expectations_digest")
    lineage_digest = lineage.pop("lineage_digest")

    assert _content_digest(corpus) == corpus_digest
    assert _content_digest(plans) == plans_digest
    assert lineage["artefacts"]["corpus_digest"] == corpus_digest
    assert lineage["artefacts"]["plans_digest"] == plans_digest
    assert _content_digest(lineage) == lineage_digest


def test_reconciliation_changes_only_two_reviewed_semantic_inputs() -> None:
    parent_snapshot = _load(V2_SNAPSHOT)
    current_snapshot = _load(FIXTURE / "corpus.json")
    parent_plans = _load(V2_PLANS)
    current_plans = _load(FIXTURE / "plans.json")
    parent_variants = _variants(parent_snapshot)
    current_variants = _variants(current_snapshot)
    parent_contracts = _plans(parent_plans)
    current_contracts = _plans(current_plans)

    changed_questions = {
        identity
        for identity in parent_variants
        if parent_variants[identity]["question"]
        != current_variants[identity]["question"]
    }
    changed_contracts = {
        identity
        for identity in parent_contracts
        if parent_contracts[identity] != current_contracts[identity]
    }
    expected = {
        ("health-safety.moving-handling.compare", "colloquial"),
        ("medication.controlled-drugs.valid-at-date", "contrast"),
    }

    assert changed_questions == expected
    assert changed_contracts == expected
    assert len(current_variants) == 126
    for identity in parent_variants:
        parent_case = next(
            case for case in parent_snapshot["cases"] if case["case_id"] == identity[0]
        )
        current_case = next(
            case for case in current_snapshot["cases"] if case["case_id"] == identity[0]
        )
        assert (
            parent_case["retrieval_expectation"]
            == current_case["retrieval_expectation"]
        )
        assert (
            parent_case["eligibility_expectation"]
            == current_case["eligibility_expectation"]
        )
        assert parent_case["outcome_expectation"] == current_case["outcome_expectation"]

    moving = current_contracts[("health-safety.moving-handling.compare", "colloquial")]
    controlled = current_contracts[
        ("medication.controlled-drugs.valid-at-date", "contrast")
    ]
    assert moving["contract"]["temporal_mode"] == "compare"
    assert moving["contract"]["temporal_reference"] is None
    assert controlled["contract"]["temporal_mode"] == "historical_reference"
    assert controlled["contract"]["temporal_reference"]["value"] == "version 1"


def test_current_aliases_are_exactly_the_independently_reviewed_v3_additions() -> None:
    v2 = _load(V2_ORGANISATION)
    v3 = _load(V3_ORGANISATION)
    current = _load(FIXTURE / "organisation.json")
    v2_aliases = {item["alias"]: item["location_ids"] for item in v2["aliases"]}
    current_aliases = {
        item["alias"]: item["location_ids"] for item in current["aliases"]
    }

    assert current == v3
    assert set(current_aliases) - set(v2_aliases) == {
        "Coventry",
        "Midlands",
        "South West",
    }
    assert current_aliases["Coventry"] == ["location.willow-bank"]
    assert current_aliases["Midlands"] == ["location.region.midlands"]
    assert current_aliases["South West"] == ["location.region.south-west"]
    lineage = _load(FIXTURE / "lineage.json")
    assert lineage["protected_splits_accessed"] is False
    assert (
        lineage["reconciliation"]["organisation_sha256"]
        == hashlib.sha256(V3_ORGANISATION.read_bytes()).hexdigest()
    )
    assert (
        lineage["artefacts"]["organisation_sha256"]
        == hashlib.sha256((FIXTURE / "organisation.json").read_bytes()).hexdigest()
    )
