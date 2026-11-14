import hashlib,json,sys
from pathlib import Path
p=Path('/workspace/docs/evaluation/r28-s04/block1-c1-live-20261114/continuation')
i=sys.argv[1]; r=json.loads((p/'cases'/f'{i}.json').read_text())
previous=''
for number,line in enumerate((p/'cases'/f'{i}-ledger.ndjson').read_text().splitlines(),1):
    event=json.loads(line)
    unhashed=line.rsplit(',"hash":',1)[0]+'}'
    assert event['sequence']==number and event['previous_hash']==previous, 'Ledger chain order mismatch'
    assert hashlib.sha256(unhashed.encode()).hexdigest()==event['hash'], 'Ledger event hash mismatch'
    previous=event['hash']
assert r['input']==json.loads((p/'inputs'/f'{i}.json').read_text()),'Input drift'
assert r['temporal_context']==r['reloaded_temporal_context'] or (not r['temporal_context'] and r['reloaded_temporal_context'] is None),'Persistence mismatch'
assert all(c['status']=='received' and c['http_status']==200 for c in r['service_calls']), 'Service transport/integrity failure'
if r['status']=='failed': raise RuntimeError(f"Production failure: {r['failure_code']}")
# No answer/content/route correctness gate.
print(json.dumps({'item':i,'status':r['status'],'service_calls':len(r['service_calls']),'precautionary_target_admitted':r['precautionary_target_admitted'],'answer_used_precautionary_target':r['answer_used_precautionary_target'],'grading':'not_performed'}))
