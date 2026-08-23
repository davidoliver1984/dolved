import hashlib
import math

from app.retrieval.deterministic_text import (
    deterministic_identity_tie_break,
    deterministic_token_counts,
)
from app.sparse.models import (
    SparseEncodedVector,
    SparseEncodingPurpose,
    SparseEncodingRequest,
    SparseEncodingResult,
    SparseVector,
)

_TIE_BREAK_INDEX = (1 << 32) - 1
_TIE_BREAK_WEIGHT = 0.1


class DeterministicSparseEncoder:
    def encode(self, request: SparseEncodingRequest) -> SparseEncodingResult:
        encoded = []
        for item in request.items:
            weighted: dict[int, float] = {}
            for token, count in deterministic_token_counts(
                item.text, limit=request.profile.max_input_tokens
            ).items():
                index = (
                    int.from_bytes(hashlib.sha256(token.encode()).digest()[:4], "big")
                    % _TIE_BREAK_INDEX
                )
                weighted[index] = weighted.get(index, 0.0) + 1.0 + math.log(count)
            weighted[_TIE_BREAK_INDEX] = _TIE_BREAK_WEIGHT * (
                deterministic_identity_tie_break(item.source_id)
                if request.purpose is SparseEncodingPurpose.DOCUMENT
                else 1.0
            )
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
