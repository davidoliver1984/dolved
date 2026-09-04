from __future__ import annotations

import importlib.util
import json
from pathlib import Path
from unittest.mock import MagicMock, patch

import pytest

ROOT = Path(__file__).resolve().parents[3]
DEFINITION = ROOT / "docs/evaluation/r28-s03/run-definition.json"
SCRIPT = ROOT / "scripts/evaluation/materialise_r28_s03.py"
VECTOR_PROVISIONER = ROOT / "scripts/evaluation/provision_r28_s03_vector_space.py"


def load_materialiser():
    spec = importlib.util.spec_from_file_location("r28_s03_materialiser", SCRIPT)
    assert spec is not None and spec.loader is not None
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def load_vector_provisioner():
    spec = importlib.util.spec_from_file_location(
        "r28_s03_vector_provisioner", VECTOR_PROVISIONER
    )
    assert spec is not None and spec.loader is not None
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def test_r28_s03_definition_preserves_four_governed_scopes_without_providers() -> None:
    definition = json.loads(DEFINITION.read_text())
    assert definition["run_id"] == "R28-S03-V4-CORPUS-MATERIALISATION-0011"
    assert definition["prior_attempts"] == [
        {
            "run_id": "R28-S03-V4-CORPUS-MATERIALISATION-0001",
            "outcome": "failed_before_import_batch_creation",
            "cause": (
                "The E2E organisation provisioner incorrectly required an aliases "
                "array that the valid foreign-tenant organisation manifest omits."
            ),
            "durable_state": {
                "workspaces_created": 3,
                "import_batches_created": 0,
                "documents_created": 0,
            },
            "provider_calls": 0,
            "aws_calls": 0,
            "selective_reruns": 0,
        },
        {
            "run_id": "R28-S03-V4-CORPUS-MATERIALISATION-0002",
            "outcome": "failed_before_import_batch_creation",
            "cause": (
                "The materialisation harness did not apply its governed "
                "effective-date fallback when a valid manifest entry explicitly "
                "contained null."
            ),
            "durable_state": {
                "workspaces_created": 3,
                "import_batches_created": 0,
                "documents_created": 0,
            },
            "provider_calls": 0,
            "aws_calls": 0,
            "selective_reruns": 0,
        },
        {
            "run_id": "R28-S03-V4-CORPUS-MATERIALISATION-0003",
            "outcome": "failed_during_first_import_batch_upload",
            "cause": (
                "The harness did not account for PHP serialising an empty "
                "signed-upload header map as an empty JSON list."
            ),
            "durable_state": {
                "workspaces_created": 3,
                "import_batches_created": 1,
                "import_items_created": 25,
                "documents_created": 0,
            },
            "provider_calls": 0,
            "aws_calls": 0,
            "selective_reruns": 0,
        },
        {
            "run_id": "R28-S03-V4-CORPUS-MATERIALISATION-0004",
            "outcome": "failed_during_primary_promotion",
            "cause": (
                "Two versions with null effective dates received the same fallback "
                "date, and the application correctly rejected the successor because "
                "it was not later than its predecessor."
            ),
            "durable_state": {
                "workspaces_created": 3,
                "import_batches_created": 9,
                "import_items_created": 210,
                "documents_created": 209,
                "documents_indexed": 209,
                "failed_promotions": 1,
            },
            "provider_calls": 0,
            "aws_calls": 0,
            "selective_reruns": 0,
        },
        {
            "run_id": "R28-S03-V4-CORPUS-MATERIALISATION-0005",
            "outcome": "failed_after_primary_ingestion_before_governance_transition",
            "cause": (
                "The materialisation harness used the import-workflow field name "
                "filename when indexing the canonical document-administration "
                "response, whose reviewed field is source_filename."
            ),
            "durable_state": {
                "workspaces_created": 3,
                "import_batches_created": 14,
                "import_items_created": 300,
                "documents_created": 300,
                "documents_indexed": 300,
                "canonical_chunks_created": 982,
                "documents_remaining_draft": 300,
            },
            "provider_calls": 0,
            "aws_calls": 0,
            "selective_reruns": 0,
        },
        {
            "run_id": "R28-S03-V4-CORPUS-MATERIALISATION-0006",
            "outcome": "failed_after_primary_ingestion_at_first_governance_transition",
            "cause": (
                "The materialisation harness prefixed its governance idempotency "
                "value, while the canonical governance request contract requires "
                "a UUID."
            ),
            "durable_state": {
                "workspaces_created": 3,
                "import_batches_created": 14,
                "import_items_created": 300,
                "documents_created": 300,
                "documents_indexed": 300,
                "canonical_chunks_created": 982,
                "documents_remaining_draft": 300,
            },
            "provider_calls": 0,
            "aws_calls": 0,
            "selective_reruns": 0,
        },
        {
            "run_id": "R28-S03-V4-CORPUS-MATERIALISATION-0007",
            "outcome": "failed_during_primary_governance_transition",
            "cause": (
                "Historical approvals were replayed at the current wall clock. "
                "Versions whose effective dates were already past therefore shared "
                "an authority-start timestamp, and the application correctly "
                "rejected the collision."
            ),
            "durable_state": {
                "workspaces_created": 3,
                "import_batches_created": 14,
                "import_items_created": 300,
                "documents_created": 300,
                "documents_indexed": 300,
                "canonical_chunks_created": 982,
                "documents_remaining_draft": 204,
                "documents_approved": 93,
                "documents_withdrawn": 3,
            },
            "provider_calls": 0,
            "aws_calls": 0,
            "selective_reruns": 0,
        },
        {
            "run_id": "R28-S03-V4-CORPUS-MATERIALISATION-0008",
            "outcome": (
                "failed_after_all_searchable_scopes_before_governance_transition"
            ),
            "cause": (
                "The negative-fixture harness recorded the oversized request "
                "under its simulated request filename instead of the governed "
                "manifest fixture filename, so the exact 13-fixture inventory "
                "check correctly failed closed."
            ),
            "durable_state": {
                "workspaces_created": 3,
                "import_batches_created": 19,
                "import_items_created": 331,
                "documents_created": 318,
                "documents_indexed": 318,
                "canonical_chunks_created": 1000,
                "documents_remaining_draft": 318,
                "preflight_verified_items": 329,
                "preflight_rejected_items": 2,
            },
            "provider_calls": 0,
            "aws_calls": 0,
            "selective_reruns": 0,
        },
        {
            "run_id": "R28-S03-V4-CORPUS-MATERIALISATION-0009",
            "outcome": "failed_post_materialisation_verification_sparse_axis_absent",
            "cause": (
                "All four governed scopes completed, but the E2E retrieval "
                "provisioner created the deterministic sparse space without "
                "binding it to each workspace corpus generation. Verification "
                "found 1,000 dense-only Qdrant points and zero sparse-bound "
                "ingestion claims or active corpus generations."
            ),
            "durable_state": {
                "workspaces_created": 3,
                "import_batches_created": 19,
                "import_items_created": 331,
                "documents_created": 318,
                "documents_indexed": 318,
                "canonical_chunks_created": 1000,
                "negative_fixtures_observed": 13,
                "primary_governance": {
                    "draft": 9,
                    "approved": 224,
                    "withdrawn": 67,
                },
                "foreign_governance": {
                    "draft": 0,
                    "approved": 12,
                    "withdrawn": 0,
                },
                "injection_governance": {
                    "draft": 0,
                    "approved": 6,
                    "withdrawn": 0,
                },
                "qdrant_points": 1000,
                "sparse_bound_claims": 0,
                "sparse_bound_active_corpus_generations": 0,
            },
            "provider_calls": 0,
            "aws_calls": 0,
            "selective_reruns": 0,
        },
        {
            "run_id": "R28-S03-V4-CORPUS-MATERIALISATION-0010",
            "outcome": "failed_before_hybrid_rebuild_first_batch",
            "cause": (
                "The isolated collection was first created by dense ingestion. "
                "Qdrant correctly rejected an attempt to add a previously absent "
                "named sparse vector to that existing collection."
            ),
            "durable_state": {
                "workspaces_created": 3,
                "import_batches_created": 19,
                "import_items_created": 331,
                "documents_created": 318,
                "documents_indexed": 318,
                "canonical_chunks_created": 1000,
                "negative_fixtures_observed": 13,
                "qdrant_points": 1000,
                "hybrid_rebuild_batches_completed": 0,
            },
            "provider_calls": 0,
            "aws_calls": 0,
            "selective_reruns": 0,
        },
    ]
    assert definition["execution"]["provider_calls_permitted"] is False
    assert definition["execution"]["aws_access_permitted"] is False
    assert definition["scopes"]["primary"]["expected_documents"] == 300
    assert definition["scopes"]["foreign_tenant"]["expected_documents"] == 12
    assert definition["scopes"]["prompt_injection_pack"]["expected_documents"] == 6
    assert definition["scopes"]["negative_import_fixtures"]["expected_fixtures"] == 13
    assert (
        definition["scopes"]["negative_import_fixtures"][
            "ordinary_searchable_promotions_permitted"
        ]
        is False
    )


