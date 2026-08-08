import os
from uuid import uuid4

import pytest
from pydantic import SecretStr

from app.reranking.models import (
    RerankCandidate,
    RerankerProfile,
    RerankRequest,
)
from app.reranking.voyage import VoyageReranker
from app.retrieval.models import RetrievalSide


@pytest.mark.integration
def test_live_voyage_reranker_requires_explicit_opt_in() -> None:
    api_key = os.getenv("VOYAGE_API_KEY", "")
    if os.getenv("RUN_VOYAGE_RERANK_INTEGRATION") != "1" or not api_key:
        pytest.skip("set RUN_VOYAGE_RERANK_INTEGRATION=1 and VOYAGE_API_KEY to opt in")
    candidates = tuple(
        RerankCandidate(
            chunk_id=uuid4(),
            document_id=uuid4(),
            document_family_id=uuid4(),
            version_position=index,
            side=RetrievalSide.PRIMARY,
            text=text,
            fused_score=score,
            fused_rank=index,
        )
        for index, text, score in (
            (1, "Annual leave is requested through the HR portal.", 0.5),
            (2, "Fire extinguishers are inspected every month.", 0.4),
        )
    )
    result = VoyageReranker(
        api_key=SecretStr(api_key),
        api_url="https://api.voyageai.com/v1/rerank",
        timeout_seconds=20,
        max_attempts=2,
        initial_backoff_seconds=0.5,
        max_backoff_seconds=2,
    ).rerank(
        RerankRequest(
            request_id=uuid4(),
            workspace_id=uuid4(),
            query="How do I request annual leave?",
            profile=RerankerProfile(
                provider="voyage",
                model="rerank-2.5",
                adapter_version="1",
                truncation=False,
            ),
            candidates=candidates,
            top_k=1,
        )
    )

    assert result.candidates[0].chunk_id == candidates[0].chunk_id
    assert result.profile.model == "rerank-2.5"
