import httpx

from app.ingestion.artifact_upload import HttpxArtifactUploader


def test_uploader_uses_only_the_exact_authorised_request_without_retry() -> None:
    requests: list[httpx.Request] = []

    def respond(request: httpx.Request) -> httpx.Response:
        requests.append(request)
        return httpx.Response(
            200,
            headers={"ETag": '"identity"', "x-amz-version-id": "version-7"},
        )

    uploader = HttpxArtifactUploader(
        timeout_seconds=1,
        client=httpx.Client(transport=httpx.MockTransport(respond)),
    )
    result = uploader.upload(
        url="https://objects.example/exact-key",
        method="PUT",
        headers={"If-None-Match": "*", "Content-Type": "application/json"},
        content=b'{"contract_version":"document-extraction-artifact-v1"}',
    )

    assert len(requests) == 1
    assert requests[0].url == httpx.URL("https://objects.example/exact-key")
    assert requests[0].headers["if-none-match"] == "*"
    assert result.storage_etag == '"identity"'
    assert result.storage_version_id == "version-7"
