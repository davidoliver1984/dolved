from collections.abc import Iterator
from typing import cast
from unittest.mock import Mock
from uuid import UUID, uuid4

import pytest
from pydantic import SecretStr
from qdrant_client import QdrantClient
from qdrant_client import models as qdrant_models

from app.settings import Settings
from app.vector_store.errors import (
    VectorSpaceCompatibilityError,
    VectorStorePartialWriteError,
)
from app.vector_store.factory import create_vector_store
from app.vector_store.models import (
    VectorCompletenessRequest,
    VectorPoint,
    VectorPublicationStatus,
    VectorScope,
    VectorSearchRequest,
    VectorSpace,
    VectorUpsertRequest,
)
from app.vector_store.qdrant import INDEXED_PAYLOAD_FIELDS, QdrantVectorStore


@pytest.fixture
def qdrant_client() -> Iterator[QdrantClient]:
    client = QdrantClient(url="http://qdrant:6333", timeout=10)
    yield client
    client.close()


@pytest.fixture
def vector_space(qdrant_client: QdrantClient) -> Iterator[VectorSpace]:
    space = VectorSpace(
        collection_name=f"test-r14-s03-{uuid4().hex}",
        embedding_space_generation_id=uuid4(),
        profile_fingerprint="test-profile",
        vector_name="dense",
        dimensions=3,
    )
    yield space
    if qdrant_client.collection_exists(space.collection_name):
        qdrant_client.delete_collection(space.collection_name)


def vector_point(
    space: VectorSpace,
    *,
    workspace_id: UUID | None = None,
    document_id: UUID | None = None,
    corpus_generation_id: UUID | None = None,
    values: tuple[float, ...] = (1.0, 0.0, 0.0),
    publication_status: VectorPublicationStatus = VectorPublicationStatus.PROVISIONAL,
) -> VectorPoint:
    return VectorPoint(
        workspace_id=workspace_id or uuid4(),
        document_id=document_id or uuid4(),
        chunk_id=uuid4(),
        workspace_corpus_generation_id=corpus_generation_id or uuid4(),
        embedding_space_generation_id=space.embedding_space_generation_id,
        event_id=uuid4(),
        publication_status=publication_status,
        values=values,
    )


def test_factory_uses_settings_without_exposing_qdrant_types() -> None:
    store = create_vector_store(
        Settings(
            qdrant_url="http://qdrant:6333",
            qdrant_api_key=SecretStr(""),
            qdrant_timeout_seconds=3,
        )
    )

    assert isinstance(store, QdrantVectorStore)


@pytest.mark.integration
def test_ensure_is_idempotent_and_creates_the_required_schema_and_indexes(
    qdrant_client: QdrantClient, vector_space: VectorSpace
) -> None:
    store = QdrantVectorStore(qdrant_client)

    store.ensure_vector_space(vector_space)
    store.ensure_vector_space(vector_space)

    collection = qdrant_client.get_collection(vector_space.collection_name)
    vectors = collection.config.params.vectors
    assert isinstance(vectors, dict)
    assert set(vectors) == {"dense"}
    assert vectors["dense"].size == 3
    assert vectors["dense"].distance is qdrant_models.Distance.COSINE
    assert set(collection.payload_schema) == set(INDEXED_PAYLOAD_FIELDS)
    assert all(
        collection.payload_schema[field].data_type
        is qdrant_models.PayloadSchemaType.KEYWORD
        for field in INDEXED_PAYLOAD_FIELDS
    )


@pytest.mark.integration
def test_existing_incompatible_collection_is_rejected(
    qdrant_client: QdrantClient, vector_space: VectorSpace
) -> None:
    qdrant_client.create_collection(
        vector_space.collection_name,
        vectors_config={
            "dense": qdrant_models.VectorParams(
                size=4,
                distance=qdrant_models.Distance.COSINE,
            )
        },
    )

    with pytest.raises(VectorSpaceCompatibilityError):
        QdrantVectorStore(qdrant_client).ensure_vector_space(vector_space)


@pytest.mark.integration
def test_destructive_operations_reject_an_incompatible_vector_space(
    qdrant_client: QdrantClient, vector_space: VectorSpace
) -> None:
    store = QdrantVectorStore(qdrant_client)
    store.ensure_vector_space(vector_space)
    incompatible = vector_space.model_copy(update={"dimensions": 4})
    scope = VectorScope(
        vector_space=incompatible,
        workspace_id=uuid4(),
        workspace_corpus_generation_id=uuid4(),
    )

    with pytest.raises(VectorSpaceCompatibilityError):
        store.count(scope)
    with pytest.raises(VectorSpaceCompatibilityError):
        store.delete(scope)
    with pytest.raises(VectorSpaceCompatibilityError):
        store.remove_vector_space(incompatible)

    assert qdrant_client.collection_exists(vector_space.collection_name) is True


