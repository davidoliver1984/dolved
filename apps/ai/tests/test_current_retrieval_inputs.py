from __future__ import annotations

import hashlib
import json
from pathlib import Path
from typing import Any
from uuid import UUID

import pytest
from jsonschema import Draft202012Validator

from app.evaluation.canonical import content_digest
from app.evaluation.current_retrieval import load_current_retrieval_inputs

COMMIT = "a" * 40
PUBLIC_ID = "11111111-1111-4111-8111-111111111111"
QDRANT_ID = "22222222-2222-4222-8222-222222222222"


def test_expected_evidence_changes_do_not_change_laravel_scopes(tmp_path: Path) -> None:
    paths = _fixture(tmp_path)
    first = _load(paths)
    snapshot = json.loads(paths["snapshot"].read_text())
    snapshot["cases"][0]["retrieval_expectation"]["evidence_units"][0][
        "canonical_excerpts"
    ] = ["A deliberately different evaluation label."]
    paths["snapshot"].write_text(json.dumps(snapshot))
    second = _load(paths)

    assert first.scopes == second.scopes
    assert first.resolutions == second.resolutions
    assert first.corpus.cases[0].evidence_units != second.corpus.cases[0].evidence_units


@pytest.mark.parametrize("mutation", ["digest", "entry", "mapping", "plan"])
def test_tampered_or_mismatched_cross_language_inputs_fail_closed(
    tmp_path: Path, mutation: str
) -> None:
    paths = _fixture(tmp_path)
    artifact = json.loads(paths["artifact"].read_text())
    if mutation == "digest":
        artifact["evaluated_at"] = "2027-01-01T00:00:00Z"
    elif mutation == "entry":
        artifact["entries"] = []
        _redigest(artifact)
    elif mutation == "mapping":
        artifact["entries"][0]["document_public_ids_by_side"]["primary"] = [
            "33333333-3333-4333-8333-333333333333"
        ]
        _redigest(artifact)
    else:
        plans = json.loads(paths["plans"].read_text())
        plans["expectations"][0]["question"] = "Changed question"
        paths["plans"].write_text(json.dumps(plans))
    paths["artifact"].write_text(json.dumps(artifact))

    with pytest.raises((ValueError, TypeError)):
        _load(paths)


def test_source_population_is_independent_and_checksum_bound(tmp_path: Path) -> None:
    paths = _fixture(tmp_path)
    loaded = _load(paths)

    assert loaded.chunks[0].text == "Independent policy source text."
    assert loaded.chunks[0].document_id == UUID(QDRANT_ID)
    paths["source"].write_text("Altered source")
    with pytest.raises(ValueError, match="source document checksum mismatch"):
        _load(paths)


def test_exact_run_digest_binds_commit_but_comparability_digest_does_not(
    tmp_path: Path,
) -> None:
    paths = _fixture(tmp_path)
    artifact = json.loads(paths["artifact"].read_text())
    original_artifact_digest = artifact["artifact_digest"]
    original_comparability_digest = artifact["comparability_digest"]

    artifact["repository_commit"] = "b" * 40
    _redigest(artifact)

    assert artifact["artifact_digest"] != original_artifact_digest
    assert artifact["comparability_digest"] == original_comparability_digest


def test_shared_eligibility_artifact_schema_is_valid() -> None:
    schema = json.loads(
        Path(
            "/contracts/evaluation/v2/deterministic-eligibility-artifact.schema.json"
        ).read_text()
    )

    Draft202012Validator.check_schema(schema)


def _load(paths: dict[str, Path]):
    return load_current_retrieval_inputs(
        snapshot_path=paths["snapshot"],
        document_catalog_path=paths["catalog"],
        organisation_path=paths["organisation"],
        source_root=paths["root"],
        checksums_path=paths["checksums"],
        plan_catalogue_path=paths["plans"],
        eligibility_artifact_path=paths["artifact"],
        repository_commit=COMMIT,
    )


