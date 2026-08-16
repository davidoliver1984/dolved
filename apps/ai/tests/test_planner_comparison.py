from app.evaluation.planner_comparison import compare_planner_contract


def expected(**overrides):
    return {
        "contract_version": 2,
        "temporal_mode": "current",
        "explicit_date": None,
        "temporal_reference": None,
        "location_references": [],
        "clarification_reason": None,
        **overrides,
    }


def test_known_good_adr_0022_plan_is_correct() -> None:
    comparison = compare_planner_contract(
        expected(
            temporal_mode="compare",
            temporal_reference={
                "kind": "historical_reference",
                "value": "version 1",
            },
            location_references=["Coventry House"],
        ),
        {
            "query": "What changed at Coventry House?",
            "temporal_mode": "compare",
            "explicit_date": None,
            "temporal_reference": {
                "kind": "historical_reference",
                "value": "version 1",
            },
            "location_references": ["Coventry House"],
            "clarification_reason": None,
        },
        "What changed at Coventry House?",
    )

    assert comparison.correct is True
    assert comparison.differences == ()


def test_semantic_differences_are_reported_field_by_field() -> None:
    comparison = compare_planner_contract(
        expected(),
        {
            "query": "Current procedure?",
            "temporal_mode": "clarification_required",
            "explicit_date": None,
            "temporal_reference": None,
            "location_references": ["Alderbridge"],
            "clarification_reason": "unclassifiable_temporal_intent",
        },
        "Current procedure?",
    )

    assert comparison.correct is False
    assert [value["field"] for value in comparison.differences] == [
        "temporal_mode",
        "location_references",
        "clarification_reason",
    ]
    assert comparison.differences[1]["classification"] == (
        "SEMANTIC_AFTER_NORMALISATION"
    )


def test_non_null_location_text_difference_is_flagged_for_alias_review() -> None:
    comparison = compare_planner_contract(
        expected(location_references=["Bristol home"]),
        {
            "query": "What applies at Harbour View?",
            "temporal_mode": "current",
            "explicit_date": None,
            "temporal_reference": None,
            "location_references": ["Harbour View"],
            "clarification_reason": None,
        },
        "What applies at Harbour View?",
    )

    assert comparison.differences[0]["classification"] == (
        "POTENTIAL_ALIAS_OR_REPRESENTATION_MISMATCH"
    )


def test_equivalent_resolved_location_identity_accepts_alias_representation() -> None:
    comparison = compare_planner_contract(
        expected(location_references=["the Bristol home"]),
        {
            "query": "What applies at the Bristol home?",
            "temporal_mode": "current",
            "explicit_date": None,
            "temporal_reference": None,
            "location_references": ["Bristol home"],
            "clarification_reason": None,
        },
        "What applies at the Bristol home?",
        expected_location_identity="location-public-id-harbour-view",
        actual_location_identity="location-public-id-harbour-view",
    )

    assert comparison.correct is True
    assert comparison.differences == ()


def test_different_resolved_location_identities_remain_mismatches() -> None:
    comparison = compare_planner_contract(
        expected(location_references=["Bristol home"]),
        {
            "query": "What applies at the Exeter home?",
            "temporal_mode": "current",
            "explicit_date": None,
            "temporal_reference": None,
            "location_references": ["Exeter home"],
            "clarification_reason": None,
        },
        "What applies at the Exeter home?",
        expected_location_identity="location-public-id-harbour-view",
        actual_location_identity="location-public-id-meadow-court",
    )

    assert comparison.correct is False
    assert comparison.differences[0]["field"] == "location_references"


def plan_with_reference(reference):
    return {
        "query": "Historical rule?",
        "temporal_mode": "historical_reference",
        "explicit_date": None,
        "temporal_reference": reference,
        "location_references": [],
        "clarification_reason": None,
    }


def test_equivalent_historical_ordinal_references_are_accepted() -> None:
    comparison = compare_planner_contract(
        expected(
            temporal_mode="historical_reference",
            temporal_reference={
                "kind": "historical_reference",
                "value": "version 1",
            },
        ),
        plan_with_reference(
            {
                "kind": "historical_reference",
                "value": "version 1 of the controlled-drugs procedure",
            }
        ),
        "Historical rule?",
    )

    assert comparison.correct is True
    assert comparison.differences == ()


def test_different_historical_ordinals_are_not_equivalent() -> None:
    comparison = compare_planner_contract(
        expected(
            temporal_mode="historical_reference",
            temporal_reference={
                "kind": "historical_reference",
                "value": "version 1",
            },
        ),
        plan_with_reference({"kind": "historical_reference", "value": "version 2"}),
        "Historical rule?",
    )

    assert comparison.correct is False


def test_omitted_historical_selector_is_not_equivalent() -> None:
    comparison = compare_planner_contract(
        expected(
            temporal_mode="compare",
            temporal_reference={
                "kind": "historical_reference",
                "value": "version 1",
            },
        ),
        {
            **plan_with_reference(None),
            "temporal_mode": "compare",
        },
        "Historical rule?",
    )

    assert comparison.correct is False
    assert comparison.differences[0]["field"] == "temporal_reference"


def test_different_reference_kinds_are_not_equivalent() -> None:
    comparison = compare_planner_contract(
        expected(
            temporal_mode="compare",
            temporal_reference={
                "kind": "historical_reference",
                "value": "2024 procedure",
            },
        ),
        {
            **plan_with_reference({"kind": "calendar_period", "value": "2024"}),
            "temporal_mode": "compare",
        },
        "Historical rule?",
    )

    assert comparison.correct is False


def test_ambiguous_or_unsupported_historical_references_fail_closed() -> None:
    for actual_value in ("version 1 or version 2", "the archived procedure"):
        comparison = compare_planner_contract(
            expected(
                temporal_mode="historical_reference",
                temporal_reference={
                    "kind": "historical_reference",
                    "value": "version 1",
                },
            ),
            plan_with_reference(
                {"kind": "historical_reference", "value": actual_value}
            ),
            "Historical rule?",
        )

        assert comparison.correct is False


def test_unversioned_expectation_is_rejected() -> None:
    try:
        compare_planner_contract({}, {}, "Question")
    except ValueError as exception:
        assert "versioned" in str(exception)
    else:
        raise AssertionError("unversioned planner truth was accepted")
