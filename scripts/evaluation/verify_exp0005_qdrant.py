from __future__ import annotations

import argparse
import json
import os
import urllib.request
from pathlib import Path
from typing import Any


def _request(url: str, payload: dict[str, Any] | None = None) -> dict[str, Any]:
    body = None if payload is None else json.dumps(payload).encode()
    request = urllib.request.Request(
        url,
        data=body,
        headers={"Content-Type": "application/json"},
        method="GET" if body is None else "POST",
    )
    with urllib.request.urlopen(request, timeout=10) as response:
        value = json.load(response)
    if not isinstance(value, dict):
        raise TypeError("Qdrant returned an unexpected response shape")
    return value


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--provisioning", type=Path, required=True)
    arguments = parser.parse_args()
    state = json.loads(arguments.provisioning.read_text())
    generation = state["generations"]["hybrid_corpus"]
    embedding = state["generations"]["embedding_space"]
    base_url = os.environ["QDRANT_URL"].rstrip("/")
    collection = embedding["collection_name"]

    information = _request(f"{base_url}/collections/{collection}")["result"]
    parameters = information["config"]["params"]
    dense = parameters["vectors"][embedding["vector_name"]]
    sparse_vectors = parameters["sparse_vectors"]
    if (
        dense["size"] != embedding["dimensions"]
        or dense["distance"].lower() != embedding["distance"]
        or "sparse" not in sparse_vectors
    ):
        raise RuntimeError("Qdrant vector schema does not match EXP-0005 lineage")

    count = _request(
        f"{base_url}/collections/{collection}/points/count",
        {
            "filter": {
                "must": [
                    {
                        "key": "workspace_corpus_generation_id",
                        "match": {"value": generation["public_id"]},
                    }
                ]
            },
            "exact": True,
        },
    )["result"]["count"]
    if count != generation["expected_point_count"]:
        raise RuntimeError("Qdrant hybrid point count does not match EXP-0005 lineage")
    print(
        f"Verified Qdrant collection {collection}: dense+sparse schema, {count} hybrid points."
    )


if __name__ == "__main__":
    main()
