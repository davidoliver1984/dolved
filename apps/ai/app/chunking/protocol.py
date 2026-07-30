from typing import Protocol

from app.chunking.models import ChunkingResult
from app.normalisation.models import NormalisedDocument


class ChunkingStrategy(Protocol):
    def chunk(self, document: NormalisedDocument) -> ChunkingResult: ...
