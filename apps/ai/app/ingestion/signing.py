import base64
import binascii
import hashlib
import hmac
import uuid
from dataclasses import dataclass


class InvalidSigningConfiguration(ValueError):
    """Raised when the worker signing identity is unsafe or malformed."""


@dataclass(frozen=True)
class SignedHeaders:
    key_id: str
    timestamp: str
    event_id: str
    signature: str
    purpose: str

    def as_http_headers(self) -> dict[str, str]:
        return {
            "Content-Type": "application/json",
            "X-Ingestion-Worker-Key-ID": self.key_id,
            "X-Ingestion-Worker-Timestamp": self.timestamp,
            "X-Ingestion-Worker-Event-ID": self.event_id,
            "X-Ingestion-Worker-Signature": self.signature,
            "X-Ingestion-Worker-Purpose": self.purpose,
        }


class IngestionWorkerSigner:
    def __init__(self, key_id: str, encoded_secret: str) -> None:
        if (
            not key_id
            or len(key_id) > 64
            or any(
                character
                not in "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789._-"
                for character in key_id
            )
        ):
            raise InvalidSigningConfiguration("The HMAC Key ID is invalid.")

        try:
            secret = base64.b64decode(encoded_secret, validate=True)
        except (binascii.Error, ValueError) as exception:
            raise InvalidSigningConfiguration(
                "The HMAC secret is not valid Base64."
            ) from exception

        if base64.b64encode(secret).decode("ascii") != encoded_secret:
            raise InvalidSigningConfiguration(
                "The HMAC secret is not canonical Base64."
            )

        if len(secret) < 32:
            raise InvalidSigningConfiguration(
                "The HMAC secret must decode to at least 32 bytes."
            )

        self._key_id = key_id
        self._secret = secret

    def sign(
        self,
        *,
        timestamp: int,
        method: str,
        request_path: str,
        body: bytes,
        event_id: str,
        purpose: str,
    ) -> SignedHeaders:
        try:
            canonical_event_id = str(uuid.UUID(event_id))
        except ValueError as exception:
            raise InvalidSigningConfiguration(
                "The logical event ID is not a UUID."
            ) from exception

        if event_id != canonical_event_id:
            raise InvalidSigningConfiguration(
                "The logical event ID must be a canonical lowercase UUID."
            )

        timestamp_text = str(timestamp)
        body_digest = hashlib.sha256(body).hexdigest()
        canonical = (
            f"{timestamp_text}\n{method.upper()}\n{request_path}\n"
            f"{body_digest}\n{event_id}\n{purpose}"
        )
        signature = hmac.new(
            self._secret,
            canonical.encode("utf-8"),
            hashlib.sha256,
        ).hexdigest()

        return SignedHeaders(
            key_id=self._key_id,
            timestamp=timestamp_text,
            event_id=event_id,
            signature=f"v2={signature}",
            purpose=purpose,
        )
