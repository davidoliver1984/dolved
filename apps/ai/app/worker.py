import argparse
import logging
import signal
import threading
from types import FrameType

import boto3  # type: ignore[import-untyped]

from app.ingestion.claim_client import IngestionClaimClient
from app.ingestion.signing import IngestionWorkerSigner
from app.ingestion.sqs import SqsIngestionQueue
from app.ingestion.worker import IngestionWorker
from app.settings import get_settings
from app.structured_logging import configure_structured_logging

logger = logging.getLogger("ingestion.worker")


def build_worker(stop_event: threading.Event) -> IngestionWorker:
    settings = get_settings()
    sqs_client = boto3.client(
        "sqs",
        region_name=settings.aws_default_region,
        endpoint_url=settings.aws_endpoint_url,
    )
    queue = SqsIngestionQueue(
        client=sqs_client,
        queue_name=settings.ingestion_queue,
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

    return IngestionWorker(
        queue=queue,
        claim_client=claim_client,
        stop_event=stop_event,
        error_wait_seconds=settings.ingestion_worker_error_wait_seconds,
    )


def main() -> int:
    parser = argparse.ArgumentParser(description="Consume document ingestion events.")
    parser.add_argument(
        "--once",
        action="store_true",
        help="Process at most one SQS receive batch, then exit.",
    )
    arguments = parser.parse_args()
    configure_structured_logging()
    stop_event = threading.Event()

    def request_shutdown(
        signal_number: int,
        frame: FrameType | None,
    ) -> None:
        del frame
        logger.info(
            "Ingestion worker shutdown requested.",
            extra={"signal": signal_number},
        )
        stop_event.set()

    signal.signal(signal.SIGTERM, request_shutdown)
    signal.signal(signal.SIGINT, request_shutdown)
    worker = build_worker(stop_event)

    if arguments.once:
        worker.run_once()
    else:
        worker.run()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
