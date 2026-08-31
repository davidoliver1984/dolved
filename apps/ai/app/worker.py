import argparse
import logging
import signal
import threading
from types import FrameType

import boto3  # type: ignore[import-untyped]

from app.deletion.client import DocumentDeletionClient
from app.deletion.orchestrator import DocumentDeletionOrchestrator
from app.embedding.factory import create_deferred_embedder, embedding_profile
from app.import_preflight.client import ImportPreflightClient
from app.import_preflight.orchestrator import ImportPreflightOrchestrator
from app.ingestion.artifact_upload import HttpxArtifactUploader
from app.ingestion.claim_client import IngestionClaimClient
from app.ingestion.orchestrator import IngestionOrchestrator
from app.ingestion.protocol_client import IngestionProtocolClient
from app.ingestion.signing import IngestionWorkerSigner
from app.ingestion.sqs import SqsIngestionQueue
from app.ingestion.worker import IngestionWorker
from app.settings import get_settings
from app.sparse.factory import create_deferred_sparse_encoder
from app.structured_logging import configure_structured_logging
from app.telemetry import configure_telemetry
from app.vector_store.factory import create_vector_store

logger = logging.getLogger("ingestion.worker")


def build_worker(
    stop_event: threading.Event, *, reconcile_dlq: bool = False
) -> IngestionWorker:
    settings = get_settings()
    sqs_client = boto3.client(
        "sqs",
        region_name=settings.aws_default_region,
        endpoint_url=settings.aws_endpoint_url,
    )
    s3_client = boto3.client(
        "s3",
        region_name=settings.aws_default_region,
        endpoint_url=settings.aws_endpoint_url,
    )
    queue = SqsIngestionQueue(
        client=sqs_client,
        queue_name=(
            settings.ingestion_dlq if reconcile_dlq else settings.ingestion_queue
        ),
        wait_time_seconds=settings.ingestion_worker_wait_time_seconds,
        visibility_timeout_seconds=(
            settings.ingestion_worker_visibility_timeout_seconds
        ),
        batch_size=settings.ingestion_worker_batch_size,
    )
    signer = IngestionWorkerSigner(
        settings.ingestion_worker_hmac_key_id,
        settings.ingestion_worker_hmac_secret.get_secret_value(),
    )
    claim_client = IngestionClaimClient(
        base_url=settings.ingestion_worker_api_url,
        timeout_seconds=settings.ingestion_worker_api_timeout_seconds,
        signer=signer,
    )
    protocol = IngestionProtocolClient(
        base_url=settings.ingestion_worker_api_url,
        timeout_seconds=settings.ingestion_worker_api_timeout_seconds,
        signer=signer,
        max_attempts=settings.ingestion_worker_callback_max_attempts,
        initial_backoff_seconds=settings.ingestion_worker_callback_backoff_seconds,
    )
    vector_store = create_vector_store(settings)
    orchestrator = IngestionOrchestrator(
        protocol=protocol,
        object_store=s3_client,
        embedder=create_deferred_embedder(settings),
        embedding_profile=embedding_profile(settings),
        sparse_encoder=create_deferred_sparse_encoder(settings),
        vector_store=vector_store,
        queue=queue,
        heartbeat_seconds=settings.ingestion_worker_heartbeat_seconds,
        embedding_batch_size=settings.embedding_batch_size,
        chunk_batch_size=settings.ingestion_chunk_batch_size,
        resume_page_size=settings.ingestion_resume_page_size,
        artifact_uploader=HttpxArtifactUploader(
            timeout_seconds=settings.ingestion_worker_api_timeout_seconds,
        ),
        processing_timeout_seconds=settings.ingestion_processing_timeout_seconds,
    )
    deletion_orchestrator = DocumentDeletionOrchestrator(
        client=DocumentDeletionClient(
            base_url=settings.ingestion_worker_api_url,
            timeout_seconds=settings.ingestion_worker_api_timeout_seconds,
            signer=signer,
        ),
        vector_store=vector_store,
    )
    import_preflight_orchestrator = ImportPreflightOrchestrator(
        client=ImportPreflightClient(
            base_url=settings.ingestion_worker_api_url,
            timeout_seconds=settings.ingestion_worker_api_timeout_seconds,
            signer=signer,
        ),
        timeout_seconds=settings.ingestion_worker_api_timeout_seconds,
    )

    return IngestionWorker(
        queue=queue,
        claim_client=claim_client,
        stop_event=stop_event,
        error_wait_seconds=settings.ingestion_worker_error_wait_seconds,
        orchestrator=orchestrator,
        deletion_orchestrator=deletion_orchestrator,
        import_preflight_orchestrator=import_preflight_orchestrator,
        reconcile_dlq=reconcile_dlq,
    )


def main() -> int:
    parser = argparse.ArgumentParser(description="Consume document ingestion events.")
    parser.add_argument(
        "--once",
        action="store_true",
        help="Process at most one SQS receive batch, then exit.",
    )
    parser.add_argument(
        "--dlq-once",
        action="store_true",
        help="Reconcile at most one exhausted DLQ receive batch, then exit.",
    )
    arguments = parser.parse_args()
    settings = get_settings()
    configure_structured_logging(
        service_name=settings.service_name,
        environment=settings.environment,
    )
    telemetry = configure_telemetry(settings)
    stop_event = threading.Event()

    def request_shutdown(
        signal_number: int,
        frame: FrameType | None,
    ) -> None:
        del frame
        logger.info(
            "Ingestion worker shutdown requested.",
            extra={
                "event_name": "ingestion.worker.shutdown_requested.v1",
                "signal": signal_number,
            },
        )
        stop_event.set()

    signal.signal(signal.SIGTERM, request_shutdown)
    signal.signal(signal.SIGINT, request_shutdown)
    try:
        worker = (
            build_worker(stop_event, reconcile_dlq=True)
            if arguments.dlq_once
            else build_worker(stop_event)
        )

        if arguments.once or arguments.dlq_once:
            worker.run_once()
        else:
            worker.run()
    finally:
        telemetry.shutdown()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
