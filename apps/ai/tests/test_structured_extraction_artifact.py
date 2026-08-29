import json
import math
from copy import deepcopy
from pathlib import Path
from typing import Any
from uuid import UUID

import pytest
import rfc8785
from jsonschema import Draft202012Validator, FormatChecker

from app.extraction.models import ExtractedDocumentMetadata, ExtractorIdentity
from app.normalisation.artifact import (
    ExtractionArtifactLimitError,
    artifact_digest,
    canonical_artifact_bytes,
    document_extraction_artifact,
    projection_manifest_digest,
    validate_artifact_limits,
    warning_manifest_digest,
)
from app.normalisation.models import NormalisedDocument, NormaliserIdentity

CONTRACT_ROOT = Path("/contracts/documents/extraction-artifact/v1")


def _vectors() -> dict[str, Any]:
    return json.loads(
        (CONTRACT_ROOT / "canonicalisation-vectors.json").read_text(encoding="utf-8")
    )


def test_shared_vector_matches_schema_and_all_three_digests() -> None:
    vectors = _vectors()
    schema = json.loads(
        (CONTRACT_ROOT / "document-extraction-artifact-v1.schema.json").read_text(
            encoding="utf-8"
        )
    )

    Draft202012Validator(schema, format_checker=FormatChecker()).validate(
        vectors["artifact"]
    )
    assert (
        artifact_digest(vectors["artifact"]) == vectors["expected"]["artifact_sha256"]
    )
    assert (
        projection_manifest_digest(vectors["artifact"])
        == vectors["expected"]["projection_manifest_sha256"]
    )
    assert (
        warning_manifest_digest(vectors["artifact"])
        == vectors["expected"]["warning_manifest_sha256"]
    )


def test_uuid_and_unicode_rules_are_canonical_without_content_normalisation() -> None:
    vectors = _vectors()
    canonical = canonical_artifact_bytes(vectors["artifact"])

    assert b"AAAAAAAA-AAAA" not in canonical
    assert b"aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1" in canonical
    assert vectors["unicode_distinct_pair"][0] != vectors["unicode_distinct_pair"][1]
    assert all(
        value.encode("utf-8") in canonical for value in vectors["unicode_distinct_pair"]
    )


def test_shared_rfc8785_number_vectors_preserve_full_precision() -> None:
    for vector in _vectors()["number_vectors"]:
        assert (
            canonical_artifact_bytes({"value": vector["value"]}).decode()
            == vector["canonical"]
        )


def test_non_finite_numbers_and_invalid_unicode_fail_closed() -> None:
    vectors = _vectors()
    invalid_number = dict(vectors["artifact"])
    invalid_number["elements"] = [dict(vectors["artifact"]["elements"][0])]
    invalid_number["elements"][0]["confidence"] = math.nan

    with pytest.raises(rfc8785.FloatDomainError):
        canonical_artifact_bytes(invalid_number)

    invalid_text = dict(vectors["artifact"])
    invalid_text["text"] = "\ud800"
    with pytest.raises((UnicodeEncodeError, rfc8785.CanonicalizationError)):
        canonical_artifact_bytes(invalid_text)


def test_builder_excludes_ownership_and_preserves_nullable_metadata() -> None:
    document = NormalisedDocument(
        workspace_id=UUID("00000000-0000-4000-8000-000000000001"),
        document_id=UUID("00000000-0000-4000-8000-000000000002"),
        source_media_type="text/plain",
        source_byte_size=12,
        source_extractor=ExtractorIdentity(name="plain-text", version="1"),
        normaliser=NormaliserIdentity(name="structural", version="1"),
        text="",
        metadata=ExtractedDocumentMetadata(),
    )

    artifact = document_extraction_artifact(document)

    assert "workspace_id" not in artifact
    assert "document_id" not in artifact
    assert artifact["metadata"] == {
        "title": None,
        "author": None,
        "subject": None,
        "keywords": None,
        "creator": None,
        "producer": None,
        "creation_date": None,
        "modification_date": None,
    }


@pytest.mark.parametrize(
    ("mutation", "limit_overrides", "expected_code"),
    [
        (None, {"max_bytes": 1}, "extraction_artifact_too_large"),
        ("elements", {"max_elements": 0}, "extraction_artifact_element_limit_exceeded"),
        (
            "text",
            {"max_element_text_bytes": 1},
            "extraction_artifact_element_text_limit_exceeded",
        ),
        ("warnings", {"max_warnings": 0}, "extraction_artifact_warning_limit_exceeded"),
        ("version", {}, "extraction_artifact_contract_unsupported"),
    ],
)
def test_every_artifact_cap_fails_closed_with_a_typed_code(
    mutation: str | None,
    limit_overrides: dict[str, int],
    expected_code: str,
) -> None:
    artifact = deepcopy(_vectors()["artifact"])
    if mutation == "version":
        artifact["contract_version"] = "document-extraction-artifact-v2"
    limits: dict[str, Any] = {
        "max_bytes": 52_428_800,
        "max_elements": 100_000,
        "max_element_text_bytes": 1_048_576,
        "max_warnings": 10_000,
        "supported_contract_versions": ["document-extraction-artifact-v1"],
        **limit_overrides,
    }

    with pytest.raises(ExtractionArtifactLimitError) as captured:
        validate_artifact_limits(artifact, canonical_artifact_bytes(artifact), limits)

    assert captured.value.code == expected_code
