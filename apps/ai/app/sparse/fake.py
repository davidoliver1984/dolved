import hashlib

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
            digest = hashlib.sha256(item.text.encode()).digest()
            indices = tuple(sorted({int(value) for value in digest[:8]}))
            values = tuple((index + 1) / 256 for index in indices)
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
