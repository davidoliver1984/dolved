"""Deterministic comparison of benchmark planner truth and validated plans."""

from __future__ import annotations

import re
from dataclasses import dataclass
from typing import Any

PLANNER_FIELDS = (
    "retrieval_queries",
    "temporal_mode",
    "explicit_date",
    "temporal_reference",
    "location_references",
    "clarification_reason",
)


@dataclass(frozen=True)
class PlannerComparison:
    expected_contract: dict[str, Any]
    actual_plan: dict[str, Any] | None
    differences: tuple[dict[str, Any], ...]

    @property
    def correct(self) -> bool:
        return self.actual_plan is not None and not self.differences


def compare_planner_contract(
    expected: dict[str, Any],
    actual: Any,
    question: str,
    *,
    expected_location_identity: str | None = None,
    actual_location_identity: str | None = None,
) -> PlannerComparison:
    """Compare ADR-0022 fields, accepting only proven location-identity equivalence."""
    expected_contract = _expected_contract(expected, question)
    if not isinstance(actual, dict):
        return PlannerComparison(
            expected_contract,
            None,
            (
                {
                    "field": "validated_plan",
                    "expected": "AVAILABLE",
                    "actual": "UNAVAILABLE",
                    "classification": "SEMANTIC_AFTER_NORMALISATION",
                },
            ),
        )
    actual_contract = _actual_contract(actual)
    equivalent_location_representation = (
        expected_location_identity is not None
        and actual_location_identity is not None
        and expected_location_identity == actual_location_identity
    )
    equivalent_historical_reference = _historical_reference_equivalent(
        expected_contract["temporal_reference"],
        actual_contract["temporal_reference"],
    )
    differences = tuple(
        {
            "field": field,
            "expected": expected_contract[field],
            "actual": actual_contract[field],
            "classification": _difference_classification(
                field, expected_contract[field], actual_contract[field]
            ),
        }
        for field in PLANNER_FIELDS
        if expected_contract[field] != actual_contract[field]
        and not (field == "location_references" and equivalent_location_representation)
        and not (field == "temporal_reference" and equivalent_historical_reference)
    )
    return PlannerComparison(expected_contract, actual_contract, differences)


def _expected_contract(expected: dict[str, Any], question: str) -> dict[str, Any]:
    if expected.get("contract_version") != 2:
        raise ValueError("planner evaluation requires versioned ADR-0022 truth")

    return {
        "retrieval_queries": [question],
        "temporal_mode": str(expected.get("temporal_mode", "")).upper(),
        "explicit_date": expected.get("explicit_date"),
        "temporal_reference": _reference(expected.get("temporal_reference")),
        "location_references": list(expected.get("location_references") or []),
        "clarification_reason": expected.get("clarification_reason"),
    }


def _actual_contract(actual: dict[str, Any]) -> dict[str, Any]:
    query = actual.get("query")
    if query is None:
        queries = actual.get("retrieval_queries")
    else:
        queries = [query] if isinstance(query, str) else []
    return {
        "retrieval_queries": queries if isinstance(queries, list) else [],
        "temporal_mode": str(actual.get("temporal_mode", "")).upper(),
        "explicit_date": actual.get("explicit_date"),
        "temporal_reference": _reference(actual.get("temporal_reference")),
        "location_references": list(actual.get("location_references") or []),
        "clarification_reason": actual.get("clarification_reason"),
    }


def _reference(value: Any) -> dict[str, str] | None:
    if not isinstance(value, dict):
        return None
    kind = value.get("kind")
    reference = value.get("value")
    return {
        "kind": str(kind).upper(),
        "value": str(reference),
    }


def _historical_reference_equivalent(expected: Any, actual: Any) -> bool:
    """Accept only references with the same deterministic Laravel selector."""
    expected_selector = _historical_selector(expected)
    actual_selector = _historical_selector(actual)
    return expected_selector is not None and expected_selector == actual_selector


def _historical_selector(value: Any) -> tuple[str, int | str] | None:
    if not isinstance(value, dict) or value.get("kind") != "HISTORICAL_REFERENCE":
        return None
    reference = value.get("value")
    if not isinstance(reference, str):
        return None

    text = reference.strip().casefold()
    versions = re.findall(r"\bversion\s+(\d+)\b", text)
    years = re.findall(r"\b(\d{4})\b", text)
    relative = re.search(r"\b(old|older|previous|prior)\b", text) is not None
    withdrawal = "withdraw" in text
    has_version = bool(versions)
    strategies = (
        int(has_version)
        + int(bool(years))
        + int(relative)
        + int(withdrawal and not has_version)
    )
    if strategies != 1 or len(set(versions)) > 1 or len(set(years)) > 1:
        return None
    if versions:
        return ("version", int(versions[0]))
    if years:
        return ("year", years[0])
    if withdrawal:
        return ("withdrawn", "withdrawn")
    if relative:
        return ("relative_previous", "relative_previous")
    return None


def _difference_classification(field: str, expected: Any, actual: Any) -> str:
    if field == "location_references" and expected and actual:
        return "POTENTIAL_ALIAS_OR_REPRESENTATION_MISMATCH"
    return "SEMANTIC_AFTER_NORMALISATION"
