import json
from datetime import UTC, datetime
from pathlib import Path
from uuid import uuid4

from jsonschema import Draft202012Validator, FormatChecker

from app.conversation.models import (
    ContextualisationRequest,
    ContextualisationResponse,
    ContextualisationResult,
    InterpretationMetadata,
)
from app.embedding.models import V1_VOYAGE_PROFILE
from app.reranking.models import RerankCandidate, RerankerProfile, RerankRequest
from app.retrieval.corpus_rebuild import (
    CorpusPointIdentity,
    CorpusRebuildBatchRequest,
    CorpusRebuildChunk,
    CorpusVerificationRequest,
)
from app.retrieval.models import (
    OperationUsage,
    PlannerLineage,
    PlanRequest,
    PlanResponse,
    RetrievalPlan,
    RetrievalSide,
    SearchRequest,
    SearchScope,
    TemporalMode,
)
from app.sparse.models import SparseEmbeddingProfile
from app.vector_store.models import SparseVectorSpace, VectorSpace

CONTRACT = Path("/contracts/http/retrieval-call/rc1")


def validate(name: str, payload: dict[str, object]) -> None:
    schema = json.loads((CONTRACT / name).read_text())
    Draft202012Validator(schema, format_checker=FormatChecker()).validate(payload)


def test_python_request_models_match_shared_rc1_schemas() -> None:
    workspace_id = uuid4()
    plan = PlanRequest(
        contract_version=1,
        request_id=uuid4(),
        workspace_id=workspace_id,
        question="What is current?",
        evaluated_at=datetime(2026, 8, 7, 12, tzinfo=UTC),
    )
    vector_space = VectorSpace(
        collection_name="rag-platform-vectors-v1",
        embedding_space_generation_id=uuid4(),
        profile_fingerprint=V1_VOYAGE_PROFILE.fingerprint(),
        dimensions=V1_VOYAGE_PROFILE.dimensions,
    )
    search = SearchRequest(
        contract_version=1,
        request_id=uuid4(),
        workspace_id=workspace_id,
        query="What is current?",
        embedding_profile=V1_VOYAGE_PROFILE,
        embedding_profile_fingerprint=V1_VOYAGE_PROFILE.fingerprint(),
        vector_space=vector_space,
        workspace_corpus_generation_id=uuid4(),
        candidate_k=10,
        scopes=(SearchScope(eligible_document_ids=(uuid4(),)),),
    )

    validate("plan-v1.schema.json", plan.model_dump(mode="json"))
    response = PlanResponse(
        request_id=plan.request_id,
        plan=RetrievalPlan(
            retrieval_queries=(plan.question,),
            temporal_mode=TemporalMode.CURRENT,
        ),
        classifier_lineage=PlannerLineage(
            provider="deterministic",
            model="fixed",
            contract_schema_version="plan-response-v2",
            prompt_version="fixed-v1",
            adapter_version="fixed-v1",
            fingerprint="a" * 64,
        ),
        usage=OperationUsage(
            stage="planner",
            provider="deterministic",
            model="fixed",
            execution="local",
            request_count=1,
            retry_count=0,
            latency_ms=0,
            cost_basis="zero_cost_local",
            cost_usd=0,
        ),
    )
    validate("plan-response-v2.schema.json", response.model_dump(mode="json"))
    validate("search-v1.schema.json", search.model_dump(mode="json"))

    contextualisation = ContextualisationRequest(
        request_id=uuid4(),
        workspace_id=workspace_id,
        current_message="What about the current version?",
        history=(),
        context_policy_version="bounded-completed-turns-v1",
    )
    validate(
        "conversation-contextualize-v1.schema.json",
        contextualisation.model_dump(mode="json"),
    )
    contextualisation_response = ContextualisationResponse(
        request_id=contextualisation.request_id,
        result=ContextualisationResult(
            status="resolved",
            resolved_query=contextualisation.current_message,
            used_prior_context=False,
            interpretation_metadata=InterpretationMetadata(used_turn_ordinals=()),
            clarification_question=None,
            contextualiser_version="conversation-context-v1",
            usage={"execution": "deterministic", "request_count": 0},
        ),
    )
    validate(
        "conversation-contextualize-response-v1.schema.json",
        contextualisation_response.model_dump(mode="json"),
    )

    rerank = RerankRequest(
        request_id=uuid4(),
        workspace_id=workspace_id,
        query="What is current?",
        profile=RerankerProfile(
            provider="voyage",
            model="rerank-2.5",
            adapter_version="1",
        ),
        candidates=(
            RerankCandidate(
                chunk_id=uuid4(),
                document_id=uuid4(),
                document_family_id=uuid4(),
                version_position=1,
                side=RetrievalSide.PRIMARY,
                document_title="Operations Policy v1",
                document_family_title="Operations Policy",
                text="Canonical policy text.",
                fused_score=0.04,
                fused_rank=1,
            ),
        ),
        top_k=1,
    )
    validate("rerank-v1.schema.json", rerank.model_dump(mode="json"))

    sparse_profile = SparseEmbeddingProfile(
        provider="test",
        model="sparse",
        tokenizer="test",
        max_input_tokens=100,
        adapter_version="1",
    )
    sparse_space = SparseVectorSpace(
        sparse_space_generation_id=uuid4(),
        profile_fingerprint=sparse_profile.fingerprint(),
    )
    hybrid_space = vector_space.model_copy(update={"sparse": sparse_space})
    chunk = CorpusRebuildChunk(
        chunk_id=uuid4(), document_id=uuid4(), text="Canonical policy text."
    )
    rebuild = CorpusRebuildBatchRequest(
        request_id=uuid4(),
        workspace_id=workspace_id,
        rebuild_event_id=uuid4(),
        embedding_profile=V1_VOYAGE_PROFILE,
        sparse_embedding_profile=sparse_profile,
        vector_space=hybrid_space,
        workspace_corpus_generation_id=uuid4(),
        chunks=(chunk,),
    )
    validate("corpus-rebuild-batch-v1.schema.json", rebuild.model_dump(mode="json"))
    verification = CorpusVerificationRequest(
        request_id=uuid4(),
        workspace_id=workspace_id,
        vector_space=hybrid_space,
        workspace_corpus_generation_id=rebuild.workspace_corpus_generation_id,
        points=(
            CorpusPointIdentity(
                chunk_id=chunk.chunk_id,
                document_id=chunk.document_id,
                event_id=rebuild.rebuild_event_id,
            ),
        ),
    )
    validate("corpus-verify-v1.schema.json", verification.model_dump(mode="json"))
