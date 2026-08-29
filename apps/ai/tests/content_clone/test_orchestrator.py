from __future__ import annotations

import hashlib
import json
from io import BytesIO
from typing import Any
from uuid import UUID, uuid4

import pytest

from app.content_clone.models import (
    ContentCloneCleanupRequest,
    ContentCloneVectorRequest,
)
from app.content_clone.orchestrator import (
    ContentCloneContractError,
    ContentCloneOrchestrator,
)
from app.ingestion.canonicalisation import point_manifest_digest
from app.vector_store.identity import deterministic_point_id
from app.vector_store.models import VectorScope


class ObjectStore:
    def __init__(self, body: bytes) -> None:
        self.body = body
        self.calls: list[dict[str, object]] = []

    def get_object(self, **kwargs: object) -> dict[str, Any]:
        self.calls.append(kwargs)
        return {"Body": BytesIO(self.body)}


class VectorStore:
    def __init__(self) -> None:
        self.request = None
        self.deleted: VectorScope | None = None

    def clone_points(self, request):  # type: ignore[no-untyped-def]
        from app.vector_store.models import VectorCloneReport

        self.request = request
        return VectorCloneReport(
            complete=True,
            point_ids=tuple(
                mapping.target_point.point_id for mapping in request.mappings
            ),
        )

    def delete(self, scope: VectorScope) -> None:
        self.deleted = scope

    def count(self, scope: VectorScope) -> int:
        assert self.deleted == scope
        return 0


def pipeline(
    workspace_corpus_generation_id: UUID, embedding_id: UUID
) -> dict[str, Any]:
    return {
        "dense": {
            "collection_name": "chunks-v1",
            "embedding_space_generation_id": str(embedding_id),
            "embedding_profile_fingerprint": "a" * 64,
            "vector_name": "dense",
            "dimensions": 3,
            "distance": "cosine",
        },
        "sparse": None,
        "workspace_corpus_generation_id": str(workspace_corpus_generation_id),
    }


def fixture() -> tuple[bytes, dict[str, Any]]:
    workspace_id = uuid4()
    source_document_id = uuid4()
    target_document_id = uuid4()
    source_event_id = uuid4()
    target_event_id = uuid4()
    operation_id = uuid4()
    request_id = uuid4()
    corpus_id = uuid4()
    embedding_id = uuid4()
    target_chunk_id = uuid4()
    source_point_id = uuid4()
    target_point_id = deterministic_point_id(
        embedding_space_generation_id=embedding_id,
        workspace_id=workspace_id,
        workspace_corpus_generation_id=corpus_id,
        chunk_id=target_chunk_id,
    )
    manifest = {
        "schema_version": "document-content-clone-manifest-v1",
        "operation_id": str(operation_id),
        "event_id": str(target_event_id),
        "lease_generation": 2,
        "entries": [
            {
                "source_point_id": str(source_point_id),
                "target_point_id": str(target_point_id),
                "source_chunk_id": str(uuid4()),
                "target_chunk_id": str(target_chunk_id),
                "target_payload": {
                    "workspace_id": str(workspace_id),
                    "document_id": str(target_document_id),
                    "chunk_id": str(target_chunk_id),
                    "workspace_corpus_generation_id": str(corpus_id),
                    "embedding_space_generation_id": str(embedding_id),
                    "sparse_space_generation_id": None,
                    "event_id": str(target_event_id),
                    "publication_status": "provisional",
                },
            }
        ],
    }
    body = json.dumps(manifest, separators=(",", ":")).encode()
    pipeline_components = pipeline(corpus_id, embedding_id)
    pipeline_body = json.dumps(
        pipeline_components, ensure_ascii=False, separators=(",", ":"), sort_keys=True
    ).encode()
    request = {
        "contract_version": 1,
        "request_id": str(request_id),
        "operation_id": str(operation_id),
        "workspace_id": str(workspace_id),
        "source_document_id": str(source_document_id),
        "target_document_id": str(target_document_id),
        "source_event_id": str(source_event_id),
        "target_event_id": str(target_event_id),
        "lease_generation": 2,
        "lease_token": "lease-token",
        "manifest": {
            "bucket": "documents",
            "object_key": "exact/manifest.json",
            "checksum_sha256": hashlib.sha256(body).hexdigest(),
            "entry_count": 1,
            "schema_version": "document-content-clone-manifest-v1",
        },
        "pipeline_fingerprint": hashlib.sha256(pipeline_body).hexdigest(),
        "pipeline_components": pipeline_components,
    }
    return body, request


