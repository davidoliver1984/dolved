import json
from typing import Literal
from uuid import UUID, uuid4

import httpx
import pytest
from pydantic import SecretStr

from app.reranking.errors import (
    MalformedRerankerResponseError,
    RerankerInputTooLargeError,
    RerankerRateLimitError,
)
from app.reranking.fake import DeterministicReranker
from app.reranking.models import (
    RerankCandidate,
    RerankerProfile,
    RerankRequest,
)
from app.reranking.voyage import VoyageReranker
from app.retrieval.fusion import ReciprocalRankFusion
from app.retrieval.models import RetrievalCandidate, RetrievalSide


def retrieval_candidate(
    chunk_id: UUID,
    *,
    rank: int,
    score: float,
    side: RetrievalSide = RetrievalSide.PRIMARY,
    method: Literal["dense", "sparse", "hybrid"] = "dense",
) -> RetrievalCandidate:
    return RetrievalCandidate(
        chunk_id=chunk_id,
        document_id=uuid4(),
        workspace_corpus_generation_id=uuid4(),
        embedding_space_generation_id=uuid4(),
        score=score,
        rank=rank,
        retrieval_method=method,
        side=side,
    )


def test_rrf_is_deterministic_deduplicated_and_preserves_source_lineage() -> None:
    shared = uuid4()
    dense_only = uuid4()
    sparse_only = uuid4()
    dense = (
        retrieval_candidate(shared, rank=1, score=0.91),
        retrieval_candidate(dense_only, rank=2, score=0.82),
    )
    sparse = (
        retrieval_candidate(sparse_only, rank=1, score=12.0, method="sparse"),
        retrieval_candidate(shared, rank=2, score=10.0, method="sparse"),
    )

    first = ReciprocalRankFusion(rrf_k=60).fuse(dense, sparse, limit=3)
    second = ReciprocalRankFusion(rrf_k=60).fuse(dense, sparse, limit=3)

    assert first == second
    assert [candidate.chunk_id for candidate in first] == [
        shared,
        sparse_only,
        dense_only,
    ]
    assert first[0].dense_rank == 1
    assert first[0].sparse_rank == 2
    assert first[0].dense_score == 0.91
    assert first[0].sparse_score == 10.0
    assert [candidate.rank for candidate in first] == [1, 2, 3]


def test_rrf_rejects_duplicate_identity_within_one_source_ranking() -> None:
    chunk_id = uuid4()
    duplicate = (
        retrieval_candidate(chunk_id, rank=1, score=1.0),
        retrieval_candidate(chunk_id, rank=2, score=0.5),
    )

    with pytest.raises(ValueError, match="duplicate candidate"):
        ReciprocalRankFusion(rrf_k=60).fuse(duplicate, (), limit=2)


def rerank_request(*, top_k: int = 2) -> RerankRequest:
    candidates = tuple(
        RerankCandidate(
            chunk_id=uuid4(),
            document_id=uuid4(),
            document_family_id=uuid4(),
            version_position=index,
            side=RetrievalSide.PRIMARY,
            text=f"canonical text {index}",
            fused_score=score,
            fused_rank=index,
        )
        for index, score in ((1, 0.4), (2, 0.9))
    )
    return RerankRequest(
        request_id=uuid4(),
        workspace_id=uuid4(),
        query="Which policy applies?",
        profile=RerankerProfile(
            provider="voyage",
            model="rerank-2.5",
            adapter_version="1",
            truncation=False,
        ),
        candidates=candidates,
        top_k=top_k,
    )


def test_deterministic_reranker_is_offline_stable_and_bounded() -> None:
    request = rerank_request(top_k=1)

    first = DeterministicReranker().rerank(request)
    second = DeterministicReranker().rerank(request)

    assert first == second
    assert first.candidates[0].chunk_id == request.candidates[1].chunk_id
    assert first.profile == request.profile


def test_voyage_adapter_disables_truncation_and_maps_provider_identity() -> None:
    request = rerank_request()

    def handler(incoming: httpx.Request) -> httpx.Response:
        payload = json.loads(incoming.content)
        assert payload["truncation"] is False
        assert payload["documents"] == [
            candidate.text for candidate in request.candidates
        ]
        return httpx.Response(
            200,
            json={
                "model": "rerank-2.5",
                "data": [
                    {"index": 1, "relevance_score": 0.93},
                    {"index": 0, "relevance_score": 0.72},
                ],
                "usage": {"total_tokens": 18},
            },
        )

    result = VoyageReranker(
        api_key=SecretStr("secret"),
        api_url="https://api.voyage.test/v1/rerank",
        timeout_seconds=1,
        max_attempts=1,
        initial_backoff_seconds=0,
        max_backoff_seconds=0,
        client=httpx.Client(transport=httpx.MockTransport(handler)),
    ).rerank(request)

    assert [item.chunk_id for item in result.candidates] == [
        request.candidates[1].chunk_id,
        request.candidates[0].chunk_id,
    ]
    assert result.provider_input_tokens == 18
    assert result.provider_retry_count == 0


