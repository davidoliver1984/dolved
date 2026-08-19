from typing import Any
from uuid import uuid4

from app.deletion.client import DeletionGrant
from app.deletion.orchestrator import DocumentDeletionOrchestrator
from app.vector_store.errors import VectorStoreUnavailableError


def scope() -> dict[str, Any]:
    return {
        "vector_space": {
            "collection_name": "vectors",
            "embedding_space_generation_id": str(uuid4()),
            "profile_fingerprint": "a" * 64,
            "vector_name": "dense",
            "dimensions": 3,
            "distance": "cosine",
            "sparse": None,
        },
        "workspace_id": str(uuid4()),
        "workspace_corpus_generation_id": str(uuid4()),
        "document_id": str(uuid4()),
    }


class Client:
    def __init__(self, grant: DeletionGrant) -> None:
        self.grant = grant
        self.completed: list[dict[str, Any]] | None = None
        self.failed: tuple[str, str] | None = None

    def claim(self, **_: Any) -> DeletionGrant:
        return self.grant

    def complete(self, context: dict[str, Any], scopes: list[dict[str, Any]]) -> None:
        self.completed = scopes

    def fail(
        self,
        context: dict[str, Any],
        *,
        classification: str,
        failure_code: str,
        failure_message: str,
    ) -> None:
        self.failed = (classification, failure_code)


class Store:
    def __init__(self, *, exists: bool = True, remaining: int = 0) -> None:
        self.exists = exists
        self.remaining = remaining
        self.deleted = 0

    def collection_exists(self, vector_space: Any) -> bool:
        return self.exists

    def delete(self, vector_scope: Any) -> None:
        self.deleted += 1

    def count(self, vector_scope: Any) -> int:
        return self.remaining


def event(raw_scope: dict[str, Any]) -> dict[str, Any]:
    return {
        "event_id": str(uuid4()),
        "workspace_id": raw_scope["workspace_id"],
        "document_id": raw_scope["document_id"],
    }


def test_deletes_only_authorised_scope_and_reports_verified_zero() -> None:
    raw_scope = scope()
    client = Client(DeletionGrant("claimed", str(uuid4()), vector_scopes=(raw_scope,)))
    store = Store()

    result = DocumentDeletionOrchestrator(client=client, vector_store=store).process(
        event=event(raw_scope), raw_body="{}"
    )

    assert result.acknowledge is True
    assert store.deleted == 1
    assert client.completed == [
        {"scope_index": 0, "outcome": "verified_clean", "remaining_point_count": 0}
    ]


def test_confirmed_missing_collection_is_clean_without_delete() -> None:
    raw_scope = scope()
    client = Client(DeletionGrant("claimed", str(uuid4()), vector_scopes=(raw_scope,)))
    store = Store(exists=False)

    result = DocumentDeletionOrchestrator(client=client, vector_store=store).process(
        event=event(raw_scope), raw_body="{}"
    )

    assert result.acknowledge is True
    assert store.deleted == 0
    assert client.completed is not None
    assert client.completed[0]["outcome"] == "authoritative_not_found"


def test_unverified_nonzero_cleanup_remains_retryable() -> None:
    raw_scope = scope()
    client = Client(DeletionGrant("claimed", str(uuid4()), vector_scopes=(raw_scope,)))

    result = DocumentDeletionOrchestrator(
        client=client, vector_store=Store(remaining=1)
    ).process(event=event(raw_scope), raw_body="{}")

    assert result.acknowledge is False
    assert client.failed == ("retryable", "vector_store_unavailable")


def test_provider_unavailability_is_not_treated_as_collection_absence() -> None:
    raw_scope = scope()
    client = Client(DeletionGrant("claimed", str(uuid4()), vector_scopes=(raw_scope,)))

    class Unavailable(Store):
        def collection_exists(self, vector_space: Any) -> bool:
            raise VectorStoreUnavailableError("unavailable")

    result = DocumentDeletionOrchestrator(
        client=client, vector_store=Unavailable()
    ).process(event=event(raw_scope), raw_body="{}")

    assert result.acknowledge is False
    assert client.completed is None