def test_exact_manifest_is_cloned_and_reported_from_target_identities() -> None:
    body, payload = fixture()
    object_store = ObjectStore(body)
    vectors = VectorStore()
    orchestrator = ContentCloneOrchestrator(
        object_store=object_store,
        vector_store=vectors,
        batch_size=16,  # type: ignore[arg-type]
    )

    complete, count, digest = orchestrator.clone(
        ContentCloneVectorRequest.model_validate(payload)
    )

    assert complete is True
    assert count == 1
    assert vectors.request is not None
    assert digest == point_manifest_digest(
        tuple(mapping.target_point.point_id for mapping in vectors.request.mappings)
    )
    assert object_store.calls == [{"Bucket": "documents", "Key": "exact/manifest.json"}]


def test_manifest_checksum_mismatch_fails_closed_before_vector_access() -> None:
    body, payload = fixture()
    payload["manifest"]["checksum_sha256"] = "0" * 64
    vectors = VectorStore()
    orchestrator = ContentCloneOrchestrator(
        object_store=ObjectStore(body),
        vector_store=vectors,
        batch_size=16,  # type: ignore[arg-type]
    )

    with pytest.raises(ContentCloneContractError, match="checksum"):
        orchestrator.clone(ContentCloneVectorRequest.model_validate(payload))

    assert vectors.request is None


def test_pipeline_fingerprint_mismatch_fails_closed_before_manifest_access() -> None:
    body, payload = fixture()
    payload["pipeline_fingerprint"] = "0" * 64
    object_store = ObjectStore(body)
    vectors = VectorStore()
    orchestrator = ContentCloneOrchestrator(
        object_store=object_store,
        vector_store=vectors,
        batch_size=16,  # type: ignore[arg-type]
    )

    with pytest.raises(ContentCloneContractError, match="pipeline fingerprint"):
        orchestrator.clone(ContentCloneVectorRequest.model_validate(payload))

    assert object_store.calls == []
    assert vectors.request is None


def test_stale_lease_generation_fails_closed() -> None:
    body, payload = fixture()
    payload["lease_generation"] = 3
    orchestrator = ContentCloneOrchestrator(
        object_store=ObjectStore(body),
        vector_store=VectorStore(),
        batch_size=16,  # type: ignore[arg-type]
    )

    with pytest.raises(ContentCloneContractError, match="identity"):
        orchestrator.clone(ContentCloneVectorRequest.model_validate(payload))


def test_cleanup_deletes_exact_target_scope_and_verifies_absence() -> None:
    _, payload = fixture()
    vectors = VectorStore()
    orchestrator = ContentCloneOrchestrator(
        object_store=ObjectStore(b""),
        vector_store=vectors,
        batch_size=16,  # type: ignore[arg-type]
    )
    cleanup = ContentCloneCleanupRequest.model_validate(
        {
            "contract_version": 1,
            "request_id": payload["request_id"],
            "operation_id": payload["operation_id"],
            "workspace_id": payload["workspace_id"],
            "target_document_id": payload["target_document_id"],
            "target_event_id": payload["target_event_id"],
            "pipeline_components": payload["pipeline_components"],
        }
    )

    assert orchestrator.cleanup(cleanup) is True
    assert vectors.deleted is not None
    assert vectors.deleted.document_id == cleanup.target_document_id
    assert vectors.deleted.event_id == cleanup.target_event_id
