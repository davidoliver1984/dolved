import json
from pathlib import Path
from uuid import UUID

import pytest
from pydantic import SecretStr

from app.retrieval.authentication import (
    ReplayCache,
    RetrievalAuthenticationError,
    RetrievalCallerAuthenticator,
)


def vector() -> dict[str, str]:
    path = Path("/contracts/http/retrieval-call/rc1/canonicalisation-vectors.json")
    return json.loads(path.read_text())["vectors"][0]


def headers(item: dict[str, str]) -> dict[str, str]:
    return {
        "X-Retrieval-Caller-Key-ID": "test-key",
        "X-Retrieval-Caller-Timestamp": item["timestamp"],
        "X-Retrieval-Caller-Workspace-ID": item["workspace_id"],
        "X-Retrieval-Caller-Request-ID": item["request_id"],
        "X-Retrieval-Caller-Purpose": item["purpose"],
        "X-Retrieval-Caller-Signature": item["expected_signature"],
    }


def authenticator(
    item: dict[str, str], cache: ReplayCache | None = None
) -> RetrievalCallerAuthenticator:
    return RetrievalCallerAuthenticator(
        keys={"test-key": SecretStr(item["secret_base64"])},
        max_clock_skew_seconds=300,
        replay_cache=cache or ReplayCache(),
        clock=lambda: float(item["timestamp"]),
    )


def verify(
    verifier: RetrievalCallerAuthenticator,
    item: dict[str, str],
    *,
    expected_purpose: str = "retrieval.search",
):
    return verifier.verify(
        headers=headers(item),
        method=item["method"],
        request_path=item["request_path"],
        body=item["exact_body"].encode(),
        expected_purpose=expected_purpose,
    )


def test_normative_adr_vector_authenticates() -> None:
    item = vector()
    result = verify(authenticator(item), item)

    assert result.workspace_id == UUID(item["workspace_id"])
    assert result.request_id == UUID(item["request_id"])


def test_replay_wrong_purpose_and_expired_requests_are_rejected() -> None:
    item = vector()
    cache = ReplayCache()
    verifier = authenticator(item, cache)
    verify(verifier, item)
    with pytest.raises(
        RetrievalAuthenticationError, match="authentication failed"
    ) as replay:
        verify(verifier, item)
    assert replay.value.reason == "replay"

    with pytest.raises(RetrievalAuthenticationError) as wrong_purpose:
        verify(authenticator(item), item, expected_purpose="retrieval.plan")
    assert wrong_purpose.value.reason == "purpose"

    expired = RetrievalCallerAuthenticator(
        keys={"test-key": SecretStr(item["secret_base64"])},
        max_clock_skew_seconds=300,
        replay_cache=ReplayCache(),
        clock=lambda: float(item["timestamp"]) + 301,
    )
    with pytest.raises(RetrievalAuthenticationError) as stale:
        verify(expired, item)
    assert stale.value.reason == "timestamp_window"
