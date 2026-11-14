"""Static validation diagnostics: never persist input, exception prose or context."""

from pydantic import ValidationError

from app.generation.models import CitationOwnershipDiagnostic


class CitationOwnershipError(ValueError):
    def __init__(
        self, need_id: str, index: int, cited: tuple[str, ...], allowed: set[str]
    ):
        super().__init__("answer part cites evidence belonging to another need")
        self.ownership: CitationOwnershipDiagnostic | None = None
        try:
            self.ownership = CitationOwnershipDiagnostic.model_validate(
                {
                    "need_id": need_id,
                    "answer_part_index": index,
                    "cited_ids": cited,
                    "allowed_ids": sorted(allowed),
                }
            )
        except ValidationError:
            # Diagnostic bounds must never replace the actual rejection.
            pass


RULES: dict[str, tuple[str, str]] = {
    **{
        message: ("result", "temporal_target_invalid")
        for message in (
            "temporal answer-use coverage mismatch",
            "temporal target unavailable",
            "temporal answer target is not question-grounded",
            "temporal answer reference mismatch",
            "temporal evidence ownership or version mismatch",
            "temporal evidence interval mismatch",
        )
    },
    "provider output violates the generation outcome invariant": (
        "outcome",
        "outcome_invariant",
    ),
    "provider answer parts require prose and evidence": (
        "answer_parts.text",
        "blank_answer",
    ),
    "detached yes/no framing is not an evidence statement": (
        "answer_parts.text",
        "detached_polarity",
    ),
    "generation outcome fields do not satisfy their invariant": (
        "outcome",
        "outcome_invariant",
    ),
    "generation result cites evidence outside the request": (
        "answer_parts.evidence_ids",
        "citation_membership",
    ),
    "answer-part evidence handles must be unique": (
        "answer_parts.evidence_ids",
        "duplicate_citation",
    ),
    "answer-part text must contain non-whitespace prose": (
        "answer_parts.text",
        "blank_answer",
    ),
    "aspect links must cover each requested need exactly once": (
        "aspect_links",
        "need_coverage",
    ),
    "aspect references must be unique and in range": (
        "aspect_links",
        "reference_range",
    ),
    "every need must reference an answer or an explicit gap": (
        "aspect_links",
        "empty_need",
    ),
    "every authoritative answer part and gap must be mapped": (
        "aspect_links",
        "unmapped_content",
    ),
    "generation outcome contradicts aspect coverage": (
        "aspect_links",
        "coverage_outcome",
    ),
    "answer part cites evidence belonging to another need": (
        "answer_parts.evidence_ids",
        "citation_ownership",
    ),
    "need without evidence requires an explicit gap": (
        "aspect_links",
        "missing_evidence_gap",
    ),
}


def validation_diagnostic(exception: ValueError) -> dict[str, object]:
    from app.generation.entailment import EntailmentRejection

    if isinstance(exception, EntailmentRejection):
        return {"field": "answer_parts.text", "code": exception.code}
    if isinstance(exception, CitationOwnershipError):
        return {
            "field": "answer_parts.evidence_ids",
            "code": "citation_ownership",
            "ownership": exception.ownership.model_dump(mode="json")
            if exception.ownership is not None
            else None,
        }
    # Exact static messages originate in our validators, not arbitrary output text.
    messages = [str(exception)]
    if isinstance(exception, ValidationError):
        if any(
            "temporal_uses" in e["loc"] for e in exception.errors(include_input=False)
        ):
            return {"field": "result", "code": "temporal_target_invalid"}
        messages = [
            e["msg"].removeprefix("Value error, ")
            for e in exception.errors(
                include_input=False, include_context=False, include_url=False
            )
        ]
    for message in messages:
        if message in RULES:
            field, code = RULES[message]
            return {"field": field, "code": code}
    return {"field": "result", "code": "schema_validation"}
