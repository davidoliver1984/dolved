from collections import defaultdict
from typing import cast
from uuid import UUID

import pytest
from pydantic import ValidationError

from app.chunking import (
    BaselineChunkingConfiguration,
    BaselineStructuralChunker,
    TiktokenTokenizer,
    UnrepresentableContentError,
)
from app.extraction import (
    ExtractedDocumentMetadata,
    ExtractorIdentity,
    TableCell,
    TableRow,
    TextSourceLocation,
)
from app.normalisation import (
    NormalisedDocument,
    NormalisedElement,
    NormalisedHeadingElement,
    NormalisedParagraphElement,
    NormalisedTableElement,
    NormalisedUnknownElement,
    NormaliserIdentity,
)

WORKSPACE_ID = UUID("79d8699d-2ae1-4072-9ad1-f9dd16f7b325")
DOCUMENT_ID = UUID("5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41")
SOURCE_ELEMENT_ID = UUID("66c411d0-8215-4338-af94-c9dc71a56f83")


def location(start: int, end: int) -> TextSourceLocation:
    return TextSourceLocation(
        start_character=start,
        end_character=end,
        start_line=1,
        end_line=1,
    )


def paragraph(
    text: str,
    *,
    element_id: int = 1,
    start: int = 0,
) -> NormalisedParagraphElement:
    return NormalisedParagraphElement(
        id=UUID(int=element_id),
        text=text,
        source_element_ids=(UUID(int=element_id + 100),),
        source_locations=(location(start, start + len(text)),),
        start_character=start,
        end_character=start + len(text),
    )


def heading(text: str, *, element_id: int = 10) -> NormalisedHeadingElement:
    return NormalisedHeadingElement(
        id=UUID(int=element_id),
        text=text,
        level=2,
        source_element_ids=(UUID(int=element_id + 100),),
        source_locations=(location(0, len(text)),),
        start_character=0,
        end_character=len(text),
    )


def document(
    elements: tuple[NormalisedElement, ...],
) -> NormalisedDocument:
    return NormalisedDocument(
        workspace_id=WORKSPACE_ID,
        document_id=DOCUMENT_ID,
        source_media_type="text/plain",
        source_byte_size=100,
        source_extractor=ExtractorIdentity(name="fixture", version="1"),
        normaliser=NormaliserIdentity(name="structural", version="1"),
        text="\n\n".join(element.text for element in elements),
        elements=elements,
        metadata=ExtractedDocumentMetadata(title="Chunk fixture"),
    )


def primary_text_by_element(result: object) -> dict[UUID, str]:
    from app.chunking import ChunkingResult

    typed_result = cast(ChunkingResult, result)
    parts: dict[UUID, list[tuple[int, str]]] = defaultdict(list)
    for chunk in typed_result.chunks:
        for contribution in chunk.contributions:
            if contribution.role != "primary":
                continue
            parts[contribution.normalised_element_id].append(
                (
                    contribution.element_start_character,
                    chunk.text[
                        contribution.chunk_start_character : contribution.chunk_end_character
                    ],
                )
            )
    return {
        element_id: "".join(text for _, text in sorted(element_parts))
        for element_id, element_parts in parts.items()
    }


def test_configuration_records_exact_tokenizer_and_semantic_settings() -> None:
    chunker = BaselineStructuralChunker()
    configuration = chunker.configuration

    assert configuration.target_tokens == 400
    assert configuration.max_tokens == 512
    assert configuration.overlap_tokens == 64
    assert configuration.preferred_min_tokens == 100
    assert configuration.tokenizer.library == "tiktoken"
    assert configuration.tokenizer.library_version == "0.13.0"
    assert configuration.tokenizer.encoding == "o200k_base"
    assert len(configuration.tokenizer.vocabulary_fingerprint) == 64
    assert len(configuration.fingerprint()) == 64
    assert "model" not in configuration.model_dump()


def test_configuration_rejects_incoherent_limits() -> None:
    identity = TiktokenTokenizer().identity

    with pytest.raises(ValidationError, match="target_tokens"):
        BaselineChunkingConfiguration(
            tokenizer=identity,
            target_tokens=20,
            max_tokens=10,
        )

    with pytest.raises(ValidationError, match="overlap_tokens"):
        BaselineChunkingConfiguration(
            tokenizer=identity,
            target_tokens=10,
            max_tokens=20,
            overlap_tokens=10,
        )


