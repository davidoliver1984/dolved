import logging
from dataclasses import dataclass
from typing import Any, Protocol
from uuid import UUID

from app.chunking.baseline import BaselineStructuralChunker
from app.chunking.errors import UnrepresentableContentError
from app.embedding.errors import (
    EmbeddingDimensionMismatchError,
    EmbeddingError,
    EmbeddingInputTooLargeError,
    InvalidEmbeddingInputError,
)
from app.embedding.models import (
    EmbeddedVector,
    EmbeddingInput,
    EmbeddingProfile,
    EmbeddingPurpose,
    EmbeddingRequest,
)
from app.embedding.protocol import Embedder
from app.extraction.docx.factory import create_docx_extractor
from app.extraction.errors import ExtractionFailure, ExtractionFailureKind
from app.extraction.models import ExtractionContext
from app.extraction.pdf.factory import create_pdf_extractor
from app.extraction.plain_text import PlainTextExtractor
from app.ingestion.canonicalisation import (
    chunk_content_digest,
    chunk_manifest_digest,
    point_manifest_digest,
)
from app.ingestion.heartbeat import CoordinatedHeartbeat, HeartbeatLost
from app.ingestion.protocol_client import ClaimGrant, IngestionProtocolClient
from app.ingestion.sqs import IngestionQueueMessage, SqsIngestionQueue
from app.normalisation.structural import StructuralNormaliser
from app.sparse.errors import SparseEncodingError
from app.sparse.models import (
    SparseEmbeddingProfile,
    SparseEncodedVector,
    SparseEncodingInput,
    SparseEncodingPurpose,
    SparseEncodingRequest,
)
from app.sparse.protocol import SparseEncoder
from app.vector_store.errors import VectorStoreError
from app.vector_store.models import (
    SparseVectorSpace,
    VectorCompletenessRequest,
    VectorDistance,
    VectorPoint,
    VectorPointIdentity,
    VectorPublicationStatus,
    VectorScope,
    VectorSpace,
    VectorUpsertRequest,
)
from app.vector_store.protocol import VectorStore

logger = logging.getLogger("ingestion.orchestrator")


class ObjectStore(Protocol):
    def get_object(self, **kwargs: object) -> dict[str, Any]: ...


@dataclass(frozen=True)
class ProcessingOutcome:
    acknowledge: bool
    code: str


