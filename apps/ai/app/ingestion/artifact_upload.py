from dataclasses import dataclass
from typing import Protocol

import httpx


@dataclass(frozen=True)
class ArtifactUploadResult:
    storage_etag: str | None
    storage_version_id: str | None


class ArtifactUploader(Protocol):
    def upload(
        self,
        *,
        url: str,
        method: str,
        headers: dict[str, str],
        content: bytes,
    ) -> ArtifactUploadResult: ...


class HttpxArtifactUploader:
    """Upload one canonical artifact to Laravel's exact authorised request."""

    def __init__(self, *, timeout_seconds: float, client: httpx.Client | None = None):
        self._client = client or httpx.Client(timeout=timeout_seconds)

    def upload(
        self,
        *,
        url: str,
        method: str,
        headers: dict[str, str],
        content: bytes,
    ) -> ArtifactUploadResult:
        if method != "PUT":
            raise ValueError("unsupported artifact upload method")
        response = self._client.request(method, url, headers=headers, content=content)
        response.raise_for_status()
        return ArtifactUploadResult(
            storage_etag=response.headers.get("etag"),
            storage_version_id=response.headers.get("x-amz-version-id"),
        )
