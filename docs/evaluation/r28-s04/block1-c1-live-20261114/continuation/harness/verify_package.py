import hashlib,json
from pathlib import Path
p=Path('/workspace/docs/evaluation/r28-s04/block1-c1-live-20261114/continuation')
questions=json.loads((p.parent/'released-input/questions-only.json').read_text())['items']
events=[json.loads(x) for x in (p/'dispatch.ndjson').read_text().splitlines()]
assert [x['item'] for x in events if x['event']=='dispatch_started']==['F1','F2','F3','F4','F5']
assert [x['item'] for x in events if x['event']=='recorded']==['F1','F2','F3','F4','F5']
persist=json.loads((p/'persistence-verification.json').read_text())['items']; assert len(persist)==5
runids=[];conv=[];nested=[]
keys=('stage','provider','model','input_tokens','cached_input_tokens','output_tokens','request_count','provider_attempt_count','retry_count','latency_ms','cost_usd','cost_basis','pricing_snapshot','verdict','outcome','code','status')
def walk(value,path,item):
    if isinstance(value,dict):
        if 'input_tokens' in value and ('output_tokens' in value or 'latency_ms' in value): nested.append({'item':item,'path':path,'receipt':{k:value[k] for k in keys if k in value},'not_additive':'may overlap parent aggregates'})
        for k,v in value.items(): walk(v,f'{path}.{k}',item)
    elif isinstance(value,list):
        for i,v in enumerate(value): walk(v,f'{path}[{i}]',item)
for i,q in enumerate(questions):
    r=json.loads((p/'cases'/f"{q['id']}.json").read_text()); v=persist[i]
    assert r['input']==q
    assert v['item']==q['id'] and v['question']==q['utterance'] and v['run_id']==r['run_id']
    assert v['user_turns']==1 and v['generation_runs']==1
    assert v['temporal_context']==r['reloaded_temporal_context']
    assert v['reloaded_rendered_text']==r['reloaded_rendered_text']
    assert v['assistant_display_text']==r['displayed_text']
    runids.append(r['run_id']);conv.append(v['conversation_id'])
    previous='';n=0
    for n,line in enumerate((p/'cases'/f"{q['id']}-ledger.ndjson").read_text().splitlines(),1):
        e=json.loads(line); assert e['sequence']==n and e['previous_hash']==previous
        assert hashlib.sha256((line.rsplit(',"hash":',1)[0]+'}').encode()).hexdigest()==e['hash']; previous=e['hash']
    assert n>0
    walk(r['usage'],'persisted_run_usage',q['id'])
    walk(r['generation_usage'],'persisted_generation_usage',q['id'])
assert len(set(runids))==5 and len(set(conv))==5
(p/'nested-stage-receipts.json').write_text(json.dumps({'note':'Component receipts for inspection; do not sum these with parent aggregates. No grading.', 'receipts':nested},indent=2)+'\n')
summary={'items':5,'dispatches_per_item':1,'new_conversations':5,'user_turns_per_conversation':1,'run_ids_unique':True,'all_ledgers_hash_chained':True,'persisted_reload_verified':True,'input_bytes_hash':hashlib.sha256((p.parent/'released-input/questions-only.json').read_bytes()).hexdigest(),'grading_performed':False,'provider_calls_in_verification':0}
(p/'verification.json').write_text(json.dumps(summary,indent=2)+'\n'); print(json.dumps(summary))
