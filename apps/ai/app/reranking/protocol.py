from typing import Protocol

from app.reranking.models import RerankRequest, RerankResult


class Reranker(Protocol):
    def rerank(self, request: RerankRequest) -> RerankResult: ...
