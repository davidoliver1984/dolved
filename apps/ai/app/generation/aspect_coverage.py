"""ADR-0038 structural coverage checks; no semantic inference or provider calls."""

from typing import Literal

from pydantic import StrictInt

from app.extraction.models import ImmutableModel
from app.generation.models import GenerationResult


class AspectLink(ImmutableModel):
    need_id: Literal["n1", "n2", "n3"]
    answer_part_indices: tuple[StrictInt, ...]
    unsupported_aspect_indices: tuple[StrictInt, ...]


def validate_aspect_links(
    result: GenerationResult,
    links: tuple[AspectLink, ...],
    need_ids: tuple[str, ...],
) -> None:
    """Validate references/outcome; Laravel owns the persisted status projection.

    GenerationResult must first pass its normal shape and citation membership
    checks. Coverage is not proof that a cited passage entails an answer.
    """
    if (
        not 1 <= len(need_ids) <= 3
        or len(set(need_ids)) != len(need_ids)
        or not set(need_ids).issubset({"n1", "n2", "n3"})
        or len(links) != len(need_ids)
        or {link.need_id for link in links} != set(need_ids)
    ):
        raise ValueError("aspect links must cover each requested need exactly once")
    part_refs: set[int] = set()
    gap_refs: set[int] = set()
    statuses: set[str] = set()
    for link in links:
        for indices, count in (
            (link.answer_part_indices, len(result.answer_parts)),
            (link.unsupported_aspect_indices, len(result.unsupported_aspects)),
        ):
            if len(indices) != len(set(indices)) or any(
                i < 0 or i >= count for i in indices
            ):
                raise ValueError("aspect references must be unique and in range")
        if not link.answer_part_indices and not link.unsupported_aspect_indices:
            raise ValueError("every need must reference an answer or an explicit gap")
        part_refs.update(link.answer_part_indices)
        gap_refs.update(link.unsupported_aspect_indices)
        statuses.add(
            "partial"
            if link.answer_part_indices and link.unsupported_aspect_indices
            else "supported"
            if link.answer_part_indices
            else "unsupported"
        )
    if part_refs != set(range(len(result.answer_parts))) or gap_refs != set(
        range(len(result.unsupported_aspects))
    ):
        raise ValueError("every authoritative answer part and gap must be mapped")
    valid = {
        "answered": statuses == {"supported"},
        "qualified": bool(statuses & {"supported", "partial"})
        and bool(statuses & {"partial", "unsupported"}),
        "insufficient_evidence": statuses == {"unsupported"},
    }.get(result.outcome.value, False)
    if not valid:
        raise ValueError("generation outcome contradicts aspect coverage")
