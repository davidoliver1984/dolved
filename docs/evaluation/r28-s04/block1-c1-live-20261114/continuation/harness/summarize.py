"""Describe recorded execution routes only. No expectations or answer grading."""
import json,hashlib
from pathlib import Path
p=Path('/workspace/docs/evaluation/r28-s04/block1-c1-live-20261114/continuation')
rows=[]
for i in ('F1','F2','F3','F4','F5'):
    path=p/'cases'/f'{i}.json'
    if not path.exists() or not path.stat().st_size: continue
    r=json.loads(path.read_text()); trace=r['production_trace']; plan=trace.get('needs_plan',{})
    request=trace.get('assembled_generation_request',{})
    rows.append({'item':i,'status':r['status'],'run_id':r['run_id'],'conversation_id':r['conversation_id'],
        'operations':[n.get('operation') for n in plan.get('needs',[])],
        'bindings':plan.get('bindings'),
        'observed_temporal_target_count':sum(len(v) for v in r['observed_temporal_targets'].values()) if isinstance(r['observed_temporal_targets'],dict) else 0,
        'precautionary_target_admitted':r['precautionary_target_admitted'],'admitted_target_ids':r['admitted_precautionary_target_ids'],
        'answer_used_precautionary_target':r['answer_used_precautionary_target'],
        'answer_used_requested_target':r['answer_used_requested_target'],
        'validated_used_target_ids':r['validated_used_target_ids'],
        'generation_request_contract':request.get('contract_version'),
        'base_logical_lanes':r['base_logical_lanes'],'additional_lanes':r['additional_search_lanes'],
        'observed_search_calls':len(r['lane_events']),
        'allocated_evidence_count':len(r['final_allocated_evidence']),
        'service_calls':len(r['service_calls']),
        'elapsed_seconds':r['elapsed_ms']/1000,
        'context_reload_equal':r['temporal_context']==r['reloaded_temporal_context'] if r['temporal_context'] else None,
        'context_reload_state':'equal' if r['temporal_context']==r['reloaded_temporal_context'] else ('not_applicable_no_temporal_context' if not r['temporal_context'] and r['reloaded_temporal_context'] is None else 'different'),
        'grader_verdict':None,'record_sha256':hashlib.sha256(path.read_bytes()).hexdigest()})
report={'schema_version':'c1-ungraded-route-summary-v1','items':rows,'completed_item_records':len(rows),'grading_performed':False,
 'limitations':['HTTP service calls are logged individually; physical provider attempt counts/usage are the existing nested service receipts, not a new direct-provider gateway.',
 'An observed route or successful persistence does not establish answer correctness.',
 'A false answer_used_requested_target flag means no requested temporal target was declared; for a non-C1 route inspect the actual plan, service calls and generation request.']}
(p/'route-summary.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(report,indent=2))
