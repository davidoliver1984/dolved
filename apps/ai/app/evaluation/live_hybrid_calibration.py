import argparse
import hashlib
import json
import time
from collections.abc import Callable
from datetime import UTC, datetime
from pathlib import Path
from typing import Literal
from uuid import NAMESPACE_URL, UUID, uuid5

from pydantic import Field, model_validator

from app.evaluation.hybrid_calibration import (
    HybridCalibrationDataset,
    HybridCalibrationObservation,
    calibrate_hybrid_threshold,
)
from app.evaluation.models import Identifier, StrictModel
from app.reranking.models import (
    RerankCandidate,
    RerankerProfile,
    RerankRequest,
)
from app.reranking.voyage import VoyageReranker
from app.retrieval.models import RetrievalSide
from app.settings import get_settings


class LiveCalibrationCandidate(StrictModel):
    candidate_id: Identifier
    source_path: str = Field(min_length=1)
    text: str = Field(min_length=1)
    relevant: bool


class LiveCalibrationCase(StrictModel):
    case_id: Identifier
    variant_id: Identifier
    split: Literal["calibration", "held_out"]
    question: str = Field(min_length=1)
    candidates: tuple[LiveCalibrationCandidate, ...] = Field(min_length=2)

    @model_validator(mode="after")
    def candidate_set_is_valid(self) -> LiveCalibrationCase:
        identities = tuple(item.candidate_id for item in self.candidates)
        if len(set(identities)) != len(identities):
            raise ValueError("candidate IDs must be unique within a case")
        if {item.relevant for item in self.candidates} != {False, True}:
            raise ValueError("each calibration case needs relevant and irrelevant text")
        return self


class LiveCalibrationInput(StrictModel):
    schema_version: Literal["v1"] = "v1"
    corpus_version: str
    corpus_digest: str = Field(pattern=r"^[0-9a-f]{64}$")
    candidate_set_method: str = Field(min_length=1)
    hybrid_configuration: dict[str, int | str]
    cases: tuple[LiveCalibrationCase, ...] = Field(min_length=4)

    @model_validator(mode="after")
    def split_is_case_isolated(self) -> LiveCalibrationInput:
        identities = tuple(item.case_id for item in self.cases)
        if len(set(identities)) != len(identities):
            raise ValueError("case IDs must be unique and assigned to one split")
        if {item.split for item in self.cases} != {"calibration", "held_out"}:
            raise ValueError("both calibration and held-out cases are required")
        return self


def run_live_calibration(
    source: LiveCalibrationInput,
    *,
    implementation_revision: str,
    delay_seconds: float = 0,
    pause: Callable[[float], None] = time.sleep,
) -> dict[str, object]:
    settings = get_settings()
    profile = RerankerProfile(
        provider="voyage",
        model=settings.reranker_model,
        adapter_version="1",
        truncation=False,
    )
    reranker = VoyageReranker(
        api_key=settings.voyage_api_key,
        api_url=settings.voyage_rerank_api_url,
        timeout_seconds=settings.reranker_timeout_seconds,
        max_attempts=settings.reranker_max_attempts,
        initial_backoff_seconds=settings.reranker_initial_backoff_seconds,
        max_backoff_seconds=settings.reranker_max_backoff_seconds,
    )
    observations: list[HybridCalibrationObservation] = []
    total_tokens = 0
    for case_index, case in enumerate(source.cases):
        chunk_id_by_candidate = {
            candidate.candidate_id: _identity(case.case_id, candidate.candidate_id)
            for candidate in case.candidates
        }
        candidate_by_chunk = {
            chunk_id_by_candidate[candidate.candidate_id]: candidate
            for candidate in case.candidates
        }
        request = RerankRequest(
            request_id=_identity(case.case_id, "request"),
            workspace_id=_identity("r16-s08", "calibration-workspace"),
            query=case.question,
            profile=profile,
            candidates=tuple(
                RerankCandidate(
                    chunk_id=chunk_id_by_candidate[candidate.candidate_id],
                    document_id=_identity(candidate.candidate_id, "document"),
                    document_family_id=_identity(candidate.source_path, "family"),
                    version_position=1,
                    side=RetrievalSide.PRIMARY,
                    text=candidate.text,
                    fused_score=1 / rank,
                    fused_rank=rank,
                )
                for rank, candidate in enumerate(case.candidates, start=1)
            ),
            top_k=len(case.candidates),
        )
        rerank_result = reranker.rerank(request)
        total_tokens += rerank_result.provider_input_tokens or 0
        score_by_chunk = {
            item.chunk_id: item.score for item in rerank_result.candidates
        }
        for chunk_id, candidate in candidate_by_chunk.items():
            observations.append(
                HybridCalibrationObservation(
                    observation_id=f"{case.case_id}.{candidate.candidate_id}",
                    case_id=case.case_id,
                    variant_id=case.variant_id,
                    candidate_id=candidate.candidate_id,
                    split=case.split,
                    reranker_score=score_by_chunk[chunk_id],
                    relevant=candidate.relevant,
                )
            )
        if delay_seconds > 0 and case_index < len(source.cases) - 1:
            pause(delay_seconds)
    dataset = HybridCalibrationDataset(
        corpus_version=source.corpus_version,
        corpus_digest=source.corpus_digest,
        reranker={
            "provider": profile.provider,
            "model": profile.model,
            "adapter_version": profile.adapter_version,
            "purpose": "r16-s08-live-threshold-calibration",
        },
        hybrid_configuration=source.hybrid_configuration,
        observations=tuple(observations),
    )
    calibration_result = calibrate_hybrid_threshold(dataset)
    canonical_input = source.model_dump_json().encode()
    return {
        "schema_version": "v1",
        "generated_at": datetime.now(UTC).isoformat(),
        "implementation_revision": implementation_revision,
        "input_digest": hashlib.sha256(canonical_input).hexdigest(),
        "candidate_set_method": source.candidate_set_method,
        "provider_input_tokens": total_tokens,
        "dataset": dataset.model_dump(mode="json"),
        "result": calibration_result.model_dump(mode="json"),
    }


def _identity(namespace: str, value: str) -> UUID:
    return uuid5(NAMESPACE_URL, f"rag-platform:r16-s08:{namespace}:{value}")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--implementation-revision", required=True)
    parser.add_argument("--delay-seconds", type=float, default=20)
    arguments = parser.parse_args()
    if arguments.delay_seconds < 0:
        parser.error("--delay-seconds cannot be negative")
    source = LiveCalibrationInput.model_validate_json(arguments.input.read_text())
    output = run_live_calibration(
        source,
        implementation_revision=arguments.implementation_revision,
        delay_seconds=arguments.delay_seconds,
    )
    arguments.output.write_text(json.dumps(output, indent=2) + "\n")


if __name__ == "__main__":
    main()