def test_voyage_adapter_reranks_compare_sides_independently() -> None:
    shared_chunk_id = uuid4()
    primary = RerankCandidate(
        chunk_id=shared_chunk_id,
        document_id=uuid4(),
        document_family_id=uuid4(),
        version_position=1,
        side=RetrievalSide.PRIMARY,
        text="Current policy text.",
        fused_score=0.04,
        fused_rank=1,
    )
    comparison = primary.model_copy(
        update={
            "side": RetrievalSide.COMPARISON,
            "text": "Policy text valid at the comparison date.",
        }
    )
    request = rerank_request(top_k=1).model_copy(
        update={"candidates": (primary, comparison)}
    )
    calls: list[list[str]] = []
    clock = [0.0]
    sleeps: list[float] = []

    def sleep(seconds: float) -> None:
        sleeps.append(seconds)
        clock[0] += seconds

    def handler(incoming: httpx.Request) -> httpx.Response:
        payload = json.loads(incoming.content)
        calls.append(payload["documents"])
        return httpx.Response(
            200,
            json={
                "model": "rerank-2.5",
                "data": [{"index": 0, "relevance_score": 0.9}],
                "usage": {"total_tokens": 5},
            },
        )

    result = VoyageReranker(
        api_key=SecretStr("secret"),
        api_url="https://api.voyage.test/v1/rerank",
        timeout_seconds=1,
        max_attempts=1,
        initial_backoff_seconds=0,
        max_backoff_seconds=0,
        client=httpx.Client(transport=httpx.MockTransport(handler)),
        sleep=sleep,
        monotonic=lambda: clock[0],
        minimum_request_interval_seconds=25,
    ).rerank(request)

    assert calls == [
        ["Policy text valid at the comparison date."],
        ["Current policy text."],
    ]
    assert [(item.side, item.chunk_id, item.rank) for item in result.candidates] == [
        (RetrievalSide.COMPARISON, shared_chunk_id, 1),
        (RetrievalSide.PRIMARY, shared_chunk_id, 1),
    ]
    assert result.provider_input_tokens == 10
    assert result.provider_retry_count == 0
    assert sleeps == [25]


def test_voyage_adapter_rejects_malformed_or_non_finite_scores() -> None:
    request = rerank_request(top_k=1)
    client = httpx.Client(
        transport=httpx.MockTransport(
            lambda _: httpx.Response(
                200,
                content=b'{"model":"rerank-2.5","data":[{"index":0,"relevance_score":NaN}]}',
                headers={"Content-Type": "application/json"},
            )
        )
    )
    adapter = VoyageReranker(
        api_key=SecretStr("secret"),
        api_url="https://api.voyage.test/v1/rerank",
        timeout_seconds=1,
        max_attempts=1,
        initial_backoff_seconds=0,
        max_backoff_seconds=0,
        client=client,
    )

    with pytest.raises(MalformedRerankerResponseError):
        adapter.rerank(request)


def test_voyage_adapter_retries_only_transient_failures() -> None:
    calls = 0

    def handler(_: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        return httpx.Response(429, headers={"Retry-After": "0"})

    adapter = VoyageReranker(
        api_key=SecretStr("secret"),
        api_url="https://api.voyage.test/v1/rerank",
        timeout_seconds=1,
        max_attempts=2,
        initial_backoff_seconds=0,
        max_backoff_seconds=0,
        client=httpx.Client(transport=httpx.MockTransport(handler)),
        sleep=lambda _: None,
        jitter=lambda: 0,
    )

    with pytest.raises(RerankerRateLimitError) as raised:
        adapter.rerank(rerank_request())

    assert calls == 2
    assert raised.value.attempts == 2


def test_voyage_adapter_records_provider_retries_on_eventual_success() -> None:
    calls = 0

    def handler(_: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        if calls == 1:
            return httpx.Response(429, headers={"Retry-After": "0"})
        return httpx.Response(
            200,
            json={
                "model": "rerank-2.5",
                "data": [{"index": 0, "relevance_score": 0.9}],
                "usage": {"total_tokens": 7},
            },
        )

    result = VoyageReranker(
        api_key=SecretStr("secret"),
        api_url="https://api.voyage.test/v1/rerank",
        timeout_seconds=1,
        max_attempts=2,
        initial_backoff_seconds=0,
        max_backoff_seconds=0,
        client=httpx.Client(transport=httpx.MockTransport(handler)),
        sleep=lambda _: None,
        jitter=lambda: 0,
    ).rerank(rerank_request(top_k=1))

    assert calls == 2
    assert result.provider_retry_count == 1


def test_voyage_adapter_reports_disabled_truncation_overflow_as_typed_failure() -> None:
    adapter = VoyageReranker(
        api_key=SecretStr("secret"),
        api_url="https://api.voyage.test/v1/rerank",
        timeout_seconds=1,
        max_attempts=1,
        initial_backoff_seconds=0,
        max_backoff_seconds=0,
        client=httpx.Client(
            transport=httpx.MockTransport(
                lambda _: httpx.Response(
                    400,
                    json={"detail": "Input exceeds max tokens; truncation is disabled"},
                )
            )
        ),
    )

    with pytest.raises(RerankerInputTooLargeError):
        adapter.rerank(rerank_request())
