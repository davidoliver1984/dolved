"""Opt-in test bridge: real ASGI routes, no outbound sockets or live providers.

Run only inside the isolated test AI container. PHP sends synthetic fixture data;
this module is never imported by the production app or used for live evaluation.
"""

import base64
import json
import os
from http.server import BaseHTTPRequestHandler, HTTPServer
from types import SimpleNamespace
from unittest.mock import patch
from uuid import NAMESPACE_URL, UUID, uuid5

import httpx
from fastapi.testclient import TestClient
from pydantic import SecretStr

from app.embedding.fake import DeterministicFakeEmbedder
from app.generation.needs_output import OpenAIGenerationNeedsOutput
from app.generation.openai_adapter import (
    NEEDS_ADAPTER_VERSION,
    NEEDS_CONTRACT_VERSION,
    NEEDS_PROMPT_VERSION,
    OpenAIGenerationProfile,
    OpenAIGenerator,
)
from app.generation.routes import needs_generator_dependency
from app.main import app
from app.reranking.voyage import VoyageReranker
from app.retrieval.needs_planner import StructuredChatNeedsPlanner
from app.retrieval.retriever import DenseRetriever
from app.retrieval.routes import (
    needs_planner_dependency,
    reranker_dependency,
    retriever_dependency,
)
from app.settings import get_settings
from app.sparse.fake import DeterministicSparseEncoder
from app.vector_store.models import VectorPublicationStatus, VectorSearchHit
from tests.test_openai_generation_adapter import response


