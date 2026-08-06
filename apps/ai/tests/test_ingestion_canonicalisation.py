import json
from pathlib import Path
from typing import Any
from uuid import UUID

from app.ingestion.canonicalisation import (
    chunk_content_digest,
    chunk_manifest_digest,
    point_manifest_digest,
)
from app.vector_store.identity import deterministic_point_id


def _vector() -> dict[str, Any]:
    path = Path("/contracts/http/ingestion-worker/v1/canonicalisation-vectors.json")
    return json.loads(path.read_text(encoding="utf-8"))


def test_shared_canonicalisation_vector() -> None:
    vector = _vector()
    chunk = vector["chunk"]

    assert chunk_content_digest(chunk) == vector["chunk_content_digest"]
    assert (
        chunk_manifest_digest(
            [
                {
                    "chunk_id": chunk["chunk_id"],
                    "ordinal": chunk["ordinal"],
                    "content_digest": vector["chunk_content_digest"],
                }
            ]
        )
        == vector["chunk_manifest_digest"]
    )
    assert point_manifest_digest(vector["point_ids"]) == vector["point_manifest_digest"]
    identity = vector["point_identity"]
    assert (
        str(
            deterministic_point_id(
                embedding_space_generation_id=UUID(
                    identity["embedding_space_generation_id"]
                ),
                workspace_id=UUID(identity["workspace_id"]),
                workspace_corpus_generation_id=UUID(
                    identity["workspace_corpus_generation_id"]
                ),
                chunk_id=UUID(identity["chunk_id"]),
            )
        )
        == identity["expected_point_id"]
    )
