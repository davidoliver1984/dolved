from uuid import UUID

from app.sparse.fastembed_adapter import FastEmbedSparseEncoder
from app.sparse.models import (
    SparseEmbeddingProfile,
    SparseEncodingInput,
    SparseEncodingPurpose,
    SparseEncodingRequest,
)


def test_pinned_real_splade_model_loads_and_encodes() -> None:
    profile = SparseEmbeddingProfile(
        provider="fastembed",
        model="prithivida/Splade_PP_en_v1",
        tokenizer="bert-base-uncased",
        tokenizer_revision=None,
        output_representation="sparse-index-weight",
        max_input_tokens=512,
        document_input_type="document",
        query_input_type="query",
        model_revision="efcd182bc7eb351e81a9445752d4388c2bab500b",
        adapter_version="1",
    )
    result = FastEmbedSparseEncoder(
        model_name=profile.model,
        model_source_repository="Qdrant/Splade_PP_en_v1",
        model_revision=profile.model_revision,
        cache_dir="/opt/fastembed-cache",
    ).encode(
        SparseEncodingRequest(
            correlation_id=UUID("00000000-0000-4000-8000-000000000002"),
            workspace_id=UUID("00000000-0000-4000-8000-000000000003"),
            profile=profile,
            purpose=SparseEncodingPurpose.QUERY,
            items=(
                SparseEncodingInput(
                    source_id=UUID("00000000-0000-4000-8000-000000000001"),
                    text="medicine omission safety",
                ),
            ),
        )
    )

    assert len(result.encodings) == 1
    vector = result.encodings[0].vector
    assert vector.indices
    assert len(vector.indices) == len(vector.values)
    assert all(value > 0 for value in vector.values)
