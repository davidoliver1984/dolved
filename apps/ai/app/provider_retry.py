import re
import threading
import time
from collections.abc import Callable
from datetime import UTC, datetime
from email.utils import parsedate_to_datetime

import httpx
from pydantic import Field

from app.extraction.models import ImmutableModel

RATE_LIMIT_RESET_HEADERS = (
    "ratelimit-reset",
    "x-ratelimit-reset-requests",
    "x-ratelimit-reset-tokens",
)


class ProviderRetryDelay(ImmutableModel):
    delay_seconds: float = Field(ge=0)
    source: str = Field(
        pattern=r"^(retry_after_numeric|retry_after_http_date|ratelimit_reset|x_ratelimit_reset_requests|x_ratelimit_reset_tokens|configured_fallback|shared_cooldown)$"
    )


class ProviderCooldown:
    """Coordinate provider-directed cooldowns within one AI process."""

    def __init__(self, *, monotonic: Callable[[], float] = time.monotonic) -> None:
        self._monotonic = monotonic
        self._state_lock = threading.Lock()
        self._probe_lock = threading.Lock()
        self._not_before = 0.0

    def extend(self, delay_seconds: float) -> None:
        if delay_seconds <= 0:
            return
        with self._state_lock:
            self._not_before = max(self._not_before, self._monotonic() + delay_seconds)

    def remaining_seconds(self) -> float:
        with self._state_lock:
            return max(0.0, self._not_before - self._monotonic())

    def acquire_probe(self) -> threading.Lock:
        return self._probe_lock

    def clear(self) -> None:
        with self._state_lock:
            self._not_before = 0.0


def voyage_provider_timing(
    headers: httpx.Headers,
    *,
    now: Callable[[], datetime],
) -> tuple[float | None, str | None]:
    """Return only the longest supported, privacy-safe provider cooldown."""

    candidates: list[tuple[float, str]] = []
    retry_after = _retry_after_seconds(headers.get("retry-after"), now=now)
    if retry_after is not None:
        raw = headers.get("retry-after", "")
        source = (
            "retry_after_numeric"
            if _number(raw) is not None
            else "retry_after_http_date"
        )
        candidates.append((retry_after, source))
    for name in RATE_LIMIT_RESET_HEADERS:
        delay = _reset_seconds(headers.get(name), now=now)
        if delay is not None:
            candidates.append((delay, name.replace("-", "_")))
    return max(candidates, default=(None, None), key=lambda value: value[0])


def _retry_after_seconds(
    value: str | None,
    *,
    now: Callable[[], datetime],
) -> float | None:
    if value is None:
        return None
    try:
        return max(0.0, float(value))
    except ValueError:
        try:
            retry_at = parsedate_to_datetime(value)
        except TypeError, ValueError, OverflowError:
            return None
        if retry_at.tzinfo is None:
            retry_at = retry_at.replace(tzinfo=UTC)
        return max(0.0, (retry_at - now()).total_seconds())


def _reset_seconds(
    value: str | None,
    *,
    now: Callable[[], datetime],
) -> float | None:
    if value is None:
        return None
    number = _number(value)
    if number is not None:
        now_epoch = now().timestamp()
        return (
            max(0.0, number - now_epoch) if number > 1_000_000_000 else max(0.0, number)
        )
    match = re.fullmatch(r"\s*(\d+(?:\.\d+)?)\s*(ms|s|m|h)\s*", value)
    if match is None:
        return _retry_after_seconds(value, now=now)
    multiplier = {"ms": 0.001, "s": 1.0, "m": 60.0, "h": 3600.0}[match.group(2)]
    return float(match.group(1)) * multiplier


def _number(value: str) -> float | None:
    try:
        return float(value)
    except ValueError:
        return None


# Voyage capacity is treated as one provider/account window within this process.
voyage_provider_cooldown = ProviderCooldown()
