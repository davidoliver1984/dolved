"""34-item sequential gate. No inputs, authority, credentials or provider client here.

Phase B supplies a released questions file, its authorised hash and a fresh one-use
record. Existing dispatch.php is the authenticated production boundary.
"""
import hashlib
import json
import os
from collections import Counter
from pathlib import Path
from check_record import disposition

RUNTIME = 'b73d054d9492e909cfaeadb642544950cfe940de10bfcbe12b388b089997b901'
WORKSPACE = '5323cd6b-8b7a-40f9-98b0-9413c75f643e'

def validate_release(raw, authority, identities):
    if authority.get('phase_b_approved') is not True:
        raise ValueError('phase_b_not_authorised')
    for key in ('commit', 'harness_sha256', 'dependencies_sha256'):
        if not authority.get(key) or authority[key] != identities.get(key):
            raise ValueError('identity_mismatch:'+key)
    if identities.get('runtime') != RUNTIME or authority.get('runtime') != RUNTIME:
        raise ValueError('runtime_mismatch')
    if authority.get('workspace') != WORKSPACE:
        raise ValueError('workspace_mismatch')
    if hashlib.sha256(raw).hexdigest() != authority.get('inputs_sha256'):
        raise ValueError('input_hash_mismatch')
    items = json.loads(raw)
    if len(items) != 34 or Counter(i['group'] for i in items) != {'fresh':24,'control':4,'regression':6}:
        raise ValueError('selection_mismatch')
    if len({i['id'] for i in items}) != 34:
        raise ValueError('duplicate_id')
    for item in items:
        if set(item) != {'id','utterance','group'} or not all(isinstance(v,str) and v for v in item.values()):
            raise ValueError('not_question_only')
        if not all(c.isalnum() or c in '-_' for c in item['id']):
            raise ValueError('unsafe_id')
    return items

def exclusive_json(path, value):
    # Same exclusive-consumption / durable-ledger discipline as earlier runs.
    with Path(path).open('x') as stream:
        json.dump(value, stream, ensure_ascii=False)
        stream.flush()
        os.fsync(stream.fileno())

def execute(raw, authority, destination, verify, dispatch, budget_check):
    """Injected boundaries are frozen by Phase B. Tests use controlled fixtures only."""
    items = validate_release(raw, authority, verify())
    root = Path(destination)
    if not root.is_dir():
        raise ValueError('missing_evidence_destination')
    if any(root.glob('*.started.json')) or any(root.glob('*.record.json')):
        raise ValueError('prior_dispatch_present')
    budget_check()
    exclusive_json(root/'authorization-consumed.json', authority)
    for index, item in enumerate(items, 1):
        validate_release(raw, authority, verify())
        budget_check()  # Never reset attempts, reservation, spend or deadline.
        exclusive_json(root/(item['id']+'.started.json'), {'index':index,'id':item['id']})
        # Exceptions deliberately halt. The durable started marker prevents retry.
        record = dispatch({'id':item['id'],'utterance':item['utterance']})
        exclusive_json(root/(item['id']+'.record.json'), record)
        action, reason = disposition(record)
        exclusive_json(root/(item['id']+'.verdict.json'), {'action':action,'reason':reason,'group':item['group']})
        if action != 'continue':
            return {'stopped_at':index,'reason':reason}
    return {'completed':34}
