from collections.abc import Callable, Iterable
from typing import Any, Protocol, cast

from app.sparse.errors import (
    InvalidSparseInputError,
    MalformedSparseResponseError,
    SparseInputTooLargeError,
    SparseProfileMismatchError,
    SparseProviderUnavailableError,
)
from app.sparse.models import (
    SparseEncodedVector,
    SparseEncodingPurpose,
    SparseEncodingRequest,
    SparseEncodingResult,
    SparseVector,
)


class FastEmbedVector(Protocol):
    indices: Any
    values: Any


class FastEmbedEngine(Protocol):
    def embed(
        self, documents: list[str], **kwargs: Any
    ) -> Iterable[FastEmbedVector]: ...

    def query_embed(
        self, query: list[str], **kwargs: Any
    ) -> Iterable[FastEmbedVector]: ...


class FastEmbedSparseEncoder:
    def __init__(
        self,
        *,
        model_name: str,
        model_source_repository: str | None = None,
        model_revision: str | None = None,
        cache_dir: str | None = None,
        engine: FastEmbedEngine | None = None,
        token_counter: Callable[[str], int] | None = None,
        batch_size: int = 64,
    ) -> None:
        self._model_name = model_name
        self._model_source_repository = model_source_repository
        self._model_revision = model_revision
        self._cache_dir = cache_dir
        self._engine = engine
        self._token_counter = token_counter
        self._batch_size = batch_size

    def encode(self, request: SparseEncodingRequest) -> SparseEncodingResult:
        if (
            request.profile.provider != "fastembed"
            or request.profile.model != self._model_name
            or (
                self._model_revision is not None
                and request.profile.model_revision != self._model_revision
            )
        ):
            raise SparseProfileMismatchError(
                "sparse profile is incompatible with the FastEmbed adapter"
            )
        engine = self._get_engine()
        counter = self._token_counter or self._engine_token_count(engine)
        for item in request.items:
            token_count = counter(item.text)
            if token_count > request.profile.max_input_tokens:
                raise SparseInputTooLargeError(
                    "sparse input exceeds the profile token bound"
                )

        texts = [item.text for item in request.items]
        try:
            raw = (
                engine.embed(texts, batch_size=self._batch_size)
                if request.purpose is SparseEncodingPurpose.DOCUMENT
                else engine.query_embed(texts)
            )
            vectors = tuple(raw)
        except SparseInputTooLargeError:
            raise
        except (TypeError, ValueError) as exception:
            raise InvalidSparseInputError(
                "sparse provider rejected the input"
            ) from exception
        except Exception as exception:
            raise SparseProviderUnavailableError(
                "sparse provider was unavailable"
            ) from exception
        if len(vectors) != len(request.items):
            raise MalformedSparseResponseError(
                "sparse provider returned the wrong number of vectors"
            )

        encoded: list[SparseEncodedVector] = []
        try:
            for source, vector in zip(request.items, vectors, strict=True):
                indices = tuple(int(index) for index in vector.indices.tolist())
                values = tuple(float(value) for value in vector.values.tolist())
                ordered = sorted(
                    zip(indices, values, strict=True), key=lambda item: item[0]
                )
                encoded.append(
                    SparseEncodedVector(
                        source_id=source.source_id,
                        vector=SparseVector(
                            indices=tuple(index for index, _ in ordered),
                            values=tuple(value for _, value in ordered),
                        ),
                    )
                )
        except (AttributeError, TypeError, ValueError) as exception:
            raise MalformedSparseResponseError(
                "sparse provider returned a malformed vector"
            ) from exception
        return SparseEncodingResult(
            profile=request.profile,
            profile_fingerprint=request.profile.fingerprint(),
            purpose=request.purpose,
            encodings=tuple(encoded),
        )

    def _get_engine(self) -> FastEmbedEngine:
        if self._engine is None:
            from fastembed import SparseTextEmbedding
            from huggingface_hub import snapshot_download

            if self._model_source_repository is None or self._model_revision is None:
                raise SparseProviderUnavailableError(
                    "sparse provider requires a pinned source repository and revision"
                )
            model_path = snapshot_download(
                repo_id=self._model_source_repository,
                revision=self._model_revision,
                cache_dir=self._cache_dir,
            )

            self._engine = cast(
                FastEmbedEngine,
                SparseTextEmbedding(
                    model_name=self._model_name,
                    cache_dir=self._cache_dir,
                    specific_model_path=model_path,
                ),
            )
        return self._engine

    @staticmethod
    def _engine_token_count(engine: FastEmbedEngine) -> Callable[[str], int]:
        model = getattr(engine, "model", None)
        token_count = getattr(model, "token_count", None)
        if not callable(token_count):
            raise SparseProviderUnavailableError(
                "sparse provider does not expose deterministic token counting"
            )
        return lambda text: int(token_count(text))