class IngestionOrchestrator:
    def __init__(
        self,
        *,
        protocol: IngestionProtocolClient,
        object_store: ObjectStore,
        embedder: Embedder,
        vector_store: VectorStore,
        embedding_profile: EmbeddingProfile,
        sparse_encoder: SparseEncoder | None = None,
        queue: SqsIngestionQueue,
        heartbeat_seconds: float,
        embedding_batch_size: int,
        chunk_batch_size: int = 50,
        resume_page_size: int = 50,
    ) -> None:
        self._protocol = protocol
        self._object_store = object_store
        self._embedder = embedder
        self._vector_store = vector_store
        self._embedding_profile = embedding_profile
        self._sparse_encoder = sparse_encoder
        self._queue = queue
        self._heartbeat_seconds = heartbeat_seconds
        self._embedding_batch_size = embedding_batch_size
        self._chunk_batch_size = chunk_batch_size
        self._resume_page_size = resume_page_size

    def process(
        self,
        *,
        event: dict[str, Any],
        raw_body: str,
        message: IngestionQueueMessage,
    ) -> ProcessingOutcome:
        event_id = str(event["event_id"])
        try:
            grant = self._protocol.claim(raw_body=raw_body, event_id=event_id)
        except Exception:  # noqa: BLE001 -- an unconfirmed claim must remain retryable
            return ProcessingOutcome(False, "claim_unavailable")
        if grant.is_terminal:
            return ProcessingOutcome(True, grant.outcome)
        if not grant.may_process:
            return ProcessingOutcome(False, grant.outcome)

        context = self._context(event, grant)
        return self._run_attempt(event, message, grant, context)

    def _run_attempt(
        self,
        event: dict[str, Any],
        message: IngestionQueueMessage,
        grant: ClaimGrant,
        context: dict[str, Any],
    ) -> ProcessingOutcome:
        heartbeat = CoordinatedHeartbeat(
            interval_seconds=self._heartbeat_seconds,
            renew_lease=lambda: self._protocol.renew(context),
            extend_visibility=lambda: self._queue.extend_visibility(message),
        )
        try:
            with heartbeat:
                if grant.reset_open_attempt:
                    self._cleanup_provisional(context, grant)
                chunks = (
                    self._resume_chunks(context)
                    if grant.resume_sealed_attempt
                    else self._extract_submit_and_seal(event, context, heartbeat)
                )
                heartbeat.assert_healthy()
                evidence = self._embed_persist_verify(
                    event, context, grant, chunks, heartbeat
                )
                self._protocol.authorise_publication(context, evidence)
                heartbeat.assert_healthy()
                provisional, published = self._scopes(event, grant)
                self._vector_store.publish(provisional)
                heartbeat.assert_healthy()
                report = self._vector_store.verify_completeness(
                    VectorCompletenessRequest(
                        scope=published,
                        expected_points=tuple(
                            point.model_copy(
                                update={
                                    "publication_status": VectorPublicationStatus.PUBLISHED
                                }
                            )
                            for point in self._point_identities(event, grant, chunks)
                        ),
                    )
                )
                if not report.is_complete:
                    return ProcessingOutcome(False, "publication_incomplete")
                heartbeat.assert_healthy()
                self._protocol.complete(context, evidence)
                return ProcessingOutcome(True, "indexed")
        except HeartbeatLost:
            return ProcessingOutcome(False, "heartbeat_lost")
        except ExtractionFailure as exception:
            return self._processing_failure(
                context,
                exception.code,
                str(exception),
                exception.kind is ExtractionFailureKind.PERMANENT,
            )
        except UnrepresentableContentError as exception:
            return self._processing_failure(
                context,
                "chunking.unrepresentable_content",
                str(exception),
                True,
            )
        except EmbeddingError as exception:
            return self._processing_failure(
                context,
                f"embedding.{exception.code}",
                str(exception),
                isinstance(
                    exception,
                    (
                        InvalidEmbeddingInputError,
                        EmbeddingInputTooLargeError,
                        EmbeddingDimensionMismatchError,
                    ),
                ),
            )
        except SparseEncodingError as exception:
            return self._processing_failure(
                context,
                f"sparse.{exception.code}",
                str(exception),
                not exception.retryable,
            )
        except VectorStoreError as exception:
            return self._processing_failure(
                context,
                f"vector.{exception.code}",
                str(exception),
                False,
            )
        except Exception as exception:
            logger.exception(
                "Ingestion orchestration remains retryable.",
                extra={
                    "event_id": context["event_id"],
                    "error_type": type(exception).__name__,
                },
            )
            return ProcessingOutcome(False, "retryable_error")

    def reconcile_dlq(
        self,
        *,
        event: dict[str, Any],
        raw_body: str,
        message: IngestionQueueMessage,
    ) -> ProcessingOutcome:
        """Resolve an exhausted delivery without confusing transport with domain state."""
        try:
            grant = self._protocol.claim(
                raw_body=raw_body, event_id=str(event["event_id"])
            )
        except Exception:  # noqa: BLE001 -- reconciliation must fail closed
            return ProcessingOutcome(False, "reconciliation_claim_unavailable")
        if grant.is_terminal:
            return ProcessingOutcome(True, grant.outcome)
        if not grant.may_process:
            return ProcessingOutcome(False, grant.outcome)
        context = self._context(event, grant)
        if grant.resume_sealed_attempt:
            return self._run_attempt(event, message, grant, context)
        try:
            if grant.reset_open_attempt:
                self._cleanup_provisional(context, grant)
            self._protocol.fail(
                context,
                failure_code="delivery_exhausted",
                failure_message="Queue redelivery was exhausted before processing completed.",
            )
        except Exception:  # noqa: BLE001 -- an unconfirmed terminal callback is retryable
            return ProcessingOutcome(False, "reconciliation_failed")
        return ProcessingOutcome(True, "delivery_exhausted")

    @staticmethod
    def _context(event: dict[str, Any], grant: ClaimGrant) -> dict[str, Any]:
        return {
            "event_id": str(event["event_id"]),
            "workspace_id": str(event["workspace_id"]),
            "document_id": str(event["document_id"]),
            "lease_token": grant.lease_token,
        }

    def _extract_submit_and_seal(
        self,
        event: dict[str, Any],
        context: dict[str, Any],
        heartbeat: CoordinatedHeartbeat,
    ) -> list[dict[str, Any]]:
        source = self._source(event)
        extraction_context = ExtractionContext(
            workspace_id=UUID(str(event["workspace_id"])),
            document_id=UUID(str(event["document_id"])),
            source_media_type=str(event["media_type"]),
        )
        extracted = self._extractor(str(event["media_type"])).extract(
            source, context=extraction_context
        )
        normalised = StructuralNormaliser().normalise(extracted)
        result = BaselineStructuralChunker().chunk(normalised)
        chunks = []
        for chunk in result.chunks:
            value = {
                "chunk_id": str(chunk.id),
                "ordinal": chunk.ordinal,
                "text": chunk.text,
                "token_count": chunk.token_count,
                "strategy_name": result.strategy_name,
                "strategy_version": result.strategy_version,
                "configuration": result.configuration.model_dump(mode="json"),
                "configuration_fingerprint": result.configuration_fingerprint,
                "provenance": [
                    contribution.model_dump(mode="json")
                    for contribution in chunk.contributions
                ],
            }
            value["content_digest"] = chunk_content_digest(value)
            chunks.append(value)
        for start in range(0, len(chunks), self._chunk_batch_size):
            heartbeat.assert_healthy()
            self._protocol.submit_chunks(
                context, chunks[start : start + self._chunk_batch_size]
            )
        manifest = self._chunk_manifest(chunks)
        self._protocol.seal(
            context,
            {
                "expected_chunk_count": len(chunks),
                "chunk_manifest_digest": chunk_manifest_digest(manifest),
                "configuration_fingerprint": result.configuration_fingerprint,
            },
        )
        return chunks

    def _resume_chunks(self, context: dict[str, Any]) -> list[dict[str, Any]]:
        cursor = 0
        chunks: list[dict[str, Any]] = []
        while True:
            page = self._protocol.resume(
                context, cursor=cursor, limit=self._resume_page_size
            )
            chunks.extend(page["chunks"])
            next_cursor = page.get("next_cursor")
            if next_cursor is None:
                return chunks
            cursor = int(next_cursor)

    def _embed_persist_verify(
        self,
        event: dict[str, Any],
        context: dict[str, Any],
        grant: ClaimGrant,
        chunks: list[dict[str, Any]],
        heartbeat: CoordinatedHeartbeat,
    ) -> dict[str, Any]:
        profile = self._embedding_profile
        if profile.fingerprint() != grant.vector_space["embedding_profile_fingerprint"]:  # type: ignore[index]
            raise RuntimeError("embedding_profile_mismatch")
        embeddings: list[EmbeddedVector] = []
        sparse_embeddings: list[SparseEncodedVector] = []
        sparse_profile = self._sparse_profile(grant)
        if sparse_profile is not None and self._sparse_encoder is None:
            raise RuntimeError("sparse_encoder_unavailable")
        for start in range(0, len(chunks), self._embedding_batch_size):
            heartbeat.assert_healthy()
            batch = chunks[start : start + self._embedding_batch_size]
            result = self._embedder.embed(
                EmbeddingRequest(
                    correlation_id=UUID(str(event["correlation_id"])),
                    workspace_id=UUID(str(event["workspace_id"])),
                    document_id=UUID(str(event["document_id"])),
                    profile=profile,
                    purpose=EmbeddingPurpose.DOCUMENT,
                    items=tuple(
                        EmbeddingInput(
                            source_id=UUID(chunk["chunk_id"]), text=chunk["text"]
                        )
                        for chunk in batch
                    ),
                )
            )
            embeddings.extend(result.embeddings)
            if sparse_profile is not None:
                assert self._sparse_encoder is not None
                sparse_result = self._sparse_encoder.encode(
                    SparseEncodingRequest(
                        correlation_id=UUID(str(event["correlation_id"])),
                        workspace_id=UUID(str(event["workspace_id"])),
                        document_id=UUID(str(event["document_id"])),
                        profile=sparse_profile,
                        purpose=SparseEncodingPurpose.DOCUMENT,
                        items=tuple(
                            SparseEncodingInput(
                                source_id=UUID(chunk["chunk_id"]), text=chunk["text"]
                            )
                            for chunk in batch
                        ),
                    )
                )
                sparse_embeddings.extend(sparse_result.encodings)
        vector_space = self._vector_space(grant)
        self._vector_store.ensure_vector_space(vector_space)
        identities = self._point_identities(event, grant, chunks)
        by_chunk = {item.source_id: item for item in embeddings}
        sparse_by_chunk = {item.source_id: item for item in sparse_embeddings}
        self._vector_store.upsert(
            VectorUpsertRequest(
                vector_space=vector_space,
                points=tuple(
                    VectorPoint(
                        **identity.model_dump(),
                        values=by_chunk[identity.chunk_id].values,
                        sparse_vector=(
                            sparse_by_chunk[identity.chunk_id].vector
                            if sparse_profile is not None
                            else None
                        ),
                    )
                    for identity in identities
                ),
                batch_size=self._embedding_batch_size,
            )
        )
        provisional, _ = self._scopes(event, grant)
        report = self._vector_store.verify_completeness(
            VectorCompletenessRequest(scope=provisional, expected_points=identities)
        )
        if not report.is_complete:
            raise RuntimeError("provisional_vector_set_incomplete")
        point_ids = [str(identity.point_id) for identity in identities]
        manifest = self._chunk_manifest(chunks)
        return {
            "expected_chunk_count": len(chunks),
            "chunk_manifest_digest": chunk_manifest_digest(manifest),
            "expected_point_count": len(point_ids),
            "point_ids": point_ids,
            "point_manifest_digest": point_manifest_digest(point_ids),
            "embedding_profile_fingerprint": profile.fingerprint(),
            "sparse_profile_fingerprint": (
                sparse_profile.fingerprint() if sparse_profile is not None else None
            ),
            "embedding_space_generation_id": grant.embedding_space_generation_id,
            "sparse_space_generation_id": (
                (grant.vector_space or {})
                .get("sparse", {})
                .get("sparse_space_generation_id")
                if isinstance((grant.vector_space or {}).get("sparse"), dict)
                else None
            ),
            "workspace_corpus_generation_id": grant.workspace_corpus_generation_id,
            "warnings": [],
        }

    def _point_identities(
        self, event: dict[str, Any], grant: ClaimGrant, chunks: list[dict[str, Any]]
    ) -> tuple[VectorPointIdentity, ...]:
        return tuple(
            VectorPointIdentity(
                workspace_id=UUID(str(event["workspace_id"])),
                document_id=UUID(str(event["document_id"])),
                chunk_id=UUID(chunk["chunk_id"]),
                workspace_corpus_generation_id=UUID(
                    str(grant.workspace_corpus_generation_id)
                ),
                embedding_space_generation_id=UUID(
                    str(grant.embedding_space_generation_id)
                ),
                sparse_space_generation_id=(
                    UUID(
                        str(
                            (grant.vector_space or {})["sparse"][
                                "sparse_space_generation_id"
                            ]
                        )
                    )
                    if isinstance((grant.vector_space or {}).get("sparse"), dict)
                    else None
                ),
                event_id=UUID(str(event["event_id"])),
                publication_status=VectorPublicationStatus.PROVISIONAL,
            )
            for chunk in chunks
        )

    def _scopes(
        self, event: dict[str, Any], grant: ClaimGrant
    ) -> tuple[VectorScope, VectorScope]:
        vector_space = self._vector_space(grant)
        workspace_id = UUID(str(event["workspace_id"]))
        corpus_id = UUID(str(grant.workspace_corpus_generation_id))
        document_id = UUID(str(event["document_id"]))
        event_id = UUID(str(event["event_id"]))
        return (
            VectorScope(
                vector_space=vector_space,
                workspace_id=workspace_id,
                workspace_corpus_generation_id=corpus_id,
                document_id=document_id,
                event_id=event_id,
                publication_status=VectorPublicationStatus.PROVISIONAL,
            ),
            VectorScope(
                vector_space=vector_space,
                workspace_id=workspace_id,
                workspace_corpus_generation_id=corpus_id,
                document_id=document_id,
                event_id=event_id,
                publication_status=VectorPublicationStatus.PUBLISHED,
            ),
        )

    @staticmethod
    def _vector_space(grant: ClaimGrant) -> VectorSpace:
        value = grant.vector_space or {}
        sparse_value = value.get("sparse")
        return VectorSpace(
            collection_name=value["collection_name"],
            embedding_space_generation_id=UUID(
                str(grant.embedding_space_generation_id)
            ),
            profile_fingerprint=value["embedding_profile_fingerprint"],
            vector_name=value["vector_name"],
            dimensions=value["dimensions"],
            distance=VectorDistance(value["distance"]),
            sparse=(
                SparseVectorSpace(
                    sparse_space_generation_id=UUID(
                        str(sparse_value["sparse_space_generation_id"])
                    ),
                    profile_fingerprint=sparse_value["profile_fingerprint"],
                    vector_name=sparse_value["vector_name"],
                )
                if isinstance(sparse_value, dict)
                else None
            ),
        )

    @staticmethod
    def _sparse_profile(grant: ClaimGrant) -> SparseEmbeddingProfile | None:
        value = grant.vector_space or {}
        sparse = value.get("sparse")
        if not isinstance(sparse, dict):
            return None
        profile = SparseEmbeddingProfile.model_validate(sparse["profile"])
        if profile.fingerprint() != sparse["profile_fingerprint"]:
            raise RuntimeError("sparse_profile_mismatch")
        return profile

    def _cleanup_provisional(self, context: dict[str, Any], grant: ClaimGrant) -> None:
        event = context
        provisional, _ = self._scopes(event, grant)
        self._vector_store.delete(provisional)

    def _processing_failure(
        self, context: dict[str, Any], code: str, message: str, permanent: bool
    ) -> ProcessingOutcome:
        if not permanent:
            return ProcessingOutcome(False, code)
        try:
            self._protocol.fail(
                context, failure_code=code, failure_message=message[:1000]
            )
        except Exception:  # noqa: BLE001 -- an unconfirmed terminal callback is retryable
            return ProcessingOutcome(False, "failure_report_unconfirmed")
        return ProcessingOutcome(True, "failed")

    def _source(self, event: dict[str, Any]) -> bytes:
        response = self._object_store.get_object(
            Bucket=event["storage_bucket"], Key=event["storage_key"]
        )
        body = response["Body"].read()
        if not isinstance(body, bytes) or len(body) != event["byte_size"]:
            raise ExtractionFailure(
                kind=ExtractionFailureKind.PERMANENT,
                code="source_size_mismatch",
                user_message="The stored source size does not match its authoritative metadata.",
            )
        return body

    @staticmethod
    def _extractor(media_type: str) -> Any:
        if media_type == "application/pdf":
            return create_pdf_extractor()
        if (
            media_type
            == "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        ):
            return create_docx_extractor()
        if media_type.startswith("text/"):
            return PlainTextExtractor()
        raise ExtractionFailure(
            kind=ExtractionFailureKind.PERMANENT,
            code="unsupported_media_type",
            user_message="The document media type is not supported.",
        )

    @staticmethod
    def _chunk_manifest(chunks: list[dict[str, Any]]) -> list[dict[str, Any]]:
        return [
            {
                "chunk_id": chunk["chunk_id"],
                "ordinal": chunk["ordinal"],
                "content_digest": chunk["content_digest"],
            }
            for chunk in chunks
        ]
