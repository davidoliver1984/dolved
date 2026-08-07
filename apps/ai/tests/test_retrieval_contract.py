import json
from datetime import UTC, datetime
from pathlib import Path
from uuid import uuid4

from jsonschema import Draft202012Validator, FormatChecker

from app.embedding.models import V1_VOYAGE_PROFILE
from app.retrieval.models import PlanRequest, SearchRequest, SearchScope
from app.vector_store.models import VectorSpace

CONTRACT = Path("/contracts/http/retrieval-call/rc1")


def validate(name: str, payload: dict[str, object]) -> None:
    schema = json.loads((CONTRACT / name).read_text())
    Draft202012Validator(schema, format_checker=FormatChecker()).validate(payload)


def test_python_request_models_match_both_shared_rc1_schemas() -> None:
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
    validate("search-v1.schema.json", search.model_dump(mode="json"))
