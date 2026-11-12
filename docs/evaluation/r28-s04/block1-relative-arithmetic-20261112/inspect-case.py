"""Read-only compact view of a retained checkpoint case; no providers."""
import json
import sys
from pathlib import Path

raw = json.loads(Path(sys.argv[1]).read_text())
obs = raw['observation']
trace = obs.get('production_trace') or {}
usage = obs.get('usage') or {}
print(json.dumps({
    'question': raw['utterance'], 'answer': raw['displayed_text'], 'status': obs['status'],
    'plan': trace.get('needs_plan'),
    'generation': obs.get('generation'),
    'evidence': trace.get('needs_final_evidence'),
    'eligibility': {k:v for k,v in trace.items() if k.endswith('.eligibility')},
    'attempts': usage.get('planner_validation_attempts', (usage.get('planner_failure') or {}).get('validation_attempts')),
}, indent=2))