@pytest.mark.integration
def test_upsert_is_bounded_idempotent_and_stores_only_the_minimal_payload(
    qdrant_client: QdrantClient, vector_space: VectorSpace
) -> None:
    store = QdrantVectorStore(qdrant_client)
    store.ensure_vector_space(vector_space)
    workspace_id = uuid4()
    corpus_generation_id = uuid4()
    points = tuple(
        vector_point(
            vector_space,
            workspace_id=workspace_id,
            corpus_generation_id=corpus_generation_id,
        )
        for _ in range(3)
    )
    request = VectorUpsertRequest(
        vector_space=vector_space,
        points=points,
        batch_size=2,
    )

    first = store.upsert(request)
    second = store.upsert(request)

    assert first == second
    assert first.batch_count == 2
    scope = VectorScope(
        vector_space=vector_space,
        workspace_id=workspace_id,
        workspace_corpus_generation_id=corpus_generation_id,
    )
    assert store.count(scope) == 3
    records, _ = qdrant_client.scroll(
        vector_space.collection_name,
        scroll_filter=store._scope_filter(scope),
        limit=10,
        with_payload=True,
        with_vectors=False,
    )
    assert all(
        set(record.payload or {})
        == {
            "workspace_id",
            "document_id",
            "chunk_id",
            "workspace_corpus_generation_id",
            "embedding_space_generation_id",
            "event_id",
            "publication_status",
        }
        for record in records
    )


@pytest.mark.integration
def test_search_and_document_delete_are_tenant_and_generation_scoped(
    qdrant_client: QdrantClient, vector_space: VectorSpace
) -> None:
    store = QdrantVectorStore(qdrant_client)
    store.ensure_vector_space(vector_space)
    workspace_a = uuid4()
    workspace_b = uuid4()
    corpus_a = uuid4()
    corpus_b = uuid4()
    document_a = uuid4()
    own = vector_point(
        vector_space,
        workspace_id=workspace_a,
        document_id=document_a,
        corpus_generation_id=corpus_a,
        publication_status=VectorPublicationStatus.PUBLISHED,
    )
    other_document = vector_point(
        vector_space,
        workspace_id=workspace_a,
        corpus_generation_id=corpus_a,
        values=(0.9, 0.1, 0.0),
        publication_status=VectorPublicationStatus.PUBLISHED,
    )
    other_workspace = vector_point(
        vector_space,
        workspace_id=workspace_b,
        corpus_generation_id=corpus_b,
        publication_status=VectorPublicationStatus.PUBLISHED,
    )
    store.upsert(
        VectorUpsertRequest(
            vector_space=vector_space,
            points=(own, other_document, other_workspace),
        )
    )
    workspace_scope = VectorScope(
        vector_space=vector_space,
        workspace_id=workspace_a,
        workspace_corpus_generation_id=corpus_a,
    )

    hits = store.search(
        VectorSearchRequest(
            scope=workspace_scope,
            query_vector=(1.0, 0.0, 0.0),
            limit=10,
        )
    )

    assert {hit.point_id for hit in hits} == {own.point_id, other_document.point_id}
    assert all(hit.workspace_id == workspace_a for hit in hits)
    assert all(hit.workspace_corpus_generation_id == corpus_a for hit in hits)

    store.delete(workspace_scope.model_copy(update={"document_id": document_a}))
    assert store.count(workspace_scope) == 1
    assert (
        store.count(
            VectorScope(
                vector_space=vector_space,
                workspace_id=workspace_b,
                workspace_corpus_generation_id=corpus_b,
            )
        )
        == 1
    )


