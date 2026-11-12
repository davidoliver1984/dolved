"""Offline checkpoint inventory, source excerpts and accounting; no providers."""
import hashlib
import json
from collections import Counter
from pathlib import Path

source = Path(__file__).parent
root = source.parent / 'r06-governed-evidence/R28-R07-BLOCK1-RELATIVE-20261112-01'
inventory, receipts, checks = [], [], []
for path in sorted((root / 'cases').glob('*.json')):
    raw = json.loads(path.read_text())
    obs = raw['observation']
    trace = obs.get('production_trace') or {}
    usage = obs.get('usage') or {}
    attempts = usage.get('planner_validation_attempts', (usage.get('planner_failure') or {}).get('validation_attempts', []))
    for attempt in attempts:
        receipts.append({'case_id': raw['case_id'], 'stage': 'planner', 'provider': 'openai', 'model': 'gpt-5-mini', **attempt})
        if attempt.get('interpretation_check'):
            checks.append({'case_id': raw['case_id'], **{k:v for k,v in attempt['interpretation_check'].items() if k != 'reviewed_plan'}})
    if isinstance(usage.get('generation'), dict):
        receipts.append({'case_id': raw['case_id'], **usage['generation']})
    for stage, value in trace.items():
        if isinstance(value, dict) and isinstance(value.get('usage'), list):
            receipts.extend({'case_id': raw['case_id'], 'path': stage, **r} for r in value['usage'])
    inventory.append({'index': int(path.name.split('-')[0]), 'raw_path': str(path.relative_to(root)),
        'case_id': raw['case_id'], 'question': raw['utterance'], 'answer': raw['displayed_text'],
        'status': obs['status'], 'plan': trace.get('needs_plan'), 'run_id': obs['run_id'],
        'evidence': trace.get('needs_final_evidence'), 'retrieval': obs.get('retrieval'),
        'attempts': attempts, 'generation': obs.get('generation')})
priced = sum(r.get('cost_usd') or 0 for r in receipts)
estimates, unavailable = [], []
for r in receipts:
    if r.get('cost_usd') is not None:
        continue
    if r.get('model') == 'gpt-5-mini' and isinstance(r.get('input_tokens'), int) and isinstance(r.get('output_tokens'), int):
        estimates.append({'case_id': r['case_id'], 'stage': r['stage'],
            'cost_usd': r['input_tokens'] * .25 / 1_000_000 + r['output_tokens'] * 2 / 1_000_000,
            'basis': 'retained repository rate, estimate not invoice'})
    else:
        unavailable.append({k:r.get(k) for k in ('case_id', 'stage', 'provider', 'model', 'input_tokens', 'request_count')})
accounting = {'receipts': receipts, 'check_overhead': checks, 'known_priced_receipt_sum_usd': priced,
    'offline_openai_estimates': estimates, 'known_plus_estimated_usd': priced + sum(e['cost_usd'] for e in estimates),
    'unavailable': unavailable, 'note': 'Nested interpretation/entailment receipts are already included in parent totals; not counted twice. Unavailable rerank/infrastructure pricing prevents complete invoice claim.'}
snapshot = source.parent / 'r07-evidence-units-v4-20261111/ranked-chunk-text-snapshot.json'
ids = {e['chunk_id'] for c in inventory for e in c['evidence'] or []}
chunks = {}
def walk(v):
    if isinstance(v, dict):
        if v.get('chunk_id') in ids and 'text' in v:
            chunks[v['chunk_id']] = v
        for child in v.values():
            walk(child)
    elif isinstance(v, list):
        for child in v:
            walk(child)
walk(json.loads(snapshot.read_text()))
summary = {'status_counts': dict(Counter(c['status'] for c in inventory)),
    'interpretation_codes': dict(Counter(c['code'] for c in checks)),
    'interpretation_input_tokens': sum(c.get('input_tokens') or 0 for c in checks),
    'interpretation_output_tokens': sum(c.get('output_tokens') or 0 for c in checks),
    'interpretation_latency_ms': sum(c.get('latency_ms') or 0 for c in checks),
    'known_plus_estimated_usd': accounting['known_plus_estimated_usd']}
for name, data in [('all-six.json', inventory), ('accounting.json', accounting), ('summary.json', summary),
                   ('cited-source-excerpts.json', {'snapshot': str(snapshot), 'sha256': hashlib.sha256(snapshot.read_bytes()).hexdigest(), 'chunks': list(chunks.values()), 'not_in_snapshot': sorted(ids - chunks.keys())})]:
    (root / name).write_text(json.dumps(data, indent=2) + '\n')
print(json.dumps(summary, indent=2))
