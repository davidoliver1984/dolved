import io
import time
from typing import Any
from uuid import UUID

from app.embedding.errors import EmbeddingDimensionMismatchError
from app.embedding.models import (
    EmbeddedVector,
    EmbeddingProfile,
    EmbeddingResult,
)
from app.extraction.models import ExtractionWarning
from app.extraction.plain_text import PlainTextExtractor
from app.ingestion.artifact_upload import ArtifactUploadResult
from app.ingestion.canonicalisation import chunk_content_digest
from app.ingestion.heartbeat import CoordinatedHeartbeat, HeartbeatLost
from app.ingestion.orchestrator import IngestionOrchestrator
from app.ingestion.protocol_client import ClaimGrant
from app.ingestion.sqs import IngestionQueueMessage
from app.vector_store.models import (
    VectorCompletenessReport,
    VectorPublicationStatus,
)

EVENT = {
    "event_id": "5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41",
    "workspace_id": "b3f2a6d4-8e4b-4b0a-9d3f-6c2e1a7d9f10",
    "document_id": "d4c9e2b7-1a6f-4e3d-8b2c-9f0e5a3d7c62",
    "correlation_id": "7e6d5c4b-3a29-4180-9f8e-2d1c0b9a8f77",
    "storage_bucket": "documents",
    "storage_key": "source.txt",
    "media_type": "text/plain",
    "byte_size": 31,
}
PROFILE = EmbeddingProfile(
    provider="voyage",
    model="voyage-4-large",
    dimensions=3,
    output_dtype="float",
    document_input_type="document",
    query_input_type="query",
    normalisation="unit_length",
    truncation=False,
    adapter_version="1",
)


def grant(**changes: Any) -> ClaimGrant:
    values = {
        "outcome": "claimed",
        "document_status": "processing",
        "lease_token": "c9a7b8d0-2e1f-4a3b-9c8d-7e6f5a4b3c2d",
        "lease_expires_at": "2026-08-06T12:02:00Z",
        "lease_generation": 1,
        "embedding_space_generation_id": "00000000-0000-0000-0000-000000000001",
        "workspace_corpus_generation_id": "00000000-0000-0000-0000-000000000002",
        "vector_space": {
            "collection_name": "test-vectors",
            "vector_name": "dense",
            "dimensions": 3,
            "distance": "cosine",
            "embedding_profile_fingerprint": PROFILE.fingerprint(),
        },
    }
    return ClaimGrant(**{**values, **changes})


class FakeProtocol:
    def __init__(
        self,
        claim_grant: ClaimGrant,
        resume_chunks: list[dict[str, Any]] | None = None,
        artifact_authorisation_outcome: str = "authorised",
        artifact_limits: dict[str, Any] | None = None,
    ) -> None:
        self.claim_grant = claim_grant
        self.resume_chunks = resume_chunks or []
        self.calls: list[str] = []
        self.evidence: dict[str, Any] | None = None
        self.failure_usage: list[dict[str, Any]] | None = None
        self.failure_code: str | None = None
        self.artifact_acknowledgement: dict[str, Any] | None = None
        self.artifact_authorisation_outcome = artifact_authorisation_outcome
        self.artifact_limits = artifact_limits or {}

    def claim(self, **_: Any) -> ClaimGrant:
        self.calls.append("claim")
        return self.claim_grant

    def renew(self, _: dict[str, Any]) -> dict[str, Any]:
        self.calls.append("renew")
        return {"outcome": "renewed"}

    def authorise_extraction_artifact(self, _: dict[str, Any]) -> dict[str, Any]:
        self.calls.append("authorise_artifact")
        return {
            "outcome": self.artifact_authorisation_outcome,
            "authorisation_id": "00000000-0000-0000-0000-000000000099",
            "contract_version": "document-extraction-artifact-v1",
            "max_bytes": 1_000_000,
            "max_elements": 100_000,
            "max_element_text_bytes": 1_048_576,
            "max_warnings": 10_000,
            "supported_contract_versions": ["document-extraction-artifact-v1"],
            "upload": None
            if self.artifact_authorisation_outcome == "already_verified"
            else {
                "url": "https://objects.example/artifact",
                "method": "PUT",
                "headers": {"If-None-Match": "*"},
            },
            **self.artifact_limits,
        }

    def acknowledge_extraction_artifact(
        self, _: dict[str, Any], evidence: dict[str, Any]
    ) -> dict[str, Any]:
        self.calls.append("acknowledge_artifact")
        self.artifact_acknowledgement = evidence
        return {"outcome": "published"}

    def submit_chunks(
        self, _: dict[str, Any], chunks: list[dict[str, Any]]
    ) -> dict[str, Any]:
        self.calls.append("submit")
        assert all("content_digest" in chunk for chunk in chunks)
        return {"outcome": "accepted"}

    def seal(self, _: dict[str, Any], __: dict[str, Any]) -> dict[str, Any]:
        self.calls.append("seal")
        return {"outcome": "sealed"}

    def resume(self, _: dict[str, Any], **__: Any) -> dict[str, Any]:
        self.calls.append("resume")
        return {"chunks": self.resume_chunks, "next_cursor": None}

    def authorise_publication(
        self, _: dict[str, Any], evidence: dict[str, Any]
    ) -> dict[str, Any]:
        self.calls.append("authorise")
        self.evidence = evidence
        return {"outcome": "authorised"}

    def complete(self, _: dict[str, Any], evidence: dict[str, Any]) -> dict[str, Any]:
        self.calls.append("complete")
        assert evidence == self.evidence
        return {"outcome": "indexed"}

    def fail(self, _: dict[str, Any], **values: Any) -> dict[str, Any]:
        self.calls.append("fail")
        self.failure_code = values.get("failure_code")
        self.failure_usage = values.get("usage")
        return {"outcome": "failed"}


