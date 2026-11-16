"""Current-policy scope projection; does not resolve authority or answerability."""

from copy import deepcopy
from typing import Literal

from pydantic import Field, ValidationError

from app.retrieval.needs import (
    CatalogueFamily,
    EvidenceNeed,
    EvidenceNeedsPlan,
    NeedsPlanRequest,
    NeedsPlanResponse,
    QuestionSpan,
    UserTurn,
    compiled_request,
)

CONTRACT = "current-policy-needs-v1"
PROMPT_VERSION = "current-policy-scope-v1"
ADAPTER_VERSION = "structured-current-policy-v1"
SCOPE_FIELDS = {
    "requested_information",
    "current_spans",
    "history_spans",
    "scope_error",
}
SCOPE_PROMPT = """
This is the current-policy product. Return current-policy-needs-v1.
For every need identify requested_information independently of document authority:
current_policy, history, mixed, or unclear. History includes what applied at a past
date, earlier versions, introduction dates, what changed and explanations of changes,
even if today's document describes them. Current requirements and calculations under
current rules remain current_policy when events or dates are background context.
An authorised event record (log, minutes, drill record) is in scope: preserve its
identity/date as context, not a request for earlier policy. Current approval does
not establish that a record is latest. Do not infer recency or answer facts.
For mixed ONLY provide current_spans and history_spans: exact nonoverlapping clauses
within that need's question_span. Each list has at most four spans, together at most
8000 characters. Otherwise both lists are empty. Do not duplicate clauses, silently
truncate, or classify an entire clear mixed request as unclear. Preserve necessary
referents using grounded context_spans, excluding declined history from current context.
Current-only needs use current document authority. Mixed needs retain the original
temporal interpretation; the application separately projects only the current clauses.
Unclear means genuinely ambiguous user meaning, not an awkward decomposition.
Do not ask a user to supply a historical date solely to answer an excluded history
question: represent that portion as history. Preserve independently current portions.
Do not output scope_error: it is application-owned. No permissions or versions are
selected by this scope classification. Use the existing three-need limit.
"""


class CurrentPolicyNeed(EvidenceNeed):
    requested_information: (
        Literal["current_policy", "history", "mixed", "unclear"] | None
    )
    current_spans: tuple[QuestionSpan, ...] = Field(max_length=4)
    history_spans: tuple[QuestionSpan, ...] = Field(max_length=4)
    scope_error: Literal["invalid_scope", "dependent_scope"] | None = None


class CurrentPolicyPlan(EvidenceNeedsPlan):
    # Pydantic wire variant deliberately replaces the legacy version discriminator.
    schema_version: Literal["current-policy-needs-v1"]  # type: ignore[assignment]
    needs: tuple[CurrentPolicyNeed, ...] = Field(max_length=3)

    def execution_plan(self) -> CurrentPolicyPlan:
        # The checker sees ALL clauses and their scope; not just admitted clauses.
        return self.model_copy(
            update={
                "needs": tuple(
                    n.model_copy(update={"aspect": compiled_request(n)})
                    for n in self.needs
                )
            }
        )

    def in_question_order(self, turns, *, evaluated_at=None):
        # Reuse the existing ordering/binding reconciliation, preserving annotations.
        base = base_plan(self.model_dump(mode="json"))
        ordered = base.in_question_order(turns, evaluated_at=evaluated_at)
        positions = {t.turn_id: (i, t.text) for i, t in enumerate(turns)}
        originals = sorted(
            self.needs,
            key=lambda n: (
                positions[n.question_span.turn_id][0],
                positions[n.question_span.turn_id][1].index(n.question_span.quote),
            ),
        )
        value = ordered.model_dump(mode="json")
        value["schema_version"] = CONTRACT
        for need, original in zip(value["needs"], originals, strict=True):
            need.update({k: original.model_dump(mode="json")[k] for k in SCOPE_FIELDS})
        return CurrentPolicyPlan.model_validate(value)


class CurrentPolicyRequest(NeedsPlanRequest):
    contract_version: Literal[3]  # type: ignore[assignment]  # New signed wire version.
    product_profile: Literal["current-policy-pool-v1"]


class CurrentPolicyResponse(NeedsPlanResponse):
    contract_version: Literal[3] = 3  # type: ignore[assignment]  # New signed wire version.
    plan: CurrentPolicyPlan


