import re
from collections import Counter
from uuid import UUID

_TOKEN = re.compile(r"[a-z0-9]+")
_STOP_WORDS = frozenset(
    {
        "a",
        "an",
        "and",
        "are",
        "at",
        "be",
        "by",
        "did",
        "do",
        "does",
        "for",
        "from",
        "how",
        "in",
        "is",
        "it",
        "of",
        "on",
        "or",
        "that",
        "the",
        "this",
        "to",
        "was",
        "what",
        "when",
        "where",
        "which",
        "with",
    }
)


def deterministic_tokens(text: str, *, limit: int | None = None) -> tuple[str, ...]:
    tokens = tuple(
        token for token in _TOKEN.findall(text.casefold()) if token not in _STOP_WORDS
    )
    if limit is not None:
        return tokens[:limit]
    return tokens


def deterministic_token_counts(text: str, *, limit: int | None = None) -> Counter[str]:
    tokens = deterministic_tokens(text, limit=limit)
    if not tokens:
        tokens = (text.casefold().strip(),)
    return Counter(tokens)


def deterministic_identity_tie_break(source_id: UUID) -> float:
    """Return a stable non-zero scalar used only to order equal lexical scores."""

    return 1.0 + (source_id.int / ((1 << 128) - 1))
