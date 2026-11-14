import json
from pathlib import Path
from collections import Counter
P=Path(__file__).resolve().parent
selection=json.loads((P/'selection.json').read_text())
accounting=json.loads((P/'accounting.json').read_text())
items=[]; totals=Counter(); rejection_supplement=[]
for s in selection:
 r=json.loads((P/'cases'/f"{s['id']}.json").read_text())
 calls=Counter(c['path'] for c in r['service_calls']);totals.update(calls)
 discovery=[]
 for need,route in (r['routes_by_need'] or {}).items():
  sel=route.get('selection') or {};d=sel.get('diagnostics') or {}
  discovery.append({'need_id':need,'route':route['route'],'original_decision':sel.get('original_decision'),
   'effective_decision':sel.get('decision'),'validation_rejection_code':sel.get('validation_rejection_code'),
   'guard':route.get('temporal_guard'),'work':{k:v for k,v in d.items() if k!='completed_usage'}})
 failure=(r.get('usage') or {}).get('planner_failure')
 if failure:
  # Not counted in the frozen harness's null service-call receipt. Available
  # rejection receipts are accounted once here, never added to a parent total.
  assert not any(x.get('accounted_usd') for x in accounting['receipts'] if x['item']==s['id'] and x['path'].endswith('/plan-needs'))
  for i,a in enumerate(failure['validation_attempts'],1):
   inc=a['input_tokens'];out=a['output_tokens']
   rejection_supplement.append({'item':s['id'],'attempt':i,'input_tokens':inc,'output_tokens':out,
    'rate_basis':'retained gpt-5-mini .25 input / 2 output per million; cache unknown treated uncached','estimated_usd':(inc*.25+out*2)/1e6})
 items.append({'item':s['id'],'development':s['id'] in ('31','32'),'graded':s['id'] not in ('G2','G3'),
  'status':r['status'],'elapsed_ms':r['elapsed_ms'],'service_calls':dict(calls),
  'base_logical_lanes':r['base_logical_lanes'],'additional_search_lanes':r['additional_search_lanes'],
  'final_evidence_count':len(r['final_allocated_evidence']),'discovery':discovery,
  'targets':r['observed_temporal_targets'],'validated_used_target_ids':r['validated_used_target_ids'],
  'displayed_text':r['displayed_text']})
supp=sum(r['estimated_usd'] for r in rejection_supplement)
out={'all18_service_calls':dict(totals),'items':items,'original_dispatch_accounting_subtotal':accounting['known_and_retained_rate_subtotal_usd'],
 'post_run_rejected_planner_receipt_supplement':rejection_supplement,
 'known_and_retained_rate_subtotal_with_supplement':accounting['known_and_retained_rate_subtotal_usd']+supp,
 'reranking_conservative_allowance_usd':accounting['reranking_conservative_allowance_usd'],
 'unpriced_reserve_usd':5,'complete_invoice_total_available':False,
 'conservative_total_with_reserve':accounting['conservative_committed_usd']+supp,
 'cost_limit':50,'pre_dispatch_reservation':47,
 'accounting_note':'Nested checker and entailment receipts are already included in parent totals; not added twice. Qdrant/infrastructure costs and complete invoice pricing remain unavailable. Frozen dispatch accounting is preserved; G3 null service receipt is supplemented only from its retained rejection token receipts.'}
(P/'final-summary.json').write_text(json.dumps(out,indent=2)+'\n')
print(json.dumps({k:v for k,v in out.items() if k!='items'},indent=2))
