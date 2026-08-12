class EmbeddingError(Exception):
    """Base class for typed embedding failures."""

    code = "embedding_error"
    retryable = False

    def __init__(
        self,
        message: str,
        *,
        attempts: int = 1,
        provider_status: int | None = None,
        first_failure_at: str | None = None,
        final_failure_at: str | None = None,
        total_retry_delay_seconds: float = 0.0,
    ) -> None:
        super().__init__(message)
        self.attempts = attempts
        self.provider_status = provider_status
        self.first_failure_at = first_failure_at
        self.final_failure_at = final_failure_at
        self.total_retry_delay_seconds = total_retry_delay_seconds


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

    def __init__(
        self,
        retry_after_seconds: float | None = None,
        *,
        provider_timing_source: str | None = None,
        provider_status: int | None = None,
    ) -> None:
        super().__init__(
            "The embedding provider rate limit was exceeded.",
            provider_status=provider_status,
        )
        self.retry_after_seconds = retry_after_seconds
        self.provider_timing_source = provider_timing_source


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
