import math
from uuid import UUID, uuid4

import pytest
from pydantic import ValidationError

from app.embedding.errors import InvalidEmbeddingInputError
from app.embedding.fake import DeterministicFakeEmbedder
from app.embedding.models import (
    V1_VOYAGE_PROFILE,
    EmbeddingInput,
    EmbeddingPurpose,
    EmbeddingRequest,
)


def request_for(
    *texts: str, purpose: EmbeddingPurpose = EmbeddingPurpose.DOCUMENT
) -> EmbeddingRequest:
    return EmbeddingRequest(
        correlation_id=uuid4(),
        workspace_id=uuid4(),
        document_id=uuid4(),
        profile=V1_VOYAGE_PROFILE,
        purpose=purpose,
        items=tuple(EmbeddingInput(source_id=uuid4(), text=text) for text in texts),
    )


def test_v1_profile_captures_semantic_configuration_and_has_stable_fingerprint() -> (
    None
):
    assert V1_VOYAGE_PROFILE.model == "voyage-4-large"
    assert V1_VOYAGE_PROFILE.dimensions == 1024
    assert V1_VOYAGE_PROFILE.input_type_for(EmbeddingPurpose.DOCUMENT) == "document"
    assert V1_VOYAGE_PROFILE.input_type_for(EmbeddingPurpose.QUERY) == "query"
    assert V1_VOYAGE_PROFILE.fingerprint() == (
        "ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c"
    )


def test_request_rejects_blank_text_and_duplicate_source_ids() -> None:
    with pytest.raises(ValidationError, match="must not be blank"):
        EmbeddingInput(source_id=uuid4(), text=" \n ")

    source_id = uuid4()
    with pytest.raises(ValidationError, match="must be unique"):
        EmbeddingRequest(
            correlation_id=uuid4(),
            workspace_id=uuid4(),
            profile=V1_VOYAGE_PROFILE,
            purpose=EmbeddingPurpose.DOCUMENT,
            items=(
                EmbeddingInput(source_id=source_id, text="one"),
                EmbeddingInput(source_id=source_id, text="two"),
            ),
        )


def test_deterministic_fake_preserves_order_and_returns_unit_vectors() -> None:
    request = request_for("alpha", "beta")
    fake = DeterministicFakeEmbedder()

    first = fake.embed(request)
    second = fake.embed(request)

    assert first == second
    assert tuple(vector.source_id for vector in first.embeddings) == tuple(
        item.source_id for item in request.items
    )
    assert all(
        math.isclose(
            math.sqrt(sum(value * value for value in vector.values)),
            1.0,
        )
        for vector in first.embeddings
    )


def test_fake_output_preserves_one_lexical_space_across_purposes() -> None:
    source_id = UUID("00000000-0000-0000-0000-000000000001")
    correlation_id = uuid4()
    workspace_id = uuid4()
    items = (EmbeddingInput(source_id=source_id, text="same text"),)
    fake = DeterministicFakeEmbedder()

    document = fake.embed(
        EmbeddingRequest(
            correlation_id=correlation_id,
            workspace_id=workspace_id,
            profile=V1_VOYAGE_PROFILE,
            items=items,
            purpose=EmbeddingPurpose.DOCUMENT,
        )
    )
    query = fake.embed(
        EmbeddingRequest(
            correlation_id=correlation_id,
            workspace_id=workspace_id,
            profile=V1_VOYAGE_PROFILE,
            items=items,
            purpose=EmbeddingPurpose.QUERY,
        )
    )

    assert document.embeddings[0].values == query.embeddings[0].values


def test_fake_can_raise_an_injected_typed_failure() -> None:
    error = InvalidEmbeddingInputError("deliberate failure")
    fake = DeterministicFakeEmbedder(failure=error)

    with pytest.raises(InvalidEmbeddingInputError) as raised:
        fake.embed(request_for("text"))

    assert raised.value is error
