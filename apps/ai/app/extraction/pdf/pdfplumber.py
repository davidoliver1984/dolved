from collections.abc import Mapping, Sequence
from dataclasses import dataclass
from io import BytesIO
from typing import Any, Literal, cast

import pdfplumber
from pdfminer.pdfdocument import PDFPasswordIncorrect
from pdfminer.pdfexceptions import PDFException
from pdfminer.psparser import PSException
from pdfplumber.utils.exceptions import PdfminerException

from app.extraction.errors import ExtractionFailure, ExtractionFailureKind
from app.extraction.limits import DEFAULT_MAX_SOURCE_BYTES
from app.extraction.models import (
    Element,
    ExtractedDocument,
    ExtractedDocumentMetadata,
    ExtractionContext,
    ExtractionWarning,
    ExtractorIdentity,
    ParagraphElement,
    PdfSourceLocation,
    TableCell,
    TableElement,
    TableRow,
)

DEFAULT_MAX_PAGES = 500
DEFAULT_MAX_EXTRACTED_CHARACTERS = 5_000_000
ELEMENT_SEPARATOR = "\n\n"
PAGE_SEPARATOR = "\n\n\f\n\n"
_TABLE_SETTINGS = {
    "vertical_strategy": "lines",
    "horizontal_strategy": "lines",
}
_BBOX_TOLERANCE = 0.01

_BoundingBox = tuple[float, float, float, float]


@dataclass(frozen=True)
class _PageGeometry:
    page_number: int
    width: float
    height: float
    rotation_degrees: Literal[0, 90, 180, 270]
    has_distinct_crop_box: bool
    origin_x: float
    origin_top: float


@dataclass(frozen=True)
class _RawCell:
    row_index: int
    column_index: int
    text: str
    bounding_box: _BoundingBox | None


@dataclass(frozen=True)
class _RawBlock:
    kind: Literal["paragraph", "table"]
    text: str
    geometry: _PageGeometry
    bounding_box: _BoundingBox
    parser_order: int
    rows: tuple[tuple[_RawCell, ...], ...] = ()


@dataclass(frozen=True)
class _PageExtraction:
    blocks: tuple[_RawBlock, ...]
    warnings: tuple[ExtractionWarning, ...]
    has_images: bool


