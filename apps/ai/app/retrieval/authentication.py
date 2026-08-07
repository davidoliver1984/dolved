import base64
import binascii
import hashlib
import hmac
import re
import threading
import time
from collections.abc import Callable, Mapping
from dataclasses import dataclass
from uuid import UUID

from pydantic import SecretStr

KEY_ID_HEADER = "X-Retrieval-Caller-Key-ID"
TIMESTAMP_HEADER = "X-Retrieval-Caller-Timestamp"
WORKSPACE_ID_HEADER = "X-Retrieval-Caller-Workspace-ID"
REQUEST_ID_HEADER = "X-Retrieval-Caller-Request-ID"
PURPOSE_HEADER = "X-Retrieval-Caller-Purpose"
SIGNATURE_HEADER = "X-Retrieval-Caller-Signature"
KEY_ID_PATTERN = re.compile(r"^[A-Za-z0-9._-]{1,64}$")
TIMESTAMP_PATTERN = re.compile(r"^(0|[1-9][0-9]{0,11})$")
SIGNATURE_PATTERN = re.compile(r"^rc1=[0-9a-f]{64}$")


class RetrievalAuthenticationError(ValueError):
    def __init__(self, reason: str) -> None:
        super().__init__("retrieval caller authentication failed")
        self.reason = reason


class ReplayCache:
    """Process-local, freshness-window-bounded replay suppression for rc1."""

    def __init__(self) -> None:
        self._accepted: dict[tuple[str, UUID], int] = {}
        self._lock = threading.Lock()

    def accept(self, principal: str, request_id: UUID, *, now: int, ttl: int) -> bool:
        with self._lock:
            self._accepted = {
                key: expires_at
                for key, expires_at in self._accepted.items()
                if expires_at >= now
            }
            key = (principal, request_id)
            if key in self._accepted:
                return False
            self._accepted[key] = now + ttl
            return True


@dataclass(frozen=True)
class AuthenticatedRetrievalCall:
    workspace_id: UUID
    request_id: UUID
    purpose: str


class RetrievalCallerAuthenticator:
    def __init__(
        self,
        *,
        keys: Mapping[str, SecretStr],
        max_clock_skew_seconds: int,
        replay_cache: ReplayCache,
        clock: Callable[[], float] = time.time,
    ) -> None:
        self._keys = keys
        self._max_clock_skew_seconds = max_clock_skew_seconds
        self._replay_cache = replay_cache
        self._clock = clock

    def verify(
        self,
        *,
        headers: Mapping[str, str],
        method: str,
        request_path: str,
        body: bytes,
        expected_purpose: str,
    ) -> AuthenticatedRetrievalCall:
        key_id = headers.get(KEY_ID_HEADER)
        timestamp = headers.get(TIMESTAMP_HEADER)
        workspace_text = headers.get(WORKSPACE_ID_HEADER)
        request_text = headers.get(REQUEST_ID_HEADER)
        purpose = headers.get(PURPOSE_HEADER)
        signature = headers.get(SIGNATURE_HEADER)

        if key_id is None or KEY_ID_PATTERN.fullmatch(key_id) is None:
            raise RetrievalAuthenticationError("key_id")
        if timestamp is None or TIMESTAMP_PATTERN.fullmatch(timestamp) is None:
            raise RetrievalAuthenticationError("timestamp")
        try:
            workspace_id = UUID(workspace_text or "")
            request_id = UUID(request_text or "")
        except ValueError as exception:
            raise RetrievalAuthenticationError("identity") from exception
        if str(workspace_id) != workspace_text or str(request_id) != request_text:
            raise RetrievalAuthenticationError("identity")
        if purpose != expected_purpose:
            raise RetrievalAuthenticationError("purpose")
        if signature is None or SIGNATURE_PATTERN.fullmatch(signature) is None:
            raise RetrievalAuthenticationError("signature_format")

        now = int(self._clock())
        if abs(now - int(timestamp)) > self._max_clock_skew_seconds:
            raise RetrievalAuthenticationError("timestamp_window")
        configured = self._keys.get(key_id)
        if configured is None:
            raise RetrievalAuthenticationError("unknown_key")
        secret = self._decode_secret(configured)
        body_digest = hashlib.sha256(body).hexdigest()
        canonical = (
            f"{timestamp}\n{method.upper()}\n{request_path}\n{body_digest}\n"
            f"{workspace_text}\n{purpose}\n{request_text}"
        )
        expected = hmac.new(secret, canonical.encode(), hashlib.sha256).hexdigest()
        if not hmac.compare_digest(expected, signature.removeprefix("rc1=")):
            raise RetrievalAuthenticationError("signature_mismatch")
        if not self._replay_cache.accept(
            "retrieval-caller",
            request_id,
            now=now,
            ttl=self._max_clock_skew_seconds,
        ):
            raise RetrievalAuthenticationError("replay")
        return AuthenticatedRetrievalCall(workspace_id, request_id, purpose)

    @staticmethod
    def _decode_secret(value: SecretStr) -> bytes:
        encoded = value.get_secret_value()
        try:
            decoded = base64.b64decode(encoded, validate=True)
        except (binascii.Error, ValueError) as exception:
            raise RetrievalAuthenticationError("invalid_key") from exception
        if len(decoded) < 32 or base64.b64encode(decoded).decode() != encoded:
            raise RetrievalAuthenticationError("invalid_key")
        return decoded
