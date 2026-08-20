from app.generation.openai_adapter import OpenAIGenerationProfile, OpenAIGenerator
from app.generation.protocol import Generator
from app.settings import Settings


def build_generator(settings: Settings) -> Generator:
    if settings.generation_provider != "openai":
        raise ValueError("unsupported generation provider")
    profile = OpenAIGenerationProfile(
        provider=settings.generation_provider,
        model=settings.generation_model,
        contract_version=settings.generation_contract_version,
        prompt_version=settings.generation_prompt_version,
        adapter_version=settings.generation_adapter_version,
        reasoning_effort=settings.generation_reasoning_effort,
        max_output_tokens=settings.generation_max_output_tokens,
        context_window_tokens=settings.generation_context_window_tokens,
    )
    return OpenAIGenerator(
        api_key=settings.generation_openai_api_key.get_secret_value(),
        profile=profile,
        timeout_seconds=settings.generation_timeout_seconds,
        max_attempts=settings.generation_max_attempts,
        initial_backoff_seconds=settings.generation_initial_backoff_seconds,
        max_backoff_seconds=settings.generation_max_backoff_seconds,
        input_cost_per_million_tokens_usd=settings.generation_input_cost_per_million_tokens_usd,
        cached_input_cost_per_million_tokens_usd=settings.generation_cached_input_cost_per_million_tokens_usd,
        output_cost_per_million_tokens_usd=settings.generation_output_cost_per_million_tokens_usd,
        pricing_snapshot=settings.generation_pricing_snapshot,
    )
