from uuid import UUID, uuid4

import pytest
from pydantic import ValidationError

from app.vector_store.identity import deterministic_point_id
from app.vector_store.models import (
    VectorCompletenessRequest,
    VectorPoint,
    VectorPointIdentity,
    VectorPublicationStatus,
    VectorScope,
    VectorSearchRequest,
    VectorSpace,
    VectorUpsertRequest,
)


def vector_space(*, dimensions: int = 3) -> VectorSpace:
    return VectorSpace(
        collection_name="embedding-space-test",
        embedding_space_generation_id=uuid4(),
        profile_fingerprint="profile-fingerprint",
        dimensions=dimensions,
    )


def point(space: VectorSpace, *, chunk_id: UUID | None = None) -> VectorPoint:
    return VectorPoint(
        workspace_id=uuid4(),
        document_id=uuid4(),
        chunk_id=chunk_id or uuid4(),
        workspace_corpus_generation_id=uuid4(),
        embedding_space_generation_id=space.embedding_space_generation_id,
        event_id=uuid4(),
        publication_status=VectorPublicationStatus.PROVISIONAL,
        values=(1.0, 0.0, 0.0),
    )


def test_point_identity_is_stable_and_uses_every_required_identity() -> None:
    embedding_space_generation_id = UUID("00000000-0000-0000-0000-000000000001")
    workspace_id = UUID("00000000-0000-0000-0000-000000000002")
    workspace_corpus_generation_id = UUID("00000000-0000-0000-0000-000000000003")
    chunk_id = UUID("00000000-0000-0000-0000-000000000004")

    point_id = deterministic_point_id(
        embedding_space_generation_id=embedding_space_generation_id,
        workspace_id=workspace_id,
        workspace_corpus_generation_id=workspace_corpus_generation_id,
        chunk_id=chunk_id,
    )

    assert point_id == UUID("94db526e-b47e-5b7b-b5aa-340537a86734")
    assert point_id.version == 5
    assert point_id == deterministic_point_id(
        embedding_space_generation_id=embedding_space_generation_id,
        workspace_id=workspace_id,
        workspace_corpus_generation_id=workspace_corpus_generation_id,
        chunk_id=chunk_id,
    )
    assert point_id != deterministic_point_id(
        embedding_space_generation_id=embedding_space_generation_id,
        workspace_id=uuid4(),
        workspace_corpus_generation_id=workspace_corpus_generation_id,
        chunk_id=chunk_id,
    )


def test_upsert_rejects_wrong_dimensions_generation_and_duplicate_identity() -> None:
    space = vector_space()
    valid = point(space)

    with pytest.raises(ValidationError, match="dimensions"):
        VectorUpsertRequest(
            vector_space=space,
            points=(valid.model_copy(update={"values": (1.0, 0.0)}),),
        )

    with pytest.raises(ValidationError, match="embedding-space generation"):
        VectorUpsertRequest(
            vector_space=space,
            points=(
                valid.model_copy(update={"embedding_space_generation_id": uuid4()}),
            ),
        )

    with pytest.raises(ValidationError, match="must be unique"):
        VectorUpsertRequest(vector_space=space, points=(valid, valid))


def test_vectors_reject_non_finite_values_and_invalid_search_dimensions() -> None:
    space = vector_space()
    valid = point(space)

    with pytest.raises(ValidationError, match="finite"):
        VectorPoint(
            **valid.model_dump(exclude={"values"}),
            values=(float("nan"), 0.0, 0.0),
        )

    scope = VectorScope(
        vector_space=space,
        workspace_id=uuid4(),
        workspace_corpus_generation_id=uuid4(),
    )
    with pytest.raises(ValidationError, match="dimensions"):
        VectorSearchRequest(scope=scope, query_vector=(1.0, 0.0), limit=10)


def test_vector_scope_requires_one_unambiguous_document_filter_shape() -> None:
    space = vector_space()
    document_id = uuid4()
    with pytest.raises(ValidationError, match="mutually exclusive"):
        VectorScope(
            vector_space=space,
            workspace_id=uuid4(),
            workspace_corpus_generation_id=uuid4(),
            document_id=document_id,
            document_ids=(document_id,),
        )

    with pytest.raises(ValidationError, match="must be unique"):
        VectorScope(
            vector_space=space,
            workspace_id=uuid4(),
            workspace_corpus_generation_id=uuid4(),
            document_ids=(document_id, document_id),
        )


def test_completeness_requires_every_expected_identity_to_match_scope() -> None:
    space = vector_space()
    workspace_id = uuid4()
    corpus_generation_id = uuid4()
    scope = VectorScope(
        vector_space=space,
        workspace_id=workspace_id,
        workspace_corpus_generation_id=corpus_generation_id,
    )
    expected = VectorPointIdentity(
        workspace_id=uuid4(),
        document_id=uuid4(),
        chunk_id=uuid4(),
        workspace_corpus_generation_id=corpus_generation_id,
        embedding_space_generation_id=space.embedding_space_generation_id,
        event_id=uuid4(),
        publication_status=VectorPublicationStatus.PROVISIONAL,
    )

    with pytest.raises(ValidationError, match="workspace does not match"):
        VectorCompletenessRequest(scope=scope, expected_points=(expected,))