def base_plan(value: dict) -> EvidenceNeedsPlan:
    if not isinstance(value, dict):
        raise TypeError("invalid base envelope")
    stripped = deepcopy(value)
    if stripped.get("schema_version") != CONTRACT:
        raise ValueError("wrong current-policy contract")
    stripped["schema_version"] = "evidence-needs-plan-v4"
    if not isinstance(stripped.get("needs"), list) or any(
        not isinstance(n, dict) for n in stripped["needs"]
    ):
        raise TypeError("invalid base needs")
    for need in stripped["needs"]:
        for field in SCOPE_FIELDS:
            need.pop(field, None)
    return EvidenceNeedsPlan.model_validate(stripped)


def validate_scope(need: CurrentPolicyNeed) -> None:
    if need.requested_information is None or need.scope_error is not None:
        raise ValueError("invalid scope annotation")
    spans = (*need.current_spans, *need.history_spans)
    if need.requested_information != "mixed":
        if spans:
            raise ValueError("partition only permitted for mixed scope")
        if need.requested_information == "current_policy" and (
            need.authority.kind != "current" or need.operation == "compare"
        ):
            raise ValueError("current request has incompatible authority")
        return
    if not need.current_spans or not need.history_spans:
        raise ValueError("mixed scope requires both partitions")
    if sum(len(s.quote) for s in spans) > 8000:
        raise ValueError("partition capacity exceeded")
    intervals: list[tuple[int, int]] = []
    for span in spans:
        quote = need.question_span.quote
        if span.turn_id != need.question_span.turn_id or quote.count(span.quote) != 1:
            raise ValueError("partition must identify one exact clause")
        start = quote.index(span.quote)
        end = start + len(span.quote)
        if any(start < b and a < end for a, b in intervals):
            raise ValueError("duplicate or overlapping clauses")
        intervals.append((start, end))
    for context in need.context_spans:
        if any(h.quote in context.quote for h in need.history_spans):
            raise ValueError("historical clause in current context")
    current_text = " ".join(s.quote for s in (*need.context_spans, *need.current_spans))
    for source in (
        need.applicability.source,
        need.fact_reference.source if need.fact_reference else None,
        need.claim_under_test.source if need.claim_under_test else None,
    ):
        if source is not None and source.quote not in current_text:
            raise ValueError("current projection loses grounded restriction")


def parse_policy_plan(
    value: dict,
    turns: tuple[UserTurn, ...],
    catalogue: tuple[CatalogueFamily, ...],
    evaluated_at: str,
    *,
    recover: bool,
) -> CurrentPolicyPlan:
    # All base, identity, provenance and reference checks precede local recovery.
    base = base_plan(value)
    base.validate_grounding(turns, require_order=False, evaluated_at=evaluated_at)
    base.validate_catalogue(catalogue, turns)
    base.execution_plan()
    parsed = deepcopy(value)
    invalid = set()
    for need in parsed["needs"]:
        if "scope_error" in need:
            raise ValueError("provider cannot assert application scope disposition")
        try:
            validate_scope(CurrentPolicyNeed.model_validate(need))
        except ValidationError, ValueError:
            if not recover:
                raise ValueError("invalid scope annotation") from None
            invalid.add(need["need_id"])
            need.update(
                requested_information=None,
                current_spans=[],
                history_spans=[],
                scope_error="invalid_scope",
            )
    # Never recover a need whose interpretation depends on a quarantined neighbour.
    changed = True
    while changed:
        changed = False
        for need in parsed["needs"]:
            if need["supports_need_id"] in invalid and need["need_id"] not in invalid:
                if not recover:
                    raise ValueError("dependent invalid scope")
                invalid.add(need["need_id"])
                need.update(
                    requested_information=None,
                    current_spans=[],
                    history_spans=[],
                    scope_error="dependent_scope",
                )
                changed = True
    return CurrentPolicyPlan.model_validate(parsed)


def dispositions(plan: CurrentPolicyPlan) -> list[dict]:
    result = []
    for need in plan.needs:
        kind = need.requested_information
        result.append(
            {
                "need_id": need.need_id,
                "version": "current-policy-disposition-v2",
                "affected_spans": []
                if kind == "current_policy" and not need.scope_error
                else [s.model_dump() for s in need.history_spans]
                if kind == "mixed"
                else [need.question_span.model_dump()],
                "status": "interpretation_failure"
                if need.scope_error
                else {
                    "current_policy": "admitted",
                    "history": "scope_declined",
                    "mixed": "partial_scope",
                    "unclear": "ambiguity",
                }[str(kind)],
                "reason": need.scope_error,
                "declined_spans": [s.model_dump() for s in need.history_spans]
                if kind == "mixed"
                else [need.question_span.model_dump()]
                if kind == "history"
                else [],
            }
        )
    return result
