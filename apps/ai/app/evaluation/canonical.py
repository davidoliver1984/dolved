"""Canonical JSON digests for immutable evaluation artefacts."""

import hashlib
import json
from typing import Any


def canonical_json(value: Any) -> bytes:
    return json.dumps(
        value,
        ensure_ascii=False,
        allow_nan=False,
        separators=(",", ":"),
        sort_keys=True,
    ).encode()


def content_digest(value: Any) -> str:
    return hashlib.sha256(canonical_json(value)).hexdigest()