def execute(value):
    observations = []
    body = json.loads(value["body"])
    fixtures = value["fixtures"]
    if value["path"].endswith("/search"):
        from app.retrieval.models import SearchRequest

        SearchRequest.model_validate(body)

    def planner_transport(request):
        observations.append({"stage": "planner_transport"})
        return httpx.Response(
            200,
            json={
                "choices": [{"message": {"content": json.dumps(fixtures["plan"])}}],
                "usage": {"prompt_tokens": 100, "completion_tokens": 80},
            },
        )

    def rerank_transport(request):
        payload = json.loads(request.content)
        observations.append(
            {"stage": "reranker_transport", "items": len(payload["documents"])}
        )
        return httpx.Response(
            200,
            json={
                "model": payload["model"],
                "data": [
                    {"index": i, "relevance_score": 0.95 - i * 0.01}
                    for i in range(payload["top_k"])
                ],
                "usage": {"total_tokens": 20},
            },
        )

    class Store:
        def hits(self, request, sparse):
            observations.append(
                {
                    "stage": "sparse_search" if sparse else "dense_search",
                    "documents": [str(x) for x in request.scope.document_ids],
                }
            )
            return tuple(
                VectorSearchHit(
                    point_id=uuid5(NAMESPACE_URL, item["chunk_id"]),
                    score=0.8,
                    workspace_id=request.scope.workspace_id,
                    document_id=UUID(item["document_id"]),
                    chunk_id=UUID(item["chunk_id"]),
                    workspace_corpus_generation_id=request.scope.workspace_corpus_generation_id,
                    embedding_space_generation_id=request.scope.vector_space.embedding_space_generation_id,
                    sparse_space_generation_id=request.scope.vector_space.sparse.sparse_space_generation_id
                    if request.scope.vector_space.sparse
                    else None,
                    event_id=uuid5(NAMESPACE_URL, "event:" + item["chunk_id"]),
                    publication_status=VectorPublicationStatus.PUBLISHED,
                )
                for item in fixtures["chunks"]
                if UUID(item["document_id"]) in request.scope.document_ids
                and item["workspace_id"] == str(request.scope.workspace_id)
            )

        def search(self, request):
            return self.hits(request, False)

        def search_sparse(self, request):
            return self.hits(request, True)

    class Responses:
        def parse(self, **kwargs):
            observations.append({"stage": "generation_transport"})
            parts, links, gaps = [], [], []
            for need in body["evidence_needs"]:
                if need["evidence_ids"]:
                    links.append(
                        {
                            "need_id": need["need_id"],
                            "answer_part_indices": [len(parts)],
                            "unsupported_aspect_indices": [],
                        }
                    )
                    parts.append(
                        {
                            "text": "Synthetic supported guidance.",
                            "evidence_ids": need["evidence_ids"],
                        }
                    )
                else:
                    links.append(
                        {
                            "need_id": need["need_id"],
                            "answer_part_indices": [],
                            "unsupported_aspect_indices": [len(gaps)],
                        }
                    )
                    gaps.append("Synthetic missing history.")
            result = OpenAIGenerationNeedsOutput.model_validate(
                {
                    "outcome": "qualified" if gaps else "answered",
                    "answer_parts": parts,
                    "unsupported_aspects": gaps,
                    "insufficiency_reason": None,
                    "aspect_links": links,
                }
            )
            assert kwargs["text_format"] is OpenAIGenerationNeedsOutput
            return response(result)

    planner = StructuredChatNeedsPlanner(
        api_url="https://test.invalid/planner",
        api_key=SecretStr("test-only"),
        provider_name="openai",
        model="gpt-5-mini",
        timeout_seconds=1,
        client=httpx.Client(transport=httpx.MockTransport(planner_transport)),
    )
    retriever = DenseRetriever(
        embedder=DeterministicFakeEmbedder(),
        vector_store=Store(),
        sparse_encoder=DeterministicSparseEncoder(),
        stage_observer=lambda stage: observations.append(
            {
                "stage": "production_fusion",
                "side": stage.side.value,
                "fused": len(stage.fused_candidates or ()),
            }
        ),
    )
    reranker = VoyageReranker(
        api_key=SecretStr("test-only"),
        api_url="https://test.invalid/rerank",
        timeout_seconds=1,
        max_attempts=1,
        initial_backoff_seconds=0,
        max_backoff_seconds=0,
        client=httpx.Client(transport=httpx.MockTransport(rerank_transport)),
    )
    generator = OpenAIGenerator(
        api_key="test-only",
        client=SimpleNamespace(responses=Responses()),
        profile=OpenAIGenerationProfile(
            contract_version=NEEDS_CONTRACT_VERSION,
            prompt_version=NEEDS_PROMPT_VERSION,
            adapter_version=NEEDS_ADAPTER_VERSION,
        ),
    )
    app.dependency_overrides.update(
        {
            needs_planner_dependency: lambda: planner,
            retriever_dependency: lambda: retriever,
            reranker_dependency: lambda: reranker,
            needs_generator_dependency: lambda: generator,
        }
    )
    os.environ["RETRIEVAL_CALLER_HMAC_KEYS"] = json.dumps(
        {"cross-test": base64.b64encode(b"x" * 32).decode()}
    )
    get_settings.cache_clear()
    try:
        with patch(
            "socket.socket.connect",
            side_effect=AssertionError(
                "Outbound socket forbidden in cross-service proof"
            ),
        ):
            result = TestClient(app).post(
                value["path"], content=value["body"], headers=value["headers"]
            )
        return {
            "status": result.status_code,
            "body": result.json(),
            "observations": observations,
        }
    finally:
        app.dependency_overrides.clear()
        get_settings.cache_clear()


class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        try:
            length = int(self.headers["Content-Length"])
            if length > 2_000_000:
                raise ValueError("Test payload too large")
            result = execute(json.loads(self.rfile.read(length)))
            status = 200
        except (ValueError, TypeError, AssertionError, KeyError, RuntimeError) as error:
            result, status = {"error": type(error).__name__, "message": str(error)}, 500
        payload = json.dumps(result).encode()
        self.send_response(status)
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)

    def log_message(self, *_):
        pass


if __name__ == "__main__":
    server = HTTPServer(("0.0.0.0", 0), Handler)
    print(f"BRIDGE_PORT={server.server_port}", flush=True)
    server.serve_forever()