def test_r28_s03_materialiser_uses_governed_media_types() -> None:
    materialiser = load_materialiser()
    assert materialiser.media_type("policy.pdf") == "application/pdf"
    assert (
        materialiser.media_type("policy.docx")
        == "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
    )
    assert materialiser.media_type("policy.txt") == "text/plain"


def test_r28_s03_materialiser_defaults_null_and_omitted_effective_dates() -> None:
    materialiser = load_materialiser()
    assert materialiser.effective_date({"effective_date": "2025-06-15"}) == (
        "2025-06-15"
    )
    assert materialiser.effective_date({"effective_date": None}) == "2026-01-01"
    assert materialiser.effective_date({}) == "2026-01-01"
    assert (
        materialiser.effective_date(
            {"effective_date": None, "superseded_date": "2025-08-01"}
        )
        == "2025-07-31"
    )


def test_r28_s03_upload_accepts_only_empty_list_as_header_map_wire_ambiguity() -> None:
    materialiser = load_materialiser()
    client = materialiser.ApiClient("http://api.test", "http://web.test")
    response = MagicMock()
    response.status = 200
    response.__enter__.return_value = response
    with patch.object(materialiser.urllib.request, "urlopen", return_value=response):
        client.put_bytes("http://storage.test/object", b"content", [])
    with pytest.raises(TypeError, match="named map or an empty list"):
        client.put_bytes(
            "http://storage.test/object",
            b"content",
            [{"name": "Content-Type", "value": "text/plain"}],
        )


