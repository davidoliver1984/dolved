from typing import Protocol

from app.retrieval.models import RetrievalPlan, SearchRequest, SearchResponse


class RetrievalPlanner(Protocol):
    def plan(self, question: str, *, evaluated_at: str) -> RetrievalPlan: ...


class Retriever(Protocol):
    def search(self, request: SearchRequest) -> SearchResponse: ...
