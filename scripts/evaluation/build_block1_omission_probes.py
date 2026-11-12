"""Freeze the four retained contract counterexamples, not new model outputs."""
import json
from copy import deepcopy
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
examples = json.loads((ROOT / 'contracts/http/retrieval-needs/v3/examples.json').read_text())
question = 'How often were highest-risk residents repositioned before January 2026, and what interval applies after the change?'
items = []
for attempt in (1, 2):
    for whole_span in (False, True):
        plan = deepcopy(examples[0]['plan'])
        need = deepcopy(plan['needs'][1])
        need.update(need_id='n1', supports_need_id=None,
                    aspect='What interval applies after the change?',
                    question_span={'turn_id':'u1','quote':question if whole_span else 'what interval applies after the change?'})
        plan.update(needs=[need], bindings=[{**plan['bindings'][1], 'need_id':'n1'}])
        items.append({'probe_id':f'attempt-{attempt}-whole-span-{str(whole_span).lower()}',
                      'user_turns':[{'turn_id':'u1','text':question}], 'plan':plan})
path = ROOT / 'docs/evaluation/r28-s04/block1-closing-20261112/omission-probes.json'
if path.exists():
    raise SystemExit('Frozen probes already exist; no overwrite')
path.write_text(json.dumps({'items':items}, indent=2) + '\n')
