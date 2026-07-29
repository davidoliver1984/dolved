from typing import Protocol

from app.extraction.models import ExtractedDocument, ExtractionContext


class PdfExtractor(Protocol):
    def extract(
        self,
        source: bytes,
        *,
        context: ExtractionContext,
    ) -> ExtractedDocument: ...
