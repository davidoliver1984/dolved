from typing import Protocol

from app.generation.models import GenerationRequest, GenerationResult


class Generator(Protocol):
    def generate(self, request: GenerationRequest) -> GenerationResult: ...
