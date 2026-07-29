import json
import re
import unicodedata
from dataclasses import dataclass
from typing import Any, Literal
from uuid import UUID, uuid5

from app.extraction.models import (
    Element,
    ExtractedDocument,
    HeadingElement,
    ParagraphElement,
    PdfSourceLocation,
    SourceLocation,
    TableCell,
    TableElement,
    TableRow,
    UnknownElement,
)
from app.normalisation.models import (
    NormalisationChange,
    NormalisedDocument,
    NormalisedElement,
    NormalisedHeadingElement,
    NormalisedParagraphElement,
    NormalisedTableElement,
    NormalisedUnknownElement,
    NormaliserIdentity,
)

ELEMENT_SEPARATOR = "\n\n"
PAGE_SEPARATOR = "\n\n\f\n\n"
NORMALISER_NAME = "structural"
NORMALISER_VERSION = "1"
_NORMALISED_ELEMENT_NAMESPACE = UUID("62e22da5-bae8-4f69-8449-d091046592a4")
_HORIZONTAL_WHITESPACE = re.compile(r"[ \t]+")
_REPEATED_EMPTY_LINES = re.compile(r"\n{3,}")


@dataclass(frozen=True)
class _DraftElement:
    kind: Literal["paragraph", "heading", "table", "unknown"]
    text: str
    source_element: Element
    rows: tuple[TableRow, ...] = ()
    heading_level: int | None = None
    original_kind: str | None = None
    preserved_payload_json: str | None = None


