import json
import time
from dataclasses import dataclass
from typing import Any

import httpx

from app.ingestion.protocol_client import IngestionProtocolError
from app.ingestion.signing import IngestionWorkerSigner


@dataclass(frozen=True)
class DeletionGrant:
    outcome: str
    lease_token: str | None = None
    lease_expires_at: str | None = None
    vector_scopes: tuple[dict[str, Any], ...] = ()

    @property
    def may_process(self) -> bool:
        return self.outcome == "claimed" and self.lease_token is not None


class DocumentDeletionClient:
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

    def claim(self, *, event_id: str, raw_body: str) -> DeletionGrant:
        data = self._request(
            event_id=event_id,
            suffix="claim",
            purpose="document.deletion.claim",
            body=raw_body.encode(),
        )
        scopes = data.get("vector_scopes", ())
        return DeletionGrant(
            outcome=str(data["outcome"]),
            lease_token=data.get("lease_token"),
            lease_expires_at=data.get("lease_expires_at"),
            vector_scopes=tuple(scopes) if isinstance(scopes, list) else (),
        )

    def complete(self, context: dict[str, Any], scopes: list[dict[str, Any]]) -> None:
        self._operation(
            context, "complete", "document.deletion.complete", {"scopes": scopes}
        )

    def fail(
        self,
        context: dict[str, Any],
        *,
        classification: str,
        failure_code: str,
        failure_message: str,
    ) -> None:
        self._operation(
            context,
            "fail",
            "document.deletion.fail",
            {
                "classification": classification,
                "failure_code": failure_code,
                "failure_message": failure_message,
            },
        )

    def _operation(
        self,
        context: dict[str, Any],
        suffix: str,
        purpose: str,
        additional: dict[str, Any],
    ) -> dict[str, Any]:
        body = json.dumps(
            {
                "contract_version": 1,
                "event_id": context["event_id"],
                "workspace_id": context["workspace_id"],
                "document_id": context["document_id"],
                "lease_token": context["lease_token"],
                **additional,
            },
            ensure_ascii=False,
            separators=(",", ":"),
        ).encode()
        return self._request(
            event_id=str(context["event_id"]),
            suffix=suffix,
            purpose=purpose,
            body=body,
        )

    def _request(
        self, *, event_id: str, suffix: str, purpose: str, body: bytes
    ) -> dict[str, Any]:
        path = f"/api/internal/document-deletions/{event_id}/{suffix}"
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
        if not 200 <= response.status_code < 300 and response.status_code != 423:
            code = payload.get("error", {}).get("code", "protocol_error")
            raise IngestionProtocolError(
                str(code),
                retryable=response.status_code == 429 or response.status_code >= 500,
                status_code=response.status_code,
            )
        data = payload.get("data")
        if not isinstance(data, dict):
            raise IngestionProtocolError(
                "malformed_response", retryable=True, status_code=response.status_code
            )
        return data
