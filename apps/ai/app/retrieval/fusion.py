from collections.abc import Sequence
from dataclasses import dataclass
from typing import Protocol

from app.retrieval.models import RetrievalCandidate


class FusionStrategy(Protocol):
    def fuse(
        self,
        dense: Sequence[RetrievalCandidate],
        sparse: Sequence[RetrievalCandidate],
        *,
        limit: int,
    ) -> tuple[RetrievalCandidate, ...]: ...


@dataclass(frozen=True)
class ReciprocalRankFusion:
    rrf_k: int

    def __post_init__(self) -> None:
        if self.rrf_k < 1:
            raise ValueError("rrf_k must be positive")

    def fuse(
        self,
        dense: Sequence[RetrievalCandidate],
        sparse: Sequence[RetrievalCandidate],
        *,
        limit: int,
    ) -> tuple[RetrievalCandidate, ...]:
        if limit < 1:
            raise ValueError("fusion limit must be positive")
        by_chunk: dict[str, dict[str, RetrievalCandidate]] = {}
        for method, candidates in (("dense", dense), ("sparse", sparse)):
            seen: set[str] = set()
            for candidate in candidates:
                identity = str(candidate.chunk_id)
                if identity in seen:
                    raise ValueError(f"duplicate candidate in {method} ranking")
                seen.add(identity)
                by_chunk.setdefault(identity, {})[method] = candidate

        scored: list[tuple[float, int, str, RetrievalCandidate]] = []
        for identity, sources in by_chunk.items():
            dense_candidate = sources.get("dense")
            sparse_candidate = sources.get("sparse")
            representative = dense_candidate or sparse_candidate
            assert representative is not None
            source_ranks = tuple(
                candidate.rank
                for candidate in (dense_candidate, sparse_candidate)
                if candidate is not None
            )
            fused_score = sum(1.0 / (self.rrf_k + rank) for rank in source_ranks)
            scored.append((fused_score, min(source_ranks), identity, representative))

        scored.sort(key=lambda value: (-value[0], value[1], value[2]))
        return tuple(
            candidate.model_copy(
                update={
                    "score": fused_score,
                    "rank": rank,
                    "retrieval_method": "hybrid",
                    "dense_score": (
                        by_chunk[identity]["dense"].score
                        if "dense" in by_chunk[identity]
                        else None
                    ),
                    "dense_rank": (
                        by_chunk[identity]["dense"].rank
                        if "dense" in by_chunk[identity]
                        else None
                    ),
                    "sparse_score": (
                        by_chunk[identity]["sparse"].score
                        if "sparse" in by_chunk[identity]
                        else None
                    ),
                    "sparse_rank": (
                        by_chunk[identity]["sparse"].rank
                        if "sparse" in by_chunk[identity]
                        else None
                    ),
                }
            )
            for rank, (fused_score, _, identity, candidate) in enumerate(
                scored[:limit], start=1
            )
        )
