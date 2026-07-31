import sys
from typing import Any

from app import worker as worker_entrypoint


class FakeWorker:
    def __init__(self) -> None:
        self.once_calls = 0
        self.run_calls = 0

    def run_once(self) -> int:
        self.once_calls += 1
        return 0

    def run(self) -> None:
        self.run_calls += 1


class FakeTelemetry:
    def __init__(self) -> None:
        self.shutdown_calls = 0

    def shutdown(self) -> None:
        self.shutdown_calls += 1


def test_once_mode_processes_one_receive_batch(
    monkeypatch: Any,
) -> None:
    worker = FakeWorker()
    telemetry = FakeTelemetry()
    monkeypatch.setattr(
        worker_entrypoint,
        "build_worker",
        lambda stop_event: worker,
    )
    monkeypatch.setattr(
        worker_entrypoint,
        "configure_telemetry",
        lambda settings: telemetry,
    )
    monkeypatch.setattr(sys, "argv", ["worker", "--once"])

    assert worker_entrypoint.main() == 0
    assert worker.once_calls == 1
    assert worker.run_calls == 0
    assert telemetry.shutdown_calls == 1
