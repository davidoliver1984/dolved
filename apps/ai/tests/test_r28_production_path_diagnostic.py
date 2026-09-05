import json
from pathlib import Path

if Path("/evaluation").is_dir():
    EVALUATION = Path("/evaluation")
else:
    EVALUATION = Path(__file__).resolve().parents[3] / "tests/evaluation"
DIAGNOSTIC = EVALUATION / "diagnostics/r28-production-path-12/v1"
DIRECTIONAL = EVALUATION / "diagnostics/r28-production-path-8/v1"
POPULATION = EVALUATION / "engineering-populations/dolved-care-v4/v2/population.json"


def load(path: Path) -> dict:
    return json.loads(path.read_text())


def test_execution_input_is_question_only_and_byte_identical_to_frozen_population() -> (
    None
):
    execution = load(DIAGNOSTIC / "execution-input.json")
    population = load(POPULATION)
    cases = {case["case_id"]: case for case in population["cases"]}

    assert execution["population_id"] == "dolved-care-v4-evaluation-population-v2"
    assert population["population_id"] == "dolved-v4-independent-comparison-compat-v2"
    assert len(execution["items"]) == 12
    assert (
        len({(item["case_id"], item["variant_id"]) for item in execution["items"]})
        == 12
    )
    for item in execution["items"]:
        assert set(item) == {"case_id", "variant_id", "utterance"}
        variant = next(
            variant
            for variant in cases[item["case_id"]]["variants"]
            if variant["variant_id"] == item["variant_id"]
        )
        assert item["utterance"] == variant["utterance"]
    serialised = json.dumps(execution).lower()
    for forbidden in (
        "expected_outcome",
        "expected_evidence",
        "reference_answer",
        "relevance",
        "judgement",
    ):
        assert forbidden not in serialised


def test_selection_is_exact_and_proposed_ceilings_reconcile() -> None:
    execution = load(DIAGNOSTIC / "execution-input.json")
    policy = load(DIAGNOSTIC / "selection-and-ceilings.json")
    selected = {
        identity
        for identities in policy["selection"].values()
        for identity in identities
    }
    actual = {f"{item['case_id']}::{item['variant_id']}" for item in execution["items"]}
    assert selected == actual
    assert {key: len(value) for key, value in policy["selection"].items()} == {
        "ordinary_current_policy": 2,
        "inherited_or_local_applicability": 2,
        "valid_at_date_or_historical": 2,
        "comparison": 2,
        "legitimate_clarification": 1,
        "legitimate_no_answer": 1,
        "prompt_injection": 2,
    }
    graph = policy["worst_case_logical_request_graph"]
    ceilings = policy["proposed_authorisation_ceilings"]
    assert sum(graph.values()) == ceilings["logical_provider_requests"] == 82
    assert ceilings["maximum_physical_attempts"] == 164
    tokens = policy["stage_token_reservations"]
    assert (
        sum(value for key, value in tokens.items() if key.endswith("_input"))
        == 2_740_000
    )
    assert (
        sum(value for key, value in tokens.items() if key.endswith("_output"))
        == 216_000
    )
    assert ceilings["cost_usd"] == "2.00000000"
    assert ceilings["wall_seconds"] == 3600
    assert ceilings["concurrency"] == 1
    assert ceilings["maximum_retries_per_logical_request"] == 1


def test_directional_execution_input_is_question_only_and_preserves_frozen_selection() -> (
    None
):
    execution = load(DIRECTIONAL / "execution-input.json")
    selection = load(DIRECTIONAL / "selection-and-ceilings.json")
    original_execution = load(DIAGNOSTIC / "execution-input.json")
    original_selection = load(DIAGNOSTIC / "selection-and-ceilings.json")
    original_questions = {
        (item["case_id"], item["variant_id"]): item["utterance"]
        for item in original_execution["items"]
    }

    assert len(execution["items"]) == 8
    identities = {
        f"{item['case_id']}::{item['variant_id']}" for item in execution["items"]
    }
    assert identities == {
        "v4.case.corrected-b02-06::v1",
        "v4.case.corrected-b01-08::v1",
        "v4.case.corrected-b02-03::v1",
        "v4.case.corrected-b01-02::v1",
        "v4.case.corrected-b02-10::v1",
        "v4.case.corrected-b01-03::v1",
        "v4.case.corrected-b01-06::v1",
        "v4.case.corrected-b03-08::v1",
    }
    for item in execution["items"]:
        assert set(item) == {"case_id", "variant_id", "utterance"}
        assert (
            item["utterance"]
            == original_questions[(item["case_id"], item["variant_id"])]
        )
    assert (
        selection["selection"]["prompt_injection"]
        == original_selection["selection"]["prompt_injection"]
    )
    assert set(selection["execution_scope"]["injection"]) == set(
        original_selection["selection"]["prompt_injection"]
    )
    assert (
        set(selection["execution_scope"]["primary"])
        | set(selection["execution_scope"]["injection"])
        == identities
    )
    serialised = json.dumps(execution).lower()
    for forbidden in (
        "expected_outcome",
        "expected_evidence",
        "reference_answer",
        "relevance",
        "judgement",
    ):
        assert forbidden not in serialised

    limits = selection["authorised_limits"]
    assert limits == {
        "cost_usd": "0.50000000",
        "wall_seconds": 2700,
        "concurrency": 1,
        "automatic_complete_subset_reruns": 0,
        "provider_retries": "existing-production-native",
    }
