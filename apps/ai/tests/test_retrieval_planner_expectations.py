import hashlib
import json
from collections import Counter
from pathlib import Path

PATH = Path("/evaluation/planner-expectations/v2/engineering-expectations.json")


def test_adr_0022_engineering_truth_is_versioned_complete_and_deterministic() -> None:
    payload = json.loads(PATH.read_text())
    expectations = payload["expectations"]
    identities = {(item["case_id"], item["variant_id"]) for item in expectations}
    modes = Counter(item["contract"]["temporal_mode"] for item in expectations)
    references = Counter(
        item["contract"]["temporal_reference"]["kind"]
        for item in expectations
        if item["contract"]["temporal_reference"] is not None
    )
    digest_payload = dict(payload)
    expected_digest = digest_payload.pop("expectations_digest")
    encoded = json.dumps(
        digest_payload, ensure_ascii=False, sort_keys=True, separators=(",", ":")
    ).encode()

    assert payload["schema_version"] == "v2"
    assert payload["scope"] == "engineering_tuning"
    assert payload["protected_splits_accessed"] is False
    assert len(expectations) == len(identities) == 126
    assert modes == {
        "current": 88,
        "compare": 22,
        "valid_at_date": 9,
        "historical_reference": 7,
    }
    assert references == {"calendar_period": 7, "historical_reference": 7}
    assert payload["reconciled_temporal_reference_count"] == 14
    assert hashlib.sha256(encoded).hexdigest() == expected_digest
