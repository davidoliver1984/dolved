"""Post-dispatch analysis of retained sidecars; no providers or application writes."""
import hashlib,json,re
from pathlib import Path
from pydantic import ValidationError
from app.retrieval.needs import NeedAuthority, validate_date_grounding

P=Path(__file__).resolve().parent
cases={i:json.loads((P/'cases'/f'{i}.json').read_text()) for i in ('G3','17','25')}
requests={call['request_id']:i for i,r in cases.items() for call in r['service_calls']}
captures=[]
for file in (P/'capture').glob('*.json'):
    r=json.loads(file.read_text())
    assert r['workspace_id']=='5323cd6b-8b7a-40f9-98b0-9413c75f643e'
    assert r['request_id'] in requests
    captures.append({**r,'item':requests[r['request_id']],'file':str(file.relative_to(P)),'sha256':hashlib.sha256(file.read_bytes()).hexdigest()})
captures.sort(key=lambda r:r['captured_at'])
assert len(captures)==6 and sum(r['status']=='rejected' for r in captures)==5
analyses=[]
for r in captures:
    row={k:r[k] for k in ('item','attempt','status','file','sha256','validation','calendar_notation_normalization_count')}
    if r['status']=='accepted':
        row.update(classification='not_applicable_accepted', explanation='Input 17 changed period to before; accepted by the planner/checker, then genuine reviewer no_match led to no final evidence. Acceptance is not an answer-correctness verdict.')
    else:
        need=r['validator_input']['needs'][0]
        authority=need['authority']
        row.update(proposed_authority=authority,proposed_fact_reference=need.get('fact_reference'),
                   question_span=need['question_span'],context_spans=need.get('context_spans',[]),binding=r['validator_input'].get('bindings'),
                   proposal_equals_validator_input=r['proposed']==r['validator_input'])
        if r['item']=='G3':
            NeedAuthority.model_validate(authority)
            validate_date_grounding(authority['value'],authority['precision'],authority['source']['quote'],cases['G3']['evaluated_at'])
            text=authority['source']['quote']+' '+need['question_span']['quote']
            assert re.search(r'\b'+authority['kind']+r'\b',text,re.IGNORECASE) is None
            row.update(classification='mixed',structural_verdict='justified_under_existing_literal_relation_rule',
                code_path='apps/ai/app/retrieval/needs.py: EvidenceNeedsPlan.validate_grounding, before/after literal-word check (lines 443–452 at execution commit)',
                exact_condition="authority.kind in {'before','after'} and no word-boundary occurrence of that kind in authority.source.quote + question_span.quote",
                explanation="The date/value and source are structurally grounded, but neither checked span contains 'after'. The rejection follows the rule exactly. Semantically, 'once it started' can ask about the policy from activation; a missing literal 'after' is not proof of an ungrounded user intent. Conversely, after a named day has an end-exclusive adjacent-authority meaning, not necessarily the activation instant. This is a mixed contract/interpretation finding, not a claim the entire rejected plan was semantically correct.")
        else:
            try: NeedAuthority.model_validate(authority)
            except ValidationError as error:
                assert 'temporal relation is not grounded' in str(error)
            else: raise AssertionError('Expected real authority validator rejection')
            row.update(classification='justified',structural_verdict='rejected_by_authority_coherence',
                code_path='apps/ai/app/retrieval/needs.py: NeedAuthority.coherent_reference (lines 144–147 at execution commit)',
                exact_condition="kind == 'period' and re.match(r'\\s*(?:before|after)\\b', source.quote, re.IGNORECASE)",
                explanation="The proposal represents January 2026 itself (period/month/2026-01), but its source says 'before January 2026'. The period reading loses the explicitly requested before relation. Both the structural rejection and the meaning distinction are justified; no calendar-notation failure occurred.")
    analyses.append(row)
