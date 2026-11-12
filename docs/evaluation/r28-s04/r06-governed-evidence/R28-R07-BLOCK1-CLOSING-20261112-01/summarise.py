"""Offline inventory/accounting only. Never dispatches, judges or alters raw cases."""
import json
from pathlib import Path

root = Path(__file__).parent
receipts, checks, cases = [], [], []
for path in sorted(root.glob('interpretation-probe-*.json')):
    probe = json.loads(path.read_text())
    receipt = probe['receipt']
    receipts.append({'case_id':probe['probe_id'], 'stage':'interpretation_probe', **receipt})
    checks.append({'case_id':probe['probe_id'], **{k:v for k,v in receipt.items() if k!='reviewed_plan'}})
for path in sorted((root/'cases').glob('*.json')):
    raw = json.loads(path.read_text())
    observation = raw['observation']
    trace = observation.get('production_trace') or {}
    usage = observation.get('usage') or {}
    attempts = usage.get('planner_validation_attempts', (usage.get('planner_failure') or {}).get('validation_attempts', []))
    for attempt in attempts:
        receipts.append({'case_id':raw['case_id'], 'stage':'planner', 'provider':'openai', 'model':'gpt-5-mini', **attempt})
        if attempt.get('interpretation_check'):
            checks.append({'case_id':raw['case_id'], **{k:v for k,v in attempt['interpretation_check'].items() if k!='reviewed_plan'}})
    if isinstance(usage.get('generation'), dict):
        receipts.append({'case_id':raw['case_id'], **usage['generation']})
    for stage, value in trace.items():
        if isinstance(value, dict) and isinstance(value.get('usage'), list):
            receipts.extend({'case_id':raw['case_id'], 'path':stage, **r} for r in value['usage'])
    cases.append({'case_id':raw['case_id'], 'path':str(path.relative_to(root)), 'utterance':raw['utterance'],
        'status':observation['status'], 'displayed_text':raw['displayed_text'],
        'plan':trace.get('needs_plan'), 'final_evidence':trace.get('needs_final_evidence'),
        'selection_coverage':trace.get('needs_selection_coverage'), 'planner_attempts':attempts})
priced = sum(r.get('cost_usd') or 0 for r in receipts)
estimates, missing = [], []
for r in receipts:
    if r.get('cost_usd') is not None:
        continue
    if r.get('model') == 'gpt-5-mini' and isinstance(r.get('input_tokens'), int) and isinstance(r.get('output_tokens'), int):
        estimates.append({'case_id':r['case_id'], 'stage':r['stage'],
            'cost_usd':r['input_tokens']*.25/1_000_000 + r['output_tokens']*2/1_000_000,
            'basis':'repository rate snapshot, no cache discount; estimate not billed receipt'})
    else:
        missing.append({k:r.get(k) for k in ('case_id','stage','provider','model','execution','input_tokens','request_count')})
accounting = {'receipts':receipts, 'known_priced_receipt_sum_usd':priced,
    'offline_missing_openai_price_estimates':estimates,
    'known_plus_offline_openai_estimate_usd':priced+sum(x['cost_usd'] for x in estimates),
    'still_unpriced':missing, 'check_overhead':checks,
    'physical_provider_requests':sum(r.get('request_count') or 0 for r in receipts if r.get('provider') not in ('qdrant',) and r.get('execution') not in ('infrastructure','local','not_executed')),
    'note':'Not a complete invoice. Parent planner receipts already include check tokens; generation includes entailment. Never add nested totals again. Unavailable Voyage/infrastructure pricing remains explicit.'}
(root/'accounting.json').write_text(json.dumps(accounting, indent=2)+'\n')
(root/'case-inventory.json').write_text(json.dumps(cases, indent=2)+'\n')
print(json.dumps({k:accounting[k] for k in ('known_priced_receipt_sum_usd','known_plus_offline_openai_estimate_usd','physical_provider_requests','still_unpriced')}, indent=2))
