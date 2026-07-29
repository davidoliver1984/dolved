from app.extraction.pdf.pdfplumber import PdfPlumberExtractor
from app.extraction.pdf.protocol import PdfExtractor


def create_pdf_extractor() -> PdfExtractor:
    return PdfPlumberExtractor()
