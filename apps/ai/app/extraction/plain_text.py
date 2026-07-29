from dataclasses import dataclass

from app.extraction.errors import ExtractionFailure, ExtractionFailureKind
from app.extraction.models import (
    ExtractedDocument,
    ExtractionContext,
    ExtractorIdentity,
    ParagraphElement,
    TextSourceLocation,
)

DEFAULT_MAX_SOURCE_BYTES = 25 * 1024 * 1024


@dataclass(frozen=True)
class _ParagraphRange:
    start_character: int
    end_character: int
    start_line: int
    end_line: int


class PlainTextExtractor:
    identity = ExtractorIdentity(name="plain-text", version="1")

    def __init__(self, *, max_source_bytes: int = DEFAULT_MAX_SOURCE_BYTES) -> None:
        if max_source_bytes < 1:
            raise ValueError("max_source_bytes must be at least one")

        self._max_source_bytes = max_source_bytes

    def extract(
        self,
        source: bytes,
        *,
        context: ExtractionContext,
    ) -> ExtractedDocument:
        self._validate_size(source)
        text = self._decode(source)
        ranges = tuple(self._paragraph_ranges(text))

        if not ranges:
            raise ExtractionFailure(
                kind=ExtractionFailureKind.PERMANENT,
                code="empty_content",
                user_message="The document does not contain any text content.",
            )

        elements = tuple(
            ParagraphElement(
                text=text[item.start_character : item.end_character],
                source_location=TextSourceLocation(
                    start_character=item.start_character,
                    end_character=item.end_character,
                    start_line=item.start_line,
                    end_line=item.end_line,
                ),
            )
            for item in ranges
        )

        return ExtractedDocument(
            workspace_id=context.workspace_id,
            document_id=context.document_id,
            source_media_type=context.source_media_type,
            source_byte_size=len(source),
            extractor=self.identity,
            text=text,
            elements=elements,
        )

    def _validate_size(self, source: bytes) -> None:
        if not source:
            raise ExtractionFailure(
                kind=ExtractionFailureKind.PERMANENT,
                code="empty_content",
                user_message="The document is empty.",
            )

        if len(source) > self._max_source_bytes:
            raise ExtractionFailure(
                kind=ExtractionFailureKind.PERMANENT,
                code="source_too_large",
                user_message="The document exceeds the supported size limit.",
            )

    @staticmethod
    def _decode(source: bytes) -> str:
        try:
            decoded = source.decode("utf-8-sig", errors="strict")
        except UnicodeDecodeError as exception:
            raise ExtractionFailure(
                kind=ExtractionFailureKind.PERMANENT,
                code="invalid_encoding",
                user_message="The document must use UTF-8 encoding.",
            ) from exception

        return decoded.replace("\r\n", "\n").replace("\r", "\n")

    @staticmethod
    def _paragraph_ranges(text: str) -> list[_ParagraphRange]:
        ranges: list[_ParagraphRange] = []
        paragraph_start: int | None = None
        paragraph_start_line: int | None = None
        paragraph_end = 0
        paragraph_end_line = 0
        position = 0

        lines = text.split("\n")

        for line_index, line in enumerate(lines):
            line_number = line_index + 1
            line_end = position + len(line)

            if line.strip():
                if paragraph_start is None:
                    paragraph_start = position
                    paragraph_start_line = line_number

                paragraph_end = line_end
                paragraph_end_line = line_number
            elif paragraph_start is not None and paragraph_start_line is not None:
                ranges.append(
                    _ParagraphRange(
                        start_character=paragraph_start,
                        end_character=paragraph_end,
                        start_line=paragraph_start_line,
                        end_line=paragraph_end_line,
                    )
                )
                paragraph_start = None
                paragraph_start_line = None

            position = line_end
            if line_index < len(lines) - 1:
                position += 1

        if paragraph_start is not None and paragraph_start_line is not None:
            ranges.append(
                _ParagraphRange(
                    start_character=paragraph_start,
                    end_character=paragraph_end,
                    start_line=paragraph_start_line,
                    end_line=paragraph_end_line,
                )
            )

        return ranges
