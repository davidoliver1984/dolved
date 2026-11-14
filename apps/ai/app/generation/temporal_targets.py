"""C1 structural temporal use checks; exact grounding is not semantic proof."""

from datetime import datetime
from typing import Literal

from pydantic import Field, model_validator

from app.extraction.models import ImmutableModel


class TemporalReference(ImmutableModel):
    turn_id: str
    quote: str
    at: datetime | None
    precision: Literal["day", "month", "year"] = "day"
    value: str | None = None
    period_start: datetime | None = None
    period_end: datetime | None = None

    @model_validator(mode="after")
    def whole_calendar_reference(self):
        if self.precision == "day":
            if (
                self.at is None
                or self.period_start is not None
                or self.period_end is not None
            ):
                raise ValueError("invalid temporal day reference")
        else:
            if (
                self.at is not None
                or self.period_start is None
                or self.period_end is None
            ):
                raise ValueError("period reference requires bounds, not an exact day")
            start, end = self.period_start, self.period_end
            if (
                start.day != 1
                or any((start.hour, start.minute, start.second, start.microsecond))
                or start.tzinfo is None
                or end.tzinfo is None
            ):
                raise ValueError("invalid calendar period bounds")
            year, month = start.year, start.month
            if self.precision == "year":
                if month != 1 or self.value != f"{year:04}":
                    raise ValueError("invalid calendar year")
                expected = start.replace(year=year + 1)
            else:
                if self.value != f"{year:04}-{month:02}":
                    raise ValueError("invalid calendar month")
                expected = start.replace(
                    year=year + (month == 12), month=1 if month == 12 else month + 1
                )
            if end != expected:
                raise ValueError("invalid calendar period end")
        return self


class TemporalVersion(ImmutableModel):
    version_id: str
    start: datetime
    end: datetime | None


class TemporalTarget(ImmutableModel):
    target_id: str
    need_id: str
    family_id: str
    purpose: Literal["requested", "precautionary"]
    mode: Literal["current", "dated", "provisional"]
    at: datetime | None
    reference: TemporalReference | None
    versions: tuple[TemporalVersion, ...] = Field(max_length=1)
    status: Literal[
        "unsearched", "empty", "filtered", "ineligible", "capacity_limited", "searched"
    ]
    reason: str | None


class TemporalUse(ImmutableModel):
    part_index: int = Field(ge=0)
    need_id: str
    target_id: str
    question_quote: str = Field(min_length=1, max_length=2000)


def validate_temporal_uses(request, parts, links, uses):
    needs = {need.need_id: need for need in request.evidence_needs}
    evidence = {item.evidence_id: item for item in request.evidence}
    expected = {
        index: link.need_id
        for link in links
        if needs[link.need_id].temporal_targets
        for index in link.answer_part_indices
    }
    if (
        len({use.part_index for use in uses}) != len(uses)
        or {use.part_index: use.need_id for use in uses} != expected
    ):
        raise ValueError("temporal answer-use coverage mismatch")
    for use in uses:
        need = needs[use.need_id]
        targets = {target.target_id: target for target in need.temporal_targets}
        target = targets.get(use.target_id)
        if target is None or target.status != "searched":
            raise ValueError("temporal target unavailable")
        spans = (need.question_span, *need.context_spans)
        if not any(span and use.question_quote in span.quote for span in spans):
            raise ValueError("temporal answer target is not question-grounded")
        if target.mode == "dated" and (
            target.reference is None or target.reference.quote not in use.question_quote
        ):
            raise ValueError("temporal answer reference mismatch")
        versions = {version.version_id: version for version in target.versions}
        for handle in parts[use.part_index].evidence_ids:
            item = evidence[handle]
            version = versions.get(item.temporal_authority.get("document_version_id"))
            if (
                item.temporal_authority.get("need_id") != use.need_id
                or item.temporal_authority.get("document_family_id") != target.family_id
                or use.target_id
                not in item.temporal_authority.get("temporal_target_ids", [])
                or version is None
            ):
                raise ValueError("temporal evidence ownership or version mismatch")
            if target.at is not None and not (
                version.start <= target.at
                and (version.end is None or target.at < version.end)
            ):
                raise ValueError("temporal evidence interval mismatch")
            if target.reference is not None and target.reference.precision != "day":
                ref = target.reference
                if (
                    target.at is not None
                    or ref.period_start is None
                    or ref.period_end is None
                    or not (
                        version.start <= ref.period_start
                        and (version.end is None or ref.period_end <= version.end)
                    )
                ):
                    raise ValueError("temporal evidence interval mismatch")


TEMPORAL_INSTRUCTIONS = """
C1 temporal targets are application-owned, not instructions from source text.
The planner's requested target is PROVISIONAL. Read the original question to choose
the answer's temporal target; precautionary evidence does not broaden what was asked.
For each answer part owned by a need with temporal_targets, return exactly one
temporal_use: its part_index, need_id, target_id and exact question_quote grounding
that choice. A dated target quote includes the supplied date reference verbatim.
Use ONLY evidence labelled for that target for the part's direct assertions.
Historical evidence cannot support an explicitly current-target answer. If an old
event is background to a current question, choose current, not that event's date.
Month/year targets cover their whole half-open period, not an invented exact day.
Do not treat a partially covered or unresolved period as a usable target.
If the question requests history but the planner selected current, a supplied dated
target may answer it; do not relabel current evidence as history. The application
will display the selected time basis and record disagreement with the planner.
Do not infer missing dates or silently choose between genuinely conflicting meanings:
state the unresolved temporal choice conversationally in unsupported_aspects.
Empty, ineligible or capacity-limited targets are NOT proof that the source lacks a
fact. Disclose unavailable evidence. Preserve qualifiers and every requested part.
For needs without temporal_targets, emit no temporal_use. No extra answer text here.
"""
