from __future__ import annotations

import copy
import importlib.util
import json
import os
import sys
import tarfile
from pathlib import Path

import pytest

SCRIPT_ROOT = Path(os.environ.get("SCRIPT_ROOT", "/workspace"))
if not SCRIPT_ROOT.is_dir():
    SCRIPT_ROOT = Path(__file__).resolve().parents[3]


def load_script(name: str):
    path = SCRIPT_ROOT / "scripts/evaluation" / name
    sys.path.insert(0, str(path.parent))
    spec = importlib.util.spec_from_file_location(name.removesuffix(".py"), path)
    assert spec and spec.loader
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


VIEW = load_script("build_r28_author_view.py")
GUARD = load_script("r28_access_guard.py")
FREEZE = load_script("verify_r28_freeze.py")


def access_manifest() -> dict:
    return json.loads(
        (SCRIPT_ROOT / "docs/evaluation/r28-s01/access-manifest.json").read_text()
    )


def valid_member_names() -> list[str]:
    root = VIEW.ROOT
    names = [
        f"{root}/documents/primary/document-{index:03d}.txt" for index in range(300)
    ]
    names += [
        f"{root}/documents/foreign_tenant/document-{index:03d}.txt"
        for index in range(12)
    ]
    names += [
        f"{root}/documents/security_test/document-{index:03d}.txt" for index in range(6)
    ]
    names += [
        f"{root}/metadata/documents.json",
        f"{root}/metadata/organisation.json",
        f"{root}/manifest.json",
    ]
    return names


def test_closed_view_member_names_accept_exact_structure() -> None:
    assert VIEW.validate_member_names(valid_member_names()) == {
        "primary": 300,
        "foreign_tenant": 12,
        "security_test": 6,
    }


@pytest.mark.parametrize(
    ("mutation", "message"),
    [
        (lambda names: names + [names[0]], "unique"),
        (lambda names: names + [f"{VIEW.ROOT}/unexpected.json"], "unexpected"),
        (lambda names: [*names[:-1], f"{VIEW.ROOT}/../escape.json"], "unsafe"),
        (lambda names: [*names[:-1], f"{VIEW.ROOT}//manifest.json"], "unsafe"),
        (lambda names: names[1:], "primary"),
    ],
)
def test_closed_view_member_names_reject_invalid_structure(
    mutation, message: str
) -> None:
    with pytest.raises(ValueError, match=message):
        VIEW.validate_member_names(mutation(valid_member_names()))


@pytest.mark.parametrize(
    "member_type", [tarfile.SYMTYPE, tarfile.LNKTYPE, tarfile.DIRTYPE, tarfile.FIFOTYPE]
)
def test_closed_view_rejects_every_non_regular_member_type(member_type: bytes) -> None:
    member = tarfile.TarInfo(f"{VIEW.ROOT}/manifest.json")
    member.type = member_type
    with pytest.raises(ValueError, match="regular files"):
        VIEW.validate_member_types([member])


@pytest.mark.parametrize(
    "path",
    [
        "docs/evaluation/r28-s01/access-manifest.json",
        "tests/evaluation/authoring-views/dolved-care-v4/v1/question-author-view.tar.gz",
        "contracts/evaluation/v4/independent-authoring-output.schema.json",
        "docs/evaluation/r28-s01/authoring-coverage-contract.json",
        "docs/evaluation/r28-s01/authoring-output-contract.md",
        "scripts/evaluation/r28_authoring_access.py",
        "scripts/evaluation/r28_access_guard.py",
        "scripts/evaluation/validate_r28_authoring_output.py",
        "docs/evaluation/r28-s01/question-authoring-handoff.md",
    ],
)
def test_access_guard_allows_each_exact_neutral_input(path: str) -> None:
    assert GUARD.rejected_r28_authoring_paths(access_manifest(), [path], set()) == []


