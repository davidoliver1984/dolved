from collections.abc import Sequence
from dataclasses import dataclass
from io import BytesIO

from PIL import Image
from reportlab.lib.pagesizes import letter
from reportlab.lib.pdfencrypt import StandardEncryption
from reportlab.pdfgen.canvas import Canvas

PAGE_WIDTH, PAGE_HEIGHT = letter


@dataclass(frozen=True)
class PositionedText:
    text: str
    x: float
    y: float


def text_pdf(
    pages: Sequence[Sequence[PositionedText]],
    *,
    title: str | None = None,
    author: str | None = None,
    subject: str | None = None,
    keywords: str | None = None,
    rotation: int = 0,
    crop_box: tuple[float, float, float, float] | None = None,
) -> bytes:
    buffer = BytesIO()
    canvas = Canvas(
        buffer,
        pagesize=letter,
        invariant=True,
        cropBox=crop_box,
    )

    if title is not None:
        canvas.setTitle(title)
    if author is not None:
        canvas.setAuthor(author)
    if subject is not None:
        canvas.setSubject(subject)
    if keywords is not None:
        canvas.setKeywords(keywords)

    for page_index, entries in enumerate(pages):
        if page_index > 0:
            canvas.showPage()
        if rotation:
            canvas.setPageRotation(rotation)

        for entry in entries:
            canvas.drawString(entry.x, entry.y, entry.text)

    canvas.save()
    return buffer.getvalue()


def table_pdf(*, ambiguous_overlap: bool = False) -> bytes:
    buffer = BytesIO()
    canvas = Canvas(buffer, pagesize=letter, invariant=True)
    left = 72.0
    top = 650.0
    row_height = 30.0
    column_widths = (140.0, 260.0)
    rows = (
        ("Service", "Responsibility"),
        ("Laravel", "Lifecycle authority"),
        ("Python", "Extraction"),
    )
    right = left + sum(column_widths)
    bottom = top - row_height * len(rows)

    for row_index in range(len(rows) + 1):
        y = top - row_index * row_height
        canvas.line(left, y, right, y)

    x = left
    canvas.line(x, top, x, bottom)
    for width in column_widths:
        x += width
        canvas.line(x, top, x, bottom)

    for row_index, row in enumerate(rows):
        y = top - row_index * row_height - 20
        canvas.drawString(left + 8, y, row[0])
        canvas.drawString(left + column_widths[0] + 8, y, row[1])

    if ambiguous_overlap:
        canvas.drawString(left - 55, top - 20, "Ambiguous caption")

    canvas.save()
    return buffer.getvalue()


def image_only_pdf() -> bytes:
    buffer = BytesIO()
    canvas = Canvas(buffer, pagesize=letter, invariant=True)
    image = Image.new("RGB", (40, 20), color="black")
    canvas.drawInlineImage(image, 72, 650, width=120, height=60)
    canvas.save()
    return buffer.getvalue()


def mixed_text_and_image_pdf() -> bytes:
    buffer = BytesIO()
    canvas = Canvas(buffer, pagesize=letter, invariant=True)
    canvas.drawString(72, 700, "Extractable first page")
    canvas.showPage()
    image = Image.new("RGB", (40, 20), color="black")
    canvas.drawInlineImage(image, 72, 650, width=120, height=60)
    canvas.save()
    return buffer.getvalue()


def encrypted_pdf() -> bytes:
    buffer = BytesIO()
    encryption = StandardEncryption(
        "correct horse battery staple",
        ownerPassword="owner secret",
        canPrint=0,
        strength=128,
    )
    canvas = Canvas(
        buffer,
        pagesize=letter,
        invariant=True,
        encrypt=encryption,
    )
    canvas.drawString(72, 700, "Protected content")
    canvas.save()
    return buffer.getvalue()
