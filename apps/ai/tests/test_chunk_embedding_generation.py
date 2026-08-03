from uuid import uuid4

import pytest

from app.chunking.models import (
    BaselineChunkingConfiguration,
    Chunk,
    ChunkContribution,
    ChunkingResult,
    TokenizerIdentity,
)
from app.embedding.errors import (
    EmbeddingProfileMismatchError,
    InvalidEmbeddingInputError,
    MalformedEmbeddingResponseError,
)
from app.embedding.fake import DeterministicFakeEmbedder
from app.embedding.generation import ChunkEmbeddingGenerator
from app.embedding.models import V1_VOYAGE_PROFILE, EmbeddingResult
from app.extraction.models import TextSourceLocation


def chunking_result(count: int) -> ChunkingResult:
    configuration = BaselineChunkingConfiguration(
        tokenizer=TokenizerIdentity(
            library="test",
            library_version="1",
            encoding="test",
            vocabulary_fingerprint="test",
        )
    )
    chunks = tuple(
        Chunk(
            id=uuid4(),
            ordinal=index,
            text=f"chunk {index}",
            token_count=2,
            contributions=(
                ChunkContribution(
                    normalised_element_id=uuid4(),
                    source_element_ids=(uuid4(),),
                    source_locations=(
                        TextSourceLocation(
                            start_character=0,
                            end_character=len(f"chunk {index}"),
                            start_line=1,
                            end_line=1,
                        ),
                    ),
                    element_start_character=0,
                    element_end_character=len(f"chunk {index}"),
                    chunk_start_character=0,
                    chunk_end_character=len(f"chunk {index}"),
                    role="primary",
                ),
            ),
        )
        for index in range(count)
    )
    return ChunkingResult(
        workspace_id=uuid4(),
        document_id=uuid4(),
        strategy_name="test",
        strategy_version="1",
        configuration=configuration,
        configuration_fingerprint=configuration.fingerprint(),
        chunks=chunks,
    )


def test_generator_batches_chunks_and_preserves_context_and_order() -> None:
    source = chunking_result(5)
    fake = DeterministicFakeEmbedder()
    generator = ChunkEmbeddingGenerator(
        embedder=fake,
        profile=V1_VOYAGE_PROFILE,
        batch_size=2,
    )
    correlation_id = uuid4()

    result = generator.generate(source, correlation_id=correlation_id)

    assert [len(request.items) for request in fake.requests] == [2, 2, 1]
    assert all(request.correlation_id == correlation_id for request in fake.requests)
    assert all(request.workspace_id == source.workspace_id for request in fake.requests)
    assert all(request.document_id == source.document_id for request in fake.requests)
    assert tuple(vector.source_id for vector in result.embeddings) == tuple(
        chunk.id for chunk in source.chunks
    )


def test_generator_rejects_an_empty_chunk_set() -> None:
    with pytest.raises(InvalidEmbeddingInputError, match="no chunks"):
        ChunkEmbeddingGenerator(
            embedder=DeterministicFakeEmbedder(),
            profile=V1_VOYAGE_PROFILE,
        ).generate(chunking_result(0), correlation_id=uuid4())


def test_generator_rejects_blank_chunk_content_as_a_typed_failure() -> None:
    source = chunking_result(1)
    source = source.model_copy(
        update={"chunks": (source.chunks[0].model_copy(update={"text": "  \n"}),)}
    )

    with pytest.raises(InvalidEmbeddingInputError, match="blank chunk"):
        ChunkEmbeddingGenerator(
            embedder=DeterministicFakeEmbedder(),
            profile=V1_VOYAGE_PROFILE,
        ).generate(source, correlation_id=uuid4())


def test_generator_rejects_a_provider_that_changes_local_mapping() -> None:
    class ReorderingEmbedder:
        def embed(self, request: object) -> EmbeddingResult:
            result = DeterministicFakeEmbedder().embed(request)  # type: ignore[arg-type]
            return result.model_copy(
                update={"embeddings": tuple(reversed(result.embeddings))}
            )

    with pytest.raises(MalformedEmbeddingResponseError, match="preserve request order"):
        ChunkEmbeddingGenerator(
            embedder=ReorderingEmbedder(),
            profile=V1_VOYAGE_PROFILE,
            batch_size=2,
        ).generate(chunking_result(2), correlation_id=uuid4())


def test_generator_rejects_an_incompatible_provider_result() -> None:
    class WrongPurposeEmbedder:
        def embed(self, request: object) -> EmbeddingResult:
            result = DeterministicFakeEmbedder().embed(request)  # type: ignore[arg-type]
            return result.model_copy(update={"purpose": "query"})

    with pytest.raises(EmbeddingProfileMismatchError, match="profile and purpose"):
        ChunkEmbeddingGenerator(
            embedder=WrongPurposeEmbedder(),
            profile=V1_VOYAGE_PROFILE,
        ).generate(chunking_result(1), correlation_id=uuid4())
