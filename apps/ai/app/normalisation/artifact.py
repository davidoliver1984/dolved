import hashlib
from collections.abc import Mapping, Sequence
from typing import Any
from uuid import UUID

import rfc8785

from app.normalisation.models import NormalisedDocument

CONTRACT_VERSION = "document-extraction-artifact-v1"


class ExtractionArtifactLimitError(RuntimeError):
    """A typed, fail-closed artifact boundary violation."""

    def __init__(self, code: str) -> None:
        self.code = code
        super().__init__(code)


def _json_value(value: Any) -> Any:
    if isinstance(value, UUID):
        return str(value)
    if isinstance(value, Mapping):
        return {str(key): _json_value(item) for key, item in value.items()}
    if isinstance(value, Sequence) and not isinstance(value, (str, bytes, bytearray)):
        return [_json_value(item) for item in value]
    return value


def _model_value(value: Any, *, exclude_none: bool = False) -> dict[str, Any]:
    return _json_value(value.model_dump(mode="python", exclude_none=exclude_none))


def document_extraction_artifact(document: NormalisedDocument) -> dict[str, Any]:
    """Build ADR-0032's ownership-free canonical artefact value."""
    elements: list[dict[str, Any]] = []
    for ordinal, element in enumerate(document.elements):
        value = _model_value(element)
        value["ordinal"] = ordinal
        elements.append(value)

    return {
        "contract_version": CONTRACT_VERSION,
        "source_extractor": _model_value(document.source_extractor, exclude_none=True),
        "normaliser": _model_value(document.normaliser),
        "source_media_type": document.source_media_type,
        "source_byte_size": document.source_byte_size,
        "text": document.text,
        "elements": elements,
        "extraction_warnings": [
            _model_value(warning, exclude_none=True)
            for warning in document.extraction_warnings
        ],
        "changes": [_model_value(change) for change in document.changes],
        "metadata": _model_value(document.metadata),
    }


def _canonical_uuid(value: Any) -> str:
    try:
        return str(UUID(str(value)))
    except (AttributeError, TypeError, ValueError) as error:
        raise ValueError("artifact contains an invalid UUID") from error


def _normalise_value(value: Any) -> Any:
    if isinstance(value, Mapping):
        result = {str(key): _normalise_value(item) for key, item in value.items()}
        if "id" in result and "kind" in result:
            result["id"] = _canonical_uuid(result["id"])
        if "element_id" in result:
            result["element_id"] = _canonical_uuid(result["element_id"])
        if "source_element_ids" in result:
            result["source_element_ids"] = [
                _canonical_uuid(item) for item in result["source_element_ids"]
            ]
        return result
    if isinstance(value, Sequence) and not isinstance(value, (str, bytes, bytearray)):
        return [_normalise_value(item) for item in value]
    if isinstance(value, UUID):
        return str(value)
    return value


def canonical_artifact_bytes(artifact: Mapping[str, Any]) -> bytes:
    return rfc8785.dumps(_normalise_value(artifact))


def artifact_digest(artifact: Mapping[str, Any]) -> str:
    return hashlib.sha256(canonical_artifact_bytes(artifact)).hexdigest()


def projection_manifest(artifact: Mapping[str, Any]) -> list[dict[str, Any]]:
    elements = artifact.get("elements")
    if not isinstance(elements, Sequence) or isinstance(elements, (str, bytes)):
        raise TypeError("artifact elements must be an array")
    normalised = [_normalise_value(element) for element in elements]
    return sorted(
        normalised, key=lambda element: (int(element["ordinal"]), element["id"])
    )


def projection_manifest_digest(artifact: Mapping[str, Any]) -> str:
    return hashlib.sha256(rfc8785.dumps(projection_manifest(artifact))).hexdigest()


def warning_manifest(artifact: Mapping[str, Any]) -> list[dict[str, Any]]:
    warnings = artifact.get("extraction_warnings")
    if not isinstance(warnings, Sequence) or isinstance(warnings, (str, bytes)):
        raise TypeError("artifact extraction_warnings must be an array")
    return [_normalise_value(warning) for warning in warnings]


def warning_manifest_digest(artifact: Mapping[str, Any]) -> str:
    return hashlib.sha256(rfc8785.dumps(warning_manifest(artifact))).hexdigest()


def validate_artifact_limits(
    artifact: Mapping[str, Any],
    content: bytes,
    limits: Mapping[str, Any],
) -> None:
    versions = limits.get("supported_contract_versions")
    if not isinstance(versions, Sequence) or isinstance(versions, (str, bytes)):
        raise ExtractionArtifactLimitError("extraction_artifact_policy_invalid")
    if artifact.get("contract_version") not in versions:
        raise ExtractionArtifactLimitError("extraction_artifact_contract_unsupported")
    if len(content) > int(limits["max_bytes"]):
        raise ExtractionArtifactLimitError("extraction_artifact_too_large")

    elements = artifact.get("elements")
    warnings = artifact.get("extraction_warnings")
    if not isinstance(elements, Sequence) or isinstance(elements, (str, bytes)):
        raise ExtractionArtifactLimitError("extraction_artifact_invalid")
    if not isinstance(warnings, Sequence) or isinstance(warnings, (str, bytes)):
        raise ExtractionArtifactLimitError("extraction_artifact_invalid")
    if len(elements) > int(limits["max_elements"]):
        raise ExtractionArtifactLimitError("extraction_artifact_element_limit_exceeded")
    if len(warnings) > int(limits["max_warnings"]):
        raise ExtractionArtifactLimitError("extraction_artifact_warning_limit_exceeded")

    maximum_text_bytes = int(limits["max_element_text_bytes"])
    for element in elements:
        if not isinstance(element, Mapping) or not isinstance(element.get("text"), str):
            raise ExtractionArtifactLimitError("extraction_artifact_invalid")
        if len(element["text"].encode("utf-8")) > maximum_text_bytes:
            raise ExtractionArtifactLimitError(
                "extraction_artifact_element_text_limit_exceeded"
            )
