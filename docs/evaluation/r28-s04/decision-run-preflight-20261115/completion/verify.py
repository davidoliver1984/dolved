"""Bounded read-only-source reconciliation; writes only this follow-up's evidence."""
import hashlib
import json
from pathlib import Path
from released_input import inventory

HERE=Path(__file__).resolve().parent
ROOT=Path('/workspace')
release=ROOT/'docs/evaluation/r28-s04/decision-run-20261115/released-input/questions-only.json'
(HERE/'released-inventory.json').write_text(json.dumps(inventory(release.read_bytes()),indent=2)+'\n')
rows=[]
for path in sorted((HERE.parent/'verification').glob('capture-*.json')):
    record=json.loads(path.read_text())
    displayed=record['displayed_text']; reloaded=record['reloaded_rendered_text']
    part_texts=[p['text'] for p in record['parts']]
    markers=['Location coverage not established','Temporal basis:','Recorded policy scope','Temporal evidence incomplete','Unconfirmed requested aspects']
    rows.append({'fixture':path.name,'evidence_sha256':hashlib.sha256(path.read_bytes()).hexdigest(),
        'backend_display_equals_reloaded':displayed==reloaded,
        'markers':{marker:{'saved_message':marker in displayed,'reloaded_answer':marker in reloaded,'raw_parts':any(marker in p for p in part_texts)} for marker in markers},
        'web_result':'code_traced_not_browser_exercised','unsupported_aspects':record['unsupported_aspects']})
(HERE/'rendering-fixtures.json').write_text(json.dumps(rows,indent=2)+'\n')
material=json.loads((ROOT/'docs/evaluation/r28-s03/run/materialisation-result.json').read_text())
corpus=json.loads((HERE/'corpus.json').read_text())
reconciled={}
for name,scope in corpus['scopes'].items():
    original=material['scopes'][name]
    ids=sorted({r['document_public_id'] for r in original['observations']})
    reconciled[name]={'original_manifest_sha256':original['manifest_sha256'],
        'expected_original_documents':original['expected_documents'],
        'original_document_identities_match':ids==scope['previous_document_public_ids'],
        'current_documents':scope['active_document_count'],
        'previous_chunks':scope['previous_count'],'current_chunks':scope['active_count'],
        'added_chunk_count':len(scope['added']),'removed_chunk_count':len(scope['removed'])}
    assert reconciled[name]['original_document_identities_match']
(HERE/'manifest-reconciliation.json').write_text(json.dumps(reconciled,indent=2)+'\n')
for line in (HERE.parent/'runtime-source.sha256').read_text().splitlines():
    digest,path=line.split('  ',1)
    assert hashlib.sha256((ROOT/path).read_bytes()).hexdigest()==digest,path
print(json.dumps({'runtime_verified':True,'release_verified':True,'retained_fixtures':len(rows),'provider_calls':0}))
