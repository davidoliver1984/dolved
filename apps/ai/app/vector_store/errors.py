from uuid import UUID


class VectorStoreError(Exception):
    """Base class for provider-neutral vector-store failures."""

    code = "vector_store_error"
    retryable = False


class VectorStoreConfigurationError(VectorStoreError):
    code = "configuration_failed"


class VectorSpaceCompatibilityError(VectorStoreError):
    code = "vector_space_incompatible"


class VectorStoreUnavailableError(VectorStoreError):
    code = "unavailable"
    retryable = True


class VectorStoreMalformedResponseError(VectorStoreError):
    code = "malformed_response"


class VectorStorePartialWriteError(VectorStoreError):
    code = "partial_write"
    retryable = True

    def __init__(self, persisted_point_ids: tuple[UUID, ...]) -> None:
        super().__init__(
            "A vector upsert failed after one or more bounded batches completed."
        )
        self.persisted_point_ids = persisted_point_ids
