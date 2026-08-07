from uuid import NAMESPACE_URL, uuid5

from opentelemetry import trace
from opentelemetry.trace import SpanKind

from app.embedding.models import EmbeddingInput, EmbeddingPurpose, EmbeddingRequest
from app.embedding.protocol import Embedder
from app.retrieval.models import (
    RetrievalCandidate,
    SearchRequest,
    SearchResponse,
)
from app.telemetry import trace_attributes
from app.vector_store.models import VectorScope, VectorSearchRequest
from app.vector_store.protocol import VectorStore


class DenseRetriever:
    def __init__(self, *, embedder: Embedder, vector_store: VectorStore) -> None:
        self._embedder = embedder
        self._vector_store = vector_store

    def search(self, request: SearchRequest) -> SearchResponse:
        source_id = uuid5(NAMESPACE_URL, f"retrieval-query:{request.request_id}")
        embedded = self._embedder.embed(
            EmbeddingRequest(
                correlation_id=request.request_id,
                workspace_id=request.workspace_id,
                profile=request.embedding_profile,
                purpose=EmbeddingPurpose.QUERY,
                items=(EmbeddingInput(source_id=source_id, text=request.query),),
            )
        )
        if embedded.profile_fingerprint != request.embedding_profile_fingerprint:
            raise ValueError("query embedding profile is incompatible with the corpus")
        query_vector = embedded.embeddings[0].values
        candidates: list[RetrievalCandidate] = []
        for scope in request.scopes:
            vector_scope = VectorScope(
                vector_space=request.vector_space,
                workspace_id=request.workspace_id,
                workspace_corpus_generation_id=(request.workspace_corpus_generation_id),
                document_ids=scope.eligible_document_ids,
            )
            with trace.get_tracer("maketime.python.retrieval").start_as_current_span(
                "search eligible vectors",
                kind=SpanKind.INTERNAL,
                attributes=trace_attributes(
                    {
                        "rag.workspace.id": str(request.workspace_id),
                        "rag.retrieval.method": "dense",
                        "rag.retrieval.side": scope.side.value,
                        "rag.retrieval.eligible_scope_size": len(
                            scope.eligible_document_ids
                        ),
                    }
                ),
            ) as span:
                hits = self._vector_store.search(
                    VectorSearchRequest(
                        scope=vector_scope,
                        query_vector=query_vector,
                        limit=request.candidate_k,
                    )
                )
                span.set_attributes(
                    trace_attributes({"rag.retrieval.candidate_count": len(hits)})
                )
            for rank, hit in enumerate(hits, start=1):
                candidates.append(
                    RetrievalCandidate(
                        chunk_id=hit.chunk_id,
                        document_id=hit.document_id,
                        workspace_corpus_generation_id=(
                            hit.workspace_corpus_generation_id
                        ),
                        embedding_space_generation_id=(
                            hit.embedding_space_generation_id
                        ),
                        score=hit.score,
                        rank=rank,
                        side=scope.side,
                    )
                )
        return SearchResponse(
            request_id=request.request_id, candidates=tuple(candidates)
        )
