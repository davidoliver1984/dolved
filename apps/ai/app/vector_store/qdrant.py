import math
from collections.abc import Mapping
from typing import Any
from uuid import UUID

from qdrant_client import QdrantClient
from qdrant_client import models as qdrant_models

from app.vector_store.errors import (
    VectorSpaceCompatibilityError,
    VectorStoreError,
    VectorStoreMalformedResponseError,
    VectorStorePartialWriteError,
    VectorStoreUnavailableError,
)
from app.vector_store.models import (
    VectorCompletenessReport,
    VectorCompletenessRequest,
    VectorDistance,
    VectorPoint,
    VectorPointIdentity,
    VectorPublicationStatus,
    VectorScope,
    VectorSearchHit,
    VectorSearchRequest,
    VectorSpace,
    VectorUpsertRequest,
    VectorUpsertResult,
)

INDEXED_PAYLOAD_FIELDS = (
    "workspace_id",
    "workspace_corpus_generation_id",
    "document_id",
    "event_id",
    "publication_status",
)
PAYLOAD_FIELDS = (
    "workspace_id",
    "document_id",
    "chunk_id",
    "workspace_corpus_generation_id",
    "embedding_space_generation_id",
    "event_id",
    "publication_status",
)


class QdrantVectorStore:
    """The only adapter allowed to translate platform vector models to Qdrant."""

    def __init__(self, client: QdrantClient) -> None:
        self._client = client

    @classmethod
    def connect(
        cls,
        *,
        url: str,
        api_key: str | None,
        timeout_seconds: int,
    ) -> QdrantVectorStore:
        return cls(
            QdrantClient(
                url=url,
                api_key=api_key,
                timeout=timeout_seconds,
            )
        )

    def ensure_vector_space(self, vector_space: VectorSpace) -> None:
        try:
            if not self._client.collection_exists(vector_space.collection_name):
                try:
                    self._client.create_collection(
                        collection_name=vector_space.collection_name,
                        vectors_config={
                            vector_space.vector_name: qdrant_models.VectorParams(
                                size=vector_space.dimensions,
                                distance=self._distance(vector_space.distance),
                            )
                        },
                    )
                except Exception:
                    if not self._client.collection_exists(vector_space.collection_name):
                        raise
            self._validate_vector_space(vector_space)
            self._ensure_payload_indexes(vector_space)
        except VectorStoreError:
            raise
        except Exception as exception:
            raise VectorStoreUnavailableError(
                "The vector store could not ensure the requested vector space."
            ) from exception

    def upsert(self, request: VectorUpsertRequest) -> VectorUpsertResult:
        self._validate_vector_space_safe(request.vector_space)
        persisted_point_ids: list[UUID] = []
        batch_count = 0

        for start in range(0, len(request.points), request.batch_size):
            batch = request.points[start : start + request.batch_size]
            try:
                result = self._client.upsert(
                    collection_name=request.vector_space.collection_name,
                    points=[
                        self._qdrant_point(request.vector_space, point)
                        for point in batch
                    ],
                    wait=True,
                )
                if result.status is not qdrant_models.UpdateStatus.COMPLETED:
                    raise VectorStoreUnavailableError(
                        "The vector store did not complete a synchronous upsert."
                    )
            except VectorStoreError:
                if persisted_point_ids:
                    raise VectorStorePartialWriteError(
                        tuple(persisted_point_ids)
                    ) from None
                raise
            except Exception as exception:
                if persisted_point_ids:
                    raise VectorStorePartialWriteError(
                        tuple(persisted_point_ids)
                    ) from exception
                raise VectorStoreUnavailableError(
                    "The vector store could not persist the requested points."
                ) from exception

            persisted_point_ids.extend(point.point_id for point in batch)
            batch_count += 1

        return VectorUpsertResult(
            point_ids=tuple(persisted_point_ids),
            batch_count=batch_count,
        )

    def search(self, request: VectorSearchRequest) -> tuple[VectorSearchHit, ...]:
        self._validate_vector_space_safe(request.scope.vector_space)
        try:
            response = self._client.query_points(
                collection_name=request.scope.vector_space.collection_name,
                query=list(request.query_vector),
                using=request.scope.vector_space.vector_name,
                query_filter=self._scope_filter(
                    request.scope.model_copy(
                        update={
                            "publication_status": VectorPublicationStatus.PUBLISHED,
                        }
                    )
                ),
                limit=request.limit,
                with_payload=list(PAYLOAD_FIELDS),
                with_vectors=False,
            )
            return tuple(
                self._search_hit(point, request.scope) for point in response.points
            )
        except VectorStoreError:
            raise
        except Exception as exception:
            raise VectorStoreUnavailableError(
                "The vector store search could not be completed."
            ) from exception

    def count(self, scope: VectorScope) -> int:
        self._validate_vector_space_safe(scope.vector_space)
        try:
            return self._client.count(
                collection_name=scope.vector_space.collection_name,
                count_filter=self._scope_filter(scope),
                exact=True,
            ).count
        except Exception as exception:
            raise VectorStoreUnavailableError(
                "The vector-store scope could not be counted."
            ) from exception

    def verify_completeness(
        self, request: VectorCompletenessRequest
    ) -> VectorCompletenessReport:
        schema_compatible = self._is_vector_space_compatible(request.scope.vector_space)
        point_vectors_compatible = True
        actual: dict[UUID, VectorPointIdentity | None] = {}

        try:
            offset: int | str | UUID | None = None
            while True:
                records, offset = self._client.scroll(
                    collection_name=request.scope.vector_space.collection_name,
                    scroll_filter=self._scope_filter(request.scope),
                    limit=256,
                    offset=offset,
                    with_payload=list(PAYLOAD_FIELDS),
                    with_vectors=[request.scope.vector_space.vector_name],
                )
                for record in records:
                    point_id = self._point_id(record.id)
                    actual[point_id] = self._payload_identity(record.payload)
                    if not self._record_vector_compatible(
                        record.vector, request.scope.vector_space
                    ):
                        point_vectors_compatible = False
                if offset is None:
                    break
        except VectorStoreError:
            raise
        except Exception as exception:
            raise VectorStoreUnavailableError(
                "Vector completeness could not be verified."
            ) from exception

        expected = {point.point_id: point for point in request.expected_points}
        expected_ids = set(expected)
        actual_ids = set(actual)
        mismatches = tuple(
            sorted(
                (
                    point_id
                    for point_id in expected_ids & actual_ids
                    if actual[point_id] != expected[point_id]
                ),
                key=str,
            )
        )
        return VectorCompletenessReport(
            expected_count=len(expected),
            actual_count=len(actual),
            missing_point_ids=tuple(sorted(expected_ids - actual_ids, key=str)),
            unexpected_point_ids=tuple(sorted(actual_ids - expected_ids, key=str)),
            payload_mismatch_point_ids=mismatches,
            vector_schema_compatible=(schema_compatible and point_vectors_compatible),
        )

    def delete(self, scope: VectorScope) -> None:
        if not self._client.collection_exists(scope.vector_space.collection_name):
            return
        self._validate_vector_space_safe(scope.vector_space)
        try:
            result = self._client.delete(
                collection_name=scope.vector_space.collection_name,
                points_selector=qdrant_models.FilterSelector(
                    filter=self._scope_filter(scope)
                ),
                wait=True,
            )
            if result.status is not qdrant_models.UpdateStatus.COMPLETED:
                raise VectorStoreUnavailableError(
                    "The vector store did not complete a synchronous delete."
                )
        except VectorStoreError:
            raise
        except Exception as exception:
            raise VectorStoreUnavailableError(
                "The vector-store scope could not be deleted."
            ) from exception

    def publish(self, scope: VectorScope) -> None:
        if scope.event_id is None:
            raise VectorSpaceCompatibilityError(
                "Publishing vectors requires an explicit event scope."
            )
        self._validate_vector_space_safe(scope.vector_space)
        provisional_scope = scope.model_copy(
            update={"publication_status": VectorPublicationStatus.PROVISIONAL}
        )
        try:
            result = self._client.set_payload(
                collection_name=scope.vector_space.collection_name,
                payload={"publication_status": VectorPublicationStatus.PUBLISHED.value},
                points=qdrant_models.FilterSelector(
                    filter=self._scope_filter(provisional_scope)
                ),
                wait=True,
            )
            if result.status is not qdrant_models.UpdateStatus.COMPLETED:
                raise VectorStoreUnavailableError(
                    "The vector store did not complete publication synchronously."
                )
        except VectorStoreError:
            raise
        except Exception as exception:
            raise VectorStoreUnavailableError(
                "The vector-store publication could not be completed."
            ) from exception

    def remove_vector_space(self, vector_space: VectorSpace) -> None:
        try:
            if self._client.collection_exists(vector_space.collection_name):
                self._validate_vector_space(vector_space)
                self._client.delete_collection(vector_space.collection_name)
        except VectorStoreError:
            raise
        except Exception as exception:
            raise VectorStoreUnavailableError(
                "The unused vector space could not be removed."
            ) from exception

    def _ensure_payload_indexes(self, vector_space: VectorSpace) -> None:
        collection = self._client.get_collection(vector_space.collection_name)
        for field_name in INDEXED_PAYLOAD_FIELDS:
            existing = collection.payload_schema.get(field_name)
            if existing is None:
                try:
                    self._client.create_payload_index(
                        collection_name=vector_space.collection_name,
                        field_name=field_name,
                        field_schema=qdrant_models.PayloadSchemaType.KEYWORD,
                        wait=True,
                    )
                except Exception:
                    refreshed = self._client.get_collection(
                        vector_space.collection_name
                    )
                    raced_index = refreshed.payload_schema.get(field_name)
                    if (
                        raced_index is None
                        or raced_index.data_type
                        is not qdrant_models.PayloadSchemaType.KEYWORD
                    ):
                        raise
                continue
            if existing.data_type is not qdrant_models.PayloadSchemaType.KEYWORD:
                raise VectorSpaceCompatibilityError(
                    f"Payload index {field_name!r} is not a keyword index."
                )

        refreshed = self._client.get_collection(vector_space.collection_name)
        for field_name in INDEXED_PAYLOAD_FIELDS:
            existing = refreshed.payload_schema.get(field_name)
            if (
                existing is None
                or existing.data_type is not qdrant_models.PayloadSchemaType.KEYWORD
            ):
                raise VectorSpaceCompatibilityError(
                    f"Required payload index {field_name!r} is unavailable."
                )

    def _validate_vector_space_safe(self, vector_space: VectorSpace) -> None:
        try:
            self._validate_vector_space(vector_space)
        except VectorStoreError:
            raise
        except Exception as exception:
            raise VectorStoreUnavailableError(
                "The vector-space schema could not be inspected."
            ) from exception

    def _validate_vector_space(self, vector_space: VectorSpace) -> None:
        collection = self._client.get_collection(vector_space.collection_name)
        vectors = collection.config.params.vectors
        vector = (
            vectors.get(vector_space.vector_name) if isinstance(vectors, dict) else None
        )
        if (
            vector is None
            or vector.size != vector_space.dimensions
            or vector.distance is not self._distance(vector_space.distance)
        ):
            raise VectorSpaceCompatibilityError(
                "The existing vector-space schema is incompatible."
            )

    def _is_vector_space_compatible(self, vector_space: VectorSpace) -> bool:
        try:
            self._validate_vector_space(vector_space)
        except VectorSpaceCompatibilityError:
            return False
        except Exception as exception:
            raise VectorStoreUnavailableError(
                "The vector-space schema could not be inspected."
            ) from exception
        return True

    @staticmethod
    def _distance(distance: VectorDistance) -> qdrant_models.Distance:
        if distance is VectorDistance.COSINE:
            return qdrant_models.Distance.COSINE
        raise VectorSpaceCompatibilityError("Unsupported vector distance.")

    @staticmethod
    def _qdrant_point(
        vector_space: VectorSpace, point: VectorPoint
    ) -> qdrant_models.PointStruct:
        return qdrant_models.PointStruct(
            id=str(point.point_id),
            vector={vector_space.vector_name: list(point.values)},
            payload={
                "workspace_id": str(point.workspace_id),
                "document_id": str(point.document_id),
                "chunk_id": str(point.chunk_id),
                "workspace_corpus_generation_id": str(
                    point.workspace_corpus_generation_id
                ),
                "embedding_space_generation_id": str(
                    point.embedding_space_generation_id
                ),
                "event_id": str(point.event_id),
                "publication_status": point.publication_status.value,
            },
        )

    @staticmethod
    def _scope_filter(scope: VectorScope) -> qdrant_models.Filter:
        conditions: list[qdrant_models.Condition] = [
            qdrant_models.FieldCondition(
                key="workspace_id",
                match=qdrant_models.MatchValue(value=str(scope.workspace_id)),
            ),
            qdrant_models.FieldCondition(
                key="workspace_corpus_generation_id",
                match=qdrant_models.MatchValue(
                    value=str(scope.workspace_corpus_generation_id)
                ),
            ),
        ]
        if scope.document_id is not None:
            conditions.append(
                qdrant_models.FieldCondition(
                    key="document_id",
                    match=qdrant_models.MatchValue(value=str(scope.document_id)),
                )
            )
        if scope.document_ids:
            conditions.append(
                qdrant_models.FieldCondition(
                    key="document_id",
                    match=qdrant_models.MatchAny(
                        any=[str(document_id) for document_id in scope.document_ids]
                    ),
                )
            )
        if scope.event_id is not None:
            conditions.append(
                qdrant_models.FieldCondition(
                    key="event_id",
                    match=qdrant_models.MatchValue(value=str(scope.event_id)),
                )
            )
        if scope.publication_status is not None:
            conditions.append(
                qdrant_models.FieldCondition(
                    key="publication_status",
                    match=qdrant_models.MatchValue(
                        value=scope.publication_status.value
                    ),
                )
            )
        return qdrant_models.Filter(must=conditions)

    def _search_hit(
        self, point: qdrant_models.ScoredPoint, scope: VectorScope
    ) -> VectorSearchHit:
        identity = self._payload_identity(point.payload)
        if identity is None or not self._identity_matches_scope(identity, scope):
            raise VectorStoreMalformedResponseError(
                "A vector search result did not preserve the requested scope."
            )
        point_id = self._point_id(point.id)
        if point_id != identity.point_id:
            raise VectorStoreMalformedResponseError(
                "A vector search result has an invalid deterministic identity."
            )
        return VectorSearchHit(
            point_id=point_id, score=point.score, **identity.model_dump()
        )

    @staticmethod
    def _point_id(value: int | str | UUID) -> UUID:
        try:
            return UUID(str(value))
        except (TypeError, ValueError) as exception:
            raise VectorStoreMalformedResponseError(
                "A vector-store point identifier is not a UUID."
            ) from exception

    @staticmethod
    def _payload_identity(
        payload: Mapping[str, Any] | None,
    ) -> VectorPointIdentity | None:
        if payload is None or any(field not in payload for field in PAYLOAD_FIELDS):
            return None
        try:
            return VectorPointIdentity(
                workspace_id=UUID(str(payload["workspace_id"])),
                document_id=UUID(str(payload["document_id"])),
                chunk_id=UUID(str(payload["chunk_id"])),
                workspace_corpus_generation_id=UUID(
                    str(payload["workspace_corpus_generation_id"])
                ),
                embedding_space_generation_id=UUID(
                    str(payload["embedding_space_generation_id"])
                ),
                event_id=UUID(str(payload["event_id"])),
                publication_status=VectorPublicationStatus(
                    str(payload["publication_status"])
                ),
            )
        except TypeError, ValueError:
            return None

    @staticmethod
    def _identity_matches_scope(
        identity: VectorPointIdentity, scope: VectorScope
    ) -> bool:
        return (
            identity.workspace_id == scope.workspace_id
            and identity.workspace_corpus_generation_id
            == scope.workspace_corpus_generation_id
            and identity.embedding_space_generation_id
            == scope.vector_space.embedding_space_generation_id
            and (scope.document_id is None or identity.document_id == scope.document_id)
            and (not scope.document_ids or identity.document_id in scope.document_ids)
            and (scope.event_id is None or identity.event_id == scope.event_id)
            and (
                scope.publication_status is None
                or identity.publication_status == scope.publication_status
            )
        )

    @staticmethod
    def _record_vector_compatible(vector: Any, vector_space: VectorSpace) -> bool:
        if not isinstance(vector, dict):
            return False
        values = vector.get(vector_space.vector_name)
        return (
            isinstance(values, list)
            and len(values) == vector_space.dimensions
            and all(isinstance(value, int | float) for value in values)
            and all(math.isfinite(float(value)) for value in values)
        )
