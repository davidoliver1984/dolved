from __future__ import annotations

import importlib.util
import json
from pathlib import Path
from unittest.mock import MagicMock, patch

import pytest

ROOT = Path(__file__).resolve().parents[3]
DEFINITION = ROOT / "docs/evaluation/r28-s03/run-definition.json"
SCRIPT = ROOT / "scripts/evaluation/materialise_r28_s03.py"


def load_materialiser():
    spec = importlib.util.spec_from_file_location("r28_s03_materialiser", SCRIPT)
    assert spec is not None and spec.loader is not None
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def test_r28_s03_definition_preserves_four_governed_scopes_without_providers() -> None:
    definition = json.loads(DEFINITION.read_text())
    assert definition["run_id"] == "R28-S03-V4-CORPUS-MATERIALISATION-0005"
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


def test_r28_s03_materialiser_calls_import_and_governance_apis() -> None:
    source = SCRIPT.read_text()
    assert "/imports" in source
    assert "/decision" in source
    assert "/promotions" in source
    assert "/governance/approve" in source
    assert "/governance/withdraw" in source
    assert "/documents/uploads" not in source
    assert "OPENAI" not in source.upper()
    assert "VOYAGE" not in source.upper()
