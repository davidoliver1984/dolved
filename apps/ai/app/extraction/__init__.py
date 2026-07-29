"""Canonical extraction models and format-specific extractors."""

from app.extraction.errors import ExtractionFailure, ExtractionFailureKind
from app.extraction.models import (
    Element,
    ExtractedDocument,
    ExtractedDocumentMetadata,
    ExtractionContext,
    ExtractionWarning,
    ExtractorIdentity,
    ParagraphElement,
    PdfSourceLocation,
    SourceLocation,
    TableCell,
    TableElement,
    TableRow,
    TextSourceLocation,
    UnknownElement,
)
from app.extraction.pdf import PdfExtractor, PdfPlumberExtractor, create_pdf_extractor
from app.extraction.plain_text import PlainTextExtractor

__all__ = [
    "Element",
    "ExtractedDocument",
    "ExtractedDocumentMetadata",
    "ExtractionContext",
    "ExtractionFailure",
    "ExtractionFailureKind",
    "ExtractionWarning",
    "ExtractorIdentity",
    "ParagraphElement",
    "PdfExtractor",
    "PdfPlumberExtractor",
    "PdfSourceLocation",
    "PlainTextExtractor",
    "SourceLocation",
    "TableCell",
    "TableElement",
    "TableRow",
    "TextSourceLocation",
    "UnknownElement",
    "create_pdf_extractor",
]
