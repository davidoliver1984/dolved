"""Canonical extraction models and format-specific extractors."""

from app.extraction.errors import ExtractionFailure, ExtractionFailureKind
from app.extraction.models import (
    Element,
    ExtractedDocument,
    ExtractionContext,
    ExtractionWarning,
    ExtractorIdentity,
    ParagraphElement,
    SourceLocation,
    TextSourceLocation,
    UnknownElement,
)
from app.extraction.plain_text import PlainTextExtractor

__all__ = [
    "Element",
    "ExtractedDocument",
    "ExtractionContext",
    "ExtractionFailure",
    "ExtractionFailureKind",
    "ExtractionWarning",
    "ExtractorIdentity",
    "ParagraphElement",
    "PlainTextExtractor",
    "SourceLocation",
    "TextSourceLocation",
    "UnknownElement",
]
