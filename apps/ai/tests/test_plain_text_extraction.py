from pathlib import Path
from typing import cast
from uuid import UUID

import pytest
from pydantic import ValidationError

from app.extraction import (
    ExtractedDocument,
    ExtractionContext,
    ExtractionFailure,
    ExtractionFailureKind,
    ExtractorIdentity,
    ParagraphElement,
    PlainTextExtractor,
    TextSourceLocation,
    UnknownElement,
)

FIXTURE_DIRECTORY = Path(__file__).parent / "fixtures" / "plain_text"
WORKSPACE_ID = UUID("79d8699d-2ae1-4072-9ad1-f9dd16f7b325")
DOCUMENT_ID = UUID("5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41")


def extraction_context() -> ExtractionContext:
    return ExtractionContext(
        workspace_id=WORKSPACE_ID,
        document_id=DOCUMENT_ID,
        source_media_type="text/plain",
    )


def test_representative_utf8_file_preserves_text_order_whitespace_and_provenance() -> (
    None
):
    source = (FIXTURE_DIRECTORY / "representative.txt").read_bytes()

    document = PlainTextExtractor().extract(
        source,
        context=extraction_context(),
    )

    assert document.workspace_id == WORKSPACE_ID
    assert document.document_id == DOCUMENT_ID
    assert document.source_media_type == "text/plain"
    assert document.source_byte_size == len(source)
    assert document.extractor == ExtractorIdentity(name="plain-text", version="1")
    assert document.text == source.decode()
    assert document.warnings == ()
    assert all(isinstance(element, ParagraphElement) for element in document.elements)
    paragraphs = cast(tuple[ParagraphElement, ...], document.elements)
    assert [element.text for element in paragraphs] == [
        "Café résumé\ncontinued line",
        "第二段",
        "final paragraph",
    ]
    assert [element.source_location for element in paragraphs] == [
        TextSourceLocation(
            start_character=0,
            end_character=26,
            start_line=1,
            end_line=2,
        ),
        TextSourceLocation(
            start_character=28,
            end_character=31,
            start_line=4,
            end_line=4,
        ),
        TextSourceLocation(
            start_character=33,
            end_character=48,
            start_line=6,
            end_line=6,
        ),
    ]


def test_meaningful_leading_and_trailing_whitespace_is_preserved() -> None:
    document = PlainTextExtractor().extract(
        b"  keep me  ",
        context=extraction_context(),
    )
    element = document.elements[0]

    assert isinstance(element, ParagraphElement)
    assert document.text == "  keep me  "
    assert element.text == "  keep me  "


def test_utf8_bom_is_accepted_and_all_newline_forms_are_normalised() -> None:
    source = b"\xef\xbb\xbfalpha\r\nbeta\r\ngamma\r\rdelta"

    document = PlainTextExtractor().extract(
        source,
        context=extraction_context(),
    )

    assert document.text == "alpha\nbeta\ngamma\n\ndelta"
    assert all(isinstance(element, ParagraphElement) for element in document.elements)
    paragraphs = cast(tuple[ParagraphElement, ...], document.elements)
    assert [element.text for element in paragraphs] == [
        "alpha\nbeta\ngamma",
        "delta",
    ]
    assert document.source_byte_size == len(source)


def test_unicode_line_separator_is_preserved_for_later_normalisation() -> None:
    document = PlainTextExtractor().extract(
        "alpha\u2028beta".encode(),
        context=extraction_context(),
    )

    element = document.elements[0]

    assert document.text == "alpha\u2028beta"
    assert isinstance(element, ParagraphElement)
    assert element.text == "alpha\u2028beta"
    assert isinstance(element.source_location, TextSourceLocation)
    assert element.source_location.end_line == 1


