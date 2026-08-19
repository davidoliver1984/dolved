from typing import Protocol

from app.vector_store.models import (
    SparseVectorSearchRequest,
    VectorCompletenessReport,
    VectorCompletenessRequest,
    VectorScope,
    VectorSearchHit,
    VectorSearchRequest,
    VectorSpace,
    VectorUpsertRequest,
    VectorUpsertResult,
)


class VectorStore(Protocol):
    def collection_exists(self, vector_space: VectorSpace) -> bool: ...

    def ensure_vector_space(self, vector_space: VectorSpace) -> None: ...

    def upsert(self, request: VectorUpsertRequest) -> VectorUpsertResult: ...

    def search(self, request: VectorSearchRequest) -> tuple[VectorSearchHit, ...]: ...

    def search_sparse(
        self, request: SparseVectorSearchRequest
    ) -> tuple[VectorSearchHit, ...]: ...

    def count(self, scope: VectorScope) -> int: ...

    def verify_completeness(
        self, request: VectorCompletenessRequest
    ) -> VectorCompletenessReport: ...

    def publish(self, scope: VectorScope) -> None: ...

    def delete(self, scope: VectorScope) -> None: ...

    def remove_vector_space(self, vector_space: VectorSpace) -> None: ...
