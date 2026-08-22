import json
from pathlib import Path
from typing import Any

import pytest
from pydantic import ValidationError

from app.embedding.factory import create_embedder, embedding_profile
from app.embedding.fake import DeterministicFakeEmbedder
from app.reranking.factory import create_reranker, reranker_profile
from app.reranking.fake import DeterministicReranker
from app.retrieval.deterministic import CatalogueRetrievalPlanner
from app.retrieval.planner import RetrievalPlanningError
from app.settings import Settings
from app.sparse.factory import create_sparse_encoder, sparse_embedding_profile
from app.sparse.fake import DeterministicSparseEncoder


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
