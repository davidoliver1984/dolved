import pytest
from pydantic import SecretStr, ValidationError

from app.embedding.errors import EmbeddingConfigurationError
from app.embedding.factory import create_embedder, embedding_profile
from app.embedding.voyage import VoyageEmbedder
from app.settings import Settings


def test_embedding_settings_build_the_profile_and_keep_the_secret_wrapped() -> None:
    settings = Settings(
        voyage_api_key=SecretStr("synthetic-secret"),
        embedding_model="voyage-4-large",
        embedding_dimensions=1024,
    )

    profile = embedding_profile(settings)

    assert profile.model == "voyage-4-large"
    assert profile.dimensions == 1024
    assert profile.truncation is False
    assert settings.voyage_api_key.get_secret_value() == "synthetic-secret"
    assert "synthetic-secret" not in repr(settings)
    assert isinstance(create_embedder(settings), VoyageEmbedder)


def test_real_adapter_factory_requires_a_secret() -> None:
    with pytest.raises(EmbeddingConfigurationError, match="VOYAGE_API_KEY"):
        create_embedder(Settings(voyage_api_key=SecretStr("")))


def test_real_adapter_rejects_invalid_retry_configuration_as_typed_failure() -> None:
    with pytest.raises(EmbeddingConfigurationError, match="backoff"):
        VoyageEmbedder(
            api_key=SecretStr("synthetic-secret"),
            initial_backoff_seconds=2,
            max_backoff_seconds=1,
        )


@pytest.mark.parametrize(
    ("field", "value"),
    [
        ("embedding_dimensions", 0),
        ("embedding_batch_size", 0),
        ("embedding_batch_size", 1001),
        ("embedding_max_attempts", 0),
        ("embedding_timeout_seconds", 0),
    ],
)
def test_embedding_settings_reject_invalid_operational_limits(
    field: str, value: int
) -> None:
    with pytest.raises(ValidationError):
        Settings.model_validate({field: value})
