import hashlib
from importlib.metadata import version
from typing import Protocol

import tiktoken
from tiktoken import Encoding

from app.chunking.errors import UnrepresentableContentError
from app.chunking.models import TokenizerIdentity

TIKTOKEN_LIBRARY = "tiktoken"
TIKTOKEN_VERSION = "0.13.0"
TIKTOKEN_ENCODING = "o200k_base"


class Tokenizer(Protocol):
    @property
    def identity(self) -> TokenizerIdentity: ...

    def count(self, text: str) -> int: ...

    def largest_prefix_end(self, text: str, max_tokens: int) -> int: ...

    def smallest_suffix_start(self, text: str, max_tokens: int) -> int: ...


class TiktokenTokenizer:
    def __init__(self) -> None:
        installed_version = version(TIKTOKEN_LIBRARY)
        if installed_version != TIKTOKEN_VERSION:
            raise RuntimeError(
                f"Expected {TIKTOKEN_LIBRARY} {TIKTOKEN_VERSION}, "
                f"found {installed_version}"
            )
        self._encoding: Encoding = tiktoken.get_encoding(TIKTOKEN_ENCODING)
        self._identity = TokenizerIdentity(
            library=TIKTOKEN_LIBRARY,
            library_version=installed_version,
            encoding=TIKTOKEN_ENCODING,
            vocabulary_fingerprint=self._vocabulary_fingerprint(self._encoding),
        )

    @property
    def identity(self) -> TokenizerIdentity:
        return self._identity

    def count(self, text: str) -> int:
        return len(self._encoding.encode(text))

    def largest_prefix_end(self, text: str, max_tokens: int) -> int:
        if not text or max_tokens <= 0:
            return 0

        boundaries = self._character_boundaries(text)
        for boundary in reversed(boundaries):
            if self.count(text[:boundary]) <= max_tokens:
                return boundary

        raise UnrepresentableContentError(
            "A single character exceeds the configured token limit."
        )

    def smallest_suffix_start(self, text: str, max_tokens: int) -> int:
        if not text or max_tokens <= 0:
            return len(text)

        boundaries = (0, *self._character_boundaries(text))
        for boundary in boundaries:
            if self.count(text[boundary:]) <= max_tokens:
                return boundary
        return len(text)

    def _character_boundaries(self, text: str) -> tuple[int, ...]:
        tokens = self._encoding.encode(text)
        decoded, offsets = self._encoding.decode_with_offsets(tokens)
        if decoded != text:
            raise UnrepresentableContentError(
                "Tokenizer could not round-trip normalised content."
            )
        return tuple(sorted({*offsets[1:], len(text)}))

    @staticmethod
    def _vocabulary_fingerprint(encoding: Encoding) -> str:
        digest = hashlib.sha256()
        # tiktoken does not expose a public vocabulary revision. Hashing the
        # loaded ranks records the exact vocabulary used by this pinned adapter.
        for token, rank in sorted(
            encoding._mergeable_ranks.items(), key=lambda item: item[1]
        ):
            digest.update(len(token).to_bytes(4, "big"))
            digest.update(token)
            digest.update(rank.to_bytes(4, "big"))
        return digest.hexdigest()
