import hashlib
import json
import os
import subprocess
import sys
from pathlib import Path
from typing import Any

REPOSITORY = Path(os.environ.get("V3_ENGINEERING_REPOSITORY", "/workspace"))
EVALUATION = Path(os.environ.get("V3_ENGINEERING_EVALUATION_ROOT", "/evaluation"))
POPULATION = EVALUATION / ("engineering-populations/dolved-care-engineering/v3/v1")
BENCHMARK = EVALUATION / "benchmarks/dolved-care-engineering/v3"


def load(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text())


def digest(value: Any) -> str:
    encoded = json.dumps(
        value, ensure_ascii=False, sort_keys=True, separators=(",", ":")
    ).encode()
    return hashlib.sha256(encoded).hexdigest()


def test_provisioning_definition_is_deterministic_and_truthful(tmp_path: Path) -> None:
    output = tmp_path / "provisioning-definition.json"
    subprocess.run(
        [
            sys.executable,
            str(
                REPOSITORY / "scripts/evaluation/compile_v3_engineering_provisioning.py"
            ),
            "--population-manifest",
            str(POPULATION / "population-manifest.json"),
            "--output",
            str(output),
        ],
        check=True,
        cwd=REPOSITORY,
    )
    assert (
        output.read_bytes()
        == (POPULATION / "provisioning-definition.json").read_bytes()
    )

    manifest = load(POPULATION / "population-manifest.json")
    provisioning = load(output)
    assert (
        provisioning["benchmark"]["population_digest"] == manifest["population_digest"]
    )
    definition = {
        key: value for key, value in provisioning.items() if key != "definition_digest"
    }
    assert provisioning["definition_digest"] == digest(definition)
    assert provisioning["status"] == "DEFINITION_ONLY"
    assert provisioning["provider_calls_performed"] is False
    assert len(provisioning["locations"]) == 10
    assert len(provisioning["document_families"]) == 71
    assert len(provisioning["documents"]) == 93
    assert provisioning["canonical_chunks"]["expected_count"] is None
    assert provisioning["vector_projection"]["expected_point_count"] is None
    assert provisioning["vector_projection"]["dense"]["model"] == "voyage-4-large"
    assert (
        provisioning["vector_projection"]["sparse"]["model"]
        == "prithivida/Splade_PP_en_v1"
    )


def test_provisioning_preserves_alias_hierarchy_and_applicability_truth() -> None:
    organisation = load(BENCHMARK / "organisation.json")
    catalog = load(BENCHMARK / "document-catalog.json")
    provisioning = load(POPULATION / "provisioning-definition.json")
    planned_locations = {
        location["location_id"]: location for location in provisioning["locations"]
    }
    assert set(planned_locations) == {
        location["location_id"] for location in organisation["locations"]
    }
    assert all(
        planned_locations[location["location_id"]]["parent_location_id"]
        == location["parent_location_id"]
        for location in organisation["locations"]
    )
    expected_aliases = {
        location_id: sorted(
            alias["alias"]
            for alias in organisation["aliases"]
            if location_id in alias["location_ids"]
        )
        for location_id in planned_locations
    }
    assert {
        location_id: value["aliases"]
        for location_id, value in planned_locations.items()
    } == expected_aliases

    catalog_versions = {
        version["version_id"]: version
        for family in catalog["families"]
        for version in family["versions"]
    }
    planned_documents = {
        document["document_version_id"]: document
        for document in provisioning["documents"]
    }
    assert set(planned_documents) == set(catalog_versions)
    assert all(
        planned_documents[version_id]["applicability"] == version["applicability"]
        for version_id, version in catalog_versions.items()
    )
