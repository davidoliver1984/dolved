"""Explicitly generated fixture; ordinary tests compare, never overwrite it."""

import json
import os
from pathlib import Path

from app.generation.needs_request import GenerationNeedsRequest
from tests.test_generation_needs_transport import prepared


def test_real_adapter_source_support_projection_matches_php_fixture():
    raw, adapter, responses = prepared()
    result = adapter.generate_needs(GenerationNeedsRequest.model_validate(raw))
    assert len(responses.calls) == 2
    assert (
        result.usage["entailment_review"]["support_contract"]
        == "need-source-support-v2"
    )
    payload = result.model_dump(mode="json")
    # Omit nondeterministic observation timing, not semantic contract fields.
    payload["usage"] = None
    document = {
        "profile": adapter.profile.fingerprint_input(),
        "profile_fingerprint": adapter.profile.fingerprint(),
        "request": raw,
        "result": payload,
    }
    path = Path("/workspace/contracts/testing/generation-source-support-v1.json")
    if os.environ.get("WRITE_SUPPORT_FIXTURE") == "1":
        path.write_text(json.dumps(document, indent=2) + "\n")
    assert json.loads(path.read_text()) == document
