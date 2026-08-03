import hashlib
import math
from collections.abc import Iterable

from app.embedding.errors import EmbeddingError
from app.embedding.models import (
    EmbeddedVector,
    EmbeddingRequest,
    EmbeddingResult,
)


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
                    seed=(
                        f"{request.profile.fingerprint()}\x00"
                        f"{request.purpose.value}\x00{item.text}"
                    ).encode(),
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
    def _vector(*, seed: bytes, dimensions: int) -> tuple[float, ...]:
        values = tuple(
            ((byte / 255.0) * 2.0) - 1.0
            for byte in DeterministicFakeEmbedder._bytes(seed, dimensions)
        )
        magnitude = math.sqrt(sum(value * value for value in values))
        return tuple(value / magnitude for value in values)

    @staticmethod
    def _bytes(seed: bytes, count: int) -> Iterable[int]:
        generated = 0
        counter = 0
        while generated < count:
            digest = hashlib.sha256(seed + counter.to_bytes(8, "big")).digest()
            for byte in digest:
                if generated == count:
                    return
                generated += 1
                yield byte
            counter += 1