def _fixture(root: Path) -> dict[str, Path]:
    documents = root / "documents"
    documents.mkdir()
    source = documents / "policy.md"
    source.write_text("Independent policy source text.")
    catalog = {
        "schema_version": "v2",
        "benchmark_id": "dolved-care-engineering",
        "catalog_version": "1",
        "families": [
            {
                "family_id": "family.policy",
                "versions": [
                    {
                        "version_id": "doc.policy.v1",
                        "source_path": "documents/policy.md",
                    }
                ],
            }
        ],
    }
    organisation = {
        "schema_version": "v2",
        "benchmark_id": "dolved-care-engineering",
        "evaluation_clock": "2026-08-01T12:00:00Z",
    }
    plans = {
        "schema_version": "v2",
        "scope": "engineering_tuning",
        "expectations": [
            {
                "case_id": "case.policy",
                "variant_id": "direct",
                "question": "What is the policy?",
                "contract": {
                    "contract_version": 2,
                    "temporal_mode": "current",
                    "explicit_date": None,
                    "temporal_reference": None,
                    "location_references": [],
                    "clarification_reason": None,
                },
            }
        ],
    }
    snapshot = {
        "schema_version": "v1",
        "cases": [
            {
                "case_id": "case.policy",
                "cluster_id": "cluster.policy",
                "eligibility_expectation": {
                    "expected_outcome": "ELIGIBLE_SCOPE_READY",
                    "eligible_versions": [
                        {
                            "document_family_id": "family.policy",
                            "document_version_id": "doc.policy.v1",
                            "side": "PRIMARY",
                        }
                    ],
                    "excluded_versions": [],
                },
                "outcome_expectation": {"outcome": "EVIDENCE_FOUND"},
                "planner_expectation": {"temporal_mode": "CURRENT"},
                "retrieval_expectation": {
                    "evidence_units": [
                        {
                            "evidence_id": "policy.fact",
                            "document_family_id": "family.policy",
                            "document_version_id": "doc.policy.v1",
                            "side": "PRIMARY",
                            "source_path": "documents/policy.md",
                            "canonical_excerpts": ["Policy source text."],
                            "relevance_grade": 2,
                            "minimum_token_coverage": 1.0,
                            "notes": None,
                        }
                    ]
                },
                "slices": ["CURRENT"],
                "variants": [
                    {"variant_id": "direct", "question": "What is the policy?"}
                ],
            }
        ],
    }
    catalog_path = root / "catalog.json"
    organisation_path = root / "organisation.json"
    plans_path = root / "plans.json"
    snapshot_path = root / "snapshot.json"
    for path, value in (
        (catalog_path, catalog),
        (organisation_path, organisation),
        (plans_path, plans),
        (snapshot_path, snapshot),
    ):
        path.write_text(json.dumps(value))
    checksums = {
        "schema_version": "v1",
        "benchmark_id": "dolved-care-engineering",
        "files": {
            "document-catalog.json": _sha256(catalog_path),
            "organisation.json": _sha256(organisation_path),
            "documents/policy.md": _sha256(source),
        },
    }
    checksums_path = root / "checksums.json"
    checksums_path.write_text(json.dumps(checksums))
    eligibility_binding = {
        "version": "dolved-care-engineering-v2",
        "document_catalog_digest": content_digest(catalog),
        "organisation_digest": content_digest(organisation),
    }
    eligibility_binding["digest"] = content_digest(eligibility_binding)
    artifact: dict[str, Any] = {
        "schema_version": "v1",
        "contract_id": "deterministic-eligibility-v1",
        "run_id": "current-retrieval-test",
        "repository_commit": COMMIT,
        "evaluated_at": "2026-08-01T12:00:00Z",
        "workspace_public_id": "44444444-4444-4444-8444-444444444444",
        "plan_catalogue": {"version": "v2", "digest": content_digest(plans)},
        "eligibility_catalogue": eligibility_binding,
        "resolver": {
            "implementation": "App\\Services\\Retrieval\\EligibilityResolver",
            "boundary": "evaluation:resolve-current-eligibility",
            "source_digest": "1" * 64,
            "configuration_digest": "2" * 64,
        },
        "documents": [
            {
                "evaluation_document_version_id": "doc.policy.v1",
                "public_document_id": PUBLIC_ID,
                "qdrant_document_id": QDRANT_ID,
            }
        ],
        "entries": [
            {
                "case_id": "case.policy",
                "variant_id": "direct",
                "question_digest": hashlib.sha256(b"What is the policy?").hexdigest(),
                "outcome": "evidence_found",
                "reason": None,
                "clarification_source": None,
                "resolved_location_public_id": None,
                "document_public_ids_by_side": {"primary": [PUBLIC_ID]},
            }
        ],
        "probes": {
            "no_active_corpus_generation": {
                "resolver_executed": True,
                "outcome": "no_eligible_evidence",
                "eligible_document_count": 0,
            }
        },
        "isolation": {
            "foreign_workspace_probe_executed": True,
            "cross_workspace_document_count_in_scopes": 0,
        },
    }
    _redigest(artifact)
    artifact_path = root / "artifact.json"
    artifact_path.write_text(json.dumps(artifact))
    return {
        "root": root,
        "source": source,
        "catalog": catalog_path,
        "organisation": organisation_path,
        "plans": plans_path,
        "snapshot": snapshot_path,
        "checksums": checksums_path,
        "artifact": artifact_path,
    }


def _redigest(artifact: dict[str, Any]) -> None:
    artifact.pop("artifact_digest", None)
    artifact["comparability_digest"] = content_digest(
        {
            key: artifact[key]
            for key in (
                "schema_version",
                "contract_id",
                "evaluated_at",
                "plan_catalogue",
                "eligibility_catalogue",
                "resolver",
                "documents",
                "entries",
                "probes",
                "isolation",
            )
        }
    )
    artifact["artifact_digest"] = content_digest(artifact)


def _sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()
