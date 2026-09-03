#!/usr/bin/env python3
"""Build and validate the restricted R28 question-author view."""

from __future__ import annotations

import argparse
import gzip
import hashlib
import io
import json
import tarfile
from pathlib import Path, PurePosixPath

ROOT = "dolved-care-v4-question-author-view-v1"
SOURCE_ROOT = "eval-corpus-v4-authoring"
SOURCE_ARCHIVE_SHA256 = (
    "6fa6602935efe8379cc2a7de4ba85af17aa8d8827082ae6de8df959f6e19a06e"
)
VIEW_ARCHIVE_SHA256 = "8f73c9c12a843be9641698f39db60243a977e6c1c700a3f89f72dbbb890e44b9"
PRIMARY_FIELDS = (
    "filename",
    "human_title",
    "family_title",
    "family_id",
    "version_id",
    "format",
    "content_type",
    "governance_status",
    "effective_date",
    "superseded_date",
    "applicability_scope",
    "applicability_locations",
    "sha256",
    "byte_count",
)
AUX_FIELDS = (
    "filename",
    "human_title",
    "family_title",
    "family_id",
    "version_id",
    "format",
    "governance_status",
    "sha256",
    "byte_count",
)
BANNED_COMPONENTS = {
    "semantic-masters",
    "generate",
    "authoring-governance",
    "checksums",
    "negative-fixtures",
    "reviews",
    "calibration",
    "held-out",
    "held_out",
}
BANNED_METADATA_KEYS = {
    "messiness_axes",
    "messiness_level",
    "version_change_type",
    "semantic_master_path",
    "prompt_injection",
    "expected_ingestible",
    "evaluation_clock",
    "tranche_history",
    "content_depth_corrected",
}


def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def canonical(value: object) -> bytes:
    return (json.dumps(value, indent=2, sort_keys=True) + "\n").encode()


def load_json(archive: tarfile.TarFile, relative: str) -> dict:
    member = archive.extractfile(f"{SOURCE_ROOT}/{relative}")
    if member is None:
        raise ValueError(f"missing source member: {relative}")
    return json.load(member)


def add(files: dict[str, bytes], path: str, data: bytes) -> None:
    if path in files:
        raise ValueError(f"duplicate output path: {path}")
    files[path] = data


def selected(item: dict, fields: tuple[str, ...], scope: str) -> dict:
    result = {key: item.get(key) for key in fields}
    result["scope"] = scope
    result["source_member"] = item["artefact_path"]
    return result


def build(source: Path, output: Path) -> str:
    if digest(source.read_bytes()) != SOURCE_ARCHIVE_SHA256:
        raise ValueError("source archive identity mismatch")
    files: dict[str, bytes] = {}
    with tarfile.open(source, "r:gz") as archive:
        primary = load_json(archive, "source-manifest.json")
        foreign = load_json(archive, "foreign-tenant/source-manifest.json")
        injection = load_json(archive, "prompt-injection-pack/source-manifest.json")
        organisation = load_json(archive, "organisation.json")
        foreign_org = load_json(archive, "foreign-tenant/organisation.json")
        metadata = {
            "schema_version": "r28-question-author-view-v1",
            "view_id": ROOT,
            "source_corpus_id": "dolved-care-v4-checkpoint-19",
            "source_archive_sha256": SOURCE_ARCHIVE_SHA256,
            "documents": [],
        }
        groups = (
            (primary["documents"], PRIMARY_FIELDS, "primary"),
            (foreign["documents"], AUX_FIELDS, "foreign_tenant"),
            (injection["documents"], AUX_FIELDS, "security_test"),
        )
        for documents, fields, scope in groups:
            for item in documents:
                source_name = f"{SOURCE_ROOT}/{item['artefact_path']}"
                member = archive.extractfile(source_name)
                if member is None:
                    raise ValueError(f"missing rendered document: {source_name}")
                data = member.read()
                if digest(data) != item["sha256"]:
                    raise ValueError(f"document checksum mismatch: {source_name}")
                destination = f"documents/{scope}/{item['filename']}"
                add(files, destination, data)
                entry = selected(item, fields, scope)
                entry["view_path"] = destination
                metadata["documents"].append(entry)
        public_org = {
            "schema_version": "r28-public-organisation-v1",
            "primary": {
                "organisation": organisation["organisation"],
                "locations": organisation["locations"],
                "aliases": organisation.get("aliases", {}),
                "terminology": organisation.get("terminology", {}),
            },
            "foreign_tenant": {
                "organisation": foreign_org["organisation"],
                "locations": foreign_org["locations"],
                "aliases": foreign_org.get("aliases", {}),
                "terminology": foreign_org.get("terminology", {}),
            },
        }
        add(files, "metadata/documents.json", canonical(metadata))
        add(files, "metadata/organisation.json", canonical(public_org))
        inventory = [
            {"path": path, "sha256": digest(data), "byte_count": len(data)}
            for path, data in sorted(files.items())
        ]
        add(
            files,
            "manifest.json",
            canonical(
                {
                    "schema_version": "r28-question-author-view-manifest-v1",
                    "view_id": ROOT,
                    "source_archive_sha256": SOURCE_ARCHIVE_SHA256,
                    "counts": {
                        "primary": 300,
                        "foreign_tenant": 12,
                        "security_test": 6,
                    },
                    "excluded_scopes": ["negative_import_fixtures"],
                    "inventory": inventory,
                }
            ),
        )
    output.parent.mkdir(parents=True, exist_ok=True)
    with (
        output.open("wb") as raw,
        gzip.GzipFile(fileobj=raw, mode="wb", filename="", mtime=0) as compressed,
        tarfile.open(fileobj=compressed, mode="w", format=tarfile.GNU_FORMAT) as result,
    ):
        for relative, data in sorted(files.items()):
            info = tarfile.TarInfo(f"{ROOT}/{relative}")
            info.size = len(data)
            info.mtime = 0
            info.mode = 0o644
            info.uid = info.gid = 0
            info.uname = info.gname = ""
            result.addfile(info, io.BytesIO(data))
    validate(output)
    return digest(output.read_bytes())


