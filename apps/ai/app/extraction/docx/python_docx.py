import re
from collections.abc import Sequence
from dataclasses import dataclass
from datetime import datetime
from io import BytesIO
from zipfile import BadZipFile

import docx
from docx.document import Document as DocumentObject
from docx.opc.exceptions import PackageNotFoundError
from docx.table import Table, _Cell
from docx.text.paragraph import Paragraph
from lxml.etree import XMLSyntaxError

from app.extraction.errors import ExtractionFailure, ExtractionFailureKind
from app.extraction.limits import DEFAULT_MAX_SOURCE_BYTES
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
    TableCell,
    TableElement,
    TableRow,
)

DEFAULT_MAX_EXTRACTED_CHARACTERS = 5_000_000
ELEMENT_SEPARATOR = "\n\n"
_HEADING_STYLE = re.compile(r"Heading ?([1-9])", re.IGNORECASE)
_OLE_COMPOUND_FILE_SIGNATURE = bytes.fromhex("d0cf11e0a1b11ae1")


@dataclass(frozen=True)
class _RawCell:
    row_index: int
    column_index: int
    text: str


@dataclass(frozen=True)
class _RawBlock:
    kind: str
    text: str
    body_block_index: int
    heading_level: int | None = None
    rows: tuple[tuple[_RawCell, ...], ...] = ()


@dataclass(frozen=True)
class _ParsedDocument:
    blocks: tuple[_RawBlock, ...]
    warnings: tuple[ExtractionWarning, ...]
    metadata: ExtractedDocumentMetadata


