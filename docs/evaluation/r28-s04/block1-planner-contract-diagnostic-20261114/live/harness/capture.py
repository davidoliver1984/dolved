"""Evaluation-only httpx observer. No application monkeypatch or response mutation."""
import json
import re
import time
from datetime import datetime, timezone

WORKSPACE = '5323cd6b-8b7a-40f9-98b0-9413c75f643e'
SPAN = {'turn_id': None, 'quote': None}
TEMPORAL = {'kind': None, 'value': None, 'precision': None, 'source': SPAN}
NEED = {'need_id': None, 'aspect': None, 'question_span': SPAN, 'context_spans': [SPAN],
        'operation': None, 'authority': TEMPORAL, 'fact_reference': TEMPORAL,
        'applicability': {'kind': None, 'source': SPAN}}
BINDING = {'need_id': None, 'subject_status': None, 'family_ids': [None],
           'ownership': None, 'ownership_source': SPAN}
PLAN = {'schema_version': None, 'decision': None, 'needs': [NEED], 'bindings': [BINDING]}
EXPLANATIONS = {
    'fact_date_precision': 'FactReference.coherent_date: value format does not match declared calendar precision (or interval endpoints/reversal).',
    'temporal_relation_grounding': 'NeedAuthority.coherent_reference or EvidenceNeedsPlan.validate_grounding: period source starts before/after, or before/after authority lacks that relation in source and question span.',
}
def project(value, schema):
    if isinstance(schema, dict):
        if not isinstance(value, dict): return None
        return {k: project(value.get(k), s) for k, s in schema.items()}
    if isinstance(schema, list):
        return [project(x, schema[0]) for x in value[:20]] if isinstance(value,list) else None
    if isinstance(value, str):
        value = re.sub(r'(?i)(bearer\s+|(?:api[_-]?key|password|secret)\s*[:=]\s*)\S+', '[REDACTED]', value)
        return value[:8000]
    return value if value is None or isinstance(value,(bool,int,float)) else None

class Capture:
    def __init__(self, workspace, emit=lambda row: None):
        if workspace != WORKSPACE: raise ValueError('evaluation_workspace_denied')
        self.calls=[]; self.started={}; self.emit=emit
    def request(self, request):
        # Runaway guard, not an altered planner or retry policy.
        if len(request.content)>1_000_000: raise RuntimeError('diagnostic_input_budget')
        body=json.loads(request.content)
        kind=body.get('response_format',{}).get('json_schema',{}).get('name')
        if kind not in ('evidence_needs_plan','interpretation_check'): raise RuntimeError('diagnostic_route_denied')
        if body.get('model')!='gpt-5-mini': raise RuntimeError('diagnostic_model_drift')
        ceiling=25000 if kind=='evidence_needs_plan' else 4096
        if body.get('max_completion_tokens')!=ceiling: raise RuntimeError('diagnostic_limit_drift')
        if sum(c['kind']==kind for c in self.calls)>=2: raise RuntimeError('diagnostic_attempt_budget')
        row={'call':len(self.calls)+1,'kind':kind,'started_at':datetime.now(timezone.utc).isoformat(),
             'request_bytes':len(request.content),'max_completion_tokens':ceiling,'status':'started'}
        if kind=='evidence_needs_plan':
            user=json.loads(body['messages'][1]['content'])
            row['correction_feedback']=project(user.get('validation_feedback'),{'stage':None,'field':None,'code':None})
        self.calls.append(row); self.started[id(request)]=(row,time.perf_counter()); self.emit(dict(row))
    def response(self, response):
        row,start=self.started[id(response.request)]
        response.read()  # Same bytes remain available to the unchanged adapter.
        row.update(http_status=response.status_code,status='received',latency_ms=(time.perf_counter()-start)*1000,
                   ended_at=datetime.now(timezone.utc).isoformat(),parsed_plan=None,unavailable_reason=None)
        try:
            payload=response.json(); choice=payload.get('choices',[{}])[0]; usage=payload.get('usage') or {}
            row['usage']={k:usage.get(k) if isinstance(usage.get(k),int) else None for k in ('prompt_tokens','completion_tokens','total_tokens')}
            row['cost_estimate_usd']=(usage['prompt_tokens']*.25+usage['completion_tokens']*2)/1e6 if isinstance(usage.get('prompt_tokens'),int) and isinstance(usage.get('completion_tokens'),int) else None
            row['cost_basis']='retained_uncached_rate_estimate_not_invoice' if row['cost_estimate_usd'] is not None else 'unavailable'
            row['finish_reason']=choice.get('finish_reason') if choice.get('finish_reason') in ('stop','length','content_filter','tool_calls') else 'unknown'
            content=choice.get('message',{}).get('content')
            if row['kind']=='evidence_needs_plan' and isinstance(content,str) and len(content.encode())<=1_000_000:
                row['parsed_plan']=project(json.loads(content),PLAN)
            if row['parsed_plan'] is None: row['unavailable_reason']='not_a_planner_plan_or_unparseable'
        except (ValueError,KeyError,IndexError,TypeError,AttributeError):
            row['unavailable_reason']='unparseable_response'
        self.emit(dict(row))

def attempts(capture, receipts):
    plans=[c for c in capture.calls if c['kind']=='evidence_needs_plan']
    result=[]
    for index,call in enumerate(plans):
        receipt=receipts[index] if index<len(receipts) else {}
        validation=project(receipt.get('validation'),{'stage':None,'field':None,'code':None})
        code=(validation or {}).get('code')
        result.append({'attempt':index+1,'proposed_plan':call.get('parsed_plan'),
            'status':receipt.get('status','unavailable'),'validation':validation,
            'bounded_explanation':EXPLANATIONS.get(code,'No additional explanation retained; inspect closed code and frozen validator.'),
            'usage':{k:receipt.get(k) for k in ('input_tokens','output_tokens','request_count','retry_count','latency_ms')},
            'interpretation_check':project(receipt.get('interpretation_check'),{'code':None,'checker_code':None,'citation_verification':None,'verification_reason':None}),
            'unavailable_fields':'Absent fields are null; raw text, unknown keys and headers are never retained. Projection caps lists at 20 and strings at 8000 characters.'})
    return result
