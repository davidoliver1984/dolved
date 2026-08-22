import copy
import json
from pathlib import Path
from typing import Any

from jsonschema import Draft202012Validator, FormatChecker

CONTRACT = Path("/contracts/http/retrieval-call/rc1")


def _load(path: Path) -> dict[str, Any]:
    value = json.loads(path.read_text(encoding="utf-8"))
    assert isinstance(value, dict)
    return value


def _mutate(payload: dict[str, Any], mutation: dict[str, Any]) -> dict[str, Any]:
    changed: Any = copy.deepcopy(payload)
    path = mutation["path"]
    assert isinstance(path, list) and path
    cursor: Any = changed
    for segment in path[:-1]:
        cursor = cursor[segment]
    final = path[-1]
    if mutation["action"] == "remove":
        del cursor[final]
    else:
        cursor[final] = mutation["value"]
    assert isinstance(changed, dict)
    return changed


def test_both_languages_share_complete_rc1_valid_and_negative_fixtures() -> None:
    fixture = _load(CONTRACT / "contract-vectors.json")
    schema_names = {path.name for path in CONTRACT.glob("*.schema.json")}
    fixture_names = {contract["schema"] for contract in fixture["contracts"]}
    assert fixture_names == schema_names

    for contract in fixture["contracts"]:
        validator = Draft202012Validator(
            _load(CONTRACT / contract["schema"]),
            format_checker=FormatChecker(),
        )
        validator.validate(contract["payload"])
        for mutation in contract["invalid"]:
            assert not validator.is_valid(_mutate(contract["payload"], mutation)), (
                f"{contract['schema']} accepted {mutation['case']}"
            )