class FakeObjectStore:
    def __init__(self, content: bytes) -> None:
        self.content = content

    def get_object(self, **_: object) -> dict[str, Any]:
        return {"Body": io.BytesIO(self.content)}


class FakeEmbedder:
    def embed(self, request: Any) -> EmbeddingResult:
        return EmbeddingResult(
            profile=request.profile,
            profile_fingerprint=request.profile.fingerprint(),
            purpose=request.purpose,
            embeddings=tuple(
                EmbeddedVector(
                    source_id=item.source_id,
                    values=(1.0, 0.0, 0.0),
                    dimensions=3,
                )
                for item in request.items
            ),
            provider_input_tokens=5,
        )


class FailingEmbedder:
    def embed(self, _: Any) -> EmbeddingResult:
        raise EmbeddingDimensionMismatchError(
            "The provider returned an incompatible vector.",
            attempts=2,
            total_retry_delay_seconds=1.5,
        )


class FakeVectorStore:
    def __init__(self) -> None:
        self.points: dict[UUID, Any] = {}
        self.delete_calls = 0

    def ensure_vector_space(self, _: Any) -> None:
        pass

    def upsert(self, request: Any) -> Any:
        for point in request.points:
            self.points[point.point_id] = point.identity()
        return type("Result", (), {"point_ids": tuple(self.points), "batch_count": 1})()

    def verify_completeness(self, request: Any) -> VectorCompletenessReport:
        actual = {
            point_id: point
            for point_id, point in self.points.items()
            if point.publication_status is request.scope.publication_status
        }
        expected = {point.point_id: point for point in request.expected_points}
        return VectorCompletenessReport(
            expected_count=len(expected),
            actual_count=len(actual),
            missing_point_ids=tuple(expected.keys() - actual.keys()),
            unexpected_point_ids=tuple(actual.keys() - expected.keys()),
            payload_mismatch_point_ids=tuple(
                point_id
                for point_id in expected.keys() & actual.keys()
                if expected[point_id] != actual[point_id]
            ),
            vector_schema_compatible=True,
        )

    def publish(self, _: Any) -> None:
        self.points = {
            point_id: point.model_copy(
                update={"publication_status": VectorPublicationStatus.PUBLISHED}
            )
            for point_id, point in self.points.items()
        }

    def delete(self, _: Any) -> None:
        self.delete_calls += 1
        self.points.clear()


class FakeQueue:
    def __init__(self) -> None:
        self.visibility_extensions = 0

    def extend_visibility(self, _: Any) -> None:
        self.visibility_extensions += 1


class FakeArtifactUploader:
    def __init__(self) -> None:
        self.content: bytes | None = None

    def upload(self, **values: Any) -> ArtifactUploadResult:
        assert values["method"] == "PUT"
        assert values["headers"]["If-None-Match"] == "*"
        self.content = values["content"]
        return ArtifactUploadResult('"etag"', "version-1")


