from collections.abc import Iterator
from typing import Protocol

from app.generation.models import (
    GenerationRequest,
    GenerationResult,
    GenerationStreamEvent,
)


class Generator(Protocol):
    def generate(self, request: GenerationRequest) -> GenerationResult: ...


class StreamingGenerator(Generator, Protocol):
    def stream(self, request: GenerationRequest) -> Iterator[GenerationStreamEvent]: ...
