"""Canonical extraction models and format-specific extractors."""

from app.extraction.docx import (
    DocxExtractor,
    PythonDocxExtractor,
    create_docx_extractor,
)
from app.extraction.errors import ExtractionFailure, ExtractionFailureKind
from app.extraction.models import (
    DocxSourceLocation,
    Element,
    ExtractedDocument,
    ExtractedDocumentMetadata,
    ExtractionContext,
    ExtractionWarning,
    ExtractorIdentity,
    HeadingElement,
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
    "DocxExtractor",
    "DocxSourceLocation",
    "Element",
    "ExtractedDocument",
    "ExtractedDocumentMetadata",
    "ExtractionContext",
    "ExtractionFailure",
    "ExtractionFailureKind",
    "ExtractionWarning",
    "ExtractorIdentity",
    "HeadingElement",
    "ParagraphElement",
    "PdfExtractor",
    "PdfPlumberExtractor",
    "PdfSourceLocation",
    "PlainTextExtractor",
    "PythonDocxExtractor",
    "SourceLocation",
    "TableCell",
    "TableElement",
    "TableRow",
    "TextSourceLocation",
    "UnknownElement",
    "create_docx_extractor",
    "create_pdf_extractor",
]
