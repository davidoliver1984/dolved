#!/usr/bin/env python3
"""Normative exact-input classifier for independent R28 authoring."""

from __future__ import annotations

import hashlib
import io
import tarfile
from pathlib import PurePosixPath

VIEW_ID = "dolved-care-v4-question-author-view-v1"
VIEW_SHA256 = "8f73c9c12a843be9641698f39db60243a977e6c1c700a3f89f72dbbb890e44b9"
VIEW_ARCHIVE = (
    "tests/evaluation/authoring-views/dolved-care-v4/v1/question-author-view.tar.gz"
)
VIEW_MEMBER_PREFIX = f"{VIEW_ARCHIVE}!/{VIEW_ID}/"


def neutral_inputs(manifest: dict) -> set[str]:
    matches = [
        item
        for item in manifest["classifications"]
        if item["classification"] == "allowed_r28_authoring_neutral_inputs"
    ]
    if len(matches) != 1:
        raise ValueError("R28 authoring neutral-input classification must occur once")
    paths = matches[0]["paths"]
    if not isinstance(paths, list) or not paths or len(paths) != len(set(paths)):
        raise ValueError("R28 authoring neutral-input allowlist is invalid")
    return set(paths)


def classify_r28_authoring_input(
    path: str, manifest: dict, view_members: set[str]
) -> str | None:
    """Return EXTERNAL or VIEW_MEMBER only for one exact authoring input."""
    if path in neutral_inputs(manifest):
        return "EXTERNAL"
    if not path.startswith(VIEW_MEMBER_PREFIX):
        return None
    relative = path.removeprefix(VIEW_MEMBER_PREFIX)
    member = PurePosixPath(relative)
    if (
        not relative
        or member.is_absolute()
        or ".." in member.parts
        or str(member) != relative
        or f"{VIEW_ID}/{relative}" not in view_members
    ):
        return None
    return "VIEW_MEMBER"


def signed_view_members(data: bytes) -> set[str]:
    if hashlib.sha256(data).hexdigest() != VIEW_SHA256:
        raise ValueError("restricted author-view SHA-256 mismatch")
    with tarfile.open(fileobj=io.BytesIO(data), mode="r:gz") as archive:
        members = archive.getmembers()
        if any(not member.isfile() for member in members):
            raise ValueError("restricted author view contains a non-regular member")
        return {member.name for member in members}
