"""Read-only-on-raw-records reconciliation. No grading, expectations or providers."""
import hashlib,json
from pathlib import Path
p=Path('/workspace/docs/evaluation/r28-s04/block1-c2-live-20261114')
ids=['G1','G2','G3','G4','G5','R16']; summaries=[]; persistence=[]; receipts=[]; nested=[]
def walk(v,path,item):
    if isinstance(v,dict):
        if 'input_tokens' in v and ('latency_ms' in v or 'output_tokens' in v):
            nested.append({'item':item,'path':path,'receipt':v,'not_additive':True})
        for k,x in v.items(): walk(x,path+'.'+k,item)
    elif isinstance(v,list):
        for i,x in enumerate(v): walk(x,path+f'[{i}]',item)
for item in ids:
    file=p/'cases'/f'{item}.json'
    if not file.exists() or not file.stat().st_size: continue
    r=json.loads(file.read_text()); plan=r['production_trace'].get('needs_plan') or {}
    request=r.get('generation_request') or {}
    targets=[t for n in request.get('evidence_needs',[]) for t in n.get('temporal_targets',[])]
    evidence=request.get('evidence',[])
    allocated={x for e in evidence for x in e.get('temporal_authority',{}).get('temporal_target_ids',[])}
    dated=[t['target_id'] for t in targets if t['mode']=='dated' and t['purpose']=='precautionary' and t['target_id'] in allocated]
    uses=(r.get('temporal_context') or {}).get('uses',[])
    summaries.append({'item':item,'group':'fresh_confirmation' if item in ids[:4] else ('partly_exposed_conflict_control' if item=='G5' else 'development_regression'),
        'status':r['status'],'failure_code':r['failure_code'],'run_id':r['run_id'],'routes_by_need':r['routes_by_need'],
        'operations':[n['operation'] for n in plan.get('needs',[])],
        'dated_alternatives_reached_generation':dated if request else None,
        'validated_temporal_uses':uses if r.get('reloaded_temporal_context') is not None else None,
        'base_logical_lanes':r['base_logical_lanes'],'additional_search_lanes':r['additional_search_lanes'],
        'actual_search_service_calls':sum(c['path'].endswith('/search') for c in r['service_calls']),
        'actual_review_service_calls':sum(c['path'].endswith('/compare-family') for c in r['service_calls']),
        'service_calls':len(r['service_calls']),'elapsed_ms':r['elapsed_ms'],'availability':r['availability'],
        'grading':None,'raw_sha256':hashlib.sha256(file.read_bytes()).hexdigest()})
    persistence.append({'item':item,'run_id':r['run_id']})
    calls=list(r['service_calls'])
    pf=(r.get('usage') or {}).get('planner_failure') or {}
    if pf.get('validation_attempts') and not any(c.get('usage') for c in calls if c['path'].endswith('/plan-needs')):
        calls.append({'key':'retained_planner_attempts','path':'persisted:planner_failure','usage':[dict(u,provider='openai',model='gpt-5-mini') for u in pf['validation_attempts']]})
    for c in calls:
        u=c.get('usage'); entries=u if isinstance(u,list) else [u]
        if c['path'].endswith('/rerank') and c.get('provider_receipt'):
            entries=[{'stage':'reranking','provider':'voyage','model':'rerank-2.5','cost_usd':None,'cost_basis':'unavailable','input_tokens':c['provider_receipt'].get('provider_input_tokens'),**c['provider_receipt']}]
        for u in entries:
            u=u if isinstance(u,dict) else {}
            amount=u.get('cost_usd',u.get('estimated_cost_usd')); basis=u.get('cost_basis'); allowance=0
            if amount is None and u.get('model')=='gpt-5-mini' and isinstance(u.get('input_tokens'),int) and isinstance(u.get('output_tokens'),int):
                cached=u.get('cached_input_tokens') or 0
                amount=((u['input_tokens']-cached)*.25+cached*.025+u['output_tokens']*2)/1e6
                basis='retained_2026-08-19_rate_estimate'
            if amount is None and u.get('provider')=='voyage': allowance=(u.get('input_tokens') or 0)*max(1,u.get('provider_attempt_count') or 1)*.05/1e6
            receipts.append({'item':item,'call':c['key'],'path':c['path'],'receipt':u or None,'accounted_usd':amount,'basis':basis,'allowance_usd':allowance})
    walk(r['usage'],'persisted_run_usage',item); walk(r['generation_usage'],'persisted_generation_usage',item)
known=sum(x['accounted_usd'] or 0 for x in receipts); allowance=sum(x['allowance_usd'] for x in receipts)
report={'run':'R28-C2-LIVE-20261114-01','grading_performed':False,'items':summaries,
    'accounting':{'recorded_and_retained_rate_subtotal_usd':known,'rerank_allowance_usd':allowance,'uncertainty_reserve_usd':.5,'conservative_total_usd':known+allowance+.5,'cap_usd':1.5,'invoice_total_available':False,'service_calls':sum(x['service_calls'] for x in summaries),'physical_provider_attempt_count':None,
    'reported_provider_input_tokens':sum((x['receipt'] or {}).get('input_tokens') or 0 for x in receipts if (x['receipt'] or {}).get('provider') in ('openai','voyage')),
    'reported_provider_output_tokens':sum((x['receipt'] or {}).get('output_tokens') or 0 for x in receipts if (x['receipt'] or {}).get('provider') in ('openai','voyage'))},
    'limitations':['Routes and validated metadata are execution observations, not semantic correctness grades.','Fallback is not successful C2 protection.','Service calls are not physical provider attempts; nested receipts may overlap parent aggregates.','Missing early-stage observations remain unavailable.','Retained-rate estimates and rerank allowances are not billed actuals.']}
(p/'route-summary.json').write_text(json.dumps(report,indent=2)+'\n')
(p/'accounting-reconciled.json').write_text(json.dumps({'summary':report['accounting'],'receipts':receipts,'nested_nonadditive_receipts':nested},indent=2)+'\n')
(p/'persistence-input.json').write_text(json.dumps({'items':persistence},indent=2)+'\n')
print(json.dumps({'items':[{'item':x['item'],'status':x['status'],'routes':{k:v['route'] for k,v in (x['routes_by_need'] or {}).items()},'dated':x['dated_alternatives_reached_generation'],'uses':None if x['validated_temporal_uses'] is None else len(x['validated_temporal_uses'])} for x in summaries],'accounting':report['accounting']},indent=2))
