from __future__ import annotations

import base64
import hashlib
import hmac
import json
import os
import time
from typing import TypedDict
from uuid import uuid4

import pytest
from fastapi.testclient import TestClient

from app.generation.factory import build_generator
from app.generation.models import GenerationOutcome, GenerationRequest
from app.generation.routes import generator_dependency
from app.main import app
from app.settings import Settings

pytestmark = pytest.mark.integration


class LiveFixture(TypedDict):
    id: str
    question: str
    evidence: tuple[str, ...]
    expected: GenerationOutcome


FIXTURES: tuple[LiveFixture, ...] = (
    {
        "id": "qualified_quarantine_duration_regression",
        "question": "How many hours must quarantined medicine stay out of use?",
        "evidence": (
            "Keep the medicine quarantined until medicine-specific pharmacy advice is recorded.",
        ),
        "expected": GenerationOutcome.QUALIFIED,
    },
    {
        "id": "qualified_lone_worker_numeric_regression",
        "question": "How many minutes can a lone worker be overdue before escalation?",
        "evidence": (
            "If a lone worker misses check-out, staff must follow the escalation procedure immediately.",
        ),
        "expected": GenerationOutcome.QUALIFIED,
    },
    {
        "id": "answered_independent_control",
        "question": "When must the incident record be completed?",
        "evidence": (
            "The incident record must be completed before the end of the shift.",
        ),
        "expected": GenerationOutcome.ANSWERED,
    },
    {
        "id": "insufficient_independent_control",
        "question": "What dismissal appeal process must managers follow?",
        "evidence": ("Staff must complete annual fire-safety training.",),
        "expected": GenerationOutcome.INSUFFICIENT_EVIDENCE,
    },
    {
        "id": "qualified_missing_actor_independent_control",
        "question": "Who authorises reopening the room and what must happen first?",
        "evidence": (
            "The room must remain closed until an infection-control review is complete.",
        ),
        "expected": GenerationOutcome.QUALIFIED,
    },
)


def request_for(fixture: LiveFixture) -> GenerationRequest:
    evidence: list[dict[str, object]] = []
    for index, evidence_text in enumerate(fixture["evidence"], start=1):
        evidence.append(
            {
                "evidence_id": f"ev-{index:02d}",
                "text": evidence_text,
                "document_chunk_id": index,
                "document_id": index,
                "ingestion_event_claim_id": index,
                "source_provenance": [{"kind": "live-verification-fixture"}],
                "temporal_authority": {"state": "current"},
                "applicability_context": {"scope": "universal"},
                "side": "primary",
            }
        )
    return GenerationRequest.model_validate(
        {
            "request_id": str(uuid4()),
            "workspace_id": str(uuid4()),
            "question": fixture["question"],
            "evidence": evidence,
            "constraints": {
                "context_policy_version": "whole-evidence-v1",
                "max_context_characters": 60_000,
                "required_sides": ["primary"],
            },
        }
    )


def test_bounded_live_generation_semantic_surfaces() -> None:
    if os.getenv("LIVE_OPENAI_GENERATION") != "1":
        pytest.skip(
            "set LIVE_OPENAI_GENERATION=1 for the bounded provider verification"
        )
    settings = Settings()
    generator = build_generator(settings)
    observations: list[dict[str, object]] = []
    mismatches: list[str] = []
    key_id, encoded_secret = next(iter(settings.retrieval_caller_hmac_keys.items()))
    secret = base64.b64decode(encoded_secret.get_secret_value())
    path = "/api/internal/retrieval/generation/answer"
    app.dependency_overrides[generator_dependency] = lambda: generator
    try:
        with TestClient(app) as client:
            for fixture in FIXTURES:
                expected: GenerationOutcome = fixture["expected"]
                request = request_for(fixture)
                body = request.model_dump_json().encode()
                timestamp = str(int(time.time()))
                canonical = (
                    f"{timestamp}\nPOST\n{path}\n{hashlib.sha256(body).hexdigest()}\n"
                    f"{request.workspace_id}\ngeneration.answer\n{request.request_id}"
                )
                signature = hmac.new(
                    secret, canonical.encode(), hashlib.sha256
                ).hexdigest()
                response = client.post(
                    path,
                    content=body,
                    headers={
                        "Content-Type": "application/json",
                        "X-Retrieval-Caller-Key-ID": key_id,
                        "X-Retrieval-Caller-Timestamp": timestamp,
                        "X-Retrieval-Caller-Signature": f"rc1={signature}",
                        "X-Retrieval-Caller-Workspace-ID": str(request.workspace_id),
                        "X-Retrieval-Caller-Purpose": "generation.answer",
                        "X-Retrieval-Caller-Request-ID": str(request.request_id),
                    },
                )
                response.raise_for_status()
                envelope = response.json()
                observations.append(
                    {
                        "fixture_id": fixture["id"],
                        "expected_outcome": expected.value,
                        "response": envelope,
                    }
                )
                assert envelope["status"] == "completed"
                print(
                    json.dumps(observations[-1], ensure_ascii=False, sort_keys=True),
                    flush=True,
                )
                if envelope["result"]["outcome"] != expected.value:
                    mismatches.append(fixture["id"])
    finally:
        app.dependency_overrides.clear()
    print(json.dumps(observations, ensure_ascii=False, sort_keys=True))
    assert not mismatches, f"unexpected generation outcomes: {mismatches}"
