import pytest

from app.ingestion.signing import (
    IngestionWorkerSigner,
    InvalidSigningConfiguration,
)

TEST_SECRET = "MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY="
EVENT_ID = "5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41"
PATH = f"/api/internal/ingestion/events/{EVENT_ID}/claim"


def test_signer_matches_the_purpose_scoped_v2_test_vector() -> None:
    signer = IngestionWorkerSigner("local-v1", TEST_SECRET)

    headers = signer.sign(
        timestamp=1_785_326_400,
        method="POST",
        request_path=PATH,
        body=b"{}",
        event_id=EVENT_ID,
        purpose="ingestion.claim",
    )

    assert headers.signature == (
        "v2=95565bdf500f5dabb99cfa3c1260664e3d94a4c47d5e489c5cd5b69e83751617"
    )
    assert headers.as_http_headers() == {
        "Content-Type": "application/json",
        "X-Ingestion-Worker-Key-ID": "local-v1",
        "X-Ingestion-Worker-Timestamp": "1785326400",
        "X-Ingestion-Worker-Event-ID": EVENT_ID,
        "X-Ingestion-Worker-Signature": headers.signature,
        "X-Ingestion-Worker-Purpose": "ingestion.claim",
    }


@pytest.mark.parametrize(
    ("key_id", "secret"),
    [
        ("contains space", TEST_SECRET),
        ("a" * 65, TEST_SECRET),
        ("local-v1", "not-base64"),
        ("local-v1", "c2hvcnQ="),
    ],
)
def test_signer_rejects_unsafe_identity_configuration(
    key_id: str,
    secret: str,
) -> None:
    with pytest.raises(InvalidSigningConfiguration):
        IngestionWorkerSigner(key_id, secret)


def test_signature_binds_every_canonical_component() -> None:
    signer = IngestionWorkerSigner("local-v1", TEST_SECRET)
    baseline = signer.sign(
        timestamp=1_785_326_400,
        method="POST",
        request_path=PATH,
        body=b"{}",
        event_id=EVENT_ID,
        purpose="ingestion.claim",
    ).signature

    variants = [
        signer.sign(
            timestamp=1_785_326_401,
            method="POST",
            request_path=PATH,
            body=b"{}",
            event_id=EVENT_ID,
            purpose="ingestion.claim",
        ).signature,
        signer.sign(
            timestamp=1_785_326_400,
            method="PUT",
            request_path=PATH,
            body=b"{}",
            event_id=EVENT_ID,
            purpose="ingestion.claim",
        ).signature,
        signer.sign(
            timestamp=1_785_326_400,
            method="POST",
            request_path=f"{PATH}/different",
            body=b"{}",
            event_id=EVENT_ID,
            purpose="ingestion.claim",
        ).signature,
        signer.sign(
            timestamp=1_785_326_400,
            method="POST",
            request_path=PATH,
            body=b'{"changed":true}',
            event_id=EVENT_ID,
            purpose="ingestion.claim",
        ).signature,
        signer.sign(
            timestamp=1_785_326_400,
            method="POST",
            request_path=PATH,
            body=b"{}",
            event_id="7e6d5c4b-3a29-4180-9f8e-2d1c0b9a8f77",
            purpose="ingestion.claim",
        ).signature,
        signer.sign(
            timestamp=1_785_326_400,
            method="POST",
            request_path=PATH,
            body=b"{}",
            event_id=EVENT_ID,
            purpose="ingestion.complete",
        ).signature,
    ]

    assert all(signature != baseline for signature in variants)


@pytest.mark.parametrize(
    "event_id",
    [
        "not-a-uuid",
        EVENT_ID.upper(),
    ],
)
def test_signer_requires_a_canonical_lowercase_event_uuid(
    event_id: str,
) -> None:
    signer = IngestionWorkerSigner("local-v1", TEST_SECRET)

    with pytest.raises(InvalidSigningConfiguration):
        signer.sign(
            timestamp=1_785_326_400,
            method="POST",
            request_path=PATH,
            body=b"{}",
            event_id=event_id,
            purpose="ingestion.claim",
        )
