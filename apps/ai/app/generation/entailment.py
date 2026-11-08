"""Independent, bounded evidence-to-answer checks for needs generation.

The deterministic guard is deliberately narrow: it recognises a conditional
obligation in the *same cited sentence* as the obligation being asserted. It is
not a general English entailment parser. The separate structured review covers
the remaining material claims and never changes eligibility or answer text.
"""

from __future__ import annotations

import json
import re
from typing import Literal

from pydantic import BaseModel, ConfigDict, Field, model_validator

from app.generation.needs_request import GenerationNeedsRequest
from app.generation.openai_adapter import OpenAIGenerationOutput


class PartEntailment(BaseModel):
    model_config = ConfigDict(extra="forbid", frozen=True)

    part_index: int = Field(ge=0, le=127)
    verdict: Literal["supported", "unsupported", "uncertain"]
    missing_condition: str | None = Field(max_length=500)

    @model_validator(mode="after")
    def require_missing_condition(self) -> PartEntailment:
        if self.verdict == "supported" and self.missing_condition is not None:
            raise ValueError("supported verdict cannot name a missing condition")
        if self.verdict != "supported" and not self.missing_condition:
            raise ValueError("unsupported verdict must name a missing condition")
        return self


class EntailmentReview(BaseModel):
    model_config = ConfigDict(extra="forbid", frozen=True)

    parts: list[PartEntailment]


ENTAILMENT_INSTRUCTIONS = """You are a separate evidence-to-claim reviewer, not an answerer.
For each answer part, examine EVERY material factual claim against only that part's
cited passages and the application-resolved metadata supplied with those passages.
Return exactly one verdict per part. Mark supported only if every material claim
follows, including every condition attached to a duty, named-site applicability,
temporal status, negation and quantifier. A universal manual does not establish
that a separately scoped procedure covers a named site. If a cited duty is
conditional, an unconditional named-site duty needs a separate resolved fact
proving the condition. Do not infer that a condition holds merely because the
named site exists. Mark unsupported for a contradicted claim, uncertain for an
unestablished condition, and name the specific missing condition. Do not rewrite
the answer, add evidence, or decide eligibility. The supplied metadata is already
resolved by the application; never reinterpret workspace, family or authority.
"""


def review_input(
    request: GenerationNeedsRequest, output: OpenAIGenerationOutput
) -> str:
    evidence = {item.evidence_id: item for item in request.evidence}
    return json.dumps(
        [
            {
                "part_index": index,
                "claim_text": part.text,
                "cited_passages": [
                    {
                        "evidence_id": handle,
                        "text": evidence[handle].text,
                        "temporal_authority": evidence[handle].temporal_authority,
                        "applicability_context": evidence[handle].applicability_context,
                    }
                    for handle in part.evidence_ids
                ],
            }
            for index, part in enumerate(output.answer_parts)
        ],
        ensure_ascii=False,
        sort_keys=True,
        separators=(",", ":"),
    )


class EntailmentRejection(ValueError):
    def __init__(self, code: str, part_index: int, missing_condition: str) -> None:
        super().__init__(code)
        self.code = code
        self.part_index = part_index
        self.missing_condition = missing_condition


_OBLIGATION = re.compile(
    r"\b(?:must|need(?:s)? to|require[ds]?|check)\b", re.IGNORECASE
)
_SITE_CONDITION = re.compile(
    r"\b(?:at an? in[\u2011-\u2013-]?scope site|only (?:if|when|at) [^.;,]+|(?:if|when) [^.;,]+)",
    re.IGNORECASE,
)
_SENTENCE = re.compile(r"(?<=[.!?])\s+|\n+")
_TERMS = re.compile(r"[a-z]{4,}")
_STOP = frozenset(
    {
        "before",
        "after",
        "with",
        "from",
        "that",
        "this",
        "there",
        "site",
        "scope",
        "work",
        "must",
        "need",
        "needs",
        "required",
        "check",
        "checking",
    }
)


def conditional_obligation_guard(
    request: GenerationNeedsRequest, output: OpenAIGenerationOutput
) -> None:
    """Reject only an affirmative named-site duty dropping its cited scope clause.

    The cited sentence must itself contain both the duty and its condition; a
    qualifier elsewhere in a passage (or an unrelated "subject to") is ignored.
    No substring match against a list of locations grants applicability.
    """
    evidence = {item.evidence_id: item for item in request.evidence}
    for index, part in enumerate(output.answer_parts):
        owners = [
            need
            for need in request.evidence_needs
            if any(
                link.need_id == need.need_id and index in link.answer_part_indices
                for link in getattr(output, "aspect_links", ())
            )
        ]
        for need in owners:
            source = need.applicability.source
            if need.applicability.kind != "named" or source is None:
                continue
            site = source.quote
            for answer_sentence in _SENTENCE.split(part.text):
                if (
                    site.casefold() not in answer_sentence.casefold()
                    or not _OBLIGATION.search(answer_sentence)
                ):
                    continue
                if _SITE_CONDITION.search(answer_sentence):
                    continue
                answer_terms = set(_TERMS.findall(answer_sentence.casefold())) - _STOP
                for handle in part.evidence_ids:
                    cited = evidence[handle]
                    for source_sentence in _SENTENCE.split(cited.text):
                        condition = _SITE_CONDITION.search(source_sentence)
                        if not condition or not _OBLIGATION.search(source_sentence):
                            continue
                        source_terms = (
                            set(_TERMS.findall(source_sentence.casefold())) - _STOP
                        )
                        if len(answer_terms & source_terms) >= 2:
                            resolved = cited.applicability_context.get(
                                "resolved_scope_fact"
                            )
                            if (
                                isinstance(resolved, dict)
                                and resolved.get("result") == "applies"
                                and resolved.get("location_name") == site
                                and resolved.get("condition") == condition.group(0)
                                and resolved.get("document_version_id")
                                == cited.temporal_authority.get("document_version_id")
                            ):
                                continue
                            raise EntailmentRejection(
                                "site_scope_condition_unproven",
                                index,
                                condition.group(0),
                            )
