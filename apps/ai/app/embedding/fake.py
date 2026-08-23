import hashlib
import math
from uuid import UUID

from app.embedding.errors import EmbeddingError
from app.embedding.models import (
    EmbeddedVector,
    EmbeddingPurpose,
    EmbeddingRequest,
    EmbeddingResult,
)
from app.retrieval.deterministic_text import (
    deterministic_identity_tie_break,
    deterministic_token_counts,
)

_TIE_BREAK_WEIGHT = 0.001


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
                    source_id=item.source_id,
                    text=item.text,
                    dimensions=request.profile.dimensions,
                    purpose=request.purpose,
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
    def _vector(
        *,
        source_id: UUID,
        text: str,
        dimensions: int,
        purpose: EmbeddingPurpose,
    ) -> tuple[float, ...]:
        values = [0.0] * dimensions
        token_dimensions = max(1, dimensions - 1)
        for token, count in deterministic_token_counts(text).items():
            digest = hashlib.sha256(token.encode()).digest()
            index = 1 + (int.from_bytes(digest[:8], "big") % token_dimensions)
            if dimensions == 1:
                index = 0
            values[index] += 1.0 + math.log(count)
        if dimensions > 1:
            values[0] = _TIE_BREAK_WEIGHT * (
                deterministic_identity_tie_break(source_id)
                if purpose is EmbeddingPurpose.DOCUMENT
                else 1.0
            )
        magnitude = math.sqrt(sum(value * value for value in values))
        return tuple(value / magnitude for value in values)