def test_chunking_is_deterministic_immutable_and_identity_bearing() -> None:
    source = document(
        (
            heading("Getting started"),
            paragraph("A short deterministic paragraph.", element_id=2),
        )
    )
    source_before = source.model_dump()
    chunker = BaselineStructuralChunker(
        target_tokens=20,
        max_tokens=30,
        overlap_tokens=4,
        preferred_min_tokens=2,
    )

    first = chunker.chunk(source)
    second = chunker.chunk(source)

    assert first == second
    assert first.workspace_id == WORKSPACE_ID
    assert first.document_id == DOCUMENT_ID
    assert first.strategy_name == "baseline-structural"
    assert first.strategy_version == "1"
    assert first.configuration_fingerprint == first.configuration.fingerprint()
    assert source.model_dump() == source_before

    with pytest.raises(ValidationError, match="frozen"):
        first.chunks[0].text = "changed"


def test_chunks_are_bounded_ordered_and_preserve_all_primary_text() -> None:
    long_text = " ".join(f"word-{index}" for index in range(180))
    elements = (
        paragraph(long_text, element_id=1),
        paragraph("A final paragraph remains present.", element_id=2),
    )
    result = BaselineStructuralChunker(
        target_tokens=32,
        max_tokens=45,
        overlap_tokens=8,
        preferred_min_tokens=4,
    ).chunk(document(elements))

    assert len(result.chunks) > 2
    assert [chunk.ordinal for chunk in result.chunks] == list(range(len(result.chunks)))
    assert all(chunk.token_count <= 45 for chunk in result.chunks)
    assert primary_text_by_element(result) == {
        element.id: element.text for element in elements
    }
    assert any(
        contribution.role == "overlap"
        for chunk in result.chunks[1:]
        for contribution in chunk.contributions
    )
    tokenizer = TiktokenTokenizer()
    for chunk in result.chunks[1:]:
        overlap_text = "\n\n".join(
            chunk.text[
                contribution.chunk_start_character : contribution.chunk_end_character
            ]
            for contribution in chunk.contributions
            if contribution.role == "overlap"
        )
        assert tokenizer.count(overlap_text) <= 8


def test_overlap_is_deterministic_recorded_and_not_counted_as_primary() -> None:
    source = document(
        (
            paragraph(
                "First sentence has enough words to require splitting. "
                "Second sentence also has enough words to require splitting. "
                "Third sentence completes the example.",
                element_id=1,
            ),
        )
    )
    chunker = BaselineStructuralChunker(
        target_tokens=12,
        max_tokens=20,
        overlap_tokens=4,
        preferred_min_tokens=0,
    )

    first = chunker.chunk(source)
    second = chunker.chunk(source)

    assert first == second
    for chunk in first.chunks[1:]:
        overlap = [
            contribution
            for contribution in chunk.contributions
            if contribution.role == "overlap"
        ]
        assert overlap
        for contribution in overlap:
            assert (
                chunk.text[
                    contribution.chunk_start_character : contribution.chunk_end_character
                ]
                == source.elements[0].text[
                    contribution.element_start_character : contribution.element_end_character
                ]
            )
    assert primary_text_by_element(first)[source.elements[0].id] == (
        source.elements[0].text
    )


def test_heading_moves_with_following_content_when_target_boundary_allows() -> None:
    first = paragraph("one two three four five six", element_id=1)
    section_heading = heading("Next section", element_id=2)
    body = paragraph("seven eight nine ten", element_id=3)

    result = BaselineStructuralChunker(
        target_tokens=8,
        max_tokens=16,
        overlap_tokens=2,
        preferred_min_tokens=0,
    ).chunk(document((first, section_heading, body)))

    primary_ids = [
        [
            contribution.normalised_element_id
            for contribution in chunk.contributions
            if contribution.role == "primary"
        ]
        for chunk in result.chunks
    ]
    assert primary_ids[0] == [first.id]
    assert primary_ids[1] == [section_heading.id, body.id]


def test_oversized_paragraph_prefers_sentence_boundaries() -> None:
    element = paragraph(
        "Alpha beta gamma. Delta epsilon zeta. Eta theta iota.",
        element_id=1,
    )
    result = BaselineStructuralChunker(
        target_tokens=6,
        max_tokens=10,
        overlap_tokens=2,
        preferred_min_tokens=0,
    ).chunk(document((element,)))

    primary_fragments = [
        chunk.text[
            contribution.chunk_start_character : contribution.chunk_end_character
        ]
        for chunk in result.chunks
        for contribution in chunk.contributions
        if contribution.role == "primary"
    ]
    assert primary_fragments[0].endswith(" ")
    assert "".join(primary_fragments) == element.text
    assert any(warning.code == "oversized_element_split" for warning in result.warnings)


