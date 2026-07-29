from typing import cast
from uuid import UUID

import pytest
from pydantic import ValidationError

from app.extraction import (
    ExtractionContext,
    ExtractionFailure,
    ExtractionFailureKind,
    ParagraphElement,
    PdfExtractor,
    PdfPlumberExtractor,
    PdfSourceLocation,
    TableElement,
    create_pdf_extractor,
)
from app.extraction.pdf.pdfplumber import ELEMENT_SEPARATOR, PAGE_SEPARATOR
from tests.pdf_fixtures import (
    PAGE_HEIGHT,
    PAGE_WIDTH,
    PositionedText,
    encrypted_pdf,
    image_only_pdf,
    mixed_text_and_image_pdf,
    table_pdf,
    text_pdf,
)

WORKSPACE_ID = UUID("79d8699d-2ae1-4072-9ad1-f9dd16f7b325")
DOCUMENT_ID = UUID("5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41")


def extraction_context() -> ExtractionContext:
    return ExtractionContext(
        workspace_id=WORKSPACE_ID,
        document_id=DOCUMENT_ID,
        source_media_type="application/pdf",
    )


def test_factory_exposes_the_small_implementation_neutral_boundary() -> None:
    extractor: PdfExtractor = create_pdf_extractor()

    assert isinstance(extractor, PdfPlumberExtractor)


def test_multi_page_text_preserves_context_metadata_provenance_and_offsets() -> None:
    source = text_pdf(
        [
            [
                PositionedText("First page heading", 72, 720),
                PositionedText("First page body", 72, 680),
            ],
            [PositionedText("Second page body", 72, 720)],
        ],
        title="Extraction fixture",
        author="MakeTime",
        subject="R10-S03",
        keywords="rag,pdf",
    )

    document = PdfPlumberExtractor().extract(
        source,
        context=extraction_context(),
    )

    assert document.workspace_id == WORKSPACE_ID
    assert document.document_id == DOCUMENT_ID
    assert document.source_media_type == "application/pdf"
    assert document.source_byte_size == len(source)
    assert document.extractor.name == "pdf"
    assert document.extractor.version == "1"
    assert document.extractor.parser_name == "pdfplumber"
    assert document.extractor.parser_version is not None
    assert document.metadata.title == "Extraction fixture"
    assert document.metadata.author == "MakeTime"
    assert document.metadata.subject == "R10-S03"
    assert document.metadata.keywords == "rag,pdf"
    assert PAGE_SEPARATOR in document.text
    assert "First page heading" in document.text
    assert "First page body" in document.text
    assert "Second page body" in document.text

    for element in document.elements:
        location = cast(PdfSourceLocation, element.source_location)
        assert isinstance(location, PdfSourceLocation)
        assert location.start_character is not None
        assert location.end_character is not None
        assert (
            document.text[location.start_character : location.end_character]
            == cast(ParagraphElement, element).text
        )
        assert location.page_width == pytest.approx(PAGE_WIDTH)
        assert location.page_height == pytest.approx(PAGE_HEIGHT)

    assert [
        cast(PdfSourceLocation, element.source_location).page_number
        for element in document.elements
    ] == [1, 1, 2]


def test_line_bounded_table_is_structured_and_not_duplicated_as_paragraphs() -> None:
    document = PdfPlumberExtractor().extract(
        table_pdf(),
        context=extraction_context(),
    )

    tables = [
        element for element in document.elements if isinstance(element, TableElement)
    ]

    assert len(tables) == 1
    table = tables[0]
    assert [[cell.text for cell in row.cells] for row in table.rows] == [
        ["Service", "Responsibility"],
        ["Laravel", "Lifecycle authority"],
        ["Python", "Extraction"],
    ]
    assert table.text == (
        "Service\tResponsibility\nLaravel\tLifecycle authority\nPython\tExtraction"
    )
    assert document.text.count("Lifecycle authority") == 1
    assert document.elements == (table,)

    table_location = cast(PdfSourceLocation, table.source_location)
    assert table_location.start_character == 0
    assert table_location.end_character == len(table.text)

    for row in table.rows:
        for cell in row.cells:
            assert isinstance(cell.source_location, PdfSourceLocation)
            assert cell.source_location.page_number == 1


def test_ambiguous_table_overlap_preserves_text_and_emits_typed_warning() -> None:
    document = PdfPlumberExtractor().extract(
        table_pdf(ambiguous_overlap=True),
        context=extraction_context(),
    )

    assert "Ambiguous caption" in document.text
    assert any(
        warning.code == "ambiguous_table_text_overlap" for warning in document.warnings
    )


def test_headers_and_footers_are_preserved_for_later_normalisation() -> None:
    document = PdfPlumberExtractor().extract(
        text_pdf(
            [
                [
                    PositionedText("Repeated header", 72, 750),
                    PositionedText("Page body", 72, 400),
                    PositionedText("Repeated footer", 72, 40),
                ]
            ]
        ),
        context=extraction_context(),
    )

    assert "Repeated header" in document.text
    assert "Page body" in document.text
    assert "Repeated footer" in document.text


