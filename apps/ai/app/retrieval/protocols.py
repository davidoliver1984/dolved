from typing import Protocol

from app.retrieval.models import RetrievalPlan, SearchRequest, SearchResponse
from app.retrieval.planner import PlanningResult


class RetrievalPlanner(Protocol):
    def plan(self, question: str, *, evaluated_at: str) -> RetrievalPlan: ...
    def plan_with_observation(
        self, question: str, *, evaluated_at: str
    ) -> PlanningResult: ...


class Retriever(Protocol):
    def search(self, request: SearchRequest) -> SearchResponse: ...
