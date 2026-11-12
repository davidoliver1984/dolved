import pytest


@pytest.fixture
def accepted_interpretation(monkeypatch):
    """Isolate existing schema/transport tests; NOT a semantic-coverage proof.

    Real check transport and rejection orchestration are exercised separately in
    test_interpretation_check. No production setting disables the check.
    """
    monkeypatch.setattr(
        "app.retrieval.needs_planner.review_interpretation",
        lambda **kw: {
            "version": "interpretation-check-v1",
            "code": "accepted",
            "provider": "openai",
            "model": "gpt-5-mini",
            "request_count": 1,
            "input_tokens": 0,
            "output_tokens": 0,
            "latency_ms": 0,
            "cost_basis": "unavailable",
            "cost_usd": None,
        },
    )