def test_multi_column_order_is_deterministic_best_effort_from_geometry() -> None:
    source = text_pdf(
        [
            [
                PositionedText("Left column", 72, 700),
                PositionedText("Right column", 330, 700),
            ]
        ]
    )
    extractor = PdfPlumberExtractor()

    first = extractor.extract(source, context=extraction_context())
    second = extractor.extract(source, context=extraction_context())

    assert first.text == f"Left column{ELEMENT_SEPARATOR}Right column"
    assert second.text == first.text
    assert [element.model_dump(exclude={"id"}) for element in second.elements] == [
        element.model_dump(exclude={"id"}) for element in first.elements
    ]
    assert [element.id for element in second.elements] != [
        element.id for element in first.elements
    ]


def test_rotated_crop_aware_page_geometry_is_explicit() -> None:
    document = PdfPlumberExtractor().extract(
        text_pdf(
            [[PositionedText("Rotated content", 100, 300)]],
            rotation=90,
            crop_box=(40, 40, PAGE_WIDTH - 40, PAGE_HEIGHT - 40),
        ),
        context=extraction_context(),
    )
    location = cast(PdfSourceLocation, document.elements[0].source_location)

    assert location.rotation_degrees == 90
    assert location.has_distinct_crop_box is True
    assert location.page_width > 0
    assert location.page_height > 0
    assert location.x1 >= location.x0
    assert location.bottom >= location.top


def test_blank_page_is_preserved_and_warned_without_being_called_scanned() -> None:
    document = PdfPlumberExtractor().extract(
        text_pdf(
            [
                [PositionedText("First", 72, 700)],
                [],
                [PositionedText("Third", 72, 700)],
            ]
        ),
        context=extraction_context(),
    )

    assert document.text == f"First{PAGE_SEPARATOR}{PAGE_SEPARATOR}Third"
    assert [warning.code for warning in document.warnings] == ["no_extractable_text"]
    assert isinstance(document.warnings[0].source_location, PdfSourceLocation)
    assert document.warnings[0].source_location.page_number == 2


def test_mixed_text_and_image_pages_return_usable_text_with_ocr_warning() -> None:
    document = PdfPlumberExtractor().extract(
        mixed_text_and_image_pdf(),
        context=extraction_context(),
    )

    assert "Extractable first page" in document.text
    assert [warning.code for warning in document.warnings] == ["ocr_may_be_required"]
    warning_location = document.warnings[0].source_location
    assert isinstance(warning_location, PdfSourceLocation)
    assert warning_location.page_number == 2


def test_wholly_image_only_pdf_fails_as_ocr_required() -> None:
    with pytest.raises(ExtractionFailure) as error:
        PdfPlumberExtractor().extract(
            image_only_pdf(),
            context=extraction_context(),
        )

    assert error.value.kind is ExtractionFailureKind.PERMANENT
    assert error.value.code == "ocr_required"
    assert "may be required" in error.value.user_message


@pytest.mark.parametrize(
    ("source", "code"),
    [
        (b"", "empty_content"),
        (b"not a pdf", "invalid_pdf"),
        (encrypted_pdf(), "encrypted_pdf"),
        (text_pdf([[]]), "empty_content"),
    ],
)
def test_permanent_pdf_failures_are_typed(source: bytes, code: str) -> None:
    with pytest.raises(ExtractionFailure) as error:
        PdfPlumberExtractor().extract(source, context=extraction_context())

    assert error.value.kind is ExtractionFailureKind.PERMANENT
    assert error.value.code == code
    assert error.value.user_message


def test_byte_page_and_extracted_character_limits_are_injectable() -> None:
    one_page = text_pdf([[PositionedText("Bounded content", 72, 700)]])
    two_pages = text_pdf(
        [
            [PositionedText("First", 72, 700)],
            [PositionedText("Second", 72, 700)],
        ]
    )

    with pytest.raises(ExtractionFailure) as byte_error:
        PdfPlumberExtractor(max_source_bytes=len(one_page) - 1).extract(
            one_page,
            context=extraction_context(),
        )
    with pytest.raises(ExtractionFailure) as page_error:
        PdfPlumberExtractor(max_pages=1).extract(
            two_pages,
            context=extraction_context(),
        )
    with pytest.raises(ExtractionFailure) as text_error:
        PdfPlumberExtractor(max_extracted_characters=5).extract(
            one_page,
            context=extraction_context(),
        )

    assert byte_error.value.code == "source_too_large"
    assert page_error.value.code == "too_many_pages"
    assert text_error.value.code == "extracted_text_too_large"


@pytest.mark.parametrize(
    "arguments",
    [
        {"max_source_bytes": 0},
        {"max_pages": 0},
        {"max_extracted_characters": 0},
    ],
)
def test_resource_limits_must_be_positive(arguments: dict[str, int]) -> None:
    with pytest.raises(ValueError, match="at least one"):
        PdfPlumberExtractor(**arguments)


def test_pdf_models_are_immutable_and_validate_bounding_boxes() -> None:
    document = PdfPlumberExtractor().extract(
        text_pdf([[PositionedText("Immutable", 72, 700)]]),
        context=extraction_context(),
    )
    location = cast(PdfSourceLocation, document.elements[0].source_location)

    with pytest.raises(ValidationError, match="frozen"):
        document.text = "changed"

    with pytest.raises(ValidationError, match="x1"):
        PdfSourceLocation(
            page_number=1,
            page_width=100,
            page_height=100,
            x0=50,
            top=0,
            x1=40,
            bottom=10,
        )

    with pytest.raises(ValidationError, match="provided together"):
        PdfSourceLocation.model_validate(
            {
                **location.model_dump(),
                "end_character": None,
            }
        )
