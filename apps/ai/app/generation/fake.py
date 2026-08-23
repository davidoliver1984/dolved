import json
from pathlib import Path

from app.generation.errors import GenerationProviderFailure
from app.generation.models import (
    AnswerPart,
    AnswerPartCandidate,
    GenerationOutcome,
    GenerationProviderError,
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


class CatalogueDeterministicGenerator(DeterministicGenerator):
    """Scenario-bound generator for the isolated deterministic E2E profile."""

    def __init__(self, catalogue_path: str) -> None:
        try:
            payload = json.loads(Path(catalogue_path).read_bytes())
            if payload.get("schema_version") != 1:
                raise ValueError("unsupported catalogue version")
            entries = payload.get("entries")
            if not isinstance(entries, list) or not entries:
                raise ValueError("catalogue entries are required")
            scenarios: dict[str, dict[str, object]] = {}
            for entry in entries:
                question = entry.get("question") if isinstance(entry, dict) else None
                scenario = entry.get("generation") if isinstance(entry, dict) else None
                if (
                    not isinstance(question, str)
                    or not question.strip()
                    or question in scenarios
                    or not isinstance(scenario, dict)
                ):
                    raise ValueError("generation scenarios must be unique and complete")
                scenarios[question] = scenario
            self._scenarios = scenarios
        except (OSError, TypeError, ValueError, json.JSONDecodeError) as exception:
            raise ValueError(
                "The deterministic generation catalogue is invalid."
            ) from exception

    def generate(self, request: GenerationRequest) -> GenerationResult:
        try:
            scenario = self._scenarios[request.question]
            outcome_value = scenario["outcome"]
            if not isinstance(outcome_value, str):
                raise TypeError("generation outcome must be a string")
            outcome = GenerationOutcome(outcome_value)
            if outcome is GenerationOutcome.INSUFFICIENT_EVIDENCE:
                result = GenerationResult.model_validate(
                    {
                        "outcome": outcome,
                        "unsupported_aspects": scenario["unsupported_aspects"],
                        "insufficiency_reason": scenario["insufficiency_reason"],
                        "usage": self._usage(),
                    }
                )
            else:
                evidence_ids = tuple(item.evidence_id for item in request.evidence)
                result = GenerationResult.model_validate(
                    {
                        "outcome": outcome,
                        "answer_parts": [
                            {
                                "text": scenario["text"],
                                "evidence_ids": evidence_ids[:1],
                            }
                        ],
                        "unsupported_aspects": scenario.get("unsupported_aspects", []),
                        "usage": self._usage(),
                    }
                )
            return result.validate_against(request)
        except (KeyError, TypeError, ValueError) as exception:
            raise GenerationProviderFailure(
                GenerationProviderError(
                    category="contract_validation_failure",
                    provider="deterministic",
                    model="catalogue-grounded-generator",
                    attempt_count=1,
                    latency_ms=0,
                )
            ) from exception

    def _usage(self) -> dict[str, object]:
        return {
            "execution": "local",
            "provider": "deterministic",
            "model": "catalogue-grounded-generator",
            "request_count": 0,
            "retry_count": 0,
            "cost_basis": "zero_cost_local",
            "cost_usd": 0,
        }