class PythonDocxExtractor:
    def __init__(
        self,
        *,
        max_source_bytes: int = DEFAULT_MAX_SOURCE_BYTES,
        max_extracted_characters: int = DEFAULT_MAX_EXTRACTED_CHARACTERS,
    ) -> None:
        if max_source_bytes < 1:
            raise ValueError("max_source_bytes must be at least one")

        if max_extracted_characters < 1:
            raise ValueError("max_extracted_characters must be at least one")

        self._max_source_bytes = max_source_bytes
        self._max_extracted_characters = max_extracted_characters

    def extract(
        self,
        source: bytes,
        *,
        context: ExtractionContext,
    ) -> ExtractedDocument:
        self._validate_source(source)
        parsed = self._parse(source)

        if not parsed.blocks:
            self._raise_permanent(
                code="empty_content",
                message="The DOCX document does not contain extractable text.",
            )

        text, elements = self._assemble(parsed.blocks)

        return ExtractedDocument(
            workspace_id=context.workspace_id,
            document_id=context.document_id,
            source_media_type=context.source_media_type,
            source_byte_size=len(source),
            extractor=ExtractorIdentity(
                name="docx",
                version="1",
                parser_name="python-docx",
                parser_version=docx.__version__,
            ),
            text=text,
            elements=elements,
            warnings=parsed.warnings,
            metadata=parsed.metadata,
        )

    def _validate_source(self, source: bytes) -> None:
        if not source:
            self._raise_permanent(
                code="empty_content",
                message="The DOCX document is empty.",
            )

        if len(source) > self._max_source_bytes:
            self._raise_permanent(
                code="source_too_large",
                message="The DOCX document exceeds the supported size limit.",
            )

        if source.startswith(_OLE_COMPOUND_FILE_SIGNATURE):
            self._raise_permanent(
                code="unsupported_word_package",
                message=(
                    "Encrypted and legacy Word packages are not supported. "
                    "Please save an unencrypted DOCX file and retry."
                ),
            )

    def _parse(self, source: bytes) -> _ParsedDocument:
        try:
            document = docx.Document(BytesIO(source))
        except (
            BadZipFile,
            PackageNotFoundError,
            KeyError,
            ValueError,
            XMLSyntaxError,
        ) as error:
            raise ExtractionFailure(
                kind=ExtractionFailureKind.PERMANENT,
                code="invalid_docx",
                user_message=(
                    "The DOCX document could not be read. "
                    "Please export a valid DOCX file and retry."
                ),
            ) from error

        blocks: list[_RawBlock] = []
        warnings: list[ExtractionWarning] = []

        if document.inline_shapes:
            warnings.append(
                ExtractionWarning(
                    code="images_not_extracted",
                    message=(
                        "The DOCX document contains embedded images. "
                        "Image content was not extracted."
                    ),
                )
            )

        for body_block_index, item in enumerate(document.iter_inner_content()):
            if isinstance(item, Paragraph):
                text = self._clean_text(item.text)
                if not text.strip():
                    continue

                heading_level = self._heading_level(item)
                blocks.append(
                    _RawBlock(
                        kind="heading" if heading_level is not None else "paragraph",
                        text=text,
                        body_block_index=body_block_index,
                        heading_level=heading_level,
                    )
                )
                continue

            table_block, table_warnings = self._table_block(
                item,
                body_block_index=body_block_index,
            )
            warnings.extend(table_warnings)
            if table_block is not None:
                blocks.append(table_block)

        return _ParsedDocument(
            blocks=tuple(blocks),
            warnings=tuple(warnings),
            metadata=self._metadata(document),
        )

    def _table_block(
        self,
        table: Table,
        *,
        body_block_index: int,
    ) -> tuple[_RawBlock | None, tuple[ExtractionWarning, ...]]:
        raw_rows: list[tuple[_RawCell, ...]] = []
        warnings: list[ExtractionWarning] = []
        expected_columns = max(
            (
                row.grid_cols_before + len(row.cells) + row.grid_cols_after
                for row in table.rows
            ),
            default=0,
        )

        for row_index, row in enumerate(table.rows):
            cells: list[_RawCell] = [
                _RawCell(
                    row_index=row_index,
                    column_index=column_index,
                    text="",
                )
                for column_index in range(row.grid_cols_before)
            ]

            for cell_offset, cell in enumerate(row.cells):
                column_index = row.grid_cols_before + cell_offset
                cell_text, contains_nested_table = self._cell_text(cell)
                cells.append(
                    _RawCell(
                        row_index=row_index,
                        column_index=column_index,
                        text=cell_text,
                    )
                )
                if contains_nested_table:
                    warnings.append(
                        ExtractionWarning(
                            code="nested_table_flattened",
                            message=(
                                "A nested DOCX table was flattened into its "
                                "containing cell in document order."
                            ),
                            source_location=DocxSourceLocation(
                                body_block_index=body_block_index,
                                table_row_index=row_index,
                                table_column_index=column_index,
                            ),
                        )
                    )

            cells.extend(
                _RawCell(
                    row_index=row_index,
                    column_index=column_index,
                    text="",
                )
                for column_index in range(
                    row.grid_cols_before + len(row.cells),
                    expected_columns,
                )
            )
            raw_rows.append(tuple(cells))

        text = self._serialise_table(raw_rows)
        if not text.strip():
            return None, tuple(warnings)

        return (
            _RawBlock(
                kind="table",
                text=text,
                body_block_index=body_block_index,
                rows=tuple(raw_rows),
            ),
            tuple(warnings),
        )

    def _cell_text(self, cell: _Cell) -> tuple[str, bool]:
        parts: list[str] = []
        contains_nested_table = False

        for item in cell.iter_inner_content():
            if isinstance(item, Paragraph):
                text = self._clean_text(item.text)
                if text.strip():
                    parts.append(text)
                continue

            contains_nested_table = True
            nested_block, _ = self._table_block(item, body_block_index=0)
            if nested_block is not None:
                parts.append(nested_block.text)

        return "\n".join(parts), contains_nested_table

    def _assemble(
        self,
        blocks: tuple[_RawBlock, ...],
    ) -> tuple[str, tuple[Element, ...]]:
        parts: list[str] = []
        elements: list[Element] = []
        cursor = 0

        for block_index, block in enumerate(blocks):
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
                        "The DOCX document produces more text than the supported limit."
                    ),
                )

            location = DocxSourceLocation(
                body_block_index=block.body_block_index,
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

            if block.kind == "heading":
                if block.heading_level is None:
                    raise RuntimeError("heading block is missing its level")

                elements.append(
                    HeadingElement(
                        text=block.text,
                        level=block.heading_level,
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
                            source_location=DocxSourceLocation(
                                body_block_index=block.body_block_index,
                                table_row_index=cell.row_index,
                                table_column_index=cell.column_index,
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

        return "".join(parts), tuple(elements)

    @staticmethod
    def _heading_level(paragraph: Paragraph) -> int | None:
        style = paragraph.style
        if style is None:
            return None

        for value in (style.name, style.style_id):
            match = _HEADING_STYLE.fullmatch(value or "")
            if match is not None:
                return int(match.group(1))

        return None

    @staticmethod
    def _metadata(document: DocumentObject) -> ExtractedDocumentMetadata:
        properties = document.core_properties

        def text(value: str | None) -> str | None:
            return value if value else None

        def timestamp(value: datetime | None) -> str | None:
            return value.isoformat() if value is not None else None

        return ExtractedDocumentMetadata(
            title=text(properties.title),
            author=text(properties.author),
            subject=text(properties.subject),
            keywords=text(properties.keywords),
            creation_date=timestamp(properties.created),
            modification_date=timestamp(properties.modified),
        )

    @staticmethod
    def _clean_text(text: str) -> str:
        return text.replace("\r\n", "\n").replace("\r", "\n").rstrip("\n")

    @staticmethod
    def _serialise_table(rows: Sequence[Sequence[_RawCell]]) -> str:
        def escape(value: str) -> str:
            return value.replace("\\", "\\\\").replace("\t", "\\t").replace("\n", "\\n")

        return "\n".join("\t".join(escape(cell.text) for cell in row) for row in rows)

    @staticmethod
    def _raise_permanent(*, code: str, message: str) -> None:
        raise ExtractionFailure(
            kind=ExtractionFailureKind.PERMANENT,
            code=code,
            user_message=message,
        )
