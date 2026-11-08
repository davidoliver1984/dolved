"""Grounded interpretation contract; document authority remains Laravel-owned."""

import re
from datetime import date, datetime
from typing import Annotated, Literal
from uuid import UUID

from pydantic import Field, StrictBool, StringConstraints, model_validator

from app.extraction.models import ImmutableModel
from app.retrieval.models import OperationUsage, PlannerLineage

Text = Annotated[str, StringConstraints(min_length=1, pattern=r"\S")]
NeedId = Literal["n1", "n2", "n3"]
Precision = Literal["day", "month", "year", "interval"]


def validate_date_grounding(value: str, precision: str, quote: str) -> None:
    """Recognise explicit calendar notation only, never infer temporal intent."""
    if precision == "interval":
        for endpoint in value.split("/"):
            validate_date_grounding(endpoint, "day", quote)
        return
    if precision == "named":
        return
    if re.search(r"(?<![0-9])" + re.escape(value) + r"(?![0-9])", quote):
        return
    parts = value.split("-")
    if len(parts) < 2:
        raise ValueError("year is not supported by user quote")
    month_names = (
        "January",
        "February",
        "March",
        "April",
        "May",
        "June",
        "July",
        "August",
        "September",
        "October",
        "November",
        "December",
    )
    month = month_names[int(parts[1]) - 1]
    name = f"(?:{month}|{month[:3]}\\.?)"
    if precision == "month":
        pattern = rf"\b{name}\s+{parts[0]}\b"
    else:
        day = int(parts[2])
        pattern = rf"\b(?:0?{day}(?:st|nd|rd|th)?\s+{name}\s+{parts[0]}|{name}\s+0?{day}(?:st|nd|rd|th)?,?\s+{parts[0]})\b"
    if re.search(pattern, quote, re.IGNORECASE) is None:
        raise ValueError("calendar value/precision is not supported by user quote")


def validate_date_value(value: str, precision: str) -> None:
    """Validate declared precision without inferring intent from question wording."""
    if precision == "named":
        return
    if precision == "interval":
        endpoints = value.split("/")
        if len(endpoints) != 2:
            raise ValueError("date interval requires two day endpoints")
        for endpoint in endpoints:
            validate_date_value(endpoint, "day")
        if endpoints[0] > endpoints[1]:
            raise ValueError("date interval is reversed")
        return
    patterns = {
        "day": r"[0-9]{4}-[0-9]{2}-[0-9]{2}",
        "month": r"[0-9]{4}-[0-9]{2}",
        "year": r"[0-9]{4}",
    }
    if precision not in patterns or re.fullmatch(patterns[precision], value) is None:
        raise ValueError("date value does not match declared precision")
    date.fromisoformat(value + {"day": "", "month": "-01", "year": "-01-01"}[precision])


class UserTurn(ImmutableModel):
    turn_id: Text
    text: Annotated[
        str, StringConstraints(min_length=1, max_length=8000, pattern=r"\S")
    ]


class QuestionSpan(ImmutableModel):
    turn_id: Text
    quote: Text


class NeedAuthority(ImmutableModel):
    kind: Literal[
        "current",
        "date",
        "period",
        "previous",
        "historical_reference",
        "transition_period",
        "transition_boundary",
    ]
    value: Text | None
    precision: Literal["none", "day", "month", "year", "interval", "named"]
    source: QuestionSpan | None

    @model_validator(mode="after")
    def coherent_reference(self) -> NeedAuthority:
        if self.kind == "current":
            if (
                self.value is not None
                or self.source is not None
                or self.precision != "none"
            ):
                raise ValueError("current authority has no invented temporal reference")
        else:
            if self.value is None or self.source is None or self.precision == "none":
                raise ValueError("non-current authority requires a grounded reference")
            allowed = {
                "date": {"day"},
                "period": {"month", "year", "interval"},
                "previous": {"named"},
                "historical_reference": {"named"},
                "transition_period": {"month", "year", "interval"},
                "transition_boundary": {"day"},
            }
            if self.precision not in allowed[self.kind]:
                raise ValueError("authority kind and precision disagree")
            validate_date_value(self.value, self.precision)
        return self


class FactReference(ImmutableModel):
    value: Text
    precision: Precision
    source: QuestionSpan

    @model_validator(mode="after")
    def coherent_date(self) -> FactReference:
        try:
            validate_date_value(self.value, self.precision)
        except ValueError as error:
            if str(error) in {
                "date value does not match declared precision",
                "date interval requires two day endpoints",
                "date interval is reversed",
            }:
                raise ValueError("fact reference " + str(error)) from None
            raise ValueError("fact reference invalid calendar date") from None
        return self


