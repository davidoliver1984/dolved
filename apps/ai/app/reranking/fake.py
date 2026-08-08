from app.reranking.models import RerankedCandidate, RerankRequest, RerankResult


class DeterministicReranker:
    def rerank(self, request: RerankRequest) -> RerankResult:
        ordered = tuple(
            candidate
            for side in sorted(
                {candidate.side for candidate in request.candidates},
                key=lambda item: item.value,
            )
            for candidate in sorted(
                (
                    candidate
                    for candidate in request.candidates
                    if candidate.side is side
                ),
                key=lambda candidate: (
                    -candidate.fused_score,
                    str(candidate.chunk_id),
                ),
            )[: request.top_k]
        )
        return RerankResult(
            request_id=request.request_id,
            profile=request.profile,
            candidates=tuple(
                RerankedCandidate(
                    chunk_id=candidate.chunk_id,
                    side=candidate.side,
                    score=max(0.0, min(1.0, 1.0 - ((rank - 1) * 0.01))),
                    rank=(
                        sum(
                            previous.side is candidate.side
                            for previous in ordered[: rank - 1]
                        )
                        + 1
                    ),
                )
                for rank, candidate in enumerate(ordered, start=1)
            ),
            provider_input_tokens=None,
        )
