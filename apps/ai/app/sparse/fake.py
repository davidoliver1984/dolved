import hashlib
import math

from app.retrieval.deterministic_text import deterministic_token_counts
from app.sparse.models import (
    SparseEncodedVector,
    SparseEncodingRequest,
    SparseEncodingResult,
    SparseVector,
)


class DeterministicSparseEncoder:
    def encode(self, request: SparseEncodingRequest) -> SparseEncodingResult:
        encoded = []
        for item in request.items:
            weighted: dict[int, float] = {}
            for token, count in deterministic_token_counts(
                item.text, limit=request.profile.max_input_tokens
            ).items():
                index = int.from_bytes(
                    hashlib.sha256(token.encode()).digest()[:4], "big"
                )
                weighted[index] = weighted.get(index, 0.0) + 1.0 + math.log(count)
            indices = tuple(sorted(weighted))
            values = tuple(weighted[index] for index in indices)
            encoded.append(
                SparseEncodedVector(
                    source_id=item.source_id,
                    vector=SparseVector(indices=indices, values=values),
                )
            )
        return SparseEncodingResult(
            profile=request.profile,
            profile_fingerprint=request.profile.fingerprint(),
            purpose=request.purpose,
            encodings=tuple(encoded),
        )
