from app.provider_retry import ProviderCooldown, voyage_provider_cooldown

# Compatibility alias for existing callers; both Voyage adapters share one window.
voyage_embedding_cooldown = voyage_provider_cooldown

__all__ = ["ProviderCooldown", "voyage_embedding_cooldown"]
