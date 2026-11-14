"""Integrity and persistence only. Does not read expected answers or grade text."""
import hashlib,json
from pathlib import Path
p=Path('/workspace/docs/evaluation/r28-s04/block1-c2-live-20261114')
q=json.loads((p/'released-input/questions-only.json').read_text())
questions=q['fresh_items']+q['known_regression_items']
events=[json.loads(x) for x in (p/'dispatch.ndjson').read_text().splitlines()]
started=[x['item'] for x in events if x['event']=='dispatch_started']
recorded=[x['item'] for x in events if x['event']=='recorded']
assert started==[x['id'] for x in questions][:len(started)]
assert len(set(started))==len(started)
persist=json.loads((p/'persistence-verification.json').read_text())['items']
assert [x['item'] for x in persist]==started
runids=[]; conversations=[]; final_hashes={}
for q,v in zip(questions,persist):
    r=json.loads((p/'cases'/f"{q['id']}.json").read_text())
    assert r['input']==q and v['question']==q['utterance'] and r['run_id']==v['run_id']
    assert v['user_turns']==1 and v['generation_runs']==1
    assert v['temporal_context']==r['reloaded_temporal_context']
    assert v['reloaded_rendered_text']==r['reloaded_rendered_text']
    assert v['assistant_display_text']==r['displayed_text']
    runids.append(r['run_id']); conversations.append(v['conversation_id']); prev=''; n=0
    for n,line in enumerate((p/'cases'/f"{q['id']}-ledger.ndjson").read_text().splitlines(),1):
        e=json.loads(line)
        assert e['sequence']==n and e['previous_hash']==prev
        assert hashlib.sha256((line.rsplit(',"hash":',1)[0]+'}').encode()).hexdigest()==e['hash']
        prev=e['hash']
    assert n and e['event']=='item_finished'
    final_hashes[q['id']]=prev
assert len(set(runids))==len(started) and len(set(conversations))==len(started)
report={'dispatch_order':started,'recorded_order':recorded,'all_six_executed':len(started)==6,
    'once_only':True,'unique_persisted_conversations':len(conversations),'one_user_turn_and_run_each':True,
    'persistence_and_rendering_equal':True,'ledger_final_hashes':final_hashes,
    'input_sha256':hashlib.sha256((p/'released-input/questions-only.json').read_bytes()).hexdigest(),
    'grading_performed':False,'provider_calls_for_verification':0}
(p/'verification.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(report,indent=2))