class NeedApplicability(ImmutableModel):
    kind: Literal["generic", "named", "relational"]
    source: QuestionSpan | None

    @model_validator(mode="after")
    def grounded_specificity(self) -> NeedApplicability:
        if self.kind != "generic" and self.source is None:
            raise ValueError("specific applicability requires a user reference")
        return self


class ClaimUnderTest(ImmutableModel):
    version: Literal["claim-under-test-v1"]
    type: Literal["named_recipient", "named_value", "numeric_value"]
    relation: Literal[
        "submission_destination",
        "contact_route",
        "numeric_rate",
        "named_value",
        "other_fact",
    ]
    source: QuestionSpan


class EvidenceNeed(ImmutableModel):
    need_id: NeedId
    aspect: Text
    central: StrictBool
    question_span: QuestionSpan
    operation: Literal["retrieve", "compare", "scope_enquiry"]
    authority: NeedAuthority
    fact_reference: FactReference | None
    applicability: NeedApplicability
    claim_under_test: ClaimUnderTest | None = None
    scope_question: Literal["membership", "breadth"] | None = None
    supports_need_id: NeedId | None

    @model_validator(mode="after")
    def transition_requires_comparison(self) -> EvidenceNeed:
        if self.operation == "compare" and self.authority.kind == "current":
            raise ValueError("comparison requires a historical or transition selector")
        if (
            self.authority.kind.startswith("transition_")
            and self.operation != "compare"
        ):
            raise ValueError("transition authority requires comparison")
        if self.operation != "scope_enquiry" and self.scope_question is not None:
            raise ValueError("scope question is only for scope enquiries")
        if self.operation == "scope_enquiry" and self.scope_question is None:
            raise ValueError("scope enquiries require a typed question")
        if self.operation == "scope_enquiry" and self.claim_under_test is not None:
            raise ValueError("scope enquiries cannot test a disputed value")
        return self


class CatalogueFamily(ImmutableModel):
    family_id: UUID
    title: Annotated[str, StringConstraints(min_length=1, max_length=500)]


class NeedBinding(ImmutableModel):
    need_id: NeedId
    subject_status: Literal["matched", "topic", "unavailable", "ambiguous"]
    family_ids: tuple[UUID, ...] = Field(max_length=8)
    ownership: Literal["authorised_workspace", "external_tenant"]
    ownership_source: QuestionSpan | None

    @model_validator(mode="after")
    def coherent_binding(self) -> NeedBinding:
        if len(set(self.family_ids)) != len(self.family_ids):
            raise ValueError("duplicate catalogue identity")
        if bool(self.family_ids) != (self.subject_status in {"matched", "ambiguous"}):
            raise ValueError("subject status and catalogue identities disagree")
        if self.subject_status == "ambiguous" and len(self.family_ids) < 2:
            raise ValueError("ambiguity requires alternative catalogue identities")
        if (self.ownership_source is not None) != (self.ownership == "external_tenant"):
            raise ValueError("external ownership must have an exact user reference")
        return self