def message() -> IngestionQueueMessage:
    return IngestionQueueMessage("{}", "receipt", "transport", 1)


def orchestrator(
    protocol: FakeProtocol,
    vectors: FakeVectorStore,
    content: bytes,
    artifact_uploader: FakeArtifactUploader | None = None,
    *,
    processing_timeout_seconds: float = 300.0,
    monotonic: Any = time.monotonic,
) -> IngestionOrchestrator:
    return IngestionOrchestrator(
        protocol=protocol,  # type: ignore[arg-type]
        object_store=FakeObjectStore(content),
        embedder=FakeEmbedder(),
        embedding_profile=PROFILE,
        vector_store=vectors,  # type: ignore[arg-type]
        queue=FakeQueue(),  # type: ignore[arg-type]
        heartbeat_seconds=60,
        embedding_batch_size=10,
        artifact_uploader=artifact_uploader,
        processing_timeout_seconds=processing_timeout_seconds,
        monotonic=monotonic,
    )


def test_canonical_artifact_is_uploaded_once_and_acknowledged_before_chunks() -> None:
    content = b"Canonical text for ingestion.\n"
    event = {**EVENT, "byte_size": len(content)}
    protocol = FakeProtocol(grant())
    uploader = FakeArtifactUploader()

    outcome = orchestrator(
        protocol, FakeVectorStore(), content, artifact_uploader=uploader
    ).process(event=event, raw_body="{}", message=message())

    assert outcome.acknowledge is True
    assert uploader.content is not None
    assert protocol.calls.index("acknowledge_artifact") < protocol.calls.index("submit")
    assert protocol.artifact_acknowledgement is not None
    assert protocol.artifact_acknowledgement["size_bytes"] == len(uploader.content)
    assert protocol.artifact_acknowledgement["storage_version_id"] == "version-1"


def test_verified_artifact_retry_skips_upload_and_repeats_acknowledgement() -> None:
    content = b"Canonical text for ingestion.\n"
    event = {**EVENT, "byte_size": len(content)}
    protocol = FakeProtocol(grant(), artifact_authorisation_outcome="already_verified")
    uploader = FakeArtifactUploader()

    outcome = orchestrator(
        protocol, FakeVectorStore(), content, artifact_uploader=uploader
    ).process(event=event, raw_body="{}", message=message())

    assert outcome.acknowledge is True
    assert uploader.content is None
    assert protocol.artifact_acknowledgement is not None
    assert protocol.artifact_acknowledgement["storage_version_id"] is None
    assert protocol.calls.index("acknowledge_artifact") < protocol.calls.index("submit")


def test_normal_path_seals_authorises_publishes_verifies_and_completes() -> None:
    content = b"Canonical text for ingestion.\n"
    event = {**EVENT, "byte_size": len(content)}
    protocol = FakeProtocol(grant())
    vectors = FakeVectorStore()

    outcome = orchestrator(protocol, vectors, content).process(
        event=event, raw_body="{}", message=message()
    )

    assert outcome.acknowledge is True
    assert outcome.code == "indexed"
    assert protocol.calls == [
        "claim",
        "submit",
        "seal",
        "renew",
        "authorise",
        "renew",
        "complete",
    ]
    assert protocol.evidence is not None
    assert protocol.evidence["usage"] == [
        {
            "stage": "ingestion_embedding",
            "provider": "voyage",
            "model": "voyage-4-large",
            "execution": "provider_api",
            "request_count": 1,
            "retry_count": 0,
            "input_tokens": 5,
            "cached_input_tokens": None,
            "output_tokens": None,
            "latency_ms": 0,
            "cost_usd": None,
            "cost_basis": "unavailable",
            "pricing_snapshot": None,
        }
    ]
    assert all(
        point.publication_status is VectorPublicationStatus.PUBLISHED
        for point in vectors.points.values()
    )


