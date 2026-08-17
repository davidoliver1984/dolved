from app.generation.models import (
    GenerationContextBudgetExceeded,
    GenerationProviderError,
)


class GenerationContextBudgetError(Exception):
    def __init__(self, failure: GenerationContextBudgetExceeded) -> None:
        super().__init__(failure.code)
        self.failure = failure


class GenerationProviderFailure(Exception):
    def __init__(self, error: GenerationProviderError) -> None:
        super().__init__(error.category)
        self.error = error