@pytest.mark.integration
def test_completeness_compares_identity_payload_and_schema_not_only_count(
    qdrant_client: QdrantClient, vector_space: VectorSpace
) -> None:
    store = QdrantVectorStore(qdrant_client)
    store.ensure_vector_space(vector_space)
    workspace_id = uuid4()
    corpus_generation_id = uuid4()
    document_id = uuid4()
    present = vector_point(
        vector_space,
        workspace_id=workspace_id,
        document_id=document_id,
        corpus_generation_id=corpus_generation_id,
    )
    unexpected = vector_point(
        vector_space,
        workspace_id=workspace_id,
        document_id=document_id,
        corpus_generation_id=corpus_generation_id,
    )
    missing = vector_point(
        vector_space,
        workspace_id=workspace_id,
        document_id=document_id,
        corpus_generation_id=corpus_generation_id,
    ).identity()
    mismatched = vector_point(
        vector_space,
        workspace_id=workspace_id,
        document_id=document_id,
        corpus_generation_id=corpus_generation_id,
    ).identity()
    store.upsert(
        VectorUpsertRequest(
            vector_space=vector_space,
            points=(present, unexpected),
        )
    )
    qdrant_client.upsert(
        vector_space.collection_name,
        points=[
            qdrant_models.PointStruct(
                id=str(mismatched.point_id),
                vector={"dense": [1.0, 0.0, 0.0]},
                payload={
                    "workspace_id": str(workspace_id),
                    "document_id": str(document_id),
                    "chunk_id": str(mismatched.chunk_id),
                    "workspace_corpus_generation_id": str(corpus_generation_id),
                    "embedding_space_generation_id": str(uuid4()),
                    "event_id": str(mismatched.event_id),
                    "publication_status": mismatched.publication_status.value,
                },
            )
        ],
        wait=True,
    )
    scope = VectorScope(
        vector_space=vector_space,
        workspace_id=workspace_id,
        workspace_corpus_generation_id=corpus_generation_id,
        document_id=document_id,
    )

    report = store.verify_completeness(
        VectorCompletenessRequest(
            scope=scope,
            expected_points=(present.identity(), missing, mismatched),
        )
    )

    assert report.expected_count == 3
    assert report.actual_count == 3
    assert report.missing_point_ids == (missing.point_id,)
    assert report.unexpected_point_ids == (unexpected.point_id,)
    assert report.payload_mismatch_point_ids == (mismatched.point_id,)
    assert report.vector_schema_compatible is True
    assert report.is_complete is False


@pytest.mark.integration
def test_successful_completeness_and_idempotent_vector_space_removal(
    qdrant_client: QdrantClient, vector_space: VectorSpace
) -> None:
    store = QdrantVectorStore(qdrant_client)
    store.ensure_vector_space(vector_space)
    point = vector_point(vector_space)
    store.upsert(VectorUpsertRequest(vector_space=vector_space, points=(point,)))
    scope = VectorScope(
        vector_space=vector_space,
        workspace_id=point.workspace_id,
        workspace_corpus_generation_id=point.workspace_corpus_generation_id,
    )

    report = store.verify_completeness(
        VectorCompletenessRequest(scope=scope, expected_points=(point.identity(),))
    )

    assert report.is_complete is True
    store.remove_vector_space(vector_space)
    store.remove_vector_space(vector_space)
    assert qdrant_client.collection_exists(vector_space.collection_name) is False


@pytest.mark.integration
def test_publication_is_event_scoped_idempotent_and_verifiably_complete(
    qdrant_client: QdrantClient, vector_space: VectorSpace
) -> None:
    store = QdrantVectorStore(qdrant_client)
    store.ensure_vector_space(vector_space)
    event_id = uuid4()
    point = vector_point(vector_space).model_copy(update={"event_id": event_id})
    store.upsert(VectorUpsertRequest(vector_space=vector_space, points=(point,)))
    provisional_scope = VectorScope(
        vector_space=vector_space,
        workspace_id=point.workspace_id,
        workspace_corpus_generation_id=point.workspace_corpus_generation_id,
        document_id=point.document_id,
        event_id=event_id,
        publication_status=VectorPublicationStatus.PROVISIONAL,
    )

    store.publish(provisional_scope)
    store.publish(provisional_scope)
    published = point.identity().model_copy(
        update={"publication_status": VectorPublicationStatus.PUBLISHED}
    )
    report = store.verify_completeness(
        VectorCompletenessRequest(
            scope=provisional_scope.model_copy(
                update={"publication_status": VectorPublicationStatus.PUBLISHED}
            ),
            expected_points=(published,),
        )
    )

    assert report.is_complete is True
    assert store.count(provisional_scope) == 0


@pytest.mark.integration
def test_later_batch_failure_reports_partial_write_for_safe_retry(
    qdrant_client: QdrantClient, vector_space: VectorSpace
) -> None:
    real_store = QdrantVectorStore(qdrant_client)
    real_store.ensure_vector_space(vector_space)
    points = tuple(vector_point(vector_space) for _ in range(3))
    request = VectorUpsertRequest(
        vector_space=vector_space,
        points=points,
        batch_size=2,
    )
    calls = 0

    def fail_second_upsert(*args: object, **kwargs: object) -> object:
        nonlocal calls
        calls += 1
        if calls == 2:
            raise RuntimeError("synthetic transport failure")
        return qdrant_client.upsert(*args, **kwargs)  # type: ignore[arg-type]

    client = Mock(wraps=qdrant_client, spec=QdrantClient)
    client.upsert.side_effect = fail_second_upsert
    failing_store = QdrantVectorStore(cast(QdrantClient, client))

    with pytest.raises(VectorStorePartialWriteError) as raised:
        failing_store.upsert(request)

    assert raised.value.persisted_point_ids == tuple(
        point.point_id for point in points[:2]
    )
    retry = real_store.upsert(request)
    assert retry.point_ids == tuple(point.point_id for point in points)