class PdfPlumberExtractor:
    def __init__(
        self,
        *,
        max_source_bytes: int = DEFAULT_MAX_SOURCE_BYTES,
        max_pages: int = DEFAULT_MAX_PAGES,
        max_extracted_characters: int = DEFAULT_MAX_EXTRACTED_CHARACTERS,
    ) -> None:
        if max_source_bytes < 1:
            raise ValueError("max_source_bytes must be at least one")

        if max_pages < 1:
            raise ValueError("max_pages must be at least one")

        if max_extracted_characters < 1:
            raise ValueError("max_extracted_characters must be at least one")

        self._max_source_bytes = max_source_bytes
        self._max_pages = max_pages
        self._max_extracted_characters = max_extracted_characters

    def extract(
        self,
        source: bytes,
        *,
        context: ExtractionContext,
    ) -> ExtractedDocument:
        self._validate_source_size(source)

        try:
            with pdfplumber.open(BytesIO(source), laparams={}) as pdf:
                if len(pdf.pages) > self._max_pages:
                    self._raise_permanent(
                        code="too_many_pages",
                        message="The PDF exceeds the supported page limit.",
                    )

                metadata = self._metadata(pdf.metadata)
                pages = tuple(self._extract_page(page) for page in pdf.pages)
        except PdfminerException as exception:
            parser_exception = exception.args[0] if exception.args else exception
            if isinstance(parser_exception, PDFPasswordIncorrect):
                self._raise_encrypted_pdf(exception)

            raise ExtractionFailure(
                kind=ExtractionFailureKind.PERMANENT,
                code="invalid_pdf",
                user_message=(
                    "The PDF could not be read. Please export a valid PDF and retry."
                ),
            ) from exception
        except PDFPasswordIncorrect as exception:
            self._raise_encrypted_pdf(exception)
        except (PDFException, PSException) as exception:
            raise ExtractionFailure(
                kind=ExtractionFailureKind.PERMANENT,
                code="invalid_pdf",
                user_message=(
                    "The PDF could not be read. Please export a valid PDF and retry."
                ),
            ) from exception

        blocks = tuple(block for page in pages for block in page.blocks)
        warnings = tuple(warning for page in pages for warning in page.warnings)

        if not blocks:
            if any(page.has_images for page in pages):
                self._raise_permanent(
                    code="ocr_required",
                    message=(
                        "The PDF contains no extractable text. "
                        "Optical character recognition may be required."
                    ),
                )

            self._raise_permanent(
                code="empty_content",
                message="The PDF does not contain extractable text.",
            )

        text, elements = self._assemble(pages)

        return ExtractedDocument(
            workspace_id=context.workspace_id,
            document_id=context.document_id,
            source_media_type=context.source_media_type,
            source_byte_size=len(source),
            extractor=ExtractorIdentity(
                name="pdf",
                version="1",
                parser_name="pdfplumber",
                parser_version=pdfplumber.__version__,
            ),
            text=text,
            elements=elements,
            warnings=warnings,
            metadata=metadata,
        )

    def _validate_source_size(self, source: bytes) -> None:
        if not source:
            self._raise_permanent(
                code="empty_content",
                message="The PDF is empty.",
            )

        if len(source) > self._max_source_bytes:
            self._raise_permanent(
                code="source_too_large",
                message="The PDF exceeds the supported size limit.",
            )

    def _extract_page(self, page: Any) -> _PageExtraction:
        geometry = self._page_geometry(page)
        has_images = bool(page.images)
        tables = self._extract_tables(page, geometry)
        text_boxes = page.objects.get("textboxhorizontal", [])
        warnings: list[ExtractionWarning] = []
        blocks: list[_RawBlock] = list(tables)

        for parser_order, text_box in enumerate(text_boxes):
            raw_text = text_box.get("text")
            if not isinstance(raw_text, str):
                continue

            text = self._clean_parser_text(raw_text)
            if not text.strip():
                continue

            bounding_box = self._object_bounding_box(text_box, geometry)
            matching_table = self._confident_table_match(
                text=text,
                bounding_box=bounding_box,
                tables=tables,
            )
            if matching_table is not None:
                continue

            if any(
                self._bounding_boxes_intersect(bounding_box, table.bounding_box)
                for table in tables
            ):
                warnings.append(
                    ExtractionWarning(
                        code="ambiguous_table_text_overlap",
                        message=(
                            "Text overlapped a detected table but could not be "
                            "confidently associated with an extracted cell, so it "
                            "was preserved."
                        ),
                        source_location=self._location(
                            geometry=geometry,
                            bounding_box=bounding_box,
                        ),
                    )
                )

            blocks.append(
                _RawBlock(
                    kind="paragraph",
                    text=text,
                    geometry=geometry,
                    bounding_box=bounding_box,
                    parser_order=parser_order,
                )
            )

        ordered_blocks = tuple(
            sorted(
                blocks,
                key=lambda block: (
                    block.bounding_box[1],
                    block.bounding_box[0],
                    block.parser_order,
                ),
            )
        )

        if not ordered_blocks:
            code = "ocr_may_be_required" if has_images else "no_extractable_text"
            message = (
                "This page contains images but no extractable text; OCR may be "
                "required."
                if has_images
                else "This page contains no extractable text."
            )
            warnings.append(
                ExtractionWarning(
                    code=code,
                    message=message,
                    source_location=self._location(
                        geometry=geometry,
                        bounding_box=(0.0, 0.0, geometry.width, geometry.height),
                    ),
                )
            )

        page.close()

        return _PageExtraction(
            blocks=ordered_blocks,
            warnings=tuple(warnings),
            has_images=has_images,
        )

    def _extract_tables(
        self,
        page: Any,
        geometry: _PageGeometry,
    ) -> tuple[_RawBlock, ...]:
        blocks: list[_RawBlock] = []

        for table_index, table in enumerate(page.find_tables(_TABLE_SETTINGS)):
            extracted_rows = table.extract()
            raw_rows: list[tuple[_RawCell, ...]] = []

            for row_index, (row, extracted_cells) in enumerate(
                zip(table.rows, extracted_rows, strict=False)
            ):
                cells: list[_RawCell] = []
                column_count = max(len(row.cells), len(extracted_cells))

                for column_index in range(column_count):
                    raw_text = (
                        extracted_cells[column_index]
                        if column_index < len(extracted_cells)
                        else None
                    )
                    raw_box = (
                        row.cells[column_index]
                        if column_index < len(row.cells)
                        else None
                    )
                    cells.append(
                        _RawCell(
                            row_index=row_index,
                            column_index=column_index,
                            text=(
                                self._clean_parser_text(raw_text)
                                if isinstance(raw_text, str)
                                else ""
                            ),
                            bounding_box=(
                                self._normalise_bounding_box(raw_box, geometry)
                                if raw_box is not None
                                else None
                            ),
                        )
                    )

                if cells:
                    raw_rows.append(tuple(cells))

            if not raw_rows:
                continue

            expected_columns = max(len(row) for row in raw_rows)
            rectangular_rows = tuple(
                row
                + tuple(
                    _RawCell(
                        row_index=row_index,
                        column_index=column_index,
                        text="",
                        bounding_box=None,
                    )
                    for column_index in range(len(row), expected_columns)
                )
                for row_index, row in enumerate(raw_rows)
            )
            text = self._serialise_table(rectangular_rows)

            if not text.strip():
                continue

            blocks.append(
                _RawBlock(
                    kind="table",
                    text=text,
                    geometry=geometry,
                    bounding_box=self._normalise_bounding_box(table.bbox, geometry),
                    parser_order=-(table_index + 1),
                    rows=rectangular_rows,
                )
            )

        return tuple(blocks)

    def _assemble(
        self,
        pages: tuple[_PageExtraction, ...],
    ) -> tuple[str, tuple[Element, ...]]:
        parts: list[str] = []
        elements: list[Element] = []
        cursor = 0

        for page_index, page in enumerate(pages):
            if page_index > 0:
                parts.append(PAGE_SEPARATOR)
                cursor += len(PAGE_SEPARATOR)

            for block_index, block in enumerate(page.blocks):
                if block_index > 0:
                    parts.append(ELEMENT_SEPARATOR)
                    cursor += len(ELEMENT_SEPARATOR)

                start_character = cursor
                parts.append(block.text)
                cursor += len(block.text)
                end_character = cursor

                if cursor > self._max_extracted_characters:
                    self._raise_permanent(
                        code="extracted_text_too_large",
                        message=(
                            "The PDF produces more text than the supported limit."
                        ),
                    )

                location = self._location(
                    geometry=block.geometry,
                    bounding_box=block.bounding_box,
                    start_character=start_character,
                    end_character=end_character,
                )

                if block.kind == "paragraph":
                    elements.append(
                        ParagraphElement(
                            text=block.text,
                            source_location=location,
                        )
                    )
                    continue

                rows = tuple(
                    TableRow(
                        index=row_index,
                        cells=tuple(
                            TableCell(
                                row_index=cell.row_index,
                                column_index=cell.column_index,
                                text=cell.text,
                                source_location=(
                                    self._location(
                                        geometry=block.geometry,
                                        bounding_box=cell.bounding_box,
                                    )
                                    if cell.bounding_box is not None
                                    else None
                                ),
                            )
                            for cell in row
                        ),
                    )
                    for row_index, row in enumerate(block.rows)
                )
                elements.append(
                    TableElement(
                        text=block.text,
                        rows=rows,
                        source_location=location,
                    )
                )

        if cursor > self._max_extracted_characters:
            self._raise_permanent(
                code="extracted_text_too_large",
                message="The PDF produces more text than the supported limit.",
            )

        return "".join(parts), tuple(elements)

    @staticmethod
    def _metadata(raw_metadata: Mapping[str, Any] | None) -> ExtractedDocumentMetadata:
        metadata = raw_metadata or {}

        def string_value(key: str) -> str | None:
            value = metadata.get(key)
            return value if isinstance(value, str) and value else None

        return ExtractedDocumentMetadata(
            title=string_value("Title"),
            author=string_value("Author"),
            subject=string_value("Subject"),
            keywords=string_value("Keywords"),
            creator=string_value("Creator"),
            producer=string_value("Producer"),
            creation_date=string_value("CreationDate"),
            modification_date=string_value("ModDate"),
        )

    @staticmethod
    def _clean_parser_text(text: str) -> str:
        return text.replace("\r\n", "\n").replace("\r", "\n").rstrip("\n")

    @staticmethod
    def _serialise_table(rows: Sequence[Sequence[_RawCell]]) -> str:
        def escape(value: str) -> str:
            return value.replace("\\", "\\\\").replace("\t", "\\t").replace("\n", "\\n")

        return "\n".join("\t".join(escape(cell.text) for cell in row) for row in rows)

    @staticmethod
    def _confident_table_match(
        *,
        text: str,
        bounding_box: _BoundingBox,
        tables: Sequence[_RawBlock],
    ) -> _RawBlock | None:
        compact_text = " ".join(text.split())

        for table in tables:
            if not PdfPlumberExtractor._bounding_box_contains(
                table.bounding_box,
                bounding_box,
            ):
                continue

            cell_texts = {
                " ".join(cell.text.split())
                for row in table.rows
                for cell in row
                if cell.text.strip()
            }
            if compact_text and compact_text in cell_texts:
                return table

        return None

    @staticmethod
    def _page_geometry(page: Any) -> _PageGeometry:
        rotation = int(page.rotation or 0) % 360
        if rotation not in (0, 90, 180, 270):
            PdfPlumberExtractor._raise_permanent(
                code="invalid_pdf",
                message="The PDF contains an unsupported page rotation.",
            )

        page_bbox = PdfPlumberExtractor._bounding_box(page.bbox)
        media_box = PdfPlumberExtractor._bounding_box(page.mediabox)
        crop_box = PdfPlumberExtractor._bounding_box(page.cropbox or page.mediabox)

        return _PageGeometry(
            page_number=int(page.page_number),
            width=float(page.width),
            height=float(page.height),
            rotation_degrees=cast(Literal[0, 90, 180, 270], rotation),
            has_distinct_crop_box=any(
                abs(crop - media) > _BBOX_TOLERANCE
                for crop, media in zip(crop_box, media_box, strict=True)
            ),
            origin_x=page_bbox[0],
            origin_top=page_bbox[1],
        )

    @staticmethod
    def _object_bounding_box(
        item: Mapping[str, Any],
        geometry: _PageGeometry,
    ) -> _BoundingBox:
        return PdfPlumberExtractor._normalise_bounding_box(
            (
                item["x0"],
                item["top"],
                item["x1"],
                item["bottom"],
            ),
            geometry,
        )

    @staticmethod
    def _normalise_bounding_box(
        raw_box: Sequence[Any],
        geometry: _PageGeometry,
    ) -> _BoundingBox:
        x0, top, x1, bottom = PdfPlumberExtractor._bounding_box(raw_box)
        return (
            x0 - geometry.origin_x,
            top - geometry.origin_top,
            x1 - geometry.origin_x,
            bottom - geometry.origin_top,
        )

    @staticmethod
    def _bounding_box(raw_box: Sequence[Any]) -> _BoundingBox:
        if len(raw_box) != 4:
            PdfPlumberExtractor._raise_permanent(
                code="invalid_pdf",
                message="The PDF contains invalid page geometry.",
            )

        values: list[float] = []
        for value in raw_box:
            if not isinstance(value, int | float):
                PdfPlumberExtractor._raise_permanent(
                    code="invalid_pdf",
                    message="The PDF contains invalid page geometry.",
                )
            values.append(float(value))

        x0, top, x1, bottom = values
        if x1 < x0 or bottom < top:
            PdfPlumberExtractor._raise_permanent(
                code="invalid_pdf",
                message="The PDF contains invalid page geometry.",
            )

        return x0, top, x1, bottom

    @staticmethod
    def _bounding_box_contains(
        outer: _BoundingBox,
        inner: _BoundingBox,
    ) -> bool:
        return (
            inner[0] >= outer[0] - _BBOX_TOLERANCE
            and inner[1] >= outer[1] - _BBOX_TOLERANCE
            and inner[2] <= outer[2] + _BBOX_TOLERANCE
            and inner[3] <= outer[3] + _BBOX_TOLERANCE
        )

    @staticmethod
    def _bounding_boxes_intersect(
        first: _BoundingBox,
        second: _BoundingBox,
    ) -> bool:
        return not (
            first[2] <= second[0]
            or first[0] >= second[2]
            or first[3] <= second[1]
            or first[1] >= second[3]
        )

    @staticmethod
    def _location(
        *,
        geometry: _PageGeometry,
        bounding_box: _BoundingBox,
        start_character: int | None = None,
        end_character: int | None = None,
    ) -> PdfSourceLocation:
        return PdfSourceLocation(
            page_number=geometry.page_number,
            page_width=geometry.width,
            page_height=geometry.height,
            x0=bounding_box[0],
            top=bounding_box[1],
            x1=bounding_box[2],
            bottom=bounding_box[3],
            rotation_degrees=geometry.rotation_degrees,
            has_distinct_crop_box=geometry.has_distinct_crop_box,
            start_character=start_character,
            end_character=end_character,
        )

    @staticmethod
    def _raise_permanent(*, code: str, message: str) -> None:
        raise ExtractionFailure(
            kind=ExtractionFailureKind.PERMANENT,
            code=code,
            user_message=message,
        )

    @staticmethod
    def _raise_encrypted_pdf(exception: Exception) -> None:
        raise ExtractionFailure(
            kind=ExtractionFailureKind.PERMANENT,
            code="encrypted_pdf",
            user_message=(
                "Password-protected PDFs are not supported. "
                "Please upload an unencrypted copy."
            ),
        ) from exception
