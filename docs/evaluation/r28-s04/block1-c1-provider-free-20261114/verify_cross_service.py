"""Validate captured production PHP assembly/persistence using the Python contract.

No clients, credentials or providers. Run in the AI container with /workspace mounted.
"""
import json
from pathlib import Path

from app.generation.needs_request import GenerationNeedsRequest
from app.generation.needs_output import GenerationTemporalResult

root = Path(__file__).resolve().parent
report = []
for name in ('varied_development', 'modified_input16'):
    data = json.loads((root / f'{name}.json').read_text())
    request = GenerationNeedsRequest.model_validate(data['generation_request'])
    uses = [{key: row[key] for key in ('part_index', 'need_id', 'target_id', 'question_quote')}
            for row in data['temporal_context']['uses']]
    result = GenerationTemporalResult.model_validate({
        'contract_version': 4, 'outcome': 'answered', 'answer_parts': data['persisted_answer_parts'],
        'unsupported_aspects': [], 'insufficiency_reason': None, 'usage': None,
        'aspect_links': data['aspect_links'], 'temporal_uses': uses,
        'entailment_checks': [{'part_index': i, 'verdict': 'supported', 'missing_condition': None}
                              for i in range(len(data['persisted_answer_parts']))],
    }).validate_for_needs_request(request)
    report.append({'fixture': name, 'request_and_persisted_use_valid': True,
                   'parts': len(result.answer_parts), 'provider_calls': 0,
                   'entailment_verdict_source': 'controlled test boundary, not a semantic measurement'})
print(json.dumps(report, indent=2))