@pytest.mark.parametrize(
    ("source", "code", "message"),
    [
        (b"", "empty_content", "The document is empty."),
        (b" \n\t\r\n", "empty_content", "does not contain any text content"),
        (b"\xff", "invalid_encoding", "UTF-8"),
    ],
)
def test_permanent_content_failures_are_typed(
    source: bytes,
    code: str,
    message: str,
) -> None:
    with pytest.raises(ExtractionFailure) as error:
        PlainTextExtractor().extract(source, context=extraction_context())

    assert error.value.kind is ExtractionFailureKind.PERMANENT
    assert error.value.code == code
    assert message in error.value.user_message


def test_source_size_is_bounded_before_decoding() -> None:
    with pytest.raises(ExtractionFailure) as error:
        PlainTextExtractor(max_source_bytes=4).extract(
            b"\xff\xff\xff\xff\xff",
            context=extraction_context(),
        )

    assert error.value.kind is ExtractionFailureKind.PERMANENT
    assert error.value.code == "source_too_large"


def test_maximum_source_size_must_be_positive() -> None:
    with pytest.raises(ValueError, match="at least one"):
        PlainTextExtractor(max_source_bytes=0)


def test_new_extraction_run_has_fresh_element_ids_but_deterministic_content() -> None:
    extractor = PlainTextExtractor()
    source = b"first\n\nsecond"

    first = extractor.extract(source, context=extraction_context())
    second = extractor.extract(source, context=extraction_context())

    assert [element.id for element in first.elements] != [
        element.id for element in second.elements
    ]
    assert [element.model_dump(exclude={"id"}) for element in first.elements] == [
        element.model_dump(exclude={"id"}) for element in second.elements
    ]


def test_canonical_models_are_deeply_immutable_and_reject_unexpected_fields() -> None:
    document = PlainTextExtractor().extract(
        b"content",
        context=extraction_context(),
    )

    with pytest.raises(ValidationError, match="frozen"):
        document.text = "changed"

    with pytest.raises(ValidationError, match="frozen"):
        cast(ParagraphElement, document.elements[0]).text = "changed"

    with pytest.raises(ValidationError, match="Extra inputs are not permitted"):
        ExtractionContext.model_validate(
            {
                "workspace_id": WORKSPACE_ID,
                "document_id": DOCUMENT_ID,
                "source_media_type": "text/plain",
                "unexpected": True,
            }
        )


def test_unknown_element_is_a_safe_preservable_fallback() -> None:
    unknown = UnknownElement(
        original_kind="future-diagram",
        preserved_text="Quarterly architecture",
        source_location=TextSourceLocation(
            start_character=0,
            end_character=22,
            start_line=1,
            end_line=1,
        ),
    )
    document = ExtractedDocument(
        workspace_id=WORKSPACE_ID,
        document_id=DOCUMENT_ID,
        source_media_type="text/plain",
        source_byte_size=22,
        extractor=ExtractorIdentity(name="future-extractor", version="1"),
        text="Quarterly architecture",
        elements=(unknown,),
    )

    serialised = document.model_dump(mode="json")

    assert document.elements == (unknown,)
    assert serialised["elements"][0]["kind"] == "unknown"
    assert serialised["elements"][0]["original_kind"] == "future-diagram"
    assert serialised["elements"][0]["preserved_text"] == "Quarterly architecture"


def test_text_source_location_rejects_reversed_ranges() -> None:
    with pytest.raises(ValidationError, match="end_character"):
        TextSourceLocation(
            start_character=2,
            end_character=1,
            start_line=1,
            end_line=1,
        )

    with pytest.raises(ValidationError, match="end_line"):
        TextSourceLocation(
            start_character=0,
            end_character=1,
            start_line=2,
            end_line=1,
        )


def test_extractor_creates_paragraph_elements_only_for_real_plain_text_structure() -> (
    None
):
    document = PlainTextExtractor().extract(
        b"# Not inferred as a heading",
        context=extraction_context(),
    )

    assert len(document.elements) == 1
    element = document.elements[0]
    assert isinstance(element, ParagraphElement)
    assert element.text == "# Not inferred as a heading"
