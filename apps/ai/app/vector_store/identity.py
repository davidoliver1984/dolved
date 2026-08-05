from uuid import NAMESPACE_URL, UUID, uuid5

VECTOR_POINT_NAMESPACE = uuid5(NAMESPACE_URL, "https://maketime.ai/vector-point/v1")


def deterministic_point_id(
    *,
    embedding_space_generation_id: UUID,
    workspace_id: UUID,
    workspace_corpus_generation_id: UUID,
    chunk_id: UUID,
) -> UUID:
    """Return the stable V1 identity for one chunk in one searchable corpus."""

    canonical_identity = "\n".join(
        (
            str(embedding_space_generation_id),
            str(workspace_id),
            str(workspace_corpus_generation_id),
            str(chunk_id),
        )
    )
    return uuid5(VECTOR_POINT_NAMESPACE, canonical_identity)
