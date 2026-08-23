from app.settings import Settings
from app.sparse.errors import SparseConfigurationError
from app.sparse.fake import DeterministicSparseEncoder
from app.sparse.fastembed_adapter import FastEmbedSparseEncoder
from app.sparse.models import SparseEmbeddingProfile
from app.sparse.protocol import SparseEncoder


def sparse_embedding_profile(settings: Settings) -> SparseEmbeddingProfile:
    if settings.sparse_embedding_provider == "deterministic":
        return SparseEmbeddingProfile(
            provider="deterministic",
            model="token-hash-sparse-v2",
            tokenizer="lowercase-alphanumeric-v1",
            tokenizer_revision="1",
            output_representation="sparse-index-weight",
            max_input_tokens=settings.sparse_embedding_max_input_tokens,
            document_input_type="document",
            query_input_type="query",
            model_revision="2",
            adapter_version="deterministic-v2",
        )
    return SparseEmbeddingProfile(
        provider="fastembed",
        model=settings.sparse_embedding_model,
        tokenizer=settings.sparse_embedding_tokenizer,
        tokenizer_revision=settings.sparse_embedding_tokenizer_revision,
        output_representation="sparse-index-weight",
        max_input_tokens=settings.sparse_embedding_max_input_tokens,
        document_input_type="document",
        query_input_type="query",
        model_revision=settings.sparse_embedding_model_revision,
        adapter_version="1",
    )


def create_sparse_encoder(settings: Settings) -> SparseEncoder:
    if settings.sparse_embedding_provider == "deterministic":
        return DeterministicSparseEncoder()
    if settings.sparse_embedding_provider != "fastembed":
        raise SparseConfigurationError("unsupported sparse encoding provider")
    return FastEmbedSparseEncoder(
        model_name=settings.sparse_embedding_model,
        model_source_repository=settings.sparse_embedding_source_repository,
        model_revision=settings.sparse_embedding_model_revision,
        cache_dir=settings.sparse_embedding_cache_dir,
        batch_size=settings.sparse_embedding_batch_size,
    )


class DeferredSparseEncoder:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._encoder: SparseEncoder | None = None

    def encode(self, request):  # type: ignore[no-untyped-def]
        if self._encoder is None:
            self._encoder = create_sparse_encoder(self._settings)
        return self._encoder.encode(request)


def create_deferred_sparse_encoder(settings: Settings) -> SparseEncoder:
    return DeferredSparseEncoder(settings)
