from typing import cast
from uuid import UUID

import pytest
from pydantic import ValidationError

from app.extraction import (
    DocxExtractor,
    DocxSourceLocation,
    ExtractionContext,
    ExtractionFailure,
    ExtractionFailureKind,
    HeadingElement,
    ParagraphElement,
    PythonDocxExtractor,
    TableElement,
    create_docx_extractor,
)
from app.extraction.docx.python_docx import ELEMENT_SEPARATOR
from tests.docx_fixtures import (
    empty_docx,
    merged_table_docx,
    mixed_text_and_image_docx,
    nested_table_docx,
    representative_docx,
)

WORKSPACE_ID = UUID("79d8699d-2ae1-4072-9ad1-f9dd16f7b325")
DOCUMENT_ID = UUID("5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41")


def extraction_context() -> ExtractionContext:
    return ExtractionContext(
        workspace_id=WORKSPACE_ID,
        document_id=DOCUMENT_ID,
        source_media_type=(
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        ),
    )


def test_factory_exposes_the_small_implementation_neutral_boundary() -> None:
    extractor: DocxExtractor = create_docx_extractor()

    assert isinstance(extractor, PythonDocxExtractor)


def test_body_content_preserves_order_context_metadata_and_offsets() -> None:
    source = representative_docx()

    document = PythonDocxExtractor().extract(
        source,
        context=extraction_context(),
    )

    assert document.workspace_id == WORKSPACE_ID
    assert document.document_id == DOCUMENT_ID
    assert document.source_byte_size == len(source)
    assert document.extractor.name == "docx"
    assert document.extractor.version == "1"
    assert document.extractor.parser_name == "python-docx"
    assert document.extractor.parser_version == "1.2.0"
    assert document.metadata.title == "Extraction fixture"
    assert document.metadata.author == "Dolved"
    assert document.metadata.subject == "R10-S04"
    assert document.metadata.keywords == "rag,docx"
    assert document.metadata.creation_date == "2026-07-29T12:00:00+00:00"
    assert document.metadata.modification_date == "2026-07-29T13:30:00+00:00"

    assert [element.kind for element in document.elements] == [
        "heading",
        "paragraph",
        "table",
        "paragraph",
    ]
    assert document.text == ELEMENT_SEPARATOR.join(
        (
            "Document heading",
            "Opening paragraph",
            "Service\tResponsibility\nPython\tExtraction",
            "Closing paragraph",
        )
    )

    for block_index, element in enumerate(document.elements):
        location = cast(DocxSourceLocation, element.source_location)
        assert location.body_block_index == block_index
        assert location.start_character is not None
        assert location.end_character is not None
        assert (
            document.text[location.start_character : location.end_character]
            == cast(ParagraphElement | TableElement, element).text
        )


def test_explicit_word_heading_style_retains_level_without_visual_inference() -> None:
    document = PythonDocxExtractor().extract(
        representative_docx(),
        context=extraction_context(),
    )

    heading = cast(HeadingElement, document.elements[0])
    paragraph = cast(ParagraphElement, document.elements[1])

    assert isinstance(heading, HeadingElement)
    assert heading.level == 2
    assert isinstance(paragraph, ParagraphElement)


def test_table_is_structured_with_cell_provenance() -> None:
    document = PythonDocxExtractor().extract(
        representative_docx(),
        context=extraction_context(),
    )
    table = cast(TableElement, document.elements[2])

    assert isinstance(table, TableElement)
    assert [[cell.text for cell in row.cells] for row in table.rows] == [
        ["Service", "Responsibility"],
        ["Python", "Extraction"],
    ]

    for row in table.rows:
        for cell in row.cells:
            location = cast(DocxSourceLocation, cell.source_location)
            assert location.body_block_index == 2
            assert location.table_row_index == cell.row_index
            assert location.table_column_index == cell.column_index
            assert location.start_character is None
            assert location.end_character is None


