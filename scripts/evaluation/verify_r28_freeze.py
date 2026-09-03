#!/usr/bin/env python3
"""Provider-free identity and boundary verification for the frozen V4 corpus."""

from __future__ import annotations

import hashlib
import json
import re
import tarfile
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[2]
FREEZE = ROOT / "tests/evaluation/corpus/dolved-care-v4/v1"
SOURCE_ROOT = "eval-corpus-v4-authoring"
EXTERNAL_ARCHIVE = Path(
    "/Users/davidoliver/Desktop/dolved-v4-corpus-checkpoints/"
    "checkpoint-19-application-evidence-corrections.tar.gz"
)
CHECKSUM_RECORD = re.compile(r"^([0-9a-f]{64})  ([^\r\n]+)$")
GOVERNED_SCOPE_NAMES = {
    "primary",
    "foreign_tenant",
    "additional_prompt_injection",
    "negative_import_fixtures",
}


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def verify(condition: bool, message: str) -> None:
    if not condition:
        raise ValueError(message)


def validate_governed_scopes(manifest: dict, scope_data: dict[str, dict]) -> None:
    governed = manifest["governed_scopes"]
    verify(set(governed) == GOVERNED_SCOPE_NAMES, "governed scope set mismatch")
    verify(set(scope_data) == GOVERNED_SCOPE_NAMES, "source scope set mismatch")
    source_paths: set[str] = set()
    for scope in sorted(GOVERNED_SCOPE_NAMES):
        contract = governed[scope]
        source_path = contract["source_manifest"]
        verify(source_path not in source_paths, "duplicate governed source manifest")
        source_paths.add(source_path)
        actual = scope_data[scope]
        verify(
            actual["corpus_id"] == contract["source_identity"],
            f"governed scope identity mismatch: {scope}",
        )
        collection = "fixtures" if scope == "negative_import_fixtures" else "documents"
        count_field = "fixture_count" if collection == "fixtures" else "document_count"
        verify(
            len(actual[collection]) == contract[count_field],
            f"governed scope count mismatch: {scope}",
        )
        if scope == "primary":
            verify(
                sum(
                    item.get("prompt_injection") is True for item in actual["documents"]
                )
                == contract["in_corpus_prompt_injection_count"]
                == 3,
                "primary in-corpus injection identity/count mismatch",
            )


def main() -> int:
    manifest = json.loads((FREEZE / "freeze-manifest.json").read_text())
    archive = FREEZE / manifest["archive"]["filename"]
    assert sha256(archive) == manifest["archive"]["sha256"]
    with tarfile.open(archive, "r:gz") as source:
        members = source.getmembers()
        regular = [member for member in members if member.isfile()]
        assert len(regular) == manifest["archive"]["regular_members"] == 752
        assert not [member for member in members if not member.isfile()]
        names = [member.name for member in regular]
        assert len(names) == len(set(names))
        for name in names:
            path = PurePosixPath(name)
            assert not path.is_absolute()
            assert ".." not in path.parts
            assert str(path) == name
            assert path.parts[0] == SOURCE_ROOT
        inventory_member = "eval-corpus-v4-authoring/checksums/checksums.sha256"
        assert names.count(inventory_member) == 1
        inventory = source.extractfile(inventory_member)
        assert inventory is not None
        lines = inventory.read().decode("utf-8").splitlines()
        assert len(lines) == manifest["archive"]["checksum_inventory_entries"] == 751
        records: dict[str, str] = {}
        for line in lines:
            match = CHECKSUM_RECORD.fullmatch(line)
            assert match is not None
            digest, relative = match.groups()
            path = PurePosixPath(relative)
            assert not path.is_absolute()
            assert ".." not in path.parts
            assert str(path) == relative
            assert relative not in records
            records[relative] = digest
        governed_members = {
            name.removeprefix(SOURCE_ROOT + "/")
            for name in names
            if name != inventory_member
        }
        assert set(records) == governed_members
        for relative, expected in records.items():
            stream = source.extractfile(f"{SOURCE_ROOT}/{relative}")
            assert stream is not None
            actual = hashlib.sha256()
            while chunk := stream.read(1024 * 1024):
                actual.update(chunk)
            assert actual.hexdigest() == expected

        scope_data: dict[str, dict] = {}
        for scope, contract in manifest["governed_scopes"].items():
            stream = source.extractfile(f"{SOURCE_ROOT}/{contract['source_manifest']}")
            assert stream is not None
            scope_data[scope] = json.load(stream)
        validate_governed_scopes(manifest, scope_data)

    external_status = "unavailable"
    if EXTERNAL_ARCHIVE.exists():
        assert EXTERNAL_ARCHIVE.is_file() and not EXTERNAL_ARCHIVE.is_symlink()
        assert sha256(EXTERNAL_ARCHIVE) == manifest["archive"]["sha256"]
        assert archive.read_bytes() == EXTERNAL_ARCHIVE.read_bytes()
        external_status = "byte-identical"
    print(
        "PASS frozen corpus identity, 751 member digests, four governed scope "
        "identities/counts and raw "
        f"boundary; external-copy comparison: {external_status}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
