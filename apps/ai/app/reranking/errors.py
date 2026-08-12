from app.provider_retry import ProviderRetryDelay


class RerankingError(RuntimeError):
    code = "reranking_error"
    retryable = False

    def __init__(
        self,
        message: str,
        *,
        attempts: int = 1,
        provider_status: int | None = None,
        provider_retry_count: int = 0,
        rate_limit_event_count: int = 0,
        retry_delays: tuple[ProviderRetryDelay, ...] = (),
        first_provider_attempt_at: str | None = None,
        first_failure_at: str | None = None,
        final_failure_at: str | None = None,
        total_retry_delay_seconds: float = 0,
        provider_input_tokens: int | None = None,
    ) -> None:
        super().__init__(message)
        self.attempts = attempts
        self.provider_status = provider_status
        self.provider_retry_count = provider_retry_count
        self.rate_limit_event_count = rate_limit_event_count
        self.retry_delays = retry_delays
        self.first_provider_attempt_at = first_provider_attempt_at
        self.first_failure_at = first_failure_at
        self.final_failure_at = final_failure_at
        self.total_retry_delay_seconds = total_retry_delay_seconds
        self.provider_input_tokens = provider_input_tokens


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
        provider_timing_source: str | None = None,
        provider_status: int | None = None,
    ) -> None:
        super().__init__(message, provider_status=provider_status)
        self.retry_after_seconds = retry_after_seconds
        self.provider_timing_source = provider_timing_source


class RerankerTimeoutError(RerankingError):
    code = "timeout"
    retryable = True


class RerankerProviderUnavailableError(RerankingError):
    code = "provider_unavailable"
    retryable = True


class MalformedRerankerResponseError(RerankingError):
    code = "malformed_response"
