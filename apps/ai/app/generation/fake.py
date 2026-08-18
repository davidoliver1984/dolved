from app.generation.models import (
    AnswerPart,
    AnswerPartCandidate,
    GenerationOutcome,
    GenerationRequest,
    GenerationResult,
    GenerationStreamEvent,
)


class DeterministicGenerator:
    """Provider-free contract fake; it makes no semantic quality claim."""

    def generate(self, request: GenerationRequest) -> GenerationResult:
        first = request.evidence[0]
        return GenerationResult(
            outcome=GenerationOutcome.ANSWERED,
            answer_parts=(
                AnswerPart(text=first.text, evidence_ids=(first.evidence_id,)),
            ),
            usage={
                "execution": "local",
                "request_count": 0,
                "cost_basis": "zero_cost_local",
                "cost_usd": 0,
            },
        )

    def stream(self, request: GenerationRequest):
        result = self.generate(request)
        sequence = 1
        for part in result.answer_parts:
            yield GenerationStreamEvent(
                request_id=request.request_id,
                sequence=sequence,
                event_type="answer_part_candidate",
                candidate=AnswerPartCandidate(
                    text=part.text, evidence_ids=part.evidence_ids
                ),
            )
            sequence += 1
        yield GenerationStreamEvent(
            request_id=request.request_id,
            sequence=sequence,
            event_type="generation_completed",
            result=result,
        )
