import json
import re
from dataclasses import dataclass
from typing import Literal
from uuid import UUID, uuid5

from app.chunking.errors import UnrepresentableContentError
from app.chunking.models import (
    BaselineChunkingConfiguration,
    Chunk,
    ChunkContribution,
    ChunkingResult,
    ChunkingWarning,
)
from app.chunking.tokenizer import TiktokenTokenizer, Tokenizer
from app.normalisation.models import (
    NormalisedDocument,
    NormalisedElement,
    NormalisedHeadingElement,
    NormalisedTableElement,
    NormalisedUnknownElement,
)

STRATEGY_NAME = "baseline-structural"
STRATEGY_VERSION = "1"
_CHUNK_NAMESPACE = UUID("b10c71c7-f681-4d64-b332-1cbbe10cf40d")
_SENTENCE_BOUNDARY = re.compile(r"(?<=[.!?])(?:[\"')\]]*)\s+")


@dataclass(frozen=True)
class _Piece:
    element: NormalisedElement
    start: int
    end: int
    text: str
    role: Literal["primary", "overlap"] = "primary"


class BaselineStructuralChunker:
    def __init__(
        self,
        *,
        tokenizer: Tokenizer | None = None,
        target_tokens: int = 400,
        max_tokens: int = 512,
        overlap_tokens: int = 64,
        preferred_min_tokens: int = 100,
    ) -> None:
        self._tokenizer = tokenizer or TiktokenTokenizer()
        self._configuration = BaselineChunkingConfiguration(
            tokenizer=self._tokenizer.identity,
            target_tokens=target_tokens,
            max_tokens=max_tokens,
            overlap_tokens=overlap_tokens,
            preferred_min_tokens=preferred_min_tokens,
        )

    @property
    def configuration(self) -> BaselineChunkingConfiguration:
        return self._configuration

    def chunk(self, document: NormalisedDocument) -> ChunkingResult:
        pieces: list[_Piece] = []
        warnings: list[ChunkingWarning] = []

        for element in document.elements:
            if not element.text:
                if isinstance(element, NormalisedUnknownElement):
                    warnings.append(
                        ChunkingWarning(
                            code="non_chunkable_empty_unknown_element",
                            message=(
                                "An unknown element with no preserved text was "
                                "explicitly classified as non-chunkable."
                            ),
                            normalised_element_id=element.id,
                        )
                    )
                    continue
                raise UnrepresentableContentError(
                    f"Chunkable element {element.id} contains no text."
                )

            element_pieces = self._split_element(element)
            pieces.extend(element_pieces)
            if len(element_pieces) > 1:
                code = (
                    "oversized_table_split"
                    if isinstance(element, NormalisedTableElement)
                    else "oversized_element_split"
                )
                warnings.append(
                    ChunkingWarning(
                        code=code,
                        message=(
                            "An element exceeded the target size and was split "
                            "deterministically without discarding content."
                        ),
                        normalised_element_id=element.id,
                    )
                )

        groups = self._group_primary_pieces(pieces)
        chunks: list[Chunk] = []
        previous_primary: tuple[_Piece, ...] = ()
        fingerprint = self._configuration.fingerprint()

        for ordinal, primary in enumerate(groups):
            overlap = self._overlap_from(previous_primary, primary)
            chunk = self._build_chunk(
                document=document,
                ordinal=ordinal,
                overlap=overlap,
                primary=primary,
                configuration_fingerprint=fingerprint,
            )
            if chunk.token_count > self._configuration.max_tokens:
                raise UnrepresentableContentError(
                    "Overlap caused a chunk to exceed the configured maximum."
                )
            if (
                self._tokenizer.count(self._join(primary))
                < self._configuration.preferred_min_tokens
                and len(groups) > 1
            ):
                warnings.append(
                    ChunkingWarning(
                        code="below_preferred_minimum",
                        message=(
                            "A structurally valid chunk is smaller than the "
                            "preferred minimum token count."
                        ),
                        chunk_ordinal=ordinal,
                    )
                )
            chunks.append(chunk)
            previous_primary = primary

        self._assert_complete(document, tuple(chunks))
        return ChunkingResult(
            workspace_id=document.workspace_id,
            document_id=document.document_id,
            strategy_name=STRATEGY_NAME,
            strategy_version=STRATEGY_VERSION,
            configuration=self._configuration,
            configuration_fingerprint=fingerprint,
            chunks=tuple(chunks),
            warnings=tuple(warnings),
        )

    def _split_element(self, element: NormalisedElement) -> tuple[_Piece, ...]:
        text = element.text
        if self._tokenizer.count(text) <= self._configuration.target_tokens:
            return (_Piece(element=element, start=0, end=len(text), text=text),)

        pieces: list[_Piece] = []
        cursor = 0
        while cursor < len(text):
            remaining = text[cursor:]
            if self._tokenizer.count(remaining) <= self._configuration.target_tokens:
                end = len(text)
            else:
                limit = self._tokenizer.largest_prefix_end(
                    remaining, self._configuration.target_tokens
                )
                preferred = self._preferred_boundary(
                    remaining,
                    maximum=limit,
                    table=isinstance(element, NormalisedTableElement),
                )
                end = cursor + (preferred or limit)

            if end <= cursor:
                raise UnrepresentableContentError(
                    f"Element {element.id} could not be split without loss."
                )
            pieces.append(
                _Piece(
                    element=element,
                    start=cursor,
                    end=end,
                    text=text[cursor:end],
                )
            )
            cursor = end

        return tuple(pieces)

    @staticmethod
    def _preferred_boundary(text: str, *, maximum: int, table: bool) -> int:
        candidate = text[:maximum]
        if table:
            row_boundary = candidate.rfind("\n")
            if row_boundary > 0:
                return row_boundary + 1

        sentence_boundaries = [
            match.end() for match in _SENTENCE_BOUNDARY.finditer(candidate)
        ]
        if sentence_boundaries:
            return sentence_boundaries[-1]

        paragraph_boundary = candidate.rfind("\n")
        if paragraph_boundary > 0:
            return paragraph_boundary + 1

        word_boundary = candidate.rfind(" ")
        return word_boundary + 1 if word_boundary > 0 else maximum

    def _group_primary_pieces(
        self, pieces: list[_Piece]
    ) -> tuple[tuple[_Piece, ...], ...]:
        groups: list[tuple[_Piece, ...]] = []
        current: list[_Piece] = []

        for piece in pieces:
            candidate = (*current, piece)
            if current and self._tokenizer.count(self._join(candidate)) > (
                self._configuration.target_tokens
            ):
                if isinstance(current[-1].element, NormalisedHeadingElement):
                    heading = current.pop()
                    if current:
                        groups.append(tuple(current))
                    current = [heading, piece]
                    if self._tokenizer.count(self._join(current)) > (
                        self._configuration.max_tokens
                    ):
                        groups.append((heading,))
                        current = [piece]
                else:
                    groups.append(tuple(current))
                    current = [piece]
            else:
                current.append(piece)

        if current:
            groups.append(tuple(current))
        return tuple(groups)

    def _overlap_from(
        self,
        previous_primary: tuple[_Piece, ...],
        current_primary: tuple[_Piece, ...],
    ) -> tuple[_Piece, ...]:
        budget = self._configuration.overlap_tokens
        if not previous_primary or budget == 0:
            return ()

        while budget > 0:
            selected = self._overlap_within_budget(previous_primary, budget)
            if not selected:
                return ()
            if self._tokenizer.count(self._join((*selected, *current_primary))) <= (
                self._configuration.max_tokens
            ):
                return selected
            budget -= 1
        return ()

    def _overlap_within_budget(
        self, primary: tuple[_Piece, ...], budget: int
    ) -> tuple[_Piece, ...]:
        selected: list[_Piece] = []
        for piece in reversed(primary):
            candidate = (
                _Piece(
                    element=piece.element,
                    start=piece.start,
                    end=piece.end,
                    text=piece.text,
                    role="overlap",
                ),
                *selected,
            )
            if self._tokenizer.count(self._join(candidate)) <= budget:
                selected.insert(0, candidate[0])
                continue

            remaining_budget = budget - self._tokenizer.count(self._join(selected))
            if remaining_budget <= 0:
                break
            for partial_budget in range(remaining_budget, 0, -1):
                start = self._tokenizer.smallest_suffix_start(
                    piece.text, partial_budget
                )
                partial = _Piece(
                    element=piece.element,
                    start=piece.start + start,
                    end=piece.end,
                    text=piece.text[start:],
                    role="overlap",
                )
                if (
                    partial.text
                    and self._tokenizer.count(self._join((partial, *selected)))
                    <= budget
                ):
                    selected.insert(0, partial)
                    break
            break
        return tuple(selected)

    def _build_chunk(
        self,
        *,
        document: NormalisedDocument,
        ordinal: int,
        overlap: tuple[_Piece, ...],
        primary: tuple[_Piece, ...],
        configuration_fingerprint: str,
    ) -> Chunk:
        all_pieces = (*overlap, *primary)
        text = self._join(all_pieces)
        contributions: list[ChunkContribution] = []
        cursor = 0

        for index, piece in enumerate(all_pieces):
            if index:
                cursor += 2
            start = cursor
            cursor += len(piece.text)
            contributions.append(
                ChunkContribution(
                    normalised_element_id=piece.element.id,
                    source_element_ids=piece.element.source_element_ids,
                    source_locations=piece.element.source_locations,
                    element_start_character=piece.start,
                    element_end_character=piece.end,
                    chunk_start_character=start,
                    chunk_end_character=cursor,
                    role=piece.role,
                )
            )

        identity_material = json.dumps(
            {
                "workspace_id": str(document.workspace_id),
                "document_id": str(document.document_id),
                "strategy": STRATEGY_NAME,
                "version": STRATEGY_VERSION,
                "configuration_fingerprint": configuration_fingerprint,
                "ordinal": ordinal,
                "provenance": [
                    {
                        "element_id": str(item.normalised_element_id),
                        "element_start": item.element_start_character,
                        "element_end": item.element_end_character,
                        "role": item.role,
                    }
                    for item in contributions
                ],
                "text": text,
            },
            ensure_ascii=False,
            separators=(",", ":"),
            sort_keys=True,
        )
        return Chunk(
            id=uuid5(_CHUNK_NAMESPACE, identity_material),
            ordinal=ordinal,
            text=text,
            token_count=self._tokenizer.count(text),
            contributions=tuple(contributions),
        )

    @staticmethod
    def _join(pieces: tuple[_Piece, ...] | list[_Piece]) -> str:
        return "\n\n".join(piece.text for piece in pieces)

    @staticmethod
    def _assert_complete(
        document: NormalisedDocument, chunks: tuple[Chunk, ...]
    ) -> None:
        primary_by_element: dict[UUID, list[tuple[int, int]]] = {}
        for chunk in chunks:
            for contribution in chunk.contributions:
                if contribution.role == "primary":
                    primary_by_element.setdefault(
                        contribution.normalised_element_id, []
                    ).append(
                        (
                            contribution.element_start_character,
                            contribution.element_end_character,
                        )
                    )

        for element in document.elements:
            if not element.text and isinstance(element, NormalisedUnknownElement):
                continue
            spans = sorted(primary_by_element.get(element.id, []))
            cursor = 0
            for start, end in spans:
                if start != cursor:
                    raise UnrepresentableContentError(
                        f"Element {element.id} has incomplete primary provenance."
                    )
                cursor = end
            if cursor != len(element.text):
                raise UnrepresentableContentError(
                    f"Element {element.id} has incomplete primary provenance."
                )
