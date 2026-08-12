from app.reranking.errors import RerankerConfigurationError
from app.reranking.models import RerankerProfile, RerankRequest, RerankResult
from app.reranking.protocol import Reranker
from app.reranking.voyage import VoyageReranker
from app.settings import Settings


def reranker_profile(settings: Settings) -> RerankerProfile:
    return RerankerProfile(
        provider="voyage",
        model=settings.reranker_model,
        adapter_version="1",
        truncation=False,
    )


def create_reranker(settings: Settings) -> Reranker:
    if not settings.voyage_api_key.get_secret_value().strip():
        raise RerankerConfigurationError(
            "VOYAGE_API_KEY is required to create the real reranker"
        )
    return VoyageReranker(
        api_key=settings.voyage_api_key,
        api_url=settings.voyage_rerank_api_url,
        timeout_seconds=settings.reranker_timeout_seconds,
        max_attempts=settings.reranker_max_attempts,
        initial_backoff_seconds=settings.reranker_initial_backoff_seconds,
        max_backoff_seconds=settings.reranker_max_backoff_seconds,
        max_provider_cooldown_seconds=(settings.reranker_max_provider_cooldown_seconds),
    )


class DeferredReranker:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._reranker: Reranker | None = None

    def rerank(self, request: RerankRequest) -> RerankResult:
        if self._reranker is None:
            self._reranker = create_reranker(self._settings)
        return self._reranker.rerank(request)


def create_deferred_reranker(settings: Settings) -> Reranker:
    return DeferredReranker(settings)
