from typing import Protocol

from app.embedding.models import EmbeddingRequest, EmbeddingResult


class Embedder(Protocol):
    def embed(self, request: EmbeddingRequest) -> EmbeddingResult: ...
