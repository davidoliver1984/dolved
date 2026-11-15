"""Source-anchored review receipts, not a deterministic semantic proof.

This is the structured output of the existing review call. It cannot add evidence
or change its authority. Exact quotation and association checks are application
facts; situation/requirement equivalence remains the reviewer's judgement.
"""

from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator

from app.generation.entailment import EntailmentRejection, PartEntailment
from app.generation.temporal_targets import association_evidence

SUPPORT_REVIEW_VERSION = "need-source-support-v1"


class ClaimSupport(BaseModel):
    model_config = ConfigDict(extra="forbid", frozen=True)

    need_id: str = Field(min_length=1, max_length=32)
    answer_quote: str = Field(min_length=1, max_length=8000)
    evidence_id: str = Field(min_length=1, max_length=32)
    source_quote: str = Field(min_length=1, max_length=8000)
    same_situation: Literal["established", "different", "unestablished"]
    requirement_preserved: Literal["established", "changed", "unestablished"]

    @field_validator("answer_quote", "source_quote")
    @classmethod
    def substantive_quote(cls, value: str) -> str:
        if not any(character.isalnum() for character in value):
            raise ValueError("support quotation requires substantive text")
        return value


class SupportedPart(PartEntailment):
    claims: list[ClaimSupport] = Field(max_length=64)


class SourceSupportReview(BaseModel):
    model_config = ConfigDict(extra="forbid", frozen=True)

    parts: list[SupportedPart] = Field(max_length=128)


SUPPORT_INSTRUCTIONS = """
Return one part receipt with claims grounded in exact source quotations. Split
material claims when their situation, requirement or supporting source differs.
For each claim identify its owning need, copy the answer wording and copy the
supporting passage with its relevant condition/qualifier intact. Account for the
whole answer text, not merely a convenient supported fragment. More than one
receipt may cover the same answer phrase when jointly supported.
Judge same_situation separately: a source for a related event, symptom, role or
procedure does not establish the instruction for the event actually asked about.
Judge requirement_preserved separately: keep mandatory conjunctions, alternatives,
conditions, quantities and qualifiers; do not turn a required contact into a
choice of contacts or transfer a duty from a different situation. Explicitly
conditional guidance is permissible when its condition remains explicit and the
answer does not claim that it holds. A source quote's presence proves its text,
not that it supports the claim. Unsupported or unestablished relationships must
produce an unsupported/uncertain verdict, not a fabricated supporting quote.
Use only the supplied association's allowed evidence handles. An answer paragraph
shared by needs does not share their evidence authority. Application-selected
versions and intervals remain authoritative. Never repair or rewrite an answer.
"""


def validate_support(
    request, output, review: SourceSupportReview
) -> list[PartEntailment]:
    """Check receipt provenance/coverage and enforce explicit semantic verdicts.

    Coverage here is text coverage only, *not* proof of semantic completeness.
    Rejection uses the established entailment path and its unchanged retry ceiling.
    """
    if [part.part_index for part in review.parts] != list(
        range(len(output.answer_parts))
    ):
        raise EntailmentRejection(
            "entailment_uncertain", 0, "review coverage incomplete"
        )
    evidence = {item.evidence_id: item for item in request.evidence}
    checks = []
    for part in review.parts:
        index = part.part_index
        if part.verdict != "supported":
            raise EntailmentRejection(
                "entailment_unsupported"
                if part.verdict == "unsupported"
                else "entailment_uncertain",
                index,
                part.missing_condition or "condition unestablished",
            )
        answer = output.answer_parts[index]
        owners = {
            link.need_id
            for link in output.aspect_links
            if index in link.answer_part_indices
        }
        covered: set[int] = set()
        reviewed_owners = set()
        for claim in part.claims:
            if claim.need_id not in owners:
                raise EntailmentRejection(
                    "entailment_uncertain", index, "support need not associated"
                )
            allowed = association_evidence(
                request,
                output.answer_parts,
                getattr(output, "temporal_uses", ()),
                claim.need_id,
                index,
            )
            need = next(
                need for need in request.evidence_needs if need.need_id == claim.need_id
            )
            if (
                claim.evidence_id not in allowed
                or claim.evidence_id not in need.evidence_ids
            ):
                raise EntailmentRejection(
                    "entailment_uncertain", index, "support evidence not owned"
                )
            if claim.source_quote not in evidence[claim.evidence_id].text:
                raise EntailmentRejection(
                    "entailment_uncertain", index, "support quotation not in source"
                )
            if claim.answer_quote not in answer.text:
                raise EntailmentRejection(
                    "entailment_uncertain", index, "support quotation not in answer"
                )
            if (
                claim.same_situation != "established"
                or claim.requirement_preserved != "established"
            ):
                raise EntailmentRejection(
                    "entailment_uncertain",
                    index,
                    "same-situation requirement not established",
                )
            start = 0
            while (start := answer.text.find(claim.answer_quote, start)) >= 0:
                covered.update(range(start, start + len(claim.answer_quote)))
                start += len(claim.answer_quote)
            reviewed_owners.add(claim.need_id)
        if reviewed_owners != owners or any(
            char.isalnum() and offset not in covered
            for offset, char in enumerate(answer.text)
        ):
            raise EntailmentRejection(
                "entailment_uncertain", index, "support receipt coverage incomplete"
            )
        checks.append(
            PartEntailment(
                part_index=index, verdict="supported", missing_condition=None
            )
        )
    return checks
