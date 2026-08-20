import json
import time
from collections.abc import Callable
from dataclasses import dataclass
from typing import Any

import httpx
from opentelemetry.propagate import inject

from app.ingestion.signing import IngestionWorkerSigner


class IngestionProtocolError(RuntimeError):
    def __init__(self, code: str, *, retryable: bool, status_code: int | None = None):
        super().__init__(code)
        self.code = code
        self.retryable = retryable
        self.status_code = status_code


@dataclass(frozen=True)
class ClaimGrant:
    outcome: str
    document_status: str
    lease_token: str | None = None
    lease_expires_at: str | None = None
    embedding_space_generation_id: str | None = None
    workspace_corpus_generation_id: str | None = None
    vector_space: dict[str, Any] | None = None
    sparse_space_generation_id: str | None = None
    resume_sealed_attempt: bool = False
    reset_open_attempt: bool = False

    @property
    def may_process(self) -> bool:
        return self.outcome in {"claimed", "reclaimed"} and self.lease_token is not None

    @property
    def is_terminal(self) -> bool:
        return self.outcome in {
            "already_completed",
            "permanently_failed",
            "stale_event",
        }


class IngestionProtocolClient:
    def __init__(
        self,
        *,
        base_url: str,
        timeout_seconds: float,
        signer: IngestionWorkerSigner,
        client: httpx.Client | None = None,
        max_attempts: int = 3,
        initial_backoff_seconds: float = 0.25,
        sleep: Callable[[float], None] = time.sleep,
    ) -> None:
        self._base_url = base_url.rstrip("/")
        self._signer = signer
        self._client = client or httpx.Client(timeout=timeout_seconds)
        self._max_attempts = max(1, max_attempts)
        self._initial_backoff_seconds = max(0.0, initial_backoff_seconds)
        self._sleep = sleep

    def claim(self, *, raw_body: str, event_id: str) -> ClaimGrant:
        data = self._request(
            event_id=event_id,
            suffix="claim",
            purpose="ingestion.claim",
            raw_body=raw_body.encode(),
            accepted_data_statuses={409, 423},
        )
        return ClaimGrant(**data)

    def renew(self, context: dict[str, Any]) -> dict[str, Any]:
        return self._operation(context, "lease/renew", "ingestion.lease.renew")

    def submit_chunks(
        self, context: dict[str, Any], chunks: list[dict[str, Any]]
    ) -> dict[str, Any]:
        return self._operation(
            context, "chunks", "ingestion.chunks.submit", {"chunks": chunks}
        )

    def seal(self, context: dict[str, Any], evidence: dict[str, Any]) -> dict[str, Any]:
        return self._operation(
            context, "chunks/seal", "ingestion.chunks.seal", evidence
        )

    def resume(
        self, context: dict[str, Any], *, cursor: int = 0, limit: int = 50
    ) -> dict[str, Any]:
        return self._operation(
            context,
            "resume",
            "ingestion.attempt.resume",
            {"cursor": cursor, "limit": limit},
        )

    def authorise_publication(
        self, context: dict[str, Any], evidence: dict[str, Any]
    ) -> dict[str, Any]:
        return self._operation(
            context,
            "publication/authorise",
            "ingestion.publication.authorise",
            evidence,
        )

    def complete(
        self, context: dict[str, Any], evidence: dict[str, Any]
    ) -> dict[str, Any]:
        return self._operation(
            context,
            "complete",
            "ingestion.complete",
            {**evidence, "publication_verified": True},
        )

    def fail(
        self,
        context: dict[str, Any],
        *,
        failure_code: str,
        failure_message: str,
        usage: list[dict[str, Any]] | None = None,
    ) -> dict[str, Any]:
        return self._operation(
            context,
            "fail",
            "ingestion.fail",
            {
                "classification": "permanent",
                "failure_code": failure_code,
                "failure_message": failure_message,
                "usage": usage or [],
            },
        )

    def cancel(
        self, context: dict[str, Any], usage: list[dict[str, Any]] | None = None
    ) -> dict[str, Any]:
        return self._operation(
            context,
            "cancel",
            "ingestion.attempt.cancel",
            {"usage": usage or []},
        )

    def _operation(
        self,
        context: dict[str, Any],
        suffix: str,
        purpose: str,
        additional: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        payload = {
            "contract_version": 1,
            "event_id": context["event_id"],
            "workspace_id": context["workspace_id"],
            "document_id": context["document_id"],
            "lease_token": context["lease_token"],
            **(additional or {}),
        }
        body = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode()
        return self._request(
            event_id=context["event_id"],
            suffix=suffix,
            purpose=purpose,
            raw_body=body,
        )

    def _request(
        self,
        *,
        event_id: str,
        suffix: str,
        purpose: str,
        raw_body: bytes,
        accepted_data_statuses: set[int] | None = None,
    ) -> dict[str, Any]:
        path = f"/api/internal/ingestion/events/{event_id}/{suffix}"
        headers = self._signer.sign(
            timestamp=int(time.time()),
            method="POST",
            request_path=path,
            body=raw_body,
            event_id=event_id,
            purpose=purpose,
        ).as_http_headers()
        headers["Content-Type"] = "application/json"
        inject(headers)
        last_error: IngestionProtocolError | None = None
        for attempt in range(1, self._max_attempts + 1):
            try:
                response = self._client.post(
                    f"{self._base_url}{path}", content=raw_body, headers=headers
                )
            except httpx.HTTPError as exception:
                last_error = IngestionProtocolError("transport_error", retryable=True)
                last_error.__cause__ = exception
            else:
                payload = self._json(response)
                if 200 <= response.status_code < 300 or (
                    accepted_data_statuses is not None
                    and response.status_code in accepted_data_statuses
                ):
                    data = payload.get("data")
                    if isinstance(data, dict):
                        return data
                    last_error = IngestionProtocolError(
                        "malformed_response",
                        retryable=True,
                        status_code=response.status_code,
                    )
                else:
                    last_error = IngestionProtocolError(
                        self._error_code(payload) or "protocol_error",
                        retryable=response.status_code == 429
                        or response.status_code >= 500,
                        status_code=response.status_code,
                    )
            if not last_error.retryable or attempt == self._max_attempts:
                raise last_error
            self._sleep(self._initial_backoff_seconds * (2 ** (attempt - 1)))

        raise RuntimeError("protocol retry loop completed unexpectedly")

    @staticmethod
    def _json(response: httpx.Response) -> dict[str, Any]:
        try:
            payload = response.json()
        except ValueError:
            return {}
        return payload if isinstance(payload, dict) else {}

    @staticmethod
    def _error_code(payload: dict[str, Any]) -> str | None:
        error = payload.get("error")
        return error.get("code") if isinstance(error, dict) else None
