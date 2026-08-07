"""Deterministic EvidenceUnit matching independent of generated identifiers."""

from __future__ import annotations

import re
import unicodedata
from collections import Counter

from app.evaluation.models import EvidenceUnit, RetrievedCandidate

MATCHING_ALGORITHM = "normalised-token-coverage-v1"


def normalise_text(value: str) -> str:
    normalised = unicodedata.normalize("NFKC", value).casefold()
    return " ".join(re.findall(r"[\w]+", normalised, flags=re.UNICODE))


def token_coverage(excerpt: str, candidate_text: str) -> float:
    expected = Counter(normalise_text(excerpt).split())
    if not expected:
        return 0.0
    actual = Counter(normalise_text(candidate_text).split())
    matched = sum(min(count, actual[token]) for token, count in expected.items())
    return matched / sum(expected.values())


def candidate_covers(unit: EvidenceUnit, candidate: RetrievedCandidate) -> bool:
    if (
        candidate.document_version_id != unit.document_version_id
        or candidate.side != unit.side
    ):
        return False
    return all(
        token_coverage(excerpt, candidate.text) >= unit.minimum_token_coverage
        for excerpt in unit.canonical_excerpts
    )


def candidates_cover(
    unit: EvidenceUnit, candidates: tuple[RetrievedCandidate, ...]
) -> bool:
    matching = [
        candidate.text
        for candidate in candidates
        if candidate.document_version_id == unit.document_version_id
        and candidate.side == unit.side
    ]
    if not matching:
        return False
    combined = " ".join(matching)
    return all(
        token_coverage(excerpt, combined) >= unit.minimum_token_coverage
        for excerpt in unit.canonical_excerpts
    )
