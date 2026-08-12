import hashlib
import json
from pathlib import Path
from typing import Any

SNAPSHOT = Path(
    "/evaluation/engineering-snapshots/dolved-care-engineering/v2/corpus.json"
)
PLANNER_TRUTH = Path(
    "/evaluation/planner-expectations/v2/engineering-expectations.json"
)


def canonical_digest(value: Any) -> str:
    encoded = json.dumps(
        value, ensure_ascii=False, sort_keys=True, separators=(",", ":")
    ).encode()
    return hashlib.sha256(encoded).hexdigest()


def test_snapshot_is_bound_to_v2_and_contains_exactly_the_engineering_population() -> (
    None
):
    snapshot = json.loads(SNAPSHOT.read_text())
    planner_truth = json.loads(PLANNER_TRUTH.read_text())
    cases = snapshot["cases"]
    case_ids = [case["case_id"] for case in cases]
    snapshot_pairs = {
        (case["case_id"], variant["variant_id"])
        for case in cases
        for variant in case["variants"]
    }
    planner_pairs = {
        (value["case_id"], value["variant_id"])
        for value in planner_truth["expectations"]
    }

    assert snapshot["benchmark"] == {
        "digest": "aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d",
        "evaluation_clock": "2026-08-01T12:00:00+00:00",
        "id": "dolved-care-engineering",
        "version": "v2",
    }
    assert snapshot["case_count"] == len(cases) == len(set(case_ids)) == 42
    assert snapshot["variant_count"] == len(snapshot_pairs) == 126
    assert snapshot_pairs == planner_pairs
    assert snapshot["split"]["case_ids"] == case_ids
    assert snapshot["split"]["case_ids_digest"] == canonical_digest(case_ids)


def test_snapshot_digest_is_deterministic_and_protected_split_content_is_absent() -> (
    None
):
    snapshot = json.loads(SNAPSHOT.read_text())
    recorded = snapshot.pop("snapshot_digest")
    assert recorded == canonical_digest(snapshot)
    assert set(snapshot["split"]) == {
        "name",
        "version",
        "case_ids",
        "case_ids_digest",
    }
    assert snapshot["split"]["name"] == "engineering_tuning"
    serialised = json.dumps(snapshot, ensure_ascii=False, sort_keys=True).lower()
    assert '"threshold_calibration"' not in serialised
    assert '"held_out_acceptance"' not in serialised
    assert '"assignments"' not in serialised


def test_snapshot_compiler_has_no_full_corpus_or_split_input() -> None:
    compiler = Path(
        "/workspace/scripts/evaluation/compile_engineering_case_snapshot.py"
    ).read_text()
    assert "compiled/corpus.json" not in compiler
    assert "splits/v1.json" not in compiler
    assert "--source-observations" in compiler
