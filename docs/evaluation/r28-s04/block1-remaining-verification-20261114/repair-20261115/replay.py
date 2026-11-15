"""Retained item24 text; labelled synthetic links and controlled reviewer verdicts."""
import hashlib
import json
from pathlib import Path
from types import SimpleNamespace

from app.generation.errors import GenerationProviderFailure
from app.generation.needs_output import GenerationNeedsResult, OpenAIGenerationNeedsOutput
from app.generation.needs_request import GenerationNeedsRequest
from app.generation.support_review import SourceSupportReview
from tests.test_generation_needs_transport import prepared
from tests.test_openai_generation_adapter import FakeResponses, response

HERE = Path(__file__).resolve().parent
source = HERE.parent / "run/cases/24.json"
raw = json.loads(source.read_text())
request = GenerationNeedsRequest.model_validate(raw["generation_request"])
parts = [{"text": part["text"], "evidence_ids": [e["evidence_handle"] for e in part["evidence"]]}
         for part in raw["parts"]]
# Links were not retained in this record. Do not claim to reconstruct the model's
# original envelope: these explicit synthetic links respect the retained owners.
links = [{"need_id": need.need_id, "answer_part_indices": [i], "unsupported_aspect_indices": []}
         for i, need in enumerate(request.evidence_needs)]
output = OpenAIGenerationNeedsOutput.model_validate({"outcome": "answered", "answer_parts": parts,
    "unsupported_aspects": [], "insufficiency_reason": None, "aspect_links": links})
legacy = GenerationNeedsResult.model_validate({**output.model_dump(), "contract_version": 3,
    "usage": None, "entailment_checks": raw["entailment_checks"]}).validate_for_needs_request(request)
results = []
for verdict in ("different", "established"):
    review_parts = []
    for i, part in enumerate(parts):
        handle = part["evidence_ids"][0]
        text = next(e.text for e in request.evidence if e.evidence_id == handle)
        review_parts.append({"part_index": i, "verdict": "supported", "missing_condition": None,
            "claims": [{"need_id": links[i]["need_id"], "answer_quote": part["text"],
                "evidence_id": handle, "source_quote": text,
                "same_situation": verdict if i == 0 else "established",
                "requirement_preserved": "changed" if i == 0 and verdict == "different" else "established"}]})
    responses = FakeResponses([response(output), response(SourceSupportReview.model_validate({"parts": review_parts}))])
    _, adapter, _ = prepared()
    adapter._client = SimpleNamespace(responses=responses)
    try:
        result = adapter.generate_needs(request)
        outcome = {"accepted": True, "code": None, "outcome": result.outcome.value}
    except GenerationProviderFailure as failure:
        outcome = {"accepted": False, "code": failure.error.validation.code}
    results.append({"controlled_same_situation_verdict": verdict, **outcome,
                    "provider_boundary_calls": len(responses.calls)})
report = {"source": str(source.relative_to(Path('/workspace'))),
    "source_sha256": hashlib.sha256(source.read_bytes()).hexdigest(),
    "synthetic_fields": ["outcome=answered", "aspect_links", "review claim receipts and semantic verdicts"],
    "original_missing_output_reconstructed": False, "live_calls": 0,
    "legacy_retained_supported_checks_accepted": legacy.outcome.value,
    "new_review_replays": results,
    "limitation": "Controlled rejection proves enforcement, not that a real reviewer will judge this claim correctly. A false supported judgement still passes."}
(HERE / 'item24-replay.json').write_text(json.dumps(report, indent=2) + '\n')
print(json.dumps(report, indent=2))
