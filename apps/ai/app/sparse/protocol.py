from typing import Protocol

from app.sparse.models import SparseEncodingRequest, SparseEncodingResult


class SparseEncoder(Protocol):
    def encode(self, request: SparseEncodingRequest) -> SparseEncodingResult: ...
