from typing import Literal, cast
from uuid import UUID

import pytest
from pydantic import ValidationError

from app.extraction import (
    Element,
    ExtractedDocument,
    ExtractedDocumentMetadata,
    ExtractionWarning,
    ExtractorIdentity,
    HeadingElement,
    ParagraphElement,
    PdfSourceLocation,
    TableCell,
    TableElement,
    TableRow,
    TextSourceLocation,
)
from app.normalisation import (
    NormalisedHeadingElement,
    NormalisedParagraphElement,
    NormalisedTableElement,
    NormalisedUnknownElement,
    StructuralNormaliser,
)
from app.normalisation.structural import ELEMENT_SEPARATOR, PAGE_SEPARATOR

WORKSPACE_ID = UUID("79d8699d-2ae1-4072-9ad1-f9dd16f7b325")
DOCUMENT_ID = UUID("5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41")


class FutureElement(Element):
    kind: Literal["future_widget"] = "future_widget"
    payload: tuple[str, ...]


def text_location(start: int = 0, end: int = 1) -> TextSourceLocation:
    return TextSourceLocation(
        start_character=start,
        end_character=end,
        start_line=1,
        end_line=1,
    )


def pdf_location(
    page: int,
    *,
    top: float,
    bottom: float,
) -> PdfSourceLocation:
    return PdfSourceLocation(
        page_number=page,
        page_width=600,
        page_height=800,
        x0=50,
        top=top,
        x1=550,
        bottom=bottom,
    )


def extracted_document(
    elements: tuple[Element, ...],
    *,
    text: str = "raw extraction",
    warnings: tuple[ExtractionWarning, ...] = (),
) -> ExtractedDocument:
    return ExtractedDocument(
        workspace_id=WORKSPACE_ID,
        document_id=DOCUMENT_ID,
        source_media_type="text/plain",
        source_byte_size=42,
        extractor=ExtractorIdentity(name="fixture", version="1"),
        text=text,
        elements=elements,
        warnings=warnings,
        metadata=ExtractedDocumentMetadata(
            title="Normalisation fixture",
            author="MakeTime",
        ),
    )


def test_normalisation_is_immutable_distinct_and_deterministic() -> None:
    source = extracted_document(
        (
            ParagraphElement(
                text="Stable paragraph",
                source_location=text_location(),
                confidence=0.9,
            ),
        )
    )
    source_before = source.model_dump()
    normaliser = StructuralNormaliser()

    first = normaliser.normalise(source)
    second = normaliser.normalise(source)

    assert source.model_dump() == source_before
    assert first == second
    assert first is not source
    assert first.workspace_id == source.workspace_id
    assert first.document_id == source.document_id
    assert first.source_extractor == source.extractor
    assert first.source_byte_size == source.source_byte_size
    assert first.normaliser.name == "structural"
    assert first.normaliser.version == "1"
    assert first.metadata == source.metadata
    assert first.elements[0].confidence == 0.9

    with pytest.raises(ValidationError, match="frozen"):
        first.text = "changed"


def test_unicode_and_known_whitespace_rules_are_explicit() -> None:
    source = extracted_document(
        (
            ParagraphElement(
                text="  Cafe\u0301\u00a0\talpha  \r\n\r\n\r\n beta\t gamma  ",
                source_location=text_location(),
            ),
        )
    )

    normalised = StructuralNormaliser().normalise(source)

    assert normalised.text == "Café alpha\n\nbeta gamma"
    assert cast(NormalisedParagraphElement, normalised.elements[0]).text == (
        normalised.text
    )


def test_heading_level_and_semantic_order_are_preserved() -> None:
    source = extracted_document(
        (
            HeadingElement(
                text=" Heading ",
                level=3,
                source_location=text_location(),
            ),
            ParagraphElement(
                text="Body",
                source_location=text_location(),
            ),
        )
    )

    normalised = StructuralNormaliser().normalise(source)

    assert [element.kind for element in normalised.elements] == [
        "heading",
        "paragraph",
    ]
    heading = cast(NormalisedHeadingElement, normalised.elements[0])
    assert heading.level == 3
    assert normalised.text == f"Heading{ELEMENT_SEPARATOR}Body"


def test_table_cells_are_normalised_and_tsv_is_rebuilt() -> None:
    table = TableElement(
        text="old parser representation",
        source_location=text_location(),
        rows=(
            TableRow(
                index=0,
                cells=(
                    TableCell(
                        row_index=0,
                        column_index=0,
                        text=" Cafe\u0301 ",
                        source_location=text_location(),
                    ),
                    TableCell(
                        row_index=0,
                        column_index=1,
                        text="Line 1\r\nLine\t2",
                        source_location=text_location(),
                    ),
                ),
            ),
        ),
    )

    normalised = StructuralNormaliser().normalise(extracted_document((table,)))
    element = cast(NormalisedTableElement, normalised.elements[0])

    assert [cell.text for cell in element.rows[0].cells] == [
        "Café",
        "Line 1\nLine 2",
    ]
    assert element.text == "Café\tLine 1\\nLine 2"
    assert normalised.text == element.text


