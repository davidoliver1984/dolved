import hashlib
import json
from collections.abc import Mapping, Sequence
from typing import Any
from uuid import UUID


def canonical_json(value: Any) -> bytes:
    return json.dumps(
        value,
        ensure_ascii=False,
        separators=(",", ":"),
        sort_keys=True,
    ).encode("utf-8")


def chunk_content_digest(chunk: Mapping[str, Any]) -> str:
    fields = {
        name: chunk[name]
        for name in (
            "chunk_id",
            "ordinal",
            "text",
            "token_count",
            "strategy_name",
            "strategy_version",
            "configuration",
            "configuration_fingerprint",
            "provenance",
        )
    }
    return hashlib.sha256(canonical_json(fields)).hexdigest()


def chunk_manifest_digest(chunks: Sequence[Mapping[str, Any]]) -> str:
    manifest = [
        {
            "chunk_id": chunk["chunk_id"],
            "ordinal": chunk["ordinal"],
            "content_digest": chunk["content_digest"],
        }
        for chunk in sorted(chunks, key=lambda item: int(item["ordinal"]))
    ]
    return hashlib.sha256(canonical_json(manifest)).hexdigest()


def point_manifest_digest(point_ids: Sequence[UUID | str]) -> str:
    canonical = "\n".join(sorted(str(UUID(str(point_id))) for point_id in point_ids))
    return hashlib.sha256(canonical.encode("utf-8")).hexdigest()
