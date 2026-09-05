from app.reranking.models import RerankedCandidate, RerankRequest, RerankResult
from app.retrieval.deterministic_text import deterministic_tokens


class DeterministicReranker:
    def rerank(self, request: RerankRequest) -> RerankResult:
        query_tokens = set(deterministic_tokens(request.query))

        def relevance(candidate) -> float:  # type: ignore[no-untyped-def]
            candidate_tokens = set(
                deterministic_tokens(candidate.provider_representation())
            )
            if not query_tokens:
                return 0.0
            return len(query_tokens & candidate_tokens) / len(query_tokens)

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
                    -relevance(candidate),
                    candidate.fused_rank,
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
