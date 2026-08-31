import json
import time
from typing import Any

import httpx

from app.ingestion.protocol_client import IngestionProtocolError
from app.ingestion.signing import IngestionWorkerSigner


class ImportPreflightClient:
    def __init__(
        self,
        *,
        base_url: str,
        timeout_seconds: float,
        signer: IngestionWorkerSigner,
        client: httpx.Client | None = None,
    ) -> None:
        self._base_url = base_url.rstrip("/")
        self._client = client or httpx.Client(timeout=timeout_seconds)
        self._signer = signer

    def complete(self, event: dict[str, Any], result: dict[str, Any]) -> str:
        return self._post(event, "complete", "import.preflight.complete", result)

    def fail(self, event: dict[str, Any], diagnostic_code: str) -> str:
        return self._post(
            event,
            "fail",
            "import.preflight.fail",
            {"diagnostic_code": diagnostic_code},
        )

    def _post(
        self,
        event: dict[str, Any],
        suffix: str,
        purpose: str,
        additional: dict[str, Any],
    ) -> str:
        event_id = str(event["event_id"])
        body = json.dumps(
            {
                "contract_version": "import-preflight-v1",
                "event_id": event_id,
                "workspace_id": event["workspace_id"],
                "import_item_id": event["import_item_id"],
                "staged_object_key": event["staged_object"]["key"],
                "lease_token": event["lease_token"],
                "lease_generation": event["lease_generation"],
                **additional,
            },
            ensure_ascii=False,
            separators=(",", ":"),
        ).encode()
        path = f"/api/internal/import-preflights/{event_id}/{suffix}"
        headers = self._signer.sign(
            timestamp=int(time.time()),
            method="POST",
            request_path=path,
            body=body,
            event_id=event_id,
            purpose=purpose,
        ).as_http_headers()
        response = self._client.post(
            f"{self._base_url}{path}", content=body, headers=headers
        )
        try:
            payload = response.json()
        except ValueError as exception:
            raise IngestionProtocolError(
                "malformed_response", retryable=True, status_code=response.status_code
            ) from exception
        if not 200 <= response.status_code < 300:
            code = payload.get("error", {}).get("code", "protocol_error")
            raise IngestionProtocolError(
                str(code),
                retryable=response.status_code >= 500,
                status_code=response.status_code,
            )
        outcome = payload.get("data", {}).get("outcome")
        if outcome not in {"recorded", "duplicate"}:
            raise IngestionProtocolError(
                "malformed_response", retryable=True, status_code=response.status_code
            )
        return str(outcome)
