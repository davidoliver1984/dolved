import threading
from collections.abc import Callable
from typing import Self


class CoordinatedHeartbeat:
    """Renews the Laravel lease and SQS visibility on one timing cadence."""

    def __init__(
        self,
        *,
        interval_seconds: float,
        renew_lease: Callable[[], object],
        extend_visibility: Callable[[], object],
    ) -> None:
        self._interval = interval_seconds
        self._renew_lease = renew_lease
        self._extend_visibility = extend_visibility
        self._stop = threading.Event()
        self._healthy = threading.Event()
        self._healthy.set()
        self._thread = threading.Thread(target=self._run, daemon=True)
        self._failure: Exception | None = None

    def __enter__(self) -> Self:
        self._thread.start()
        return self

    def __exit__(self, *_: object) -> None:
        self._stop.set()
        self._thread.join(timeout=max(1.0, self._interval))

    def assert_healthy(self) -> None:
        if not self._healthy.is_set():
            raise HeartbeatLost(
                "The processing lease or SQS visibility is uncertain.",
                cause=self._failure,
            )

    def _run(self) -> None:
        while not self._stop.wait(self._interval):
            cycle_healthy = True
            try:
                self._renew_lease()
            except Exception as exception:  # noqa: BLE001 -- loss of either authority is fatal
                cycle_healthy = False
                if self._failure is None:
                    self._failure = exception
            try:
                self._extend_visibility()
            except Exception as exception:  # noqa: BLE001 -- both operations must be attempted
                cycle_healthy = False
                if self._failure is None:
                    self._failure = exception
            if not cycle_healthy:
                self._healthy.clear()
                return


class HeartbeatLost(RuntimeError):
    def __init__(self, message: str, *, cause: Exception | None = None) -> None:
        super().__init__(message)
        self.cause = cause
