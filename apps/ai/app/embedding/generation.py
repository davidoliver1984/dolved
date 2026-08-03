from uuid import UUID

from app.chunking.models import ChunkingResult
from app.embedding.errors import (
    EmbeddingConfigurationError,
    EmbeddingProfileMismatchError,
    InvalidEmbeddingInputError,
    MalformedEmbeddingResponseError,
)
from app.embedding.models import (
    EmbeddedVector,
    EmbeddingInput,
    EmbeddingProfile,
    EmbeddingPurpose,
    EmbeddingRequest,
    EmbeddingResult,
)
from app.embedding.protocol import Embedder


class ChunkEmbeddingGenerator:
    """Generate document-purpose embeddings without owning their persistence."""

    def __init__(
        self,
        *,
        embedder: Embedder,
        profile: EmbeddingProfile,
        batch_size: int = 64,
    ) -> None:
        if not 1 <= batch_size <= 1000:
            raise EmbeddingConfigurationError(
                "embedding batch size must be between 1 and 1000"
            )
        self._embedder = embedder
        self._profile = profile
        self._batch_size = batch_size

    def generate(
        self,
        chunking_result: ChunkingResult,
        *,
        correlation_id: UUID,
    ) -> EmbeddingResult:
        if not chunking_result.chunks:
            raise InvalidEmbeddingInputError("cannot embed a document with no chunks")
        if any(not chunk.text.strip() for chunk in chunking_result.chunks):
            raise InvalidEmbeddingInputError("cannot embed blank chunk content")

        items = tuple(
            EmbeddingInput(source_id=chunk.id, text=chunk.text)
            for chunk in chunking_result.chunks
        )
        embeddings: list[EmbeddedVector] = []
        input_token_counts: list[int] = []
        batch_count = (len(items) + self._batch_size - 1) // self._batch_size

        for start in range(0, len(items), self._batch_size):
            batch = items[start : start + self._batch_size]
            request = EmbeddingRequest(
                correlation_id=correlation_id,
                workspace_id=chunking_result.workspace_id,
                document_id=chunking_result.document_id,
                profile=self._profile,
                purpose=EmbeddingPurpose.DOCUMENT,
                items=batch,
            )
            result = self._embedder.embed(request)
            if (
                result.profile != self._profile
                or result.profile_fingerprint != self._profile.fingerprint()
                or result.purpose is not EmbeddingPurpose.DOCUMENT
            ):
                raise EmbeddingProfileMismatchError(
                    "embedding response did not retain the requested profile and purpose"
                )
            expected_ids = tuple(item.source_id for item in batch)
            actual_ids = tuple(embedding.source_id for embedding in result.embeddings)
            if actual_ids != expected_ids:
                raise MalformedEmbeddingResponseError(
                    "embedding response did not preserve request order"
                )
            embeddings.extend(result.embeddings)
            if result.provider_input_tokens is not None:
                input_token_counts.append(result.provider_input_tokens)

        return EmbeddingResult(
            profile=self._profile,
            profile_fingerprint=self._profile.fingerprint(),
            purpose=EmbeddingPurpose.DOCUMENT,
            embeddings=tuple(embeddings),
            provider_input_tokens=(
                sum(input_token_counts)
                if len(input_token_counts) == batch_count
                else None
            ),
        )