def test_element_offsets_slice_the_complete_normalised_text() -> None:
    source = extracted_document(
        (
            ParagraphElement(text=" First ", source_location=text_location()),
            ParagraphElement(text=" Second ", source_location=text_location()),
        )
    )

    normalised = StructuralNormaliser().normalise(source)

    for element in normalised.elements:
        assert (
            normalised.text[element.start_character : element.end_character]
            == element.text
        )


def test_repeated_pdf_headers_and_footers_require_strong_boundary_evidence() -> None:
    elements: list[Element] = []

    for page in range(1, 4):
        elements.extend(
            (
                ParagraphElement(
                    text="Repeated header",
                    source_location=pdf_location(page, top=20, bottom=40),
                ),
                ParagraphElement(
                    text=f"Body page {page}",
                    source_location=pdf_location(page, top=200, bottom=300),
                ),
                ParagraphElement(
                    text="Repeated footer",
                    source_location=pdf_location(page, top=760, bottom=790),
                ),
            )
        )

    normalised = StructuralNormaliser().normalise(
        extracted_document(tuple(elements), text="pdf extraction")
    )

    assert [element.text for element in normalised.elements] == [
        "Body page 1",
        "Body page 2",
        "Body page 3",
    ]
    assert [change.code for change in normalised.changes] == [
        "repeated_header_removed",
        "repeated_footer_removed",
    ]
    assert all(len(change.source_element_ids) == 3 for change in normalised.changes)
    assert normalised.text == PAGE_SEPARATOR.join(
        ("Body page 1", "Body page 2", "Body page 3")
    )


def test_two_pages_or_non_edge_text_are_not_suppressed() -> None:
    elements = tuple(
        ParagraphElement(
            text="Repeated body",
            source_location=pdf_location(page, top=300, bottom=330),
        )
        for page in (1, 2, 3)
    )

    normalised = StructuralNormaliser().normalise(
        extracted_document(elements, text="pdf extraction")
    )

    assert len(normalised.elements) == 3
    assert normalised.changes == ()


def test_pdf_page_gaps_preserve_blank_page_boundaries() -> None:
    first = ParagraphElement(
        text="First page",
        source_location=pdf_location(1, top=200, bottom=250),
    )
    third = ParagraphElement(
        text="Third page",
        source_location=pdf_location(3, top=200, bottom=250),
    )
    blank_warning = ExtractionWarning(
        code="no_extractable_text",
        message="This page contains no extractable text.",
        source_location=pdf_location(2, top=0, bottom=800),
    )

    normalised = StructuralNormaliser().normalise(
        extracted_document(
            (first, third),
            text="pdf extraction",
            warnings=(blank_warning,),
        )
    )

    assert normalised.text == f"First page{PAGE_SEPARATOR}{PAGE_SEPARATOR}Third page"
    assert normalised.extraction_warnings == (blank_warning,)
    assert (
        normalised.text[
            normalised.elements[1].start_character : normalised.elements[
                1
            ].end_character
        ]
        == "Third page"
    )


def test_unknown_future_elements_use_a_safe_immutable_fallback() -> None:
    source_element = FutureElement(
        payload=("alpha", "beta"),
        source_location=text_location(),
    )

    normalised = StructuralNormaliser().normalise(extracted_document((source_element,)))
    fallback = cast(NormalisedUnknownElement, normalised.elements[0])

    assert isinstance(fallback, NormalisedUnknownElement)
    assert fallback.original_kind == "future_widget"
    assert fallback.text == ""
    assert '"payload":["alpha","beta"]' in fallback.preserved_payload_json
    assert fallback.source_element_ids == (source_element.id,)


def test_existing_unknown_text_is_preserved_conservatively() -> None:
    from app.extraction import UnknownElement

    source_element = UnknownElement(
        original_kind="future_text",
        preserved_text="  Keep\tspacing\r\n",
        source_location=text_location(),
    )

    fallback = cast(
        NormalisedUnknownElement,
        StructuralNormaliser()
        .normalise(extracted_document((source_element,)))
        .elements[0],
    )

    assert fallback.text == "  Keep\tspacing\n"


def test_semantically_empty_known_elements_are_removed_with_traceability() -> None:
    empty = ParagraphElement(
        text="\u00a0\t",
        source_location=text_location(),
    )
    body = ParagraphElement(
        text="Body",
        source_location=text_location(),
    )

    normalised = StructuralNormaliser().normalise(extracted_document((empty, body)))

    assert [element.text for element in normalised.elements] == ["Body"]
    assert [change.code for change in normalised.changes] == [
        "semantically_empty_element_removed"
    ]
    assert normalised.changes[0].source_element_ids == (empty.id,)


def test_derived_elements_retain_source_identity_and_location() -> None:
    location = text_location(2, 10)
    source_element = ParagraphElement(
        text="Traceable",
        source_location=location,
    )

    normalised = StructuralNormaliser().normalise(extracted_document((source_element,)))
    element = normalised.elements[0]

    assert element.source_element_ids == (source_element.id,)
    assert element.source_locations == (location,)
