"""Structural punctuation cases, deliberately not semantic correctness labels."""

import pytest

from app.generation.needs_output import GenerationNeedsResult

CASES = [
    (
        "comma_yes",
        "Yes, and the cited section also notes that staff may request a replacement badge.",
        True,
    ),
    ("dash_no", "No — the policy explicitly forbids sharing access codes.", True),
    (
        "period_yes_consistent",
        "Yes. This is confirmed by the cited passage, which states that staff may request a replacement badge.",
        False,
    ),
    (
        "period_no_consistent",
        "No. The policy explicitly forbids sharing access codes.",
        False,
    ),
    (
        "newline_consistent",
        "Yes.\nStaff may request a replacement badge under section 4.",
        False,
    ),
    ("bare_yes", "Yes.", False),
    ("bare_no", "No", False),
    ("contradictory_yes", "Yes. Staff are not permitted to share access codes.", False),
    (
        "contradictory_no",
        "No. Staff are permitted to request a replacement badge.",
        False,
    ),
    ("unrelated", "Yes. The fire drill is held quarterly.", False),
    ("lowercase_whitespace", "  yes. Staff may request a replacement badge.", False),
    ("exclamation_consistent", "Yes! Staff may request a replacement badge.", False),
    ("subject_first", "Staff may request a replacement badge under section 4.", True),
    (
        "comma_contradictory",
        "Yes, staff are not permitted to share access codes.",
        True,
    ),
    ("colon_consistent", "Yes: staff may request a replacement badge.", True),
    (
        "markdown_detached",
        "**Yes.** Staff are not permitted to share access codes.",
        False,
    ),
    ("no_space_detached", "Yes.Staff are not permitted to share access codes.", False),
]
CASES += [
    (
        "emphasis_joined",
        "**Yes**, the fictional policy allows replacement badges.",
        True,
    ),
    ("emphasis_detached", "**No**. Access codes must not be shared.", False),
    ("empty_comma", "Yes, ", False),
    ("empty_colon", "No:", False),
    ("empty_dash", "No — ", False),
    ("hyphen", "No - access codes must not be shared.", False),
    ("semicolon", "Yes; replacement badges are available.", False),
    ("question_mark", "Yes? Replacement badges are available.", False),
    ("ordinary_no_word", "Nobody may share access codes.", True),
]


@pytest.mark.parametrize("identity,text,accepted", CASES, ids=[c[0] for c in CASES])
def test_new_structural_polarity_matrix(identity, text, accepted):
    payload = {
        "contract_version": 2,
        "outcome": "answered",
        "answer_parts": [{"text": text, "evidence_ids": ["ev-01"]}],
        "unsupported_aspects": [],
        "insufficiency_reason": None,
        "usage": None,
        "aspect_links": [
            {
                "need_id": "n1",
                "answer_part_indices": [0],
                "unsupported_aspect_indices": [],
            }
        ],
    }
    if accepted:
        GenerationNeedsResult.model_validate(payload)
    else:
        with pytest.raises(ValueError, match="detached yes/no"):
            GenerationNeedsResult.model_validate(payload)
