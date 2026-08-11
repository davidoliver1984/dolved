"""Validate and deterministically compile the Dolved engineering benchmark."""

from __future__ import annotations

import argparse
import hashlib
import json
from datetime import datetime
from pathlib import Path
from typing import Any

from jsonschema import Draft202012Validator, FormatChecker

BENCHMARK_ID = "dolved-care-engineering"
FORBIDDEN_GROUND_TRUTH_KEYS = {
    "chunk_id",
    "extracted_element_id",
    "normalised_element_id",
    "source_element_id",
}


def load_json(path: Path) -> Any:
    return json.loads(path.read_text())


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


def parse_time(value: str) -> datetime:
    return datetime.fromisoformat(value.replace("Z", "+00:00"))


def validate_schema(value: Any, schema_path: Path) -> None:
    schema = load_json(schema_path)
    Draft202012Validator.check_schema(schema)
    Draft202012Validator(schema, format_checker=FormatChecker()).validate(value)


def assert_no_generated_identifiers(value: Any, path: str = "$") -> None:
    if isinstance(value, dict):
        forbidden = FORBIDDEN_GROUND_TRUTH_KEYS.intersection(value)
        if forbidden:
            names = ", ".join(sorted(forbidden))
            raise ValueError(f"pipeline-generated identifiers at {path}: {names}")
        for key, item in value.items():
            assert_no_generated_identifiers(item, f"{path}.{key}")
    elif isinstance(value, list):
        for index, item in enumerate(value):
            assert_no_generated_identifiers(item, f"{path}[{index}]")


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
        expected_predecessor = versions[index - 1]["version_id"] if index else None
        if version["supersedes_version_id"] != expected_predecessor:
            raise ValueError(
                f"{version_id} must supersede {expected_predecessor!r} in its linear family"
            )

        approved_at = version["approved_at"]
        if approved_at is None:
            continue
        authority_start = max(
            parse_time(version["effective_from"]), parse_time(approved_at)
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
        withdrawal = item["withdrawn_at"]
        candidates = [value for value in (next_start, withdrawal) if value is not None]
        authority_end = min(candidates) if candidates else None
        windows.append(
            {
                "version_id": item["version"]["version_id"],
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


def validate_catalog(
    catalog: dict[str, Any],
    manifest: dict[str, Any],
    benchmark_root: Path,
    locations: dict[str, dict[str, Any]],
) -> tuple[dict[str, dict[str, Any]], dict[str, dict[str, Any]], dict[str, Any]]:
    families = {family["family_id"]: family for family in catalog["families"]}
    if len(families) != len(catalog["families"]):
        raise ValueError("family_id values must be unique")
    versions: dict[str, dict[str, Any]] = {}
    catalogued_sources: set[str] = set()
    authority: dict[str, Any] = {}

    for family in catalog["families"]:
        for relationship in family["relationships"]:
            if relationship["target_family_id"] not in families:
                raise ValueError(
                    f"unknown related family: {relationship['target_family_id']}"
                )
        authority[family["family_id"]] = derive_authority_windows(family)
        for version in family["versions"]:
            version_id = version["version_id"]
            if version_id in versions:
                raise ValueError(f"duplicate version_id: {version_id}")
            versions[version_id] = {**version, "family_id": family["family_id"]}
            for location_id in version["applicability"]["location_ids"]:
                if location_id not in locations:
                    raise ValueError(f"unknown applicability location: {location_id}")
            source_path = version["source_path"]
            if source_path is not None:
                if not (benchmark_root / source_path).is_file():
                    raise ValueError(f"missing authored source: {source_path}")
                catalogued_sources.add(source_path)
            if version["pilot"] and source_path is None:
                raise ValueError(f"pilot version has no source: {version_id}")

    markdown_sources = {
        path.relative_to(benchmark_root).as_posix()
        for path in (benchmark_root / "documents").rglob("*.md")
    }
    if markdown_sources != catalogued_sources:
        raise ValueError(
            "catalogue/source mismatch: "
            f"missing={sorted(catalogued_sources - markdown_sources)}, "
            f"unexpected={sorted(markdown_sources - catalogued_sources)}"
        )

    planned = manifest["planned_counts"]
    pilot = manifest["pilot_counts"]
    if len(families) != planned["document_families"]:
        raise ValueError("manifest planned family count does not match catalogue")
    if len(versions) != planned["document_versions"]:
        raise ValueError("manifest planned version count does not match catalogue")
    pilot_versions = [version for version in versions.values() if version["pilot"]]
    pilot_families = {version["family_id"] for version in pilot_versions}
    authored_versions = [
        version for version in versions.values() if version["source_path"] is not None
    ]
    authored_families = {version["family_id"] for version in authored_versions}
    if len(pilot_families) != pilot["document_families"]:
        raise ValueError("manifest pilot family count does not match catalogue")
    if len(pilot_versions) != pilot["document_versions"]:
        raise ValueError("manifest pilot version count does not match catalogue")
    authored = manifest["authored_counts"]
    if len(authored_families) != authored["document_families"]:
        raise ValueError("manifest authored family count does not match catalogue")
    if len(authored_versions) != authored["document_versions"]:
        raise ValueError("manifest authored version count does not match catalogue")
    if manifest["status"] == "PILOT" and len(authored_versions) != len(pilot_versions):
        raise ValueError("PILOT status cannot include expansion sources")
    if manifest["status"] in {"COMPLETE", "BASELINED"} and len(
        authored_versions
    ) != len(versions):
        raise ValueError("complete benchmark has unauthored document versions")
    return families, versions, authority


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


def validate_cases(
    corpus: dict[str, Any],
    split: dict[str, Any],
    manifest: dict[str, Any],
    benchmark_root: Path,
    families: dict[str, dict[str, Any]],
    versions: dict[str, dict[str, Any]],
    authority: dict[str, list[dict[str, str | None]]],
    locations: dict[str, dict[str, Any]],
) -> None:
    cases = corpus["cases"]
    case_ids = [case["case_id"] for case in cases]
    if len(case_ids) != len(set(case_ids)):
        raise ValueError("case_id values must be unique")
    if len(cases) != manifest["authored_counts"]["semantic_cases"]:
        raise ValueError("manifest authored case count does not match case shards")

    assignments = split["assignments"]
    all_assignments = [case_id for values in assignments.values() for case_id in values]
    if len(all_assignments) != len(set(all_assignments)):
        raise ValueError("a case is assigned to more than one split")
    if set(all_assignments) != set(case_ids):
        raise ValueError("every authored case must have exactly one split assignment")
    if split["assignment_status"] in {"PILOT_ONLY", "EXPANDING"} and (
        assignments["threshold_calibration"] or assignments["sealed_held_out"]
    ):
        raise ValueError("unfinished cases must not spend calibration or held-out capacity")
    if split["assignment_status"] == "COMPLETE":
        if assignments["unassigned"]:
            raise ValueError("complete split cannot contain unassigned cases")
        for split_name, target_name in (
            ("engineering_tuning", "engineering_tuning"),
            ("threshold_calibration", "threshold_calibration"),
            ("sealed_held_out", "sealed_held_out"),
        ):
            if len(assignments[split_name]) != split["targets"][target_name]:
                raise ValueError(f"complete split has incorrect {split_name} count")
    split_by_case = {
        case_id: split_name
        for split_name, values in assignments.items()
        for case_id in values
    }
    cluster_splits: dict[str, set[str]] = {}
    evaluation_clock = parse_time(corpus["evaluation_clock"])

    for case in cases:
        cluster_splits.setdefault(case["cluster_id"], set()).add(
            split_by_case[case["case_id"]]
        )
        variant_ids = [variant["variant_id"] for variant in case["variants"]]
        if len(variant_ids) != len(set(variant_ids)):
            raise ValueError(f"duplicate variant_id in {case['case_id']}")
        planner = case["planner_expectation"]
        moment = (
            parse_time(planner["valid_at"])
            if planner["temporal_mode"] == "VALID_AT_DATE"
            else evaluation_clock
        )
        eligible_versions = case["eligibility_expectation"]["eligible_versions"]
        eligible_keys = {
            (
                expected["document_family_id"],
                expected["document_version_id"],
                expected["side"],
            )
            for expected in eligible_versions
        }
        if len(eligible_keys) != len(eligible_versions):
            raise ValueError(f"duplicate eligible version in {case['case_id']}")
        for expected in eligible_versions:
            family_id = expected["document_family_id"]
            version_id = expected["document_version_id"]
            if family_id not in families or version_id not in versions:
                raise ValueError(f"unknown eligible version: {family_id}/{version_id}")
            if versions[version_id]["family_id"] != family_id:
                raise ValueError(
                    f"eligible version belongs to another family: {version_id}"
                )
            validate_applicability(
                expected["applicability"], versions[version_id], locations
            )
            if planner["temporal_mode"] in {"CURRENT", "VALID_AT_DATE"}:
                if authoritative_version_at(authority[family_id], moment) != version_id:
                    raise ValueError(
                        f"{case['case_id']} expects temporally ineligible {version_id}"
                    )
            elif expected["side"] == "PRIMARY" and (
                authoritative_version_at(authority[family_id], evaluation_clock)
                != version_id
            ):
                raise ValueError(f"COMPARE primary is not current: {version_id}")

        for excluded in case["eligibility_expectation"]["excluded_versions"]:
            version_id = excluded["document_version_id"]
            if version_id not in versions:
                raise ValueError(f"unknown excluded version: {version_id}")
            if versions[version_id]["family_id"] != excluded["document_family_id"]:
                raise ValueError(
                    f"excluded version belongs to another family: {version_id}"
                )

        evidence_ids: set[str] = set()
        for evidence in case["retrieval_expectation"]["evidence_units"]:
            evidence_id = evidence["evidence_id"]
            if evidence_id in evidence_ids:
                raise ValueError(
                    f"duplicate evidence_id in {case['case_id']}: {evidence_id}"
                )
            evidence_ids.add(evidence_id)
            version_id = evidence["document_version_id"]
            if version_id not in versions:
                raise ValueError(f"unknown evidence version: {version_id}")
            version = versions[version_id]
            if version["family_id"] != evidence["document_family_id"]:
                raise ValueError(f"evidence family/version mismatch: {evidence_id}")
            if version["source_path"] != evidence["source_path"]:
                raise ValueError(f"evidence source/catalogue mismatch: {evidence_id}")
            evidence_key = (
                evidence["document_family_id"],
                evidence["document_version_id"],
                evidence["side"],
            )
            if evidence_key not in eligible_keys:
                raise ValueError(
                    f"evidence is not part of the expected eligible scope: {evidence_id}"
                )
            source = (benchmark_root / evidence["source_path"]).read_text()
            for excerpt in evidence["canonical_excerpts"]:
                if excerpt not in source:
                    raise ValueError(f"canonical excerpt missing for {evidence_id}")

    split_clusters = {
        cluster_id: values
        for cluster_id, values in cluster_splits.items()
        if len(values) > 1
    }
    if split_clusters:
        raise ValueError(f"semantic clusters cross split boundaries: {split_clusters}")


def compile_benchmark(benchmark_root: Path, contract_root: Path) -> None:
    manifest_path = benchmark_root / "manifest.json"
    organisation_path = benchmark_root / "organisation.json"
    catalog_path = benchmark_root / "document-catalog.json"
    split_path = benchmark_root / "splits/v1.json"
    manifest = load_json(manifest_path)
    organisation = load_json(organisation_path)
    catalog = load_json(catalog_path)
    split = load_json(split_path)

    for value, schema_name in (
        (manifest, "benchmark-manifest.schema.json"),
        (organisation, "organisation.schema.json"),
        (catalog, "document-catalog.schema.json"),
        (split, "split.schema.json"),
    ):
        validate_schema(value, contract_root / schema_name)
        assert_no_generated_identifiers(value)

    if len({manifest["evaluation_clock"], organisation["evaluation_clock"]}) != 1:
        raise ValueError("manifest and organisation evaluation clocks differ")
    locations = location_index(organisation)
    families, versions, authority = validate_catalog(
        catalog, manifest, benchmark_root, locations
    )

    case_paths = sorted((benchmark_root / "cases").glob("*.json"))
    cases = [case for path in case_paths for case in load_json(path)]
    cases.sort(key=lambda case: case["case_id"])
    corpus = {
        "schema_version": "v2",
        "benchmark_id": BENCHMARK_ID,
        "corpus_version": manifest["corpus_version"],
        "title": (
            "Dolved Care Engineering Benchmark"
            if manifest["status"] in {"COMPLETE", "BASELINED"}
            else "Dolved Care Engineering Benchmark — pilot"
        ),
        "evaluation_clock": manifest["evaluation_clock"],
        "matching_algorithm": "normalised-token-coverage-v1",
        "cases": cases,
    }
    validate_schema(corpus, contract_root / "corpus.schema.json")
    assert_no_generated_identifiers(corpus)
    validate_cases(
        corpus,
        split,
        manifest,
        benchmark_root,
        families,
        versions,
        authority,
        locations,
    )

    compiled_dir = benchmark_root / "compiled"
    compiled_dir.mkdir(parents=True, exist_ok=True)
    corpus_path = compiled_dir / "corpus.json"
    authority_path = compiled_dir / "authority-windows.json"
    corpus_path.write_text(json.dumps(corpus, indent=2, ensure_ascii=False) + "\n")
    output_authority = (
        dict(sorted(authority.items()))
        if manifest["status"] in {"COMPLETE", "BASELINED"}
        else {
            family_id: authority[family_id]
            for family_id, family in sorted(families.items())
            if any(version["pilot"] for version in family["versions"])
        }
    )
    authority_record = {
        "benchmark_id": BENCHMARK_ID,
        "corpus_version": manifest["corpus_version"],
        "evaluation_clock": manifest["evaluation_clock"],
        "windows": output_authority,
    }
    authority_path.write_text(
        json.dumps(authority_record, indent=2, ensure_ascii=False) + "\n"
    )

    canonical_files = [
        manifest_path,
        organisation_path,
        catalog_path,
        split_path,
        *case_paths,
        *sorted((benchmark_root / "documents").rglob("*.md")),
        corpus_path,
        authority_path,
    ]
    file_digests = {
        path.relative_to(benchmark_root).as_posix(): digest_bytes(path.read_bytes())
        for path in canonical_files
    }
    checksum_record = {
        "schema_version": "v1",
        "benchmark_id": BENCHMARK_ID,
        "corpus_version": manifest["corpus_version"],
        "files": file_digests,
        "benchmark_digest": digest_bytes(canonical_bytes(file_digests)),
    }
    (compiled_dir / "checksums.json").write_text(
        json.dumps(checksum_record, indent=2, ensure_ascii=False) + "\n"
    )


def parser() -> argparse.ArgumentParser:
    root = argparse.ArgumentParser()
    root.add_argument("--benchmark-root", type=Path, required=True)
    root.add_argument("--contract-root", type=Path, required=True)
    return root


if __name__ == "__main__":
    arguments = parser().parse_args()
    compile_benchmark(arguments.benchmark_root, arguments.contract_root)