def test_r28_s03_materialiser_uses_import_api_and_frozen_governance_command() -> None:
    source = SCRIPT.read_text()
    runner = (ROOT / "scripts/evaluation/run_r28_s03.sh").read_text()
    assert "/imports" in source
    assert "/decision" in source
    assert "/promotions" in source
    assert "/governance/approve" not in source
    assert "/governance/withdraw" not in source
    assert runner.count("e2e:apply-frozen-governance") == 3
    assert runner.count("retrieval:rebuild-hybrid-corpus") == 3
    assert "sparse_space_generation_id" in runner
    assert "/documents/uploads" not in source
    assert "OPENAI" not in source.upper()
    assert "VOYAGE" not in source.upper()


def test_r28_s03_hybrid_schema_is_created_before_materialisation() -> None:
    runner = (ROOT / "scripts/evaluation/run_r28_s03.sh").read_text()
    provision = runner.index("provision_r28_s03_vector_space.py")
    materialise = runner.index("materialise_r28_s03.py")
    rebuild = runner.index("retrieval:rebuild-hybrid-corpus")
    assert provision < materialise < rebuild
    assert "vector-space-provisioning.json" in runner


def test_r28_s03_vector_provisioner_binds_exact_hybrid_lineage() -> None:
    provisioner = load_vector_provisioner()
    profile = json.loads(
        (ROOT / "contracts/testing/deterministic-retrieval-profile-v1.json").read_text()
    )
    vector_space = provisioner.vector_space_from_profile(
        profile,
        embedding_space_generation_id=provisioner.UUID(
            "11111111-1111-4111-8111-111111111111"
        ),
        sparse_space_generation_id=provisioner.UUID(
            "22222222-2222-4222-8222-222222222222"
        ),
    )
    assert vector_space.collection_name == "dolved-e2e-vectors-v1"
    assert vector_space.vector_name == "dense"
    assert vector_space.dimensions == 1024
    assert vector_space.profile_fingerprint == profile["dense"]["fingerprint"]
    assert vector_space.sparse is not None
    assert vector_space.sparse.vector_name == "sparse"
    assert vector_space.sparse.profile_fingerprint == profile["sparse"]["fingerprint"]


def test_r28_s03_oversized_simulation_uses_governed_fixture_identity() -> None:
    source = SCRIPT.read_text()
    assert '"annual-company-report-2026-full.pdf"' in source
    assert '"oversized-file-simulation.json"' in source
    assert "outcomes[fixture_filename]" in source
    assert '"request_filename": request_filename' in source


def test_r28_s03_governance_replay_uses_canonical_document_field() -> None:
    command = (
        ROOT / "apps/api/app/Console/Commands/ApplyE2eFrozenGovernanceCommand.php"
    ).read_text()
    assert "->keyBy('source_filename')" in command


def test_r28_s03_import_promotions_use_unique_idempotency_keys() -> None:
    source = SCRIPT.read_text()
    assert source.count('f"r28-s03-{uuid.uuid4()}"') == 1
    assert "r28-s03-approve-" not in source
    assert "r28-s03-withdraw-" not in source
