import json
from pathlib import Path
from typing import Any

import pytest
from jsonschema import Draft202012Validator, FormatChecker
from jsonschema.exceptions import ValidationError

INVALID_FIXTURES = {
    "invalid-missing-workspace-id.json": "required",
    "invalid-unknown-field.json": "additionalProperties",
    "invalid-unsupported-version.json": "const",
    "invalid-zero-byte-size.json": "minimum",
}


def find_contract_directory() -> Path:
    for directory in Path(__file__).resolve().parents:
        candidate = directory / "contracts/events/document-ingestion-requested"
        if candidate.is_dir():
            return candidate

    raise RuntimeError(
        "Unable to locate the canonical document-ingestion-requested contract directory."
    )


CONTRACT_DIRECTORY = find_contract_directory()


def load_json_object(path: Path) -> dict[str, Any]:
    with path.open(encoding="utf-8") as handle:
        payload = json.load(handle)

    if not isinstance(payload, dict):
        raise TypeError(f"Expected a JSON object: {path}")

    return payload


SCHEMA = load_json_object(CONTRACT_DIRECTORY / "v1.schema.json")
Draft202012Validator.check_schema(SCHEMA)
VALIDATOR = Draft202012Validator(SCHEMA, format_checker=FormatChecker())


def test_valid_example_matches_the_canonical_v1_schema() -> None:
    payload = load_json_object(CONTRACT_DIRECTORY / "v1.example.json")

    VALIDATOR.validate(payload)


@pytest.mark.parametrize(
    ("fixture", "expected_validator"),
    INVALID_FIXTURES.items(),
    ids=INVALID_FIXTURES,
)
def test_invalid_fixture_fails_for_the_intended_keyword(
    fixture: str,
    expected_validator: str,
) -> None:
    payload = load_json_object(CONTRACT_DIRECTORY / "fixtures" / fixture)

    errors = list(VALIDATOR.iter_errors(payload))

    assert errors
    assert expected_validator in {error.validator for error in errors}


def test_unsupported_version_is_rejected_because_v1_is_required() -> None:
    payload = load_json_object(
        CONTRACT_DIRECTORY / "fixtures/invalid-unsupported-version.json"
    )

    with pytest.raises(ValidationError) as error:
        VALIDATOR.validate(payload)

    assert error.value.validator == "const"
    assert error.value.validator_value == 1
    assert list(error.value.absolute_path) == ["event_version"]


def test_unexpected_presigned_url_is_rejected_as_an_additional_property() -> None:
    payload = load_json_object(
        CONTRACT_DIRECTORY / "fixtures/invalid-unknown-field.json"
    )

    with pytest.raises(ValidationError) as error:
        VALIDATOR.validate(payload)

    assert error.value.validator == "additionalProperties"
    assert "presigned_url" in error.value.message


def test_missing_workspace_id_is_rejected_as_required() -> None:
    payload = load_json_object(
        CONTRACT_DIRECTORY / "fixtures/invalid-missing-workspace-id.json"
    )

    with pytest.raises(ValidationError) as error:
        VALIDATOR.validate(payload)

    assert error.value.validator == "required"
    assert "workspace_id" in error.value.message


def test_every_shared_invalid_fixture_has_an_expected_reason() -> None:
    fixtures = {
        fixture.name for fixture in (CONTRACT_DIRECTORY / "fixtures").glob("*.json")
    }

    assert fixtures == set(INVALID_FIXTURES)