def validate_member_names(names: list[str]) -> dict[str, int]:
    if len(names) != len(set(names)):
        raise ValueError("view member names must be unique")
    for name in names:
        relative = PurePosixPath(name)
        if (
            relative.is_absolute()
            or ".." in relative.parts
            or str(relative) != name
            or relative.parts[0] != ROOT
        ):
            raise ValueError(f"unsafe view path: {name}")
        if BANNED_COMPONENTS.intersection(relative.parts):
            raise ValueError(f"forbidden view component: {name}")
    relative_names = {name.removeprefix(ROOT + "/") for name in names}
    fixed = {"metadata/documents.json", "metadata/organisation.json", "manifest.json"}
    document_groups = {
        "primary": {
            name for name in relative_names if name.startswith("documents/primary/")
        },
        "foreign_tenant": {
            name
            for name in relative_names
            if name.startswith("documents/foreign_tenant/")
        },
        "security_test": {
            name
            for name in relative_names
            if name.startswith("documents/security_test/")
        },
    }
    expected_counts = {"primary": 300, "foreign_tenant": 12, "security_test": 6}
    for scope, group in document_groups.items():
        if len(group) != expected_counts[scope] or any(
            len(PurePosixPath(name).parts) != 3 for name in group
        ):
            raise ValueError(f"closed document-member structure mismatch: {scope}")
    expected_names = fixed | set().union(*document_groups.values())
    if relative_names != expected_names:
        raise ValueError("unexpected restricted-view member")
    return expected_counts


def validate_member_types(members: list[tarfile.TarInfo]) -> None:
    if any(not member.isfile() for member in members):
        raise ValueError("view must contain regular files only")


def validate(path: Path) -> None:
    if digest(path.read_bytes()) != VIEW_ARCHIVE_SHA256:
        raise ValueError("restricted author-view archive identity mismatch")
    with tarfile.open(path, "r:gz") as archive:
        members = archive.getmembers()
        names = [member.name for member in members]
        validate_member_types(members)
        expected_counts = validate_member_names(names)
        manifest = json.load(archive.extractfile(f"{ROOT}/manifest.json"))
        if manifest.get("counts") != expected_counts:
            raise ValueError("restricted-view manifest count mismatch")
        declared = {item["path"]: item for item in manifest["inventory"]}
        actual = {name.removeprefix(ROOT + "/") for name in names} - {"manifest.json"}
        if set(declared) != actual:
            raise ValueError("view inventory mismatch")
        for relative, item in declared.items():
            data = archive.extractfile(f"{ROOT}/{relative}").read()
            if digest(data) != item["sha256"] or len(data) != item["byte_count"]:
                raise ValueError(f"view member identity mismatch: {relative}")
        metadata = json.load(archive.extractfile(f"{ROOT}/metadata/documents.json"))
        if len(metadata["documents"]) != 318:
            raise ValueError("view document count mismatch")
        for item in metadata["documents"]:
            if BANNED_METADATA_KEYS.intersection(item):
                raise ValueError("forbidden metadata key present")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--validate-only", action="store_true")
    args = parser.parse_args()
    if args.validate_only:
        validate(args.output)
        print("PASS restricted author view")
    else:
        print(build(args.source, args.output))


if __name__ == "__main__":
    main()