class EvidenceNeedsPlan(ImmutableModel):
    schema_version: Literal["evidence-needs-plan-v3"]
    decision: Literal["plan", "clarify"]
    clarification: Text | None
    needs: tuple[EvidenceNeed, ...] = Field(max_length=3)
    bindings: tuple[NeedBinding, ...] = Field(default=(), max_length=3)

    def validate_catalogue(
        self, catalogue: tuple[CatalogueFamily, ...], turns: tuple[UserTurn, ...]
    ) -> None:
        if [b.need_id for b in self.bindings] != [n.need_id for n in self.needs]:
            raise ValueError("every need requires exactly one catalogue binding")
        allowed = {f.family_id for f in catalogue}
        texts = {t.turn_id: t.text for t in turns}
        for binding, need in zip(self.bindings, self.needs, strict=True):
            if not set(binding.family_ids).issubset(allowed):
                raise ValueError(
                    "family identity was not supplied by the authorised catalogue"
                )
            source = binding.ownership_source
            if source is not None and (
                source.turn_id not in texts or source.quote not in texts[source.turn_id]
            ):
                raise ValueError("ownership reference is not grounded")
            if (
                binding.ownership == "external_tenant"
                and need.applicability.kind != "generic"
            ):
                raise ValueError(
                    "external tenant ownership cannot become a location restriction"
                )

    @model_validator(mode="after")
    def coherent_plan(self) -> EvidenceNeedsPlan:
        if self.decision == "clarify":
            if self.needs or self.clarification is None:
                raise ValueError("clarification requires a question and no needs")
            return self
        if (
            not self.needs
            or self.clarification is not None
            or not any(n.central for n in self.needs)
        ):
            raise ValueError("plan requires central needs and no clarification")
        if [n.need_id for n in self.needs] != [
            f"n{i + 1}" for i in range(len(self.needs))
        ]:
            raise ValueError("need identifiers must be unique and sequential")
        edges: dict[str, str | None] = {
            n.need_id: n.supports_need_id for n in self.needs
        }
        for origin in edges:
            seen: set[str] = set()
            node: str | None = origin
            while node is not None:
                if node not in edges or node in seen:
                    raise ValueError("support reference is missing or cyclic")
                seen.add(node)
                node = edges[node]
        return self

    def in_question_order(self, turns: tuple[UserTurn, ...]) -> EvidenceNeedsPlan:
        """Reconcile presentation order, preserving every grounded need and edge."""
        self.validate_grounding(turns, require_order=False)
        if [b.need_id for b in self.bindings] != [n.need_id for n in self.needs]:
            raise ValueError("every need requires exactly one catalogue binding")
        positions = {t.turn_id: (i, t.text) for i, t in enumerate(turns)}
        ordered = sorted(
            self.needs,
            key=lambda n: (
                positions[n.question_span.turn_id][0],
                positions[n.question_span.turn_id][1].index(n.question_span.quote),
            ),
        )
        ids = {n.need_id: f"n{i + 1}" for i, n in enumerate(ordered)}
        bindings = {b.need_id: b for b in self.bindings}
        value = self.model_dump(mode="json")
        value["needs"] = [
            {
                **n.model_dump(mode="json"),
                "need_id": ids[n.need_id],
                "supports_need_id": ids[n.supports_need_id]
                if n.supports_need_id is not None
                else None,
            }
            for n in ordered
        ]
        value["bindings"] = [
            {**bindings[n.need_id].model_dump(mode="json"), "need_id": ids[n.need_id]}
            for n in ordered
        ]
        result = EvidenceNeedsPlan.model_validate(value)
        result.validate_grounding(turns)
        return result

    def validate_grounding(
        self, turns: tuple[UserTurn, ...], *, require_order: bool = True
    ) -> None:
        by_id = {turn.turn_id: turn.text for turn in turns}
        if len(by_id) != len(turns):
            raise ValueError("user turn identifiers must be unique")
        turn_order = {turn.turn_id: index for index, turn in enumerate(turns)}
        previous_position = (-1, -1)
        for need in self.needs:
            spans = [
                need.question_span,
                need.authority.source,
                need.applicability.source,
                need.claim_under_test.source
                if need.claim_under_test is not None
                else None,
            ]
            if need.fact_reference is not None:
                spans.append(need.fact_reference.source)
            for span in spans:
                if span is not None and (
                    span.turn_id not in by_id or span.quote not in by_id[span.turn_id]
                ):
                    raise ValueError(
                        "plan reference is not grounded in an authorised user turn"
                    )
            position = (
                turn_order[need.question_span.turn_id],
                by_id[need.question_span.turn_id].index(need.question_span.quote),
            )
            if require_order and position < previous_position:
                raise ValueError("needs must retain user question order")
            previous_position = position
            if need.authority.value is not None and need.authority.source is not None:
                validate_date_grounding(
                    need.authority.value,
                    need.authority.precision,
                    need.authority.source.quote,
                )
                if (
                    need.authority.kind == "historical_reference"
                    and need.authority.value not in need.authority.source.quote
                ):
                    raise ValueError(
                        "named historical reference must retain its wording"
                    )
            if need.fact_reference is not None:
                validate_date_grounding(
                    need.fact_reference.value,
                    need.fact_reference.precision,
                    need.fact_reference.source.quote,
                )


class NeedsPlanRequest(ImmutableModel):
    contract_version: Literal[2]
    request_id: UUID
    workspace_id: UUID
    evaluated_at: datetime
    user_turns: tuple[UserTurn, ...] = Field(min_length=1, max_length=20)
    catalogue: tuple[CatalogueFamily, ...] = Field(default=(), max_length=500)

    @model_validator(mode="after")
    def unique_turns(self) -> NeedsPlanRequest:
        if len({t.turn_id for t in self.user_turns}) != len(self.user_turns):
            raise ValueError("duplicate user turn identifier")
        if len({f.family_id for f in self.catalogue}) != len(self.catalogue):
            raise ValueError("duplicate catalogue identity")
        return self


class NeedsOperationUsage(OperationUsage):
    validation_attempts: tuple[dict[str, object], ...] = Field(default=(), max_length=2)


class NeedsPlanResponse(ImmutableModel):
    contract_version: Literal[2] = 2
    request_id: UUID
    plan: EvidenceNeedsPlan
    classifier_lineage: PlannerLineage
    usage: NeedsOperationUsage
