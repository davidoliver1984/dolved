import time
from enum import StrEnum
from typing import Any

import httpx
from opentelemetry import metrics, trace
from opentelemetry.propagate import inject
from opentelemetry.trace import SpanKind, Status, StatusCode

from app.ingestion.signing import IngestionWorkerSigner
from app.telemetry import metric_attributes, trace_attributes


class ClaimDisposition(StrEnum):
    ACKNOWLEDGE = "acknowledge"
    POISON = "poison"
    RETRY = "retry"


class IngestionClaimClient:
    def __init__(
        self,
        *,
        base_url: str,
        timeout_seconds: float,
        signer: IngestionWorkerSigner,
        client: httpx.Client | None = None,
    ) -> None:
        self._base_url = base_url.rstrip("/")
        self._signer = signer
        self._client = client or httpx.Client(timeout=timeout_seconds)

    def claim(
        self,
        *,
        raw_body: str,
        event_id: str,
        timestamp: int | None = None,
    ) -> ClaimDisposition:
        path = f"/api/internal/ingestion/events/{event_id}/claim"
        body = raw_body.encode("utf-8")
        headers = self._signer.sign(
            timestamp=int(time.time()) if timestamp is None else timestamp,
            method="POST",
            request_path=path,
            body=body,
            event_id=event_id,
            purpose="ingestion.claim",
        ).as_http_headers()
        attributes: dict[str, Any] = {
            "http.request.method": "POST",
            "http.route": "/api/internal/ingestion/events/{event}/claim",
            "rag.event.id": event_id,
        }
        started_at = time.perf_counter()
        meter = metrics.get_meter("maketime.python.ingestion.claim")
        request_count = meter.create_counter(
            "rag.ingestion.claim.request.count",
            unit="{request}",
            description="Count of Laravel ingestion claim requests.",
        )
        request_duration = meter.create_histogram(
            "rag.ingestion.claim.request.duration",
            unit="s",
            description="Duration of Laravel ingestion claim requests.",
        )

        with trace.get_tracer("maketime.python.ingestion.claim").start_as_current_span(
            "POST /api/internal/ingestion/events/{event}/claim",
            kind=SpanKind.CLIENT,
            attributes=trace_attributes(attributes),
            record_exception=False,
            set_status_on_exception=False,
        ) as span:
            try:
                inject(headers)
                response = self._client.post(
                    f"{self._base_url}{path}",
                    content=body,
                    headers=headers,
                )
                attributes["http.response.status_code"] = response.status_code
                disposition = self._disposition(response)
            except httpx.HTTPError as exception:
                attributes["error.type"] = type(exception).__name__
                disposition = ClaimDisposition.RETRY

            attributes["rag.processing.outcome"] = disposition.value
            span.set_attributes(trace_attributes(attributes))

            if disposition is not ClaimDisposition.ACKNOWLEDGE:
                span.set_status(Status(StatusCode.ERROR))

            metric_values = metric_attributes(attributes)
            request_count.add(1, metric_values)
            request_duration.record(
                time.perf_counter() - started_at,
                metric_values,
            )

            return disposition

    def _disposition(self, response: httpx.Response) -> ClaimDisposition:

        response_code = self._response_code(response)

        if response.status_code == 200 and response_code in {
            "claimed",
            "already_claimed",
        }:
            return ClaimDisposition.ACKNOWLEDGE

        if response.status_code == 409 and response_code == "stale_event":
            return ClaimDisposition.ACKNOWLEDGE

        if response.status_code in {401, 403, 429} or response.status_code >= 500:
            return ClaimDisposition.RETRY

        if response.status_code in {404, 409, 422}:
            return ClaimDisposition.POISON

        return ClaimDisposition.RETRY

    @staticmethod
    def _response_code(response: httpx.Response) -> str | None:
        try:
            payload = response.json()
        except ValueError:
            return None

        if not isinstance(payload, dict):
            return None

        data = payload.get("data")

        if isinstance(data, dict) and isinstance(data.get("outcome"), str):
            return data["outcome"]

        error = payload.get("error")

        if isinstance(error, dict) and isinstance(error.get("code"), str):
            return error["code"]

        return None
