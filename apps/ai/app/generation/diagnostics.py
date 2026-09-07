"""Static validation diagnostics: never persist input, exception prose or context."""

from pydantic import ValidationError

RULES: dict[str, tuple[str, str]] = {
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


def validation_diagnostic(exception: ValueError) -> dict[str, str]:
    # Exact static messages originate in our validators, not arbitrary output text.
    messages = [str(exception)]
    if isinstance(exception, ValidationError):
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
