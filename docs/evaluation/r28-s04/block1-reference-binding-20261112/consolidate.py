"""Offline consolidation of the fixed reference checkpoint; no providers."""
import hashlib
import json
from pathlib import Path

source = Path(__file__).parent
root = source.parent / 'r06-governed-evidence/R28-R07-BLOCK1-REFERENCE-BINDING-20261112-01'
manifest = json.loads((source/'execution-input.json').read_text())
cases, receipts, checks = {}, [], []
for path in sorted((root/'cases').glob('*.json')):
    raw = json.loads(path.read_text()); obs = raw['observation']
    trace, usage = obs.get('production_trace') or {}, obs.get('usage') or {}
    attempts = usage.get('planner_validation_attempts', (usage.get('planner_failure') or {}).get('validation_attempts', []))
    for index, attempt in enumerate(attempts, 1):
        receipts.append({'case_id':raw['case_id'], 'stage':'planner', 'model':'gpt-5-mini', **attempt})
        if attempt.get('interpretation_check'):
            checks.append({'case_id':raw['case_id'], 'attempt':index, **attempt['interpretation_check']})
    if isinstance(usage.get('generation'), dict): receipts.append({'case_id':raw['case_id'], **usage['generation']})
    for stage, value in trace.items():
        if isinstance(value, dict) and isinstance(value.get('usage'), list):
            receipts.extend({'case_id':raw['case_id'], 'path':stage, **r} for r in value['usage'])
    cases[raw['case_id']] = {'status':obs['status'], 'answer':raw['displayed_text'], 'plan':trace.get('needs_plan'),
        'raw_path':str(path.relative_to(root)), 'attempts':attempts, 'evidence':trace.get('needs_final_evidence'),
        'eligibility':{k:v for k,v in trace.items() if k.endswith('.eligibility')},
        'notice':'Location coverage not established' in raw['displayed_text'],
        'generation':obs.get('generation'), 'run_id':obs['run_id']}
inventory = [{'index':i, **item, **cases.get(item['case_id'], {'status':'not_dispatched'})}
             for i,item in enumerate(manifest['items'],1)]
priced=sum(r.get('cost_usd') or 0 for r in receipts); estimates=[]; missing=[]
for r in receipts:
    if r.get('cost_usd') is not None: continue
    if r.get('model')=='gpt-5-mini' and isinstance(r.get('input_tokens'),int) and isinstance(r.get('output_tokens'),int):
        estimates.append({'case_id':r['case_id'],'cost_usd':r['input_tokens']*.25/1e6+r['output_tokens']*2/1e6})
    else: missing.append({k:r.get(k) for k in ('case_id','stage','provider','model','input_tokens','request_count')})
accounting={'receipts':receipts,'checks':checks,'priced_usd':priced,'openai_estimates':estimates,
    'known_plus_estimated_usd':priced+sum(e['cost_usd'] for e in estimates),'unavailable':missing,
    'basis':'Retained repository GPT-5-mini 0.25/M input, 2/M output estimate, not invoice. Nested checker/entailment included in parents, not counted twice; unpriced reranking/infrastructure excluded.'}
snapshot=source.parent/'r07-evidence-units-v4-20261111/ranked-chunk-text-snapshot.json'
ids={e['chunk_id'] for c in inventory for e in c.get('evidence') or []}; chunks={}
def walk(v):
    if isinstance(v,dict):
        if v.get('chunk_id') in ids and 'text' in v: chunks[v['chunk_id']]=v
        for child in v.values(): walk(child)
    elif isinstance(v,list):
        for child in v: walk(child)
walk(json.loads(snapshot.read_text()))
for name,data in [('all-six.json',inventory),('all-checks-and-plans.json',checks),('accounting.json',accounting),
    ('source-excerpts.json',{'snapshot':str(snapshot),'sha256':hashlib.sha256(snapshot.read_bytes()).hexdigest(),
                           'chunks':list(chunks.values()),'not_in_snapshot':sorted(ids-chunks.keys())})]:
    (root/name).write_text(json.dumps(data,indent=2)+'\n')
print(json.dumps({'attempted':len(cases),'known_plus_estimated_usd':accounting['known_plus_estimated_usd']},indent=2))