def test_merged_cells_follow_the_documented_layout_grid_approximation() -> None:
    document = PythonDocxExtractor().extract(
        merged_table_docx(),
        context=extraction_context(),
    )
    table = cast(TableElement, document.elements[0])

    assert [[cell.text for cell in row.cells] for row in table.rows] == [
        ["Merged heading", "Merged heading"],
        ["Left", "Right"],
    ]
    assert table.text == "Merged heading\tMerged heading\nLeft\tRight"


def test_nested_table_text_is_flattened_and_warned_instead_of_discarded() -> None:
    document = PythonDocxExtractor().extract(
        nested_table_docx(),
        context=extraction_context(),
    )
    table = cast(TableElement, document.elements[0])

    assert table.rows[0].cells[0].text == "Outer text\nNested\tValues"
    assert table.text == "Outer text\\nNested\\tValues"
    assert [warning.code for warning in document.warnings] == ["nested_table_flattened"]
    warning_location = document.warnings[0].source_location
    assert isinstance(warning_location, DocxSourceLocation)
    assert warning_location.body_block_index == 0
    assert warning_location.table_row_index == 0
    assert warning_location.table_column_index == 0


def test_embedded_images_are_not_silently_treated_as_extracted_content() -> None:
    document = PythonDocxExtractor().extract(
        mixed_text_and_image_docx(),
        context=extraction_context(),
    )

    assert document.text == "Extractable text"
    assert [warning.code for warning in document.warnings] == ["images_not_extracted"]


@pytest.mark.parametrize(
    ("source", "code"),
    [
        (b"", "empty_content"),
        (b"not a docx", "invalid_docx"),
        (empty_docx(), "empty_content"),
        (bytes.fromhex("d0cf11e0a1b11ae1") + b"encrypted", "unsupported_word_package"),
    ],
)
def test_permanent_docx_failures_are_typed(source: bytes, code: str) -> None:
    with pytest.raises(ExtractionFailure) as error:
        PythonDocxExtractor().extract(source, context=extraction_context())

    assert error.value.kind is ExtractionFailureKind.PERMANENT
    assert error.value.code == code
    assert error.value.user_message


def test_byte_and_extracted_character_limits_are_injectable() -> None:
    source = representative_docx()

    with pytest.raises(ExtractionFailure) as byte_error:
        PythonDocxExtractor(max_source_bytes=len(source) - 1).extract(
            source,
            context=extraction_context(),
        )
    with pytest.raises(ExtractionFailure) as text_error:
        PythonDocxExtractor(max_extracted_characters=5).extract(
            source,
            context=extraction_context(),
        )

    assert byte_error.value.code == "source_too_large"
    assert text_error.value.code == "extracted_text_too_large"


@pytest.mark.parametrize(
    "arguments",
    [
        {"max_source_bytes": 0},
        {"max_extracted_characters": 0},
    ],
)
def test_resource_limits_must_be_positive(arguments: dict[str, int]) -> None:
    with pytest.raises(ValueError, match="at least one"):
        PythonDocxExtractor(**arguments)


def test_docx_models_are_immutable_and_validate_source_indexes() -> None:
    document = PythonDocxExtractor().extract(
        representative_docx(),
        context=extraction_context(),
    )
    location = cast(DocxSourceLocation, document.elements[0].source_location)

    with pytest.raises(ValidationError, match="frozen"):
        document.text = "changed"

    with pytest.raises(ValidationError, match="provided together"):
        DocxSourceLocation.model_validate(
            {
                **location.model_dump(),
                "table_row_index": 0,
                "table_column_index": None,
            }
        )

    with pytest.raises(ValidationError, match="end_character"):
        assert location.start_character is not None
        DocxSourceLocation.model_validate(
            {
                **location.model_dump(),
                "end_character": location.start_character - 1,
            }
        )


def test_re_extraction_keeps_content_deterministic_but_uses_fresh_element_ids() -> None:
    source = representative_docx()
    extractor = PythonDocxExtractor()

    first = extractor.extract(source, context=extraction_context())
    second = extractor.extract(source, context=extraction_context())

    assert second.text == first.text
    assert [element.model_dump(exclude={"id"}) for element in second.elements] == [
        element.model_dump(exclude={"id"}) for element in first.elements
    ]
    assert [element.id for element in second.elements] != [
        element.id for element in first.elements
    ]
