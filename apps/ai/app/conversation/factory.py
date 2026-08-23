from app.conversation.deterministic import CatalogueQueryContextualizer
from app.conversation.openai_adapter import OpenAIQueryContextualizer
from app.conversation.protocol import QueryContextualizer
from app.settings import Settings


def build_contextualizer(settings: Settings) -> QueryContextualizer:
    if settings.contextualiser_provider == "deterministic":
        return CatalogueQueryContextualizer(settings.contextualiser_catalogue_path)
    if settings.contextualiser_provider != "openai":
        raise ValueError("unsupported contextualiser provider")
    return OpenAIQueryContextualizer(
        api_url=settings.contextualiser_api_url,
        api_key=settings.contextualiser_api_key,
        model=settings.contextualiser_model,
        contextualiser_version=(
            f"{settings.contextualiser_contract_version}:"
            f"{settings.contextualiser_prompt_version}:"
            f"{settings.contextualiser_adapter_version}:"
            f"{settings.contextualiser_model}"
        ),
        timeout_seconds=settings.contextualiser_timeout_seconds,
        max_attempts=settings.contextualiser_max_attempts,
    )
