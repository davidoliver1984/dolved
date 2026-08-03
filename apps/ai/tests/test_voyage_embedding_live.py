import os
from uuid import uuid4

import pytest
from pydantic import SecretStr

from app.embedding.models import (
    V1_VOYAGE_PROFILE,
    EmbeddingInput,
    EmbeddingPurpose,
    EmbeddingRequest,
)
from app.embedding.voyage import VoyageEmbedder

pytestmark = pytest.mark.integration


def test_voyage_contract_against_live_provider() -> None:
    api_key = os.getenv("VOYAGE_API_KEY", "")
    if os.getenv("RUN_VOYAGE_INTEGRATION") != "1" or not api_key:
        pytest.skip("set RUN_VOYAGE_INTEGRATION=1 and VOYAGE_API_KEY to opt in")

    request = EmbeddingRequest(
        correlation_id=uuid4(),
        workspace_id=uuid4(),
        document_id=uuid4(),
        profile=V1_VOYAGE_PROFILE,
        purpose=EmbeddingPurpose.DOCUMENT,
        items=(
            EmbeddingInput(
                source_id=uuid4(),
                text="Synthetic integration-test document content.",
            ),
        ),
    )

    result = VoyageEmbedder(api_key=SecretStr(api_key)).embed(request)

    assert result.profile_fingerprint == V1_VOYAGE_PROFILE.fingerprint()
    assert result.embeddings[0].source_id == request.items[0].source_id
    assert result.embeddings[0].dimensions == 1024
