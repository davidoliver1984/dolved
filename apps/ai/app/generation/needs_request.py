"""Request-side need and evidence ownership for the bounded production slice."""

from __future__ import annotations

from typing import Literal
from uuid import UUID

from pydantic import Field, StrictBool, model_validator

from app.extraction.models import ImmutableModel
from app.generation.models import (
    BoundedText,
    EvidenceId,
    GenerationConstraints,
    GenerationEvidence,
    GenerationRequest,
)
from app.generation.temporal_targets import TemporalTarget
from app.retrieval.needs import (
    ClaimUnderTest,
    FactReference,
    NeedApplicability,
    NeedAuthority,
    QuestionSpan,
    compiled_request,
)


class GenerationNeed(ImmutableModel):
    need_id: Literal["n1", "n2", "n3"]
    aspect: BoundedText
    # Additive compatibility for retained v2 requests; production forwards the
    # span already grounded by the planner contract, without re-paraphrasing it.
    question_span: QuestionSpan | None = None
    context_spans: tuple[QuestionSpan, ...] = Field(default=(), max_length=4)
    request_text_version: Literal["extractive-request-v1"] | None = None
    central: StrictBool
    operation: Literal["retrieve", "compare"]
    authority: NeedAuthority
    fact_reference: FactReference | None
    applicability: NeedApplicability
    claim_under_test: ClaimUnderTest | None = None
    retrieval_outcome: BoundedText
    retrieval_reason: BoundedText | None
    evidence_ids: tuple[EvidenceId, ...]
    temporal_targets: tuple[TemporalTarget, ...] = Field(default=(), max_length=9)


class GenerationNeedsRequest(ImmutableModel):
    contract_version: Literal[2, 3, 4]
    request_id: UUID
    workspace_id: UUID
    question: BoundedText
    evidence: tuple[GenerationEvidence, ...] = Field(min_length=1, max_length=5)
    constraints: GenerationConstraints
    evidence_needs: tuple[GenerationNeed, ...] = Field(min_length=1, max_length=3)

    def legacy_request(self) -> GenerationRequest:
        return GenerationRequest.model_validate(
            self.model_dump(exclude={"contract_version", "evidence_needs"})
        )

    @model_validator(mode="after")
    def validate_ownership(self) -> GenerationNeedsRequest:
        self.legacy_request()
        needs = self.evidence_needs
        if len({need.need_id for need in needs}) != len(needs) or not any(
            need.central for need in needs
        ):
            raise ValueError(
                "generation needs must be unique and include a central need"
            )
        if sum(2 if need.operation == "compare" else 1 for need in needs) > 4:
            raise ValueError("generation slice allows at most four lanes")
        by_id = {item.evidence_id: item for item in self.evidence}
        assigned: set[str] = set()
        for need in needs:
            if self.contract_version >= 3 and (
                need.request_text_version != "extractive-request-v1"
                or need.question_span is None
                or need.aspect != compiled_request(need)
            ):
                raise ValueError("generation requires the compiled extractive request")
            if need.temporal_targets:
                if self.contract_version != 4 or need.operation != "retrieve":
                    raise ValueError("temporal targets require the C1 envelope")
                if len({t.target_id for t in need.temporal_targets}) != len(
                    need.temporal_targets
                ):
                    raise ValueError("duplicate temporal target")
                for target in need.temporal_targets:
                    if target.need_id != need.need_id:
                        raise ValueError("temporal target need mismatch")
                    if target.reference is not None and not any(
                        span
                        and span.turn_id == target.reference.turn_id
                        and target.reference.quote in span.quote
                        for span in (need.question_span, *need.context_spans)
                    ):
                        raise ValueError("temporal reference is not grounded")
            ids = need.evidence_ids
            if len(set(ids)) != len(ids) or not set(ids).issubset(by_id):
                raise ValueError("need evidence references must be unique and supplied")
            if assigned.intersection(ids):
                raise ValueError("each evidence item has exactly one need owner")
            assigned.update(ids)
            families: dict[str, dict[str, set[int]]] = {}
            for handle in ids:
                item = by_id[handle]
                family = item.temporal_authority.get("document_family_id")
                version = item.temporal_authority.get("document_version_id")
                if item.temporal_authority.get("need_id") != need.need_id:
                    raise ValueError("evidence need ownership mismatch")
                if not isinstance(family, str) or not isinstance(version, str):
                    raise ValueError("versioned family provenance is required")  # noqa: TRY004 -- Pydantic must report a validation error, not leak TypeError.
                UUID(family)
                UUID(version)
                families.setdefault(family, {}).setdefault(item.side.value, set()).add(
                    item.document_id
                )
            for sides in families.values():
                if need.operation == "compare":
                    primary = sides.get("primary", set())
                    comparison = sides.get("comparison", set())
                    if (
                        not primary
                        or not comparison
                        or primary.intersection(comparison)
                    ):
                        raise ValueError(
                            "comparison needs a distinct pair in each family"
                        )
                elif "comparison" in sides:
                    raise ValueError("retrieve needs cannot use a comparison lane")
        if assigned != set(by_id):
            raise ValueError("all supplied evidence must belong to a requested need")
        return self
