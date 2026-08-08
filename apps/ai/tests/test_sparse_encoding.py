from types import SimpleNamespace
from uuid import uuid4

import numpy as np
import pytest

from app.sparse.errors import (
    MalformedSparseResponseError,
    SparseInputTooLargeError,
    SparseProfileMismatchError,
)
from app.sparse.fastembed_adapter import FastEmbedSparseEncoder
from app.sparse.models import (
    SparseEmbeddingProfile,
    SparseEncodingInput,
    SparseEncodingPurpose,
    SparseEncodingRequest,
)


class RecordingEngine:
    def __init__(self, vectors: list[object]) -> None:
        self.vectors = vectors
        self.document_calls: list[list[str]] = []
        self.query_calls: list[list[str]] = []

    def embed(self, documents: list[str], **kwargs):  # type: ignore[no-untyped-def]
        self.document_calls.append(documents)
        return self.vectors

    def query_embed(self, query: list[str], **kwargs):  # type: ignore[no-untyped-def]
        self.query_calls.append(query)
        return self.vectors


def profile(*, max_input_tokens: int = 8) -> SparseEmbeddingProfile:
    return SparseEmbeddingProfile(
        provider="fastembed",
        model="test-splade",
        tokenizer="test-tokenizer",
        output_representation="sparse-index-weight",
        max_input_tokens=max_input_tokens,
        adapter_version="1",
    )


def request(
    sparse_profile: SparseEmbeddingProfile,
    *,
    purpose: SparseEncodingPurpose = SparseEncodingPurpose.DOCUMENT,
) -> SparseEncodingRequest:
    return SparseEncodingRequest(
        correlation_id=uuid4(),
        workspace_id=uuid4(),
        profile=sparse_profile,
        purpose=purpose,
        items=(SparseEncodingInput(source_id=uuid4(), text="alpha beta"),),
    )


def test_fastembed_adapter_preserves_profile_lineage_and_sorts_sparse_indices() -> None:
    engine = RecordingEngine(
        [
            SimpleNamespace(
                indices=np.array([9, 2], dtype=np.int64),
                values=np.array([0.25, 0.75], dtype=np.float32),
            )
        ]
    )
    sparse_profile = profile()
    encoder = FastEmbedSparseEncoder(
        model_name="test-splade",
        engine=engine,
        token_counter=lambda text: len(text.split()),
    )

    result = encoder.encode(request(sparse_profile))

    assert result.profile_fingerprint == sparse_profile.fingerprint()
    assert result.encodings[0].vector.indices == (2, 9)
    assert result.encodings[0].vector.values == pytest.approx((0.75, 0.25))
    assert engine.document_calls == [["alpha beta"]]


def test_fastembed_adapter_uses_distinct_query_operation() -> None:
    engine = RecordingEngine(
        [SimpleNamespace(indices=np.array([1]), values=np.array([1.0]))]
    )
    sparse_profile = profile()
    encoder = FastEmbedSparseEncoder(
        model_name="test-splade",
        engine=engine,
        token_counter=lambda _: 2,
    )

    encoder.encode(request(sparse_profile, purpose=SparseEncodingPurpose.QUERY))

    assert engine.query_calls == [["alpha beta"]]
    assert engine.document_calls == []


def test_fastembed_adapter_rejects_input_before_provider_truncation() -> None:
    engine = RecordingEngine([])
    sparse_profile = profile(max_input_tokens=1)
    encoder = FastEmbedSparseEncoder(
        model_name="test-splade",
        engine=engine,
        token_counter=lambda _: 2,
    )

    with pytest.raises(SparseInputTooLargeError):
        encoder.encode(request(sparse_profile))

    assert engine.document_calls == []


def test_fastembed_adapter_rejects_profile_and_malformed_provider_output() -> None:
    wrong = profile().model_copy(update={"model": "wrong"})
    encoder = FastEmbedSparseEncoder(
        model_name="test-splade",
        engine=RecordingEngine([]),
        token_counter=lambda _: 1,
    )
    with pytest.raises(SparseProfileMismatchError):
        encoder.encode(request(wrong))

    malformed = FastEmbedSparseEncoder(
        model_name="test-splade",
        engine=RecordingEngine(
            [SimpleNamespace(indices=np.array([1, 1]), values=np.array([1.0, 2.0]))]
        ),
        token_counter=lambda _: 1,
    )
    with pytest.raises(MalformedSparseResponseError):
        malformed.encode(request(profile()))