assert all(r['calendar_notation_normalization_count']==0 for r in captures)
reconciliation=[]
for item,case in cases.items():
    actual=[r for r in captures if r['item']==item]
    planner=next(c for c in case['service_calls'] if c['path'].endswith('/plan-needs'))
    expected=(planner.get('usage') or {}).get('validation_attempts') or (planner.get('planner_detail') or {}).get('validation_attempts')
    assert len(actual)==len(expected)==2
    assert [r['status'] for r in actual]==[r['status'] for r in expected]
    for a,e in zip(actual,expected):
        if a['status']=='rejected': assert a['validation']==e['validation']
    reconciliation.append({'item':item,'attempts_expected':len(expected),'sidecars':len(actual),'matching':True,
        'generation_requests':sum(c['path'].endswith('/answer-needs') for c in case['service_calls'])})
summary={'run_id':'R28-TEMPORAL-ASSOCIATION-DIAGNOSTIC-20261114-01','new_observations_only':True,
    'rejected_attempts':5,'classifications':{'justified':3,'mixed':2,'unjustified':0,'inconclusive':0},
    'accepted_attempts':1,'calendar_notation_conversions_observed':0,
    'generation_capture_live':'unexercised: no generation request; provider-free authenticated-route proof only',
    'reconciliation':reconciliation,'attempts':analyses,
    'items':[{'item':i,'status':r['status'],'displayed_text':r['displayed_text'],'routes':r['routes_by_need'],
              'start':r['started_at_london'],'end':r['finished_at_london']} for i,r in cases.items()]}
(P/'diagnostic-analysis.json').write_text(json.dumps(summary,indent=2)+'\n')

# Complete retained-rate accounting, including failed-planner receipts omitted
# from top-level service usage, without double-counting their nested attempts.
usage=[]
for item,r in cases.items():
    for c in r['service_calls']:
        u=c.get('usage')
        if isinstance(u,dict) and u.get('model')=='gpt-5-mini':
            usage.append({'item':item,'stage':u['stage'],'requests':u['request_count'],'input_tokens':u['input_tokens'],'output_tokens':u['output_tokens'],
                'estimated_usd':(u['input_tokens']*.25+u['output_tokens']*2)/1e6,'basis':'retained_uncached_rate_estimate','source':'service usage aggregate'})
        elif c['path'].endswith('/plan-needs'):
            for attempt in c['planner_detail']['validation_attempts']:
                usage.append({'item':item,'stage':'rejected_planner','requests':1,'input_tokens':attempt['input_tokens'],'output_tokens':attempt['output_tokens'],
                    'estimated_usd':(attempt['input_tokens']*.25+attempt['output_tokens']*2)/1e6,'basis':'retained_uncached_rate_estimate','source':'planner_detail.validation_attempts'})
        elif isinstance(u,list):
            for receipt in u:
                if receipt.get('execution')=='provider_api':
                    usage.append({'item':item,'stage':receipt['stage'],'requests':receipt['request_count'],'input_tokens':receipt['input_tokens'],'output_tokens':0,
                        'estimated_usd':receipt['cost_usd'],'basis':receipt['cost_basis'],'source':'service usage'})
        if c['path'].endswith('/rerank'):
            receipt=c['provider_receipt']
            usage.append({'item':item,'stage':'reranking','requests':receipt['provider_attempt_count'],'input_tokens':receipt['provider_input_tokens'],'output_tokens':0,
                'estimated_usd':None,'conservative_allowance_usd':receipt['provider_input_tokens']*.05/1e6,
                'basis':'retained allowance, provider price identity unavailable','source':'provider_receipt'})
accounting={'reservation_usd':12,'ceiling_usd':50,'provider_requests':sum(u['requests'] for u in usage),
    'recorded_input_tokens':sum(u['input_tokens'] for u in usage),'recorded_output_tokens':sum(u['output_tokens'] for u in usage),
    'known_and_retained_rate_estimate_usd':sum(u['estimated_usd'] or 0 for u in usage),
    'unpriced_reranker_allowance_usd':sum(u.get('conservative_allowance_usd',0) for u in usage),
    'invoice_total_available':False,'receipts':usage}
(P/'complete-accounting.json').write_text(json.dumps(accounting,indent=2)+'\n')
(P/'persistence-input.json').write_text(json.dumps({'items':[{'item':i,'run_id':r['run_id']} for i,r in cases.items()]})+'\n')
print(json.dumps({'classifications':summary['classifications'],'accounting':{k:v for k,v in accounting.items() if k!='receipts'}},indent=2))
