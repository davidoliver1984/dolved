from __future__ import annotations

from enum import StrEnum
from typing import Annotated, Literal
from uuid import UUID

from pydantic import Field, StringConstraints, model_validator

from app.extraction.models import ImmutableModel
from app.retrieval.models import RequestedEvidenceType, RetrievalSide

NonEmptyText = Annotated[str, StringConstraints(min_length=1)]
BoundedText = Annotated[
    str, StringConstraints(strip_whitespace=True, min_length=1, max_length=8000)
]
EvidenceId = Annotated[str, StringConstraints(pattern=r"^ev-[0-9]{2,}$")]


class GenerationOutcome(StrEnum):
    ANSWERED = "answered"
    QUALIFIED = "qualified"
    INSUFFICIENT_EVIDENCE = "insufficient_evidence"


class GenerationEvidence(ImmutableModel):
    evidence_id: EvidenceId
    text: NonEmptyText
    document_chunk_id: int = Field(gt=0)
    document_id: int = Field(gt=0)
    ingestion_event_claim_id: int = Field(gt=0)
    source_provenance: tuple[dict[str, object], ...] = Field(min_length=1)
    temporal_authority: dict[str, object]
    applicability_context: dict[str, object]
    side: RetrievalSide


class GenerationConstraints(ImmutableModel):
    context_policy_version: BoundedText
    max_context_characters: int = Field(gt=0)
    required_sides: tuple[RetrievalSide, ...] = Field(min_length=1, max_length=2)
    requested_evidence_type: RequestedEvidenceType = (
        RequestedEvidenceType.POLICY_OR_PROCEDURAL_REQUIREMENTS
    )

    @model_validator(mode="after")
    def validate_sides(self) -> GenerationConstraints:
        if len(set(self.required_sides)) != len(self.required_sides):
            raise ValueError("required generation sides must be unique")
        return self


class GenerationRequest(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    workspace_id: UUID
    question: BoundedText
    evidence: tuple[GenerationEvidence, ...] = Field(min_length=1, max_length=100)
    constraints: GenerationConstraints

    @model_validator(mode="after")
    def validate_evidence(self) -> GenerationRequest:
        handles = tuple(item.evidence_id for item in self.evidence)
        if len(set(handles)) != len(handles):
            raise ValueError("evidence handles must be unique")
        available_sides = {item.side for item in self.evidence}
        if not set(self.constraints.required_sides).issubset(available_sides):
            raise ValueError("every required side must have evidence")
        return self


class AnswerPart(ImmutableModel):
    text: NonEmptyText
    evidence_ids: tuple[EvidenceId, ...] = Field(min_length=1)

    @model_validator(mode="after")
    def validate_evidence_ids(self) -> AnswerPart:
        if not self.text.strip():
            raise ValueError("answer-part text must contain non-whitespace prose")
        if len(set(self.evidence_ids)) != len(self.evidence_ids):
            raise ValueError("answer-part evidence handles must be unique")
        return self


class GenerationResult(ImmutableModel):
    contract_version: Literal[1] = 1
    outcome: GenerationOutcome
    answer_parts: tuple[AnswerPart, ...] = ()
    unsupported_aspects: tuple[BoundedText, ...] = ()
    insufficiency_reason: BoundedText | None = None
    usage: dict[str, object] | None = None

    @model_validator(mode="after")
    def validate_outcome(self) -> GenerationResult:
        if self.outcome is GenerationOutcome.ANSWERED:
            valid = (
                bool(self.answer_parts)
                and not self.unsupported_aspects
                and self.insufficiency_reason is None
            )
        elif self.outcome is GenerationOutcome.QUALIFIED:
            valid = (
                bool(self.answer_parts)
                and bool(self.unsupported_aspects)
                and self.insufficiency_reason is None
            )
        else:
            valid = (
                not self.answer_parts
                and bool(self.unsupported_aspects)
                and self.insufficiency_reason is not None
            )
        if not valid:
            raise ValueError("generation outcome fields do not satisfy their invariant")
        return self

    def validate_against(self, request: GenerationRequest) -> GenerationResult:
        authorised = {item.evidence_id for item in request.evidence}
        cited = {item for part in self.answer_parts for item in part.evidence_ids}
        if not cited.issubset(authorised):
            raise ValueError("generation result cites evidence outside the request")
        return self


class GenerationContextBudgetExceeded(ImmutableModel):
    code: Literal["GENERATION_CONTEXT_BUDGET_EXCEEDED"] = (
        "GENERATION_CONTEXT_BUDGET_EXCEEDED"
    )
    policy_version: BoundedText
    proposed_units: int = Field(ge=0)
    maximum_units: int = Field(gt=0)


class GenerationProviderError(ImmutableModel):
    category: Literal[
        "transport_failure",
        "rate_limit",
        "timeout",
        "malformed_typed_output",
        "provider_refusal",
        "contract_validation_failure",
    ]
    provider: str | None = None
    model: str | None = None
    http_status: int | None = None
    attempt_count: int = Field(ge=1)
    latency_ms: float = Field(ge=0)


class GenerationResponse(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    status: Literal["completed", "context_budget_exceeded", "provider_error"]
    result: GenerationResult | None = None
    failure: GenerationContextBudgetExceeded | None = None
    error: GenerationProviderError | None = None

    @model_validator(mode="after")
    def exactly_one_shape(self) -> GenerationResponse:
        present = sum(
            value is not None for value in (self.result, self.failure, self.error)
        )
        expected = {
            "completed": self.result,
            "context_budget_exceeded": self.failure,
            "provider_error": self.error,
        }[self.status]
        if present != 1 or expected is None:
            raise ValueError(
                "generation response must contain exactly its tagged payload"
            )
        return self


class AnswerPartCandidate(ImmutableModel):
    text: NonEmptyText
    evidence_ids: tuple[EvidenceId, ...] = Field(min_length=1)

    @model_validator(mode="after")
    def validate_candidate(self) -> AnswerPartCandidate:
        AnswerPart(text=self.text, evidence_ids=self.evidence_ids)
        return self

    def validate_against(self, request: GenerationRequest) -> AnswerPartCandidate:
        authorised = {item.evidence_id for item in request.evidence}
        if not set(self.evidence_ids).issubset(authorised):
            raise ValueError("generation candidate cites evidence outside the request")
        return self


class GenerationStreamEvent(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    sequence: int = Field(ge=1)
    event_type: Literal[
        "answer_part_candidate",
        "generation_completed",
        "generation_failed",
        "context_budget_exceeded",
    ]
    candidate: AnswerPartCandidate | None = None
    result: GenerationResult | None = None
    error: GenerationProviderError | None = None
    failure: GenerationContextBudgetExceeded | None = None

    @model_validator(mode="after")
    def exactly_one_stream_shape(self) -> GenerationStreamEvent:
        fields = {
            "answer_part_candidate": self.candidate,
            "generation_completed": self.result,
            "generation_failed": self.error,
            "context_budget_exceeded": self.failure,
        }
        if sum(value is not None for value in fields.values()) != 1:
            raise ValueError("generation stream event must contain one tagged payload")
        if fields[self.event_type] is None:
            raise ValueError("generation stream event payload does not match its type")
        return self