class StructuralNormaliser:
    def normalise(self, document: ExtractedDocument) -> NormalisedDocument:
        suppressed_ids, boundary_changes = self._repeated_boundary_changes(document)
        drafts: list[_DraftElement] = []
        changes: list[NormalisationChange] = list(boundary_changes)

        for element in document.elements:
            if element.id in suppressed_ids:
                continue

            draft = self._normalise_element(element)
            if draft.text or draft.kind == "unknown":
                drafts.append(draft)
                continue

            changes.append(
                NormalisationChange(
                    code="semantically_empty_element_removed",
                    message=(
                        "An element containing only deterministically empty "
                        "structural content was removed."
                    ),
                    source_element_ids=(element.id,),
                )
            )

        text, elements = self._assemble(
            drafts=tuple(drafts),
            page_count=self._page_count(document),
        )

        return NormalisedDocument(
            workspace_id=document.workspace_id,
            document_id=document.document_id,
            source_media_type=document.source_media_type,
            source_byte_size=document.source_byte_size,
            source_extractor=document.extractor,
            normaliser=NormaliserIdentity(
                name=NORMALISER_NAME,
                version=NORMALISER_VERSION,
            ),
            text=text,
            elements=elements,
            extraction_warnings=document.warnings,
            changes=tuple(changes),
            metadata=document.metadata,
        )

    def _normalise_element(self, element: Element) -> _DraftElement:
        if isinstance(element, HeadingElement):
            return _DraftElement(
                kind="heading",
                text=self._normalise_known_text(element.text),
                source_element=element,
                heading_level=element.level,
            )

        if isinstance(element, ParagraphElement):
            return _DraftElement(
                kind="paragraph",
                text=self._normalise_known_text(element.text),
                source_element=element,
            )

        if isinstance(element, TableElement):
            rows = tuple(
                TableRow(
                    index=row.index,
                    cells=tuple(
                        TableCell(
                            row_index=cell.row_index,
                            column_index=cell.column_index,
                            text=self._normalise_known_text(cell.text),
                            source_location=cell.source_location,
                        )
                        for cell in row.cells
                    ),
                )
                for row in element.rows
            )
            return _DraftElement(
                kind="table",
                text=self._serialise_table(rows),
                source_element=element,
                rows=rows,
            )

        preserved_text = (
            element.preserved_text
            if isinstance(element, UnknownElement)
            else getattr(element, "text", "")
        )
        text = (
            self._normalise_unknown_text(preserved_text)
            if isinstance(preserved_text, str)
            else ""
        )
        return _DraftElement(
            kind="unknown",
            text=text,
            source_element=element,
            original_kind=(
                element.original_kind
                if isinstance(element, UnknownElement)
                else element.kind
            ),
            preserved_payload_json=json.dumps(
                element.model_dump(mode="json"),
                ensure_ascii=False,
                separators=(",", ":"),
                sort_keys=True,
            ),
        )

    def _assemble(
        self,
        *,
        drafts: tuple[_DraftElement, ...],
        page_count: int | None,
    ) -> tuple[str, tuple[NormalisedElement, ...]]:
        parts: list[str] = []
        elements: list[NormalisedElement] = []
        cursor = 0
        previous_page: int | None = None

        for index, draft in enumerate(drafts):
            page_number = self._page_number(draft.source_element.source_location)
            separator = ""

            if index == 0 and page_number is not None and page_number > 1:
                separator = PAGE_SEPARATOR * (page_number - 1)
            elif index > 0:
                if (
                    page_number is not None
                    and previous_page is not None
                    and page_number > previous_page
                ):
                    separator = PAGE_SEPARATOR * (page_number - previous_page)
                else:
                    separator = ELEMENT_SEPARATOR

            parts.append(separator)
            cursor += len(separator)
            start_character = cursor
            parts.append(draft.text)
            cursor += len(draft.text)
            end_character = cursor
            elements.append(
                self._build_element(
                    draft=draft,
                    start_character=start_character,
                    end_character=end_character,
                )
            )
            previous_page = page_number

        if (
            page_count is not None
            and previous_page is not None
            and previous_page < page_count
        ):
            trailing_boundaries = PAGE_SEPARATOR * (page_count - previous_page)
            parts.append(trailing_boundaries)

        return "".join(parts), tuple(elements)

    def _build_element(
        self,
        *,
        draft: _DraftElement,
        start_character: int,
        end_character: int,
    ) -> NormalisedElement:
        element_id = uuid5(
            _NORMALISED_ELEMENT_NAMESPACE,
            "|".join(
                (
                    NORMALISER_VERSION,
                    str(draft.source_element.id),
                    draft.kind,
                    draft.text,
                )
            ),
        )
        common: dict[str, Any] = {
            "id": element_id,
            "text": draft.text,
            "source_element_ids": (draft.source_element.id,),
            "source_locations": (draft.source_element.source_location,),
            "start_character": start_character,
            "end_character": end_character,
            "confidence": draft.source_element.confidence,
        }

        if draft.kind == "paragraph":
            return NormalisedParagraphElement(**common)

        if draft.kind == "heading":
            if draft.heading_level is None:
                raise RuntimeError("heading draft is missing its level")
            return NormalisedHeadingElement(level=draft.heading_level, **common)

        if draft.kind == "table":
            return NormalisedTableElement(rows=draft.rows, **common)

        if draft.original_kind is None or draft.preserved_payload_json is None:
            raise RuntimeError("unknown draft is missing its preserved source payload")
        return NormalisedUnknownElement(
            original_kind=draft.original_kind,
            preserved_payload_json=draft.preserved_payload_json,
            **common,
        )

    def _repeated_boundary_changes(
        self,
        document: ExtractedDocument,
    ) -> tuple[set[UUID], tuple[NormalisationChange, ...]]:
        pages: dict[int, list[Element]] = {}

        for element in document.elements:
            location = element.source_location
            if not isinstance(location, PdfSourceLocation):
                continue
            pages.setdefault(location.page_number, []).append(element)

        if len(pages) < 3 or any(len(elements) < 2 for elements in pages.values()):
            return set(), ()

        suppressed_ids: set[UUID] = set()
        changes: list[NormalisationChange] = []

        for boundary in ("header", "footer"):
            candidates: list[ParagraphElement] = []

            for elements in pages.values():
                candidate = elements[0] if boundary == "header" else elements[-1]
                if not isinstance(candidate, ParagraphElement):
                    break

                location = candidate.source_location
                if not isinstance(location, PdfSourceLocation):
                    break

                at_boundary = (
                    location.top <= location.page_height * 0.15
                    if boundary == "header"
                    else location.bottom >= location.page_height * 0.85
                )
                if not at_boundary:
                    break

                candidates.append(candidate)

            if len(candidates) != len(pages):
                continue

            texts = {
                self._normalise_known_text(candidate.text) for candidate in candidates
            }
            if len(texts) != 1 or not next(iter(texts)):
                continue

            ids = tuple(candidate.id for candidate in candidates)
            suppressed_ids.update(ids)
            changes.append(
                NormalisationChange(
                    code=f"repeated_{boundary}_removed",
                    message=(
                        f"A repeated PDF {boundary} was removed from every "
                        "content-bearing page under the conservative boundary rule."
                    ),
                    source_element_ids=ids,
                )
            )

        return suppressed_ids, tuple(changes)

    @staticmethod
    def _normalise_known_text(value: str) -> str:
        canonical = unicodedata.normalize("NFC", value)
        canonical = canonical.replace("\r\n", "\n").replace("\r", "\n")
        canonical = "".join(
            " " if unicodedata.category(character) == "Zs" else character
            for character in canonical
        )
        lines = [
            _HORIZONTAL_WHITESPACE.sub(" ", line).strip()
            for line in canonical.split("\n")
        ]
        return _REPEATED_EMPTY_LINES.sub("\n\n", "\n".join(lines)).strip("\n")

    @staticmethod
    def _normalise_unknown_text(value: str) -> str:
        return (
            unicodedata.normalize("NFC", value)
            .replace("\r\n", "\n")
            .replace("\r", "\n")
        )

    @staticmethod
    def _serialise_table(rows: tuple[TableRow, ...]) -> str:
        def escape(value: str) -> str:
            return value.replace("\\", "\\\\").replace("\t", "\\t").replace("\n", "\\n")

        return "\n".join(
            "\t".join(escape(cell.text) for cell in row.cells) for row in rows
        )

    @staticmethod
    def _page_number(location: SourceLocation) -> int | None:
        return location.page_number if isinstance(location, PdfSourceLocation) else None

    @staticmethod
    def _page_count(document: ExtractedDocument) -> int | None:
        page_numbers = [
            location.page_number
            for location in (
                *(element.source_location for element in document.elements),
                *(
                    warning.source_location
                    for warning in document.warnings
                    if warning.source_location is not None
                ),
            )
            if isinstance(location, PdfSourceLocation)
        ]
        return max(page_numbers, default=None)
