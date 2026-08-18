from typing import Protocol

from app.conversation.models import ContextualisationRequest, ContextualisationResult


class QueryContextualizer(Protocol):
    def contextualize(
        self, request: ContextualisationRequest
    ) -> ContextualisationResult: ...
