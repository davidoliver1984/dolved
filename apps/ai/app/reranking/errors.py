class RerankingError(RuntimeError):
    code = "reranking_error"
    retryable = False

    def __init__(self, message: str, *, attempts: int = 1) -> None:
        super().__init__(message)
        self.attempts = attempts


class RerankerConfigurationError(RerankingError):
    code = "configuration_error"


class InvalidRerankerInputError(RerankingError):
    code = "invalid_input"


class RerankerInputTooLargeError(RerankingError):
    code = "input_too_large"


class RerankerAuthenticationError(RerankingError):
    code = "authentication_error"


class RerankerRateLimitError(RerankingError):
    code = "rate_limited"
    retryable = True

    def __init__(
        self,
        message: str = "reranker rate limited",
        *,
        retry_after_seconds: float | None = None,
    ) -> None:
        super().__init__(message)
        self.retry_after_seconds = retry_after_seconds


class RerankerTimeoutError(RerankingError):
    code = "timeout"
    retryable = True


class RerankerProviderUnavailableError(RerankingError):
    code = "provider_unavailable"
    retryable = True


class MalformedRerankerResponseError(RerankingError):
    code = "malformed_response"