@pytest.mark.parametrize(
    "path",
    [
        "tests/evaluation/hybrid/v1/live-calibration-input.json",
        "tests/evaluation/held-out/cases.json",
        "docs/evaluation/runs/example/result.json",
        "tests/evaluation/unknown.json",
        "contracts/evaluation/v4/alias.schema.json",
        "./contracts/evaluation/v4/independent-authoring-output.schema.json",
    ],
)
def test_access_guard_rejects_forbidden_uncertain_and_alias_paths(path: str) -> None:
    assert GUARD.rejected_r28_authoring_paths(access_manifest(), [path], set())


def test_r28_authoring_mode_rejects_generally_allowed_runner() -> None:
    manifest = access_manifest()
    assert GUARD.rejected_paths(manifest, ["scripts/evaluation/run.py"]) == []
    assert GUARD.rejected_r28_authoring_paths(
        manifest, ["scripts/evaluation/run.py"], set()
    )


def test_r28_authoring_mode_accepts_exact_member_and_rejects_bad_syntax() -> None:
    prefix = (
        "tests/evaluation/authoring-views/dolved-care-v4/v1/"
        "question-author-view.tar.gz!/dolved-care-v4-question-author-view-v1/"
    )
    member = "metadata/documents.json"
    members = {f"dolved-care-v4-question-author-view-v1/{member}"}
    assert (
        GUARD.rejected_r28_authoring_paths(
            access_manifest(), [prefix + member], members
        )
        == []
    )
    for invalid in (prefix + "../escape.json", prefix + "metadata//documents.json"):
        assert GUARD.rejected_r28_authoring_paths(access_manifest(), [invalid], members)


def frozen_manifest() -> dict:
    return json.loads(
        (
            SCRIPT_ROOT
            / "tests/evaluation/corpus/dolved-care-v4/v1/freeze-manifest.json"
        ).read_text()
    )


def synthetic_scope_data(manifest: dict) -> dict[str, dict]:
    result: dict[str, dict] = {}
    for scope, contract in manifest["governed_scopes"].items():
        if scope == "negative_import_fixtures":
            result[scope] = {
                "corpus_id": contract["source_identity"],
                "fixtures": [{} for _ in range(contract["fixture_count"])],
            }
        else:
            documents = [{} for _ in range(contract["document_count"])]
            if scope == "primary":
                for item in documents[:3]:
                    item["prompt_injection"] = True
            result[scope] = {
                "corpus_id": contract["source_identity"],
                "documents": documents,
            }
    return result


def test_frozen_scope_identities_and_counts_accept_exact_contract() -> None:
    manifest = frozen_manifest()
    FREEZE.validate_governed_scopes(manifest, synthetic_scope_data(manifest))


def test_frozen_scopes_reject_wrong_identity_with_correct_count() -> None:
    manifest = frozen_manifest()
    data = synthetic_scope_data(manifest)
    data["foreign_tenant"]["corpus_id"] = "substituted-identity"
    with pytest.raises(ValueError, match="identity mismatch: foreign_tenant"):
        FREEZE.validate_governed_scopes(manifest, data)


def test_frozen_scopes_reject_duplicate_substituted_and_misclassified_scope() -> None:
    manifest = frozen_manifest()
    duplicate = copy.deepcopy(manifest)
    duplicate["governed_scopes"]["foreign_tenant"]["source_manifest"] = duplicate[
        "governed_scopes"
    ]["primary"]["source_manifest"]
    with pytest.raises(ValueError, match="duplicate governed source manifest"):
        FREEZE.validate_governed_scopes(duplicate, synthetic_scope_data(duplicate))

    data = synthetic_scope_data(manifest)
    data["additional_prompt_injection"] = data["foreign_tenant"]
    with pytest.raises(ValueError, match="identity mismatch|count mismatch"):
        FREEZE.validate_governed_scopes(manifest, data)

    missing = synthetic_scope_data(manifest)
    missing.pop("negative_import_fixtures")
    with pytest.raises(ValueError, match="source scope set mismatch"):
        FREEZE.validate_governed_scopes(manifest, missing)
