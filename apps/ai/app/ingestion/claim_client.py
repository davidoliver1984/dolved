import time
from enum import StrEnum

import httpx

from app.ingestion.signing import IngestionWorkerSigner


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
        ).as_http_headers()

        try:
            response = self._client.post(
                f"{self._base_url}{path}",
                content=body,
                headers=headers,
            )
        except httpx.HTTPError:
            return ClaimDisposition.RETRY

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
