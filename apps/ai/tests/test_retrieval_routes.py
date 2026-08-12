import base64
import hashlib
import hmac
import json
import time
from uuid import uuid4

from fastapi.testclient import TestClient

from app.main import app
from app.reranking.fake import DeterministicReranker
from app.retrieval.models import (
    RetrievalPlan,
    SearchLineage,
    SearchResponse,
    TemporalMode,
)
from app.retrieval.planner import FixedRetrievalPlanner
from app.retrieval.routes import (
    planner_dependency,
    reranker_dependency,
    retriever_dependency,
)

SECRET = "MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY="


class EmptyRetriever:
    def search(self, request):
        return SearchResponse(
            request_id=request.request_id,
            candidates=(),
            lineage=SearchLineage(
                embedding_profile_fingerprint=request.embedding_profile_fingerprint
            ),
        )


def signed_headers(
    path: str, body: bytes, workspace_id: str, request_id: str, purpose: str
) -> dict[str, str]:
    timestamp = str(int(time.time()))
    canonical = (
        f"{timestamp}\nPOST\n{path}\n{hashlib.sha256(body).hexdigest()}\n"
        f"{workspace_id}\n{purpose}\n{request_id}"
    )
    signature = hmac.new(
        base64.b64decode(SECRET), canonical.encode(), hashlib.sha256
    ).hexdigest()
    return {
        "Content-Type": "application/json",
        "X-Retrieval-Caller-Key-ID": "local-rc1",
        "X-Retrieval-Caller-Timestamp": timestamp,
        "X-Retrieval-Caller-Workspace-ID": workspace_id,
        "X-Retrieval-Caller-Request-ID": request_id,
        "X-Retrieval-Caller-Purpose": purpose,
        "X-Retrieval-Caller-Signature": f"rc1={signature}",
    }


def test_plan_endpoint_validates_signed_identity_and_rejects_replay() -> None:
    workspace_id = str(uuid4())
    request_id = str(uuid4())
    path = "/api/internal/retrieval/plan"
    payload = {
        "contract_version": 1,
        "request_id": request_id,
        "workspace_id": workspace_id,
        "question": "What is current?",
        "evaluated_at": "2026-08-07T12:00:00Z",
    }
    body = json.dumps(payload, separators=(",", ":")).encode()
    question = "What is current?"
    app.dependency_overrides[planner_dependency] = lambda: FixedRetrievalPlanner(
        RetrievalPlan(
            retrieval_queries=(question,),
            temporal_mode=TemporalMode.CURRENT,
        )
    )
    try:
        client = TestClient(app)
        headers = signed_headers(path, body, workspace_id, request_id, "retrieval.plan")
        response = client.post(path, content=body, headers=headers)
        replay = client.post(path, content=body, headers=headers)
    finally:
        app.dependency_overrides.clear()

    assert response.status_code == 200
    assert response.json()["contract_version"] == 2
    assert response.json()["plan"]["temporal_mode"] == "current"
    assert response.json()["classifier_lineage"]["provider"] == "deterministic"
    assert replay.status_code == 401


def test_search_endpoint_is_purpose_isolated_and_returns_typed_empty_result() -> None:
    workspace_id = str(uuid4())
    request_id = str(uuid4())
    embedding_generation_id = str(uuid4())
    path = "/api/internal/retrieval/search"
    profile = {
        "provider": "test",
        "model": "deterministic",
        "dimensions": 3,
        "output_dtype": "float",
        "document_input_type": "document",
        "query_input_type": "query",
        "normalisation": "unit_length",
        "truncation": False,
        "model_revision": None,
        "adapter_version": "1",
    }
    from app.embedding.models import EmbeddingProfile

    fingerprint = EmbeddingProfile.model_validate(profile).fingerprint()
    payload = {
        "contract_version": 1,
        "request_id": request_id,
        "workspace_id": workspace_id,
        "query": "Policy",
        "embedding_profile": profile,
        "embedding_profile_fingerprint": fingerprint,
        "vector_space": {
            "collection_name": "test",
            "embedding_space_generation_id": embedding_generation_id,
            "profile_fingerprint": fingerprint,
            "vector_name": "dense",
            "dimensions": 3,
            "distance": "cosine",
        },
        "workspace_corpus_generation_id": str(uuid4()),
        "candidate_k": 5,
        "scopes": [{"side": "primary", "eligible_document_ids": [str(uuid4())]}],
    }
    body = json.dumps(payload, separators=(",", ":")).encode()
    app.dependency_overrides[retriever_dependency] = EmptyRetriever
    try:
        client = TestClient(app)
        wrong = client.post(
            path,
            content=body,
            headers=signed_headers(
                path, body, workspace_id, request_id, "retrieval.plan"
            ),
        )
        fresh_request_id = str(uuid4())
        payload["request_id"] = fresh_request_id
        fresh_body = json.dumps(payload, separators=(",", ":")).encode()
        valid = client.post(
            path,
            content=fresh_body,
            headers=signed_headers(
                path, fresh_body, workspace_id, fresh_request_id, "retrieval.search"
            ),
        )
    finally:
        app.dependency_overrides.clear()

    assert wrong.status_code == 401
    assert valid.status_code == 200
    assert valid.json()["candidates"] == []


def test_rerank_endpoint_has_its_own_purpose_and_returns_provider_lineage() -> None:
    workspace_id = str(uuid4())
    request_id = str(uuid4())
    path = "/api/internal/retrieval/rerank"
    payload = {
        "contract_version": 1,
        "request_id": request_id,
        "workspace_id": workspace_id,
        "query": "Which policy applies?",
        "profile": {
            "provider": "deterministic-fake",
            "model": "deterministic-reranker",
            "adapter_version": "1",
            "truncation": False,
        },
        "candidates": [
            {
                "chunk_id": str(uuid4()),
                "document_id": str(uuid4()),
                "document_family_id": str(uuid4()),
                "version_position": 1,
                "side": "primary",
                "text": "Canonical text supplied by Laravel.",
                "fused_score": 0.5,
                "fused_rank": 1,
            }
        ],
        "top_k": 1,
    }
    body = json.dumps(payload, separators=(",", ":")).encode()
    app.dependency_overrides[reranker_dependency] = DeterministicReranker
    try:
        client = TestClient(app)
        wrong = client.post(
            path,
            content=body,
            headers=signed_headers(
                path, body, workspace_id, request_id, "retrieval.search"
            ),
        )
        fresh_request_id = str(uuid4())
        payload["request_id"] = fresh_request_id
        fresh_body = json.dumps(payload, separators=(",", ":")).encode()
        valid = client.post(
            path,
            content=fresh_body,
            headers=signed_headers(
                path,
                fresh_body,
                workspace_id,
                fresh_request_id,
                "retrieval.rerank",
            ),
        )
    finally:
        app.dependency_overrides.clear()

    assert wrong.status_code == 401
    assert valid.status_code == 200
    assert valid.json()["profile"]["provider"] == "deterministic-fake"
    assert valid.json()["candidates"][0]["side"] == "primary"
    assert valid.json()["candidates"][0]["rank"] == 1
