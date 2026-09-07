"""A punctuation requirement, not semantic agreement or intent detection."""

import re


def has_invalid_leading_polarity(text: str) -> bool:
    # Recognise plain or simply emphasised leading tokens. Do not rewrite prose.
    match = re.match(
        r"^\s*(?:\*\*|__|\*|_)?(yes|no)\b(?:\*\*|__|\*|_)?", text, re.IGNORECASE
    )
    if match is None:
        return False
    remainder = text[match.end() :].lstrip()
    return not (remainder[:1] in {",", ":", "—"} and bool(remainder[1:].strip()))
