"""Shared deterministic benchmark compilation utilities."""

from __future__ import annotations

import hashlib
import json
from datetime import datetime
from pathlib import Path
from typing import Any

from jsonschema import Draft202012Validator, FormatChecker
from referencing import Registry

FORBIDDEN_GROUND_TRUTH_KEYS = {
    "chunk_id",
    "extracted_element_id",
    "normalised_element_id",
    "source_element_id",
}


def load_json(file_path: Path) -> Any:
    return json.loads(file_path.read_text())


def canonical_bytes(value: Any) -> bytes:
    return json.dumps(
        value,
        allow_nan=False,
        ensure_ascii=False,
        separators=(",", ":"),
        sort_keys=True,
    ).encode()


def digest_bytes(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


def content_digest(value: Any) -> str:
    return digest_bytes(canonical_bytes(value))


def parse_time(value: str) -> datetime:
    return datetime.fromisoformat(value)


def validate_schema(
    value: Any, schema_path: Path, *, registry: Registry | None = None
) -> None:
    schema = load_json(schema_path)
    Draft202012Validator.check_schema(schema)
    Draft202012Validator(
        schema,
        registry=registry or Registry(),
        format_checker=FormatChecker(),
    ).validate(value)


def assert_no_generated_identifiers(value: Any, location: str = "$") -> None:
    if isinstance(value, dict):
        forbidden = FORBIDDEN_GROUND_TRUTH_KEYS.intersection(value)
        if forbidden:
            names = ", ".join(sorted(forbidden))
            raise ValueError(f"pipeline-generated identifiers at {location}: {names}")
        for key, item in value.items():
            assert_no_generated_identifiers(item, f"{location}.{key}")
    elif isinstance(value, list):
        for index, item in enumerate(value):
            assert_no_generated_identifiers(item, f"{location}[{index}]")


def location_index(organisation: dict[str, Any]) -> dict[str, dict[str, Any]]:
    locations = {item["location_id"]: item for item in organisation["locations"]}
    if len(locations) != len(organisation["locations"]):
        raise ValueError("location_id values must be unique")
    for location in locations.values():
        parent = location["parent_location_id"]
        if parent is not None and parent not in locations:
            raise ValueError(f"unknown parent location: {parent}")
    for alias in organisation["aliases"]:
        for location_id in alias["location_ids"]:
            if location_id not in locations:
                raise ValueError(f"alias references unknown location: {location_id}")
    return locations


def derive_authority_windows(
    family: dict[str, Any],
) -> list[dict[str, str | None]]:
    """Apply ADR-0017's attained-authority and no-resurrection rules."""

    versions = family["versions"]
    seen: set[str] = set()
    attained: list[dict[str, Any]] = []
    previous_start: datetime | None = None
    for index, version in enumerate(versions):
        version_id = version["version_id"]
        if version_id in seen:
            raise ValueError(f"duplicate version_id: {version_id}")
        seen.add(version_id)
        predecessor = versions[index - 1]["version_id"] if index else None
        if version["supersedes_version_id"] != predecessor:
            raise ValueError(
                f"{version_id} must supersede {predecessor!r} in its linear family"
            )
        if version["approved_at"] is None:
            continue
        authority_start = max(
            parse_time(version["effective_from"]), parse_time(version["approved_at"])
        )
        withdrawn_at = (
            parse_time(version["withdrawn_at"])
            if version["withdrawn_at"] is not None
            else None
        )
        if withdrawn_at is not None and withdrawn_at <= authority_start:
            continue
        if previous_start is not None and authority_start <= previous_start:
            raise ValueError(
                f"non-monotonic attained authority in {family['family_id']}: {version_id}"
            )
        previous_start = authority_start
        attained.append(
            {
                "version": version,
                "authority_start": authority_start,
                "withdrawn_at": withdrawn_at,
            }
        )

    windows: list[dict[str, str | None]] = []
    for index, item in enumerate(attained):
        next_start = (
            attained[index + 1]["authority_start"]
            if index + 1 < len(attained)
            else None
        )
        candidates = [
            value for value in (next_start, item["withdrawn_at"]) if value is not None
        ]
        authority_end = min(candidates) if candidates else None
        windows.append(
            {
                "version_id": str(item["version"]["version_id"]),
                "authority_start": item["authority_start"]
                .isoformat()
                .replace("+00:00", "Z"),
                "authority_end": (
                    authority_end.isoformat().replace("+00:00", "Z")
                    if authority_end is not None
                    else None
                ),
            }
        )
    return windows


def authoritative_version_at(
    windows: list[dict[str, str | None]], moment: datetime
) -> str | None:
    for window in windows:
        start = parse_time(str(window["authority_start"]))
        end = (
            parse_time(str(window["authority_end"]))
            if window["authority_end"] is not None
            else None
        )
        if start <= moment and (end is None or moment < end):
            return str(window["version_id"])
    return None


def is_descendant(
    requested_location_id: str,
    governing_location_id: str,
    locations: dict[str, dict[str, Any]],
) -> bool:
    current: str | None = requested_location_id
    visited: set[str] = set()
    while current is not None:
        if current == governing_location_id:
            return True
        if current in visited:
            raise ValueError(f"location hierarchy cycle at {current}")
        visited.add(current)
        current = locations[current]["parent_location_id"]
    return False


def validate_applicability(
    expected: dict[str, Any],
    version: dict[str, Any],
    locations: dict[str, dict[str, Any]],
) -> None:
    match_kind = expected["kind"]
    applicability = version["applicability"]
    governing = expected["governing_location_id"]
    requested = expected["requested_location_id"]
    if match_kind == "UNIVERSAL":
        if applicability["kind"] != "UNIVERSAL" or governing is not None:
            raise ValueError("invalid UNIVERSAL applicability expectation")
        return
    if governing not in applicability["location_ids"] or requested not in locations:
        raise ValueError("applicability expectation does not match catalogue")
    if match_kind == "DIRECT" and governing != requested:
        raise ValueError("DIRECT applicability must use the requested location")
    if match_kind == "ANCESTOR" and not is_descendant(requested, governing, locations):
        raise ValueError("ANCESTOR applicability is not an ancestor relationship")
