"""Post-run reconciliation of existing receipts, not scoring or a new provider call."""
import json
from pathlib import Path
p=Path('/workspace/docs/evaluation/r28-s04/block1-c1-live-20261114/continuation')
a=json.loads((p/'accounting.json').read_text()); rows=list(a['receipts']); supplemental=[]
for path in sorted((p/'cases').glob('F*.json')):
    r=json.loads(path.read_text())
    # Rerank HTTP envelope is not projected by the generic response hook;
    # use its already recorded production-stage receipt, without changing execution.
    for stage,data in r['production_trace'].items():
        if not stage.endswith('.reranked') or not isinstance(data,dict): continue
        usage=data.get('usage')
        entries=usage if isinstance(usage,list) else [usage]
        for u in entries:
            if not isinstance(u,dict): continue
            amount=u.get('cost_usd'); allowance=0
            if amount is None and u.get('provider','').lower()=='voyage':
                allowance=(u.get('input_tokens') or 0)*max(1,u.get('provider_attempt_count') or u.get('request_count') or 1)*.05/1e6
            supplemental.append({'item':r['input']['id'],'source':stage,'receipt':u,'accounted_usd':amount,'accounted_basis':u.get('cost_basis'),'conservative_reranker_allowance_usd':allowance})
report={'dispatch_accounting':{k:v for k,v in a.items() if k!='receipts'},'supplemental_rerank_receipts':supplemental,
 'known_and_retained_rate_subtotal_usd':a['known_and_retained_rate_subtotal_usd']+sum(float(x['accounted_usd']) for x in supplemental if x['accounted_usd'] is not None),
 'reranking_conservative_allowance_usd':a['reranking_conservative_allowance_usd']+sum(x['conservative_reranker_allowance_usd'] for x in supplemental),
 'complete_invoice_total_available':False,
 'note':'Recorded-cost and retained-rate subtotal; missing HTTP receipt fields remain visible. Supplemental rerank receipts come from the production observer already captured during execution. They are not new calls. No nested receipt is summed again.'}
allrows=rows+supplemental
stage_rows=[]
for x in allrows:
    u=x.get('receipt') or {}
    stage_rows.append({'item':x['item'],'source':x.get('path',x.get('source')),'stage':u.get('stage'),'provider':u.get('provider'),'model':u.get('model'),'input_tokens':u.get('input_tokens'),'cached_input_tokens':u.get('cached_input_tokens'),'output_tokens':u.get('output_tokens'),'request_count':u.get('request_count'),'provider_attempt_count':u.get('provider_attempt_count'),'retry_count':u.get('retry_count'),'latency_ms':u.get('latency_ms'),'cost_usd':x.get('accounted_usd'),'cost_basis':x.get('accounted_basis')})
report['stage_receipts']=stage_rows
report['conservative_total_including_uncertainty_usd']=report['known_and_retained_rate_subtotal_usd']+report['reranking_conservative_allowance_usd']+.5
report['reported_provider_input_tokens']=sum(x['input_tokens'] or 0 for x in stage_rows if (x['provider'] or '').lower() in ('openai','voyage'))
report['reported_provider_output_tokens']=sum(x['output_tokens'] or 0 for x in stage_rows if (x['provider'] or '').lower() in ('openai','voyage'))
(p/'accounting-reconciled.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps({k:v for k,v in report.items() if k not in ('dispatch_accounting','supplemental_rerank_receipts','stage_receipts')},indent=2))