def test_extraction_warnings_reach_publication_evidence(monkeypatch: Any) -> None:
    content = b"Canonical text for ingestion.\n"
    event = {**EVENT, "byte_size": len(content)}
    protocol = FakeProtocol(grant())
    flow = orchestrator(protocol, FakeVectorStore(), content)

    class WarningExtractor:
        def extract(self, source: Any, *, context: Any) -> Any:
            extracted = PlainTextExtractor().extract(source, context=context)
            return extracted.model_copy(
                update={
                    "warnings": (
                        ExtractionWarning(
                            code="images_not_extracted",
                            message="Images were not extracted.",
                        ),
                    )
                }
            )

    monkeypatch.setattr(flow, "_extractor", lambda _: WarningExtractor())

    outcome = flow.process(event=event, raw_body="{}", message=message())

    assert outcome.acknowledge is True
    assert protocol.evidence is not None
    assert protocol.evidence["warnings"] == [
        {"code": "images_not_extracted", "message": "Images were not extracted."}
    ]


def test_sealed_reclaim_resumes_without_reextracting_or_resubmitting() -> None:
    chunk = {
        "chunk_id": "00000000-0000-0000-0000-000000000003",
        "ordinal": 0,
        "text": "Sealed canonical text.",
        "token_count": 4,
        "strategy_name": "baseline-structural",
        "strategy_version": "1",
        "configuration": {"target_tokens": 400},
        "configuration_fingerprint": "a" * 64,
        "provenance": [
            {"source_element_ids": ["00000000-0000-0000-0000-000000000004"]}
        ],
    }
    chunk["content_digest"] = chunk_content_digest(chunk)
    protocol = FakeProtocol(
        grant(outcome="reclaimed", resume_sealed_attempt=True), [chunk]
    )

    outcome = orchestrator(protocol, FakeVectorStore(), b"").process(
        event=EVENT, raw_body="{}", message=message()
    )

    assert outcome.acknowledge is True
    assert protocol.calls == [
        "claim",
        "resume",
        "renew",
        "authorise",
        "renew",
        "complete",
    ]


def test_terminal_duplicate_is_acknowledged_without_repeating_work() -> None:
    protocol = FakeProtocol(
        grant(
            outcome="already_completed",
            document_status="indexed",
            lease_token=None,
        )
    )
    outcome = orchestrator(protocol, FakeVectorStore(), b"").process(
        event=EVENT, raw_body="{}", message=message()
    )

    assert outcome.acknowledge is True
    assert protocol.calls == ["claim"]


def test_open_reclaim_invokes_the_shared_event_scoped_cleanup_path() -> None:
    protocol = FakeProtocol(grant(outcome="reclaimed", reset_open_attempt=True))
    vectors = FakeVectorStore()
    content = b"Canonical text for ingestion.\n"
    event = {**EVENT, "byte_size": len(content)}

    outcome = orchestrator(protocol, vectors, content).process(
        event=event, raw_body="{}", message=message()
    )

    assert outcome.acknowledge is True
    assert vectors.delete_calls == 1


def test_dlq_reconciliation_records_a_distinct_terminal_outcome() -> None:
    protocol = FakeProtocol(grant(outcome="reclaimed", reset_open_attempt=True))
    vectors = FakeVectorStore()
    outcome = orchestrator(protocol, vectors, b"").reconcile_dlq(
        event=EVENT, raw_body="{}", message=message()
    )

    assert outcome.acknowledge is True
    assert outcome.code == "delivery_exhausted"
    assert protocol.calls == ["claim", "fail"]
    assert vectors.delete_calls == 1


def test_dlq_reconciliation_resumes_sealed_authoritative_work() -> None:
    chunk = {
        "chunk_id": "00000000-0000-0000-0000-000000000003",
        "ordinal": 0,
        "text": "Sealed canonical text.",
        "token_count": 4,
        "strategy_name": "baseline-structural",
        "strategy_version": "1",
        "configuration": {"target_tokens": 400},
        "configuration_fingerprint": "a" * 64,
        "provenance": [
            {"source_element_ids": ["00000000-0000-0000-0000-000000000004"]}
        ],
    }
    chunk["content_digest"] = chunk_content_digest(chunk)
    protocol = FakeProtocol(
        grant(outcome="reclaimed", resume_sealed_attempt=True), [chunk]
    )

    outcome = orchestrator(protocol, FakeVectorStore(), b"").reconcile_dlq(
        event=EVENT, raw_body="{}", message=message()
    )

    assert outcome == type(outcome)(True, "indexed")
    assert protocol.calls == [
        "claim",
        "resume",
        "renew",
        "authorise",
        "renew",
        "complete",
    ]


