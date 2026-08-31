import json
from pathlib import Path
from typing import Any

from jsonschema import Draft202012Validator, FormatChecker
from jsonschema.exceptions import ValidationError


class InvalidImportPreflightEvent(ValueError):
    """Raised when an import preflight event is outside the frozen contract."""


def _contract_directory() -> Path:
    for directory in Path(__file__).resolve().parents:
        candidate = directory / "contracts/events/import-preflight-requested"
        if candidate.is_dir():
            return candidate
    raise RuntimeError("Unable to locate the import preflight contract.")


with (_contract_directory() / "v1.schema.json").open(encoding="utf-8") as handle:
    SCHEMA: dict[str, Any] = json.load(handle)

VALIDATOR = Draft202012Validator(SCHEMA, format_checker=FormatChecker())


def parse_and_validate_preflight_event(raw_body: str) -> dict[str, Any]:
    try:
        payload = json.loads(raw_body)
    except json.JSONDecodeError as exception:
        raise InvalidImportPreflightEvent("Invalid JSON.") from exception
    if not isinstance(payload, dict):
        raise InvalidImportPreflightEvent("The event must be an object.")
    try:
        VALIDATOR.validate(payload)
    except ValidationError as exception:
        raise InvalidImportPreflightEvent(
            "The event contract is invalid."
        ) from exception
    return payload
