from __future__ import annotations

from typing import Annotated, Literal
from uuid import UUID

from pydantic import Field, StringConstraints, model_validator

from app.extraction.models import ImmutableModel

BoundedText = Annotated[
    str, StringConstraints(strip_whitespace=True, min_length=1, max_length=8000)
]
VersionText = Annotated[
    str, StringConstraints(strip_whitespace=True, min_length=1, max_length=255)
]
AssistantText = Annotated[
    str, StringConstraints(strip_whitespace=True, min_length=1, max_length=24000)
]


class ContextTurn(ImmutableModel):
    user_message: BoundedText
    assistant_message: AssistantText
    assistant_kind: Literal["grounded_answer", "clarification", "no_answer"]
    user_ordinal: int = Field(ge=1)
    assistant_ordinal: int = Field(ge=1)

    @model_validator(mode="after")
    def ordered(self) -> ContextTurn:
        if self.assistant_ordinal <= self.user_ordinal:
            raise ValueError("assistant history must follow its user message")
        return self


class ContextualisationRequest(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    workspace_id: UUID
    current_message: BoundedText
    history: tuple[ContextTurn, ...] = Field(max_length=3)
    context_policy_version: VersionText

    @model_validator(mode="after")
    def ordered_history(self) -> ContextualisationRequest:
        ordinals = [item.user_ordinal for item in self.history]
        if ordinals != sorted(ordinals) or len(ordinals) != len(set(ordinals)):
            raise ValueError("context turns must be uniquely ordered")
        return self


class InterpretationMetadata(ImmutableModel):
    used_turn_ordinals: tuple[int, ...] = Field(max_length=3)

    @model_validator(mode="after")
    def unique_ordered_ordinals(self) -> InterpretationMetadata:
        if any(value < 1 for value in self.used_turn_ordinals):
            raise ValueError("used turn ordinals must be positive")
        if tuple(sorted(set(self.used_turn_ordinals))) != self.used_turn_ordinals:
            raise ValueError("used turn ordinals must be uniquely ordered")
        return self


class ContextualisationResult(ImmutableModel):
    status: Literal["resolved", "clarification_required"]
    resolved_query: BoundedText | None = None
    used_prior_context: bool
    interpretation_metadata: InterpretationMetadata | None = None
    clarification_question: (
        Annotated[
            str, StringConstraints(strip_whitespace=True, min_length=1, max_length=1000)
        ]
        | None
    ) = None
    contextualiser_version: VersionText
    usage: dict[str, object] | None = None

    @model_validator(mode="after")
    def valid_shape(self) -> ContextualisationResult:
        if self.status == "resolved":
            valid = (
                self.resolved_query is not None and self.clarification_question is None
            )
        else:
            valid = (
                self.resolved_query is None and self.clarification_question is not None
            )
        if not valid:
            raise ValueError("contextualisation result fields do not match status")
        return self


class ContextualisationResponse(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    result: ContextualisationResult
