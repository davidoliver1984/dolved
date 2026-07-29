from app.extraction.docx.factory import create_docx_extractor
from app.extraction.docx.protocol import DocxExtractor
from app.extraction.docx.python_docx import PythonDocxExtractor

__all__ = [
    "DocxExtractor",
    "PythonDocxExtractor",
    "create_docx_extractor",
]
