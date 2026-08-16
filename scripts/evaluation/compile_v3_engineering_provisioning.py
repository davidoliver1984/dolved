"""Compile the provider-free Benchmark V3 engineering provisioning definition."""

from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Any
from uuid import NAMESPACE_URL, uuid5

from compile_v3_engineering_population import (
    BENCHMARK_ROOT,
    POPULATION_ID,
    POPULATION_ROOT,
    content_digest,
    load,
    sha256,
    source_catalog_digest,
    write_json,
)


def stable_uuid(kind: str, identity: str) -> str:
    namespace = uuid5(
        NAMESPACE_URL, "https://dolved.invalid/evaluation/v3-engineering/v1"
    )
    return str(uuid5(namespace, f"{kind}:{identity}"))


def provisioning_definition(manifest: dict[str, Any]) -> dict[str, Any]:
    organisation = load(BENCHMARK_ROOT / "organisation.json")
    catalog = load(BENCHMARK_ROOT / "document-catalog.json")
    workspace_id = stable_uuid("workspace", POPULATION_ID)
    embedding_id = stable_uuid("embedding-space", "voyage-4-large:1024")
    sparse_id = stable_uuid("sparse-space", "prithivida/Splade_PP_en_v1")
    corpus_id = stable_uuid("workspace-corpus", POPULATION_ID)
    document_records = []
    for family in catalog["families"]:
        for version in family["versions"]:
            document_records.append(
                {
                    "document_family_id": family["family_id"],
                    "document_family_public_id": stable_uuid(
                        "document-family", family["family_id"]
                    ),
                    "document_version_id": version["version_id"],
                    "document_public_id": stable_uuid(
                        "document", version["version_id"]
                    ),
                    "source_path": version["source_path"],
                    "source_sha256": version["source_sha256"],
                    "governance_state": version["governance_state"],
                    "effective_from": version["effective_from"],
                    "approved_at": version["approved_at"],
                    "withdrawn_at": version["withdrawn_at"],
                    "applicability": version["applicability"],
                }
            )
    definition: dict[str, Any] = {
        "schema_version": "v1",
        "provisioning_id": "dolved-care-engineering-v3-engineering-v1",
        "status": "DEFINITION_ONLY",
        "benchmark": {
            "id": "dolved-care-engineering",
            "version": "3",
            "authoring_digest": manifest["benchmark_authoring_digest"],
            "population_digest": manifest["population_digest"],
            "organisation_sha256": sha256(BENCHMARK_ROOT / "organisation.json"),
            "document_catalog_sha256": sha256(BENCHMARK_ROOT / "document-catalog.json"),
            "source_catalog_digest": source_catalog_digest(),
        },
        "workspace": {
            "logical_id": "workspace.evaluation.dolved-care-engineering.v3",
            "public_id": workspace_id,
            "name": "Dolved Care Engineering Benchmark V3",
            "slug": "evaluation-dolved-care-engineering-v3",
        },
        "locations": [
            {
                **location,
                "public_id": stable_uuid("location", location["location_id"]),
                "aliases": sorted(
                    alias["alias"]
                    for alias in organisation["aliases"]
                    if location["location_id"] in alias["location_ids"]
                ),
            }
            for location in organisation["locations"]
        ],
        "document_families": [
            {
                "document_family_id": family["family_id"],
                "public_id": stable_uuid("document-family", family["family_id"]),
                "domain": family["domain"],
                "leakage_group_ids": family["leakage_group_ids"],
            }
            for family in catalog["families"]
        ],
        "documents": document_records,
        "canonical_chunks": {
            "materialisation_status": "PENDING_NORMAL_INGESTION",
            "identity_owner": "baseline-structural chunker after authoritative document IDs exist",
            "strategy": "baseline-structural",
            "strategy_version": "1",
            "configuration": {
                "tokenizer": "tiktoken/o200k_base",
                "target_tokens": 400,
                "max_tokens": 512,
                "overlap_tokens": 64,
                "preferred_min_tokens": 100,
            },
            "expected_count": None,
            "reason": (
                "Canonical chunks are created through the accepted ingestion protocol; "
                "the definition does not fabricate pre-ingestion chunk identities."
            ),
        },
        "vector_projection": {
            "materialisation_status": "PENDING_PROVIDER_BACKED_INGESTION",
            "collection_name": "rag-platform-vectors-v1",
            "workspace_corpus_generation_id": corpus_id,
            "dense": {
                "embedding_space_generation_id": embedding_id,
                "provider": "voyage",
                "model": "voyage-4-large",
                "dimensions": 1024,
                "vector_name": "text-dense-v1",
                "distance": "COSINE",
                "profile_fingerprint": (
                    "ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c"
                ),
            },
            "sparse": {
                "sparse_space_generation_id": sparse_id,
                "provider": "fastembed",
                "model": "prithivida/Splade_PP_en_v1",
                "vector_name": "text-sparse-v1",
                "profile_fingerprint": (
                    "e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d"
                ),
            },
            "point_identity_algorithm": "vector-point/v1 UUIDv5",
            "expected_point_count": None,
        },
        "provider_calls_performed": False,
    }
    definition["definition_digest"] = content_digest(definition)
    return definition


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--population-manifest",
        type=Path,
        default=POPULATION_ROOT / "population-manifest.json",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=POPULATION_ROOT / "provisioning-definition.json",
    )
    values = parser.parse_args()
    definition = provisioning_definition(load(values.population_manifest))
    write_json(values.output, definition)
    print(
        json.dumps(
            {"provisioning_definition_digest": definition["definition_digest"]},
            indent=2,
        )
    )


if __name__ == "__main__":
    main()
