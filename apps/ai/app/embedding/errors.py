class EmbeddingError(Exception):
    """Base class for typed embedding failures."""

    code = "embedding_error"
    retryable = False

    def __init__(self, message: str, *, attempts: int = 1) -> None:
        super().__init__(message)
        self.attempts = attempts


class InvalidEmbeddingInputError(EmbeddingError):
    code = "invalid_input"


class EmbeddingInputTooLargeError(EmbeddingError):
    code = "input_too_large"


class EmbeddingAuthenticationError(EmbeddingError):
    code = "authentication_failed"


class EmbeddingConfigurationError(EmbeddingError):
    code = "configuration_failed"


class EmbeddingRateLimitError(EmbeddingError):
    code = "rate_limited"
    retryable = True

    def __init__(self, retry_after_seconds: float | None = None) -> None:
        super().__init__("The embedding provider rate limit was exceeded.")
        self.retry_after_seconds = retry_after_seconds


class EmbeddingTimeoutError(EmbeddingError):
    code = "timeout"
    retryable = True


class EmbeddingProviderUnavailableError(EmbeddingError):
    code = "provider_unavailable"
    retryable = True


class MalformedEmbeddingResponseError(EmbeddingError):
    code = "malformed_response"


class EmbeddingDimensionMismatchError(EmbeddingError):
    code = "dimension_mismatch"


class EmbeddingProfileMismatchError(EmbeddingError):
    code = "profile_mismatch"
