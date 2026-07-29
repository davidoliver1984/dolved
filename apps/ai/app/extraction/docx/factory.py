from app.extraction.docx.protocol import DocxExtractor
from app.extraction.docx.python_docx import PythonDocxExtractor


def create_docx_extractor() -> DocxExtractor:
    return PythonDocxExtractor()
