import hashlib
import math

from app.embedding.errors import EmbeddingError
from app.embedding.models import (
    EmbeddedVector,
    EmbeddingRequest,
    EmbeddingResult,
)
from app.retrieval.deterministic_text import deterministic_token_counts


class DeterministicFakeEmbedder:
    """Test embedder whose output is stable for a semantic input and profile."""

    def __init__(self, *, failure: EmbeddingError | None = None) -> None:
        self.failure = failure
        self.requests: list[EmbeddingRequest] = []

    def embed(self, request: EmbeddingRequest) -> EmbeddingResult:
        self.requests.append(request)

        if self.failure is not None:
            raise self.failure

        embeddings = tuple(
            EmbeddedVector(
                source_id=item.source_id,
                values=self._vector(
                    text=item.text,
                    dimensions=request.profile.dimensions,
                ),
                dimensions=request.profile.dimensions,
            )
            for item in request.items
        )
        return EmbeddingResult(
            profile=request.profile,
            profile_fingerprint=request.profile.fingerprint(),
            purpose=request.purpose,
            embeddings=embeddings,
        )

    @staticmethod
    def _vector(*, text: str, dimensions: int) -> tuple[float, ...]:
        values = [0.0] * dimensions
        for token, count in deterministic_token_counts(text).items():
            digest = hashlib.sha256(token.encode()).digest()
            index = int.from_bytes(digest[:8], "big") % dimensions
            values[index] += 1.0 + math.log(count)
        magnitude = math.sqrt(sum(value * value for value in values))
        return tuple(value / magnitude for value in values)
