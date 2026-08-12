from app.embedding.errors import EmbeddingConfigurationError
from app.embedding.models import EmbeddingProfile, EmbeddingRequest, EmbeddingResult
from app.embedding.protocol import Embedder
from app.embedding.voyage import VoyageEmbedder
from app.settings import Settings


def embedding_profile(settings: Settings) -> EmbeddingProfile:
    return EmbeddingProfile(
        provider="voyage",
        model=settings.embedding_model,
        dimensions=settings.embedding_dimensions,
        output_dtype="float",
        document_input_type="document",
        query_input_type="query",
        normalisation="unit_length",
        truncation=False,
        model_revision=None,
        adapter_version="1",
    )


def create_embedder(settings: Settings) -> Embedder:
    if not settings.voyage_api_key.get_secret_value().strip():
        raise EmbeddingConfigurationError(
            "VOYAGE_API_KEY is required to create the real embedding adapter"
        )

    return VoyageEmbedder(
        api_key=settings.voyage_api_key,
        api_url=settings.voyage_api_url,
        timeout_seconds=settings.embedding_timeout_seconds,
        max_attempts=settings.embedding_max_attempts,
        initial_backoff_seconds=settings.embedding_initial_backoff_seconds,
        max_backoff_seconds=settings.embedding_max_backoff_seconds,
        max_provider_cooldown_seconds=settings.embedding_max_provider_cooldown_seconds,
        estimated_cost_per_million_tokens_usd=(
            settings.embedding_estimated_cost_per_million_tokens_usd
        ),
        pricing_snapshot=settings.embedding_pricing_snapshot,
    )


class DeferredEmbedder:
    """Delay provider validation until work actually requires an embedding call."""

    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._embedder: Embedder | None = None

    def embed(self, request: EmbeddingRequest) -> EmbeddingResult:
        if self._embedder is None:
            self._embedder = create_embedder(self._settings)
        return self._embedder.embed(request)


def create_deferred_embedder(settings: Settings) -> Embedder:
    return DeferredEmbedder(settings)
