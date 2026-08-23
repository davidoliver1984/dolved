import json
from pathlib import Path
from uuid import UUID

import pytest

from app.conversation.deterministic import CatalogueQueryContextualizer
from app.conversation.models import ContextTurn, ContextualisationRequest
from app.conversation.openai_adapter import ContextualisationError
from app.generation.errors import GenerationProviderFailure
from app.generation.fake import CatalogueDeterministicGenerator
from app.generation.models import GenerationRequest

QUESTION = "What must staff do when a dose is omitted?"
FOLLOW_UP = "What exact time limit applies?"
RESOLVED_FOLLOW_UP = "What exact time limit applies to recording an omitted dose?"


def catalogue(tmp_path: Path) -> Path:
    path = tmp_path / "scenario-catalogue.json"
    path.write_text(
        json.dumps(
            {
                "schema_version": 1,
                "entries": [
                    {
                        "question": QUESTION,
                        "contextualisation_inputs": [
                            {
                                "message": QUESTION,
                                "result": {
                                    "status": "resolved",
                                    "resolved_query": QUESTION,
                                    "used_prior_context": False,
                                    "used_turn_ordinals": [],
                                    "clarification_question": None,
                                },
                            }
                        ],
                        "generation": {
                            "outcome": "answered",
                            "text": "Record the omission and assess immediate safety.",
                            "unsupported_aspects": [],
                        },
                    },
                    {
                        "question": RESOLVED_FOLLOW_UP,
                        "contextualisation_inputs": [
                            {
                                "message": FOLLOW_UP,
                                "result": {
                                    "status": "resolved",
                                    "resolved_query": RESOLVED_FOLLOW_UP,
                                    "used_prior_context": True,
                                    "used_turn_ordinals": [1],
                                    "clarification_question": None,
                                },
                            }
                        ],
                        "generation": {
                            "outcome": "insufficient_evidence",
                            "unsupported_aspects": ["an exact recording time limit"],
                            "insufficiency_reason": "No exact time limit is established.",
                        },
                    },
                ],
            }
        )
    )
    return path


def contextualisation_request(
    message: str, *, history: tuple[ContextTurn, ...] = ()
) -> ContextualisationRequest:
    return ContextualisationRequest(
        request_id=UUID("11111111-1111-4111-8111-111111111111"),
        workspace_id=UUID("22222222-2222-4222-8222-222222222222"),
        current_message=message,
        history=history,
        context_policy_version="bounded-turn-pairs-v1",
    )


def generation_request(question: str) -> GenerationRequest:
    return GenerationRequest.model_validate(
        {
            "request_id": "33333333-3333-4333-8333-333333333333",
            "workspace_id": "22222222-2222-4222-8222-222222222222",
            "question": question,
            "evidence": [
                {
                    "evidence_id": "ev-01",
                    "text": "Record the omission and assess immediate safety.",
                    "document_chunk_id": 1,
                    "document_id": 2,
                    "ingestion_event_claim_id": 3,
                    "source_provenance": [{"kind": "text"}],
                    "temporal_authority": {"mode": "current"},
                    "applicability_context": {"scope": "universal"},
                    "side": "primary",
                }
            ],
            "constraints": {
                "context_policy_version": "whole-evidence-v1",
                "max_context_characters": 1000,
                "required_sides": ["primary"],
            },
        }
    )


def test_catalogue_contextualiser_resolves_exact_inputs_and_bounded_history(
    tmp_path: Path,
) -> None:
    contextualiser = CatalogueQueryContextualizer(str(catalogue(tmp_path)))
    direct = contextualiser.contextualize(contextualisation_request(QUESTION))
    history = (
        ContextTurn(
            user_message=QUESTION,
            assistant_message="Record the omission.",
            assistant_kind="grounded_answer",
            user_ordinal=1,
            assistant_ordinal=2,
        ),
    )
    follow_up = contextualiser.contextualize(
        contextualisation_request(FOLLOW_UP, history=history)
    )

    assert direct.resolved_query == QUESTION
    assert direct.used_prior_context is False
    assert direct.usage is not None and direct.usage["request_count"] == 0
    assert follow_up.resolved_query == RESOLVED_FOLLOW_UP
    assert follow_up.used_prior_context is True
    assert follow_up.interpretation_metadata is not None
    assert follow_up.interpretation_metadata.used_turn_ordinals == (1,)


def test_catalogue_contextualiser_fails_closed_for_unknown_or_missing_history(
    tmp_path: Path,
) -> None:
    contextualiser = CatalogueQueryContextualizer(str(catalogue(tmp_path)))
    with pytest.raises(ContextualisationError, match="not authorised"):
        contextualiser.contextualize(contextualisation_request("Unknown question"))
    with pytest.raises(ContextualisationError, match="requires prior context"):
        contextualiser.contextualize(contextualisation_request(FOLLOW_UP))


def test_catalogue_generator_returns_grounded_and_controlled_results(
    tmp_path: Path,
) -> None:
    generator = CatalogueDeterministicGenerator(str(catalogue(tmp_path)))
    answered = generator.generate(generation_request(QUESTION))
    insufficient = generator.generate(generation_request(RESOLVED_FOLLOW_UP))

    assert answered.outcome.value == "answered"
    assert answered.answer_parts[0].evidence_ids == ("ev-01",)
    assert answered.usage is not None and answered.usage["request_count"] == 0
    assert insufficient.outcome.value == "insufficient_evidence"
    assert insufficient.answer_parts == ()
    assert insufficient.unsupported_aspects == ("an exact recording time limit",)


def test_catalogue_generator_fails_closed_for_unknown_question(tmp_path: Path) -> None:
    generator = CatalogueDeterministicGenerator(str(catalogue(tmp_path)))
    with pytest.raises(GenerationProviderFailure) as failure:
        generator.generate(generation_request("Unknown question"))
    assert failure.value.error.category == "contract_validation_failure"
