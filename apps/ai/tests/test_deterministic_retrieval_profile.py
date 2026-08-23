import json
from pathlib import Path
from typing import Any
from uuid import UUID

import pytest
from pydantic import ValidationError

from app.embedding.factory import create_embedder, embedding_profile
from app.embedding.fake import DeterministicFakeEmbedder
from app.embedding.models import EmbeddingInput, EmbeddingPurpose, EmbeddingRequest
from app.reranking.factory import create_reranker, reranker_profile
from app.reranking.fake import DeterministicReranker
from app.retrieval.deterministic import CatalogueRetrievalPlanner
from app.retrieval.planner import RetrievalPlanningError
from app.settings import Settings
from app.sparse.factory import create_sparse_encoder, sparse_embedding_profile
from app.sparse.fake import DeterministicSparseEncoder
from app.sparse.models import (
    SparseEncodedVector,
    SparseEncodingInput,
    SparseEncodingPurpose,
    SparseEncodingRequest,
)


def settings(**values: object) -> Settings:
    configured: dict[str, Any] = {
        "environment": "e2e",
        "embedding_provider": "deterministic",
        "sparse_embedding_provider": "deterministic",
        "reranker_provider": "deterministic",
        "retrieval_planner_provider": "deterministic",
        "voyage_api_key": "",
        "retrieval_planner_api_key": "",
        "contextualiser_api_key": "",
        "generation_openai_api_key": "",
    }
    configured.update(values)
    return Settings(**configured)


def test_complete_e2e_profile_selects_only_deterministic_adapters() -> None:
    configured = settings()
    assert isinstance(create_embedder(configured), DeterministicFakeEmbedder)
    assert isinstance(create_sparse_encoder(configured), DeterministicSparseEncoder)
    assert isinstance(create_reranker(configured), DeterministicReranker)
    assert embedding_profile(configured).provider == "deterministic"
    assert sparse_embedding_profile(configured).provider == "deterministic"
    assert reranker_profile(configured).provider == "deterministic"
    assert embedding_profile(configured).model == "token-hash-unit-vector-v3"
    assert sparse_embedding_profile(configured).model == "token-hash-sparse-v3"
    assert reranker_profile(configured).model == "token-overlap-v2"


def test_deterministic_dense_and_sparse_adapters_preserve_lexical_signal() -> None:
    configured = settings(embedding_dimensions=64)
    dense_profile = embedding_profile(configured)
    dense = DeterministicFakeEmbedder().embed(
        EmbeddingRequest(
            correlation_id=UUID("11111111-1111-4111-8111-111111111111"),
            workspace_id=UUID("22222222-2222-4222-8222-222222222222"),
            purpose=EmbeddingPurpose.QUERY,
            profile=dense_profile,
            items=(
                EmbeddingInput(
                    source_id=UUID("44444444-4444-4444-8444-444444444441"),
                    text="missed medicine dose safety",
                ),
                EmbeddingInput(
                    source_id=UUID("44444444-4444-4444-8444-444444444442"),
                    text="medicine dose safety procedure",
                ),
                EmbeddingInput(
                    source_id=UUID("44444444-4444-4444-8444-444444444443"),
                    text="fire evacuation assembly point",
                ),
            ),
        )
    )

    def dot(left: tuple[float, ...], right: tuple[float, ...]) -> float:
        return sum(a * b for a, b in zip(left, right, strict=True))

    vectors = {str(item.source_id): item.values for item in dense.embeddings}
    query = vectors["44444444-4444-4444-8444-444444444441"]
    assert dot(query, vectors["44444444-4444-4444-8444-444444444442"]) > dot(
        query, vectors["44444444-4444-4444-8444-444444444443"]
    )

    sparse_profile = sparse_embedding_profile(configured)
    sparse = DeterministicSparseEncoder().encode(
        SparseEncodingRequest(
            correlation_id=UUID("33333333-3333-4333-8333-333333333333"),
            workspace_id=UUID("22222222-2222-4222-8222-222222222222"),
            profile=sparse_profile,
            purpose=SparseEncodingPurpose.QUERY,
            items=(
                SparseEncodingInput(
                    source_id=UUID("55555555-5555-4555-8555-555555555551"),
                    text="missed medicine dose safety",
                ),
                SparseEncodingInput(
                    source_id=UUID("55555555-5555-4555-8555-555555555552"),
                    text="medicine dose safety procedure",
                ),
                SparseEncodingInput(
                    source_id=UUID("55555555-5555-4555-8555-555555555553"),
                    text="fire evacuation assembly point",
                ),
            ),
        )
    )
    indices = {
        str(item.source_id): set(item.vector.indices) for item in sparse.encodings
    }
    query_indices = indices["55555555-5555-4555-8555-555555555551"]
    assert len(query_indices & indices["55555555-5555-4555-8555-555555555552"]) == 4
    assert len(query_indices & indices["55555555-5555-4555-8555-555555555553"]) == 1


