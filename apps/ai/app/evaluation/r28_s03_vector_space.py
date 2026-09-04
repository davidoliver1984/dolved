#!/usr/bin/env python3
"""Create the isolated R28-S03 hybrid vector schema before dense ingestion."""

from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Any
from uuid import UUID

from app.settings import get_settings
from app.vector_store.factory import create_vector_store
from app.vector_store.models import SparseVectorSpace, VectorDistance, VectorSpace


def vector_space_from_profile(
    profile: dict[str, Any],
    *,
    embedding_space_generation_id: UUID,
    sparse_space_generation_id: UUID,
) -> VectorSpace:
    dense = profile["dense"]
    sparse = profile["sparse"]
    dense_space = dense["space"]
    sparse_space = sparse["space"]
    return VectorSpace(
        collection_name=dense_space["collection_name"],
        embedding_space_generation_id=embedding_space_generation_id,
        profile_fingerprint=dense["fingerprint"],
        vector_name=dense_space["vector_name"],
        dimensions=dense_space["dimensions"],
        distance=VectorDistance(dense_space["distance"]),
        sparse=SparseVectorSpace(
            sparse_space_generation_id=sparse_space_generation_id,
            profile_fingerprint=sparse["fingerprint"],
            vector_name=sparse_space["vector_name"],
        ),
    )


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--profile", type=Path, required=True)
    parser.add_argument("--embedding-space-generation-id", type=UUID, required=True)
    parser.add_argument("--sparse-space-generation-id", type=UUID, required=True)
    args = parser.parse_args()

    settings = get_settings()
    if settings.environment != "e2e":
        raise RuntimeError("R28-S03 vector provisioning requires the E2E runtime")
    profile = json.loads(args.profile.read_text())
    vector_space = vector_space_from_profile(
        profile,
        embedding_space_generation_id=args.embedding_space_generation_id,
        sparse_space_generation_id=args.sparse_space_generation_id,
    )
    store = create_vector_store(settings)
    if store.collection_exists(vector_space):
        raise RuntimeError(
            "R28-S03 vector collection must be absent before materialisation"
        )
    store.ensure_vector_space(vector_space)
    if not store.collection_exists(vector_space):
        raise RuntimeError("R28-S03 hybrid vector schema was not created")
    sparse_vector = vector_space.sparse
    if sparse_vector is None:
        raise RuntimeError("R28-S03 hybrid vector schema omitted its sparse axis")
    print(
        json.dumps(
            {
                "collection_name": vector_space.collection_name,
                "dense_vector_name": vector_space.vector_name,
                "sparse_vector_name": sparse_vector.vector_name,
                "status": "created",
            },
            sort_keys=True,
        )
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
