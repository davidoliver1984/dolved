from typing import Protocol

from app.vector_store.models import (
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
    def ensure_vector_space(self, vector_space: VectorSpace) -> None: ...

    def upsert(self, request: VectorUpsertRequest) -> VectorUpsertResult: ...

    def search(self, request: VectorSearchRequest) -> tuple[VectorSearchHit, ...]: ...

    def count(self, scope: VectorScope) -> int: ...

    def verify_completeness(
        self, request: VectorCompletenessRequest
    ) -> VectorCompletenessReport: ...

    def delete(self, scope: VectorScope) -> None: ...

    def remove_vector_space(self, vector_space: VectorSpace) -> None: ...