def test_oversized_table_splits_at_row_boundaries_and_preserves_metadata() -> None:
    rows = tuple(
        TableRow(
            index=index,
            cells=(
                TableCell(
                    row_index=index,
                    column_index=0,
                    text=f"row {index} value",
                    source_location=location(index, index + 1),
                ),
            ),
        )
        for index in range(8)
    )
    text = "\n".join(row.cells[0].text for row in rows)
    element = NormalisedTableElement(
        id=UUID(int=20),
        text=text,
        rows=rows,
        source_element_ids=(SOURCE_ELEMENT_ID,),
        source_locations=(location(0, len(text)),),
        start_character=0,
        end_character=len(text),
    )

    result = BaselineStructuralChunker(
        target_tokens=8,
        max_tokens=14,
        overlap_tokens=2,
        preferred_min_tokens=0,
    ).chunk(document((element,)))

    assert primary_text_by_element(result)[element.id] == text
    assert any(warning.code == "oversized_table_split" for warning in result.warnings)
    first_contribution = result.chunks[0].contributions[0]
    assert first_contribution.source_element_ids == (SOURCE_ELEMENT_ID,)
    assert first_contribution.source_locations == element.source_locations


def test_unknown_elements_use_deliberate_safe_fallbacks() -> None:
    preserved = NormalisedUnknownElement(
        id=UUID(int=30),
        original_kind="future_text",
        preserved_payload_json='{"kind":"future_text"}',
        text="Preserved future content",
        source_element_ids=(UUID(int=130),),
        source_locations=(location(0, 24),),
        start_character=0,
        end_character=24,
    )
    structural_only = NormalisedUnknownElement(
        id=UUID(int=31),
        original_kind="future_shape",
        preserved_payload_json='{"kind":"future_shape","points":[1,2]}',
        text="",
        source_element_ids=(UUID(int=131),),
        source_locations=(location(24, 24),),
        start_character=24,
        end_character=24,
    )

    result = BaselineStructuralChunker(
        target_tokens=20,
        max_tokens=30,
        overlap_tokens=4,
        preferred_min_tokens=0,
    ).chunk(document((preserved, structural_only)))

    assert primary_text_by_element(result) == {preserved.id: preserved.text}
    assert [warning.code for warning in result.warnings] == [
        "non_chunkable_empty_unknown_element"
    ]
    assert result.warnings[0].normalised_element_id == structural_only.id


def test_empty_normalised_document_produces_explicit_empty_result() -> None:
    result = BaselineStructuralChunker().chunk(document(()))

    assert result.chunks == ()
    assert result.warnings == ()


def test_empty_known_element_fails_instead_of_returning_incomplete_success() -> None:
    empty = paragraph("", element_id=1)

    with pytest.raises(UnrepresentableContentError, match="contains no text"):
        BaselineStructuralChunker().chunk(document((empty,)))


def test_small_final_chunk_is_retained_with_semantic_warning() -> None:
    elements = (
        paragraph("one two three four five", element_id=1),
        paragraph("tiny", element_id=2),
    )
    result = BaselineStructuralChunker(
        target_tokens=5,
        max_tokens=10,
        overlap_tokens=1,
        preferred_min_tokens=3,
    ).chunk(document(elements))

    assert primary_text_by_element(result) == {
        element.id: element.text for element in elements
    }
    assert any(
        warning.code == "below_preferred_minimum" and warning.chunk_ordinal == 1
        for warning in result.warnings
    )


def test_chunk_identity_changes_with_content_or_consequential_configuration() -> None:
    first_document = document((paragraph("Stable content", element_id=1),))
    changed_document = document((paragraph("Changed content", element_id=1),))

    baseline = BaselineStructuralChunker(
        target_tokens=10,
        max_tokens=20,
        overlap_tokens=2,
        preferred_min_tokens=0,
    ).chunk(first_document)
    changed_content = BaselineStructuralChunker(
        target_tokens=10,
        max_tokens=20,
        overlap_tokens=2,
        preferred_min_tokens=0,
    ).chunk(changed_document)
    changed_configuration = BaselineStructuralChunker(
        target_tokens=11,
        max_tokens=20,
        overlap_tokens=2,
        preferred_min_tokens=0,
    ).chunk(first_document)

    assert baseline.chunks[0].id != changed_content.chunks[0].id
    assert baseline.chunks[0].id != changed_configuration.chunks[0].id


def test_tokenizer_round_trips_unicode_at_safe_boundaries() -> None:
    tokenizer = TiktokenTokenizer()
    text = "Café 👩🏽‍💻 — deterministic token boundaries"
    end = tokenizer.largest_prefix_end(text, 5)
    start = tokenizer.smallest_suffix_start(text, 5)

    assert text[:end] + text[end:] == text
    assert text[:start] + text[start:] == text
    assert tokenizer.count(text[:end]) <= 5
    assert tokenizer.count(text[start:]) <= 5
