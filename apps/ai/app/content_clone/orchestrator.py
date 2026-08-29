from __future__ import annotations

import hashlib
import json
from typing import Any, Protocol
from uuid import UUID

from app.content_clone.models import (
    ContentCloneCleanupRequest,
    ContentCloneVectorRequest,
)
from app.ingestion.canonicalisation import point_manifest_digest
from app.vector_store.models import (
    SparseVectorSpace,
    VectorCloneMapping,
    VectorCloneReport,
    VectorCloneRequest,
    VectorDistance,
    VectorPointIdentity,
    VectorPublicationStatus,
    VectorScope,
    VectorSpace,
)


class ObjectStore(Protocol):
    def get_object(self, **kwargs: object) -> dict[str, Any]: ...


class CloneVectorStore(Protocol):
    def clone_points(self, request: VectorCloneRequest) -> VectorCloneReport: ...

    def delete(self, scope: VectorScope) -> None: ...

    def count(self, scope: VectorScope) -> int: ...


class ContentCloneContractError(RuntimeError):
    pass


class ContentCloneOrchestrator:
    def __init__(
        self,
        *,
        object_store: ObjectStore,
        vector_store: CloneVectorStore,
        batch_size: int,
    ) -> None:
        self._object_store = object_store
        self._vector_store = vector_store
        self._batch_size = batch_size

    def clone(self, request: ContentCloneVectorRequest) -> tuple[bool, int, str]:
        pipeline_body = json.dumps(
            request.pipeline_components,
            ensure_ascii=False,
            separators=(",", ":"),
            sort_keys=True,
        ).encode()
        if hashlib.sha256(pipeline_body).hexdigest() != request.pipeline_fingerprint:
            raise ContentCloneContractError(
                "pipeline fingerprint does not match its components"
            )
        body = self._read_manifest(request)
        try:
            manifest = json.loads(body)
        except json.JSONDecodeError as exception:
            raise ContentCloneContractError(
                "clone manifest is not valid JSON"
            ) from exception
        if (
            not isinstance(manifest, dict)
            or manifest.get("schema_version") != request.manifest.schema_version
            or manifest.get("operation_id") != str(request.operation_id)
            or manifest.get("event_id") != str(request.target_event_id)
            or manifest.get("lease_generation") != request.lease_generation
            or not isinstance(manifest.get("entries"), list)
            or len(manifest["entries"]) != request.manifest.entry_count
        ):
            raise ContentCloneContractError(
                "clone manifest identity does not match request"
            )

        vector_space = self._vector_space(request.pipeline_components)
        source_scope = VectorScope(
            vector_space=vector_space,
            workspace_id=request.workspace_id,
            workspace_corpus_generation_id=UUID(
                request.pipeline_components["workspace_corpus_generation_id"]
            ),
            document_id=request.source_document_id,
            event_id=request.source_event_id,
            publication_status=VectorPublicationStatus.PUBLISHED,
        )
        target_scope = VectorScope(
            vector_space=vector_space,
            workspace_id=request.workspace_id,
            workspace_corpus_generation_id=UUID(
                request.pipeline_components["workspace_corpus_generation_id"]
            ),
            document_id=request.target_document_id,
            event_id=request.target_event_id,
            publication_status=VectorPublicationStatus.PROVISIONAL,
        )
        mappings = tuple(
            self._mapping(entry, request, vector_space) for entry in manifest["entries"]
        )
        report = self._vector_store.clone_points(
            VectorCloneRequest(
                vector_space=vector_space,
                source_scope=source_scope,
                target_scope=target_scope,
                mappings=mappings,
                batch_size=self._batch_size,
            )
        )
        return (
            report.complete,
            len(report.point_ids),
            point_manifest_digest(report.point_ids),
        )

    def cleanup(self, request: ContentCloneCleanupRequest) -> bool:
        vector_space = self._vector_space(request.pipeline_components)
        scope = VectorScope(
            vector_space=vector_space,
            workspace_id=request.workspace_id,
            workspace_corpus_generation_id=UUID(
                request.pipeline_components["workspace_corpus_generation_id"]
            ),
            document_id=request.target_document_id,
            event_id=request.target_event_id,
        )
        self._vector_store.delete(scope)
        return self._vector_store.count(scope) == 0

    def _read_manifest(self, request: ContentCloneVectorRequest) -> bytes:
        try:
            response = self._object_store.get_object(
                Bucket=request.manifest.bucket,
                Key=request.manifest.object_key,
            )
            body = response["Body"].read()
        except Exception as exception:
            raise ContentCloneContractError(
                "clone manifest could not be read"
            ) from exception
        if not isinstance(body, bytes):
            raise ContentCloneContractError("clone manifest body is invalid")
        if hashlib.sha256(body).hexdigest() != request.manifest.checksum_sha256:
            raise ContentCloneContractError("clone manifest checksum does not match")
        return body

    def _mapping(
        self,
        entry: Any,
        request: ContentCloneVectorRequest,
        vector_space: VectorSpace,
    ) -> VectorCloneMapping:
        if not isinstance(entry, dict) or not isinstance(
            entry.get("target_payload"), dict
        ):
            raise ContentCloneContractError("clone mapping entry is invalid")
        payload = entry["target_payload"]
        if (
            payload.get("workspace_id") != str(request.workspace_id)
            or payload.get("document_id") != str(request.target_document_id)
            or payload.get("event_id") != str(request.target_event_id)
            or payload.get("publication_status") != "provisional"
            or payload.get("chunk_id") != entry.get("target_chunk_id")
        ):
            raise ContentCloneContractError("clone target payload exceeds signed scope")
        point = VectorPointIdentity(
            workspace_id=UUID(payload["workspace_id"]),
            document_id=UUID(payload["document_id"]),
            chunk_id=UUID(payload["chunk_id"]),
            workspace_corpus_generation_id=UUID(
                payload["workspace_corpus_generation_id"]
            ),
            embedding_space_generation_id=UUID(
                payload["embedding_space_generation_id"]
            ),
            sparse_space_generation_id=(
                UUID(payload["sparse_space_generation_id"])
                if payload.get("sparse_space_generation_id") is not None
                else None
            ),
            event_id=UUID(payload["event_id"]),
            publication_status=VectorPublicationStatus.PROVISIONAL,
        )
        if (
            point.embedding_space_generation_id
            != vector_space.embedding_space_generation_id
            or str(point.point_id) != entry.get("target_point_id")
        ):
            raise ContentCloneContractError("clone vector generation does not match")
        return VectorCloneMapping(
            source_point_id=UUID(entry["source_point_id"]),
            target_point=point,
        )

    def _vector_space(self, components: dict[str, Any]) -> VectorSpace:
        try:
            dense = components["dense"]
            sparse = components.get("sparse")
            return VectorSpace(
                collection_name=dense["collection_name"],
                embedding_space_generation_id=UUID(
                    dense["embedding_space_generation_id"]
                ),
                profile_fingerprint=dense["embedding_profile_fingerprint"],
                vector_name=dense["vector_name"],
                dimensions=dense["dimensions"],
                distance=VectorDistance(dense["distance"]),
                sparse=(
                    SparseVectorSpace(
                        sparse_space_generation_id=UUID(
                            sparse["sparse_space_generation_id"]
                        ),
                        profile_fingerprint=sparse["sparse_profile_fingerprint"],
                        vector_name=sparse["vector_name"],
                    )
                    if sparse is not None
                    else None
                ),
            )
        except (KeyError, TypeError, ValueError) as exception:
            raise ContentCloneContractError(
                "pipeline vector identity is invalid"
            ) from exception
