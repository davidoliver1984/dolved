"""Provider-free captured-field replay; never claims reconstruction of omitted fields."""
import hashlib
import json
from copy import deepcopy
from pathlib import Path

from app.retrieval.needs import UserTurn
from app.retrieval.needs_planner import NeedsValidationFailure, _canonicalize_leading_relations
from tests.test_needs_correction_feedback import run_outputs

ROOT = Path('/workspace')
HERE = Path(__file__).resolve().parent
OLD = HERE.parent / 'block1-temporal-association-diagnostic-20261114'
analysis = json.loads((OLD / 'diagnostic-analysis.json').read_text())
rows = []
for attempt in analysis['attempts']:
    if attempt['status'] != 'rejected':
        continue
    source = OLD / attempt['file']
    record = json.loads(source.read_text())
    item = attempt['item']
    question = json.loads((OLD / 'inputs' / f'{item}.json').read_text())['utterance']
    turns = (UserTurn(turn_id='user-1', text=question),)
    plan = deepcopy(record['proposed'])
    synthetic = []
    if 'clarification' not in plan:
        plan['clarification'] = None
        synthetic.append('clarification=null')
    for need in plan['needs']:
        if 'aspect' not in need:
            need['aspect'] = need['question_span']['quote']
            synthetic.append(f"{need['need_id']}.aspect=question_span.quote (diagnostic only)")
        if 'supports_need_id' not in need:
            need['supports_need_id'] = None
            synthetic.append(f"{need['need_id']}.supports_need_id=null")
    for binding in plan['bindings']:
        if 'ownership' not in binding:
            binding['ownership'] = 'authorised_workspace'
            synthetic.append(f"{binding['need_id']}.binding.ownership=authorised_workspace")
        if 'ownership_source' not in binding:
            binding['ownership_source'] = None
            synthetic.append(f"{binding['need_id']}.binding.ownership_source=null")
    canonical, count = _canonicalize_leading_relations(plan, turns)
    planner, calls, _ = run_outputs([plan], turns)
    # _plan_needs_once is the production canonicalisation/schema/grounding/catalogue
    # boundary, before interpretation. Mock transport supplies retained projections;
    # no model/checker invoked and no retry implied by this per-attempt replay.
    try:
        result = planner._plan_needs_once(turns, evaluated_at='2026-11-14T19:00:00Z')
        outcome = {'status': 'structurally_accepted_synthetic_envelope',
                   'authority': result.plan.needs[0].authority.model_dump(mode='json')}
    except NeedsValidationFailure as error:
        outcome = {'status': 'rejected', 'validation': error.validation}
    rows.append({'item': item, 'attempt': attempt['attempt'],
                 'source': str(source.relative_to(ROOT)),
                 'source_sha256': hashlib.sha256(source.read_bytes()).hexdigest(),
                 'synthetic_fields': synthetic,
                 'evaluated_at': 'synthetic fixed anchor, no relative dates in these plans',
                 'catalogue': 'empty synthetic catalogue: all retained bindings topic/empty',
                 'interpretation_check': 'not run; structural boundary only',
                 'before': plan['needs'][0]['authority'],
                 'after': canonical['needs'][0]['authority'],
                 'conversion_count': count, 'controlled_requests': len(calls), **outcome})
assert len(rows) == 5
(HERE / 'replay.json').write_text(json.dumps(rows, indent=2) + '\n')
print(json.dumps(rows, indent=2))
