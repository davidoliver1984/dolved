"""Versioned ADR-0038 output adaptation; legacy generation stays unchanged."""

from __future__ import annotations

from typing import Annotated, Literal
from uuid import UUID

from pydantic import Field, TypeAdapter, model_validator

from app.extraction.models import ImmutableModel
from app.generation.aspect_coverage import AspectLink, validate_aspect_links
from app.generation.diagnostics import CitationOwnershipError
from app.generation.entailment import PartEntailment
from app.generation.models import (
    AnswerPart,
    BoundedText,
    GenerationContextBudgetExceeded,
    GenerationOutcome,
    GenerationProviderError,
    GenerationRequest,
    GenerationResult,
)
from app.generation.needs_request import GenerationNeedsRequest
from app.generation.openai_adapter import OpenAIGenerationOutput
from app.generation.polarity import has_invalid_leading_polarity


class NeedsCompletedResponse(ImmutableModel):
    contract_version: Literal[2]
    request_id: UUID
    status: Literal["completed"]
    result: GenerationNeedsResult


class NeedsBudgetResponse(ImmutableModel):
    contract_version: Literal[2]
    request_id: UUID
    status: Literal["context_budget_exceeded"]
    failure: GenerationContextBudgetExceeded


class NeedsProviderResponse(ImmutableModel):
    contract_version: Literal[2]
    request_id: UUID
    status: Literal["provider_error"]
    error: GenerationProviderError


class GenerationNeedsResult(ImmutableModel):
    contract_version: Literal[3]
    outcome: GenerationOutcome
    answer_parts: tuple[AnswerPart, ...]
    unsupported_aspects: tuple[BoundedText, ...]
    insufficiency_reason: BoundedText | None
    usage: dict[str, object] | None
    aspect_links: tuple[AspectLink, ...] = Field(min_length=1, max_length=3)
    entailment_checks: tuple[PartEntailment, ...]

    def authoritative_result(self) -> GenerationResult:
        # Reuse exactly the established shape/citation checks, not a relaxed copy.
        return GenerationResult.model_validate(
            self.model_dump(
                exclude={"contract_version", "aspect_links", "entailment_checks"}
            )
        )

    @model_validator(mode="after")
    def validate_shape(self) -> GenerationNeedsResult:
        self.authoritative_result()
        for part in self.answer_parts:
            if has_invalid_leading_polarity(part.text):
                raise ValueError("detached yes/no framing is not an evidence statement")
        if [check.part_index for check in self.entailment_checks] != list(
            range(len(self.answer_parts))
        ) or any(check.verdict != "supported" for check in self.entailment_checks):
            raise ValueError("answer entailment checks must support every answer part")
        return self

    def validate_against(
        self, request: GenerationRequest, need_ids: tuple[str, ...]
    ) -> GenerationNeedsResult:
        result = self.authoritative_result().validate_against(request)
        validate_aspect_links(result, self.aspect_links, need_ids)
        return self

    def validate_for_needs_request(
        self, request: GenerationNeedsRequest
    ) -> GenerationNeedsResult:
        self.validate_against(
            request.legacy_request(),
            tuple(need.need_id for need in request.evidence_needs),
        )
        owners = {
            need.need_id: set(need.evidence_ids) for need in request.evidence_needs
        }
        for link in self.aspect_links:
            for index in link.answer_part_indices:
                if not set(self.answer_parts[index].evidence_ids).issubset(
                    owners[link.need_id]
                ):
                    raise CitationOwnershipError(
                        link.need_id,
                        index,
                        self.answer_parts[index].evidence_ids,
                        owners[link.need_id],
                    )
            if not owners[link.need_id] and (
                link.answer_part_indices or not link.unsupported_aspect_indices
            ):
                raise ValueError("need without evidence requires an explicit gap")
        return self


class OpenAIGenerationNeedsOutput(OpenAIGenerationOutput):
    aspect_links: list[AspectLink] = Field(
        min_length=1,
        max_length=3,
        description=(
            "Exactly one link per requested need, using zero-based indices into "
            "this response's answer_parts and unsupported_aspects. No independent "
            "status or additional answer text. Map every part and gap."
        ),
    )

    def to_result(
        self,
        request: GenerationRequest,
        need_ids: tuple[str, ...],
        usage: dict[str, object] | None = None,
        entailment_checks: tuple[PartEntailment, ...] = (),
    ) -> GenerationNeedsResult:
        return GenerationNeedsResult.model_validate(
            {
                **self.model_dump(),
                "contract_version": 3,
                "usage": usage,
                "entailment_checks": [item.model_dump() for item in entailment_checks],
            }
        ).validate_against(request, need_ids)


NeedsCompletedResponse.model_rebuild()
NEEDS_RESPONSE: TypeAdapter[
    NeedsCompletedResponse | NeedsBudgetResponse | NeedsProviderResponse
] = TypeAdapter(
    Annotated[
        NeedsCompletedResponse | NeedsBudgetResponse | NeedsProviderResponse,
        Field(discriminator="status"),
    ]
)
