from app.generation.models import (
    AnswerPart,
    GenerationOutcome,
    GenerationRequest,
    GenerationResult,
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
