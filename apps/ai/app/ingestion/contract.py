import json
from pathlib import Path
from typing import Any

from jsonschema import Draft202012Validator, FormatChecker
from jsonschema.exceptions import ValidationError


class InvalidIngestionEvent(ValueError):
    """Raised when an SQS body is not a supported ingestion event."""


def find_contract_directory() -> Path:
    for directory in Path(__file__).resolve().parents:
        candidate = directory / "contracts/events/document-ingestion-requested"
        if candidate.is_dir():
            return candidate

    raise RuntimeError(
        "Unable to locate the canonical document-ingestion-requested contract."
    )


def load_json_object(path: Path) -> dict[str, Any]:
    with path.open(encoding="utf-8") as handle:
        payload = json.load(handle)

    if not isinstance(payload, dict):
        raise TypeError(f"Expected a JSON object: {path}")

    return payload


CONTRACT_DIRECTORY = find_contract_directory()
SCHEMA = load_json_object(CONTRACT_DIRECTORY / "v1.schema.json")
Draft202012Validator.check_schema(SCHEMA)
VALIDATOR = Draft202012Validator(SCHEMA, format_checker=FormatChecker())


def parse_and_validate_event(raw_body: str) -> dict[str, Any]:
    try:
        event = json.loads(raw_body)
    except json.JSONDecodeError as exception:
        raise InvalidIngestionEvent("The SQS body is not valid JSON.") from exception

    if not isinstance(event, dict):
        raise InvalidIngestionEvent("The ingestion event must be a JSON object.")

    try:
        VALIDATOR.validate(event)
    except ValidationError as exception:
        raise InvalidIngestionEvent(
            "The ingestion event does not match the canonical v1 contract."
        ) from exception

    return event