def test_permanent_extraction_failure_is_reported_before_acknowledgement() -> None:
    protocol = FakeProtocol(grant())
    event = {
        **EVENT,
        "media_type": "application/octet-stream",
        "byte_size": 1,
    }

    outcome = orchestrator(protocol, FakeVectorStore(), b"x").process(
        event=event, raw_body="{}", message=message()
    )

    assert outcome == type(outcome)(True, "failed")
    assert protocol.calls == ["claim", "fail"]


def test_processing_timeout_remains_typed_and_retryable_without_terminal_failure() -> (
    None
):
    content = b"Canonical text for ingestion.\n"
    protocol = FakeProtocol(grant())
    readings = iter((0.0, 0.0, 301.0))

    outcome = orchestrator(
        protocol,
        FakeVectorStore(),
        content,
        processing_timeout_seconds=300.0,
        monotonic=lambda: next(readings),
    ).process(
        event={**EVENT, "byte_size": len(content)},
        raw_body="{}",
        message=message(),
    )

    assert outcome == type(outcome)(False, "ingestion.processing_timeout")
    assert protocol.calls == ["claim", "submit", "seal"]


def test_artifact_limit_violation_is_reported_as_typed_terminal_failure() -> None:
    content = b"Canonical text for ingestion.\n"
    protocol = FakeProtocol(grant(), artifact_limits={"max_bytes": 0})

    outcome = orchestrator(
        protocol,
        FakeVectorStore(),
        content,
        artifact_uploader=FakeArtifactUploader(),
    ).process(
        event={**EVENT, "byte_size": len(content)},
        raw_body="{}",
        message=message(),
    )

    assert outcome == type(outcome)(True, "failed")
    assert protocol.failure_code == "extraction_artifact_too_large"
    assert protocol.calls == ["claim", "authorise_artifact", "fail"]


def test_permanent_embedding_failure_reports_typed_usage_before_acknowledgement() -> (
    None
):
    content = b"Canonical text for ingestion.\n"
    protocol = FakeProtocol(grant())
    flow = orchestrator(protocol, FakeVectorStore(), content)
    flow._embedder = FailingEmbedder()  # type: ignore[assignment]

    outcome = flow.process(
        event={**EVENT, "byte_size": len(content)}, raw_body="{}", message=message()
    )

    assert outcome == type(outcome)(True, "failed")
    assert protocol.calls == ["claim", "submit", "seal", "fail"]
    assert protocol.failure_usage == [
        {
            "stage": "ingestion_embedding",
            "provider": "voyage",
            "model": "voyage-4-large",
            "execution": "provider_api",
            "request_count": 2,
            "retry_count": 1,
            "input_tokens": None,
            "cached_input_tokens": None,
            "output_tokens": None,
            "latency_ms": 1500,
            "cost_usd": None,
            "cost_basis": "unavailable",
            "pricing_snapshot": None,
        }
    ]


def test_heartbeat_revokes_authority_when_either_coordinated_half_fails() -> None:
    renewals = 0

    def renew() -> None:
        nonlocal renewals
        renewals += 1

    def visibility() -> None:
        raise RuntimeError("visibility unavailable")

    with CoordinatedHeartbeat(
        interval_seconds=0.01,
        renew_lease=renew,
        extend_visibility=visibility,
    ) as heartbeat:
        time.sleep(0.03)
        try:
            heartbeat.assert_healthy()
            raise AssertionError("An uncertain heartbeat remained authoritative.")
        except HeartbeatLost:
            pass

    assert renewals == 1


def test_heartbeat_attempts_visibility_even_when_lease_renewal_fails() -> None:
    visibility_extensions = 0

    def renew() -> None:
        raise RuntimeError("lease unavailable")

    def visibility() -> None:
        nonlocal visibility_extensions
        visibility_extensions += 1

    with CoordinatedHeartbeat(
        interval_seconds=0.01,
        renew_lease=renew,
        extend_visibility=visibility,
    ) as heartbeat:
        time.sleep(0.03)
        try:
            heartbeat.assert_healthy()
            raise AssertionError("An uncertain heartbeat remained authoritative.")
        except HeartbeatLost:
            pass

    assert visibility_extensions == 1
