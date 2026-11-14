"""Retained-rate, non-grading accounting from service-call receipts; no providers."""
import json
from pathlib import Path
root=Path('/workspace/docs/evaluation/r28-s04/block1-c2-live-20261114')
rows=[]
for path in sorted((root/'cases').glob('*.json')):
    record=json.loads(path.read_text())
    if 'input' not in record: continue
    calls=list(record.get('service_calls',[]))
    # Supplemental rerank receipts are available only on production stage hooks.
    # Do not sum nested receipts with their parent aggregates.
    for stage,data in record.get('production_trace',{}).items():
        if stage.endswith('.reranked') and isinstance(data,dict):
            calls.append({'key':stage,'path':'observer:'+stage,'usage':data.get('usage')})
    for call in calls:
        usage=call.get('usage')
        entries=usage if isinstance(usage,list) else [usage]
        for index,u in enumerate(entries):
            if not isinstance(u,dict):
                rows.append({'item':record['input']['id'],'call_key':call['key'],'path':call['path'],'unavailable':True,'reason':'no usage receipt','accounted_usd':None})
                continue
            amount=u.get('cost_usd',u.get('estimated_cost_usd'))
            basis=u.get('cost_basis'); allowance=0
            if amount is None and u.get('model')=='gpt-5-mini' and isinstance(u.get('input_tokens'),int) and isinstance(u.get('output_tokens'),int):
                cached=u.get('cached_input_tokens') or 0
                amount=((u['input_tokens']-cached)*.25+cached*.025+u['output_tokens']*2)/1e6
                basis='retained_2026-08-19_rate_estimate_uncached_when_unknown'
            if amount is None and u.get('provider')=='voyage':
                allowance=(u.get('input_tokens') or 0)*max(1,u.get('provider_attempt_count') or u.get('request_count') or 1)*.05/1e6
            rows.append({'item':record['input']['id'],'call_key':call['key'],'path':call['path'],'receipt_index':index,'receipt':u,'accounted_usd':amount,'accounted_basis':basis,'conservative_reranker_allowance_usd':allowance})
known=sum(float(r['accounted_usd']) for r in rows if r['accounted_usd'] is not None)
allowance=sum(r.get('conservative_reranker_allowance_usd',0) for r in rows)
report={'cap_usd':1.5,'known_and_retained_rate_subtotal_usd':known,'reranking_conservative_allowance_usd':allowance,'unpriced_uncertainty_reserve_usd':.5,'next_item_headroom_required_usd':.5,'conservative_committed_usd':known+allowance+.5,'available_after_reserve_usd':1.5-known-allowance-.5,'next_dispatch_budget_gate':known+allowance+.5+.5<=1.5,'complete_invoice_total_available':False,'receipt_accounting_unit':'API-to-AI service call; provider attempts nested in receipts, not independently reconstructed','receipts':rows}
(root/'accounting.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps({k:v for k,v in report.items() if k!='receipts'}))
if not report['next_dispatch_budget_gate']: raise SystemExit(3)