def test_deterministic_adapters_break_equal_lexical_scores_by_stable_identity() -> None:
    configured = settings(embedding_dimensions=64)
    dense_profile = embedding_profile(configured)
    query_id = UUID("11111111-1111-4111-8111-111111111111")
    lower_id = UUID("22222222-2222-4222-8222-222222222221")
    higher_id = UUID("eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee1")
    embedder = DeterministicFakeEmbedder()
    query = (
        embedder.embed(
            EmbeddingRequest(
                correlation_id=query_id,
                workspace_id=query_id,
                purpose=EmbeddingPurpose.QUERY,
                profile=dense_profile,
                items=(EmbeddingInput(source_id=query_id, text="zebra"),),
            )
        )
        .embeddings[0]
        .values
    )
    documents = embedder.embed(
        EmbeddingRequest(
            correlation_id=query_id,
            workspace_id=query_id,
            purpose=EmbeddingPurpose.DOCUMENT,
            profile=dense_profile,
            items=(
                EmbeddingInput(source_id=lower_id, text="quartz"),
                EmbeddingInput(source_id=higher_id, text="flamingo"),
            ),
        )
    ).embeddings

    def dot(left: tuple[float, ...], right: tuple[float, ...]) -> float:
        return sum(a * b for a, b in zip(left, right, strict=True))

    assert dot(query, documents[0].values) < dot(query, documents[1].values)

    sparse_profile = sparse_embedding_profile(configured)
    sparse_encoder = DeterministicSparseEncoder()
    sparse_query = (
        sparse_encoder.encode(
            SparseEncodingRequest(
                correlation_id=query_id,
                workspace_id=query_id,
                profile=sparse_profile,
                purpose=SparseEncodingPurpose.QUERY,
                items=(SparseEncodingInput(source_id=query_id, text="zebra"),),
            )
        )
        .encodings[0]
        .vector
    )
    sparse_documents = sparse_encoder.encode(
        SparseEncodingRequest(
            correlation_id=query_id,
            workspace_id=query_id,
            profile=sparse_profile,
            purpose=SparseEncodingPurpose.DOCUMENT,
            items=(
                SparseEncodingInput(source_id=lower_id, text="quartz"),
                SparseEncodingInput(source_id=higher_id, text="flamingo"),
            ),
        )
    ).encodings

    query_values = dict(zip(sparse_query.indices, sparse_query.values, strict=True))

    def sparse_dot(document: SparseEncodedVector) -> float:
        return sum(
            query_values.get(index, 0.0) * value
            for index, value in zip(
                document.vector.indices, document.vector.values, strict=True
            )
        )

    assert sparse_dot(sparse_documents[0]) < sparse_dot(sparse_documents[1])


def test_e2e_profile_rejects_any_live_adapter() -> None:
    with pytest.raises(ValidationError, match="complete deterministic profile"):
        settings(embedding_provider="voyage")


def test_deterministic_provider_is_rejected_outside_approved_isolation() -> None:
    with pytest.raises(ValidationError, match="approved isolated environment"):
        Settings(embedding_provider="deterministic")


def test_catalogue_planner_is_exact_and_fails_closed_for_unknown_questions(
    tmp_path: Path,
) -> None:
    question = "Which incident deadline applies?"
    catalogue = tmp_path / "catalogue.json"
    catalogue.write_text(
        json.dumps(
            {
                "schema_version": 1,
                "entries": [
                    {
                        "question": question,
                        "plan": {
                            "retrieval_queries": [question],
                            "temporal_mode": "current",
                            "explicit_date": None,
                            "temporal_reference": None,
                            "location_references": [],
                            "clarification_reason": None,
                        },
                    }
                ],
            }
        )
    )
    planner = CatalogueRetrievalPlanner(str(catalogue))

    reformatted = tmp_path / "reformatted.json"
    reformatted.write_text(json.dumps(json.loads(catalogue.read_text()), indent=4))
    reformatted_planner = CatalogueRetrievalPlanner(str(reformatted))

    assert planner.plan(
        question, evaluated_at="2026-08-22T00:00:00Z"
    ).retrieval_queries == (question,)
    assert len(planner.catalogue_checksum) == 64
    assert reformatted_planner.catalogue_checksum == planner.catalogue_checksum
    with pytest.raises(RetrievalPlanningError) as failure:
        planner.plan("Different question", evaluated_at="2026-08-22T00:00:00Z")
    assert failure.value.category == "deterministic_scenario_unknown"
