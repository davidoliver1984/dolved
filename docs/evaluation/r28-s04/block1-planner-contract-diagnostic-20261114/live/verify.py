"""Post-dispatch integrity and field-only validator replay; no providers or plan repair."""
import hashlib,json
from pathlib import Path
from pydantic import ValidationError
from app.retrieval.needs import FactReference
p=Path(__file__).resolve().parent
events=[json.loads(s) for s in (p/'dispatch.ndjson').read_text().splitlines()]
assert [e['item'] for e in events if e['event']=='dispatch_started']==['G2','G3']
assert [e['item'] for e in events if e['event']=='recorded']==['G2','G3']
rows=[];cost=0;tokens_in=tokens_out=0
for item in ('G2','G3'):
    r=json.loads((p/'cases'/f'{item}.json').read_text())
    q=json.loads((p/'inputs'/f'{item}.json').read_text())
    request=json.loads((p/'requests'/f'{item}.json').read_text())
    assert request['workspace_id']=='5323cd6b-8b7a-40f9-98b0-9413c75f643e'
    assert request['user_turns']==[{'turn_id':'user-1','text':q['utterance']}]
    previous='';seen=[]
    for line in (p/'cases'/f'{item}.ledger.ndjson').read_text().splitlines():
        entry=json.loads(line); h=entry.pop('hash')
        assert entry['previous_hash']==previous
        assert hashlib.sha256(json.dumps(entry,separators=(',',':'),ensure_ascii=False).encode()).hexdigest()==h
        previous=h;seen.append(entry['receipt'])
    assert len([x for x in seen if x['status']=='started'])==len(r['calls'])
    assert len([x for x in seen if x['status']=='received'])==len(r['calls'])
    assert all(c['kind']=='evidence_needs_plan' and c['http_status']==200 for c in r['calls'])
    assert len(r['attempts'])==2
    cost+=r['recorded_token_cost_estimate_usd']
    for c in r['calls']:
        tokens_in+=c['usage']['prompt_tokens'];tokens_out+=c['usage']['completion_tokens']
    for a in r['attempts']:
        ref=a['proposed_plan']['needs'][0]['fact_reference']
        actual={k:ref[k] for k in ('value','precision','source')} # exclude synthetic null kind from projection
        try: FactReference.model_validate(actual)
        except ValidationError as exc:
            errors=exc.errors(include_input=False,include_context=False,include_url=False)
            expected=all(e['type']=='value_error' and e['msg']=='Value error, fact reference date value does not match declared precision' for e in errors)
            assert expected
        else: raise AssertionError('Captured invalid calendar value unexpectedly accepted')
        rows.append({'item':item,'attempt':a['attempt'],'fact_reference':actual,'observed_validation':a['validation'],'field_only_replay_rejects_identically':True,'classification':'justified','classification_scope':'Frozen structural format rule only; not a full semantic verdict.'})
out={'run_id':'R28-PLANNER-CONTRACT-DIAGNOSTIC-20261114-01','once_per_question':True,'questions':2,'planner_attempts':4,'interpretation_check_calls':0,'downstream_calls':0,'hash_chains_valid':True,'provider_input_tokens':tokens_in,'provider_output_tokens':tokens_out,'retained_uncached_cost_estimate_usd':cost,'complete_invoice_available':False,'reservation_usd':3.25,'cap_usd':10,'observations':rows}
(p/'verification.json').write_text(json.dumps(out,indent=2)+'\n')
print(json.dumps(out,indent=2))
