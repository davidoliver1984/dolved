import json
from pathlib import Path
from typing import Any

from jsonschema import Draft202012Validator, FormatChecker
from jsonschema.exceptions import ValidationError


class InvalidDocumentDeletionEvent(ValueError):
    """Raised when a deletion event violates the repository contract."""


def _schema_path() -> Path:
    for directory in Path(__file__).resolve().parents:
        candidate = (
            directory / "contracts/events/document-deletion-requested/v1.schema.json"
        )
        if candidate.is_file():
            return candidate
    raise RuntimeError("Unable to locate the document-deletion contract.")


with _schema_path().open(encoding="utf-8") as handle:
    SCHEMA = json.load(handle)
VALIDATOR = Draft202012Validator(SCHEMA, format_checker=FormatChecker())


def parse_and_validate_deletion_event(raw_body: str) -> dict[str, Any]:
    try:
        event = json.loads(raw_body)
    except json.JSONDecodeError as exception:
        raise InvalidDocumentDeletionEvent(
            "The event is not valid JSON."
        ) from exception
    if not isinstance(event, dict):
        raise InvalidDocumentDeletionEvent("The event must be an object.")
    try:
        VALIDATOR.validate(event)
    except ValidationError as exception:
        raise InvalidDocumentDeletionEvent(
            "The event does not match the deletion contract."
        ) from exception
    return event
