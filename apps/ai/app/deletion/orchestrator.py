from dataclasses import dataclass
from typing import Any, Protocol

from pydantic import ValidationError

from app.deletion.client import DeletionGrant
from app.vector_store.errors import (
    VectorStoreError,
    VectorStoreUnavailableError,
)
from app.vector_store.models import VectorScope, VectorSpace


class DeletionReportingClient(Protocol):
    def claim(self, *, event_id: str, raw_body: str) -> DeletionGrant: ...

    def complete(
        self, context: dict[str, Any], scopes: list[dict[str, Any]]
    ) -> None: ...

    def fail(
        self,
        context: dict[str, Any],
        *,
        classification: str,
        failure_code: str,
        failure_message: str,
    ) -> None: ...


class VectorCleanupStore(Protocol):
    def collection_exists(self, vector_space: VectorSpace) -> bool: ...

    def delete(self, scope: VectorScope) -> None: ...

    def count(self, scope: VectorScope) -> int: ...


@dataclass(frozen=True)
class DeletionResult:
    acknowledge: bool
    code: str


class DocumentDeletionOrchestrator:
    def __init__(
        self, *, client: DeletionReportingClient, vector_store: VectorCleanupStore
    ) -> None:
        self._client = client
        self._vector_store = vector_store

    def process(self, *, event: dict[str, Any], raw_body: str) -> DeletionResult:
        grant = self._client.claim(event_id=str(event["event_id"]), raw_body=raw_body)
        if grant.outcome == "already_completed":
            return DeletionResult(True, "already_completed")
        if not grant.may_process:
            return DeletionResult(False, grant.outcome)
        context = {
            "event_id": event["event_id"],
            "workspace_id": event["workspace_id"],
            "document_id": event["document_id"],
            "lease_token": grant.lease_token,
        }
        evidence: list[dict[str, Any]] = []
        try:
            for index, raw_scope in enumerate(grant.vector_scopes):
                scope = VectorScope.model_validate(raw_scope)
                if not self._vector_store.collection_exists(scope.vector_space):
                    evidence.append(
                        {
                            "scope_index": index,
                            "outcome": "authoritative_not_found",
                            "remaining_point_count": 0,
                        }
                    )
                    continue
                self._vector_store.delete(scope)
                remaining = self._vector_store.count(scope)
                if remaining != 0:
                    raise VectorStoreUnavailableError(
                        "The vector scope still contains document points."
                    )
                evidence.append(
                    {
                        "scope_index": index,
                        "outcome": "verified_clean",
                        "remaining_point_count": remaining,
                    }
                )
            self._client.complete(context, evidence)
        except VectorStoreUnavailableError:
            self._client.fail(
                context,
                classification="retryable",
                failure_code="vector_store_unavailable",
                failure_message="Vector cleanup could not be verified.",
            )
            return DeletionResult(False, "vector_store_unavailable")
        except ValidationError, VectorStoreError:
            self._client.fail(
                context,
                classification="permanent",
                failure_code="invalid_vector_scope",
                failure_message="An authorised vector scope was invalid.",
            )
            return DeletionResult(True, "invalid_vector_scope")

        return DeletionResult(True, "deleted")
